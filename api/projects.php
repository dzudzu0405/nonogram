<?php
require __DIR__ . '/../lib/bootstrap.php';
require __DIR__ . '/../lib/puzzles.php';

$user = api_begin();
$f = features($user);
require_app_access($f);
$uid = (int) $user['id'];
$in = json_in();

switch ($_GET['action'] ?? '') {

    case 'list':
        $projects = q('SELECT id, name, created_at, updated_at FROM projects WHERE user_id = ? ORDER BY updated_at DESC, id DESC', [$uid])->fetchAll();
        $stats = [];
        foreach (q('SELECT project_id, w, h, difficulty, is_unique FROM puzzles WHERE user_id = ?', [$uid]) as $r) {
            $s = &$stats[$r['project_id']];
            $s['count'] = ($s['count'] ?? 0) + 1;
            $s['not_unique'] = ($s['not_unique'] ?? 0) + ($r['is_unique'] ? 0 : 1);
            $s['sizes'][$r['w'] . '×' . $r['h']] = true;
            $s['levels'][$r['difficulty']] = ($s['levels'][$r['difficulty']] ?? 0) + 1;
            unset($s);
        }
        $covers = [];
        $sql = 'SELECT p.project_id, p.w, p.h, p.grid, p.palette FROM puzzles p WHERE p.user_id = ? AND p.id = '
             . '(SELECT p2.id FROM puzzles p2 WHERE p2.project_id = p.project_id ORDER BY p2.position, p2.id LIMIT 1)';
        foreach (q($sql, [$uid]) as $r) {
            $covers[$r['project_id']] = ['w' => (int) $r['w'], 'h' => (int) $r['h'], 'grid' => $r['grid'], 'palette' => json_decode($r['palette'], true)];
        }
        $out = array_map(function ($p) use ($stats, $covers) {
            $s = $stats[$p['id']] ?? [];
            return [
                'id' => (int) $p['id'], 'name' => $p['name'],
                'created_at' => $p['created_at'], 'updated_at' => $p['updated_at'],
                'count' => $s['count'] ?? 0, 'not_unique' => $s['not_unique'] ?? 0,
                'sizes' => array_keys($s['sizes'] ?? []), 'levels' => $s['levels'] ?? (object) [],
                'cover' => $covers[$p['id']] ?? null,
            ];
        }, $projects);
        json_out(['ok' => true, 'projects' => $out, 'limit' => $f['max_projects']]);

    case 'get':
        $p = own_project((int) ($_GET['id'] ?? 0), $uid);
        $puzzles = q('SELECT * FROM puzzles WHERE project_id = ? ORDER BY position, id', [$p['id']])->fetchAll();
        json_out(['ok' => true, 'project' => [
            'id' => (int) $p['id'], 'name' => $p['name'],
            'settings' => $p['settings'] ? json_decode($p['settings'], true) : (object) [],
            'updated_at' => $p['updated_at'],
            'puzzles' => array_map('puzzle_out', $puzzles),
        ]]);

    case 'create':
        $count = (int) qv('SELECT COUNT(*) FROM projects WHERE user_id = ?', [$uid]);
        if ($count >= $f['max_projects']) json_error("You have reached {$f['max_projects']} projects. Delete one or upgrade to Pro.", 403);
        $name = str_limit((string) ($in['name'] ?? ''), 160) ?: 'Untitled project';
        $id = insert('projects', ['user_id' => $uid, 'name' => $name, 'settings' => null, 'created_at' => now(), 'updated_at' => now()]);
        json_out(['ok' => true, 'id' => $id]);

    case 'rename':
        $p = own_project((int) ($in['id'] ?? 0), $uid);
        $name = str_limit((string) ($in['name'] ?? ''), 160);
        if ($name === '') json_error('Enter a project name.');
        q('UPDATE projects SET name = ?, updated_at = ? WHERE id = ?', [$name, now(), $p['id']]);
        json_out(['ok' => true]);

    case 'settings':
        $p = own_project((int) ($in['id'] ?? 0), $uid);
        $settings = json_encode($in['settings'] ?? []);
        if (strlen($settings) > 20000) json_error('Settings are too large.');
        q('UPDATE projects SET settings = ? WHERE id = ?', [$settings, $p['id']]);
        json_out(['ok' => true]);

    case 'duplicate':
        $p = own_project((int) ($in['id'] ?? 0), $uid);
        $count = (int) qv('SELECT COUNT(*) FROM projects WHERE user_id = ?', [$uid]);
        if ($count >= $f['max_projects']) json_error("You have reached {$f['max_projects']} projects.", 403);
        db()->beginTransaction();
        $newId = insert('projects', ['user_id' => $uid, 'name' => str_limit($p['name'] . ' (copy)', 160), 'settings' => $p['settings'], 'created_at' => now(), 'updated_at' => now()]);
        foreach (q('SELECT * FROM puzzles WHERE project_id = ? ORDER BY position, id', [$p['id']])->fetchAll() as $pz) {
            unset($pz['id']);
            $pz['project_id'] = $newId;
            $pz['created_at'] = $pz['updated_at'] = now();
            insert('puzzles', $pz);
        }
        db()->commit();
        json_out(['ok' => true, 'id' => $newId]);

    case 'delete':
        $p = own_project((int) ($in['id'] ?? 0), $uid);
        q('DELETE FROM puzzles WHERE project_id = ?', [$p['id']]);
        q('DELETE FROM projects WHERE id = ?', [$p['id']]);
        json_out(['ok' => true]);

    default:
        json_error('Unknown action.', 404);
}
