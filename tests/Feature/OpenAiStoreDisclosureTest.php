<?php

use App\Livewire\Pages\WorkspacePreview;
use App\Support\WorkspaceCopy;
use Livewire\Livewire;

test('openai response storage stays off and the preview discloses abuse monitoring separately from training', function () {
    expect(config('ai.providers.openai.store'))->toBeFalse()
        ->and(file_get_contents(base_path('config/ai.php')))->toContain("env('OPENAI_STORE', false)")
        ->and(file_get_contents(base_path('.env.example')))->toContain('OPENAI_STORE=false')
        ->and(WorkspaceCopy::DISCLOSURE)->toContain('model training')
        ->and(WorkspaceCopy::DISCLOSURE)->toContain('abuse-monitoring logs');

    Livewire::test(WorkspacePreview::class)
        ->assertSee(WorkspaceCopy::DISCLOSURE)
        ->assertSee('model training')
        ->assertSee('abuse-monitoring logs');
});
