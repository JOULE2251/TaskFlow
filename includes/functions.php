<?php
declare(strict_types=1);

function task_title(mixed $value): string
{
    $title = trim((string)$value);
    if ($title === '' || mb_strlen($title) > 160) {
        throw new InvalidArgumentException('Judul wajib diisi dan maksimal 160 karakter.');
    }
    return $title;
}

function task_description(mixed $value): string
{
    $description = trim((string)$value);
    if (mb_strlen($description) > 5000) {
        throw new InvalidArgumentException('Deskripsi maksimal 5.000 karakter.');
    }
    return $description;
}

function validate_priority(mixed $value): string
{
    $allowed = ['Low', 'Medium', 'High', 'Urgent'];
    if (!in_array($value, $allowed, true)) {
        throw new InvalidArgumentException('Prioritas tidak valid.');
    }
    return $value;
}

function validate_deadline(mixed $value): ?string
{
    $value = trim((string)$value);
    if ($value === '') {
        return null;
    }
    $date = DateTime::createFromFormat('Y-m-d', $value);
    if (!$date || $date->format('Y-m-d') !== $value) {
        throw new InvalidArgumentException('Tanggal deadline tidak valid.');
    }
    return $value;
}

function validate_category(PDO $pdo, mixed $value): ?int
{
    if ($value === '' || $value === null) {
        return null;
    }
    $id = valid_id($value);
    $stmt = $pdo->prepare('SELECT id FROM categories WHERE id = ? AND workspace_id = ?');
    $stmt->execute([$id, active_workspace_id()]);
    if (!$stmt->fetch()) {
        throw new InvalidArgumentException('Kategori tidak ditemukan.');
    }
    return $id;
}

function validate_project(PDO $pdo, mixed $value): ?int
{
    if ($value === '' || $value === null) {
        return null;
    }
    $id = valid_id($value);
    $stmt = $pdo->prepare('SELECT id FROM projects WHERE id = ? AND workspace_id = ?');
    $stmt->execute([$id, active_workspace_id()]);
    if (!$stmt->fetch()) {
        throw new InvalidArgumentException('Proyek tidak ditemukan.');
    }
    return $id;
}

function checklist_input(mixed $values): array
{
    if (!is_array($values)) {
        return [];
    }
    $result = [];
    foreach (array_slice($values, 0, 50) as $value) {
        $title = trim((string)$value);
        if ($title === '') {
            continue;
        }
        if (mb_strlen($title) > 240) {
            throw new InvalidArgumentException('Nama checklist maksimal 240 karakter.');
        }
        $result[] = $title;
    }
    return $result;
}
