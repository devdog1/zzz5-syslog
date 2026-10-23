<?php
/**
 * Syslog Settings View Template
 */

if (!defined('APP_ROOT')) {
    exit('Direct access not permitted.');
}

$successMsg = null;
$errorMsg = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (function_exists('validate_csrf')) {
        validate_csrf();
    } elseif (function_exists('csrf_verify')) {
        csrf_verify();
    }

    $logDir = isset($_POST['log_directory']) ? trim($_POST['log_directory']) : '/var/log/syslog/';
    $maxTail = isset($_POST['max_tail_lines']) ? intval($_POST['max_tail_lines']) : 300;
    $refreshRate = isset($_POST['refresh_rate']) ? intval($_POST['refresh_rate']) : 3;

    if (empty($logDir)) {
        $errorMsg = 'Log directory path cannot be empty.';
    } else {
        set_plugin_setting('syslog-viewer', 'log_directory', $logDir);
        set_plugin_setting('syslog-viewer', 'max_tail_lines', $maxTail);
        set_plugin_setting('syslog-viewer', 'refresh_rate', $refreshRate);
        $successMsg = 'Syslog settings updated successfully.';
    }
}

$currentLogDir = get_plugin_setting('syslog-viewer', 'log_directory', '/var/log/syslog/');
$currentMaxTail = get_plugin_setting('syslog-viewer', 'max_tail_lines', 300);
$currentRefreshRate = get_plugin_setting('syslog-viewer', 'refresh_rate', 3);
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 text-white mb-1">
                <i class="fas fa-cog text-secondary me-2"></i><?= __('Syslog Settings', 'syslog-viewer'); ?>
            </h1>
            <p class="text-muted mb-0">Configure system log directory paths, streaming parameters, and tail limits.</p>
        </div>
        <div>
            <a href="index.php?route=syslog_viewer_live" class="btn btn-outline-success me-2">
                <i class="fas fa-terminal me-1"></i> <?= __('Syslog Live Viewer', 'syslog-viewer'); ?>
            </a>
            <a href="index.php?route=syslog_viewer_search" class="btn btn-outline-info">
                <i class="fas fa-search me-1"></i> <?= __('Syslog Search', 'syslog-viewer'); ?>
            </a>
        </div>
    </div>

    <?php if ($successMsg): ?>
        <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
            <i class="fas fa-check-circle me-2"></i><?= e($successMsg); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($errorMsg): ?>
        <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i><?= e($errorMsg); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-lg-8">
            <div class="syslog-card p-4">
                <form method="POST" action="index.php?route=syslog_viewer_settings">
                    <?php if (function_exists('csrf_field')) echo csrf_field(); ?>

                    <div class="mb-4">
                        <label class="form-label text-white fw-bold mb-1">Syslog Directory Path</label>
                        <p class="text-muted small mb-2">Specify the server directory where system log files reside (default: <code>/var/log/syslog/</code>).</p>
                        <input type="text" name="log_directory" class="form-control bg-dark text-white border-secondary" value="<?= e($currentLogDir); ?>" required>
                    </div>

                    <div class="mb-4">
                        <label class="form-label text-white fw-bold mb-1">Default Tailing Line Limit</label>
                        <p class="text-muted small mb-2">Controls how many recent log entries are fetched when opening the Live Viewer.</p>
                        <select name="max_tail_lines" class="form-select bg-dark text-white border-secondary">
                            <option value="100" <?= $currentMaxTail == 100 ? 'selected' : ''; ?>>100 lines</option>
                            <option value="300" <?= $currentMaxTail == 300 ? 'selected' : ''; ?>>300 lines</option>
                            <option value="500" <?= $currentMaxTail == 500 ? 'selected' : ''; ?>>500 lines</option>
                            <option value="1000" <?= $currentMaxTail == 1000 ? 'selected' : ''; ?>>1000 lines</option>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="form-label text-white fw-bold mb-1">Live Auto-Refresh Interval</label>
                        <p class="text-muted small mb-2">Polling interval in seconds for appending new written lines in real-time.</p>
                        <select name="refresh_rate" class="form-select bg-dark text-white border-secondary">
                            <option value="1" <?= $currentRefreshRate == 1 ? 'selected' : ''; ?>>1 second</option>
                            <option value="2" <?= $currentRefreshRate == 2 ? 'selected' : ''; ?>>2 seconds</option>
                            <option value="3" <?= $currentRefreshRate == 3 ? 'selected' : ''; ?>>3 seconds (recommended)</option>
                            <option value="5" <?= $currentRefreshRate == 5 ? 'selected' : ''; ?>>5 seconds</option>
                            <option value="10" <?= $currentRefreshRate == 10 ? 'selected' : ''; ?>>10 seconds</option>
                        </select>
                    </div>

                    <div class="pt-2">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save me-1"></i> Save Plugin Settings
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="syslog-card p-4">
                <h5 class="text-white mb-3"><i class="fas fa-info-circle text-info me-2"></i> System Information</h5>
                <ul class="list-group list-group-flush bg-transparent">
                    <li class="list-group-item bg-transparent text-muted px-0 border-secondary d-flex justify-content-between">
                        <span>Directory Active:</span>
                        <strong class="text-white"><?= e($currentLogDir); ?></strong>
                    </li>
                    <li class="list-group-item bg-transparent text-muted px-0 border-secondary d-flex justify-content-between">
                        <span>Performance Mode:</span>
                        <strong class="text-success">Backwards Seek Enabled</strong>
                    </li>
                    <li class="list-group-item bg-transparent text-muted px-0 border-secondary d-flex justify-content-between">
                        <span>Large File Safety:</span>
                        <strong class="text-info">1GB+ Safe Tailing</strong>
                    </li>
                </ul>
                <div class="mt-3 text-muted small">
                    <i class="fas fa-shield-alt text-success me-1"></i> The Syslog Viewer uses chunked reverse seek buffers to tail multi-gigabyte log files safely without memory overflow.
                </div>
            </div>
        </div>
    </div>
</div>
