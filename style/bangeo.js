(function ($) {
    'use strict';
    $(function () {
        var panel = $('#bangeo-panel');
        if (!panel.length) return;
        var tree = {}, continent = $('#bangeo-continent'), country = $('#bangeo-country'), province = $('#bangeo-province');
        var hint = $('#bangeo-data-status'), add = $('#bangeo-add');

        function fill(select, values, label) {
            select.empty().append($('<option>').val('').text(label));
            $.each(values, function (_, value) { select.append($('<option>').val(value).text(value)); });
            select.prop('disabled', values.length === 0);
        }

        function request(action, data, onError) {
            var loading = layer.load(2, {shade: [0.1, '#fff']});
            $.ajax({
                url: '?c=banip&a=' + action, type: 'POST', data: data, dataType: 'json',
                success: function (result) {
                    if (result && result.code == 200) { window.location.reload(); }
                    else {
                        layer.msg(result && result.msg ? result.msg : '操作失败', {icon: 2});
                        if (onError) onError();
                    }
                },
                error: function () {
                    layer.msg('请求失败，请检查网络或重新登录后重试', {icon: 2});
                    if (onError) onError();
                },
                complete: function () { layer.close(loading); }
            });
        }

        continent.on('change', function () {
            fill(country, Object.keys(tree[continent.val()] || {}), '缺省');
            fill(province, [], '缺省');
        });
        country.on('change', function () {
            var countries = tree[continent.val()] || {};
            fill(province, countries[country.val()] || [], '缺省');
        });
        $('#bangeo-form').on('submit', function (event) {
            event.preventDefault();
            if (!continent.val()) {
                layer.msg('请选择大洲', {icon: 0}); continent.trigger('focus'); return;
            }
            add.prop('disabled', true);
            request('addBangeo', {continent: continent.val(), country: country.val() || '', province: province.val() || ''},
                function () { add.prop('disabled', false); });
        });
        $('#bangeo-toggle').on('click', function (event) {
            event.preventDefault();
            var toggle = $(this);
            if (toggle.data('busy')) return;
            toggle.data('busy', true).attr('aria-disabled', 'true');
            request('switchGeo', {status: toggle.attr('data-geo-status')}, function () {
                toggle.data('busy', false).attr('aria-disabled', 'false');
            });
        });
        $('#bangeo-enabled').on('change', function () {
            var checkbox = $(this), enabled = checkbox.prop('checked');
            checkbox.prop('disabled', true);
            request('switchGeo', {status: enabled ? '1' : '0'}, function () {
                checkbox.prop('checked', !enabled).prop('disabled', false);
            });
        });
        panel.on('click', '[data-geo-delete]', function () {
            var id = $(this).attr('data-geo-delete');
            layer.confirm('确定删除该地区黑名单吗？', {icon: 3, title: '确认删除'}, function (index) {
                layer.close(index); request('delBangeo', {id: id});
            });
        });
        $('#bangeo-clear').on('click', function () {
            layer.confirm('确定清空全部地区黑名单吗？', {icon: 3, title: '确认清空'}, function (index) {
                layer.close(index); request('delBangeoAll', {});
            });
        });
        if (!window.EP_GEO_REGIONS || !Object.keys(window.EP_GEO_REGIONS).length) {
            hint.text('地区数据加载失败，请检查静态地区数据文件');
            return;
        }
        tree = window.EP_GEO_REGIONS;
        fill(continent, Object.keys(tree), '请选择大洲');
        add.prop('disabled', false);
    });
})(jQuery);
