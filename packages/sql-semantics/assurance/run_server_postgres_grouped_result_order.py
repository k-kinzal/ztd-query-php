"""Compare PostgreSQL ordered aggregate outputs and bounded selections within ties."""

import json
import random
import sys
from itertools import product

import run_server_grouped as groups
from run import TOOL, Failure, digest, require
from run_server import rejects, same
from run_server_join import OBSERVER, main, probe
from run_server_postgres_output_order_names import DIAGNOSTIC, SEMANTIC
from server_grouped_cases import aggregate, case, expected as grouped_relation
from server_join_cases import NAMES_BY_TABLE, declarations, slot, table
from server_result_cases import match, result_window
from sqlite_domain import RenderFault

LABEL = "postgres-grouped-result-order"
CONTRACT = "ce8a7f242a23b4708cb34b6280193d0d86dd28066eee65ce3bdefd1886bc0b9d"
COMPLETE_SEEDS = True
TABLE = "public." + NAMES_BY_TABLE["accounts"]
EMPTY = "public." + NAMES_BY_TABLE["empty"]
TYPES = {"int4": 23, "int8": 20}
BOUNDS = {"unbounded": None, "limited": [2, 0], "skip": [None, 1], "zero": [0, 0]}
INPUTS = {"table": "accounts", "empty": "empty"}


def specifications():
    path = TOOL / "postgres-grouped-result-order-v1.json"
    require(digest(path) == CONTRACT, "postgres-grouped-result-order-contract-mismatch")
    return json.loads(path.read_text()), {"postgres": groups.specifications()[1]["postgres"]}


def request(tag, sql):
    return {"dialect": "postgres", "release": tag, "sql": sql, "declarations": declarations("postgres")}


def ordered(tag, source, keys, fields, window, *, where=None, sort=()):
    item = case("postgres", tag, source, keys, fields, where)
    item["grouped"]["orderBy"] = list(sort)
    item["grouped"]["limit"] = window
    return item


def finite(tag, source, operation, key, direction, nulls, bound):
    field = 1 if key == "grouping field" else 2
    return ordered(tag, table("postgres", INPUTS[source]), [slot(0)],
                   [[slot(0), "key"], [aggregate(operation, slot(1)), "amount"]], BOUNDS[bound],
                   sort=[[field, direction == "descending", nulls == "first"]])


def finite_ids(_, frozen, _tag):
    finite_ = frozen["finite"]
    result = set(product(*(finite_[name] for name in ("source", "aggregate", "key", "direction", "nulls", "bound"))))
    require(len(result) == finite_["identities"], "postgres-grouped-order-grid-boundary")
    return result


def finite_id(_, observed, _item):
    query = observed["grouped"]
    (position, descending, first), = query["orderBy"]
    source = {NAMES_BY_TABLE[name]: role for role, name in INPUTS.items()}[query["source"]["table"]]
    operation = query["columns"][1][0][1]
    key = {1: "grouping field", 2: "aggregate field"}[position]
    bound = next(name for name, window in BOUNDS.items() if window == query["limit"])
    return source, operation, key, "descending" if descending else "ascending", "first" if first else "last", bound


def analyzed(tag):
    physical = table("postgres", "accounts")
    without_measure = table("postgres", "accounts", columns=["id"])
    counted = [aggregate("count"), "amount"]
    summed = [aggregate("sum", slot(1)), "amount"]
    prefix = f"SELECT id AS k, count(*) AS amount FROM {TABLE} GROUP BY id"
    yield (prefix + " ORDER BY amount DESC NULLS LAST, k LIMIT 2",
           ordered(tag, without_measure, [slot(0)], [[slot(0), "k"], counted], [2, 0], sort=[[2, True, False], [1, False, False]]))
    yield (prefix + " ORDER BY (k), 2 DESC LIMIT 3 OFFSET 1",
           ordered(tag, without_measure, [slot(0)], [[slot(0), "k"], counted], [3, 1], sort=[[1, False, False], [2, True, True]]))
    yield (f"SELECT id AS k, sum(delta) AS id FROM {TABLE} GROUP BY id ORDER BY id DESC, k",
           ordered(tag, physical, [slot(0)], [[slot(0), "k"], [summed[0], "id"]], None, sort=[[2, True, True], [1, False, False]]))
    yield (f"SELECT id AS k, pg_catalog.count(DISTINCT delta) AS amount FROM {TABLE} GROUP BY id ORDER BY amount, k OFFSET 2",
           ordered(tag, physical, [slot(0)], [[slot(0), "k"], [aggregate("count", slot(1), True), "amount"]], [None, 2],
                   sort=[[2, False, False], [1, False, False]]))
    yield (f"SELECT count(*) AS n FROM {EMPTY} ORDER BY n OFFSET 1",
           ordered(tag, table("postgres", "empty", columns=[]), [], [[aggregate("count"), "n"]], [None, 1], sort=[[1, False, False]]))
    yield (f'SELECT id AS k, min(delta) AS "Selected value" FROM {TABLE} GROUP BY id ORDER BY "Selected value" NULLS FIRST',
           ordered(tag, physical, [slot(0)], [[slot(0), "k"], [aggregate("min", slot(1)), "Selected value"]], None, sort=[[2, False, True]]))
    yield (f"SELECT id AS k, count(*) AS amount, count(*) AS amount FROM {TABLE} GROUP BY id ORDER BY amount, k",
           ordered(tag, without_measure, [slot(0)], [[slot(0), "k"], counted, counted], None, sort=[[2, False, False], [1, False, False]]))
    yield (f"SELECT id AS owner, max(delta) AS amount FROM {TABLE} GROUP BY id ORDER BY 2 DESC NULLS LAST, owner LIMIT 2 OFFSET 1",
           ordered(tag, physical, [slot(0)], [[slot(0), "owner"], [aggregate("max", slot(1)), "amount"]], [2, 1],
                   sort=[[2, True, False], [1, False, False]]))


def native_negatives():
    return (
        (f"SELECT id, count(*) AS n, sum(delta) AS n FROM {TABLE} GROUP BY id ORDER BY n", "Output column reference n is ambiguous.", "42702"),
        (f"SELECT id, count(*) FROM {TABLE} GROUP BY id ORDER BY 3", "ORDER BY position 3 is not in select list", "42P10"),
    )


def prepare(engine, db):
    return {"grouping": groups.prepare(engine, db), "errors": [db.request({"sql": sql}) for sql, _, _ in native_negatives()]}


def check_preparation(engine, environment, result):
    groups.check_preparation(engine, environment, result["grouping"])
    same([{"status": "db-error", "state": state} for _, _, state in native_negatives()], result["errors"])
    require(environment["database"][-1] == "pg_catalog, public", "postgres-grouped-result-order-profile")


def negatives(tag):
    sample = finite(tag, "table", "sum", "aggregate field", "ascending", "first", "unbounded")
    for key in (3, slot(0)):
        bad = {**sample, "grouped": {**sample["grouped"], "orderBy": [[key, False, True]]}}
        yield bad, {"exception": DIAGNOSTIC, "message": "A grouped order key must select a projected position."}
    for sql, message, _ in native_negatives():
        yield request(tag, sql), {"exception": SEMANTIC, "message": message}
    for sql, message in (
        (f"SELECT id, count(*) FROM {TABLE} GROUP BY id ORDER BY 1 USING <", "ORDER BY requires a supported default sort operator."),
        (f"SELECT count(*) AS n FROM {TABLE} GROUP BY id ORDER BY id", "A grouped ORDER BY needs a projected name or position."),
    ):
        yield request(tag, sql), {"exception": DIAGNOSTIC, "message": message}


def seeded(tag, seed, amount):
    rng = random.Random(seed)
    for example in range(amount):
        native = rng.choice(("accounts", "empty", "independent"))
        partition = rng.sample([slot(0), slot(1)], rng.randrange(3))
        fields = [[term, f"key_{index}"] for index, term in enumerate(partition)]
        terms = []
        for _ in range(rng.randrange(1, 5)):
            operation = rng.choice(("count", "sum", "min", "max"))
            arg = None if operation == "count" and rng.randrange(3) == 0 else slot(rng.randrange(2))
            terms.append([aggregate(operation, arg, arg is not None and bool(rng.getrandbits(1))), rng.choice(("value", "other", '値 " y'))])
        fields += terms
        rng.shuffle(fields)
        positions = rng.sample(list(range(1, len(fields) + 1)), rng.randrange(1, len(fields) + 1))
        sort = [[key, bool(rng.getrandbits(1)), bool(rng.getrandbits(1))] for key in positions]
        limit = rng.choice((None, [None, 1], [rng.randrange(8), rng.randrange(4)]))
        where = rng.choice((None, ["boolean", False], ["null-test", bool(rng.getrandbits(1)), slot(rng.randrange(2))]))
        yield ordered(tag, table("postgres", native, bool(rng.getrandbits(1))), partition, fields, limit, where=where, sort=sort)


def requests(_, tag, specification):
    finite_ = specification["finite"]
    grid = product(*(finite_[name] for name in ("source", "aggregate", "key", "direction", "nulls", "bound")))
    for index, cell in enumerate(grid):
        item = finite(tag, *cell)
        yield f"finite/{index}", item, item, None
    inputs = list(analyzed(tag))
    require(len(inputs) == len(specification["analysis"]), "postgres-grouped-result-order-analysis-count")
    for index, (sql, item) in enumerate(inputs):
        yield f"analyze/{index}", request(tag, sql), item, None
    exploration = specification["exploration"]
    for seed in exploration["seeds"]:
        for index, item in enumerate(seeded(tag, seed, exploration["casesPerSeed"])):
            yield f"seed/{seed}/{index}", item, item, None
    for index, (item, reason) in enumerate(negatives(tag)):
        yield f"negative/{index}", item, None, reason


def prediction(item):
    relation = grouped_relation(item)
    fields = [[label, native, TYPES[native]] for label, native in relation["fields"]]
    query = item["grouped"]
    return result_window(fields, relation["rows"], query["orderBy"], query["limit"])


def observer_request(_, sql):
    return {"sql": sql, "observePostgresTypeIds": True}


def observation(raw):
    return raw


def check_value(item, reply):
    groups.check_value(item, reply)


def check_observation(item, actual, sql):
    match(prediction(item), actual, sql)


def required_forms(*_):
    return {"global", "keyed", "multiple-keys", "min", "max", "sum", "count", "aggregate-target", "field-target",
            "null-first", "null-last", "ascending", "descending", "offset-only", "bounded", "unbounded", "some-input", "empty-input"}


def sample_forms(forms, reply, *_):
    query = reply["grouped"]
    forms["global" if not query["keys"] else "keyed"] += 1
    forms["empty-input" if query["source"]["table"] == NAMES_BY_TABLE["empty"] else "some-input"] += 1
    if len(query["orderBy"]) > 1:
        forms["multiple-keys"] += 1
    limit = query["limit"]
    forms["unbounded" if limit is None else "offset-only" if limit[0] is None else "bounded"] += 1
    for term, _ in query["columns"]:
        if term[0] == "aggregate":
            forms[term[1]] += 1
    for position, descending, first in query["orderBy"]:
        forms["null-first" if first else "null-last"] += 1
        forms["descending" if descending else "ascending"] += 1
        forms["aggregate-target" if query["columns"][position - 1][0][0] == "aggregate" else "field-target"] += 1


def rows_from_bands(bands):
    return [row for band in bands for row in band["members"][:band["multiplicity"]]]


def controls():
    item = finite("pg-17.2", "table", "min", "grouping field", "descending", "first", "unbounded")
    outcome = prediction(item)
    raw = {"status": "rows", "fields": outcome["fields"], "rows": rows_from_bands(outcome["bands"])}
    check_observation(item, raw, "grouped order control")
    errors = {
        "wrong group order": {**raw, "rows": raw["rows"][::-1]},
        "drop grouped row": {**raw, "rows": raw["rows"][1:]},
        "change result kind": {**raw, "fields": [["key", "int8", 20], raw["fields"][1]]},
    }
    result = {}
    for name, altered in errors.items():
        try:
            check_observation(item, altered, name)
        except Failure as error:
            require(error.signal == "meaning-mismatch", "postgres-grouped-order-control-signal", name)
            result[name] = {"input": item, **error.detail}
        else:
            raise Failure("postgres-grouped-order-control-accepted", name)
    return result


def boundary(item, reply):
    if rejects(reply, item["boundary"]):
        return
    require(reply["status"] == "constructed", "postgres-grouped-order-mutant-unexpected-response", reply)
    raise Failure("postgres-grouped-order-published-invalid", {"sql": reply["sql"], "output": reply, "expected": item["boundary"]})


def fault_probe(item, reply, db):
    if "boundary" in item:
        boundary(item, reply)
        return
    probe(item, reply, db, sys.modules[__name__])


def replay_fault(rule, record):
    witness = record["detail"]
    try:
        if "boundary" in rule.item:
            boundary(rule.item, record["healthyResponse"])
            boundary(rule.item, record["mutantResponse"])
        else:
            check_value(rule.item, record["healthyResponse"])
            check_value(rule.item, record["mutantResponse"])
            same(prediction(rule.item), witness["expected"])
            require(witness["observed"]["status"] == "rows", "postgres-grouped-order-fault-native")
            check_observation(rule.item, witness["observed"], witness["sql"])
    except Failure as error:
        same(rule.signal, error.signal)
        same(witness, error.detail)
        return
    raise Failure("postgres-grouped-order-undetected-fault", rule.name)


def faults(_, tag):
    ir = "sql-semantics-postgres/src/Ir/"
    printer = ir + "Ordering/ResultSql.php"
    full = finite(tag, "table", "min", "grouping field", "ascending", "first", "unbounded")
    tied = ordered(tag, table("postgres", "accounts"), [slot(0)],
                   [[slot(0), "key"], [aggregate("count"), "amount"]], None, sort=[[2, False, False], [1, True, True]])
    skip = finite(tag, "table", "sum", "grouping field", "ascending", "last", "skip")
    invalid, reason = next(negatives(tag))
    return [
        RenderFault("change-grouped-order-position", printer, "(string) $key->target",
                    "($key->target === 1 ? '2' : (string) $key->target)", full, "meaning-mismatch"),
        RenderFault("drop-second-group-order", printer, "implode(', ', $terms)", "$terms[0]", tied, "meaning-mismatch"),
        RenderFault("reverse-group-output-null-order", printer, "$key->nullsFirst ? ' NULLS FIRST' : ' NULLS LAST'",
                    "$key->nullsFirst ? ' NULLS LAST' : ' NULLS FIRST'", full, "meaning-mismatch"),
        RenderFault("discard-offset-after-grouping", printer, "$sql .= ' OFFSET ' . $select->limit->offset;",
                    "", skip, "meaning-mismatch"),
        RenderFault("skip-owned-group-position", ir + "Aggregation/GroupedSelect.php",
                    "is_int($key->target) && $key->target <= count($results)", "true",
                    {"boundary": reason}, "postgres-grouped-order-published-invalid", invalid),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])
