<?php
/**
 * 后台视图：安全设置（等保二级相关控制点落地配置；layui 折叠面板分组 + 开关）
 */
defined('APP_BOOT') or exit;
?>
<div class="card">
    <p class="tip">以下默认值为等保二级安全水位，随意调低将削弱系统安全性。所有变更均会写入审计日志。</p>
    <form method="post" action="<?php echo e(site_base_admin('setting/security_save')); ?>" class="layui-form v-form">
        <?php echo Csrf::field(); ?>

        <div class="layui-collapse" style="margin-bottom:16px">
            <div class="layui-colla-item">
                <h3 class="layui-colla-title">登录与会话</h3>
                <div class="layui-colla-content layui-show">
                    <div class="layui-form-item">
                        <label class="v-label">连续登录失败锁定阈值（次，默认 5）</label>
                        <input type="number" name="login_max_fail" min="1" max="20"
                               value="<?php echo (int) Option::get('login_max_fail', 5); ?>" class="layui-input">
                    </div>
                    <div class="layui-form-item">
                        <label class="v-label">锁定时长（分钟，默认 10）</label>
                        <input type="number" name="login_lock_minutes" min="1" max="1440"
                               value="<?php echo (int) Option::get('login_lock_minutes', 10); ?>" class="layui-input">
                    </div>
                    <div class="layui-form-item">
                        <label class="v-label">会话无操作超时（分钟，默认 30）</label>
                        <input type="number" name="session_timeout_minutes" min="1" max="1440"
                               value="<?php echo (int) Option::get('session_timeout_minutes', 30); ?>" class="layui-input">
                    </div>
                </div>
            </div>
            <div class="layui-colla-item">
                <h3 class="layui-colla-title">口令策略</h3>
                <div class="layui-colla-content layui-show">
                    <div class="layui-form-item">
                        <label class="v-label">启用密码定期更换</label>
                        <input type="checkbox" name="pwd_expire_enabled" lay-skin="switch" lay-text="开|关" value="1"
                               <?php echo Option::get('pwd_expire_enabled', '0') === '1' ? 'checked' : ''; ?>>
                        <div class="form-hint">默认关闭；开启后超期登录将被强制改密</div>
                    </div>
                    <div class="layui-form-item">
                        <label class="v-label">密码有效期（天，30-365，默认 90）</label>
                        <input type="number" name="pwd_expire_days" min="30" max="365"
                               value="<?php echo (int) Option::get('pwd_expire_days', 90); ?>" class="layui-input">
                    </div>
                    <div class="layui-form-item">
                        <label class="v-label">密码历史防重复次数（0 表示关闭，默认 0）</label>
                        <input type="number" name="pwd_history_count" min="0" max="24"
                               value="<?php echo (int) Option::get('pwd_history_count', 0); ?>" class="layui-input">
                    </div>
                </div>
            </div>
            <div class="layui-colla-item">
                <h3 class="layui-colla-title">客户端 IP</h3>
                <div class="layui-colla-content layui-show">
                    <div class="layui-form-item">
                        <label class="v-label">从自定义标头读取客户端 IP</label>
                        <input type="checkbox" name="ip_header_enabled" lay-skin="switch" lay-text="开|关" value="1"
                               <?php echo Option::get('ip_header_enabled', '0') === '1' ? 'checked' : ''; ?>>
                        <div class="form-hint">默认关闭</div>
                    </div>
                    <div class="layui-form-item">
                        <label class="v-label">标头名称（支持多个，英文逗号分隔，在前优先级高；如 ali-real-client-ip,X-Forwarded-For）</label>
                        <input type="text" name="ip_header_name" pattern="[A-Za-z0-9-]+(,[A-Za-z0-9-]+)*"
                               placeholder="ali-real-client-ip,X-Forwarded-For"
                               value="<?php echo e(Option::get('ip_header_name', 'X-Forwarded-For')); ?>" class="layui-input">
                        <div class="form-hint">按顺序依次尝试，某个标头取到合法 IP 即停止；均取不到时使用 REMOTE_ADDR。</div>
                    </div>
                    <p class="tip">警告：仅在站点部署于可信反向代理/CDN 之后才可开启此项。
                    若服务器直接暴露公网却信任 X-Forwarded-For 等标头，攻击者可伪造 IP 绕过限流与审计。</p>
                </div>
            </div>
            <div class="layui-colla-item">
                <h3 class="layui-colla-title">审计日志</h3>
                <div class="layui-colla-content layui-show">
                    <div class="layui-form-item">
                        <label class="v-label">日志留存天数（下限 180 天，默认 180）</label>
                        <input type="number" name="log_retention_days" min="180" max="3650"
                               value="<?php echo (int) Option::get('log_retention_days', 180); ?>" class="layui-input">
                    </div>
                </div>
            </div>
            <div class="layui-colla-item">
                <h3 class="layui-colla-title">调试</h3>
                <div class="layui-colla-content layui-show">
                    <div class="layui-form-item">
                        <label class="v-label">调试模式</label>
                        <input type="checkbox" name="debug" lay-skin="switch" lay-text="开|关" value="1"
                               <?php echo Option::get('debug', '0') === '1' ? 'checked' : ''; ?>>
                        <div class="form-hint">生产环境必须关闭；开启后错误详情会输出到页面</div>
                    </div>
                </div>
            </div>
        </div>

        <button class="layui-btn" type="submit"><i class="layui-icon layui-icon-ok"></i> 保存</button>
    </form>
</div>
