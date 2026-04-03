<?php

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\AuthServiceProvider::class,
    App\Providers\EventServiceProvider::class,
    ...(class_exists(\App\Providers\TelescopeServiceProvider::class) ? [App\Providers\TelescopeServiceProvider::class] : []),
];
