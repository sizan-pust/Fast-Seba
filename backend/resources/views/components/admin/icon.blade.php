@props([
    'name' => 'circle',
    'size' => 24,
    'stroke' => 2,
])

<svg
    xmlns="http://www.w3.org/2000/svg"
    width="{{ $size }}"
    height="{{ $size }}"
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    stroke-width="{{ $stroke }}"
    stroke-linecap="round"
    stroke-linejoin="round"
    {{ $attributes->merge(['class' => 'icon']) }}
>
    @switch($name)
        @case('dashboard')
            <path d="M4 4h6v8H4z"/>
            <path d="M14 4h6v5h-6z"/>
            <path d="M14 13h6v7h-6z"/>
            <path d="M4 16h6v4H4z"/>
            @break
        @case('package')
            <path d="m12 3 8 4-8 4-8-4 8-4z"/>
            <path d="m4 7 8 4 8-4"/>
            <path d="M4 7v10l8 4 8-4V7"/>
            @break
        @case('return')
            <path d="M9 14 4 9l5-5"/>
            <path d="M4 9h10a6 6 0 0 1 6 6v1"/>
            @break
        @case('truck')
            <path d="M3 6h11v10H3z"/>
            <path d="M14 9h4l3 3v4h-7z"/>
            <circle cx="7" cy="18" r="2"/>
            <circle cx="18" cy="18" r="2"/>
            @break
        @case('category')
            <rect x="3" y="3" width="7" height="7" rx="1"/>
            <rect x="14" y="3" width="7" height="7" rx="1"/>
            <rect x="3" y="14" width="7" height="7" rx="1"/>
            <rect x="14" y="14" width="7" height="7" rx="1"/>
            @break
        @case('box')
            <path d="m12 3 8 4-8 4-8-4 8-4z"/>
            <path d="M4 7v10l8 4 8-4V7"/>
            <path d="M12 11v10"/>
            @break
        @case('sparkles')
            <path d="m12 3 1.2 3.8L17 8l-3.8 1.2L12 13l-1.2-3.8L7 8l3.8-1.2L12 3z"/>
            <path d="m19 14 .7 2.3L22 17l-2.3.7L19 20l-.7-2.3L16 17l2.3-.7L19 14z"/>
            <path d="m5 13 .7 2.3L8 16l-2.3.7L5 19l-.7-2.3L2 16l2.3-.7L5 13z"/>
            @break
        @case('inventory')
            <path d="M4 5h16v4H4z"/>
            <path d="M5 9v10h14V9"/>
            <path d="M9 13h6"/>
            @break
        @case('percentage')
            <line x1="5" y1="19" x2="19" y2="5"/>
            <circle cx="7" cy="7" r="2"/>
            <circle cx="17" cy="17" r="2"/>
            @break
        @case('users')
            <circle cx="9" cy="7" r="4"/>
            <path d="M3 21v-2a6 6 0 0 1 12 0v2"/>
            <path d="M16 3.1a4 4 0 0 1 0 7.8"/>
            <path d="M21 21v-2a6 6 0 0 0-4-5.7"/>
            @break
        @case('seller')
            <path d="M4 10h16"/>
            <path d="M5 10V7l2-4h10l2 4v3"/>
            <path d="M6 10v10h12V10"/>
            <path d="M9 14h6"/>
            @break
        @case('store')
            <path d="M3 9l2-5h14l2 5"/>
            <path d="M5 13v8h14v-8"/>
            <path d="M9 21v-6h6v6"/>
            <path d="M3 9a3 3 0 0 0 6 0 3 3 0 0 0 6 0 3 3 0 0 0 6 0"/>
            @break
        @case('map')
            <path d="m3 6 6-3 6 3 6-3v15l-6 3-6-3-6 3V6z"/>
            <path d="M9 3v15"/>
            <path d="M15 6v15"/>
            @break
        @case('photo')
            <rect x="3" y="4" width="18" height="16" rx="2"/>
            <circle cx="8.5" cy="9" r="1.5"/>
            <path d="m21 15-5-5L5 20"/>
            @break
        @case('layout')
            <rect x="3" y="4" width="18" height="16" rx="2"/>
            <path d="M3 10h18"/>
            <path d="M9 10v10"/>
            @break
        @case('ticket')
            <path d="M4 5h16v5a2 2 0 0 0 0 4v5H4v-5a2 2 0 0 0 0-4V5z"/>
            <path d="M13 5v14"/>
            @break
        @case('ad')
            <path d="M4 13V8a2 2 0 0 1 2-2h3l8-3v16l-8-3H6a2 2 0 0 1-2-2z"/>
            <path d="M9 16v5H6l-1-7"/>
            <path d="M21 8v6"/>
            @break
        @case('credit-card')
            <rect x="3" y="5" width="18" height="14" rx="2"/>
            <path d="M3 10h18"/>
            <path d="M7 15h2"/>
            @break
        @case('gift')
            <rect x="3" y="8" width="18" height="13" rx="1"/>
            <path d="M12 8v13"/>
            <path d="M3 12h18"/>
            <path d="M12 8H7.5a2.5 2.5 0 1 1 2.5-2.5C10 7 12 8 12 8z"/>
            <path d="M12 8h4.5A2.5 2.5 0 1 0 14 5.5C14 7 12 8 12 8z"/>
            @break
        @case('chart')
            <path d="M4 19V9"/>
            <path d="M10 19V5"/>
            <path d="M16 19v-7"/>
            <path d="M22 19H2"/>
            @break
        @case('wallet')
            <path d="M4 6h14a2 2 0 0 1 2 2v10H4a2 2 0 0 1-2-2V6a3 3 0 0 1 3-3h13"/>
            <path d="M16 12h4"/>
            @break
        @case('cash')
            <rect x="3" y="6" width="18" height="12" rx="2"/>
            <circle cx="12" cy="12" r="2"/>
            <path d="M7 9H6v1"/>
            <path d="M17 15h1v-1"/>
            @break
        @case('prescription')
            <path d="M6 3h8a4 4 0 0 1 0 8H6z"/>
            <path d="M6 3v18"/>
            <path d="m11 11 7 10"/>
            <path d="m18 11-7 10"/>
            @break
        @case('support')
            <circle cx="12" cy="12" r="9"/>
            <circle cx="12" cy="12" r="3"/>
            <path d="m5.6 5.6 4.3 4.3"/>
            <path d="m14.1 14.1 4.3 4.3"/>
            <path d="m18.4 5.6-4.3 4.3"/>
            <path d="m9.9 14.1-4.3 4.3"/>
            @break
        @case('star')
            <path d="m12 3 2.8 5.7 6.2.9-4.5 4.4 1.1 6.2L12 17.3l-5.6 2.9 1.1-6.2L3 9.6l6.2-.9L12 3z"/>
            @break
        @case('bell')
            <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/>
            <path d="M10 21h4"/>
            @break
        @case('faq')
            <circle cx="12" cy="12" r="9"/>
            <path d="M9.7 9a2.5 2.5 0 1 1 3.8 2.1c-1 .6-1.5 1-1.5 2.4"/>
            <path d="M12 17h.01"/>
            @break
        @case('shield')
            <path d="M12 3 5 6v5c0 5 3 8 7 10 4-2 7-5 7-10V6l-7-3z"/>
            <path d="m9 12 2 2 4-4"/>
            @break
        @case('settings')
            <circle cx="12" cy="12" r="3"/>
            <path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1-2.8 2.8-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.6v.2h-4V21a1.7 1.7 0 0 0-1-1.6 1.7 1.7 0 0 0-1.9.3l-.1.1L4.2 17l.1-.1a1.7 1.7 0 0 0 .3-1.9A1.7 1.7 0 0 0 3 14H3v-4h.1a1.7 1.7 0 0 0 1.6-1 1.7 1.7 0 0 0-.3-1.9L4.2 7 7 4.2l.1.1a1.7 1.7 0 0 0 1.9.3A1.7 1.7 0 0 0 10 3V3h4v.1a1.7 1.7 0 0 0 1 1.6 1.7 1.7 0 0 0 1.9-.3l.1-.1L19.8 7l-.1.1a1.7 1.7 0 0 0-.3 1.9A1.7 1.7 0 0 0 21 10h.1v4H21a1.7 1.7 0 0 0-1.6 1z"/>
            @break
        @case('upload')
            <path d="M12 16V4"/>
            <path d="m7 9 5-5 5 5"/>
            <path d="M5 20h14"/>
            @break
        @case('activity')
            <path d="M3 12h4l2-6 4 12 2-6h6"/>
            @break
        @case('server')
            <rect x="3" y="4" width="18" height="6" rx="2"/>
            <rect x="3" y="14" width="18" height="6" rx="2"/>
            <path d="M7 7h.01"/>
            <path d="M7 17h.01"/>
            @break
        @case('user')
            <circle cx="12" cy="8" r="4"/>
            <path d="M4 21a8 8 0 0 1 16 0"/>
            @break
        @case('logout')
            <path d="M10 17l5-5-5-5"/>
            <path d="M15 12H3"/>
            <path d="M15 4h4a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-4"/>
            @break
        @case('moon')
            <path d="M20 15.5A8.5 8.5 0 0 1 8.5 4 8.5 8.5 0 1 0 20 15.5z"/>
            @break
        @case('sun')
            <circle cx="12" cy="12" r="4"/>
            <path d="M12 2v2"/>
            <path d="M12 20v2"/>
            <path d="m4.9 4.9 1.4 1.4"/>
            <path d="m17.7 17.7 1.4 1.4"/>
            <path d="M2 12h2"/>
            <path d="M20 12h2"/>
            <path d="m4.9 19.1 1.4-1.4"/>
            <path d="m17.7 6.3 1.4-1.4"/>
            @break
        @case('menu')
            <path d="M4 6h16"/>
            <path d="M4 12h16"/>
            <path d="M4 18h16"/>
            @break
        @case('chevron-down')
            <path d="m6 9 6 6 6-6"/>
            @break
        @default
            <circle cx="12" cy="12" r="9"/>
    @endswitch
</svg>
