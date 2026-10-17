# Royal SMM production shell plan

## Goal

Ship a Capacitor Android shell that preserves the live Royal SMM website as the source of truth. The app provides secure native WebView behavior, offline/error recovery, Android intent routing, release signing, and documentation without modifying website ordering, payment, login, or API code.

## Structure

- `capacitor.config.json`: live HTTPS start URL and app identity.
- `android/app/src/main/java/.../MainActivity.java`: secure WebView, navigation policy, fallback page, downloads, file chooser, update checker, and back behavior.
- `android/app/src/main/res`: theme, splash, backup, network, and launcher resources.
- `docs`: version contract, deep-link hosting, privacy/data safety, and optional push setup.
- `README.md`: reproducible build, signing, install, and release instructions.

## Design decisions

The shell keeps the existing Royal SMM purple-and-white brand: dark purple fallback/splash surfaces, white crown mark, and purple action button. It uses a quiet, non-animated native error state so network failures are understandable rather than blank. Website language and all live UI copy remain controlled by the website.

## Acceptance boundary

The APK/AAB build, HTTPS-only navigation, allowed-host policy, external intents, native retry screen, session persistence configuration, release signing, and documentation can be validated in the sandbox. Device-specific login, payment, file-upload, rotation, push, and emulator/physical-device matrix tests require the user's Android devices and live credentials and remain explicitly marked in `TODO.md`.
