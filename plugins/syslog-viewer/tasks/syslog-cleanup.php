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
        $table = $db->getTableName('search_history');

        $stmt = $db->query("DELETE FROM `{$table}` WHERE `searched_at` < DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $deleted = $stmt ? $stmt->rowCount() : 0;

        echo "[syslog-viewer] Cleaned up {$deleted} old search history entries.\n";
    } catch (Exception $e) {
        echo "[syslog-viewer] Cleanup error: " . $e->getMessage() . "\n";
    }
}

syslog_viewer_cleanup_task();
