@props(['title' => null])

@if (auth()->user()?->hasAnyRole([\App\Enums\RoleName::AdminOfficer->value, \App\Enums\RoleName::SuperAdmin->value, \App\Enums\RoleName::Hod->value, \App\Enums\RoleName::Dean->value]))
    <x-layouts::admin :title="$title">{{ $slot }}</x-layouts::admin>
@else
    <x-layouts::student :title="$title">{{ $slot }}</x-layouts::student>
@endif
