<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AnalisisHarga extends Model {
    use HasFactory;

    // Lindungi dari mass assignment
    protected $guarded = ['id'];

    // Cast tipe data agar sesuai
    protected $casts = [
        'analysis_date' => 'date',
        'data_freshness' => 'date',
        'volatility_index' => 'float',
        'deviation_percentage' => 'float',
    ];

    // Relasi ke Model Komoditas
    public function komoditas(): BelongsTo {
        return $this->belongsTo(Komoditas::class);
    }

    // Relasi ke tabel-tabel pendukung
    public function dataBasedAlerts(): HasMany {
        return $this->hasMany(AnalisisDataBasedAlert::class);
    }

    public function monitoringSuggestions(): HasMany {
        return $this->hasMany(AnalisisMonitoringSuggestion::class);
    }

    // ... definisikan relasi HasMany lainnya dengan cara yang sama
    public function patternImplications(): HasMany {
        return $this->hasMany(AnalisisPatternImplication::class);
    }
    public function keyMetricsToWatch(): HasMany {
        return $this->hasMany(AnalisisKeyMetricsToWatch::class);
    }
    public function dataQualityNotes(): HasMany {
        return $this->hasMany(AnalisisDataQualityNote::class);
    }
    public function additionalDataSuggestions(): HasMany {
        return $this->hasMany(AnalisisAdditionalDataSuggestion::class);
    }
    public function statisticalWarnings(): HasMany {
        return $this->hasMany(AnalisisStatisticalWarning::class);
    }
    public function dataConstraints(): HasMany {
        return $this->hasMany(AnalisisDataConstraint::class);
    }
    public function assumptionsMade(): HasMany {
        return $this->hasMany(AnalisisAssumptionsMade::class);
    }
}
