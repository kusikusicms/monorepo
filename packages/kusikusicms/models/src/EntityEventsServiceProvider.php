<?php

namespace KusikusiCMS\Models;

use Illuminate\Foundation\Support\Providers\EventServiceProvider;
use KusikusiCMS\Models\Listeners\EntityEventSubscriber;

class EntityEventsServiceProvider extends EventServiceProvider
{
    /**
     * The subscriber classes to register.
     *
     * @var array
     */
    protected $subscribe = [
        EntityEventSubscriber::class,
    ];
}
