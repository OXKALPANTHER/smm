# App icons (PWA)

These icons are what users see when they **install Royal SMM as an app** (home screen
icon + splash). A branded placeholder (`icon.svg`) is already here, so the app is
installable right now. To use **your own logo**, drop two PNG files in this folder:

| File          | Size      | Notes                                                |
|---------------|-----------|------------------------------------------------------|
| `icon-192.png`| 192×192   | Used by Android + iOS `apple-touch-icon`.            |
| `icon-512.png`| 512×512   | Used for the install splash / high-res home screen.  |

Tips for the best result:
- Use a **square** image (1:1). Non-square logos get cropped.
- Keep important detail in the **center ~80%** (the icon is "maskable" — corners may be
  rounded/cropped on some phones).
- A solid or branded background (not transparent) looks best as an app icon.

Optional: replace `icon.svg` with your own square SVG to also change the browser-tab icon.

No code changes are needed — the file names above are already wired into
`manifest.webmanifest` and the page `<head>`. Just add the files and reload.
