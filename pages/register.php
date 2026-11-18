<?php
$authError = $_SESSION['auth_error'] ?? '';
unset($_SESSION['auth_error']);
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Register · TaskFlow</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
<main class="auth-shell"><form class="panel auth-card" method="post" action="index.php?page=register">
    <a class="brand auth-brand" href="index.php"><span class="brand-mark"><img src="assets/image/logo%20TaskFlow.png" alt=""></span><span>TaskFlow</span></a>
    <p class="eyebrow">GET STARTED</p><h1>Buat akun</h1><p class="muted">Akunmu akan memiliki Personal Workspace privat.</p>
    <?php if ($authError !== ''): ?><div class="auth-error" role="alert"><?= e($authError) ?></div><?php endif; ?>
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <div class="form-field"><label for="name">Nama</label><input id="name" name="name" maxlength="120" autocomplete="name" required></div>
    <div class="form-field"><label for="email">Email</label><input id="email" name="email" type="email" maxlength="190" autocomplete="email" required></div>
    <div class="form-field"><label for="password">Password</label><input id="password" name="password" type="password" minlength="8" autocomplete="new-password" required><small>Gunakan minimal 8 karakter.</small></div>
    <button class="button button-primary auth-submit" type="submit">Daftar</button>
    <p class="auth-switch">Sudah punya akun? <a href="index.php?page=login">Login</a></p>
</form></main>
</body>
</html>
