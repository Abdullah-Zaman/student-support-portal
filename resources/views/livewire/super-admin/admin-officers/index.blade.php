<div class="flex flex-col gap-6 py-7">
    <x-page-header :title="__('Admin Officers')" :subtitle="__('One Admin Officer per department.')">
        <x-slot:actions>
            <flux:button variant="primary" icon="plus" wire:click="startCreate">{{ __('New Admin Officer') }}</flux:button>
        </x-slot:actions>
    </x-page-header>

    @if ($this->admins->isEmpty())
        <x-empty-state icon="user-group" :title="__('No Admin Officers yet')" :description="__('Create the first Admin Officer account.')" />
    @else
        <x-panel :title="__('Admin Officers')">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[720px] border-collapse text-sm">
                    <thead>
                        <tr class="text-left text-xs tracking-[0.06em] text-subtle uppercase">
                            <th class="px-[22px] py-3 font-semibold">{{ __('Name') }}</th>
                            <th class="px-3 py-3 font-semibold">{{ __('Department') }}</th>
                            <th class="px-3 py-3 font-semibold">{{ __('Status') }}</th>
                            <th class="px-[22px] py-3 font-semibold"><span class="sr-only">{{ __('Actions') }}</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->admins as $admin)
                            <tr class="border-t border-border-soft">
                                <td class="px-[22px] py-3.5">
                                    <span class="flex flex-wrap items-center gap-2 font-semibold text-ink">{{ $admin->name }} @if ($admin->is_test)<x-test-badge />@endif</span>
                                    <span class="block text-[13px] text-subtle">{{ $admin->email }}</span>
                                </td>
                                <td class="px-3 py-3.5 text-ink-soft">{{ $admin->department?->name }}</td>
                                <td class="px-3 py-3.5">
                                    <flux:badge :color="$admin->status->value === 'active' ? 'green' : 'red'" size="sm">
                                        {{ \Illuminate\Support\Str::headline($admin->status->value) }}
                                    </flux:badge>
                                </td>
                                <td class="px-[22px] py-3.5 text-right">
                                    <div class="flex justify-end gap-2">
                                        <flux:button variant="ghost" size="sm" wire:click="startEdit({{ $admin->id }})">{{ __('Edit') }}</flux:button>

                                        @if ($admin->status->value === 'suspended')
                                            <x-confirm-modal
                                                :name="'confirm-reactivate-admin-'.$admin->id"
                                                :heading="__('Reactivate this Admin Officer?')"
                                                :confirmLabel="__('Reactivate')"
                                                variant="primary"
                                                :confirmAction="'reactivate('.$admin->id.')'"
                                            >
                                                <x-slot:trigger>
                                                    <flux:button variant="ghost" size="sm">{{ __('Reactivate') }}</flux:button>
                                                </x-slot:trigger>
                                            </x-confirm-modal>
                                        @else
                                            <x-confirm-modal
                                                :name="'confirm-suspend-admin-'.$admin->id"
                                                :heading="__('Suspend this Admin Officer?')"
                                                :text="__('They will be logged out immediately and unable to log in until reactivated.')"
                                                :confirmLabel="__('Suspend')"
                                                variant="danger"
                                                :confirmAction="'suspend('.$admin->id.')'"
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
            {{ $this->admins->links() }}
        </div>
    @endif

    <flux:modal name="admin-officer-form" class="md:w-[28rem]">
        <div class="flex flex-col gap-5">
            <flux:heading size="lg">{{ $editing_id ? __('Edit Admin Officer') : __('New Admin Officer') }}</flux:heading>

            <flux:input wire:model="name" :label="__('Full name')" />
            <flux:input type="email" wire:model="email" :label="__('University email')" :description="__('Must end with :domain', ['domain' => config('students.email_domain')])" />

            <flux:select wire:model="department_id" :label="__('Department')">
                <flux:select.option value="">{{ __('Select a department') }}</flux:select.option>
                @foreach ($this->departments as $department)
                    <flux:select.option :value="(string) $department->id">{{ $department->name }}</flux:select.option>
                @endforeach
            </flux:select>

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
