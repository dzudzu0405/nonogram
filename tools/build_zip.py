"""Build the upload package for cPanel: python tools/build_zip.py
Creates dist/nonogram-studio-<version>.zip without local-only files (config.php, storage data, tests, dev tools)."""
import pathlib, re, zipfile

ROOT = pathlib.Path(__file__).resolve().parent.parent
version = re.search(r"APP_VERSION', '([^']+)'", (ROOT / 'lib/bootstrap.php').read_text()).group(1)
EXCLUDE_DIRS = {'.git', '.claude', 'dist', 'tests', 'tools', 'prototypes', 'node_modules'}
EXCLUDE_FILES = {'config.php', 'dev-router.php', '.gitignore'}

out = ROOT / 'dist' / f'nonogram-studio-{version}.zip'
out.parent.mkdir(exist_ok=True)
count = 0
with zipfile.ZipFile(out, 'w', zipfile.ZIP_DEFLATED) as z:
    for p in sorted(ROOT.rglob('*')):
        rel = p.relative_to(ROOT)
        if p.is_dir() or rel.parts[0] in EXCLUDE_DIRS or rel.name in EXCLUDE_FILES or rel.suffix == '.md':
            continue
        if rel.parts[0] == 'storage' and rel.name != '.htaccess':
            continue
        z.write(p, rel.as_posix())
        count += 1
print(f'{out.relative_to(ROOT)}: {count} files')
