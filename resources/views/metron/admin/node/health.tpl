{include file='admin/main.tpl'}

<main class="content">
    <div class="content-header ui-content-header">
        <div class="container">
            <h1 class="content-heading">节点健康中心</h1>
        </div>
    </div>
    <div class="container">
        <section class="content-inner margin-top-no">
            <div class="row health-summary">
                <div class="col-md-3 col-sm-6"><div class="card"><div class="card-inner"><h3>{$health_summary.total}</h3><p>节点总数</p></div></div></div>
                <div class="col-md-3 col-sm-6"><div class="card"><div class="card-inner"><h3 class="text-green">{$health_summary.online}</h3><p>当前在线</p></div></div></div>
                <div class="col-md-3 col-sm-6"><div class="card"><div class="card-inner"><h3 class="text-red">{$health_summary.alerts}</h3><p>异常告警</p></div></div></div>
                <div class="col-md-3 col-sm-6"><div class="card"><div class="card-inner"><h3>{$health_summary.traffic}</h3><p>累计流量</p></div></div></div>
            </div>

            <div class="card">
                <div class="card-main">
                    <div class="card-inner">
                        <p>在线率按最近 24 小时节点上报分钟数计算；延迟优先显示测速记录，并在页面加载时执行一次 1 秒超时的 TCP 探测。</p>
                        <p>告警阈值：离线 5 分钟、延迟 250ms、CPU 85%、内存/磁盘/流量 90%。</p>
                        <p>复制出的中转节点可继承源节点的心跳、在线人数、系统负载与内核版本；入口延迟和摘除状态仍按各节点单独探测。</p>
                    </div>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover health-table">
                    <thead><tr>
                        <th>节点</th><th>状态/人数</th><th>延迟</th><th>24h 在线率</th><th>流量</th>
                        <th>系统</th><th>运行时间</th><th>内核版本</th><th>告警</th>
                    </tr></thead>
                    <tbody>
                    {foreach $health_nodes as $node}
                        <tr data-node-id="{$node.id}">
                            <td><strong>{$node.name|escape}</strong><br><small>{$node.server|escape}</small>{if $node.health_source_name}<br><small>状态来源：{$node.health_source_name|escape}</small>{/if}</td>
                            <td>{if $node.online}<span class="label label-success">在线</span>{else}<span class="label label-danger">离线</span>{/if}<br><small>在线人数：{$node.online_users}</small><br><small>{$node.last_report}</small></td>
                            <td class="health-latency">{if $node.latency !== null}{$node.latency} ms{else}探测中…{/if}</td>
                            <td>{$node.online_rate}%</td>
                            <td>{$node.traffic} / {$node.traffic_limit}{if $node.traffic_percent !== null}<br><small>{$node.traffic_percent}%</small>{/if}</td>
                            <td>CPU: {if $node.cpu !== null}{$node.cpu}%{else}--{/if}<br>内存: {if $node.memory !== null}{$node.memory}%{else}--{/if}<br>磁盘: {if $node.disk !== null}{$node.disk}%{else}--{/if}</td>
                            <td>{$node.uptime}</td>
                            <td><small>Xray: {$node.xray_version|escape}<br>Hysteria2: {$node.hysteria_version|escape}<br>sing-box: {$node.singbox_version|escape}</small></td>
                            <td class="health-alerts">
                                {if count($node.alerts) === 0}<span class="label label-success">正常</span>{/if}
                                {foreach $node.alerts as $alert}<span class="label label-danger health-alert">{$alert|escape}</span>{/foreach}
                            </td>
                        </tr>
                    {/foreach}
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</main>

<style>
    .health-summary .card-inner { text-align: center; }
    .health-summary h3 { margin: 0 0 8px; }
    .health-table td { vertical-align: middle !important; min-width: 90px; }
    .health-table td:first-child, .health-table td:nth-child(8) { min-width: 170px; }
    .health-alert { display: inline-block; margin: 2px; }
    .text-green { color: #21ba45; }
    .text-red { color: #db2828; }
</style>

{include file='admin/footer.tpl'}

<script>
{literal}
document.querySelectorAll('tr[data-node-id]').forEach(function (row) {
    function addAlert(message) {
        var alerts = row.querySelector('.health-alerts');
        var existing = Array.prototype.some.call(alerts.querySelectorAll('.health-alert'), function (item) {
            return item.textContent === message;
        });
        if (existing) return;
        var normal = alerts.querySelector('.label-success');
        if (normal) normal.remove();
        var badge = document.createElement('span');
        badge.className = 'label label-danger health-alert';
        badge.textContent = message;
        alerts.appendChild(badge);
    }

    fetch('/admin/node/health/' + row.dataset.nodeId + '/probe', {credentials: 'same-origin'})
        .then(function (response) { return response.json(); })
        .then(function (data) {
            var latency = row.querySelector('.health-latency');
            if (!data.ret) {
                latency.textContent = '连接失败';
                addAlert('端口探测失败');
                return;
            }
            latency.textContent = data.latency_ms + ' ms';
            if (data.high_latency) {
                addAlert('延迟过高');
            }
        })
        .catch(function () {
            row.querySelector('.health-latency').textContent = '探测失败';
            addAlert('端口探测失败');
        });
});
{/literal}
</script>
