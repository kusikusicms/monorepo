<?php

namespace App\Listeners;

use \KusikusiCMS\Models\Events\EntityCreating;

class ValidateEntityCreation
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(EntityCreating $event): void
    {
        if ($event->entity->id === 'devil') {
            throw new \InvalidArgumentException('ID can not be devil');
        }
    }
}
