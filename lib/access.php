<?php
// WarriorPlus delivery links. Each WarriorPlus product gets one link (access.php?c=<code>) pasted in as its
// delivery URL. Opening it signed in adds the link's products to the account; opening it as a guest parks the
// code in the session until they sign in or create an account, so nobody has to click the link twice.
//
// What a link proves: only that whoever opened it was given it (there is no call back to WarriorPlus). That keeps
// setup to one field per product. A leaked link is contained by: a 24-character random code, an optional use limit
// and expiry date, rotating the code (old link dies, redeemed accounts keep their products), and the log of every
// redemption with its IP. Adding products is never harmful to re-run: re-opening an old link changes nothing.
// Same design as GameCraft Studio's access links, but Nonogram products add up instead of being one plan.

const ACCESS_PENDING = 'access_code';

function access_new_code(): string
{
    return bin2hex(random_bytes(12));
}

function access_find(string $code): ?array
{
    $code = strtolower(trim($code));
    if (!preg_match('/^[a-f0-9]{8,64}$/', $code)) return null;
    return q1('SELECT * FROM access_links WHERE code = ?', [$code]);
}

function access_products(array $link): array
{
    return array_values(array_filter(explode(',', (string) $link['products']), fn($p) => isset(PRODUCTS[$p])));
}

function access_url(array $link): string
{
    return url_full('access.php?c=' . $link['code']);
}

/** Why the link cannot be used (shown to the buyer), or null when it can. */
function access_problem(array $link): ?string
{
    $contact = ' Please contact support with your WarriorPlus receipt.';
    if ((int) $link['is_active'] !== 1) return 'This access link has been switched off.' . $contact;
    if (!empty($link['expires_at']) && strtotime($link['expires_at'] . ' UTC') < time()) return 'This access link has expired.' . $contact;
    if ((int) $link['max_uses'] > 0 && (int) $link['uses'] >= (int) $link['max_uses']) return 'This access link has been used the maximum number of times.' . $contact;
    if (!access_products($link)) return 'This access link does not unlock anything yet.' . $contact;
    return null;
}

/** Adds the link's products to the account. Returns [type: success|info|error, message]. */
function access_redeem(array $link, array $user): array
{
    if ($p = access_problem($link)) return ['error', $p];
    $added = [];
    foreach (access_products($link) as $prod) {
        if (grant_product((int) $user['id'], $prod, 'link', null, ['link_id' => (int) $link['id'], 'note' => $link['label'], 'ip' => client_ip()])) $added[] = $prod;
    }
    if (!$added) return ['info', 'Your account already has ' . product_names(access_products($link)) . '. Nothing else to do.'];
    q('UPDATE access_links SET uses = uses + 1, updated_at = ? WHERE id = ?', [now(), (int) $link['id']]);
    return ['success', 'Unlocked: ' . product_names($added) . '. Enjoy!'];
}

function access_park(string $code): void { $_SESSION[ACCESS_PENDING] = strtolower($code); }
function access_parked(): ?string { $c = $_SESSION[ACCESS_PENDING] ?? null; return is_string($c) && $c !== '' ? $c : null; }
function access_forget(): void { unset($_SESSION[ACCESS_PENDING]); }

/** After sign-in or sign-up: applies a parked link and flashes the outcome. */
function access_apply_parked(array $user): void
{
    $code = access_parked();
    if ($code === null) return;
    access_forget();
    $link = access_find($code);
    if (!$link) { flash('error', 'That access link was not recognised. Check it against your WarriorPlus receipt.'); return; }
    [$type, $msg] = access_redeem($link, $user);
    flash($type, $msg);
}

/** How many accounts each link has unlocked (distinct users in the log), for the admin list. */
function access_redemptions(): array
{
    $out = [];
    foreach (q('SELECT link_id, COUNT(DISTINCT user_id) AS n FROM product_events WHERE link_id IS NOT NULL GROUP BY link_id')->fetchAll() as $r) $out[(int) $r['link_id']] = (int) $r['n'];
    return $out;
}
