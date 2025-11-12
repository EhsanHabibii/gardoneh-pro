<?php
if (!defined('ABSPATH')) exit;

class LWT_Ajax {

    public function __construct() {
        // Admin AJAX hooks
        add_action('wp_ajax_lwt_get_prizes', [$this, 'get_prizes']);
        add_action('wp_ajax_lwt_save_prize', [$this, 'save_prize']);
        add_action('wp_ajax_lwt_delete_prize', [$this, 'delete_prize']);
        add_action('wp_ajax_lwt_export_outputs', [$this, 'export_outputs']);

        // Frontend AJAX hooks
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

    private function check_frontend_nonce() {
        if (!check_ajax_referer('lwt_spin_nonce', 'nonce', false)) {
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
        $this->check_frontend_nonce();
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

        if ($is_winner && $winner_prize->quantity < 999990) { 
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

    public function export_outputs() {
        $this->check_admin_nonce();
        global $wpdb;
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=lucky-wheel-outputs-' . date('Y-m-d') . '.csv');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['نام', 'ایمیل', 'موبایل', 'جایزه', 'تاریخ']);

        $where_clauses = [];
        if (!empty($_GET['start_date'])) $where_clauses[] = $wpdb->prepare("win_date >= %s", date('Y-m-d 00:00:00', strtotime($_GET['start_date'])));
        if (!empty($_GET['end_date'])) $where_clauses[] = $wpdb->prepare("win_date <= %s", date('Y-m-d 23:59:59', strtotime($_GET['end_date'])));
        $where_sql = count($where_clauses) > 0 ? " WHERE " . implode(' AND ', $where_clauses) : '';
        
        $results = $wpdb->get_results("SELECT user_name, user_email, user_phone, prize_won, win_date FROM {$wpdb->prefix}lwt_outputs" . $where_sql . " ORDER BY win_date DESC", ARRAY_A);
        if ($results) {
            foreach ($results as $row) {
                fputcsv($output, $row);
            }
        }
        fclose($output);
        exit;
    }
}
