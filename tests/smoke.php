<?php
declare(strict_types=1);
define('ABSPATH', __DIR__ . '/');
require __DIR__ . '/../vendor/dendrila-forms/src/Core.php';
require __DIR__ . '/../includes/class-dfw-admin.php';
require __DIR__ . '/../includes/class-dfw-entries.php';
function check(bool $ok, string $why): void
{
    if (!$ok) { throw new RuntimeException('Échec : ' . $why); }
}
$survey = df_form('survey-demo');
$interview = df_form('interview-demo');
check(df_validate_form($survey) === $survey, 'modèle en mémoire');
check(count(df_steps($survey, array())) === 3, 'questions conditionnelles masquées');
check(count(df_steps($survey, array('frequency' => 'daily'))) === 4, 'questions conditionnelles visibles');
check(count(df_steps($interview, array())) === 5, 'parcours interview');
$optional = array('id' => 'extra', 'type' => 'multiple', 'required' => false,
    'options' => array(array('value' => 'a'), array('value' => 'b')));
check(df_answer($optional, '') === array(), 'multiples sans sélection');
try {
    df_answer($optional, array('interdit'));
    check(false, 'option inconnue acceptée');
} catch (InvalidArgumentException $expected) {
}
check(DFW_Entries::cookie_name('mon-enquete') !== DFW_Entries::cookie_name('mon-interview'), 'cookies séparés');
check(!str_contains(file_get_contents(__DIR__ . '/../includes/class-dfw-entries.php'), 'manage_woocommerce'),
    'aucun privilège commerce intégré');
check(str_contains(file_get_contents(__DIR__ . '/../includes/class-dfw-admin.php'), 'manage_options'),
    'administration restreinte');
echo "Dendrila Forms WordPress : contrats OK\n";
