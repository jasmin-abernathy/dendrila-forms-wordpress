<?php
defined('ABSPATH') || exit;

final class DFW_Admin
{
    public static function boot(): void
    {
        add_action('admin_menu', array(__CLASS__, 'menu'));
        add_action('admin_post_dfw_save', array(__CLASS__, 'save'));
        add_action('admin_post_dfw_remove', array(__CLASS__, 'remove'));
        add_action('admin_post_dfw_export', array(__CLASS__, 'export'));
        add_action('admin_enqueue_scripts', static function ($hook): void {
            if ($hook === 'toplevel_page_dendrila-forms') {
                wp_enqueue_style('dfw-admin', plugins_url('assets/admin.css', DFW_FILE), array(), DFW_VERSION);
            }
        });
    }

    public static function forms(): array
    {
        $forms = get_option('dfw_forms', array());
        return is_array($forms) ? $forms : array();
    }

    public static function active(string $slug): ?array
    {
        $forms = self::forms();
        if (!isset($forms[$slug]) || !is_array($forms[$slug]) || empty($forms[$slug]['active'])) {
            return null;
        }
        try {
            return df_validate_form(json_decode((string) $forms[$slug]['json'], true, 512, JSON_THROW_ON_ERROR));
        } catch (Throwable $error) {
            return null;
        }
    }

    public static function menu(): void
    {
        add_menu_page(
            'Dendrila Forms', 'Dendrila Forms', 'manage_options',
            'dendrila-forms', array(__CLASS__, 'render'), 'dashicons-feedback', 61
        );
    }

    public static function save(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Accès réservé à l’administration.', 'Accès refusé', array('response' => 403));
        }
        check_admin_referer('dfw_save');
        $slug = isset($_POST['dfw_slug']) && is_string($_POST['dfw_slug'])
            ? sanitize_title(wp_unslash($_POST['dfw_slug'])) : '';
        $raw = isset($_POST['dfw_json']) && is_string($_POST['dfw_json'])
            ? wp_unslash($_POST['dfw_json']) : '';
        if (!preg_match('/\A[a-z0-9-]{1,60}\z/D', $slug) || strlen($raw) > 48000) {
            wp_die('Identifiant ou taille du formulaire invalide.', 'Formulaire invalide', array('response' => 400));
        }
        try {
            $validated = df_validate_form(json_decode($raw, true, 512, JSON_THROW_ON_ERROR));
        } catch (Throwable $error) {
            wp_die('Le modèle JSON est invalide. Vérifiez les types, questions, réponses et conditions.', 'Formulaire invalide', array('response' => 400));
        }
        $forms = self::forms();
        if (!isset($forms[$slug]) && count($forms) >= 30) {
            wp_die('Limite de 30 formulaires atteinte.', 'Limite atteinte', array('response' => 400));
        }
        $normal = wp_json_encode($validated, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        if (isset($forms[$slug]) && $forms[$slug]['json'] !== $normal && DFW_Entries::count_for($slug) > 0) {
            wp_die('Un questionnaire ayant déjà des réponses ne peut pas être modifié. Dupliquez-le sous un nouvel identifiant.', 'Modification bloquée', array('response' => 409));
        }
        $forms[$slug] = array(
            'json' => $normal,
            'active' => isset($_POST['dfw_active']) && $_POST['dfw_active'] === '1',
        );
        update_option('dfw_forms', $forms, false);
        wp_safe_redirect(admin_url('admin.php?page=dendrila-forms&dfw_status=saved'));
        exit;
    }

    public static function remove(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Accès refusé.', 'Accès refusé', array('response' => 403));
        }
        check_admin_referer('dfw_remove');
        $slug = isset($_POST['dfw_slug']) && is_string($_POST['dfw_slug'])
            ? sanitize_title(wp_unslash($_POST['dfw_slug'])) : '';
        if (!preg_match('/\A[a-z0-9-]{1,60}\z/D', $slug)) {
            wp_die('Identifiant invalide.', 'Requête invalide', array('response' => 400));
        }
        if (DFW_Entries::count_for($slug) > 0) {
            wp_die('Ce formulaire contient encore des brouillons ou réponses. Désactivez-le, puis attendez la purge.', 'Suppression bloquée', array('response' => 409));
        }
        $forms = self::forms();
        unset($forms[$slug]);
        update_option('dfw_forms', $forms, false);
        wp_safe_redirect(admin_url('admin.php?page=dendrila-forms&dfw_status=removed'));
        exit;
    }

    public static function export(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Accès refusé.', 'Accès refusé', array('response' => 403));
        }
        $slug = isset($_POST['dfw_slug']) && is_string($_POST['dfw_slug'])
            ? sanitize_title(wp_unslash($_POST['dfw_slug'])) : '';
        if (!preg_match('/\A[a-z0-9-]{1,60}\z/D', $slug)) {
            wp_die('Identifiant invalide.', 'Requête invalide', array('response' => 400));
        }
        check_admin_referer('dfw_export_' . $slug);
        $forms = self::forms();
        if (!isset($forms[$slug])) {
            wp_die('Formulaire introuvable.', 'Requête invalide', array('response' => 404));
        }
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare(
            'SELECT id, answers, status, created_at, submitted_at FROM ' . DFW_Entries::table() .
            ' WHERE form_slug = %s AND status = %s ORDER BY created_at DESC LIMIT 501',
            $slug, 'submitted'
        ), ARRAY_A);
        $rows = (array) $rows;
        $has_more = count($rows) > 500;
        $entries = array();
        foreach (array_slice($rows, 0, 500) as $row) {
            $answers = json_decode((string) $row['answers'], true);
            $entries[] = array(
                'id' => $row['id'], 'answers' => is_array($answers) ? $answers : array(),
                'submitted_at' => $row['submitted_at'],
            );
        }
        nocache_headers();
        header('Content-Type: application/json; charset=UTF-8');
        header('X-Content-Type-Options: nosniff');
        header('Content-Disposition: attachment; filename="dendrila-' . $slug . '-reponses.json"');
        echo wp_json_encode(array('form' => $slug, 'exported_at' => gmdate('c'),
            'limit' => 500, 'more_available' => $has_more, 'entries' => $entries),
            JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    public static function render(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        $forms = self::forms();
        $slug = isset($_GET['edit']) && is_string($_GET['edit']) ? sanitize_title(wp_unslash($_GET['edit'])) : '';
        $example = isset($_GET['example']) && is_string($_GET['example']) ? sanitize_key(wp_unslash($_GET['example'])) : '';
        $json = '';
        $active = false;
        if ($slug !== '' && isset($forms[$slug])) {
            $json = $forms[$slug]['json'];
            $active = !empty($forms[$slug]['active']);
        } elseif ($example === 'survey' || $example === 'interview') {
            $json = wp_json_encode(df_form($example === 'survey' ? 'survey-demo' : 'interview-demo'), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            $slug = $example === 'survey' ? 'mon-enquete' : 'mon-interview';
        }
        echo '<div class="wrap dfw-admin"><h1>Dendrila Forms <span class="dfw-version">alpha</span></h1>';
        echo '<p class="dfw-lead">Créez une enquête ou une interview avec un modèle validé. Seules les personnes administratrices peuvent la configurer.</p>';
        if (isset($_GET['dfw_status']) && in_array($_GET['dfw_status'], array('saved', 'removed'), true)) {
            echo '<div class="notice notice-success"><p>Modification enregistrée.</p></div>';
        }
        echo '<h2>Formulaires existants</h2>';
        if (!$forms) {
            echo '<p>Aucun formulaire. Commencez à partir d’un modèle d’exemple ci-dessous.</p>';
        } else {
            echo '<div class="dfw-list">';
            foreach ($forms as $id => $entry) {
                $label = $id;
                $decoded = json_decode($entry['json'], true);
                if (is_array($decoded) && !empty($decoded['title'])) { $label = $decoded['title']; }
                echo '<div class="dfw-list-item"><div><strong>' . esc_html($label) . '</strong><p>' .
                    (!empty($entry['active']) ? 'Activé' : 'Désactivé') . ' · ' .
                    esc_html((string) DFW_Entries::count_for($id)) . ' enregistrement(s)</p>' .
                    '<label>Code à copier <input readonly onclick="this.select()" value="' .
                    esc_attr('[dendrila_forms id="' . $id . '"]') . '"></label></div>' .
                    '<div class="dfw-actions"><a class="button" href="' .
                    esc_url(admin_url('admin.php?page=dendrila-forms&edit=' . rawurlencode($id))) .
                    '">Configurer</a>' .
                    '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">' .
                    '<input type="hidden" name="action" value="dfw_export">' .
                    '<input type="hidden" name="dfw_slug" value="' . esc_attr($id) . '">' .
                    wp_nonce_field('dfw_export_' . $id, '_wpnonce', true, false) .
                    '<button class="button" type="submit">Exporter les 500 réponses terminées les plus récentes (JSON)</button>' .
                    '</form></div></div>';
            }
            echo '</div>';
        }
        echo '<h2>' . ($slug !== '' && isset($forms[$slug]) ? 'Modifier un formulaire' : 'Créer un formulaire') . '</h2>';
        echo '<div class="dfw-template-choices"><p>Commencez par un modèle prêt à adapter :</p>' .
            '<a class="button" href="' . esc_url(admin_url('admin.php?page=dendrila-forms&example=survey')) . '">Exemple d’enquête</a> ' .
            '<a class="button" href="' . esc_url(admin_url('admin.php?page=dendrila-forms&example=interview')) . '">Exemple d’interview</a></div>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="dfw-form">';
        echo '<input type="hidden" name="action" value="dfw_save">';
        wp_nonce_field('dfw_save');
        echo '<p><label for="dfw_slug"><strong>Identifiant du formulaire</strong></label><br><input id="dfw_slug" class="regular-text" name="dfw_slug" required maxlength="60" value="' .
            esc_attr($slug) . '"' . (isset($forms[$slug]) ? ' readonly' : '') . '></p>';
        echo '<p><label for="dfw_json"><strong>Définition du formulaire (JSON)</strong></label><br>' .
            '<textarea id="dfw_json" name="dfw_json" rows="17" spellcheck="false" required>' .
            esc_textarea($json) . '</textarea></p>';
        echo '<p><label><input type="checkbox" name="dfw_active" value="1" ' . checked($active, true, false) . '> ' .
            'Autoriser les réponses à ce formulaire</label></p>';
        echo '<p class="description">Version de démonstration. Ne collectez pas de données personnelles ou sensibles. Les réponses ne sont pas publiées ni exposées dans l’interface.</p>';
        submit_button('Enregistrer le formulaire');
        echo '</form>';
        if ($slug !== '' && isset($forms[$slug]) && DFW_Entries::count_for($slug) === 0) {
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="dfw_remove"><input type="hidden" name="dfw_slug" value="' . esc_attr($slug) . '">';
            wp_nonce_field('dfw_remove');
            submit_button('Supprimer ce formulaire sans réponses', 'delete', 'submit', false);
            echo '</form>';
        }
        echo '<p><a href="https://github.com/jasmin-abernathy/dendrila-forms-wordpress" target="_blank" rel="noopener noreferrer">Code source et documentation</a></p></div>';
    }
}
