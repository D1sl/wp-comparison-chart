/* global jQuery */
(function ($) {
  'use strict';

  var $rows, $addBtn, $hidden, $template;

  // ── Init ──────────────────────────────────────────────────────────────────

  $(function () {
    $rows     = $('#sdb-sc-rows');
    $addBtn   = $('#sdb-sc-add-row');
    $hidden   = $('#sdb_sc_schema_json');
    $template = $('#sdb-sc-row-template').html();

    if (!$rows.length) return;

    // Sortable drag-to-reorder
    $rows.sortable({
      handle:      '.sdb-sc-handle',
      placeholder: 'sdb-sc-row-placeholder',
      tolerance:   'pointer',
      update:      function () { syncToHidden(); },
    });

    // Delegate events
    $rows.on('click', '.sdb-sc-remove', function () {
      $(this).closest('.sdb-sc-row').remove();
      reindex();
      syncToHidden();
    });

    $rows.on('change', '.sdb-sc-type', function () {
      var $row = $(this).closest('.sdb-sc-row');
      updateTypeVisibility($row, $(this).val());
      syncToHidden();
    });

    $rows.on('input change', '.sdb-sc-label, .sdb-sc-variant, .sdb-sc-max, .sdb-sc-max-meter, .sdb-sc-icon, .sdb-sc-yes-label, .sdb-sc-no-label', function () {
      // When a label changes and its row has no key yet, generate one.
      var $row = $(this).closest('.sdb-sc-row');
      var $key = $row.find('.sdb-sc-key');
      if ($(this).hasClass('sdb-sc-label') && !$key.val()) {
        $key.val(makeUniqueKey($.trim($(this).val()), $row));
      }
      syncToHidden();
    });

    $addBtn.on('click', function () {
      var newIndex = $rows.children('.sdb-sc-row').length;
      var html     = $template.replace(/__INDEX__/g, newIndex);
      var $row     = $(html);
      $rows.append($row);
      updateTypeVisibility($row, $row.find('.sdb-sc-type').val());
      $row.find('.sdb-sc-label').focus(); // focus the label, key is auto
      syncToHidden();
    });

    // Trigger on page load to set correct visibility for existing rows
    $rows.find('.sdb-sc-row').each(function () {
      updateTypeVisibility($(this), $(this).find('.sdb-sc-type').val());
    });
  });

  // ── Show/hide type-specific options ───────────────────────────────────────

  function updateTypeVisibility($row, type) {
    $row.find('.sdb-sc-opt-text').toggle(type === 'text');
    $row.find('.sdb-sc-opt-rating').toggle(type === 'rating');
    $row.find('.sdb-sc-opt-meter').toggle(type === 'meter');
    $row.find('.sdb-sc-opt-bool').toggle(type === 'bool');
  }

  // ── Reindex rows after remove/sort ────────────────────────────────────────

  function reindex() {
    $rows.find('.sdb-sc-row').each(function (i) {
      $(this).attr('data-index', i);
    });
  }

  // ── Serialise rows → hidden JSON input ────────────────────────────────────

  function syncToHidden() {
    var schema = [];
    $rows.find('.sdb-sc-row').each(function () {
      var $r    = $(this);
      var type  = $r.find('.sdb-sc-type').val();
      var key   = $.trim($r.find('.sdb-sc-key').val());
      var label = $.trim($r.find('.sdb-sc-label').val());
      if (!key || !label) return; // skip incomplete rows

      var row = { key: key, label: label, type: type };

      if (type === 'text') {
        var variant = $r.find('.sdb-sc-variant').val();
        if (variant) row.variant = variant;
      }
      if (type === 'rating') {
        row.max  = parseInt($r.find('.sdb-sc-max').val(), 10) || 5;
        var icon = $r.find('.sdb-sc-icon').val();
        if (icon) row.icon = icon;
      }
      if (type === 'meter') {
        row.max = parseInt($r.find('.sdb-sc-max-meter').val(), 10) || 5;
      }
      if (type === 'bool') {
        var yesLabel = $.trim($r.find('.sdb-sc-yes-label').val());
        var noLabel  = $.trim($r.find('.sdb-sc-no-label').val());
        if (yesLabel) row.yes_label = yesLabel;
        if (noLabel)  row.no_label  = noLabel;
      }

      schema.push(row);
    });

    $hidden.val(JSON.stringify(schema));
  }

  // ── Key auto-generation ───────────────────────────────────────────────────
  // Keys are generated from the label and never shown to the editor. Existing
  // rows keep whatever key was already saved.

  function makeUniqueKey(label, $thisRow) {
    var base = slugify(label) || 'row';
    // Collect keys already in use by OTHER rows
    var used = {};
    $rows.find('.sdb-sc-row').each(function () {
      if ($thisRow && this === $thisRow.get(0)) return;
      var k = $(this).find('.sdb-sc-key').val();
      if (k) used[k] = true;
    });
    if (!used[base]) return base;
    var i = 2;
    while (used[base + '_' + i]) i++;
    return base + '_' + i;
  }

  function slugify(str) {
    return String(str)
      .toLowerCase()
      .replace(/\s+/g, '_')
      .replace(/[^a-z0-9_]/g, '')
      .replace(/__+/g, '_')
      .replace(/^_+|_+$/g, '');
  }

}(jQuery));
