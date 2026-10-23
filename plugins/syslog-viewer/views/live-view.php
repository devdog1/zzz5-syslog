<?php
/**
 * Live Syslog Viewer View Template
 */

if (!defined('APP_ROOT')) {
    exit('Direct access not permitted.');
}

$availableFiles = syslog_viewer_get_available_files();
$defaultMaxLines = get_plugin_setting('syslog-viewer', 'max_tail_lines', 300);
$refreshRateSec = get_plugin_setting('syslog-viewer', 'refresh_rate', 3);
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 text-white mb-1">
                <i class="fas fa-terminal text-success me-2"></i><?= __('Syslog Live Viewer', 'syslog-viewer'); ?>
            </h1>
            <p class="text-muted mb-0">Real-time log tailing, host filtering, and streaming analysis.</p>
        </div>
        <div>
            <a href="index.php?route=syslog_viewer_search" class="btn btn-outline-info me-2">
                <i class="fas fa-search me-1"></i> <?= __('Syslog Search', 'syslog-viewer'); ?>
            </a>
            <a href="index.php?route=syslog_viewer_settings" class="btn btn-outline-secondary">
                <i class="fas fa-cog me-1"></i> <?= __('Syslog Settings', 'syslog-viewer'); ?>
            </a>
        </div>
    </div>

    <div class="syslog-card">
        <div class="syslog-controls">
            <div class="row g-3 align-items-center">
                <!-- Log File Selection -->
                <div class="col-md-3">
                    <label class="form-label text-muted small mb-1"><?= __('Select Syslog File', 'syslog-viewer'); ?></label>
                    <select id="syslogFileSelect" class="form-select form-select-sm bg-dark text-white border-secondary">
                        <?php if (empty($availableFiles)): ?>
                            <option value="syslog">syslog (default)</option>
                        <?php else: ?>
                            <?php foreach ($availableFiles as $file): ?>
                                <option value="<?= e($file['filename']); ?>">
                                    <?= e($file['filename']); ?> (<?= $file['formatted_size']; ?>)
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>

                <!-- Host Filter -->
                <div class="col-md-3">
                    <label class="form-label text-muted small mb-1"><?= __('Filter by Host', 'syslog-viewer'); ?></label>
                    <select id="syslogHostFilter" class="form-select form-select-sm bg-dark text-white border-secondary">
                        <option value="all"><?= __('All Hosts', 'syslog-viewer'); ?></option>
                    </select>
                </div>

                <!-- Lines to Tail -->
                <div class="col-md-2">
                    <label class="form-label text-muted small mb-1"><?= __('Lines to Tail', 'syslog-viewer'); ?></label>
                    <select id="syslogLineCount" class="form-select form-select-sm bg-dark text-white border-secondary">
                        <option value="100" <?= $defaultMaxLines == 100 ? 'selected' : ''; ?>>100 lines</option>
                        <option value="300" <?= $defaultMaxLines == 300 ? 'selected' : ''; ?>>300 lines</option>
                        <option value="500" <?= $defaultMaxLines == 500 ? 'selected' : ''; ?>>500 lines</option>
                        <option value="1000" <?= $defaultMaxLines == 1000 ? 'selected' : ''; ?>>1000 lines</option>
                    </select>
                </div>

                <!-- Controls & Status -->
                <div class="col-md-4 text-md-end mt-md-4">
                    <button id="btnTogglePause" class="btn btn-sm btn-warning me-2">
                        <i class="fas fa-pause me-1"></i> <?= __('Pause Auto-Refresh', 'syslog-viewer'); ?>
                    </button>
                    <button id="btnClearLog" class="btn btn-sm btn-outline-danger">
                        <i class="fas fa-eraser me-1"></i> Clear View
                    </button>
                </div>
            </div>
        </div>

        <div class="syslog-card-header">
            <div class="d-flex align-items-center">
                <span id="syslogStatusDot" class="syslog-status-dot active"></span>
                <span id="syslogStatusText" class="fw-bold text-success small">LIVE AUTO-REFRESH</span>
            </div>
            <div id="logCounter" class="text-muted small">
                0 entries
            </div>
        </div>

        <div id="syslogContainer" class="syslog-table-container">
            <table class="syslog-table">
                <thead>
                    <tr>
                        <th style="width: 170px;"><?= __('Timestamp', 'syslog-viewer'); ?></th>
                        <th style="width: 140px;"><?= __('Host', 'syslog-viewer'); ?></th>
                        <th style="width: 150px;"><?= __('Process', 'syslog-viewer'); ?></th>
                        <th style="width: 110px;"><?= __('Severity', 'syslog-viewer'); ?></th>
                        <th><?= __('Message', 'syslog-viewer'); ?></th>
                    </tr>
                </thead>
                <tbody id="syslogTableBody">
                    <tr>
                        <td colspan="5" class="text-center text-muted p-4">
                            <i class="fas fa-spinner fa-spin me-2"></i> Initializing syslog connection...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof SyslogViewer !== 'undefined') {
        SyslogViewer.initLiveView({
            refreshRate: <?= (int)$refreshRateSec * 1000; ?>
        });
    }
});
</script>
