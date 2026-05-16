// Global toast - accessible from all scopes
function toast(message, type = 'success') {
    const container = document.getElementById('wrb-toast-container');
    if (!container) return;

    const el = document.createElement('div');
    el.className = 'wrb-toast wrb-toast--' + type;
    el.textContent = message;

    container.appendChild(el);

    setTimeout(() => {
        el.remove();
    }, 3000);
}

jQuery(function($){

    function toast(message, type='success') {
        window.toast(message, type);
    }
    function showToast(message, type='success') {
        toast(message, type);
    }

    async function post(action, data = {}) {
        data.action = 'wrb_' + action;
        data.nonce  = WRB.nonce;
        return await $.post(WRB.ajaxurl, data);
    }

    $('.js-block').on('click', async function(){
        const btn = $(this);
        const domain = btn.data('domain');
        const originalText = btn.text();
        btn.prop('disabled', true).text('در حال مسدودسازی...');

        try {
            await post('block', {domain});
            btn.closest('tr').remove();
            toast('دامنه مسدود شد', 'success');
        } catch(e) {
            toast('خطا در مسدودسازی', 'error');
            btn.prop('disabled', false).text(originalText);
        }
    });

    $('.js-unblock').on('click', async function(){
        const btn = $(this);
        const domain = btn.data('domain');
        const originalText = btn.text();
        btn.prop('disabled', true).text('در حال آزادسازی...');

        try {
            await post('unblock', {domain});
            btn.closest('tr').remove();
            toast('دامنه آزاد شد', 'success');
        } catch(e) {
            toast('خطا در آزادسازی', 'error');
            btn.prop('disabled', false).text(originalText);
        }
    });

    $('.js-manual-block').on('click', async function(){
        const btn = $(this);
        const domain = $('#wrb-manual-input').val().trim();
        if (!domain) return toast(WRB.i18n.empty_domain, 'error');

        const originalText = btn.text();
        btn.prop('disabled', true).text('در حال افزودن...');

        try {
            await post('manual_block', {domain});
            $('#wrb-manual-input').val('');
            toast('دامنه به لیست مسدودی‌ها اضافه شد', 'success');
            btn.prop('disabled', false).text(originalText);
        } catch(e) {
            toast('خطا در افزودن', 'error');
            btn.prop('disabled', false).text(originalText);
        }
    });

    $('.js-clear-logs').on('click', async function(){
        if (!confirm(WRB.i18n.confirm_clear_logs)) return;

        const btn = $(this);
        const originalText = btn.text();
        btn.prop('disabled', true).text('در حال پاک‌سازی...');

        try {
            await post('clear_logs');
            $('#wrb-logs-table tbody').empty();
            toast('لاگ‌ها پاک شدند', 'success');
            btn.prop('disabled', false).text(originalText);
        } catch(e) {
            toast('خطا در پاک‌سازی', 'error');
            btn.prop('disabled', false).text(originalText);
        }
    });

    $('.js-clear-blocks').on('click', async function(){
        if (!confirm(WRB.i18n.confirm_clear_blocks)) return;

        const btn = $(this);
        const originalText = btn.text();
        btn.prop('disabled', true).text('در حال حذف...');

        try {
            await post('clear_blocks');
            $('#wrb-blocks-table tbody').empty();
            toast('لیست مسدودی‌ها پاک شد', 'success');
            btn.prop('disabled', false).text(originalText);
        } catch(e) {
            toast('خطا در حذف', 'error');
            btn.prop('disabled', false).text(originalText);
        }
    });

    // مدیریت ویرایش تنظیمات
    $(document).on('click', '.edit-setting', function() {
        const $row = $(this).closest('tr');
        const key = $(this).data('key');
        const label = $row.find('td:first').text();
        const currentValue = $row.find('td:last').text().trim();

        $('#wrb-edit-modal .wrb-modal-label').text('ویرایش: ' + label);
        $('#wrb-edit-input').val(currentValue).data('key', key);
        $('#wrb-edit-modal').fadeIn(200);
    });

    $('#wrb-cancel-edit, .wrb-modal-overlay').on('click', function() {
        $('#wrb-edit-modal').fadeOut(200);
    });

    $('#wrb-save-edit').on('click', async function() {
        const key = $('#wrb-edit-input').data('key');
        const value = $('#wrb-edit-input').val();

        try {
            const res = await post('update_setting', { key, value });
            if (res.success) {
                toast('تنظیمات ذخیره شد', 'success');
                setTimeout(() => location.reload(), 1000);
            } else {
                toast(res.data.message || 'خطا در ذخیره', 'error');
            }
        } catch(e) {
            toast('خطا در ارتباط با سرور', 'error');
        }
    });

});




/* ═══════════════════════════════════════Debug Log Viewer═══════════════════════════════════════ */

(function() {
    if (!document.querySelector('.wrb-card__title')?.textContent.includes('لاگ‌های دیباگ')) return;

    let autoRefreshInterval = null;
    let currentLogs = [];

    // Toggle Debug
    document.querySelector('.js-toggle-debug')?.addEventListener('click', function() {
        if (!confirm('آیا مطمئن هستید؟')) return;

        fetch(ajaxurl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                action: 'wrb_toggle_debug',
                nonce: WRB.nonce
            })
        })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    if (typeof toast === "function") {
                        toast('تنظیمات با موفقیت تغییر کرد', 'success');
                    }
                    setTimeout(() => location.reload(), 800);
                }
            });
    });

    // Clear Log
    document.querySelector('.js-clear-debug-log')?.addEventListener('click', function() {
        if (!confirm('آیا از پاک کردن لاگ مطمئن هستید؟')) return;

        fetch(ajaxurl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                action: 'wrb_clear_debug_log',
                nonce: WRB.nonce
            })
        })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    window.toast('لاگ پاک شد', 'success');
                    loadLogs();
                } else {
                    window.toast('خطا در پاک کردن', 'error');
                }
            });
    });

    // Load Logs
    function loadLogs() {
        const loading = document.getElementById('wrb-log-loading');
        const tableWrap = document.getElementById('wrb-log-table-wrap');

        if (loading) loading.style.display = 'block';
        if (tableWrap) tableWrap.style.display = 'none';

        fetch(ajaxurl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                action: 'wrb_get_debug_log',
                nonce: WRB.nonce
            })
        })
            .then(r => r.json())
            .then(data => {
                if (loading) loading.style.display = 'none';
                if (data.success) {
                    currentLogs = data.data.logs;
                    renderLogs();
                    if (tableWrap) tableWrap.style.display = 'block';
                }
            });
    }

    // Render Logs
    function renderLogs() {
        const tbody = document.getElementById('wrb-log-tbody');
        if (!tbody) return;

        const level = document.getElementById('wrb-log-level')?.value || '';
        const search = document.getElementById('wrb-log-search')?.value.toLowerCase() || '';

        const filtered = currentLogs.filter(log => {
            if (level && !log.level.includes(level)) return false;
            if (search && !JSON.stringify(log).toLowerCase().includes(search)) return false;
            return true;
        });

        if (filtered.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" class="wrb-empty">هیچ لاگی یافت نشد</td></tr>';
            return;
        }

        tbody.innerHTML = filtered.map(log => {
            const levelClass = log.level.includes('Fatal') ? 'red' :
                log.level.includes('Warning') ? 'orange' :
                    log.level.includes('Notice') ? 'blue' : 'gray';

            return `<tr>
                <td><span class="wrb-badge wrb-badge--${levelClass} wrb-log-level">${log.level}</span></td>
                <td class="wrb-log-date">${log.date}</td>
                <td class="wrb-log-message">${escapeHtml(log.message)}</td>
                <td class="wrb-log-file" title="${escapeHtml(log.full_file)}">${escapeHtml(log.file)}</td>
                <td>${log.line}</td>
            </tr>`;
        }).join('');
    }

    // Filters
    document.getElementById('wrb-log-level')?.addEventListener('change', renderLogs);
    document.getElementById('wrb-log-search')?.addEventListener('input', renderLogs);

    // Auto Refresh
    document.getElementById('wrb-auto-refresh')?.addEventListener('change', function() {
        if (this.checked) {
            autoRefreshInterval = setInterval(loadLogs, 10000);
        } else {
            clearInterval(autoRefreshInterval);
        }
    });

    // Helper
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Initial load
    if (document.getElementById('wrb-log-table')) {
        loadLogs();
    }
})();