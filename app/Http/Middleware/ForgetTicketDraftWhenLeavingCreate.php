<?php

namespace App\Http\Middleware;

use App\Livewire\Pages\TicketCreate;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForgetTicketDraftWhenLeavingCreate
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->shouldKeepDraft($request)) {
            return $next($request);
        }

        $request->session()->forget(TicketCreate::DRAFT_SESSION_KEYS);

        return $next($request);
    }

    protected function shouldKeepDraft(Request $request): bool
    {
        if (! $request->isMethod('GET') && ! $request->isMethod('HEAD')) {
            return true;
        }

        return $request->routeIs('tickets.create');
    }
}
