<?php
/**
 * 内核引导：加载 config、DB、Session、Hook、Logger、插件；统一安全头与错误处理
 */

define('APP_BOOT', true);
define('APP_ROOT', dirname(__DIR__));
define('APP_VERSION', '1.0.0');

// 调试模式仅从环境变量 APP_DEBUG 或 config.php 中的 DEBUG 常量读取，禁止数据库 Option 控制，
// 防止数据库被篡改（如后台配置项被注入）后远程开启调试模式、借错误输出泄露路径/SQL 等敏感信息
define('APP_DEBUG', getenv('APP_DEBUG') === '1' || (defined('DEBUG') && DEBUG === true));

// 立即关闭错误显示，防止引导早期故障（DB 连接失败等）在策略生效前泄露信息；
// 调试模式下由下方错误显示策略重新开启
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

require APP_ROOT . '/core/Config.php';
require APP_ROOT . '/core/Utils.php';
require APP_ROOT . '/core/DB.php';
require APP_ROOT . '/core/Hook.php';
require APP_ROOT . '/core/Option.php';
require APP_ROOT . '/core/Lang.php';
require APP_ROOT . '/core/Csrf.php';
require APP_ROOT . '/core/Router.php';
require APP_ROOT . '/core/Auth.php';
require APP_ROOT . '/core/Logger.php';
require APP_ROOT . '/core/Markdown.php';
require APP_ROOT . '/core/Plugin.php';
require APP_ROOT . '/core/Theme.php';

/**
 * 错误处理：debug=0 时禁止向页面输出路径/SQL/堆栈，仅写服务器错误日志
 */
function app_error_handler($errno, $errstr, $errfile, $errline)
{
    error_log(sprintf('[blog] %s at %s:%d', $errstr, $errfile, $errline));
    if (APP_DEBUG) {
        return false; // 调试模式交给默认输出
    }
    return true;
}

function app_exception_handler($ex)
{
    error_log('[blog] exception: ' . $ex->getMessage() . ' at ' . $ex->getFile() . ':' . $ex->getLine());
    if (!headers_sent()) {
        http_response_code(500);
    }
    if (APP_DEBUG) {
        echo '<pre>' . e($ex->getMessage()) . '</pre>';
    } else {
        echo '服务器内部错误，请稍后再试';
    }
    exit;
}

set_error_handler('app_error_handler');
set_exception_handler('app_exception_handler');

// 未安装（config.php 不存在）时跳转安装程序；安装程序自身不加载本引导
if (!Config::load(APP_ROOT . '/config.php')) {
    $dir = str_replace('\\', '/', dirname(isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '/'));
    // 从 user/、install/ 子目录入口进入时回退到站点根，避免拼出 /user/install/ 错误路径
    $dir = preg_replace('#/(user|install)$#', '', $dir);
    $base = ($dir === '/' || $dir === '.') ? '' : rtrim($dir, '/');
    // 相对路径 Location：不拼接 HTTP_HOST（Host 头可伪造，拼绝对地址会形成开放重定向）
    header('Location: ' . $base . '/install/');
    exit;
}

// 数据库连接
DB::init(array(
    'host'    => Config::get('db.host', '127.0.0.1'),
    'port'    => Config::get('db.port', 3306),
    'name'    => Config::get('db.name', ''),
    'user'    => Config::get('db.user', ''),
    'pass'    => Config::get('db.pass', ''),
    'prefix'  => Config::get('db.prefix', 'cb_'),
    'charset' => 'utf8mb4',
));

// 站点时区：默认 UTC+8（Asia/Shanghai），影响 now()/date_fmt()/日志等全部时间
// 非法值回退默认，避免 date() 报警告
$timezone = Option::get('timezone', 'Asia/Shanghai');
if (!in_array($timezone, timezone_identifiers_list(), true)) {
    $timezone = 'Asia/Shanghai';
}
date_default_timezone_set($timezone);

// 错误显示策略：display_errors/log_errors 已在文件顶部按"默认关闭"设置，
// 此处仅在 APP_DEBUG（环境变量/config.php，禁止数据库控制）开启时重新打开错误显示
if (APP_DEBUG) {
    ini_set('display_errors', '1');
}

// Session：HttpOnly + SameSite=Lax + HTTPS 下 Secure；空闲超时在 Auth::checkSessionTimeout 处理
$secure = is_https();
session_set_cookie_params(array(
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => $secure,
    'httponly' => true,
    'samesite' => 'Lax',
));
session_name('cb_sid');
session_start();

Auth::checkSessionTimeout();

// 安全响应头（内核统一输出）
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
// HSTS 仅在 HTTPS 下发送（HTTP 响应中的 HSTS 头会被浏览器忽略，
// 且对尚未启用 HTTPS 的部署发送会误导后续强制跳转预期）
if ($secure) {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}
// X-XSS-Protection 已被现代浏览器废弃（且历史上引入过新 XSS 面），移除；
// 改用 CSP 作为 XSS 纵深防御（前台文章页会输出更严格的 CSP 覆盖本头）
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:;");

// 加载已启用插件并触发 init
Plugin::loadActive();
do_action('init');

// 审计日志留存期惰性清理（每日最多一次）
Logger::purgeExpired();

// 插件临时缓存（plugin_data 带 expires_at 的行）惰性清理，每日最多一次
plugin_data_purge_expired();

// IP 限流计数器（options 表 throttle_* 行）惰性清理，每日最多一次
ip_throttle_purge();
