/**
 * Syslog Viewer & Tailing Engine JS Module
 */

(function($) {
    'use strict';

    window.SyslogViewer = {
        isPaused: false,
        pollInterval: null,
        currentOffset: 0,
        currentFile: '',
        currentHost: 'all',
        maxLines: 300,
        refreshRateMs: 3000,

        initLiveView: function(options) {
            var self = this;
            options = options || {};

            self.currentFile = options.initialFile || $('#syslogFileSelect').val() || 'syslog';
            self.maxLines = parseInt($('#syslogLineCount').val(), 10) || 300;
            self.refreshRateMs = parseInt(options.refreshRate, 10) || 3000;

            self.bindEvents();
            self.loadInitialTail();
            self.startAutoRefresh();
        },

        bindEvents: function() {
            var self = this;

            $('#syslogFileSelect').on('change', function() {
                self.currentFile = $(this).val();
                self.loadHosts();
                self.loadInitialTail();
            });

            $('#syslogHostFilter').on('change', function() {
                self.currentHost = $(this).val();
                self.applyClientFilter();
            });

            $('#syslogLineCount').on('change', function() {
                self.maxLines = parseInt($(this).val(), 10) || 300;
                self.loadInitialTail();
            });

            $('#btnTogglePause').on('click', function() {
                self.togglePause();
            });

            $('#btnClearLog').on('click', function() {
                $('#syslogTableBody').empty();
                $('#logCounter').text('0 entries');
            });
        },

        togglePause: function() {
            var self = this;
            self.isPaused = !self.isPaused;

            var $btn = $('#btnTogglePause');
            var $status = $('#syslogStatusDot');
            var $statusText = $('#syslogStatusText');

            if (self.isPaused) {
                $btn.removeClass('btn-warning').addClass('btn-success').html('<i class="fas fa-play me-1"></i> Resume');
                $status.removeClass('active').addClass('paused');
                $statusText.text('PAUSED');
            } else {
                $btn.removeClass('btn-success').addClass('btn-warning').html('<i class="fas fa-pause me-1"></i> Pause');
                $status.removeClass('paused').addClass('active');
                $statusText.text('LIVE AUTO-REFRESH');
            }
        },

        loadHosts: function() {
            var self = this;
            $.ajax({
                url: 'index.php?route=api/v1/syslog/hosts&file=' + encodeURIComponent(self.currentFile),
                method: 'GET',
                dataType: 'json',
                success: function(res) {
                    if (res && res.status === 'success') {
                        var $select = $('#syslogHostFilter');
                        $select.find('option:not([value="all"])').remove();
                        $.each(res.hosts, function(idx, host) {
                            $select.append($('<option>', {
                                value: host,
                                text: host
                            }));
                        });
                    }
                }
            });
        },

        loadInitialTail: function() {
            var self = this;
            $('#syslogTableBody').html('<tr><td colspan="5" class="text-center text-muted p-4"><i class="fas fa-spinner fa-spin me-2"></i> Loading log lines...</td></tr>');

            $.ajax({
                url: 'index.php?route=api/v1/syslog/live&file=' + encodeURIComponent(self.currentFile) + '&lines=' + self.maxLines,
                method: 'GET',
                dataType: 'json',
                success: function(res) {
                    if (res && res.status === 'success') {
                        self.currentOffset = res.offset;
                        self.renderLines(res.parsed_lines, true);
                        self.loadHosts();
                    } else {
                        $('#syslogTableBody').html('<tr><td colspan="5" class="text-center text-danger p-4"><i class="fas fa-exclamation-triangle me-2"></i> ' + (res.message || 'Error reading log file') + '</td></tr>');
                    }
                },
                error: function() {
                    $('#syslogTableBody').html('<tr><td colspan="5" class="text-center text-danger p-4"><i class="fas fa-exclamation-triangle me-2"></i> Network error loading log lines.</td></tr>');
                }
            });
        },

        startAutoRefresh: function() {
            var self = this;
            if (self.pollInterval) {
                clearInterval(self.pollInterval);
            }

            self.pollInterval = setInterval(function() {
                if (self.isPaused || !self.currentFile) {
                    return;
                }

                $.ajax({
                    url: 'index.php?route=api/v1/syslog/live&file=' + encodeURIComponent(self.currentFile) + '&offset=' + self.currentOffset,
                    method: 'GET',
                    dataType: 'json',
                    success: function(res) {
                        if (res && res.status === 'success') {
                            self.currentOffset = res.offset;
                            if (res.parsed_lines && res.parsed_lines.length > 0) {
                                self.appendNewLines(res.parsed_lines);
                            }
                        }
                    }
                });
            }, self.refreshRateMs);
        },

        renderLines: function(parsedLines, replace) {
            var self = this;
            var $tbody = $('#syslogTableBody');
            if (replace) {
                $tbody.empty();
            }

            if (!parsedLines || parsedLines.length === 0) {
                $tbody.html('<tr><td colspan="5" class="text-center text-muted p-4">No log entries found.</td></tr>');
                $('#logCounter').text('0 entries');
                return;
            }

            var html = '';
            $.each(parsedLines, function(idx, line) {
                html += self.buildRowHtml(line);
            });

            if (replace) {
                $tbody.html(html);
            } else {
                $tbody.append(html);
            }

            self.applyClientFilter();
            self.scrollToBottom();
            self.updateCount();
        },

        appendNewLines: function(parsedLines) {
            var self = this;
            var html = '';
            $.each(parsedLines, function(idx, line) {
                html += self.buildRowHtml(line);
            });

            var $tbody = $('#syslogTableBody');
            $tbody.append(html);

            // Maintain max line limit in DOM
            var $rows = $tbody.find('tr');
            if ($rows.length > self.maxLines) {
                $rows.slice(0, $rows.length - self.maxLines).remove();
            }

            self.applyClientFilter();
            self.scrollToBottom();
            self.updateCount();
        },

        buildRowHtml: function(line) {
            var self = this;
            var sevClass = 'severity-' + (line.severity || 'info');
            var procText = line.process ? line.process + (line.pid ? '[' + line.pid + ']' : '') : 'system';
            var hostText = line.host || 'unknown';

            return '<tr class="syslog-row" data-host="' + self.escapeAttr(hostText) + '">' +
                '<td><span class="text-muted">' + self.escapeHtml(line.timestamp) + '</span></td>' +
                '<td><span class="host-pill">' + self.escapeHtml(hostText) + '</span></td>' +
                '<td><span class="process-badge">' + self.escapeHtml(procText) + '</span></td>' +
                '<td><span class="severity-badge ' + sevClass + '">' + self.escapeHtml(line.severity) + '</span></td>' +
                '<td class="syslog-msg-col">' + self.escapeHtml(line.message) + '</td>' +
                '</tr>';
        },

        applyClientFilter: function() {
            var self = this;
            var selectedHost = self.currentHost;

            $('#syslogTableBody tr').each(function() {
                var $row = $(this);
                var rowHost = $row.attr('data-host');

                if (selectedHost === 'all' || !rowHost || rowHost === selectedHost) {
                    $row.show();
                } else {
                    $row.hide();
                }
            });

            self.updateCount();
        },

        scrollToBottom: function() {
            var $container = $('#syslogContainer');
            if ($container.length) {
                $container.scrollTop($container[0].scrollHeight);
            }
        },

        updateCount: function() {
            var visibleCount = $('#syslogTableBody tr:visible').length;
            var totalCount = $('#syslogTableBody tr').length;
            $('#logCounter').text(visibleCount + ' visible (' + totalCount + ' total)');
        },

        escapeHtml: function(str) {
            if (!str) return '';
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        },

        escapeAttr: function(str) {
            if (!str) return '';
            return String(str).replace(/"/g, '&quot;');
        }
    };
})(jQuery);
