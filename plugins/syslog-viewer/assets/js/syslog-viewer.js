/**
 * Syslog Viewer & Tailing Engine JS Module
 * Safe for environments with or without global jQuery pre-loaded.
 */

(function(global) {
    'use strict';

    var SyslogViewer = {
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

            var fileEl = document.getElementById('syslogFileSelect');
            var lineEl = document.getElementById('syslogLineCount');

            self.currentFile = options.initialFile || (fileEl ? fileEl.value : '') || 'syslog';
            self.maxLines = parseInt((lineEl ? lineEl.value : 300), 10) || 300;
            self.refreshRateMs = parseInt(options.refreshRate, 10) || 3000;

            self.bindEvents();
            self.loadInitialTail();
            self.startAutoRefresh();
        },

        bindEvents: function() {
            var self = this;

            var fileSelect = document.getElementById('syslogFileSelect');
            if (fileSelect) {
                fileSelect.addEventListener('change', function() {
                    self.currentFile = this.value;
                    self.loadHosts();
                    self.loadInitialTail();
                });
            }

            var hostFilter = document.getElementById('syslogHostFilter');
            if (hostFilter) {
                hostFilter.addEventListener('change', function() {
                    self.currentHost = this.value;
                    self.applyClientFilter();
                });
            }

            var lineCount = document.getElementById('syslogLineCount');
            if (lineCount) {
                lineCount.addEventListener('change', function() {
                    self.maxLines = parseInt(this.value, 10) || 300;
                    self.loadInitialTail();
                });
            }

            var btnPause = document.getElementById('btnTogglePause');
            if (btnPause) {
                btnPause.addEventListener('click', function() {
                    self.togglePause();
                });
            }

            var btnClear = document.getElementById('btnClearLog');
            if (btnClear) {
                btnClear.addEventListener('click', function() {
                    var tbody = document.getElementById('syslogTableBody');
                    if (tbody) tbody.innerHTML = '';
                    var counter = document.getElementById('logCounter');
                    if (counter) counter.textContent = '0 entries';
                });
            }
        },

        togglePause: function() {
            var self = this;
            self.isPaused = !self.isPaused;

            var btn = document.getElementById('btnTogglePause');
            var statusDot = document.getElementById('syslogStatusDot');
            var statusText = document.getElementById('syslogStatusText');

            if (self.isPaused) {
                if (btn) {
                    btn.className = 'btn btn-sm btn-success me-2';
                    btn.innerHTML = '<i class="fas fa-play me-1"></i> Resume';
                }
                if (statusDot) {
                    statusDot.className = 'syslog-status-dot paused';
                }
                if (statusText) {
                    statusText.textContent = 'PAUSED';
                }
            } else {
                if (btn) {
                    btn.className = 'btn btn-sm btn-warning me-2';
                    btn.innerHTML = '<i class="fas fa-pause me-1"></i> Pause';
                }
                if (statusDot) {
                    statusDot.className = 'syslog-status-dot active';
                }
                if (statusText) {
                    statusText.textContent = 'LIVE AUTO-REFRESH';
                }
            }
        },

        loadHosts: function() {
            var self = this;
            self.ajaxFetch('index.php?route=api/v1/syslog/hosts&file=' + encodeURIComponent(self.currentFile), function(res) {
                if (res && res.status === 'success' && Array.isArray(res.hosts)) {
                    var select = document.getElementById('syslogHostFilter');
                    if (select) {
                        // Keep 'all' option, remove rest
                        select.innerHTML = '<option value="all">All Hosts</option>';
                        res.hosts.forEach(function(host) {
                            var opt = document.createElement('option');
                            opt.value = host;
                            opt.textContent = host;
                            select.appendChild(opt);
                        });
                    }
                }
            });
        },

        loadInitialTail: function() {
            var self = this;
            var tbody = document.getElementById('syslogTableBody');
            if (tbody) {
                tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted p-4"><i class="fas fa-spinner fa-spin me-2"></i> Loading log lines...</td></tr>';
            }

            self.ajaxFetch('index.php?route=api/v1/syslog/live&file=' + encodeURIComponent(self.currentFile) + '&lines=' + self.maxLines, function(res) {
                if (res && res.status === 'success') {
                    self.currentOffset = res.offset;
                    self.renderLines(res.parsed_lines, true);
                    self.loadHosts();
                } else {
                    if (tbody) {
                        tbody.innerHTML = '<tr><td colspan="5" class="text-center text-danger p-4"><i class="fas fa-exclamation-triangle me-2"></i> ' + self.escapeHtml(res ? res.message : 'Error reading log file') + '</td></tr>';
                    }
                }
            }, function() {
                if (tbody) {
                    tbody.innerHTML = '<tr><td colspan="5" class="text-center text-danger p-4"><i class="fas fa-exclamation-triangle me-2"></i> Network error loading log lines.</td></tr>';
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

                self.ajaxFetch('index.php?route=api/v1/syslog/live&file=' + encodeURIComponent(self.currentFile) + '&offset=' + self.currentOffset, function(res) {
                    if (res && res.status === 'success') {
                        self.currentOffset = res.offset;
                        if (res.parsed_lines && res.parsed_lines.length > 0) {
                            self.appendNewLines(res.parsed_lines);
                        }
                    }
                });
            }, self.refreshRateMs);
        },

        renderLines: function(parsedLines, replace) {
            var self = this;
            var tbody = document.getElementById('syslogTableBody');
            if (!tbody) return;

            if (replace) {
                tbody.innerHTML = '';
            }

            if (!parsedLines || parsedLines.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted p-4">No log entries found.</td></tr>';
                var counter = document.getElementById('logCounter');
                if (counter) counter.textContent = '0 entries';
                return;
            }

            var html = '';
            parsedLines.forEach(function(line) {
                html += self.buildRowHtml(line);
            });

            if (replace) {
                tbody.innerHTML = html;
            } else {
                tbody.insertAdjacentHTML('beforeend', html);
            }

            self.applyClientFilter();
            self.scrollToBottom();
            self.updateCount();
        },

        appendNewLines: function(parsedLines) {
            var self = this;
            var tbody = document.getElementById('syslogTableBody');
            if (!tbody) return;

            var html = '';
            parsedLines.forEach(function(line) {
                html += self.buildRowHtml(line);
            });

            tbody.insertAdjacentHTML('beforeend', html);

            // Maintain max line limit
            var rows = tbody.querySelectorAll('tr');
            if (rows.length > self.maxLines) {
                var removeCount = rows.length - self.maxLines;
                for (var i = 0; i < removeCount; i++) {
                    if (rows[i]) rows[i].remove();
                }
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
            var rows = document.querySelectorAll('#syslogTableBody tr');

            rows.forEach(function(row) {
                var rowHost = row.getAttribute('data-host');
                if (selectedHost === 'all' || !rowHost || rowHost === selectedHost) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });

            self.updateCount();
        },

        scrollToBottom: function() {
            var container = document.getElementById('syslogContainer');
            if (container) {
                container.scrollTop = container.scrollHeight;
            }
        },

        updateCount: function() {
            var totalRows = document.querySelectorAll('#syslogTableBody tr');
            var visibleCount = 0;
            totalRows.forEach(function(row) {
                if (row.style.display !== 'none') {
                    visibleCount++;
                }
            });

            var counter = document.getElementById('logCounter');
            if (counter) {
                counter.textContent = visibleCount + ' visible (' + totalRows.length + ' total)';
            }
        },

        ajaxFetch: function(url, successCb, errorCb) {
            if (typeof fetch !== 'undefined') {
                fetch(url)
                    .then(function(response) {
                        return response.json();
                    })
                    .then(function(data) {
                        if (successCb) successCb(data);
                    })
                    .catch(function(err) {
                        if (errorCb) errorCb(err);
                    });
            } else {
                var xhr = new XMLHttpRequest();
                xhr.open('GET', url, true);
                xhr.onreadystatechange = function() {
                    if (xhr.readyState === 4) {
                        if (xhr.status === 200) {
                            try {
                                var data = JSON.parse(xhr.responseText);
                                if (successCb) successCb(data);
                            } catch(e) {
                                if (errorCb) errorCb(e);
                            }
                        } else {
                            if (errorCb) errorCb(xhr.statusText);
                        }
                    }
                };
                xhr.send();
            }
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

    global.SyslogViewer = SyslogViewer;
})(typeof window !== 'undefined' ? window : this);
