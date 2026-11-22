<?php

namespace KusikusiCMS\Acl\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class ApiUser extends Authenticatable
{
    use HasApiTokens;
    use HasRoles;

    protected $table = 'users';

    protected string $guard_name = 'web';
}
