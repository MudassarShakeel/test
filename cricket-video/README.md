# Cricket Sport video service

Makes the result video for the Cricket Sport Live WordPress plugin. The plugin sends the match data, this service renders a
1080x1920 MP4 (animated scoreboard, top players, original music, optional voice) and gives back a link that Facebook fetches.

It needs a small server (a VPS with 2 GB RAM is enough). It cannot run on shared hosting.

## Install on a VPS (Ubuntu)
```bash
sudo apt update
sudo apt install -y nodejs npm ffmpeg libnss3 libdbus-1-3 libatk1.0-0 libgbm-dev libasound2t64 libxrandr2 libxkbcommon-dev libxfixes3 libxcomposite1 libxdamage1 libatk-bridge2.0-0 libpango-1.0-0 libcairo2 libcups2
# Node 18 or newer is needed: check with node -v
unzip cricket-video-service.zip -d video-service && cd video-service
npm install
```

## Run it
```bash
API_KEY="make-a-long-random-key" PUBLIC_URL="https://video.yourdomain.com" npm run service
```
- `API_KEY` is the key you will paste in the plugin's Setup.
- `PUBLIC_URL` is the https address Facebook will download the video from.
- Optional voiceover: also set `ELEVENLABS_API_KEY` and, if you want another voice, `ELEVENLABS_VOICE_ID`.
- Optional: `PORT` (default 8200), `BROWSER_EXECUTABLE` (path to Chrome if you do not want Remotion to download its own).

Keep it running with `pm2 start "npm run service" --name video` (after `npm i -g pm2`).

## HTTPS
Facebook needs https. The simplest way is Caddy in front of the service:
```
video.yourdomain.com {
    reverse_proxy 127.0.0.1:8200
}
```
Point the DNS of `video.yourdomain.com` at the server first.

## In WordPress
Cricket Sport Live, Setup: Posting format = Result video only, the address `https://video.yourdomain.com`, the key, then Test service.

## Endpoints
- `POST /render` with `Authorization: Bearer KEY` and the match JSON. Returns `{ "id": "..." }`.
- `GET /status/<id>` returns `queued`, `rendering`, `done` (with `url`) or `failed` (with `error`).
- `GET /videos/<id>.mp4` the video. Videos are deleted after 7 days.

## Change the look
The video is a Remotion project: `src/ResultVideo.tsx`. Run `npm run studio` to preview it. The music is made by
`music/make_music.py` (original, nothing copyrighted).

Remotion is free for individuals and small companies. Larger companies need a paid licence (remotion.dev/license).
