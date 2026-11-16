<?php

/**
 * IDE helper for the ACL package. It is not autoloaded or executed; it only documents methods added
 * to the models package's EntityBuilder at runtime. Keep it outside PHPStan's analysis paths.
 */

namespace KusikusiCMS\Models\Builders {
    use KusikusiCMS\Acl\Builders\EntityAclQueries;

    /**
     * @mixin EntityAclQueries
     *
     * @method $this whereUserCan(string $action = 'view', ?\Illuminate\Contracts\Auth\Authenticatable $user = null)
     */
    class EntityBuilder {}
}

namespace KusikusiCMS\Models {
    /**
     * @method static \KusikusiCMS\Models\Builders\EntityBuilder whereUserCan(string $action = 'view', ?\Illuminate\Contracts\Auth\Authenticatable $user = null)
     */
    class Entity {}
}
