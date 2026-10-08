{{--
    Auth Platform Illustration - Ship condition survey visual.

    Application context: marine survey / ship condition reporting
    (PT Biro Klasifikasi Indonesia). Visual language: blueprint
    drafting sheet with a vessel profile, draft marks, ultrasonic
    hull gauging echoes, and an inspection checklist clipboard.

    Uses currentColor so it inherits the white text color of the blue
    gradient branding panel. Inline SVG (no external asset dependency).
--}}
<svg class="w-full h-auto max-w-md mx-auto" viewBox="0 0 480 320" xmlns="http://www.w3.org/2000/svg" fill="none" stroke="currentColor" aria-hidden="true">
    <style>
        @keyframes auth-float { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-3px); } }
        @keyframes auth-radar { to { transform: rotate(360deg); } }
        @keyframes auth-flow { to { stroke-dashoffset: -24; } }
        @keyframes auth-scan { 0%, 100% { transform: translateX(0); } 50% { transform: translateX(-10px); } }
        @keyframes auth-wave { to { transform: translateX(-60px); } }
        @keyframes auth-draw { to { stroke-dashoffset: 0; } }

        .anim-float { animation: auth-float 5s ease-in-out infinite; }
        .anim-radar { transform-origin: 119px 96px; animation: auth-radar 4s linear infinite; }
        .anim-echo { animation: auth-flow 3s linear infinite; }
        .anim-echo-2 { animation-delay: -1s; }
        .anim-echo-3 { animation-delay: -2s; }
        .anim-flow-line { animation: auth-flow 2.5s linear infinite; }
        .anim-scan { animation: auth-scan 6s ease-in-out infinite; }
        .anim-wave-1 { animation: auth-wave 9s linear infinite; }
        .anim-wave-2 { animation: auth-wave 12s linear infinite; }
        .anim-wave-3 { animation: auth-wave 15s linear infinite; }
        .anim-draw { stroke-dasharray: 24; stroke-dashoffset: 24; animation: auth-draw 0.6s ease-out forwards; }
        .anim-draw-2 { animation-delay: 0.4s; }
        .anim-draw-seal { animation-delay: 0.8s; }

        @media (prefers-reduced-motion: reduce) {
            .anim-float, .anim-radar, .anim-echo, .anim-flow-line, .anim-scan,
            .anim-wave-1, .anim-wave-2, .anim-wave-3, .anim-draw { animation: none; }
        }
    </style>

    {{-- Blueprint sheet frame --}}
    <rect x="40" y="40" width="400" height="240" rx="8" stroke-width="1.5" opacity="0.4"/>

    {{-- Corner accents --}}
    <path d="M 40 68 L 40 48 L 60 48" stroke-width="2" opacity="0.6"/>
    <path d="M 440 68 L 440 48 L 420 48" stroke-width="2" opacity="0.6"/>
    <path d="M 40 252 L 40 272 L 60 272" stroke-width="2" opacity="0.6"/>
    <path d="M 440 252 L 440 272 L 420 272" stroke-width="2" opacity="0.6"/>

    {{-- Sheet title block --}}
    <rect x="56" y="52" width="112" height="20" rx="4" stroke-width="1.5" opacity="0.5"/>
    <rect x="62" y="58" width="8" height="8" rx="1" stroke-width="1.5" opacity="0.7"/>
    <rect x="76" y="58" width="60" height="4" rx="1" stroke-width="1" opacity="0.45"/>
    <rect x="76" y="65" width="40" height="3" rx="1" stroke-width="1" opacity="0.3"/>

    {{-- Compass rose (top right) --}}
    <g opacity="0.7">
        <circle cx="416" cy="64" r="11" stroke-width="1.5"/>
        <path d="M 416 55 L 416 73 M 407 64 L 425 64" stroke-width="1" opacity="0.6"/>
        <path d="M 416 57 L 419 64 L 416 71 L 413 64 Z" fill="currentColor" opacity="0.3"/>
    </g>

    {{-- LOA dimension line above vessel --}}
    <path d="M 80 84 L 282 84" stroke-width="1" opacity="0.4" stroke-dasharray="4 3"/>
    <path d="M 80 78 L 80 90 M 282 78 L 282 90" stroke-width="1.5" opacity="0.6"/>
    <path d="M 80 90 L 80 164 M 282 90 L 282 168" stroke-width="1" opacity="0.25" stroke-dasharray="3 3"/>

    {{-- Vessel profile (bow facing right) --}}
    <g class="anim-float">
        {{-- Hull outline + light fill --}}
        <path d="M 80 170 L 265 170 Q 285 176 272 200 Q 262 215 244 215 L 96 215 Q 84 204 80 170 Z" stroke-width="2" opacity="0.9"/>
        <path d="M 80 170 L 265 170 Q 285 176 272 200 Q 262 215 244 215 L 96 215 Q 84 204 80 170 Z" fill="currentColor" opacity="0.06"/>

        {{-- Boot topping / waterline stripe --}}
        <path d="M 83 197 L 254 197 L 252 203 L 84 203 Z" fill="currentColor" opacity="0.12"/>

        {{-- Portholes --}}
        <circle cx="115" cy="188" r="3" stroke-width="1.5" opacity="0.6"/>
        <circle cx="140" cy="188" r="3" stroke-width="1.5" opacity="0.6"/>
        <circle cx="165" cy="188" r="3" stroke-width="1.5" opacity="0.6"/>

        {{-- Stern draft marks --}}
        <path d="M 82 175 h6 M 85 185 h6 M 89 195 h6" stroke-width="1" opacity="0.6"/>
        {{-- Bow draft marks --}}
        <path d="M 266 178 h6 M 268 188 h6 M 269 198 h6" stroke-width="1" opacity="0.6"/>

        {{-- Cargo hold hatch covers --}}
        <rect x="152" y="156" width="50" height="14" rx="3" stroke-width="1.5" opacity="0.7"/>
        <rect x="152" y="156" width="50" height="14" rx="3" fill="currentColor" opacity="0.05"/>
        <path d="M 177 156 L 177 170" stroke-width="1" opacity="0.4"/>
        <rect x="208" y="156" width="44" height="14" rx="3" stroke-width="1.5" opacity="0.7"/>
        <rect x="208" y="156" width="44" height="14" rx="3" fill="currentColor" opacity="0.05"/>
        <path d="M 230 156 L 230 170" stroke-width="1" opacity="0.4"/>

        {{-- Superstructure (aft) --}}
        <rect x="92" y="140" width="54" height="30" rx="2" stroke-width="1.5" opacity="0.8"/>
        <rect x="92" y="140" width="54" height="30" rx="2" fill="currentColor" opacity="0.05"/>
        <rect x="98" y="122" width="42" height="18" rx="2" stroke-width="1.5" opacity="0.75"/>
        <rect x="98" y="122" width="42" height="18" rx="2" fill="currentColor" opacity="0.05"/>

        {{-- Bridge --}}
        <rect x="104" y="108" width="30" height="14" rx="2" stroke-width="1.5" opacity="0.8"/>
        <rect x="108" y="112" width="6" height="5" rx="1" stroke-width="1" opacity="0.6"/>
        <rect x="117" y="112" width="6" height="5" rx="1" stroke-width="1" opacity="0.6"/>
        <rect x="126" y="112" width="6" height="5" rx="1" stroke-width="1" opacity="0.6"/>

        {{-- Funnel --}}
        <rect x="102" y="96" width="14" height="26" rx="2" stroke-width="1.5" opacity="0.8"/>
        <rect x="102" y="96" width="14" height="6" fill="currentColor" opacity="0.25"/>

        {{-- Radar mast on bridge top --}}
        <path d="M 119 108 L 119 94" stroke-width="1.5" opacity="0.7"/>
        <g class="anim-radar">
            <path d="M 110 96 L 128 96" stroke-width="2" opacity="0.7"/>
            <path d="M 111 94 Q 119 87 127 94" stroke-width="1" opacity="0.4" stroke-dasharray="2 2"/>
        </g>

        {{-- Signal mast at forecastle --}}
        <path d="M 260 170 L 260 148" stroke-width="1.5" opacity="0.7"/>
        <path d="M 252 154 L 268 154" stroke-width="1.5" opacity="0.6"/>
        <path d="M 260 148 L 272 151.5 L 260 155 Z" fill="currentColor" opacity="0.35"/>

        {{-- Ultrasonic thickness gauging points on hull --}}
        <path d="M 128 206 l6 6 M 134 206 l-6 6 M 152 206 l6 6 M 158 206 l-6 6
                 M 194 206 l6 6 M 200 206 l-6 6 M 216 206 l6 6 M 222 206 l-6 6"
              stroke-width="1.5" opacity="0.55"/>

        {{-- Echo sounding arcs below keel --}}
        <path class="anim-echo" d="M 156 215 Q 170 231 184 215" stroke-width="1" opacity="0.5" stroke-dasharray="3 3"/>
        <path class="anim-echo anim-echo-2" d="M 148 215 Q 170 241 192 215" stroke-width="1" opacity="0.4" stroke-dasharray="3 3"/>
        <path class="anim-echo anim-echo-3" d="M 140 215 Q 170 250 200 215" stroke-width="1" opacity="0.3" stroke-dasharray="3 3"/>
    </g>

    {{-- Magnifying glass: hull inspection focus --}}
    <g class="anim-scan">
        <circle cx="258" cy="192" r="13" stroke-width="2" opacity="0.85"/>
        <circle cx="258" cy="192" r="13" fill="currentColor" opacity="0.06"/>
        <path d="M 267 202 L 279 214" stroke-width="3" stroke-linecap="round" opacity="0.85"/>
    </g>

    {{-- Inspection flow: checklist -> hull --}}
    <path class="anim-flow-line" d="M 330 178 Q 305 186 273 190" stroke-width="1" opacity="0.35" stroke-dasharray="4 4"/>

    {{-- Survey checklist clipboard --}}
    <g>
        <rect x="330" y="120" width="88" height="114" rx="8" stroke-width="1.5" opacity="0.6"/>
        <rect x="330" y="120" width="88" height="114" rx="8" fill="currentColor" opacity="0.05"/>
        {{-- Clip --}}
        <rect x="358" y="112" width="32" height="14" rx="4" stroke-width="1.5" opacity="0.75"/>
        <circle cx="374" cy="119" r="2" stroke-width="1.5" opacity="0.7"/>

        {{-- Document title lines --}}
        <rect x="342" y="134" width="40" height="5" rx="1" stroke-width="1" opacity="0.5"/>
        <rect x="342" y="142" width="28" height="4" rx="1" stroke-width="1" opacity="0.3"/>

        {{-- Checklist rows --}}
        <rect x="342" y="154" width="11" height="11" rx="2" stroke-width="1.5" opacity="0.75"/>
        <path class="anim-draw" d="M 344 160 L 346.5 162.5 L 351 156" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" opacity="0.9"/>
        <rect x="359" y="157" width="42" height="4" rx="1" stroke-width="1" opacity="0.4"/>
        <rect x="359" y="164" width="26" height="3" rx="1" stroke-width="1" opacity="0.3"/>

        <rect x="342" y="178" width="11" height="11" rx="2" stroke-width="1.5" opacity="0.75"/>
        <path class="anim-draw anim-draw-2" d="M 344 184 L 346.5 186.5 L 351 180" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" opacity="0.9"/>
        <rect x="359" y="181" width="42" height="4" rx="1" stroke-width="1" opacity="0.4"/>
        <rect x="359" y="188" width="30" height="3" rx="1" stroke-width="1" opacity="0.3"/>

        <rect x="342" y="202" width="11" height="11" rx="2" stroke-width="1.5" opacity="0.5"/>
        <rect x="359" y="205" width="36" height="4" rx="1" stroke-width="1" opacity="0.35"/>

        {{-- Approval seal --}}
        <circle cx="407" cy="218" r="13" stroke-width="1.5" opacity="0.85"/>
        <circle cx="407" cy="218" r="9" stroke-width="1" opacity="0.35" stroke-dasharray="2 2"/>
        <path class="anim-draw anim-draw-seal" d="M 400 218.5 L 404.5 223 L 413 213" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" opacity="0.9"/>
    </g>

    {{-- Draft scale ruler (left edge) --}}
    <path d="M 60 190 L 60 250" stroke-width="1.5" opacity="0.5"/>
    <path d="M 60 190 h8 M 60 200 h5 M 60 210 h5 M 60 220 h5 M 60 230 h5 M 60 240 h5 M 60 250 h8" stroke-width="1" opacity="0.5"/>

    {{-- Water waves (extended beyond frame so drift animation loops seamlessly) --}}
    <path class="anim-wave-1" d="M 0 255 q 15 -7 30 0 t 30 0 t 30 0 t 30 0 t 30 0 t 30 0 t 30 0 t 30 0 t 30 0 t 30 0 t 30 0 t 30 0 t 30 0 t 30 0 t 30 0 t 30 0 t 30 0" stroke-width="1.5" opacity="0.35"/>
    <path class="anim-wave-2" d="M 0 262 q 15 -6 30 0 t 30 0 t 30 0 t 30 0 t 30 0 t 30 0 t 30 0 t 30 0 t 30 0 t 30 0 t 30 0 t 30 0 t 30 0 t 30 0 t 30 0 t 30 0 t 30 0" stroke-width="1.5" opacity="0.25"/>
    <path class="anim-wave-3" d="M 0 269 q 15 -5 30 0 t 30 0 t 30 0 t 30 0 t 30 0 t 30 0 t 30 0 t 30 0 t 30 0 t 30 0 t 30 0 t 30 0 t 30 0 t 30 0 t 30 0 t 30 0 t 30 0" stroke-width="1.5" opacity="0.15"/>
</svg>
