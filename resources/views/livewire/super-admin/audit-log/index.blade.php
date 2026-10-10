<div class="flex flex-col gap-6 py-7">
    <x-page-header :title="__('Audit Log')" :subtitle="__('Read-only record of logins and important changes, across every department.')" />

    <div class="flex flex-wrap items-end gap-4 rounded-[14px] border border-border bg-white px-6 py-5">
        <div class="flex min-w-0 flex-1 basis-48 flex-col gap-1">
            <flux:select wire:model.live="causer_id" :label="__('User')">
                <flux:select.option value="">{{ __('Anyone') }}</flux:select.option>
                @foreach ($this->causers as $causer)
                    <flux:select.option :value="(string) $causer->id">{{ $causer->name }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        <div class="flex min-w-0 flex-1 basis-48 flex-col gap-1">
            <flux:select wire:model.live="subject_type" :label="__('Type')">
                <flux:select.option value="">{{ __('Everything') }}</flux:select.option>
                @foreach ($this->subjectTypes as $value => $label)
                    <flux:select.option :value="$value">{{ $label }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        <div class="flex min-w-0 flex-1 basis-40 flex-col gap-1">
            <flux:input type="date" wire:model.live="date_from" :label="__('From')" />
        </div>
        <div class="flex min-w-0 flex-1 basis-40 flex-col gap-1">
            <flux:input type="date" wire:model.live="date_to" :label="__('To')" />
        </div>

        @if ($this->hasActiveFilters)
            <flux:button variant="ghost" wire:click="clearFilters">{{ __('Clear filters') }}</flux:button>
        @endif
    </div>

    @if ($this->activities->isEmpty())
        <x-empty-state icon="shield-check" :title="__('No activity found')" :description="__('Try a different filter.')" />
    @else
        <x-panel :title="__('Activity')">
            <ul class="m-0 flex flex-col">
                @foreach ($this->activities as $activity)
                    @php
                        $diff = $this->diffFor($activity);
                    @endphp
                    <li class="flex flex-col gap-1.5 border-t border-border-soft px-[22px] py-3.5 first:border-t-0">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <span class="text-sm font-semibold text-ink">
                                {{ $activity->causer?->name ?? __('Unknown / unauthenticated') }}
                                <span class="font-normal text-ink-soft">&middot; {{ \Illuminate\Support\Str::headline($activity->event ?? $activity->description) }}</span>
                                @if ($activity->subject_type)
                                    <span class="font-normal text-subtle">&middot; {{ \Illuminate\Support\Str::headline(class_basename($activity->subject_type)) }} #{{ $activity->subject_id }}</span>
                                @endif
                            </span>
                            <span class="text-[13px] text-subtle">{{ $activity->created_at->format('j M Y, g:i A') }}</span>
                        </div>

                        @if ($activity->event === 'login_failed' && $activity->properties->get('email'))
                            <span class="text-[13px] text-subtle">{{ __('Attempted email: :email', ['email' => $activity->properties->get('email')]) }}</span>
                        @endif

                        @if (! empty($diff['attributes']))
                            <ul class="m-0 flex flex-col gap-0.5 text-[13px] text-ink-soft">
                                @foreach ($diff['attributes'] as $field => $newValue)
                                    <li>
                                        <span class="font-medium text-subtle">{{ \Illuminate\Support\Str::headline($field) }}:</span>
                                        {{ $diff['old'][$field] ?? __('(none)') }} &rarr; {{ $newValue }}
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </li>
                @endforeach
            </ul>
        </x-panel>

        <div>
            {{ $this->activities->links() }}
        </div>
    @endif
</div>
