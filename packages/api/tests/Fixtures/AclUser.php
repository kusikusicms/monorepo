<?php

namespace KusikusiCMS\Api\Tests\Fixtures;

use Spatie\Permission\Traits\HasRoles;

class AclUser extends User
{
    use HasRoles;

    protected string $guard_name = 'web';
}
