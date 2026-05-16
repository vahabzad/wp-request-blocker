<?php
if (!defined('ABSPATH')) {
    exit;
}

class WRB_Admin_Page
{

    private static $instance = null;

    public static function get_instance(): self
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        add_action('admin_menu', [$this, 'register_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue']);
        add_action('wp_ajax_wrb_update_setting', [$this, 'ajax_update_setting']);

        // AJAX
        $actions = ['block', 'unblock', 'clear_logs', 'clear_blocks', 'manual_block', 'toggle_debug', 'clear_debug_log', 'get_debug_log'];
        foreach ($actions as $action) {
            add_action("wp_ajax_wrb_{$action}", [$this, "ajax_{$action}"]);
        }
    }

    /* ───────────────────────────────────────
       Menu Registration
    ─────────────────────────────────────── */

    public function register_menu(): void
    {

        // منوی اصلی (تاپ‌لول) با آیکون سپر
        add_menu_page(
            'WP Request Blocker',
            'Request Blocker',
            'manage_options',
            'wrb-dashboard',
            [$this, 'render_dashboard'],
            'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>'),
            30
        );

        // زیرمنو ۱: داشبورد
        add_submenu_page(
            'wrb-dashboard',
            'داشبورد — WP Request Blocker',
            'داشبورد',
            'manage_options',
            'wrb-dashboard',
            [$this, 'render_dashboard']
        );

        // زیرمنو ۲: Request Blocker
        add_submenu_page(
            'wrb-dashboard',
            'مسدودساز درخواست — WP Request Blocker',
            'Request Blocker',
            'manage_options',
            'wrb-blocker',
            [$this, 'render_blocker']
        );

        // زیرمنو ۳: PHP Info
        add_submenu_page(
            'wrb-dashboard',
            'اطلاعات سرور — WP Request Blocker',
            'PHP & Server Info',
            'manage_options',
            'wrb-phpinfo',
            [$this, 'render_phpinfo']
        );

        // زیرمنو ۴: Debug Log
        add_submenu_page(
            'wrb-dashboard',
            'دیباگ لاگ — WP Request Blocker',
            'Debug Log',
            'manage_options',
            'wrb-debug-log',
            [$this, 'render_debug_log']
        );
    }

    /* ───────────────────────────────────────
       Assets
    ─────────────────────────────────────── */

    public function enqueue(string $hook): void
    {
        $wrb_hooks = [
            'toplevel_page_wrb-dashboard',
            'request-blocker_page_wrb-blocker',
            'request-blocker_page_wrb-phpinfo',
            'request-blocker_page_wrb-debug-log',

        ];
        if (!in_array($hook, $wrb_hooks, true)) return;

        wp_enqueue_style(
            'wrb-admin',
            WRB_URL . 'assets/css/admin.css',
            [],
            WRB_VERSION
        );
        wp_enqueue_script(
            'wrb-admin',
            WRB_URL . 'assets/js/admin.js',
            ['jquery'],
            WRB_VERSION,
            true
        );
        wp_enqueue_script(
            'wrb-admin-tailwind',
            WRB_URL . 'assets/js/tailwind.js',
            [],
            WRB_VERSION,
            true
        );
        wp_localize_script('wrb-admin', 'WRB', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wrb_nonce'),
            'i18n' => [
                'confirm_clear_logs' => 'تمام لاگ‌های تایم‌اوت پاک می‌شوند. ادامه می‌دهید؟',
                'confirm_clear_blocks' => 'تمام بلاک‌ها حذف می‌شوند. ادامه می‌دهید؟',
                'empty_domain' => 'لطفاً یک دامنه معتبر وارد کنید.',
            ],
        ]);
        wp_localize_script('wrb-admin', 'wrb_nonce', wp_create_nonce('wrb_nonce'));
    }

    /* ═══════════════════════════════════════
       PAGE 1: داشبورد
    ═══════════════════════════════════════ */

    public function render_dashboard(): void
    {

        ?>
        <div class="wrb-wrap" dir="rtl">
            <?php $this->render_header('داشبورد'); ?>

            <div class="grid grid-col-4 w-full p-6">
                <div class="flex px-6 py-4">
                    <h3>رکوئست بلاکر</h3>
                    <p>در زمان نت ملی برا جبران افت سرعت به واسته عدم پاسخ </p>
                </div>
            </div>

            <?php $this->render_footer(); ?>
        </div>
        <div class="wrb-toast-container" id="wrb-toast-container"></div>
        <?php
    }

    /* ═══════════════════════════════════════
       PAGE 2: Request Blocker
    ═══════════════════════════════════════ */

    public function render_blocker(): void
    {
        $monitor = WRB_Request_Monitor::get_instance();
        $blocker = WRB_Request_Blocker::get_instance();
        $log = $monitor->get_log();
        $blocked = $blocker->get_blocked();
        ?>
        <div class="wrb-wrap" dir="rtl">
            <?php $this->render_header('Request Blocker', [
                ['label' => 'پاک‌کردن لاگ‌ها', 'class' => 'wrb-btn--ghost js-clear-logs', 'icon' => 'trash'],
                ['label' => 'حذف همه بلاک‌ها', 'class' => 'wrb-btn--danger js-clear-blocks', 'icon' => 'x-circle'],
            ]); ?>

            <div class="wrb-grid">
                <!-- جدول تایم‌اوت‌ها -->
                <div class="wrb-card">
                    <div class="wrb-card__head">
                        <span class="wrb-card__icon wrb-card__icon--orange">
                            <?php echo $this->icon('clock'); ?>
                        </span>
                        <h2 class="wrb-card__title">درخواست‌های تایم‌اوت</h2>
                        <span class="wrb-badge wrb-badge--orange"><?php echo count($log); ?> دامنه</span>
                    </div>

                    <?php if (empty($log)) : ?>
                        <div class="wrb-empty">
                            <p>هیچ تایم‌اوتی ثبت نشده است.</p>
                        </div>
                    <?php else : ?>
                        <div class="wrb-table-wrap">
                            <table class="wrb-table">
                                <thead>
                                <tr>
                                    <th>دامنه</th>
                                    <th>تعداد</th>
                                    <th>میانگین</th>
                                    <th>آخرین بار</th>
                                    <th>وضعیت</th>
                                    <th>عملیات</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($log as $domain => $data) :
                                    $avg = $data['count'] ? $data['total_time'] / $data['count'] : 0;
                                    $is_blocked = in_array($domain, $blocked, true);
                                    ?>
                                    <tr class="<?php echo $is_blocked ? 'is-blocked' : ''; ?>">
                                        <td>
                                            <div class="wrb-domain">
                                                <span class="wrb-domain__name"><?php echo esc_html($domain); ?></span>
                                                <?php if ($data['is_timeout']) : ?>
                                                    <span class="wrb-badge wrb-badge--red wrb-badge--xs">timeout</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td><span class="wrb-pill"><?php echo esc_html($data['count']); ?>×</span></td>
                                        <td>
                                            <span class="wrb-time <?php echo $avg >= 8 ? 'wrb-time--danger' : ($avg >= 5 ? 'wrb-time--warn' : ''); ?>">
                                                <?php echo number_format($avg, 2); ?>s
                                            </span>
                                        </td>
                                        <td class="wrb-muted"><?php echo esc_html(human_time_diff($data['last_seen']) . ' پیش'); ?></td>
                                        <td>
                                            <?php if ($is_blocked) : ?>
                                                <span class="wrb-badge wrb-badge--red">مسدود</span>
                                            <?php else : ?>
                                                <span class="wrb-badge wrb-badge--green">فعال</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="wrb-actions-cell">
                                            <?php if ($is_blocked) : ?>
                                                <button class="wrb-btn wrb-btn--ghost wrb-btn--xs js-unblock"
                                                        data-domain="<?php echo esc_attr($domain); ?>">آزادسازی
                                                </button>
                                            <?php else : ?>
                                                <button class="wrb-btn wrb-btn--danger wrb-btn--xs js-block"
                                                        data-domain="<?php echo esc_attr($domain); ?>">مسدود
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Sidebar -->
                <div class="wrb-sidebar">

                    <!-- افزودن دستی -->
                    <div class="wrb-card">
                        <div class="wrb-card__head">
                            <span class="wrb-card__icon wrb-card__icon--purple">
                                <?php echo $this->icon('plus'); ?>
                            </span>
                            <h2 class="wrb-card__title">مسدودسازی دستی</h2>
                        </div>
                        <div class="wrb-manual">
                            <div class="wrb-input-group">
                                <input class="wrb-input" id="wrb-manual-input" type="text" placeholder="example.com"
                                       dir="ltr" autocomplete="off"/>
                                <button class="wrb-btn wrb-btn--primary js-manual-block">افزودن</button>
                            </div>
                            <p class="wrb-hint">فقط دامنه بدون http/https وارد کنید.</p>
                        </div>
                    </div>

                    <!-- لیست بلاک‌شده‌ها -->
                    <div class="wrb-card" id="wrb-card-blocked">
                        <div class="wrb-card__head">
                            <span class="wrb-card__icon wrb-card__icon--red">
                                <?php echo $this->icon('ban'); ?>
                            </span>
                            <h2 class="wrb-card__title">دامنه‌های مسدود</h2>
                            <span class="wrb-badge wrb-badge--red"><?php echo count($blocked); ?></span>
                        </div>
                        <?php if (empty($blocked)) : ?>
                            <div class="wrb-empty wrb-empty--sm"><p>هیچ دامنه‌ای مسدود نشده است.</p></div>
                        <?php else : ?>
                            <ul class="wrb-blocked-list">
                                <?php foreach ($blocked as $b_domain) : ?>
                                    <li class="wrb-blocked-item">
                                        <span class="wrb-blocked-item__name"
                                              dir="ltr"><?php echo esc_html($b_domain); ?></span>
                                        <button class="wrb-btn wrb-btn--ghost wrb-btn--xs js-unblock"
                                                data-domain="<?php echo esc_attr($b_domain); ?>">
                                            <?php echo $this->icon('x', 12); ?>
                                        </button>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>

                </div>
            </div>

            <?php $this->render_footer(); ?>
        </div>
        <div class="wrb-toast-container" id="wrb-toast-container"></div>
        <?php
    }

    /* ═══════════════════════════════════════
       PAGE 3: PHP Info & Server
    ═══════════════════════════════════════ */

    public function render_phpinfo(): void
    {
        // وضعیت SSL/HTTPS
        $ssl_on = is_ssl();
        $site_url = get_site_url();
        $ssl_cert = $this->get_ssl_expiry();

        // اطلاعات PHP
        $php_version = PHP_VERSION;
        $php_os = PHP_OS;
        $php_sapi = php_sapi_name();
        $memory_limit = ini_get('memory_limit');
        $max_exec = ini_get('max_execution_time');
        $upload_max = ini_get('upload_max_filesize');
        $post_max = ini_get('post_max_size');
        $max_input = ini_get('max_input_vars');
        $display_err = ini_get('display_errors');

        // اکستنشن‌های مهم
        $extensions = get_loaded_extensions();
        sort($extensions);


        // اطلاعات دیتابیس
        global $wpdb;
        $db_version = $wpdb->get_var('SELECT VERSION()');
        $db_charset = $wpdb->charset;
        $db_collate = $wpdb->collate;

        // اطلاعات سرور
        $server_software = $_SERVER['SERVER_SOFTWARE'] ?? 'نامشخص';
        $server_protocol = $_SERVER['SERVER_PROTOCOL'] ?? 'نامشخص';
        $server_name = $_SERVER['SERVER_NAME'] ?? 'نامشخص';
        ?>
        <div class="wrb-wrap" dir="rtl">
            <?php $this->render_header('PHP & Server Info'); ?>

            <!-- HTTPS Status Banner -->
            <div class="wrb-https-banner wrb-https-banner--<?php echo $ssl_on ? 'secure' : 'insecure'; ?>">
                <div class="wrb-https-banner__icon">
                    <?php echo $ssl_on ? $this->icon('lock', 28) : $this->icon('unlock', 28); ?>
                </div>
                <div class="wrb-https-banner__body">
                    <strong><?php echo $ssl_on ? 'اتصال امن HTTPS فعال است' : 'HTTPS فعال نیست — سایت ناامن است'; ?></strong>
                    <span><?php echo esc_html($site_url); ?></span>
                </div>
                <?php if ($ssl_on && $ssl_cert) : ?>
                    <div class="wrb-https-banner__cert">
                        <span class="wrb-muted" style="font-size:12px">انقضای گواهی:</span>
                        <strong style="font-size:13px"><?php echo esc_html($ssl_cert); ?></strong>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Grid: 3 columns -->
            <div class="wrb-info-grid">

                <!-- کارت PHP -->
                <div class="wrb-card">
                    <div class="wrb-card__head">
                        <span class="wrb-card__icon wrb-card__icon--purple">
                            <?php echo $this->icon('code'); ?>
                        </span>
                        <h2 class="wrb-card__title">اطلاعات PHP</h2>
                    </div>
                    <div class="wrb-info-list">
                        <?php $this->info_row('نسخه PHP', $php_version, true); ?>
                        <?php $this->info_row('سیستم‌عامل', $php_os, true); ?>
                        <?php $this->info_row('SAPI', $php_sapi, true); ?>
                        <?php $this->info_row('Memory Limit', $memory_limit, true, true, 'memory_limit'); ?>
                        <?php $this->info_row('Max Execution', $max_exec . 's', true, true, 'max_execution_time'); ?>
                        <?php $this->info_row('Upload Max', $upload_max, true, true, 'upload_max_filesize'); ?>
                        <?php $this->info_row('Post Max Size', $post_max, true, true, 'post_max_size'); ?>
                        <?php $this->info_row('Max Input Vars', $max_input, true); ?>
                        <?php $this->info_row('Display Errors', $display_err ? 'روشن' : 'خاموش', false, true, 'display_errors'); ?>
                    </div>
                </div>

                <!-- کارت سرور -->
                <div class="wrb-card">
                    <div class="wrb-card__head">
                        <span class="wrb-card__icon wrb-card__icon--blue">
                            <?php echo $this->icon('server'); ?>
                        </span>
                        <h2 class="wrb-card__title">اطلاعات سرور</h2>
                    </div>
                    <div class="wrb-info-list">
                        <?php $this->info_row('نرم‌افزار سرور', $server_software, true); ?>
                        <?php $this->info_row('پروتکل', $server_protocol, true); ?>
                        <?php $this->info_row('نام سرور', $server_name, true); ?>
                        <?php $this->info_row('HTTPS', $ssl_on ? '✅ فعال' : '❌ غیرفعال'); ?>
                        <?php $this->info_row('نسخه وردپرس', get_bloginfo('version'), true); ?>
                        <?php $this->info_row('نسخه دیتابیس', $db_version, true); ?>
                        <?php $this->info_row('Charset DB', $db_charset, true); ?>
                        <?php $this->info_row('Collation DB', $db_collate, true); ?>
                        <?php $this->info_row('پیشوند جداول', $wpdb->prefix, true); ?>
                    </div>
                </div>

                <!-- کارت وردپرس -->
                <div class="wrb-card">
                    <div class="wrb-card__head">
                        <span class="wrb-card__icon wrb-card__icon--orange">
                            <?php echo $this->icon('globe'); ?>
                        </span>
                        <h2 class="wrb-card__title">تنظیمات وردپرس</h2>
                    </div>
                    <div class="wrb-info-list">
                        <?php $this->info_row('آدرس سایت', get_site_url()); ?>
                        <?php $this->info_row('آدرس وردپرس', get_option('siteurl')); ?>
                        <?php $this->info_row('زبان', get_locale(), true); ?>
                        <?php $this->info_row('Timezone', wp_timezone_string(), true); ?>
                        <?php $this->info_row('WP_DEBUG', (defined('WP_DEBUG') && WP_DEBUG) ? '⚠️ روشن' : '✅ خاموش', false, true, 'WP_DEBUG'); ?>
                        <?php $this->info_row('WP_MEMORY_LIMIT', defined('WP_MEMORY_LIMIT') ? WP_MEMORY_LIMIT : 'نامشخص', true, true, 'WP_MEMORY_LIMIT'); ?>
                        <?php $this->info_row('Multisite', is_multisite() ? 'بله' : 'خیر'); ?>
                        <?php $this->info_row('تم فعال', wp_get_theme()->get('Name')); ?>
                    </div>
                </div>
            </div>

            <!-- PHP Extensions -->
            <div class="wrb-card" style="margin-top:20px">
                <div class="wrb-card__head">
                    <span class="wrb-card__icon wrb-card__icon--purple">
                        <?php echo $this->icon('puzzle'); ?>
                    </span>
                    <h2 class="wrb-card__title">اکستنشن‌های PHP</h2>
                </div>
                <div class="wrb-ext-grid">
                    <?php foreach ($extensions as $ext) : ?>
                        <div class="wrb-ext-item wrb-ext-item--ok">
                            <?php echo $this->icon('check-circle', 16); ?>
                            <span>
                                <?php echo esc_html($ext); ?>
                                <?php if (phpversion($ext)) : ?>
                                    <small>(<?php echo esc_html(phpversion($ext)); ?>)</small>
                                <?php endif; ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                    
                </div>
            </div>
            <!-- مودال ویرایش -->
            <div id="wrb-edit-modal" class="wrb-modal" style="display:none;">
                <div class="wrb-modal-overlay"></div>
                <div class="wrb-modal-content">
                    <h3>ویرایش تنظیمات</h3>
                    <p class="wrb-modal-label"></p>
                    <input type="text" id="wrb-edit-input" class="wrb-input"/>
                    <div class="wrb-modal-actions">
                        <button id="wrb-save-edit" class="wrb-btn wrb-btn--primary">ذخیره</button>
                        <button id="wrb-cancel-edit" class="wrb-btn">انصراف</button>
                    </div>
                </div>
            </div>

            <?php $this->render_footer(); ?>
        </div>
        <?php
    }

    /* ═══════════════════════════════════════
        PAGE 4: Debug Log Viewer
       ═══════════════════════════════════════ */

    public function render_debug_log(): void
    {
        $debug_enabled = defined('WP_DEBUG') && WP_DEBUG;
        $log_file = WP_CONTENT_DIR . '/debug.log';
        $log_exists = file_exists($log_file);
        $log_size = $log_exists ? size_format(filesize($log_file)) : '0 B';

        ?>
        <div class="wrb-wrap" dir="rtl">
            <?php $this->render_header('Debug Log Viewer', [
                ['label' => $debug_enabled ? 'خاموش کردن Debug' : 'روشن کردن Debug', 'class' => 'wrb-btn--primary js-toggle-debug', 'icon' => 'power'],
                ['label' => 'پاک کردن لاگ', 'class' => 'wrb-btn--danger js-clear-debug-log', 'icon' => 'trash'],]); ?>

            <div class="wrb-grid wrb-grid--single">
                <div class="wrb-card">
                    <div class="wrb-card__head">
                    <span class="wrb-card__icon wrb-card__icon--<?php echo $debug_enabled ? 'green' : 'gray'; ?>">
                        <?php echo $this->icon('file-text'); ?>
                    </span>
                        <h2 class="wrb-card__title">لاگ‌های دیباگ</h2><span
                                class="wrb-badge wrb-badge--<?php echo $debug_enabled ? 'green' : 'gray'; ?>">
                        <?php echo $debug_enabled ? 'فعال' : 'غیرفعال'; ?>
                    </span>
                        <span class="wrb-badge wrb-badge--blue"><?php echo $log_size; ?></span>
                    </div>

                    <div class="wrb-debug-controls">
                        <div class="wrb-field-group">
                            <div class="wrb-field">
                                <label class="wrb-label">فیلتر سطح:</label>
                                <select id="wrb-log-level" class="wrb-select">
                                    <option value="">همه</option>
                                    <option value="Fatal">Fatal Error</option>
                                    <option value="Warning">Warning</option>
                                    <option value="Notice">Notice</option>
                                    <option value="Deprecated">Deprecated</option>
                                </select>
                            </div>
                            <div class="wrb-field">
                                <label class="wrb-label">جستجو:</label>
                                <input type="text" id="wrb-log-search" class="wrb-input"
                                       placeholder="جستجو در لاگ‌ها...">
                            </div>
                            <div class="wrb-field">
                                <label class="wrb-checkbox">
                                    <input type="checkbox" id="wrb-auto-refresh">
                                    <span>Auto Refresh (10s)</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <?php if (!$log_exists) : ?>
                        <div class="wrb-empty">
                            <p>فایل debug.log وجود ندارد یا خالی است.</p>
                        </div>
                    <?php else : ?>
                        <div class="wrb-debug-log-container">
                            <div class="wrb-loading" id="wrb-log-loading">در حال بارگذاری...</div>
                            <div class="wrb-table-wrap" id="wrb-log-table-wrap" style="display:none;">
                                <table class="wrb-table wrb-table--debug" id="wrb-log-table">
                                    <thead>
                                    <tr>
                                        <th width="120">سطح</th>
                                        <th width="150">تاریخ/زمان</th>
                                        <th>پیام</th>
                                        <th width="300">فایل</th>
                                        <th width="80">خط</th>
                                    </tr>
                                    </thead>
                                    <tbody id="wrb-log-tbody"></tbody>
                                </table>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php $this->render_footer(); ?>
        </div>
        <div class="wrb-toast-container" id="wrb-toast-container"></div>
        <?php
    }


    /* ═══════════════════════════════════════
       Shared UI Helpers
    ═══════════════════════════════════════ */

    private function render_header(string $page_title, array $actions = []): void
    {
        ?>
        <div class="wrb-header">
            <div class="wrb-header__brand">
                <svg class="wrb-logo" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="20" cy="20" r="20" fill="#6366f1"/>
                    <path d="M20 6l10 4v8c0 6-4.5 10-10 12C14.5 28 10 24 10 18v-8l10-4z" fill="rgba(255,255,255,.2)"
                          stroke="#fff" stroke-width="1.5" stroke-linejoin="round"/>
                    <path d="M15 20l3 3 7-7" stroke="#fff" stroke-width="2" stroke-linecap="round"
                          stroke-linejoin="round"/>
                </svg>
                <div>
                    <h1 class="wrb-header__title">WP Request Blocker</h1>
                    <p class="wrb-header__sub"><?php echo esc_html($page_title); ?></p>
                </div>
            </div>
            <?php if (!empty($actions)) : ?>
                <div class="wrb-header__actions">
                    <?php foreach ($actions as $action) : ?>
                        <button class="wrb-btn wrb-btn--sm <?php echo esc_attr($action['class']); ?>">
                            <?php if (!empty($action['icon'])) echo $this->icon($action['icon']); ?>
                            <?php echo esc_html($action['label']); ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    private function render_footer(): void
    {
        ?>
        <div class="wrb-footer">
            <p>این افزونه توسط <a href="https://vahabzad.ir" target="_blank" rel="noopener noreferrer">سید حمید
                    وهاب‌زاده</a> توسعه داده شده است. — نسخه <?php echo esc_html(WRB_VERSION); ?></p>
        </div>
        <?php
    }

    private function stat_card(string $color, string $icon, $number, string $label): void
    {
        ?>
        <div class="wrb-stat wrb-stat--<?php echo esc_attr($color); ?>">
            <div class="wrb-stat__icon"><?php echo $this->icon($icon, 24); ?></div>
            <div class="wrb-stat__body">
                <span class="wrb-stat__number"><?php echo esc_html($number); ?></span>
                <span class="wrb-stat__label"><?php echo esc_html($label); ?></span>
            </div>
        </div>
        <?php
    }

    private function info_row(string $label, string $value, bool $ltr = false, bool $editable = false, string $edit_key = ''): void
    {
        ?>
        <div class="wrb-info-row">
            <span class="wrb-info-label"><?php echo esc_html($label); ?></span>
            <span class="wrb-info-value<?php echo $ltr ? ' wrb-ltr' : ''; ?>">
            <?php echo esc_html($value); ?>
                <?php if ($editable && $edit_key) : ?>
                    <button class="wrb-edit-btn edit-setting" data-key="WP_MEMORY_LIMIT" data-current="40M"
                            title="ویرایش">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
    </svg>
</button>

                <?php endif; ?>
        </span>
        </div>
        <?php
    }


    /**
     * دریافت تاریخ انقضای گواهی SSL سایت جاری
     */
    private function get_ssl_expiry(): string
    {
        if (!function_exists('stream_socket_client')) return '';
        $host = wp_parse_url(get_site_url(), PHP_URL_HOST);
        if (!$host) return '';

        $context = stream_context_create(['ssl' => ['capture_peer_cert' => true]]);
        $client = @stream_socket_client(
            "ssl://{$host}:443",
            $errno, $errstr, 5,
            STREAM_CLIENT_CONNECT,
            $context
        );
        if (!$client) return '';

        $params = stream_context_get_params($client);
        $cert = openssl_x509_parse($params['options']['ssl']['peer_certificate'] ?? null);
        fclose($client);

        if (empty($cert['validTo_time_t'])) return '';

        return date_i18n('Y/m/d', $cert['validTo_time_t']);
    }

    /**
     * SVG آیکون‌های Feather-style
     */
    private function icon(string $name, int $size = 18): string
    {
        $s = $size;
        $icons = [
            'clock' => "<svg width='{$s}' height='{$s}' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><circle cx='12' cy='12' r='10'/><polyline points='12 6 12 12 16 14'/></svg>",
            'globe' => "<svg width='{$s}' height='{$s}' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><circle cx='12' cy='12' r='10'/><line x1='2' y1='12' x2='22' y2='12'/><path d='M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z'/></svg>",
            'ban' => "<svg width='{$s}' height='{$s}' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><circle cx='12' cy='12' r='10'/><line x1='4.93' y1='4.93' x2='19.07' y2='19.07'/></svg>",
            'shield' => "<svg width='{$s}' height='{$s}' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><path d='M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z'/></svg>",
            'server' => "<svg width='{$s}' height='{$s}' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><rect x='2' y='2' width='20' height='8' rx='2' ry='2'/><rect x='2' y='14' width='20' height='8' rx='2' ry='2'/><line x1='6' y1='6' x2='6.01' y2='6'/><line x1='6' y1='18' x2='6.01' y2='18'/></svg>",
            'trash' => "<svg width='{$s}' height='{$s}' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><polyline points='3 6 5 6 21 6'/><path d='M19 6l-1 14H6L5 6'/><path d='M10 11v6'/><path d='M14 11v6'/><path d='M9 6V4h6v2'/></svg>",
            'zap' => "<svg width='{$s}' height='{$s}' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><polygon points='13 2 3 14 12 14 11 22 21 10 12 10 13 2'/></svg>",
            'flame' => "<svg width='{$s}' height='{$s}' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><path d='M8.5 14.5A2.5 2.5 0 0011 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 01-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 002.5 2.5z'/></svg>",
            'plus' => "<svg width='{$s}' height='{$s}' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><line x1='12' y1='5' x2='12' y2='19'/><line x1='5' y1='12' x2='19' y2='12'/></svg>",
            'x' => "<svg width='{$s}' height='{$s}' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><line x1='18' y1='6' x2='6' y2='18'/><line x1='6' y1='6' x2='18' y2='18'/></svg>",
            'x-circle' => "<svg width='{$s}' height='{$s}' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><circle cx='12' cy='12' r='10'/><line x1='15' y1='9' x2='9' y2='15'/><line x1='9' y1='9' x2='15' y2='15'/></svg>",
            'info' => "<svg width='{$s}' height='{$s}' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><circle cx='12' cy='12' r='10'/><line x1='12' y1='16' x2='12' y2='12'/><line x1='12' y1='8' x2='12.01' y2='8'/></svg>",
            'code' => "<svg width='{$s}' height='{$s}' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><polyline points='16 18 22 12 16 6'/><polyline points='8 6 2 12 8 18'/></svg>",
            'lock' => "<svg width='{$s}' height='{$s}' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><rect x='3' y='11' width='18' height='11' rx='2' ry='2'/><path d='M7 11V7a5 5 0 0110 0v4'/></svg>",
            'unlock' => "<svg width='{$s}' height='{$s}' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><rect x='3' y='11' width='18' height='11' rx='2' ry='2'/><path d='M7 11V7a5 5 0 019.9-1'/></svg>",
            'check-circle' => "<svg width='{$s}' height='{$s}' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><path d='M22 11.08V12a10 10 0 11-5.93-9.14'/><polyline points='22 4 12 14.01 9 11.01'/></svg>",
            'puzzle' => "<svg width='{$s}' height='{$s}' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><path d='M20.59 13.41l-7.17 7.17a2 2 0 01-2.83 0L2 12V2h10l8.59 8.59a2 2 0 010 2.82z'/><line x1='7' y1='7' x2='7.01' y2='7'/></svg>",
        ];
        return $icons[$name] ?? '';
    }

    /* ───────────────────────────────────────
       AJAX Handlers
    ─────────────────────────────────────── */

    private function verify(): void
    {
        check_ajax_referer('wrb_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'دسترسی غیرمجاز.'], 403);
        }
    }

    public function ajax_block(): void
    {
        $this->verify();
        $domain = sanitize_text_field(wp_unslash($_POST['domain'] ?? ''));
        WRB_Request_Blocker::get_instance()->add($domain);
        wp_send_json_success(['message' => "دامنه «{$domain}» مسدود شد."]);
    }

    public function ajax_unblock(): void
    {
        $this->verify();
        $domain = sanitize_text_field(wp_unslash($_POST['domain'] ?? ''));
        WRB_Request_Blocker::get_instance()->remove($domain);
        wp_send_json_success(['message' => "دامنه «{$domain}» آزاد شد."]);
    }

    public function ajax_manual_block(): void
    {
        $this->verify();
        $domain = sanitize_text_field(wp_unslash($_POST['domain'] ?? ''));
        $added = WRB_Request_Blocker::get_instance()->add($domain);
        if ($added) {
            wp_send_json_success(['message' => "دامنه «{$domain}» افزوده شد."]);
        } else {
            wp_send_json_error(['message' => 'دامنه نامعتبر است یا قبلاً اضافه شده.']);
        }
    }

    public function ajax_clear_logs(): void
    {
        $this->verify();
        WRB_Request_Monitor::get_instance()->clear_log();
        wp_send_json_success(['message' => 'لاگ‌ها پاک شدند.']);
    }

    public function ajax_clear_blocks(): void
    {
        $this->verify();
        WRB_Request_Blocker::get_instance()->clear();
        wp_send_json_success(['message' => 'همه بلاک‌ها حذف شدند.']);
    }

    public function ajax_toggle_debug(): void
    {
        check_ajax_referer('wrb_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('دسترسی غیرمجاز');
        }

        $wp_config = ABSPATH . 'wp-config.php';
        if (!file_exists($wp_config) || !is_writable($wp_config)) {
            wp_send_json_error('فایل wp-config.php قابل نوشتن نیست');
        }

        $content = file_get_contents($wp_config);
        $debug_enabled = defined('WP_DEBUG') && WP_DEBUG;

        if ($debug_enabled) {
            $content = preg_replace("/define\s*\(\s*['\"]WP_DEBUG['\"]\s*,\s*true\s*\);/", "define( 'WP_DEBUG', false );", $content);
        } else {
            if (strpos($content, 'WP_DEBUG') === false) {
                $content = preg_replace("/(\/\*\s*That's all.*?\*\/)/s", "define( 'WP_DEBUG', true );\n$1", $content);
            } else {
                $content = preg_replace("/define\s*\(\s*['\"]WP_DEBUG['\"]\s*,\s*false\s*\);/", "define( 'WP_DEBUG', true );", $content);
            }
        }

        file_put_contents($wp_config, $content);

        wp_send_json_success(['enabled' => !$debug_enabled]);
    }

    public function ajax_clear_debug_log(): void
    {
        check_ajax_referer('wrb_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('دسترسی غیرمجاز');
        }

        $log_file = WP_CONTENT_DIR . '/debug.log';
        if (file_exists($log_file)) {
            file_put_contents($log_file, '');
        }

        wp_send_json_success();
    }

    public function ajax_get_debug_log(): void
    {
        check_ajax_referer('wrb_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('دسترسی غیرمجاز');
        }

        $log_file = WP_CONTENT_DIR . '/debug.log';
        if (!file_exists($log_file)) {
            wp_send_json_success(['logs' => []]);
        }

        $content = file_get_contents($log_file);
        $lines = array_filter(explode("\n", $content));
        $parsed = [];

        foreach (array_reverse(array_slice($lines, -500)) as $line) {
            if (preg_match('/^\[(\d{2}-\w{3}-\d{4} \d{2}:\d{2}:\d{2} UTC)\] PHP (Fatal error|Warning|Notice|Deprecated):(.+?) in (.+?) on line (\d+)/', $line, $m)) {
                $parsed[] = [
                    'level' => trim($m[2]),
                    'date' => $m[1],
                    'message' => trim($m[3]),
                    'file' => basename($m[4]),
                    'line' => $m[5],
                    'full_file' => $m[4]
                ];
            } elseif (preg_match('/^\[(\d{2}-\w{3}-\d{4} \d{2}:\d{2}:\d{2} UTC)\] (.+)/', $line, $m)) {
                $parsed[] = [
                    'level' => 'Info',
                    'date' => $m[1],
                    'message' => trim($m[2]),
                    'file' => '',
                    'line' => '',
                    'full_file' => ''
                ];
            }
        }

        wp_send_json_success(['logs' => $parsed]);
    }

    public function ajax_update_setting()
    {
        check_ajax_referer('wrb_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'دسترسی غیرمجاز']);
        }

        $key = sanitize_text_field($_POST['key'] ?? '');
        $value = sanitize_text_field($_POST['value'] ?? '');

        if (in_array($key, ['WP_DEBUG', 'WP_MEMORY_LIMIT'])) {
            $result = $this->update_wp_config_setting($key, $value);
        } elseif (in_array($key, ['memory_limit', 'max_execution_time', 'upload_max_filesize', 'post_max_size', 'display_errors'])) {
            $result = $this->update_php_ini_setting($key, $value);
        } else {
            wp_send_json_error(['message' => 'تنظیم نامعتبر']);
        }

        if ($result) {
            wp_send_json_success(['message' => 'تنظیمات ذخیره شد']);
        } else {
            wp_send_json_error(['message' => 'خطا در ذخیره']);
        }
    }

    private function update_wp_config_setting($key, $value)
    {
        $config_file = ABSPATH . 'wp-config.php';
        if (!is_writable($config_file)) return false;

        $content = file_get_contents($config_file);

        if ($key === 'WP_DEBUG') {
            $new_value = ($value === 'روشن' || $value === 'true') ? 'true' : 'false';
            $pattern = "/define\s*\(\s*['\"]WP_DEBUG['\"]\s*,\s*(true|false)\s*\)/";
            $replacement = "define( 'WP_DEBUG', $new_value )";
        } else {
            $pattern = "/define\s*\(\s*['\"]" . preg_quote($key, '/') . "['\"]\s*,\s*['\"]([^'\"]+)['\"]\s*\)/";
            $replacement = "define( '$key', '$value' )";
        }


        if (preg_match($pattern, $content)) {
            $content = preg_replace($pattern, $replacement, $content);
        } else {
            $insert = "\ndefine( '$key', '$value' );\n";
            $content = preg_replace("/<\?php/", "<?php$insert", $content, 1);
        }

        return file_put_contents($config_file, $content) !== false;
    }

    private function update_php_ini_setting($key, $value)
    {
        return @ini_set($key, $value) !== false;
    }

}