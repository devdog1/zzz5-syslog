<?php
/**
 * Plugin Name: Syslog Viewer & Search Engine
 * Description: Real-time monitoring, live tailing, dynamic host filtering, and historical search for system syslog files.
 * Version: 1.0.0
 * Author: DevDog
 * Requires PHP: 8.1
 * Requires Core: 1.0.0
 * Permissions: syslog_viewer_view, syslog_viewer_search, syslog_viewer_settings
 * Roles: administrator:syslog_viewer_view,syslog_viewer_search,syslog_viewer_settings; operator:syslog_viewer_view,syslog_viewer_search; viewer:syslog_viewer_view
 */

if (!defined('APP_ROOT')) {
    define('APP_ROOT', __DIR__ . '/../../');
}

// Load core models and helper utility functions
require_once __DIR__ . '/models/syslog-viewer-models.php';

// Enqueue static CSS & JS assets
if (function_exists('enqueue_style')) {
    enqueue_style('syslog-viewer-css', 'plugins/syslog-viewer/assets/css/syslog-viewer.css', [], '1.0.0');
}
if (function_exists('enqueue_script')) {
    enqueue_script('syslog-viewer-js', 'plugins/syslog-viewer/assets/js/syslog-viewer.js', ['jquery'], '1.0.0', true);
}

// Navigation Menu Items
add_filter('theme_nav_links', function($links) {
    if (!function_exists('has_permission') || has_permission('syslog_viewer_view')) {
        $links[] = [
            'id'       => 'syslog-viewer-menu',
            'title'    => __('Syslog Viewer', 'syslog-viewer'),
            'icon'     => 'fas fa-terminal',
            'href'     => 'index.php?route=syslog_viewer_live',
            'children' => [
                [
                    'title'  => __('Live Viewer', 'syslog-viewer'),
                    'icon'   => 'fas fa-play',
                    'href'   => 'index.php?route=syslog_viewer_live',
                    'parent' => 'syslog-viewer-menu'
                ],
                [
                    'title'  => __('Log Search', 'syslog-viewer'),
                    'icon'   => 'fas fa-search',
                    'href'   => 'index.php?route=syslog_viewer_search',
                    'parent' => 'syslog-viewer-menu'
                ],
                [
                    'title'  => __('Settings', 'syslog-viewer'),
                    'icon'   => 'fas fa-cog',
                    'href'   => 'index.php?route=syslog_viewer_settings',
                    'parent' => 'syslog-viewer-menu'
                ]
            ]
        ];
    }
    return $links;
}, 10);

// Register Custom Routes
add_action('register_routes', function() {
    register_route('syslog_viewer_live', function() {
        if (function_exists('has_permission') && !has_permission('syslog_viewer_view')) {
            exit('Access Denied');
        }
        require_once __DIR__ . '/views/live-view.php';
    });

    register_route('syslog_viewer_search', function() {
        if (function_exists('has_permission') && !has_permission('syslog_viewer_search')) {
            exit('Access Denied');
        }
        require_once __DIR__ . '/views/search-view.php';
    });

    register_route('syslog_viewer_settings', function() {
        if (function_exists('has_permission') && !has_permission('syslog_viewer_settings')) {
            exit('Access Denied');
        }
        require_once __DIR__ . '/views/settings-view.php';
    });
});

// REST API Endpoints
if (function_exists('register_api_route')) {
    // Live Tailing API Endpoint
    register_api_route('api/v1/syslog/live', 'GET', function($params) {
        $file = isset($_GET['file']) ? $_GET['file'] : 'syslog';
        $filePath = syslog_viewer_sanitize_path($file);

        if (isset($_GET['offset']) && $_GET['offset'] !== '') {
            $offset = intval($_GET['offset']);
            $readData = syslog_viewer_read_new_lines($filePath, $offset);
            $parsed = [];
            foreach ($readData['lines'] as $line) {
                $parsed[] = syslog_viewer_parse_line($line);
            }

            return [
                'status'       => 'success',
                'file'         => $file,
                'offset'       => $readData['new_offset'],
                'file_size'    => $readData['file_size'],
                'rotated'      => $readData['rotated'],
                'parsed_lines' => $parsed
            ];
        } else {
            $linesCount = isset($_GET['lines']) ? intval($_GET['lines']) : 300;
            $tailData = syslog_viewer_tail($filePath, $linesCount);
            $parsed = [];
            foreach ($tailData['lines'] as $line) {
                $parsed[] = syslog_viewer_parse_line($line);
            }

            return [
                'status'       => 'success',
                'file'         => $file,
                'offset'       => $tailData['offset'],
                'file_size'    => $tailData['file_size'],
                'parsed_lines' => $parsed
            ];
        }
    }, 'syslog_viewer_view');

    // Available Syslog Files Endpoint
    register_api_route('api/v1/syslog/files', 'GET', function($params) {
        $files = syslog_viewer_get_available_files();
        return [
            'status' => 'success',
            'files'  => $files
        ];
    }, 'syslog_viewer_view');

    // Unique Hosts List Endpoint
    register_api_route('api/v1/syslog/hosts', 'GET', function($params) {
        $file = isset($_GET['file']) ? $_GET['file'] : 'syslog';
        $filePath = syslog_viewer_sanitize_path($file);
        $hosts = syslog_viewer_get_hosts($filePath, 1000);

        return [
            'status' => 'success',
            'hosts'  => $hosts
        ];
    }, 'syslog_viewer_view');

    // Log Search API Endpoint
    register_api_route('api/v1/syslog/search', 'GET', function($params) {
        $file = isset($_GET['file']) ? $_GET['file'] : 'syslog';
        $filePath = syslog_viewer_sanitize_path($file);

        $options = [
            'host'      => isset($_GET['host']) ? $_GET['host'] : null,
            'keyword'   => isset($_GET['keyword']) ? $_GET['keyword'] : null,
            'severity'  => isset($_GET['severity']) ? $_GET['severity'] : null,
            'time_from' => isset($_GET['time_from']) ? $_GET['time_from'] : null,
            'time_to'   => isset($_GET['time_to']) ? $_GET['time_to'] : null,
            'limit'     => isset($_GET['limit']) ? intval($_GET['limit']) : 500
        ];

        $results = syslog_viewer_search_log($filePath, $options);

        return [
            'status' => 'success',
            'search' => $results
        ];
    }, 'syslog_viewer_search');
}

// Home Dashboard Widget
add_filter('index_dashboard_widgets', function($widgets) {
    if (!function_exists('has_permission') || has_permission('syslog_viewer_view')) {
        ob_start();
        require __DIR__ . '/views/widget-view.php';
        $content = ob_get_clean();

        $widgets[] = [
            'id'       => 'widget_syslog_viewer',
            'title'    => 'Syslog Monitor',
            'content'  => $content,
            'width'    => 'col-lg-6'
        ];
    }
    return $widgets;
});

// Register Task Scheduler Job
add_action('init_scheduler', function($scheduler) {
    if (is_object($scheduler) && method_exists($scheduler, 'registerTask')) {
        $scheduler->registerTask('syslog_cleanup', __DIR__ . '/tasks/syslog-cleanup.php', 86400, 'syslog-viewer');
    }
});

// Inter-Plugin Services Registration
if (class_exists('PluginManager')) {
    PluginManager::getInstance()->registerService('get_syslog_files', function() {
        return syslog_viewer_get_available_files();
    }, 'syslog-viewer');

    PluginManager::getInstance()->registerService('query_syslog', function($filePath, $options = []) {
        return syslog_viewer_search_log($filePath, $options);
    }, 'syslog-viewer');
}

// Asynchronous Event Bus Listeners
if (function_exists('add_event_listener')) {
    add_event_listener('syslog_alert_triggered', function($payload) {
        if (function_exists('log_action')) {
            log_action('SYSLOG_ALERT_EVENT', json_encode($payload));
        }
    });
}
