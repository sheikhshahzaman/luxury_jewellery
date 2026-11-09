#!/usr/bin/env bash
# Turns a video into the frame sequence the home page's opening film plays on scroll.
#
#   scripts/make-hero-frames.sh <video> wide   # landscape film (desktop, tablets in landscape)
#   scripts/make-hero-frames.sh <video> tall   # portrait film (phones)
#
# Writes public/media/hero/<set>/001.webp, 002.webp, … replacing any old frames.
# Needs ffmpeg and cwebp (brew install ffmpeg webp). Optional: FRAMES=120 QUALITY=70.
set -euo pipefail

video=${1:?usage: make-hero-frames.sh <video> wide|tall}
set_name=${2:?usage: make-hero-frames.sh <video> wide|tall}
frames=${FRAMES:-120}
quality=${QUALITY:-70}

case "$set_name" in
    wide) scale='1280:-2' ;;
    tall) scale='-2:1280' ;;
    *) echo "set must be wide or tall" >&2; exit 1 ;;
esac

out="$(cd "$(dirname "$0")/.." && pwd)/public/media/hero/$set_name"
duration=$(ffprobe -v error -show_entries format=duration -of csv=p=0 "$video")
fps=$(awk -v n="$frames" -v d="$duration" 'BEGIN { printf "%.4f", n / d }')

tmp=$(mktemp -d)
trap 'rm -rf "$tmp"' EXIT
ffmpeg -v error -i "$video" -an -vf "fps=$fps,scale=$scale:flags=lanczos" -frames:v "$frames" "$tmp/%03d.png"
for png in "$tmp"/*.png; do
    cwebp -quiet -q "$quality" -m 6 -sharp_yuv "$png" -o "${png%.png}.webp" &
    (( $(jobs -r | wc -l) >= 8 )) && wait -n
done
wait

rm -rf "$out"
mkdir -p "$out"
mv "$tmp"/*.webp "$out/"
echo "$(ls "$out" | wc -l | tr -d ' ') frames, $(du -sh "$out" | cut -f1) → $out"
