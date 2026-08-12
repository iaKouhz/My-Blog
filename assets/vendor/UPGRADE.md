# 第三方本地化资源升级作业手册

> 适用范围：`assets/vendor/` 下所有本地化第三方资源（Vditor、KaTeX、highlight.js、layui 等）的版本升级、裁剪与验证。
> 本手册沉淀自 2026-08 Vditor 3.10.9 → 3.11.3 升级与瘦身实践（21.35 MB → 6.76 MB）。
> 强制约束见 AGENTS.md（§2.5 禁 CDN、§4.6 编辑器、§6.3 文档同步矩阵）。

---

## 一、通用升级流程

1. **下载官方包**（PowerShell，勿用浏览器手工另存）：
   ```powershell
   Invoke-WebRequest -Uri 'https://registry.npmjs.org/<包名>/-/<包名>-<版本>.tgz' -OutFile "$env:TEMP\<包名>.tgz" -UseBasicParsing
   tar -xzf "$env:TEMP\<包名>.tgz" -C "$env:TEMP"   # 解压到 $env:TEMP\package
   ```
2. **先验后换**：在临时目录先完成版本确认、兼容检查、裁剪，再整体替换正式目录；替换时先把旧目录改名备份（如 `dist.bak-旧版本`），验证通过后再删备份。
3. **裁剪**：按 `assets/vendor/README.md` 的裁剪清单对新包重新执行删除（新版可能引入新的冗余目录，如 3.11.3 新增了 `ts/`、`js/wavedrom/`、额外语言包，需逐个研判后并入清单）。
4. **验证**（见第三节命令）：语法检查、集成点路径存在性、CDN 字符串扫描、体积统计。
5. **文档同步**（AGENTS.md §6.3）：`assets/vendor/README.md` 资源清单版本号 + 裁剪清单；根 `README.md` 仅在硬编码版本时改；`plan.md` 仅在与蓝图表述冲突时改。
6. **单独提交**：第三方资源升级建议独立 commit，便于回溯（如「升级 Vditor 至 3.11.3 并裁剪 dist」）。

## 二、Vditor 专项注意事项

### 兼容检查清单（每次升级必查）

| 检查项 | 方法 | 不通过的影响 |
|---|---|---|
| 公式引擎匹配仍为 `=== "KaTeX"`（大小写敏感） | 在新版 `dist/index.js` 正则搜 `===\s*"KaTeX"` 与 `===\s*"katex"` | 后台 `post_edit.php` 的 `engine: 'KaTeX'` 失配，公式不渲染 |
| 懒加载默认 CDN 仍可被 `cdn`/`emojiPath` 配置覆盖 | 确认 post_edit.php 显式传入这两个参数 | 运行时触发 unpkg 外网请求，违反 §2.5 |
| `dist/css/content-theme/dark.css`、`light.css` 仍在 | 存在性检查 | 暗色预览黑底黑字 |
| `dist/js/lute/lute.min.js`、`js/katex/`、`js/highlight.js/`、`js/icons/`、`images/emoji/` 路径未变 | 存在性检查（post_edit.php 以 `index.min.js` 存在为启用开关，其余为懒加载路径） | 编辑器降级为 textarea 或子功能失效 |
| `index.min.js` 语法有效 | `node --check` | 页面 JS 报错 |

### 体积构成认知（决定裁剪取舍）

- **运行必需**：`index.min.js` + `index.css` + `js/lute/`（解析引擎，单文件 ~3.6 MB，每次初始化必载，是 Vditor 固有成本）+ `js/icons/` + `js/i18n/zh_CN.js`。
- **按需懒加载**：仅当编辑内容出现对应语法才拉取——`js/katex/`（公式）、`js/highlight.js/`（代码块）、各图表库（mermaid/markmap/echarts 等）。
- **图表库前台无渲染支持**：`core/Markdown.php` + `themes/default/js/render.js` 只处理公式与高亮，读者侧看不到图表语法渲染结果——这是裁剪图表库的关键依据。若将来要做前台图表渲染，属独立新功能，需另行本地化到 `assets/vendor/` 并扩展 render.js。
- **"不会加载"是条件性结论**：mathjax 不加载的前提是引擎配置保持 KaTeX；图表库不加载的前提是文章不写对应语法块。裁剪即放弃对应能力（可从 npm 包拷回恢复）。

## 三、常用验证命令（PowerShell）

```powershell
# 1. 体积统计
$d = 'd:\Code\AI\my-blog\assets\vendor\vditor\dist'
$s = (Get-ChildItem $d -Recurse -File | Measure-Object Length -Sum).Sum
[math]::Round($s/1MB, 2)

# 2. JS 语法检查（node --check 对 UMD/CJS 产物可用）
node --check "$d\index.min.js"

# 3. CDN 字符串全量扫描（Grep 工具对超长压缩行失效，必须用脚本）
Get-ChildItem $d -Recurse -File -Include *.js,*.css | ForEach-Object {
    $c = [IO.File]::ReadAllText($_.FullName)
    $u = [regex]::Matches($c, 'https://unpkg\.com').Count
    $j = [regex]::Matches($c, 'https://cdn\.jsdelivr\.net').Count
    if ($u -gt 0 -or $j -gt 0) { Write-Output ("{0}: unpkg={1} jsdelivr={2}" -f $_.Name, $u, $j) }
}
# 对命中的文件，用正则带上下文（.{80}URL.{120}）研判是"默认值可被配置覆盖"还是"运行时可达"

# 4. 大文件字节级核查（判断替换/编辑是否引入意外损坏）
$c = [IO.File]::ReadAllText('<文件>')
$c.Contains('<关键代码片段>')

# 5. 提取 git 历史版本做对照基线（管道会丢字节，必须用 BaseStream）
$psi = New-Object System.Diagnostics.ProcessStartInfo
$psi.FileName = 'git'; $psi.Arguments = '-C <仓库路径> show HEAD:<相对路径>'
$psi.RedirectStandardOutput = $true; $psi.UseShellExecute = $false
$p = [System.Diagnostics.Process]::Start($psi)
$ms = New-Object System.IO.MemoryStream
$p.StandardOutput.BaseStream.CopyTo($ms); $p.WaitForExit()
[IO.File]::WriteAllBytes("$env:TEMP\baseline", $ms.ToArray())
```

## 四、踩坑记录（已验证，勿重蹈）

1. **SearchReplace 编辑压缩/转义密集的 JS 会破坏语法**：工具回写时会把字符串内的转义序列（如 d3 源码 `new RegExp('["'+t+"\n\r]")` 中的 `\n\r`）解释为真实换行，导致字符串字面量断裂、`node --check` 报错，且返回的 diff 可能截断误导判断。**结论：不要对 min 文件做 SearchReplace 补丁；确需修补时，先 `git checkout --` 还原，改用字节级脚本替换，改完立即 `node --check` 并与基线做字节 diff。**
2. **Grep 工具搜不到超长压缩行**：对单行数百 KB 的 min 文件，Grep 返回 0 匹配不代表内容不存在；一律改用 `[IO.File]::ReadAllText` + `[regex]` 脚本验证。
3. **PowerShell `>` 重定向 / `Set-Content` 管道提取 git 二进制输出会损坏内容**（转 UTF-16 或丢字节）：做基线对照必须用 `ProcessStartInfo` + `BaseStream.CopyTo`。
4. **"永远不会加载"是过强表述**：懒加载资源的触发条件来自配置与内容，描述时必须写明前提条件与被打破时的后果。
5. **npm 包结构跨版本会变**：新版可能新增目录（3.11.3 的 `ts/`、`wavedrom`、新语言包），裁剪清单不能机械套用，替换后要重新全量列目录研判。
6. **编辑器残留 CDN 默认值的判定方法**：区分"默认值"与"可达路径"——Vditor 的 `Constants.CDN`、lute 的 emoji 默认路径都会被应用层显式配置覆盖，属安全残留，登记说明即可，不必（也不应）手工改 min 文件。

## 五、升级后文档同步清单

| 文档 | 更新内容 |
|---|---|
| `assets/vendor/README.md` | 资源清单版本号与来源 URL；裁剪清单变化；残留 CDN 说明 |
| `assets/vendor/UPGRADE.md`（本文件） | 新踩坑经验、新增兼容检查项 |
| 根 `README.md` | 仅当硬编码了版本号或降级行为描述变化时 |
| `AGENTS.md` | 仅当强制条款涉及的版本号/规则变化（当前 §4.6 记录 Vditor 版本） |
| `plan.md` | 仅当与蓝图表述冲突（如目录结构描述） |
