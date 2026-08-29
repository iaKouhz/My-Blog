<?php
/**
 * 后台图片上传：fileinfo 真实 MIME 校验 + 白名单 + 内容哈希命名（秒传去重）
 * imageAction 能力点 upload（文章配图/封面）；avatarAction 能力点 edit_profile（个人头像，全体登录用户可用）
 * 上传目录 uploads/年/月/，禁止 PHP 执行（见 uploads/.htaccess 与 nginx 规则）
 */
defined('APP_BOOT') or exit;

class AdminUpload
{
    /** MIME → 扩展名白名单 */
    private static $mimeMap = array(
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
    );

    /** 图片上传接口（JSON 响应，供 Vditor 与封面上传调用） */
    public static function imageAction()
    {
        Auth::require_cap('upload');
        $relative = self::saveImage(2 * 1024 * 1024, admin_t('admin.upload.image_size'));
        blog_log('post', 'upload.image', 'success', array('path' => $relative));
        // 同时返回相对路径：文章封面表单只接受 uploads/ 开头的相对路径（与头像接口一致）
        json_out(array('code' => 0, 'msg' => 'ok', 'data' => array(
            'url'  => Router::base() . '/' . $relative,
            'path' => $relative,
        )));
    }

    /** 头像上传接口（个人资料页调用，全体登录用户可用，上限 1MB） */
    public static function avatarAction()
    {
        Auth::require_cap('edit_profile');
        $relative = self::saveImage(1024 * 1024, admin_t('admin.upload.avatar_size'));
        blog_log('user', 'upload.avatar', 'success', array('path' => $relative));
        // 同时返回相对路径：个人资料表只接受 uploads/ 开头的相对路径
        json_out(array('code' => 0, 'msg' => 'ok', 'data' => array(
            'url' => Router::base() . '/' . $relative,
            'path' => $relative,
        )));
    }

    /**
     * 统一落盘逻辑：校验上传文件 → fileinfo 真实 MIME 白名单 → 内容哈希命名存入 uploads/年/月/
     * （md5_file 命名实现秒传去重：内容相同的图片命中已存在文件即跳过落盘，为既定 feature）
     *
     * @param int    $maxBytes 大小上限（字节）
     * @param string $sizeMsg  超限提示
     * @return string uploads/ 下相对路径
     */
    private static function saveImage($maxBytes, $sizeMsg)
    {
        if (empty($_FILES['file'])) {
            json_out(array('code' => 1, 'msg' => admin_t('admin.upload.no_file')));
        }
        $file = $_FILES['file'];
        if ((int) $file['error'] !== UPLOAD_ERR_OK) {
            json_out(array('code' => 1, 'msg' => admin_t('admin.upload.error_code', array((int) $file['error']))));
        }
        if ((int) $file['size'] > $maxBytes || (int) $file['size'] <= 0) {
            json_out(array('code' => 1, 'msg' => $sizeMsg));
        }
        // fileinfo 检测真实 MIME（不信任客户端声明的扩展名/类型）
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        if (!isset(self::$mimeMap[$mime])) {
            json_out(array('code' => 1, 'msg' => admin_t('admin.upload.mime_whitelist')));
        }
        // 内容级校验：MIME 可被伪造文件头欺骗，getimagesize 解析图片结构确保是真实图片
        if (getimagesize($file['tmp_name']) === false) {
            json_out(array('code' => 1, 'msg' => '无效的图片文件'));
        }

        $subDir = date('Y/m');
        $targetDir = APP_ROOT . '/uploads/' . $subDir;
        if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true)) {
            json_out(array('code' => 1, 'msg' => admin_t('admin.upload.dir_failed')));
        }
        // 用文件内容 md5 命名：内容相同的图片得到相同文件名，命中已存在文件则跳过落盘（秒传去重）
        $dest = $targetDir . '/' . md5_file($file['tmp_name']) . '.' . self::$mimeMap[$mime];
        if (!is_file($dest)) {
            if (!move_uploaded_file($file['tmp_name'], $dest)) {
                json_out(array('code' => 1, 'msg' => admin_t('admin.upload.save_failed')));
            }
        }
        return 'uploads/' . $subDir . '/' . basename($dest);
    }
}
