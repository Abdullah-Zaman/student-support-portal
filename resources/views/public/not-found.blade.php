<x-public-layout :title="__('Page not found')">
    <div class="flex flex-col items-center gap-3 py-16 text-center">
        <span class="font-display text-[64px] leading-none font-semibold text-plum">404</span>
        <h1 class="m-0 text-[22px] font-bold text-ink">{{ __('Page not found') }}</h1>
        <p class="m-0 text-[15px] text-muted">{{ __('The page you are looking for doesn\'t exist or has moved.') }}</p>
    </div>
</x-public-layout>
