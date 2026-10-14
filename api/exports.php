<?php
// Export history for My Exports. PDFs and PNGs are built in the browser, so the editor reports each download here;
// no file is stored on the server. "Download again" rebuilds the PDF from the project's current puzzles.
require __DIR__ . '/../lib/bootstrap.php';
require __DIR__ . '/../lib/puzzles.php';

const EXPORT_KINDS = ['project_pdf', 'puzzle_pdf', 'png'];

$user = api_begin();
require_app_access(features($user));
$uid = (int) $user['id'];
$in = json_in();

switch ($_GET['action'] ?? '') {
    case 'log':
        $kind = (string) ($in['kind'] ?? '');
        if (!in_array($kind, EXPORT_KINDS, true)) json_error('Unknown export type.');
        $pid = (int) ($in['project_id'] ?? 0);
        $project = $pid ? own_project($pid, $uid) : null;
        $page = (string) ($in['page'] ?? '');
        $id = insert('exports', [
            'user_id' => $uid, 'project_id' => $project ? (int) $project['id'] : null, 'kind' => $kind,
            'name' => str_limit((string) ($in['name'] ?? ''), 160) ?: ($project['name'] ?? 'Export'),
            'page_size' => in_array($page, ['letter', '8x10', '6x9', 'a4', 'a5'], true) ? $page : null,
            'puzzle_count' => max(0, min(10000, (int) ($in['puzzles'] ?? 0))),
            'page_count' => max(0, min(100000, (int) ($in['pages'] ?? 0))),
            'created_at' => now(),
        ]);
        json_out(['ok' => true, 'id' => $id]);

    case 'delete':
        q('DELETE FROM exports WHERE id = ? AND user_id = ?', [(int) ($in['id'] ?? 0), $uid]);
        json_out(['ok' => true]);

    default:
        json_error('Unknown action.', 404);
}
