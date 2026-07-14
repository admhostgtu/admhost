<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title ?? 'Admin') ?> — Admin</title>
    <link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body class="admin-body">
    <?php if (!empty($admin)): ?>
        <?php include __DIR__ . '/../partials/sidebar.php'; ?>
    <?php endif; ?>

    <div class="admin-content">
        <?php if (!empty($admin)): ?>
            <?php include __DIR__ . '/../partials/topbar.php'; ?>
        <?php endif; ?>

        <main class="admin-main">
            <?= $content ?? '' ?>
        </main>
    </div>

    <script src="/assets/js/admin.js"></script>
</body>
</html>
