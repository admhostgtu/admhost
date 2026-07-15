<?php
/**
 * Service de provisionnement sécurisé — whitelist, validation, exécution non-root.
 */

declare(strict_types=1);

namespace Backend\Services;

class ProvisioningService
{
    private const TIMEOUT_SECONDS = 60;

    /** @var array<string, string> */
    private array $allowedScripts;

    private string $scriptsDir;
    private string $logFile;
    private string $runAsUser;

    public function __construct()
    {
        $this->scriptsDir = realpath(dirname(__DIR__, 3) . '/scripts/provision') ?: '';
        $this->logFile    = dirname(__DIR__, 3) . '/storage/logs/provisioning.log';
        $this->runAsUser  = env('PROVISION_USER', 'provisioner');

        $this->allowedScripts = [
            'create_linux_user' => 'create_linux_user.sh',
            'create_ssh_access' => 'create_ssh_access.sh',
            'create_mailbox'    => 'create_mailbox.sh',
            'provision_full'    => 'provision_full.sh',
        ];
    }

    public function provisionFull(int $userId, int $serviceId, string $email): array
    {
        $this->validateEmail($email);
        $username = $this->generateUsername($email);

        return $this->runScript('provision_full', [
            'user_id'    => (string) $userId,
            'service_id' => (string) $serviceId,
            'username'   => $username,
            'email'      => $email,
        ]);
    }

    public function createLinuxUser(string $username, int $userId): array
    {
        $this->validateUsername($username);
        return $this->runScript('create_linux_user', [
            'username' => $username,
            'user_id'  => (string) $userId,
        ]);
    }

    public function createSshAccess(string $username): array
    {
        $this->validateUsername($username);
        return $this->runScript('create_ssh_access', ['username' => $username]);
    }

    public function createMailbox(string $email, string $username): array
    {
        $this->validateEmail($email);
        $this->validateUsername($username);
        return $this->runScript('create_mailbox', [
            'email'    => $email,
            'username' => $username,
        ]);
    }

    /**
     * Exécute un script de la whitelist — aucun chemin dynamique accepté.
     */
    private function runScript(string $scriptKey, array $params): array
    {
        if (!isset($this->allowedScripts[$scriptKey])) {
            $this->log('DENIED', "Script non autorisé: $scriptKey");
            return ['success' => false, 'error' => 'Script non autorisé'];
        }

        $scriptPath = $this->resolveScriptPath($this->allowedScripts[$scriptKey]);
        if ($scriptPath === null) {
            return ['success' => false, 'error' => 'Script introuvable ou chemin invalide'];
        }

        // Validation stricte de chaque paramètre
        $validatedParams = $this->validateParams($params);

        $args = [];
        foreach ($validatedParams as $key => $value) {
            $args[] = escapeshellarg($key . '=' . $value);
        }

        $bashCmd = 'bash ' . escapeshellarg($scriptPath) . ' ' . implode(' ', $args);
        $command = $this->wrapWithRunUser($bashCmd);

        $this->log('EXEC', $command);

        return $this->executeProcess($command, $scriptKey);
    }

    /**
     * Résout le chemin absolu et vérifie qu'il reste dans scriptsDir (anti path traversal).
     */
    private function resolveScriptPath(string $filename): ?string
    {
        if ($this->scriptsDir === '') {
            return null;
        }

        $path = realpath($this->scriptsDir . DIRECTORY_SEPARATOR . $filename);

        if ($path === false || !str_starts_with($path, $this->scriptsDir)) {
            $this->log('DENIED', "Path traversal bloqué: $filename");
            return null;
        }

        if (!is_executable($path) && PHP_OS_FAMILY !== 'Windows') {
            @chmod($path, 0750);
        }

        return $path;
    }

    /**
     * Exécute via proc_open avec timeout strict et lecture non-bloquante.
     */
    private function executeProcess(string $command, string $scriptKey): array
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptors, $pipes);

        if (!is_resource($process)) {
            $this->log('ERROR', 'proc_open échoué');
            return ['success' => false, 'error' => 'Impossible de lancer le processus'];
        }

        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $stdout = '';
        $stderr = '';
        $start  = time();

        while (true) {
            $stdout .= stream_get_contents($pipes[1]) ?: '';
            $stderr .= stream_get_contents($pipes[2]) ?: '';

            $status = proc_get_status($process);

            if (!$status['running']) {
                break;
            }

            if ((time() - $start) >= self::TIMEOUT_SECONDS) {
                proc_terminate($process, 9);
                fclose($pipes[1]);
                fclose($pipes[2]);
                proc_close($process);
                $this->log('TIMEOUT', "Script $scriptKey dépassé " . self::TIMEOUT_SECONDS . 's');
                return ['success' => false, 'error' => 'Timeout du script (' . self::TIMEOUT_SECONDS . 's)'];
            }

            usleep(100000);
        }

        $stdout .= stream_get_contents($pipes[1]) ?: '';
        $stderr .= stream_get_contents($pipes[2]) ?: '';
        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        $this->log('RESULT', "script=$scriptKey exit=$exitCode stdout=" . $this->redactForLog($stdout) . " stderr=" . trim($stderr));

        $result = json_decode(trim($stdout), true);

        if (!is_array($result)) {
            return [
                'success' => false,
                'error'   => $stderr ?: 'Sortie JSON invalide',
                'raw'     => $stdout,
            ];
        }

        $result['success'] = ($result['success'] ?? false) && $exitCode === 0;

        if ($result['success'] && isset($result['data'])) {
            // Credentials en clair ici — chiffrement effectué par Service::activate()
            $result['credentials'] = $this->mapCredentials($result['data']);
        }

        return $result;
    }

    /**
     * Exécute en tant qu'utilisateur non-root (provisioner) via runuser/sudo.
     */
    private function wrapWithRunUser(string $command): string
    {
        if (PHP_OS_FAMILY === 'Windows' || $this->runAsUser === '') {
            return $command;
        }

        if (env('APP_ENV', 'production') === 'production'
            && !$this->commandExists('runuser')
            && !$this->commandExists('sudo')) {
            throw new \RuntimeException('Provisioning indisponible : runuser/sudo requis en production.');
        }

        // runuser (util-linux) ou sudo -u
        if ($this->commandExists('runuser')) {
            return 'runuser -u ' . escapeshellarg($this->runAsUser) . ' -- ' . $command;
        }

        if ($this->commandExists('sudo')) {
            return 'sudo -u ' . escapeshellarg($this->runAsUser) . ' -n ' . $command;
        }

        if (env('APP_ENV', 'production') === 'production') {
            throw new \RuntimeException('Provisioning refusé en production sans runuser/sudo.');
        }

        $this->log('WARN', 'runuser/sudo indisponible — exécution directe (dev only)');
        return $command;
    }

    /** Masque mots de passe et secrets dans les logs. */
    private function redactForLog(string $output): string
    {
        $trimmed = trim($output);
        $data = json_decode($trimmed, true);

        if (is_array($data)) {
            $this->redactArray($data);
            return json_encode($data, JSON_UNESCAPED_UNICODE) ?: '[invalid json]';
        }

        return preg_replace(
            '/"(ssh_password|smtp_password|linux_password|password)"\s*:\s*"[^"]*"/i',
            '"$1":"[REDACTED]"',
            $trimmed
        ) ?? $trimmed;
    }

    private function redactArray(array &$data): void
    {
        foreach ($data as $key => &$value) {
            if (is_array($value)) {
                $this->redactArray($value);
            } elseif (is_string($key) && preg_match('/password|secret|token/i', $key)) {
                $value = '[REDACTED]';
            }
        }
    }

    private function validateParams(array $params): array
    {
        $validated = [];

        foreach ($params as $key => $value) {
            if (!preg_match('/^[a-z_]+$/', $key)) {
                throw new \InvalidArgumentException("Paramètre non autorisé: $key");
            }

            $value = (string) $value;

            match ($key) {
                'username' => $this->validateUsername($value),
                'email'    => $this->validateEmail($value),
                'user_id', 'service_id' => $this->validateNumericId($value),
                default    => null,
            };

            $validated[$key] = $value;
        }

        return $validated;
    }

    private function mapCredentials(array $data): array
    {
        return [
            'linux_username'  => $data['linux_username'] ?? null,
            'home_directory'  => $data['home_directory'] ?? null,
            'ssh_host'        => $data['ssh_host'] ?? env('SSH_HOST', 'localhost'),
            'ssh_port'        => (int) ($data['ssh_port'] ?? 22),
            'ssh_username'    => $data['ssh_username'] ?? $data['linux_username'] ?? null,
            'ssh_password'    => $data['ssh_password'] ?? null,
            'smtp_host'       => $data['smtp_host'] ?? env('SMTP_HOST', 'mail.localhost'),
            'smtp_port'       => (int) ($data['smtp_port'] ?? 587),
            'smtp_username'   => $data['smtp_username'] ?? $data['email'] ?? null,
            'smtp_password'   => $data['smtp_password'] ?? null,
            'smtp_encryption' => $data['smtp_encryption'] ?? 'tls',
        ];
    }

    private function generateUsername(string $email): string
    {
        $base = preg_replace('/[^a-z0-9]/', '', strtolower(explode('@', $email)[0]));
        return substr($base, 0, 16) . '_' . bin2hex(random_bytes(2));
    }

    private function validateUsername(string $username): void
    {
        if (!preg_match('/^[a-z][a-z0-9_]{2,31}$/', $username)) {
            throw new \InvalidArgumentException('Nom d\'utilisateur Linux invalide');
        }
    }

    private function validateEmail(string $email): void
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Email invalide');
        }
    }

    private function validateNumericId(string $value): void
    {
        if (!ctype_digit($value) || (int) $value <= 0) {
            throw new \InvalidArgumentException('ID numérique invalide');
        }
    }

    private function commandExists(string $cmd): bool
    {
        $which = PHP_OS_FAMILY === 'Windows' ? 'where' : 'command -v';
        exec("$which " . escapeshellarg($cmd) . ' 2>/dev/null', $out, $code);
        return $code === 0;
    }

    private function log(string $level, string $message): void
    {
        $line = sprintf("[%s] [%s] %s\n", date('Y-m-d H:i:s'), $level, $message);
        @file_put_contents($this->logFile, $line, FILE_APPEND | LOCK_EX);
    }
}
