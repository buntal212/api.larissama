<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use JsonException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class EnsureValidJsonApiBody
{
    /** @param Closure(Request): mixed $next */
    public function handle(Request $request, Closure $next): mixed
    {
        if ($request->isJson()) {
            $content = $request->getContent();

            if ($content !== '') {
                try {
                    json_decode($content, false, 512, JSON_THROW_ON_ERROR);
                } catch (JsonException $exception) {
                    throw new BadRequestHttpException('JSON request tidak dapat dibaca.', $exception);
                }
            }
        }

        return $next($request);
    }
}
