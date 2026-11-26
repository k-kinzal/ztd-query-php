"""Compare resolved native sort aliases with the selected one-based result keys."""

import json
import random
import re
import sys
from itertools import product

from run import TOOL, Failure, digest, require
from run_server import CONTRACT as PROJECTION, same
from run_server_join import main
from run_server_postgres_boolean_fields import DIAGNOSTIC, query
from run_server_postgres_int8_fields import (
    DECLARATIONS, EMPTY, HEADINGS, LEFT, OBSERVER, expected as project, field, graph,
    observation, observer_request, prepare as prepare_fixture, table, type_of,
)
from sqlite_domain import RenderFault

LABEL = "postgres-output-order-names"
CONTRACT_NAME = "postgres-output-order-names-v1.json"
CONTRACT = "835398078917b8a701f496ad7f14ba52f3cd7f8aac6329de08c7a4294f1d75c9"
SOURCE = f"public.{LEFT}"
SEMANTIC = DIAGNOSTIC.rsplit("\\", 1)[0] + "\\SemanticException"
KINDS = {"int8": "low", "boolean": "enabled"}


def specifications():
    scope, projection = TOOL / CONTRACT_NAME, TOOL / "server-projection-v1.json"
    require(digest(scope) == CONTRACT and digest(projection) == PROJECTION, "postgres-output-order-contract-mismatch")
    return json.loads(scope.read_text()), {"postgres": json.loads(projection.read_text())["profiles"]["postgres"]}


def analysis_request(tag, sql):
    return {"dialect": "postgres", "release": tag, "sql": sql, "declarations": DECLARATIONS}


def negative_sql():
    return (
        (f"SELECT low AS x, high AS x FROM {SOURCE} ORDER BY x", "Output column reference x is ambiguous.", "42702"),
        (f"SELECT low AS x FROM {SOURCE} ORDER BY absent", "Column absent does not exist.", "42703"),
        (f"SELECT low AS x FROM {SOURCE} ORDER BY 3", "ORDER BY position 3 is not in select list", "42P10"),
        (f'SELECT low AS "Caps" FROM {SOURCE} ORDER BY caps', "Column caps does not exist.", "42703"),
    )


def prepare(engine, db):
    fixtures = prepare_fixture(engine, db)
    return {"fixture": fixtures, "errors": [db.request(observer_request({}, sql)) for sql, _, _ in negative_sql()]}


def check_preparation(_, environment, result):
    same({"status": "fixture"}, result["fixture"])
    require(environment["database"][-1] == "pg_catalog, public", "postgres-output-order-profile")
    same([{"status": "db-error", "state": state} for _, _, state in negative_sql()], result["errors"])


def select(tag, fields, order, *, source=LEFT, where=None, distinct=False, limit=None):
    return {**query(tag, table(source), fields, where, distinct, order), "limit": limit}


def labels(form):
    if form == "quoted":
        return '"Chosen Value"', '"Chosen Value"', "Chosen Value"
    return "picked", "((picked))" if form == "grouped" else "picked", "picked"


def finite(tag, form, direction, nulls, distinct, native):
    as_name, by_name, label = labels(form)
    column = KINDS[native]
    descending = direction == "descending"
    nulls_first = descending if nulls == "implicit" else nulls == "first"
    options = " DESC" if descending else " ASC"
    if nulls != "implicit":
        options += " NULLS " + nulls.upper()
    raw = f"SELECT {'DISTINCT ' if distinct else ''}{column} AS {as_name}, id FROM {SOURCE} ORDER BY {by_name}{options}, id"
    projections = [[field(LEFT, 0, column), label], [field(LEFT, 0, "id"), "id"]]
    return raw, select(tag, projections, [[1, descending, nulls_first], [2, False, False]], distinct=distinct)


def finite_ids(_, contract, _tag):
    grid = contract["finite"]
    ids = {(form, descending, nulls, distinct, native) for form in grid["form"] for descending in grid["direction"]
           for nulls in grid["nulls"] for distinct in grid["distinct"] for native in grid["kind"]}
    require(len(ids) == grid["identities"], "postgres-output-order-finite-size", len(ids))
    return ids


def analysis_finite_id(_, reply, item, sql):
    suffix = r' ORDER BY (picked|\(\(picked\)\)|"Chosen Value") (ASC|DESC)( NULLS (FIRST|LAST))?, id$'
    original = re.search(suffix, sql)
    require(original is not None, "postgres-output-order-missing-original", sql)
    name, direction, _, nulls = original.groups()
    form = {"picked": "ordinary", "((picked))": "grouped", '"Chosen Value"': "quoted"}[name]
    actual = reply["orderBy"]
    explicit = nulls.lower() if nulls else "implicit"
    descending = direction == "DESC"
    require(actual == [[1, descending, descending if nulls is None else nulls == "FIRST"], [2, False, False]],
            "postgres-output-order-observed-positional-meaning", actual)
    native = {"int8": "int8", "bool": "boolean"}[type_of(reply["columns"][0][0])]
    check_value(item, reply)
    return form, "descending" if descending else "ascending", explicit, reply["distinct"], native


def analyzed(tag):
    low, signed, enabled = (field(LEFT, 0, name) for name in ("low", "id", "enabled"))
    yield (f"SELECT low AS id, id AS signed FROM {SOURCE} ORDER BY id DESC NULLS FIRST, signed",
           select(tag, [[low, "id"], [signed, "signed"]], [[1, True, True], [2, False, False]]))
    repeated = select(tag, [[low, "rank"], [low, "rank"], [signed, "id"]], [[1, False, False], [3, False, False]])
    yield f"SELECT low AS rank, t.low AS rank, id FROM {SOURCE} t ORDER BY rank, id", repeated
    yield f"SELECT ((low)) AS rank, t.low AS rank, id FROM {SOURCE} t ORDER BY (rank), id", repeated
    yield (f"SELECT enabled AS chosen, low AS large, id FROM {SOURCE} ORDER BY chosen NULLS FIRST, large DESC NULLS LAST, id",
           select(tag, [[enabled, "chosen"], [low, "large"], [signed, "id"]],
                  [[1, False, True], [2, True, False], [3, False, False]]))
    yield (f"SELECT * FROM {SOURCE} ORDER BY enabled, id",
           select(tag, [[field(LEFT, 0, name), name] for name in HEADINGS[LEFT]], [[4, False, False], [1, False, False]]))
    yield (f'SELECT low AS "Caps", id FROM {SOURCE} ORDER BY "Caps", id',
           select(tag, [[low, "Caps"], [signed, "id"]], [[1, False, False], [2, False, False]]))
    yield (f"SELECT DISTINCT low AS chosen, id FROM {SOURCE} ORDER BY chosen DESC NULLS LAST, id LIMIT 2 OFFSET 1",
           select(tag, [[low, "chosen"], [signed, "id"]], [[1, True, False], [2, False, False]], distinct=True, limit=[2, 1]))
    yield (f"SELECT enabled AS chosen, id FROM public.{EMPTY} WHERE FALSE ORDER BY chosen, id",
           select(tag, [[field(EMPTY, 0, "enabled"), "chosen"], [field(EMPTY, 0, "id"), "id"]],
                  [[1, False, False], [2, False, False]], source=EMPTY, where=["boolean", False]))


def negatives(tag):
    for sql, message, _ in negative_sql():
        yield analysis_request(tag, sql), {"exception": SEMANTIC, "message": message}
    yield (select(tag, [[field(LEFT, 0, "low"), "picked"]], [[2, False, False]]),
           {"exception": DIAGNOSTIC, "message": "An output order position must belong to the projection."})


def seeded(tag, seed, count):
    rng = random.Random(seed)
    for index in range(count):
        names = rng.sample(["low", "high", "id", "enabled"], rng.randint(2, 4))
        labeled = [[field(LEFT, 0, name), rng.choice((name, "tally", 'Á " marker'))] for name in names]
        positions = rng.sample(list(range(1, len(names)+1)), len(names))
        orders = [[place, bool(rng.getrandbits(1)), bool(rng.getrandbits(1))] for place in positions]
        limit = rng.choice((None, [None, 1], [rng.randint(1, 6), rng.randrange(3)]))
        yield select(tag, labeled, orders, distinct=bool(rng.getrandbits(1)), limit=limit)


def requests(_, tag, contract):
    f = contract["finite"]
    dimensions = product(f["form"], f["direction"], f["nulls"], f["distinct"], f["kind"])
    for index, coords in enumerate(dimensions):
        sql, meaning = finite(tag, *coords)
        yield f"finite/{index}", analysis_request(tag, sql), meaning, None
        yield f"direct/{index}", meaning, meaning, None
    examples = list(analyzed(tag))
    require(len(examples) == len(contract["analysis"]), "postgres-output-order-analysis-count")
    for index, (sql, meaning) in enumerate(examples):
        yield f"analyze/{index}", analysis_request(tag, sql), meaning, None
    settings = contract["exploration"]
    for seed in settings["seeds"]:
        for index, item in enumerate(seeded(tag, seed, settings["casesPerSeed"])):
            yield f"seed/{seed}/{index}", item, item, None
    for index, (item, reason) in enumerate(negatives(tag)):
        yield f"negative/{index}", item, None, reason


def expected(item):
    predicted = project(item)
    require(predicted["status"] == "rows", "postgres-output-order-reference-error", item)
    if item["limit"] is not None:
        count, offset = item["limit"]
        end = None if count is None else offset + count
        predicted["rows"] = predicted["rows"][offset:end]
    return predicted


def check_value(item, actual):
    require(actual["status"] == "constructed" and actual["profile"].startswith(item["release"] + "/"),
            "postgres-output-order-positive-rejected", actual)
    require(graph(actual) == graph(item), "postgres-output-order-graph", {"expected": item, "actual": actual})


def check_observation(item, actual, sql):
    wanted = expected(item)
    witness = {"sql": sql, "expected": wanted, "observed": actual}
    require(actual["status"] == "rows", "postgres-output-order-native-rejection", witness)
    try:
        same(wanted, actual)
    except Failure as failure:
        raise Failure(failure.signal, witness) from failure


def check_fault_witness(item, witness):
    same(expected(item), witness["expected"])
    require(witness["observed"]["status"] == "rows", "postgres-output-order-fault-native")


def controls():
    _, item = finite("pg-17.2", "ordinary", "ascending", "first", False, "int8")
    healthy = expected(item)
    check_observation(item, healthy, "healthy")
    controls = {}
    for name, other in (
        ("reverse order", {**healthy, "rows": healthy["rows"][::-1]}),
        ("omit duplicate", {**healthy, "rows": list(map(list, dict.fromkeys(map(tuple, healthy["rows"]))))}),
        ("change output alias", {**healthy, "fields": [["other", "int8", 20], healthy["fields"][1]]}),
    ):
        try:
            check_observation(item, other, name)
        except Failure as failure:
            require(failure.signal == "meaning-mismatch", "postgres-output-order-control-signal")
            controls[name] = {"input": item, **failure.detail}
        else:
            raise Failure("postgres-output-order-control-accepted", name)
    return controls


def required_forms(*_):
    return {"direction/True", "direction/False", "nulls/True", "nulls/False", "bound/True", "bound/False",
            "kind/int4", "kind/int8", "kind/bool", "duplicate-label", "multi-key", "reordered-keys"}


def sample_forms(forms, reply, *_):
    if len(reply["orderBy"]) > 1:
        forms["multi-key"] += 1
    if [index for index, _, _ in reply["orderBy"]] != list(range(1, len(reply["columns"])+1)):
        forms["reordered-keys"] += 1
    for _, direction, nulls in reply["orderBy"]:
        forms[f"direction/{direction}"] += 1
        forms[f"nulls/{nulls}"] += 1
    for value, _ in reply["columns"]:
        forms["kind/" + type_of(value)] += 1
    forms[f"bound/{reply['limit'] is not None}"] += 1
    if len({name for _, name in reply["columns"]}) < len(reply["columns"]):
        forms["duplicate-label"] += 1


def faults(_, tag):
    renderer = "sql-semantics-postgres/src/Ir/Ordering/ResultSql.php"
    simple = finite(tag, "ordinary", "ascending", "first", False, "int8")[1]
    shadowed = next(analyzed(tag))[1]
    key = field(LEFT, 0, "id")
    tie_break = select(tag, [[["boolean", True], "same"], [key, "id"]], [[1, False, False], [2, True, True]])
    return [
        RenderFault("emit-wrong-output-ordinal", renderer, "(string) $key->target", "($key->target === 1 ? '2' : (string) $key->target)",
                    simple, "meaning-mismatch"),
        RenderFault("order-by-shadowed-input-name", renderer, "(string) $key->target",
                    "($key->target === 1 ? '\"__ztd_source\".\"id\"' : (string) $key->target)", shadowed, "meaning-mismatch"),
        RenderFault("reverse-native-output-direction", renderer, "$key->descending ? ' DESC' : ' ASC'",
                    "$key->descending ? ' ASC' : ' DESC'", simple, "meaning-mismatch"),
        RenderFault("reverse-native-output-null-order", renderer, "$key->nullsFirst ? ' NULLS FIRST' : ' NULLS LAST'",
                    "$key->nullsFirst ? ' NULLS LAST' : ' NULLS FIRST'", simple, "meaning-mismatch"),
        RenderFault("discard-native-tie-breaker", renderer, "implode(', ', $terms)", "$terms[0]", tie_break, "meaning-mismatch"),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])
