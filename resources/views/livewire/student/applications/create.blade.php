<div class="flex flex-col gap-6 py-8">
    <x-page-header :title="__('New application')" :subtitle="__('Tell us what\'s going on and the Admin Office will take it from here.')" />

    <x-card class="max-w-2xl">
        <div
            x-data="{
                subject: @js($subject),
                body: @js($body),
                failedUploads: [],
                uploadErrorReason(file) {
                    if (file.size > {{ \App\Livewire\Student\Applications\Create::ATTACHMENT_MAX_KILOBYTES }} * 1024) {
                        return @js(__('is too large. Maximum size is 5 MB.'));
                    }

                    if (! /\.(pdf|jpe?g|png|docx?)$/i.test(file.name)) {
                        return @js(__('is not an allowed type. Use PDF, JPG, PNG, DOC or DOCX.'));
                    }

                    return @js(__('could not be uploaded because of a server or network problem. Please try again, and contact the Admin Office if it keeps failing.'));
                },
                onUploadError(event) {
                    this.failedUploads = Array.from(event.target.files ?? []).map((file) => ({ name: file.name, reason: this.uploadErrorReason(file) }));
                },
            }"
            x-on:livewire-upload-error="onUploadError($event)"
            class="flex flex-col gap-5"
        >
            <x-validated-field>
                <flux:select wire:model="category_id" :label="__('Category')" :placeholder="__('Select a category')" required>
                    @foreach ($this->categories as $category)
                        <flux:select.option :value="(string) $category->id">{{ $category->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </x-validated-field>

            <x-validated-field>
                <div class="flex flex-col gap-1">
                    <flux:input
                        wire:model="subject"
                        x-model="subject"
                        :label="__('Subject')"
                        type="text"
                        required
                        maxlength="{{ $this->subjectMax }}"
                        :placeholder="__('A short summary of your issue')"
                    />
                    <span class="self-end text-xs text-subtle" x-text="subject.length + ' / {{ $this->subjectMax }}'"></span>
                </div>
            </x-validated-field>

            <x-validated-field>
                <div class="flex flex-col gap-1">
                    <flux:textarea
                        wire:model="body"
                        x-model="body"
                        :label="__('Application body')"
                        required
                        rows="6"
                        maxlength="{{ $this->bodyMax }}"
                        :placeholder="__('Describe your issue in detail')"
                    />
                    <span class="self-end text-xs text-subtle" x-text="body.length + ' / {{ $this->bodyMax }}'"></span>
                </div>
            </x-validated-field>

            <x-validated-field>
                <flux:field>
                    <flux:label>{{ __('Attachments (optional)') }}</flux:label>
                    <flux:input type="file" wire:model="attachments" multiple x-on:livewire-upload-start="failedUploads = []" />
                    <flux:description>{{ __('PDF, JPG, PNG, DOC or DOCX. Up to 3 files, 5 MB each.') }}</flux:description>
                    <flux:error name="attachments" />
                </flux:field>
            </x-validated-field>

            <template x-for="failed in failedUploads" :key="failed.name">
                <p class="text-sm font-medium text-red-600" x-text="failed.name + ' ' + failed.reason"></p>
            </template>

            @if ($attachments)
                <ul class="flex flex-col gap-1.5">
                    @foreach ($attachments as $index => $file)
                        <li class="flex items-center justify-between gap-3 rounded-lg border border-border-soft bg-page px-3 py-2 text-sm">
                            <span class="truncate text-ink-soft">
                                {{ $file->getClientOriginalName() }}
                                <span class="text-subtle">({{ \Illuminate\Support\Number::fileSize($file->getSize(), precision: 1) }})</span>
                            </span>
                            <flux:button type="button" variant="ghost" size="sm" icon="x-mark" wire:click="removeAttachment({{ $index }})" />
                        </li>
                    @endforeach
                </ul>
            @endif

            <div class="flex items-center justify-end gap-3 pt-2">
                <x-confirm-modal
                    name="confirm-submit-application"
                    :heading="__('Submit this application?')"
                    :text="__('Once submitted, it cannot be edited. Make sure everything is correct before continuing.')"
                    :confirmLabel="__('Submit application')"
                    :cancelLabel="__('Review again')"
                    variant="primary"
                    confirmAction="submit"
                >
                    <x-slot:trigger>
                        <flux:button type="button" variant="primary" wire:loading.attr="disabled" wire:target="submit">
                            {{ __('Submit application') }}
                        </flux:button>
                    </x-slot:trigger>
                </x-confirm-modal>
            </div>
        </div>
    </x-card>
</div>
