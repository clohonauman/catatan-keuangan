<?php
/**
 * Optimasi gambar saat disimpan ke storage.
 * Browser tetap melakukan kompresi utama sebelum upload. Helper ini adalah
 * lapisan pengaman untuk request lama/perangkat yang gagal melakukan kompresi.
 */

function imageStorageAllowedMime(string $mime): bool {
    return in_array(strtolower($mime), ['image/jpeg', 'image/png', 'image/webp'], true);
}

function imageStorageDetectMime(string $path): string {
    $mime = '';
    if (function_exists('finfo_open')) {
        $f = @finfo_open(FILEINFO_MIME_TYPE);
        if ($f) {
            $mime = strtolower((string)@finfo_file($f, $path));
            @finfo_close($f);
        }
    }
    if (($mime === '' || !imageStorageAllowedMime($mime)) && function_exists('getimagesize')) {
        $info = @getimagesize($path);
        if (is_array($info) && !empty($info['mime'])) $mime = strtolower((string)$info['mime']);
    }
    return $mime;
}

function imageStorageSourceResource(string $path, string $mime) {
    if ($mime === 'image/jpeg' && function_exists('imagecreatefromjpeg')) return @imagecreatefromjpeg($path);
    if ($mime === 'image/png' && function_exists('imagecreatefrompng')) return @imagecreatefrompng($path);
    if ($mime === 'image/webp' && function_exists('imagecreatefromwebp')) return @imagecreatefromwebp($path);
    return false;
}

/**
 * Simpan upload gambar dengan optimasi opsional memakai GD.
 *
 * Return: path, file, mime, size, width, height, original_size, optimized.
 */
function imageStorageSaveUpload(
    string $tmpPath,
    string $targetDir,
    string $baseName,
    array $options = []
): array {
    if (!is_file($tmpPath)) throw new RuntimeException('File gambar sementara tidak ditemukan.');
    if (!is_dir($targetDir) && !@mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
        throw new RuntimeException('Folder penyimpanan gambar tidak dapat dibuat.');
    }

    $info = @getimagesize($tmpPath);
    $mime = strtolower((string)($info['mime'] ?? imageStorageDetectMime($tmpPath)));
    if (!$info || !imageStorageAllowedMime($mime)) {
        throw new RuntimeException('Format gambar harus JPG, PNG, atau WebP.');
    }

    $originalSize = max(0, (int)@filesize($tmpPath));
    $originalWidth = (int)($info[0] ?? 0);
    $originalHeight = (int)($info[1] ?? 0);
    $maxSide = max(800, (int)($options['max_side'] ?? 1600));
    $quality = max(60, min(88, (int)($options['quality'] ?? 78)));
    $thresholdBytes = max(250 * 1024, (int)($options['threshold_bytes'] ?? 750 * 1024));
    $allowCliCopy = !empty($options['allow_cli_copy']);

    $needsResize = max($originalWidth, $originalHeight) > $maxSide;
    $needsCompression = $originalSize > $thresholdBytes || $needsResize || ($mime === 'image/png' && $originalSize > 450 * 1024);
    $canGd = function_exists('imagecreatetruecolor') && function_exists('imagecopyresampled') && function_exists('imagejpeg');

    if ($needsCompression && $canGd) {
        $src = imageStorageSourceResource($tmpPath, $mime);
        if ($src) {
            $scale = min(1, $maxSide / max(1, max($originalWidth, $originalHeight)));
            $width = max(1, (int)round($originalWidth * $scale));
            $height = max(1, (int)round($originalHeight * $scale));
            $dst = @imagecreatetruecolor($width, $height);
            if ($dst) {
                $white = @imagecolorallocate($dst, 255, 255, 255);
                if ($white !== false) @imagefill($dst, 0, 0, $white);
                @imagecopyresampled($dst, $src, 0, 0, 0, 0, $width, $height, $originalWidth, $originalHeight);
                $jpgPath = rtrim($targetDir, '/\\').'/'.$baseName.'.jpg';
                $ok = @imagejpeg($dst, $jpgPath, $quality);
                @imagedestroy($dst);
                @imagedestroy($src);

                if ($ok && is_file($jpgPath)) {
                    $savedSize = max(0, (int)@filesize($jpgPath));
                    // Gunakan hasil optimasi bila memang lebih kecil, atau bila resize diperlukan.
                    if ($savedSize > 0 && ($savedSize < $originalSize || $needsResize)) {
                        @chmod($jpgPath, 0644);
                        return [
                            'path'=>$jpgPath,
                            'file'=>basename($jpgPath),
                            'mime'=>'image/jpeg',
                            'size'=>$savedSize,
                            'width'=>$width,
                            'height'=>$height,
                            'original_size'=>$originalSize,
                            'optimized'=>true,
                        ];
                    }
                    @unlink($jpgPath);
                }
            } else {
                @imagedestroy($src);
            }
        }
    }

    $extMap = ['image/jpeg'=>'jpg', 'image/png'=>'png', 'image/webp'=>'webp'];
    $dest = rtrim($targetDir, '/\\').'/'.$baseName.'.'.$extMap[$mime];
    $moved = false;
    if (PHP_SAPI === 'cli' && $allowCliCopy) $moved = @copy($tmpPath, $dest);
    else $moved = @move_uploaded_file($tmpPath, $dest);
    if (!$moved) throw new RuntimeException('Gambar tidak dapat disimpan. Periksa permission folder storage.');
    @chmod($dest, 0644);

    return [
        'path'=>$dest,
        'file'=>basename($dest),
        'mime'=>$mime,
        'size'=>max(0, (int)@filesize($dest)),
        'width'=>$originalWidth,
        'height'=>$originalHeight,
        'original_size'=>$originalSize,
        'optimized'=>false,
    ];
}
