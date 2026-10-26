@props(['title' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-page">
        <flux:header container class="border-b-0 bg-plum">
            <flux:sidebar.toggle class="text-white lg:hidden" icon="bars-2" inset="left" />

            <a href="{{ route('student.dashboard') }}" wire:navigate class="flex items-center">
                <x-superior-logo white class="h-8 w-auto" />
            </a>

            <nav aria-label="{{ __('Main') }}" class="ms-6 hidden flex-1 flex-wrap gap-1 lg:flex">
                <a
                    href="{{ route('student.dashboard') }}"
                    wire:navigate
                    @class([
                        'rounded-lg px-3.5 py-2.5 text-sm no-underline',
                        'bg-white/16 font-semibold text-white' => request()->routeIs('student.dashboard'),
                        'font-medium text-white/88 hover:text-white' => ! request()->routeIs('student.dashboard'),
                    ])
                >
                    {{ __('Dashboard') }}
                </a>
                <a
                    href="{{ Route::has('student.applications.index') ? route('student.applications.index') : '#' }}"
                    wire:navigate
                    @class([
                        'rounded-lg px-3.5 py-2.5 text-sm no-underline',
                        'bg-white/16 font-semibold text-white' => request()->routeIs('student.applications.*'),
                        'font-medium text-white/88 hover:text-white' => ! request()->routeIs('student.applications.*'),
                    ])
                >
                    {{ __('My applications') }}
                </a>
                <a
                    href="{{ route('student.timetable') }}"
                    wire:navigate
                    @class([
                        'rounded-lg px-3.5 py-2.5 text-sm no-underline',
                        'bg-white/16 font-semibold text-white' => request()->routeIs('student.timetable'),
                        'font-medium text-white/88 hover:text-white' => ! request()->routeIs('student.timetable'),
                    ])
                >
                    {{ __('Timetable') }}
                </a>
                <a
                    href="{{ Route::has('student.announcements.index') ? route('student.announcements.index') : '#' }}"
                    wire:navigate
                    @class([
                        'rounded-lg px-3.5 py-2.5 text-sm no-underline',
                        'bg-white/16 font-semibold text-white' => request()->routeIs('student.announcements.*'),
                        'font-medium text-white/88 hover:text-white' => ! request()->routeIs('student.announcements.*'),
                    ])
                >
                    {{ __('Announcements') }}
                </a>
            </nav>

            <flux:spacer class="lg:hidden" />

            <div class="ms-auto flex items-center gap-2">
                <livewire:notifications.bell />

                <flux:dropdown position="bottom" align="end">
                    <button type="button" class="flex min-h-11 items-center gap-2.5 rounded-xl bg-white/12 py-1 pr-2 pl-1 text-white" data-test="user-menu-button">
                        <flux:avatar size="sm" :name="auth()->user()->name" :initials="auth()->user()->initials()" />
                        <span class="hidden flex-col items-start leading-tight sm:flex">
                            <span class="text-sm font-semibold">{{ auth()->user()->name }}</span>
                            <span class="text-xs text-white/80">{{ __('Student') }}</span>
                        </span>
                        <flux:icon icon="chevron-down" variant="mini" class="size-4" />
                    </button>

                    <flux:menu>
                        <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                            <flux:avatar :name="auth()->user()->name" :initials="auth()->user()->initials()" />

                            <div class="grid flex-1 text-start text-sm leading-tight">
                                <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                                <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                            </div>
                        </div>

                        <flux:menu.separator />

                        <flux:menu.radio.group>
                            <flux:menu.item :href="route('student.profile')" icon="user-circle" wire:navigate>
                                {{ __('My Profile') }}
                            </flux:menu.item>

                            <flux:menu.item :href="route('security.edit')" icon="cog" wire:navigate>
                                {{ __('Security') }}
                            </flux:menu.item>

                            <form method="POST" action="{{ route('logout') }}" class="w-full">
                                @csrf
                                <flux:menu.item
                                    as="button"
                                    type="submit"
                                    icon="arrow-right-start-on-rectangle"
                                    class="w-full cursor-pointer"
                                    data-test="logout-button"
                                >
                                    {{ __('Log out') }}
                                </flux:menu.item>
                            </form>
                        </flux:menu.radio.group>
                    </flux:menu>
                </flux:dropdown>
            </div>
        </flux:header>

        <flux:sidebar collapsible class="border-e border-border bg-white lg:hidden">
            <flux:sidebar.nav>
                <flux:sidebar.item icon="layout-grid" :href="route('student.dashboard')" :current="request()->routeIs('student.dashboard')" wire:navigate>
                    {{ __('Dashboard') }}
                </flux:sidebar.item>
                <flux:sidebar.item
                    icon="clipboard-document-list"
                    :href="Route::has('student.applications.index') ? route('student.applications.index') : '#'"
                    :current="request()->routeIs('student.applications.*')"
                    wire:navigate
                >
                    {{ __('My applications') }}
                </flux:sidebar.item>
                <flux:sidebar.item icon="calendar-days" :href="route('student.timetable')" :current="request()->routeIs('student.timetable')" wire:navigate>
                    {{ __('Timetable') }}
                </flux:sidebar.item>
                <flux:sidebar.item
                    icon="megaphone"
                    :href="Route::has('student.announcements.index') ? route('student.announcements.index') : '#'"
                    :current="request()->routeIs('student.announcements.*')"
                    wire:navigate
                >
                    {{ __('Announcements') }}
                </flux:sidebar.item>
                <flux:sidebar.item
                    icon="bell"
                    :href="Route::has('student.notifications.index') ? route('student.notifications.index') : '#'"
                    :current="request()->routeIs('student.notifications.*')"
                    wire:navigate
                >
                    {{ __('Notifications') }}
                </flux:sidebar.item>
            </flux:sidebar.nav>
        </flux:sidebar>

        <flux:main container>
            {{ $slot }}
        </flux:main>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
