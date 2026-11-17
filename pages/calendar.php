<?php
$timezone = new DateTimeZone('Asia/Jakarta');
$today = new DateTimeImmutable('today', $timezone);
$requestedYear = $_GET['year'] ?? '';
$requestedMonth = $_GET['month'] ?? '';
$year = is_string($requestedYear) && ctype_digit($requestedYear) ? (int)$requestedYear : (int)$today->format('Y');
$month = is_string($requestedMonth) && ctype_digit($requestedMonth) ? (int)$requestedMonth : (int)$today->format('n');
if ($year < 1000 || $year > 9999 || $month < 1 || $month > 12) {
    $year = (int)$today->format('Y');
    $month = (int)$today->format('n');
}

$calendarMonth = new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month), $timezone);
$monthStart = $calendarMonth->format('Y-m-d');
$monthEnd = $calendarMonth->modify('last day of this month')->format('Y-m-d');
$taskStmt = $pdo->prepare('SELECT id, title, status, priority, deadline FROM tasks WHERE archived_at IS NULL AND deadline BETWEEN ? AND ? ORDER BY deadline ASC, title ASC');
$taskStmt->execute([$monthStart, $monthEnd]);
$tasksByDate = [];
foreach ($taskStmt->fetchAll() as $task) {
    $tasksByDate[$task['deadline']][] = $task;
}

$requestedDay = $_GET['day'] ?? '';
$validSelectedDay = false;
if (is_string($requestedDay) && preg_match('/^\d{4}-\d{2}-\d{2}$/D', $requestedDay)) {
    [$selectedYear, $selectedMonth, $selectedDayNumber] = array_map('intval', explode('-', $requestedDay));
    $validSelectedDay = checkdate($selectedMonth, $selectedDayNumber, $selectedYear)
        && $selectedYear === $year
        && $selectedMonth === $month;
}
$selectedDate = $validSelectedDay
    ? new DateTimeImmutable($requestedDay, $timezone)
    : ($calendarMonth->format('Y-m') === $today->format('Y-m') ? $today : $calendarMonth);
$selectedDateKey = $selectedDate->format('Y-m-d');
$selectedTasks = $tasksByDate[$selectedDateKey] ?? [];
$monthNames = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
$weekdays = ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'];
$calendarUrl = static function (DateTimeImmutable $date, ?string $day = null): string {
    $query = ['page' => 'calendar', 'year' => $date->format('Y'), 'month' => $date->format('n')];
    if ($day !== null) {
        $query['day'] = $day;
    }
    return 'index.php?' . http_build_query($query);
};
$previousMonth = $calendarMonth->modify('-1 month');
$nextMonth = $calendarMonth->modify('+1 month');
$canGoPrevious = (int)$previousMonth->format('Y') >= 1000;
$canGoNext = (int)$nextMonth->format('Y') <= 9999;
$leadingDays = (int)$calendarMonth->format('N') - 1;
$daysInMonth = (int)$calendarMonth->format('t');
$cellCount = (int)(ceil(($leadingDays + $daysInMonth) / 7) * 7);
$addTaskUrl = 'index.php?' . http_build_query(['page' => 'task-create', 'deadline' => $selectedDateKey]);
$selectedDateLabel = $weekdays[(int)$selectedDate->format('N') - 1] . ', '
    . $selectedDate->format('j') . ' ' . $monthNames[(int)$selectedDate->format('n') - 1] . ' ' . $selectedDate->format('Y');
?>
<section class="page-heading calendar-page-heading">
    <div><p class="eyebrow">PLANNING</p><h1>Calendar</h1><p class="muted">Lihat dan atur tugas berdasarkan deadline.</p></div>
    <a class="button button-primary" href="<?= e($addTaskUrl) ?>"><i class="bi bi-plus-lg"></i> Add New Task</a>
</section>

<section class="panel calendar-panel" aria-label="Kalender bulanan">
    <div class="calendar-toolbar">
        <div><p class="eyebrow">MONTH VIEW</p><h2><?= e($monthNames[$month - 1]) ?> <?= $year ?></h2></div>
        <nav class="calendar-navigation" aria-label="Navigasi kalender">
            <?php if ($canGoPrevious): ?><a class="icon-button" href="<?= e($calendarUrl($previousMonth)) ?>" aria-label="Previous month" title="Previous month"><i class="bi bi-chevron-left"></i></a><?php else: ?><span class="icon-button calendar-nav-disabled" aria-disabled="true"><i class="bi bi-chevron-left"></i></span><?php endif; ?>
            <a class="button button-secondary button-small" href="<?= e($calendarUrl($today, $today->format('Y-m-d'))) ?>">Today</a>
            <?php if ($canGoNext): ?><a class="icon-button" href="<?= e($calendarUrl($nextMonth)) ?>" aria-label="Next month" title="Next month"><i class="bi bi-chevron-right"></i></a><?php else: ?><span class="icon-button calendar-nav-disabled" aria-disabled="true"><i class="bi bi-chevron-right"></i></span><?php endif; ?>
        </nav>
    </div>
    <div class="calendar-grid">
        <?php foreach ($weekdays as $weekday): ?><div class="calendar-weekday"><?= e($weekday) ?></div><?php endforeach; ?>
        <?php for ($cell = 0; $cell < $cellCount; $cell++): $dayNumber = $cell - $leadingDays + 1; ?>
            <?php if ($dayNumber < 1 || $dayNumber > $daysInMonth): ?><div class="calendar-day is-empty" aria-hidden="true"></div>
            <?php else:
                $date = $calendarMonth->setDate($year, $month, $dayNumber);
                $dateKey = $date->format('Y-m-d');
                $dayTasks = $tasksByDate[$dateKey] ?? [];
                $isToday = $dateKey === $today->format('Y-m-d');
                $isSelected = $dateKey === $selectedDateKey;
                $dateClasses = 'calendar-day' . ($isToday ? ' is-today' : '') . ($isSelected ? ' is-selected' : '');
            ?>
                <article class="<?= e($dateClasses) ?>">
                    <div class="calendar-day-header"><a class="calendar-day-number" href="<?= e($calendarUrl($calendarMonth, $dateKey) . '#selected-day') ?>"<?= $isToday ? ' aria-current="date"' : '' ?> aria-label="<?= e($date->format('j') . ' ' . $monthNames[$month - 1] . ' ' . $year) ?>"><?= $dayNumber ?></a></div>
                    <div class="calendar-day-tasks">
                        <?php foreach (array_slice($dayTasks, 0, 2) as $task):
                            $statusClass = match ($task['status']) {
                                'Completed' => 'is-completed',
                                'In Progress' => 'is-progress',
                                default => 'is-pending',
                            };
                        ?>
                            <a class="calendar-task <?= e($statusClass) ?>" href="index.php?page=task-detail&amp;id=<?= (int)$task['id'] ?>" title="<?= e($task['title'] . ' · ' . $task['status'] . ' · ' . $task['priority']) ?>"><span><?= e($task['title']) ?></span></a>
                        <?php endforeach; ?>
                    </div>
                    <?php if (count($dayTasks) > 2): ?><a class="calendar-more" href="<?= e($calendarUrl($calendarMonth, $dateKey) . '#selected-day') ?>">+<?= count($dayTasks) - 2 ?> more</a><?php endif; ?>
                </article>
            <?php endif; ?>
        <?php endfor; ?>
    </div>
</section>

<section class="panel calendar-selected-panel" id="selected-day">
    <div class="panel-heading calendar-selected-heading">
        <div><h2><?= e($selectedDateLabel) ?></h2><p class="muted"><?= count($selectedTasks) ?> tugas dengan deadline pada tanggal ini</p></div>
        <a class="button button-secondary button-small" href="<?= e($addTaskUrl) ?>"><i class="bi bi-plus-lg"></i> Tambah tugas tanggal ini</a>
    </div>
    <?php if (!$selectedTasks): ?><div class="compact-empty">Tidak ada tugas pada tanggal ini.</div>
    <?php else: ?><div class="calendar-selected-list"><?php foreach ($selectedTasks as $task):
        $statusClass = match ($task['status']) {
            'Completed' => 'is-completed',
            'In Progress' => 'is-progress',
            default => 'is-pending',
        };
    ?>
        <a class="calendar-selected-task" href="index.php?page=task-detail&amp;id=<?= (int)$task['id'] ?>">
            <span class="calendar-selected-indicator <?= e($statusClass) ?>"></span>
            <span class="calendar-selected-main"><strong><?= e($task['title']) ?></strong><small><?= e($task['priority']) ?> priority</small></span>
            <span class="status-pill <?= e(status_class($task['status'])) ?>"><?= e($task['status']) ?></span>
        </a>
    <?php endforeach; ?></div><?php endif; ?>
</section>
