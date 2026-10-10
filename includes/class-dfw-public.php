<?php
defined('ABSPATH') || exit;

final class DFW_Public
{
    private static array $errors = array();

    public static function boot(): void
    {
        add_shortcode('dendrila_forms', array(__CLASS__, 'shortcode'));
        add_action('template_redirect', array(__CLASS__, 'handle_post'), 1);
        add_action('wp_enqueue_scripts', static function (): void {
            wp_register_style('dfw-public', plugins_url('assets/public.css', DFW_FILE), array(), DFW_VERSION);
        });
    }

    private static function definition(string $slug): ?array
    {
        $forms = DFW_Admin::forms();
        if (!isset($forms[$slug]) || !is_array($forms[$slug])) {
            return null;
        }
        try {
            return df_validate_form(json_decode((string) $forms[$slug]['json'], true, 512, JSON_THROW_ON_ERROR));
        } catch (Throwable $error) {
            return null;
        }
    }

    public static function handle_post(): void
    {
        if (!is_singular() || $_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['dfw_action'])) {
            return;
        }
        $slug = isset($_POST['dfw_form']) && is_string($_POST['dfw_form'])
            ? sanitize_key(wp_unslash($_POST['dfw_form'])) : '';
        $action = is_string($_POST['dfw_action']) ? sanitize_key(wp_unslash($_POST['dfw_action'])) : '';
        $nonce = isset($_POST['dfw_nonce']) && is_string($_POST['dfw_nonce'])
            ? sanitize_text_field(wp_unslash($_POST['dfw_nonce'])) : '';
        if (!preg_match('/\A[a-z0-9-]{1,60}\z/D', $slug) || !wp_verify_nonce($nonce, 'dfw_public_' . $slug)) {
            self::$errors[$slug] = 'Session expirée ou requête refusée. Rechargez la page.';
            return;
        }
        $form = self::definition($slug);
        if ($form === null) {
            self::$errors[$slug] = 'Ce formulaire est indisponible.';
            return;
        }
        $forms = DFW_Admin::forms();
        $active = !empty($forms[$slug]['active']);
        try {
            $token = DFW_Entries::token($slug);
            $row = $token === '' ? null : DFW_Entries::open($token, $slug);
            if ($action === 'start') {
                if (!$active) { throw new RuntimeException('Ce formulaire est fermé.'); }
                DFW_Entries::remember($slug, DFW_Entries::create($slug));
            } elseif ($action === 'resume') {
                DFW_Entries::throttle('resume', 24);
                $candidate = isset($_POST['dfw_code']) && is_string($_POST['dfw_code'])
                    ? trim(wp_unslash($_POST['dfw_code'])) : '';
                if (!DFW_Entries::open($candidate, $slug)) {
                    throw new InvalidArgumentException('Code de reprise inconnu.');
                }
                DFW_Entries::remember($slug, $candidate);
            } elseif ($action === 'leave') {
                DFW_Entries::remember($slug, '');
            } else {
                if (!$row) { throw new RuntimeException('Commencez ou reprenez votre questionnaire.'); }
                if ($action === 'delete') {
                    DFW_Entries::delete($row['id']);
                    DFW_Entries::remember($slug, '');
                } elseif ($action === 'review_back') {
                    $steps = df_steps($form, $row['answers']);
                    if ($row['status'] !== 'draft' || $row['cursor'] !== '__review' || !$steps) {
                        throw new RuntimeException('Retour impossible.');
                    }
                    DFW_Entries::update($row, $row['answers'], end($steps)['id'], 'draft');
                } elseif ($action === 'submit') {
                    if (!$active || $row['status'] !== 'draft' || $row['cursor'] !== '__review') {
                        throw new RuntimeException('Envoi impossible.');
                    }
                    $answers = df_prune($form, $row['answers']);
                    foreach (df_steps($form, $answers) as $question) {
                        df_answer($question, $answers[$question['id']] ?? null);
                    }
                    DFW_Entries::update($row, $answers, '__review', 'submitted');
                } elseif ($action === 'next' || $action === 'back') {
                    if (!$active || $row['status'] !== 'draft') {
                        throw new RuntimeException('Le questionnaire est fermé ou terminé.');
                    }
                    $steps = df_steps($form, $row['answers']);
                    $ids = array_column($steps, 'id');
                    $qid = isset($_POST['dfw_question']) && is_string($_POST['dfw_question'])
                        ? sanitize_key(wp_unslash($_POST['dfw_question'])) : '';
                    $position = array_search($qid, $ids, true);
                    if ($position === false || ($row['cursor'] ?: $ids[0]) !== $qid) {
                        throw new RuntimeException('Cette étape a changé. Rechargez la page.');
                    }
                    $value = isset($_POST['dfw_answer']) ? wp_unslash($_POST['dfw_answer']) : '';
                    $answers = $row['answers'];
                    $answers[$qid] = df_answer($steps[$position], $value, $action !== 'back');
                    $answers = df_prune($form, $answers);
                    $newIds = array_column(df_steps($form, $answers), 'id');
                    $newPosition = array_search($qid, $newIds, true);
                    if ($newPosition === false) { throw new RuntimeException('Étape introuvable.'); }
                    $cursor = $action === 'back'
                        ? $newIds[max(0, $newPosition - 1)]
                        : ($newIds[$newPosition + 1] ?? '__review');
                    DFW_Entries::update($row, $answers, $cursor, 'draft');
                } else {
                    throw new InvalidArgumentException('Action inconnue.');
                }
            }
            wp_safe_redirect(get_permalink(get_queried_object_id()), 303);
            exit;
        } catch (InvalidArgumentException | RuntimeException | JsonException $error) {
            self::$errors[$slug] = $error->getMessage();
        }
    }

    private static function hidden(string $slug): string
    {
        return '<input type="hidden" name="dfw_form" value="' . esc_attr($slug) . '">' .
            wp_nonce_field('dfw_public_' . $slug, 'dfw_nonce', true, false);
    }

    private static function action(string $slug, string $action, string $label, string $css = ''): string
    {
        return '<form method="post">' . self::hidden($slug) .
            '<button type="submit" name="dfw_action" value="' . esc_attr($action) . '" class="' .
            esc_attr($css) . '">' . esc_html($label) . '</button></form>';
    }

    private static function value_label(array $question, mixed $value): string
    {
        if (is_array($value)) {
            $labels = array_column($question['options'] ?? array(), 'label', 'value');
            return implode(', ', array_map(static fn ($item) => $labels[$item] ?? $item, $value));
        }
        if ($question['type'] === 'single') {
            $labels = array_column($question['options'], 'label', 'value');
            return $labels[$value] ?? '';
        }
        return (string) $value;
    }

    public static function shortcode($attributes): string
    {
        $attributes = shortcode_atts(array('id' => ''), $attributes, 'dendrila_forms');
        $slug = is_string($attributes['id']) ? $attributes['id'] : '';
        if (!preg_match('/\A[a-z0-9-]{1,60}\z/D', $slug)) {
            return '<p role="status">Formulaire indisponible.</p>';
        }
        $form = self::definition($slug);
        if (!$form || !is_singular()) {
            return '<p role="status">Formulaire indisponible sur cette page.</p>';
        }
        wp_enqueue_style('dfw-public');
        // Avoid cache disclosures: anonymous progress depends on an HttpOnly cookie.
        if (!headers_sent()) { nocache_headers(); }
        $forms = DFW_Admin::forms();
        $active = !empty($forms[$slug]['active']);
        $token = DFW_Entries::token($slug);
        $row = $token === '' ? null : DFW_Entries::open($token, $slug);
        $steps = $row ? df_steps($form, $row['answers']) : array();
        $cursor = $row ? ($row['cursor'] ?: ($steps[0]['id'] ?? '')) : '';
        $index = array_search($cursor, array_column($steps, 'id'), true);
        $question = $index === false ? null : $steps[$index];
        $review = $row && $row['status'] === 'draft' && $cursor === '__review';
        $error = self::$errors[$slug] ?? '';
        ob_start();
        ?>
        <section class="dfw" aria-label="<?php echo esc_attr($form['title']); ?>">
            <p class="dfw-eyebrow"><?php echo $form['mode'] === 'interview' ? 'Interview' : 'Enquête'; ?> · Dendrila Forms</p>
            <h2><?php echo esc_html($form['title']); ?></h2>
            <p class="dfw-intro"><?php echo esc_html($form['description']); ?></p>
            <?php if ($error !== '') : ?>
                <p class="dfw-error" role="alert"><?php echo esc_html($error); ?></p>
            <?php endif; ?>
            <?php if (!$row) : ?>
                <?php if (!$active) : ?>
                    <p role="status">Ce formulaire n’accepte plus de nouvelles réponses.</p>
                <?php else : ?>
                    <p class="dfw-info">Prototype alpha : ne communiquez pas de données personnelles ou sensibles.</p>
                    <?php echo self::action($slug, 'start', 'Commencer', 'dfw-primary'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <details><summary>J’ai déjà un code de reprise</summary>
                        <form method="post" class="dfw-fields"><?php echo self::hidden($slug); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                            <label for="dfw-code-<?php echo esc_attr($slug); ?>">Code de reprise</label>
                            <input id="dfw-code-<?php echo esc_attr($slug); ?>" name="dfw_code" type="text" required autocomplete="off" spellcheck="false">
                            <button name="dfw_action" value="resume" type="submit">Reprendre</button>
                        </form>
                    </details>
                <?php endif; ?>
            <?php elseif ($row['status'] === 'submitted') : ?>
                <p class="dfw-info" role="status">Vos réponses sont enregistrées et verrouillées. Elles ne sont pas publiées automatiquement.</p>
                <details><summary>Supprimer définitivement mes réponses</summary>
                    <p>Cette action est irréversible.</p><?php echo self::action($slug, 'delete', 'Confirmer la suppression', 'dfw-danger'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </details>
            <?php else : ?>
                <div class="dfw-code"><strong>Votre code de reprise</strong>
                    <code><?php echo esc_html($token); ?></code>
                    <p>Conservez-le en lieu sûr : il donne accès aux réponses de ce questionnaire.</p></div>
                <?php if (!$active) : ?>
                    <p role="status">Ce formulaire est fermé. Votre brouillon reste supprimable avec le code de reprise.</p>
                <?php elseif ($review) : ?>
                    <h3>Relire avant de terminer</h3>
                    <?php foreach ($steps as $item) : ?>
                        <div class="dfw-review"><strong><?php echo esc_html($item['label']); ?></strong>
                            <p><?php $text = self::value_label($item, $row['answers'][$item['id']] ?? '');
                                echo $text === '' ? '—' : nl2br(esc_html($text)); ?></p>
                        </div>
                    <?php endforeach; ?>
                    <div class="dfw-buttons"><?php echo self::action($slug, 'review_back', '← Modifier'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        <?php echo self::action($slug, 'submit', 'Terminer et verrouiller', 'dfw-primary'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                <?php elseif ($question) : ?>
                    <p class="dfw-step"><?php echo esc_html($question['section']); ?>
                        <strong>Question <?php echo esc_html((string) ($index + 1)); ?> / <?php echo esc_html((string) count($steps)); ?></strong></p>
                    <progress max="<?php echo esc_attr((string) count($steps)); ?>" value="<?php echo esc_attr((string) ($index + 1)); ?>" aria-label="Progression du questionnaire"></progress>
                    <form method="post" class="dfw-fields"><?php echo self::hidden($slug); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        <input name="dfw_question" type="hidden" value="<?php echo esc_attr($question['id']); ?>">
                        <fieldset><legend><?php echo esc_html($question['label']); ?></legend>
                            <p class="dfw-help"><?php echo !empty($question['required']) ? 'Réponse obligatoire' : 'Réponse facultative'; ?></p>
                            <?php
                            $saved = $error !== '' && isset($_POST['dfw_question']) && $_POST['dfw_question'] === $question['id']
                                ? wp_unslash($_POST['dfw_answer'] ?? '') : ($row['answers'][$question['id']] ?? '');
                            if ($question['type'] === 'text' || $question['type'] === 'textarea') :
                                if ($question['type'] === 'textarea') : ?>
                                    <textarea name="dfw_answer" rows="6"><?php echo esc_textarea(is_string($saved) ? $saved : ''); ?></textarea>
                                <?php else : ?>
                                    <input name="dfw_answer" type="text" value="<?php echo esc_attr(is_string($saved) ? $saved : ''); ?>">
                                <?php endif;
                            else :
                                $options = $question['type'] === 'scale'
                                    ? array_map(static fn ($n) => array('value' => (string) $n, 'label' => (string) $n), range($question['min'], $question['max']))
                                    : $question['options'];
                                foreach ($options as $option) :
                                    $multiple = $question['type'] === 'multiple';
                                    $selected = $multiple
                                        ? is_array($saved) && in_array($option['value'], $saved, true)
                                        : (string) $saved === (string) $option['value']; ?>
                                    <label class="dfw-choice"><input type="<?php echo $multiple ? 'checkbox' : 'radio'; ?>"
                                        name="<?php echo $multiple ? 'dfw_answer[]' : 'dfw_answer'; ?>"
                                        value="<?php echo esc_attr($option['value']); ?>" <?php checked($selected); ?>>
                                        <span><?php echo esc_html($option['label']); ?></span></label>
                                <?php endforeach;
                            endif; ?>
                        </fieldset>
                        <?php if ($form['mode'] === 'interview' && isset($steps[$index + 1])) : ?>
                            <aside class="dfw-next"><strong>Question suivante</strong><p><?php echo esc_html($steps[$index + 1]['label']); ?></p></aside>
                        <?php endif; ?>
                        <div class="dfw-buttons">
                            <?php if ($index > 0) : ?><button formnovalidate name="dfw_action" value="back" type="submit">← Précédent</button><?php endif; ?>
                            <button name="dfw_action" value="next" type="submit" class="dfw-primary"><?php echo $index === count($steps) - 1 ? 'Relire les réponses' : 'Enregistrer et continuer →'; ?></button>
                        </div>
                    </form>
                <?php endif; ?>
                <div class="dfw-bottom"><?php echo self::action($slug, 'leave', 'Quitter sans supprimer'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <details><summary>Supprimer mes réponses</summary>
                        <?php echo self::action($slug, 'delete', 'Confirmer la suppression définitive', 'dfw-danger'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </details></div>
            <?php endif; ?>
        </section>
        <?php
        return (string) ob_get_clean();
    }
}
