<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalisisDataConstraint extends Model {
    protected $guarded = ['id']; // Eksplisit nama tabel
    protected $table = 'analisis_data_constraints';


    public function analisisHarga(): BelongsTo {
        return $this->belongsTo(AnalisisHarga::class);
    }
}
