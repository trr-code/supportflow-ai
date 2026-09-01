<?php

use App\Http\Controllers\EnterDemoAgentController;
use App\Http\Controllers\StartDictationSessionController;
use App\Livewire\Pages\Dashboard;
use App\Livewire\Pages\KnowledgeIndex;
use App\Livewire\Pages\KnowledgeShow;
use App\Livewire\Pages\TicketCreate;
use App\Livewire\Pages\TicketIndex;
use App\Livewire\Pages\TicketShow;
use App\Livewire\Pages\TicketStatus;
use App\Livewire\Pages\Welcome;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', Welcome::class)->name('home');

Route::get('/knowledge', KnowledgeIndex::class)->name('knowledge.index');
Route::get('/knowledge/{slug}', KnowledgeShow::class)->name('knowledge.show');

Route::get('/tickets/create', TicketCreate::class)->name('tickets.create');
Route::get('/t/{publicToken}', TicketStatus::class)
    ->where('publicToken', '[a-f0-9]{32}')
    ->name('tickets.status');

Route::post('/demo/enter-agent', EnterDemoAgentController::class)
    ->middleware('throttle:demo.enter-agent')
    ->name('demo.enter-agent');

Route::post('/demo/dictation/session', StartDictationSessionController::class)
    ->middleware('throttle:30,1')
    ->name('demo.dictation.session');

Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', Dashboard::class)->name('dashboard');
    Route::get('/agent/tickets', TicketIndex::class)->name('agent.tickets.index');
    Route::get('/agent/tickets/{ticket}', TicketShow::class)->name('agent.tickets.show');

    Route::post('/logout', function (Request $request) {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    })->name('logout');
});

require __DIR__.'/settings.php';
