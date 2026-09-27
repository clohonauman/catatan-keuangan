package com.charlie.finance;

import android.Manifest;
import android.app.AlarmManager;
import android.app.DownloadManager;
import android.app.NotificationChannel;
import android.app.NotificationManager;
import android.app.PendingIntent;
import android.content.Context;
import android.content.SharedPreferences;
import android.content.pm.PackageManager;
import android.content.Intent;
import android.content.pm.ApplicationInfo;
import android.content.pm.PackageInfo;
import android.graphics.Bitmap;
import android.net.ConnectivityManager;
import android.net.Network;
import android.net.NetworkCapabilities;
import android.net.Uri;
import android.os.Build;
import android.os.Bundle;
import android.os.Environment;
import android.view.View;
import android.webkit.CookieManager;
import android.webkit.DownloadListener;
import android.webkit.JavascriptInterface;
import android.webkit.ServiceWorkerController;
import android.webkit.ServiceWorkerWebSettings;
import android.webkit.ValueCallback;
import android.webkit.WebChromeClient;
import android.webkit.WebResourceError;
import android.webkit.WebResourceRequest;
import android.webkit.WebSettings;
import android.webkit.WebView;
import android.webkit.WebViewClient;
import android.widget.Button;
import android.widget.FrameLayout;
import android.widget.ProgressBar;
import android.widget.TextView;
import android.widget.Toast;
import android.text.TextUtils;

import androidx.biometric.BiometricManager;
import androidx.biometric.BiometricPrompt;
import androidx.core.content.ContextCompat;
import androidx.core.content.FileProvider;
import androidx.fragment.app.FragmentActivity;

import java.io.File;
import java.io.IOException;
import java.text.SimpleDateFormat;
import java.util.Date;
import java.util.Locale;
import java.util.ArrayList;
import java.util.List;

import org.json.JSONArray;
import org.json.JSONObject;
import java.util.concurrent.Executor;

public class MainActivity extends FragmentActivity {

    private static final String APP_URL = "https://catatan-keuangan.cloud/";
    private static final String APP_HOST = "catatan-keuangan.cloud";
    private static final int FILE_CHOOSER_REQUEST = 1001;
    private static final String SECURITY_PREFS = "catatan_keuangan_security";
    private static final String PREF_BIOMETRIC_ENABLED = "biometric_enabled";
    private static final String OFFLINE_PREFS = "catatan_keuangan_offline";
    private static final String PREF_OFFLINE_READY = "offline_ready";
    private static final String PREF_OFFLINE_USER_ID = "offline_user_id";
    private static final String PREF_OFFLINE_READY_AT = "offline_ready_at";
    private static final String REMINDER_PREFS = "credit_card_reminders";
    private static final String PREF_REMINDER_CODES = "request_codes";
    private static final int NOTIFICATION_PERMISSION_REQUEST = 2002;
    private static final long BIOMETRIC_RELOCK_AFTER_MS = 30_000L;
    private static final int BIOMETRIC_MODE_UNLOCK = 1;
    private static final int BIOMETRIC_MODE_ENABLE = 2;
    private static final int BIOMETRIC_MODE_DISABLE = 3;

    private WebView webView;
    private ProgressBar progressBar;
    private ValueCallback<Uri[]> filePathCallback;
    private Uri cameraPhotoUri;
    private boolean showingLocalOfflinePage = false;
    private ConnectivityManager connectivityManager;
    private ConnectivityManager.NetworkCallback networkCallback;
    private SharedPreferences securityPrefs;
    private SharedPreferences offlinePrefs;
    private SharedPreferences reminderPrefs;
    private boolean offlineBootRetryAttempted = false;
    private FrameLayout biometricLockOverlay;
    private TextView biometricLockMessage;
    private Button biometricRetryButton;
    private Button biometricUsePinButton;
    private boolean biometricPromptVisible = false;
    private long backgroundedAt = 0L;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        setContentView(R.layout.activity_main);

        webView = findViewById(R.id.webView);
        progressBar = findViewById(R.id.progressBar);
        biometricLockOverlay = findViewById(R.id.biometricLockOverlay);
        biometricLockMessage = findViewById(R.id.biometricLockMessage);
        biometricRetryButton = findViewById(R.id.biometricRetryButton);
        biometricUsePinButton = findViewById(R.id.biometricUsePinButton);
        securityPrefs = getSharedPreferences(SECURITY_PREFS, MODE_PRIVATE);
        offlinePrefs = getSharedPreferences(OFFLINE_PREFS, MODE_PRIVATE);
        reminderPrefs = getSharedPreferences(REMINDER_PREFS, MODE_PRIVATE);
        ensureCreditCardNotificationChannel();

        configureWebView();
        configureBiometricGate();
        configureServiceWorker();
        registerNetworkWatcher();

        if (savedInstanceState != null) {
            webView.restoreState(savedInstanceState);
        } else {
            loadInitialApp();
        }
    }

    private void configureWebView() {
        WebSettings settings = webView.getSettings();

        // Fitur utama WebView
        settings.setJavaScriptEnabled(true);
        settings.setDomStorageEnabled(true);
        settings.setDatabaseEnabled(true);
        settings.setCacheMode(isConnected()
                ? WebSettings.LOAD_DEFAULT
                : WebSettings.LOAD_CACHE_ELSE_NETWORK);
        settings.setLoadsImagesAutomatically(true);
        settings.setUseWideViewPort(true);
        settings.setLoadWithOverviewMode(false);

        // Zoom dimatikan agar terasa seperti aplikasi native
        settings.setSupportZoom(false);
        settings.setBuiltInZoomControls(false);
        settings.setDisplayZoomControls(false);

        // Akses konten yang diperlukan
        settings.setAllowContentAccess(true);
        settings.setAllowFileAccess(false);

        // Pop-up/window baru tidak diizinkan
        settings.setJavaScriptCanOpenWindowsAutomatically(false);
        settings.setSupportMultipleWindows(false);

        // Media hanya diputar setelah interaksi pengguna
        settings.setMediaPlaybackRequiresUserGesture(true);

        // Jangan izinkan mixed content HTTP di halaman HTTPS
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.LOLLIPOP) {
            settings.setMixedContentMode(WebSettings.MIXED_CONTENT_NEVER_ALLOW);
        }

        /*
         * Safe Browsing WebView dimatikan.
         * PENTING: sebelumnya kode Anda memanggil setSafeBrowsingEnabled(false),
         * lalu beberapa baris kemudian setSafeBrowsingEnabled(true).
         * Pemanggilan kedua itulah yang mengaktifkannya kembali.
         */
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            settings.setSafeBrowsingEnabled(false);
        }

        // Tandai request berasal dari aplikasi Android Charlie Finance
        settings.setUserAgentString(
                settings.getUserAgentString() + " CatatanKeuanganAndroid/" + getAppVersionName());

        // Cookie/session login tetap tersimpan
        CookieManager cookieManager = CookieManager.getInstance();
        cookieManager.setAcceptCookie(true);

        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.LOLLIPOP) {
            cookieManager.setAcceptThirdPartyCookies(webView, false);
        }

        webView.setWebViewClient(new FinanceWebViewClient());
        webView.setWebChromeClient(new FinanceWebChromeClient());
        webView.setDownloadListener(new FinanceDownloadListener());
        webView.addJavascriptInterface(new BiometricBridge(), "AndroidBiometric");
        webView.addJavascriptInterface(new OfflineBridge(), "AndroidOffline");
        webView.addJavascriptInterface(new ReminderBridge(), "AndroidReminders");
        webView.addJavascriptInterface(new AppInfoBridge(), "AndroidApp");

        // Debug WebView hanya aktif pada debug build
        boolean isDebuggable = (getApplicationInfo().flags & ApplicationInfo.FLAG_DEBUGGABLE) != 0;

        if (isDebuggable) {
            WebView.setWebContentsDebuggingEnabled(true);
        }
    }

    private void configureBiometricGate() {
        biometricRetryButton.setOnClickListener(v -> authenticateBiometric(BIOMETRIC_MODE_UNLOCK));
        biometricUsePinButton.setOnClickListener(v -> useApplicationPinFallback());

        if (isBiometricEnabled()) {
            showBiometricOverlay("Verifikasi biometrik untuk membuka aplikasi.");
            biometricLockOverlay.post(() -> authenticateBiometric(BIOMETRIC_MODE_UNLOCK));
        } else {
            hideBiometricOverlay();
        }
    }

    private boolean isBiometricEnabled() {
        return securityPrefs != null && securityPrefs.getBoolean(PREF_BIOMETRIC_ENABLED, false);
    }

    private int biometricAuthenticators() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.R) {
            return BiometricManager.Authenticators.BIOMETRIC_STRONG
                    | BiometricManager.Authenticators.DEVICE_CREDENTIAL;
        }
        // Android 7–10: gunakan biometrik yang tersedia. Fallback PIN aplikasi
        // ditampilkan sebagai negative button pada dialog biometrik.
        return BiometricManager.Authenticators.BIOMETRIC_WEAK;
    }

    private int biometricCapability() {
        try {
            return BiometricManager.from(this).canAuthenticate(biometricAuthenticators());
        } catch (Exception e) {
            return BiometricManager.BIOMETRIC_ERROR_HW_UNAVAILABLE;
        }
    }

    private String biometricLabel() {
        PackageManager pm = getPackageManager();
        boolean face = pm.hasSystemFeature("android.hardware.biometrics.face");
        boolean fingerprint = pm.hasSystemFeature("android.hardware.fingerprint");
        if (face && fingerprint)
            return "Pengenalan wajah / sidik jari";
        if (face)
            return "Pengenalan wajah";
        if (fingerprint)
            return "Sidik jari";
        return "Biometrik perangkat";
    }

    private String biometricUnavailableMessage(int capability) {
        if (capability == BiometricManager.BIOMETRIC_ERROR_NONE_ENROLLED) {
            return "Belum ada biometrik atau kunci layar yang didaftarkan pada perangkat.";
        }
        if (capability == BiometricManager.BIOMETRIC_ERROR_NO_HARDWARE) {
            return "Perangkat ini tidak memiliki sensor biometrik yang didukung.";
        }
        if (capability == BiometricManager.BIOMETRIC_ERROR_HW_UNAVAILABLE) {
            return "Sensor biometrik sedang tidak tersedia. Coba lagi beberapa saat.";
        }
        return "Biometrik perangkat belum dapat digunakan.";
    }

    private String getBiometricStatusJson() {
        int capability = biometricCapability();
        boolean supported = capability == BiometricManager.BIOMETRIC_SUCCESS;
        boolean enabled = isBiometricEnabled();
        String label = biometricLabel();
        String message = supported ? "" : biometricUnavailableMessage(capability);
        return "{" +
                "\"supported\":" + supported + "," +
                "\"enabled\":" + enabled + "," +
                "\"label\":\"" + jsonEscape(label) + "\"," +
                "\"message\":\"" + jsonEscape(message) + "\"" +
                "}";
    }

    private String jsonEscape(String value) {
        if (value == null)
            return "";
        return value.replace("\\", "\\\\")
                .replace("\"", "\\\"")
                .replace("\n", "\\n")
                .replace("\r", "\\r");
    }

    /**
     * Ambil versi aplikasi langsung dari PackageManager agar tidak bergantung
     * pada generated version constants dari Gradle.
     */
    private String getAppVersionName() {
        try {
            PackageInfo info = getPackageManager().getPackageInfo(getPackageName(), 0);
            return info.versionName != null ? info.versionName : "0.0.0";
        } catch (PackageManager.NameNotFoundException e) {
            return "0.0.0";
        }
    }

    private long getAppVersionCode() {
        try {
            PackageInfo info = getPackageManager().getPackageInfo(getPackageName(), 0);
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.P) {
                return info.getLongVersionCode();
            }
            //noinspection deprecation
            return info.versionCode;
        } catch (PackageManager.NameNotFoundException e) {
            return 0L;
        }
    }

    private void notifyWebBiometricStatus() {
        if (webView == null)
            return;
        String status = getBiometricStatusJson();
        webView.evaluateJavascript(
                "window.dispatchEvent(new CustomEvent('finance-biometric-status',{detail:" + status + "}));",
                null);
    }

    private void showBiometricOverlay(String message) {
        if (biometricLockMessage != null && message != null) {
            biometricLockMessage.setText(message);
        }
        if (biometricLockOverlay != null) {
            biometricLockOverlay.setVisibility(View.VISIBLE);
            biometricLockOverlay.bringToFront();
        }
    }

    private void hideBiometricOverlay() {
        if (biometricLockOverlay != null) {
            biometricLockOverlay.setVisibility(View.GONE);
        }
    }

    private void useApplicationPinFallback() {
        if (!isConnected()) {
            Toast.makeText(
                    this,
                    "PIN aplikasi memerlukan koneksi untuk mengunci ulang sesi dengan aman.",
                    Toast.LENGTH_LONG).show();
            return;
        }
        biometricPromptVisible = false;
        webView.loadUrl(APP_URL + "?app_lock=1");
        hideBiometricOverlay();
    }

    private void authenticateBiometric(int mode) {
        if (biometricPromptVisible)
            return;

        int capability = biometricCapability();
        if (capability != BiometricManager.BIOMETRIC_SUCCESS) {
            if (mode == BIOMETRIC_MODE_UNLOCK && isBiometricEnabled()) {
                // Jangan otomatis mematikan pengamanan hanya karena sensor sementara tidak
                // tersedia.
                // Pengguna tetap dapat memilih fallback PIN aplikasi.
                showBiometricOverlay(biometricUnavailableMessage(capability) + " Gunakan PIN aplikasi bila perlu.");
                Toast.makeText(this, biometricUnavailableMessage(capability), Toast.LENGTH_LONG).show();
            } else if (mode == BIOMETRIC_MODE_DISABLE) {
                // Pengguna sudah berada di sesi aplikasi yang terbuka; izinkan mematikan
                // setting lokal
                // jika sensor memang sudah tidak tersedia agar tidak terjadi lockout permanen.
                securityPrefs.edit().putBoolean(PREF_BIOMETRIC_ENABLED, false).apply();
                hideBiometricOverlay();
            } else {
                Toast.makeText(this, biometricUnavailableMessage(capability), Toast.LENGTH_LONG).show();
            }
            notifyWebBiometricStatus();
            return;
        }

        Executor executor = ContextCompat.getMainExecutor(this);
        BiometricPrompt prompt = new BiometricPrompt(
                this,
                executor,
                new BiometricPrompt.AuthenticationCallback() {
                    @Override
                    public void onAuthenticationError(int errorCode, CharSequence errString) {
                        super.onAuthenticationError(errorCode, errString);
                        biometricPromptVisible = false;
                        if (mode == BIOMETRIC_MODE_UNLOCK) {
                            if (errorCode == BiometricPrompt.ERROR_NEGATIVE_BUTTON) {
                                useApplicationPinFallback();
                                return;
                            }
                            showBiometricOverlay(
                                    "Aplikasi tetap terkunci. Coba biometrik lagi atau gunakan PIN aplikasi.");
                        }
                        notifyWebBiometricStatus();
                    }

                    @Override
                    public void onAuthenticationSucceeded(BiometricPrompt.AuthenticationResult result) {
                        super.onAuthenticationSucceeded(result);
                        biometricPromptVisible = false;
                        if (mode == BIOMETRIC_MODE_ENABLE) {
                            securityPrefs.edit().putBoolean(PREF_BIOMETRIC_ENABLED, true).apply();
                            Toast.makeText(MainActivity.this, "Kunci biometrik diaktifkan.", Toast.LENGTH_SHORT).show();
                        } else if (mode == BIOMETRIC_MODE_DISABLE) {
                            securityPrefs.edit().putBoolean(PREF_BIOMETRIC_ENABLED, false).apply();
                            Toast.makeText(MainActivity.this, "Kunci biometrik dinonaktifkan.", Toast.LENGTH_SHORT)
                                    .show();
                        }
                        hideBiometricOverlay();
                        notifyWebBiometricStatus();
                    }

                    @Override
                    public void onAuthenticationFailed() {
                        super.onAuthenticationFailed();
                        if (mode == BIOMETRIC_MODE_UNLOCK) {
                            showBiometricOverlay("Biometrik tidak cocok. Silakan coba lagi.");
                        }
                    }
                });

        BiometricPrompt.PromptInfo.Builder promptBuilder = new BiometricPrompt.PromptInfo.Builder()
                .setTitle(mode == BIOMETRIC_MODE_DISABLE ? "Nonaktifkan kunci biometrik" : "Buka Catatan Keuangan")
                .setSubtitle(mode == BIOMETRIC_MODE_ENABLE
                        ? "Verifikasi untuk mengaktifkan " + biometricLabel().toLowerCase(Locale.ROOT)
                        : "Gunakan " + biometricLabel().toLowerCase(Locale.ROOT))
                .setAllowedAuthenticators(biometricAuthenticators())
                .setConfirmationRequired(false);

        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.R) {
            promptBuilder.setNegativeButtonText(
                    mode == BIOMETRIC_MODE_UNLOCK ? "Gunakan PIN aplikasi" : "Batal");
        }

        biometricPromptVisible = true;
        prompt.authenticate(promptBuilder.build());
    }

    public class AppInfoBridge {
        @JavascriptInterface
        public String getVersionInfo() {
            return "{" +
                    "\"version_name\":\"" + jsonEscape(getAppVersionName()) + "\"," +
                    "\"version_code\":" + getAppVersionCode() +
                    "}";
        }
    }



    private class BiometricBridge {
        @JavascriptInterface
        public String getStatus() {
            return getBiometricStatusJson();
        }

        @JavascriptInterface
        public void requestEnable() {
            runOnUiThread(() -> authenticateBiometric(BIOMETRIC_MODE_ENABLE));
        }

        @JavascriptInterface
        public void requestDisable() {
            runOnUiThread(() -> {
                if (!isBiometricEnabled()) {
                    notifyWebBiometricStatus();
                    return;
                }
                authenticateBiometric(BIOMETRIC_MODE_DISABLE);
            });
        }
    }

    private class OfflineBridge {
        @JavascriptInterface
        public void markReady(String userId) {
            if (offlinePrefs == null)
                return;
            offlinePrefs.edit()
                    .putBoolean(PREF_OFFLINE_READY, true)
                    .putString(PREF_OFFLINE_USER_ID, userId == null ? "" : userId)
                    .putLong(PREF_OFFLINE_READY_AT, System.currentTimeMillis())
                    .apply();
        }

        @JavascriptInterface
        public void clearReady() {
            if (offlinePrefs == null)
                return;
            offlinePrefs.edit().clear().apply();
        }

        @JavascriptInterface
        public boolean isReady() {
            return isOfflineReady();
        }
    }

    private class ReminderBridge {
        @JavascriptInterface
        public void replaceCreditCardSchedules(String json) {
            runOnUiThread(() -> replaceCreditCardSchedulesInternal(json));
        }

        @JavascriptInterface
        public void clearCreditCardSchedules() {
            runOnUiThread(() -> clearCreditCardSchedulesInternal());
        }
    }

    private void ensureCreditCardNotificationChannel() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            NotificationManager manager = (NotificationManager) getSystemService(Context.NOTIFICATION_SERVICE);
            if (manager != null) {
                NotificationChannel channel = new NotificationChannel(
                        CreditCardReminderReceiver.CHANNEL_ID,
                        "Pengingat Kartu Kredit",
                        NotificationManager.IMPORTANCE_HIGH);
                channel.setDescription("Pengingat H-2 dan H-1 penagihan kartu kredit");
                manager.createNotificationChannel(channel);
            }
        }
    }

    private void requestNotificationPermissionIfNeeded() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU
                && ContextCompat.checkSelfPermission(this, Manifest.permission.POST_NOTIFICATIONS)
                != PackageManager.PERMISSION_GRANTED) {
            requestPermissions(new String[]{Manifest.permission.POST_NOTIFICATIONS}, NOTIFICATION_PERMISSION_REQUEST);
        }
    }

    private int reminderRequestCode(String key) {
        int code = Math.abs((key == null ? "" : key).hashCode());
        return 490000 + (code % 100000);
    }

    private PendingIntent reminderPendingIntent(int requestCode, String title, String message) {
        Intent intent = new Intent(this, CreditCardReminderReceiver.class);
        intent.putExtra("title", title);
        intent.putExtra("message", message);
        intent.putExtra("notification_id", requestCode);
        return PendingIntent.getBroadcast(
                this,
                requestCode,
                intent,
                PendingIntent.FLAG_UPDATE_CURRENT | PendingIntent.FLAG_IMMUTABLE);
    }

    private void clearCreditCardSchedulesInternal() {
        if (reminderPrefs == null) return;
        AlarmManager alarms = (AlarmManager) getSystemService(Context.ALARM_SERVICE);
        String stored = reminderPrefs.getString(PREF_REMINDER_CODES, "");
        if (alarms != null && stored != null && !stored.trim().isEmpty()) {
            for (String part : stored.split(",")) {
                try {
                    int requestCode = Integer.parseInt(part.trim());
                    Intent intent = new Intent(this, CreditCardReminderReceiver.class);
                    PendingIntent pending = PendingIntent.getBroadcast(
                            this,
                            requestCode,
                            intent,
                            PendingIntent.FLAG_NO_CREATE | PendingIntent.FLAG_IMMUTABLE);
                    if (pending != null) {
                        alarms.cancel(pending);
                        pending.cancel();
                    }
                } catch (Exception ignored) {}
            }
        }
        reminderPrefs.edit().remove(PREF_REMINDER_CODES).apply();
    }

    private void replaceCreditCardSchedulesInternal(String json) {
        clearCreditCardSchedulesInternal();
        requestNotificationPermissionIfNeeded();
        if (json == null || json.trim().isEmpty()) return;
        AlarmManager alarms = (AlarmManager) getSystemService(Context.ALARM_SERVICE);
        if (alarms == null) return;
        List<String> requestCodes = new ArrayList<>();
        try {
            JSONArray schedules = new JSONArray(json);
            long now = System.currentTimeMillis();
            for (int i = 0; i < schedules.length(); i++) {
                JSONObject row = schedules.optJSONObject(i);
                if (row == null) continue;
                String key = row.optString("key", "cc_" + i);
                long triggerAt = row.optLong("trigger_at", 0L);
                if (triggerAt <= now + 30000L) continue;
                String title = row.optString("title", "Pengingat Tagihan Kartu Kredit");
                String message = row.optString("message", "Tagihan kartu kredit akan segera ditagihkan.");
                int requestCode = reminderRequestCode(key);
                PendingIntent pending = reminderPendingIntent(requestCode, title, message);
                if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M) {
                    alarms.setAndAllowWhileIdle(AlarmManager.RTC_WAKEUP, triggerAt, pending);
                } else {
                    alarms.set(AlarmManager.RTC_WAKEUP, triggerAt, pending);
                }
                requestCodes.add(String.valueOf(requestCode));
            }
            if (reminderPrefs != null) {
                reminderPrefs.edit().putString(PREF_REMINDER_CODES, TextUtils.join(",", requestCodes)).apply();
            }
        } catch (Exception ignored) {
            // Reminder lokal tidak boleh mengganggu fungsi utama WebView.
        }
    }

    private boolean isOfflineReady() {
        return offlinePrefs != null && offlinePrefs.getBoolean(PREF_OFFLINE_READY, false);
    }

    private void setOfflineAwareCacheMode(boolean online) {
        if (webView != null) {
            webView.getSettings().setCacheMode(
                    online ? WebSettings.LOAD_DEFAULT : WebSettings.LOAD_CACHE_ELSE_NETWORK);
        }
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.N) {
            try {
                ServiceWorkerController.getInstance()
                        .getServiceWorkerWebSettings()
                        .setCacheMode(online ? WebSettings.LOAD_DEFAULT : WebSettings.LOAD_CACHE_ELSE_NETWORK);
            } catch (Exception ignored) {
            }
        }
    }

    private void loadInitialApp() {
        boolean online = isConnected();
        setOfflineAwareCacheMode(online);
        offlineBootRetryAttempted = false;

        if (!online && !isOfflineReady()) {
            showOfflineFallback();
            return;
        }

        // Tetap gunakan origin HTTPS saat offline. Service Worker + Cache Storage
        // kemudian dapat membuka shell akun dan IndexedDB yang sama seperti saat online.
        showingLocalOfflinePage = false;
        webView.loadUrl(APP_URL);
    }

    private void configureServiceWorker() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.N) {
            ServiceWorkerWebSettings sw = ServiceWorkerController
                    .getInstance()
                    .getServiceWorkerWebSettings();

            sw.setCacheMode(isConnected()
                    ? WebSettings.LOAD_DEFAULT
                    : WebSettings.LOAD_CACHE_ELSE_NETWORK);
            sw.setAllowContentAccess(true);
            sw.setAllowFileAccess(false);
            sw.setBlockNetworkLoads(false);
        }
    }

    private void registerNetworkWatcher() {
        connectivityManager = (ConnectivityManager) getSystemService(Context.CONNECTIVITY_SERVICE);

        if (connectivityManager == null ||
                Build.VERSION.SDK_INT < Build.VERSION_CODES.N) {
            return;
        }

        networkCallback = new ConnectivityManager.NetworkCallback() {
            @Override
            public void onAvailable(Network network) {
                runOnUiThread(() -> {
                    setOfflineAwareCacheMode(true);
                    offlineBootRetryAttempted = false;
                    if (showingLocalOfflinePage) {
                        showingLocalOfflinePage = false;
                        webView.loadUrl(APP_URL);
                    } else {
                        // Trigger event online agar queue IndexedDB langsung diproses.
                        webView.evaluateJavascript(
                                "window.dispatchEvent(new Event('online'));",
                                null);
                    }
                });
            }

            @Override
            public void onLost(Network network) {
                runOnUiThread(() -> {
                    setOfflineAwareCacheMode(false);
                    webView.evaluateJavascript(
                            "window.dispatchEvent(new Event('offline'));",
                            null);
                });
            }
        };

        try {
            connectivityManager.registerDefaultNetworkCallback(networkCallback);
        } catch (Exception ignored) {
        }
    }

    private class FinanceWebViewClient extends WebViewClient {

        @Override
        public boolean shouldOverrideUrlLoading(
                WebView view,
                WebResourceRequest request) {
            return handleNavigation(request.getUrl());
        }

        @Override
        public boolean shouldOverrideUrlLoading(WebView view, String url) {
            return handleNavigation(Uri.parse(url));
        }

        @Override
        public void onPageStarted(
                WebView view,
                String url,
                Bitmap favicon) {
            super.onPageStarted(view, url, favicon);

            if (url != null && url.startsWith("http")) {
                showingLocalOfflinePage = false;
            }

            progressBar.setVisibility(View.VISIBLE);
        }

        @Override
        public void onPageFinished(WebView view, String url) {
            super.onPageFinished(view, url);

            progressBar.setVisibility(View.GONE);

            if (url != null && url.startsWith(APP_URL)) {
                offlineBootRetryAttempted = false;
                showingLocalOfflinePage = false;
            }

            // Pastikan cookie/session tersimpan ke disk
            CookieManager.getInstance().flush();
            notifyWebBiometricStatus();
        }

        @Override
        public void onReceivedError(
                WebView view,
                WebResourceRequest request,
                WebResourceError error) {
            super.onReceivedError(view, request, error);

            if (request.isForMainFrame() && !isConnected()) {
                setOfflineAwareCacheMode(false);

                if (isOfflineReady() && !offlineBootRetryAttempted) {
                    // Beri Service Worker/WebView cache satu kesempatan lagi sebelum
                    // meninggalkan origin HTTPS. Origin harus dipertahankan agar IndexedDB
                    // akun tetap dapat dibaca pada cold-start offline.
                    offlineBootRetryAttempted = true;
                    view.postDelayed(() -> view.loadUrl(APP_URL), 180);
                    return;
                }

                showOfflineFallback();
            }
        }

        private boolean handleNavigation(Uri uri) {
            if (uri == null) {
                return false;
            }

            String scheme = uri.getScheme() == null
                    ? ""
                    : uri.getScheme().toLowerCase(Locale.ROOT);

            String host = uri.getHost();

            // Link domain aplikasi tetap dibuka di WebView
            if (("http".equals(scheme) || "https".equals(scheme))
                    && APP_HOST.equalsIgnoreCase(host)) {
                return false;
            }

            // URL internal WebView
            if ("blob".equals(scheme)
                    || "data".equals(scheme)
                    || "about".equals(scheme)) {
                return false;
            }

            // Link eksternal dibuka dengan aplikasi/browser Android
            try {
                startActivity(new Intent(Intent.ACTION_VIEW, uri));
            } catch (Exception e) {
                Toast.makeText(
                        MainActivity.this,
                        "Tidak dapat membuka tautan ini.",
                        Toast.LENGTH_SHORT).show();
            }

            return true;
        }
    }

    private class FinanceWebChromeClient extends WebChromeClient {

        @Override
        public void onProgressChanged(
                WebView view,
                int newProgress) {
            progressBar.setProgress(newProgress);
            progressBar.setVisibility(
                    newProgress >= 100 ? View.GONE : View.VISIBLE);
        }

        @Override
        public boolean onShowFileChooser(
                WebView webView,
                ValueCallback<Uri[]> callback,
                FileChooserParams fileChooserParams) {
            if (filePathCallback != null) {
                filePathCallback.onReceiveValue(null);
            }

            filePathCallback = callback;

            // Galeri
            Intent galleryIntent = new Intent(Intent.ACTION_GET_CONTENT);
            galleryIntent.addCategory(Intent.CATEGORY_OPENABLE);
            galleryIntent.setType("image/*");

            // Kamera
            Intent cameraIntent = new Intent(android.provider.MediaStore.ACTION_IMAGE_CAPTURE);

            try {
                cameraPhotoUri = createCameraUri();

                cameraIntent.putExtra(
                        android.provider.MediaStore.EXTRA_OUTPUT,
                        cameraPhotoUri);

                cameraIntent.addFlags(
                        Intent.FLAG_GRANT_WRITE_URI_PERMISSION
                                | Intent.FLAG_GRANT_READ_URI_PERMISSION);

            } catch (IOException e) {
                cameraIntent = null;
                cameraPhotoUri = null;
            }

            Intent chooser = Intent.createChooser(
                    galleryIntent,
                    "Pilih foto nota");

            if (cameraIntent != null) {
                chooser.putExtra(
                        Intent.EXTRA_INITIAL_INTENTS,
                        new Intent[] { cameraIntent });
            }

            startActivityForResult(
                    chooser,
                    FILE_CHOOSER_REQUEST);

            return true;
        }
    }

    private Uri createCameraUri() throws IOException {
        File dir = new File(getCacheDir(), "camera");

        if (!dir.exists() && !dir.mkdirs()) {
            throw new IOException(
                    "Tidak dapat membuat folder kamera");
        }

        String timestamp = new SimpleDateFormat(
                "yyyyMMdd_HHmmss",
                Locale.US).format(new Date());

        File image = File.createTempFile(
                "CF_" + timestamp + "_",
                ".jpg",
                dir);

        return FileProvider.getUriForFile(
                this,
                getPackageName() + ".fileprovider",
                image);
    }

    @Override
    protected void onActivityResult(
            int requestCode,
            int resultCode,
            Intent data) {
        super.onActivityResult(
                requestCode,
                resultCode,
                data);

        if (requestCode != FILE_CHOOSER_REQUEST
                || filePathCallback == null) {
            return;
        }

        Uri[] result = null;

        if (resultCode == RESULT_OK) {
            if (data != null && data.getData() != null) {
                result = new Uri[] { data.getData() };

            } else if (cameraPhotoUri != null) {
                result = new Uri[] { cameraPhotoUri };

            } else {
                result = WebChromeClient.FileChooserParams
                        .parseResult(
                                resultCode,
                                data);
            }
        }

        filePathCallback.onReceiveValue(result);
        filePathCallback = null;
        cameraPhotoUri = null;
    }

    private class FinanceDownloadListener
            implements DownloadListener {

        @Override
        public void onDownloadStart(
                String url,
                String userAgent,
                String contentDisposition,
                String mimeType,
                long contentLength) {
            try {
                String filename = android.webkit.URLUtil.guessFileName(
                        url,
                        contentDisposition,
                        mimeType);

                DownloadManager.Request request = new DownloadManager.Request(
                        Uri.parse(url));

                request.setTitle(filename);
                request.setDescription(
                        "Mengunduh laporan Catatan Keuangan");

                request.setMimeType(mimeType);
                request.setAllowedOverMetered(true);
                request.setAllowedOverRoaming(true);

                request.setNotificationVisibility(
                        DownloadManager.Request.VISIBILITY_VISIBLE_NOTIFY_COMPLETED);

                request.addRequestHeader(
                        "User-Agent",
                        userAgent);

                request.addRequestHeader(
                        "Referer",
                        APP_URL);

                String cookies = CookieManager
                        .getInstance()
                        .getCookie(url);

                if (cookies != null
                        && !cookies.isEmpty()) {
                    request.addRequestHeader(
                            "Cookie",
                            cookies);
                }

                /*
                 * Android 10+:
                 * simpan langsung ke folder Downloads.
                 *
                 * Android 7-9:
                 * simpan ke folder external app-specific
                 * tanpa permission storage tambahan.
                 */
                if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.Q) {

                    request.setDestinationInExternalPublicDir(
                            Environment.DIRECTORY_DOWNLOADS,
                            filename);

                } else {

                    request.setDestinationInExternalFilesDir(
                            MainActivity.this,
                            Environment.DIRECTORY_DOWNLOADS,
                            filename);
                }

                DownloadManager dm = (DownloadManager) getSystemService(
                        DOWNLOAD_SERVICE);

                if (dm == null) {
                    throw new IllegalStateException(
                            "DownloadManager tidak tersedia");
                }

                dm.enqueue(request);

                Toast.makeText(
                        MainActivity.this,
                        "Download dimulai: " + filename,
                        Toast.LENGTH_SHORT).show();

            } catch (Exception e) {
                Toast.makeText(
                        MainActivity.this,
                        "Download gagal. Coba lagi.",
                        Toast.LENGTH_LONG).show();
            }
        }
    }

    private void showOfflineFallback() {
        if (showingLocalOfflinePage) {
            return;
        }

        showingLocalOfflinePage = true;
        progressBar.setVisibility(View.GONE);

        webView.loadUrl(
                "file:///android_asset/offline.html");
    }

    private boolean isConnected() {
        ConnectivityManager cm = (ConnectivityManager) getSystemService(
                Context.CONNECTIVITY_SERVICE);

        if (cm == null) {
            return false;
        }

        Network network = cm.getActiveNetwork();

        if (network == null) {
            return false;
        }

        NetworkCapabilities caps = cm.getNetworkCapabilities(network);

        return caps != null
                && (caps.hasTransport(
                        NetworkCapabilities.TRANSPORT_WIFI)
                        || caps.hasTransport(
                                NetworkCapabilities.TRANSPORT_CELLULAR)
                        || caps.hasTransport(
                                NetworkCapabilities.TRANSPORT_ETHERNET)
                        || caps.hasTransport(
                                NetworkCapabilities.TRANSPORT_VPN));
    }

    @Override
    public void onBackPressed() {
        if (webView.canGoBack()) {
            webView.goBack();
        } else {
            super.onBackPressed();
        }
    }

    @Override
    protected void onSaveInstanceState(
            Bundle outState) {
        webView.saveState(outState);
        super.onSaveInstanceState(outState);
    }

    @Override
    protected void onPause() {
        CookieManager.getInstance().flush();
        webView.onPause();
        super.onPause();
    }

    @Override
    protected void onResume() {
        super.onResume();

        webView.onResume();

        if (isBiometricEnabled()
                && backgroundedAt > 0L
                && (System.currentTimeMillis() - backgroundedAt) >= BIOMETRIC_RELOCK_AFTER_MS
                && !biometricPromptVisible) {
            showBiometricOverlay("Verifikasi biometrik untuk kembali ke aplikasi.");
            authenticateBiometric(BIOMETRIC_MODE_UNLOCK);
        }
        backgroundedAt = 0L;

        boolean online = isConnected();
        setOfflineAwareCacheMode(online);

        if (online && showingLocalOfflinePage) {
            showingLocalOfflinePage = false;
            offlineBootRetryAttempted = false;
            webView.loadUrl(APP_URL);
        } else if (online) {
            // Membantu queue IndexedDB memicu auto-sync setelah aplikasi kembali aktif.
            webView.evaluateJavascript(
                    "window.dispatchEvent(new Event('online'));",
                    null);
        } else if (!showingLocalOfflinePage && isOfflineReady()) {
            webView.evaluateJavascript(
                    "window.dispatchEvent(new Event('offline'));",
                    null);
        }
    }

    @Override
    protected void onStop() {
        backgroundedAt = System.currentTimeMillis();
        super.onStop();
    }

    @Override
    protected void onDestroy() {

        if (filePathCallback != null) {
            filePathCallback.onReceiveValue(null);
            filePathCallback = null;
        }

        if (connectivityManager != null
                && networkCallback != null
                && Build.VERSION.SDK_INT >= Build.VERSION_CODES.N) {

            try {
                connectivityManager
                        .unregisterNetworkCallback(
                                networkCallback);
            } catch (Exception ignored) {
            }
        }

        webView.stopLoading();
        webView.setWebChromeClient(null);
        webView.setWebViewClient(null);
        webView.destroy();

        super.onDestroy();
    }
}
