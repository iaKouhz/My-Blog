<?php
/**
 * zip 包安全解包助手：主题/插件上传共用的上传校验、条目白名单与解压落盘工具
 */
defined('APP_BOOT') or exit;

class ZipSafe
{
    /**
     * 校验 zip 上传请求的基本合法性（错误码/大小/扩展名/ZipArchive 探测）
     *
     * @param array $file     $_FILES 中的文件项
     * @param int   $maxBytes 程序侧大小上限
     * @return string 错误文案；空串表示通过
     */
    public static function uploadError(array $file, $maxBytes)
    {
        $err = isset($file['error']) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;
        if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
            return '文件超过服务器上传限制（约 ' . floor(self::uploadLimit() / 1048576) . 'MB）';
        }
        if ($err !== UPLOAD_ERR_OK) {
            return '请选择要上传的 zip 文件（错误码 ' . $err . '）';
        }
        if ((int) $file['size'] > $maxBytes) {
            return 'zip 文件不得超过 ' . floor($maxBytes / 1048576) . 'MB';
        }
        $name = strtolower((string) $file['name']);
        if (substr($name, -4) !== '.zip') {
            return '仅支持 .zip 格式';
        }
        if (!class_exists('ZipArchive')) {
            return '服务器未启用 ZipArchive 扩展，无法解压 zip 包';
        }
        return '';
    }

    /**
     * 打开 zip 并逐条目安全校验
     *
     * @param string         $path      zip 文件路径
     * @param string         $mustRegex 包内必须存在匹配该正则的条目（空串不检查）
     * @param ZipArchive|null $zip      输出：成功时为已打开的 zip 句柄
     * @return string 错误文案；空串表示通过
     */
    public static function openChecked($path, $mustRegex, &$zip)
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            $zip = null;
            return 'zip 文件无法打开';
        }
        $hasMust = $mustRegex === '';
        // Zip Bomb 防护：累计解压后体积（压缩包可很小，解压后可能巨大）
        $totalSize = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            // Zip Bomb 防护：限制条目数，防止海量条目耗尽内存/磁盘
            if ($i > 2000) {
                $zip->close();
                $zip = null;
                return 'zip 条目数超限（最多2000个）';
            }
            // Zip Bomb 防护：限制解压后总大小（以条目声明的解压尺寸累计）
            $st = $zip->statIndex($i);
            $totalSize += isset($st['size']) ? (int) $st['size'] : 0;
            if ($totalSize > 200 * 1024 * 1024) {
                $zip->close();
                $zip = null;
                return 'zip 解压后体积超限（最大200MB）';
            }
            $entry = $zip->getNameIndex($i);
            // 拒绝路径穿越/绝对路径/反斜杠；冒号条目（Windows 盘符如 C:/evil 或 ADS 流名）同样非法
            if (strpos($entry, '..') !== false || substr($entry, 0, 1) === '/'
                || strpos($entry, '\\') !== false || strpos($entry, ':') !== false) {
                $zip->close();
                $zip = null;
                return 'zip 包含非法路径条目';
            }
            // 条目白名单：拒绝隐藏文件（.htaccess/.user.ini 等，防宝塔下重写失效后被直接执行）
            // 与 phar/phtml/php3+ 等可执行伪装；注意不能拒 .php —— 主题模板与插件主文件本身就是 PHP，
            // 直接执行防护由重写规则（.htaccess/nginx.conf.example/bt-panel.rewrite.conf）承担
            $base = basename($entry);
            // 大小写不敏感匹配可执行伪装后缀（.PhAr/.PHTML/.PHP5 等大小写变体在某些环境同样可执行）；
            // 注意不能拒 .php —— 主题模板与插件主文件本身就是 PHP
            if ($base === '' || substr($base, 0, 1) === '.' || preg_match('/\.(phar|phtml?|php\d)$/i', $base)) {
                $zip->close();
                $zip = null;
                return 'zip 包含不允许的条目：' . $base;
            }
            // 拒绝符号链接等 Unix 特殊类型条目：extractTo 会忠实重建 symlink，
            // 后续普通条目可跟随链接落到目标目录之外（Zip Slip 变种）
            $opsys = 0;
            $extAttr = 0;
            if ($zip->getExternalAttributesIndex($i, $opsys, $extAttr) && $opsys === ZipArchive::OPSYS_UNIX) {
                // 外部属性高 16 位为 st_mode；0x8000=普通文件、0x4000=目录，其余（含 0xA000 符号链接）一律拒绝
                $entryType = ((int) $extAttr >> 16) & 0xF000;
                if ($entryType !== 0 && $entryType !== 0x8000 && $entryType !== 0x4000) {
                    $zip->close();
                    $zip = null;
                    return 'zip 包含不允许的条目类型（符号链接等）：' . $base;
                }
            }
            if ($mustRegex !== '' && preg_match($mustRegex, $entry)) {
                $hasMust = true;
            }
        }
        if (!$hasMust) {
            $zip->close();
            $zip = null;
            return 'zip 包缺少必需的文件';
        }
        return '';
    }

    /**
     * 解压到临时目录（与最终目录同盘以保证 rename 可用），并做套层扁平化
     * 覆盖更新必须先解压到临时目录完成校验，再替换就位，避免"旧目录已删、新包没解开"的窗口期
     *
     * @param ZipArchive $zip       已通过 openChecked 校验的 zip 句柄（本函数负责 close）
     * @param string     $tmpParent 临时目录的父目录
     * @param string     $tmp       输出：成功时为临时目录路径
     * @return string 错误文案；空串表示成功
     */
    public static function extractToTemp(ZipArchive $zip, $tmpParent, &$tmp)
    {
        $tmp = rtrim($tmpParent, '/') . '/.tmp-' . bin2hex(random_bytes(4));
        if (!mkdir($tmp, 0755, true) || !$zip->extractTo($tmp)) {
            $zip->close();
            if (is_dir($tmp)) {
                self::removeDir($tmp);
            }
            $tmp = '';
            return 'zip 解压失败';
        }
        $zip->close();
        // 兼容"包内再套一层目录"的打包方式：若解压结果仅含一个子目录则把内容上移
        self::flattenSingleSubdir($tmp);
        return '';
    }

    /**
     * 临时目录校验通过后替换最终目录：已存在时先 rename 旧目录为备份再就位，失败自动回滚
     *
     * @param string $tmp  校验通过的临时目录（成功后会被移走）
     * @param string $dest 最终目录
     * @return string 错误文案；空串表示成功
     */
    public static function swapIn($tmp, $dest)
    {
        if (!is_dir($dest)) {
            if (!rename($tmp, $dest)) {
                self::removeDir($tmp);
                return '目标目录创建失败';
            }
            return '';
        }
        $backup = dirname($dest) . '/.bak-' . bin2hex(random_bytes(4));
        if (!rename($dest, $backup)) {
            self::removeDir($tmp);
            return '旧目录备份失败，已中止覆盖';
        }
        if (!rename($tmp, $dest)) {
            rename($backup, $dest);
            self::removeDir($tmp);
            return '新目录替换失败，已回滚';
        }
        self::removeDir($backup);
        return '';
    }

    /**
     * 服务器实际上传上限（upload_max_filesize 与 post_max_size 的较小者，单位字节）
     *
     * @return int
     */
    public static function uploadLimit()
    {
        $upload = self::iniBytes(ini_get('upload_max_filesize'));
        $post = self::iniBytes(ini_get('post_max_size'));
        // post_max_size=0 表示不限制
        return $post > 0 ? min($upload, $post) : $upload;
    }

    /** php.ini 简写容量（如 2M/512K/1G）换算为字节 */
    private static function iniBytes($value)
    {
        $value = trim((string) $value);
        if ($value === '') {
            return 0;
        }
        $num = (float) $value;
        switch (strtolower(substr($value, -1))) {
            case 'g':
                $num *= 1024;
                // no break
            case 'm':
                $num *= 1024;
                // no break
            case 'k':
                $num *= 1024;
        }
        return (int) $num;
    }

    /** 若目录内只有一个子目录，则把子目录内容上移一层（兼容套层打包的 zip） */
    public static function flattenSingleSubdir($dir)
    {
        $items = array_values(array_diff(scandir($dir), array('.', '..')));
        if (count($items) !== 1) {
            return;
        }
        $sub = $dir . '/' . $items[0];
        if (!is_dir($sub)) {
            return;
        }
        foreach (array_diff(scandir($sub), array('.', '..')) as $item) {
            rename($sub . '/' . $item, $dir . '/' . $item);
        }
        rmdir($sub);
    }

    /** 递归删除目录（符号链接只删链接本身，不跟随进入目标目录） */
    public static function removeDir($dir)
    {
        foreach (array_diff(scandir($dir), array('.', '..')) as $item) {
            $path = $dir . '/' . $item;
            // 符号链接直接 unlink：is_dir 会跟随链接，误删链接目标目录的内容
            if (is_link($path)) {
                unlink($path);
                continue;
            }
            if (is_dir($path)) {
                self::removeDir($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }
}
