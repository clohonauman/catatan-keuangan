package com.charlie.finance;

import android.content.Intent;
import android.os.Bundle;
import android.os.Handler;
import android.os.Looper;

import androidx.fragment.app.FragmentActivity;

/**
 * Splash screen native yang tampil hanya saat aplikasi dibuka (cold start).
 * Tidak mengganggu layar biometrik; setelah splash selesai MainActivity akan
 * menangani WebView dan penguncian biometrik seperti biasa.
 */
public class SplashActivity extends FragmentActivity {

    private static final long SPLASH_DURATION_MS = 900L;
    private final Handler handler = new Handler(Looper.getMainLooper());

    private final Runnable openMain = () -> {
        if (isFinishing() || isDestroyed()) {
            return;
        }

        Intent intent = new Intent(SplashActivity.this, MainActivity.class);
        startActivity(intent);
        overridePendingTransition(android.R.anim.fade_in, android.R.anim.fade_out);
        finish();
    };

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        setContentView(R.layout.activity_splash);
        handler.postDelayed(openMain, SPLASH_DURATION_MS);
    }

    @Override
    protected void onDestroy() {
        handler.removeCallbacks(openMain);
        super.onDestroy();
    }
}
