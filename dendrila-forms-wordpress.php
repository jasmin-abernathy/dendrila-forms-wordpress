<?php
/**
 * Plugin Name: Dendrila Forms – Enquêtes & Interviews
 * Plugin URI: https://github.com/jasmin-abernathy/dendrila-forms-wordpress
 * Description: Enquêtes et entretiens guidés, avec modèles JSON et sauvegarde privée des brouillons.
 * Version: 0.1.0-alpha
 * Requires at least: 6.5
 * Requires PHP: 8.1
 * Author: Le Potager du Web
 * License: AGPL-3.0-or-later
 * License URI: https://www.gnu.org/licenses/agpl-3.0.html
 * Text Domain: dendrila-forms-wordpress
 * Update URI: https://github.com/jasmin-abernathy/dendrila-forms-wordpress
 */
defined('ABSPATH') || exit;

define('DFW_VERSION', '0.1.0-alpha');
define('DFW_FILE', __FILE__);
define('DFW_DIR', plugin_dir_path(__FILE__));

require_once DFW_DIR . 'includes/class-dfw-entries.php';
register_activation_hook(__FILE__, array('DFW_Entries', 'install'));
register_deactivation_hook(__FILE__, array('DFW_Entries', 'deactivate'));
add_action('dfw_daily_purge', array('DFW_Entries', 'purge'));

add_action('plugins_loaded', static function (): void {
    $core = DFW_DIR . 'vendor/dendrila-forms/src/Core.php';
    if (!is_readable($core)) {
        add_action('admin_notices', static function (): void {
            if (current_user_can('activate_plugins')) {
                echo '<div class="notice notice-error"><p>' .
                    esc_html__('Dendrila Forms : noyau absent. Installez le ZIP préparé par le workflow de construction, et non l’archive du code source.', 'dendrila-forms-wordpress') .
                    '</p></div>';
            }
        });
        return;
    }
    require_once $core;
    require_once DFW_DIR . 'includes/class-dfw-admin.php';
    require_once DFW_DIR . 'includes/class-dfw-public.php';
    DFW_Admin::boot();
    DFW_Public::boot();
});
