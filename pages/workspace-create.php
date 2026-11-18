<section class="page-heading"><div><p class="eyebrow">COLLABORATION</p><h1>Buat Team Workspace</h1><p class="muted">Buat ruang bersama, lalu undang anggota melalui halaman Team Members.</p></div></section>
<form class="panel task-form workspace-create-form" method="post" action="index.php?page=workspace-create">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="workspace-create"><input type="hidden" name="return_page" value="workspace-create">
    <div class="form-section">
        <div class="form-field"><label for="workspace-name">Team name <span>*</span></label><input id="workspace-name" name="name" maxlength="120" required placeholder="Contoh: Product Team"></div>
        <div class="form-field"><label for="workspace-description">Description</label><textarea id="workspace-description" name="description" rows="4" maxlength="2000" placeholder="Tujuan workspace ini..."></textarea></div>
    </div>
    <div class="form-actions"><a class="button button-secondary" href="index.php?page=dashboard">Batal</a><button class="button button-primary" type="submit">Buat Team Workspace</button></div>
</form>
