<?php
$baseUrl = rtrim((string)(Yii::$app->params['appUrl'] ?: Yii::$app->request->hostInfo), '/');
$csrfToken = Yii::$app->request->csrfToken;
$initialJson = json_encode($snapshot, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT);
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="theme-color" content="#175cd3">
<meta name="csrf-token" content="<?= h($csrfToken) ?>">
<meta name="csrf-param" content="<?= h(Yii::$app->request->csrfParam) ?>">
<title>Perencanaan Keuangan</title>
<link rel="icon" type="image/webp" href="/assets/icon.webp">
<link rel="apple-touch-icon" href="/assets/icon.webp">
<link rel="stylesheet" href="/perencanaan-pengeluaran/web/assets/planning.css?v=<?= h($assetVersion) ?>">
</head>
<body>
<div class="planning-shell" id="planningApp" data-csrf="<?= h($csrfToken) ?>">
    <header class="planning-header">
        <div class="planning-brand">
            <div class="planning-brand-mark"><img src="/assets/icon.webp" alt="Catatan Keuangan"></div>
            <div><span>CATAT KEUANGAN</span><h1>Perencanaan Keuangan</h1><small>Susun rencana belanja berdasarkan data akun Anda.</small></div>
        </div>
        <div class="planning-header-actions">
            <span class="premium-chip">Premium</span>
            <a class="header-link" href="<?= h($baseUrl) ?>/index.php?app=chooser">Pilih Aplikasi</a>
            <form method="post" action="<?= h($baseUrl) ?>/index.php">
                <input type="hidden" name="<?= h(Yii::$app->request->csrfParam) ?>" value="<?= h($csrfToken) ?>">
                <input type="hidden" name="action" value="set_preferred_app">
                <input type="hidden" name="app" value="main">
                <button type="submit" class="header-link button-link">Catat Keuangan</button>
            </form>
        </div>
    </header>

    <main class="planning-main">
        <section class="planning-hero">
            <div>
                <span class="eyebrow">RENCANA BULANAN</span>
                <h2>Rencana belanja untuk <span id="monthLabel">—</span></h2>
                <p>Angka awal diambil dari riwayat pengeluaran, pemasukan yang tercatat, serta tagihan dan transaksi berulang yang sudah terjadwal. Semua masih bisa Anda ubah.</p>
            </div>
            <div class="month-picker-wrap">
                <label>Bulan yang direncanakan<input type="month" id="planMonth" value="<?= h($snapshot['month']) ?>"></label>
            </div>
        </section>

        <section class="planning-kpis" id="planningKpis"></section>

        <section class="planning-alert" id="planningAlert"></section>

        <div class="planning-grid">
            <section class="planning-card plan-editor-card">
                <div class="card-head">
                    <div><span class="eyebrow">RENCANA BELANJA</span><h3>Susun dan ubah anggaran</h3><p>Anda bebas mengubah nominal, menonaktifkan item, atau menambah kebutuhan baru.</p></div>
                    <button type="button" class="btn secondary" id="addPlanItem">+ Tambah rencana</button>
                </div>
                <div class="plan-section-title"><span>Belanja variabel</span><small>Berdasarkan riwayat</small></div>
                <div id="variablePlanList" class="plan-list"></div>
                <div class="plan-section-title obligation-title"><span>Kewajiban terjadwal</span><small>Tagihan & transaksi berulang</small></div>
                <div id="obligationPlanList" class="plan-list"></div>
                <div class="empty-plan" id="emptyPlan" hidden>Belum ada rencana. Tambahkan kebutuhan belanja yang ingin Anda siapkan.</div>
            </section>

            <aside class="planning-side">
                <section class="planning-card cash-card">
                    <div class="card-head compact"><div><span class="eyebrow">DANA</span><h3>Ruang yang tersedia</h3></div></div>
                    <div class="cash-line"><span>Saldo tersedia sekarang</span><b id="currentBalance">Rp0</b></div>
                    <div class="cash-line"><span>Perkiraan saldo awal bulan</span><b id="openingBalance">Rp0</b></div>
                    <div class="cash-line"><span>Pemasukan terjadwal</span><b id="scheduledIncome">Rp0</b></div>
                    <div class="cash-line"><span>Pemasukan berdasarkan riwayat</span><b id="historicalIncome">Rp0</b></div>
                    <div class="divider"></div>
                    <label class="income-override">Pemasukan untuk rencana <small>Kosongkan untuk memakai estimasi otomatis</small><input type="number" min="0" step="5000" id="incomeOverride" placeholder="Otomatis"></label>
                </section>

                <section class="planning-card summary-card">
                    <div class="card-head compact"><div><span class="eyebrow">RINGKASAN</span><h3>Jika rencana diterapkan</h3></div></div>
                    <div class="summary-row"><span>Belanja variabel</span><b id="variableTotal">Rp0</b></div>
                    <div class="summary-row"><span>Tagihan & kewajiban</span><b id="obligationTotal">Rp0</b></div>
                    <label class="summary-row savings-row"><span>Target dana disisihkan</span><input type="number" min="0" step="5000" id="targetSavings" value="0"></label>
                    <div class="summary-row strong"><span>Total perlu disiapkan</span><b id="requiredTotal">Rp0</b></div>
                    <div class="summary-row strong"><span>Perkiraan sisa</span><b id="remainingTotal">Rp0</b></div>
                </section>

                <section class="planning-card obligation-card">
                    <div class="card-head compact"><div><span class="eyebrow">KETERIKATAN</span><h3>Kewajiban dari aplikasi utama</h3></div></div>
                    <p>Tagihan/cicilan dan transaksi berulang dipisahkan dari belanja variabel agar tidak dihitung dua kali.</p>
                    <div id="obligationSummary" class="obligation-summary"></div>
                </section>

                <section class="planning-card credit-card-summary">
                    <div class="card-head compact"><div><span class="eyebrow">KARTU KREDIT</span><h3>Informasi limit</h3></div></div>
                    <div id="creditSummary"></div>
                </section>
            </aside>
        </div>

        <section class="planning-card notes-card">
            <div class="card-head compact"><div><span class="eyebrow">CATATAN</span><h3>Catatan untuk rencana ini</h3></div></div>
            <textarea id="planNotes" maxlength="2000" placeholder="Misalnya ada belanja khusus, perjalanan, atau kebutuhan lain bulan depan..."><?= h((string)($snapshot['saved_plan']['notes'] ?? '')) ?></textarea>
            <div class="planning-disclaimer">Ini adalah estimasi berdasarkan data yang tercatat di Catatan Keuangan, bukan jaminan. Pemasukan, transaksi baru, tagihan, dan perubahan jadwal dapat mengubah hasil.</div>
        </section>
    </main>

    <footer class="planning-footer">
        <span>Data tersambung dengan Catatan Keuangan untuk akun yang sama.</span>
        <div><button type="button" class="btn secondary" id="resetPlan">Kembalikan saran awal</button><button type="button" class="btn primary" id="savePlan">Simpan Rencana</button></div>
    </footer>
</div>
<script>window.PLANNING_INITIAL=<?= $initialJson ?: '{}' ?>;</script>
<script src="/perencanaan-pengeluaran/web/assets/planning.js?v=<?= h($assetVersion) ?>"></script>
</body>
</html>
