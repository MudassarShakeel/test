<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Front-end: assets, shortcode and "do not translate" keyword filter.
 */
class LS_MS_Frontend {

    /** @var LS_MS_Settings */
    private $settings;

    public function __construct(LS_MS_Settings $settings) {
        $this->settings = $settings;

        add_shortcode('language_switcher_ms', array($this, 'render_shortcode'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('wp_footer', array($this, 'render_hidden_gt_container'));
        add_action('template_redirect', array($this, 'start_content_buffer'), 1);
    }

    /* ---------------------------------------------------------------
     * Keyword exclusion
     * ------------------------------------------------------------- */

    private function get_keywords() {
        $str = get_option('ls_ms_excluded_keywords', '');
        if (!is_string($str) || $str === '') {
            return array();
        }
        $keywords = array();
        foreach (explode(',', $str) as $kw) {
            $kw = trim($kw);
            if ($kw !== '' && mb_strlen($kw) <= 100) {
                $keywords[] = $kw;
            }
        }
        return array_slice(array_unique($keywords), 0, 100);
    }

    public function start_content_buffer() {
        if (is_admin() || is_feed() || is_robots() || is_embed() || is_trackback()
            || wp_doing_ajax() || wp_is_json_request() || (defined('REST_REQUEST') && REST_REQUEST)) {
            return;
        }
        if (!$this->get_keywords()) {
            return;
        }
        ob_start(array($this, 'filter_excluded_keywords'));
    }

    /**
     * Wrap keywords found in visible text nodes with a notranslate span.
     * HTML tags and script/style/textarea/title blocks are never touched.
     */
    public function filter_excluded_keywords($buffer) {
        if (!is_string($buffer) || $buffer === '' || stripos($buffer, '<html') === false) {
            return $buffer;
        }
        $keywords = $this->get_keywords();
        if (!$keywords) {
            return $buffer;
        }

        // Longest first so longer phrases win over their substrings.
        usort($keywords, function ($a, $b) {
            return mb_strlen($b) - mb_strlen($a);
        });
        $alternation = implode('|', array_map(function ($kw) {
            return preg_quote($kw, '/');
        }, $keywords));
        $keyword_re = '/(?<![\p{L}\p{N}_])(' . $alternation . ')(?![\p{L}\p{N}_])/iu';

        $parts = preg_split(
            '/(<(?:script|style|textarea|title|noscript)\b[^>]*>.*?<\/(?:script|style|textarea|title|noscript)\s*>|<!--.*?-->|<[^>]*>)/isu',
            $buffer,
            -1,
            PREG_SPLIT_DELIM_CAPTURE
        );
        if ($parts === false) {
            return $buffer;
        }

        // One capture group, so the split alternates: text, tag, text, tag, ...
        $out = '';
        $count = count($parts);
        for ($i = 0; $i < $count; $i += 2) {
            $text = $parts[$i];
            $replaced = preg_replace($keyword_re, '<span class="notranslate" translate="no">$1</span>', $text);
            $out .= ($replaced === null) ? $text : $replaced;
            if (isset($parts[$i + 1])) {
                $out .= $parts[$i + 1];
            }
        }
        return $out;
    }

    /* ---------------------------------------------------------------
     * Assets
     * ------------------------------------------------------------- */

    private function build_css_vars() {
        $d = LS_MS_Languages::defaults();
        $s = $this->settings;

        $vars = array(
            '--mcs-bg'         => $s->sanitize_bg_color(get_option('ls_ms_bg_color', $d['ls_ms_bg_color'])),
            '--mcs-text'       => $s->sanitize_text_color(get_option('ls_ms_text_color', $d['ls_ms_text_color'])),
            '--mcs-hover'      => $s->sanitize_hover_bg(get_option('ls_ms_hover_bg', $d['ls_ms_hover_bg'])),
            '--mcs-border'     => $s->sanitize_border_color(get_option('ls_ms_border_color', $d['ls_ms_border_color'])),
            '--mcs-font'       => $s->sanitize_font_family(get_option('ls_ms_font_family', $d['ls_ms_font_family'])),
            '--mcs-font-size'  => $s->sanitize_font_size(get_option('ls_ms_font_size', $d['ls_ms_font_size'])),
            '--mcs-width'      => $s->sanitize_btn_width(get_option('ls_ms_btn_width', $d['ls_ms_btn_width'])),
            '--mcs-height'     => $s->sanitize_btn_height(get_option('ls_ms_btn_height', $d['ls_ms_btn_height'])),
            '--mcs-menu-width' => $s->sanitize_menu_width(get_option('ls_ms_menu_min_width', $d['ls_ms_menu_min_width'])),
        );

        $css = '.mcs-lang{';
        foreach ($vars as $name => $value) {
            // Values were whitelisted above; strip anything that could end the declaration/block anyway.
            $css .= $name . ':' . str_replace(array(';', '{', '}', '<', '>', '\\'), '', $value) . ';';
        }
        return $css . '}';
    }

    public function enqueue_assets() {
        wp_enqueue_style('ls-ms-style', LS_MS_URL . 'assets/css/frontend.css', array(), LS_MS_VERSION);
        wp_add_inline_style('ls-ms-style', $this->build_css_vars());

        wp_enqueue_script('ls-ms-script', LS_MS_URL . 'assets/js/frontend.js', array(), LS_MS_VERSION, true);
        wp_localize_script('ls-ms-script', 'mcsLangConfig', array(
            'siteLang' => LS_MS_Languages::get('ls_ms_site_lang'),
            'langs'    => implode(',', LS_MS_Languages::get('ls_ms_active_langs')),
        ));
    }

    public function render_hidden_gt_container() {
        echo '<div id="mcs_gt" class="notranslate" style="position:absolute;left:-9999px;top:-9999px;height:0;width:0;overflow:hidden;"></div>';
    }

    /* ---------------------------------------------------------------
     * Shortcode
     * ------------------------------------------------------------- */

    private function current_lang($site_lang) {
        if (isset($_COOKIE['googtrans']) && is_string($_COOKIE['googtrans'])) {
            $cookie = sanitize_text_field(wp_unslash($_COOKIE['googtrans']));
            if (preg_match('#^/[A-Za-z-]{2,10}/([A-Za-z-]{2,10})$#', $cookie, $m) && LS_MS_Languages::is_valid($m[1])) {
                return $m[1];
            }
        }
        return $site_lang;
    }

    public function render_shortcode() {
        $languages    = LS_MS_Languages::all();
        $active_langs = LS_MS_Languages::get('ls_ms_active_langs');
        $site_lang    = LS_MS_Languages::get('ls_ms_site_lang');
        $current      = $languages[$this->current_lang($site_lang)];

        ob_start();
        ?>
        <div class="mcs-lang notranslate" translate="no">
          <button type="button" class="mcs-lang-btn" aria-haspopup="true" aria-expanded="false" aria-label="<?php esc_attr_e('Select language', 'language-switcher-ms'); ?>">
            <img class="mcs-flag" src="<?php echo esc_url($current['flag']); ?>" alt="" width="20" height="14" referrerpolicy="no-referrer">
            <span class="mcs-code"><?php echo esc_html($current['code']); ?></span>
            <svg class="mcs-caret" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" aria-hidden="true"><path d="M6 9l6 6 6-6"></path></svg>
          </button>
          <div class="mcs-lang-menu" role="menu">
            <?php foreach ($active_langs as $code) : $item = $languages[$code]; ?>
              <button type="button" class="mcs-menu-item" role="menuitem" data-lang="<?php echo esc_attr($code); ?>" data-flag="<?php echo esc_url($item['flag']); ?>">
                <img class="mcs-flag" src="<?php echo esc_url($item['flag']); ?>" alt="" width="20" height="14" referrerpolicy="no-referrer">
                <b><?php echo esc_html($item['code']); ?></b>
                <span><?php echo esc_html($item['name']); ?></span>
              </button>
            <?php endforeach; ?>
          </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
