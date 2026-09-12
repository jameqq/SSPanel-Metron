<?php

require_once __DIR__ . '/../src/Utils/NodeConfigValidator.php';
require_once __DIR__ . '/../src/Utils/AppURI.php';
require_once __DIR__ . '/../src/Services/Config.php';

use App\Utils\AppURI;
use App\Utils\NodeConfigValidator;
use App\Services\Config;

function expect($condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

function expectInvalid(callable $callback, string $message): void
{
    try {
        $callback();
    } catch (InvalidArgumentException $exception) {
        return;
    }
    expect(false, $message);
}

$normalized = NodeConfigValidator::normalizeCustomConfig(" {\n \"Log\": {\"Level\": \"warning\"} } ");
expect($normalized === '{"Log":{"Level":"warning"}}', 'custom_config should be normalized');
expect(NodeConfigValidator::decodeCustomConfig('{broken') === [], 'invalid stored JSON must produce an object-shaped empty array');
expectInvalid(function (): void {
    NodeConfigValidator::normalizeCustomConfig('[]');
}, 'custom_config must reject a JSON list');
expectInvalid(function (): void {
    NodeConfigValidator::normalizeCustomConfig('{"__proto__":{}}');
}, 'custom_config must reject prototype-pollution keys');

$_ENV['xrayr_cert_provider'] = 'cloudflare';
$_ENV['xrayr_cert_email'] = 'ops@example.com';
$_ENV['xrayr_cert_dns_env'] = '{"CF_DNS_API_TOKEN":"secret","IGNORED":{"nested":true}}';
$certConfig = Config::getXrayRCertConfig();
expect($certConfig['provider'] === 'cloudflare', 'XrayRP certificate provider');
expect($certConfig['email'] === 'ops@example.com', 'XrayRP certificate email');
expect((array) $certConfig['dns_env'] === ['CF_DNS_API_TOKEN' => 'secret'], 'XrayRP certificate DNS environment');
$_ENV['xrayr_cert_dns_env'] = [];
expect(json_encode(Config::getXrayRCertConfig()['dns_env']) === '{}', 'empty certificate DNS environment must be a JSON object');

NodeConfigValidator::validateServer(16, 'hy.example.com;port=443|sni=hy.example.com|alpn=h3|obfs=salamander|upmbps=50|downmbps=200|ignore_client_bandwidth=1');
NodeConfigValidator::validateServer(17, 'tuic.example.com;port=443|congestion_control=bbr|udp_relay_mode=native|zero_rtt_handshake=1');
NodeConfigValidator::validateServer(18, 'anytls.example.com;port=443|alpn=h2,http/1.1|insecure=0');
expectInvalid(function (): void {
    NodeConfigValidator::validateServer(17, 'tuic.example.com;port=70000');
}, 'protocol port must be bounded');
expectInvalid(function (): void {
    NodeConfigValidator::validateServer(18, 'anytls.example.com;unknown=value');
}, 'unknown protocol parameters must be rejected');

$hysteria = [
    'type' => 'hysteria2', 'remark' => 'HY2', 'address' => 'hy.example.com', 'port' => 443,
    'passwd' => 'secret', 'sni' => 'hy.example.com', 'alpn' => ['h3'], 'insecure' => false,
    'obfs' => 'salamander', 'obfs_password' => 'obfs-secret', 'up_mbps' => 50, 'down_mbps' => 200,
];
$tuic = [
    'type' => 'tuic', 'remark' => 'TUIC', 'address' => 'tuic.example.com', 'port' => 443,
    'id' => '00000000-0000-4000-8000-000000000000', 'passwd' => 'secret', 'sni' => 'tuic.example.com',
    'alpn' => ['h3'], 'insecure' => false, 'congestion_control' => 'bbr', 'udp_relay_mode' => 'native',
    'zero_rtt_handshake' => true,
];
$anyTls = [
    'type' => 'anytls', 'remark' => 'AnyTLS', 'address' => 'anytls.example.com', 'port' => 443,
    'passwd' => 'secret', 'sni' => 'anytls.example.com', 'alpn' => ['h2', 'http/1.1'], 'insecure' => false,
];
$vless = [
    'type' => 'vless', 'remark' => 'VLESS xHTTP', 'add' => 'vless.example.com', 'port' => 443,
    'id' => '00000000-0000-4000-8000-000000000000', 'net' => 'xhttp', 'headerType' => 'none',
    'host' => 'vless.example.com', 'path' => '/xhttp', 'mode' => 'auto', 'tls' => 'tls', 'flow' => '',
    'security' => 'reality', 'publicKey' => 'public-key', 'shortId' => '01234567', 'sni' => 'vless.example.com',
];
$trojan = [
    'type' => 'trojan', 'remark' => 'Trojan gRPC', 'address' => 'trojan.example.com', 'port' => 443,
    'passwd' => 'secret', 'host' => 'trojan.example.com', 'net' => 'grpc', 'servicename' => 'trojan-grpc',
];
$vlessGrpc = $vless;
$vlessGrpc['remark'] = 'VLESS gRPC';
$vlessGrpc['net'] = 'grpc';
$vlessGrpc['servicename'] = 'vless-grpc';

expect(strpos(AppURI::getV2RayNURI($hysteria), 'hysteria2://') === 0, 'Hysteria2 URI generation');
expect(strpos(AppURI::getV2RayNURI($tuic), 'tuic://') === 0, 'TUIC URI generation');
expect(strpos(AppURI::getV2RayNURI($anyTls), 'anytls://') === 0, 'AnyTLS URI generation');
expect(strpos(AppURI::getV2RayNURI($vless), 'type=xhttp') !== false, 'VLESS xHTTP URI generation');

$clashHysteria = AppURI::getClashMetaURI($hysteria);
$clashTuic = AppURI::getClashMetaURI($tuic);
$clashAnyTls = AppURI::getClashMetaURI($anyTls);
$clashVless = AppURI::getClashMetaURI($vless);
$clashTrojan = AppURI::getClashMetaURI($trojan);
expect($clashHysteria['type'] === 'hysteria2' && $clashHysteria['obfs'] === 'salamander', 'Mihomo Hysteria2 output');
expect($clashTuic['type'] === 'tuic' && $clashTuic['congestion-controller'] === 'bbr', 'Mihomo TUIC output');
expect($clashAnyTls['type'] === 'anytls', 'Mihomo AnyTLS output');
expect($clashVless['network'] === 'xhttp' && $clashVless['xhttp-opts']['mode'] === 'auto', 'Mihomo xHTTP output');
expect($clashTrojan['network'] === 'grpc' && $clashTrojan['grpc-opts']['grpc-service-name'] === 'trojan-grpc', 'Mihomo Trojan gRPC output');

expect(AppURI::getSingBoxURI($vless) === null, 'sing-box must not emit unsupported xHTTP transport');
foreach ([$hysteria, $tuic, $anyTls, $vlessGrpc, $trojan] as $item) {
    $singBox = AppURI::getSingBoxURI($item);
    expect(is_array($singBox) && isset($singBox['type']), 'sing-box output for ' . $item['type']);
}
expect(AppURI::getSingBoxURI($vlessGrpc)['transport']['service_name'] === 'vless-grpc', 'sing-box VLESS gRPC output');
expect(AppURI::getSingBoxURI($trojan)['transport']['service_name'] === 'trojan-grpc', 'sing-box Trojan gRPC output');

fwrite(STDOUT, "Protocol and configuration regression tests passed.\n");
