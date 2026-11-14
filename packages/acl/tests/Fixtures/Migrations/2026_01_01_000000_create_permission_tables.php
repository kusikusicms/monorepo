<?php

use Spatie\Permission\PermissionServiceProvider;

$providerPath = (new ReflectionClass(PermissionServiceProvider::class))->getFileName();

if ($providerPath === false) {
    throw new RuntimeException('Unable to locate the Spatie permission provider.');
}

return require dirname($providerPath, 2).'/database/migrations/create_permission_tables.php.stub';
