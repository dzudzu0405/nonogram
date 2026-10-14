<?php
// What each WarriorPlus product unlocks. Product codes are stored in the entitlements table.

const PRODUCTS = [
    'fe'          => ['name' => 'Nonogram Studio',        'step' => 'FE'],
    'vault'       => ['name' => 'Picture Vault',          'step' => 'Bump'],
    'pro'         => ['name' => 'Pro',                    'step' => 'OTO1'],
    'pro_lite'    => ['name' => 'Pro Lite',               'step' => 'OTO1 downsell'],
    'dfy10'       => ['name' => 'DFY Puzzle Books (10)',  'step' => 'OTO2'],
    'dfy5'        => ['name' => 'DFY Puzzle Books (5)',   'step' => 'OTO2 downsell'],
    'agency'      => ['name' => 'Agency',                 'step' => 'OTO3'],
    'agency_lite' => ['name' => 'Agency Lite',            'step' => 'OTO3 downsell'],
];

function user_products(int $userId): array
{
    $rows = q('SELECT DISTINCT product FROM entitlements WHERE user_id = ? AND active = 1', [$userId])->fetchAll(PDO::FETCH_COLUMN);
    return array_values(array_intersect(array_keys(PRODUCTS), $rows));
}

/** Feature flags and limits for a user. Admins get everything. */
function features(array $user): array
{
    $p = $user['role'] === 'admin' ? array_keys(PRODUCTS) : user_products((int) $user['id']);
    $has = fn(string $code) => in_array($code, $p, true);

    $f = [
        'products'     => $p,
        'app'          => $has('fe'),
        'vault'        => $has('vault'),
        'pro'          => $has('pro') || $has('pro_lite'),
        'color'        => $has('pro'),
        'dfy'          => $has('dfy10') ? 10 : ($has('dfy5') ? 5 : 0),
        'play'         => $has('agency') || $has('agency_lite'),
        'white_label'  => $has('agency'),
        'team'         => $has('agency'),
        'max_grid'     => 30,
        'max_projects' => 100,
        'max_book'     => 50,
        'storage_mb'   => 500,
    ];
    if ($f['pro']) {
        $f['max_grid'] = 50;
        $f['max_projects'] = 1000;
        $f['max_book'] = 500;
        $f['storage_mb'] = 5000;
    }
    return $f;
}

/** Turns a product on for a user. Returns true when it was newly granted (false if they already had it).
    $ctx: link_id, actor_id, note, ip - kept in the product_events log. */
function grant_product(int $userId, string $product, string $source = 'admin', ?string $txn = null, array $ctx = []): bool
{
    if (!isset(PRODUCTS[$product])) throw new InvalidArgumentException('Unknown product ' . $product);
    $exists = qv('SELECT id FROM entitlements WHERE user_id = ? AND product = ? AND active = 1', [$userId, $product]);
    if ($exists) return false;
    insert('entitlements', [
        'user_id' => $userId, 'product' => $product, 'source' => $source, 'txn_id' => $txn,
        'active' => 1, 'created_at' => now(),
    ]);
    product_event($userId, $product, 'grant', $source, $ctx);
    return true;
}

/** Turns a product off (refunds). Returns true when something was revoked. Projects are never touched. */
function revoke_product(int $userId, string $product, string $source = 'admin', array $ctx = []): bool
{
    $n = q('UPDATE entitlements SET active = 0, revoked_at = ? WHERE user_id = ? AND product = ? AND active = 1', [now(), $userId, $product])->rowCount();
    if ($n) product_event($userId, $product, 'revoke', $source, $ctx);
    return $n > 0;
}

function product_event(int $userId, string $product, string $action, string $source, array $ctx = []): void
{
    try {
        insert('product_events', [
            'user_id' => $userId, 'product' => $product, 'action' => $action, 'source' => $source,
            'link_id' => $ctx['link_id'] ?? null, 'actor_id' => $ctx['actor_id'] ?? null,
            'note' => isset($ctx['note']) && $ctx['note'] !== '' ? str_limit((string) $ctx['note'], 255) : null,
            'ip' => $ctx['ip'] ?? null, 'created_at' => now(),
        ]);
    } catch (Throwable $e) {
        error_log('[nonogram] product_events: ' . $e->getMessage()); // log table missing must not block a sale
    }
}

/** Funnel products the user does not own yet that have a sales page set in config upgrade_urls, in funnel order. */
function upgrade_offers(array $user): array
{
    $urls = (array) config('upgrade_urls', []);
    $own = features($user)['products'];
    $out = [];
    foreach (['pro', 'vault', 'dfy10', 'agency'] as $code) {
        if (!in_array($code, $own, true) && !empty($urls[$code])) $out[] = ['code' => $code, 'name' => PRODUCTS[$code]['name'], 'url' => (string) $urls[$code]];
    }
    return $out;
}

function product_names(array $codes): string
{
    return implode(', ', array_map(fn($c) => PRODUCTS[$c]['name'] ?? $c, $codes));
}
