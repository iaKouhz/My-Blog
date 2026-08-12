# My Blog（纯 PHP 个人博客系统）

零框架、零 Composer 的轻量个人博客系统，按等保二级（GB/T 22239-2019）相关要求设计，
原生 PHP 7.2+ / MySQL 5.7 / PDO 实现。

## 功能概览

- 文章（Markdown，支持多文章置顶）/ 独立页面（可选是否显示在侧边栏导航）/ 分类 / 评论 / 搜索 / 归档
- 用户体系：注册（邮箱/手机验证码，可选）、登录、找回密码、角色（admin/editor/user）、个性签名（作者页展示）
- 后台管理：文章、评论（支持按作者/文章/发表时间筛选）、分类、用户、站点设置、安全设置、模板、插件、日志中心（默认查询窗口为近一个月）、个人资料；界面基于本地化的 layui 组件库（布局/导航/表单/表格/弹层）；明暗模式切换（与前台偏好联动）、固定导航侧栏、移动端抽屉导航
- 插件机制（仿 WordPress Hook）与模板机制（style.css 元数据 + zip 上传）
- 统一审计日志（只增不改、留存 ≥180 天、查询/导出留痕）
- 登录失败锁定（5 次/10 分钟，可配）+ IP 限流、会话 30 分钟无操作超时；改密后其余既有会话自动失效；登录失败提示统一防账号枚举
- 验证码发送接口 IP 维度限流；提交时原子消费（防并发重放）；不设预检接口，验证码对错仅在提交时统一提示（防有效性探测）；错误容忍次数由发送插件管理（默认 2 次，错满即作废）；发送与核验全程审计
- 预装插件（默认禁用）：`smtp-mailer`（SMTP 发信）、`aliyun-sms`（阿里云短信验证码）、`comment-guard`（文章级评论管制：关闭/禁止三态）

## 环境要求

| 项目 | 要求 |
|---|---|
| PHP | ≥ 7.2（语法按 7.2 兼容），扩展：pdo_mysql、mbstring、curl、fileinfo；主题上传需 zip |
| MySQL | ≥ 5.7.44（utf8mb4 / InnoDB） |
| Web 服务器 | Apache（mod_rewrite）或 Nginx |

## 安装步骤

1. 将本目录部署到 Web 根目录（或子目录，需同步修改 `.htaccess` 的 `RewriteBase`）。
2. 浏览器访问 `/install/`，按四步向导填写数据库信息、站点信息、管理员账号，
   安装程序自动建表并生成根目录 `config.php`（配置文件不入库）。
3. 安装完成后删除或改名 `install/` 目录（安装程序检测到已有 config.php 会拒绝重复执行）。
4. 访问首页；管理员从 `/user/` 登录后台。

## 伪静态

- Apache：使用根目录自带的 `.htaccess`（需启用 mod_rewrite）。
- Nginx：完整 server 块参考 `nginx.conf.example`；宝塔面板用户直接将
  `bt-panel.rewrite.conf` 内容粘贴到「网站 → 设置 → 伪静态」即可（含敏感文件防护规则）。
- 三套规则均包含敏感文件防护：禁止访问 `config.php` 与 `core/`，禁止 `plugins/`、`themes/`
  下 PHP 文件直接执行，`uploads/` 禁止执行 PHP（另有文件头部 APP_BOOT 守卫双重防护）。
- 后台「站点设置 → 启用伪静态」开启后，链接形如 `/post/1.html`；
  关闭时自动使用 `index.php?r=...` 兼容模式，站内链接全部经 `Router::url()` 生成，切换不破坏链接。

## HTTPS 要求（等保二级·传输防窃听）

**管理后台必须通过 HTTPS 访问。** 系统在 HTTPS 环境下自动为会话 Cookie 附加 `Secure` 属性；
HTTP 环境下该属性不会错误下发。

## 安全默认值（安装程序写入，勿随意调低）

| 项目 | 默认值 |
|---|---|
| 登录失败锁定 | 连续 5 次失败锁定 10 分钟（持久化 + IP 限流） |
| 会话无操作超时 | 30 分钟 |
| 审计日志留存 | 180 天（下限即 180 天，《网络安全法》第 21 条不少于六个月） |
| 自定义 IP 标头 | 关闭（`ip_header_enabled=0`；仅在可信反代/CDN 后才可开启；标头名支持英文逗号分隔多个，在前优先级高） |
| 调试模式 | 关闭（`debug=0`，生产环境保持关闭） |
| 密码定期更换 | 预留完整、默认关闭（`pwd_expire_enabled=0`，可配 30-365 天） |
| 密码历史防重复 | 预留完整、默认关闭（`pwd_history_count=0`） |
| 文章/评论审核 | 默认关闭（开启后仅管理员可发布） |

口令策略（四个设密入口统一强制）：8-64 位、大小写/数字/特殊字符至少三类、
不含用户名、不命中弱口令黑名单；改密必须验证原密码；改邮箱/手机等敏感操作重验密码。

## 目录结构

```
index.php          前台单一入口（路由分发）
config.php         安装程序生成（禁止 HTTP 访问）
core/              内核（禁止 HTTP 访问，文件头部 APP_BOOT 守卫）
themes/            模板（themes/default 为默认主题，开发指南见 themes/README.md）
plugins/           插件（开发规范见 plugins/README.md）
assets/            静态资源；assets/vendor/ 为本地化第三方资源（登记见其 README），assets/front/ 为内核跨场景共用脚本；主题自有资源随主题目录存放（如 themes/default/js/）
uploads/           上传目录（已配置禁止执行 PHP）
user/              后台入口（/user/index.php）
install/           安装向导（安装后应删除）
```

## 审计日志与备份

- 日志只增不改：系统不提供任何编辑/删除接口，仅到期自动清理与管理员导出。
- 日志查询与导出行为本身写入审计记录。
- **请定期备份数据库**（含日志表），例如每日：
  `mysqldump -u USER -p DBNAME cb_logs > logs_$(date +%F).sql`
  建议备份文件异地存放并保留至少六个月。

## 第三方本地化资源

本站无运行时 CDN 引用。layui / Vditor / KaTeX / highlight.js 已按登记版本下载并放置在
`assets/vendor/`（版本、来源见 `assets/vendor/README.md`）。资源缺失时系统自动降级：
layui 缺失时后台回退为原生表单提交与原生 confirm，编辑器回退为 textarea，
公式与代码块以纯文本展示，不影响其余功能。

## 文档索引

- 实施蓝图：`plan.md`
- 强制约束：`AGENTS.md`
- 插件开发规范：`plugins/README.md`
- 主题开发指南：`themes/README.md`
- 阿里云短信接入指引：`plugins/aliyun-sms/README.md`
- 本地化资源登记：`assets/vendor/README.md`
- 本地化资源升级作业手册：`assets/vendor/UPGRADE.md`
