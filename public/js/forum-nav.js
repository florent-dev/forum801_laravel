// Keeps the left list scroll position across wire:navigate page swaps
(function () {
    let saved = null;

    function leftScroller() {
        return document.querySelector('#left-panel .scroll');
    }

    // Bring the selected thread into view only when it is hidden
    function revealSelected(smooth) {
        const scroller = leftScroller();
        const selected = scroller && scroller.querySelector('.ui-content.selected');
        if (!selected) return;
        const box = scroller.getBoundingClientRect();
        const item = selected.getBoundingClientRect();
        if (item.top < box.top || item.bottom > box.bottom) {
            window.scrollInPanel(selected, 'center', smooth);
        }
    }

    document.addEventListener('livewire:navigating', function () {
        const scroller = leftScroller();
        saved = scroller ? { section: scroller.dataset.section || null, top: scroller.scrollTop } : null;
    });

    document.addEventListener('livewire:navigated', function () {
        const scroller = leftScroller();
        // Restore only when the same list is still on screen
        if (scroller && saved && saved.section && saved.section === (scroller.dataset.section || null)) {
            scroller.scrollTop = saved.top;
        }
        saved = null;
        revealSelected(false);
        revealReported();
    });

    // When reviewing a report, bring the reported post into view
    function revealReported() {
        const target = document.querySelector('.thread-post.reported-post') || document.querySelector('.thread-post.targeted-post') || document.querySelector('.unread-divider');
        if (target) {
            window.scrollInPanel(target, 'start');
            return;
        }
        // Everything already read: go straight to the newest post and the reply box
        const thread = document.querySelector('#thread-scroll[data-all-read]');
        if (thread) thread.scrollTop = thread.scrollHeight;
    }
    window.addEventListener('load', revealReported);
})();

// @mention autocomplete for editors and nickname fields
(function () {
    let box = null, items = [], active = 0, field = null, start = 0, timer = null, lastQuery = '';

    function endpoint() {
        const meta = document.querySelector('meta[name="mention-endpoint"]');
        return meta && meta.content;
    }

    // Returns the @word being typed before the caret, or null
    function currentToken(el) {
        const caret = el.selectionStart;
        const before = el.value.slice(0, caret);
        if (el.matches('[data-mention-input]')) {
            return { query: before.replace(/^@/, ''), start: 0 };
        }
        if (el.matches('[data-mention-multi]')) {
            const last = before.match(/(^|[,;\s])@?([A-Za-z0-9_.\-]{1,30})$/);
            return last ? { query: last[2], start: caret - last[2].length, multi: true } : null;
        }
        const match = before.match(/(^|[\s(>])@([A-Za-z0-9_.\-]{1,30})$/);
        return match ? { query: match[2], start: caret - match[2].length - 1 } : null;
    }

    function close() {
        if (box) box.remove();
        box = null; items = []; field = null;
    }

    function render() {
        if (!box) {
            box = document.createElement('div');
            box.className = 'mention-box';
            document.body.appendChild(box);
        }
        const rect = field.getBoundingClientRect();
        box.style.left = (rect.left + window.scrollX) + 'px';
        box.style.top = (rect.bottom + window.scrollY + 2) + 'px';
        box.innerHTML = '';
        items.forEach(function (user, i) {
            const option = document.createElement('div');
            option.className = 'mention-item' + (i === active ? ' active' : '');
            option.textContent = '@' + user.name;
            option.style.color = user.color;
            option.addEventListener('mousedown', function (e) { e.preventDefault(); pick(i); });
            box.appendChild(option);
        });
    }

    function pick(i) {
        const user = items[i];
        if (!user || !field) return;
        const el = field;
        if (el.matches('[data-mention-input]')) {
            el.value = user.name;
        } else {
            const caret = el.selectionStart;
            const insert = el.matches('[data-mention-multi]') ? user.name + ', ' : '@' + user.name + ' ';
            el.value = el.value.slice(0, start) + insert + el.value.slice(caret);
            el.selectionStart = el.selectionEnd = start + insert.length;
        }
        el.dispatchEvent(new Event('input', { bubbles: true }));
        close();
        el.focus();
    }

    document.addEventListener('input', function (e) {
        const el = e.target;
        if (!endpoint() || !(el.matches('textarea') || el.matches('[data-mention-input]') || el.matches('[data-mention-multi]'))) return;
        const token = currentToken(el);
        if (!token || token.query.length < 1) { close(); return; }
        clearTimeout(timer);
        timer = setTimeout(function () {
            if (token.query === lastQuery && box) return;
            lastQuery = token.query;
            fetch(endpoint() + '?q=' + encodeURIComponent(token.query), { headers: { Accept: 'application/json' } })
                .then(function (r) { return r.ok ? r.json() : []; })
                .then(function (users) {
                    if (!users.length) { close(); return; }
                    field = el; start = token.start; items = users; active = 0;
                    render();
                })
                .catch(close);
        }, 150);
    });

    document.addEventListener('keydown', function (e) {
        if (!box || e.target !== field) return;
        if (e.key === 'ArrowDown') { e.preventDefault(); active = (active + 1) % items.length; render(); }
        else if (e.key === 'ArrowUp') { e.preventDefault(); active = (active - 1 + items.length) % items.length; render(); }
        else if (e.key === 'Enter' || e.key === 'Tab') { e.preventDefault(); e.stopPropagation(); pick(active); }
        else if (e.key === 'Escape') { close(); }
    }, true);

    document.addEventListener('focusout', function () { setTimeout(close, 150); });
    document.addEventListener('livewire:navigating', close);
})();
