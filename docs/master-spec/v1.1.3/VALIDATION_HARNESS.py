#!/usr/bin/env python3
"""Static contract validation for Manga Overlay Master Spec.

Requires only PyYAML + jsonschema. Exits non-zero on any failure.
"""
from __future__ import annotations
import csv, json, re, sys
from pathlib import Path
from typing import Any
import yaml
from yaml.tokens import AnchorToken, AliasToken
from jsonschema import Draft202012Validator

ROOT = Path(__file__).resolve().parent
API = ROOT / "API.openapi.yaml"

passes: list[str] = []
fails: list[str] = []

def check(cond: bool, label: str, detail: str = "") -> None:
    if cond:
        passes.append(label)
    else:
        fails.append(label + (f": {detail}" if detail else ""))

def fail_if_exception(label, fn):
    try:
        value = fn()
        passes.append(label)
        return value
    except Exception as exc:
        fails.append(f"{label}: {exc}")
        return None

# Duplicate-key rejecting loader.
class UniqueKeyLoader(yaml.SafeLoader):
    pass

def construct_mapping(loader, node, deep=False):
    mapping = {}
    for key_node, value_node in node.value:
        key = loader.construct_object(key_node, deep=deep)
        if key in mapping:
            raise ValueError(f"duplicate YAML key: {key!r} at line {key_node.start_mark.line + 1}")
        mapping[key] = loader.construct_object(value_node, deep=deep)
    return mapping
UniqueKeyLoader.add_constructor(yaml.resolver.BaseResolver.DEFAULT_MAPPING_TAG, construct_mapping)

raw = API.read_text()
doc = fail_if_exception("YAML parses with duplicate-key rejection", lambda: yaml.load(raw, Loader=UniqueKeyLoader))
if doc is None:
    print("FAIL\n" + "\n".join(fails)); sys.exit(1)

# Anchors/aliases are forbidden in the shipped contract.
tokens = list(yaml.scan(raw))
anchors = [t for t in tokens if isinstance(t, (AnchorToken, AliasToken))]
check(not anchors, "No YAML anchors or aliases", ", ".join(str(t) for t in anchors[:5]))
check(doc.get("openapi") == "3.1.2", "OpenAPI version is 3.1.2", str(doc.get("openapi")))
check(doc.get("info", {}).get("version") == "1.1.3", "API info.version is 1.1.3")

# Operations and operationIds.
methods = {"get", "post", "put", "patch", "delete"}
ops: list[tuple[str,str,dict[str,Any]]] = []
for path, item in doc.get("paths", {}).items():
    for method, op in item.items():
        if method in methods and isinstance(op, dict):
            ops.append((path, method, op))
opids = [o.get("operationId") for _,_,o in ops]
check(all(opids), "Every operation has operationId")
check(len(opids) == len(set(opids)), "operationId values are unique")

# Internal refs resolution.
def ptr(root: Any, ref: str) -> Any:
    if not ref.startswith("#/"):
        raise ValueError(f"external ref not allowed: {ref}")
    cur = root
    for part in ref[2:].split("/"):
        part = part.replace("~1", "/").replace("~0", "~")
        cur = cur[part]
    return cur

refs: list[str] = []
def walk(x: Any):
    if isinstance(x, dict):
        if "$ref" in x: refs.append(x["$ref"])
        for v in x.values(): walk(v)
    elif isinstance(x, list):
        for v in x: walk(v)
walk(doc)
ref_errors=[]
for ref in refs:
    try: ptr(doc, ref)
    except Exception as e: ref_errors.append(f"{ref}: {e}")
check(not ref_errors, "All internal $ref values resolve", ref_errors[0] if ref_errors else "")


# Component usage: no dead schemas/responses/parameters.
used_schema_refs={r.rsplit('/',1)[-1] for r in refs if r.startswith('#/components/schemas/')}
used_response_refs={r.rsplit('/',1)[-1] for r in refs if r.startswith('#/components/responses/')}
used_parameter_refs={r.rsplit('/',1)[-1] for r in refs if r.startswith('#/components/parameters/')}
check(set(doc['components']['schemas']) == used_schema_refs, f"All component schemas are referenced ({len(used_schema_refs)})", ", ".join(sorted(set(doc['components']['schemas'])-used_schema_refs)))
check(set(doc['components']['responses']) == used_response_refs, f"All response components are referenced ({len(used_response_refs)})", ", ".join(sorted(set(doc['components']['responses'])-used_response_refs)))
check(set(doc['components']['parameters']) == used_parameter_refs, f"All parameter components are referenced ({len(used_parameter_refs)})", ", ".join(sorted(set(doc['components']['parameters'])-used_parameter_refs)))
check(len(doc['paths']) == 22, "Path count is 22", str(len(doc['paths'])))
check(len(ops) == 31, "Operation count is 31", str(len(ops)))
check(len(doc['components']['schemas']) == 57, "Schema count is 57", str(len(doc['components']['schemas'])))
check(len(refs) == sum(1 for line in raw.splitlines() if '$ref:' in line), "Raw and parsed $ref counts match (no alias inflation)")

# Protected write contract completeness + deliberate rate-limit set.
write_methods={'post','put','patch','delete'}
missing_security=[]; missing_policy=[]; body_without_400=[]; rate_limited=set()
for path,method,op in ops:
    if method not in write_methods: continue
    if not op.get('security'): missing_security.append(f"{method.upper()} {path}")
    if not op.get('x-required-capability'): missing_policy.append(f"{method.upper()} {path}")
    if op.get('requestBody') and '400' not in op.get('responses',{}): body_without_400.append(f"{method.upper()} {path}")
    if '429' in op.get('responses',{}): rate_limited.add((method,path))
check(not missing_security, "Every write operation declares security", ", ".join(missing_security))
check(not missing_policy, "Every write operation declares x-required-capability/policy", ", ".join(missing_policy))
check(not body_without_400, "Every write requestBody declares 400 validation response", ", ".join(body_without_400))
expected_rate={
    ('post','/chapters/{id}/pages'),('post','/elements'),('patch','/elements/{id}'),
    ('delete','/elements/{id}'),('post','/elements/{id}/lock'),('post','/reports')
}
check(rate_limited == expected_rate, "429 appears on exactly the six MVP rate-limited operations", str(sorted(rate_limited ^ expected_rate)))

# Canonical docs must not reintroduce excluded backup/restore requirements.
canon=list(ROOT.glob('*.md'))+[API, ROOT/'ELEMENT_STYLE.schema.json']
forbidden_hits=[]
for f in canon:
    if f.name in {'INTERNET_VERIFICATION_REPORT.md'}:  # audit prose may quote historical terminology; currently it does not matter to product contract.
        continue
    txt=f.read_text(errors='ignore').lower()
    for term in ['backup','backups','نسخ احتياطي']:
        if term in txt: forbidden_hits.append(f"{f.name}:{term}")
check(not forbidden_hits, "No backup requirement reintroduced", ", ".join(forbidden_hits[:5]))

# GET 200 JSON schemas.
missing_get=[]
for path, method, op in ops:
    if method != "get": continue
    r=op.get("responses",{}).get("200",{})
    if "$ref" in r: r=ptr(doc,r["$ref"])
    schema=r.get("content",{}).get("application/json",{}).get("schema")
    if not schema: missing_get.append(f"GET {path}")
check(not missing_get, "Every GET 200 has application/json schema", ", ".join(missing_get))

# Semantic error examples: data.status MUST match the response status key.
def resolve_response(resp: dict[str,Any]) -> dict[str,Any]:
    seen=set()
    while isinstance(resp,dict) and "$ref" in resp:
        ref=resp["$ref"]
        if ref in seen: raise ValueError("response ref cycle")
        seen.add(ref); resp=ptr(doc,ref)
    return resp

def response_examples(resp: dict[str,Any]):
    resp=resolve_response(resp)
    for media in resp.get("content",{}).values():
        if "example" in media: yield media["example"]
        for ex in media.get("examples",{}).values():
            if isinstance(ex,dict) and "value" in ex: yield ex["value"]

status_mismatches=[]
for path, method, op in ops:
    for status, resp in op.get("responses",{}).items():
        if not str(status).isdigit(): continue
        expected=int(status)
        for ex in response_examples(resp):
            if isinstance(ex,dict) and isinstance(ex.get("data"),dict) and "status" in ex["data"]:
                actual=ex["data"]["status"]
                if actual != expected:
                    status_mismatches.append(f"{method.upper()} {path} {status}: example status={actual}, code={ex.get('code')}")
check(not status_mismatches, "Every error example status matches its HTTP response key", status_mismatches[0] if status_mismatches else "")

# Build standalone Draft 2020-12 schema bundle from OpenAPI components for validation.
def rewrite_refs(x: Any) -> Any:
    if isinstance(x,dict):
        out={}
        for k,v in x.items():
            if k=="$ref" and isinstance(v,str) and v.startswith("#/components/schemas/"):
                out[k]="#/$defs/"+v.rsplit("/",1)[-1]
            else: out[k]=rewrite_refs(v)
        return out
    if isinstance(x,list): return [rewrite_refs(v) for v in x]
    return x
schemas=doc["components"]["schemas"]
def validator_for(name: str):
    bundle={"$schema":"https://json-schema.org/draft/2020-12/schema","$defs":rewrite_refs(schemas),"$ref":f"#/$defs/{name}"}
    Draft202012Validator.check_schema(bundle)
    return Draft202012Validator(bundle)

# Every request body object schema is closed at the top level.
def root_schema_from_media(media: dict[str,Any]) -> dict[str,Any]:
    s=media.get("schema",{})
    if "$ref" in s:
        return ptr(doc,s["$ref"])
    return s

non_strict=[]
for path,method,op in ops:
    rb=op.get("requestBody")
    if not rb: continue
    for ct,media in rb.get("content",{}).items():
        schema=root_schema_from_media(media)
        if schema.get("type") == "object" or "allOf" in schema or "oneOf" in schema or "anyOf" in schema:
            if schema.get("additionalProperties") is not False and schema.get("unevaluatedProperties") is not False:
                non_strict.append(f"{method.upper()} {path} ({ct})")
check(not non_strict, "Every request-body object schema is closed", ", ".join(non_strict))

# API_SPEC §10 error codes must be exercised by at least one operation response example.
api_spec=(ROOT/"API_SPEC.md").read_text()
error_codes=set(re.findall(r"\|\s*\d{3}\s*\|\s*`(mol_[a-z0-9_]+)`\s*\|",api_spec))
used_codes=set()
for path,method,op in ops:
    for status,resp in op.get("responses",{}).items():
        for ex in response_examples(resp):
            if isinstance(ex,dict) and isinstance(ex.get("code"),str): used_codes.add(ex["code"])
unused=sorted(error_codes-used_codes)
check(not unused, "Every API_SPEC §10 error code is used by an operation example", ", ".join(unused))

# ElementStyle mirror file is exact except $schema/$id.
mirror=json.loads((ROOT/"ELEMENT_STYLE.schema.json").read_text())
mirror.pop("$schema",None); mirror.pop("$id",None)
check(mirror == schemas["ElementStyle"], "ELEMENT_STYLE.schema.json matches OpenAPI ElementStyle exactly")

# DATA_MODEL_EXAMPLES schema-tagged JSON examples.
text=(ROOT/"DATA_MODEL_EXAMPLES.md").read_text()
pat=re.compile(r"<!--\s*schema:\s*([A-Za-z0-9_]+)\s*-->\s*```json\s*(.*?)\s*```",re.S)
example_errors=[]; example_count=0
for name,body in pat.findall(text):
    example_count+=1
    try:
        obj=json.loads(body)
        errs=sorted(validator_for(name).iter_errors(obj), key=lambda e:list(e.path))
        if errs: example_errors.append(f"{name}: {errs[0].message}")
    except Exception as e: example_errors.append(f"{name}: {e}")
check(example_count > 0 and not example_errors, f"Schema-tagged data examples validate ({example_count})", example_errors[0] if example_errors else "")

# Targeted Element contract tests.
def is_valid(name,payload): return not list(validator_for(name).iter_errors(payload))
base_create={'page_id':1,'target_lang':'ar','element_type':'bubble','content':'x','x_unit':1,'y_unit':1,'w_unit':2,'h_unit':2}
checks=[
    (not is_valid('ElementPatch',{}), 'ElementPatch rejects empty object'),
    (not is_valid('ElementPatch',{'unknown':1}), 'ElementPatch rejects unknown property'),
    (not is_valid('ElementPatch',{'rotation_mdeg':360001}), 'ElementPatch enforces rotation maximum'),
    (not is_valid('ElementPatch',{'z_index':10001}), 'ElementPatch enforces z-index maximum'),
    (not is_valid('ElementPatch',{'style':{'shape':'ellipse'}}), 'Style patch requires element_type discriminator'),
    (not is_valid('ElementPatch',{'element_type':'bubble'}), 'element_type alone is not a valid patch'),
    (not is_valid('ElementPatch',{'element_type':'bubble','style':{'shape':'burst'}}), 'Bubble style rejects SFX burst shape'),
    (is_valid('ElementPatch',{'element_type':'sfx','style':{'shape':'burst','scaleX':1.2}}), 'SFX style accepts burst/scale'),
    (not is_valid('ElementCreate',{**base_create,'bad':1}), 'ElementCreate rejects unknown property'),
    (not is_valid('ElementCreate',{**base_create,'style':{'tail':{'enabled':True}},'element_type':'narration'}), 'Narration style rejects bubble tail'),
]
for cond,label in checks: check(cond,label)

# Specific response/route hardening checks.
def has_status(path,method,status): return str(status) in doc['paths'][path][method]['responses']
for path,method,status,label in [
    ('/chapters/{id}/pages','post',413,'Upload declares 413'),
    ('/elements/{id}/lock','post',404,'Lock acquire declares 404'),
    ('/elements/{id}/lock','put',404,'Lock renew declares 404'),
    ('/elements/{id}/lock','put',409,'Lock renew declares lock-lost 409'),
    ('/elements/{id}/lock','delete',404,'Lock release declares 404'),
    ('/elements/{id}/lock','delete',409,'Lock release declares lock-lost 409'),
    ('/works/{id}/chapters','get',404,'Work chapters declares missing work 404'),
    ('/presets/{id}','patch',404,'Preset patch declares 404'),
    ('/presets/{id}','delete',404,'Preset delete declares 404'),
    ('/reports/{id}','patch',404,'Report patch declares 404'),
    ('/chapters','post',409,'Chapter create declares slug conflict 409'),
]: check(has_status(path,method,status),label)

# Runtime capability contract + baseline upload types.
check('/capabilities' in doc['paths'], 'Runtime capabilities endpoint exists')
enc=doc['paths']['/chapters/{id}/pages']['post']['requestBody']['content']['multipart/form-data']['encoding']['image']
check(enc.get('contentType') == 'image/jpeg, image/png, image/webp', 'Upload baseline excludes unconditional AVIF')
check('image/avif' in enc.get('x-optional-runtime-content-types',[]), 'AVIF documented as optional runtime upload type')

print(f"PASS {len(passes)} / FAIL {len(fails)}")
for x in passes: print("PASS:",x)
for x in fails: print("FAIL:",x)
if fails: sys.exit(1)
