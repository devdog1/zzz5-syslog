<?php
/**
 * Dashboard Widget View Template for Syslog Viewer
 */

if (!defined('APP_ROOT')) {
    exit('Direct access not permitted.');
}

$availableFiles = syslog_viewer_get_available_files();
$fileCount = count($availableFiles);
$defaultFile = $fileCount > 0 ? $availableFiles[0]['filename'] : 'syslog';
$filePath = syslog_viewer_sanitize_path($defaultFile);

$tailData = syslog_viewer_tail($filePath, 5);
$recentRows = [];

if (!empty($tailData['lines'])) {
    foreach ($tailData['lines'] as $line) {
        $recentRows[] = syslog_viewer_parse_line($line);
    }
}
?>

<div class="card bg-dark text-white border-secondary h-100 shadow-sm">
    <div class="card-header border-secondary d-flex justify-content-between align-items-center">
        <span class="fw-bold">
            <i class="fas fa-terminal text-success me-2"></i> Syslog Viewer Summary
        </span>
        <span class="badge bg-secondary"><?= $fileCount; ?> Log Files</span>
    </div>

    <div class="card-body p-3">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <span class="text-muted small">Active Log File:</span>
                <div class="fw-bold text-info"><?= e($defaultFile); ?></div>
            </div>
            <div>
                <a href="index.php?route=syslog_viewer_live" class="btn btn-sm btn-outline-success me-1">
                    <i class="fas fa-play"></i> Live
                </a>
                <a href="index.php?route=syslog_viewer_search" class="btn btn-sm btn-outline-info">
                    <i class="fas fa-search"></i> Search
                </a>
            </div>
        </div>

        <h6 class="text-muted small mb-2 fw-bold uppercase">Latest Recent Entries:</h6>
        <?php if (empty($recentRows)): ?>
            <div class="text-muted small text-center py-2">No syslog entries available.</div>
        <?php else: ?>
            <div class="list-group list-group-flush border-0">
                <?php foreach ($recentRows as $row): ?>
                    <div class="list-group-item bg-dark border-secondary text-white p-2 small">
                        <div class="d-flex justify-content-between">
                            <span class="badge bg-secondary me-1"><?= e($row['host']); ?></span>
                            <span class="badge severity-<?= e($row['severity']); ?>"><?= e($row['severity']); ?></span>
                        </div>
                        <div class="text-truncate mt-1 text-muted" style="max-width: 100%;">
                            <?= e($row['message']); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
