{{-- Cari menu di sidebar (pola sama dengan project cattleya). Ctrl+K / "/" untuk fokus, Enter membuka hasil pertama. --}}
<div
    x-data="{
        q: '',
        filter() {
            const nav = this.$el.closest('.fi-sidebar-nav');
            if (! nav) return;
            const kata = this.q.trim().toLowerCase();
            let ada = 0;
            nav.querySelectorAll('.fi-sidebar-group').forEach((g) => {
                const label = (g.querySelector('.fi-sidebar-group-label')?.textContent || '').toLowerCase();
                let tampilGrup = 0;
                g.querySelectorAll('.fi-sidebar-item').forEach((it) => {
                    const teks = (it.querySelector('.fi-sidebar-item-label')?.textContent || it.textContent).toLowerCase();
                    const cocok = kata === '' || teks.includes(kata) || label.includes(kata);
                    it.style.display = cocok ? '' : 'none';
                    if (cocok) tampilGrup++;
                });
                g.style.display = tampilGrup ? '' : 'none';
                // Grup yang tertutup ikut dibuka selama mencari.
                const isi = g.querySelector('.fi-sidebar-group-items');
                if (isi && kata && tampilGrup) {
                    if (! ('asli' in isi.dataset)) isi.dataset.asli = isi.style.display;
                    isi.style.display = '';
                } else if (isi && 'asli' in isi.dataset) {
                    isi.style.display = isi.dataset.asli;
                    delete isi.dataset.asli;
                }
                ada += tampilGrup;
            });
            this.$refs.kosong.style.display = kata && ! ada ? '' : 'none';
        },
        buka() {
            const nav = this.$el.closest('.fi-sidebar-nav');
            const pertama = [...(nav?.querySelectorAll('.fi-sidebar-item a') || [])].find((a) => a.offsetParent !== null);
            if (pertama) pertama.click();
        },
    }"
    x-on:keydown.window="
        if ((($event.ctrlKey || $event.metaKey) && $event.key.toLowerCase() === 'k')
            || ($event.key === '/' && ! ['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement?.tagName) && ! document.activeElement?.isContentEditable)) {
            $event.preventDefault(); $refs.input.focus(); $refs.input.select();
        }"
    class="fi-menu-search"
    style="padding: 0 0 .75rem"
    x-show="$store.sidebar?.isOpen ?? true"
>
    <x-filament::input.wrapper prefix-icon="heroicon-m-magnifying-glass">
        <x-filament::input
            type="search"
            x-ref="input"
            x-model="q"
            x-on:input="filter()"
            x-on:keydown.enter.prevent="buka()"
            x-on:keydown.escape="q = ''; filter(); $el.blur()"
            placeholder="Cari menu... (Ctrl+K)"
            autocomplete="off"
            aria-label="Cari menu"
        />
    </x-filament::input.wrapper>
    <div x-ref="kosong" style="display:none;font-size:.8rem;opacity:.7;padding:.5rem .25rem 0">Tidak ada menu yang cocok.</div>
</div>
