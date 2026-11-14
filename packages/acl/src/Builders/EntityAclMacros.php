<?php

namespace KusikusiCMS\Acl\Builders;

use KusikusiCMS\Models\Builders\EntityBuilder;

class EntityAclMacros
{
    public static function register(): void
    {
        EntityBuilder::mixin(new EntityAclQueries);
    }
}
