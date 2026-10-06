/**
 * Alpine.js Store Definitions
 * Shared between app and guest layouts to avoid duplication.
 */

document.addEventListener('alpine:init', () => {
    if (!Alpine.store('layout')) {
        Alpine.store('layout', {
            mode: localStorage.getItem('layoutMode') || 'sidebar',
            toggleMode() {
                this.mode = this.mode === 'sidebar' ? 'navbar' : 'sidebar';
                localStorage.setItem('layoutMode', this.mode);
            },
            isSidebar() { return this.mode === 'sidebar'; },
            isNavbar() { return this.mode === 'navbar'; },
        });
    }

    if (!Alpine.store('sidebar')) {
        Alpine.store('sidebar', {
            open: false,
            collapsed: localStorage.getItem('sidebarCollapsed') === 'true',
            toggle() { this.open = !this.open; },
            close() { this.open = false; },
            toggleCollapse() {
                this.collapsed = !this.collapsed;
                localStorage.setItem('sidebarCollapsed', this.collapsed);
            },
        });
    }

    if (!Alpine.store('notification')) {
        Alpine.store('notification', {
            items: [],
            nextId: 0,
            maxVisible: 5,
            duration: 5000,

            add(type, message, title) {
                const id = this.nextId++;
                const defaultTitle = type === 'success' ? 'Berhasil'
                    : type === 'error' ? 'Error'
                    : type === 'warning' ? 'Peringatan'
                    : 'Informasi';

                this.items.push({ id, type, message, title: title || defaultTitle, timeout: null, startTime: 0, remaining: this.duration, paused: false });

                if (this.items.length > this.maxVisible) {
                    const removed = this.items.shift();
                    if (removed.timeout) clearTimeout(removed.timeout);
                }

                this._startTimer(id);
            },

            _startTimer(id) {
                const item = this.items.find(i => i.id === id);
                if (!item) return;
                item.paused = false;
                item.startTime = Date.now();
                clearTimeout(item.timeout);
                item.timeout = setTimeout(() => this.remove(id), item.remaining);
            },

            pause(id) {
                const item = this.items.find(i => i.id === id);
                if (!item || item.paused) return;
                item.paused = true;
                item.remaining -= Date.now() - item.startTime;
                clearTimeout(item.timeout);
            },

            resume(id) {
                const item = this.items.find(i => i.id === id);
                if (!item || !item.paused) return;
                this._startTimer(id);
            },

            remove(id) {
                const item = this.items.find(i => i.id === id);
                if (item && item.timeout) clearTimeout(item.timeout);
                this.items = this.items.filter(i => i.id !== id);
            }
        });

        // Listen for Livewire notify events
        window.addEventListener('notify', (e) => {
            const notification = Array.isArray(e.detail) ? e.detail[0] : e.detail;
            Alpine.store('notification').add(notification.type || 'success', notification.message || '', notification.title);
        });
    }

    if (!Alpine.store('darkMode')) {
        const stored = localStorage.getItem('darkMode');
        Alpine.store('darkMode', {
            dark: stored === 'true' || (stored === null && window.matchMedia('(prefers-color-scheme: dark)').matches),
            toggle() {
                this.dark = !this.dark;
                localStorage.setItem('darkMode', this.dark);
            },
        });
    }

    /**
     * x-autogrow — textarea yang tingginya otomatis mengikuti isi konten.
     * Resize saat user mengetik (input), saat init, saat tab berganti
     * (report-tab-changed), dan setelah Livewire morph mengubah value
     * tanpa memicu event input (via hook morph.updated di bawah).
     */
    Alpine.directive('autogrow', (el) => {
        if (el.tagName !== 'TEXTAREA') return;

        const resize = () => {
            if (!el.offsetParent) return; // elemen tersembunyi (tab lain) punya scrollHeight 0
            el.style.height = 'auto';
            el.style.height = el.scrollHeight + 'px';
        };

        el._autogrowResize = resize;
        el.addEventListener('input', resize);
        window.addEventListener('report-tab-changed', () => requestAnimationFrame(resize));
        // double rAF agar layout sudah settle sebelum ukur scrollHeight
        requestAnimationFrame(() => requestAnimationFrame(resize));
        // Ukur ulang setelah webfont & stylesheet selesai dimuat — penting di
        // first load (cold cache) di mana rAF di atas bisa berjalan sebelum
        // font siap, sehingga scrollHeight terukur dengan font fallback.
        document.fonts?.ready.then(resize);
        if (document.readyState !== 'complete') {
            window.addEventListener('load', resize, { once: true });
        }
        // Textarea w-full: resize window mengubah lebar → wrap & scrollHeight
        // berubah. Throttle via rAF agar tidak reflow tiap pixel drag.
        let resizeRaf = null;
        window.addEventListener('resize', () => {
            if (resizeRaf) return;
            resizeRaf = requestAnimationFrame(() => {
                resizeRaf = null;
                resize();
            });
        });
    });
});

/**
 * Setelah setiap Livewire morph, hitung ulang tinggi textarea x-autogrow.
 * Morph mengubah value via DOM tanpa event 'input', jadi perlu trigger manual.
 */
document.addEventListener('livewire:init', () => {
    Livewire.hook('morph.updated', ({ el }) => {
        if (el.nodeType !== Node.ELEMENT_NODE) return;
        if (el.tagName === 'TEXTAREA' && el._autogrowResize) el._autogrowResize();
        el.querySelectorAll?.('textarea').forEach(t => t._autogrowResize?.());
    });

    /**
     * x-cloak hanya relevan sebelum Alpine init pertama kali. HTML hasil render
     * server selalu membawa x-cloak lagi; tanpa hook ini morph me-sync atribut
     * tersebut ke elemen yang sudah dikelola x-show → seluruh elemen ber-x-cloak
     * sesaat display:none → tinggi dokumen kolaps → browser me-reset scroll ke
     * atas. Strip x-cloak dari node target agar tidak pernah ditambahkan ulang.
     */
    Livewire.hook('morph.updating', ({ toEl }) => {
        toEl.removeAttribute?.('x-cloak');
    });
});

/**
 * Dark mode sync — keeps <html> class in sync with localStorage.
 */
function syncDarkMode() {
    const stored = localStorage.getItem('darkMode');
    const dark = stored === 'true' || (stored === null && window.matchMedia('(prefers-color-scheme: dark)').matches);
    document.documentElement.classList.toggle('dark', dark);
}

syncDarkMode();
document.addEventListener('livewire:navigated', syncDarkMode);
window.addEventListener('storage', function (e) {
    if (e.key === 'darkMode') syncDarkMode();
});

/**
 * Cleanup orphaned x-teleport elements on Livewire SPA navigation.
 * Prevents stuck tooltips/flyouts and layout glitches after wire:navigate.
 */
document.addEventListener('livewire:navigating', () => {
    document.querySelectorAll('body > [x-teleport-target]').forEach(el => el.remove());
    document.querySelectorAll('body > .fixed').forEach(el => {
        if (!el.closest('[wire\\:id]') && !el.closest('.min-h-screen') && !el.matches('[x-data]')) {
            el.remove();
        }
    });
});

/**
 * After Livewire SPA navigation completes, dispatch event to reset Alpine component states.
 */
document.addEventListener('livewire:navigated', () => {
    window.dispatchEvent(new Event('resize'));
});
