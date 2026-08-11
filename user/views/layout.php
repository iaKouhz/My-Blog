<?php
/**
 * 后台统一布局（layui 版）：layui-layout-admin 经典三栏 + 侧栏导航树（按能力点显隐）
 * 变量由 Admin::render() 注入：$pageTitle/$menuItems/$currentM/$view
 * layui 本地化自 assets/vendor/layui/（单文件全量包 v2.13.8，禁 CDN）
 */
defined('APP_BOOT') or exit;
$adminUser = Auth::user();
// 侧栏图标映射（按菜单 m 键），未收录的菜单退回默认图标
$menuIcons = array(
    'dashboard'      => 'layui-icon-home',
    'post/list'      => 'layui-icon-read',
    'comment/list'   => 'layui-icon-dialogue',
    'category/list'  => 'layui-icon-tabs',
    'user/list'      => 'layui-icon-user',
    'setting/site'   => 'layui-icon-website',
    'setting/nav'    => 'layui-icon-menu-fill',
    'setting/security' => 'layui-icon-auz',
    'theme/list'     => 'layui-icon-theme',
    'plugin/list'    => 'layui-icon-component',
    'log/list'       => 'layui-icon-log',
    'profile'        => 'layui-icon-username',
);
// 闪存消息 JSON 化供 layer.msg 轻提示（DOM 内同时保留可见块，无 JS 也能看到）
$flashMsgs = flash_pull();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?php echo e($pageTitle); ?> - 后台管理</title>
<link rel="stylesheet" href="<?php echo e(assets_url('vendor/layui/css/layui.css')); ?>">
<link rel="stylesheet" href="<?php echo e(assets_url('admin/admin.css')); ?>">
<script>
// 渲染前应用已保存的明暗偏好，避免亮色闪屏（cb-darkmode 键与前台默认主题共用）
(function () {
    try {
        var saved = localStorage.getItem('cb-darkmode');
        var dark = saved === 'true' || (saved === null && window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches);
        if (dark) {
            document.documentElement.className += ' darkmode';
        }
    } catch (err) { /* 隐私模式下静默回退亮色 */ }
})();
</script>
<?php do_action('admin_head'); /* 插件后台样式/脚本注入点（head 内） */ ?>
</head>
<body class="layui-layout-body">
<div class="layui-layout layui-layout-admin">
    <div class="layui-header admin-header">
        <div class="top-left">
            <button type="button" class="side-toggle" id="admin-side-toggle" aria-label="切换导航菜单" aria-expanded="false">
                <i class="layui-icon layui-icon-spread-left"></i>
            </button>
            <div class="top-title"><?php echo e($pageTitle); ?></div>
        </div>
        <ul class="layui-nav admin-top-nav" lay-filter="adminTopNav">
            <li class="layui-nav-item">
                <a href="javascript:;">
                    <?php echo e($adminUser['nickname']); ?><span class="top-role">（<?php echo e($adminUser['role']); ?>）</span>
                </a>
                <dl class="layui-nav-child">
                    <dd><a href="<?php echo e(Router::url('home')); ?>" target="_blank"><i class="layui-icon layui-icon-website"></i> 查看站点</a></dd>
                    <dd>
                        <form method="post" action="<?php echo e(site_base_admin('auth/logout')); ?>" class="logout-form">
                            <?php echo Csrf::field(); ?>
                            <button type="submit" class="logout-btn"><i class="layui-icon layui-icon-logout"></i> 登出</button>
                        </form>
                    </dd>
                </dl>
            </li>
        </ul>
    </div>
    <div class="layui-side admin-side" id="admin-side">
        <div class="side-brand"><?php echo e(site_name()); ?><span>管理后台</span></div>
        <div class="side-scroll">
            <ul class="layui-nav layui-nav-tree admin-nav-tree" lay-filter="adminNav" lay-shrink="all">
                <?php foreach ($menuItems as $item): ?>
                <?php if (!empty($item['children'])): ?>
                <?php
                // 二级菜单组：子项任一激活时展开并高亮父项
                $groupOn = false;
                foreach ($item['children'] as $child) {
                    if ($currentM === $child['m'] || strpos($currentM, explode('&', $child['m'])[0] . '&') === 0) {
                        $groupOn = true;
                        break;
                    }
                }
                ?>
                <li class="layui-nav-item<?php echo $groupOn ? ' layui-nav-itemed group-on' : ''; ?>">
                    <a href="javascript:;"><i class="layui-icon layui-icon-component"></i> <?php echo e($item['name']); ?></a>
                    <dl class="layui-nav-child">
                        <?php foreach ($item['children'] as $child): ?>
                        <?php $childOn = $currentM === $child['m'] || strpos($currentM, explode('&', $child['m'])[0] . '&') === 0; ?>
                        <dd<?php echo $childOn ? ' class="layui-this"' : ''; ?>>
                            <a href="<?php echo e(site_base_admin($child['m'])); ?>"><?php echo e($child['name']); ?></a>
                        </dd>
                        <?php endforeach; ?>
                    </dl>
                </li>
                <?php else: ?>
                <?php
                $itemOn = strpos($currentM, explode('&', $item['m'])[0]) === 0;
                $icon = isset($menuIcons[$item['m']]) ? $menuIcons[$item['m']] : 'layui-icon-app';
                ?>
                <li class="layui-nav-item<?php echo $itemOn ? ' layui-this' : ''; ?>">
                    <a href="<?php echo e(site_base_admin($item['m'])); ?>">
                        <i class="layui-icon <?php echo e($icon); ?>"></i> <?php echo e($item['name']); ?>
                    </a>
                </li>
                <?php endif; ?>
                <?php endforeach; ?>
            </ul>
        </div>
        <div class="side-foot">
            <button type="button" class="layui-btn layui-btn-primary layui-btn-sm layui-btn-fluid darkmode-toggle"
                    id="admin-darkmode-toggle" title="切换明暗模式" aria-label="切换明暗模式">
                <i class="layui-icon layui-icon-moon" id="admin-darkmode-icon"></i> 明暗切换
            </button>
        </div>
    </div>
    <div class="admin-side-overlay" id="admin-side-overlay"></div>
    <div class="layui-body admin-body">
        <main class="admin-main">
            <?php foreach ($flashMsgs as $fm): ?>
            <div class="admin-flash <?php echo $fm['type'] === 'success' ? 'ok' : 'err'; ?>">
                <i class="layui-icon <?php echo $fm['type'] === 'success' ? 'layui-icon-ok-circle' : 'layui-icon-tips'; ?>"></i>
                <?php echo e($fm['text']); ?>
            </div>
            <?php endforeach; ?>
            <?php include APP_ROOT . '/user/views/' . $view . '.php'; ?>
            <div class="admin-footnote"><?php echo e(site_name()); ?> · 管理后台</div>
        </main>
    </div>
</div>
<?php if (!empty($flashMsgs)): ?>
<script>window.CB_FLASH = <?php echo json_encode(array_map(function ($fm) {
    return array('type' => $fm['type'], 'text' => $fm['text']);
}, $flashMsgs), JSON_UNESCAPED_UNICODE); ?>;</script>
<?php endif; ?>
<script src="<?php echo e(assets_url('vendor/layui/layui.js')); ?>"></script>
<script src="<?php echo e(assets_url('admin/admin.js')); ?>"></script>
<?php if (isset($viewScripts)): ?>
<?php foreach ($viewScripts as $scriptUrl): ?>
<script src="<?php echo e($scriptUrl); ?>"></script>
<?php endforeach; ?>
<?php endif; ?>
<?php do_action('admin_footer'); /* 插件后台脚本注入点（body 末尾） */ ?>
</body>
</html>
