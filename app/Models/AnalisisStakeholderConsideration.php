<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnalisisStakeholderConsideration extends Model
{
    protected $fillable = [
        'analisis_harga_id',
        'for_dinas_perdagangan',
        'for_dinas_pertanian',
        'for_koordinator_tpid',
    ];
}
