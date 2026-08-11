/**
 * 后台导航管理：列表拖拽排序 + 动态增删导航行
 * 提交时按 DOM 顺序重编号改写 title_i/url_i/parent_i 并填写 row_total，
 * parent 值同步映射为新行号；被引用的行已移除时回退为顶层
 */
(function () {
    'use strict';

    var MAX_ITEMS = 30;
    var list = document.getElementById('nav-list');
    var form = document.getElementById('nav-form');
    if (!list || !form) {
        return;
    }
    var tpl = document.getElementById('nav-row-tpl');
    var addBtn = document.getElementById('nav-add-btn');
    var totalInput = document.getElementById('nav-row-total');
    var fallbackRow = document.getElementById('nav-new-row');
    var dragRow = null; // 当前被拖拽的行
    var newSeq = 0;     // 动态新增行的唯一标识序号

    // JS 启用后改用「添加导航项」按钮（可直接选父级），隐藏无 JS 兜底新增行
    if (fallbackRow) {
        fallbackRow.style.display = 'none';
    }
    if (addBtn) {
        addBtn.style.display = '';
    }

    function rows() {
        return list.querySelectorAll('.nav-row');
    }

    /* ---------- 拖拽排序：HTML5 DnD，仅按住 ⠿ 手柄可发起，避免干扰输入框 ---------- */

    function bindDrag(row) {
        var handle = row.querySelector('.nav-handle');
        if (handle) {
            handle.addEventListener('mousedown', function () {
                row.setAttribute('draggable', 'true');
            });
        }
        row.addEventListener('dragstart', function (e) {
            dragRow = row;
            e.dataTransfer.effectAllowed = 'move';
            try { e.dataTransfer.setData('text/plain', ''); } catch (err) { /* 部分旧内核要求先 setData */ }
            setTimeout(function () { row.classList.add('dragging'); }, 0);
        });
        row.addEventListener('dragend', function () {
            row.classList.remove('dragging');
            row.removeAttribute('draggable');
            dragRow = null;
        });
    }

    // 按住手柄未按住拖动即松开时，复位 draggable，避免残留影响输入框内文本选择
    document.addEventListener('mouseup', function () {
        var items = rows();
        for (var i = 0; i < items.length; i++) {
            items[i].removeAttribute('draggable');
        }
    });

    // 找到光标 Y 坐标处应插入其前的行（返回 null 表示放到列表末尾）
    function afterRowAt(y) {
        var candidates = list.querySelectorAll('.nav-row:not(.dragging)');
        var closest = null;
        var closestOffset = -Infinity;
        for (var i = 0; i < candidates.length; i++) {
            var box = candidates[i].getBoundingClientRect();
            var offset = y - box.top - box.height / 2;
            if (offset < 0 && offset > closestOffset) {
                closestOffset = offset;
                closest = candidates[i];
            }
        }
        return closest;
    }

    // 行 X 的当前父行（父级下拉值 → data-key）；顶层或父行不存在返回 null
    function parentRowOf(row) {
        var sel = row.querySelector('[data-field="parent"]');
        if (!sel || sel.value === '-1') {
            return null;
        }
        return list.querySelector('.nav-row[data-key="' + sel.value + '"]');
    }

    // 行 X 当前子行中 DOM 最靠前的一个（下拉值等于 X 的 data-key）；无则 null
    function firstChildRowOf(row) {
        var key = row.getAttribute('data-key');
        var items = list.querySelectorAll('.nav-row:not(.dragging)');
        for (var i = 0; i < items.length; i++) {
            var sel = items[i].querySelector('[data-field="parent"]');
            if (sel && sel.value === key) {
                return items[i];
            }
        }
        return null;
    }

    // a 是否位于 b 之前
    function isBefore(a, b) {
        if (!a || !b || a === b) {
            return false;
        }
        // DOCUMENT_POSITION_FOLLOWING = 4：b 在 a 之后
        return (a.compareDocumentPosition(b) & 4) !== 0;
    }

    list.addEventListener('dragover', function (e) {
        if (!dragRow) {
            return;
        }
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
        var after = afterRowAt(e.clientY);
        // 落点约束：子行不能拖到其父行之上、父行不能拖到其子行之下，
        // 避免保存时父子关系被服务端静默拆散
        var parent = parentRowOf(dragRow);
        if (parent && !(after === null || (after !== parent && isBefore(parent, after)))) {
            after = parent.nextElementSibling;
        }
        var firstChild = firstChildRowOf(dragRow);
        if (firstChild && !(after === firstChild || (after !== null && isBefore(after, firstChild)))) {
            after = firstChild;
        }
        if (after === dragRow) {
            after = dragRow.nextElementSibling;
        }
        if (after === null) {
            if (list.lastElementChild !== dragRow) {
                list.appendChild(dragRow);
            }
        } else if (after !== dragRow) {
            list.insertBefore(dragRow, after);
        }
    });
    list.addEventListener('drop', function (e) {
        e.preventDefault();
    });

    /* ---------- 行级操作：移除 / 父级切换时同步子项缩进与一层约束 ---------- */

    function syncChildIndent(row) {
        var sel = row.querySelector('[data-field="parent"]');
        if (sel) {
            if (sel.value !== '-1') {
                row.classList.add('nav-child');
            } else {
                row.classList.remove('nav-child');
            }
        }
    }

    // 一行成为子项后，引用它作父级的行回归顶层（仅支持一层父子）
    function cascadeDropChildren(row, sel) {
        var myKey = row.getAttribute('data-key');
        var items = rows();
        for (var k = 0; k < items.length; k++) {
            var otherSel = items[k].querySelector('[data-field="parent"]');
            if (otherSel && otherSel !== sel && otherSel.value === myKey) {
                otherSel.value = '-1';
                syncChildIndent(items[k]);
            }
        }
    }

    // 引用指定 data-key 作父级的行全部回归顶层（父行被移除/降级时调用）
    function resetRefsTo(key) {
        var items = rows();
        for (var k = 0; k < items.length; k++) {
            var otherSel = items[k].querySelector('[data-field="parent"]');
            if (otherSel && otherSel.value === key) {
                otherSel.value = '-1';
                syncChildIndent(items[k]);
            }
        }
    }

    function bindRow(row) {
        bindDrag(row);
        var del = row.querySelector('.nav-del');
        if (del) {
            del.addEventListener('click', function () {
                var key = row.getAttribute('data-key');
                row.parentNode.removeChild(row);
                // 移除后清理悬空父级引用，避免保存时才隐式回退
                resetRefsTo(key);
            });
        }
        var sel = row.querySelector('[data-field="parent"]');
        if (sel) {
            sel.addEventListener('change', function () {
                if (sel.value !== '-1') {
                    var prow = list.querySelector('.nav-row[data-key="' + sel.value + '"]');
                    var psel = prow ? prow.querySelector('[data-field="parent"]') : null;
                    if (!prow || (psel && psel.value !== '-1')) {
                        // 父行不存在/自身已是子项/选中本行：仅支持一层，回退为顶层
                        sel.value = '-1';
                    } else {
                        cascadeDropChildren(row, sel);
                    }
                }
                syncChildIndent(row);
            });
        }
        syncChildIndent(row);
    }

    var existing = rows();
    for (var i = 0; i < existing.length; i++) {
        bindRow(existing[i]);
    }

    /* ---------- 动态添加：已有顶层项时新行的父级下拉即含全部顶层行 ---------- */

    if (addBtn && tpl && tpl.content) {
        addBtn.addEventListener('click', function () {
            if (rows().length >= MAX_ITEMS) {
                var tip = '最多 ' + MAX_ITEMS + ' 个导航项';
                // 优先用 layui layer 轻提示（admin.js 暴露于 CB_ADMIN），缺失时回退 alert
                if (window.CB_ADMIN && CB_ADMIN.layer) {
                    CB_ADMIN.layer.msg(tip, { icon: 2 });
                } else {
                    alert(tip);
                }
                return;
            }
            var frag = tpl.content.cloneNode(true);
            var row = frag.querySelector('.nav-row');
            row.setAttribute('data-key', 'n' + (newSeq++));
            list.appendChild(frag);
            bindRow(row);
            var title = row.querySelector('[data-field="title"]');
            if (title) {
                title.focus();
            }
        });
    }

    /* ---------- 提交重编号：DOM 顺序即保存顺序 ---------- */

    form.addEventListener('submit', function () {
        var items = rows();
        var map = {};
        var i, j;
        for (i = 0; i < items.length; i++) {
            map[items[i].getAttribute('data-key')] = i;
        }
        for (i = 0; i < items.length; i++) {
            var fields = items[i].querySelectorAll('[data-field]');
            for (j = 0; j < fields.length; j++) {
                var el = fields[j];
                var field = el.getAttribute('data-field');
                el.name = field + '_' + i;
                if (field === 'parent') {
                    var ref = el.value;
                    el.value = (ref !== '-1' && map[ref] !== undefined) ? map[ref] : '-1';
                }
            }
        }
        if (totalInput) {
            totalInput.value = items.length;
        }
    });
})();
