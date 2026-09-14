<?php

namespace App\Services;

use App\Models\{Node, NodeHealthState};

class NodeHealthMonitor
{
    public const FAILURE_THRESHOLD = 3;
    public const RECOVERY_THRESHOLD = 2;

    public static function probe(Node $node, float $timeout = 1.0): array
    {
        $endpoint = self::endpoint($node);
        if ($endpoint === null) {
            return ['ok' => false, 'latency_ms' => null, 'error' => '节点没有可探测的固定端口'];
        }
        $host = strpos($endpoint['host'], ':') !== false
            ? '[' . trim($endpoint['host'], '[]') . ']'
            : $endpoint['host'];
        $start = microtime(true);
        $socket = @stream_socket_client(
            'tcp://' . $host . ':' . $endpoint['port'],
            $errorNumber,
            $errorMessage,
            $timeout,
            STREAM_CLIENT_CONNECT
        );
        if ($socket === false) {
            return ['ok' => false, 'latency_ms' => null, 'error' => $errorMessage ?: '连接失败'];
        }
        fclose($socket);
        return [
            'ok' => true,
            'latency_ms' => (int) round((microtime(true) - $start) * 1000),
            'error' => null,
        ];
    }

    public static function record(Node $node, array $result): NodeHealthState
    {
        $state = NodeHealthState::find((int) $node->id) ?: new NodeHealthState();
        $state->node_id = (int) $node->id;
        $state->last_checked_at = time();
        $state->last_latency_ms = $result['latency_ms'];

        if ($result['ok']) {
            $successes = (int) $state->consecutive_successes + 1;
            $state->consecutive_successes = $successes;
            $state->consecutive_failures = 0;
            $state->last_success_at = time();
            $state->last_error = null;
            $state->status = $state->status === 'unhealthy' && $successes < self::RECOVERY_THRESHOLD
                ? 'degraded'
                : 'healthy';
            if ($state->status === 'healthy') {
                $state->unhealthy_since = null;
            }
        } else {
            $failures = (int) $state->consecutive_failures + 1;
            $state->consecutive_failures = $failures;
            $state->consecutive_successes = 0;
            $state->last_failure_at = time();
            $state->last_error = substr((string) $result['error'], 0, 255);
            $state->status = $failures >= self::FAILURE_THRESHOLD ? 'unhealthy' : 'degraded';
            if ($state->status === 'unhealthy' && !$state->unhealthy_since) {
                $state->unhealthy_since = time();
            }
        }
        $state->save();
        return $state;
    }

    public static function endpoint(Node $node): ?array
    {
        $parts = explode(';', trim((string) $node->server));
        $host = trim($parts[0] ?? '');
        if ($host === '' || !preg_match('/^[A-Za-z0-9.:[\]-]+$/', $host)) {
            return null;
        }
        $port = isset($parts[1]) && ctype_digit($parts[1]) ? (int) $parts[1] : 0;
        if ($port === 0 && isset($parts[1])) {
            foreach (explode('|', $parts[1]) as $option) {
                if (strpos($option, 'port=') === 0) {
                    $port = (int) substr($option, 5);
                    break;
                }
            }
        }
        return $port >= 1 && $port <= 65535 ? ['host' => $host, 'port' => $port] : null;
    }
}
