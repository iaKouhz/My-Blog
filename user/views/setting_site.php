<?php
/**
 * 后台视图：站点设置（layui 表单；布尔项经 form 模块渲染为开关）
 */
defined('APP_BOOT') or exit;
// 常用时区候选（保存时服务端会校验是否为 PHP 合法时区标识）
$timezones = array(
    'Asia/Shanghai'      => '中国标准时间（UTC+8）',
    'Asia/Hong_Kong'     => '香港（UTC+8）',
    'Asia/Taipei'        => '台北（UTC+8）',
    'Asia/Singapore'     => '新加坡（UTC+8）',
    'Asia/Tokyo'         => '日本（UTC+9）',
    'Asia/Seoul'         => '韩国（UTC+9）',
    'Asia/Bangkok'       => '泰国（UTC+7）',
    'Asia/Kolkata'       => '印度（UTC+5:30）',
    'Asia/Dubai'         => '迪拜（UTC+4）',
    'Europe/London'      => '英国（UTC+0/+1）',
    'Europe/Paris'       => '法国/德国（UTC+1/+2）',
    'Europe/Athens'      => '希腊（UTC+2/+3）',
    'Europe/Moscow'      => '莫斯科（UTC+3）',
    'America/New_York'   => '美东（UTC-5/-4）',
    'America/Chicago'    => '美中（UTC-6/-5）',
    'America/Denver'     => '美山地（UTC-7/-6）',
    'America/Los_Angeles' => '美西（UTC-8/-7）',
    'America/Sao_Paulo'  => '巴西（UTC-3）',
    'Australia/Sydney'   => '悉尼（UTC+10/+11）',
    'Pacific/Auckland'   => '新西兰（UTC+12/+13）',
    'UTC'                => '协调世界时（UTC+0）',
);
$currentTimezone = Option::get('timezone', 'Asia/Shanghai');
?>
<div class="card">
    <form method="post" action="<?php echo e(site_base_admin('setting/site_save')); ?>" class="layui-form v-form">
        <?php echo Csrf::field(); ?>
        <div class="layui-form-item">
            <label class="v-label">站点名称</label>
            <input type="text" name="site_name" value="<?php echo e(Option::get('site_name', '个人博客')); ?>" required class="layui-input">
        </div>
        <div class="layui-form-item">
            <label class="v-label">一句话座右铭</label>
            <input type="text" name="site_motto" value="<?php echo e(Option::get('site_motto', '')); ?>" class="layui-input">
        </div>
        <div class="layui-form-item">
            <label class="v-label">站点描述（SEO）</label>
            <textarea name="site_description" rows="2" class="layui-textarea"><?php echo e(Option::get('site_description', '')); ?></textarea>
        </div>
        <div class="layui-form-item">
            <label class="v-label">关键词（SEO，逗号分隔）</label>
            <input type="text" name="site_keywords" value="<?php echo e(Option::get('site_keywords', '')); ?>" class="layui-input">
        </div>
        <div class="layui-form-item">
            <label class="v-label">每页文章条数（1-50）</label>
            <input type="number" name="posts_per_page" min="1" max="50"
                   value="<?php echo (int) Option::get('posts_per_page', 10); ?>" class="layui-input">
        </div>
        <div class="layui-form-item">
            <label class="v-label">站点时区（影响日志、文章、评论等全部时间显示）</label>
            <select name="timezone">
                <?php foreach ($timezones as $tzId => $tzLabel): ?>
                <option value="<?php echo e($tzId); ?>" <?php echo $currentTimezone === $tzId ? 'selected' : ''; ?>>
                    <?php echo e($tzLabel); ?> [<?php echo e($tzId); ?>]
                </option>
                <?php endforeach; ?>
                <?php if (!isset($timezones[$currentTimezone])): ?>
                <option value="<?php echo e($currentTimezone); ?>" selected><?php echo e($currentTimezone); ?></option>
                <?php endif; ?>
            </select>
        </div>
        <?php // 布尔开关：lay-skin=switch 由 layui form 渲染；无 JS 时回退为原生勾选框 ?>
        <div class="layui-form-item">
            <label class="v-label">关闭公开注册</label>
            <input type="checkbox" name="register_disabled" lay-skin="switch" lay-text="开|关" value="1"
                   <?php echo Option::get('register_disabled', '0') === '1' ? 'checked' : ''; ?>>
            <div class="form-hint">勾选后前台注册页不可访问，新用户只能由后台创建</div>
        </div>
        <div class="layui-form-item">
            <label class="v-label">开启文章审核</label>
            <input type="checkbox" name="post_audit" lay-skin="switch" lay-text="开|关" value="1"
                   <?php echo Option::get('post_audit', '0') === '1' ? 'checked' : ''; ?>>
            <div class="form-hint">开启后仅管理员可直接发布，投稿进入待审核</div>
        </div>
        <div class="layui-form-item">
            <label class="v-label">开启评论审核</label>
            <input type="checkbox" name="comment_audit" lay-skin="switch" lay-text="开|关" value="1"
                   <?php echo Option::get('comment_audit', '0') === '1' ? 'checked' : ''; ?>>
            <div class="form-hint">开启后评论须管理员审核后才公开</div>
        </div>
        <div class="layui-form-item">
            <label class="v-label">启用伪静态</label>
            <input type="checkbox" name="rewrite_enabled" lay-skin="switch" lay-text="开|关" value="1"
                   <?php echo Option::get('rewrite_enabled', '0') === '1' ? 'checked' : ''; ?>>
            <div class="form-hint">需服务器已配置 rewrite 规则，见 README</div>
        </div>
        <button class="layui-btn" type="submit"><i class="layui-icon layui-icon-ok"></i> 保存</button>
    </form>
</div>
