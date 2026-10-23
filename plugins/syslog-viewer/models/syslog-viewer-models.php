<?php
/**
 * Syslog Viewer Plugin Models & Helper Utility Functions
 */

if (!defined('APP_ROOT')) {
    exit('Direct access not permitted.');
}

/**
 * Retrieves the configured syslog directory or returns default.
 */
function syslog_viewer_get_directory() {
    $dir = get_plugin_setting('syslog-viewer', 'log_directory', '/var/log/syslog/');
    if (empty($dir)) {
        $dir = '/var/log/syslog/';
    }
    // Ensure trailing slash
    return rtrim($dir, '/') . '/';
}

/**
 * Validates and sanitizes a file path to prevent directory traversal.
 * Ensures the target file resides within the configured log directory or /var/log/.
 */
function syslog_viewer_sanitize_path($filename) {
    $baseDir = syslog_viewer_get_directory();

    // Remove any path traversal sequences
    $filename = basename($filename);

    $fullPath = $baseDir . $filename;

    // Fallback: If configured dir doesn't exist but /var/log does and file exists there
    if (!file_exists($fullPath) && file_exists('/var/log/' . $filename)) {
        $fullPath = '/var/log/' . $filename;
    }

    return $fullPath;
}

/**
 * Scans configured syslog directory and retrieves available log files.
 */
function syslog_viewer_get_available_files() {
    $dir = syslog_viewer_get_directory();
    $files = [];

    $scanDirs = array_unique([$dir, '/var/log/syslog/', '/var/log/']);

    foreach ($scanDirs as $scanDir) {
        if (!is_dir($scanDir) || !is_readable($scanDir)) {
            continue;
        }

        $items = @scandir($scanDir);
        if ($items === false) {
            continue;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $scanDir . $item;
            if (is_file($path) && is_readable($path)) {
                // Avoid duplicating
                if (!isset($files[$item])) {
                    $size = @filesize($path);
                    $mtime = @filemtime($path);
                    $files[$item] = [
                        'filename'     => $item,
                        'path'         => $path,
                        'size'         => $size !== false ? $size : 0,
                        'formatted_size' => syslog_viewer_format_bytes($size),
                        'mtime'        => $mtime !== false ? $mtime : 0,
                        'mtime_formatted' => $mtime ? date('Y-m-d H:i:s', $mtime) : 'Unknown'
                    ];
                }
            }
        }
    }

    // Sort files by modification time descending
    uasort($files, function($a, $b) {
        return $b['mtime'] <=> $a['mtime'];
    });

    return array_values($files);
}

/**
 * Format bytes to human readable format.
 */
function syslog_viewer_format_bytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);

    return round($bytes, $precision) . ' ' . $units[$pow];
}

/**
 * Efficiently reads the last N lines of a potentially massive file (1GB+)
 * using backwards seek chunking.
 */
function syslog_viewer_tail($filePath, $lineCount = 300) {
    if (!file_exists($filePath) || !is_readable($filePath)) {
        return [
            'lines'     => [],
            'offset'    => 0,
            'file_size' => 0,
            'error'     => 'File does not exist or is not readable.'
        ];
    }

    $fileSize = @filesize($filePath);
    if ($fileSize === false || $fileSize === 0) {
        return [
            'lines'     => [],
            'offset'    => 0,
            'file_size' => 0,
            'error'     => null
        ];
    }

    $fp = @fopen($filePath, 'rb');
    if (!$fp) {
        return [
            'lines'     => [],
            'offset'    => 0,
            'file_size' => $fileSize,
            'error'     => 'Failed to open log file.'
        ];
    }

    $bufferSize = 4096;
    $lines = [];
    $currentPos = $fileSize;
    $leftover = '';

    while ($currentPos > 0 && count($lines) <= $lineCount) {
        $readSize = min($bufferSize, $currentPos);
        $currentPos -= $readSize;

        fseek($fp, $currentPos, SEEK_SET);
        $chunk = fread($fp, $readSize);
        if ($chunk === false) {
            break;
        }

        $chunk .= $leftover;
        $chunkLines = explode("\n", $chunk);

        // Save incomplete first line for previous chunk
        if ($currentPos > 0) {
            $leftover = array_shift($chunkLines);
        } else {
            $leftover = '';
        }

        for ($i = count($chunkLines) - 1; $i >= 0; $i--) {
            $line = trim($chunkLines[$i]);
            if ($line !== '') {
                array_unshift($lines, $line);
                if (count($lines) >= $lineCount) {
                    break 2;
                }
            }
        }
    }

    fclose($fp);

    return [
        'lines'     => $lines,
        'offset'    => $fileSize,
        'file_size' => $fileSize,
        'error'     => null
    ];
}

/**
 * Polls new lines written to log file since previous byte offset.
 */
function syslog_viewer_read_new_lines($filePath, $lastOffset) {
    if (!file_exists($filePath) || !is_readable($filePath)) {
        return [
            'lines'     => [],
            'new_offset'=> 0,
            'file_size' => 0,
            'rotated'   => false,
            'error'     => 'File does not exist or is not readable.'
        ];
    }

    $currentSize = @filesize($filePath);
    if ($currentSize === false) {
        $currentSize = 0;
    }

    $rotated = false;
    // Log rotation or truncation detected
    if ($lastOffset > $currentSize) {
        $lastOffset = 0;
        $rotated = true;
    }

    $fp = @fopen($filePath, 'rb');
    if (!$fp) {
        return [
            'lines'     => [],
            'new_offset'=> $lastOffset,
            'file_size' => $currentSize,
            'rotated'   => $rotated,
            'error'     => 'Failed to open file for update.'
        ];
    }

    fseek($fp, $lastOffset, SEEK_SET);
    $content = stream_get_contents($fp);
    $newOffset = ftell($fp);
    fclose($fp);

    $rawLines = explode("\n", $content);
    $lines = [];
    foreach ($rawLines as $line) {
        $line = trim($line);
        if ($line !== '') {
            $lines[] = $line;
        }
    }

    return [
        'lines'     => $lines,
        'new_offset'=> $newOffset,
        'file_size' => $currentSize,
        'rotated'   => $rotated,
        'error'     => null
    ];
}

/**
 * Parses a standard syslog message line into structured components.
 * Supports RFC 3164 (BSD syslog), RFC 5424, and systemd / journal syslog formats.
 */
function syslog_viewer_parse_line($line) {
    $parsed = [
        'raw'       => $line,
        'timestamp' => 'N/A',
        'epoch'     => 0,
        'host'      => 'unknown',
        'process'   => 'system',
        'pid'       => null,
        'severity'  => 'info',
        'message'   => $line
    ];

    if (empty($line)) {
        return $parsed;
    }

    // Attempt ISO-8601 / RFC 5424 timestamp parsing: e.g. 2023-10-23T14:05:01.123+00:00 host process[123]: msg
    if (preg_match('/^(\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:?\d{2})?)\s+([^\s]+)\s+([^:\[\s]+)(?:\[(\d+)\])?\s*:\s*(.*)$/S', $line, $m)) {
        $parsed['timestamp'] = $m[1];
        $parsed['epoch']     = strtotime($m[1]) ?: 0;
        $parsed['host']      = $m[2];
        $parsed['process']   = $m[3];
        $parsed['pid']       = $m[4] !== '' ? $m[4] : null;
        $parsed['message']   = $m[5];
        $parsed['severity']  = syslog_viewer_detect_severity($m[5]);
        return $parsed;
    }

    // Attempt BSD RFC 3164 timestamp parsing: e.g. Oct 23 14:05:01 host process[123]: message
    if (preg_match('/^([A-Z][a-z]{2}\s+\d+\s+\d{2}:\d{2}:\d{2})\s+([^\s]+)\s+([^:\[\s]+)(?:\[(\d+)\])?\s*:\s*(.*)$/S', $line, $m)) {
        $parsed['timestamp'] = $m[1];
        // Standard BSD syslog omits year, assume current year
        $year = date('Y');
        $parsed['epoch']     = strtotime($m[1] . ' ' . $year) ?: 0;
        $parsed['host']      = $m[2];
        $parsed['process']   = $m[3];
        $parsed['pid']       = $m[4] !== '' ? $m[4] : null;
        $parsed['message']   = $m[5];
        $parsed['severity']  = syslog_viewer_detect_severity($m[5]);
        return $parsed;
    }

    // Fallback: BSD timestamp with host and message without process tag
    if (preg_match('/^([A-Z][a-z]{2}\s+\d+\s+\d{2}:\d{2}:\d{2})\s+([^\s]+)\s+(.*)$/S', $line, $m)) {
        $parsed['timestamp'] = $m[1];
        $parsed['epoch']     = strtotime($m[1] . ' ' . date('Y')) ?: 0;
        $parsed['host']      = $m[2];
        $parsed['message']   = $m[3];
        $parsed['severity']  = syslog_viewer_detect_severity($m[3]);
        return $parsed;
    }

    // Generic fallback severity detection
    $parsed['severity'] = syslog_viewer_detect_severity($line);

    return $parsed;
}

/**
 * Infer log severity based on keywords in line/message.
 */
function syslog_viewer_detect_severity($message) {
    $msgLower = strtolower($message);
    if (strpos($msgLower, 'emerg') !== false || strpos($msgLower, 'fatal') !== false || strpos($msgLower, 'panic') !== false) {
        return 'emergency';
    }
    if (strpos($msgLower, 'crit') !== false) {
        return 'critical';
    }
    if (strpos($msgLower, 'err') !== false || strpos($msgLower, 'failed') !== false || strpos($msgLower, 'error') !== false) {
        return 'error';
    }
    if (strpos($msgLower, 'warn') !== false || strpos($msgLower, 'warning') !== false) {
        return 'warning';
    }
    if (strpos($msgLower, 'notice') !== false) {
        return 'notice';
    }
    if (strpos($msgLower, 'debug') !== false) {
        return 'debug';
    }

    return 'info';
}

/**
 * Extracts list of unique hostnames from recent syslog entries.
 */
function syslog_viewer_get_hosts($filePath, $scanLines = 1000) {
    $tailResult = syslog_viewer_tail($filePath, $scanLines);
    $hosts = [];

    foreach ($tailResult['lines'] as $line) {
        $parsed = syslog_viewer_parse_line($line);
        if (!empty($parsed['host']) && $parsed['host'] !== 'unknown') {
            $hosts[$parsed['host']] = true;
        }
    }

    $hostList = array_keys($hosts);
    sort($hostList);

    return $hostList;
}

/**
 * Performs search query over a log file with filtering options.
 * Options: host, time_from, time_to, keyword, severity, limit.
 */
function syslog_viewer_search_log($filePath, array $options = []) {
    $hostFilter   = !empty($options['host']) ? strtolower(trim($options['host'])) : null;
    $timeFrom     = !empty($options['time_from']) ? strtotime($options['time_from']) : null;
    $timeTo       = !empty($options['time_to']) ? strtotime($options['time_to']) : null;
    $keyword      = !empty($options['keyword']) ? strtolower(trim($options['keyword'])) : null;
    $severityFilter = !empty($options['severity']) ? strtolower(trim($options['severity'])) : null;
    $limit        = !empty($options['limit']) ? intval($options['limit']) : 500;

    if (!file_exists($filePath) || !is_readable($filePath)) {
        return [
            'results' => [],
            'total_matches' => 0,
            'error' => 'Selected log file does not exist or is unreadable.'
        ];
    }

    $fp = @fopen($filePath, 'rb');
    if (!$fp) {
        return [
            'results' => [],
            'total_matches' => 0,
            'error' => 'Failed to open log file.'
        ];
    }

    $results = [];
    $totalMatches = 0;

    while (($line = fgets($fp)) !== false) {
        $lineTrim = trim($line);
        if ($lineTrim === '') {
            continue;
        }

        // Quick string check for keyword before full parsing if keyword specified
        if ($keyword !== null && strpos(strtolower($lineTrim), $keyword) === false) {
            continue;
        }

        $parsed = syslog_viewer_parse_line($lineTrim);

        // Host filter
        if ($hostFilter !== null && strtolower($parsed['host']) !== $hostFilter) {
            continue;
        }

        // Severity filter
        if ($severityFilter !== null && strtolower($parsed['severity']) !== $severityFilter) {
            continue;
        }

        // Time Period Filter
        if ($parsed['epoch'] > 0) {
            if ($timeFrom !== null && $parsed['epoch'] < $timeFrom) {
                continue;
            }
            if ($timeTo !== null && $parsed['epoch'] > $timeTo) {
                continue;
            }
        }

        $totalMatches++;
        if (count($results) < $limit) {
            $results[] = $parsed;
        }
    }

    fclose($fp);

    return [
        'results'       => $results,
        'total_matches' => $totalMatches,
        'limit'         => $limit,
        'error'         => null
    ];
}
