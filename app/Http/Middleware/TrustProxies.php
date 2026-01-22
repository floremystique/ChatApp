<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;

class TrustProxies extends Middleware
{
    /**
     * Trust all proxies (Railway, load balancers, etc.)
     */
    protected $proxies = '*';

    /**
     * Use all forwarded headers
     */
    protected $headers = Request::HEADER_X_FORWARDED_ALL;
}
