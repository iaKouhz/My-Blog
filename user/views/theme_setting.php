<?php
/**
 * 后台视图：主题设置（表单由主题 settings.php 清单驱动，内核通用渲染；layui 表单控件）
 * 清单字段类型：text / textarea / checkbox / select
 */
defined('APP_BOOT') or exit;
?>
<div class="card">
    <h3>主题设置：<?php echo e($name); ?>（<?php echo e($dir); ?>）</h3>
    <?php if (empty($schema)): ?>
    <p class="tip">该主题未提供设置清单（主题目录内无 settings.php），没有可配置项。</p>
    <?php else: ?>
    <form method="post" action="<?php echo e(site_base_admin('theme/setting_save')); ?>" class="layui-form v-form">
        <?php echo Csrf::field(); ?>
        <input type="hidden" name="dir" value="<?php echo e($dir); ?>">
        <?php foreach ($schema as $setKey => $field): ?>
        <?php $cur = isset($settings[$setKey]) ? (string) $settings[$setKey] : $field['default']; ?>
        <div class="layui-form-item">
            <?php if ($field['type'] === 'checkbox'): ?>
            <label class="v-label"><?php echo e($field['label']); ?></label>
            <input type="checkbox" name="<?php echo e($setKey); ?>" lay-skin="switch" lay-text="开|关" value="1" <?php echo $cur === '1' ? 'checked' : ''; ?>>
            <?php elseif ($field['type'] === 'textarea'): ?>
            <label class="v-label"><?php echo e($field['label']); ?></label>
            <textarea name="<?php echo e($setKey); ?>" class="layui-textarea" style="max-width:100%;width:100%;min-height:80px"><?php echo e($cur); ?></textarea>
            <?php elseif ($field['type'] === 'select'): ?>
            <label class="v-label"><?php echo e($field['label']); ?></label>
            <select name="<?php echo e($setKey); ?>">
                <?php foreach ($field['options'] as $optValue => $optLabel): ?>
                <option value="<?php echo e($optValue); ?>" <?php echo $cur === $optValue ? 'selected' : ''; ?>><?php echo e($optLabel); ?></option>
                <?php endforeach; ?>
            </select>
            <?php else: ?>
            <label class="v-label"><?php echo e($field['label']); ?></label>
            <input type="text" name="<?php echo e($setKey); ?>" maxlength="<?php echo (int) $field['maxlength']; ?>" value="<?php echo e($cur); ?>" class="layui-input">
            <?php endif; ?>
            <?php if ($field['hint'] !== ''): ?>
            <div class="form-hint"><?php echo e($field['hint']); ?></div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <button class="layui-btn" type="submit"><i class="layui-icon layui-icon-ok"></i> 保存</button>
    </form>
    <?php endif; ?>
</div>
