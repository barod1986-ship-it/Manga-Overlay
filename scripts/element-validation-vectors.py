"""Generate contract-based adversarial cases with an independent JSON Schema validator."""
from pathlib import Path
from copy import deepcopy
import json, sys, yaml
from jsonschema import Draft202012Validator
root = Path(__file__).resolve().parents[1]
spec = yaml.safe_load((root / 'docs/spec-v1.1.3/API.openapi.yaml').read_text())
styles = spec['components']['schemas']['ElementStyle']['properties']
vectors = []
def add(name, value):
    schema = {'$ref': '#/components/schemas/' + name, 'components': spec['components']}
    vectors.append({'schema': name, 'value': value, 'valid': Draft202012Validator(schema).is_valid(value)})
for kind in ['bubble', 'narration', 'free_text', 'sfx']:
    base = dict(page_id=1, target_lang='ar', element_type=kind, content='نص', x_unit=0, y_unit=0, w_unit=10, h_unit=10)
    for key, prop in styles.items():
        cases = [None, {}, [], True, '', 0, -1, 'url(javascript:1)', 'https://example.invalid/font', '#A1b2C3']
        cases += prop.get('enum', [])
        for boundary in ['minimum', 'maximum']:
            if boundary in prop: cases += [prop[boundary], prop[boundary] - 1, prop[boundary] + 1]
        if key in ['shadow', 'tail', 'burst']:
            cases += [{'unknown': 1}]
            for sub, subprop in prop['properties'].items():
                cases += [{sub: subprop.get('enum', [subprop.get('minimum', True)])[0]}]
        for value in cases:
            add('ElementCreate', base | {'style': {key: value}})
            add('ElementPatch', {'element_type': kind, 'style': {key: value}})
    for invalid in [None, [], {}, {'element_type': kind}, {'content': ''}, {'style': {}}, {'x_unit': 0}, {'rotation_mdeg': 360001}, {'z_index': -1001}, {'id': 1}, {'page_id': 2}]:
        add('ElementPatch', invalid)
    for key in base:
        missing = deepcopy(base); del missing[key]; add('ElementCreate', missing)
        for value in [None, False, [], {}, 1.5, 1.0, '1', 0, -1, 1000001]:
            add('ElementCreate', base | {key: value})
    add('ElementCreate', base | {'evil': True})
    add('ElementCreate', base | {'style': {'shadow': {'color': '#FFFFFF', 'evil': True}}})
# Preset envelopes use the generic style schema; stored type restrictions are tested in REST.
for scope in ['personal', 'work', 'global']:
    base = dict(scope=scope, name='نمط', element_type='bubble', style={})
    for key, values in {'scope': [None, 'unknown', False], 'name': ['', 'ن' * 101, 12], 'work_id': [None, 1, 0, '1', [], 1.5], 'is_default': [True, False, None, 1], 'style': [None, [], {}, {'tail': {'enabled': True}}, {'evil': True}, {'fontSizeUnit': 200001}]}.items():
        for value in values:
            add('PresetCreate', base | {key: value})
            add('PresetPatch', {key: value})
    for key in base:
        missing = deepcopy(base); del missing[key]; add('PresetCreate', missing)
    add('PresetCreate', base | {'owner_user_id': 99})
for value in [{}, [], None, {'name': 'صحيح'}, {'style': {}}, {'style': {'shadow': {'evil': True}}}]: add('PresetPatch', value)
Path(sys.argv[1]).write_text(json.dumps(vectors, ensure_ascii=False))
print(f'{len(vectors)} independent element contract vectors generated.')
