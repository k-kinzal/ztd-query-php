"""Verify named PostgreSQL B-tree definitions against independent native catalog observations."""

import json
from random import Random
import sys

from evidence_transcript import canonical
from postgres_create_btree_cases import (
    BITS, HEADINGS, KEY_AXES, PATH, ROUTE_AXES, ROWS, analyzed, binding_coordinates,
    case, expected, grid, key_case, refusal, route_case,
)
from run import Failure, TOOL, digest, require
from run_server import rejects, same
from run_server_drop_table import specifications as drop_contract
from run_server_join import main, probe
from sqlite_domain import RenderFault

LABEL = "postgres-create-btree"
OBSERVER = "postgres_create_btree_observer.py"
CONTRACT_NAME = "postgres-create-btree-v1.json"
CONTRACT = "cd677c222925381961521d7cfa47864d52a316eb023fd3d13cc34b0537057629"
COMPLETE_SEEDS = True
GRAPH = "postgres-btree-typed-graph"
INVALID = "postgres-btree-invalid-published"


def specifications():
    path = TOOL / CONTRACT_NAME
    require(digest(path) == CONTRACT, "postgres-btree-contract")
    spec = json.loads(path.read_text())
    require(spec["exploration"]["allowedExclusions"] == [], "postgres-btree-exclusions")
    spec["additionalPostgreSqlNegatives"] = list(range(16))

    return spec, {"postgres": drop_contract()[1]["postgres"]}


def coordinates(spec):
    full_keys = {("key", tuple(slots), order, tuple(included), tuple(policy))
                 for slots, order, included, policy in grid(spec, KEY_AXES)}
    routes = {("route", *values) for values in grid(spec, ROUTE_AXES)}
    refused = {coordinate for coordinate in routes if not coordinate[4] and coordinate[5] != "vacant"}
    require(len(full_keys) == 320 and len(routes) == 48 and len(refused) == 16, "postgres-btree-finite-size")

    return (full_keys | (routes - refused)), refused


def finite_ids(_, spec, _tag):
    return coordinates(spec)[0]


def rejected_finite_ids(spec, _tag):
    return coordinates(spec)[1]


def finite_id(_, actual, item):
    return binding_coordinates(item, actual)


def direct_finite_id(engine, actual, item, _request):
    return finite_id(engine, actual, item)


def analysis_finite_id(engine, actual, item, _authored):
    return finite_id(engine, actual, item)


def observe_rejection(spec, item, actual, sql):
    coordinate = binding_coordinates(item, item)
    require(coordinate in rejected_finite_ids(spec, item["release"]), "postgres-btree-negative-coordinate")
    check_observation(item, actual, sql)

    return coordinate


def direct_negatives(tag):
    key = (0, "ASC NULLS LAST")
    yield case(tag, []), refusal("nonempty ordered list of key occurrences")
    yield case(tag, [key] * 32, [0]), refusal("at most 32 key and covering occurrences")
    yield case(tag, [(-1, "ASC NULLS LAST")]), refusal("nonnegative column slot")
    yield case(tag, [(99, "ASC NULLS LAST")]), refusal("key must refer to a column")
    yield case(tag, [key], [99]), refusal("included position must refer to a column")
    yield case(tag, [key], occupied="existing-table"), refusal("occupied index name requires IF NOT EXISTS")


def analysis_negatives(tag):
    item = case(tag, [(0, "ASC NULLS LAST")])
    request = analyzed(item)
    base = "CREATE TABLE "
    view = "CREATE VIEW fixture.orders AS SELECT 17 AS a, CAST(33 AS bigint) AS b, TRUE AS payload"
    yield {**request, "declarations": [view if declaration.startswith(base) else declaration for declaration in request["declarations"]]}, refusal("cannot create index on relation", "SemanticException")
    yield {**request, "sql": request["sql"].replace('"a" ASC NULLS LAST', '"missing"')}, refusal("Column missing does not exist.", "SemanticException")
    yield {**request, "complete": False}, refusal("cannot infer name availability in an open index namespace")
    yield {**request, "declarations": [
        declaration + ' PARTITION BY RANGE ("a")' if declaration.startswith(base) else declaration for declaration in request["declarations"]
    ]}, refusal("needs a local nonpartitioned table")


def edge_cases(tag):
    key = (0, "ASC NULLS LAST")
    repeated = case(tag, [key] * 16, [0] * 16)
    only = case(tag, [key, (2, "DESC NULLS FIRST")], [0, 0], only=True, name='named"index')
    shadow = case(tag, [key, (1, "DESC NULLS LAST")], [2], scope="temporary")
    bad_data = case(tag, [key], unique=True, nulls=True, concurrently=True)
    temporary_bad_data = case(tag, [key], scope="temporary", unique=True, nulls=True, concurrently=True)

    return [(repeated, False), (only, False), (shadow, True), (bad_data, False), (temporary_bad_data, False)]


def generated(tag, seed, count):
    rng = Random(seed)
    headings = [HEADINGS, ["id", "wide data", "Ready"], ["key", 'value"long', "行"]]
    orders = tuple(BITS)
    names = ["idx", "select", 'quote"d', "登録"]
    for ordinal in range(count):
        width = 1 + ordinal % 8
        keys = [(rng.randrange(3), rng.choice(orders)) for _ in range(width)]
        if ordinal % 5 == 0 and width > 1:
            keys[-1] = keys[0]
        included = [rng.randrange(3) for _ in range(rng.randrange(6))]
        occupancy = rng.choice(("vacant", "existing-index", "existing-table"))
        rows = [[a, b + (ordinal * 4096), c] for a, b, c in ROWS]
        yield case(tag, keys, included, rng.choice(("persistent", "temporary")), occupancy,
                   guard=occupancy != "vacant" or bool(rng.getrandbits(1)), unique=bool(rng.getrandbits(1)),
                   nulls=bool(rng.getrandbits(1)), concurrently=bool(rng.getrandbits(1)),
                   transaction=bool(rng.getrandbits(1)), only=bool(rng.getrandbits(1)),
                   name=rng.choice(names), columns=rng.choice(headings), rows=rows)


def requests(_, tag, spec):
    finite = [key_case(tag, *values) for values in grid(spec, KEY_AXES)]
    finite.extend(route_case(tag, *values) for values in grid(spec, ROUTE_AXES))
    rejected = rejected_finite_ids(spec, tag)
    for number, item in enumerate(finite):
        if binding_coordinates(item, item) in rejected:
            yield f"rejected-finite/{number}", analyzed(item), item, refusal("occupied index name requires IF NOT EXISTS")
        else:
            yield f"finite/{number}", analyzed(item), item, None
            yield f"direct/{number}", item, item, None
    for number, (item, unqualified) in enumerate(edge_cases(tag)):
        yield f"edge/{number}", analyzed(item, unqualified=unqualified), item, None
    for seed in spec["exploration"]["seeds"]:
        for number, item in enumerate(generated(tag, seed, spec["exploration"]["casesPerSeed"])):
            yield f"seed/{seed}/{number}", item, item, None
    for number, (item, boundary) in enumerate([*direct_negatives(tag), *analysis_negatives(tag)]):
        yield f"negative/{number}", item, None, boundary


def check_value(item, reply):
    require(reply["status"] == "constructed"
            and reply["profile"] == item["release"] + "/utf8/standard-conforming-strings/native",
            "postgres-btree-positive-rejected", reply)
    require(reply["createBtree"] == item["createBtree"], GRAPH,
            {"sql": reply["sql"], "expected": item["createBtree"], "observed": reply["createBtree"]})


def prepare(_, db):
    return {"libpq": db.environment["libpq"], "python": db.environment["python"]}


def check_preparation(_, environment, preparation):
    same(preparation, {"libpq": environment["libpq"], "python": environment["python"]})
    require(type(preparation["libpq"]) is int and preparation["libpq"] > 0, "postgres-btree-observer-library")


def observer_request(item, sql):
    return {"sql": sql, "trial": item, "path": PATH}


def observation(raw):
    return raw


def check_observation(item, actual, sql):
    model = expected(item)
    witness = {"expected": model, "observed": actual, "sql": sql}
    require(actual["status"] == "btree-creation", "postgres-btree-observer-refusal", witness)
    require(canonical(model) == canonical(actual), "meaning-mismatch", witness)


def sample_forms(forms, reply, _, item):
    index = reply["createBtree"]
    keys = [slot for slot, _ in index["keys"]]
    covering = index["included"]
    for label in ("ifNotExists", "unique", "nullsNotDistinct", "concurrently"):
        forms[f"{label}/{index[label]}"] += 1
    forms[f"schema/{index['table'][0]}"] += 1
    forms[f"arity/{len(keys)}"] += 1
    forms[f"availability/{item['occupancy']}"] += 1
    forms[f"transaction/{item['transaction']}"] += 1
    forms[f"quoted-name/{index['name'] != 'idx'}"] += 1
    forms[f"key-repeat/{len(set(keys)) != len(keys)}"] += 1
    forms[f"cover-repeat/{len(set(covering)) != len(covering)}"] += 1
    forms[f"cover-overlap/{bool(set(covering) & set(keys))}"] += 1
    for slot, order in index["keys"]:
        forms[f"kind/{index['table'][3][slot]}"] += 1
        forms[f"order/{order}"] += 1


def required_forms(*_):
    return {
        *(f"{axis}/{value}" for axis in ("ifNotExists", "unique", "nullsNotDistinct", "concurrently",
                                       "transaction", "quoted-name", "key-repeat", "cover-repeat", "cover-overlap") for value in (False, True)),
        *(f"arity/{arity}" for arity in range(1, 9)), *(f"order/{order}" for order in BITS),
        "schema/fixture", "schema/pg_temp", "availability/vacant", "availability/existing-index", "availability/existing-table",
        "kind/int4", "kind/int8", "kind/bool",
    }


def controls():
    item = case("pg-17.2", [(0, "DESC NULLS FIRST")], [0, 0])
    sample = expected(item)
    check_observation(item, sample, "ordered cover control")
    changed = json.loads(json.dumps(sample))
    changed["after"]["index"]["memberCount"] -= 1
    try:
        check_observation(item, changed, "lost repeated payload")
    except Failure as error:
        same("meaning-mismatch", error.signal)
        return {"repeated-payload-not-ignored": {"input": item, "witness": error.detail}}
    raise Failure("postgres-btree-comparator-accepted")


def fault_probe(item, reply, db):
    if "boundary" not in item:
        probe(item, reply, db, sys.modules[__name__])
        return
    if rejects(reply, item["boundary"]):
        return
    require(reply["status"] == "constructed", "postgres-btree-unrelated-fault", reply)
    raise Failure(INVALID, {"expected": item["boundary"], "observed": reply, "sql": reply["sql"]})


def replay_fault(rule, record):
    witness = record["detail"]
    try:
        if "boundary" in rule.item:
            fault_probe(rule.item, record["healthyResponse"], None)
            fault_probe(rule.item, record["mutantResponse"], None)
        else:
            check_value(rule.item, record["healthyResponse"])
            check_value(rule.item, record["mutantResponse"])
            same(expected(rule.item), witness["expected"])
            check_observation(rule.item, witness["observed"], witness["sql"])
    except Failure as error:
        same(rule.signal, error.signal)
        same(witness, error.detail)
        return
    raise Failure("postgres-btree-fault-not-replayed", rule.name)


def faults(_, tag):
    root = "sql-semantics-postgres/src/Ir/Schema/Index/Btree/CreateIndex.php"
    asc = "ASC NULLS LAST"
    key = (0, asc)
    order = case(tag, [key, (1, "DESC NULLS FIRST")], [2, 2])
    temp = case(tag, [key], scope="temporary")
    unique = case(tag, [(1, asc)], unique=True)
    no_distinct = case(tag, [key], nulls=True)
    concurrent = case(tag, [key], concurrently=True, transaction=True)
    guarded = case(tag, [key], occupied="existing-table", guard=True)
    invalid, boundary = list(direct_negatives(tag))[1]
    return [
        RenderFault("postgres-btree-wrong-scope", root, "$this->table->schema->quoted()", """'"fixture"'""", temp, "meaning-mismatch"),
        RenderFault("postgres-btree-reverse-key-order", root, "foreach ($this->keys as $key)", "foreach (array_reverse($this->keys) as $key)", order, "meaning-mismatch"),
        RenderFault("postgres-btree-erase-covered-columns", root, """($included === [] ? '' : ' INCLUDE (' . implode(', ', $included) . ')')""", "''", order, "meaning-mismatch"),
        RenderFault("postgres-btree-default-null-order", root, "$key->order->value", "'DESC NULLS LAST'", order, "meaning-mismatch"),
        RenderFault("postgres-btree-erase-uniqueness", root, "($this->unique ? 'UNIQUE ' : '')", "''", unique, "meaning-mismatch"),
        RenderFault("postgres-btree-distinguish-nulls", root, "($this->nullsNotDistinct ? ' NULLS NOT DISTINCT' : '')", "''", no_distinct, "meaning-mismatch"),
        RenderFault("postgres-btree-erase-concurrency", root, "($this->concurrently ? 'CONCURRENTLY ' : '')", "''", concurrent, "meaning-mismatch"),
        RenderFault("postgres-btree-erase-guard", root, "($this->ifNotExists ? 'IF NOT EXISTS ' : '')", "''", guarded, "meaning-mismatch"),
        RenderFault("postgres-btree-admit-33-occurrences", root, "count($checkedKeys) + count($checkedIncluded) <= 32", "true",
                    {"boundary": boundary}, INVALID, invalid),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])
