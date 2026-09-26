<?php
/**
 * User-facing FAQ knowledge base.
 *
 * This file is the single source used by both the FAQ modal and Smart Chat.
 * Super Admin operational features are intentionally excluded.
 */
function faqCategoryLabels(): array {
    return [
        'transaksi' => 'Transaksi',
        'asisten' => 'Asisten AI',
        'saldo' => 'Saldo',
        'tagihan' => 'Tagihan',
        'premium' => 'Premium',
        'sinkronisasi' => 'Offline & Sinkronisasi',
        'akun' => 'Akun & Keamanan',
    ];
}

function faqKnowledgeBase(): array {
    return [
        [
            'id' => 'faq-1',
            'category' => 'transaksi',
            'question' => 'Bagaimana mencatat transaksi melalui chat?',
            'keywords' => 'chat catat transaksi asisten konfirmasi simpan draft',
            'answer_html' => 'Ketik transaksi dengan bahasa biasa, misalnya <b>“makan siang 25 ribu cash”</b>. Asisten akan membuat draft berisi jenis transaksi, nominal, kategori, tanggal, dompet, pola, dan tagihan terkait. Periksa lalu pilih <b>Simpan transaksi</b>.',
        ],
        [
            'id' => 'faq-2',
            'category' => 'transaksi',
            'question' => 'Bagaimana menambah transaksi secara manual?',
            'keywords' => 'tambah manual plus popup pemasukan pengeluaran transfer dompet',
            'answer_html' => 'Buka bagian <b>Transaksi</b> lalu tekan <b>+ Tambah</b>. Pilih <b>Pengeluaran</b>, <b>Pemasukan</b>, atau <b>Transfer Antar Dompet</b>, lengkapi nominal, tanggal, dompet, kategori, dan keterangan, lalu simpan. Transaksi manual memakai backend yang sama dengan chat sehingga langsung masuk saldo, riwayat, dan laporan.',
        ],
        [
            'id' => 'faq-3',
            'category' => 'transaksi',
            'question' => 'Bisakah jenis transaksi diubah sebelum disimpan?',
            'keywords' => 'ubah jenis transaksi draft pemasukan pengeluaran transfer dompet asal tujuan',
            'answer_html' => 'Bisa. Pada kartu konfirmasi chat, <b>Jenis Transaksi</b> dapat diubah menjadi Pengeluaran, Pemasukan, atau Transfer Antar Dompet. Jika Transfer dipilih, form otomatis meminta <b>Dompet Asal</b> dan <b>Dompet Tujuan</b>.',
        ],
        [
            'id' => 'faq-4',
            'category' => 'transaksi',
            'question' => 'Mengapa transaksi dari chat harus dikonfirmasi?',
            'keywords' => 'konfirmasi draft saldo belum berubah transaksi chat scan nota',
            'answer_html' => 'Konfirmasi mencegah transaksi salah akibat kalimat ambigu atau hasil scan nota yang kurang jelas. Selama masih berupa draft, transaksi belum memengaruhi saldo maupun analitik.',
        ],
        [
            'id' => 'faq-5',
            'category' => 'transaksi',
            'question' => 'Apa bedanya Harian, Sekali Bayar, dan Berulang?',
            'keywords' => 'harian sekali bayar berulang recurring pola analitik proyeksi',
            'answer_html' => '<b>Harian</b> dipakai untuk pengeluaran rutin sehari-hari dan dapat masuk ke estimasi kebutuhan harian. <b>Sekali Bayar</b> untuk pembelian yang tidak berulang, sedangkan <b>Berulang</b> untuk transaksi dengan jadwal rutin seperti mingguan atau bulanan.',
        ],
        [
            'id' => 'faq-6',
            'category' => 'transaksi',
            'question' => 'Bagaimana membatalkan transaksi yang salah?',
            'keywords' => 'edit hapus undo riwayat perubahan batalkan salah nominal',
            'answer_html' => 'Gunakan fitur <b>Riwayat</b> untuk melihat perubahan. Jika tersedia tombol <b>Undo</b>, Anda dapat mengembalikan transaksi atau perubahan saldo ke kondisi sebelumnya.',
        ],
        [
            'id' => 'faq-7',
            'category' => 'transaksi',
            'question' => 'Apakah nota atau struk bisa dicatat dari foto?',
            'keywords' => 'nota foto scan struk receipt lampiran kamera galeri kompres',
            'answer_html' => 'Bisa. Pilih sumber foto pada area chat lalu kirim foto nota/struk. Gambar dikompres otomatis sebelum diunggah dan hasil pembacaan tetap dibuat sebagai draft agar nominal serta informasi lain dapat diperiksa sebelum disimpan.',
        ],
        [
            'id' => 'faq-8',
            'category' => 'asisten',
            'question' => 'Apakah Asisten benar-benar belajar dari koreksi saya?',
            'keywords' => 'adaptive learning belajar otomatis koreksi ai pribadi machine learning',
            'answer_html' => 'Ya. <b>Adaptive Learning</b> mempelajari pola pribadi per akun dari transaksi yang sudah dikonfirmasi, terutama ketika Anda mengoreksi jenis transaksi, kategori, dompet, atau pola. Koreksi manual diberi bobot lebih tinggi dan pola salah sebelumnya dikurangi bobotnya. Nominal, tanggal, plat kendaraan, dan nomor rekening tidak dijadikan pola hafalan.',
        ],
        [
            'id' => 'faq-9',
            'category' => 'asisten',
            'question' => 'Bagaimana tahu koreksi saya sudah dipelajari?',
            'keywords' => 'koreksi sudah dipelajari indikator belajar pola berikutnya',
            'answer_html' => 'Setelah Anda memperbaiki draft lalu menyimpannya, Asisten dapat menampilkan pesan seperti <b>“🧠 Koreksi ini sudah saya pelajari”</b>. Pola serupa berikutnya akan diprioritaskan mengikuti koreksi tersebut.',
        ],
        [
            'id' => 'faq-10',
            'category' => 'asisten',
            'question' => 'Bisakah Asisten menghitung skenario “cukup sampai gajian”?',
            'keywords' => 'cukup sampai gajian bbm makan per hari simulasi skenario aman tidak humanoid',
            'answer_html' => 'Bisa. Contoh: <b>“Kalau per hari BBM 20rb dan makan 15–20rb sampai gajian, cukup nggak?”</b>. Asisten akan memakai saldo tersedia, jumlah hari menuju gajian, kewajiban relevan, serta skenario harian yang Anda berikan untuk menghitung kebutuhan, perkiraan sisa, dan batas aman per hari.',
        ],
        [
            'id' => 'faq-11',
            'category' => 'asisten',
            'question' => 'Apakah Asisten memahami pertanyaan lanjutan yang pendek?',
            'keywords' => 'follow up konteks selain cicilan kecuali makan bukan bensin bulan lalu seabank',
            'answer_html' => 'Ya. Setelah pertanyaan seperti <b>“Pengeluaran terbesar?”</b>, Anda dapat melanjutkan dengan <b>“Selain cicilan”</b>, <b>“Kecuali makan”</b>, <b>“Kalau di Seabank?”</b>, atau <b>“Bulan lalu?”</b>. Asisten akan memakai konteks pertanyaan sebelumnya, bukan langsung menganggapnya sebagai transaksi baru.',
        ],
        [
            'id' => 'faq-12',
            'category' => 'asisten',
            'question' => 'Bagaimana Asisten membedakan nominal dari plat atau kode?',
            'keywords' => 'plat kendaraan kode nomor rekening nominal rb k juta parsing bbm pertamax',
            'answer_html' => 'Parser memprioritaskan nominal yang bertanda seperti <b>Rp</b>, <b>rb</b>, <b>ribu</b>, <b>k</b>, <b>jt</b>, atau <b>juta</b>. Angka yang terlihat seperti plat kendaraan, tanggal, jam, nomor rekening, referensi, atau kode akan dihindari sebagai nominal bila konteksnya mendukung.',
        ],
        [
            'id' => 'faq-13',
            'category' => 'saldo',
            'question' => 'Apa beda Saldo Tersedia dan Dana Disisihkan?',
            'keywords' => 'saldo tersedia dana disisihkan tabungan khusus aman gajian',
            'answer_html' => '<b>Saldo Tersedia</b> adalah uang yang dianggap dapat digunakan untuk kebutuhan sehari-hari. <b>Dana Disisihkan</b> tetap bagian dari total uang Anda, tetapi tidak dianggap sebagai uang belanja pada prediksi <b>aman sampai gajian</b>.',
        ],
        [
            'id' => 'faq-14',
            'category' => 'saldo',
            'question' => 'Apa itu Saldo Minimum Dompet?',
            'keywords' => 'saldo minimum bank rekening tidak minus mengendap minimum balance autodebit admin bank',
            'answer_html' => '<b>Saldo Minimum Dompet</b> adalah batas yang tidak dianggap sebagai uang belanja. Perhitungan dilakukan <b>per dompet</b>: saldo aktual dikurangi dana disisihkan dan saldo minimum, dengan nilai minimum Rp0. Jika saldo aktual berada di bawah batas minimum, kontribusi dompet tersebut ke Saldo Tersedia menjadi Rp0 dan <b>tidak membuat total menjadi minus</b>. Saldo aktualnya tetap tercatat untuk kebutuhan seperti admin bank atau autodebit.',
        ],
        [
            'id' => 'faq-15',
            'category' => 'saldo',
            'question' => 'Bagaimana mengubah saldo awal?',
            'keywords' => 'saldo awal ubah dompet rekening atur saldo',
            'answer_html' => 'Buka menu <b>Atur saldo awal</b>. Semua dompet aktif akan ditampilkan sehingga saldo awal, dana yang disisihkan, dan saldo minimum dapat diperbarui per dompet.',
        ],
        [
            'id' => 'faq-16',
            'category' => 'saldo',
            'question' => 'Bagaimana menambah dompet atau rekening?',
            'keywords' => 'dompet rekening cash ewallet bank tambah wallet transfer',
            'answer_html' => 'Buka <b>Dompet &amp; Rekening</b>, kemudian tambahkan sumber dana seperti cash, rekening bank, atau e-wallet. Saat mencatat transaksi, pilih dompet yang benar agar saldo tiap sumber dana tetap akurat.',
        ],
        [
            'id' => 'faq-17',
            'category' => 'saldo',
            'question' => 'Bagaimana menyembunyikan nominal saldo?',
            'keywords' => 'sembunyikan saldo privasi pemasukan pengeluaran saldo awal mata',
            'answer_html' => 'Gunakan tombol ikon mata di kartu saldo. Saat nominal disembunyikan, tampilan saldo, pemasukan, pengeluaran, dan saldo awal ikut disamarkan.',
        ],
        [
            'id' => 'faq-18',
            'category' => 'saldo',
            'question' => 'Bagaimana prediksi “aman sampai gajian” dihitung?',
            'keywords' => 'aman sampai gajian prediksi analitik cukup tidak cukup harian',
            'answer_html' => 'Prediksi memakai saldo yang benar-benar tersedia untuk dibelanjakan, jumlah hari menuju gajian, pola pengeluaran harian, serta kewajiban yang relevan. Dana disisihkan dan saldo minimum tidak dianggap uang belanja, sedangkan transaksi sekali bayar tidak otomatis dianggap pengeluaran harian berulang.',
        ],
        [
            'id' => 'faq-19',
            'category' => 'tagihan',
            'question' => 'Bagaimana menghubungkan pembayaran ke cicilan atau tagihan?',
            'keywords' => 'cicilan tagihan transaksi hubungkan lunas bayar link bill',
            'answer_html' => 'Saat mengonfirmasi transaksi pengeluaran, pilih tagihan yang sesuai pada bagian keterkaitan tagihan. Setelah transaksi tersimpan, tagihan terkait dapat otomatis diperbarui menjadi lunas sehingga pembayaran tidak tercatat dua kali.',
        ],
        [
            'id' => 'faq-20',
            'category' => 'tagihan',
            'question' => 'Apa yang terjadi jika transaksi pembayaran tagihan dihapus?',
            'keywords' => 'hapus pembayaran cicilan status belum lunas undo',
            'answer_html' => 'Jika transaksi tersebut terhubung ke tagihan, penghapusan akan mengoreksi status tagihan. Tagihan dapat kembali menjadi belum lunas. Jika penghapusan dibatalkan melalui Undo, hubungan dan status tagihan ikut dipulihkan.',
        ],
        [
            'id' => 'faq-21',
            'category' => 'tagihan',
            'question' => 'Kapan sebaiknya memakai Transaksi Berulang?',
            'keywords' => 'recurring transaksi berulang otomatis jadwal bulanan mingguan pemasukan',
            'answer_html' => 'Gunakan untuk transaksi yang memang terjadi menurut jadwal, misalnya cicilan bulanan, langganan, atau pemasukan rutin. Hindari menandai pembelian satu kali sebagai berulang karena dapat membuat proyeksi menjadi tidak akurat.',
        ],
        [
            'id' => 'faq-22',
            'category' => 'premium',
            'question' => 'Bagaimana Free Trial Premium 7 hari bekerja?',
            'keywords' => 'free trial 7 hari coba premium konfirmasi sekali akun tidak otomatis tagih',
            'answer_html' => 'Free Trial <b>tidak aktif otomatis</b>. User Free harus membuka menu <b>Premium</b>, menekan <b>Coba Premium 7 Hari</b>, lalu menyetujui konfirmasi. Trial hanya dapat digunakan satu kali per akun dan tidak melakukan penagihan otomatis. Setelah 7 hari, fitur Premium kembali terkunci jika belum ada paket berbayar aktif.',
        ],
        [
            'id' => 'faq-23',
            'category' => 'premium',
            'question' => 'Bagaimana membeli Premium?',
            'keywords' => 'beli premium invoice transfer bukti bayar verifikasi admin paket',
            'answer_html' => 'Buka menu <b>Premium</b>, pilih paket, pilih rekening pembayaran, isi data pengirim, lalu buat invoice. Lakukan transfer sesuai total invoice dan unggah bukti pembayaran. Premium aktif setelah pembayaran berhasil diverifikasi.',
        ],
        [
            'id' => 'faq-24',
            'category' => 'premium',
            'question' => 'Apakah perubahan harga paket mengubah invoice yang sudah dibuat?',
            'keywords' => 'harga paket berubah invoice lama baru subscription plan snapshot',
            'answer_html' => 'Tidak. Invoice menyimpan harga dan durasi saat invoice dibuat. Jika harga paket berubah, perubahan hanya berlaku untuk <b>invoice baru</b>; invoice lama tetap memakai nilai sebelumnya.',
        ],
        [
            'id' => 'faq-25',
            'category' => 'premium',
            'question' => 'Kalau membeli Premium saat trial masih aktif, apakah sisa trial hilang?',
            'keywords' => 'trial beli sebelum habis sisa hari tidak hangus premium berbayar mulai akhir trial',
            'answer_html' => 'Tidak. Jika pembelian Premium berbayar dilakukan saat trial masih aktif, masa paket berbayar dihitung mulai dari akhir masa trial sehingga sisa trial tidak hangus.',
        ],
        [
            'id' => 'faq-26',
            'category' => 'premium',
            'question' => 'Fitur apa saja yang membutuhkan Premium?',
            'keywords' => 'fitur premium dompet budget tagihan recurring target analitik backup laporan export',
            'answer_html' => 'Status paket menentukan akses ke fitur lanjutan seperti multi-dompet, Budget Bulanan, Tagihan &amp; Cicilan, Transaksi Berulang, Target Menabung, Analitik &amp; Prediksi, serta fitur Premium lain yang ditandai di aplikasi. Daftar yang berlaku ditampilkan langsung pada menu <b>Premium</b>.',
        ],
        [
            'id' => 'faq-27',
            'category' => 'sinkronisasi',
            'question' => 'Apakah aplikasi bisa dipakai saat offline?',
            'keywords' => 'offline internet tanpa koneksi pwa transaksi antre loading memuat data',
            'answer_html' => 'Bisa, setelah aplikasi/PWA pernah dimuat pada perangkat. Perubahan yang didukung mode offline akan masuk antrean lokal dan dicoba dikirim kembali ketika koneksi internet tersedia. Saat data awal belum selesai ditarik, aplikasi menampilkan status <b>Memuat data</b> dan menyediakan <b>Coba Lagi</b> jika proses gagal.',
        ],
        [
            'id' => 'faq-28',
            'category' => 'sinkronisasi',
            'question' => 'Apa arti status Antre, Gagal, dan Konflik?',
            'keywords' => 'antre gagal konflik status sinkronisasi 409 perangkat lain',
            'answer_html' => '<b>Antre</b> berarti data menunggu dikirim ke server. <b>Gagal</b> berarti proses sinkronisasi mengalami error dan perlu dicoba kembali. <b>Konflik</b> berarti data yang sama telah berubah di perangkat/server lain sehingga aplikasi tidak langsung menimpa versi yang lebih baru.',
        ],
        [
            'id' => 'faq-29',
            'category' => 'sinkronisasi',
            'question' => 'Bagaimana memaksa sinkronisasi ulang?',
            'keywords' => 'sinkronkan sekarang refresh reload koneksi online',
            'answer_html' => 'Saat ada data offline yang masih mengantre, gunakan tombol <b>Sinkronkan</b> pada status koneksi jika tersedia. Pastikan perangkat terhubung internet. Tombol refresh di dekat kontrol saldo juga dapat digunakan untuk menarik ulang data terbaru.',
        ],
        [
            'id' => 'faq-30',
            'category' => 'sinkronisasi',
            'question' => 'Apa saja yang ikut dalam backup aplikasi?',
            'keywords' => 'backup restore adaptive learning ai data pindah perangkat version 5',
            'answer_html' => 'Backup menyimpan data keuangan dan konfigurasi yang didukung, termasuk data Adaptive Learning dan Draf Transaksi pada format backup terbaru. Gunakan menu <b>Backup &amp; Aplikasi</b> dan lakukan backup terutama sebelum mengganti perangkat atau melakukan perubahan besar.',
        ],
        [
            'id' => 'faq-31',
            'category' => 'akun',
            'question' => 'Saya lupa password atau PIN, apa yang harus dilakukan?',
            'keywords' => 'lupa password pin email token pemulihan reset',
            'answer_html' => 'Gunakan fitur pemulihan pada halaman login. Token pemulihan akan dikirim ke email yang tersimpan. Karena itu, pastikan email pada menu <b>Email &amp; Keamanan</b> sudah benar dan terverifikasi.',
        ],
        [
            'id' => 'faq-32',
            'category' => 'akun',
            'question' => 'Mengapa email perlu diverifikasi?',
            'keywords' => 'email verifikasi keamanan perangkat logout device',
            'answer_html' => 'Email terverifikasi digunakan untuk membantu pemulihan password/PIN dan meningkatkan keamanan akun. Pada menu <b>Email &amp; Keamanan</b> Anda juga dapat meninjau perangkat yang masuk dan mengeluarkan perangkat lain bila diperlukan.',
        ],
        [
            'id' => 'faq-33',
            'category' => 'akun',
            'question' => 'Bagaimana kunci biometrik bekerja di Android/iOS?',
            'keywords' => 'biometrik fingerprint sidik jari face id android ios pin ganda native web unlock',
            'answer_html' => 'Pada aplikasi native Android/iOS, aktifkan <b>Kunci Biometrik Perangkat</b> melalui <b>Email &amp; Keamanan</b>. Setelah biometrik native berhasil, web langsung dibuka tanpa meminta PIN aplikasi untuk kedua kalinya. Jika biometrik tidak tersedia atau Anda memilih <b>Gunakan PIN aplikasi</b>, PIN tetap menjadi fallback. Aplikasi tidak menyimpan data sidik jari atau wajah.',
        ],
        [
            'id' => 'faq-34',
            'category' => 'akun',
            'question' => 'Apakah Catatan Keuangan bisa digunakan di Android dan iPhone?',
            'keywords' => 'android ios iphone webview aplikasi native download browser pwa',
            'answer_html' => 'Ya. Aplikasi dapat digunakan melalui browser/PWA dan juga melalui aplikasi native WebView Android/iOS yang terhubung ke server serta akun yang sama. Fitur perangkat seperti biometrik, kamera/galeri, dan download native tersedia sesuai kemampuan aplikasi perangkat.',
        ],
        [
            'id' => 'faq-35',
            'category' => 'akun',
            'question' => 'Apakah notifikasi aplikasi bisa dikirim ke email?',
            'keywords' => 'email notifikasi pemberitahuan login keamanan broadcast pembaruan android tagihan saldo batas harian',
            'answer_html' => 'Bisa. Aktifkan <b>Kirim notifikasi juga ke email terverifikasi</b> pada menu <b>Backup &amp; Aplikasi → Notifikasi</b>. Peringatan batas harian, tagihan jatuh tempo, saldo rendah, serta pemberitahuan penting dari aplikasi dapat dikirim ke email. Riwayat login baru, broadcast admin, dan peringatan aplikasi juga tersimpan di menu <b>Pemberitahuan</b>. Sistem melakukan deduplikasi agar peringatan yang sama tidak dibuat berulang pada hari yang sama.',
        ],
        [
            'id' => 'faq-36',
            'category' => 'akun',
            'question' => 'Bagaimana menghapus akun?',
            'keywords' => 'hapus akun permanen data privasi password pin',
            'answer_html' => 'Gunakan menu <b>Hapus Akun</b>, lakukan verifikasi dengan password atau PIN, lalu konfirmasi penghapusan. Penghapusan bersifat permanen dan mencakup data akun serta data terkait di server sesuai penjelasan pada halaman tersebut.',
        ],
        [
            'id' => 'faq-37',
            'category' => 'transaksi',
            'question' => 'Apa itu Catat Cepat dan Draf Transaksi?',
            'keywords' => 'catat cepat quick add draf transaksi draft transaksi transaction inbox lupa transaksi rapikan nanti',
            'answer_html' => '<b>Catat Cepat</b> dipakai saat Anda tidak sempat melengkapi transaksi. Isi nominal, jenis, dan keterangan singkat lalu simpan ke <b>Draf Transaksi</b>. Saldo belum berubah sampai draf dikonfirmasi. Saat punya waktu, buka Draf Transaksi untuk menerima saran kategori/dompet, mengedit detail, lalu menyimpannya sebagai transaksi final.',
        ],
        [
            'id' => 'faq-38',
            'category' => 'transaksi',
            'question' => 'Bagaimana aplikasi membantu jika saya lupa mencatat transaksi?',
            'keywords' => 'lupa mencatat transaksi rekonsiliasi harian sudah lengkap transaksi hari ini pengingat',
            'answer_html' => 'Setiap malam mulai sekitar <b>19.00</b>, fitur <b>Rekonsiliasi Harian</b> dapat menampilkan ringkasan jumlah transaksi, pemasukan, pengeluaran, serta draf transaksi yang belum dirapikan. Pilih <b>+ Yang terlupa</b> untuk mencatat cepat, <b>Rapikan Draf Transaksi</b> untuk melengkapi draf, atau <b>Sudah lengkap</b> jika semua transaksi hari itu sudah tercatat.',
        ],
        [
            'id' => 'faq-39',
            'category' => 'sinkronisasi',
            'question' => 'Apakah Draf Transaksi ikut dalam backup?',
            'keywords' => 'backup transaction inbox catat cepat draft backup versi 5 restore',
            'answer_html' => 'Ya. Format backup terbaru ikut menyimpan <b>Draf Transaksi</b> beserta data Adaptive Learning. Backup versi lama tetap dapat direstore; jika field Draf Transaksi tidak ada, data draf yang sudah ada tidak perlu dibuat ulang dari transaksi lama.',
        ],
    ];
}

function faqPlainText(string $html): string {
    $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace('/\s+([,.!?;:])/u', '$1', $text);
    $text = preg_replace('/\s+/u', ' ', $text);
    return trim((string)$text);
}

function faqNormalize(string $text): string {
    $text = strtolower(trim($text));
    $text = str_replace(['–','—','“','”','‘','’'], ['-','-',' ',' ',' ',' '], $text);
    $aliases = [
        'gimana' => 'bagaimana', 'gmna' => 'bagaimana', 'gmn' => 'bagaimana',
        'biometric' => 'biometrik', 'fingerprint' => 'sidik jari', 'faceid' => 'face id',
        'iphone' => 'ios iphone', 'hp' => 'perangkat', 'app' => 'aplikasi',
        'subs' => 'premium', 'langganan' => 'premium langganan',
        'trialnya' => 'trial', 'backupnya' => 'backup', 'saldo minimum nya' => 'saldo minimum',
    ];
    foreach ($aliases as $from => $to) {
        $text = preg_replace('/(?<![a-z0-9])'.preg_quote($from, '/').'(?![a-z0-9])/u', $to, $text);
    }
    $text = preg_replace('/[^a-z0-9]+/u', ' ', $text);
    return trim((string)preg_replace('/\s+/u', ' ', $text));
}

function faqTokens(string $text): array {
    $stop = [
        'apa','apakah','bagaimana','cara','gimana','bisakah','bisa','boleh','kenapa','mengapa','kok',
        'saya','aku','kita','yang','itu','ini','di','ke','dari','dan','atau','untuk','dengan','pada',
        'mau','ingin','harus','perlu','jadi','dong','ya','kah','nya','sebuah','tentang','soal','fitur'
    ];
    $tokens = preg_split('/\s+/u', faqNormalize($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $out = [];
    foreach ($tokens as $token) {
        if (strlen($token) < 2 || in_array($token, $stop, true)) continue;
        $out[$token] = true;
    }
    return array_keys($out);
}

function faqLooksLikeFeatureQuestion(string $message): bool {
    $t = faqNormalize($message);
    if ($t === '') return false;
    if (preg_match('/\b(?:super admin|admin dashboard|broadcast|migrasi mysql|subscription plan|kupon premium|rekening pembayaran|notifikasi admin|akun pengguna)\b/u', $t)) return false;
    if (preg_match('/\b(?:ubah|atur|edit|kelola|setting)\s+(?:harga\s+)?(?:paket|premium|subscription)\b/u', $t)) return false;
    if (strpos($message, '?') !== false) return true;
    if (preg_match('/^(?:apa|apakah|bagaimana|gimana|cara|kenapa|mengapa|bisa|bisakah|berapa|dimana|kapan)\b/u', $t)) return true;
    return (bool)preg_match('/\b(?:free trial|trial premium|saldo minimum|dana disisihkan|adaptive learning|belajar dari koreksi|tambah transaksi manual|transaksi manual|catat cepat|draf transaksi|transaction inbox|rekonsiliasi harian|lupa mencatat transaksi|nota foto|scan nota|offline|sinkronisasi|backup|biometrik|face id|sidik jari|hapus akun|lupa pin|lupa password|notifikasi email|pemberitahuan)\b/u', $t);
}

function faqFindBestMatch(string $message): ?array {
    if (!faqLooksLikeFeatureQuestion($message)) return null;
    $queryNorm = faqNormalize($message);
    $queryTokens = faqTokens($message);
    if (!$queryTokens) return null;

    $ranked = [];
    foreach (faqKnowledgeBase() as $entry) {
        $questionNorm = faqNormalize((string)$entry['question']);
        $keywordNorm = faqNormalize((string)$entry['keywords']);
        $answerNorm = faqNormalize(faqPlainText((string)$entry['answer_html']));
        $primary = ' '.$questionNorm.' '.$keywordNorm.' ';
        $all = $primary.' '.$answerNorm.' ';
        $matches = 0;
        $score = 0.0;
        foreach ($queryTokens as $token) {
            if (strpos($primary, ' '.$token.' ') !== false) {
                $matches++; $score += 3.0;
            } elseif (strpos($all, ' '.$token.' ') !== false) {
                $matches++; $score += 1.25;
            }
        }
        $coverage = $matches / max(1, count($queryTokens));
        if ($queryNorm === $questionNorm) $score += 8.0;
        if (strpos($questionNorm, $queryNorm) !== false || strpos($queryNorm, $questionNorm) !== false) $score += 4.0;

        $words = array_values($queryTokens);
        for ($i = 0; $i + 1 < count($words); $i++) {
            $phrase = $words[$i].' '.$words[$i+1];
            if (strpos($primary, $phrase) !== false) $score += 2.5;
        }
        $score += $coverage * 3.0;
        if ($matches > 0) $ranked[] = ['score'=>$score, 'coverage'=>$coverage, 'matches'=>$matches, 'entry'=>$entry];
    }
    if (!$ranked) return null;
    usort($ranked, static fn($a,$b) => $b['score'] <=> $a['score']);
    $best = $ranked[0];
    $second = $ranked[1] ?? null;

    $neededCoverage = count($queryTokens) <= 2 ? 0.50 : 0.45;
    if ($best['coverage'] < $neededCoverage || $best['score'] < 5.0) return null;
    if ($second && ($best['score'] - $second['score']) < 1.0 && $best['coverage'] < 0.80) return null;
    return $best;
}

function faqAssistantReply(string $message): ?string {
    $match = faqFindBestMatch($message);
    if (!$match) return null;
    $entry = $match['entry'];
    return faqPlainText((string)$entry['answer_html']);
}
