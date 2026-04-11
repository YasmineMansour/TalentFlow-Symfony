/* TalentFlow - Predictive Search */
(function() {
    'use strict';

    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('[data-predictive-search]').forEach(initSearch);
    });

    function initSearch(wrapper) {
        var input = wrapper.querySelector('.tf-search-input');
        var results = wrapper.querySelector('.tf-search-results');
        var url = wrapper.getAttribute('data-predictive-search');

        if (!input || !results || !url) return;

        var timer = null;
        var selectedIndex = -1;
        var items = [];

        input.setAttribute('role', 'combobox');
        input.setAttribute('aria-autocomplete', 'list');
        input.setAttribute('aria-expanded', 'false');
        input.setAttribute('aria-haspopup', 'listbox');
        results.setAttribute('role', 'listbox');

        input.addEventListener('input', function() {
            clearTimeout(timer);
            var q = input.value.trim();
            if (q.length < 2) {
                close();
                return;
            }
            timer = setTimeout(function() { fetchResults(q); }, 250);
        });

        input.addEventListener('keydown', function(e) {
            if (!results.classList.contains('active')) return;
            items = results.querySelectorAll('.tf-search-item');
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                selectedIndex = Math.min(selectedIndex + 1, items.length - 1);
                updateSelection();
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                selectedIndex = Math.max(selectedIndex - 1, 0);
                updateSelection();
            } else if (e.key === 'Enter' && selectedIndex >= 0 && items[selectedIndex]) {
                e.preventDefault();
                items[selectedIndex].click();
            } else if (e.key === 'Escape') {
                close();
            }
        });

        document.addEventListener('click', function(e) {
            if (!wrapper.contains(e.target)) close();
        });

        function fetchResults(query) {
            var separator = url.indexOf('?') >= 0 ? '&' : '?';
            fetch(url + separator + 'q=' + encodeURIComponent(query), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                renderResults(data);
            })
            .catch(function() { close(); });
        }

        function renderResults(data) {
            if (!data || !data.length) {
                results.innerHTML = '<div class="tf-search-item" style="justify-content:center;color:var(--tf-text-muted);"><span>Aucun résultat</span></div>';
                results.classList.add('active');
                input.setAttribute('aria-expanded', 'true');
                return;
            }

            var html = '';
            data.forEach(function(item, i) {
                html += '<a class="tf-search-item" href="' + escapeHtml(item.url) + '" role="option" id="search-item-' + i + '">'
                    + '<div class="item-icon"><i class="fas fa-' + (item.icon || 'briefcase') + '"></i></div>'
                    + '<div><div class="item-title">' + escapeHtml(item.title) + '</div>'
                    + '<div class="item-sub">' + escapeHtml(item.subtitle || '') + '</div></div>'
                    + '</a>';
            });
            results.innerHTML = html;
            results.classList.add('active');
            input.setAttribute('aria-expanded', 'true');
            selectedIndex = -1;
        }

        function updateSelection() {
            items.forEach(function(el, i) {
                el.setAttribute('aria-selected', i === selectedIndex ? 'true' : 'false');
            });
            if (items[selectedIndex]) {
                input.setAttribute('aria-activedescendant', items[selectedIndex].id);
                items[selectedIndex].scrollIntoView({ block: 'nearest' });
            }
        }

        function close() {
            results.classList.remove('active');
            results.innerHTML = '';
            input.setAttribute('aria-expanded', 'false');
            input.removeAttribute('aria-activedescendant');
            selectedIndex = -1;
        }

        function escapeHtml(str) {
            var div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }
    }
})();
