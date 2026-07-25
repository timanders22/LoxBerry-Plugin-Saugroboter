<?php
/**
 * Saugroboter (Valetudo) - Admin-Oberflaeche (v1.0.0)
 * Reiter: Einstellungen | Einbindung in Loxone | Test | Protokoll
 * Kompatibel mit PHP 7.4 und PHP 8.x (LoxBerry 3.x/4.x).
 *
 * WICHTIG: LBWeb::lbheader() setzt SDK-GLOBALS (u.a. $cfg aus general.json als
 * stdClass) und wuerde gleichnamige Plugin-Variablen ueberschreiben - daher
 * tragen hier ALLE Variablen ein rb_-Praefix.
 */

error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
ini_set('display_errors', '1');

$rb_lbhome = getenv('LBHOMEDIR') ?: (is_dir('/opt/loxberry') ? '/opt/loxberry' : '');
$rb_plugin = getenv('LBPPLUGINDIR') ?: basename(__DIR__);
if ($rb_lbhome && is_dir($rb_lbhome . '/config/plugins/' . $rb_plugin) === false) {
    $rb_plugin = basename(dirname(__DIR__));
    if (is_dir($rb_lbhome . '/config/plugins/' . $rb_plugin) === false) { $rb_plugin = 'saugrobo'; }
}
if ($rb_lbhome) {
    $rb_sdk = $rb_lbhome . '/libs/phplib/loxberry_system.php';
    if (file_exists($rb_sdk)) { require_once $rb_sdk; require_once $rb_lbhome . '/libs/phplib/loxberry_web.php'; }
    $rb_cfgdir = $rb_lbhome . '/config/plugins/' . $rb_plugin;
    $rb_bkfile = $rb_lbhome . '/config/plugins/' . $rb_plugin . '.backup.json';
    $rb_logfile = $rb_lbhome . '/log/plugins/' . $rb_plugin . '/robo.log';
} else {
    $rb_cfgdir = dirname(dirname(__DIR__)) . '/config';
    $rb_bkfile = $rb_cfgdir . '/robo.backup.json';
    $rb_logfile = sys_get_temp_dir() . '/saugrobo/robo.log';
}
$rb_cfgfile = $rb_cfgdir . '/robo.json';

foreach (array(dirname(dirname(dirname(__DIR__))) . '/html/plugins/' . $rb_plugin . '/robo_lib.php',
               dirname(__DIR__) . '/html/robo_lib.php') as $rb_cand) {
    if (is_file($rb_cand)) { require_once $rb_cand; break; }
}

if ((!is_file($rb_cfgfile) || trim((string) @file_get_contents($rb_cfgfile)) === '' || trim((string) @file_get_contents($rb_cfgfile)) === '{}') && is_file($rb_bkfile)) {
    @mkdir($rb_cfgdir, 0775, true);
    @copy($rb_bkfile, $rb_cfgfile);
}

$rb_saved = false; $rb_err = ''; $rb_note = '';
$rb_tab = preg_match('/^tab-(settings|loxone|test|log)$/', (string) (isset($_POST['activetab']) ? $_POST['activetab'] : '')) ? $_POST['activetab'] : 'tab-settings';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clearlog'])) {
    @mkdir(dirname($rb_logfile), 0775, true);
    @file_put_contents($rb_logfile, '[' . date('Y-m-d H:i:s') . "] Protokoll geleert (Admin-Oberflaeche)\n");
    $rb_tab = 'tab-log';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    $rb_new = array();
    $rb_new['robots'] = array();
    $rb_n = isset($_POST['r_name']) ? (array) $_POST['r_name'] : array();
    $rb_i2 = isset($_POST['r_ip']) ? (array) $_POST['r_ip'] : array();
    $rb_p2 = isset($_POST['r_port']) ? (array) $_POST['r_port'] : array();
    for ($rb_i = 0; $rb_i < 2; $rb_i++) {
        $ip = trim((string) (isset($rb_i2[$rb_i]) ? $rb_i2[$rb_i] : ''));
        if ($ip === '') { continue; }
        if (!preg_match('/^[\w\.\-]+$/', $ip)) { $rb_err = 'Roboter ' . ($rb_i + 1) . ': ung&uuml;ltige Adresse.'; continue; }
        $rb_new['robots'][] = array('name' => trim((string) (isset($rb_n[$rb_i]) ? $rb_n[$rb_i] : '')),
            'ip' => $ip, 'port' => max(1, min(65535, (int) (isset($rb_p2[$rb_i]) ? $rb_p2[$rb_i] : 80))));
    }
    $rb_new['cache_sec'] = max(5, min(300, (int) (isset($_POST['cache_sec']) ? $_POST['cache_sec'] : 20)));
    $rb_new['warn_hours'] = max(0, min(200, (int) (isset($_POST['warn_hours']) ? $_POST['warn_hours'] : 10)));
    $rb_new['mqtt_enabled'] = isset($_POST['mqtt_enabled']) ? 1 : 0;
    $rb_new['mqtt_topic'] = preg_replace('#[^\w/\-]#', '', (string) (isset($_POST['mqtt_topic']) ? $_POST['mqtt_topic'] : 'saugrobo')) ?: 'saugrobo';
    $rb_new['notify'] = array(
        'audio' => isset($_POST['notify_audio']) ? 1 : 0,
        'push' => isset($_POST['notify_push']) ? 1 : 0,
        'fertig' => isset($_POST['n_fertig']) ? 1 : 0,
        'fehler' => isset($_POST['n_fehler']) ? 1 : 0,
        'material' => isset($_POST['n_material']) ? 1 : 0,
    );
    $rb_mode = (string) (isset($_POST['tts_mode']) ? $_POST['tts_mode'] : 'musicserver');
    $rb_new['tts'] = array(
        'mode' => in_array($rb_mode, array('musicserver', 'ms4h', 'audioserver', 'custom'), true) ? $rb_mode : 'musicserver',
        'ip' => trim((string) (isset($_POST['tts_ip']) ? $_POST['tts_ip'] : '')),
        'port' => max(1, min(65535, (int) (isset($_POST['tts_port']) ? $_POST['tts_port'] : 7091))),
        'zones' => trim((string) (isset($_POST['tts_zones']) ? $_POST['tts_zones'] : '1')),
        'volume' => max(1, min(100, (int) (isset($_POST['tts_volume']) ? $_POST['tts_volume'] : 8))),
        'lang' => preg_replace('/[^a-z]/', '', strtolower((string) (isset($_POST['tts_lang']) ? $_POST['tts_lang'] : 'de'))) ?: 'de',
        'template' => trim((string) (isset($_POST['tts_template']) ? $_POST['tts_template'] : '')),
    );
    if ($rb_err === '') {
        if (!is_dir($rb_cfgdir)) { @mkdir($rb_cfgdir, 0775, true); }
        $rb_json = json_encode($rb_new, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (@file_put_contents($rb_cfgfile, $rb_json) !== false) {
            $rb_saved = true;
            @copy($rb_cfgfile, $rb_bkfile);
            foreach (glob('/tmp/saugrobo/state_*.json') ?: array() as $g) { @unlink($g); }
        } else {
            $rb_err = 'Konfiguration konnte nicht gespeichert werden: ' . $rb_cfgfile;
        }
    }
}

$rb_cfg = function_exists('ro_config') ? ro_config() : array();
if (!is_array($rb_cfg)) { $rb_cfg = array(); }
$rb_cfg += array('robots' => array(), 'cache_sec' => 20, 'warn_hours' => 10, 'mqtt_enabled' => 0,
    'mqtt_topic' => 'saugrobo', 'notify' => array(), 'tts' => array());
$rb_notify = is_array($rb_cfg['notify']) ? $rb_cfg['notify'] : array();
$rb_notify += array('audio' => 0, 'push' => 0, 'fertig' => 1, 'fehler' => 1, 'material' => 1);
$rb_tts = is_array($rb_cfg['tts']) ? $rb_cfg['tts'] : array();
$rb_tts += array('mode' => 'musicserver', 'ip' => '', 'port' => 7091, 'zones' => '1', 'volume' => 8, 'lang' => 'de', 'template' => '');
$rb_robots = function_exists('ro_robots') ? ro_robots() : array();
$rb_states = array();
foreach ($rb_robots as $rb_k => $rb_r) { $rb_states[$rb_k] = ro_state($rb_k); }
$rb_loglines = array();
if (is_file($rb_logfile)) {
    $rb_loglines = array_slice(array_reverse(file($rb_logfile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: array()), 0, 300);
}

function rb_e($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
function rb_h($h) { return $h < 0 ? '&ndash;' : (int) $h . ' h'; }

$rb_frame = class_exists('LBWeb', false);
if ($rb_frame) { LBWeb::lbheader('Saugroboter', 'https://wiki.loxberry.de/', ''); }
$rb_host = rb_e(isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '<loxberry-ip>');
?>
<style>
.rb-wrap { max-width: 940px; margin: 0 auto; font-family: -apple-system, 'Segoe UI', Roboto, sans-serif; color: #333; }
.rb-wrap h2 { color: #6dac20; margin: 24px 0 10px; font-size: 1.15em; border-bottom: 2px solid #e0e0e0; padding-bottom: 6px; }
.rb-wrap label { display: block; font-weight: 600; font-size: 0.88em; color: #555; margin: 10px 0 4px; }
.rb-wrap input[type=text], .rb-wrap input[type=number], .rb-wrap select, .rb-wrap textarea {
  width: 100%; padding: 8px 10px; border: 1px solid #ccc; border-radius: 6px; font-size: 0.95em; box-sizing: border-box; }
.rb-wrap input[type=checkbox] { width: 17px; height: 17px; margin: 0; vertical-align: middle; }
.rb-row { display: flex; gap: 12px; flex-wrap: wrap; }
.rb-row > div { flex: 1; min-width: 150px; }
.rb-row > div > label:not([style]) { min-height: 2.6em; display: flex; align-items: flex-end; }
.rb-btn { background: #6dac20; color: #fff !important; border: 0; border-radius: 6px; padding: 10px 22px; font-size: 1em; cursor: pointer; margin-top: 18px; font-weight: 600; }
.rb-alert { border-radius: 8px; padding: 10px 14px; margin: 12px 0; }
.rb-ok { background: #e8f5e9; border: 1px solid #a5d6a7; }
.rb-err { background: #ffebee; border: 1px solid #ef9a9a; }
.rb-warn { background: #fff8e1; border: 1px solid #ffe082; }
.rb-info { background: #e3f2fd; border: 1px solid #90caf9; font-size: 0.9em; }
.rb-mono { font-family: ui-monospace, monospace; background: #f5f5f5; padding: 2px 6px; border-radius: 4px; }
.rb-small { font-size: 0.82em; color: #666; margin-top: 3px; }
.rb-tabs { display: flex; gap: 4px; margin: 14px 0 0; border-bottom: 2px solid #6dac20; flex-wrap: wrap; }
.rb-tab { background: #eee; border: 1px solid #ccc; border-bottom: 0; border-radius: 8px 8px 0 0; padding: 9px 18px; cursor: pointer; font-size: 0.95em; color: #444 !important; text-shadow: none !important; }
.rb-tab.rb-active { background: #6dac20; color: #fff !important; border-color: #6dac20; font-weight: 600; }
.rb-pane { display: none; padding-top: 4px; }
.rb-pane.rb-active { display: block; }
.rb-log { text-shadow: none !important; background: #1e1e1e; color: #d4d4d4; font-family: ui-monospace, monospace; font-size: 0.82em; padding: 12px; border-radius: 8px; max-height: 480px; overflow: auto; white-space: pre-wrap; }
.rb-step { margin: 10px 0; padding: 10px 14px; background: #fafafa; border-left: 4px solid #6dac20; border-radius: 0 8px 8px 0; }
.rb-tbl { border-collapse: collapse; margin: 8px 0; }
.rb-tbl th, .rb-tbl td { border: 1px solid #ddd; padding: 6px 10px; text-align: left; font-size: 0.9em; }
.rb-tbl th { background: #f0f0f0; }
.rb-wrap .rb-btn, .rb-wrap a.rb-btn, .rb-wrap button { text-shadow: none !important; box-shadow: none !important; }
.rb-wrap a.rb-btn, .rb-wrap a.rb-btn:visited, .rb-wrap a.rb-btn:hover { color: #fff !important; text-decoration: none; }
</style>
<div class="rb-wrap">

<?php if ($rb_saved) { ?><div class="rb-alert rb-ok"><b>Konfiguration gespeichert</b> (inkl. Sicherungskopie f&uuml;r Updates).</div><?php } ?>
<?php if ($rb_note !== '') { ?><div class="rb-alert rb-ok"><?= rb_e($rb_note) ?></div><?php } ?>
<?php if ($rb_err !== '') { ?><div class="rb-alert rb-err"><b>Fehler:</b> <?= $rb_err ?></div><?php } ?>

<?php if (!$rb_robots) { ?>
<div class="rb-alert rb-info"><b>Noch kein Roboter eingerichtet.</b> Bitte unten die Adresse der Valetudo-Oberfl&auml;che eintragen und speichern.</div>
<?php } ?>
<?php foreach ($rb_states as $rb_k => $rb_s) { ?>
<div class="rb-alert <?= $rb_s['fehler'] ? 'rb-warn' : 'rb-info' ?>">
<b><?= rb_e($rb_s['name']) ?></b>:
<?php if ($rb_s['ok']) { ?>
<b><?= rb_e($rb_s['text']) ?></b> &middot; Batterie <?= (int) $rb_s['batterie'] ?> %<?= $rb_s['laedt'] ? ' (l&auml;dt)' : '' ?>
<?= $rb_s['fehler'] ? ' &middot; <b>Fehler ' . (int) $rb_s['fehler'] . '</b> ' . rb_e($rb_s['fehlertext']) : '' ?><br>
Letzte Reinigung: <?= rb_e($rb_s['flaeche']) ?> m&sup2; in <?= (int) $rb_s['dauer'] ?> min<?= $rb_s['letzte'] ? ' (' . rb_e(date('d.m.Y H:i', $rb_s['letzte'])) . ')' : '' ?><br>
Gesamt: <?= rb_e($rb_s['flaeche_gesamt']) ?> m&sup2; &middot; <?= rb_e($rb_s['dauer_gesamt']) ?> h &middot; <?= (int) $rb_s['anzahl_gesamt'] ?> Reinigungen<br>
Verbrauchsmaterial: Filter <?= rb_h($rb_s['filter']) ?> &middot; Hauptb&uuml;rste <?= rb_h($rb_s['buerste_haupt']) ?> &middot;
Seitenb&uuml;rste <?= rb_h($rb_s['buerste_seite']) ?> &middot; Sensoren <?= rb_h($rb_s['sensor']) ?>
<?= $rb_s['material_warn'] ? ' &rarr; <b>Wartung f&auml;llig</b>' : '' ?>
<?php } else { ?>
<b>keine Verbindung</b> &mdash; Adresse pr&uuml;fen (Valetudo-Oberfl&auml;che im Browser erreichbar?).
<?php } ?>
</div>
<?php } ?>

<div class="rb-tabs">
    <div class="rb-tab" data-pane="tab-settings">Einstellungen</div>
    <div class="rb-tab" data-pane="tab-loxone">Einbindung in Loxone</div>
    <div class="rb-tab" data-pane="tab-test">Test</div>
    <div class="rb-tab" data-pane="tab-log">Protokoll</div>
</div>

<!-- ================= Einstellungen ================= -->
<div class="rb-pane" id="tab-settings">
<form method="post" autocomplete="off">
<input data-role="none" type="hidden" name="save" value="1">
<input data-role="none" type="hidden" name="activetab" value="tab-settings">

<h2>Roboter (bis zu 2)</h2>
<table class="rb-tbl" style="width:100%;">
<tr><th style="width:36px;">Nr.</th><th style="width:34%;">Name (frei)</th><th>Adresse (IP oder Hostname)</th><th style="width:100px;">Port</th></tr>
<?php for ($rb_i = 0; $rb_i < 2; $rb_i++) {
    $rb_r = isset($rb_cfg['robots'][$rb_i]) ? (array) $rb_cfg['robots'][$rb_i] : array();
    $rb_r += array('name' => '', 'ip' => '', 'port' => 80); ?>
<tr>
<td><?= $rb_i + 1 ?></td>
<td><input data-role="none" type="text" name="r_name[]" value="<?= rb_e($rb_r['name']) ?>" placeholder="<?= $rb_i === 0 ? 'z. B. Saugroboter OG' : 'leer = ungenutzt' ?>"></td>
<td><input data-role="none" type="text" name="r_ip[]" value="<?= rb_e($rb_r['ip']) ?>" placeholder="<?= $rb_i === 0 ? 'z. B. 192.168.1.36' : '' ?>"></td>
<td><input data-role="none" type="number" name="r_port[]" value="<?= (int) $rb_r['port'] ?>" min="1" max="65535"></td>
</tr>
<?php } ?>
</table>
<div class="rb-small">Voraussetzung: Auf dem Roboter l&auml;uft <b>Valetudo</b> (cloudfreie Firmware). Die Adresse ist dieselbe,
unter der die Valetudo-Weboberfl&auml;che erreichbar ist. Roboter 2 wird in Loxone mit <span class="rb-mono">&amp;dev=2</span> abgefragt.</div>

<div class="rb-row">
    <div>
        <label>Status-Cache (Sekunden)</label>
        <input data-role="none" type="number" name="cache_sec" value="<?= (int) $rb_cfg['cache_sec'] ?>" min="5" max="300">
        <div class="rb-small">H&auml;ufigere Abfragen werden aus dem Zwischenspeicher beantwortet. Empfehlung 20 &mdash;
        damit gen&uuml;gt EINE Loxone-Abfrage alle 30 s statt bisher vier Abfragen alle 10 s.</div>
    </div>
    <div>
        <label>Warnschwelle Verbrauchsmaterial (Stunden)</label>
        <input data-role="none" type="number" name="warn_hours" value="<?= (int) $rb_cfg['warn_hours'] ?>" min="0" max="200">
        <div class="rb-small">Unterhalb dieser Restlaufzeit meldet das Plugin &bdquo;Wartung f&auml;llig&ldquo; (<span class="rb-mono">MATWARN=1</span>).</div>
    </div>
</div>

<h2>Meldungen</h2>
<div style="margin-bottom:10px;">
    <label style="display:inline-flex;align-items:center;gap:6px;margin-right:24px;">
        <input data-role="none" type="checkbox" name="notify_audio" <?= !empty($rb_notify['audio']) ? 'checked' : '' ?>> Audioausgabe aktiv
    </label>
    <label style="display:inline-flex;align-items:center;gap:6px;">
        <input data-role="none" type="checkbox" name="notify_push" <?= !empty($rb_notify['push']) ? 'checked' : '' ?>> Push-Nachricht aktiv
    </label>
    <div class="rb-small">Die Ansage spricht das Plugin selbst; den Push verschickt der Miniserver &uuml;ber <span class="rb-mono">ANN=1</span>.</div>
</div>
<div>
    <label style="display:inline-flex;align-items:center;gap:6px;margin-right:20px;">
        <input data-role="none" type="checkbox" name="n_fertig" <?= !empty($rb_notify['fertig']) ? 'checked' : '' ?>> Reinigung fertig (mit Fl&auml;che und Dauer)
    </label>
    <label style="display:inline-flex;align-items:center;gap:6px;margin-right:20px;">
        <input data-role="none" type="checkbox" name="n_fehler" <?= !empty($rb_notify['fehler']) ? 'checked' : '' ?>> St&ouml;rung/Fehler
    </label>
    <label style="display:inline-flex;align-items:center;gap:6px;">
        <input data-role="none" type="checkbox" name="n_material" <?= !empty($rb_notify['material']) ? 'checked' : '' ?>> Wartung f&auml;llig (h&ouml;chstens 1&times; t&auml;glich)
    </label>
</div>

<h2>Sprachausgabe</h2>
<div class="rb-row">
    <div>
        <label>Audio-Ausgabe</label>
        <select data-role="none" name="tts_mode" id="tts_mode" onchange="rbTtsMode()">
            <option value="musicserver"<?= $rb_tts['mode'] === 'musicserver' ? ' selected' : '' ?>>Loxone Music Server (klassisch)</option>
            <option value="ms4h"<?= $rb_tts['mode'] === 'ms4h' ? ' selected' : '' ?>>Audioserver4Home / MusicServer4Home</option>
            <option value="audioserver"<?= $rb_tts['mode'] === 'audioserver' ? ' selected' : '' ?>>Original Loxone Audioserver (via Loxone Config)</option>
            <option value="custom"<?= $rb_tts['mode'] === 'custom' ? ' selected' : '' ?>>Eigene URL-Vorlage</option>
        </select>
    </div>
    <div>
        <label>IP des Audio-Servers</label>
        <input data-role="none" type="text" name="tts_ip" value="<?= rb_e($rb_tts['ip']) ?>" placeholder="z. B. 192.168.1.50">
    </div>
    <div>
        <label>Port</label>
        <input data-role="none" type="number" name="tts_port" value="<?= (int) $rb_tts['port'] ?>" min="1" max="65535">
    </div>
</div>
<div class="rb-row">
    <div>
        <label>Zonen</label>
        <input data-role="none" type="text" name="tts_zones" value="<?= rb_e($rb_tts['zones']) ?>" placeholder="z. B. 2,4,6">
        <div class="rb-small">Zonennummern mit Komma (z.&nbsp;B. <span class="rb-mono">2,4,6</span>) &mdash; die Lautst&auml;rke kommt aus dem Feld daneben. Optional je Zone eigene Lautst&auml;rke: <span class="rb-mono">Zone~Lautst&auml;rke</span> (z.&nbsp;B. <span class="rb-mono">2~25,4~40</span>). Leerzeichen nach dem Komma sind erlaubt &mdash; <span class="rb-mono">2,4,6</span> und <span class="rb-mono">2, 4, 6</span> funktionieren beide.</div>
    </div>
    <div>
        <label>Lautst&auml;rke (%)</label>
        <input data-role="none" type="number" name="tts_volume" value="<?= (int) $rb_tts['volume'] ?>" min="1" max="100">
    </div>
    <div>
        <label>Sprache</label>
        <input data-role="none" type="text" name="tts_lang" value="<?= rb_e($rb_tts['lang']) ?>" maxlength="2">
    </div>
</div>
<div id="tts_template_row">
    <label>URL-Vorlage (f&uuml;r Audioserver4Home/MS4H bzw. eigene Ausgabe)</label>
    <textarea data-role="none" name="tts_template" id="tts_template" rows="2" placeholder="http://{ip}:{port}/tts?text={text}&amp;zone={zones}&amp;vol={vol}"><?= rb_e($rb_tts['template']) ?></textarea>
    <div class="rb-small">Platzhalter: <span class="rb-mono">{ip} {port} {zones} {vol} {lang} {text}</span>. Leer = Standard-Vorlage.</div>
</div>
<div id="tts_audioserver_hint" class="rb-alert rb-info" style="display:none;">
    Der originale Loxone Audioserver bietet keine HTTP-TTS-Schnittstelle. In diesem Modus spricht das Plugin nicht selbst;
    die Ausgabe baut man in Loxone Config &uuml;ber Textgenerator und <span class="rb-mono">ANN=1</span>.
</div>

<h2>MQTT (optional)</h2>
<label style="display:inline-flex;align-items:center;gap:6px;">
    <input data-role="none" type="checkbox" name="mqtt_enabled" <?= !empty($rb_cfg['mqtt_enabled']) ? 'checked' : '' ?>> Zustand per MQTT ver&ouml;ffentlichen
</label>
<div class="rb-row" style="margin-top:6px;">
    <div>
        <label>Topic-Pr&auml;fix</label>
        <input data-role="none" type="text" name="mqtt_topic" value="<?= rb_e($rb_cfg['mqtt_topic']) ?>" placeholder="saugrobo">
        <div class="rb-small">Ver&ouml;ffentlicht u.&nbsp;a. <span class="rb-mono"><?= rb_e($rb_cfg['mqtt_topic']) ?>/code</span>,
        <span class="rb-mono">/status</span>, <span class="rb-mono">/batterie</span>, <span class="rb-mono">/fehler</span>,
        <span class="rb-mono">/flaeche</span>, <span class="rb-mono">/filter</span>, <span class="rb-mono">/material_warn</span>
        (Roboter 2: <span class="rb-mono"><?= rb_e($rb_cfg['mqtt_topic']) ?>/2/...</span>).</div>
    </div>
</div>

<button data-role="none" class="rb-btn" type="submit">Speichern</button>
</form>
</div>

<!-- ================= Einbindung in Loxone ================= -->
<div class="rb-pane" id="tab-loxone">
<h2>Einbindung in Loxone &mdash; Schritt f&uuml;r Schritt</h2>
<p>Das Plugin fasst die vier Valetudo-Schnittstellen zu <b>einer</b> Abfrage zusammen. Statt bisher vier virtuellen
Eing&auml;ngen im 10-Sekunden-Takt gen&uuml;gt einer alle 30 Sekunden &mdash; und statt Buchstaben aus dem Status-Text
zu zerlegen, gibt es eine saubere <b>Statuszahl</b>.</p>

<div class="rb-step"><b>Schritt 1: Virtueller HTTP-Eingang &bdquo;Saugroboter&ldquo;</b> (Abfrage alle 30 s)
<table class="rb-tbl">
<tr><th>Eigenschaft</th><th>Wert</th></tr>
<tr><td>URL</td><td><span class="rb-mono">http://<?= $rb_host ?>/plugins/<?= rb_e($rb_plugin) ?>/robo.php</span> (Roboter 2: <span class="rb-mono">?dev=2</span>)</td></tr>
<tr><td>Abfragezyklus</td><td>30 Sekunden</td></tr>
</table>
</div>

<div class="rb-step"><b>Schritt 2: Befehlserkennungen</b>
<table class="rb-tbl">
<tr><th>Befehlserkennung</th><th>Bedeutung</th></tr>
<tr><td><span class="rb-mono">\iCODE=\i\v</span></td><td><b>Statuszahl</b>: 0 = Ladestation, 1 = bereit, 2 = reinigt, 3 = pausiert, 4 = f&auml;hrt zur Station, 5 = f&auml;hrt, 8 = unbekannt, 9 = Fehler</td></tr>
<tr><td><span class="rb-mono">\iBATT=\i\v</span> / <span class="rb-mono">\iLAEDT=\i\v</span></td><td>Batterie in % / 1 = l&auml;dt gerade</td></tr>
<tr><td><span class="rb-mono">\iFEHLER=\i\v</span></td><td>Fehlercode (0 = kein Fehler)</td></tr>
<tr><td><span class="rb-mono">\iFLAECHE=\i\v</span> / <span class="rb-mono">\iDAUER=\i\v</span></td><td>letzte Reinigung: m&sup2; und Minuten</td></tr>
<tr><td><span class="rb-mono">\iFLAECHEG=\i\v</span> / <span class="rb-mono">\iDAUERG=\i\v</span> / <span class="rb-mono">\iANZAHLG=\i\v</span></td><td>Gesamtwerte: m&sup2;, Stunden, Anzahl Reinigungen</td></tr>
<tr><td><span class="rb-mono">\iFILTER=\i\v</span> / <span class="rb-mono">\iBHAUPT=\i\v</span> / <span class="rb-mono">\iBSEITE=\i\v</span> / <span class="rb-mono">\iSENSOR=\i\v</span></td><td>Verbrauchsmaterial: Reststunden bis zum Wechsel (&minus;1 = nicht verf&uuml;gbar)</td></tr>
<tr><td><span class="rb-mono">\iMATWARN=\i\v</span></td><td>1 = mindestens ein Teil unter der Warnschwelle</td></tr>
<tr><td><span class="rb-mono">\iANN=\i\v</span> / <span class="rb-mono">\iPUSH=\i\v</span> / <span class="rb-mono">\iPTEST=\i\v</span></td><td>Meldefenster / Push-Freigabe / Test-Push</td></tr>
<tr><td><span class="rb-mono">\iOK=\i\v</span></td><td>1 = Roboter erreichbar</td></tr>
</table>
</div>

<div class="rb-step"><b>Schritt 3: Steuerung &uuml;ber einen Virtuellen Ausgang</b><br>
Valetudo verlangt eigentlich PUT-Aufrufe mit JSON-Rumpf. Das Plugin macht daraus einfache Adressen, die Loxone
direkt senden kann.
<table class="rb-tbl">
<tr><th>Eigenschaft</th><th>Wert</th></tr>
<tr><td>Adresse (Virtueller Ausgang)</td><td><span class="rb-mono">http://<?= $rb_host ?></span></td></tr>
</table>
<table class="rb-tbl">
<tr><th>Befehl bei EIN</th><th>Wirkung</th></tr>
<tr><td><span class="rb-mono">/plugins/<?= rb_e($rb_plugin) ?>/robo.php?cmd=start</span></td><td>Komplettreinigung starten</td></tr>
<tr><td><span class="rb-mono">/plugins/<?= rb_e($rb_plugin) ?>/robo.php?cmd=pause</span></td><td>pausieren</td></tr>
<tr><td><span class="rb-mono">/plugins/<?= rb_e($rb_plugin) ?>/robo.php?cmd=stop</span></td><td>stoppen</td></tr>
<tr><td><span class="rb-mono">/plugins/<?= rb_e($rb_plugin) ?>/robo.php?cmd=home</span></td><td>zur Ladestation</td></tr>
<tr><td><span class="rb-mono">/plugins/<?= rb_e($rb_plugin) ?>/robo.php?cmd=locate</span></td><td>Roboter piepsen lassen (wiederfinden)</td></tr>
<tr><td><span class="rb-mono">/plugins/<?= rb_e($rb_plugin) ?>/robo.php?cmd=segments&amp;p=1,4</span></td><td>nur bestimmte R&auml;ume reinigen (IDs siehe Reiter Test)</td></tr>
<tr><td><span class="rb-mono">/plugins/<?= rb_e($rb_plugin) ?>/robo.php?cmd=fan&amp;p=max</span></td><td>Saugst&auml;rke (low, medium, high, max, turbo)</td></tr>
</table>
</div>

<div class="rb-step"><b>Schritt 4: Komplette Baustein-Liste zum 1:1-Nachbauen</b><br>
<b>4a) Kacheln und Zustandsanzeige</b>
<table class="rb-tbl">
<tr><th>Baustein</th><th>Name</th><th>Einstellung</th><th>Eing&auml;nge</th></tr>
<tr><td>Statusbaustein</td><td>Saugroboter-Zustand</td><td>Texte je Wert: 0 &bdquo;in der Ladestation&ldquo;, 1 &bdquo;bereit&ldquo;, 2 &bdquo;reinigt&ldquo;, 3 &bdquo;pausiert&ldquo;, 4 &bdquo;f&auml;hrt zur Station&ldquo;, 9 &bdquo;St&ouml;rung&ldquo;</td><td>I1 &larr; CODE</td></tr>
<tr><td>Analoganzeigen</td><td>Batterie / Fl&auml;che / Dauer</td><td>Einheiten <span class="rb-mono">&lt;v.0&gt; %</span>, <span class="rb-mono">&lt;v.1&gt; m&sup2;</span>, <span class="rb-mono">&lt;v.0&gt; min</span></td><td>&larr; BATT, FLAECHE, DAUER</td></tr>
<tr><td>Analoganzeigen</td><td>Filter / B&uuml;rsten / Sensoren</td><td>Einheit <span class="rb-mono">&lt;v.0&gt; h</span> &mdash; Restlaufzeit bis zur Wartung</td><td>&larr; FILTER, BHAUPT, BSEITE, SENSOR</td></tr>
</table>
<b>4b) Meldungen (fertig, St&ouml;rung, Wartung)</b>
<table class="rb-tbl">
<tr><th>Baustein</th><th>Name</th><th>Einstellung</th><th>Eing&auml;nge</th></tr>
<tr><td>Schwellwertschalter S1</td><td>Meldefenster aktiv</td><td>Ein 0,5 / Aus 0,4</td><td>&larr; ANN</td></tr>
<tr><td>Schwellwertschalter S2</td><td>Push freigegeben</td><td>Ein 0,5 / Aus 0,4</td><td>&larr; PUSH</td></tr>
<tr><td>UND U1 + ODER O1</td><td>Roboter-Meldung</td><td>O1 ist die einzige Quelle des Benachrichtigungs-Bausteins</td><td>U1: S1 &amp; S2</td></tr>
<tr><td>Benachrichtigungs-Baustein</td><td>Push &bdquo;Saugroboter&ldquo;</td><td>Text z. B. &bdquo;Saugroboter-Meldung &mdash; Details in der App&ldquo;</td><td>&larr; O1</td></tr>
<tr><td>Schwellwertschalter S3</td><td>St&ouml;rung</td><td>Ein 0,5 an FEHLER &mdash; f&uuml;r eine eigene Warnkachel</td><td>&larr; FEHLER</td></tr>
<tr><td>Schwellwertschalter S4</td><td>Wartung f&auml;llig</td><td>Ein 0,5 an MATWARN</td><td>&larr; MATWARN</td></tr>
<tr><td>Benachrichtigungs-Baustein 2</td><td>Test-Push</td><td>eigener Baustein NUR f&uuml;r den Test</td><td>&larr; Schwellwertschalter an PTEST</td></tr>
</table>
<b>4c) Automatisch saugen, wenn niemand da ist</b>
<table class="rb-tbl">
<tr><th>Baustein</th><th>Name</th><th>Einstellung</th><th>Eing&auml;nge</th></tr>
<tr><td>Schwellwertschalter S5</td><td>Roboter ist bereit</td><td>invertiert: Ein bei Unterschreiten von 1,5 (CODE 0 oder 1)</td><td>&larr; CODE</td></tr>
<tr><td>UND U2</td><td>Saugen freigeben</td><td>&rarr; auf den Virtuellen Ausgang <span class="rb-mono">?cmd=start</span></td><td>S5 &amp; Abwesenheit &amp; Zeitfenster &amp; NICHT Wochenende</td></tr>
<tr><td>UND U3</td><td>Sofort heimschicken</td><td>&rarr; <span class="rb-mono">?cmd=home</span>, wenn jemand nach Hause kommt</td><td>Anwesenheit &amp; (CODE = 2)</td></tr>
</table>
<b>Praxis-Erfahrung:</b> Der Benachrichtigungs-Baustein sendet nur bei einer 0&rarr;1-Flanke &mdash; niemals mehrere
Quellen direkt an den Eingang legen, immer erst im ODER sammeln. F&uuml;r den Test einen eigenen Baustein verwenden.
</div>

<div class="rb-step"><b>Schritt 5: MQTT und JSON</b><br>
Alle Werte auch per MQTT (Reiter Einstellungen) und als JSON inklusive Raumliste:
<span class="rb-mono">http://<?= $rb_host ?>/plugins/<?= rb_e($rb_plugin) ?>/robo.php?json=1</span>
</div>
</div>

<!-- ================= Test ================= -->
<div class="rb-pane" id="tab-test">
<h2>Test</h2>
<p>
<a class="rb-btn" style="display:inline-block;margin-right:8px;" href="/plugins/<?= rb_e($rb_plugin) ?>/robo.php" target="_blank">Loxone-Zeile abrufen</a>
<a class="rb-btn" style="display:inline-block;margin-right:8px;" href="/plugins/<?= rb_e($rb_plugin) ?>/robo.php?debug=1&amp;refresh=1" target="_blank">Debug (inkl. Raumliste)</a>
<a class="rb-btn" style="display:inline-block;margin-right:8px;background:#607d8b;" href="/plugins/<?= rb_e($rb_plugin) ?>/robo.php?json=1" target="_blank">JSON-Ansicht</a>
<a class="rb-btn" style="display:inline-block;background:#e65100;" href="/plugins/<?= rb_e($rb_plugin) ?>/robo.php?ptest=1" target="_blank">Test-Pushnachricht</a>
</p>
<p>
<a class="rb-btn" style="display:inline-block;margin-right:8px;background:#607d8b;" href="/plugins/<?= rb_e($rb_plugin) ?>/robo.php?cmd=locate" target="_blank">Roboter piepsen lassen</a>
<a class="rb-btn" style="display:inline-block;margin-right:8px;background:#607d8b;" href="/plugins/<?= rb_e($rb_plugin) ?>/robo.php?cmd=home" target="_blank">Zur Ladestation</a>
<a class="rb-btn" style="display:inline-block;background:#607d8b;" href="/plugins/<?= rb_e($rb_plugin) ?>/robo.php?cmd=stop" target="_blank">Stopp</a>
</p>
<div class="rb-small">&bdquo;Piepsen lassen&ldquo; ist der ungef&auml;hrlichste Verbindungstest &mdash; der Roboter meldet sich akustisch, f&auml;hrt aber nicht los.</div>
<?php $rb_seg = function_exists('ro_segments') ? ro_segments(1) : array(); if ($rb_seg) { ?>
<h2>R&auml;ume (Segment-IDs)</h2>
<table class="rb-tbl"><tr><th>ID</th><th>Name</th><th>Aufruf f&uuml;r Loxone</th></tr>
<?php foreach ($rb_seg as $rb_id => $rb_nm) { ?>
<tr><td><span class="rb-mono"><?= rb_e($rb_id) ?></span></td><td><?= rb_e($rb_nm) ?></td>
<td><span class="rb-mono">?cmd=segments&amp;p=<?= rb_e($rb_id) ?></span></td></tr>
<?php } ?></table>
<div class="rb-small">Mehrere R&auml;ume mit Komma: <span class="rb-mono">?cmd=segments&amp;p=<?= rb_e(implode(',', array_slice(array_keys($rb_seg), 0, 2))) ?></span></div>
<?php } else { ?>
<div class="rb-alert rb-info">Raumliste noch nicht verf&uuml;gbar &mdash; erscheint, sobald der Roboter erreichbar ist und eine Karte mit benannten R&auml;umen hat.</div>
<?php } ?>
</div>

<!-- ================= Protokoll ================= -->
<div class="rb-pane" id="tab-log">
<h2>Protokoll</h2>
<div class="rb-small" style="margin-bottom:8px;">Protokolliert werden Status&auml;nderungen, beendete Reinigungen, Fehler, Wartungsmeldungen und Steuerbefehle. Neueste Eintr&auml;ge oben (max. 300).<br>Datei: <span class="rb-mono"><?= rb_e($rb_logfile) ?></span></div>
<?php if ($rb_loglines) { ?>
<div class="rb-log"><?= rb_e(implode("\n", $rb_loglines)) ?></div>
<?php } else { ?>
<div class="rb-alert rb-info">Noch keine Protokoll-Eintr&auml;ge vorhanden.</div>
<?php } ?>
<form method="post" style="margin-top:10px;">
    <input data-role="none" type="hidden" name="clearlog" value="1">
    <input data-role="none" type="hidden" name="activetab" value="tab-log">
    <button data-role="none" class="rb-btn" type="submit" style="background:#c62828;">Protokoll leeren</button>
</form>
</div>

</div>
<script>
function rbTtsMode() {
    var m = document.getElementById('tts_mode').value;
    document.getElementById('tts_audioserver_hint').style.display = (m === 'audioserver') ? 'block' : 'none';
    document.getElementById('tts_template_row').style.display = (m === 'ms4h' || m === 'custom') ? 'block' : 'none';
    var port = document.getElementsByName('tts_port')[0];
    if (m === 'musicserver' && (!port.value || port.value === '80')) { port.value = 7091; }
}
(function () {
    var tabs = document.querySelectorAll('.rb-tab');
    function activate(id) {
        tabs.forEach(function (t) { t.classList.toggle('rb-active', t.dataset.pane === id); });
        document.querySelectorAll('.rb-pane').forEach(function (p) { p.classList.toggle('rb-active', p.id === id); });
    }
    tabs.forEach(function (t) { t.addEventListener('click', function () { activate(t.dataset.pane); }); });
    activate(<?= json_encode($rb_tab) ?>);
    rbTtsMode();
})();
</script>
<?php
if ($rb_frame) { LBWeb::lbfooter(); }
