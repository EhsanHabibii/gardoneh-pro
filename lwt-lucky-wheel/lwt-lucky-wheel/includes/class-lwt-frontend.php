<?php
if (!defined('ABSPATH')) exit;

class LWT_Frontend {

    public function __construct() {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
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

    public function render_wheel_and_form($settings) {
        $layout = !empty($settings['layout_position']) ? $settings['layout_position'] : get_option('lwt_layout_position', 'right');
        $form_type = !empty($settings['form_type']) ? $settings['form_type'] : get_option('lwt_form_type', 'custom_form');

        echo '<div class="lwt-container lwt-layout-'.$layout.'">';
        echo $this->get_wheel_html($settings);

        echo '<div class="lwt-form-column">';
        if ($form_type === 'gravity_forms' && !empty($settings['gf_form_id'])) {
            $shortcode_atts = [
                'form_id' => $settings['gf_form_id'],
                'name_field_id' => $settings['gf_name_field_id'],
                'phone_field_id' => $settings['gf_phone_field_id'],
                'prize_field_id' => $settings['gf_prize_field_id'],
            ];
            $_SESSION['lwt_shortcode_attributes_for_form_' . $settings['gf_form_id']] = $shortcode_atts;
             wp_add_inline_script('lwt-main', 'var lwt_gf_config = ' . json_encode($shortcode_atts) . ';', 'before');
            echo do_shortcode('[gravityform id="' . $settings['gf_form_id'] . '" title="false" description="false" ajax="true"]');
        } elseif ($form_type === 'custom_form') {
            echo $this->render_custom_form();
        }
        echo '</div>';
        echo '</div>';
    }

    public function get_wheel_html($settings = []) {
        $pointer_image_url = !empty($settings['pointer_image_url']) ? $settings['pointer_image_url'] : get_option('lwt_pointer_image_url');
        $center_image_url = !empty($settings['center_image_url']) ? $settings['center_image_url'] : get_option('lwt_center_image_url');
        $wheel_size = !empty($settings['wheel_size']) ? $settings['wheel_size'] : get_option('lwt_wheel_size', 450);
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
                 if ($winner_prize->quantity < 999990) { $wpdb->update($prizes_table, ['quantity' => $winner_prize->quantity - 1], ['id' => $winner_prize->id]); }
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
