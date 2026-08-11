# assets/vendor 第三方本地化资源登记

本站禁止任何运行时 CDN 引用（含字体、图片、统计脚本）。
所有第三方 JS/CSS 必须本地化到本目录，并在此登记名称、版本、来源。

## 资源清单

| 资源 | 版本 | 用途 | 来源 URL | 放置位置 |
|---|---|---|---|---|
| Vditor | 3.10.9 | 后台 Markdown 编辑器 | https://registry.npmjs.org/vditor/-/vditor-3.10.9.tgz（npm 官方包，取 `dist/` 全套） | `assets/vendor/vditor/dist/` |
| KaTeX | 0.16.11 | 前台数学公式渲染 | https://registry.npmjs.org/katex/-/katex-0.16.11.tgz（npm 官方包，取 `dist/`） | `assets/vendor/katex/` |
| highlight.js | 11.9.0 | 前台代码高亮 | https://registry.npmjs.org/@highlightjs/cdn-assets/-/cdn-assets-11.9.0.tgz（`highlight.min.js` + `styles/default.min.css`） | `assets/vendor/highlight.js/` |
| layui | 2.13.8 | 后台管理界面 UI 组件库（布局/导航/表单/表格/徽章/选项卡/分页/弹层） | https://registry.npmjs.org/layui/-/layui-2.13.8.tgz（npm 官方包，取 `dist/` 全套：layui.js + css/layui.css + font/图标字体） | `assets/vendor/layui/` |

> 上述资源已从官方 npm registry 下载并放置到位（layui 由用户于 2026-08 放置），无需再次手工下载。

## 目录结构（当前实际）

```
assets/vendor/
├── vditor/
│   └── dist/               ← Vditor dist 全套（index.min.js / index.css / js / images / css）
├── katex/
│   ├── katex.min.js
│   ├── katex.min.css
│   └── fonts/…             ← KaTeX 公式字体全量
├── highlight.js/
│   ├── highlight.min.js
│   └── default.min.css
└── layui/
    ├── layui.js            ← 全量单文件包（含 layer/form/element/laypage/laydate 等全部内置模块）
    ├── css/
    │   ├── layui.css       ← 全部组件样式（含 laydate/layer，2.13.8 全量版已合并）
    │   └── modules/laydate.css ← 占位文件：laydate 运行时会按需请求该路径，缺失会导致日期面板无法弹出（勿删）
    └── font/…              ← 图标字体（layui.css 经相对路径 ../font/ 引用，勿单独移动）
```

## 加载与降级机制（无需改代码）

- 后台管理界面整体基于 layui（布局、导航、按钮、表格、徽章、选项卡、分页、弹层确认/轻提示、laydate 日期范围选择）；
  layui 加载失败时，核心功能回退为原生表单提交 + 原生 confirm，日期筛选回退为按原隐藏域值提交，不阻断操作。
- 后台文章编辑页检测到 `vditor/dist/index.min.js` 存在即自动启用 Vditor，
  并显式传入本地 `cdn` 参数（Vditor 默认从 unpkg 懒加载子资源，本地化后必须指向本目录）；
  资源缺失时回退为原生 textarea，功能不受影响。
- 前台检测到 `katex/`、`highlight.js/` 文件存在即自动加载渲染公式与代码高亮，
  缺失时静默降级为纯文本展示。

## 说明

- 升级任一资源后，请同步更新本表中的版本号，并确认懒加载子资源仍从本地解析。
- 禁止将任何资源改回 CDN 引用（AGENTS.md §2.5）。
