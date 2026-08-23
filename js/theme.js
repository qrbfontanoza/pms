(function () {
  'use strict';

  // Theme toggle click handling + persistence (System Enhancements
  // initiative, Step 8b). The *initial* theme (avoiding a flash of the
  // wrong theme) is applied by a tiny inline script in each page's <head>,
  // before this file ever loads — see the inline snippet duplicated across
  // all 13 pages. This file's job is narrower: keep every .js-theme-toggle
  // button's icon/aria-state in sync with whatever theme is active, and
  // handle clicks (write to localStorage, flip the attribute, update the
  // button). Decision D1: the OS preference only *seeds* the first visit —
  // once the user manually toggles, that choice is pinned in localStorage
  // and no longer follows OS changes. Decision D2: localStorage only, no
  // server round-trip, no per-account setting.

  var STORAGE_KEY = 'pms-theme';

  function currentTheme() {
    return document.documentElement.getAttribute('data-bs-theme') || 'light';
  }

  function applyTheme(theme) {
    document.documentElement.setAttribute('data-bs-theme', theme);
    $('.js-theme-toggle').each(function () {
      var $btn = $(this);
      var $icon = $btn.find('i');
      if (theme === 'dark') {
        $icon.removeClass('fa-moon').addClass('fa-sun');
        $btn.attr('aria-label', 'Switch to light mode');
        $btn.attr('aria-pressed', 'true');
      } else {
        $icon.removeClass('fa-sun').addClass('fa-moon');
        $btn.attr('aria-label', 'Switch to dark mode');
        $btn.attr('aria-pressed', 'false');
      }
    });
  }

  $(function () {
    // Bring the toggle button(s) into agreement with whatever the inline
    // <head> script already applied to <html> before first paint.
    applyTheme(currentTheme());

    $(document).on('click', '.js-theme-toggle', function () {
      var next = currentTheme() === 'dark' ? 'light' : 'dark';
      localStorage.setItem(STORAGE_KEY, next);
      applyTheme(next);
    });
  });
})();
