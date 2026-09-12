"""Package the verified development plugin, including its built PoC and autoloader."""
from hashlib import sha256
import json
from pathlib import Path
from zipfile import ZIP_DEFLATED, ZipFile, ZipInfo

root = Path(__file__).resolve().parents[1]
plugin = root / 'wp-content/plugins/manga-overlay-core'
required = ['manga-overlay-core.php', 'vendor/autoload.php', 'database/schema.sql', 'templates/editor.php', 'assets/dist/admin/content.js', 'assets/dist/admin/reports.js', 'assets/admin/reports.css', 'assets/dist/poc/index.html', 'assets/dist/reader/reader.js', 'assets/dist/reader/reader.css', 'assets/dist/editor/editor.js', 'assets/dist/editor/editor.css']
for name in required:
    if not (plugin / name).is_file():
        raise SystemExit(f'Missing build input: {name}; build Composer and the frontend first.')

# Explicit inputs keep credentials, uploads, tests and development dependencies out.
files = {}
for name in ['manga-overlay-core.php', 'composer.json', 'src', 'database', 'vendor', 'templates', 'assets/dist/poc', 'assets/dist/reader', 'assets/dist/editor', 'assets/dist/admin', 'assets/admin']:
    source = plugin / name
    for path in sorted(source.rglob('*')) if source.is_dir() else [source]:
        if path.is_file():
            files['manga-overlay-core/' + path.relative_to(plugin).as_posix()] = path.read_bytes()
for path in sorted((root / 'licenses').rglob('*')):
    if path.is_file():
        files['manga-overlay-core/' + path.relative_to(root).as_posix()] = path.read_bytes()
files['manga-overlay-core/THIRD_PARTY_NOTICES.md'] = (root / 'THIRD_PARTY_NOTICES.md').read_bytes()
files['manga-overlay-core/DEVELOPMENT.md'] = (root / 'docs/WORDPRESS_DEVELOPMENT.md').read_bytes()
files['manga-overlay-core/build-info.json'] = json.dumps({
    'version': json.loads((plugin / 'package.json').read_text())['version'],
    'spec_version': '1.1.3',
    'stage': 'development-editor-security',
    'editor_persistence': True,
}, indent=2).encode() + b'\n'

output = root / 'build/manga-overlay-core-development.zip'
output.parent.mkdir(exist_ok=True)
with ZipFile(output, 'w', compression=ZIP_DEFLATED) as archive:
    for name, content in sorted(files.items()):
        info = ZipInfo(name, date_time=(2026, 1, 1, 0, 0, 0))
        info.compress_type = ZIP_DEFLATED
        info.external_attr = 0o100644 << 16
        archive.writestr(info, content)
with ZipFile(output) as archive:
    if archive.testzip() is not None or len(archive.namelist()) != len(files):
        raise SystemExit('Package integrity check failed.')
checksum = sha256(output.read_bytes()).hexdigest()
(output.parent / 'SHA256SUMS.txt').write_text(f'{checksum}  {output.name}\n')
print(f'Built {output.name}: {len(files)} files, {output.stat().st_size} bytes, SHA256 {checksum}')

# Ship the WordPress theme separately, as required by WordPress's installers.
theme = root / 'wp-content/themes/manga-overlay-theme'
theme_output = output.parent / 'manga-overlay-theme-development.zip'
for name in ['style.css', 'functions.php', 'index.php', 'templates/reader.php']:
    if not (theme / name).is_file():
        raise SystemExit(f'Missing theme build input: {name}')
with ZipFile(theme_output, 'w', compression=ZIP_DEFLATED) as archive:
    for path in sorted(theme.rglob('*')):
        if not path.is_file() or path.suffix not in ['.php', '.css', '.json']:
            continue
        info = ZipInfo('manga-overlay-theme/' + path.relative_to(theme).as_posix(), date_time=(2026, 1, 1, 0, 0, 0))
        info.compress_type = ZIP_DEFLATED
        info.external_attr = 0o100644 << 16
        archive.writestr(info, path.read_bytes())
with ZipFile(theme_output) as archive:
    if archive.testzip() is not None:
        raise SystemExit('Theme package integrity check failed.')
with (output.parent / 'SHA256SUMS.txt').open('a') as checksums:
    checksums.write(f'{sha256(theme_output.read_bytes()).hexdigest()}  {theme_output.name}\n')
print(f'Built {theme_output.name}: {theme_output.stat().st_size} bytes')
