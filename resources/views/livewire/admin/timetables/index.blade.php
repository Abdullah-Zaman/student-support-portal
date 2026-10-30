<div class="flex flex-col gap-6 py-7">
    <x-page-header :title="__('Timetable')" :subtitle="__('Upload the department timetable (.xlsx), check the preview, then publish it to students.')" />

    <livewire:admin.timetables.explorer :department-id="$department_id" />

    @if ($this->isSuperAdmin === false || $department_id !== '')
        <livewire:admin.timetables.share-card :department-id="$department_id" />
    @endif

    <x-card>
        <form
            wire:submit="uploadTimetable"
            class="flex flex-wrap items-end gap-4"
            x-data="{ uploading: false, uploadFailed: false }"
            x-on:livewire-upload-start="uploading = true; uploadFailed = false"
            x-on:livewire-upload-finish="uploading = false"
            x-on:livewire-upload-cancel="uploading = false"
            x-on:livewire-upload-error="uploading = false; uploadFailed = true"
        >
            @if ($this->isSuperAdmin)
                <div class="flex min-w-0 flex-1 basis-56 flex-col gap-1">
                    <flux:select wire:model.live="department_id" :label="__('Department')">
                        <flux:select.option value="">{{ __('Select a department') }}</flux:select.option>
                        @foreach ($this->departments as $department)
                            <flux:select.option :value="(string) $department->id">{{ $department->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>
            @endif

            <div class="flex min-w-0 flex-[2_1_20rem] flex-col gap-1">
                <flux:input type="file" wire:model="file" :label="__('Timetable file')" accept=".xlsx" />
                <flux:description>{{ __('Excel .xlsx with the weekday and weekend sheets. Up to 10 MB.') }}</flux:description>
                <p x-show="uploadFailed" x-cloak class="m-0 text-sm text-red-600" role="alert" data-test="timetable-upload-failed">
                    {{ __('The file could not be uploaded. Check that it is an .xlsx file under 10 MB and try again.') }}
                </p>
            </div>

            <flux:button type="submit" variant="primary" icon="arrow-up-tray" wire:loading.attr="disabled" wire:target="file,uploadTimetable" x-bind:disabled="uploading">
                <span wire:loading.remove wire:target="file,uploadTimetable">{{ __('Upload and preview') }}</span>
                <span wire:loading wire:target="file">{{ __('Uploading…') }}</span>
                <span wire:loading wire:target="uploadTimetable">{{ __('Reading file…') }}</span>
            </flux:button>
        </form>
    </x-card>

    @if ($this->versions->isEmpty())
        <x-empty-state icon="calendar-days" :title="__('No timetable uploaded yet')" :description="__('Upload the timetable Excel file to get started.')" />
    @else
        <x-panel :title="__('Versions')">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[820px] border-collapse text-sm">
                    <thead>
                        <tr class="text-left text-xs tracking-[0.06em] text-subtle uppercase">
                            <th class="px-[22px] py-3 font-semibold">{{ __('Version') }}</th>
                            @if ($this->isSuperAdmin)
                                <th class="px-3 py-3 font-semibold">{{ __('Department') }}</th>
                            @endif
                            <th class="px-3 py-3 font-semibold">{{ __('Status') }}</th>
                            <th class="px-3 py-3 font-semibold">{{ __('Classes') }}</th>
                            <th class="px-3 py-3 font-semibold">{{ __('Unparsed') }}</th>
                            <th class="px-3 py-3 font-semibold">{{ __('Uploaded') }}</th>
                            <th class="px-[22px] py-3 font-semibold"><span class="sr-only">{{ __('Actions') }}</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->versions as $version)
                            @php
                                [$statusColor, $statusIcon] = match ($version->status) {
                                    \App\Enums\TimetableVersionStatus::Published => ['green', 'check-circle'],
                                    \App\Enums\TimetableVersionStatus::Draft => ['blue', 'pencil-square'],
                                    \App\Enums\TimetableVersionStatus::Archived => ['zinc', 'archive-box'],
                                };
                                $unparsedCount = count($version->unparsed_cells ?? []);
                            @endphp
                            <tr class="border-t border-border-soft" wire:key="version-{{ $version->id }}">
                                <td class="px-[22px] py-3.5">
                                    <a href="{{ route($this->routePrefix().'timetable.show', ['version' => $version->id]) }}" wire:navigate class="flex flex-col gap-0.5 no-underline">
                                        <span class="font-semibold text-ink">{{ __('Version :number', ['number' => $version->version_no]) }}</span>
                                        <span class="max-w-72 truncate text-[13px] text-subtle">{{ $version->original_name }}</span>
                                    </a>
                                </td>
                                @if ($this->isSuperAdmin)
                                    <td class="px-3 py-3.5 text-ink-soft">{{ $version->department->name }}</td>
                                @endif
                                <td class="px-3 py-3.5">
                                    <flux:badge :color="$statusColor" :icon="$statusIcon" size="sm">{{ __(ucfirst($version->status->value)) }}</flux:badge>
                                </td>
                                <td class="px-3 py-3.5 text-ink-soft tabular-nums">{{ $version->class_count }}</td>
                                <td class="px-3 py-3.5 tabular-nums {{ $unparsedCount > 0 ? 'font-semibold text-[#7A4A00]' : 'text-ink-soft' }}">{{ $unparsedCount }}</td>
                                <td class="px-3 py-3.5 whitespace-nowrap text-ink-soft">
                                    <span class="flex flex-col gap-0.5">
                                        <span>{{ $version->created_at->format('j M Y, g:i A') }}</span>
                                        <span class="text-[13px] text-subtle">{{ $version->uploader->name }}</span>
                                    </span>
                                </td>
                                <td class="px-[22px] py-3.5 text-right">
                                    <div class="flex justify-end gap-2">
                                        <flux:button variant="ghost" size="sm" :href="route($this->routePrefix().'timetable.show', ['version' => $version->id])" wire:navigate>{{ __('Preview') }}</flux:button>
                                        <flux:button variant="ghost" size="sm" icon="arrow-down-tray" :href="route('timetable.download', $version->id)">{{ __('Download') }}</flux:button>

                                        @if ($version->status !== \App\Enums\TimetableVersionStatus::Published)
                                            @php
                                                $isRestore = $version->status === \App\Enums\TimetableVersionStatus::Archived;
                                            @endphp
                                            <x-confirm-modal
                                                :name="'publish-version-'.$version->id"
                                                :heading="$isRestore ? __('Restore version :number?', ['number' => $version->version_no]) : __('Publish version :number?', ['number' => $version->version_no])"
                                                :text="__('Students will see this timetable instead of the current one and get a notification.')"
                                                :confirm-label="$isRestore ? __('Restore') : __('Publish')"
                                                variant="primary"
                                                :confirm-action="'publish('.$version->id.')'"
                                            >
                                                <x-slot:trigger>
                                                    <flux:button variant="primary" size="sm">{{ $isRestore ? __('Restore') : __('Publish') }}</flux:button>
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
            {{ $this->versions->links() }}
        </div>
    @endif
</div>
