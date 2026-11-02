(function ($) {
    'use strict';
    var activeJob = null;
    var STORE_KEY = 'epShellJob';
    var STORE_TTL = 12 * 60 * 60 * 1000;
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
    var panelHtml = '<div class="ep-shell-panel"><pre class="ep-shell-output" role="log" aria-label="命令执行输出"></pre><div class="ep-shell-status" role="status"><img class="ep-shell-loading" src="/style/busy.gif" alt="" aria-hidden="true"><span class="ep-shell-status-text"></span></div></div>';

    // 任务编号存本地，关窗或刷新页面后仍能继续跟踪进度
    function loadSaved() {
        try {
            var saved = JSON.parse(localStorage.getItem(STORE_KEY) || 'null');
            if (!saved || !saved.session) return null;
            if (saved.time && Date.now() - saved.time > STORE_TTL) {
                localStorage.removeItem(STORE_KEY);
                return null;
            }
            return saved;
        } catch (e) {
            return null;
        }
    }

    function saveJob(job) {
        try {
            localStorage.setItem(STORE_KEY, JSON.stringify({session: job.session, title: job.title, time: Date.now()}));
        } catch (e) { }
    }

    function clearSaved() {
        try { localStorage.removeItem(STORE_KEY); } catch (e) { }
    }

    // 状态栏小徽标：任务在后台跑时提供入口，点开查看进度
    function showChip(job) {
        if (job.chip || !job.running) return;
        var chip = $('<a class="ep-task-chip" href="javascript:;"><i class="ep-task-chip-spin"></i><span></span></a>');
        chip.find('span').text(job.title + ' 执行中');
        chip.on('click', function () {
            if (!job.opened) openPanel(job);
        });
        var slot = document.getElementById('epShellTaskSlot');
        if (slot && !slot.firstChild) {
            chip.addClass('is-inline');
            slot.appendChild(chip[0]);
        } else {
            chip.addClass('is-floating');
            document.body.appendChild(chip[0]);
        }
        job.chip = chip;
    }

    function hideChip(job) {
        if (!job.chip) return;
        job.chip.remove();
        job.chip = null;
    }

    function append(job, text) {
        if (!text) return;
        job.log.push(text);
        if (!job.output) return;
        job.output.appendChild(document.createTextNode(text));
        job.output.scrollTop = job.output.scrollHeight;
    }

    function finish(job, success, message) {
        job.running = false;
        job.success = success;
        clearTimeout(job.timer);
        clearSaved();
        hideChip(job);
        if (activeJob === job) activeJob = null;
        if (job.buttons) job.buttons.prop('disabled', false);
        if (job.loading) job.loading.hide();
        if (job.status) job.status.text(message);
        append(job, '\n' + message + '\n');
        layer.msg(job.title + '：' + message, {icon: success ? 1 : 2, time: success ? 3000 : 6000});
        // 窗口已关闭时由后台轮询收尾，直接刷新文件列表
        if (job.detached && success && typeof window.refresh === 'function') window.refresh();
    }

    function receive(job, result) {
        if (!result || result.code === undefined) {
            finish(job, false, '服务器返回异常，请重新登录后检查任务结果');
            return;
        }
        append(job, result.out);
        var code = Number(result.code);
        if (code === 201) {
            if (result.session) job.session = result.session;
            if (!job.session) {
                finish(job, false, '未返回任务编号，无法查询执行状态');
                return;
            }
            saveJob(job);
            showChip(job);
            if (job.status) job.status.text('正在执行…');
            job.timer = setTimeout(function () { query(job); }, 1000);
        } else if (code === 200) {
            var errno = Number(result.errno || 0);
            finish(job, errno === 0, errno === 0 ? '执行完成' : '执行失败（错误码：' + errno + '）');
        } else {
            finish(job, false, result.msg || '执行错误（状态码：' + result.code + '）');
        }
    }

    function query(job) {
        if (!job.running) return;
        $.ajax({
            url: '?c=shell&a=query', type: 'GET', data: {session: job.session},
            dataType: 'json', cache: false, timeout: 30000,
            success: function (result) { job.failures = 0; receive(job, result); },
            error: function () {
                if (++job.failures < 3) {
                    if (job.status) job.status.text('状态查询失败，正在重试…');
                    job.timer = setTimeout(function () { query(job); }, 2000);
                } else {
                    finish(job, false, '状态查询中断，任务可能仍在后台执行，请勿重复提交');
                }
            }
        });
    }

    function openPanel(job, submit) {
        job.opened = true;
        layer.open({
            type: 1, title: job.title + ' - 命令执行状态', skin: 'ep-shell-dialog',
            area: [Math.max(240, Math.min(760, $(window).width() - 30)) + 'px',
                Math.max(200, Math.min(480, $(window).height() - 30)) + 'px'],
            shadeClose: false, content: panelHtml,
            // 允许关闭：任务在服务端继续执行，点状态栏徽标可重新打开
            end: function () {
                job.opened = false;
                if (job.running) {
                    job.detached = true;
                    showChip(job);
                    return;
                }
                clearTimeout(job.timer);
                // Refresh the file list only after the user has reviewed the log.
                if (job.success && typeof window.refresh === 'function') window.refresh();
            },
            success: function (layero) {
                job.output = layero.find('.ep-shell-output')[0];
                job.status = layero.find('.ep-shell-status-text');
                job.loading = layero.find('.ep-shell-loading');
                job.output.textContent = job.log.join('');
                job.output.scrollTop = job.output.scrollHeight;
                // File-manager parameter dialogs are no longer needed after submission.
                if (window.art && art.dialog && art.dialog.list.id22) art.dialog.list.id22.close();
                if (job.session) {
                    job.status.text('正在执行…');
                    if (!job.running) {
                        job.running = true;
                        job.failures = 0;
                        job.timer = setTimeout(function () { query(job); }, 300);
                    }
                } else if (submit) {
                    job.status.text('正在提交任务…');
                    submit(job);
                }
            }
        });
    }

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
        buttons.prop('disabled', true);
        var job = {session: '', title: title, buttons: buttons, running: true, success: false,
            detached: false, failures: 0, timer: null, log: [], output: null, status: null,
            loading: null, opened: false, chip: null};
        openPanel(job, function (job) {
            $.ajax({
                url: url, type: 'POST', data: data, dataType: 'json', timeout: 60000,
                success: function (result) { receive(job, result); },
                error: function () {
                    finish(job, false, '请求失败或登录已过期，请检查任务结果后再操作');
                }
            });
        });
    });

    // 刷新或切换目录后，用状态栏徽标继续跟踪未完成的任务
    $(function () {
        if (activeJob) return;
        var saved = loadSaved();
        if (!saved) return;
        var job = {session: saved.session, title: saved.title || '任务', buttons: null, running: true,
            success: false, detached: true, failures: 0, timer: null, log: [], output: null,
            status: null, loading: null, opened: false, chip: null};
        activeJob = job;
        showChip(job);
        job.timer = setTimeout(function () { query(job); }, 500);
    });
})(jQuery);
