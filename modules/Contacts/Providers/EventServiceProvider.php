<?php

namespace Modules\Contacts\Providers;

use Nova\Events\Dispatcher;
use Nova\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;


class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = array(
        'Modules\Contacts\Events\SomeEvent' => array(
            'Modules\Contacts\Listeners\EventListener',
        ),
    );


    /**
     * Register any other events for your application.
     *
     * @param  \Nova\Events\Dispatcher  $events
     * @return void
     */
    public function boot(Dispatcher $events)
    {
        parent::boot($events);

        //
        $path = realpath(__DIR__ .'/../');

        // Load the Events.
        $path = $path .DS .'Events.php';

        $this->loadEventsFrom($path);
    }
}
