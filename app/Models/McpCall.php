<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class McpCall extends Model
{
    public $timestamps = false;
    protected $table = 'mcp_calls';
    protected $fillable = ['mcp_key_id', 'key_name', 'tool', 'arguments', 'status', 'error', 'duration_ms', 'created_at'];
    protected $casts = ['created_at' => 'datetime'];
}
