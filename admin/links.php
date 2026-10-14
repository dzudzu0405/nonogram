<?php
// Admin: WarriorPlus delivery links. One link per WarriorPlus product, pasted into that product as its
// delivery URL. See lib/access.php for what a link does and does not prove.
require __DIR__ . '/../lib/bootstrap.php';
require __DIR__ . '/../lib/layout.php';
require __DIR__ . '/../lib/admin.php';

$me = require_admin();
$back = 'admin/links.php';

/** Reads the shared form fields; returns [row, error]. */
function link_input(): array
{
    $label = str_limit((string) ($_POST['label'] ?? ''), 120);
    $products = array_values(array_intersect(array_keys(PRODUCTS), (array) ($_POST['products'] ?? [])));
    $max = max(0, (int) ($_POST['max_uses'] ?? 0));
    $exp = trim((string) ($_POST['expires_at'] ?? ''));
    if ($label === '') return [null, 'Give the link a name, ideally the WarriorPlus product name.'];
    if (!$products) return [null, 'Tick at least one product for the link to unlock.'];
    if ($exp !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $exp)) return [null, 'The end date must be a date.'];
    return [[
        'label' => $label, 'products' => implode(',', $products), 'max_uses' => $max,
        'expires_at' => $exp !== '' ? $exp . ' 23:59:59' : null, 'note' => str_limit((string) ($_POST['note'] ?? ''), 255) ?: null,
    ], null];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf($back);
    $action = (string) ($_POST['action'] ?? '');
    $link = isset($_POST['id']) ? q1('SELECT * FROM access_links WHERE id = ?', [(int) $_POST['id']]) : null;
    if ($action !== 'create' && !$link) { flash('error', 'That link no longer exists.'); redirect($back); }
    switch ($action) {
        case 'create':
            [$row, $err] = link_input();
            if ($err) { flash('error', $err); break; }
            insert('access_links', $row + ['code' => access_new_code(), 'uses' => 0, 'is_active' => 1, 'created_by' => (int) $me['id'], 'created_at' => now(), 'updated_at' => now()]);
            flash('success', 'Link created. Copy it into the WarriorPlus product as the delivery URL.');
            break;
        case 'update':
            [$row, $err] = link_input();
            if ($err) { flash('error', $err); break; }
            $sets = implode(', ', array_map(fn($k) => "$k = ?", array_keys($row)));
            q("UPDATE access_links SET $sets, updated_at = ? WHERE id = ?", array_merge(array_values($row), [now(), (int) $link['id']]));
            flash('success', 'Link saved.');
            break;
        case 'toggle':
            q('UPDATE access_links SET is_active = ?, updated_at = ? WHERE id = ?', [(int) $link['is_active'] ? 0 : 1, now(), (int) $link['id']]);
            flash('success', (int) $link['is_active'] ? 'Link switched off. Accounts that used it keep their products.' : 'Link switched on.');
            break;
        case 'rotate':
            q('UPDATE access_links SET code = ?, updated_at = ? WHERE id = ?', [access_new_code(), now(), (int) $link['id']]);
            flash('success', 'New address made. The old one stopped working; update the delivery URL in WarriorPlus.');
            break;
        case 'delete':
            if ((int) $link['uses'] > 0) { flash('error', 'This link has been used, so it is kept for the record. Switch it off instead.'); break; }
            q('DELETE FROM access_links WHERE id = ?', [(int) $link['id']]);
            flash('success', 'Link deleted.');
            break;
    }
    redirect($back);
}

$links = q('SELECT * FROM access_links ORDER BY is_active DESC, id DESC')->fetchAll();
$redeemed = access_redemptions();

function link_fields(?array $l = null): void
{
    $prods = $l ? explode(',', (string) $l['products']) : [];
    $exp = $l && $l['expires_at'] ? substr($l['expires_at'], 0, 10) : '';
    ?>
    <div class="row2">
      <label>Name<input name="label" maxlength="120" required value="<?= e($l['label'] ?? '') ?>" placeholder="e.g. Nonogram Studio FE (W+ product)"></label>
      <label>Note <span class="muted small">(only you see this)</span><input name="note" maxlength="255" value="<?= e($l['note'] ?? '') ?>" placeholder="Optional"></label>
    </div>
    <fieldset class="prodpick"><legend>Unlocks</legend>
      <?php foreach (PRODUCTS as $c => $p): ?><label class="check"><input type="checkbox" name="products[]" value="<?= e($c) ?>" <?= in_array($c, $prods, true) ? 'checked' : '' ?>><?= e($p['name']) ?> <span class="muted small"><?= e($p['step']) ?></span></label><?php endforeach; ?>
    </fieldset>
    <div class="row2">
      <label>Use limit <span class="muted small">(0 = no limit)</span><input name="max_uses" type="number" min="0" value="<?= (int) ($l['max_uses'] ?? 0) ?>"></label>
      <label>Stops working after <span class="muted small">(empty = never)</span><input name="expires_at" type="date" value="<?= e($exp) ?>"></label>
    </div>
    <?php
}

admin_begin('Access links', 'Access links');
?>
  <section class="card explain">
    <p><b>How it works:</b> make one link per WarriorPlus product and paste it into that product as the <b>delivery URL</b>. A buyer who opens it is asked to log in or create an account, and the products land on the account straight away. Opening a link twice changes nothing.</p>
    <p class="muted small">There is no check back with WarriorPlus, so treat links like a key: a link posted in public would unlock the products for anyone. If that happens, make a new address: the old one stops working and earlier buyers keep their products.</p>
  </section>

  <section class="card">
    <div class="cardhead"><h2>Links</h2><span class="countpill"><?= count($links) ?></span></div>
    <?php if (!$links): ?><p class="muted">No links yet. Create the first one below.</p><?php endif; ?>
    <div class="linklist">
      <?php foreach ($links as $l): $url = access_url($l); $problem = access_problem($l); ?>
        <article class="linkrow <?= (int) $l['is_active'] ? '' : 'off' ?>">
          <div class="linkmain">
            <div class="linktitle"><b><?= e($l['label']) ?></b>
              <?= $problem ? '<span class="chip warn">' . e((int) $l['is_active'] ? 'Not usable' : 'Off') . '</span>' : '<span class="chip ok">Live</span>' ?></div>
            <div class="linkprods"><?php foreach (access_products($l) as $p): ?><span class="chip prod"><?= e(PRODUCTS[$p]['name']) ?></span> <?php endforeach; ?></div>
            <div class="urlrow"><code class="url"><?= e($url) ?></code><button type="button" class="btn sm" data-copy="<?= e($url) ?>"><?= icon('copy', 14) ?>Copy</button></div>
            <div class="muted small">
              <a href="<?= e(url('admin/activity.php?link=' . (int) $l['id'])) ?>"><?= (int) ($redeemed[(int) $l['id']] ?? 0) ?> account<?= ($redeemed[(int) $l['id']] ?? 0) === 1 ? '' : 's' ?> unlocked</a>
              · used <?= (int) $l['uses'] ?><?= (int) $l['max_uses'] ? ' of ' . (int) $l['max_uses'] : '' ?> times
              <?= $l['expires_at'] ? ' · until ' . e(substr($l['expires_at'], 0, 10)) : '' ?>
              <?= $l['note'] ? ' · ' . e($l['note']) : '' ?>
            </div>
          </div>
          <div class="linkactions">
            <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int) $l['id'] ?>"><button class="btn sm"><?= (int) $l['is_active'] ? 'Switch off' : 'Switch on' ?></button></form>
            <form method="post" data-confirm="Make a new address? The current link stops working at once, so update the delivery URL in WarriorPlus."><?= csrf_field() ?><input type="hidden" name="action" value="rotate"><input type="hidden" name="id" value="<?= (int) $l['id'] ?>"><button class="btn sm">New address</button></form>
            <?php if (!(int) $l['uses']): ?><form method="post" data-confirm="Delete this link?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $l['id'] ?>"><button class="btn sm danger">Delete</button></form><?php endif; ?>
          </div>
          <details class="linkedit"><summary>Edit</summary>
            <form method="post" class="form"><?= csrf_field() ?><input type="hidden" name="action" value="update"><input type="hidden" name="id" value="<?= (int) $l['id'] ?>">
              <?php link_fields($l); ?><div><button class="btn primary">Save link</button></div></form>
          </details>
        </article>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="card">
    <div class="cardhead"><h2>New link</h2></div>
    <form method="post" class="form"><?= csrf_field() ?><input type="hidden" name="action" value="create">
      <?php link_fields(); ?><div><button class="btn primary"><?= icon('plus', 16) ?>Create link</button></div></form>
  </section>
<?php admin_end(); ?>
