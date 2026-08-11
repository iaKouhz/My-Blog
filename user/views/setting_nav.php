<?php
/**
 * 后台视图：导航管理（自定义导航项增删改，支持一层父子层级与拖拽排序）
 * JS 下按 DOM 顺序重编号提交 title_i/url_i/parent_i + row_total；
 * 无 JS 时按服务端渲染的 name（旧顺序）原样提交，兜底新增行在列表底部
 * 注：父级下拉为原生 select（行模板由 JS 动态克隆，不经 layui form 渲染）
 */
defined('APP_BOOT') or exit;
$flat = AdminSetting::flattenNav($items);
// 顶层行号集合（父级下拉仅允许选择顶层行，保证单层结构）
$topIndexes = array();
foreach ($flat as $i => $row) {
    if ($row['parent'] < 0) {
        $topIndexes[] = $i;
    }
}
?>
<div class="card">
    <h3>导航管理</h3>
    <p class="tip">
        拖动行首手柄图标调整顺序，导航项按保存顺序展示在前台主题导航中。
        支持一层父子层级：把某行的「父级」选为另一行，该行即归入其下成为子项（父级须为更早的顶层行）；
        拖动不会改变父子从属关系（子项不能拖到其父项上方）。
        新添加的项必须填写链接；既有顶层项的链接之后可留空作纯文本分组标签（仅在它有子项时生效），子项链接必填。
        点击「添加导航项」追加新行，已有顶层项时可直接为其选择父级；行尾红色按钮移除该行，
        父项被移除时其子项提升为顶层。所有改动点击「保存」后生效。
        链接支持站内相对路径（如 <code>/index.php?r=page/about</code>）或 http(s) 地址。
    </p>
    <form id="nav-form" method="post" action="<?php echo e(site_base_admin('setting/nav_save')); ?>">
        <?php echo Csrf::field(); ?>
        <input type="hidden" id="nav-row-total" name="row_total" value="-1">
        <ul id="nav-list" class="nav-list">
            <?php foreach ($flat as $i => $row): ?>
            <li class="nav-row<?php echo $row['parent'] >= 0 ? ' nav-child' : ''; ?>" data-key="<?php echo (int) $i; ?>">
                <span class="nav-handle" title="拖动排序"><i class="layui-icon layui-icon-slider"></i></span>
                <input type="text" class="layui-input nav-title" data-field="title" name="title_<?php echo (int) $i; ?>"
                    value="<?php echo e($row['title']); ?>" placeholder="标题" required>
                <input type="text" class="layui-input nav-url" data-field="url" name="url_<?php echo (int) $i; ?>"
                    value="<?php echo e($row['url']); ?>"
                    placeholder="<?php echo $row['parent'] >= 0 ? '子项链接必填' : '顶层可留空作分组标签'; ?>">
                <select class="layui-input nav-parent" data-field="parent" name="parent_<?php echo (int) $i; ?>">
                    <option value="-1"<?php echo $row['parent'] < 0 ? ' selected' : ''; ?>>顶层</option>
                    <?php foreach ($topIndexes as $t): ?>
                    <?php if ($t >= $i) { break; } ?>
                    <option value="<?php echo (int) $t; ?>"<?php echo $row['parent'] === $t ? ' selected' : ''; ?>><?php echo e(mb_substr($flat[$t]['title'], 0, 12)); ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="button" class="layui-btn layui-btn-xs layui-btn-danger nav-del" title="移除该行"><i class="layui-icon layui-icon-close"></i></button>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php if (empty($flat)): ?>
        <p class="tip">暂无自定义导航项，点击下方按钮添加。</p>
        <?php endif; ?>

        <?php // 无 JS 兜底新增行（恒为顶层）；JS 启用后隐藏，改由「添加导航项」按钮追加可选父级的完整行 ?>
        <div id="nav-new-row" class="nav-new-fallback">
            <input type="text" name="new_title" placeholder="新增导航标题（留空不新增）" class="layui-input">
            <input type="text" name="new_url" placeholder="新增链接，如 /index.php?r=page/about" class="layui-input">
            <span class="tip">新增（顶层）</span>
        </div>

        <div class="nav-actions">
            <button type="button" id="nav-add-btn" class="layui-btn layui-btn-primary" style="display:none">
                <i class="layui-icon layui-icon-add-1"></i> 添加导航项
            </button>
            <button class="layui-btn" type="submit"><i class="layui-icon layui-icon-ok"></i> 保存</button>
        </div>

        <?php // JS 动态新增行模板：父级选项为当前全部顶层行（已有顶层项时添加即可直接选父级）；
              // fresh 隐藏域供服务端识别新增行（新增行链接必填） ?>
        <template id="nav-row-tpl">
        <li class="nav-row" data-key="">
            <span class="nav-handle" title="拖动排序"><i class="layui-icon layui-icon-slider"></i></span>
            <input type="text" class="layui-input nav-title" data-field="title" value="" placeholder="标题" required>
            <input type="text" class="layui-input nav-url" data-field="url" value="" placeholder="链接（必填），如 /index.php?r=page/about" required>
            <input type="hidden" data-field="fresh" value="1">
            <select class="layui-input nav-parent" data-field="parent">
                <option value="-1" selected>顶层</option>
                <?php foreach ($topIndexes as $t): ?>
                <option value="<?php echo (int) $t; ?>"><?php echo e(mb_substr($flat[$t]['title'], 0, 12)); ?></option>
                <?php endforeach; ?>
            </select>
            <button type="button" class="layui-btn layui-btn-xs layui-btn-danger nav-del" title="移除该行"><i class="layui-icon layui-icon-close"></i></button>
        </li>
        </template>
    </form>
</div>
<script src="<?php echo e(assets_url('admin/nav_sort.js')); ?>"></script>
