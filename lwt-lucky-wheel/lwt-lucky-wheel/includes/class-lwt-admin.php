<?php
if (!defined('ABSPATH')) exit;

class LWT_Admin {

    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
    }

    public static function activate() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        $prizes_table_name = $wpdb->prefix . 'lwt_prizes';
        $sql_prizes = "CREATE TABLE $prizes_table_name (id mediumint(9) NOT NULL AUTO_INCREMENT, name varchar(255) NOT NULL, quantity mediumint(9) NOT NULL, weight mediumint(9) NOT NULL DEFAULT 10, is_losing_prize tinyint(1) NOT NULL DEFAULT 0, PRIMARY KEY  (id)) $charset_collate;";
        $outputs_table_name = $wpdb->prefix . 'lwt_outputs';
        $sql_outputs = "CREATE TABLE $outputs_table_name (id mediumint(9) NOT NULL AUTO_INCREMENT, user_name varchar(255) DEFAULT NULL, user_phone varchar(11) DEFAULT NULL, user_email varchar(255) DEFAULT NULL, prize_won varchar(255) NOT NULL, win_date datetime DEFAULT '0000-00-00 00:00:00' NOT NULL, PRIMARY KEY  (id)) $charset_collate;";
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_prizes);
        dbDelta($sql_outputs);
        
        $default_options = [
            'lwt_form_type' => 'custom_form',
            'lwt_layout_position' => 'right',
            'lwt_custom_form_settings' => ['name_enabled' => 1, 'name_required' => 1, 'email_enabled' => 1, 'email_required' => 0, 'phone_enabled' => 1, 'phone_required' => 1, 'checkbox_enabled' => 1, 'checkbox_text' => 'من شرایط و قوانین را می‌پذیرم.'],
            'lwt_custom_form_button_text' => 'چرخاندن گردونه',
            'lwt_success_message' => 'تبریک! شما برنده {prize_name} شدید!', 'lwt_losing_message'  => 'متاسفانه این بار برنده نشدید. دوباره امتحان کنید!',
            'lwt_wheel_colors' => ['#C4161C', '#2c3e50', '#f39c12'], 'lwt_wheel_size' => 450, 'lwt_font_size' => 16,
            'lwt_pointer_image_url' => '', 'lwt_center_image_url'  => ''
        ];
        foreach ($default_options as $key => $value) { if (get_option($key) === false) { update_option($key, $value); } }
    }

    public function add_admin_menu() {
        add_menu_page('گردونه شانس','گردونه شانس','manage_options','lwt-prizes',[$this, 'prizes_page_html'],'dashicons-awards',25);
        add_submenu_page('lwt-prizes','جوایز','جوایز','manage_options','lwt-prizes',[$this, 'prizes_page_html']);
        add_submenu_page('lwt-prizes','تنظیمات','تنظیمات','manage_options','lwt-settings',[$this, 'settings_page_html']);
        add_submenu_page('lwt-prizes','خروجی','خروجی','manage_options','lwt-outputs',[$this, 'outputs_page_html']);
    }

    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'lwt-') === false && strpos($hook, 'prizes') === false) return;
        wp_enqueue_media();
        wp_enqueue_style('lwt-admin-style', LWT_PLUGIN_URL . 'assets/css/lwt-admin-style.css', [], LWT_VERSION);
        wp_enqueue_script('lwt-admin-script', LWT_PLUGIN_URL . 'assets/js/lwt-admin.js', ['jquery', 'jquery-ui-datepicker'], LWT_VERSION, true);
        wp_localize_script('lwt-admin-script', 'lwt_admin_ajax', ['ajax_url' => admin_url('admin-ajax.php'), 'nonce' => wp_create_nonce('lwt_admin_nonce')]);
    }

    public function register_settings() {
        $settings = ['lwt_form_type', 'lwt_layout_position', 'lwt_custom_form_settings', 'lwt_custom_form_button_text', 'lwt_success_message', 'lwt_losing_message', 'lwt_wheel_colors', 'lwt_wheel_size', 'lwt_font_size', 'lwt_pointer_image_url', 'lwt_center_image_url'];
        foreach($settings as $setting) { register_setting('lwt_settings_group', $setting, ['sanitize_callback' => 'lwt_sanitize_settings']); }
    }

    public function prizes_page_html() {
        ?>
        <div class="wrap" id="lwt-prizes-app">
            <h1 class="wp-heading-inline">مدیریت جوایز</h1>
            <button id="lwt-add-new-prize" class="page-title-action">افزودن جایزه جدید</button>
            <hr class="wp-header-end">
            <div id="lwt-prize-form-modal" class="lwt-modal" style="display:none;">
                <div class="lwt-modal-content">
                    <span class="lwt-close-modal">&times;</span>
                    <h2>افزودن / ویرایش جایزه</h2>
                    <form id="lwt-prize-form">
                        <input type="hidden" id="lwt-prize-id" value="0">
                        <p><label for="lwt-prize-name">نام جایزه</label><input type="text" id="lwt-prize-name" required /></p>
                        <p><label for="lwt-prize-quantity">تعداد</label><input type="number" id="lwt-prize-quantity" min="0" required /></p>
                        <p><label for="lwt-prize-weight">وزن (شانس)</label><input type="number" id="lwt-prize-weight" min="1" value="10" required /></p>
                        <p><label><input type="checkbox" id="lwt-is-losing-prize" value="1"> این آیتم پوچ است (برنده نشدن)</label></p>
                        <button type="submit" class="button button-primary">ذخیره جایزه</button>
                    </form>
                </div>
            </div>
            <table class="wp-list-table widefat fixed striped">
                <thead><tr><th style="width: 35%;">نام جایزه</th><th>تعداد</th><th>وزن</th><th>پوچ؟</th></tr></thead>
                <tbody id="lwt-prizes-list"><tr><td colspan="4">در حال بارگذاری...</td></tr></tbody>
            </table>
        </div>
        <?php
    }

    public function settings_page_html() {
        ?>
        <div class="wrap lwt-settings-wrap">
            <h1>تنظیمات گردونه شانس</h1>
            <h2 class="nav-tab-wrapper">
                <a href="#tab-form" class="nav-tab nav-tab-active">تنظیمات فرم و چیدمان</a>
                <a href="#tab-appearance" class="nav-tab">تنظیمات ظاهری</a>
                <a href="#tab-messages" class="nav-tab">تنظیمات پیام‌ها</a>
            </h2>
            <form method="post" action="options.php">
                <?php settings_fields('lwt_settings_group'); ?>
                <div id="tab-form" class="tab-content active">
                    <table class="form-table">
                        <tr valign="top"><th scope="row">نوع فرم</th><td><select id="lwt_form_type_selector" name="lwt_form_type"><option value="gravity_forms" <?php selected(get_option('lwt_form_type'), 'gravity_forms'); ?>>Gravity Forms</option><option value="custom_form" <?php selected(get_option('lwt_form_type'), 'custom_form'); ?>>فرم سفارشی داخلی</option></select></td></tr>
                        <tr valign="top"><th scope="row">موقعیت فرم نسبت به گردونه</th><td><select name="lwt_layout_position"><option value="right" <?php selected(get_option('lwt_layout_position'), 'right'); ?>>فرم در سمت راست</option><option value="left" <?php selected(get_option('lwt_layout_position'), 'left'); ?>>فرم در سمت چپ</option><option value="top" <?php selected(get_option('lwt_layout_position'), 'top'); ?>>فرم در بالا</option><option value="bottom" <?php selected(get_option('lwt_layout_position'), 'bottom'); ?>>فرم در پایین</option></select></td></tr>
                    </table>
                    <div id="lwt-gravity-form-help" class="<?php echo get_option('lwt_form_type') == 'gravity_forms' ? '' : 'hidden'; ?>">
                        <h3>راهنمای اتصال به Gravity Forms</h3>
                        <ol>
                            <li>یک فرم در Gravity Forms بسازید که حداقل شامل فیلد نام و شماره موبایل باشد.</li>
                            <li>یک فیلد از نوع **"متن تک خطی"** به فرم اضافه کرده و نام آن را "جایزه برنده شده" بگذارید. سپس از تب "نمایش"، این فیلد را **"مخفی" (Hidden)** کنید.</li>
                            <li>شناسه فرم و شناسه‌های هر کدام از این فیلدها را یادداشت کنید.</li>
                            <li>در ویجت المنتور گردونه شانس، این شناسه‌ها را در فیلدهای مربوطه وارد نمایید.</li>
                        </ol>
                    </div>
                    <div id="lwt-custom-form-settings-wrapper" class="<?php echo get_option('lwt_form_type') == 'custom_form' ? '' : 'hidden'; ?>">
                        <h4>فیلدهای فرم سفارشی</h4>
                        <?php $form_settings = get_option('lwt_custom_form_settings'); ?>
                        <table class="form-table">
                            <tr><th>فیلد نام</th><td><label><input type="checkbox" name="lwt_custom_form_settings[name_enabled]" value="1" <?php checked(1, $form_settings['name_enabled'] ?? 0); ?>> فعال</label> | <label><input type="checkbox" name="lwt_custom_form_settings[name_required]" value="1" <?php checked(1, $form_settings['name_required'] ?? 0); ?>> اجباری</label></td></tr>
                            <tr><th>فیلد ایمیل</th><td><label><input type="checkbox" name="lwt_custom_form_settings[email_enabled]" value="1" <?php checked(1, $form_settings['email_enabled'] ?? 0); ?>> فعال</label> | <label><input type="checkbox" name="lwt_custom_form_settings[email_required]" value="1" <?php checked(1, $form_settings['email_required'] ?? 0); ?>> اجباری</label></td></tr>
                            <tr><th>فیلد شماره موبایل</th><td><label><input type="checkbox" name="lwt_custom_form_settings[phone_enabled]" value="1" <?php checked(1, $form_settings['phone_enabled'] ?? 0); ?>> فعال</label> | <label><input type="checkbox" name="lwt_custom_form_settings[phone_required]" value="1" <?php checked(1, $form_settings['phone_required'] ?? 0); ?>> اجباری</label></td></tr>
                            <tr><th>فیلد چک‌باکس</th><td><label><input type="checkbox" name="lwt_custom_form_settings[checkbox_enabled]" value="1" <?php checked(1, $form_settings['checkbox_enabled'] ?? 0); ?>> فعال (اجباری است)</label><br><label>متن چک‌باکس: <input type="text" name="lwt_custom_form_settings[checkbox_text]" value="<?php echo esc_attr($form_settings['checkbox_text'] ?? 'من شرایط و قوانین را می‌پذیرم.'); ?>" class="regular-text"></label></td></tr>
                            <tr><th scope="row"><label for="lwt_custom_form_button_text">متن دکمه فرم</label></th><td><input type="text" name="lwt_custom_form_button_text" value="<?php echo esc_attr(get_option('lwt_custom_form_button_text')); ?>" class="regular-text" /></td></tr>
                        </table>
                    </div>
                </div>
                <div id="tab-appearance" class="tab-content hidden">
                    <h3>تنظیمات ظاهری</h3>
                    <?php $colors = get_option('lwt_wheel_colors', ['#C4161C', '#2c3e50', '#f39c12']); ?>
                    <table class="form-table">
                        <tr><th scope="row">رنگ‌های گردونه</th><td><input type="color" name="lwt_wheel_colors[]" value="<?php echo esc_attr($colors[0]); ?>"> <input type="color" name="lwt_wheel_colors[]" value="<?php echo esc_attr($colors[1]); ?>"> <input type="color" name="lwt_wheel_colors[]" value="<?php echo esc_attr($colors[2]); ?>"></td></tr>
                        <tr><th scope="row"><label for="lwt_wheel_size">اندازه گردونه (px)</label></th><td><input type="number" name="lwt_wheel_size" value="<?php echo esc_attr(get_option('lwt_wheel_size')); ?>" /></td></tr>
                        <tr><th scope="row"><label for="lwt_font_size">اندازه فونت (px)</label></th><td><input type="number" name="lwt_font_size" value="<?php echo esc_attr(get_option('lwt_font_size')); ?>" /></td></tr>
                        <tr><th scope="row"><label for="lwt_pointer_image_url">آدرس تصویر نشانگر</label></th><td><input type="text" name="lwt_pointer_image_url" id="lwt_pointer_image_url" value="<?php echo esc_attr(get_option('lwt_pointer_image_url')); ?>" class="regular-text ltr" /> <button type="button" class="button" id="lwt_upload_pointer_image_button">انتخاب از رسانه</button></td></tr>
                        <tr><th scope="row"><label for="lwt_center_image_url">آدرس تصویر مرکز گردونه</label></th><td><input type="text" name="lwt_center_image_url" id="lwt_center_image_url" value="<?php echo esc_attr(get_option('lwt_center_image_url')); ?>" class="regular-text ltr" /> <button type="button" class="button" id="lwt_upload_center_image_button">انتخاب از رسانه</button></td></tr>
                    </table>
                </div>
                 <div id="tab-messages" class="tab-content hidden">
                    <h3>تنظیمات پیام‌ها</h3>
                    <table class="form-table">
                        <tr><th scope="row"><label for="lwt_success_message">پیام موفقیت</label></th><td><input type="text" name="lwt_success_message" value="<?php echo esc_attr(get_option('lwt_success_message')); ?>" class="large-text" /><p class="description">از `{prize_name}` برای نمایش نام جایزه استفاده کنید.</p></td></tr>
                        <tr><th scope="row"><label for="lwt_losing_message">پیام پوچ</label></th><td><input type="text" name="lwt_losing_message" value="<?php echo esc_attr(get_option('lwt_losing_message')); ?>" class="large-text" /></td></tr>
                    </table>
                </div>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    public function outputs_page_html() {
        global $wpdb;
        ?>
        <div class="wrap">
            <h1>خروجی برندگان</h1>
            <form method="get" class="lwt-export-form">
                <input type="hidden" name="page" value="lwt-outputs">
                <label for="start_date">از تاریخ:</label>
                <input type="text" class="lwt-datepicker" id="start_date" name="start_date" value="<?php echo isset($_GET['start_date']) ? esc_attr($_GET['start_date']) : ''; ?>">
                <label for="end_date">تا تاریخ:</label>
                <input type="text" class="lwt-datepicker" id="end_date" name="end_date" value="<?php echo isset($_GET['end_date']) ? esc_attr($_GET['end_date']) : ''; ?>">
                <input type="submit" value="فیلتر" class="button">
                <button name="export_csv" value="1" class="button button-primary">خروجی CSV</button>
            </form>
            <table class="wp-list-table widefat fixed striped">
                <thead><tr><th>نام</th><th>ایمیل</th><th>موبایل</th><th>جایزه</th><th>تاریخ</th></tr></thead>
                <tbody>
                    <?php
                    $where_clauses = [];
                    if (!empty($_GET['start_date'])) $where_clauses[] = $wpdb->prepare("win_date >= %s", date('Y-m-d 00:00:00', strtotime($_GET['start_date'])));
                    if (!empty($_GET['end_date'])) $where_clauses[] = $wpdb->prepare("win_date <= %s", date('Y-m-d 23:59:59', strtotime($_GET['end_date'])));
                    $where_sql = count($where_clauses) > 0 ? " WHERE " . implode(' AND ', $where_clauses) : '';
                    $outputs = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}lwt_outputs" . $where_sql . " ORDER BY win_date DESC");
                    if ($outputs): foreach ($outputs as $output): ?>
                    <tr><td><?php echo esc_html($output->user_name); ?></td><td><?php echo esc_html($output->user_email); ?></td><td><?php echo esc_html($output->user_phone); ?></td><td><?php echo esc_html($output->prize_won); ?></td><td><?php echo date_i18n('Y/m/d H:i:s', strtotime($output->win_date)); ?></td></tr>
                    <?php endforeach; else: ?><tr><td colspan="5">هیچ ورودی‌ای یافت نشد.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public function help_page_html() {
        ?>
        <div class="wrap">
            <h1>راهنمای استفاده</h1>
            <h2>استفاده از ویجت المنتور</h2>
            <p>۱. به صفحه مورد نظر در ویرایشگر المنتور بروید.</p>
            <p>۲. از لیست ویجت‌ها، ویجت «گردونه شانس» را پیدا کرده و به صفحه بکشید.</p>
            <p>۳. تمام تنظیمات مورد نیاز از جمله انتخاب نوع فرم (گراویتی یا سفارشی)، چیدمان و شناسه‌های مربوطه در خود ویجت قرار دارد.</p>
            <p><strong>نکته مهم:</strong> تنظیمات ویجت المنتور همیشه بر تنظیمات عمومی افزونه اولویت دارند.</p>
            <h2>استفاده از شورت‌کد</h2>
            <p>از شورت‌کد زیر می‌توانید در بخش‌های مختلف سایت (خارج از المنتور) استفاده کنید.</p>
            <p><strong>برای اتصال به Gravity Forms:</strong></p>
            <code>[lucky_wheel form_id="1" name_field_id="2" phone_field_id="3" prize_field_id="5"]</code>
            <p><strong>برای استفاده از فرم سفارشی داخلی:</strong></p>
            <code>[lucky_wheel]</code>
        </div>
        <?php
    }
}

function lwt_sanitize_settings($input) {
    // This is a simplified sanitizer. A more robust one would check each key.
    if (is_array($input)) {
        foreach ($input as $key => &$value) {
            if (is_array($value)) {
                $value = lwt_sanitize_settings($value);
            } else {
                $value = sanitize_text_field($value);
            }
        }
    }
    return $input;
}
```

<immersive type="code" title="✅ includes/class-lwt-ajax.php">
```php
<?php
if (!defined('ABSPATH')) exit;

class LWT_Ajax {

    public function __construct() {
        add_action('wp_ajax_lwt_get_prizes', [$this, 'get_prizes']);
        add_action('wp_ajax_lwt_save_prize', [$this, 'save_prize']);
        add_action('wp_ajax_lwt_delete_prize', [$this, 'delete_prize']);
        add_action('wp_ajax_lwt_custom_form_spin', [$this, 'custom_form_spin']);
        add_action('wp_ajax_nopriv_lwt_custom_form_spin', [$this, 'custom_form_spin']);
        add_action('wp_ajax_lwt_get_spin_result', [$this, 'get_spin_result']);
        add_action('wp_ajax_nopriv_lwt_get_spin_result', [$this, 'get_spin_result']);
    }

    private function check_admin_nonce() {
        if (!check_ajax_referer('lwt_admin_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Nonce verification failed.'], 403);
        }
    }
    
    public function get_prizes() {
        $this->check_admin_nonce();
        global $wpdb;
        $prizes = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}lwt_prizes ORDER BY id DESC");
        wp_send_json_success($prizes);
    }

    public function save_prize() {
        $this->check_admin_nonce();
        global $wpdb;
        $table_name = $wpdb->prefix . 'lwt_prizes';
        
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $name = sanitize_text_field($_POST['name']);
        $quantity = intval($_POST['quantity']);
        $weight = intval($_POST['weight']);
        $is_losing = isset($_POST['is_losing']) && $_POST['is_losing'] === 'true' ? 1 : 0;

        if (empty($name) || $quantity < 0 || $weight <= 0) {
            wp_send_json_error(['message' => 'اطلاعات نامعتبر است.']);
        }

        $data = ['name' => $name, 'quantity' => $quantity, 'weight' => $weight, 'is_losing_prize' => $is_losing];

        if ($id > 0) {
            $wpdb->update($table_name, $data, ['id' => $id]);
        } else {
            $wpdb->insert($table_name, $data);
        }
        wp_send_json_success(['message' => 'جایزه با موفقیت ذخیره شد.']);
    }

    public function delete_prize() {
        $this->check_admin_nonce();
        global $wpdb;
        $id = intval($_POST['id']);
        $wpdb->delete($wpdb->prefix . 'lwt_prizes', ['id' => $id]);
        wp_send_json_success(['message' => 'جایزه حذف شد.']);
    }

    public function custom_form_spin() {
        check_ajax_referer('lwt_spin_nonce', 'nonce');
        global $wpdb;
        $outputs_table = $wpdb->prefix . 'lwt_outputs';
        $prizes_table = $wpdb->prefix . 'lwt_prizes';
        $settings = get_option('lwt_custom_form_settings');

        $name = !empty($settings['name_enabled']) && isset($_POST['name']) ? sanitize_text_field($_POST['name']) : null;
        $email = !empty($settings['email_enabled']) && isset($_POST['email']) ? sanitize_email($_POST['email']) : null;
        $phone = !empty($settings['phone_enabled']) && isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : null;

        if (!empty($settings['phone_required']) && !preg_match('/^09[0-9]{9}$/', $phone)) { wp_send_json_error(['message' => 'لطفا شماره موبایل معتبر وارد کنید.']); }
        if (!empty($settings['email_required']) && !is_email($email)) { wp_send_json_error(['message' => 'لطفا ایمیل معتبر وارد کنید.']); }
        if (!empty($settings['name_required']) && empty($name)) { wp_send_json_error(['message' => 'لطفا نام خود را وارد کنید.']); }
        if (!empty($settings['checkbox_enabled']) && !isset($_POST['terms'])) { wp_send_json_error(['message' => 'شما باید با شرایط و قوانین موافقت کنید.']); }

        if ($phone) {
            $existing_winner_count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $outputs_table WHERE user_phone = %s", $phone));
            if ($existing_winner_count > 0) { wp_send_json_error(['message' => 'این شماره موبایل قبلاً شرکت کرده است.']); }
        }

        $prizes = $wpdb->get_results("SELECT * FROM $prizes_table WHERE quantity > 0");
        if (empty($prizes)) { wp_send_json_error(['message' => 'متاسفانه تمام جوایز به اتمام رسیده است.']); }

        $weighted_list = [];
        foreach ($prizes as $prize) { for ($i = 0; $i < $prize->weight; $i++) { $weighted_list[] = $prize; } }
        $winner_prize = $weighted_list[array_rand($weighted_list)];
        
        $is_winner = !$winner_prize->is_losing_prize;

        if ($winner_prize->quantity < 99999) { // Don't decrease "infinite" prizes like 'losing' ones
            $wpdb->update($prizes_table, ['quantity' => $winner_prize->quantity - 1], ['id' => $winner_prize->id]);
        }
        $wpdb->insert($outputs_table, ['user_name' => $name, 'user_phone' => $phone, 'user_email' => $email, 'prize_won' => $winner_prize->name, 'win_date' => current_time('mysql')]);
        
        wp_send_json_success(['prize_name' => $winner_prize->name, 'is_winner' => $is_winner]);
    }

    public function get_spin_result() {
        check_ajax_referer('lwt_get_result_nonce', 'nonce');
        $entry_id = isset($_POST['entry_id']) ? intval($_POST['entry_id']) : 0;
        if (!$entry_id) { wp_send_json_error(['message' => 'شناسه ورودی نامعتبر است.']); }
        $transient_key = 'lwt_result_' . $entry_id;
        $prize_data = get_transient($transient_key);
        if ($prize_data !== false) {
            delete_transient($transient_key);
            wp_send_json_success($prize_data);
        } else {
            wp_send_json_error(['message' => 'نتیجه یافت نشد یا منقضی شده است.']);
        }
    }
}
```

<immersive type="code" title="✅ includes/class-lwt-frontend.php">
```php
<?php
if (!defined('ABSPATH')) exit;

class LWT_Frontend {

    public function __construct() {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_shortcode('lucky_wheel', [$this, 'render_shortcode']);
        
        // Gravity Forms specific hooks
        add_action('gform_after_submission', [$this, 'process_gf_submission'], 10, 2);
        add_filter('gform_confirmation', [$this, 'add_entry_id_to_confirmation'], 20, 3);
    }
    
    public function enqueue_scripts() {
        wp_enqueue_style('lwt-style', LWT_PLUGIN_URL . 'assets/css/lwt-style.css', [], LWT_VERSION);
        wp_enqueue_script('lwt-gsap', LWT_PLUGIN_URL . 'assets/js/gsap.min.js', [], '3.12.5', true);
        wp_enqueue_script('lwt-winwheel', LWT_PLUGIN_URL . 'assets/js/winwheel.min.js', ['lwt-gsap'], '2.8', true);
        wp_enqueue_script('lwt-main', LWT_PLUGIN_URL . 'assets/js/lwt-main.js', ['jquery', 'lwt-winwheel'], LWT_VERSION, true);
        
        global $wpdb;
        $prizes = $wpdb->get_results("SELECT name FROM {$wpdb->prefix}lwt_prizes WHERE quantity > 0", ARRAY_A);
        
        $settings = [
            'ajax_url'    => admin_url('admin-ajax.php'),
            'nonce'       => wp_create_nonce('lwt_spin_nonce'),
            'gf_nonce'    => wp_create_nonce('lwt_get_result_nonce'),
            'form_type'   => get_option('lwt_form_type', 'gravity_forms'),
            'prizes'      => $prizes, 'colors' => get_option('lwt_wheel_colors'), 
            'font_size'   => get_option('lwt_font_size'), 'wheel_size'  => get_option('lwt_wheel_size'),
            'success_message' => get_option('lwt_success_message'),
            'losing_message'  => get_option('lwt_losing_message')
        ];
        wp_localize_script('lwt-main', 'lwt_settings', $settings);
    }

    public function render_shortcode($atts) {
        if (get_option('lwt_form_type') == 'gravity_forms') {
             wp_add_inline_script('lwt-main', 'var lwt_gf_config = ' . json_encode($atts) . ';', 'before');
        }
        $layout = get_option('lwt_layout_position', 'right');
        $html = '<div class="lwt-container lwt-layout-'.$layout.'">';
        $html .= $this->get_wheel_html();
        if (get_option('lwt_form_type') == 'custom_form') {
            $html .= $this->render_custom_form();
        }
        $html .= '</div>';
        return $html;
    }

    public function get_wheel_html($is_elementor = false) {
        $pointer_image_url = get_option('lwt_pointer_image_url');
        $center_image_url = get_option('lwt_center_image_url');
        $wheel_size = get_option('lwt_wheel_size', 450);
        ob_start();
        ?>
        <div class="lwt-wheel-column">
            <div id="lwt-wheel-ui-container" style="--wheel-size: <?php echo esc_attr($wheel_size); ?>px;">
                <div id="lwt-main-container">
                    <div id="lwt-pointer-container">
                        <?php if (!empty($pointer_image_url)): ?><img src="<?php echo esc_url($pointer_image_url); ?>" alt="Pointer"><?php endif; ?>
                    </div>
                    <div id="lwt-wheel-container">
                        <div id="lwt-static-ring"></div>
                        <div id="lwt-static-dots"></div>
                        <div id="lwt-wheel-wrapper">
                            <canvas id="lwt-canvas" width="<?php echo esc_attr($wheel_size); ?>" height="<?php echo esc_attr($wheel_size); ?>"></canvas>
                            <div id="lwt-center-image-container">
                                 <?php if (!empty($center_image_url)): ?><img src="<?php echo esc_url($center_image_url); ?>" alt="Center Logo"><?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="lwt-message" style="display:none; margin-top: 20px;"></div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function render_custom_form() {
        $settings = get_option('lwt_custom_form_settings');
        ob_start();
        ?>
        <div class="lwt-form-column">
            <div id="lwt-form-wrapper">
                <form id="lwt-custom-form">
                    <?php if (!empty($settings['name_enabled'])): ?>
                        <div class="lwt-form-field"><input type="text" name="name" placeholder="نام و نام خانوادگی" <?php if(!empty($settings['name_required'])) echo 'required'; ?>></div>
                    <?php endif; ?>
                    <?php if (!empty($settings['email_enabled'])): ?>
                        <div class="lwt-form-field"><input type="email" name="email" placeholder="ایمیل" <?php if(!empty($settings['email_required'])) echo 'required'; ?>></div>
                    <?php endif; ?>
                    <?php if (!empty($settings['phone_enabled'])): ?>
                        <div class="lwt-form-field"><input type="tel" name="phone" placeholder="شماره موبایل (09...)" pattern="09[0-9]{9}" maxlength="11" <?php if(!empty($settings['phone_required'])) echo 'required'; ?>></div>
                    <?php endif; ?>
                    <?php if (!empty($settings['checkbox_enabled'])): ?>
                        <div class="lwt-form-field lwt-checkbox-field"><label><input type="checkbox" name="terms" required> <?php echo esc_html($settings['checkbox_text']); ?></label></div>
                    <?php endif; ?>
                    <button type="submit" class="lwt-spin-button"><?php echo esc_html(get_option('lwt_custom_form_button_text', 'چرخاندن گردونه')); ?></button>
                </form>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function process_gf_submission($entry, $form) {
        if (isset($_SESSION['lwt_shortcode_attributes_for_form_' . $form['id']])) {
            $shortcode_atts = $_SESSION['lwt_shortcode_attributes_for_form_' . $form['id']];
            global $wpdb;
            $prizes_table = $wpdb->prefix . 'lwt_prizes';
            $outputs_table = $wpdb->prefix . 'lwt_outputs';
            $prizes = $wpdb->get_results("SELECT * FROM $prizes_table WHERE quantity > 0");
            if (empty($prizes)) return;
            $weighted_list = [];
            foreach ($prizes as $prize) { for ($i = 0; $i < $prize->weight; $i++) { $weighted_list[] = $prize; } }
            $winner_prize = $weighted_list[array_rand($weighted_list)];
            $is_winner = !$winner_prize->is_losing_prize;
            if ($is_winner) {
                 if ($winner_prize->quantity < 99999) { $wpdb->update($prizes_table, ['quantity' => $winner_prize->quantity - 1], ['id' => $winner_prize->id]); }
                $name = rgar($entry, $shortcode_atts['name_field_id']);
                $phone = rgar($entry, $shortcode_atts['phone_field_id']);
                $wpdb->insert($outputs_table, ['user_name' => $name, 'user_phone' => $phone, 'prize_won' => $winner_prize->name, 'win_date' => current_time('mysql')]);
            }
            gform_update_meta($entry['id'], $shortcode_atts['prize_field_id'], $winner_prize->name);
            $transient_data = ['prize_name' => $winner_prize->name, 'is_winner' => $is_winner];
            set_transient('lwt_result_' . $entry['id'], $transient_data, 60);
            unset($_SESSION['lwt_shortcode_attributes_for_form_' . $form['id']]);
        }
    }
    public function add_entry_id_to_confirmation($confirmation, $form, $entry) {
        if (isset($_SESSION['lwt_shortcode_attributes_for_form_' . $form['id']])) {
            if (is_string($confirmation) && strpos($confirmation, "id='gform_confirmation_wrapper_") !== false) {
                $data_attribute = " data-lwt-entry-id='" . esc_attr($entry['id']) . "'";
                $id_string = "id='gform_confirmation_wrapper_{$form['id']}'";
                $confirmation = str_replace($id_string, $id_string . $data_attribute, $confirmation);
            }
        }
        return $confirmation;
    }
}
```

<immersive type="code" title="✅ includes/elementor/elementor-init.php">
```php
<?php
if (!defined('ABSPATH')) exit;

class LWT_Elementor_Init {

    public function __construct() {
        add_action('elementor/widgets/register', [$this, 'register_widgets']);
    }

    public function register_widgets($widgets_manager) {
        require_once LWT_PLUGIN_PATH . 'includes/elementor/class-lwt-elementor-widget.php';
        $widgets_manager->register(new LWT_Elementor_Widget());
    }
}
```

<immersive type="code" title="✅ includes/elementor/class-lwt-elementor-widget.php">
```php
<?php
if (!defined('ABSPATH')) exit;

class LWT_Elementor_Widget extends \Elementor\Widget_Base {

    public function get_name() { return 'lwt_lucky_wheel'; }
    public function get_title() { return __('گردونه شانس', 'lwt-lucky-wheel'); }
    public function get_icon() { return 'eicon-rating'; }
    public function get_categories() { return ['general']; }

    protected function register_controls() {
        $this->start_controls_section('content_section', ['label' => __('تنظیمات اصلی', 'lwt-lucky-wheel')]);
        $this->add_control('form_type', ['label' => __('نوع فرم', 'lwt-lucky-wheel'), 'type' => \Elementor\Controls_Manager::SELECT, 'default' => get_option('lwt_form_type', 'custom_form'), 'options' => ['custom_form'  => __('فرم سفارشی داخلی', 'lwt-lucky-wheel'), 'gravity_forms' => __('Gravity Forms', 'lwt-lucky-wheel')]]);
        $this->add_control('gf_form_id', ['label' => __( 'انتخاب فرم', 'lwt-lucky-wheel' ), 'type' => \Elementor\Controls_Manager::SELECT, 'options' => $this->get_gravity_forms(), 'condition' => ['form_type' => 'gravity_forms']]);
        $this->add_control('gf_name_field_id', ['label' => 'ID فیلد نام', 'type' => \Elementor\Controls_Manager::NUMBER, 'condition' => ['form_type' => 'gravity_forms']]);
        $this->add_control('gf_phone_field_id', ['label' => 'ID فیلد موبایل', 'type' => \Elementor\Controls_Manager::NUMBER, 'condition' => ['form_type' => 'gravity_forms']]);
        $this->add_control('gf_prize_field_id', ['label' => 'ID فیلد جایزه (مخفی)', 'type' => \Elementor\Controls_Manager::NUMBER, 'condition' => ['form_type' => 'gravity_forms']]);
        $this->end_controls_section();

        $this->start_controls_section('layout_section', ['label' => __('تنظیمات چیدمان و ظاهر', 'lwt-lucky-wheel')]);
        $this->add_control('layout_position', ['label' => 'موقعیت فرم نسبت به گردونه', 'type' => \Elementor\Controls_Manager::SELECT, 'default' => '', 'options' => ['' => 'پیش‌فرض تنظیمات', 'right' => 'راست', 'left' => 'چپ', 'top' => 'بالا', 'bottom' => 'پایین'], 'description' => 'این تنظیمات، مقدار پیش‌فرض در صفحه تنظیمات را لغو می‌کند.']);
        $this->add_control('wheel_size', ['label' => 'اندازه گردونه (px)', 'type' => \Elementor\Controls_Manager::NUMBER, 'placeholder' => get_option('lwt_wheel_size'), 'description' => 'این تنظیمات، مقدار پیش‌فرض در صفحه تنظیمات را لغو می‌کند.']);
        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        $frontend = new LWT_Frontend();
        
        $layout = !empty($settings['layout_position']) ? $settings['layout_position'] : get_option('lwt_layout_position', 'right');
        
        echo '<div class="lwt-elementor-widget-wrapper">';
        echo '<div class="lwt-container lwt-layout-'.$layout.'">';
        echo '<div class="lwt-wheel-column">' . $frontend->get_wheel_html(true) . '</div>';

        echo '<div class="lwt-form-column">';
        if ($settings['form_type'] === 'gravity_forms' && !empty($settings['gf_form_id'])) {
            $shortcode_atts = [
                'form_id' => $settings['gf_form_id'],
                'name_field_id' => $settings['gf_name_field_id'],
                'phone_field_id' => $settings['gf_phone_field_id'],
                'prize_field_id' => $settings['gf_prize_field_id'],
            ];
            $_SESSION['lwt_shortcode_attributes_for_form_' . $settings['gf_form_id']] = $shortcode_atts;
             wp_add_inline_script('lwt-main', 'var lwt_gf_config = ' . json_encode($shortcode_atts) . ';', 'before');
            echo do_shortcode('[gravityform id="' . $settings['gf_form_id'] . '" title="false" description="false" ajax="true"]');
        } elseif ($settings['form_type'] === 'custom_form') {
            echo $frontend->render_custom_form();
        }
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }

    private function get_gravity_forms() {
        if (class_exists('GFAPI')) {
            $forms = GFAPI::get_forms();
            $options = ['' => __('یک فرم انتخاب کنید', 'lwt-lucky-wheel')];
            foreach ($forms as $form) { $options[$form['id']] = $form['title']; }
            return $options;
        }
        return ['' => __('Gravity Forms نصب نیست', 'lwt-lucky-wheel')];
    }
}
```

<immersive type="code" title="✅ assets/js/lwt-admin.js">
```javascript
jQuery(document).ready(function($) {
    // Tabs in settings page
    $('.lwt-settings-wrap .nav-tab').on('click', function(e) {
        e.preventDefault();
        $('.nav-tab').removeClass('nav-tab-active');
        $('.tab-content').removeClass('active').addClass('hidden');
        $(this).addClass('nav-tab-active');
        $($(this).attr('href')).addClass('active').removeClass('hidden');
    });

    // Toggle form settings based on selection
    $('#lwt_form_type_selector').on('change', function() {
        if ($(this).val() === 'custom_form') {
            $('#lwt-custom-form-settings-wrapper').removeClass('hidden');
            $('#lwt-gravity-form-help').addClass('hidden');
        } else {
            $('#lwt-custom-form-settings-wrapper').addClass('hidden');
            $('#lwt-gravity-form-help').removeClass('hidden');
        }
    }).trigger('change');

    // Media Uploader
    function initialize_media_uploader(button_id, input_id) {
        $(document).on('click', button_id, function(e) {
            e.preventDefault();
            var image_frame = wp.media({ title: 'Select Media', multiple: false, library: { type: 'image' } });
            image_frame.on('select', function() {
                var media_attachment = image_frame.state().get('selection').first().toJSON();
                $(input_id).val(media_attachment.url);
            });
            image_frame.open();
        });
    }
    initialize_media_uploader('#lwt_upload_center_image_button', '#lwt_center_image_url');
    initialize_media_uploader('#lwt_upload_pointer_image_button', '#lwt_pointer_image_url');

    // AJAX Prize Management
    const prizeModal = $('#lwt-prize-form-modal');
    const prizeForm = $('#lwt-prize-form');
    const prizeList = $('#lwt-prizes-list');
    const prizeIdField = $('#lwt-prize-id');
    const prizeNameField = $('#lwt-prize-name');
    const prizeQuantityField = $('#lwt-prize-quantity');
    const prizeWeightField = $('#lwt-prize-weight');
    const isLosingPrizeField = $('#lwt-is-losing-prize');

    function loadPrizes() {
        prizeList.html('<tr><td colspan="5">در حال بارگذاری...</td></tr>');
        $.ajax({
            url: lwt_admin_ajax.ajax_url,
            type: 'POST',
            data: { action: 'lwt_get_prizes', nonce: lwt_admin_ajax.nonce },
            success: function(response) {
                if (response.success) {
                    renderPrizes(response.data);
                }
            }
        });
    }

    function renderPrizes(prizes) {
        prizeList.empty();
        if (prizes && prizes.length > 0) {
            prizes.forEach(function(prize) {
                const row = `
                    <tr class="entry-row" data-id="${prize.id}" data-name="${prize.name}" data-quantity="${prize.quantity}" data-weight="${prize.weight}" data-is-losing="${prize.is_losing_prize}">
                        <td class="title column-title has-row-actions column-primary">
                            <strong><a class="row-title" href="#">${prize.name}</a></strong>
                            <div class="row-actions">
                                <span class="edit"><a href="#" class="edit-prize">ویرایش</a> | </span>
                                <span class="trash"><a href="#" class="delete-prize">حذف</a></span>
                            </div>
                        </td>
                        <td>${prize.quantity}</td>
                        <td>${prize.weight}</td>
                        <td>${prize.is_losing_prize == '1' ? 'بله' : 'خیر'}</td>
                    </tr>`;
                prizeList.append(row);
            });
        } else {
            prizeList.html('<tr><td colspan="5">هیچ جایزه‌ای یافت نشد.</td></tr>');
        }
    }

    prizeForm.on('submit', function(e) {
        e.preventDefault();
        const prizeData = {
            action: 'lwt_save_prize', nonce: lwt_admin_ajax.nonce,
            id: prizeIdField.val(), name: prizeNameField.val(),
            quantity: prizeQuantityField.val(), weight: prizeWeightField.val(),
            is_losing: isLosingPrizeField.is(':checked')
        };
        $.ajax({
            url: lwt_admin_ajax.ajax_url,
            type: 'POST',
            data: prizeData,
            success: function(response) {
                if (response.success) {
                    closeModal();
                    loadPrizes();
                } else {
                    alert(response.data.message);
                }
            }
        });
    });

    prizeList.on('click', '.edit-prize', function(e) {
        e.preventDefault();
        const row = $(this).closest('tr');
        prizeIdField.val(row.data('id'));
        prizeNameField.val(row.data('name'));
        prizeQuantityField.val(row.data('quantity'));
        prizeWeightField.val(row.data('weight'));
        isLosingPrizeField.prop('checked', row.data('is-losing') == '1');
        openModal();
    });

    prizeList.on('click', '.delete-prize', function(e) {
        e.preventDefault();
        if (!confirm('آیا از حذف این جایزه مطمئن هستید؟')) return;
        const id = $(this).closest('tr').data('id');
        $.ajax({
            url: lwt_admin_ajax.ajax_url,
            type: 'POST',
            data: { action: 'lwt_delete_prize', nonce: lwt_admin_ajax.nonce, id: id },
            success: function(response) {
                if (response.success) {
                    loadPrizes();
                }
            }
        });
    });

    $('#lwt-add-new-prize').on('click', function() { openModal(); });
    $('.lwt-close-modal').on('click', function() { closeModal(); });
    $(window).on('click', function(e) { if ($(e.target).is(prizeModal)) { closeModal(); } });

    function openModal() { prizeForm[0].reset(); prizeIdField.val(0); prizeModal.show(); }
    function closeModal() { prizeModal.hide(); prizeForm[0].reset(); prizeIdField.val(0); }

    if ($('#lwt-prizes-app').length) {
        loadPrizes();
    }
});
```

<immersive type="code" title="✅ assets/js/lwt-main.js">
```javascript
var theWheel; // Make the wheel object global

jQuery(document).ready(function($) {
    if ($('#lwt-container-wrapper').length === 0) return;
    if (typeof lwt_settings === 'undefined' || !lwt_settings.prizes || !lwt_settings.prizes.length) {
        $('#lwt-container-wrapper').html('<p class="lwt-error-message">گردونه در دسترس نیست. لطفاً جوایز را در پنل مدیریت تعریف کنید.</p>');
        return;
    }
    
    initializeWheel();

    // Handler for Gravity Forms
    if (lwt_settings.form_type === 'gravity_forms') {
        $(document).on('gform_confirmation_loaded', function(event, formId){
            if (typeof lwt_gf_config !== 'undefined' && formId == lwt_gf_config.form_id) {
                const confirmationWrapper = $('#gform_confirmation_wrapper_' + formId);
                const entryId = confirmationWrapper.data('lwt-entry-id');
                if (!entryId) { console.error('Lucky Wheel: Could not find Entry ID.'); return; }
                $('#gform_wrapper_' + formId).slideUp();
                $.ajax({
                    url: lwt_settings.ajax_url, type: 'POST',
                    data: { action: 'lwt_get_spin_result', nonce: lwt_settings.gf_nonce, entry_id: entryId },
                    success: function(response) {
                        if (response.success) { startTheWheelAnimation(response.data); } 
                        else { showMessage(response.data.message || 'نتیجه‌ای برای چرخاندن یافت نشد.', 'error'); }
                    },
                    error: function() { showMessage('خطا در دریافت نتیجه قرعه‌کشی.', 'error'); }
                });
            }
        });
    } 
    // Handler for our Custom Form
    else if (lwt_settings.form_type === 'custom_form') {
        $('#lwt-custom-form').on('submit', function(e) {
            e.preventDefault();
            let formData = $(this).serialize();
            let spinButton = $(this).find('button[type="submit"]');
            
            spinButton.prop('disabled', true).text('در حال بررسی...');
            
            $.ajax({
                url: lwt_settings.ajax_url,
                type: 'POST',
                data: formData + '&action=lwt_custom_form_spin&nonce=' + lwt_settings.nonce,
                success: function(response) {
                    if (response.success) {
                        spinButton.hide();
                        startTheWheelAnimation(response.data);
                    } else {
                        showMessage(response.data.message, 'error');
                        spinButton.prop('disabled', false).text('دوباره امتحان کن');
                    }
                },
                error: function() {
                    showMessage('خطای سرور رخ داد.', 'error');
                    spinButton.prop('disabled', false).text('دوباره امتحان کن');
                }
            });
        });
    }
});

function initializeWheel() {
    let segments = [];
    for (let i = 0; i < lwt_settings.prizes.length; i++) {
        segments.push({ 'fillStyle': lwt_settings.colors[i % lwt_settings.colors.length], 'text': lwt_settings.prizes[i].name });
    }
    const wheelSize = parseInt(lwt_settings.wheel_size);
    theWheel = new Winwheel({
        'canvasId': 'lwt-canvas', 'pointerAngle': 90, 'numSegments': segments.length, 'segments': segments,
        'outerRadius': (wheelSize / 2) * 0.97, 'innerRadius': (wheelSize / 2) * 0.15, 
        'textFontSize': parseInt(lwt_settings.font_size), 'textFontFamily': 'Vazirmatn, sans-serif',
        'textFillStyle': '#ffffff', 'textMargin': 20, 'lineWidth': 0, 'strokeStyle': 'transparent',
        'textOrientation': 'horizontal', 'textAlignment': 'outer',
        'animation': { 'type': 'spinToStop', 'duration': 10, 'spins': 12, 'callbackFinished': alertPrize }
    });
    createStaticDots();
}

function createStaticDots() {
    const numSegments = theWheel.numSegments; if (numSegments === 0) return;
    const dotsContainer = jQuery('#lwt-static-dots'); dotsContainer.empty();
    const angleStep = 360 / numSegments;
    for (let i = 0; i < numSegments; i++) {
        const dotAngle = i * angleStep;
        const dot = jQuery('<div class="lwt-static-dot"></div>');
        dot.css('transform', `rotate(${dotAngle}deg) translate(-50%, -50%)`);
        dotsContainer.append(dot);
    }
}

function startTheWheelAnimation(prizeData) {
    const messageBox = jQuery('#lwt-message');
    const winningSegment = theWheel.segments.find(s => s && s.text === prizeData.prize_name);
    if (winningSegment) {
         let middleAngle = winningSegment.startAngle + ((winningSegment.endAngle - winningSegment.startAngle) / 2);
         theWheel.animation.stopAngle = middleAngle;
         theWheel.startAnimation();
         messageBox.data('prize_data', prizeData);
    } else { showMessage('خطای داخلی: جایزه یافت نشد.', 'error'); }
}

function alertPrize() {
    const messageBox = jQuery('#lwt-message');
    const prizeData = messageBox.data('prize_data');
    const successMessage = lwt_settings.success_message || 'تبریک! شما برنده {prize_name} شدید!';
    const losingMessage = lwt_settings.losing_message || 'متاسفانه برنده نشدید.';

    if (prizeData.is_winner) {
        showMessage(successMessage.replace('{prize_name}', '<strong>' + prizeData.prize_name + '</strong>'), 'success');
    } else {
        showMessage(losingMessage, 'error');
    }
}

function showMessage(message, type) {
    const messageBox = jQuery('#lwt-message');
    messageBox.html(message).removeClass('success error').addClass(type).show();
}
```

<immersive type="code" title="✅ assets/css/lwt-admin-style.css">
```css
/* Admin Settings Tabs */
.lwt-settings-wrap .nav-tab-wrapper { margin-bottom: 20px; }
.lwt-settings-wrap .tab-content { display: none; padding-top: 10px; }
.lwt-settings-wrap .tab-content.active { display: block; }
.lwt-settings-wrap .hidden { display: none; }

/* AJAX Prize Table Modal */
.lwt-modal { position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.4); }
.lwt-modal-content { background-color: #fefefe; margin: 15% auto; padding: 20px; border: 1px solid #888; width: 80%; max-width: 500px; border-radius: 5px; }
.lwt-close-modal { color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer; }
#lwt-prize-form p { margin-bottom: 15px; }
#lwt-prize-form label { display: block; font-weight: bold; margin-bottom: 5px; }
#lwt-prize-form input[type="text"],
#lwt-prize-form input[type="number"] { width: 100%; padding: 8px; }

/* AJAX Prize Table Layout */
@media (min-width: 783px) {
    #lwt-prizes-app { display: grid; grid-template-columns: 1fr; gap: 20px; }
}
.lwt-export-form { margin-bottom: 20px; }
.ltr { direction: ltr; text-align: left; }
.row-actions { left: auto; right: 0; }
```

<immersive type="code" title="✅ assets/css/lwt-style.css">
```css
/* Main Container for Wheel + Form */
.lwt-container, .lwt-elementor-widget-wrapper > .elementor-widget-container > .lwt-container {
    max-width: 100%;
    margin: 20px auto;
    font-family: 'Vazirmatn', sans-serif;
    display: flex;
    align-items: center;
    gap: 30px;
}
.lwt-container.lwt-layout-left { flex-direction: row-reverse; }
.lwt-container.lwt-layout-top { flex-direction: column-reverse; }
.lwt-container.lwt-layout-bottom { flex-direction: column; }

.lwt-wheel-column { flex: 1 1 60%; min-width: 280px; }
.lwt-form-column { flex: 1 1 40%; max-width: 400px; margin: 0 auto; }

/* Wheel Styles */
#lwt-wheel-ui-container, #lwt-container-wrapper { --wheel-size: 450px; max-width: var(--wheel-size); margin: 0 auto; }
#lwt-main-container { position: relative; margin-bottom: 20px;}
#lwt-pointer-container { position: absolute; width: 15%; height: 18%; top: -9%; left: 50%; transform: translateX(-50%); z-index: 20; display: flex; align-items: center; justify-content: center; }
#lwt-pointer-container img { max-width: 100%; max-height: 100%; filter: drop-shadow(0px 3px 3px rgba(0,0,0,0.2)); }
#lwt-wheel-container { position: relative; width: 100%; padding-top: 100%; height: 0; }
#lwt-static-ring { position: absolute; top: 0; left: 0; width: 100%; height: 100%; border-radius: 50%; background-color: #ffffff; box-shadow: 4px 4px 18px rgba(0, 0, 0, 0.2), inset 2px 2px 8px rgba(0, 0, 0, 0.2); box-sizing: border-box; }
#lwt-static-dots { position: absolute; top: 0; left: 0; width: 100%; height: 100%; z-index: 10; }
.lwt-static-dot { position: absolute; top: 50%; left: 50%; width: 10px; height: 10px; margin-left: -5px; margin-top: calc(var(--wheel-size) / 2 * -0.97); background-color: #000000; border-radius: 50%; transform-origin: 5px calc(var(--wheel-size) / 2 * 0.97); }
#lwt-wheel-wrapper { position: absolute; top: 50%; left: 50%; width: 97%; height: 97%; transform: translate(-50%, -50%); }
#lwt-canvas { width: 100%; height: 100%; }
#lwt-center-image-container { position: absolute; width: 25%; height: 25%; top: 50%; left: 50%; transform: translate(-50%, -50%); z-index: 15; display: flex; align-items: center; justify-content: center; }
#lwt-center-image-container img { max-width: 100%; max-height: 100%; height: auto; width: auto; filter: drop-shadow(0px 2px 5px rgba(0,0,0,0.1)); }
#lwt-message { margin: 20px 0; padding: 15px; border-radius: 8px; width: 100%; text-align: center; }
#lwt-message.success { background-color: #d4edda; color: #155724; }
#lwt-message.error, .lwt-error-message { background-color: #f8d7da; color: #721c24; }

/* Custom Form Styles */
#lwt-custom-form .lwt-form-field { margin-bottom: 15px; }
#lwt-custom-form input[type="text"],
#lwt-custom-form input[type="email"],
#lwt-custom-form input[type="tel"] {
    width: 100%; padding: 12px; border: 1px solid #ccc; border-radius: 8px; text-align: right; direction: rtl; font-size: 16px; font-family: 'Vazirmatn', sans-serif;
}
#lwt-custom-form .lwt-checkbox-field { text-align: right; }
#lwt-custom-form .lwt-spin-button { background-color: #C4161C; color: white; font-weight: bold; padding: 12px 30px; font-size: 18px; cursor: pointer; border: none; border-radius: 8px; width: 100%; }
#lwt-custom-form .lwt-spin-button:disabled { background-color: #999; cursor: not-allowed; }

@media (max-width: 767px) {
    .lwt-container, .lwt-elementor-widget-wrapper > .elementor-widget-container > .lwt-container { flex-direction: column; }
    .lwt-wheel-column { order: 1; }
    .lwt-form-column { order: 2; width: 100%; max-width: 100%; }
}
