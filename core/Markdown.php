<?php
/**
 * Markdown→HTML 轻量解析 + 基于 DOMDocument 的标签/属性白名单 XSS 过滤
 * 数学公式 $...$/$$...$$ 转为占位容器交由前端本地化 KaTeX 渲染
 *
 * 安全提示：
 * 1. span/div/code/pre 允许任意 class 值，建议限制白名单（如 ^language-[\w-]+$）
 * 2. cleanNode 递归解包未知标签，建议添加深度上限（如 10 层）防 DoS
 */
defined('APP_BOOT') or exit;

class Markdown
{
    /** @var array 代码块暂存 */
    private $codeBlocks = array();

    /** @var array 公式暂存 */
    private $mathBlocks = array();

    /**
     * 渲染入口：Markdown 原文 → 过滤后的安全 HTML
     *
     * @param string $markdown 原文
     * @return string
     */
    public static function render($markdown)
    {
        $instance = new self();
        return $instance->parse((string) $markdown);
    }

    /**
     * 解析主流程
     */
    private function parse($markdown)
    {
        $text = str_replace(array("\r\n", "\r"), "\n", $markdown);

        // 1. 先提取围栏代码块，避免其中内容被解析
        $text = preg_replace_callback('/^```([^\n`]*)\n([\s\S]*?)^```\s*$/m', array($this, 'stashCode'), $text);

        // 2. 提取数学公式占位（块级 $$...$$ 与行内 $...$）
        $text = preg_replace_callback('/\$\$([\s\S]+?)\$\$/', array($this, 'stashBlockMath'), $text);
        $text = preg_replace_callback('/(?<![\$\\\\])\$([^\$\n]+?)\$(?!\$)/', array($this, 'stashInlineMath'), $text);

        // 3. 块级解析
        $html = $this->parseBlocks($text);

        // 4. 服务端白名单过滤
        $html = self::sanitize($html);

        // 5. 还原公式占位（内容已转义）
        $html = $this->restoreMath($html);

        return $html;
    }

    /** 围栏代码块暂存 */
    private function stashCode($m)
    {
        $lang = trim($m[1]);
        $this->codeBlocks[] = array('lang' => $lang, 'code' => $m[2]);
        $index = count($this->codeBlocks) - 1;
        return "\n@@CODEBLOCK" . $index . "@@\n";
    }

    /** 块级公式暂存 */
    private function stashBlockMath($m)
    {
        $this->mathBlocks[] = array('block' => true, 'tex' => $m[1]);
        $index = count($this->mathBlocks) - 1;
        return "\n@@MATHBLOCK" . $index . "@@\n";
    }

    /** 行内公式暂存 */
    private function stashInlineMath($m)
    {
        $this->mathBlocks[] = array('block' => false, 'tex' => $m[1]);
        $index = count($this->mathBlocks) - 1;
        return '@@MATHBLOCK' . $index . '@@';
    }

    /**
     * 块级结构解析（按行扫描）
     */
    private function parseBlocks($text)
    {
        $lines = explode("\n", $text);
        $html = '';
        $paragraph = array();
        $listStack = array();
        $inQuote = false;
        $quoteBuf = array();
        $tableBuf = array();

        $flushParagraph = function () use (&$paragraph, &$html) {
            if (!empty($paragraph)) {
                $html .= '<p>' . $this->parseInline(implode("\n", $paragraph)) . '</p>';
                $paragraph = array();
            }
        };
        $flushQuote = function () use (&$inQuote, &$quoteBuf, &$html) {
            if ($inQuote) {
                $html .= '<blockquote><p>' . $this->parseInline(implode(' ', $quoteBuf)) . '</p></blockquote>';
                $inQuote = false;
                $quoteBuf = array();
            }
        };
        $closeLists = function () use (&$listStack, &$html) {
            while (!empty($listStack)) {
                $html .= array_pop($listStack) === 'ul' ? '</li></ul>' : '</li></ol>';
            }
        };

        $total = count($lines);
        for ($i = 0; $i < $total; $i++) {
            $line = $lines[$i];

            // 表格（当前行含 | 且下一行为分隔行）
            if (strpos($line, '|') !== false && isset($lines[$i + 1])
                && preg_match('/^\s*\|?[\s:|-]+\|?\s*$/', $lines[$i + 1])
                && strpos($lines[$i + 1], '-') !== false) {
                $flushParagraph();
                $tableBuf = array($line);
                $i++;
                while (isset($lines[$i + 1]) && strpos($lines[$i + 1], '|') !== false) {
                    $i++;
                    $tableBuf[] = $lines[$i];
                }
                $html .= $this->parseTable($tableBuf);
                continue;
            }

            // 代码块占位行
            if (preg_match('/^@@CODEBLOCK(\d+)@@$/', trim($line), $m)) {
                $flushParagraph();
                $html .= $this->renderCodeBlock((int) $m[1]);
                continue;
            }
            // 公式占位行：块级 $$ 占位输出 div；单独成行的行内 $ 占位仍按行内处理（包 p），
            // 若包 div 会形成 div.tex-block > span.tex-inline 嵌套，前端会对同一公式渲染两次
            if (preg_match('/^@@MATHBLOCK(\d+)@@$/', trim($line), $m)) {
                $flushParagraph();
                $mathIndex = (int) $m[1];
                if (isset($this->mathBlocks[$mathIndex]) && $this->mathBlocks[$mathIndex]['block']) {
                    $html .= '<div class="tex-block">@@MATHBLOCK' . $mathIndex . '@@</div>';
                } else {
                    $html .= '<p>@@MATHBLOCK' . $mathIndex . '@@</p>';
                }
                continue;
            }

            // 引用
            if (preg_match('/^>\s?(.*)$/', $line, $m)) {
                $flushParagraph();
                $inQuote = true;
                $quoteBuf[] = $m[1];
                continue;
            }
            $flushQuote();

            // 标题
            if (preg_match('/^(#{1,6})\s+(.+)$/', $line, $m)) {
                $flushParagraph();
                $closeLists();
                $level = strlen($m[1]);
                $html .= '<h' . $level . '>' . $this->parseInline($m[2]) . '</h' . $level . '>';
                continue;
            }

            // 分隔线
            if (preg_match('/^\s*(-{3,}|\*{3,}|_{3,})\s*$/', $line)) {
                $flushParagraph();
                $html .= '<hr>';
                continue;
            }

            // 无序列表
            if (preg_match('/^\s*[-*+]\s+(.+)$/', $line, $m)) {
                $flushParagraph();
                if (empty($listStack) || end($listStack) !== 'ul') {
                    $closeLists();
                    $html .= '<ul><li>';
                    $listStack[] = 'ul';
                } else {
                    $html .= '</li><li>';
                }
                $html .= $this->parseInline($m[1]);
                continue;
            }

            // 有序列表
            if (preg_match('/^\s*\d+\.\s+(.+)$/', $line, $m)) {
                $flushParagraph();
                if (empty($listStack) || end($listStack) !== 'ol') {
                    $closeLists();
                    $html .= '<ol><li>';
                    $listStack[] = 'ol';
                } else {
                    $html .= '</li><li>';
                }
                $html .= $this->parseInline($m[1]);
                continue;
            }
            $closeLists();

            // 空行分段
            if (trim($line) === '') {
                $flushParagraph();
                continue;
            }

            $paragraph[] = $line;
        }

        $flushParagraph();
        $flushQuote();
        $closeLists();

        return $html;
    }

    /**
     * 行内语法解析（先整体转义再替换安全标签）
     */
    private function parseInline($text)
    {
        // 行内代码优先保护
        $codes = array();
        $text = preg_replace_callback('/`([^`]+)`/', function ($m) use (&$codes) {
            $codes[] = '<code>' . e($m[1]) . '</code>';
            return '@@INLINECODE' . (count($codes) - 1) . '@@';
        }, $text);

        $text = e($text);

        // 图片
        $text = preg_replace_callback('/!\[([^\]]*)\]\(([^)\s]+)\)/', array($this, 'renderImage'), $text);
        // 链接
        $text = preg_replace_callback('/\[([^\]]+)\]\(([^)\s]+)\)/', array($this, 'renderLink'), $text);
        // 粗体/斜体/删除线
        $text = preg_replace('/\*\*([^\*]+)\*\*/', '<strong>$1</strong>', $text);
        $text = preg_replace('/__([^_]+)__/', '<strong>$1</strong>', $text);
        $text = preg_replace('/\*([^\*]+)\*/', '<em>$1</em>', $text);
        $text = preg_replace('/~~([^~]+)~~/', '<del>$1</del>', $text);
        // 换行
        $text = str_replace("\n", '<br>', $text);

        // 还原行内代码
        $text = preg_replace_callback('/@@INLINECODE(\d+)@@/', function ($m) use ($codes) {
            return $codes[(int) $m[1]];
        }, $text);

        return $text;
    }

    /** 图片渲染（URL 协议校验） */
    private function renderImage($m)
    {
        $url = html_entity_decode($m[2], ENT_QUOTES, 'UTF-8');
        if (!$this->isSafeUrl($url)) {
            return $m[1];
        }
        return '<img src="' . e($url) . '" alt="' . e($m[1]) . '">';
    }

    /** 链接渲染（URL 协议校验） */
    private function renderLink($m)
    {
        $url = html_entity_decode($m[2], ENT_QUOTES, 'UTF-8');
        if (!$this->isSafeUrl($url)) {
            return $m[1];
        }
        return '<a href="' . e($url) . '" rel="noopener">' . $m[1] . '</a>';
    }

    /** URL 安全校验（委托静态版，保持单一判定逻辑） */
    private function isSafeUrl($url)
    {
        return self::isSafeUrlStatic($url);
    }

    /** 围栏代码块渲染 */
    private function renderCodeBlock($index)
    {
        if (!isset($this->codeBlocks[$index])) {
            return '';
        }
        $block = $this->codeBlocks[$index];
        $cls = $block['lang'] !== '' ? ' class="language-' . e($block['lang']) . '"' : '';
        return '<pre><code' . $cls . '>' . e(rtrim($block['code'], "\n")) . '</code></pre>';
    }

    /** 简易表格解析 */
    private function parseTable(array $rows)
    {
        $splitRow = function ($row) {
            $row = trim($row);
            if (strpos($row, '|') === 0) {
                $row = substr($row, 1);
            }
            if (substr($row, -1) === '|') {
                $row = substr($row, 0, -1);
            }
            return array_map('trim', explode('|', $row));
        };

        $headCells = $splitRow($rows[0]);
        // 外层滚动容器：宽表格在窄屏下横向滑动而不撑破页面
        $html = '<div class="table-wrap"><table><thead><tr>';
        foreach ($headCells as $cell) {
            $html .= '<th>' . $this->parseInline($cell) . '</th>';
        }
        $html .= '</tr></thead><tbody>';
        // rows[0] 为表头；分隔行在 parseBlocks 收集阶段已排除，数据行自索引 1 开始
        $rowTotal = count($rows);
        for ($i = 1; $i < $rowTotal; $i++) {
            $cells = $splitRow($rows[$i]);
            $html .= '<tr>';
            foreach ($cells as $cell) {
                $html .= '<td>' . $this->parseInline($cell) . '</td>';
            }
            $html .= '</tr>';
        }
        return $html . '</tbody></table></div>';
    }

    /** 还原公式占位为前端 KaTeX 容器 */
    private function restoreMath($html)
    {
        foreach ($this->mathBlocks as $index => $math) {
            $tag = '@@MATHBLOCK' . $index . '@@';
            // 块级公式不再包 div：parseBlocks 已为占位行输出带 class 的包裹层，
            // 重复包裹会产生嵌套 .tex-block，导致前端重复渲染
            $replacement = $math['block']
                ? e($math['tex'])
                : '<span class="tex-inline">' . e($math['tex']) . '</span>';
            $html = str_replace($tag, $replacement, $html);
        }
        return $html;
    }

    /**
     * DOMDocument 白名单过滤：剔除危险标签，剥离非法属性
     *
     * @param string $html 待过滤 HTML
     * @return string
     */
    public static function sanitize($html)
    {
        if (trim($html) === '') {
            return '';
        }
        $allowed = array(
            'p', 'br', 'hr', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
            'blockquote', 'pre', 'code', 'ul', 'ol', 'li',
            'em', 'strong', 'b', 'i', 'del', 's', 'sup', 'sub',
            'table', 'thead', 'tbody', 'tr', 'th', 'td',
            'a', 'img', 'span', 'div', 'figure', 'figcaption',
        );
        $allowedAttrs = array(
            'a'   => array('href', 'title', 'rel'),
            'img' => array('src', 'alt', 'title'),
            'code' => array('class'),
            'pre' => array('class'),
            'span' => array('class'),
            'div' => array('class'),
        );

        $doc = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        // 声明编码避免中文被转实体；按片段加载
        $doc->loadHTML(
            '<?xml encoding="utf-8"?><div id="cb-root">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();

        $root = $doc->getElementById('cb-root');
        if ($root === null) {
            return '';
        }
        self::cleanNode($root, $allowed, $allowedAttrs);

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $doc->saveHTML($child);
        }
        return $out;
    }

    /**
     * 递归清理节点：危险标签整体移除，未知标签解包保留文本，属性白名单过滤
     */
    private static function cleanNode($node, array $allowed, array $allowedAttrs)
    {
        $dangerous = array('script', 'style', 'iframe', 'object', 'embed', 'form',
            'input', 'button', 'textarea', 'select', 'link', 'meta', 'base', 'video', 'audio');

        $children = array();
        foreach ($node->childNodes as $child) {
            $children[] = $child;
        }
        foreach ($children as $child) {
            if ($child->nodeType === XML_ELEMENT_NODE) {
                $tag = strtolower($child->nodeName);
                if (in_array($tag, $dangerous, true)) {
                    $node->removeChild($child);
                    continue;
                }
                if (!in_array($tag, $allowed, true)) {
                    // 未知标签：先处理其后代，再解包为文本/子节点
                    self::cleanNode($child, $allowed, $allowedAttrs);
                    while ($child->firstChild) {
                        $node->insertBefore($child->firstChild, $child);
                    }
                    $node->removeChild($child);
                    continue;
                }
                // 属性白名单
                $keep = isset($allowedAttrs[$tag]) ? $allowedAttrs[$tag] : array();
                $removeAttrs = array();
                foreach ($child->attributes as $attr) {
                    if (!in_array(strtolower($attr->nodeName), $keep, true)) {
                        $removeAttrs[] = $attr->nodeName;
                    }
                }
                foreach ($removeAttrs as $name) {
                    $child->removeAttribute($name);
                }
                // URL 属性协议复检
                foreach (array('href', 'src') as $urlAttr) {
                    if ($child->hasAttribute($urlAttr)) {
                        $url = $child->getAttribute($urlAttr);
                        if (!self::isSafeUrlStatic($url)) {
                            $child->removeAttribute($urlAttr);
                        }
                    }
                }
                self::cleanNode($child, $allowed, $allowedAttrs);
            } elseif ($child->nodeType === XML_PI_NODE || $child->nodeType === XML_COMMENT_NODE) {
                $node->removeChild($child);
            }
        }
    }

    /**
     * 静态版 URL 安全校验：仅允许相对路径与 http(s) 绝对地址
     * 浏览器解析 URL 前会先百分号解码、再剔除首尾及协议段内的空白与 C0 控制字符，
     * 因此判定前必须做同样规范化，否则 " javascript:"、"%20javascript:"、"java\tscript:"
     * 等变体可绕过位置 0 的协议黑名单在前台执行脚本
     */
    private static function isSafeUrlStatic($url)
    {
        $probe = rawurldecode((string) $url);
        $probe = preg_replace('/[\x00-\x20\x7f]+/', '', $probe);
        if ($probe === '' || stripos($probe, 'javascript:') === 0 || stripos($probe, 'data:') === 0
            || stripos($probe, 'vbscript:') === 0) {
            return false;
        }
        if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $probe)) {
            return (bool) preg_match('#^https?://#i', $probe);
        }
        return true;
    }
}
