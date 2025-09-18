<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalisisDataBasedAlert extends Model {
    protected $guarded = ['id'];
    protected $table = 'analisis_data_based_alerts'; // Eksplisit nama tabel

    public function analisisHarga(): BelongsTo {
        return $this->belongsTo(AnalisisHarga::class);
    }
}
