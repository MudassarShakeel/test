<?php
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

$options = array(
    'ls_ms_active_langs', 'ls_ms_site_lang', 'ls_ms_excluded_keywords',
    'ls_ms_bg_color', 'ls_ms_text_color', 'ls_ms_hover_bg', 'ls_ms_border_color',
    'ls_ms_font_family', 'ls_ms_font_size',
    'ls_ms_btn_width', 'ls_ms_btn_height', 'ls_ms_menu_min_width',
);

foreach ($options as $option) {
    delete_option($option);
}
