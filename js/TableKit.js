/**
 * TableKit.js — Excel-style sort & filter for any HTML table
 * Usage:
 *   TableKit.init('#my-table');
 *   TableKit.init(document.querySelector('#my-table'));
 *   TableKit.initAll();           // all tables on the page
 *   TableKit.initAll('.data-table'); // scoped selector
 *
 * Requirements:
 *   - Table must have a <thead> with <th> elements
 *   - Table must have a <tbody> with <tr> rows
 */

(function (global) {
  'use strict';

  /* ─── Styles (injected once) ─────────────────────────────────────── */
  const STYLE_ID = '__tablekit_styles__';

  function injectStyles() {
    if (document.getElementById(STYLE_ID)) return;
    const style = document.createElement('style');
    style.id = STYLE_ID;
    style.textContent = `
      /* Header button */
      .tk-th-btn {
        display: flex;
        align-items: center;
        gap: 5px;
        width: 100%;
        padding: 9px 12px;
        cursor: pointer;
        background: none;
        border: none;
        font: inherit;
        font-size: inherit;
        font-weight: inherit;
        color: inherit;
        text-align: left;
        white-space: nowrap;
        transition: background 0.12s;
      }
      .tk-th-btn:hover { background: rgba(128,128,128,0.1); }
      .tk-th-label { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; }
      .tk-th-indicators { display: flex; align-items: center; gap: 3px; flex-shrink: 0; }
      .tk-sort-indicator { font-size: 11px; opacity: 0; transition: opacity 0.15s; }
      .tk-sort-indicator.tk-visible { opacity: 1; }
      .tk-filter-dot {
        width: 6px; height: 6px; border-radius: 50%;
        background: #4f8ef7; opacity: 0; transition: opacity 0.15s;
      }
      .tk-filter-dot.tk-visible { opacity: 1; }
      .tk-chevron { opacity: 0.4; transition: opacity 0.15s, transform 0.15s; flex-shrink: 0; }
      .tk-th-btn:hover .tk-chevron { opacity: 0.7; }
      .tk-th-btn.tk-open .tk-chevron { opacity: 1; transform: rotate(180deg); }

      /* Dropdown */
      .tk-dropdown {
        position: fixed;
        z-index: 99999;
        min-width: 210px;
        max-width: 320px;
        background: #fff;
        border: 1px solid #d1d5db;
        border-radius: 10px;
        box-shadow: 0 8px 28px rgba(0,0,0,0.14);
        display: none;
        flex-direction: column;
        font-size: 13px;
        font-family: inherit;
        overflow: hidden;
      }
      .tk-dropdown.tk-open { display: flex; }

      /* Sort rows */
      .tk-dd-sort { padding: 6px 6px 4px; border-bottom: 1px solid #e5e7eb; }
      .tk-dd-sort-row {
        display: flex; align-items: center; gap: 8px;
        padding: 6px 8px; border-radius: 6px;
        cursor: pointer; transition: background 0.1s;
        color: #374151;
      }
      .tk-dd-sort-row:hover { background: #f3f4f6; }
      .tk-dd-sort-row.tk-selected { background: #eff6ff; color: #1d4ed8; }
      .tk-dd-sort-icon { width: 16px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; }

      /* Filter section */
      }
      .tk-dd-search { padding: 4px 8px 6px; border-bottom: 1px solid #e5e7eb; }
      .tk-dd-search input {
        width: 100%; box-sizing: border-box;
        padding: 5px 8px; border: 1px solid #d1d5db;
        border-radius: 5px; font-size: 12px; outline: none;
        background: #f9fafb; color: inherit;
      }
      .tk-dd-search input:focus { border-color: #4f8ef7; background: #fff; }
      .tk-dd-actions {
        display: flex; gap: 6px;
        padding: 4px 8px 6px; border-bottom: 1px solid #e5e7eb;
      }
      .tk-dd-actions button {
        flex: 1; font-size: 11px; padding: 3px 6px;
        border: 1px solid #d1d5db; border-radius: 5px;
        cursor: pointer; background: #f9fafb; color: inherit;
        transition: background 0.12s;
      }
      .tk-dd-actions button:hover { background: #e5e7eb; }
      .tk-dd-list { max-height: 200px; overflow-y: auto; padding: 4px 0; }
      .tk-dd-item {
        display: flex; align-items: center; gap: 8px;
        padding: 5px 12px; cursor: pointer; transition: background 0.1s;
      }
      .tk-dd-item:hover { background: #f3f4f6; }
      .tk-dd-item input[type="checkbox"] {
        width: 14px; height: 14px; flex-shrink: 0;
        accent-color: #4f8ef7; cursor: pointer; margin: 0;
      }
      .tk-dd-item label {
        flex: 1; cursor: pointer; overflow: hidden;
        text-overflow: ellipsis; white-space: nowrap; color: inherit;
      }
      .tk-dd-empty { padding: 10px 12px; color: #9ca3af; font-size: 12px; text-align: center; }
      .tk-dd-footer {
        display: flex; gap: 6px; padding: 8px;
        border-top: 1px solid #e5e7eb;
      }
      .tk-dd-footer button {
        flex: 1; font-size: 12px; padding: 5px 8px;
        border-radius: 6px; cursor: pointer; border: none;
        transition: background 0.12s;
      }
      .tk-btn-cancel { background: #f3f4f6; color: #374151; border: 1px solid #d1d5db !important; }
      .tk-btn-cancel:hover { background: #e5e7eb; }
      .tk-btn-apply { background: #4f8ef7; color: #fff; }
      .tk-btn-apply:hover { background: #3b7de8; }

      /* Dark mode */
      @media (prefers-color-scheme: dark) {
        .tk-dropdown { background: #1f2937; border-color: #374151; box-shadow: 0 8px 28px rgba(0,0,0,0.45); }
        .tk-dd-sort { border-color: #374151; }
        .tk-dd-sort-row { color: #f3f4f6; }
        .tk-dd-sort-row:hover { background: #374151; }
        .tk-dd-sort-row.tk-selected { background: #1e3a5f; color: #93c5fd; }
