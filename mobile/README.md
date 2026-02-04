# JooJo Mobile (Capacitor shell)

This folder turns the existing **Laravel + Inertia (React) chat app** into a **real Android/iOS app** using Capacitor.

Because Inertia pages are rendered via Laravel routes, the most reliable approach is to **wrap the hosted web app** using `server.url`.

## 0) Requirements
- Node.js 20+ (Capacitor 8 requires modern Node)
- Android Studio (Android)
- Xcode (macOS only) for iOS

## 1) Set your production URL
Edit `capacitor.config.ts`:
- Replace `https://YOUR-DOMAIN-HERE` with your real deployed URL (Railway / Cloud.mu / custom domain).

## 2) Install deps
```bash
cd mobile
npm install
```

## 3) Init + add platforms
```bash
npx cap init "JooJo" "mu.floremystique.joojo" --web-dir www
npx cap add android
# macOS only:
# npx cap add ios
```

> If you already ran `cap init`, you can skip it.

## 4) Run
```bash
npx cap open android
```
Then hit Run in Android Studio.

## 5) Deep links + external links
Recommended:
- Keep all navigation inside your domain.
- Open external URLs in the system browser (safer UX).

Later, when you add Push Notifications, you'll also add deep-link handling to open a specific chat thread.

## 6) Performance checklist (what makes it feel "stock")
- Use **HTTPS** and enable HTTP/2 + compression on the server.
- Strong caching headers for Vite assets (`/build/assets/*`).
- Keep chat lists virtualized (React windowing) and avoid heavy re-renders.
- Avoid large images; lazy-load + set correct sizes.
- WebSockets for real-time messages (you already have Echo/Reverb code).
