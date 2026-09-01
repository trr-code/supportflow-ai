<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnterDemoAgentController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $user = User::query()->where('email', config('supportflow.brand.agent_email'))->firstOrFail();

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }
}
