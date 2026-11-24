"""Verify native PostgreSQL primary-key slots, allocation and constraint timing."""

from copy import deepcopy
from itertools import product
import json
import os
from random import Random
import sys

from native_postgres_fixture import ENV as FIXTURE_CONTAINER
from postgres_create_btree_cases import refusal
from postgres_primary_key_cases import (
    FIXED_TYPES, FORMS, NAMES, NS, NULLS, PAYLOADS, SINGLE, STATUS, TARGET, TIMINGS, analyzed, direct,
    effective, expected, recipe, sql,
)
from postgres_reindex_cases import qualified
from postgres_schema_tables_cases import table
from run import Failure, TOOL, digest, require
from run_server import same
from run_server_join import main, negative_count
from run_server_postgres_create_schema import specifications as namespace_profiles
from sqlite_domain import RenderFault

LABEL = "postgres-primary-key"
NAME = "postgres-primary-key-evidence-v1.json"
CONTRACT = "4ac3f9884cfadd7e008806bc779e34cdcef27c79dd0f7d07cc1e3fdc32caf959"
BASE = "9b70aef7ee573717530bdacda3698adbc57c65c50afebc03a4637f99f81106be"
OBSERVER = "postgres_primary_key_observer.py"
AXES = ("keyForms", "timing", "names", "payload", "nullableInput")
SEMANTIC_EXCEPTION = "SqlSemantics\\Platform\\PostgreSql\\Model\\Diagnostic\\SemanticException"
NATIVE_NEGATIVES = {
    "duplicate-key": ('"a" INTEGER, PRIMARY KEY ("a", "a")', "42701", "A primary key cannot repeat a key column."),
    "missing-key": ('"a" INTEGER, PRIMARY KEY ("missing")', "42703", None),
    "missing-include": ('"a" INTEGER, PRIMARY KEY ("a") INCLUDE ("missing")', "42703", None),
    "multiple": ('"a" INTEGER PRIMARY KEY, "b" INTEGER PRIMARY KEY', "42P16", None),
    "null-conflict": ('"a" INTEGER NULL NOT NULL PRIMARY KEY', "42601", None),
    "timing-conflict": ('"a" INTEGER, PRIMARY KEY ("a") NOT DEFERRABLE INITIALLY DEFERRED', "42601", None),
    "same-relation": ('"a" INTEGER CONSTRAINT ir_pk_target PRIMARY KEY', "42P07", "A primary key index cannot have its table name."),
    "wide": ('"a" INTEGER, PRIMARY KEY ("a") INCLUDE (' + ", ".join('"a"' for _ in range(32)) + ')', "54011",
             "A primary key index can reference at most 32 attributes."),
}
OCCUPANCY = "A schema primary key index requires an unused relation name."


def specifications():
    file = TOOL / NAME
    require(digest(file) == CONTRACT and digest(TOOL / "postgres-create-primary-key-v1.json") == BASE,
            "postgres-primary-key-contract")
    spec = json.loads(file.read_text())
    same([], spec["exploration"]["allowedExclusions"])
    return spec, namespace_profiles()[1]


def grid(spec):
    return (point for point in product(*(spec["finite"][axis] for axis in AXES))
            if point[0] != "inline-single" or point[3] == "none")


def finite_ids(_, spec, _tag):
    independent = set(product(FORMS[1:], TIMINGS, NAMES, PAYLOADS, NULLS))
    independent |= set(product(FORMS[:1], TIMINGS, NAMES, PAYLOADS[:1], NULLS))
    require(set(grid(spec)) == independent and len(independent) == 84, "postgres-primary-key-grid")
    return independent


def analysis_finite_id(_, response, item, command):
    point = tuple(item["coordinate"])
    same(sql(recipe(item["release"], *point)), command)
    check_value(item, response)
    return point


def direct_finite_id(_, response, item, request):
    point = tuple(item["coordinate"])
    same(direct(recipe(item["release"], *point)), request)
    check_value(item, response)
    return point


def seeded(tag, seed, count):
    rng = Random(seed)
    for index in range(count):
        timing = list(TIMINGS)[index % len(TIMINGS)]
        item = recipe(tag, "table-forward", timing, NAMES[index % 2], "none", rng.choice(NULLS))
        width = 1 + index % 6
        kinds = [FIXED_TYPES[(index + slot) % len(FIXED_TYPES)] for slot in range(width)]
        names = [f"attr_{slot}" if rng.randrange(2) else f'Field "{slot}.棚"' for slot in range(width)]
        selected = rng.sample(names, 1 + rng.randrange(width))
        payload = rng.choices(names, k=rng.randrange(8)) if index % 3 != 0 else []
        fields = []
        for name, kind in zip(names, kinds):
            default = None
            if rng.randrange(3):
                default = {
                    "INTEGER": ["integer", str(rng.randrange(-20, 20))], "SMALLINT": ["integer", str(rng.randrange(-20, 20))],
                    "BIGINT": ["wide-integer", str(rng.randrange(4294967296, 4294967424))],
                    "pg_catalog.text": ["text", f"note {index}'"], "pg_catalog.bool": ["boolean", bool(index % 2)],
                }[kind]
            fields.append([name, kind, True, default])
        item["member"]["columns"] = fields
        item["member"]["primaryKey"].update(keys=selected, included=payload)
        if len(selected) == 1 and not payload:
            item["form"] = "inline-single"
        yield item


def simple(tag, namespace, name, guard=False):
    return table(tag, (namespace, name), [["prior", "INTEGER", True, None]], guard)


def edges(tag):
    base = lambda: recipe(tag, "table-reverse", "deferred", "automatic", "repeat-and-overlap", "explicit-null")
    item = base()
    item["prefix"] = [simple(tag, SINGLE, TARGET + "_pkey"), simple(tag, SINGLE, TARGET + "_pkey1")]
    yield "native-two-collisions", item
    item = base()
    item["member"]["target"][1] = "x" * 55 + "漢字"
    item["prefix"] = [simple(tag, SINGLE, "x" * 55 + "漢_pkey")]
    yield "truncated-multibyte-name", item
    item = base()
    item["member"]["primaryKey"]["included"] = ["unkeyed"] * 30
    yield "exact-32-attributes", item
    item = base()
    item["member"]["columns"][0][3] = ["null"]
    yield "explicit-null-key-default", item
    item = base()
    item["compose"] = True
    item["member"]["target"][0] = NS
    item["prefix"] = [simple(tag, NS, TARGET + "_pkey")]
    item["suffix"] = [simple(tag, NS, TARGET + "_pkey1", True)]
    yield "allocated-index-occupies-table-name", item
    item = base()
    item["compose"] = True
    item["member"]["target"][0] = NS
    item["member"]["primaryKey"]["name"] = "fixed_key"
    item["suffix"] = [simple(tag, NS, "fixed_key", True)]
    yield "explicit-index-occupies-table-name", item
    item = base()
    item.update(compose=True, skipProbe=True)
    item["member"].update(target=[NS, TARGET], ifNotExists=True)
    item["prefix"] = [simple(tag, NS, TARGET)]
    item["suffix"] = [simple(tag, NS, TARGET + "_pkey")]
    yield "skipped-table-contributes-no-index", item
    item = base()
    item["member"]["ifNotExists"] = True
    item["prefix"] = [simple(tag, SINGLE, TARGET)]
    item["skipProbe"] = True
    yield "standalone-existing-table", item


def refusals(tag):
    item = recipe(tag, "table-forward", "nondeferrable", "automatic", "separate", "implicit")
    for label, (columns, code, boundary) in NATIVE_NEGATIVES.items():
        selected = {**item, "command": f"CREATE TABLE {qualified(SINGLE, 'ir_pk_target')} ({columns})",
                    "nativeRefusal": code, "negative": label}
        yield analyzed(selected), selected, refusal(boundary) if boundary is not None else SEMANTIC_REFUSALS[label]
    item = deepcopy(item)
    item["compose"] = True
    item["member"]["target"][0] = NS
    item["member"]["primaryKey"]["name"] = "occupied_index"
    item["prefix"] = [simple(tag, NS, "occupied_index")]
    selected = {**item, "nativeRefusal": "42P07", "negative": "nested-occupied"}
    yield analyzed(selected), selected, refusal(OCCUPANCY)
    yield direct(item), None, refusal(OCCUPANCY)
    child = deepcopy(item)
    child["prefix"] = []
    child["suffix"] = [simple(tag, NS, "occupied_index")]
    selected = {**child, "nativeRefusal": "42P07", "negative": "nested-unguarded-child"}
    yield analyzed(selected), selected, refusal("A repeated schema table requires its own IF NOT EXISTS guard.")
    good = direct(recipe(tag, "table-forward", "nondeferrable", "automatic", "separate", "implicit"))
    for field, value, message in (
        ("keys", [], "A primary key requires ordered key columns."),
        ("keys", {"1": "left"}, "A primary key requires ordered key columns."),
        ("keys", [19], "A primary key requires ordered key columns."),
        ("keys", ["left", "left"], "A primary key cannot repeat a key column."),
        ("keys", ["absent"], "A primary key reference must select a declared column."),
        ("included", ["absent"], "A primary key reference must select a declared column."),
        ("included", ["unkeyed"] * 31, "A primary key index can reference at most 32 attributes."),
    ):
        invalid = deepcopy(good)
        invalid["create"]["primaryKey"][field] = value
        yield invalid, None, refusal(message)


SEMANTIC_REFUSALS = {
    "missing-key": {"exception": SEMANTIC_EXCEPTION, "message": 'column "missing" named in key does not exist'},
    "missing-include": {"exception": SEMANTIC_EXCEPTION, "message": 'column "missing" named in key does not exist'},
    "multiple": {"exception": SEMANTIC_EXCEPTION, "message": 'multiple primary keys for table "ir_pk_target" are not allowed'},
    "null-conflict": {"exception": SEMANTIC_EXCEPTION, "message": 'conflicting NULL/NOT NULL declarations for column "a"'},
    "timing-conflict": {"exception": SEMANTIC_EXCEPTION, "message": 'constraint declared INITIALLY DEFERRED must be DEFERRABLE'},
}


def requests(engine, tag, spec):
    for index, point in enumerate(grid(spec)):
        item = recipe(tag, *point, coordinate=list(point))
        yield f"finite/{index}", analyzed(item), item, None
        yield f"direct/{index}", direct(item), item, None
    for name, item in edges(tag):
        yield f"edge/{name}/analysis", analyzed(item), item, None
        yield f"edge/{name}/direct", direct(item), item, None
    same(spec["edgeNames"], [name for name, _ in edges(tag)])
    for seed in spec["exploration"]["seeds"]:
        for index, item in enumerate(seeded(tag, seed, spec["exploration"]["casesPerSeed"])):
            yield f"seed/{seed}/{index}", analyzed(item), item, None
            yield f"direct-seed/{seed}/{index}", direct(item), item, None
    invalid = list(refusals(tag))
    same(negative_count(spec, engine, tag), len(invalid))
    for index, (request, item, reason) in enumerate(invalid):
        yield f"negative/{index}", request, item, reason


def check_value(item, response):
    profile = item["release"] + "/utf8/standard-conforming-strings/native"
    require(response.get("status") == "constructed", "postgres-primary-key-accepted-domain", response)
    same(profile if item["compose"] else profile + "/heap", response["profile"])
    if item["compose"]:
        shape = direct(item)["createSchema"]
        same({key: shape[key] for key in ("name", "owner", "ifNotExists")}, response["createSchema"])
        same([effective(member) for member in shape["tables"]], response["schemaTables"])
        for field in ("schemaSequences", "schemaViews", "schemaIndexes", "schemaGrants"):
            same([], response[field])
    else:
        value = effective(item["member"])
        del value["release"]
        same(value, response["create"])


def observation(result):
    return result


def observer_request(item, command):
    return {"item": item, "sql": command}


def prepare(_, db):
    return db.request({"fixture": True})


def check_preparation(_, _environment, measured):
    same({"status": "fixture", "namespaces": [[SINGLE]]}, measured)


def check_observation(item, actual, command):
    model = expected(item)
    witness = {"expected": model, "observed": actual, "sql": command}
    require(actual.get("status") == STATUS, "postgres-primary-key-observer-scope", witness)
    require({key: actual[key] for key in model} == model, "meaning-mismatch", witness)
    identities = actual["physicalOids"]
    catalog = actual["relations"] or {}
    require(identities.keys() == catalog.keys() and len(set(identities.values())) == len(catalog)
            and all(type(oid) is int and oid > 0 for oid in identities.values()), "meaning-mismatch", witness)
    links = {name: [identities[entry[2]], identities[name], identities[entry[2]], identities[name], "p"]
             for name, entry in catalog.items() if entry[0] == "i"}
    require(actual["links"] == links, "meaning-mismatch", witness)
    error = actual["error"]
    require(error is None if model["sqlState"] == "00000" else error is not None and error[:2] == ["ERROR", model["sqlState"]],
            "meaning-mismatch", witness)


def observe_rejection(_, item, actual, command):
    same(sql(item), command)
    check_observation(item, actual, command)
    return item["negative"]


def rejected_finite_ids(_, _tag):
    return {*NATIVE_NEGATIVES, "nested-occupied", "nested-unguarded-child"}


def sample_forms(forms, response, _engine, _item):
    value = response["create"]
    key = value["primaryKey"]
    names = [field[0] for field in value["columns"]]
    forms[f"width/{len(names)}"] += 1
    forms[f"keyWidth/{'single' if len(key['keys']) == 1 else 'multiple'}"] += 1
    forms[f"payload/{bool(key['included'])}"] += 1
    forms[f"overlap/{bool(set(key['included']) & set(key['keys']))}"] += 1
    forms[f"repeated/{len(key['included']) != len(set(key['included']))}"] += 1
    forms[f"named/{key['name'] is not None}"] += 1
    forms[f"timing/{key['timing']}"] += 1
    for _, kind, _, default in value["columns"]:
        forms[f"storage/{kind}"] += 1
        forms[f"default/{None if default is None else default[0]}"] += 1


def required_forms(*_):
    return {*(f"width/{width}" for width in range(1, 7)), *(f"timing/{mode}" for mode in TIMINGS.values()),
            *(f"{key}/{value}" for key in ("payload", "overlap", "repeated", "named") for value in (False, True)),
            *(f"storage/{kind}" for kind in FIXED_TYPES), "keyWidth/single", "keyWidth/multiple",
            "default/None", "default/integer", "default/wide-integer", "default/text", "default/boolean"}


def controls():
    item = recipe("pg-17.2", "table-reverse", "deferred", "quoted-explicit", "repeat-and-overlap", "implicit")
    prediction = expected(item)
    identity = {key: index + 12000 for index, key in enumerate(prediction["relations"])}
    table_name = item["member"]["target"][1]
    index = item["member"]["primaryKey"]["name"]
    bound = [identity[table_name], identity[index], identity[table_name], identity[index], "p"]
    healthy = {**prediction, "physicalOids": identity, "links": {index: bound}, "error": None}
    check_observation(item, healthy, sql(item))
    results = {}
    for label, key in (("ordering", "relations"), ("physical-index", "links"), ("deferred-enforcement", "witnesses")):
        changed = deepcopy(healthy)
        if key == "relations":
            changed[key][index][5].reverse()
        elif key == "links":
            changed[key][index][3] = identity[table_name]
        else:
            changed[key]["duplicate"] = ["23505", None]
        try:
            check_observation(item, changed, sql(item))
        except Failure as error:
            same("meaning-mismatch", error.signal)
            results[label] = error.detail
            continue
        raise Failure("postgres-primary-key-ignored-observation", label)
    return results


def check_fault_witness(item, witness):
    same(expected(item), witness["expected"])
    same(STATUS, witness["observed"]["status"])
    require(witness["observed"]["sqlState"] == "00000" and witness["observed"]["relations"],
            "postgres-primary-key-fault-must-execute", witness)


def faults(_, tag):
    key = "sql-semantics-postgres/src/Ir/Schema/Key/PrimaryKey.php"
    parent = "sql-semantics-postgres/src/Ir/Schema/CreateTable.php"
    trial = recipe(tag, "table-forward", "deferred", "quoted-explicit", "separate", "implicit")
    mutations = (
        ("reverse-primary-key", key, "implode(', ', $keys)", "implode(', ', array_reverse($keys))"),
        ("drop-primary-key-payload", key, "$keys) . ')' . $payload", "$keys) . ')'"),
        ("erase-primary-key-timing", key, "$this->timing->value;", "'NOT DEFERRABLE';"),
        ("drop-primary-key", parent, "$columns[] = $this->primaryKey->toString();", "$columns = array_values($columns);"),
        ("wrong-primary-key-slot", key, "$keys[] = $key->quoted();", """$keys[] = $keys === [] ? '"unkeyed"' : $key->quoted();"""),
    )
    return [RenderFault(name, path, anchor, replacement, trial, "meaning-mismatch", direct(trial))
            for name, path, anchor, replacement in mutations]


if __name__ == "__main__":
    require(FIXTURE_CONTAINER in os.environ, "postgres-primary-key-disposable-service-required")
    main(sys.modules[__name__])
