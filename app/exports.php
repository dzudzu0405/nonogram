<?php
// My Exports: every PDF and PNG downloaded from the editor, newest first.
// Files are made in the browser and not kept on the server; "Download again" rebuilds a project PDF from the
// project's current puzzles (the editor opens with #export and starts the download).
require __DIR__ . '/../lib/bootstrap.php';
require __DIR__ . '/../lib/layout.php';

$user = require_login();
$f = features($user);
if (!$f['app']) redirect('app/');
$uid = (int) $user['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf('app/exports.php');
    if (($_POST['action'] ?? '') === 'clear') {
        q('DELETE FROM exports WHERE user_id = ?', [$uid]);
        flash('success', 'Export history cleared. Your projects are untouched.');
    }
    redirect('app/exports.php');
}

$pid = (int) ($_GET['project'] ?? 0);
$args = [$uid];
$where = 'e.user_id = ?';
if ($pid) { $where .= ' AND e.project_id = ?'; $args[] = $pid; }
$total = (int) qv("SELECT COUNT(*) FROM exports e WHERE $where", $args);
$per = 50;
$page = max(1, min(max(1, (int) ceil($total / $per)), (int) ($_GET['page'] ?? 1)));
$rows = q("SELECT e.*, p.name AS project_name, p.id AS live_project FROM exports e LEFT JOIN projects p ON p.id = e.project_id AND p.user_id = e.user_id
           WHERE $where ORDER BY e.id DESC LIMIT $per OFFSET " . (($page - 1) * $per), $args)->fetchAll();
$projects = q('SELECT DISTINCT p.id, p.name FROM exports e JOIN projects p ON p.id = e.project_id WHERE e.user_id = ? ORDER BY p.name', [$uid])->fetchAll();
$kinds = ['project_pdf' => ['Book PDF', 'book'], 'puzzle_pdf' => ['Puzzle PDF', 'printer'], 'png' => ['PNG image', 'picture']];
$pages = ['letter' => 'US Letter', '8x10' => '8 × 10 in', '6x9' => '6 × 9 in', 'a4' => 'A4', 'a5' => 'A5'];

page_head('My Exports', $user, 'exports');
?>
<main class="page">
  <section class="pagehead">
    <div>
      <h1>My Exports <span class="countpill"><?= $total ?></span></h1>
      <p class="muted">Every PDF and image you downloaded. Files are made in your browser and not stored, so "Download again" rebuilds the book from the project as it is now.</p>
    </div>
    <div class="hero-actions"><a class="btn lg" href="<?= e(url('app/projects.php')) ?>"><?= icon('folder') ?>Go to projects</a></div>
  </section>

  <?php if (!$total && !$pid): ?>
    <section class="emptystate">
      <h2>Nothing exported yet</h2>
      <p class="muted">Open a project and press "Export project PDF" (or "Puzzle PDF" / "PNG" for one puzzle). Every download is listed here.</p>
      <div class="row-actions"><a class="btn lg primary" href="<?= e(url('app/projects.php')) ?>">Open my projects</a></div>
    </section>
  <?php else: ?>
    <form class="card filters" method="get">
      <select name="project" aria-label="Project" onchange="this.form.submit()">
        <option value="">All projects</option>
        <?php foreach ($projects as $p): ?><option value="<?= (int) $p['id'] ?>" <?= $pid === (int) $p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option><?php endforeach; ?>
      </select>
      <noscript><button class="btn">Filter</button></noscript>
      <?php if ($pid): ?><a class="btn" href="<?= e(url('app/exports.php')) ?>">Show all</a><?php endif; ?>
    </form>

    <div class="card tablecard">
      <table class="table exports">
        <thead><tr><th>Export</th><th>Project</th><th>Page</th><th class="num">Puzzles</th><th class="num">Pages</th><th>When</th><th></th></tr></thead>
        <tbody>
          <?php if (!$rows): ?><tr><td colspan="7" class="muted center">No exports for this project.</td></tr><?php endif; ?>
          <?php foreach ($rows as $r): [$kindName, $kindIcon] = $kinds[$r['kind']] ?? [$r['kind'], 'printer']; ?>
            <tr>
              <td><div class="acct"><span class="howicon sm"><?= icon($kindIcon, 16) ?></span><div><b><?= e($r['name']) ?></b><span class="muted small"><?= e($kindName) ?></span></div></div></td>
              <td><?php if ($r['live_project']): ?><a href="<?= e(url('app/editor.php?id=' . (int) $r['live_project'])) ?>"><?= e($r['project_name']) ?></a><?php else: ?><span class="muted small">deleted project</span><?php endif; ?></td>
              <td class="muted small"><?= e($pages[$r['page_size']] ?? '—') ?></td>
              <td class="num"><?= (int) $r['puzzle_count'] ?: '—' ?></td>
              <td class="num"><?= (int) $r['page_count'] ?: '—' ?></td>
              <td class="muted small" title="<?= e($r['created_at']) ?> UTC"><?= e(time_ago($r['created_at'])) ?></td>
              <td class="right">
                <?php if ($r['live_project'] && $r['kind'] === 'project_pdf'): ?>
                  <a class="btn sm" href="<?= e(url('app/editor.php?id=' . (int) $r['live_project'] . ($r['page_size'] ? '&page=' . $r['page_size'] : '') . '#export')) ?>"><?= icon('printer', 14) ?>Download again</a>
                <?php elseif ($r['live_project']): ?>
                  <a class="btn sm" href="<?= e(url('app/editor.php?id=' . (int) $r['live_project'])) ?>">Open project</a>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php if ($total > $per): $q = $_GET; ?>
      <nav class="pager">
        <?php if ($page > 1): $q['page'] = $page - 1; ?><a class="btn sm" href="?<?= e(http_build_query($q)) ?>">← Newer</a><?php else: ?><span></span><?php endif; ?>
        <span class="muted small">Page <?= $page ?> of <?= (int) ceil($total / $per) ?></span>
        <?php if ($page * $per < $total): $q['page'] = $page + 1; ?><a class="btn sm" href="?<?= e(http_build_query($q)) ?>">Older →</a><?php else: ?><span></span><?php endif; ?>
      </nav>
    <?php endif; ?>
    <form method="post" class="clearform" data-confirm="Clear the whole export history? Your projects and puzzles are not touched."><?= csrf_field() ?><input type="hidden" name="action" value="clear"><button class="btn sm danger">Clear history</button></form>
  <?php endif; ?>
</main>
<?php page_foot(); ?>
