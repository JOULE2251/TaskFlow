<section class="page-heading"><div><p class="eyebrow">ACCOUNT</p><h1>Profile</h1><p class="muted">Kelola informasi akun dan keamanan password.</p></div></section>
<div class="profile-layout">
    <section class="panel profile-panel">
        <div class="panel-heading"><div><h2>Informasi profil</h2><p class="muted">Email digunakan untuk login ke TaskFlow.</p></div></div>
        <form method="post" action="index.php?page=profile">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="profile-update"><input type="hidden" name="return_page" value="profile">
            <div class="form-field"><label for="profile-name">Nama</label><input id="profile-name" name="name" maxlength="120" required value="<?= e($currentUser['name']) ?>"></div>
            <div class="form-field"><label for="profile-email">Email</label><input id="profile-email" name="email" type="email" maxlength="190" required value="<?= e($currentUser['email']) ?>"></div>
            <div class="form-actions"><button class="button button-primary" type="submit">Simpan profil</button></div>
        </form>
    </section>
    <section class="panel profile-panel">
        <div class="panel-heading"><div><h2>Ganti password</h2><p class="muted">Gunakan password minimal 8 karakter.</p></div></div>
        <form method="post" action="index.php?page=profile">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="password-change"><input type="hidden" name="return_page" value="profile">
            <div class="form-field"><label for="current-password">Password saat ini</label><input id="current-password" name="current_password" type="password" autocomplete="current-password" required></div>
            <div class="form-field"><label for="new-password">Password baru</label><input id="new-password" name="new_password" type="password" minlength="8" autocomplete="new-password" required></div>
            <div class="form-actions"><button class="button button-secondary" type="submit">Ganti password</button></div>
        </form>
    </section>
</div>
