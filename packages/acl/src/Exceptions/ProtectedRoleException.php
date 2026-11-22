<?php

namespace KusikusiCMS\Acl\Exceptions;

use RuntimeException;

/**
 * The guest role cannot be renamed, deleted or assigned to users.
 */
class ProtectedRoleException extends RuntimeException {}
