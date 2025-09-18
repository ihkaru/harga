<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalisisAssumptionsMade extends Model {
    protected $guarded = ['id']; // Eksplisit nama tabel
    protected $table = 'analisis_assumptions_made';

    public function analisisHarga(): BelongsTo {
        return $this->belongsTo(AnalisisHarga::class);
    }
}
