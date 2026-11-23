"""Compare ordered PostgreSQL FROM-list products and resolved slots against the native engine."""

from itertools import product
import json
import random
import sys

from run import TOOL, Failure, digest, require
from run_server import CONTRACT as PROJECTION, same
from run_server_join import OBSERVER, check_preparation, main, observer_request
from server_cases import CHILD_ROWS, observation
from server_join_cases import (
    DATA, NAMES_BY_TABLE, case, compare, declarations, expected as join_expected, fixture,
    is_join, join, leaves, slot, table,
)
from sqlite_domain import RenderFault

LABEL = "postgres-from-list"
CONTRACT = "3652cde46b77297be0214843b918c8d9f827a38bb2c64259abfaeed70b53ce95"
SMALL = {"schema": "public", "table": "ir_list_small", "columns": ["id", "delta"], "only": False}
SMALL_DECLARATION = "CREATE TABLE public.ir_list_small (id INTEGER, delta INTEGER)"
REFUSALS = "SqlSemantics\\Platform\\PostgreSql\\Model\\Diagnostic\\"


def specifications():
    path, profile = TOOL / "postgres-from-list-v1.json", TOOL / "server-projection-v1.json"
    require(digest(path) == CONTRACT and digest(profile) == PROJECTION, "postgres-from-list-contract-mismatch")
    return json.loads(path.read_text()), {"postgres": json.loads(profile.read_text())["profiles"]["postgres"]}


def prepare(_, db):
    result = db.request({"fixture": [*fixture("postgres"), "DROP TABLE IF EXISTS public.ir_list_small",
                                     SMALL_DECLARATION, "INSERT INTO public.ir_list_small VALUES (-4,81),(1,82)"]})
    same({"status": "fixture"}, result)
    return result


def relation(role, only=False):
    if role == "small":
        return {**SMALL, "only": only}
    return table("postgres", role, only)


def product_case(tag, inputs, fields, where=None):
    source = inputs[0]
    for item in inputs[1:]:
        source = join("CROSS", source, item)
    return case("postgres", tag, source, fields, where)


def fields(positions):
    return [[slot(index, occurrence), name] for occurrence in positions for index, name in enumerate(("id", "delta"))]


def finite(tag, width, policy, ending, projection, filter_kind):
    inputs = [relation("accounts", policy == "only"), *(relation("small") for _ in range(width - 2)),
              relation("empty" if ending == "empty-last" else "independent")]
    columns = fields(range(width) if projection == "all" else (width - 1,))
    where = None if filter_kind == "absent" else compare("<", slot(0), slot(1, width - 1))
    return product_case(tag, inputs, columns, where)


def analysis_request(tag, sql):
    return {"dialect": "postgres", "release": tag, "sql": sql,
            "declarations": [*declarations("postgres"), SMALL_DECLARATION]}


def analyzed(tag):
    accounts, independent, empty = (f"public.{NAMES_BY_TABLE[role]}" for role in ("accounts", "independent", "empty"))
    a, b, e, s = (relation(role) for role in ("accounts", "independent", "empty", "small"))
    yield f"SELECT * FROM {accounts} a, {independent} b, {accounts} c", product_case(tag, [a, b, a], fields(range(3)))
    yield f"SELECT c.* FROM {accounts} a, {independent} b, {accounts} c", product_case(tag, [a, b, a], fields((2,)))
    yield f"SELECT b.delta AS right_delta,a.delta AS left_delta FROM {accounts} a, {accounts} b WHERE a.id<b.id", product_case(
        tag, [a, a], [[slot(1, 1), "right_delta"], [slot(1), "left_delta"]], compare("<", slot(0), slot(0, 1)))
    yield f"SELECT a.id,b.id FROM {independent} a, ONLY {accounts} b", product_case(
        tag, [b, relation("accounts", True)], [[slot(0), "id"], [slot(0, 1), "id"]])
    yield f"SELECT * FROM {accounts} a, {independent} b, {empty} c", product_case(tag, [a, b, e], fields(range(3)))
    yield f"SELECT d.delta AS last_value FROM {accounts} a, public.ir_list_small b, {independent} c, {accounts} d WHERE a.id=d.id", product_case(
        tag, [a, s, b, a], [[slot(1, 3), "last_value"]], compare("=", slot(0), slot(0, 3)))


def negatives(tag):
    small = "public.ir_list_small"
    missing = product_case(tag, [relation("small"), relation("independent")], [[slot(0, 2), "absent"]])
    yield missing, {"exception": REFUSALS + "InvalidConstruction", "message": "must belong to the SELECT source"}
    inputs = ", ".join(f"{small} a{i}" for i in range(62))
    for sql, error, message in (
        (f"SELECT id FROM {small} a, {small} b", "SemanticException", "ambiguous"),
        (f"SELECT 1 FROM {small} a, (SELECT 1 AS x) b", "InvalidConstruction", "requires ordinary table inputs"),
        (f"SELECT a.id FROM public.absent a, {small} b", "SemanticException", "Relation absent does not exist"),
        (f"SELECT 1 FROM {inputs}", "InvalidConstruction", "at most 61 input occurrences"),
    ):
        yield analysis_request(tag, sql), {"exception": REFUSALS + error, "message": message}


def requests(_, tag, spec):
    bounds = spec["finite"]
    for index, dimensions in enumerate(product(*(bounds[key] for key in ("widths", "policies", "inputs", "projections", "filters")))):
        item = finite(tag, *dimensions)
        yield f"finite/{index}", item, item, None
    cases = list(analyzed(tag))
    require(len(cases) == len(spec["analysis"]), "incomplete-postgres-from-list-analysis")
    for index, (sql, item) in enumerate(cases):
        yield f"analyze/{index}", analysis_request(tag, sql), item, None
    for seed in spec["exploration"]["seeds"]:
        randomizer = random.Random(seed)
        for index in range(spec["exploration"]["casesPerSeed"]):
            width = randomizer.randint(2, 6)
            roles = randomizer.choices(("accounts", "independent", "empty"), k=2) + ["small"] * (width - 2)
            randomizer.shuffle(roles)
            inputs = [relation(role, bool(randomizer.getrandbits(1))) for role in roles]
            columns = [[slot(randomizer.randrange(2), randomizer.randrange(width)), randomizer.choice(("id", "値", 'x " field'))]
                       for _ in range(randomizer.randint(1, 6))]
            where = randomizer.choice((None, compare("<", slot(1), slot(0, width - 1))))
            item = product_case(tag, inputs, columns, where)
            yield f"seed/{seed}/{index}", item, item, None
    for index, (request, boundary) in enumerate(negatives(tag)):
        yield f"negative/{index}", request, None, boundary


def graph(item):
    root = item["source"]
    inputs = leaves(root)

    def tree(node):
        if not is_join(node):
            return node["schema"], node["table"], node["only"]
        return node["kind"], tree(node["left"]), tree(node["right"]), node["on"]

    def field(term):
        require(term[0] == "slot", "postgres-from-list-unexpected-expression", term)
        occurrence = term[2] if len(term) == 3 else 0
        return occurrence, inputs[occurrence]["columns"][term[1]]

    where = item["where"]
    return (tree(root), tuple((field(expression), name) for expression, name in item["columns"]),
            None if where is None else (where[0], where[1], field(where[2]), field(where[3])))


def finite_id(_, reply, _item):
    origins = leaves(reply["source"])
    return (len(origins), "only" if origins[0]["only"] else "inherit",
            "empty-last" if origins[-1]["table"] == NAMES_BY_TABLE["empty"] else "populated",
            "last" if len(reply["columns"]) == 2 else "all", "absent" if reply["where"] is None else "int4-comparison")


def finite_ids(_, spec, _tag):
    bounds = spec["finite"]
    identities = {(width, policy, ending, projection, condition)
                  for width in bounds["widths"] for policy in bounds["policies"] for ending in bounds["inputs"]
                  for projection in bounds["projections"] for condition in bounds["filters"]}
    require(len(identities) == bounds["identities"], "wrong-postgres-from-list-domain", len(identities))
    return identities


def heading_and_rows(source):
    if source["table"] == SMALL["table"]:
        return SMALL["columns"], [(-4, 81), (1, 82)]
    role = next(role for role, table_name in NAMES_BY_TABLE.items() if table_name == source["table"])
    descendants = CHILD_ROWS if role == "accounts" and not source["only"] else []
    return ["id", "delta"], DATA[role] + descendants


def expected(item):
    return join_expected(item, heading_and_rows)


def check_value(item, reply):
    require(reply["status"] == "constructed", "postgres-from-list-positive-rejected", reply)
    require(reply["profile"].startswith(item["release"] + "/") and graph(reply) == graph(item),
            "postgres-from-list-graph-differs", {"expected": item, "actual": reply})


def check_observation(item, actual, sql):
    prediction = expected(item)
    require(actual["status"] == "rows", "postgres-from-list-native-rejected", {"sql": sql, "actual": actual})
    require(prediction == actual, "meaning-mismatch", {"sql": sql, "expected": prediction, "observed": actual})


def check_fault_witness(item, witness):
    same(expected(item), witness["expected"])
    require(witness["observed"]["status"] == "rows", "postgres-from-list-fault-native")


def sample_forms(forms, reply, *_):
    origins = leaves(reply["source"])
    forms[f"width/{len(origins)}"] += 1
    tables = [leaf["table"] for leaf in origins]
    if len(set(tables)) < len(tables):
        forms["same-table"] += 1
    if NAMES_BY_TABLE["empty"] in tables:
        forms["empty"] += 1
    for leaf in origins:
        forms[f"only/{leaf['only']}"] += 1
    if any('"' in label for _, label in reply["columns"]):
        forms["quoted-label"] += 1
    _, _, predicate = graph(reply)
    if predicate is not None and predicate[2][0] != predicate[3][0]:
        forms["cross-occurrence-filter"] += 1


def required_forms(*_):
    return {*(f"width/{width}" for width in range(2, 7)), "same-table", "empty", "only/False", "only/True",
            "quoted-label", "cross-occurrence-filter"}


def controls():
    item = finite("pg-17.2", 3, "inherit", "populated", "all", "absent")
    prediction = expected(item)
    check_observation(item, prediction, "known Cartesian product")
    altered = {**prediction, "rows": prediction["rows"][1:]}
    try:
        check_observation(item, altered, "missing duplicate result row")
    except Failure as failure:
        require(failure.signal == "meaning-mismatch", "wrong-postgres-from-list-control", failure.detail)
    else:
        raise Failure("postgres-from-list-comparator-accepted")
    return {"healthy": prediction, "lostRow": altered}


def faults(_, tag):
    target = "sql-semantics-postgres/src/Ir/SelectSql.php"
    later = product_case(tag, [relation("small"), relation("accounts", True)], [[slot(0, 1), "later"]])
    filtered = finite(tag, 3, "only", "populated", "all", "int4-comparison")
    return [
        RenderFault("use-first-product-occurrence", target, "$this->alias($offset + $expression->occurrence)",
                    "$this->alias($offset)", later, "meaning-mismatch"),
        RenderFault("inherit-a-later-only-scan", target, "($source->only ? 'ONLY ' : '')", "''", later, "meaning-mismatch"),
        RenderFault("omit-product-filter", target, "if ($select->where !== null)", "if (false)", filtered, "meaning-mismatch"),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])
