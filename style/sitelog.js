(function ($) {
    $(function () {
        var button = $('#site-log-refresh');
        var output = $('#site-log-output');
        var status = $('#site-log-status');
        var wrap = $('#site-log-wrap');
        var loaded = false;
        if (!button.length || !output.length) { return; }
        function updateWrapping() {
            output.toggleClass('site-log-wrap', wrap.prop('checked'));
            output[0].scrollTop = output[0].scrollHeight;
        }
        function refresh() {
            button.prop('disabled', true).text('正在刷新…');
            status.text('正在读取日志…');
            $.ajax({
                url: '?c=sitelog&a=read',
                type: 'GET',
                dataType: 'text',
                cache: false,
                timeout: 15000,
                success: function (text) {
                    loaded = true;
                    output.text(text || '暂无访问日志');
                    output[0].scrollTop = output[0].scrollHeight;
                    status.text('显示最新500行');
                },
                error: function (xhr) {
                    status.text(xhr.status === 403 ? '站点未开启日志独立功能或无法读取日志' : '刷新失败，请重试');
                    if (!loaded) { output.text('日志读取失败，请点击上方按钮重试。'); }
                },
                complete: function () {
                    button.prop('disabled', false).text('刷新日志');
                }
            });
        }
        button.on('click', refresh);
        wrap.on('change', updateWrapping);
        updateWrapping();
        refresh();
    });
})(jQuery);
