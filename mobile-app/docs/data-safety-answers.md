# Google Play Data Safety answers (review before submission)

- Does the app collect data? **Yes, only through the embedded Royal SMM website during normal account use.**
- Does the app share data with third parties? **No app analytics or advertising SDKs are included.** External apps are opened only when the customer requests a link/action.
- Data types: account/login/session data and customer-submitted order/payment content are handled by the website; the native shell stores WebView cookies/local storage on-device.
- Is data encrypted in transit? **Yes.** The app allows HTTPS only and rejects cleartext traffic and invalid TLS certificates.
- Can users request deletion? **Yes, through Royal SMM support/operator procedures.**
- Is data used for advertising or tracking? **No.**
- App permissions: INTERNET and ACCESS_NETWORK_STATE are required for the HTTPS website and offline/error decisions. POST_NOTIFICATIONS is reserved for optional Firebase notifications and should remain unrequested until Firebase is configured.
