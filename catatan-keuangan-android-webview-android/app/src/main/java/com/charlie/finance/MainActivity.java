package com.charlie.finance;

import android.app.Activity;
import android.app.DownloadManager;
import android.content.Context;
import android.content.Intent;
import android.content.pm.ApplicationInfo;
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
import android.webkit.ServiceWorkerController;
import android.webkit.ServiceWorkerWebSettings;
import android.webkit.ValueCallback;
import android.webkit.WebChromeClient;
import android.webkit.WebResourceError;
import android.webkit.WebResourceRequest;
import android.webkit.WebSettings;
import android.webkit.WebView;
import android.webkit.WebViewClient;
import android.widget.ProgressBar;
import android.widget.Toast;

import androidx.core.content.FileProvider;

import java.io.File;
import java.io.IOException;
import java.text.SimpleDateFormat;
import java.util.Date;
import java.util.Locale;

public class MainActivity extends Activity {

    private static final String APP_URL = "https://charlie-finance.rf.gd/";
    private static final String APP_HOST = "charlie-finance.rf.gd";
    private static final int FILE_CHOOSER_REQUEST = 1001;

    private WebView webView;
    private ProgressBar progressBar;
    private ValueCallback<Uri[]> filePathCallback;
    private Uri cameraPhotoUri;
    private boolean showingLocalOfflinePage = false;
    private ConnectivityManager connectivityManager;
    private ConnectivityManager.NetworkCallback networkCallback;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        setContentView(R.layout.activity_main);

        webView = findViewById(R.id.webView);
        progressBar = findViewById(R.id.progressBar);

        configureWebView();
        configureServiceWorker();
        registerNetworkWatcher();

        if (savedInstanceState != null) {
            webView.restoreState(savedInstanceState);
        } else {
            webView.loadUrl(APP_URL);
        }
    }

    private void configureWebView() {
        WebSettings settings = webView.getSettings();

        // Fitur utama WebView
        settings.setJavaScriptEnabled(true);
        settings.setDomStorageEnabled(true);
        settings.setDatabaseEnabled(true);
        settings.setCacheMode(WebSettings.LOAD_DEFAULT);
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
                settings.getUserAgentString() + " CatatanKeuanganAndroid/1.1"
        );

        // Cookie/session login tetap tersimpan
        CookieManager cookieManager = CookieManager.getInstance();
        cookieManager.setAcceptCookie(true);

        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.LOLLIPOP) {
            cookieManager.setAcceptThirdPartyCookies(webView, false);
        }

        webView.setWebViewClient(new FinanceWebViewClient());
        webView.setWebChromeClient(new FinanceWebChromeClient());
        webView.setDownloadListener(new FinanceDownloadListener());

        // Debug WebView hanya aktif pada debug build
        boolean isDebuggable =
                (getApplicationInfo().flags & ApplicationInfo.FLAG_DEBUGGABLE) != 0;

        if (isDebuggable) {
            WebView.setWebContentsDebuggingEnabled(true);
        }
    }

    private void configureServiceWorker() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.N) {
            ServiceWorkerWebSettings sw =
                    ServiceWorkerController
                            .getInstance()
                            .getServiceWorkerWebSettings();

            sw.setCacheMode(WebSettings.LOAD_DEFAULT);
            sw.setAllowContentAccess(true);
            sw.setAllowFileAccess(false);
            sw.setBlockNetworkLoads(false);
        }
    }

    private void registerNetworkWatcher() {
        connectivityManager =
                (ConnectivityManager) getSystemService(Context.CONNECTIVITY_SERVICE);

        if (connectivityManager == null ||
                Build.VERSION.SDK_INT < Build.VERSION_CODES.N) {
            return;
        }

        networkCallback = new ConnectivityManager.NetworkCallback() {
            @Override
            public void onAvailable(Network network) {
                runOnUiThread(() -> {
                    if (showingLocalOfflinePage) {
                        showingLocalOfflinePage = false;
                        webView.loadUrl(APP_URL);
                    } else {
                        // Trigger event online pada aplikasi web agar antrean
                        // IndexedDB/offline sync segera diproses.
                        webView.evaluateJavascript(
                                "window.dispatchEvent(new Event('online'));",
                                null
                        );
                    }
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
                WebResourceRequest request
        ) {
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
                Bitmap favicon
        ) {
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

            // Pastikan cookie/session tersimpan ke disk
            CookieManager.getInstance().flush();
        }

        @Override
        public void onReceivedError(
                WebView view,
                WebResourceRequest request,
                WebResourceError error
        ) {
            super.onReceivedError(view, request, error);

            if (request.isForMainFrame() && !isConnected()) {
                // Service Worker mendapat kesempatan lebih dulu.
                // Fallback lokal dipakai bila halaman domain tidak tersedia.
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
                        Toast.LENGTH_SHORT
                ).show();
            }

            return true;
        }
    }

    private class FinanceWebChromeClient extends WebChromeClient {

        @Override
        public void onProgressChanged(
                WebView view,
                int newProgress
        ) {
            progressBar.setProgress(newProgress);
            progressBar.setVisibility(
                    newProgress >= 100 ? View.GONE : View.VISIBLE
            );
        }

        @Override
        public boolean onShowFileChooser(
                WebView webView,
                ValueCallback<Uri[]> callback,
                FileChooserParams fileChooserParams
        ) {
            if (filePathCallback != null) {
                filePathCallback.onReceiveValue(null);
            }

            filePathCallback = callback;

            // Galeri
            Intent galleryIntent =
                    new Intent(Intent.ACTION_GET_CONTENT);
            galleryIntent.addCategory(Intent.CATEGORY_OPENABLE);
            galleryIntent.setType("image/*");

            // Kamera
            Intent cameraIntent =
                    new Intent(android.provider.MediaStore.ACTION_IMAGE_CAPTURE);

            try {
                cameraPhotoUri = createCameraUri();

                cameraIntent.putExtra(
                        android.provider.MediaStore.EXTRA_OUTPUT,
                        cameraPhotoUri
                );

                cameraIntent.addFlags(
                        Intent.FLAG_GRANT_WRITE_URI_PERMISSION
                                | Intent.FLAG_GRANT_READ_URI_PERMISSION
                );

            } catch (IOException e) {
                cameraIntent = null;
                cameraPhotoUri = null;
            }

            Intent chooser =
                    Intent.createChooser(
                            galleryIntent,
                            "Pilih foto nota"
                    );

            if (cameraIntent != null) {
                chooser.putExtra(
                        Intent.EXTRA_INITIAL_INTENTS,
                        new Intent[]{cameraIntent}
                );
            }

            startActivityForResult(
                    chooser,
                    FILE_CHOOSER_REQUEST
            );

            return true;
        }
    }

    private Uri createCameraUri() throws IOException {
        File dir =
                new File(getCacheDir(), "camera");

        if (!dir.exists() && !dir.mkdirs()) {
            throw new IOException(
                    "Tidak dapat membuat folder kamera"
            );
        }

        String timestamp =
                new SimpleDateFormat(
                        "yyyyMMdd_HHmmss",
                        Locale.US
                ).format(new Date());

        File image =
                File.createTempFile(
                        "CF_" + timestamp + "_",
                        ".jpg",
                        dir
                );

        return FileProvider.getUriForFile(
                this,
                getPackageName() + ".fileprovider",
                image
        );
    }

    @Override
    protected void onActivityResult(
            int requestCode,
            int resultCode,
            Intent data
    ) {
        super.onActivityResult(
                requestCode,
                resultCode,
                data
        );

        if (requestCode != FILE_CHOOSER_REQUEST
                || filePathCallback == null) {
            return;
        }

        Uri[] result = null;

        if (resultCode == RESULT_OK) {
            if (data != null && data.getData() != null) {
                result = new Uri[]{data.getData()};

            } else if (cameraPhotoUri != null) {
                result = new Uri[]{cameraPhotoUri};

            } else {
                result =
                        WebChromeClient
                                .FileChooserParams
                                .parseResult(
                                        resultCode,
                                        data
                                );
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
                long contentLength
        ) {
            try {
                String filename =
                        android.webkit.URLUtil.guessFileName(
                                url,
                                contentDisposition,
                                mimeType
                        );

                DownloadManager.Request request =
                        new DownloadManager.Request(
                                Uri.parse(url)
                        );

                request.setTitle(filename);
                request.setDescription(
                        "Mengunduh laporan Catatan Keuangan"
                );

                request.setMimeType(mimeType);
                request.setAllowedOverMetered(true);
                request.setAllowedOverRoaming(true);

                request.setNotificationVisibility(
                        DownloadManager.Request
                                .VISIBILITY_VISIBLE_NOTIFY_COMPLETED
                );

                request.addRequestHeader(
                        "User-Agent",
                        userAgent
                );

                request.addRequestHeader(
                        "Referer",
                        APP_URL
                );

                String cookies =
                        CookieManager
                                .getInstance()
                                .getCookie(url);

                if (cookies != null
                        && !cookies.isEmpty()) {
                    request.addRequestHeader(
                            "Cookie",
                            cookies
                    );
                }

                /*
                 * Android 10+:
                 * simpan langsung ke folder Downloads.
                 *
                 * Android 7-9:
                 * simpan ke folder external app-specific
                 * tanpa permission storage tambahan.
                 */
                if (Build.VERSION.SDK_INT
                        >= Build.VERSION_CODES.Q) {

                    request.setDestinationInExternalPublicDir(
                            Environment.DIRECTORY_DOWNLOADS,
                            filename
                    );

                } else {

                    request.setDestinationInExternalFilesDir(
                            MainActivity.this,
                            Environment.DIRECTORY_DOWNLOADS,
                            filename
                    );
                }

                DownloadManager dm =
                        (DownloadManager)
                                getSystemService(
                                        DOWNLOAD_SERVICE
                                );

                if (dm == null) {
                    throw new IllegalStateException(
                            "DownloadManager tidak tersedia"
                    );
                }

                dm.enqueue(request);

                Toast.makeText(
                        MainActivity.this,
                        "Download dimulai: " + filename,
                        Toast.LENGTH_SHORT
                ).show();

            } catch (Exception e) {
                Toast.makeText(
                        MainActivity.this,
                        "Download gagal. Coba lagi.",
                        Toast.LENGTH_LONG
                ).show();
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
                "file:///android_asset/offline.html"
        );
    }

    private boolean isConnected() {
        ConnectivityManager cm =
                (ConnectivityManager)
                        getSystemService(
                                Context.CONNECTIVITY_SERVICE
                        );

        if (cm == null) {
            return false;
        }

        Network network =
                cm.getActiveNetwork();

        if (network == null) {
            return false;
        }

        NetworkCapabilities caps =
                cm.getNetworkCapabilities(network);

        return caps != null
                && (
                caps.hasTransport(
                        NetworkCapabilities.TRANSPORT_WIFI
                )
                        || caps.hasTransport(
                        NetworkCapabilities.TRANSPORT_CELLULAR
                )
                        || caps.hasTransport(
                        NetworkCapabilities.TRANSPORT_ETHERNET
                )
                        || caps.hasTransport(
                        NetworkCapabilities.TRANSPORT_VPN
                )
        );
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
            Bundle outState
    ) {
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

        if (isConnected()
                && showingLocalOfflinePage) {

            showingLocalOfflinePage = false;
            webView.loadUrl(APP_URL);

        } else if (isConnected()) {

            // Membantu queue IndexedDB memicu auto-sync
            // setelah aplikasi kembali aktif.
            webView.evaluateJavascript(
                    "window.dispatchEvent(new Event('online'));",
                    null
            );
        }
    }

    @Override
    protected void onDestroy() {

        if (filePathCallback != null) {
            filePathCallback.onReceiveValue(null);
            filePathCallback = null;
        }

        if (connectivityManager != null
                && networkCallback != null
                && Build.VERSION.SDK_INT
                >= Build.VERSION_CODES.N) {

            try {
                connectivityManager
                        .unregisterNetworkCallback(
                                networkCallback
                        );
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
