"""Compare PostgreSQL namespace sequence and table phases against native effects."""

from copy import deepcopy
from itertools import product
import json
import os
from random import Random
import sys

from native_postgres_fixture import ENV as FIXTURE_CONTAINER
from postgres_create_btree_cases import refusal
from postgres_create_sequence_cases import POLICIES, TYPES, trial as sequence_trial
from postgres_schema_sequences_cases import DECOY, LAYOUTS, STATUS, analyzed, direct, expected, sequence_shape, sql, trial
from run import Failure, TOOL, digest, require
from run_server import same
from run_server_join import main
from run_server_postgres_create_schema import check_preparation, observation, observer_request, prepare
from run_server_postgres_create_schema import specifications as namespace_profiles
from sqlite_domain import RenderFault

LABEL = "postgres-schema-sequences"
CONTRACT_NAME = "postgres-schema-sequences-v3.json"
CONTRACT = "fc5e99d2e791b630c01135ac7c8344a2418df57e9a13a2be37d6f1af276eca5c"
OBSERVER = "postgres_schema_sequences_observer.py"
AXES = ("authorization", "writtenOrder", "layouts", "sequenceStorage", "transactional")
OWNERS = ("effective", "named")
ORDERS = ("sequence-first", "table-first")
STORAGES = ("permanent", "unlogged")
CHILD = "A schema sequence must belong to its namespace and release."
DETACHED = "Schema tables and sequences require an unguarded, statically named namespace."


def specifications():
    path = TOOL / CONTRACT_NAME
    require(digest(path) == CONTRACT, "postgres-schema-sequences-contract")
    spec = json.loads(path.read_bytes())
    same([], spec["exploration"]["allowedExclusions"])
    return spec, namespace_profiles()[1]


def grid(spec):
    return product(*(spec["finite"][axis] for axis in AXES))


def finite_ids(_, spec, _tag):
    independent = set(product(OWNERS, ORDERS, LAYOUTS, STORAGES, (True, False)))
    require(set(grid(spec)) == independent and len(independent) == 48, "postgres-schema-sequence-grid")
    return independent


def analysis_finite_id(_, response, item, command):
    key = tuple(item["coordinate"])
    same(sql(trial(item["release"], *key)), command)
    check_value(item, response)
    return key


def direct_finite_id(_, response, item, request):
    key = tuple(item["coordinate"])
    same(direct(trial(item["release"], *key)), request)
    check_value(item, response)
    return key


def generated(tag, seed, count):
    rng = Random(seed)
    layouts, types = list(LAYOUTS), list(TYPES)
    rng.shuffle(layouts)
    rng.shuffle(types)
    for index in range(count):
        layout = layouts[index % len(layouts)]
        ownership = rng.choice(OWNERS)
        yield trial(tag, ownership, rng.choice(ORDERS), layout, rng.choice(STORAGES), bool(rng.getrandbits(1)),
                    width=max(2 if layout == "repeated-sequence" else 1, index % 5 + 1),
                    base=rng.randrange(-47, 89), kind=types[(index // 2) % len(types)], direction=rng.choice((-1, 1)),
                    quoted_name=bool(rng.getrandbits(1)), derived=ownership == "named" and bool(rng.getrandbits(1)),
                    explicit=bool(rng.getrandbits(1)), policy=rng.choice(POLICIES))


def edges(tag, spec):
    earlier = trial(tag, "effective", "sequence-first", "independent", "permanent", False, width=1, kind="bigint")
    child = sequence_trial(tag, "bigint", 1, "default", "permanent")
    earlier.update(namespace="making", explicit="making", command="CREATE SCHEMA making CREATE SEQUENCE feed",
                   sequences=[{**child, "target": ["making", "feed"]}], members=[])
    yield "former-sequence-member-refusal", earlier
    promoted = trial(tag, "effective", "sequence-first", "independent", "permanent", False, width=1, kind="bigint")
    promoted.update(namespace="owning_schema", explicit="owning_schema", command=spec["requiredFormerRefusal"]["sql"],
                    sequences=[{**child, "target": ["owning_schema", "unimplemented_member"]}], members=[])
    same("CREATE SCHEMA owning_schema CREATE SEQUENCE unimplemented_member", sql(promoted))
    yield "former-standalone-schema-refusal", promoted
    yield "quoted-derived-owner", trial(tag, "named", "table-first", "independent", "unlogged", True, quoted_name=True, derived=True)
    yield "guarded-table-written-first", trial(tag, "named", "table-first", "shared-name", "permanent", True, explicit=True)
    yield "occupied-containing-namespace", trial(tag, "effective", "sequence-first", "independent", "unlogged", True, occupied=True)


def refusals(tag):
    item = trial(tag, "effective", "table-first", "independent", "permanent", True)
    boundary = [
        ("member-schema", "CREATE SCHEMA ir_ns_sequence_subject CREATE SEQUENCE public.ticket", "42P15",
         refusal("CREATE specifies a schema (public) different from the one being created (ir_ns_sequence_subject)", "SemanticException")),
        ("temporary-member", "CREATE SCHEMA ir_ns_sequence_subject CREATE TEMP SEQUENCE ticket", "42P16", refusal(CHILD)),
        ("unguarded-repeated-sequence", "CREATE SCHEMA ir_ns_sequence_subject CREATE SEQUENCE ticket CREATE SEQUENCE ticket",
         "42P07", refusal("A repeated schema sequence requires its own IF NOT EXISTS guard.")),
        ("unguarded-shared-name", "CREATE SCHEMA ir_ns_sequence_subject CREATE TABLE ticket (n integer) CREATE SEQUENCE ticket",
         "42P07", refusal("A repeated schema table requires its own IF NOT EXISTS guard.")),
        ("dynamic-namespace", "CREATE SCHEMA AUTHORIZATION CURRENT_USER CREATE SEQUENCE ticket", None, refusal(DETACHED)),
        ("guarded-parent", "CREATE SCHEMA IF NOT EXISTS ir_ns_sequence_subject CREATE SEQUENCE ticket", None,
         refusal("CREATE SCHEMA IF NOT EXISTS cannot include schema elements", "SemanticException")),
        ("temporary-view-member", "CREATE SCHEMA ir_ns_sequence_subject CREATE TEMP VIEW feed AS SELECT 1", "42P16",
         refusal("CREATE VIEW requires a persistent ordinary view in an absolute schema.")),
    ]
    for label, command, native, rejected in boundary:
        chosen = {**item, "command": command, "nativeRefusal": native, "negative": label}
        yield analyzed(chosen), chosen if native else None, rejected
    request = direct(item)
    request["createSchema"]["sequences"][0]["release"] = "pg-17.2" if tag == "pg-16.6" else "pg-16.6"
    yield request, None, refusal(CHILD)


def requests(_, tag, spec):
    for ordinal, key in enumerate(grid(spec)):
        item = trial(tag, *key, coordinate=list(key))
        yield f"finite/{ordinal}", analyzed(item), item, None
        yield f"direct/{ordinal}", direct(item), item, None
    for label, item in edges(tag, spec):
        yield f"edge/{label}", analyzed(item), item, None
    settings = spec["exploration"]
    for seed in settings["seeds"]:
        for index, item in enumerate(generated(tag, seed, settings["casesPerSeed"])):
            yield f"seed/{seed}/{index}", analyzed(item), item, None
            yield f"direct-seed/{seed}/{index}", direct(item), item, None
    for index, (request, item, rejected) in enumerate(refusals(tag)):
        yield f"negative/{index}", request, item, rejected


def check_value(item, response):
    same([], response.get("schemaIndexes"))
    same([], response.get("schemaViews"))
    body = direct(item)["createSchema"]
    header = {key: body[key] for key in ("owner", "ifNotExists")}
    header["name"] = item["namespace"]
    children = [sequence_shape(value, True) for value in item["sequences"]]
    same(["constructed", item["release"] + "/utf8/standard-conforming-strings/native", header, item["members"], children],
         [response.get("status"), response.get("profile"), response.get("createSchema"),
          response.get("schemaTables"), response.get("schemaSequences")])


def measurement(state, witness):
    if state is None:
        return None
    sequences = state["sequences"]
    ids = [value["oid"] for value in sequences.values()]
    require(len(set(ids)) == len(ids) and all(type(value) is int and value > 0 for value in ids), "meaning-mismatch", witness)
    return {**state, "sequences": {name: {key: v for key, v in obj.items() if key != "oid"}
                                  for name, obj in sequences.items()}}


def check_observation(item, actual, command):
    model = expected(item)
    witness = {"expected": model, "observed": actual, "sql": command}
    require(actual.get("status") == STATUS, "schema-sequence-observer-scope", witness)
    measured = {key: measurement(actual[key], witness) if key in ("before", "during", "settled") else actual[key] for key in model}
    require(measured == model, "meaning-mismatch", witness)
    err = actual["error"]
    require(err is None if model["sqlState"] == "00000"
            else err is not None and err[:2] == ["ERROR", model["sqlState"]], "meaning-mismatch", witness)


def observe_rejection(_, item, actual, command):
    same(item["command"], command)
    check_observation(item, actual, command)
    return item["negative"]


def rejected_finite_ids(_, _tag):
    return {"member-schema", "temporary-member", "unguarded-repeated-sequence", "unguarded-shared-name", "temporary-view-member"}


def sample_forms(forms, response, _engine, item):
    for field in ("authorization", "order", "layout", "storage", "qualified", "active", "quoted"):
        forms[f"{field}/{item[field]}"] += 1
    forms[f"width/{len(response['schemaSequences'])}"] += 1
    for sequence in response["schemaSequences"]:
        forms["type/" + sequence["storage"].lower()] += 1
        forms["sign/" + ("negative" if sequence["increment"].startswith("-") else "positive")] += 1


def required_forms(*_):
    return {*(f"authorization/{value}" for value in OWNERS), *(f"order/{value}" for value in ORDERS),
            *(f"layout/{value}" for value in LAYOUTS), *(f"storage/{value}" for value in STORAGES),
            *(f"type/{value}" for value in TYPES), "sign/negative", "sign/positive", "width/1", "width/5",
            *(f"{name}/{value}" for name in ("qualified", "active", "quoted") for value in (True, False))}


def controls():
    item = trial("pg-17.2", "named", "table-first", "shared-name", "unlogged", True)
    good = deepcopy({**expected(item), "error": None})
    for i, entry in enumerate(good["during"]["sequences"].values(), 91):
        entry["oid"] = i
    check_observation(item, good, sql(item))
    broken = deepcopy(good)
    broken["during"]["sequences"]["ticket"]["start"] += 1
    try:
        check_observation(item, broken, sql(item))
    except Failure as failure:
        same("meaning-mismatch", failure.signal)
        return {"altered-catalog-start": failure.detail}
    raise Failure("schema-sequence-comparison-ignored-start")


def check_fault_witness(item, witness):
    same(expected(item), witness["expected"])
    same(STATUS, witness["observed"]["status"])


def faults(_, tag):
    path = "sql-semantics-postgres/src/Ir/Schema/Namespace/CreateSchema.php"
    once = trial(tag, "named", "sequence-first", "independent", "permanent", True)
    repeated = trial(tag, "effective", "table-first", "repeated-sequence", "unlogged", False)
    return [
        RenderFault("schema-sequences-erase-phase", path, "foreach ($this->sequences as $sequence)",
                    "foreach ([] as $sequence)", once, "meaning-mismatch", direct(once)),
        RenderFault("schema-sequences-erase-guard", path, "$sequence->toString()",
                    "str_replace('SEQUENCE IF NOT EXISTS ', 'SEQUENCE ', $sequence->toString())",
                    repeated, "meaning-mismatch", direct(repeated)),
        RenderFault("schema-sequences-place-in-decoy", path, "$sequence->toString()",
                    "str_replace($this->name->quoted() . '.', '\"" + DECOY + "\".', $sequence->toString())",
                    once, "meaning-mismatch", direct(once)),
    ]


if __name__ == "__main__":
    require(FIXTURE_CONTAINER in os.environ, "postgres-schema-sequences-disposable-service-required")
    main(sys.modules[__name__])
