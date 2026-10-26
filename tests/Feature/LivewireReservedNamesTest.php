<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Livewire\Component;

/**
 * In the browser, $wire maps these names to Livewire's own helpers
 * ($wire.upload, $wire.set, ...), so wire:click="upload" or
 * wire:submit="upload" would never reach a PHP action with that name.
 */
test('no Livewire component declares an action that clashes with a $wire helper', function () {
    $reserved = [
        'on', 'el', 'id', 'js', 'get', 'set', 'refs', 'call', 'hook', 'watch', 'dirty', 'effect', 'commit',
        'errors', 'island', 'upload', 'entangle', 'dispatch', 'intercept', 'interceptAction', 'interceptMessage',
        'interceptRequest', 'dispatchTo', 'dispatchSelf', 'dispatchEl', 'dispatchRef', 'removeUpload',
        'cancelUpload', 'uploadMultiple',
    ];

    $components = collect(File::allFiles(app_path('Livewire')))
        ->map(fn (SplFileInfo $file) => 'App\\Livewire\\'.str_replace('/', '\\', Str::beforeLast($file->getRelativePathname(), '.php')))
        ->filter(fn (string $class) => is_subclass_of($class, Component::class));

    expect($components->all())->toContain('App\\Livewire\\Admin\\Timetables\\Index');

    $clashes = $components
        ->flatMap(fn (string $class) => collect((new ReflectionClass($class))->getMethods(ReflectionMethod::IS_PUBLIC))
            ->filter(fn (ReflectionMethod $method) => ! $method->isStatic() && in_array($method->getName(), $reserved, true))
            ->filter(fn (ReflectionMethod $method) => ! str_starts_with($method->getDeclaringClass()->getName(), 'Livewire\\'))
            ->map(fn (ReflectionMethod $method) => "{$class}::{$method->getName()}()"))
        ->values()
        ->all();

    expect($clashes)->toBe([]);
});
