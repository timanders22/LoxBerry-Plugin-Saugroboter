#!/bin/bash
ARGV1=$1; ARGV3=$3; ARGV5=$5
PFOLDER="${ARGV3:-saugrobo}"; BASE="${ARGV5:-$LBHOMEDIR}"
mkdir -p "$ARGV1" 2>/dev/null
cp -p "$BASE/config/plugins/$PFOLDER/robo.json" "$ARGV1/robo.json" 2>/dev/null
cp -p "$BASE/log/plugins/$PFOLDER/robo.log" "$ARGV1/robo.log" 2>/dev/null
exit 0
