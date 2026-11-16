<?php

namespace KusikusiCMS\Acl\Enums;

enum EntityScope: string
{
    case Self = 'self';
    case Children = 'children';
    case Descendants = 'descendants';
}
