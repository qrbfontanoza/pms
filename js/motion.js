(function () {
  'use strict';

  var reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /**
   * Sets up IntersectionObserver for [data-reveal] elements. Safe no-op
   * until a later phase adds data-reveal markup to real page content.
   */
  function initReveal() {
    if (!('IntersectionObserver' in window)) return;

    var staggerMs = parseInt(
      getComputedStyle(document.documentElement).getPropertyValue('--motion-stagger'),
      10
    ) || 80;

    document.querySelectorAll('[data-reveal-stagger]').forEach(function (container) {
      // data-reveal-stagger="N" overrides the global --motion-stagger token for this
      // container only. Bare data-reveal-stagger (no value) yields '', which parseInt
      // rejects (NaN) — falls back to staggerMs, preserving existing bare usage.
      var containerStaggerAttr = container.getAttribute('data-reveal-stagger');
      var containerStaggerMs = parseInt(containerStaggerAttr, 10);
      var effectiveStaggerMs = isNaN(containerStaggerMs) ? staggerMs : containerStaggerMs;

      container.querySelectorAll('[data-reveal]').forEach(function (child, index) {
        // Cap at index 5 (the 6th item, 0-indexed) per MOTION_DESIGN_ANALYSIS.md §9.1 —
        // items 7+ reveal simultaneously with #6 instead of an ever-increasing delay.
        var cappedIndex = Math.min(index, 5);
        child.style.setProperty('--reveal-delay', (cappedIndex * effectiveStaggerMs) + 'ms');
      });
    });

    var observer = new IntersectionObserver(function (entries, obs) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          entry.target.classList.add('is-visible');
          obs.unobserve(entry.target);
        }
      });
    }, { threshold: 0.15 });

    document.querySelectorAll('[data-reveal]').forEach(function (el) {
      observer.observe(el);
    });
  }

  /**
   * Toggles a Bootstrap spinner + disabled/aria-busy state on a button.
   * Not wired to any button in this phase.
   */
  function setButtonLoading($btn, isLoading) {
    if (!$btn || !$btn.length) return;

    if (isLoading) {
      if ($btn.find('.js-motion-spinner').length) return;
      var $spinner = $(
        '<span class="spinner-border spinner-border-sm me-2 js-motion-spinner" role="status" aria-hidden="true"></span>'
      );
      $btn.prepend($spinner);
      $btn.prop('disabled', true).addClass('is-loading').attr('aria-busy', 'true');
    } else {
      $btn.find('.js-motion-spinner').remove();
      $btn.prop('disabled', false).removeClass('is-loading').removeAttr('aria-busy');
    }
  }

  /**
   * Wires a delegated submit handler onto forms matching `selector`: shows
   * a spinner on the form's submit button and leaves it spinning (GET/POST
   * forms navigate away, so there's no success/error branch to reset it).
   */
  function initFormSubmitSpinner(selector) {
    $(document).on('submit', selector, function () {
      var $btn = $(this).find('button[type="submit"]').first();
      setButtonLoading($btn, true);
    });
  }

  /**
   * Delegated click handler on .page-link: spins the specific link that was
   * clicked until the browser navigates away. Ignores the non-anchor
   * ellipsis spans and disabled links (Bootstrap's .disabled CSS already
   * blocks the click via pointer-events, so this is a defensive check).
   */
  function initPaginationSpinner() {
    $(document).on('click', '.page-link', function () {
      var $link = $(this);
      if (!$link.is('a') || !$link.attr('href')) return;
      setButtonLoading($link, true);
    });
  }

  /**
   * Focuses the first input/select/textarea in a modal on open, or the
   * Close button when there's nothing to focus. Applies to existing
   * modals' focus behavior only — no changes to modal content/transitions.
   */
  function initModalFocus() {
    $(document).on('shown.bs.modal', '.modal', function () {
      var $modal = $(this);
      var $first = $modal.find('input:not([type="hidden"]), select, textarea').filter(':visible').first();
      if ($first.length) {
        $first.trigger('focus');
      } else {
        $modal.find('.btn-close').trigger('focus');
      }
    });
  }

  function prefersReducedMotion() {
    return reducedMotion;
  }

  /**
   * Animates each [.js-count] span's textContent from 0 to its data-target
   * over 1000ms when it first enters the viewport. The visible span stays
   * aria-hidden throughout; a visually-hidden sibling (static, correct from
   * first paint) is what screen readers announce, so no mid-animation
   * number is ever exposed to assistive tech.
   */
  function initCounters() {
    var counters = document.querySelectorAll('.js-count');
    if (!counters.length) return;

    if (reducedMotion || !('IntersectionObserver' in window)) {
      counters.forEach(function (el) {
        el.textContent = el.getAttribute('data-target');
      });
      return;
    }

    var duration = 1000;

    var observer = new IntersectionObserver(function (entries, obs) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting) return;

        var el = entry.target;
        var target = parseInt(el.getAttribute('data-target'), 10) || 0;
        var startTime = null;

        function step(timestamp) {
          if (startTime === null) startTime = timestamp;
          var progress = Math.min((timestamp - startTime) / duration, 1);
          el.textContent = Math.floor(progress * target);
          if (progress < 1) {
            requestAnimationFrame(step);
          } else {
            el.textContent = target;
          }
        }

        requestAnimationFrame(step);
        obs.unobserve(el);
      });
    }, { threshold: 0.5 });

    counters.forEach(function (el) {
      observer.observe(el);
    });
  }

  $(function () {
    initReveal();
    initModalFocus();
    initCounters();
    initFormSubmitSpinner('#filterForm');
    initPaginationSpinner();
  });

  window.PMSMotion = {
    setButtonLoading: setButtonLoading,
    prefersReducedMotion: prefersReducedMotion
  };
})();
