# Cricket Sport result video (Remotion)

1. `npm install`
2. Preview: `npm run studio`
3. Edit `match.json` for a real match (teams, scores, top performers).
4. Voice: make the narration from `voice-script.txt` (ElevenLabs, voice "Kaanha"), save it as `public/voice.mp3`,
   set `"audio": "voice.mp3"` in `match.json`, and adjust `scenes` (seconds when each part starts) to match the voice.
5. Render: `npm run render` (writes `out/result.mp4`). Upload it to the Page as a Reel.

Remotion needs Node, FFmpeg and Chromium, so run it on your computer or a VPS, not on shared hosting.

## Music
`music/make_music.py` makes an original 22-second backing track (nothing copyrighted): `python3 music/make_music.py music.wav`,
then `ffmpeg -i music.wav -b:a 192k public/music.mp3`. Change `BPM`, the chords in `prog` or the notes in `pent` to change the mood.
`match.json` has `music` (the file) and `musicVolume`. When a voice is added the music drops to under half of that level.
