"""Verify the uploaded frozen specification before generating any DTOs."""
from pathlib import Path
import hashlib
import subprocess
import sys

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
subprocess.run([sys.executable, str(root / 'VALIDATION_HARNESS.py')], check=True)
