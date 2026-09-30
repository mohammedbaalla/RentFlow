<?php
namespace App\Models;
class Unit extends CompanyRecord { protected $table='units'; public function building(){return $this->belongsTo(Building::class);} }
