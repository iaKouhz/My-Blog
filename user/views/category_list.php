<?php
/**
 * 后台视图：分类管理（layui 表单 + 可编辑表格）
 */
defined('APP_BOOT') or exit;
?>
<div class="card">
    <h3>新增分类</h3>
    <form method="post" action="<?php echo e(site_base_admin('category/save')); ?>" class="layui-form filter-bar">
        <?php echo Csrf::field(); ?>
        <input type="hidden" name="id" value="0">
        <input type="text" name="name" placeholder="名称" required class="layui-input" style="width:130px">
        <input type="text" name="slug" placeholder="中文释义（slug）" pattern="[a-z0-9-]+" required class="layui-input" style="width:160px">
        <input type="text" name="description" placeholder="描述" class="layui-input" style="width:180px">
        <input type="number" name="sort" value="0" class="layui-input" style="width:80px" title="排序">
        <button class="layui-btn" type="submit"><i class="layui-icon layui-icon-add-1"></i> 添加</button>
    </form>
</div>

<div class="card">
    <div class="table-wrap">
    <table class="layui-table" lay-skin="line">
        <thead>
            <tr><th>ID</th><th>编辑（名称 / slug / 描述 / 排序）</th><th>中文释义（slug）</th><th>描述</th><th>排序</th><th>操作</th></tr>
        </thead>
        <tbody>
        <?php foreach ($categories as $cat): ?>
        <tr>
            <td><?php echo (int) $cat['id']; ?></td>
            <td>
                <form method="post" action="<?php echo e(site_base_admin('category/save')); ?>" class="filter-bar">
                    <?php echo Csrf::field(); ?>
                    <input type="hidden" name="id" value="<?php echo (int) $cat['id']; ?>">
                    <input type="text" name="name" value="<?php echo e($cat['name']); ?>" style="width:120px" required class="layui-input">
                    <input type="text" name="slug" value="<?php echo e($cat['slug']); ?>" style="width:120px" pattern="[a-z0-9-]+" required class="layui-input">
                    <input type="text" name="description" value="<?php echo e($cat['description']); ?>" style="width:160px" class="layui-input">
                    <input type="number" name="sort" value="<?php echo (int) $cat['sort']; ?>" style="width:70px" class="layui-input">
                    <button class="layui-btn layui-btn-xs" type="submit">保存</button>
                </form>
            </td>
            <td><?php echo e($cat['slug']); ?></td>
            <td><?php echo e(mb_substr($cat['description'], 0, 30)); ?></td>
            <td><?php echo (int) $cat['sort']; ?></td>
            <td>
                <form method="post" action="<?php echo e(site_base_admin('category/delete')); ?>"
                      data-confirm="确认删除该分类？">
                    <?php echo Csrf::field(); ?>
                    <input type="hidden" name="id" value="<?php echo (int) $cat['id']; ?>">
                    <button class="layui-btn layui-btn-xs layui-btn-danger">删除</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>
