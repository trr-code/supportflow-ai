<?php

use Pest\Rector\Rules\ChainExpectCallsRector;
use Pest\Rector\Rules\SimplifyToLiteralBooleanRector;
use Pest\Rector\Rules\UseToBeEmptyRector;
use Pest\Rector\Set\PestSetList;
use Rector\Config\RectorConfig;
use Rector\Php74\Rector\Closure\ClosureToArrowFunctionRector;
use Rector\Php84\Rector\MethodCall\NewMethodCallWithoutParenthesesRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/app',
        __DIR__.'/bootstrap/app.php',
        __DIR__.'/config',
        __DIR__.'/database',
        __DIR__.'/routes',
        __DIR__.'/tests',
    ])
    ->withSkip([
        __DIR__.'/vendor',
        __DIR__.'/storage',
        __DIR__.'/bootstrap/cache',
        __DIR__.'/tests/Stress/RuntimeAbTest.php',
        NewMethodCallWithoutParenthesesRector::class,
        UseToBeEmptyRector::class,
        SimplifyToLiteralBooleanRector::class,
        ClosureToArrowFunctionRector::class => [
            __DIR__.'/tests/Pest.php',
        ],
    ])
    ->withPhpSets()
    ->withSets([
        PestSetList::CODING_STYLE,
    ])
    ->withConfiguredRule(ChainExpectCallsRector::class, [
        'merge_different_variables' => false,
    ]);
