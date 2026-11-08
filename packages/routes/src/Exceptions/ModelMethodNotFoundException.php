<?php

namespace KusikusiCMS\Routes\Exceptions;

use KusikusiCMS\Routes\Support\ResolvedRoute;
use RuntimeException;

/**
 * Thrown when a method for a model is not found in hosting app's WebSiteController.
 */
class ModelMethodNotFoundException extends RuntimeException
{
    public function __construct(
        public readonly string $method,
    ) {
        parent::__construct(sprintf(
            'The method [%s] was not found in hosting app\'s WebsiteController.',
            $method
        ));
    }
}
