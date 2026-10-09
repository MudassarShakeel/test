<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin settings page, registration and sanitisation.
 */
class LS_MS_Settings {

    const GROUP = 'ls_ms_settings_group';
    const SLUG  = 'language-switcher-ms';

    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }

    /* ---------------------------------------------------------------
     * Sanitisers
     * ------------------------------------------------------------- */

    public function sanitize_site_lang($value) {
        $value = is_string($value) ? sanitize_text_field($value) : '';
        return LS_MS_Languages::is_valid($value) ? $value : 'en';
    }

    public function sanitize_active_langs($value) {
        if (!is_array($value)) {
            return array();
        }
        $clean = array();
        foreach ($value as $code) {
            $code = is_string($code) ? sanitize_text_field($code) : '';
            if (LS_MS_Languages::is_valid($code) && !in_array($code, $clean, true)) {
                $clean[] = $code;
            }
        }
        return $clean;
    }

    public function sanitize_keywords($value) {
        if (!is_string($value)) {
            return '';
        }
        $value    = sanitize_textarea_field($value);
        $keywords = array();
        foreach (explode(',', $value) as $kw) {
            $kw = trim($kw);
            if ($kw === '' || mb_strlen($kw) > 100) {
                continue;
            }
            $keywords[mb_strtolower($kw)] = $kw;
            if (count($keywords) >= 100) {
                break;
            }
        }
        return implode(', ', $keywords);
    }

    public function sanitize_color($value, $default) {
        $value = is_string($value) ? sanitize_hex_color(trim($value)) : null;
        return $value ? $value : $default;
    }

    public function sanitize_size($value, $default, $allow_auto = true) {
        $value = is_string($value) ? strtolower(trim($value)) : '';
        $auto  = $allow_auto ? 'auto|' : '';
        if (preg_match('/^(' . $auto . '\d{1,4}(\.\d{1,2})?(px|%|em|rem|vw|vh)?)$/', $value)) {
            return $value;
        }
        return $default;
    }

    public function sanitize_bg_color($v)     { return $this->sanitize_color($v, '#ffffff'); }
    public function sanitize_text_color($v)   { return $this->sanitize_color($v, '#1d1d1f'); }
    public function sanitize_hover_bg($v)     { return $this->sanitize_color($v, '#f5f5f5'); }
    public function sanitize_border_color($v) { return $this->sanitize_color($v, '#ececec'); }
    public function sanitize_font_size($v)    { return $this->sanitize_size($v, '13px', false); }
    public function sanitize_btn_width($v)    { return $this->sanitize_size($v, 'auto'); }
    public function sanitize_btn_height($v)   { return $this->sanitize_size($v, 'auto'); }
    public function sanitize_menu_width($v)   { return $this->sanitize_size($v, '170px', false); }

    public function sanitize_font_family($value) {
        $value = is_string($value) ? sanitize_text_field($value) : '';
        // Letters, digits, spaces, commas, hyphens, underscores and quotes only (no CSS-breaking chars).
        $value = trim(preg_replace('/[^A-Za-z0-9 ,_\'"-]/', '', $value));
        return ($value === '' || strlen($value) > 200) ? 'inherit' : $value;
    }

    /* ---------------------------------------------------------------
     * Registration
     * ------------------------------------------------------------- */

    public function register_settings() {
        $defaults = LS_MS_Languages::defaults();
        $map = array(
            'ls_ms_site_lang'         => 'sanitize_site_lang',
            'ls_ms_active_langs'      => 'sanitize_active_langs',
            'ls_ms_excluded_keywords' => 'sanitize_keywords',
            'ls_ms_bg_color'          => 'sanitize_bg_color',
            'ls_ms_text_color'        => 'sanitize_text_color',
            'ls_ms_hover_bg'          => 'sanitize_hover_bg',
            'ls_ms_border_color'      => 'sanitize_border_color',
            'ls_ms_font_family'       => 'sanitize_font_family',
            'ls_ms_font_size'         => 'sanitize_font_size',
            'ls_ms_btn_width'         => 'sanitize_btn_width',
            'ls_ms_btn_height'        => 'sanitize_btn_height',
            'ls_ms_menu_min_width'    => 'sanitize_menu_width',
        );

        foreach ($map as $option => $callback) {
            register_setting(self::GROUP, $option, array(
                'type'              => is_array($defaults[$option]) ? 'array' : 'string',
                'sanitize_callback' => array($this, $callback),
                'default'           => $defaults[$option],
                'show_in_rest'      => false,
            ));
        }
    }

    public function add_admin_menu() {
        add_options_page(
            __('Language Switcher Settings', 'language-switcher-ms'),
            __('Language Switcher MS', 'language-switcher-ms'),
            'manage_options',
            self::SLUG,
            array($this, 'render_admin_page')
        );
    }

    public function enqueue_admin_assets($hook) {
        if ($hook !== 'settings_page_' . self::SLUG) {
            return;
        }
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script(
            'ls-ms-admin',
            LS_MS_URL . 'assets/js/admin.js',
            array('wp-color-picker'),
            LS_MS_VERSION,
            true
        );
    }

    /* ---------------------------------------------------------------
     * Admin page
     * ------------------------------------------------------------- */

    private function text_row($id, $label, $class, $placeholder, $description = '') {
        $value = get_option($id, LS_MS_Languages::defaults()[$id]);
        ?>
        <tr>
            <th scope="row"><label for="<?php echo esc_attr($id); ?>"><?php echo esc_html($label); ?></label></th>
            <td>
                <input type="text" name="<?php echo esc_attr($id); ?>" id="<?php echo esc_attr($id); ?>" value="<?php echo esc_attr($value); ?>" class="<?php echo esc_attr($class); ?>" placeholder="<?php echo esc_attr($placeholder); ?>" maxlength="200">
                <?php if ($description) : ?>
                    <p class="description"><?php echo wp_kses($description, array('code' => array())); ?></p>
                <?php endif; ?>
            </td>
        </tr>
        <?php
    }

    private function color_row($id, $label) {
        $defaults = LS_MS_Languages::defaults();
        $value    = get_option($id, $defaults[$id]);
        ?>
        <tr>
            <th scope="row"><label for="<?php echo esc_attr($id); ?>"><?php echo esc_html($label); ?></label></th>
            <td><input type="text" name="<?php echo esc_attr($id); ?>" id="<?php echo esc_attr($id); ?>" value="<?php echo esc_attr($value); ?>" class="ls-color-field" data-default-color="<?php echo esc_attr($defaults[$id]); ?>"></td>
        </tr>
        <?php
    }

    public function render_admin_page() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'language-switcher-ms'), '', array('response' => 403));
        }

        $languages    = LS_MS_Languages::all();
        $active_langs = LS_MS_Languages::get('ls_ms_active_langs');
        $site_lang    = LS_MS_Languages::get('ls_ms_site_lang');
        $keywords     = get_option('ls_ms_excluded_keywords', '');
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Language Switcher by Mudassar', 'language-switcher-ms'); ?></h1>
            <p>Developed by <a href="https://mudassar.work/" target="_blank" rel="noopener noreferrer" style="text-decoration:none;font-weight:bold;">Mudassar Shakeel</a></p>

            <div style="background:#fff;border-left:4px solid #2271b1;padding:12px 16px;margin:15px 0;box-shadow:0 1px 1px rgba(0,0,0,.04);">
                <h3 style="margin:0 0 8px 0;"><?php esc_html_e('Shortcode', 'language-switcher-ms'); ?></h3>
                <code>[language_switcher_ms]</code>
                <p style="margin:8px 0 0 0;color:#50575e;font-size:13px;"><?php esc_html_e('Paste this shortcode anywhere on your site.', 'language-switcher-ms'); ?></p>
            </div>

            <form method="post" action="options.php">
                <?php settings_fields(self::GROUP); ?>

                <h2><?php esc_html_e('General Configuration', 'language-switcher-ms'); ?></h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="ls_ms_site_lang"><?php esc_html_e('Default Site Language', 'language-switcher-ms'); ?></label></th>
                        <td>
                            <select name="ls_ms_site_lang" id="ls_ms_site_lang">
                                <?php foreach ($languages as $code => $data) : ?>
                                    <option value="<?php echo esc_attr($code); ?>" <?php selected($site_lang, $code); ?>>
                                        <?php echo esc_html($data['name']); ?> (<?php echo esc_html($code); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Enable Languages', 'language-switcher-ms'); ?></th>
                        <td>
                            <fieldset>
                                <?php foreach ($languages as $code => $data) : ?>
                                    <label style="display:inline-block;width:180px;margin-bottom:8px;">
                                        <input type="checkbox" name="ls_ms_active_langs[]" value="<?php echo esc_attr($code); ?>" <?php checked(in_array($code, $active_langs, true)); ?>>
                                        <img src="<?php echo esc_url($data['flag']); ?>" width="18" height="12" alt="" style="vertical-align:middle;margin:0 4px;">
                                        <?php echo esc_html($data['name']); ?>
                                    </label>
                                <?php endforeach; ?>
                            </fieldset>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="ls_ms_excluded_keywords"><?php esc_html_e('Do Not Translate Keywords', 'language-switcher-ms'); ?></label></th>
                        <td>
                            <textarea name="ls_ms_excluded_keywords" id="ls_ms_excluded_keywords" rows="3" cols="50" class="large-text" maxlength="5000"><?php echo esc_textarea($keywords); ?></textarea>
                            <p class="description"><?php esc_html_e('Brand names or keywords separated by commas (e.g. Mudassar, WooCommerce, MyBrand). Max 100 keywords.', 'language-switcher-ms'); ?></p>
                        </td>
                    </tr>
                </table>

                <hr style="margin:30px 0;">

                <h2><?php esc_html_e('Dimensions & Size Settings', 'language-switcher-ms'); ?></h2>
                <table class="form-table" role="presentation">
                    <?php
                    $this->text_row('ls_ms_btn_width', __('Button Width', 'language-switcher-ms'), 'regular-text', 'auto', __('Default <code>auto</code>. Examples: <code>130px</code>, <code>100%</code>.', 'language-switcher-ms'));
                    $this->text_row('ls_ms_btn_height', __('Button Height', 'language-switcher-ms'), 'regular-text', 'auto', __('Default <code>auto</code>. Examples: <code>36px</code>, <code>40px</code>.', 'language-switcher-ms'));
                    $this->text_row('ls_ms_menu_min_width', __('Dropdown Minimum Width', 'language-switcher-ms'), 'regular-text', '170px', __('Default <code>170px</code>.', 'language-switcher-ms'));
                    ?>
                </table>

                <hr style="margin:30px 0;">

                <h2><?php esc_html_e('Style & Color Settings', 'language-switcher-ms'); ?></h2>
                <table class="form-table" role="presentation">
                    <?php
                    $this->color_row('ls_ms_bg_color', __('Background Color', 'language-switcher-ms'));
                    $this->color_row('ls_ms_text_color', __('Text & Icon Color', 'language-switcher-ms'));
                    $this->color_row('ls_ms_hover_bg', __('Item Hover Background', 'language-switcher-ms'));
                    $this->color_row('ls_ms_border_color', __('Border Color', 'language-switcher-ms'));
                    $this->text_row('ls_ms_font_family', __('Font Family', 'language-switcher-ms'), 'regular-text', 'inherit, Arial, sans-serif');
                    $this->text_row('ls_ms_font_size', __('Font Size', 'language-switcher-ms'), 'small-text', '13px');
                    ?>
                </table>

                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}
