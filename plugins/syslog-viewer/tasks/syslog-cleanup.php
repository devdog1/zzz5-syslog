<?php
/**
 * Syslog Viewer Scheduled Background Task
 * Cleans up search history entries older than 30 days.
 */

if (!defined('APP_ROOT')) {
    exit('Direct access not permitted.');
}

function syslog_viewer_cleanup_task() {
    try {
        $db = new PluginDatabase('syslog-viewer');
        $pdo = $db->getPdo();
        $table = $db->getTableName('search_history');

        $stmt = $pdo->prepare("DELETE FROM `{$table}` WHERE `searched_at` < DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $stmt->execute();
        $deleted = $stmt->rowCount();

        echo "[syslog-viewer] Cleaned up {$deleted} old search history entries.\n";
    } catch (Exception $e) {
        echo "[syslog-viewer] Cleanup error: " . $e->getMessage() . "\n";
    }
}

syslog_viewer_cleanup_task();
