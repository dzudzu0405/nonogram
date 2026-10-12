<?php
// Server-side puzzle helpers: validation, clue generation and the JSON shape sent to the browser.

const DIFFICULTIES = ['Easy', 'Medium', 'Hard', 'Expert', 'Not unique'];

/** Clue runs for one line of cell values: [[length, color], ...] (same as engine.js runs()). */
function line_runs(array $line): array
{
    $out = []; $c = 0; $n = 0;
    foreach ($line as $v) {
        if ($v && $v === $c) { $n++; continue; }
        if ($c) $out[] = [$n, $c];
        $c = $v; $n = $v ? 1 : 0;
    }
    if ($c) $out[] = [$n, $c];
    return $out;
}

function grid_clues(int $w, int $h, array $cells): array
{
    $rows = []; $cols = [];
    for ($r = 0; $r < $h; $r++) $rows[] = line_runs(array_slice($cells, $r * $w, $w));
    for ($c = 0; $c < $w; $c++) {
        $col = [];
        for ($r = 0; $r < $h; $r++) $col[] = $cells[$r * $w + $c];
        $cols[] = line_runs($col);
    }
    return [$rows, $cols];
}

/** Validate a puzzle sent by the editor against the user's plan. Returns a clean row or ends the request. */
function validate_puzzle_input(array $in, array $features): array
{
    $w = (int) ($in['w'] ?? 0);
    $h = (int) ($in['h'] ?? 0);
    $max = (int) $features['max_grid'];
    if ($w < 5 || $h < 5 || $w > 50 || $h > 50) json_error('Grid size must be between 5 and 50.');
    if ($w > $max || $h > $max) json_error("Your plan supports grids up to {$max}×{$max}. Pro raises this to 50×50.", 403);

    $palette = $in['palette'] ?? [];
    if (!is_array($palette) || count($palette) < 1 || count($palette) > 4) json_error('A puzzle needs 1 to 4 colors.');
    foreach ($palette as $hex) if (!is_string($hex) || !preg_match('/^#[0-9a-fA-F]{6}$/', $hex)) json_error('Invalid color in palette.');
    if (count($palette) > 1 && !$features['color']) json_error('Color Nonograms are part of Pro.', 403);

    $grid = (string) ($in['grid'] ?? '');
    $k = count($palette);
    if (strlen($grid) !== $w * $h || !preg_match('/^[0-' . $k . ']+$/', $grid)) json_error('The grid data does not match its size.');
    $cells = array_map('intval', str_split($grid));
    if (!array_filter($cells)) json_error('The grid is empty. Fill at least one cell.');

    $difficulty = in_array($in['difficulty'] ?? '', DIFFICULTIES, true) ? $in['difficulty'] : 'Not unique';
    $hash = (string) ($in['hash'] ?? '');
    if (!preg_match('/^[0-9a-f]{16}$/', $hash)) json_error('Missing puzzle fingerprint.');
    [$rows, $cols] = grid_clues($w, $h, $cells);

    return [
        'name' => str_limit((string) ($in['name'] ?? 'Puzzle'), 160) ?: 'Puzzle',
        'w' => $w, 'h' => $h, 'colors' => $k,
        'grid' => $grid,
        'palette' => json_encode(array_values(array_map('strtolower', $palette))),
        'row_clues' => json_encode($rows),
        'col_clues' => json_encode($cols),
        'difficulty' => $difficulty,
        'score' => round(min(99, max(0, (float) ($in['score'] ?? 99))), 2),
        'is_unique' => !empty($in['unique']) ? 1 : 0,
        'hash' => $hash,
    ];
}

function puzzle_out(array $p): array
{
    return [
        'id' => (int) $p['id'],
        'name' => $p['name'],
        'w' => (int) $p['w'], 'h' => (int) $p['h'],
        'grid' => $p['grid'],
        'palette' => json_decode($p['palette'], true),
        'difficulty' => $p['difficulty'],
        'score' => (float) $p['score'],
        'unique' => (bool) $p['is_unique'],
        'position' => (int) $p['position'],
        'updated_at' => $p['updated_at'],
    ];
}

function own_project(int $id, int $uid): array
{
    $p = q1('SELECT * FROM projects WHERE id = ? AND user_id = ?', [$id, $uid]);
    if (!$p) json_error('Project not found.', 404);
    return $p;
}

function touch_project(int $id): void
{
    q('UPDATE projects SET updated_at = ? WHERE id = ?', [now(), $id]);
}

function require_app_access(array $features): void
{
    if (!$features['app']) json_error('Your account has no active Nonogram Studio license.', 403);
}
