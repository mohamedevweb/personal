#!/usr/bin/env bash
#
# Renders the launch film in all three shapes: H.264 MP4, CRF 18.
# Usage: ./render.sh [language]   (language defaults to en)
set -euo pipefail

cd "$(dirname "$0")"

LANGUAGE="${1:-en}"
# Pass "silent" as the second argument to render without the voiceover.
VOICE="true"
if [ "${2:-}" = "silent" ]; then VOICE="false"; fi
OUT_DIR="out"
PROPS="{\"language\":\"$LANGUAGE\",\"hasScore\":false,\"hasVoice\":$VOICE}"

mkdir -p "$OUT_DIR"

render () {
  local composition="$1"
  local name="$2"
  echo "→ $composition"
  npx remotion render "$composition" "$OUT_DIR/$name-$LANGUAGE.mp4" \
    --codec=h264 \
    --crf=18 \
    --props="$PROPS" \
    --log=info
}

render LaunchFilm         launch-film-1920x1080
render LaunchFilmVertical launch-film-1080x1920
render LaunchFilmSquare   launch-film-1080x1080

echo
echo "Done. Files in $OUT_DIR:"
ls -lh "$OUT_DIR"/*.mp4
