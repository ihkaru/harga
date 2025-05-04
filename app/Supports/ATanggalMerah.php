<?php
namespace App\Supports;

use Grei\TanggalMerah as GreiTanggalMerah;

class ATanggalMerah extends GreiTanggalMerah{
    public function getData(){
        return $this->data;
    }
}
