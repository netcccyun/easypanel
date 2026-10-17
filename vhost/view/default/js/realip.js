function realip_header_changed(form)
{
    var custom = form.elements.header.value === '__custom__';
    document.getElementById('realip-custom-row').style.display = custom ? '' : 'none';
    form.elements.custom_header.disabled = !custom;
    form.elements.custom_header.required = custom;
}

function realip_save(form)
{
    var header = form.elements.header.value;
    if (header === '__custom__') {
        header = $.trim(form.elements.custom_header.value);
        if (!/^[!#$%&'*+.^_`|~0-9A-Za-z-]+$/.test(header)) {
            alert('请输入有效的Header名称，不要包含冒号、空格或换行');
            form.elements.custom_header.focus();
            return false;
        }
    }
    var button = form.querySelector('[type="submit"]');
    button.disabled = true;
    $.ajax({
        type: 'POST',
        url: form.action,
        data: {status: header === '' ? '0' : '1', header: header},
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
