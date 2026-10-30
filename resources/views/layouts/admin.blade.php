@props(['title' => null])

@php
    $staffRole = auth()->user()?->staffRole();
    $isSuperAdmin = $staffRole === \App\Enums\RoleName::SuperAdmin;
    $isReviewer = (bool) $staffRole?->isReviewer();
    $routePrefix = match (true) {
        $isSuperAdmin => 'super-admin.',
        $isReviewer => $staffRole->value.'.',
        default => 'admin.',
    };
    $dashboardRoute = $routePrefix.'dashboard';
    $inboxUnreadCount = ! $isSuperAdmin && auth()->user() ? app(\App\Support\Correspondence\CorrespondenceInbox::class)->unreadCount(auth()->user()) : 0;
    $openSupportCount = $isSuperAdmin ? \App\Models\SupportRequest::query()->where('status', \App\Enums\SupportRequestStatus::Open)->count() : 0;
    $navItemClass = 'text-white/85! data-current:border-transparent! data-current:bg-white/14! data-current:text-white! hover:text-white! hover:bg-white/8!';
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-page">
        <flux:sidebar collapsible="mobile" sticky class="flex w-64 flex-col gap-7 border-e-0 bg-plum-dark p-4 text-white">
            <div class="flex items-center justify-between gap-2 px-2">
                <a href="{{ route($dashboardRoute) }}" wire:navigate class="flex items-center">
                    <x-superior-logo white class="h-8 w-auto" />
                </a>
                <flux:sidebar.collapse class="text-white/70 hover:text-white lg:hidden" />
            </div>

            <flux:sidebar.nav>
                <span class="px-3 pb-1.5 text-[11px] font-semibold tracking-[0.08em] text-white/60 uppercase">{{ __('Workspace') }}</span>

                <flux:sidebar.item icon="layout-grid" :href="route($dashboardRoute)" :current="request()->routeIs($dashboardRoute)" wire:navigate :class="$navItemClass">
                    {{ __('Dashboard') }}
                </flux:sidebar.item>

                <flux:sidebar.item
                    icon="clipboard-document-list"
                    :href="Route::has($routePrefix.'applications.index') ? route($routePrefix.'applications.index') : '#'"
                    :current="request()->routeIs($routePrefix.'applications.*')"
                    wire:navigate
                    :class="$navItemClass"
                >
                    {{ $isReviewer ? __('Forwarded to me') : __('Applications') }}
                </flux:sidebar.item>

                @unless ($isSuperAdmin)
                    <flux:sidebar.item
                        icon="lifebuoy"
                        :href="route('support.index')"
                        :current="request()->routeIs('support.*')"
                        wire:navigate
                        :class="$navItemClass"
                        data-test="sidebar-contact-super-admin"
                    >
                        {{ __('Contact Super Admin') }}
                    </flux:sidebar.item>

                    <flux:sidebar.item
                        icon="inbox"
                        :href="route($routePrefix.'inbox')"
                        :current="request()->routeIs($routePrefix.'inbox')"
                        :badge="$inboxUnreadCount > 0 ? $inboxUnreadCount : null"
                        wire:navigate
                        :class="$navItemClass"
                        data-test="sidebar-inbox"
                    >
                        {{ __('Inbox') }}
                    </flux:sidebar.item>
                @endunless

                @unless ($isReviewer)
                @if ($isSuperAdmin)
                    <flux:sidebar.item
                        icon="user-group"
                        :href="Route::has('super-admin.admins.index') ? route('super-admin.admins.index') : '#'"
                        :current="request()->routeIs('super-admin.admins.*')"
                        wire:navigate
                        :class="$navItemClass"
                    >
                        {{ __('Admin Officers') }}
                    </flux:sidebar.item>

                    <flux:sidebar.item
                        icon="identification"
                        :href="route('super-admin.reviewers.index')"
                        :current="request()->routeIs('super-admin.reviewers.*')"
                        wire:navigate
                        :class="$navItemClass"
                    >
                        {{ __('HODs & Deans') }}
                    </flux:sidebar.item>

                    <flux:sidebar.item
                        icon="lifebuoy"
                        :href="route('super-admin.support.index')"
                        :current="request()->routeIs('super-admin.support.*')"
                        :badge="$openSupportCount > 0 ? $openSupportCount : null"
                        wire:navigate
                        :class="$navItemClass"
                        data-test="sidebar-support-requests"
                    >
                        {{ __('Support requests') }}
                    </flux:sidebar.item>

                    @can('createTest', \App\Models\User::class)
                        <flux:sidebar.item
                            icon="beaker"
                            :href="route('super-admin.test-users.create')"
                            :current="request()->routeIs('super-admin.test-users.*')"
                            wire:navigate
                            :class="$navItemClass"
                            data-test="sidebar-create-test-user"
                        >
                            {{ __('Create test user') }}
                        </flux:sidebar.item>
                    @endcan
                @endif

                <flux:sidebar.item
                    icon="users"
                    :href="Route::has($routePrefix.'students.index') ? route($routePrefix.'students.index') : '#'"
                    :current="request()->routeIs($routePrefix.'students.*')"
                    wire:navigate
                    :class="$navItemClass"
                >
                    {{ __('Students') }}
                </flux:sidebar.item>

                @if ($isSuperAdmin)
                    <flux:sidebar.item
                        icon="tag"
                        :href="Route::has('super-admin.categories.index') ? route('super-admin.categories.index') : '#'"
                        :current="request()->routeIs('super-admin.categories.*')"
                        wire:navigate
                        :class="$navItemClass"
                    >
                        {{ __('Categories') }}
                    </flux:sidebar.item>

                    <flux:sidebar.item
                        icon="building-library"
                        :href="Route::has('super-admin.faculties.index') ? route('super-admin.faculties.index') : '#'"
                        :current="request()->routeIs('super-admin.faculties.*')"
                        wire:navigate
                        :class="$navItemClass"
                    >
                        {{ __('Faculties') }}
                    </flux:sidebar.item>

                    <flux:sidebar.item
                        icon="building-office"
                        :href="Route::has('super-admin.departments.index') ? route('super-admin.departments.index') : '#'"
                        :current="request()->routeIs('super-admin.departments.*')"
                        wire:navigate
                        :class="$navItemClass"
                    >
                        {{ __('Departments') }}
                    </flux:sidebar.item>

                    <flux:sidebar.item
                        icon="academic-cap"
                        :href="Route::has('super-admin.programs.index') ? route('super-admin.programs.index') : '#'"
                        :current="request()->routeIs('super-admin.programs.*')"
                        wire:navigate
                        :class="$navItemClass"
                    >
                        {{ __('Programs') }}
                    </flux:sidebar.item>

                    <flux:sidebar.item
                        icon="identification"
                        :href="route('super-admin.registration-formats.index')"
                        :current="request()->routeIs('super-admin.registration-formats.*')"
                        wire:navigate
                        :class="$navItemClass"
                        data-test="sidebar-registration-formats"
                    >
                        {{ __('Registration formats') }}
                    </flux:sidebar.item>
                @endif

                <flux:sidebar.item
                    icon="megaphone"
                    :href="Route::has($routePrefix.'announcements.index') ? route($routePrefix.'announcements.index') : '#'"
                    :current="request()->routeIs($routePrefix.'announcements.*')"
                    wire:navigate
                    :class="$navItemClass"
                >
                    {{ __('Announcements') }}
                </flux:sidebar.item>

                <flux:sidebar.item
                    icon="calendar-days"
                    :href="route($routePrefix.'timetable.index')"
                    :current="request()->routeIs($routePrefix.'timetable.*')"
                    wire:navigate
                    :class="$navItemClass"
                >
                    {{ __('Timetable') }}
                </flux:sidebar.item>

                @can('viewAny', \App\Models\Office::class)
                    <flux:sidebar.item
                        icon="building-office-2"
                        :href="route('admin.offices.index')"
                        :current="request()->routeIs('admin.offices.*')"
                        wire:navigate
                        :class="$navItemClass"
                        data-test="sidebar-offices"
                    >
                        {{ __('Offices') }}
                    </flux:sidebar.item>
                @endcan

                @endunless

                <flux:sidebar.item
                    icon="bell"
                    :href="Route::has($routePrefix.'notifications.index') ? route($routePrefix.'notifications.index') : '#'"
                    :current="request()->routeIs($routePrefix.'notifications.*')"
                    wire:navigate
                    :class="$navItemClass"
                >
                    {{ __('Notifications') }}
                </flux:sidebar.item>

                @if ($isSuperAdmin)
                    <flux:sidebar.item
                        icon="shield-check"
                        :href="Route::has('super-admin.audit-log.index') ? route('super-admin.audit-log.index') : '#'"
                        :current="request()->routeIs('super-admin.audit-log.*')"
                        wire:navigate
                        :class="$navItemClass"
                    >
                        {{ __('Audit Log') }}
                    </flux:sidebar.item>

                    <flux:sidebar.item
                        icon="cog"
                        :href="Route::has('super-admin.settings.edit') ? route('super-admin.settings.edit') : '#'"
                        :current="request()->routeIs('super-admin.settings.*')"
                        wire:navigate
                        :class="$navItemClass"
                    >
                        {{ __('System Settings') }}
                    </flux:sidebar.item>
                @endif
            </flux:sidebar.nav>

            <flux:spacer />

            <flux:dropdown position="top" align="start" class="mt-auto">
                <button type="button" class="flex w-full items-center gap-3 rounded-xl bg-white/8 p-3 text-left" data-test="sidebar-user-menu-button">
                    <flux:avatar size="sm" :name="auth()->user()->name" :initials="auth()->user()->initials()" />
                    <span class="flex flex-1 flex-col leading-tight">
                        <span class="text-sm font-semibold">{{ auth()->user()->name }}</span>
                        <span class="text-xs text-white/72">
                            @if ($isSuperAdmin)
                                {{ __('Super Admin') }}
                            @elseif ($staffRole === \App\Enums\RoleName::Dean)
                                {{ __('Dean').' · '.(auth()->user()->faculty?->code ?? '') }}
                            @else
                                {{ __($staffRole?->label() ?? 'Admin Officer').' · '.(auth()->user()->department?->code ?? '') }}
                            @endif
                        </span>
                    </span>
                    <flux:icon icon="ellipsis-vertical" variant="mini" class="size-4.5 text-white/80" />
                </button>

                <flux:menu>
                    <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                        {{ __('Settings') }}
                    </flux:menu.item>

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full cursor-pointer" data-test="logout-button">
                            {{ __('Log out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:sidebar>

        <flux:header class="border-b border-border bg-white">
            <flux:sidebar.toggle class="text-ink lg:hidden" icon="bars-2" />

            @unless ($isReviewer)
            <form
                action="{{ Route::has($routePrefix.'applications.index') ? route($routePrefix.'applications.index') : '#' }}"
                method="GET"
                class="ms-2 flex h-11 flex-1 basis-80 items-center gap-2.5 rounded-xl border border-border-input bg-[#FAF8F9] px-3.5 text-subtle sm:max-w-[520px]"
            >
                <flux:icon icon="magnifying-glass" variant="outline" class="size-4.5" />
                <label class="sr-only" for="admin-top-search">{{ __('Search') }}</label>
                <input
                    id="admin-top-search"
                    type="search"
                    name="search"
                    placeholder="{{ __('Search by application ID, student name or reg. no.') }}"
                    class="min-w-0 flex-1 border-0 bg-transparent font-sans text-sm text-ink outline-none placeholder:text-placeholder"
                />
            </form>
            @endunless

            <flux:spacer />

            <livewire:notifications.bell variant="light" />
        </flux:header>

        <flux:main container>
            @if (auth()->user()?->googleAccount?->needs_reconnect)
                <div class="mb-5 flex flex-wrap items-center gap-3 rounded-[14px] border border-[#F2D49B] bg-[#FFF1D6] px-4 py-3 text-sm text-[#7A4A00]" role="alert" data-test="gmail-reconnect-banner">
                    <flux:icon icon="exclamation-triangle" variant="mini" class="size-5 shrink-0" />
                    <span class="flex-1">{{ __('Reconnect your Gmail. Google no longer accepts the portal\'s access, so your correspondence is saved but not emailed until you reconnect.') }}</span>
                    <flux:button size="sm" :href="route('google.connect')">{{ __('Reconnect Gmail') }}</flux:button>
                </div>
            @endif

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
