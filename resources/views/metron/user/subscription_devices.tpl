<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <title>设备订阅 &mdash; {$config["appName"]}</title>
    {include file='include/global/head.tpl'}
    <div class="d-flex flex-column flex-root">
        <div class="d-flex flex-row flex-column-fluid page">
            <div class="d-flex flex-column flex-row-fluid wrapper" id="kt_wrapper">
                {include file='include/global/menu.tpl'}
                <div class="content d-flex flex-column flex-column-fluid" id="kt_content">
                    <div class="subheader min-h-lg-175px pt-5 pb-7 subheader-transparent" id="kt_subheader">
                        <div class="container d-flex align-items-center justify-content-between flex-wrap flex-sm-nowrap">
                            <h2 class="text-white font-weight-bold my-2 mr-5">设备订阅管理</h2>
                        </div>
                    </div>
                    <div class="d-flex flex-column-fluid">
                        <div class="container">
                            <div class="card card-custom gutter-b {$metron['style_shadow']}">
                                <div class="card-header"><div class="card-title"><h3 class="card-label">新增设备订阅</h3></div></div>
                                <div class="card-body">
                                    <form id="device-create-form" class="row">
                                        <div class="form-group col-md-4"><label>设备名称</label><input class="form-control" name="name" maxlength="64" placeholder="例如：家里梅林路由器" required></div>
                                        <div class="form-group col-md-3"><label>订阅格式</label><select class="form-control" name="profile">{foreach $subscription_profiles as $value => $label}<option value="{$value|escape}">{$label|escape}</option>{/foreach}</select></div>
                                        <div class="form-group col-md-3"><label>有效天数</label><input class="form-control" type="number" name="expires_days" min="0" max="3650" value="0"><small class="text-muted">0 表示不自动过期</small></div>
                                        <div class="form-group col-md-2 d-flex align-items-end"><button class="btn btn-primary btn-block" type="submit">创建</button></div>
                                    </form>
                                    <div id="device-created" class="alert alert-success d-none">
                                        <strong>请立即复制，关闭后不会再次显示完整令牌：</strong>
                                        <div class="input-group mt-2"><input id="device-created-url" class="form-control" readonly><div class="input-group-append"><button id="device-copy" class="btn btn-success" type="button">复制</button></div></div>
                                    </div>
                                </div>
                            </div>

                            <div class="card card-custom gutter-b {$metron['style_shadow']}">
                                <div class="card-header"><div class="card-title"><h3 class="card-label">已创建设备</h3></div></div>
                                <div class="card-body table-responsive">
                                    <table class="table table-hover">
                                        <thead><tr><th>设备</th><th>格式</th><th>令牌前缀</th><th>创建时间</th><th>最后访问</th><th>状态</th><th>操作</th></tr></thead>
                                        <tbody>
                                        {foreach $subscription_devices as $device}
                                            <tr>
                                                <td>{$device->name|escape}</td>
                                                <td>{$device->profile|escape}</td>
                                                <td><code>{$device->token_prefix|escape}…</code></td>
                                                <td>{$device->created_at|date_format:'%Y-%m-%d %H:%M'}</td>
                                                <td>{if $device->last_access_at}{$device->last_access_at|date_format:'%Y-%m-%d %H:%M'}<br><small>{$device->last_access_ip|escape}</small>{else}从未访问{/if}</td>
                                                <td>{if $device->revoked_at}<span class="label label-danger">已撤销</span>{elseif $device->expires_at && $device->expires_at < time()}<span class="label label-warning">已过期</span>{else}<span class="label label-success">有效</span>{/if}</td>
                                                <td><button class="btn btn-sm btn-primary device-rotate" data-id="{$device->id}">轮换</button> <button class="btn btn-sm btn-danger device-revoke" data-id="{$device->id}">撤销</button></td>
                                            </tr>
                                        {foreachelse}
                                            <tr><td colspan="7" class="text-center text-muted">尚未创建设备订阅</td></tr>
                                        {/foreach}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    {include file='include/global/footer.tpl'}
                </div>
            </div>
        </div>
        {include file='include/global/scripts.tpl'}
        <script>
        {literal}
        function showDeviceUrl(url) {
            document.getElementById('device-created-url').value = url;
            document.getElementById('device-created').classList.remove('d-none');
        }
        document.getElementById('device-create-form').addEventListener('submit', function (event) {
            event.preventDefault();
            fetch('/user/subscription/devices', {method: 'POST', credentials: 'same-origin', body: new URLSearchParams(new FormData(event.target))})
                .then(function (response) { return response.json(); })
                .then(function (data) { if (!data.ret) throw new Error(data.msg); showDeviceUrl(data.url); });
        });
        document.getElementById('device-copy').addEventListener('click', function () {
            navigator.clipboard.writeText(document.getElementById('device-created-url').value);
        });
        document.querySelectorAll('.device-rotate').forEach(function (button) {
            button.addEventListener('click', function () {
                fetch('/user/subscription/devices/' + button.dataset.id + '/rotate', {method: 'POST', credentials: 'same-origin'})
                    .then(function (response) { return response.json(); })
                    .then(function (data) { if (!data.ret) throw new Error(data.msg); showDeviceUrl(data.url); });
            });
        });
        document.querySelectorAll('.device-revoke').forEach(function (button) {
            button.addEventListener('click', function () {
                if (!confirm('确认撤销这个设备订阅？')) return;
                fetch('/user/subscription/devices/' + button.dataset.id, {method: 'DELETE', credentials: 'same-origin'})
                    .then(function (response) { return response.json(); })
                    .then(function (data) { if (!data.ret) throw new Error(data.msg); location.reload(); });
            });
        });
        {/literal}
        </script>
    </body>
</html>
