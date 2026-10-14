<?php
// The address a WarriorPlus buyer lands on after paying: access.php?c=<code>
//   signed in  -> products added at once, then on to the dashboard
//   guest      -> the code is parked in the session; the page shows what they bought and asks them to
//                 log in or create an account, and the products land the moment they do
require __DIR__ . '/lib/bootstrap.php';
require __DIR__ . '/lib/layout.php';

$link = access_find((string) ($_GET['c'] ?? ''));
$problem = $link ? access_problem($link) : 'That access link was not recognised. Check it against the one in your WarriorPlus receipt: it is easy to lose a character when copying by hand.';

if ($problem === null) {
    access_park((string) $link['code']);
    if ($u = current_user()) {
        access_apply_parked($u);
        redirect('app/');
    }
} else {
    access_forget();
}

page_head($problem ? 'Access link' : 'Unlock your purchase');
?>
<main class="auth">
  <div class="authcard">
    <h1 class="brand"><span class="mark" aria-hidden="true"></span><?= e(config('app_name')) ?></h1>
    <?php if ($problem): ?>
      <p class="err-msg" role="alert"><?= e($problem) ?></p>
      <p class="muted small">Already have an account? <a href="<?= e(url('login.php')) ?>">Log in</a></p>
    <?php else: ?>
      <h2>Thanks for your purchase</h2>
      <p>This link unlocks:</p>
      <ul class="unlocks">
        <?php foreach (access_products($link) as $p): ?><li><?= icon('check', 16) ?><?= e(PRODUCTS[$p]['name']) ?></li><?php endforeach; ?>
      </ul>
      <p class="muted">Create your account, or log in if you already have one. Everything is added the moment you are in.</p>
      <div class="authactions">
        <a class="btn primary" href="<?= e(url('register.php')) ?>">Create my account</a>
        <a class="btn" href="<?= e(url('login.php')) ?>">I already have an account</a>
      </div>
    <?php endif; ?>
  </div>
</main>
<?php page_foot(); ?>
