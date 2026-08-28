<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceLine extends Model
{
    public $timestamps = false;
    protected $fillable = ['invoice_id','package_id','description','qty','unit_price','amount'];
}
