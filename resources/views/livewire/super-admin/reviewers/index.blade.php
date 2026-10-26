<div class="flex flex-col gap-6 py-7">
    <x-page-header :title="__('HODs & Deans')" :subtitle="__('One active HOD per department and one active Dean per faculty.')">
        <x-slot:actions>
            <flux:button variant="primary" icon="plus" wire:click="startCreate">{{ __('New HOD or Dean') }}</flux:button>
        </x-slot:actions>
    </x-page-header>

    @if ($this->reviewers->isEmpty())
        <x-empty-state icon="user-group" :title="__('No HODs or Deans yet')" :description="__('Create an HOD or Dean account so applications can be forwarded to them.')" />
    @else
        <x-panel :title="__('HODs & Deans')">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[720px] border-collapse text-sm">
                    <thead>
                        <tr class="text-left text-xs tracking-[0.06em] text-subtle uppercase">
                            <th class="px-[22px] py-3 font-semibold">{{ __('Name') }}</th>
                            <th class="px-3 py-3 font-semibold">{{ __('Role') }}</th>
                            <th class="px-3 py-3 font-semibold">{{ __('Department / faculty') }}</th>
                            <th class="px-3 py-3 font-semibold">{{ __('Status') }}</th>
                            <th class="px-[22px] py-3 font-semibold"><span class="sr-only">{{ __('Actions') }}</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->reviewers as $reviewer)
                            @php
                                $isDean = $reviewer->hasRole(\App\Enums\RoleName::Dean->value);
                            @endphp
                            <tr class="border-t border-border-soft" wire:key="reviewer-{{ $reviewer->id }}">
                                <td class="px-[22px] py-3.5">
                                    <span class="font-semibold text-ink">{{ $reviewer->name }}</span>
                                    <span class="block text-[13px] text-subtle">{{ $reviewer->email }}</span>
                                </td>
                                <td class="px-3 py-3.5">
                                    <flux:badge :color="$isDean ? 'blue' : 'purple'" size="sm">{{ $isDean ? __('Dean') : __('HOD') }}</flux:badge>
                                </td>
                                <td class="px-3 py-3.5 text-ink-soft">{{ $isDean ? $reviewer->faculty?->name : $reviewer->department?->name }}</td>
                                <td class="px-3 py-3.5">
                                    <flux:badge :color="$reviewer->status->value === 'active' ? 'green' : 'red'" size="sm">
                                        {{ \Illuminate\Support\Str::headline($reviewer->status->value) }}
                                    </flux:badge>
                                </td>
                                <td class="px-[22px] py-3.5 text-right">
                                    <div class="flex justify-end gap-2">
                                        <flux:button variant="ghost" size="sm" wire:click="startEdit({{ $reviewer->id }})">{{ __('Edit') }}</flux:button>

                                        @if ($reviewer->status->value === 'suspended')
                                            <x-confirm-modal
                                                :name="'confirm-reactivate-reviewer-'.$reviewer->id"
                                                :heading="__('Reactivate this account?')"
                                                :confirmLabel="__('Reactivate')"
                                                variant="primary"
                                                :confirmAction="'reactivate('.$reviewer->id.')'"
                                            >
                                                <x-slot:trigger>
                                                    <flux:button variant="ghost" size="sm">{{ __('Reactivate') }}</flux:button>
                                                </x-slot:trigger>
                                            </x-confirm-modal>
                                        @else
                                            <x-confirm-modal
                                                :name="'confirm-suspend-reviewer-'.$reviewer->id"
                                                :heading="__('Suspend this account?')"
                                                :text="__('They will be logged out immediately. Applications can no longer be forwarded to them.')"
                                                :confirmLabel="__('Suspend')"
                                                variant="danger"
                                                :confirmAction="'suspend('.$reviewer->id.')'"
                                            >
                                                <x-slot:trigger>
                                                    <flux:button variant="ghost" size="sm">{{ __('Suspend') }}</flux:button>
                                                </x-slot:trigger>
                                            </x-confirm-modal>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-panel>

        <div>
            {{ $this->reviewers->links() }}
        </div>
    @endif

    <flux:modal name="reviewer-form" class="md:w-[28rem]">
        <div class="flex flex-col gap-5">
            <flux:heading size="lg">{{ $editing_id ? __('Edit account') : __('New HOD or Dean') }}</flux:heading>

            <flux:radio.group wire:model.live="role" :label="__('Role')" variant="segmented">
                <flux:radio :value="\App\Enums\RoleName::Hod->value" :label="__('HOD')" />
                <flux:radio :value="\App\Enums\RoleName::Dean->value" :label="__('Dean')" />
            </flux:radio.group>

            <flux:input wire:model="name" :label="__('Full name')" />
            <flux:input type="email" wire:model="email" :label="__('University email')" :description="__('Must end with :domain', ['domain' => config('students.email_domain')])" />

            @if ($role === \App\Enums\RoleName::Dean->value)
                <flux:select wire:model="faculty_id" :label="__('Faculty')">
                    <flux:select.option value="">{{ __('Select a faculty') }}</flux:select.option>
                    @foreach ($this->faculties as $faculty)
                        <flux:select.option :value="(string) $faculty->id">{{ $faculty->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            @else
                <flux:select wire:model="department_id" :label="__('Department')">
                    <flux:select.option value="">{{ __('Select a department') }}</flux:select.option>
                    @foreach ($this->departments as $department)
                        <flux:select.option :value="(string) $department->id">{{ $department->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            @endif

            <flux:input type="password" wire:model="password" :label="__('Password')" :description="$editing_id ? __('Leave blank to keep the current password.') : null" />
            <flux:input type="password" wire:model="password_confirmation" :label="__('Confirm password')" />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" wire:click="save">{{ __('Save') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
