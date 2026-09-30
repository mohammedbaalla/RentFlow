<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
class CompanyRecord extends Model {
    protected $guarded=['id'];
    protected static function booted(): void {
        static::addGlobalScope('company',function(Builder $query){
            if(auth()->check()) $query->where($query->getModel()->qualifyColumn('company_id'),auth()->user()->company_id);
        });
    }
    public function company(){ return $this->belongsTo(Company::class); }
}
