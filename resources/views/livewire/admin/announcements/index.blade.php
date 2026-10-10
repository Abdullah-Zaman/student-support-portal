<div class="flex flex-col gap-6 py-7">
    <x-page-header :title="__('Announcements')" :subtitle="$this->isSuperAdmin ? __('Visible to students of each announcement\'s department.') : __('Visible to every student in your department.')">
        <x-slot:actions>
            <flux:button variant="primary" icon="plus" wire:click="startCreate">{{ __('New announcement') }}</flux:button>
        </x-slot:actions>
    </x-page-header>

    @if ($this->announcements->isEmpty())
        <x-empty-state icon="megaphone" :title="__('No announcements yet')" :description="__('Create your first announcement for your department.')" />
    @else
        <x-panel :title="__('Announcements')">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[760px] border-collapse text-sm">
                    <thead>
                        <tr class="text-left text-xs tracking-[0.06em] text-subtle uppercase">
                            <th class="px-[22px] py-3 font-semibold">{{ __('Title') }}</th>
                            @if ($this->isSuperAdmin)
                                <th class="px-3 py-3 font-semibold">{{ __('Department') }}</th>
                            @endif
                            <th class="px-3 py-3 font-semibold">{{ __('Status') }}</th>
                            <th class="px-3 py-3 font-semibold">{{ __('Publishes') }}</th>
                            <th class="px-3 py-3 font-semibold">{{ __('Expires') }}</th>
                            <th class="px-[22px] py-3 font-semibold"><span class="sr-only">{{ __('Actions') }}</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->announcements as $announcement)
                            @php
                                $statusColor = match ($announcement->statusLabel()) {
                                    'Live' => 'green',
                                    'Scheduled' => 'blue',
                                    'Expired' => 'zinc',
                                    'Inactive' => 'red',
                                };
                            @endphp
                            <tr class="border-t border-border-soft">
                                <td class="px-[22px] py-3.5">
                                    <span class="font-semibold text-ink">{{ $announcement->title }}</span>
                                </td>
                                @if ($this->isSuperAdmin)
                                    <td class="px-3 py-3.5 text-ink-soft">{{ $announcement->department->name }}</td>
                                @endif
                                <td class="px-3 py-3.5"><flux:badge :color="$statusColor">{{ __($announcement->statusLabel()) }}</flux:badge></td>
                                <td class="px-3 py-3.5 whitespace-nowrap text-ink-soft">{{ $announcement->publish_at?->format('j M Y, g:i A') ?? __('Immediately') }}</td>
                                <td class="px-3 py-3.5 whitespace-nowrap text-ink-soft">{{ $announcement->expires_at?->format('j M Y, g:i A') ?? __('Never') }}</td>
                                <td class="px-[22px] py-3.5 text-right">
                                    <div class="flex justify-end gap-2">
                                        <flux:button variant="ghost" size="sm" wire:click="startEdit({{ $announcement->id }})">{{ __('Edit') }}</flux:button>
                                        <flux:button variant="ghost" size="sm" wire:click="toggleActive({{ $announcement->id }})">
                                            {{ $announcement->is_active ? __('Deactivate') : __('Activate') }}
                                        </flux:button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-panel>

        <div>
            {{ $this->announcements->links() }}
        </div>
    @endif

    <flux:modal name="announcement-form" class="md:w-[32rem]">
        <div
            x-data="{ body: @js($body) }"
            class="flex flex-col gap-5"
        >
            <flux:heading size="lg">{{ $editing_id ? __('Edit announcement') : __('New announcement') }}</flux:heading>

            @if ($this->isSuperAdmin)
                <flux:select wire:model="department_id" :label="__('Department')">
                    <flux:select.option value="">{{ __('Select a department') }}</flux:select.option>
                    @foreach ($this->departments as $department)
                        <flux:select.option :value="(string) $department->id">{{ $department->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            @endif

            <flux:input wire:model="title" :label="__('Title')" maxlength="150" />

            <div class="flex flex-col gap-1">
                <flux:textarea wire:model="body" x-model="body" :label="__('Body')" rows="5" maxlength="2000" />
                <span class="self-end text-xs text-subtle" x-text="body.length + ' / 2000'"></span>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <flux:input type="datetime-local" wire:model="publish_at" :label="__('Publish at')" :description="__('Leave blank to publish immediately.')" />
                <flux:input type="datetime-local" wire:model="expires_at" :label="__('Expires at')" :description="__('Leave blank to never expire.')" />
            </div>

            <div class="flex flex-col gap-1">
                <flux:input type="file" wire:model="image" :label="__('Image (optional)')" />
                <flux:description>{{ __('JPG, JPEG, PNG or WEBP. Up to 2 MB.') }}</flux:description>
            </div>

            <div class="flex flex-col gap-1">
                <flux:input type="file" wire:model="attachment" :label="__('Attachment (optional)')" />
                <flux:description>{{ __('PDF, DOC or DOCX. Up to 5 MB.') }}</flux:description>
            </div>

            <flux:checkbox wire:model="is_active" :label="__('Active')" />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" wire:click="save">{{ __('Save') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
