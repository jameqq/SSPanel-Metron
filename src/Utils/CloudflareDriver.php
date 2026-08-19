<?php
/**
 * Created by PhpStorm.
 * User: tonyzou
 * Date: 2018/9/10
 * Time: 上午9:54
 */

namespace App\Utils;

use GuzzleHttp\Client;
use RuntimeException;

class CloudflareDriver
{

    private static function client(): Client
    {
        $headers = ['Accept' => 'application/json'];
        if (!empty($_ENV['cloudflare_token'])) {
            $headers['Authorization'] = 'Bearer ' . $_ENV['cloudflare_token'];
        } else {
            $headers['X-Auth-Email'] = $_ENV['cloudflare_email'];
            $headers['X-Auth-Key'] = $_ENV['cloudflare_key'];
        }
        return new Client([
            'base_uri' => 'https://api.cloudflare.com/client/v4/',
            'headers' => $headers,
            'timeout' => 15,
        ]);
    }

    private static function request(Client $client, string $method, string $uri, array $options = []): array
    {
        $payload = json_decode((string) $client->request($method, $uri, $options)->getBody(), true);
        if (!is_array($payload) || empty($payload['success'])) {
            throw new RuntimeException('Cloudflare API 请求失败');
        }
        return $payload;
    }

    public static function updateRecord($name, $content, $proxied = false)
    {
        $client = self::client();
        $zones = self::request($client, 'GET', 'zones', ['query' => ['name' => $_ENV['cloudflare_name'], 'status' => 'active']]);
        if (empty($zones['result'][0]['id'])) {
            throw new RuntimeException('Cloudflare 区域不存在或无权访问');
        }
        $zoneId = $zones['result'][0]['id'];
        $records = self::request($client, 'GET', "zones/{$zoneId}/dns_records", ['query' => ['type' => 'A', 'name' => $name]]);
        $details = ['type' => 'A', 'name' => $name, 'content' => $content, 'ttl' => 120, 'proxied' => (bool) $proxied];

        if (empty($records['result'])) {
            self::request($client, 'POST', "zones/{$zoneId}/dns_records", ['json' => $details]);
            return;
        }
        foreach ($records['result'] as $record) {
            if (!empty($record['id'])) {
                self::request($client, 'PUT', "zones/{$zoneId}/dns_records/{$record['id']}", ['json' => $details]);
            }
        }
    }
}
