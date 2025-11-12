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
        $this->add_control('layout_position', ['label' => 'موقعیت فرم نسبت به گردونه', 'type' => \Elementor\Controls_Manager::SELECT, 'default' => '', 'options' => ['' => 'پیش‌فرض تنظیمات', 'right' => 'راست', 'left' => 'چپ', 'top' => 'بالا', 'bottom' => 'پایین'], 'description' => 'این تنظیمات، مقدار پیش‌فرض در صفحه تنظیمات را لغو (override) می‌کند.']);
        $this->add_control('wheel_size', ['label' => 'اندازه گردونه (px)', 'type' => \Elementor\Controls_Manager::NUMBER, 'placeholder' => get_option('lwt_wheel_size'), 'description' => 'این تنظیمات، مقدار پیش‌فرض در صفحه تنظیمات را لغو می‌کند.']);
        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        $frontend = new LWT_Frontend();
        $frontend->render_wheel_and_form($settings);
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
