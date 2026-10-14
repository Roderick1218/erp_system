/* ERP System — small vanilla JS layer (no build step). Behaviours are opt-in via data-* attributes. */
(function () {
    'use strict';
    const $ = (s, r = document) => r.querySelector(s);
    const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));
    const html = document.documentElement;
    const store = {
        get: (k) => { try { return localStorage.getItem(k); } catch (e) { return null; } },
        set: (k, v) => { try { localStorage.setItem(k, v); } catch (e) { /* private mode */ } },
    };
    const t = (k) => (window.ERP_I18N && window.ERP_I18N[k]) || k;
    const csrf = () => $('meta[name="csrf-token"]')?.content;

    /* ---------- Sidebar ---------- */
    document.addEventListener('click', (e) => {
        if (e.target.closest('[data-sidebar-collapse]')) {
            html.classList.toggle('sb-collapsed');
            store.set('erp.sidebar', html.classList.contains('sb-collapsed') ? 'collapsed' : 'open');
        }
        if (e.target.closest('[data-sidebar-open]')) html.classList.add('sb-open');
        if (e.target.closest('.scrim')) html.classList.remove('sb-open');
    });
    // Collapsed sidebar: clicking a group icon expands the sidebar instead of toggling an invisible submenu.
    $$('.nav-group > summary').forEach((s) => s.addEventListener('click', (e) => {
        if (html.classList.contains('sb-collapsed') && window.innerWidth > 1024) {
            e.preventDefault();
            html.classList.remove('sb-collapsed');
            store.set('erp.sidebar', 'open');
            s.parentElement.open = true;
        }
    }));

    /* ---------- Theme ---------- */
    document.addEventListener('click', (e) => {
        if (!e.target.closest('[data-theme-toggle]')) return;
        const next = html.dataset.theme === 'dark' ? 'light' : 'dark';
        html.dataset.theme = next;
        store.set('erp.theme', next);
    });

    /* ---------- Dropdowns ---------- */
    document.addEventListener('click', (e) => {
        const trigger = e.target.closest('[data-dropdown]');
        $$('.dropdown.open').forEach((d) => { if (!trigger || d !== trigger.closest('.dropdown')) d.classList.remove('open'); });
        if (trigger) { e.preventDefault(); trigger.closest('.dropdown').classList.toggle('open'); }
    });

    /* ---------- Dialogs ---------- */
    document.addEventListener('click', (e) => {
        const opener = e.target.closest('[data-dialog]');
        if (opener) {
            e.preventDefault();
            const dlg = document.getElementById(opener.dataset.dialog);
            if (!dlg) return;
            // data-fill='{"field":"value"}' pre-fills inputs in the dialog (edit-in-modal pattern)
            if (opener.dataset.fill) {
                const data = JSON.parse(opener.dataset.fill);
                Object.entries(data).forEach(([k, v]) => {
                    const el = dlg.querySelector(`[name="${k}"]`);
                    if (!el) return;
                    if (el.type === 'checkbox') el.checked = !!v; else el.value = v ?? '';
                });
            }
            if (opener.dataset.action) dlg.querySelector('form')?.setAttribute('action', opener.dataset.action);
            if (opener.dataset.title && dlg.querySelector('.dlg-head h3')) dlg.querySelector('.dlg-head h3').textContent = opener.dataset.title;
            const m = dlg.querySelector('input[name="_method"]');
            if (m && opener.dataset.method) m.value = opener.dataset.method;
            dlg.showModal();
            dlg.querySelector('input:not([type=hidden]),select,textarea')?.focus();
        }
        if (e.target.closest('[data-close]')) e.target.closest('dialog')?.close();
    });
    $$('dialog').forEach((d) => d.addEventListener('click', (e) => { if (e.target === d) d.close(); }));
    // Re-open a dialog after a failed validation round-trip
    const reopen = $('[data-reopen-dialog]');
    if (reopen) document.getElementById(reopen.dataset.reopenDialog)?.showModal();

    /* ---------- Confirm + double-submit guard ---------- */
    document.addEventListener('submit', (e) => {
        const f = e.target;
        if (f.dataset.confirm && !window.confirm(f.dataset.confirm)) { e.preventDefault(); return; }
        if (f.dataset.submitted) { e.preventDefault(); return; }
        if (f.method.toLowerCase() === 'post') {
            f.dataset.submitted = '1';
            setTimeout(() => delete f.dataset.submitted, 4000);
        }
    });

    /* ---------- Clickable rows ---------- */
    document.addEventListener('click', (e) => {
        const tr = e.target.closest('tr[data-href]');
        if (!tr || e.target.closest('a,button,input,select,label,form')) return;
        if (e.ctrlKey || e.metaKey) window.open(tr.dataset.href, '_blank'); else window.location = tr.dataset.href;
    });

    /* ---------- Auto-submit filters ---------- */
    document.addEventListener('change', (e) => {
        if (e.target.matches('[data-autosubmit]')) e.target.form.requestSubmit();
    });

    /* ---------- Client-side tabs ---------- */
    $$('[data-tabs]').forEach((tabs) => {
        const scope = tabs.closest('[data-tab-scope]') || document;
        const activate = (name) => {
            $$('[data-tab]', tabs).forEach((b) => b.classList.toggle('active', b.dataset.tab === name));
            $$('[data-tab-panel]', scope).forEach((p) => p.classList.toggle('active', p.dataset.tabPanel === name));
        };
        tabs.addEventListener('click', (e) => {
            const b = e.target.closest('[data-tab]');
            if (!b) return;
            e.preventDefault();
            activate(b.dataset.tab);
            history.replaceState(null, '', '#' + b.dataset.tab);
        });
        const fromHash = location.hash.slice(1);
        if (fromHash && $(`[data-tab="${fromHash}"]`, tabs)) activate(fromHash);
    });

    /* ---------- Live clock ---------- */
    const clocks = $$('[data-clock]');
    if (clocks.length) {
        const loc = html.lang.startsWith('zh') ? 'zh-CN' : 'en-GB';
        const tick = () => {
            const now = new Date();
            clocks.forEach((c) => {
                c.textContent = c.dataset.clock === 'time'
                    ? now.toLocaleTimeString(loc, { hour12: false })
                    : now.toLocaleDateString(loc, { weekday: 'long', day: '2-digit', month: 'short', year: 'numeric' }) + ' · ' + now.toLocaleTimeString(loc, { hour12: false });
            });
        };
        tick();
        setInterval(tick, 1000);
    }

    /* ---------- Toasts ---------- */
    $$('.toast').forEach((t) => setTimeout(() => { t.style.transition = 'opacity .4s'; t.style.opacity = '0'; setTimeout(() => t.remove(), 400); }, 4500));

    /* ---------- Line items editor (quotation / invoice / PO) ---------- */
    $$('[data-lines]').forEach((wrap) => {
        const body = $('tbody', wrap);
        const tpl = $('template', wrap);
        const num = (v) => parseFloat(String(v).replace(/,/g, '')) || 0;
        const fmt = (n) => n.toLocaleString('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        let idx = body.children.length;
        const recalc = () => {
            let sub = 0;
            $$('tr', body).forEach((tr) => {
                const amt = num($('input[data-qty]', tr)?.value) * num($('input[data-price]', tr)?.value);
                sub += amt;
                const cell = $('[data-amount]', tr);
                if (cell) cell.textContent = fmt(amt);
            });
            const form = wrap.closest('form');
            const disc = num($('[name="discount"]', form)?.value);
            const rate = num($('[name="tax_rate"]', form)?.value);
            const taxable = Math.max(sub - disc, 0);
            const tax = Math.round(taxable * rate) / 100;
            const set = (sel, v) => { const el = $(sel, form); if (el) el.textContent = fmt(v); };
            set('[data-subtotal]', sub);
            set('[data-tax]', tax);
            set('[data-total]', taxable + tax);
        };
        const add = () => {
            const node = tpl.content.cloneNode(true);
            $$('[name]', node).forEach((el) => { el.name = el.name.replace('__i__', idx); });
            idx++;
            body.appendChild(node);
            recalc();
            $$('tr', body).pop()?.querySelector('select,input')?.focus();
        };
        wrap.addEventListener('click', (e) => {
            if (e.target.closest('[data-add-line]')) { e.preventDefault(); add(); }
            if (e.target.closest('[data-del-line]')) {
                e.preventDefault();
                if ($$('tr', body).length > 1) e.target.closest('tr').remove(); else $$('input', e.target.closest('tr')).forEach((i) => { i.value = i.dataset.qty !== undefined ? 1 : ''; });
                recalc();
            }
        });
        wrap.closest('form').addEventListener('input', recalc);
        // Picking a stock item fills description / uom / price from the <option data-*>
        wrap.addEventListener('change', (e) => {
            if (!e.target.matches('[data-item]')) return;
            const opt = e.target.selectedOptions[0];
            const tr = e.target.closest('tr');
            if (!opt || !opt.value) return;
            const desc = $('input[data-desc],textarea[data-desc]', tr); if (desc && !desc.value) desc.value = opt.dataset.name || opt.textContent.trim();
            const uom = $('input[data-uom],select[data-uom]', tr); const oUom = opt.dataset.itemUom || opt.dataset.uom; if (uom && oUom) uom.value = oUom;
            const price = $('input[data-price]', tr); if (price && opt.dataset.price && !num(price.value)) price.value = opt.dataset.price;
            recalc();
        });
        if (!body.children.length) add(); else recalc();
    });

    /* ---------- Kanban drag & drop (PATCHes the new column value) ---------- */
    $$('[data-kanban]').forEach((board) => {
        let dragged = null;
        board.addEventListener('dragstart', (e) => {
            const card = e.target.closest('.kcard[draggable]');
            if (!card) return;
            dragged = card; card.classList.add('dragging');
            e.dataTransfer.effectAllowed = 'move';
        });
        board.addEventListener('dragend', () => { dragged?.classList.remove('dragging'); $$('.drag-over', board).forEach((l) => l.classList.remove('drag-over')); });
        board.addEventListener('dragover', (e) => {
            const list = e.target.closest('.kanban-list');
            if (!list || !dragged) return;
            e.preventDefault();
            $$('.drag-over', board).forEach((l) => l !== list && l.classList.remove('drag-over'));
            list.classList.add('drag-over');
        });
        board.addEventListener('drop', async (e) => {
            const list = e.target.closest('.kanban-list');
            if (!list || !dragged) return;
            e.preventDefault();
            list.classList.remove('drag-over');
            const from = dragged.parentElement;
            if (from === list) return;
            list.prepend(dragged);
            const field = board.dataset.kanban;
            try {
                const res = await fetch(dragged.dataset.url, {
                    method: 'PATCH',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf() },
                    body: JSON.stringify({ [field]: list.dataset.value }),
                });
                if (!res.ok) throw new Error((await res.json().catch(() => ({}))).message || t('updateFailed'));
                toast(t('movedTo') + ' ' + list.dataset.label);
                const counts = (l) => { const h = l.closest('.kanban-col').querySelector('[data-count]'); if (h) h.textContent = l.children.length; };
                counts(list); counts(from);
            } catch (err) {
                from.prepend(dragged);
                toast(err.message, true);
            }
        });
    });

    function toast(msg, error = false) {
        let box = $('.toasts');
        if (!box) { box = document.createElement('div'); box.className = 'toasts'; document.body.appendChild(box); }
        const t = document.createElement('div');
        t.className = 'toast' + (error ? ' error' : '');
        t.innerHTML = '<svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="' + (error ? 'M6 18L18 6M6 6l12 12' : 'M4.5 12.75l6 6 9-13.5') + '"/></svg><span></span>';
        t.querySelector('span').textContent = msg;
        box.appendChild(t);
        setTimeout(() => { t.style.transition = 'opacity .4s'; t.style.opacity = '0'; setTimeout(() => t.remove(), 400); }, 3500);
    }
    window.erpToast = toast;

    /* ---------- Command palette (Ctrl/Cmd + K) ---------- */
    const palette = $('#palette');
    if (palette) {
        const input = $('input', palette);
        const results = $('.palette-results', palette);
        const pages = JSON.parse($('#palette-pages')?.textContent || '[]');
        const searchUrl = palette.dataset.searchUrl;
        let sel = 0; let timer = null; let records = [];

        const render = () => {
            const q = input.value.trim().toLowerCase();
            const pageHits = pages.filter((p) => !q || (p.label + ' ' + p.group + ' ' + (p.kw || '')).toLowerCase().includes(q)).slice(0, q ? 8 : 12);
            const groups = [];
            if (pageHits.length) groups.push([t('goTo'),pageHits.map((p) => ({ label: p.label, hint: p.group, url: p.url }))]);
            const byType = {};
            records.forEach((r) => { (byType[r.type] = byType[r.type] || []).push({ label: r.label, hint: r.hint, url: r.url }); });
            Object.entries(byType).forEach(([type, list]) => groups.push([t(type), list]));
            results.innerHTML = '';
            let i = 0;
            if (!groups.length) { results.innerHTML = '<div class="empty"><p>' + t('noResults') + ' “' +input.value.replace(/</g, '&lt;') + '”</p></div>'; return; }
            groups.forEach(([title, items]) => {
                const g = document.createElement('div'); g.className = 'palette-group'; g.textContent = title; results.appendChild(g);
                items.forEach((it) => {
                    const a = document.createElement('a');
                    a.className = 'palette-item' + (i === sel ? ' sel' : '');
                    a.href = it.url; a.dataset.i = i++;
                    a.innerHTML = '<span></span><small></small>';
                    a.querySelector('span').textContent = it.label;
                    a.querySelector('small').textContent = it.hint || '';
                    results.appendChild(a);
                });
            });
        };
        const open = () => { input.value = ''; records = []; sel = 0; render(); palette.showModal(); input.focus(); };
        document.addEventListener('keydown', (e) => {
            if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') { e.preventDefault(); palette.open ? palette.close() : open(); }
            if (e.key === '/' && !e.target.closest('input,textarea,select,[contenteditable]') && !palette.open) { e.preventDefault(); open(); }
        });
        document.addEventListener('click', (e) => { if (e.target.closest('[data-palette]')) { e.preventDefault(); open(); } });
        input.addEventListener('input', () => {
            sel = 0; render();
            clearTimeout(timer);
            const q = input.value.trim();
            if (q.length < 2) { records = []; render(); return; }
            timer = setTimeout(async () => {
                try {
                    const res = await fetch(searchUrl + '?q=' + encodeURIComponent(q), { headers: { Accept: 'application/json' } });
                    records = res.ok ? await res.json() : [];
                } catch (err) { records = []; }
                render();
            }, 180);
        });
        input.addEventListener('keydown', (e) => {
            const items = $$('.palette-item', results);
            if (e.key === 'ArrowDown') { e.preventDefault(); sel = Math.min(sel + 1, items.length - 1); }
            else if (e.key === 'ArrowUp') { e.preventDefault(); sel = Math.max(sel - 1, 0); }
            else if (e.key === 'Enter') { e.preventDefault(); if (items[sel]) window.location = items[sel].href; return; }
            else return;
            items.forEach((it, i) => it.classList.toggle('sel', i === sel));
            items[sel]?.scrollIntoView({ block: 'nearest' });
        });
    }

    /* ---------- Keyboard: Ctrl+S submits the main form ---------- */
    document.addEventListener('keydown', (e) => {
        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') {
            const f = $('form[data-main-form]');
            if (f) { e.preventDefault(); f.requestSubmit(); }
        }
    });
})();
