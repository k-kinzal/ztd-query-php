"""Verify ordered namespace indexes using native catalog identities and write effects."""

from copy import deepcopy
from itertools import product
import json
import os
from random import Random
import sys

from native_postgres_fixture import ENV as FIXTURE_CONTAINER
from postgres_create_btree_cases import published, refusal
from postgres_schema_indexes_cases import (
    DECOY, INDEX_ORDERS, LAYOUTS, NS, ORDERS, OWNERS, PREDICATES, STATUS, analyzed, direct, expected, generator, sql, tables, trial, view,
)
from postgres_schema_sequences_cases import sequence_shape
from run import Failure, TOOL, digest, require
from run_server import same
from run_server_join import main, negative_count
from run_server_postgres_create_schema import check_preparation, observation, prepare
from run_server_postgres_create_schema import specifications as namespace_profiles
from run_server_postgres_create_view import graph
from sqlite_domain import RenderFault

LABEL = "postgres-schema-indexes"
CONTRACT_NAME = "postgres-schema-indexes-v2.json"
CONTRACT = "60aba853804ce6d7ec72131075382de14aa0bee42cdcf6eba170d7d8ab18ce7a"
OBSERVER = "postgres_schema_indexes_observer.py"
AXES = ("owners", "layouts", "writtenOrders", "transactional")


def specifications():
    path = TOOL / CONTRACT_NAME
    require(digest(path) == CONTRACT, "postgres-schema-indexes-contract")
    spec = json.loads(path.read_text())
    same([], spec["exploration"]["allowedExclusions"])
    return spec, namespace_profiles()[1]


def grid(spec):
    return product(*(spec["finite"][axis] for axis in AXES))


def finite_ids(_, spec, _tag):
    independent = set(product(OWNERS, LAYOUTS, ORDERS, (False, True)))
    require(set(grid(spec)) == independent and len(independent) == 40, "postgres-schema-index-grid")
    return independent


def analysis_finite_id(_, response, item, statement):
    key = tuple(item["coordinate"])
    same(sql(trial(item["release"], *key)), statement)
    check_value(item, response)
    return key


def direct_finite_id(_, response, item, request):
    key = tuple(item["coordinate"])
    same(direct(trial(item["release"], *key)), request)
    check_value(item, response)
    return key


def key_policy(rng, number):
    first = number % 2
    slots = [first, rng.choice((1 - first, 2))] if number % 3 else [first]
    included = [field for field in rng.sample(range(3), 3) if field not in slots][:number % 3]
    unique = bool(rng.getrandbits(1))
    return {"keys": [[slot, INDEX_ORDERS[(number + i) % len(INDEX_ORDERS)]] for i, slot in enumerate(slots)],
            "included": included, "unique": unique, "nulls": unique and bool(rng.getrandbits(1)),
            "predicate": PREDICATES[number % len(PREDICATES)], "only": bool(rng.getrandbits(1))}


def generated(tag, seed, count):
    rng = Random(seed)
    layouts = list(LAYOUTS)
    rng.shuffle(layouts)
    for index in range(count):
        owner = OWNERS[index % len(OWNERS)]
        width = index % 4 + 1
        yield trial(tag, owner, layouts[index % len(layouts)], rng.choice(ORDERS), bool(rng.getrandbits(1)),
                    width=width, base=rng.randrange(-483, 330), quoted_name=bool(rng.getrandbits(1)),
                    derived=owner == "named" and bool(rng.getrandbits(1)), explicit=bool(rng.getrandbits(1)),
                    repeat=bool(rng.getrandbits(1)), depths=[key_policy(rng, index + part) for part in range(width)])


def edges(tag):
    resolved = trial(tag, "effective", "vacant", "index-first", False, explicit=True)
    bare = trial(tag, "effective", "prior-view", "index-first", True, repeat=True)
    yield "open-absolute", {**analyzed(resolved), "declarations": None}, resolved
    yield "open-local-member-table", {**analyzed(bare), "declarations": None}, bare
    quoted_role = trial(tag, "named", "prior-index", "index-first", False, quoted_name=True, derived=True, width=4)
    yield "owner-derived-namespace", analyzed(quoted_role), quoted_role


def refusals(tag):
    item = trial(tag, "effective", "prior-view", "index-first", True)
    boundaries = (
        ("wrong-schema", f"CREATE SCHEMA {NS} CREATE INDEX idx ON {DECOY}.ledger (n)", "42P15",
         refusal(f"CREATE specifies a schema ({DECOY}) different from the one being created ({NS})", "SemanticException")),
        ("missing-local", f"CREATE SCHEMA {NS} CREATE INDEX idx ON ledger (n)", "42P01",
         refusal("Relation ledger does not exist.\nColumn n does not exist.", "SemanticException")),
        ("ordinary-view", f"CREATE SCHEMA {NS} CREATE INDEX idx ON report (n) CREATE VIEW report AS SELECT 1 AS n", "42809",
         refusal('cannot create index on relation "report"', "SemanticException")),
        ("concurrent", f"CREATE SCHEMA {NS} CREATE INDEX CONCURRENTLY idx ON ledger (n) CREATE TABLE ledger (n integer)", "25001",
         refusal("A schema index cannot be built concurrently.")),
        ("view-no-guard", f"CREATE SCHEMA {NS} CREATE INDEX report ON ledger (n) CREATE VIEW report AS SELECT n FROM ledger"
         " CREATE TABLE ledger (n integer)", "42P07", refusal("An occupied index name requires IF NOT EXISTS.")),
        ("bad-guarded-key", f"CREATE SCHEMA {NS} CREATE INDEX IF NOT EXISTS report ON ledger (absent)"
         " CREATE VIEW report AS SELECT n FROM ledger CREATE TABLE ledger (n integer)", "42703",
         refusal("Column absent does not exist.", "SemanticException")),
    )
    for label, command, native, rejection in boundaries:
        selected = {**item, "command": command, "nativeRefusal": native, "negative": label}
        yield analyzed(selected), selected, rejection
    request = direct(item)
    request["createSchema"]["indexes"][0]["presence"] = "vacant"
    yield request, None, refusal("A schema index must retain the actual presence of its name.")
    request = direct(item)
    request["createSchema"]["indexes"][0]["release"] = "pg-16.6" if tag == "pg-17.2" else "pg-17.2"
    yield request, None, refusal("A schema index must belong to its namespace and release.")
    request = direct(item)
    request["createSchema"]["indexes"][0]["table"][3] = ["int8", "int8", "bool"]
    yield request, None, refusal("A schema index needs the exact ordered operand bindings.")
    request = direct(item)
    request["createSchema"]["sequences"][0]["target"] = [item["namespace"], item["relation"]]
    request["createSchema"]["tables"][0]["ifNotExists"] = True
    yield request, None, refusal("A schema index must resolve to a preceding base table.")
    request = direct(item)
    request["createSchema"]["indexes"][0] = "unchecked index"
    yield request, None, refusal("Schema indexes are an ordered list of checked index declarations.")


def requests(engine, tag, spec):
    for index, key in enumerate(grid(spec)):
        item = trial(tag, *key, coordinate=list(key))
        yield f"finite/{index}", analyzed(item), item, None
        yield f"direct/{index}", direct(item), item, None
    for label, request, item in edges(tag):
        yield f"edge/{label}", request, item, None
    exploration = spec["exploration"]
    for seed in exploration["seeds"]:
        for index, item in enumerate(generated(tag, seed, exploration["casesPerSeed"])):
            yield f"seed/{seed}/{index}", analyzed(item), item, None
            yield f"direct-seed/{seed}/{index}", direct(item), item, None
    invalid = list(refusals(tag))
    same(negative_count(spec, engine, tag), len(invalid))
    for index, (request, item, rejected) in enumerate(invalid):
        yield f"negative/{index}", request, item, rejected


def check_value(item, response):
    same([], response.get("schemaGrants"))
    same(["constructed", item["release"] + "/utf8/standard-conforming-strings/native"],
         [response.get("status"), response.get("profile")])
    schema = direct(item)["createSchema"]
    same({**{key: schema[key] for key in ("owner", "ifNotExists")}, "name": item["namespace"]}, response["createSchema"])
    same(tables(item), response["schemaTables"])
    same([sequence_shape(generator(item), True)], response["schemaSequences"])
    definition = view(item)
    same([{**definition, "query": graph(definition["query"])}], response["schemaViews"])
    same([published(index) for index in schema["indexes"]], response["schemaIndexes"])


def measure(state, item, witness):
    if state is None:
        return None
    scope, records = state["namespaceOid"], state["relationOids"]
    expect = set(state["indexes"]) | ({item["relation"], "report", "tickets"} if state["owner"] is not None else set())
    coordinates = [state["externalOid"], *records.values(), *([] if scope is None else [scope])]
    require(set(records) == expect and len(set(coordinates)) == len(coordinates)
            and all(type(oid) is int and oid > 0 for oid in coordinates) and state["externalIndexCount"] == 0
            and (scope is None) == (state["owner"] is None), "meaning-mismatch", witness)
    view = state["view"]
    if view is not None:
        require(view["physicalDependencies"] == [[item["namespace"], "report", None, 0, "i", records["report"]]],
                "meaning-mismatch", witness)
        view = {key: value for key, value in view.items() if key != "physicalDependencies"}
    indexes = {}
    for name, actual in state["indexes"].items():
        require(actual["targetSchema"] == item["namespace"] and actual["table"] == item["relation"]
                and actual["targetOid"] == records[item["relation"]], "meaning-mismatch", witness)
        indexes[name] = {k: v for k, v in actual.items() if k not in ("targetSchema", "targetOid", "predicateSQL")}
    return {**{name: state[name] for name in ("owner", "table", "sequence", "external")}, "view": view, "indexes": indexes}


def check_observation(item, actual, statement):
    model = expected(item)
    witness = {"expected": model, "observed": actual, "sql": statement}
    require(actual.get("status") == STATUS, "postgres-schema-indexes-observer-scope", witness)
    parts = ("before", "during", "settled")
    seen = {key: measure(actual[key], item, witness) if key in parts else actual[key] for key in model}
    writes = seen["writeProbes"]
    if writes is not None:
        for response in writes:
            code, diagnostic = response["sqlState"], response["diagnostic"]
            require(diagnostic is None if code == "00000" else diagnostic is not None and diagnostic[:2] == ["ERROR", code],
                    "meaning-mismatch", witness)
        seen["writeProbes"] = [response["sqlState"] for response in writes]
    require(seen == model, "meaning-mismatch", witness)
    require(len({actual[part]["externalOid"] for part in parts if actual[part] is not None}) == 1, "meaning-mismatch", witness)
    if not item["active"] and actual["during"] is not None:
        require(actual["settled"] == actual["during"], "meaning-mismatch", witness)
    error = actual["error"]
    require(error is None if model["sqlState"] == "00000" else error is not None and error[:2] == ["ERROR", model["sqlState"]],
            "meaning-mismatch", witness)


def observer_request(item, statement):
    return {"trial": item, "sql": statement}


def observe_rejection(_, item, actual, statement):
    same(item["command"], statement)
    check_observation(item, actual, statement)
    return item["negative"]


def rejected_finite_ids(_, _tag):
    return {"wrong-schema", "missing-local", "ordinary-view", "concurrent", "view-no-guard", "bad-guarded-key"}


def sample_forms(forms, response, _engine, item):
    for name in ("owner", "order", "layout", "qualified", "active", "quoted", "repeat"):
        forms[f"{name}/{item[name]}"] += 1
    indexes = response["schemaIndexes"]
    forms[f"width/{len(indexes)}"] += 1
    for index in indexes:
        for role in ("presence", "unique", "nullsNotDistinct", "ifNotExists"):
            forms[f"{role}/{index[role]}"] += 1
        forms[f"predicate/{PREDICATES.index(index['predicate'])}"] += 1
        forms[f"covered/{bool(index['included'])}"] += 1
        for slot, policy in index["keys"]:
            forms[f"key/{slot}"] += 1
            forms[f"order/{policy}"] += 1


def required_forms(*_):
    return {*(f"owner/{value}" for value in OWNERS), *(f"order/{value}" for value in (*ORDERS, *INDEX_ORDERS)),
            *(f"layout/{value}" for value in LAYOUTS), *(f"width/{width}" for width in range(1, 5)),
            *(f"{name}/{value}" for name in ("qualified", "active", "quoted", "repeat", "unique", "nullsNotDistinct", "covered",
                                            "ifNotExists") for value in (True, False)),
            *(f"key/{slot}" for slot in range(3)), *(f"predicate/{idx}" for idx in range(len(PREDICATES))),
            "presence/vacant", "presence/occupied"}


def controls():
    item = trial("pg-17.2", "effective", "vacant", "index-first", True)
    good = deepcopy({**expected(item), "error": None})
    for name in ("before", "during", "settled"):
        good[name].update(namespaceOid=None, externalOid=100, externalIndexCount=0, relationOids={})
    during = good["during"]
    during.update(namespaceOid=101, relationOids={"ledger": 102, "tickets": 103, "report": 104, "lookup": 105})
    during["view"]["physicalDependencies"] = [[*during["view"]["dependencies"][0], 104]]
    during["indexes"]["lookup"].update(targetSchema=item["namespace"], targetOid=102, predicateSQL="enabled")
    good["writeProbes"] = [{"sqlState": code, "diagnostic": None if code == "00000" else ["ERROR", code]}
                           for code in good["writeProbes"]]
    check_observation(item, good, sql(item))
    failures = {}
    for key, value in (("positions", [2, 1, 3]), ("targetOid", 100)):
        broken = deepcopy(good)
        broken["during"]["indexes"]["lookup"][key] = value
        try:
            check_observation(item, broken, sql(item))
        except Failure as failure:
            same("meaning-mismatch", failure.signal)
            failures[key] = failure.detail
            continue
        raise Failure("schema-index-comparison-ignored-observation", key)
    return failures


def check_fault_witness(item, witness):
    same(expected(item), witness["expected"])
    same(STATUS, witness["observed"]["status"])


def faults(_, tag):
    schema = "sql-semantics-postgres/src/Ir/Schema/Namespace/CreateSchema.php"
    index = "sql-semantics-postgres/src/Ir/Schema/Index/Btree/CreateIndex.php"
    item = trial(tag, "effective", "vacant", "index-first", True, depths=[{
        "keys": [[0, "DESC NULLS LAST"]], "included": [], "unique": True, "nulls": True, "predicate": [2, "column"], "only": False,
    }])
    key_item = {**item, "depths": [{**item["depths"][0], "included": [1]}]}
    return [
        RenderFault("schema-indexes-omit-phase", schema, "foreach ($this->indexes as $index)",
                    "foreach ([] as $index)", item, "meaning-mismatch", direct(item)),
        RenderFault("schema-indexes-move-key", index, "$this->bindings[$key->slot]->name->quoted()",
                    "$this->bindings[1]->name->quoted()", key_item, "meaning-mismatch", direct(key_item)),
        RenderFault("schema-indexes-omit-null-policy", index, "($this->nullsNotDistinct ? ' NULLS NOT DISTINCT' : '')",
                    "''", item, "meaning-mismatch", direct(item)),
        RenderFault("schema-indexes-omit-predicate", index, "($this->predicate === null ? '' : IndexCondition::sql($this->bindings, $this->predicate))",
                    "''", item, "meaning-mismatch", direct(item)),
    ]


if __name__ == "__main__":
    require(FIXTURE_CONTAINER in os.environ, "postgres-schema-indexes-disposable-service-required")
    main(sys.modules[__name__])
