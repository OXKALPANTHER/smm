# Royal SMM production delivery checklist

## Completed in this implementation

- [x] Capacitor Android shell uses application ID `com.t20tech.royalsmm`, version name `1.0.0`, version code `1`, min SDK 24, compile/target SDK 36, and HTTPS-only live start URL.
- [x] Only `royal.t20tech.site` and its subdomains stay in the WebView; other links and external schemes route to Android.
- [x] WebView session cookies/storage persist between launches and are excluded from cloud backup.
- [x] Native loading timeout, offline/error screen, Retry action, automatic retry after network recovery, TLS rejection, HTTP 5xx recovery, and crash-safe fallback are implemented.
- [x] Android back navigation, double-back exit toast, file chooser, DownloadManager, status/navigation colors, splash gating, and responsive WebView settings are implemented.
- [x] R8/resource shrinking is enabled for release. Release signing is read only from ignored `keystore.properties`.
- [x] `app-version.json`, asset-links example, privacy/data safety answers, push setup guide, install instructions, and update checklist are documented.

## Required before public release

- [ ] Host `docs/app-version.json` at `https://royal.t20tech.site/app-version.json` and replace the example APK URL with the signed release APK URL. The current live endpoint returned HTTP 404, so this is a hosting action still required.
- [x] Generate and locally protect `royal-release.jks`; create ignored `keystore.properties`; build and verify signed APK/AAB. The release certificate fingerprint is recorded in `docs/assetlinks.json.example`.
- [ ] Host `docs/assetlinks.json.example` as `/.well-known/assetlinks.json`. The fingerprint is prepared; hosting is still required.
- [ ] Test fresh install, login persistence after close/reopen, order flow, add-funds return, back behavior, rotation, keyboard, file upload, external links, airplane mode, and recovery on Android 8, 11, and 14 profiles at 360px and 412px widths.
- [ ] If push is wanted, provide Firebase `google-services.json`, add the optional messaging dependency, create the token endpoint, request Android 13 notification permission after login, and test notification deep links.
- [ ] If biometric/PIN, app shortcuts, native review prompt, or FLAG_SECURE are wanted, enable and test each independently; they are intentionally off by default.
- [ ] Use the website's own language switch and verify no native screen hardcodes customer-facing website language.
