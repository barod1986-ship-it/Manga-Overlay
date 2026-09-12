"""Validate captured real REST responses against the frozen OpenAPI schemas."""
import json
from pathlib import Path
import sys
import yaml
from jsonschema import Draft202012Validator, FormatChecker

root = Path(__file__).resolve().parents[1]
spec = yaml.safe_load((root / 'docs/spec-v1.1.3/API.openapi.yaml').read_text())
samples = json.loads(Path(sys.argv[1]).read_text())
for index, sample in enumerate(samples):
    schema = {'$ref': '#/components/schemas/' + sample['schema'], 'components': spec['components']}
    errors = list(Draft202012Validator(schema, format_checker=FormatChecker()).iter_errors(sample['body']))
    if errors:
        raise SystemExit(f'Response {index} {sample["schema"]}: {errors[0].message}')
print(f'{len(samples)} actual content responses match frozen OpenAPI schemas.')
