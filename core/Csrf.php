<?php
/**
 * CSRF 防护：token 与 Session 及 config.php 密钥绑定，所有改状态请求必须校验
 */
defined('APP_BOOT') or exit;

class Csrf
{
    /**
     * 获取当前会话的 CSRF token（不存在则生成）
     *
     * @return string
     */
    public static function token()
    {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = hash_hmac(
                'sha256',
                bin2hex(random_bytes(16)) . '|' . session_id(),
                (string) Config::get('key')
            );
        }
        return $_SESSION['_csrf'];
    }

    /**
     * 输出隐藏表单域
     *
     * @return string HTML
     */
    public static function field()
    {
        return '<input type="hidden" name="_csrf" value="' . e(self::token()) . '">';
    }

    /**
     * 校验 token（默认取 POST 中的 _csrf）
     *
     * @param string|null $token 待校验 token
     * @return bool
     */
    public static function check($token = null)
    {
        if ($token === null) {
            $token = isset($_POST['_csrf']) ? (string) $_POST['_csrf'] : '';
        }
        if (!is_string($token) || $token === '' || empty($_SESSION['_csrf'])) {
            return false;
        }
        return hash_equals($_SESSION['_csrf'], $token);
    }

    /**
     * 校验失败直接终止请求（用于状态变更入口）
     *
     * @return void
     */
    public static function verifyOrDie()
    {
        if (!self::check()) {
            http_response_code(419);
            exit('CSRF token check failed');
        }
    }

    /**
     * 一次性 CSRF 校验（用于高危操作：改密、改绑、用户管理）
     * 校验后立即作废 token，下次渲染需重新生成，防止 token 会话级复用导致重放
     *
     * @return void
     */
    public static function verifyOnceOrDie()
    {
        $token = isset($_POST['_csrf']) ? (string) $_POST['_csrf'] : '';
        $ok = self::check($token);
        unset($_SESSION['_csrf']); // 无论成败都作废，下次渲染重新生成
        if (!$ok) {
            http_response_code(419);
            exit('CSRF token check failed');
        }
    }
}
