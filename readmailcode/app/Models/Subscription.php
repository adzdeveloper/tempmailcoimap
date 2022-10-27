<?php

namespace App\Models;
use Laravel\Paddle\Subscription as CashierSubscription;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subscription extends CashierSubscription
{
    use HasFactory;
}
