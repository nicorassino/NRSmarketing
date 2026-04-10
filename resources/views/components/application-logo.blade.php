@if (file_exists(public_path('images/logo-nr.png')))
    <img
        src="{{ asset('images/logo-nr.png') }}"
        alt="NR Marketing"
        {{ $attributes->merge(['class' => 'object-contain']) }}
    />
@else
    <svg viewBox="0 0 120 32" xmlns="http://www.w3.org/2000/svg" {{ $attributes }}>
        <rect x="1" y="1" width="118" height="30" rx="6" fill="none" stroke="currentColor" stroke-width="2"/>
        <text x="12" y="22" fill="currentColor" font-size="14" font-family="Arial, sans-serif" font-weight="700">NR MARKETING</text>
    </svg>
@endif
