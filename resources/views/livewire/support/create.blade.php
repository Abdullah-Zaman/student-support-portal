<div class="flex flex-col gap-6 py-8">
    <x-page-header :title="__('Contact Super Admin')" :subtitle="__('Tell the Super Admin what went wrong. Nothing from your conversations or applications is added unless you write or attach it yourself.')" />

    <x-card class="max-w-2xl">
        <form wire:submit="save" class="flex flex-col gap-5" data-test="support-request-form">
            <flux:select wire:model="category" :label="__('Category')">
                @foreach (\App\Enums\SupportRequestCategory::cases() as $category)
                    <flux:select.option :value="$category->value">{{ __($category->label()) }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:input wire:model="subject" :label="__('Subject')" maxlength="150" required />

            <flux:textarea wire:model="message" :label="__('Message')" rows="6" maxlength="3000" :placeholder="__('What happened, and what did you expect?')" required />

            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <flux:input wire:model="application_no" :label="__('Related application (optional)')" :placeholder="__('SC-2026-000124')" />
                <flux:input wire:model="student_registration_no" :label="__('Related student (optional)')" :placeholder="__('SU92-BSSEM-F22-171')" />
            </div>

            <flux:field>
                <flux:label>{{ __('Screenshots (optional)') }}</flux:label>
                <flux:input type="file" wire:model="attachments" multiple accept="image/png,image/jpeg,application/pdf" />
                <flux:description>{{ __('JPG, PNG or PDF. Up to 3 files, 5 MB each.') }}</flux:description>
                <flux:error name="attachments" />
                @foreach ($errors->get('attachments.*') as $fileErrors)
                    @foreach ($fileErrors as $fileError)
                        <p class="m-0 text-sm font-medium text-red-600" data-test="attachment-error">{{ $fileError }}</p>
                    @endforeach
                @endforeach
            </flux:field>

            @if ($attachments)
                <ul class="m-0 flex list-none flex-col gap-1.5 p-0">
                    @foreach ($attachments as $index => $file)
                        <li class="flex items-center justify-between gap-3 rounded-lg border border-border-soft bg-page px-3 py-2 text-sm">
                            <span class="truncate text-ink-soft">{{ $file->getClientOriginalName() }}</span>
                            <flux:button type="button" variant="ghost" size="sm" icon="x-mark" wire:click="removeAttachment({{ $index }})" />
                        </li>
                    @endforeach
                </ul>
            @endif

            <div class="flex justify-end gap-2">
                <flux:button :href="route('support.index')" variant="ghost" wire:navigate>{{ __('Cancel') }}</flux:button>
                <flux:button type="submit" variant="primary" icon="paper-airplane" wire:loading.attr="disabled" wire:target="save,attachments" data-test="send-support-request">{{ __('Send to Super Admin') }}</flux:button>
            </div>
        </form>
    </x-card>
</div>
