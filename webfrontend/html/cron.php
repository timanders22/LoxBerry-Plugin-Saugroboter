<?php
/**
 * Saugroboter (Valetudo) - minutlicher Cron-Lauf
 *
 * 1. Status aller Roboter aktualisieren (Cache-schonend).
 * 2. Ereignisse erkennen und melden: Reinigung fertig, Fehler, Wartung faellig.
 * 3. MQTT bei Aenderung, mindestens halbstuendlich.
 */

require_once __DIR__ . '/robo_lib.php';

ro_events_check();

foreach (ro_robots() as $n => $r) {
    $st = ro_state($n);
    $sig = json_encode(array($st['code'], $st['batterie'], $st['fehler'], $st['material_warn'],
                             $st['flaeche'], $st['anzahl_gesamt']));
    $sigf = ro_tmpdir() . '/mqtt_sig_' . $n . '.txt';
    $beat = ro_tmpdir() . '/mqtt_beat_' . $n;
    $old = is_file($sigf) ? (string) file_get_contents($sigf) : '';
    if ($sig !== $old || !is_file($beat) || time() - filemtime($beat) > 1800) {
        ro_mqtt_publish($st, $n);
        @file_put_contents($sigf, $sig);
        @touch($beat);
    }
}
echo "OK\n";
