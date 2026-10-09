<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Supported languages and shared validation helpers.
 */
class LS_MS_Languages {

    public static function all() {
        return array(
            'en'    => array('name' => 'English',   'flag' => 'https://flagcdn.com/w40/gb.png', 'code' => 'EN'),
            'es'    => array('name' => 'Español',   'flag' => 'https://flagcdn.com/w40/es.png', 'code' => 'ES'),
            'pt'    => array('name' => 'Português', 'flag' => 'https://flagcdn.com/w40/pt.png', 'code' => 'PT'),
            'fr'    => array('name' => 'Français',  'flag' => 'https://flagcdn.com/w40/fr.png', 'code' => 'FR'),
            'de'    => array('name' => 'Deutsch',   'flag' => 'https://flagcdn.com/w40/de.png', 'code' => 'DE'),
            'it'    => array('name' => 'Italiano',  'flag' => 'https://flagcdn.com/w40/it.png', 'code' => 'IT'),
            'ar'    => array('name' => 'العربية',   'flag' => 'https://flagcdn.com/w40/sa.png', 'code' => 'AR'),
            'zh-CN' => array('name' => '简体中文',   'flag' => 'https://flagcdn.com/w40/cn.png', 'code' => 'ZH'),
            'ja'    => array('name' => '日本語',     'flag' => 'https://flagcdn.com/w40/jp.png', 'code' => 'JA'),
            'ru'    => array('name' => 'Русский',   'flag' => 'https://flagcdn.com/w40/ru.png', 'code' => 'RU'),
            'hi'    => array('name' => 'हिन्दी',     'flag' => 'https://flagcdn.com/w40/in.png', 'code' => 'HI'),
            'ur'    => array('name' => 'اردو',      'flag' => 'https://flagcdn.com/w40/pk.png', 'code' => 'UR'),
        );
    }

    public static function is_valid($code) {
        return is_string($code) && array_key_exists($code, self::all());
    }

    /**
     * Default option values (single source of truth).
     */
    public static function defaults() {
        return array(
            'ls_ms_active_langs'       => array('en', 'es', 'pt'),
            'ls_ms_site_lang'          => 'en',
            'ls_ms_excluded_keywords'  => '',
            'ls_ms_bg_color'           => '#ffffff',
            'ls_ms_text_color'         => '#1d1d1f',
            'ls_ms_hover_bg'           => '#f5f5f5',
            'ls_ms_border_color'       => '#ececec',
            'ls_ms_font_family'        => 'inherit',
            'ls_ms_font_size'          => '13px',
            'ls_ms_btn_width'          => 'auto',
            'ls_ms_btn_height'         => 'auto',
            'ls_ms_menu_min_width'     => '170px',
        );
    }

    /**
     * Get an option that is always valid for its language fields.
     */
    public static function get($key) {
        $defaults = self::defaults();
        $value    = get_option($key, $defaults[$key]);

        if ($key === 'ls_ms_site_lang') {
            return self::is_valid($value) ? $value : $defaults[$key];
        }
        if ($key === 'ls_ms_active_langs') {
            return is_array($value)
                ? array_values(array_filter($value, array(__CLASS__, 'is_valid')))
                : $defaults[$key];
        }
        return $value;
    }
}
