(function ($) {
    'use strict';
    var activeJob = null;
    var titles = {
        wget: '远程下载', bakupweb: '网站备份', mysqldumpout: '数据库导出',
        mysqldumpin: '数据库导入', remotemysqldumpin: '远程数据库导入', restoreweb: '网站恢复'
    };
    var requiredFields = {
        wget: [['url', '请输入下载地址'], ['filename', '请输入存放文件名']],
        mysqldumpout: [['passwd', '请输入数据库密码']],
        mysqldumpin: [['file', '请选择导入文件'], ['passwd', '请输入数据库密码']],
        remotemysqldumpin: [
            ['rehost', '请输入远程数据库IP'], ['reuser', '请输入远程数据库账号'],
            ['repasswd', '请输入远程数据库密码'], ['redate', '请输入远程数据库名'],
            ['passwd', '请输入本地数据库密码']
        ],
        restoreweb: [['file', '请选择要恢复的备份文件']]
    };

    $(document).on('submit', 'form[data-shell-command]', function (event) {
        event.preventDefault();
        if (activeJob) {
            layer.msg('已有任务正在执行，请等待完成', {icon: 0});
            return;
        }
        var form = $(this), url = form.attr('action');
        var match = /[?&]a=([a-z]+)/i.exec(url || '');
        if (!match || !titles[match[1]]) return;
        var fields = requiredFields[match[1]] || [];
        for (var i = 0; i < fields.length; i++) {
            var input = form.find('[name="' + fields[i][0] + '"]');
            if (!$.trim(input.val() || '')) {
                layer.msg(fields[i][1], {icon: 0});
                var focus = input.is(':visible') ? input : form.find('input:not([type="hidden"])').first();
                focus.trigger('focus');
                return;
            }
        }
        // Serialize before disabling controls, including database passwords.
        var data = form.serialize(), title = titles[match[1]];
        var buttons = form.find(':submit').filter(':enabled');
        var panelHtml = '<div class="ep-shell-panel"><pre class="ep-shell-output" role="log" aria-label="命令执行输出"></pre><div class="ep-shell-status" role="status"><img class="ep-shell-loading" src="/style/busy.gif" alt="" aria-hidden="true"><span class="ep-shell-status-text"></span></div></div>';
        var output, status, loading;
        var job = {session: '', timer: null, running: true, failures: 0, success: false};
        activeJob = job;
        buttons.prop('disabled', true);

        function append(text) {
            if (!text) return;
            output.appendChild(document.createTextNode(text));
            output.scrollTop = output.scrollHeight;
        }

        function finish(success, message) {
            job.running = false;
            job.success = success;
            clearTimeout(job.timer);
            if (activeJob === job) activeJob = null;
            buttons.prop('disabled', false);
            loading.hide();
            status.text(message);
            append('\n' + message + '\n');
            layer.msg(title + '：' + message, {icon: success ? 1 : 2, time: success ? 3000 : 6000});
        }

        function receive(result) {
            if (!result || result.code === undefined) {
                finish(false, '服务器返回异常，请重新登录后检查任务结果');
                return;
            }
            append(result.out);
            var code = Number(result.code);
            if (code === 201) {
                if (result.session) job.session = result.session;
                if (!job.session) {
                    finish(false, '未返回任务编号，无法查询执行状态');
                    return;
                }
                status.text('正在执行…');
                job.timer = setTimeout(query, 1000);
            } else if (code === 200) {
                var errno = Number(result.errno || 0);
                finish(errno === 0, errno === 0 ? '执行完成' : '执行失败（错误码：' + errno + '）');
            } else {
                finish(false, result.msg || '执行错误（状态码：' + result.code + '）');
            }
        }

        function query() {
            if (!job.running) return;
            $.ajax({
                url: '?c=shell&a=query', type: 'GET', data: {session: job.session},
                dataType: 'json', cache: false, timeout: 30000,
                success: function (result) { job.failures = 0; receive(result); },
                error: function () {
                    if (++job.failures < 3) {
                        status.text('状态查询失败，正在重试…');
                        job.timer = setTimeout(query, 2000);
                    } else {
                        finish(false, '状态查询中断，任务可能仍在后台执行，请勿重复提交');
                    }
                }
            });
        }

        layer.open({
            type: 1, title: title + ' - 命令执行状态', skin: 'ep-shell-dialog',
            area: [Math.max(240, Math.min(760, $(window).width() - 30)) + 'px',
                Math.max(200, Math.min(480, $(window).height() - 30)) + 'px'],
            shadeClose: false, content: panelHtml,
            cancel: function () {
                if (job.running) {
                    layer.msg('任务正在执行，请等待完成后关闭窗口', {icon: 0});
                    return false;
                }
            },
            end: function () {
                clearTimeout(job.timer);
                // Refresh the file list only after the user has reviewed the log.
                if (job.success && typeof window.refresh === 'function') window.refresh();
            },
            success: function (layero) {
                output = layero.find('.ep-shell-output')[0];
                status = layero.find('.ep-shell-status-text');
                loading = layero.find('.ep-shell-loading');
                // File-manager parameter dialogs are no longer needed after submission.
                if (window.art && art.dialog && art.dialog.list.id22) art.dialog.list.id22.close();
                status.text('正在提交任务…');
                $.ajax({
                    url: url, type: 'POST', data: data, dataType: 'json', timeout: 60000,
                    success: receive,
                    error: function () {
                        finish(false, '请求失败或登录已过期，请检查任务结果后再操作');
                    }
                });
            }
        });
    });
})(jQuery);
