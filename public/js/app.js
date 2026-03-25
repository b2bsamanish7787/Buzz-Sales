/* ============================================================
   app.js – Buzznation Client Requirement Portal
   ============================================================ */

const BuzzApp = (function ($) {
    'use strict';

    // ---- CSRF ----
    function getCsrfToken() {
        return $('input[name="csrf_token"]').val() ||
               $('meta[name="csrf-token"]').attr('content') || '';
    }

    // ---- AJAX helper ----
    function ajax(url, data, successCb, errorCb) {
        if (data instanceof FormData) {
            data.append('csrf_token', getCsrfToken());
        } else if (typeof data === 'object') {
            data.csrf_token = getCsrfToken();
        }
        $.ajax({
            url: url,
            method: 'POST',
            data: data,
            dataType: 'json',
            processData: !(data instanceof FormData),
            contentType: data instanceof FormData ? false : 'application/x-www-form-urlencoded; charset=UTF-8',
            success: successCb,
            error: function (xhr) {
                var msg = 'Server error. Please try again.';
                try { msg = JSON.parse(xhr.responseText).message || msg; } catch (e) {}
                if (typeof errorCb === 'function') errorCb(msg);
                else showToast(msg, 'danger');
            }
        });
    }

    // ---- Toast ----
    function showToast(message, type) {
        type = type || 'info';
        var id = 'toast_' + Date.now();
        var icons = {success:'fa-check-circle', danger:'fa-times-circle', warning:'fa-exclamation-triangle', info:'fa-info-circle'};
        var icon  = icons[type] || 'fa-info-circle';
        var html = '<div id="' + id + '" class="toast align-items-center text-bg-' + type + ' border-0 mb-2" role="alert" data-bs-autohide="true" data-bs-delay="4000">' +
            '<div class="d-flex"><div class="toast-body"><i class="fa ' + icon + ' me-2"></i>' + escapeHtml(message) + '</div>' +
            '<button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div></div>';

        if (!$('#toastContainer').length) {
            $('body').append('<div id="toastContainer" class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index:11000"></div>');
        }
        $('#toastContainer').append(html);
        var el = document.getElementById(id);
        var toast = new bootstrap.Toast(el);
        toast.show();
        $(el).on('hidden.bs.toast', function () { $(this).remove(); });
    }

    // ---- Escape HTML ----
    function escapeHtml(str) {
        return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');
    }

    // ---- File Upload with Progress ----
    function initFileUpload(dropZoneSelector, inputSelector, fileListSelector, maxSizeMB) {
        maxSizeMB = maxSizeMB || 500;
        var maxBytes = maxSizeMB * 1024 * 1024;
        var files = [];

        var $zone  = $(dropZoneSelector);
        var $input = $(inputSelector);
        var $list  = $(fileListSelector);

        if (!$zone.length) return { getFiles: function() { return []; } };

        $zone.on('click', function () { $input[0].click(); });

        $zone.on('dragover dragenter', function (e) {
            e.preventDefault();
            $zone.addClass('dragover');
        }).on('dragleave drop', function (e) {
            e.preventDefault();
            $zone.removeClass('dragover');
            if (e.type === 'drop') {
                addFiles(e.originalEvent.dataTransfer.files);
            }
        });

        $input.on('change', function () { addFiles(this.files); this.value = ''; });

        function addFiles(fileList) {
            $.each(fileList, function (_, f) {
                if (f.size > maxBytes) {
                    showToast(f.name + ' exceeds ' + maxSizeMB + 'MB limit.', 'warning');
                    return;
                }
                files.push(f);
                renderFile(f, files.length - 1);
            });
        }

        function renderFile(f, idx) {
            var size = formatSize(f.size);
            var ext  = f.name.split('.').pop().toUpperCase();
            var row  = $('<div class="file-item">' +
                '<span class="badge bg-secondary me-1">' + escapeHtml(ext) + '</span>' +
                '<span class="file-name">' + escapeHtml(f.name) + '</span>' +
                '<span class="file-size">' + size + '</span>' +
                '<span class="remove-file" data-idx="' + idx + '"><i class="fa fa-times"></i></span>' +
            '</div>');
            $list.append(row);
        }

        $list.on('click', '.remove-file', function () {
            var idx = parseInt($(this).data('idx'));
            files[idx] = null;
            $(this).closest('.file-item').remove();
        });

        return {
            getFiles: function () { return files.filter(Boolean); },
            reset: function () { files = []; $list.empty(); }
        };
    }

    // ---- Upload files via XHR ----
    function uploadFiles(fileObjs, projectId, uploadType, progressCb, doneCb) {
        if (!fileObjs.length) { if (typeof doneCb === 'function') doneCb([]); return; }
        var uploaded = [];
        var pending  = fileObjs.slice();

        function next() {
            if (!pending.length) { if (typeof doneCb === 'function') doneCb(uploaded); return; }
            var f  = pending.shift();
            var fd = new FormData();
            fd.append('file', f);
            fd.append('project_id', projectId || '');
            fd.append('upload_type', uploadType || 'requirement');
            fd.append('csrf_token', getCsrfToken());

            var xhr = new XMLHttpRequest();
            xhr.open('POST', BASE_URL + '/api/file-upload.php', true);

            xhr.upload.onprogress = function (e) {
                if (e.lengthComputable && typeof progressCb === 'function') {
                    progressCb(Math.round(e.loaded / e.total * 100), f.name);
                }
            };

            xhr.onload = function () {
                try {
                    var res = JSON.parse(xhr.responseText);
                    if (res.success) uploaded.push(res);
                    else showToast('Upload failed: ' + (res.message || f.name), 'warning');
                } catch (e) {
                    showToast('Upload error for ' + f.name, 'danger');
                }
                next();
            };
            xhr.onerror = function () {
                showToast('Network error uploading ' + f.name, 'danger');
                next();
            };
            xhr.send(fd);
        }
        next();
    }

    // ---- Format file size ----
    function formatSize(bytes) {
        if (bytes >= 1073741824) return (bytes / 1073741824).toFixed(2) + ' GB';
        if (bytes >= 1048576)    return (bytes / 1048576).toFixed(2) + ' MB';
        if (bytes >= 1024)       return (bytes / 1024).toFixed(2) + ' KB';
        return bytes + ' B';
    }

    // ---- Notification polling ----
    function initNotifications(userId) {
        if (!userId) return;

        function poll() {
            $.getJSON(BASE_URL + '/api/notifications.php?action=get&user_id=' + userId, function (res) {
                if (res.success) {
                    updateBell(res.count);
                    renderNotifications(res.notifications);
                }
            });
        }

        function updateBell(count) {
            var $badge = $('.notif-badge');
            if (count > 0) {
                $badge.text(count > 99 ? '99+' : count).show();
            } else {
                $badge.hide();
            }
            $('#notifCount').text(count);
        }

        function renderNotifications(notifs) {
            var $list = $('#notifList');
            if (!$list.length) return;
            if (!notifs || !notifs.length) {
                $list.html('<div class="p-3 text-center text-muted small">No new notifications</div>');
                return;
            }
            var html = '';
            $.each(notifs, function (_, n) {
                html += '<div class="notif-item' + (n.is_read == 0 ? ' unread' : '') + '" data-id="' + n.id + '">' +
                    '<div>' + escapeHtml(n.message) + '</div>' +
                    '<div class="notif-time">' + escapeHtml(n.created_at) + '</div></div>';
            });
            $list.html(html);
        }

        $(document).on('click', '.notif-item', function () {
            var id = $(this).data('id');
            $(this).removeClass('unread');
            $.post(BASE_URL + '/api/notifications.php', { action: 'read', id: id, csrf_token: getCsrfToken() });
        });

        $('#markAllRead').on('click', function () {
            $.post(BASE_URL + '/api/notifications.php', { action: 'read_all', csrf_token: getCsrfToken() }, function () {
                $('.notif-badge').hide();
                $('.notif-item').removeClass('unread');
            });
        });

        poll();
        setInterval(poll, 30000);
    }

    // ---- Confirm dialog ----
    function confirm(message, callback) {
        if (window.confirm(message)) callback();
    }

    // ---- Loading overlay ----
    function showLoading() {
        if (!$('#loadingOverlay').length) {
            $('body').append('<div id="loadingOverlay" class="loading-overlay"><div class="spinner-border text-light" style="width:3rem;height:3rem;"></div></div>');
        }
        $('#loadingOverlay').show();
    }
    function hideLoading() { $('#loadingOverlay').hide(); }

    // ---- Public API ----
    return {
        ajax:           ajax,
        showToast:      showToast,
        escapeHtml:     escapeHtml,
        initFileUpload: initFileUpload,
        uploadFiles:    uploadFiles,
        formatSize:     formatSize,
        initNotifications: initNotifications,
        confirm:        confirm,
        showLoading:    showLoading,
        hideLoading:    hideLoading,
        getCsrfToken:   getCsrfToken
    };

})(jQuery);

// Global BASE_URL (set per-page if needed, fallback empty)
if (typeof BASE_URL === 'undefined') { var BASE_URL = ''; }

$(function () {
    // Init tooltips
    $('[data-bs-toggle="tooltip"]').each(function () {
        new bootstrap.Tooltip(this);
    });

    // Auto-hide alerts
    $('.alert.auto-hide').delay(4000).fadeOut(500);

    // Status filter
    $('#statusFilter').on('change', function () {
        var val = $(this).val().toLowerCase();
        $('.filterable-row').each(function () {
            var status = $(this).data('status') || '';
            $(this).toggle(!val || status === val);
        });
    });

    // Search filter
    $('#searchInput').on('keyup', function () {
        var val = $(this).val().toLowerCase();
        $('.filterable-row').each(function () {
            $(this).toggle($(this).text().toLowerCase().includes(val));
        });
    });
});
