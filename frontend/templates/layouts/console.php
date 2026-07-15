<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#14100d">
    <title><?= e($title ?? 'Mon espace') ?> — <?= e(env('APP_NAME', 'AdmHost')) ?></title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="console-body">
    <?php include __DIR__ . '/../partials/console-nav.php'; ?>
    <div class="console-main">
        <?php if (!empty($_GET['checkout']) && $_GET['checkout'] === 'success'): ?>
            <div class="alert-success">Paiement réussi. Votre service sera provisionné sous peu.</div>
        <?php endif; ?>
        <?= $content ?? '' ?>
    </div>
    <script src="/assets/js/app.js"></script>
</body>
</html>
