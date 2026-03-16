<?php

namespace Devespresso\SystemLifeCycle\Tests\Fixtures\Models;

use Devespresso\SystemLifeCycle\Traits\EnableSystemLifeCycles;
use Illuminate\Database\Eloquent\Model;

class DummyUser extends Model
{
    use EnableSystemLifeCycles;

    protected $table = 'dummy_users';
    protected $guarded = [];
}
