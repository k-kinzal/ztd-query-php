"""Compare PostgreSQL unique-key normalization, physical indexes and write effects."""

from copy import deepcopy
from itertools import product
import json
import os
from random import Random
import sys

from native_postgres_fixture import ENV as FIXTURE_CONTAINER
from postgres_create_btree_cases import published, refusal
from postgres_primary_key_cases import FIXED_TYPES, TIMINGS
from postgres_reindex_cases import qualified
from postgres_unique_key_cases import (
    EQUALITY, KINDS, NAMES, NS, PAYLOADS, SINGLE, STATUS, TARGET, analyzed, direct, expected, key, recipe, resolve, sql,
)
from postgres_schema_tables_cases import table
from run import Failure, TOOL, digest, require
from run_server import same
from run_server_join import main, negative_count
from run_server_postgres_create_schema import specifications as namespace_profiles
from sqlite_domain import RenderFault

LABEL = "postgres-unique-key"
NAME = "postgres-unique-key-evidence-v2.json"
CONTRACT = "155537d5813e188ae121f5af111619db9098f27913906fa53186466f7e80e4ed"
BASE = "e7cd49f3bfd471204d7d67729a8e6aad1097e46e9b28a8ddb7cd01604a1c2195"
OBSERVER = "postgres_unique_key_observer.py"
AXES = ("kind", "nullableInput", "nullEquality", "timing", "naming", "payload")
NATIVE_BAD = (
    ("duplicate-key", "a INTEGER, UNIQUE (a,a)", "42701", refusal("A unique key cannot repeat a key column.")),
    ("missing-key", "a INTEGER, UNIQUE (b)", "42703", refusal('column "b" named in key does not exist', "SemanticException")),
    ("missing-payload", "a INTEGER, UNIQUE (a) INCLUDE (b)", "42703", refusal('column "b" named in key does not exist', "SemanticException")),
    ("conflicting-timing", "a INTEGER, UNIQUE (a) NOT DEFERRABLE INITIALLY DEFERRED", "42601",
     refusal("constraint declared INITIALLY DEFERRED must be DEFERRABLE", "SemanticException")),
    ("repeated-index-name", "a INTEGER CONSTRAINT occupied UNIQUE,b INTEGER CONSTRAINT occupied UNIQUE", "42P07",
     refusal("Retained table keys must use unused explicit index names.")),
    ("same-name-as-table", "a INTEGER CONSTRAINT ir_uk_target UNIQUE", "42P07", refusal("Retained table keys must use unused explicit index names.")),
    ("width-exceeded", 'a INTEGER, UNIQUE (a) INCLUDE (' + ", ".join("a" for _ in range(32)) + ")", "54011",
     refusal("A unique index can reference at most 32 attributes.")),
)


def specifications():
    path = TOOL / NAME
    require(digest(path) == CONTRACT and digest(TOOL / "postgres-create-unique-keys-v1.json") == BASE,
            "postgres-unique-key-contract")
    record = json.loads(path.read_text())
    same([], record["exploration"]["allowedExclusions"])
    return record, namespace_profiles()[1]


def grid(spec):
    return (point for point in product(*(spec["finite"][axis] for axis in AXES)) if point[0] != "inline" or point[-1] == "none")


def finite_ids(_, spec, _tag):
    expected_points = set(product(KINDS[1:], (True, False), EQUALITY, TIMINGS, NAMES, PAYLOADS))
    expected_points |= set(product(KINDS[:1], (True, False), EQUALITY, TIMINGS, NAMES, PAYLOADS[:1]))
    require(expected_points == set(grid(spec)) and len(expected_points) == 120, "postgres-unique-key-finite-grid")
    return expected_points


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


def alternate(tag, *, namespace=SINGLE, name=TARGET, guard=False):
    return table(tag, (namespace, name), [["original", "INTEGER", True, None]], guard)


def base(tag):
    return recipe(tag, "ordered", True, "distinct", "nondeferrable", "automatic", "none")


def composed(item):
    item["compose"] = True
    item["member"]["target"][0] = NS
    return item


def edges(tag):
    item = base(tag)
    item["member"]["uniqueKeys"] = [key(["a"]), key(["a"]), key(["b"])]
    yield "unique merge", item

    item = base(tag)
    item["member"]["primaryKey"] = {"keys": ["a", "b"], "included": [], "name": None, "timing": TIMINGS["nondeferrable"]}
    yield "primary merge", item

    item = base(tag)
    item["member"]["primaryKey"] = {"keys": ["a", "b"], "included": [], "name": None, "timing": TIMINGS["nondeferrable"]}
    item["member"]["uniqueKeys"][0]["name"] = "transferred"
    yield "transfer name", item

    item = base(tag)
    item["member"]["uniqueKeys"] = [key(["a"], name="firstname"), key(["a"], name="latername")]
    item["faultModes"] = ["last-name"]
    yield "retain first explicit name", item

    item = base(tag)
    item["member"]["uniqueKeys"] += [key(["b", "a"])]
    yield "separate order", item

    item = base(tag)
    item["member"]["uniqueKeys"] += [key(["a", "b"], nulls_equal=True)]
    item["faultModes"] = ["merge-policy"]
    yield "separate NULL policy", item

    item = base(tag)
    item["member"]["uniqueKeys"] += [key(["a", "b"], timing="deferred"), key(["a", "b"], timing="immediate")]
    yield "separate timing", item

    item = base(tag)
    item["member"]["uniqueKeys"] += [key(["a", "b"], ["extra", "a", "extra"])]
    yield "separate payload", item

    item = base(tag)
    item["member"]["columns"] = [[name, "INTEGER", True, None] for name in ("a_b", "c", "a", "b_c")]
    item["member"]["uniqueKeys"] = [key(["a_b", "c"]), key(["a", "b_c"])]
    item["prefix"] = [alternate(tag, name=TARGET + "_a_b_c_key")]
    item["faultModes"] = ["order"]
    yield "same generated candidate", item

    item = base(tag)
    item["member"]["columns"] = [["c1", "BIGINT", True, None], ["c", "INTEGER", True, None], ["d", "pg_catalog.bool", True, None]]
    item["member"]["uniqueKeys"] = [key(["c"], ["c1", "c", "c", "d"])]
    yield "index column label suffix", item

    item = composed(base(tag))
    name = "𐐷" * 14
    item["member"]["target"][1] = name
    item["member"]["columns"] = [[name, "INTEGER", True, None]]
    item["member"]["uniqueKeys"] = [key([name])]
    item["prefix"] = [alternate(tag, namespace=NS, name="𐐷" * 7 + "_" + "𐐷" * 7 + "_key")]
    yield "UTF-8 allocation", item

    item = composed(base(tag))
    item["suffix"] = [alternate(tag, namespace=NS, name=TARGET + "_a_b_key", guard=True)]
    yield "guarded namespace shadow", item

    item = composed(base(tag))
    item["member"]["ifNotExists"] = True
    item["prefix"] = [alternate(tag, namespace=NS)]
    item["suffix"] = [alternate(tag, namespace=NS, name=TARGET + "_a_b_key")]
    item["skipProbe"] = True
    yield "skipped namespace table", item

    item = composed(base(tag))
    item["member"]["columns"] = [["a", "INTEGER", True, None], ["b", "BIGINT", True, None]]
    item["indexes"] = [{"release": tag, "table": [NS, TARGET, ["a", "b"], ["int4", "int8"], False],
                       "name": TARGET + "_a_b_key", "keys": [[1, "DESC NULLS FIRST"]], "included": [],
                       "presence": "occupied", "ifNotExists": True, "unique": False, "nullsNotDistinct": False,
                       "concurrently": False, "predicate": None}]
    yield "late index occupancy", item


def generated(tag, seed, count):
    rng = Random(seed)
    for index in range(count):
        item = base(tag)
        width = 2 + index % 5
        names = [f'f" {n}.鍵' if rng.randrange(2) else f"f_{n}" for n in range(width)]
        fields = []
        for pos, name in enumerate(names):
            kind = FIXED_TYPES[(index + pos) % len(FIXED_TYPES)]
            default = None if (pos + index) % 3 == 0 else {
                "SMALLINT": ["integer", "6"], "INTEGER": ["integer", "-6"], "BIGINT": ["wide-integer", "4000000003"],
                "pg_catalog.bool": ["boolean", True], "pg_catalog.text": ["text", "candidate's"],
            }[kind]
            fields.append([name, kind, bool(rng.randrange(2)), default])
        member = item["member"]
        member["columns"] = fields
        member["uniqueKeys"] = []
        for i in range(1 + index % 4):
            accepted = rng.sample(names, 1 + rng.randrange(width))
            included = rng.choices(names, k=rng.randrange(5))
            if i == 0 and index % 5 == 0:
                included = [accepted[0], accepted[0]]
            member["uniqueKeys"].append(key(accepted, included, f"uq {i}" if (i + index) % 2 else None,
                                            list(TIMINGS)[(i + index) % 3], bool((i + index) % 2)))
        if index % 2 == 0:
            selected = member["uniqueKeys"][0]
            member["primaryKey"] = {name: value for name, value in selected.items() if name != "nullsNotDistinct"}
        if index % 3 == 0:
            member["uniqueKeys"].append(deepcopy(member["uniqueKeys"][0]))
        yield item


def refusals(tag):
    for label, columns, code, boundary in NATIVE_BAD:
        item = {**base(tag), "command": f"CREATE TABLE {qualified(SINGLE, TARGET)} ({columns})",
                "nativeRefusal": code, "negative": label}
        yield analyzed(item), item, boundary
    item = composed(base(tag))
    item["member"]["uniqueKeys"][0]["name"] = "occupied"
    item["prefix"] = [alternate(tag, namespace=NS, name="occupied")]
    selected = {**item, "nativeRefusal": "42P07", "negative": "earlier-occupancy"}
    yield analyzed(selected), selected, refusal("A schema unique key index requires an unused relation name.")
    yield direct(item), None, refusal("A schema unique key index requires an unused relation name.")
    item["prefix"] = []
    item["suffix"] = [alternate(tag, namespace=NS, name="occupied")]
    selected = {**item, "nativeRefusal": "42P07", "negative": "later-unguarded"}
    yield analyzed(selected), selected, refusal("A repeated schema table requires its own IF NOT EXISTS guard.")
    valid = direct(base(tag))
    for field, value, message in (
        ("keys", [], "A unique key requires ordered key columns."),
        ("keys", {"1": "a"}, "A unique key requires ordered key columns."),
        ("keys", [3], "A unique key requires ordered key columns."),
        ("keys", ["a", "a"], "A unique key cannot repeat a key column."),
        ("keys", ["missing"], "A unique key reference must select a declared column."),
        ("included", ["missing"], "A unique key reference must select a declared column."),
        ("included", ["b"] * 31, "A unique index can reference at most 32 attributes."),
    ):
        invalid = deepcopy(valid)
        invalid["create"]["uniqueKeys"][0][field] = value
        yield invalid, None, refusal(message)
    unsupported = base(tag)
    unsupported["command"] = f"CREATE TABLE {qualified(SINGLE, TARGET)} (a INTEGER UNIQUE WITH (fillfactor=50))"
    yield analyzed(unsupported), None, refusal("A table key requires default index parameters.")


def requests(engine, tag, spec):
    for number, point in enumerate(grid(spec)):
        item = recipe(tag, *point, coordinate=list(point))
        yield f"finite/{number}", analyzed(item), item, None
        yield f"direct/{number}", direct(item), item, None
    planned = list(edges(tag))
    same(spec["finite"]["compositions"], [name for name, _ in planned])
    for number, (_name, item) in enumerate(planned):
        yield f"composition/{number}/analysis", analyzed(item), item, None
        yield f"composition/{number}/direct", direct(item), item, None
    for seed in spec["exploration"]["seeds"]:
        for trial, item in enumerate(generated(tag, seed, spec["exploration"]["casesPerSeed"])):
            yield f"seed/{seed}/{trial}", analyzed(item), item, None
            yield f"direct-seed/{seed}/{trial}", direct(item), item, None
    invalid = list(refusals(tag))
    same(negative_count(spec, engine, tag), len(invalid))
    for index, (request, item, error) in enumerate(invalid):
        yield f"negative/{index}", request, item, error


def check_value(item, response):
    require(response.get("status") == "constructed", "postgres-unique-key-admission", response)
    profile = item["release"] + "/utf8/standard-conforming-strings/native"
    same(profile if item["compose"] else profile + "/heap", response["profile"])
    if item["compose"]:
        header = direct(item)["createSchema"]
        same({key: header[key] for key in ("name", "owner", "ifNotExists")}, response["createSchema"])
        same([resolve(member) for member in header["tables"]], response["schemaTables"])
        same([published({key: value for key, value in index.items() if key != "release"})
              for index in header["indexes"]], response["schemaIndexes"])
        for role in ("schemaSequences", "schemaViews", "schemaGrants"):
            same([], response[role])
    else:
        expected_value = resolve(item["member"])
        del expected_value["release"]
        same(expected_value, response["create"])


def observation(result):
    return result


def observer_request(item, command):
    return {"item": item, "sql": command}


def prepare(_, db):
    return db.request({"fixture": True})


def check_preparation(_, _env, preparation):
    same({"status": "fixture", "namespaces": [[SINGLE]]}, preparation)


def check_observation(item, actual, command):
    model = expected(item)
    witness = {"expected": model, "observed": actual, "sql": command}
    require(actual.get("status") == STATUS, "postgres-unique-key-observer-scope", witness)
    require({key: actual[key] for key in model} == model, "meaning-mismatch", witness)
    rels = actual["relations"] or {}
    oids = actual["physicalOids"]
    require(oids.keys() == rels.keys() and len(set(oids.values())) == len(rels)
            and all(type(oid) is int and oid > 0 for oid in oids.values()), "meaning-mismatch", witness)
    required = {name: [oids[fields[2]], oids[name], oids[fields[2]], oids[name], fields[15]]
                for name, fields in rels.items() if fields[0] == "i"}
    require(actual["links"] == required, "meaning-mismatch", witness)
    fault = actual["error"]
    require(fault is None if model["sqlState"] == "00000" else fault is not None and fault[:2] == ["ERROR", model["sqlState"]],
            "meaning-mismatch", witness)


def observe_rejection(_, item, actual, command):
    same(sql(item), command)
    check_observation(item, actual, command)
    return item["negative"]


def rejected_finite_ids(_, _tag):
    return {*(label for label, *_ in NATIVE_BAD), "earlier-occupancy", "later-unguarded"}


def sample_forms(forms, response, _engine, item):
    member = response["create"]
    forms[f"width/{len(member['columns'])}"] += 1
    forms[f"primary/{'primaryKey' in member}"] += 1
    keys = member.get("uniqueKeys", [])
    forms[f"multiple/{len(keys) > 1}"] += 1
    forms[f"merged/{len(keys) < len(item['member']['uniqueKeys'])}"] += 1
    for value in keys:
        forms[f"timing/{value['timing']}"] += 1
        forms[f"null-equality/{value['nullsNotDistinct']}"] += 1
        forms[f"explicit/{value['name'] is not None}"] += 1
        forms[f"repeat-payload/{len(value['included']) != len(set(value['included']))}"] += 1
        forms[f"overlap/{bool(set(value['keys']) & set(value['included']))}"] += 1
    for _, kind, _, _ in member["columns"]:
        forms[f"kind/{kind}"] += 1


def required_forms(*_):
    axes = ("primary", "multiple", "merged", "null-equality", "explicit", "repeat-payload", "overlap")
    return {*(f"width/{i}" for i in range(2, 7)), *(f"timing/{value}" for value in TIMINGS.values()),
            *(f"{key}/{value}" for key in axes for value in (False, True)), *(f"kind/{kind}" for kind in FIXED_TYPES)}


def controls():
    trial = recipe("pg-17.2", "ordered", True, "equal", "deferred", "explicit", "twice-overlap")
    baseline = expected(trial)
    values = baseline["relations"]
    identities = {name: i + 18000 for i, name in enumerate(values)}
    index = trial["member"]["uniqueKeys"][0]["name"]
    links = {index: [identities[TARGET], identities[index], identities[TARGET], identities[index], "u"]}
    healthy = {**baseline, "physicalOids": identities, "links": links, "error": None}
    check_observation(trial, healthy, sql(trial))
    controls = {}
    for label in ("included-name", "null-equality", "physical-owner"):
        variant = deepcopy(healthy)
        if label == "included-name":
            variant["relations"][index][14].reverse()
        elif label == "null-equality":
            variant["relations"][index][13] = False
        else:
            variant["links"][index][2] = identities[index]
        try:
            check_observation(trial, variant, sql(trial))
        except Failure as error:
            same("meaning-mismatch", error.signal)
            controls[label] = error.detail
            continue
        raise Failure("postgres-unique-key-ignored-observation", label)
    return controls


def fault_probe(item, response, db):
    actual = db.request(observer_request(item, response["sql"]))
    check_observation(item, actual, response["sql"])
    return actual


def check_fault_witness(item, witness):
    same(expected(item), witness["expected"])
    same(STATUS, witness["observed"]["status"])
    require(witness["observed"]["sqlState"] == "00000" and witness["observed"]["relations"],
            "postgres-unique-key-fault-must-execute", witness)


def faults(_, tag):
    key_path = "sql-semantics-postgres/src/Ir/Schema/Key/UniqueKey.php"
    table_path = "sql-semantics-postgres/src/Ir/Schema/CreateTable.php"
    compose_path = "sql-semantics-postgres/src/Ir/Schema/Key/TableKeySet.php"
    finite = recipe(tag, "ordered", True, "equal", "deferred", "explicit", "twice-overlap")
    computed = dict(edges(tag))
    entries = (
        ("null-policy", key_path, "'UNIQUE NULLS NOT DISTINCT ('", "'UNIQUE NULLS DISTINCT ('", finite),
        ("omit", table_path, "$columns[] = $unique->toString();", "$columns = array_values($columns);", finite),
        ("include", key_path, "$keys) . ')' . $payload", "$keys) . ')'", finite),
        ("order", table_path, "foreach ($this->uniqueKeys as $unique)", "foreach (array_reverse($this->uniqueKeys) as $unique)",
         computed["same generated candidate"]),
        ("merge-policy", compose_path, "$priorNullsEqual === $candidate->nullsNotDistinct && $prior->timing === $candidate->timing",
         "true", computed["separate NULL policy"]),
        ("last-name", compose_path, "$prior->name === null && $candidate->name !== null", "$candidate->name !== null",
         computed["retain first explicit name"]),
    )
    return [RenderFault(f"unique-{mode}", path, before, after, {**item, "faultModes": [mode]}, "meaning-mismatch", direct(item))
            for mode, path, before, after, item in entries]


if __name__ == "__main__":
    require(FIXTURE_CONTAINER in os.environ, "postgres-unique-key-disposable-service-required")
    main(sys.modules[__name__])
