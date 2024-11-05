<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Komoditas extends Model
{
    protected $guarded = [];
    public function hargas()
    {
        return $this->hasMany(Harga::class, 'id_komoditas', 'id_komoditas');
    }
}
