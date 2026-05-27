#!/usr/bin/env bash
# Regenerates assets/icons/phosphor-sprite.svg from Phosphor core (regular, MIT).
set -euo pipefail
VER="2.1.1"
ICONS="bank trend-up gear stack check-circle caret-down arrow-right article monitor-play microphone seal-check"
OUT="assets/icons/phosphor-sprite.svg"
tmp="$(mktemp -d)"
{
  printf '<svg xmlns="http://www.w3.org/2000/svg" width="0" height="0" style="position:absolute" aria-hidden="true">'
  for n in $ICONS; do
    curl -fsSL "https://unpkg.com/@phosphor-icons/core@${VER}/assets/regular/${n}.svg" -o "$tmp/$n.svg"
    inner="$(sed -E 's#.*<svg[^>]*>(.*)</svg>.*#\1#' "$tmp/$n.svg")"
    printf '<symbol id="ph-%s" viewBox="0 0 256 256">%s</symbol>' "$n" "$inner"
  done
  printf '</svg>\n'
} > "$OUT"
rm -rf "$tmp"
echo "wrote $OUT ($(wc -c < "$OUT") bytes)"
