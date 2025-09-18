<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalisisKeyMetricsToWatch extends Model {
    protected $guarded = ['id']; // Eksplisit nama tabel
    protected $table = 'analisis_key_metrics_to_watch';


    public function analisisHarga(): BelongsTo {
        return $this->belongsTo(AnalisisHarga::class);
    }
}
