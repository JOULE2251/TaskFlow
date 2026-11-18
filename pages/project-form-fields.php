<?php
$projectForm = $project ?? [
    'name' => '',
    'description' => '',
    'status' => 'Planning',
    'start_date' => '',
    'end_date' => '',
];
?>
<div class="form-section project-form-fields">
    <div class="form-field"><label for="project-name">Nama proyek <span>*</span></label><input id="project-name" name="name" maxlength="160" required value="<?= e($projectForm['name']) ?>" placeholder="Contoh: Website Revamp"></div>
    <div class="form-field"><label for="project-description">Deskripsi</label><textarea id="project-description" name="description" rows="4" maxlength="5000" placeholder="Tujuan dan ruang lingkup proyek..."><?= e($projectForm['description']) ?></textarea></div>
    <div class="form-grid">
        <div class="form-field"><label for="project-status">Status</label><select id="project-status" name="status"><?php foreach (['Planning', 'In Progress', 'Completed', 'Archived'] as $status): ?><option<?= $projectForm['status'] === $status ? ' selected' : '' ?>><?= e($status) ?></option><?php endforeach; ?></select></div>
        <div class="form-field"><label for="project-start">Tanggal mulai</label><input id="project-start" name="start_date" type="date" value="<?= e($projectForm['start_date']) ?>"></div>
        <div class="form-field"><label for="project-end">Tanggal selesai</label><input id="project-end" name="end_date" type="date" value="<?= e($projectForm['end_date']) ?>"></div>
    </div>
</div>
<div class="form-actions"><button class="button button-secondary" type="button" data-project-modal-close>Batal</button><button class="button button-primary" type="submit"><i class="bi bi-check2"></i> Simpan proyek</button></div>
