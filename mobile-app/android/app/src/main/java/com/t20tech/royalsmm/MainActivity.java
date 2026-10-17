package com.t20tech.royalsmm;

import android.annotation.SuppressLint;
import android.app.AlertDialog;
import android.app.DownloadManager;
import android.content.ActivityNotFoundException;
import android.content.Context;
import android.content.Intent;
import android.graphics.Color;
import android.net.ConnectivityManager;
import android.net.Network;
import android.net.Uri;
import android.os.Bundle;
import android.os.Handler;
import android.os.Looper;
import android.provider.Settings;
import android.view.View;
import android.view.Window;
import android.webkit.CookieManager;
import android.webkit.DownloadListener;
import android.webkit.GeolocationPermissions;
import android.webkit.SslErrorHandler;
import android.webkit.URLUtil;
import android.webkit.ValueCallback;
import android.webkit.WebChromeClient;
import android.webkit.WebResourceError;
import android.webkit.WebResourceRequest;
import android.webkit.WebSettings;
import android.webkit.WebView;
import android.webkit.WebViewClient;
import android.widget.Toast;

import androidx.annotation.Nullable;
import androidx.core.view.WindowCompat;
import androidx.core.splashscreen.SplashScreen;

import com.getcapacitor.BridgeActivity;

import org.json.JSONObject;

import java.io.BufferedReader;
import java.io.InputStreamReader;
import java.net.HttpURLConnection;
import java.net.URL;
import java.util.Locale;

public class MainActivity extends BridgeActivity {
    private static final String ALLOWED_HOST = "royal.t20tech.site";
    private static final String HOME_URL = "https://royal.t20tech.site";
    private static final int FILE_CHOOSER_REQUEST = 4101;
    private static final long LOAD_TIMEOUT_MS = 15000L;
    private static final boolean SECURE_SCREEN = false;

    private WebView webView;
    private ValueCallback<Uri[]> fileCallback;
    private final Handler handler = new Handler(Looper.getMainLooper());
    private long lastBackPress;
    private boolean showingFallback;
    private boolean pageLoaded;
    private final Runnable timeoutRunnable = () -> {
        if (!pageLoaded && webView != null) showFallback("The page took too long to load.");
    };

    @Override
    public void onCreate(@Nullable Bundle savedInstanceState) {
        SplashScreen splashScreen = SplashScreen.installSplashScreen(this);
        splashScreen.setKeepOnScreenCondition(() -> !pageLoaded && !showingFallback);
        super.onCreate(savedInstanceState);
        Window window = getWindow();
        window.setStatusBarColor(Color.rgb(21, 19, 43));
        window.setNavigationBarColor(Color.rgb(21, 19, 43));
        WindowCompat.setDecorFitsSystemWindows(window, true);
        configureWebView();
        registerNetworkRecovery();
    }

    @SuppressLint({"SetJavaScriptEnabled", "ClickableViewAccessibility"})
    private void configureWebView() {
        webView = getBridge().getWebView();
        WebSettings settings = webView.getSettings();
        settings.setJavaScriptEnabled(true);
        settings.setDomStorageEnabled(true);
        settings.setDatabaseEnabled(true);
        settings.setAllowFileAccess(false);
        settings.setAllowContentAccess(true);
        settings.setAllowFileAccessFromFileURLs(false);
        settings.setAllowUniversalAccessFromFileURLs(false);
        settings.setBuiltInZoomControls(false);
        settings.setDisplayZoomControls(false);
        settings.setTextZoom(100);
        settings.setSupportMultipleWindows(false);
        settings.setMediaPlaybackRequiresUserGesture(true);
        settings.setMixedContentMode(WebSettings.MIXED_CONTENT_NEVER_ALLOW);
        CookieManager.getInstance().setAcceptCookie(true);
        CookieManager.getInstance().setAcceptThirdPartyCookies(webView, false);
        webView.setOverScrollMode(View.OVER_SCROLL_NEVER);
        webView.setBackgroundColor(Color.WHITE);
        webView.setWebViewClient(new SecureWebViewClient());
        webView.setWebChromeClient(new RoyalChromeClient());
        webView.setDownloadListener(new RoyalDownloadListener(this));
        webView.setOnLongClickListener(v -> false);
        webView.getSettings().setSaveFormData(true);
        startPageLoad();
    }

    private void startPageLoad() {
        showingFallback = false;
        pageLoaded = false;
        handler.removeCallbacks(timeoutRunnable);
        handler.postDelayed(timeoutRunnable, LOAD_TIMEOUT_MS);
        if (isOnline()) webView.loadUrl(HOME_URL);
        else showFallback("No internet connection.");
    }

    private boolean isOnline() {
        ConnectivityManager cm = (ConnectivityManager) getSystemService(Context.CONNECTIVITY_SERVICE);
        return cm != null && cm.getActiveNetwork() != null;
    }

    private boolean isAllowed(Uri uri) {
        String host = uri.getHost();
        return host != null && (host.equalsIgnoreCase(ALLOWED_HOST) || host.toLowerCase(Locale.US).endsWith("." + ALLOWED_HOST));
    }

    private void openExternal(String rawUrl) {
        try {
            Uri uri;
            if (rawUrl.startsWith("intent://")) {
                Intent intent = Intent.parseUri(rawUrl, Intent.URI_INTENT_SCHEME);
                intent.addCategory(Intent.CATEGORY_BROWSABLE);
                intent.setComponent(null);
                startActivity(intent);
                return;
            }
            String fixed = rawUrl.replace("#", "%23");
            uri = Uri.parse(fixed);
            Intent intent = new Intent(Intent.ACTION_VIEW, uri);
            startActivity(intent);
        } catch (ActivityNotFoundException | SecurityException | java.net.URISyntaxException ignored) {
            Toast.makeText(this, "No app is available for this link.", Toast.LENGTH_SHORT).show();
        }
    }

    private void showFallback(String reason) {
        if (webView == null) return;
        showingFallback = true;
        pageLoaded = false;
        handler.removeCallbacks(timeoutRunnable);
        String safe = reason.replace("&", "&amp;").replace("<", "&lt;").replace(">", "&gt;");
        String html = "<!doctype html><html><head><meta name='viewport' content='width=device-width,initial-scale=1'><style>body{margin:0;background:#15132b;color:#fff;font-family:system-ui;text-align:center;display:grid;place-items:center;min-height:100vh;padding:24px}main{max-width:360px}div{width:72px;height:72px;margin:0 auto 20px;border-radius:24px;background:#fff;color:#6c5ce7;display:grid;place-items:center;font-size:36px;font-weight:900}h1{font-size:24px;margin:0 0 10px}p{color:#d9d5ff;line-height:1.5}button{border:0;border-radius:14px;background:#6c5ce7;color:#fff;padding:14px 24px;font-weight:800;font-size:16px}</style></head><body><main><div>♛</div><h1>Royal SMM</h1><p>" + safe + "</p><button onclick=\"location.href='royal://retry'\">Retry</button></main></body></html>";
        webView.loadDataWithBaseURL(HOME_URL, html, "text/html", "UTF-8", HOME_URL);
    }

    private void registerNetworkRecovery() {
        ConnectivityManager cm = (ConnectivityManager) getSystemService(Context.CONNECTIVITY_SERVICE);
        if (cm == null) return;
        cm.registerDefaultNetworkCallback(new ConnectivityManager.NetworkCallback() {
            @Override public void onAvailable(Network network) {
                handler.post(() -> { if (showingFallback) startPageLoad(); });
            }
        });
    }

    @Override
    public void onBackPressed() {
        if (webView != null && webView.canGoBack() && !showingFallback) {
            webView.goBack();
            return;
        }
        long now = System.currentTimeMillis();
        if (now - lastBackPress < 1800) {
            super.onBackPressed();
        } else {
            lastBackPress = now;
            Toast.makeText(this, "Press back again to exit", Toast.LENGTH_SHORT).show();
        }
    }

    @Override
    protected void onNewIntent(Intent intent) {
        super.onNewIntent(intent);
        setIntent(intent);
        Uri destination = intent == null ? null : intent.getData();
        if (destination != null && isAllowed(destination) && webView != null) {
            showingFallback = false;
            webView.loadUrl(destination.toString());
        }
    }

    @Override
    public void onDestroy() {
        handler.removeCallbacksAndMessages(null);
        if (webView != null) webView.stopLoading();
        super.onDestroy();
    }

    private final class SecureWebViewClient extends WebViewClient {
        @Override public boolean shouldOverrideUrlLoading(WebView view, WebResourceRequest request) {
            return routeUrl(request.getUrl().toString());
        }
        @Override public boolean shouldOverrideUrlLoading(WebView view, String url) {
            return routeUrl(url);
        }
        private boolean routeUrl(String rawUrl) {
            if (rawUrl == null) return true;
            if (rawUrl.equals("royal://retry")) { startPageLoad(); return true; }
            if (rawUrl.startsWith("http://") || rawUrl.startsWith("https://")) {
                Uri uri = Uri.parse(rawUrl);
                if (isAllowed(uri)) return false;
                openExternal(rawUrl);
                return true;
            }
            openExternal(rawUrl);
            return true;
        }
        @Override public void onPageStarted(WebView view, String url, android.graphics.Bitmap favicon) {
            pageLoaded = false;
            showingFallback = false;
            handler.removeCallbacks(timeoutRunnable);
            handler.postDelayed(timeoutRunnable, LOAD_TIMEOUT_MS);
        }
        @Override public void onPageFinished(WebView view, String url) {
            pageLoaded = true;
            showingFallback = false;
            handler.removeCallbacks(timeoutRunnable);
            checkForUpdate();
        }
        @Override public void onReceivedError(WebView view, WebResourceRequest request, WebResourceError error) {
            if (request.isForMainFrame()) showFallback("We could not load Royal SMM. Check your connection and try again.");
        }
        @Override public void onReceivedHttpError(WebView view, WebResourceRequest request, android.webkit.WebResourceResponse response) {
            if (request.isForMainFrame() && response.getStatusCode() >= 500) showFallback("Royal SMM is temporarily unavailable. Please try again.");
        }
        @Override public void onReceivedSslError(WebView view, SslErrorHandler handler, android.net.http.SslError error) {
            handler.cancel();
            showFallback("A secure connection could not be verified.");
        }
    }

    private final class RoyalChromeClient extends WebChromeClient {
        @Override public boolean onShowFileChooser(WebView view, ValueCallback<Uri[]> callback, FileChooserParams params) {
            if (fileCallback != null) fileCallback.onReceiveValue(null);
            fileCallback = callback;
            try {
                Intent intent = params.createIntent();
                startActivityForResult(intent, FILE_CHOOSER_REQUEST);
            } catch (ActivityNotFoundException e) {
                fileCallback = null;
                callback.onReceiveValue(null);
            }
            return true;
        }
        @Override public void onGeolocationPermissionsShowPrompt(String origin, GeolocationPermissions.Callback callback) {
            callback.invoke(origin, false, false);
        }
    }

    private static final class RoyalDownloadListener implements DownloadListener {
        private final Context context;
        RoyalDownloadListener(Context context) { this.context = context; }
        @Override public void onDownloadStart(String url, String userAgent, String contentDisposition, String mimetype, long contentLength) {
            DownloadManager.Request request = new DownloadManager.Request(Uri.parse(url));
            request.setMimeType(mimetype);
            request.addRequestHeader("User-Agent", userAgent);
            request.setTitle(URLUtil.guessFileName(url, contentDisposition, mimetype));
            request.setNotificationVisibility(DownloadManager.Request.VISIBILITY_VISIBLE_NOTIFY_COMPLETED);
            request.setDestinationInExternalPublicDir(android.os.Environment.DIRECTORY_DOWNLOADS, URLUtil.guessFileName(url, contentDisposition, mimetype));
            DownloadManager manager = (DownloadManager) context.getSystemService(Context.DOWNLOAD_SERVICE);
            if (manager != null) manager.enqueue(request);
        }
    }

    @Override
    protected void onActivityResult(int requestCode, int resultCode, @Nullable Intent data) {
        super.onActivityResult(requestCode, resultCode, data);
        if (requestCode == FILE_CHOOSER_REQUEST && fileCallback != null) {
            Uri[] results = resultCode == RESULT_OK && data != null ? WebChromeClient.FileChooserParams.parseResult(resultCode, data) : null;
            fileCallback.onReceiveValue(results);
            fileCallback = null;
        }
    }

    private void checkForUpdate() {
        if (!isOnline()) return;
        new Thread(() -> {
            HttpURLConnection connection = null;
            try {
                connection = (HttpURLConnection) new URL(HOME_URL + "/app-version.json").openConnection();
                connection.setConnectTimeout(4000);
                connection.setReadTimeout(4000);
                if (connection.getResponseCode() != 200) return;
                BufferedReader reader = new BufferedReader(new InputStreamReader(connection.getInputStream()));
                StringBuilder body = new StringBuilder(); String line;
                while ((line = reader.readLine()) != null) body.append(line);
                JSONObject json = new JSONObject(body.toString());
                String latest = json.optString("latest", "1.0.0");
                String minimum = json.optString("minimum", "1.0.0");
                String url = json.optString("url", HOME_URL);
                int current = versionCode("1.0.0");
                int latestCode = versionCode(latest);
                int minimumCode = versionCode(minimum);
                if (latestCode > current || current < minimumCode) runOnUiThread(() -> showUpdateDialog(url, current < minimumCode));
            } catch (Exception ignored) {
            } finally { if (connection != null) connection.disconnect(); }
        }).start();
    }

    private int versionCode(String value) {
        try {
            String[] parts = value.replaceAll("[^0-9.]", "").split("\\.");
            return Integer.parseInt(parts[0]) * 10000 + (parts.length > 1 ? Integer.parseInt(parts[1]) : 0) * 100 + (parts.length > 2 ? Integer.parseInt(parts[2]) : 0);
        } catch (Exception e) { return 0; }
    }

    private void showUpdateDialog(String url, boolean forced) {
        AlertDialog dialog = new AlertDialog.Builder(this).setTitle("Update available").setMessage("A newer Royal SMM app is available.").setPositiveButton("Update", (d, w) -> openExternal(url)).setCancelable(!forced).create();
        dialog.setOnShowListener(d -> { if (forced) dialog.setCanceledOnTouchOutside(false); });
        dialog.show();
    }
}
