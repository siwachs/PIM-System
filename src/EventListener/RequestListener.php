<?php

namespace App\EventListener;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;

class RequestListener
{
    public function __invoke(RequestEvent $event): void
    {
        // dd('Request Listener Fired.');
    }
}
