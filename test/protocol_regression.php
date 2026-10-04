<?php

require_once __DIR__ . '/../src/Utils/NodeConfigValidator.php';
require_once __DIR__ . '/../src/Utils/AppURI.php';
require_once __DIR__ . '/../src/Services/Config.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/Utils/Tools.php';
require_once __DIR__ . '/../src/Utils/URL.php';

use App\Controllers\LinkController;
use App\Models\Node;
use App\Services\NodeHealthMonitor;
use App\Utils\AppURI;
use App\Utils\NodeConfigValidator;
use App\Services\Config;
use App\Utils\Tools;
use App\Utils\URL;

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

$fancySS = LinkController::getSubscribeExtend('fancyss');
expect($fancySS['class'] === 'FancySS' && $fancySS['suffix'] === 'yaml', 'FancySS subscription metadata');
expect(
    LinkController::isFancySSUserAgent('AsusWRT/koolcenter/RT-BE88U/102.5/fancyss/mtk/lite/3.5.29'),
    'FancySS AsusWRT user agent detection'
);
expect(LinkController::detectSubscriptionOptions('Clash.Meta/1.19') === ['clashmeta' => 1], 'Clash Meta user agent detection');
expect(LinkController::detectSubscriptionOptions('sing-box 1.14.0') === ['singbox' => 1], 'sing-box user agent detection');
expect(LinkController::detectSubscriptionOptions('v2rayN/7.15.4') === ['sub' => 3], 'V2RayN user agent detection');
expect(LinkController::subscriptionOptionsForProfile('mihomo') === ['clashmeta' => 1], 'Mihomo profile mapping');
expect(LinkController::subscriptionOptionsForProfile('auto') === [], 'automatic profile mapping');
expect(LinkController::subscriptionOptionsForProfile('unknown-client') === null, 'unknown profile rejection');
$healthNode = new Node();
$healthNode->id = 20;
$healthNode->health_source_node_id = 3;
expect($healthNode->getHealthSourceNodeId() === 3, 'copied node health source mapping');
$healthNode->server = 'example.com;443;0;tcp;tls';
expect(NodeHealthMonitor::endpoint($healthNode) === ['host' => 'example.com', 'port' => 443], 'node health endpoint parsing');
$healthNode->server = ';443';
expect(NodeHealthMonitor::endpoint($healthNode) === null, 'node health endpoint requires host');
$encryption = 'mlkem768x25519plus.native.0rtt.test-key';
$server = 'vless.example.com;21636;0;xhttp;tls;security=reality|host=vless.example.com|path=/assets|enable_vless=true|flow=xtls-rprx-vision|publicKey=public-key|shortId=01234567';
$user = new class {
    public function getUuid(): string { return '00000000-0000-4000-8000-000000000000'; }
};
$node = (object) ['server' => $server, 'name' => 'VLESS', 'node_class' => 0];
foreach ([null, '', 'none', $encryption, 'mlkem768x25519plus.native.1rtt.key+suffix/='] as $value) {
    $node->server = $server . ($value === null ? '' : '|encryption=' . $value);
    $parsed = Tools::v2Array($node->server);
    if ($value !== null) {
        expect($parsed['encryption'] === $value, 'node address must preserve encryption');
    }
    $item = array_merge($vless, $parsed);
    $expected = $value === null || $value === '' ? 'none' : $value;
    $legacy = $item;
    $legacy['type'] = 'vmess';
    $legacy['vtype'] = 'vless://';
    $vmess = $legacy;
    $vmess['vtype'] = 'vmess://';
    $vmessJson = json_decode(base64_decode(substr(AppURI::getV2RayNURI($vmess), 8)), true);
    expect($vmessJson['id'] === $item['id'] && !isset($vmessJson['encryption']), 'VMess output must remain unchanged');
    foreach ([AppURI::getV2RayNURI($item), AppURI::getV2RayNURI($legacy), AppURI::getShadowrocketURI($item), AppURI::getAnXrayURI($legacy), URL::getV2UrlVLESS($user, $node)] as $uri) {
        parse_str(parse_url($uri, PHP_URL_QUERY), $query);
        expect($query['encryption'] === $expected, 'VLESS URI encryption must round-trip without truncation');
        expect($query['path'] === '/assets', 'VLESS path must be preserved');
        expect($query['flow'] === 'xtls-rprx-vision', 'VLESS Vision flow must be preserved');
    }
    expect(URL::getV2UrlVLESS($user, $node, true) === array_merge($parsed, [
        'v' => '2', 'type' => 'vless', 'ps' => 'VLESS', 'remark' => 'VLESS',
        'id' => $user->getUuid(), 'class' => 0,
    ]), 'array output must preserve encryption');
    $mihomo = AppURI::getClashMetaURI($item);
    if ($expected === 'none') {
        expect(!isset($mihomo['encryption']), 'unencrypted Mihomo nodes retain existing output');
    } else {
        expect($mihomo['encryption'] === $expected, 'Mihomo encryption must be emitted');
    }
    $item['net'] = 'grpc';
    $item['servicename'] = 'vless-grpc';
    expect((AppURI::getSingBoxURI($item) === null) === ($expected !== 'none'), 'sing-box must filter encrypted nodes');
}

fwrite(STDOUT, "Protocol and configuration regression tests passed.\n");
