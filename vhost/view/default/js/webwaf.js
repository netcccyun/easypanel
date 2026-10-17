function webwafRequest(action, data) {
    $.ajax({
        type: 'POST',
        url: '?c=webwaf&a=' + action,
        data: data,
        success: function (message) {
            if ($.trim(message) !== '成功') {
                alert(message);
                return;
            }
            show_sync();
            window.location.reload();
        },
        error: function () { alert('操作失败，请稍后重试'); }
    });
}

function webwafSwitch(status) {
    if (confirm(status === 1 ? '确定开启Web安全防护吗？' : '确定关闭Web安全防护吗？')) {
        webwafRequest('webwafSwitch', {status: status});
    }
}
