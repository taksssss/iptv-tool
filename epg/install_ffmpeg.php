<?php
/**
 * @file install_ffmpeg.php
 * @brief 网页端安装 ffmpeg/ffprobe 脚本
 *
 * 作者: Tak
 * GitHub: https://github.com/taksssss/iptv-tool
 */

session_start();
if (php_sapi_name() !== 'cli' && (empty($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true)) {
    http_response_code(403);
    exit('无访问权限，请先登录。');
}
session_write_close();

ob_implicit_flush(true);
@ob_end_flush();
header('X-Accel-Buffering: no');

echo '<link rel="icon" href="assets/img/favicon.ico" type="image/x-icon">';
echo '<title>安装 ffmpeg</title>';

require_once 'public.php';

$binDir = __DIR__ . '/data/scripts/bin';
@mkdir($binDir, 0755, true);

$ffmpegPath = $binDir . '/ffmpeg';
$ffprobePath = $binDir . '/ffprobe';

if (is_executable($ffmpegPath) && is_executable($ffprobePath)) {
    echo "检测到已安装 ffmpeg，无需重复安装。";
    exit;
}

function logInstallMessage($msg) {
    echo htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . "<br>";
    flush();
}

function detectFfmpegArch() {
    $machine = php_uname('m');
    if (in_array($machine, ['x86_64', 'amd64'], true)) return 'x64';
    if (in_array($machine, ['aarch64', 'arm64'], true)) return 'arm64';
    if (in_array($machine, ['armv7l', 'armv6l', 'armhf'], true)) return 'arm';
    return '';
}

function downloadBinary($url, $destination, $connectTimeout, $maxTime, $retries) {
    $attempts = max(1, $retries + 1);
    $errorMessage = '未知错误';

    for ($i = 1; $i <= $attempts; $i++) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => $connectTimeout,
            CURLOPT_TIMEOUT => $maxTime,
            CURLOPT_FAILONERROR => true,
            CURLOPT_USERAGENT => 'iptv-tool ffmpeg installer',
        ]);
        $content = curl_exec($ch);
        $errorMessage = curl_error($ch);
        curl_close($ch);

        if ($content !== false && strlen($content) > 0) {
            if (file_put_contents($destination, $content) !== false) {
                @chmod($destination, 0755);
                return true;
            }
            $errorMessage = '写入文件失败';
        }
    }

    return $errorMessage;
}

$arch = getenv('FFMPEG_ARCH_OVERRIDE') ?: detectFfmpegArch();
if ($arch === '') {
    exit('不支持当前架构：' . php_uname('m'));
}

$baseUrl = getenv('FFMPEG_BASE_URL') ?: 'https://github.com/eugeneware/ffmpeg-static/releases/latest/download';
$connectTimeout = (int)(getenv('FFMPEG_DOWNLOAD_TIMEOUT') ?: 15);
$maxTime = (int)(getenv('FFMPEG_DOWNLOAD_MAX_TIME') ?: 600);
$retries = (int)(getenv('FFMPEG_DOWNLOAD_RETRIES') ?: 3);

$ffmpegUrl = "{$baseUrl}/ffmpeg-linux-{$arch}";
$ffprobeUrl = "{$baseUrl}/ffprobe-linux-{$arch}";

logInstallMessage("开始安装 ffmpeg（架构：{$arch}）...");

logInstallMessage("下载 ffmpeg：{$ffmpegUrl}");
$ffmpegResult = downloadBinary($ffmpegUrl, $ffmpegPath, $connectTimeout, $maxTime, $retries);
if ($ffmpegResult !== true || !is_file($ffmpegPath) || filesize($ffmpegPath) <= 0) {
    @unlink($ffmpegPath);
    exit("下载 ffmpeg 失败：{$ffmpegResult}");
}

logInstallMessage("下载 ffprobe：{$ffprobeUrl}");
$ffprobeResult = downloadBinary($ffprobeUrl, $ffprobePath, $connectTimeout, $maxTime, $retries);
if ($ffprobeResult !== true || !is_file($ffprobePath) || filesize($ffprobePath) <= 0) {
    @unlink($ffprobePath);
    exit("下载 ffprobe 失败：{$ffprobeResult}");
}

$ffmpegCheck = [];
$ffmpegCode = 1;
exec(escapeshellarg($ffmpegPath) . ' -version 2>&1', $ffmpegCheck, $ffmpegCode);
if ($ffmpegCode !== 0) {
    @unlink($ffmpegPath);
    @unlink($ffprobePath);
    exit('ffmpeg 可执行校验失败，请重试。');
}

$ffprobeCheck = [];
$ffprobeCode = 1;
exec(escapeshellarg($ffprobePath) . ' -version 2>&1', $ffprobeCheck, $ffprobeCode);
if ($ffprobeCode !== 0) {
    @unlink($ffmpegPath);
    @unlink($ffprobePath);
    exit('ffprobe 可执行校验失败，请重试。');
}

logInstallMessage('ffmpeg 安装成功，可开始测速。');

