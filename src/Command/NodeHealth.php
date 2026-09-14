<?php

namespace App\Command;

use App\Models\Node;
use App\Services\NodeHealthMonitor;

class NodeHealth extends Command
{
    public $description = '├─=: php xcat NodeHealth     - 探测节点并更新订阅健康状态' . PHP_EOL;

    public function boot()
    {
        foreach (Node::where('type', 1)->orderBy('id')->get() as $node) {
            if (NodeHealthMonitor::endpoint($node) === null) {
                echo sprintf("%d %s skipped%s", $node->id, $node->name, PHP_EOL);
                continue;
            }
            $result = NodeHealthMonitor::probe($node);
            $state = NodeHealthMonitor::record($node, $result);
            echo sprintf(
                "%d %s %s%s",
                $node->id,
                $node->name,
                $state->status,
                PHP_EOL
            );
        }
    }
}
