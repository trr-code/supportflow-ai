<?php

use App\Exceptions\DocumentExtractException;
use App\Support\DocumentParser;
use App\Support\DocumentTextExtractor;
use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Exception\ProcessTimedOutException as SymfonyTimeoutException;
use Symfony\Component\Process\Process as SymfonyProcess;

// Real subprocesses here only read small fixtures; no provider requests are made.
test('isolated extraction preserves supported document formats', function (string $fixture, string $expected) {
    $text = app(DocumentTextExtractor::class)->extract(base_path('tests/Fixtures/'.$fixture), pathinfo($fixture, PATHINFO_EXTENSION));

    expect($text)->toContain($expected);
})->with([
    ['notes.txt', '4:10 p.m.'],
    ['notes.md', '30 days'],
    ['warranty.docx', '30 days'],
    ['poles.pdf', 'overnight'],
]);

test('oversized expanded docx text is rejected before parsing', function () {
    $disk = Storage::fake('local');
    $path = $disk->path('expanded.docx');
    $archive = new ZipArchive;
    expect($archive->open($path, ZipArchive::CREATE))->toBeTrue();
    $archive->addFromString('word/document.xml', '<w:p>'.str_repeat('x', 2_000_001).'</w:p>');
    $archive->close();
    expect(filesize($path))->toBeLessThan(10000);

    expect(fn () => app(DocumentParser::class)->extract($path, 'docx'))
        ->toThrow(DocumentExtractException::class, 'longer than this preview can index');
    expect(fn () => app(DocumentTextExtractor::class)->extract($path, 'docx'))
        ->toThrow(DocumentExtractException::class, 'longer than this preview can index');
});

test('missing docx content and malformed archives fail safely', function (bool $validArchive) {
    $disk = Storage::fake('local');
    $path = $disk->path('broken.docx');
    if ($validArchive) {
        $archive = new ZipArchive;
        $archive->open($path, ZipArchive::CREATE);
        $archive->addFromString('unrelated.xml', '<document/>');
        $archive->close();
    } else {
        $disk->put('broken.docx', 'Not a ZIP archive');
    }

    expect(fn () => app(DocumentTextExtractor::class)->extract($path, 'docx'))->toThrow(DocumentExtractException::class);
})->with([true, false]);

test('the child process receives the configured resource boundaries', function () {
    config(['supportflow.workspaces.extract_seconds' => 17, 'supportflow.workspaces.extract_memory_bytes' => 100000000]);
    Process::fake(fn () => Process::result(output: '{"text":"safe text"}'));
    Process::preventStrayProcesses();

    expect(app(DocumentTextExtractor::class)->extract(base_path('tests/Fixtures/notes.txt'), 'txt'))->toBe('safe text');
    Process::assertRan(fn (PendingProcess $process): bool => $process->timeout === 17
        && in_array('memory_limit=100000000', $process->command, true)
        && in_array('display_errors=0', $process->command, true)
        && in_array('log_errors=0', $process->command, true)
        && in_array('documents:extract-text', $process->command, true));
});

test('a parser crash or invalid output cannot become extracted document text', function (string $output, int $exitCode) {
    Process::fake(fn () => Process::result(output: $output, errorOutput: 'private parser diagnostic', exitCode: $exitCode));
    Process::preventStrayProcesses();

    expect(fn () => app(DocumentTextExtractor::class)->extract('unused.pdf', 'pdf'))
        ->toThrow(DocumentExtractException::class, 'This document could not be read.');
    Process::assertRanTimes(fn (PendingProcess $process): bool => is_array($process->command), 1);
})->with([
    ['{"text":"partial"}', 1],
    ['not json', 0],
    ['{"text":""}', 0],
    ['{"text":123}', 0],
    ['{"text":"'.str_repeat('x', 150001).'"}', 0],
]);

test('a timed out parser returns a recoverable document error', function () {
    Process::fake(function (): never {
        $child = new SymfonyProcess([PHP_BINARY, '-v']);
        $child->setTimeout(1);
        throw new ProcessTimedOutException(new SymfonyTimeoutException($child, SymfonyTimeoutException::TYPE_GENERAL), Process::result());
    });

    expect(fn () => app(DocumentTextExtractor::class)->extract('unused.pdf', 'pdf'))
        ->toThrow(DocumentExtractException::class, 'extraction time limit');
});

test('the child enforces memory limits without ending the application worker', function () {
    config(['supportflow.workspaces.extract_memory_bytes' => 8 * 1024 * 1024]);
    expect(fn () => app(DocumentTextExtractor::class)->extract(base_path('tests/Fixtures/poles.pdf'), 'pdf'))
        ->toThrow(DocumentExtractException::class, 'This document could not be read.');

    config(['supportflow.workspaces.extract_memory_bytes' => 256 * 1024 * 1024]);
    expect(app(DocumentTextExtractor::class)->extract(base_path('tests/Fixtures/notes.txt'), 'txt'))->not->toBeEmpty();
});

test('an extracted character limit is enforced in the child process', function () {
    config(['supportflow.workspaces.max_extracted_characters' => 5]);
    expect(fn () => app(DocumentTextExtractor::class)->extract(base_path('tests/Fixtures/notes.txt'), 'txt'))
        ->toThrow(DocumentExtractException::class, 'longer than this preview can index');
});
