<?php
namespace App\Models;
class LeaseContract extends CompanyRecord { protected $table='contracts'; public function unit(){return $this->belongsTo(Unit::class);} public function tenant(){return $this->belongsTo(Tenant::class);} public function payments(){return $this->hasMany(RentPayment::class,'contract_id');} }
