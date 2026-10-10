<?php
defined('ABSPATH') || exit;

/** Private, site-local WordPress storage. The anonymous code is never stored in clear text. */
final class DFW_Entries
{
    public static function table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'dendrila_form_entries';
    }

    public static function install(): void
    {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $table = self::table();
        $charset = $wpdb->get_charset_collate();
        dbDelta("CREATE TABLE {$table} (
            id char(32) NOT NULL,
            form_slug varchar(60) NOT NULL,
            secret_hash char(64) NOT NULL,
            answers longtext NOT NULL,
            cursor varchar(80) NOT NULL DEFAULT '',
            status varchar(12) NOT NULL DEFAULT 'draft',
            revision bigint(20) unsigned NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            submitted_at datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY form_slug (form_slug),
            KEY updated_at (updated_at)
        ) {$charset};");
        if (!wp_next_scheduled('dfw_daily_purge')) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', 'dfw_daily_purge');
        }
    }

    public static function deactivate(): void
    {
        wp_clear_scheduled_hook('dfw_daily_purge');
        // Never discard answers automatically during plugin deactivation.
    }

    public static function purge(): void
    {
        global $wpdb;
        $days = (int) apply_filters('dfw_retention_days', 30);
        $days = max(1, min(365, $days));
        $cutoff = gmdate('Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS);
        $wpdb->query($wpdb->prepare('DELETE FROM ' . self::table() . ' WHERE updated_at < %s', $cutoff));
    }

    public static function throttle(string $action, int $limit, int $window = 600): void
    {
        // Remote address comes from the PHP server, not untrusted proxy headers.
        $address = is_string($_SERVER['REMOTE_ADDR'] ?? null) ? $_SERVER['REMOTE_ADDR'] : 'unknown';
        $key = 'dfw_' . substr(hash_hmac('sha256', $action . "\0" . $address, wp_salt('auth')), 0, 35);
        $count = (int) get_transient($key);
        if ($count >= $limit) {
            throw new RuntimeException('Trop de tentatives. Revenez dans quelques minutes.');
        }
        set_transient($key, $count + 1, $window);
    }

    public static function create(string $slug): string
    {
        global $wpdb;
        self::throttle('create', 8);
        $id = bin2hex(random_bytes(16));
        $secret = bin2hex(random_bytes(32));
        $now = gmdate('Y-m-d H:i:s');
        $success = $wpdb->insert(self::table(), array(
            'id' => $id, 'form_slug' => $slug, 'secret_hash' => hash('sha256', $secret),
            'answers' => '{}', 'cursor' => '', 'status' => 'draft',
            'revision' => 0, 'created_at' => $now, 'updated_at' => $now,
        ));
        if ($success !== 1) {
            throw new RuntimeException('Impossible de créer ce brouillon.');
        }
        return $id . '.' . $secret;
    }

    public static function open(string $token, string $slug): ?array
    {
        if (!preg_match('/\A([a-f0-9]{32})\.([a-f0-9]{64})\z/D', $token, $parts)) {
            return null;
        }
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . self::table() . ' WHERE id = %s AND form_slug = %s',
            $parts[1], $slug
        ), ARRAY_A);
        if (!is_array($row) || !hash_equals((string) $row['secret_hash'], hash('sha256', $parts[2]))) {
            return null;
        }
        $answers = json_decode((string) $row['answers'], true);
        if (!is_array($answers)) {
            throw new RuntimeException('Brouillon inaccessible.');
        }
        $row['answers'] = $answers;
        $row['revision'] = (int) $row['revision'];
        return $row;
    }

    public static function update(array $row, array $answers, string $cursor, string $status): void
    {
        global $wpdb;
        if ($row['status'] !== 'draft' || !in_array($status, array('draft', 'submitted'), true)) {
            throw new RuntimeException('Ce questionnaire est déjà terminé.');
        }
        $data = array(
            'answers' => wp_json_encode($answers),
            'cursor' => $cursor,
            'status' => $status,
            'revision' => $row['revision'] + 1,
            'updated_at' => gmdate('Y-m-d H:i:s'),
        );
        if ($status === 'submitted') {
            $data['submitted_at'] = gmdate('Y-m-d H:i:s');
        }
        $changed = $wpdb->update(
            self::table(), $data,
            array('id' => $row['id'], 'revision' => $row['revision'], 'status' => 'draft')
        );
        if ($changed !== 1) {
            throw new RuntimeException('Une modification plus récente existe. Rechargez la page.');
        }
    }

    public static function delete(string $id): void
    {
        global $wpdb;
        if (!preg_match('/\A[a-f0-9]{32}\z/D', $id)) {
            throw new InvalidArgumentException('Identifiant invalide.');
        }
        $wpdb->delete(self::table(), array('id' => $id));
    }

    public static function count_for(string $slug): int
    {
        global $wpdb;
        return (int) $wpdb->get_var($wpdb->prepare(
            'SELECT COUNT(*) FROM ' . self::table() . ' WHERE form_slug = %s', $slug
        ));
    }

    public static function cookie_name(string $slug): string
    {
        return 'dfw_' . substr(hash('sha256', $slug), 0, 20);
    }

    public static function remember(string $slug, string $token): void
    {
        $name = self::cookie_name($slug);
        $options = array(
            'expires' => $token === '' ? time() - 3600 : time() + 7 * DAY_IN_SECONDS,
            'path' => defined('COOKIEPATH') && COOKIEPATH !== '' ? COOKIEPATH : '/',
            'secure' => is_ssl(), 'httponly' => true, 'samesite' => 'Lax',
        );
        setcookie($name, $token, $options);
        $_COOKIE[$name] = $token;
    }

    public static function token(string $slug): string
    {
        $value = $_COOKIE[self::cookie_name($slug)] ?? '';
        return is_string($value) && strlen($value) <= 100 ? $value : '';
    }
}
