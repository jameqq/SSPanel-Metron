<footer class="ui-footer">
    <div class="container">
        &copy;{date("Y")} {$config['appName']} | Powered by <a href="/staff">SSPANEL</a>
        {if $config['enable_analytics_code'] === true}{include file='analytics.tpl'}{/if}
    </div>
</footer>

{if $config['sspanelAnalysis'] === true}
    <script>
        window.ga = window.ga || function () { (ga.q = ga.q || []).push(arguments) };
        ga.l = +new Date;
        ga('create', 'UA-111801619-3', 'auto');
        var hostDomain = window.location.host || document.location.host || document.domain;
        ga('set', 'dimension1', hostDomain);
        ga('send', 'pageview');

        (function () {
            function perfops() {
                var js = document.createElement('script');
                // jsDelivr 封禁了此包，使用 unpkg 绕过，如果仍然报错，可以直接删除此函数内容
                js.src = 'https://unpkg.com/perfops-rom@1.2.0/dist/rom.min.js';
                document.body.appendChild(js);
            }
            if (document.readyState === 'complete') { perfops(); } 
            else { window.addEventListener('load', perfops); }
        })();
    </script>
    <script async src="https://www.google-analytics.com/analytics.js"></script>
{/if}

<script src="https://cdn.jsdelivr.net/npm/jquery-validation@1.17.0/dist/jquery.validate.min.js"></script>

<script src="https://cdn.jsdelivr.net/npm/datatables.net@1.10.19/js/jquery.dataTables.min.js"></script>

<script src="https://cdn.jsdelivr.net/npm/datatables.net-dt@1.10.19/js/dataTables.dataTables.min.js"></script>
<script src="https://cdn.jsdelivr.net/gh/DataTables/DataTables@1.10.19/media/js/dataTables.material.min.js"></script>

<script src="/theme/material/js/base.min.js"></script>
<script src="/theme/material/js/project.min.js"></script>

</body>
</html>