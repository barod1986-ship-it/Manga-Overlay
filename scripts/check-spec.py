"""Verify the uploaded frozen specification before generating any DTOs."""
from pathlib import Path
import hashlib
import subprocess
import sys
import re
import json
import yaml

root = Path(__file__).resolve().parents[1] / 'docs/spec-v1.1.3'
failures = []
for entry in (root / 'SHA256SUMS.txt').read_text().splitlines():
    if not entry.strip():
        continue
    expected, filename = entry.split(maxsplit=1)
    path = root / filename.lstrip('*')
    if not path.is_file() or hashlib.sha256(path.read_bytes()).hexdigest() != expected:
        failures.append(filename)
if failures:
    sys.exit('Frozen spec integrity failure: ' + ', '.join(failures))
print('Frozen specification SHA256 checks passed.', flush=True)
runtime_schema = root.parents[1] / 'wp-content/plugins/manga-overlay-core/database/schema.sql'
if runtime_schema.exists():
    statements = re.findall(r'```sql\n(.*?)```', (root / 'DATABASE_SCHEMA.md').read_text(), re.S)
    expected_sql = '\n\n'.join(statement.rstrip() for statement in statements) + '\n'
    if len(statements) != 9 or runtime_schema.read_text() != expected_sql:
        sys.exit('Runtime SQL differs from the nine canonical schema statements.')
    print('Runtime SQL matches all nine canonical tables.', flush=True)
subprocess.run([sys.executable, str(root / 'VALIDATION_HARNESS.py')], check=True)
contracts_path = root.parents[1] / 'wp-content/plugins/manga-overlay-core/database/request-contracts.json'
if contracts_path.exists():
    contracts = json.loads(contracts_path.read_text())
    schemas = yaml.safe_load((root / 'API.openapi.yaml').read_text())['components']['schemas']
    expected_names = {'ChapterCreate', 'ChapterPatch', 'ChapterReviewPatch', 'PageReorder', 'ReadingProgressUpdate'}
    if set(contracts) != expected_names or any(contracts[name] != schemas[name] for name in expected_names):
        sys.exit('Content request schemas differ from frozen OpenAPI.')
    print('Content request schemas match frozen OpenAPI.', flush=True)

contracts = json.loads((contracts_path.parent / 'element-contracts.json').read_text())
expected_names = {'ElementCreate', 'ElementPatch', 'Geometry', 'ElementStyle', 'BubbleStyle', 'NarrationStyle', 'FreeTextStyle', 'SfxStyle'}
if set(contracts) != expected_names or any(contracts[name] != schemas[name] for name in expected_names):
    sys.exit('Element request schemas differ from frozen OpenAPI.')
print('Element request schemas match frozen OpenAPI.', flush=True)
