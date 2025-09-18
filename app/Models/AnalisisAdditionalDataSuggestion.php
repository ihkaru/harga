<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalisisAdditionalDataSuggestion extends Model {
    protected $guarded = ['id']; // Eksplisit nama tabel
    protected $table = 'analisis_additional_data_suggestions';

    public function analisisHarga(): BelongsTo {
        return $this->belongsTo(AnalisisHarga::class);
    }
}
