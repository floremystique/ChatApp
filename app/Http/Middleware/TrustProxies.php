<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;

class TrustProxies extends Middleware
{
    /**
     * Trust all proxies (Railway, load balancers, etc.)
     */
    protected $proxies = '*';

    /**
     * Which forwarded headers to trust.
     * (Symfony-safe for Laravel 12)
     */
    protected $headers =
        \Symfony\Component\HttpFoundation\Request::HEADER_X_FORWARDED_FOR |
        \Symfony\Component\HttpFoundation\Request::HEADER_X_FORWARDED_HOST |
        \Symfony\Component\HttpFoundation\Request::HEADER_X_FORWARDED_PORT |
        \Symfony\Component\HttpFoundation\Request::HEADER_X_FORWARDED_PROTO;
}
