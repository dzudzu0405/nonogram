<?php
require __DIR__ . '/../lib/bootstrap.php';
require __DIR__ . '/../lib/layout.php';
require __DIR__ . '/../lib/puzzles.php';

$user = require_login();
$f = features($user);
if (!$f['app']) redirect('app/');
$project = q1('SELECT id, name FROM projects WHERE id = ? AND user_id = ?', [(int) ($_GET['id'] ?? 0), (int) $user['id']]);
if (!$project) redirect('app/');

page_head($project['name'], $user, 'projects', ['compact' => true, 'tagline' => 'Editing: ' . $project['name']]);
$sizes = [5, 7, 10, 12, 15, 20, 25, 30, 35, 40, 45, 50];
?>
<main class="editor" data-project="<?= (int) $project['id'] ?>">
  <div class="edbar">
    <a class="back" href="<?= e(url('app/projects.php')) ?>">← My Projects</a>
    <input class="projname" id="projname" value="<?= e($project['name']) ?>" maxlength="160" aria-label="Project name">
    <span class="muted small" id="projmeta"></span>
    <div class="edbar-actions">
      <label class="inline">Page
        <select id="trim">
          <option value="letter">US Letter 8.5 × 11 in</option>
          <option value="8x10">8 × 10 in</option>
          <option value="6x9">6 × 9 in</option>
          <option value="a4">A4</option>
          <option value="a5">A5</option>
        </select>
      </label>
      <button class="btn primary" id="export-project">Export project PDF</button>
    </div>
  </div>

  <div class="edgrid">
    <aside class="panel">
      <section class="step">
        <h2><b>1</b>Picture</h2>
        <p class="note" id="savednote" hidden>You are editing a saved puzzle. Click cells in the Solution tab, or pick a picture to start over.</p>
        <label class="f">Collection
          <select id="coll">
            <option value="starter">Starter pictures</option>
            <option value="nature" <?= $f['vault'] ? '' : 'disabled' ?>>Vault: Nature &amp; food<?= $f['vault'] ? '' : ' (Picture Vault)' ?></option>
            <option value="letters" <?= $f['vault'] ? '' : 'disabled' ?>>Vault: Letters A–Z<?= $f['vault'] ? '' : ' (Picture Vault)' ?></option>
            <option value="numbers" <?= $f['vault'] ? '' : 'disabled' ?>>Vault: Numbers 0–9<?= $f['vault'] ? '' : ' (Picture Vault)' ?></option>
          </select>
        </label>
        <div class="samples" id="samples"></div>
        <label class="drop" id="drop" tabindex="0">
          <input type="file" id="file" accept="image/png,image/jpeg,image/webp" multiple>
          <strong>Upload images</strong> or drop them here<br>
          <span class="note">PNG, JPG or WEBP up to 10 MB. Several at once are added straight to the project.</span>
        </label>
      </section>

      <section class="step">
        <h2><b>2</b>Grid</h2>
        <div class="row2">
          <label class="f">Width
            <select id="w"><?php foreach ($sizes as $s): ?><option value="<?= $s ?>" <?= $s > $f['max_grid'] ? 'disabled' : '' ?> <?= $s === 15 ? 'selected' : '' ?>><?= $s ?><?= $s > $f['max_grid'] ? ' (Pro)' : '' ?></option><?php endforeach; ?></select>
          </label>
          <label class="f">Height
            <select id="h"><option value="auto">Match picture</option><?php foreach ($sizes as $s): ?><option value="<?= $s ?>" <?= $s > $f['max_grid'] ? 'disabled' : '' ?>><?= $s ?><?= $s > $f['max_grid'] ? ' (Pro)' : '' ?></option><?php endforeach; ?></select>
          </label>
        </div>
        <label class="f">Colors
          <select id="colors">
            <option value="1">Black &amp; white</option>
            <?php for ($i = 2; $i <= 4; $i++): ?><option value="<?= $i ?>" <?= $f['color'] ? '' : 'disabled' ?>><?= $i ?> colors<?= $f['color'] ? '' : ' (Pro)' ?></option><?php endfor; ?>
          </select>
        </label>
        <label class="f"><span class="rangehead"><span>Background cut-off</span><span id="thrv">150</span></span><input type="range" id="thr" min="20" max="240" value="150"></label>
        <label class="f"><span class="rangehead"><span>Contrast</span><span id="ctv">0</span></span><input type="range" id="ct" min="-60" max="80" value="0"></label>
        <label class="f"><span class="rangehead"><span>Brightness</span><span id="brv">0</span></span><input type="range" id="br" min="-60" max="60" value="0"></label>
        <label class="check"><input type="checkbox" id="inv"> Invert (fill the light areas)</label>
        <label class="check"><input type="checkbox" id="clean" checked> Remove stray pixels</label>
        <button class="btn" id="fix">Auto-fix to one solution</button>
      </section>
    </aside>

    <section class="center">
      <div class="sheet">
        <div class="sheethead">
          <div class="tabs" role="tablist">
            <button class="tab" role="tab" id="t-puzzle" aria-selected="true">Puzzle</button>
            <button class="tab" role="tab" id="t-sol" aria-selected="false">Solution · click to edit</button>
          </div>
          <div class="chips" id="chips"></div>
        </div>
        <div class="stage" id="stage"><canvas id="cv"></canvas></div>
        <div class="sheetfoot">
          <span class="stats" id="stats"></span>
          <span class="legend" id="legend" hidden></span>
        </div>
      </div>
      <div class="savebar">
        <label class="f grow">Puzzle name<input id="pname" value="Heart" maxlength="160"></label>
        <button class="btn primary" id="save">Save to project</button>
        <button class="btn" id="saveas" hidden>Save as new</button>
        <button class="btn" id="png">PNG</button>
        <button class="btn" id="pdf1">Puzzle PDF</button>
      </div>
    </section>

    <aside class="strip">
      <div class="striphead"><h2>In this project</h2><span class="muted small" id="count"></span></div>
      <ol class="plist" id="plist"></ol>
      <button class="btn" id="newpuzzle">+ New puzzle</button>
    </aside>
  </div>
</main>
<?php page_foot([
    'https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js',
    'assets/js/engine.js', 'assets/js/render.js', 'assets/js/pictures.js', 'assets/js/api.js', 'assets/js/editor.js',
]); ?>
