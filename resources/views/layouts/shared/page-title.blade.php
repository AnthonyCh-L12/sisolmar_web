
  @push('page-heading')
  <div class="hidden md:flex flex-col justify-center leading-tight {{ $headingClass ?? '' }}">
      <h5 class="text-lg font-bold text-gray-900 dark:text-slate-100 tracking-tight">
          {{ $title }}
      </h5>
      <div class="flex items-center gap-1.5 mt-0.5">
          <span class="text text-gray-400 dark:text-slate-500">Sisolmar Web</span>
          <i class="i-tabler-chevron-right text-xs text-gray-300 dark:text-slate-600 flex-shrink-0"></i>
          <span class="text text-gray-400 dark:text-slate-500">{{ $subtitle }}</span>
          <i class="i-tabler-chevron-right text-xs text-gray-300 dark:text-slate-600 flex-shrink-0"></i>
          <span class="text font-semibold text-gray-500 dark:text-slate-400">{{ $title }}</span>
      </div>
  </div>
  @endpush
