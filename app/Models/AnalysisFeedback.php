<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnalysisFeedback extends Model
{
    use HasFactory;

    protected $fillable = [
        'commodity_id',
        'analysis_date',
        'feedback_type',
    ];
}
