<?php

namespace KusikusiCMS\Acl\Exceptions;

use RuntimeException;

/**
 * A change would leave no user holding the manage permission.
 */
class LastManagerException extends RuntimeException {}
