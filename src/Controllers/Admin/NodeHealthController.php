<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\{Node, NodeHealthState, NodeInfoLog, NodeOnlineLog, Speedtest};
use App\Utils\Tools;
use App\Services\NodeHealthMonitor;
use Psr\Http\Message\ResponseInterface;

class NodeHealthController extends BaseController
{
    private const OFFLINE_AFTER = 300;
    private const HIGH_LATENCY_MS = 250;
    private const HIGH_LOAD_PERCENT = 85;
    private const HIGH_USAGE_PERCENT = 90;

    public function index($request, $response, $args): ResponseInterface
    {
        $rows = [];
        $onlineCount = 0;
        $alertCount = 0;
        $totalTraffic = 0;
        $since = time() - 86400;

        foreach (Node::orderBy('node_sort', 'desc')->orderBy('id')->get() as $node) {
            $healthSource = $node->getHealthSourceNode();
            $healthSourceId = (int) $healthSource->id;
            $info = NodeInfoLog::where('node_id', $healthSourceId)->orderBy('id', 'desc')->first();
            $samples = NodeOnlineLog::where('node_id', $healthSourceId)->where('log_time', '>=', $since)->count();
            $onlineRate = min(100, round($samples / 14.4, 1));
            $online = $node->getEffectiveNodeHeartbeat() > time() - self::OFFLINE_AFTER;
            $onlineUsers = $node->getOnlineUserCount();
            $cpu = self::percent($info ? $info->load : null);
            $memory = self::percent($info ? $info->memory_usage : null);
            $disk = self::percent($info ? $info->disk_usage : null);
            $used = (int) $node->node_bandwidth;
            $limit = (int) $node->node_bandwidth_limit;
            $trafficPercent = $limit > 0 ? round($used * 100 / $limit, 1) : null;
            $latency = $this->latestLatency((int) $node->id);
            $healthState = NodeHealthState::find((int) $node->id);
            $alerts = [];

            if (!$online) {
                $alerts[] = '节点离线';
            } else {
                $onlineCount++;
            }
            if ($latency !== null && $latency >= self::HIGH_LATENCY_MS) {
                $alerts[] = '延迟过高';
            }
            if ($cpu !== null && $cpu >= self::HIGH_LOAD_PERCENT) {
                $alerts[] = 'CPU 负载过高';
            }
            if ($memory !== null && $memory >= self::HIGH_USAGE_PERCENT) {
                $alerts[] = '内存使用过高';
            }
            if ($disk !== null && $disk >= self::HIGH_USAGE_PERCENT) {
                $alerts[] = '磁盘空间不足';
            }
            if ($trafficPercent !== null && $trafficPercent >= self::HIGH_USAGE_PERCENT) {
                $alerts[] = '节点流量不足';
            }
            if ($info === null || !$this->hasExpectedCoreVersion((int) $node->sort, $info)) {
                $alerts[] = '内核版本未上报';
            }
            if (
                $healthState !== null &&
                $healthState->status === 'unhealthy' &&
                (int) $healthState->last_checked_at >= time() - 900
            ) {
                $alerts[] = '已从订阅自动摘除';
            }

            $alertCount += count($alerts);
            $totalTraffic += $used;
            $rows[] = [
                'id' => (int) $node->id,
                'name' => $node->name,
                'server' => explode(';', $node->server)[0],
                'online' => $online,
                'online_users' => $onlineUsers,
                'health_source_name' => $healthSourceId !== (int) $node->id ? $healthSource->name : null,
                'online_rate' => $onlineRate,
                'latency' => $latency,
                'traffic' => Tools::flowAutoShow($used),
                'traffic_limit' => $limit > 0 ? Tools::flowAutoShow($limit) : '不限',
                'traffic_percent' => $trafficPercent,
                'cpu' => $cpu,
                'memory' => $memory,
                'disk' => $disk,
                'uptime' => $info ? Tools::secondsToTime((int) $info->uptime) : '未上报',
                'last_report' => $info ? date('Y-m-d H:i:s', (int) $info->log_time) : '未上报',
                'xray_version' => self::reportedVersion($info, 'xray_version'),
                'hysteria_version' => self::reportedVersion($info, 'hysteria_version'),
                'singbox_version' => self::reportedVersion($info, 'singbox_version'),
                'alerts' => $alerts,
                'health_state' => $healthState ? $healthState->status : 'unknown',
            ];
        }

        return $response->write(
            $this->view()
                ->assign('health_nodes', $rows)
                ->assign('health_summary', [
                    'total' => count($rows),
                    'online' => $onlineCount,
                    'alerts' => $alertCount,
                    'traffic' => Tools::flowAutoShow($totalTraffic),
                ])
                ->display('admin/node/health.tpl')
        );
    }

    public function probe($request, $response, $args): ResponseInterface
    {
        $node = Node::find((int) $args['id']);
        if ($node === null) {
            return $response->withJson(['ret' => 0, 'msg' => '节点不存在'], 404);
        }
        $result = NodeHealthMonitor::probe($node);
        if (!$result['ok']) {
            return $response->withJson(['ret' => 0, 'msg' => $result['error']]);
        }
        $latency = $result['latency_ms'];

        return $response->withJson([
            'ret' => 1,
            'latency_ms' => $latency,
            'high_latency' => $latency >= self::HIGH_LATENCY_MS,
        ]);
    }

    private function latestLatency(int $nodeId): ?int
    {
        $speedtest = Speedtest::where('nodeid', $nodeId)->orderBy('id', 'desc')->first();
        if ($speedtest === null) {
            return null;
        }
        $values = [];
        foreach ([$speedtest->telecomping, $speedtest->unicomping, $speedtest->cmccping] as $value) {
            if (preg_match('/([0-9]+(?:\.[0-9]+)?)/', (string) $value, $match)) {
                $values[] = (float) $match[1];
            }
        }
        return $values ? (int) round(array_sum($values) / count($values)) : null;
    }

    private function hasExpectedCoreVersion(int $sort, NodeInfoLog $info): bool
    {
        if ($sort === 16) {
            return self::hasVersion($info->hysteria_version);
        }
        if (in_array($sort, [17, 18], true)) {
            return self::hasVersion($info->singbox_version);
        }
        return self::hasVersion($info->xray_version);
    }

    private static function percent($value): ?float
    {
        return preg_match('/([0-9]+(?:\.[0-9]+)?)/', (string) $value, $match)
            ? min(100, max(0, (float) $match[1]))
            : null;
    }

    private static function hasVersion($value): bool
    {
        $value = strtolower(trim((string) $value));
        return $value !== '' && $value !== 'unknown' && $value !== '(devel)';
    }

    private static function reportedVersion($info, string $field): string
    {
        if (!$info || !self::hasVersion($info->{$field})) {
            return '未上报';
        }
        return $info->{$field};
    }
}
