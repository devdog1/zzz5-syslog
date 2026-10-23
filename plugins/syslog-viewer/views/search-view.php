<?php
/**
 * Syslog Search View Template
 */

if (!defined('APP_ROOT')) {
    exit('Direct access not permitted.');
}

$availableFiles = syslog_viewer_get_available_files();

$selectedFile = isset($_GET['file']) ? trim($_GET['file']) : (count($availableFiles) > 0 ? $availableFiles[0]['filename'] : 'syslog');
$selectedHost = isset($_GET['host']) ? trim($_GET['host']) : '';
$selectedKeyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';
$selectedSeverity = isset($_GET['severity']) ? trim($_GET['severity']) : '';
$timeFrom = isset($_GET['time_from']) ? trim($_GET['time_from']) : '';
$timeTo = isset($_GET['time_to']) ? trim($_GET['time_to']) : '';

$filePath = syslog_viewer_sanitize_path($selectedFile);
$availableHosts = syslog_viewer_get_hosts($filePath, 1000);

$searchResults = null;
$searchError = null;

if (isset($_GET['search']) && $_GET['search'] === '1') {
    $searchOptions = [
        'host'      => $selectedHost,
        'keyword'   => $selectedKeyword,
        'severity'  => $selectedSeverity,
        'time_from' => $timeFrom,
        'time_to'   => $timeTo,
        'limit'     => 500
    ];

    $searchResults = syslog_viewer_search_log($filePath, $searchOptions);
    if (!empty($searchResults['error'])) {
        $searchError = $searchResults['error'];
    }

    // Save to search history DB
    try {
        if (isset($_SESSION['user_id'])) {
            $db = new PluginDatabase('syslog-viewer');
            $table = $db->getTableName('search_history');
            $db->query(
                "INSERT INTO `{$table}` (user_id, search_file, host_filter, keyword, results_count) VALUES (?, ?, ?, ?, ?)",
                [
                    $_SESSION['user_id'],
                    $selectedFile,
                    $selectedHost,
                    $selectedKeyword,
                    count($searchResults['results'] ?? [])
                ]
            );
        }
    } catch (Exception $e) {
        // Silently skip if DB not initialized
    }
}
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 text-white mb-1">
                <i class="fas fa-search text-info me-2"></i><?= __('Syslog Search Engine', 'syslog-viewer'); ?>
            </h1>
            <p class="text-muted mb-0">Perform advanced historical queries across system syslog files by host, timeframe, severity, or keyword.</p>
        </div>
        <div>
            <a href="index.php?route=syslog_viewer_live" class="btn btn-outline-success me-2">
                <i class="fas fa-terminal me-1"></i> <?= __('Syslog Live Viewer', 'syslog-viewer'); ?>
            </a>
            <a href="index.php?route=syslog_viewer_settings" class="btn btn-outline-secondary">
                <i class="fas fa-cog me-1"></i> <?= __('Syslog Settings', 'syslog-viewer'); ?>
            </a>
        </div>
    </div>

    <!-- Search Filters Form -->
    <div class="syslog-card mb-4 p-4">
        <form method="GET" action="index.php">
            <input type="hidden" name="route" value="syslog_viewer_search">
            <input type="hidden" name="search" value="1">

            <div class="row g-3">
                <!-- Log File -->
                <div class="col-md-3">
                    <label class="form-label text-muted small fw-bold"><?= __('Select Syslog File', 'syslog-viewer'); ?></label>
                    <select name="file" class="form-select bg-dark text-white border-secondary">
                        <?php foreach ($availableFiles as $file): ?>
                            <option value="<?= e($file['filename']); ?>" <?= $selectedFile === $file['filename'] ? 'selected' : ''; ?>>
                                <?= e($file['filename']); ?> (<?= $file['formatted_size']; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Host Filter -->
                <div class="col-md-3">
                    <label class="form-label text-muted small fw-bold"><?= __('Filter by Host', 'syslog-viewer'); ?></label>
                    <select name="host" class="form-select bg-dark text-white border-secondary">
                        <option value=""><?= __('All Hosts', 'syslog-viewer'); ?></option>
                        <?php foreach ($availableHosts as $host): ?>
                            <option value="<?= e($host); ?>" <?= $selectedHost === $host ? 'selected' : ''; ?>>
                                <?= e($host); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Severity Filter -->
                <div class="col-md-3">
                    <label class="form-label text-muted small fw-bold"><?= __('Severity', 'syslog-viewer'); ?></label>
                    <select name="severity" class="form-select bg-dark text-white border-secondary">
                        <option value=""><?= __('All Severities', 'syslog-viewer'); ?></option>
                        <option value="emergency" <?= $selectedSeverity === 'emergency' ? 'selected' : ''; ?>>Emergency / Fatal</option>
                        <option value="critical" <?= $selectedSeverity === 'critical' ? 'selected' : ''; ?>>Critical</option>
                        <option value="error" <?= $selectedSeverity === 'error' ? 'selected' : ''; ?>>Error</option>
                        <option value="warning" <?= $selectedSeverity === 'warning' ? 'selected' : ''; ?>>Warning</option>
                        <option value="notice" <?= $selectedSeverity === 'notice' ? 'selected' : ''; ?>>Notice</option>
                        <option value="info" <?= $selectedSeverity === 'info' ? 'selected' : ''; ?>>Info</option>
                        <option value="debug" <?= $selectedSeverity === 'debug' ? 'selected' : ''; ?>>Debug</option>
                    </select>
                </div>

                <!-- Keyword Search -->
                <div class="col-md-3">
                    <label class="form-label text-muted small fw-bold"><?= __('Search Query', 'syslog-viewer'); ?></label>
                    <input type="text" name="keyword" class="form-control bg-dark text-white border-secondary" placeholder="Keyword or string..." value="<?= e($selectedKeyword); ?>">
                </div>

                <!-- Time Period From -->
                <div class="col-md-3">
                    <label class="form-label text-muted small fw-bold">Time From</label>
                    <input type="datetime-local" name="time_from" class="form-control bg-dark text-white border-secondary" value="<?= e($timeFrom); ?>">
                </div>

                <!-- Time Period To -->
                <div class="col-md-3">
                    <label class="form-label text-muted small fw-bold">Time To</label>
                    <input type="datetime-local" name="time_to" class="form-control bg-dark text-white border-secondary" value="<?= e($timeTo); ?>">
                </div>

                <!-- Action Buttons -->
                <div class="col-md-6 d-flex align-items-end justify-content-end">
                    <button type="submit" class="btn btn-info me-2">
                        <i class="fas fa-search me-1"></i> <?= __('Search', 'syslog-viewer'); ?>
                    </button>
                    <a href="index.php?route=syslog_viewer_search" class="btn btn-outline-secondary">
                        <i class="fas fa-undo me-1"></i> <?= __('Clear Filters', 'syslog-viewer'); ?>
                    </a>
                </div>
            </div>
        </form>
    </div>

    <!-- Error Banner -->
    <?php if ($searchError): ?>
        <div class="alert alert-danger mb-4">
            <i class="fas fa-exclamation-triangle me-2"></i><?= e($searchError); ?>
        </div>
    <?php endif; ?>

    <!-- Results Table -->
    <?php if ($searchResults !== null): ?>
        <div class="syslog-card">
            <div class="syslog-card-header">
                <span class="text-white fw-bold">
                    Search Results (Showing <?= count($searchResults['results']); ?> of <?= $searchResults['total_matches']; ?> matches)
                </span>
            </div>

            <div class="syslog-table-container">
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
                    <tbody>
                        <?php if (empty($searchResults['results'])): ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted p-4">
                                    <?= __('No matching log entries found.', 'syslog-viewer'); ?>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($searchResults['results'] as $row): ?>
                                <?php
                                $procText = $row['process'] ? $row['process'] . ($row['pid'] ? '[' . $row['pid'] . ']' : '') : 'system';
                                $msg = e($row['message']);
                                if (!empty($selectedKeyword)) {
                                    $msg = preg_replace('/(' . preg_quote($selectedKeyword, '/') . ')/i', '<span class="search-highlight">$1</span>', $msg);
                                }
                                ?>
                                <tr class="syslog-row">
                                    <td><span class="text-muted"><?= e($row['timestamp']); ?></span></td>
                                    <td><span class="host-pill"><?= e($row['host']); ?></span></td>
                                    <td><span class="process-badge"><?= e($procText); ?></span></td>
                                    <td><span class="severity-badge severity-<?= e($row['severity']); ?>"><?= e($row['severity']); ?></span></td>
                                    <td class="syslog-msg-col"><?= $msg; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>
