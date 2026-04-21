/* ssr-modal.js - 增强版：监听 .btn-ssr / .btn-SSR 等，兼容多种 data-* 命名与 DOM 结构 */
(function(window, $) {
    'use strict';

    function base64_encode(str) {
        try {
            return btoa(unescape(encodeURIComponent(str))).replace(/=+$/, '');
        } catch (e) {
            return btoa(str).replace(/=+$/, '');
        }
    }

    function genQRCodeIntoElement(el, text) {
        if (!el) return false;
        if (typeof window.QRCode === 'function') {
            try {
                el.innerHTML = '';
                new window.QRCode(el, { text: text, width: 220, height: 220 });
                return true;
            } catch (e) {
                console.error('QRCode constructor 生成失败', e);
            }
        }
        try {
            if ($ && $.fn && typeof $.fn.qrcode === 'function') {
                $(el).empty();
                $(el).qrcode({ text: text, width: 220, height: 220 });
                return true;
            }
        } catch (e) {
            console.error('jQuery.fn.qrcode 生成失败', e);
        }
        return false;
    }

    function getFirstNonEmpty() {
        for (var i = 0; i < arguments.length; i++) {
            var v = arguments[i];
            if (typeof v !== 'undefined' && v !== null && String(v) !== '') return v;
        }
        return '';
    }

    function readNodeDataFromElement($el) {
        var host = getFirstNonEmpty($el.data('host'), $el.data('server'), $el.data('node-ip'), $el.data('node_ip'), $el.data('node'), $el.data('nodeid'), $el.data('id'));
        var port = getFirstNonEmpty($el.data('port'), $el.data('node-port'), $el.data('node_port'));
        var passwd = getFirstNonEmpty($el.data('passwd'), $el.data('password'));
        var method = getFirstNonEmpty($el.data('method'), $el.data('enc'), $el.data('cipher'));
        var protocol = getFirstNonEmpty($el.data('protocol'), $el.data('proto'));
        var obfs = getFirstNonEmpty($el.data('obfs'), $el.data('obfsname'));
        var protoParam = getFirstNonEmpty($el.data('proto-param'), $el.data('protocol_param'), $el.data('protoparam'));
        var obfsParam = getFirstNonEmpty($el.data('obfs-param'), $el.data('obfs_param'), $el.data('obfsparam'));
        var name = getFirstNonEmpty($el.data('name'), $el.data('node-name'), $el.data('remarks'));
        return {
            host: host,
            port: port,
            passwd: passwd,
            method: method,
            protocol: protocol,
            obfs: obfs,
            protoParam: protoParam,
            obfsParam: obfsParam,
            name: name
        };
    }

    // 绑定点击：支持多类触发器
    $(document).on('click', '.show-ssr-modal, .btn-ssr, .btn-SSR, [data-target="#nodeinfo-ssr-modal"], [data-target="#ssrModal"]', function(e) {
        var $btn = $(this);

        // 读取触发元素数据
        var node = readNodeDataFromElement($btn);

        // 如果不足，从父级或行内寻找 data 或隐藏 input
        if (!node.host || !node.port || !node.passwd || !node.method) {
            var $parent = $btn.closest('[data-host], [data-server], .node-item, .node-row, tr, li');
            if ($parent && $parent.length) {
                var more = readNodeDataFromElement($parent);
                node.host = node.host || more.host;
                node.port = node.port || more.port;
                node.passwd = node.passwd || more.passwd;
                node.method = node.method || more.method;
                node.protocol = node.protocol || more.protocol;
                node.obfs = node.obfs || more.obfs;
                node.protoParam = node.protoParam || more.protoParam;
                node.obfsParam = node.obfsParam || more.obfsParam;
                node.name = node.name || more.name;
            }
        }

        // 再尝试隐藏 input 查找（common patterns）
        if ((!node.host || !node.port || !node.passwd || !node.method) && $btn.closest('tr').length) {
            var $row = $btn.closest('tr');
            node.host = node.host || $row.find('input[name="host"]').val() || $row.data('host');
            node.port = node.port || $row.find('input[name="port"]').val() || $row.data('port');
            node.passwd = node.passwd || $row.find('input[name="passwd"]').val() || $row.data('passwd');
            node.method = node.method || $row.find('input[name="method"]').val() || $row.data('method');
        }

        if (!node.host || !node.port || !node.passwd || !node.method) {
            $('#nodeinfo-ssr-modal #ssr_modal_body, #ssrModal #ssr_modal_body').html('<div class="alert alert-warning">节点信息不完整（缺 host/port/method/passwd），无法生成 SSR 链接。请在模板中添加 data-host/data-port/data-method/data-passwd。</div>');
            if (typeof $.fn.modal === 'function') {
                if ($('#nodeinfo-ssr-modal').length) $('#nodeinfo-ssr-modal').modal('show');
                else if ($('#ssrModal').length) $('#ssrModal').modal('show');
            }
            return;
        }

        var pwdBase64 = base64_encode(node.passwd);
        var params = [];
        if (node.obfsParam) params.push('obfsparam=' + base64_encode(node.obfsParam));
        if (node.protoParam) params.push('protoparam=' + base64_encode(node.protoParam));
        if (node.name) params.push('remarks=' + base64_encode(node.name));
        var paramStr = params.length ? ('/?' + params.join('&')) : '/';
        var proto = node.protocol || 'origin';
        var ob = node.obfs || 'plain';
        var target = node.host + ':' + node.port + ':' + proto + ':' + node.method + ':' + ob + ':' + pwdBase64 + paramStr;
        var ssrUri = 'ssr://' + base64_encode(target);

        // 填充 textarea（支持多个 modal id）
        var manualSel = $('#nodeinfo-ssr-modal #ssr_manual_textarea, #ssrModal #ssr_manual_textarea, #ssr_manual_textarea');
        if (manualSel.length) manualSel.val(ssrUri).text(ssrUri);

        // 找二维码容器
        var el = document.querySelector('#nodeinfo-ssr-modal #ssr_qrcode') || document.querySelector('#ssrModal #ssr_qrcode') || document.querySelector('#ssr_qrcode') || document.querySelector('.ssr-qrcode');
        if (!el) {
            console.warn('未找到 #ssr_qrcode 容器，请在 modal 中添加 id="ssr_qrcode" 的元素');
        } else {
            var ok = genQRCodeIntoElement(el, ssrUri);
            if (!ok) {
                el.innerHTML = '<div class="text-danger">二维码生成失败，请检查二维码库是否加载（Console 可能有错误）。</div>';
            }
        }

        // 显示 modal
        if (typeof $.fn.modal === 'function') {
            if ($('#nodeinfo-ssr-modal').length) $('#nodeinfo-ssr-modal').modal('show');
            else if ($('#ssrModal').length) $('#ssrModal').modal('show');
            else if ($('#ssr_modal_body').length) $('#ssr_modal_body').closest('.modal').modal('show');
        } else {
            try { (el || document.body).scrollInto
