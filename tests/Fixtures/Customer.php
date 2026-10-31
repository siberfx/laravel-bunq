<?php

namespace Siberfx\LaravelBunq\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Siberfx\LaravelBunq\Concerns\HasBunqPayments;

class Customer extends Model
{
    use HasBunqPayments;

    protected $table = 'customers';

    protected $guarded = [];
}
