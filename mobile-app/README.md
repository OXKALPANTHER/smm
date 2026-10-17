# Royal SMM Android app

This repository contains a Capacitor Android shell for `https://royal.t20tech.site`. The production app does not rewrite the website and does not duplicate its login, ordering, payment, or API logic. It loads the live HTTPS site in a secure native WebView and supplies native recovery and device behavior around it.

## Production behavior

The start URL is `https://royal.t20tech.site`. Only that host and its subdomains remain inside the WebView. Other HTTP(S) links open in the system browser; WhatsApp, telephone, email, SMS, mobile-money, payment, market, and `intent://` links are routed to Android. TLS errors are rejected rather than bypassed. Cleartext HTTP is disabled.

Cookies, WebView storage, and website sessions remain in the Android WebView profile between launches and are excluded from cloud backup. The native shell provides a 15-second loading timeout, a branded offline/error screen with Retry, automatic retry when connectivity returns, Android back navigation, double-back exit confirmation, file chooser support, DownloadManager downloads, keyboard-safe WebView settings, and update checking through `/app-version.json`.

Firebase notifications, biometric/PIN lock, rate prompts, and shortcuts are deliberately optional. The app compiles and runs without Firebase files. Setup notes are in `docs/push-setup.md`.

## Project identity

The release application ID is `com.t20tech.royalsmm`, version name `1.0.1`, version code `2`, min SDK 24, compile/target SDK 36, and portrait orientation. Release builds use R8 and resource shrinking. The debug build uses the same app identity and is intended for development installation.

## Build from a clean checkout

```bash
cd mobile-app
npm install
npx cap sync android
cd android
# Set sdk.dir in local.properties or export ANDROID_HOME.
./gradlew assembleDebug
```

Debug output:

```text
android/app/build/outputs/apk/debug/app-debug.apk
```

## Release signing

Generate the private release key once and keep both the keystore and its passwords in two separate secure backups. Do not commit either file.

```bash
cd mobile-app/android
keytool -genkeypair -v \
  -storetype JKS \
  -keystore royal-release.jks \
  -alias royal-release \
  -keyalg RSA -keysize 4096 -validity 10000 \
  -storepass 'REPLACE_WITH_A_LONG_RANDOM_PASSWORD' \
  -keypass 'REPLACE_WITH_A_DIFFERENT_LONG_RANDOM_PASSWORD' \
  -dname 'CN=Royal SMM, OU=Mobile, O=T20Tech, L=Dar es Salaam, ST=Dar es Salaam, C=TZ'
```

Create ignored `android/keystore.properties`:

```properties
storeFile=royal-release.jks
storePassword=YOUR_KEYSTORE_PASSWORD
keyAlias=royal-release
keyPassword=YOUR_KEY_PASSWORD
```

Then build both signed release artifacts:

```bash
./gradlew clean assembleRelease bundleRelease
$ANDROID_HOME/build-tools/36.0.0/apksigner verify --verbose app/build/outputs/apk/release/app-release.apk
sha256sum app/build/outputs/apk/release/app-release.apk app/build/outputs/bundle/release/app-release.aab
keytool -list -v -keystore royal-release.jks -alias royal-release | grep -E 'SHA256:|Alias name:'
```

Back up `royal-release.jks` and the two passwords to an encrypted password manager and a separate encrypted offline drive. Never upload the keystore, passwords, `keystore.properties`, or `google-services.json` to GitHub. Losing the key prevents updates to the same Google Play package.

To release an update, increment `versionCode`, update `versionName`, update the hosted `app-version.json`, run `npx cap sync android`, build and verify the signed APK/AAB, upload the AAB to Play Console, and retain the same keystore.

## Website files to host

Host the contents of `docs/app-version.json` at `https://royal.t20tech.site/app-version.json`. Its required shape is `{"latest":"1.0.0","minimum":"1.0.0","url":"https://royal.t20tech.site/downloads/royal-smm.apk"}`. Host `docs/assetlinks.json.example` as `https://royal.t20tech.site/.well-known/assetlinks.json` after replacing the certificate fingerprint with the release SHA-256 fingerprint. The APK URL should be HTTPS and point to the signed release APK.

For a simple download page, use this snippet:

```html
<a href="/downloads/royal-smm.apk" download>Download Royal SMM for Android</a>
<p>Android may ask you to allow this browser to install unknown apps. Enable that permission only for the browser you trust, install the APK, then disable it again.</p>
```

## Android installation

On Android, open the signed APK download, choose Settings when Android blocks the install, enable “Allow from this source” for the browser or file manager you used, return to the installer, and install Royal SMM. Disable the permission again after installation. Google Play distribution should use the AAB instead of sideloading.

## Permissions and privacy

The app uses INTERNET for the HTTPS website and ACCESS_NETWORK_STATE for offline/error handling. POST_NOTIFICATIONS is declared only for the future optional Firebase feature and is not requested by the current build. File selection uses Android's system picker rather than broad storage access. There are no analytics or ad SDKs. See `docs/privacy-policy.md`, `docs/data-safety-answers.md`, and `docs/push-setup.md`.

## Testing status

Static JavaScript validation and Android Gradle compilation are performed in this repository. The production shell is designed for Android 8/11/14 behavior, but an emulator and physical-device matrix, login persistence, payment return, file upload, and Firebase delivery require Android devices and credentials and must be completed before a public store release. The website remains the source of truth for live ordering, payments, login, and API behavior; any failure in those flows should be reported as a website issue rather than changed in this app repository.
