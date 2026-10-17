# Royal SMM Offline-first Mobile App

This is a bundled Capacitor Android app for Royal SMM. It is not a redirect-only wrapper: the dashboard, service catalogue, new-order form, orders, wallet, notifications, account, and support screens are bundled locally and remain usable without internet.

## Offline behavior

- The service catalogue is bundled locally and searchable by platform, name, and ID.
- Orders created offline are saved in device storage and shown immediately in My Orders.
- A connection banner shows when the app is offline.
- When online, the app keeps the local queue safe and directs the customer to the live Royal SMM sign-in flow before any server order is submitted. This prevents the app from falsely marking an offline order as paid or delivered.
- Wallet top-ups, authenticated account data, live order submission, and testimonial moderation require the live website connection.

## Build the Android APK

```bash
cd mobile-app
npm install
npx cap add android       # first build only
npx cap sync android
cd android
./gradlew assembleDebug
```

The debug APK is generated at:

```text
mobile-app/android/app/build/outputs/apk/debug/app-debug.apk
```

For a release APK, configure a signing key in the Android project and run `./gradlew assembleRelease`.

## Updating the bundled app

Edit `www/index.html` for the interface and `www/app.js` for navigation, local storage, services, order queueing, and online state. Then run:

```bash
npx cap sync android
```
