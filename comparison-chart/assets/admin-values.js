/* global jQuery, SDB_SC_Schema */
(function ($) {
  'use strict';

  $(function () {

    // ── Range slider → live output label ──────────────────────────────────
    $(document).on('input', '.sdb-sc-meter-slider', function () {
      var $slider = $(this);
      var max     = parseInt($slider.attr('max'), 10) || 5;
      $slider.siblings('.sdb-sc-meter-output').text($slider.val() + ' / ' + max);
    });

    // ── Star/dot rating: highlight on hover, commit on click ──────────────
    $(document).on('mouseenter', '.sdb-sc-star-label', function () {
      var $label  = $(this);
      var $wrap   = $label.closest('.sdb-sc-rating-field');
      var $labels = $wrap.find('.sdb-sc-star-label:not(.sdb-sc-star-clear)');
      var idx     = $labels.index($label);
      $labels.each(function (i) {
        $(this).toggleClass('hover', i <= idx);
      });
    });

    $(document).on('mouseleave', '.sdb-sc-rating-field', function () {
      $(this).find('.sdb-sc-star-label').removeClass('hover');
    });

    $(document).on('change', '.sdb-sc-star-label input[type="radio"]', function () {
      var $wrap   = $(this).closest('.sdb-sc-rating-field');
      var val     = parseInt($(this).val(), 10);
      var $labels = $wrap.find('.sdb-sc-star-label:not(.sdb-sc-star-clear)');
      $labels.each(function (i) {
        $(this).toggleClass('active', i < val);
      });
    });

    // ── Auto-slugify key field on schema rows (delegated, shared) ─────────
    // (also handled in admin-schema.js — this is a no-op on values screens)

    // ── Conditional visibility ─────────────────────────────────────────────
    // The values metabox already shows only the rows defined on the parent
    // (rendered server-side from the current schema). This JS layer handles
    // a future enhancement: if the parent schema is edited in another tab and
    // the page is reloaded, the server render already reflects the new schema.
    // Nothing extra needed here — the PHP render is the single source of truth.

    // ── Schema mismatch notice ─────────────────────────────────────────────
    // If SDB_SC_Schema is available (localized on child pages), we can show
    // a non-blocking notice if any stored values have keys no longer in the schema.
    if (typeof SDB_SC_Schema !== 'undefined' && SDB_SC_Schema.schema) {
      var schemaKeys = SDB_SC_Schema.schema.map(function (r) { return r.key; });
      // Highlight any rendered row whose key is not in the current schema
      // (shouldn't normally happen — PHP only renders current schema keys)
      $('.sdb-sc-val-row').each(function () {
        var key = $(this).data('key');
        if (key && schemaKeys.indexOf(key) === -1) {
          $(this).css({ opacity: '0.4' }).append(
            '<td colspan="2"><em style="color:#c00;font-size:11px">This row key is no longer in the parent schema.</em></td>'
          );
        }
      });
    }
  });

}(jQuery));
