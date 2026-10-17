function realip_save(form)
{
    var button = form.querySelector('[type="submit"]');
    button.disabled = true;
    $.ajax({
        type: 'POST',
        url: form.action,
        data: {status: form.elements.header.value === '' ? '0' : '1', header: form.elements.header.value},
        success: function (message) {
            if (message !== '成功') {
                alert(message);
                return;
            }
            window.location.reload();
        },
        error: function () { alert('保存设置失败，请重试'); },
        complete: function () { button.disabled = false; }
    });
    return false;
}
