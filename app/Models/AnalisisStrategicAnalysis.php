<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnalisisStrategicAnalysis extends Model
{
    protected $fillable = [
        'analisis_harga_id',
        'causal_hypothesis',
        'potential_impact_framing',
    ];
}
