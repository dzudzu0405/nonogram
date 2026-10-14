<?php
// New / rename / delete project dialogs, shared by the Dashboard and My Projects (driven by assets/js/dashboard.js).
if (!defined('APP_ROOT')) exit;
?>
  <dialog class="dlg" id="dlg-name">
    <form method="dialog" class="form">
      <h2 id="dlg-name-title">New project</h2>
      <label>Project name<input id="dlg-name-input" maxlength="160" required placeholder="e.g. Animal Nonograms Vol. 1" autocomplete="off"></label>
      <div class="suggest" id="dlg-suggest">
        <span class="muted small">Ideas:</span>
        <button type="button" class="chip">Animal Nonograms Vol. 1</button>
        <button type="button" class="chip">Kids Worksheets</button>
        <button type="button" class="chip">Holiday Puzzle Book</button>
      </div>
      <div class="dlg-actions">
        <button type="button" class="btn" value="cancel" data-close>Cancel</button>
        <button class="btn primary" id="dlg-name-ok" value="ok">Create project</button>
      </div>
    </form>
  </dialog>

  <dialog class="dlg" id="dlg-confirm">
    <form method="dialog" class="form">
      <h2 id="dlg-confirm-title">Delete project?</h2>
      <p class="muted" id="dlg-confirm-text"></p>
      <div class="dlg-actions">
        <button type="button" class="btn" data-close>Cancel</button>
        <button class="btn danger-solid" value="ok">Delete</button>
      </div>
    </form>
  </dialog>
