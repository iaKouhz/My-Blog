<?php
/**
 * 后台视图：用户列表（layui 表格/徽章/按钮）
 */
defined('APP_BOOT') or exit;
$roleNames = array('admin' => '管理员', 'editor' => '编辑', 'user' => '用户');
?>
<div class="card">
    <div style="margin-bottom:14px">
        <a class="layui-btn" href="<?php echo e(site_base_admin('user/edit')); ?>">
            <i class="layui-icon layui-icon-add-1"></i> 新增用户
        </a>
    </div>
    <div class="table-wrap">
    <table class="layui-table" lay-skin="line">
        <thead>
            <tr><th>ID</th><th>用户名</th><th>昵称</th><th>邮箱</th><th>手机</th><th>角色</th><th>状态</th><th>锁定</th><th>注册时间</th><th>操作</th></tr>
        </thead>
        <tbody>
        <?php foreach ($users as $u): ?>
        <?php $locked = !empty($u['locked_until']) && strtotime($u['locked_until']) > time(); ?>
        <tr>
            <td><?php echo (int) $u['id']; ?></td>
            <td><?php echo e($u['username']); ?><?php echo (int) $u['id'] === Auth::id() ? '（我）' : ''; ?></td>
            <td><?php echo e($u['nickname']); ?></td>
            <td><?php echo $u['email'] !== null ? e(mask_email($u['email'])) : '-'; ?></td>
            <td><?php echo $u['phone'] !== '' ? e(mask_phone($u['phone'])) : '-'; ?></td>
            <td><span class="layui-badge-rim layui-badge"><?php echo isset($roleNames[$u['role']]) ? e($roleNames[$u['role']]) : e($u['role']); ?></span></td>
            <td>
                <?php if ((int) $u['status'] === 1): ?><span class="layui-badge layui-bg-green">正常</span>
                <?php else: ?><span class="layui-badge layui-bg-red">已禁用</span><?php endif; ?>
                <?php if (!empty($u['is_banned'])): ?><span class="layui-badge layui-bg-red">已封禁</span><?php endif; ?>
                <?php if (!empty($u['is_deleted'])): ?><span class="layui-badge layui-bg-gray">已注销</span><?php endif; ?>
            </td>
            <td><?php echo $locked ? '<span class="layui-badge layui-bg-red">锁定至 ' . e($u['locked_until']) . '</span>' : '-'; ?></td>
            <td><?php echo e($u['created_at']); ?></td>
            <td>
                <div class="row-actions">
                <a class="layui-btn layui-btn-xs" href="<?php echo e(site_base_admin('user/edit&id=' . (int) $u['id'])); ?>">编辑</a>
                <?php if ($locked): ?>
                <form method="post" action="<?php echo e(site_base_admin('user/unlock')); ?>">
                    <?php echo Csrf::field(); ?>
                    <input type="hidden" name="id" value="<?php echo (int) $u['id']; ?>">
                    <button class="layui-btn layui-btn-xs layui-btn-normal">解锁</button>
                </form>
                <?php endif; ?>
                <?php // 自己与安装管理员不可封禁/注销（服务端同样拦截，此处仅隐藏按钮） ?>
                <?php if ((int) $u['id'] !== Auth::id() && (int) $u['id'] !== $rootId): ?>
                <?php if (empty($u['is_banned'])): ?>
                <form method="post" action="<?php echo e(site_base_admin('user/ban')); ?>"
                      data-confirm="封禁后该用户将无法登录，确认封禁？">
                    <?php echo Csrf::field(); ?>
                    <input type="hidden" name="id" value="<?php echo (int) $u['id']; ?>">
                    <button class="layui-btn layui-btn-xs layui-btn-primary">封禁</button>
                </form>
                <?php else: ?>
                <form method="post" action="<?php echo e(site_base_admin('user/unban')); ?>">
                    <?php echo Csrf::field(); ?>
                    <input type="hidden" name="id" value="<?php echo (int) $u['id']; ?>">
                    <button class="layui-btn layui-btn-xs layui-btn-normal">解封</button>
                </form>
                <?php endif; ?>
                <?php if (empty($u['is_deleted'])): ?>
                <form method="post" action="<?php echo e(site_base_admin('user/deregister')); ?>"
                      data-confirm="注销后该用户无法登录，其文章与评论将显示为“用户已注销”，确认注销？">
                    <?php echo Csrf::field(); ?>
                    <input type="hidden" name="id" value="<?php echo (int) $u['id']; ?>">
                    <button class="layui-btn layui-btn-xs layui-btn-primary">注销</button>
                </form>
                <?php else: ?>
                <form method="post" action="<?php echo e(site_base_admin('user/restore')); ?>">
                    <?php echo Csrf::field(); ?>
                    <input type="hidden" name="id" value="<?php echo (int) $u['id']; ?>">
                    <button class="layui-btn layui-btn-xs layui-btn-normal">恢复</button>
                </form>
                <?php endif; ?>
                <?php endif; ?>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php echo admin_pager($page, $totalPages, 'user/list&'); ?>
</div>
