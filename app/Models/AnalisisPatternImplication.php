<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalisisPatternImplication extends Model {
    protected $guarded = ['id']; // Eksplisit nama tabel

    protected $table = 'analisis_pattern_implications';


    public function analisisHarga(): BelongsTo {
        return $this->belongsTo(AnalisisHarga::class);
    }
}
