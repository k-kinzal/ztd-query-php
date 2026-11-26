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
CONTRACT = "b6d00deef43c26922855c4386a15544fb09caced9b6ac619b2fb1b85dce1527a"
SMALL = {"schema": "public", "table": "ir_list_small", "columns": ["id", "delta"], "only": False}
SMALL_DECLARATION = "CREATE TABLE public.ir_list_small (id INTEGER, delta INTEGER)"
REFUSALS = "SqlSemantics\\Platform\\PostgreSql\\Model\\Diagnostic\\"


def specifications():
    path, profile = TOOL / "postgres-from-list-v2.json", TOOL / "server-projection-v1.json"
    require(digest(path) == CONTRACT and digest(profile) == PROJECTION, "postgres-from-list-contract-mismatch")
    return json.loads(path.read_text()), {"postgres": json.loads(profile.read_text())["profiles"]["postgres"]}


def prepare(_, db):
    result = db.request({"fixture": [*fixture("postgres"), "DROP TABLE IF EXISTS public.ir_list_small",
                                     SMALL_DECLARATION, "INSERT INTO public.ir_list_small VALUES (-4,81),(2,82)"]})
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


def finite(tag, width, policy, ending, projection, filter_kind, last_item):
    tail = relation("empty" if ending == "empty-last" else "independent")
    prefix = [relation("accounts", policy == "only"), *(relation("small") for _ in range(width - 2))]
    if last_item != "table":
        tail = join("LEFT" if last_item == "left-join" else "RIGHT", relation("small"), tail,
                    compare("=", slot(0), slot(0, 1)))
    inputs = [*prefix, tail]
    occurrences = len(prefix) + len(leaves(tail))
    columns = fields(range(occurrences) if projection == "all" else (occurrences - 1,))
    where = None if filter_kind == "absent" else compare("<", slot(0), slot(1, occurrences - 1))
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
    eq = compare("=", slot(0), slot(0, 1))
    yield f"SELECT * FROM {accounts} a, public.ir_list_small b LEFT JOIN {independent} c ON b.id=c.id", product_case(
        tag, [a, join("LEFT", s, b, eq)], fields(range(3)))
    yield f"SELECT a.*,b.*,c.* FROM {accounts} a, public.ir_list_small b RIGHT JOIN {independent} c ON b.id=c.id", product_case(
        tag, [a, join("RIGHT", s, b, eq)], fields(range(3)))
    yield f"SELECT * FROM {accounts} a, (public.ir_list_small b LEFT JOIN {independent} c ON b.id=c.id) RIGHT JOIN {accounts} d ON c.id=d.id", product_case(
        tag, [a, join("RIGHT", join("LEFT", s, b, eq), a, compare("=", slot(0, 1), slot(0, 2)))], fields(range(4)))


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
        (f"SELECT 1 FROM {small} a, {small} b LEFT JOIN {small} c ON a.id=c.id", "SemanticException", "a.id"),
        (f"SELECT 1 FROM {small} a, {small} b NATURAL JOIN {small} c", "InvalidConstruction", "cannot be nested"),
    ):
        yield analysis_request(tag, sql), {"exception": REFUSALS + error, "message": message}


def requests(_, tag, spec):
    bounds = spec["finite"]
    for index, dimensions in enumerate(product(*(bounds[key] for key in ("widths", "policies", "inputs", "projections", "filters", "lastItem")))):
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
            if width >= 3 and randomizer.getrandbits(1):
                kind = randomizer.choice(("LEFT", "RIGHT"))
                nested = join(kind, inputs[-2], inputs[-1],
                              compare(randomizer.choice(("=", "<")), slot(randomizer.randrange(2)), slot(randomizer.randrange(2), 1)))
                inputs = [*inputs[:-2], nested]
            item = product_case(tag, inputs, columns, where)
            yield f"seed/{seed}/{index}", item, item, None
    for index, (request, boundary) in enumerate(negatives(tag)):
        yield f"negative/{index}", request, None, boundary


def graph(item):
    root = item["source"]
    inputs = leaves(root)

    def field(term, scope):
        require(term[0] == "slot", "postgres-from-list-unexpected-expression", term)
        occurrence = term[2] if len(term) == 3 else 0
        return occurrence, scope[occurrence]["columns"][term[1]]

    def predicate(term, scope):
        if term is None:
            return None
        require(term[0] == "comparison", "postgres-from-list-unexpected-condition", term)
        return term[0], term[1], field(term[2], scope), field(term[3], scope)

    def tree(node):
        if not is_join(node):
            return node["schema"], node["table"], node["only"]
        return node["kind"], tree(node["left"]), tree(node["right"]), predicate(node["on"], leaves(node))

    return tree(root), tuple((field(expression, inputs), name) for expression, name in item["columns"]), predicate(item["where"], inputs)


def finite_id(_, reply, _item):
    root = reply["source"]
    origins = leaves(root)
    last = root["right"]
    members = len(leaves(root["left"])) + 1
    last_item = last["kind"].lower() + "-join" if is_join(last) else "table"
    return (members, "only" if origins[0]["only"] else "inherit",
            "empty-last" if origins[-1]["table"] == NAMES_BY_TABLE["empty"] else "populated",
            "last" if len(reply["columns"]) == 2 else "all", "absent" if reply["where"] is None else "int4-comparison", last_item)


def finite_ids(_, spec, _tag):
    bounds = spec["finite"]
    identities = {(width, policy, ending, projection, condition, member)
                  for width in bounds["widths"] for policy in bounds["policies"] for ending in bounds["inputs"]
                  for projection in bounds["projections"] for condition in bounds["filters"] for member in bounds["lastItem"]}
    require(len(identities) == bounds["identities"], "wrong-postgres-from-list-domain", len(identities))
    return identities


def heading_and_rows(source):
    if source["table"] == SMALL["table"]:
        return SMALL["columns"], [(-4, 81), (2, 82)]
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
    tree, _, predicate = graph(reply)
    member = tree[2]
    forms[f"last/{member[0] if member[0] in ('LEFT', 'RIGHT') else 'table'}"] += 1
    if predicate is not None and predicate[2][0] != predicate[3][0]:
        forms["cross-occurrence-filter"] += 1


def required_forms(*_):
    return {*(f"width/{width}" for width in range(2, 7)), "same-table", "empty", "only/False", "only/True",
            "quoted-label", "cross-occurrence-filter", "last/table", "last/LEFT", "last/RIGHT"}


def controls():
    item = finite("pg-17.2", 3, "inherit", "populated", "all", "absent", "left-join")
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
    filtered = finite(tag, 3, "only", "populated", "all", "int4-comparison", "table")
    extended = finite(tag, 2, "only", "empty-last", "all", "absent", "left-join")
    return [
        RenderFault("use-first-product-occurrence", target, "$this->alias($offset + $expression->occurrence)",
                    "$this->alias($offset)", later, "meaning-mismatch"),
        RenderFault("inherit-a-later-only-scan", target, "($source->only ? 'ONLY ' : '')", "''", later, "meaning-mismatch"),
        RenderFault("omit-product-filter", target, "if ($select->where !== null)", "if (false)", filtered, "meaning-mismatch"),
        RenderFault("flatten-null-extending-member", target, "$source->kind->value . ' JOIN '",
                    "($source->kind->value === 'LEFT' ? 'INNER' : $source->kind->value) . ' JOIN '", extended, "meaning-mismatch"),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])
