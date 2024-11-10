<?php

namespace Spatie\HttpLogger;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

interface LogWriter
{
    public function logRequest(Request $request, Response $response, int $time);
}
