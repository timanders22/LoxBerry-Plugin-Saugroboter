#!/bin/bash
ARGV1=$1; ARGV3=$3; ARGV5=$5
PFOLDER="${ARGV3:-saugrobo}"; BASE="${ARGV5:-$LBHOMEDIR}"
mkdir -p "$BASE/config/plugins/$PFOLDER" "$BASE/log/plugins/$PFOLDER" "$BASE/data/plugins/$PFOLDER" 2>/dev/null
[ -f "$ARGV1/robo.json" ] && cp -p "$ARGV1/robo.json" "$BASE/config/plugins/$PFOLDER/robo.json"
[ -f "$ARGV1/robo.log" ] && cp -p "$ARGV1/robo.log" "$BASE/log/plugins/$PFOLDER/robo.log"
BK="$BASE/config/plugins/$PFOLDER.backup.json"; CF="$BASE/config/plugins/$PFOLDER/robo.json"
if [ -f "$BK" ]; then
    if [ ! -s "$CF" ] || [ "$(cat "$CF" 2>/dev/null)" = "{}" ]; then cp -p "$BK" "$CF"; fi
fi
exit 0
