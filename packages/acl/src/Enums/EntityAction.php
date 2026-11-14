<?php

namespace KusikusiCMS\Acl\Enums;

enum EntityAction: string
{
    case View = 'view';
    case Create = 'create';
    case Update = 'update';
    case Delete = 'delete';
}
