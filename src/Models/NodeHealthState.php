<?php

namespace App\Models;

class NodeHealthState extends Model
{
    protected $connection = 'default';
    protected $table = 'node_health_state';
    protected $primaryKey = 'node_id';
    public $incrementing = false;
}
