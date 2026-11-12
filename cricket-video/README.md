# Cricket Sport result video (Remotion)

1. `npm install`
2. Preview: `npm run studio`
3. Edit `match.json` for a real match (teams, scores, top performers).
4. Voice: make the narration from `voice-script.txt` (ElevenLabs, voice "Kaanha"), save it as `public/voice.mp3`,
   set `"audio": "voice.mp3"` in `match.json`, and adjust `scenes` (seconds when each part starts) to match the voice.
5. Render: `npm run render` (writes `out/result.mp4`). Upload it to the Page as a Reel.

Remotion needs Node, FFmpeg and Chromium, so run it on your computer or a VPS, not on shared hosting.
