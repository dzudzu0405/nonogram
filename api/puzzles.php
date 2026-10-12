<?php
require __DIR__ . '/../lib/bootstrap.php';
require __DIR__ . '/../lib/puzzles.php';

$user = api_begin();
$f = features($user);
require_app_access($f);
$uid = (int) $user['id'];
$in = json_in();

switch ($_GET['action'] ?? '') {

    // Create or update a puzzle inside a project.
    case 'save':
        $project = own_project((int) ($in['project_id'] ?? 0), $uid);
        $row = validate_puzzle_input($in, $f);
        $id = (int) ($in['id'] ?? 0);
        if ($id && !q1('SELECT id FROM puzzles WHERE id = ? AND project_id = ?', [$id, $project['id']])) json_error('Puzzle not found.', 404);

        if (empty($in['force'])) {
            $dup = q1('SELECT id, name FROM puzzles WHERE project_id = ? AND hash = ? AND id <> ?', [$project['id'], $row['hash'], $id]);
            if ($dup) json_error("This puzzle matches \"{$dup['name']}\" in this project (same picture, rotated or mirrored).", 409, ['duplicate' => ['id' => (int) $dup['id'], 'name' => $dup['name']]]);
        }

        if ($id) {
            $sets = implode(', ', array_map(fn($k) => "$k = ?", array_keys($row)));
            q("UPDATE puzzles SET $sets, updated_at = ? WHERE id = ?", array_merge(array_values($row), [now(), $id]));
        } else {
            $count = (int) qv('SELECT COUNT(*) FROM puzzles WHERE project_id = ?', [$project['id']]);
            if ($count >= $f['max_book']) json_error("A project holds up to {$f['max_book']} puzzles on your plan.", 403);
            $pos = (int) qv('SELECT COALESCE(MAX(position), 0) FROM puzzles WHERE project_id = ?', [$project['id']]) + 1;
            $id = insert('puzzles', $row + [
                'project_id' => $project['id'], 'user_id' => $uid, 'position' => $pos,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        touch_project((int) $project['id']);
        json_out(['ok' => true, 'puzzle' => puzzle_out(q1('SELECT * FROM puzzles WHERE id = ?', [$id]))]);

    case 'delete':
        $pz = q1('SELECT p.id, p.project_id FROM puzzles p JOIN projects pr ON pr.id = p.project_id WHERE p.id = ? AND pr.user_id = ?', [(int) ($in['id'] ?? 0), $uid]);
        if (!$pz) json_error('Puzzle not found.', 404);
        q('DELETE FROM puzzles WHERE id = ?', [$pz['id']]);
        touch_project((int) $pz['project_id']);
        json_out(['ok' => true]);

    // Save a new order: ids in the order they should appear.
    case 'reorder':
        $project = own_project((int) ($in['project_id'] ?? 0), $uid);
        $ids = array_map('intval', (array) ($in['ids'] ?? []));
        db()->beginTransaction();
        foreach ($ids as $i => $pid) q('UPDATE puzzles SET position = ? WHERE id = ? AND project_id = ?', [$i + 1, $pid, $project['id']]);
        db()->commit();
        touch_project((int) $project['id']);
        json_out(['ok' => true]);

    default:
        json_error('Unknown action.', 404);
}
