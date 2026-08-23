<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WalletTransaction extends Model
{
    protected $fillable = [
        "wallet_id",
        'reference',
        'type',
        'amount',
        'balance_before',
        'balance_after',
        'status',
        'description',
        'metadata'
    ];
}
