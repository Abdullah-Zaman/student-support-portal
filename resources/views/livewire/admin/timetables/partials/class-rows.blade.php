<div class="border-t border-border-soft first:border-t-0">
    <h3 class="m-0 bg-page px-[22px] py-2 text-xs font-semibold tracking-[0.06em] text-subtle uppercase">{{ $title }}</h3>

    @if ($classes->isNotEmpty())
        <div class="overflow-x-auto">
            <table class="w-full min-w-[560px] border-collapse text-sm">
                <tbody>
                    @foreach ($classes as $class)
                        <tr class="border-t border-border-soft first:border-t-0">
                            @foreach ($columns as $column)
                                <td class="px-3 py-2.5 first:pl-[22px] last:pr-[22px] {{ $column === 'time' ? 'whitespace-nowrap tabular-nums text-ink' : 'text-ink-soft' }}">
                                    {{ match ($column) {
                                        'time' => $class->timeLabel(),
                                        'course' => $class->course,
                                        'teacher' => $class->teacher ?? '—',
                                        'room' => $class->room,
                                        'sections' => $class->sections->pluck('section')->implode(', '),
                                    } }}
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @elseif (! isset($free))
        <p class="m-0 px-[22px] py-3 text-sm text-subtle">{{ __('No classes.') }}</p>
    @endif

    @isset($free)
        <p class="m-0 px-[22px] py-2.5 text-sm text-ink-soft" data-test="free-slots">
            <span class="font-semibold text-[#1F6A3A]">{{ __('Free') }}:</span>
            {{ $free === [] ? __('No free slots') : implode(', ', $free) }}
        </p>
    @endisset
</div>
