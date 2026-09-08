<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InvoiceLine extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = ['invoice_id', 'package_id', 'description', 'qty', 'unit_price', 'amount'];

    protected $casts = ['unit_price' => 'decimal:2', 'amount' => 'decimal:2', 'qty' => 'integer'];
}
