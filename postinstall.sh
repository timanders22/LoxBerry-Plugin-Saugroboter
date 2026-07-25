#!/bin/bash
ARGV3=$3; ARGV5=$5
PFOLDER="${ARGV3:-saugrobo}"; BASE="${ARGV5:-$LBHOMEDIR}"
mkdir -p "$BASE/config/plugins/$PFOLDER" "$BASE/data/plugins/$PFOLDER" 2>/dev/null
[ -f "$BASE/config/plugins/$PFOLDER/robo.json" ] || echo '{}' > "$BASE/config/plugins/$PFOLDER/robo.json"
BK="$BASE/config/plugins/$PFOLDER.backup.json"; CF="$BASE/config/plugins/$PFOLDER/robo.json"
if [ -f "$BK" ]; then
    if [ ! -s "$CF" ] || [ "$(cat "$CF" 2>/dev/null)" = "{}" ]; then cp -p "$BK" "$CF"; echo "<OK> Konfiguration aus Sicherung wiederhergestellt."; fi
fi
echo "<OK> Installation abgeschlossen. Bitte Plugin-Oberflaeche oeffnen und Roboter-IP eintragen."
exit 0
