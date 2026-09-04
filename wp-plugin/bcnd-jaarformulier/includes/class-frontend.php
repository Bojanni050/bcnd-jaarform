<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Loads the compiled React app: shortcode [bcnd_portal] for members and
 * admin pages under the BCND menu. Enqueues built assets and injects boot data.
 */
class BCND_Frontend {

    private static $enqueued = false;

    public static function init() {
        add_shortcode('bcnd_portal', [__CLASS__, 'shortcode']);
    }

    private static function app_base() {
        return BCND_PLUGIN_URL . 'assets/app/';
    }

    private static function manifest() {
        $file = BCND_PLUGIN_DIR . 'assets/app/asset-manifest.json';
        if (!file_exists($file)) { return null; }
        $data = json_decode(file_get_contents($file), true);
        return is_array($data) ? $data : null;
    }

    /**
     * Enqueue built JS/CSS + localize BCND boot data.
     */
    public static function enqueue($view, $initial_route) {
        if (self::$enqueued) { return; }
        self::$enqueued = true;
        $manifest = self::manifest();
        $base = self::app_base();
        $ver = BCND_VERSION;

        $js = []; $css = [];
        if ($manifest && !empty($manifest['entrypoints'])) {
            foreach ($manifest['entrypoints'] as $entry) {
                if (substr($entry, -3) === '.js') { $js[] = $base . ltrim($entry, '/'); }
                elseif (substr($entry, -4) === '.css') { $css[] = $base . ltrim($entry, '/'); }
            }
        }

        foreach ($css as $i => $href) {
            wp_enqueue_style('bcnd-app-' . $i, $href, [], $ver);
        }
        $handle = 'bcnd-app';
        foreach ($js as $i => $src) {
            $h = $i === 0 ? $handle : $handle . '-' . $i;
            wp_enqueue_script($h, $src, [], $ver, true);
        }

        $current = BCND_Core::current_identity();
        $boot = [
            'restUrl' => esc_url_raw(trailingslashit(rest_url())),
            'nonce' => wp_create_nonce('wp_rest'),
            'appBase' => esc_url_raw($base),
            'currentUser' => $current, // null when not logged in
            'loginUrl' => wp_login_url(self::current_url()),
            'logoutUrl' => wp_logout_url(home_url()),
            'view' => $view,
            'initialRoute' => $initial_route,
        ];
        $inline = 'window.BCND = ' . wp_json_encode($boot) . ';';
        // Attach before the main script so it is available at boot.
        if (!empty($js)) {
            wp_add_inline_script($handle, $inline, 'before');
        } else {
            add_action(is_admin() ? 'admin_footer' : 'wp_footer', function () use ($inline) {
                echo '<script>' . $inline . '</script>';
            }, 1);
        }
    }

    private static function current_url() {
        $scheme = is_ssl() ? 'https://' : 'http://';
        return $scheme . ($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? '');
    }

    /* ---------- Member portal shortcode ---------- */

    public static function shortcode($atts) {
        self::enqueue('member', '/');
        return '<div id="bcnd-portal-root" class="bcnd-app-root"></div>';
    }

    /* ---------- WP admin ---------- */

    public static function register_admin_menu() {
        $cap = 'bcnd_review_training';
        if (!current_user_can($cap) && !current_user_can('manage_options')) { return; }
        $cap = current_user_can('manage_options') ? 'manage_options' : $cap;

        add_menu_page('BCND', 'BCND', $cap, 'bcnd', [__CLASS__, 'render_admin'], 'dashicons-clipboard', 30);

        $subs = [
            ['bcnd', 'Dashboard', '/admin'],
            ['bcnd-leden', 'Leden', '/admin/leden'],
            ['bcnd-bijscholingen', 'Bijscholingen', '/admin/bijscholingen'],
            ['bcnd-jaarformulieren', 'Jaarformulieren', '/admin/jaarformulieren'],
            ['bcnd-instellingen', 'Instellingen', '/admin/instellingen'],
        ];
        foreach ($subs as $s) {
            add_submenu_page('bcnd', 'BCND ' . $s[1], $s[1], $cap, $s[0], [__CLASS__, 'render_admin']);
        }
    }

    public static function render_admin() {
        $page = isset($_GET['page']) ? sanitize_key($_GET['page']) : 'bcnd';
        $map = [
            'bcnd' => '/admin',
            'bcnd-leden' => '/admin/leden',
            'bcnd-bijscholingen' => '/admin/bijscholingen',
            'bcnd-jaarformulieren' => '/admin/jaarformulieren',
            'bcnd-instellingen' => '/admin/instellingen',
        ];
        $route = isset($map[$page]) ? $map[$page] : '/admin';
        self::enqueue('admin', $route);
        echo '<div class="wrap"><div id="bcnd-admin-root" class="bcnd-app-root"></div></div>';
    }
}
