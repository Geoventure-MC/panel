<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OptionsAzuriom extends Model
{
    use HasFactory;

    protected $table = 'options_azuriom';

    protected $fillable = [
        'name',
        'url',
        'api_key',
        'server_id',
        'is_primary',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    public function server(): BelongsTo
    {
        return $this->belongsTo(OptionsServer::class, 'server_id', 'server_id');
    }
}
