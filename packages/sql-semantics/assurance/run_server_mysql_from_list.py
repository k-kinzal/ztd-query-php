"""Observe MySQL comma precedence, operand-local joins and resolved result slots."""

from itertools import product
import json
import random
import sys

from run import TOOL, Failure, digest, require
from run_server import CONTRACT as PROJECTION, same
from run_server_join import OBSERVER, check_preparation, main, observer_request
from server_cases import observation
from server_join_cases import (
    DATA, NAMES_BY_TABLE, case, compare, declarations, expected as join_expected, fixture, is_join, join, leaves, slot, table,
)
from sqlite_domain import RenderFault

LABEL = "mysql-from-list"
CONTRACT = "840628765b42c335636c6d7eaf033a3db0b6eb63534d65351a1361ce0d7749f2"
NAMES = ("Id", "delta")
SMALL = {"schema": "ir_fixture", "table": "ir_list_small", "columns": list(NAMES)}
SMALL_DECLARATION = "CREATE TABLE ir_fixture.ir_list_small (Id INT, delta INT)"
DIAGNOSTICS = "SqlSemantics\\Platform\\MySql\\Model\\Diagnostic\\"


def specifications():
    path, profile = TOOL / "mysql-from-list-v1.json", TOOL / "server-projection-v1.json"
    require(digest(path) == CONTRACT and digest(profile) == PROJECTION, "mysql-from-list-contract-mismatch")
    return json.loads(path.read_text()), {"mysql": json.loads(profile.read_text())["profiles"]["mysql"]}


def prepare(_, db):
    result = db.request({"fixture": [*fixture("mysql"), "DROP TABLE IF EXISTS ir_fixture.ir_list_small",
                                     SMALL_DECLARATION, "INSERT INTO ir_fixture.ir_list_small VALUES (-4,81),(2,82)"]})
    same({"status": "fixture"}, result)
    return result


def relation(role):
    return SMALL if role == "small" else table("mysql", role)


def fields(occurrences):
    return [[slot(index, occurrence), name] for occurrence in occurrences for index, name in enumerate(NAMES)]


def product_tree(inputs):
    source = inputs[0]
    for item in inputs[1:]:
        source = join("CROSS", source, item)
    return source


def finite(tag, prefix, last, final_table, projection, filter_kind):
    tail = relation("empty" if final_table == "empty" else "independent")
    members = [relation("accounts"), *(relation("small") for _ in range(prefix - 1))]
    if last != "base":
        tail = join("LEFT" if last == "left-join" else "RIGHT", relation("small"), tail, compare("=", slot(0), slot(0, 1)))
    source = product_tree([*members, tail])
    width = len(leaves(source))
    columns = fields(range(width) if projection == "all" else (width - 1,))
    where = None if filter_kind == "absent" else compare("<", slot(0), slot(1, width - 1))
    return case("mysql", tag, source, columns, where)


def analysis_request(tag, sql):
    return {"dialect": "mysql", "release": tag, "sql": sql, "declarations": [*declarations("mysql"), SMALL_DECLARATION]}


def analyzed(tag):
    accounts, independent, empty = (f"ir_fixture.{NAMES_BY_TABLE[role]}" for role in ("accounts", "independent", "empty"))
    a, b, e, s = (relation(role) for role in ("accounts", "independent", "empty", "small"))
    small = "ir_fixture.ir_list_small"
    yield f"SELECT * FROM {accounts} a, {small} b", case("mysql", tag, product_tree([a, s]), fields(range(2)))
    yield f"SELECT c.* FROM {accounts} a, {independent} b, {accounts} c", case(
        "mysql", tag, product_tree([a, b, a]), fields((2,)))
    yield f"SELECT b.delta AS right_delta,a.delta AS left_delta FROM {accounts} a, {accounts} b WHERE a.Id<b.Id", case(
        "mysql", tag, product_tree([a, a]), [[slot(1, 1), "right_delta"], [slot(1), "left_delta"]], compare("<", slot(0), slot(0, 1)))
    yield f"SELECT * FROM {accounts} a, {independent} b, {empty} c", case(
        "mysql", tag, product_tree([a, b, e]), fields(range(3)))
    eq = compare("=", slot(0), slot(0, 1))
    yield f"SELECT * FROM {accounts} a, {small} b LEFT JOIN {independent} c ON b.Id=c.Id", case(
        "mysql", tag, join("CROSS", a, join("LEFT", s, b, eq)), fields(range(3)))
    yield f"SELECT a.Id AS first_id,b.Id AS middle_id,c.Id AS last_id FROM {accounts} a, {small} b RIGHT JOIN {independent} c ON b.Id=c.Id WHERE a.Id<c.delta", case(
        "mysql", tag, join("CROSS", a, join("RIGHT", s, b, eq)), [[slot(0, i), label] for i, label in enumerate(("first_id", "middle_id", "last_id"))],
        compare("<", slot(0), slot(1, 2)))
    yield f"SELECT * FROM ({accounts} a, {small} b) LEFT JOIN {independent} c ON a.Id=c.Id", case(
        "mysql", tag, join("LEFT", product_tree([a, s]), b, compare("=", slot(0), slot(0, 2))), fields(range(3)))
    yield f"SELECT a.*,c.* FROM {accounts} a, ({small} b, {independent} c)", case(
        "mysql", tag, join("CROSS", a, product_tree([s, b])), fields((0, 2)))


def negatives(tag):
    sample = finite(tag, 1, "base", "populated", "all", "absent")
    sample["columns"][0][0] = slot(0, 2)
    yield sample, {"exception": DIAGNOSTICS + "InvalidConstruction", "message": "must belong to the SELECT source"}
    small = "ir_fixture.ir_list_small"
    too_many = ", ".join(f"{small} a{i}" for i in range(62))
    for sql, error, message in (
        (f"SELECT 1 FROM {small} a, {small} b LEFT JOIN {small} c ON a.Id=c.Id", "SemanticException", "a.Id"),
        (f"SELECT Id FROM {small} a, {small} b", "SemanticException", "Id"),
        (f"SELECT a.Id FROM ir_fixture.absent a, {small} b", "SemanticException", "absent"),
        (f"SELECT 1 FROM {too_many}", "InvalidConstruction", "at most 61 input occurrences"),
    ):
        yield analysis_request(tag, sql), {"exception": DIAGNOSTICS + error, "message": message}


def requests(_, tag, spec):
    bounds = spec["finite"]
    for index, dimensions in enumerate(product(*(bounds[key] for key in ("prefixWidths", "lastItems", "lastTable", "projection", "filter")))):
        item = finite(tag, *dimensions)
        yield f"finite/{index}", item, item, None
    cases = list(analyzed(tag))
    require(len(cases) == len(spec["analysis"]), "mysql-from-list-analysis-incomplete")
    for index, (sql, item) in enumerate(cases):
        yield f"analyze/{index}", analysis_request(tag, sql), item, None
    for seed in spec["exploration"]["seeds"]:
        rng = random.Random(seed)
        for index in range(spec["exploration"]["casesPerSeed"]):
            width = rng.randint(3, 5)
            roles = rng.choices(("accounts", "independent", "empty"), k=2) + ["small"] * (width - 2)
            rng.shuffle(roles)
            members = [relation(role) for role in roles]
            kind = rng.choice(("CROSS", "LEFT", "RIGHT"))
            nested = join(kind, members[-2], members[-1], None if kind == "CROSS" else compare(
                rng.choice(("=", "<")), slot(rng.randrange(2)), slot(rng.randrange(2), 1)))
            source = product_tree([*members[:-2], nested])
            values = [[slot(rng.randrange(2), rng.randrange(width)), rng.choice(("id", "値", "a ` label"))]
                      for _ in range(rng.randint(1, 6))]
            where = rng.choice((None, compare("<", slot(1), slot(0, width - 1))))
            item = case("mysql", tag, source, values, where)
            yield f"seed/{seed}/{index}", item, item, None
    for index, (item, boundary) in enumerate(negatives(tag)):
        yield f"negative/{index}", item, None, boundary


def graph(item):
    def field(term, inputs):
        require(term[0] == "slot", "mysql-from-list-wrong-projection-type", term)
        occurrence = term[2] if len(term) == 3 else 0
        return occurrence, inputs[occurrence]["columns"][term[1]]

    def predicate(term, inputs):
        if term is None:
            return None
        require(term[0] == "comparison", "mysql-from-list-wrong-predicate-role", term)
        return term[0], term[1], field(term[2], inputs), field(term[3], inputs)

    def tree(node):
        if not is_join(node):
            return node["schema"], node["table"]
        return node["kind"], tree(node["left"]), tree(node["right"]), predicate(node["on"], leaves(node))

    root, inputs = item["source"], leaves(item["source"])
    return tree(root), tuple((field(term, inputs), label) for term, label in item["columns"]), predicate(item["where"], inputs)


def finite_id(_, reply, _item):
    root = reply["source"]
    last = root["right"]
    prefix = len(leaves(root["left"]))
    last_item = last["kind"].lower() + "-join" if is_join(last) else "base"
    ending = "empty" if leaves(last)[-1]["table"] == NAMES_BY_TABLE["empty"] else "populated"
    return prefix, last_item, ending, "last" if len(reply["columns"]) == 2 else "all", "absent" if reply["where"] is None else "compare"


def finite_ids(_, spec, _tag):
    b = spec["finite"]
    identities = {(w, i, t, p, f) for w in b["prefixWidths"] for i in b["lastItems"] for t in b["lastTable"]
                  for p in b["projection"] for f in b["filter"]}
    require(len(identities) == b["identities"], "wrong-mysql-from-list-domain", len(identities))
    return identities


def heading_and_rows(source):
    if source["table"] == SMALL["table"]:
        return NAMES, [(-4, 81), (2, 82)]
    role = next(role for role, table_name in NAMES_BY_TABLE.items() if source["table"] == table_name)
    return NAMES, DATA[role]


def expected(item):
    return join_expected(item, heading_and_rows)


def check_value(item, reply):
    require(reply["status"] == "constructed", "mysql-from-list-positive-rejected", reply)
    require(reply["profile"].startswith(item["release"] + "/") and graph(reply) == graph(item),
            "mysql-from-list-graph-differs", {"expected": item, "actual": reply})


def check_observation(item, actual, sql):
    prediction = expected(item)
    require(actual["status"] == "rows", "mysql-from-list-native-rejection", {"sql": sql, "actual": actual})
    require(prediction == actual, "meaning-mismatch", {"sql": sql, "expected": prediction, "observed": actual})


def check_fault_witness(item, witness):
    same(expected(item), witness["expected"])
    require(witness["observed"]["status"] == "rows", "mysql-from-list-fault-native")


def sample_forms(forms, reply, *_):
    tree, projected, where = graph(reply)
    inputs = leaves(reply["source"])
    tables = [leaf["table"] for leaf in inputs]
    forms[f"width/{len(inputs)}"] += 1
    forms[f"nested/{tree[2][0]}"] += 1
    if len(tables) != len(set(tables)):
        forms["repeated"] += 1
    if NAMES_BY_TABLE["empty"] in tables:
        forms["empty"] += 1
    if where is not None:
        forms["filter"] += 1
    if any("`" in label for _, label in projected):
        forms["quoted-label"] += 1


def required_forms(*_):
    return {"width/3", "width/4", "width/5", "nested/CROSS", "nested/LEFT", "nested/RIGHT", "repeated", "empty", "filter", "quoted-label"}


def controls():
    item = finite("mysql-8.0.44", 1, "left-join", "populated", "all", "absent")
    prediction = expected(item)
    check_observation(item, prediction, "known local LEFT result")
    altered = {**prediction, "rows": prediction["rows"][1:]}
    try:
        check_observation(item, altered, "missing retained row")
    except Failure as failure:
        require(failure.signal == "meaning-mismatch", "wrong-mysql-from-list-control", failure.detail)
    else:
        raise Failure("mysql-from-list-comparator-accepted")
    return {"healthy": prediction, "lostRow": altered}


def faults(_, tag):
    target = "sql-semantics-mysql/src/Ir/SelectSql.php"
    unfiltered = finite(tag, 1, "left-join", "empty", "all", "absent")
    filtered = finite(tag, 2, "base", "populated", "all", "compare")
    return [
        RenderFault("use-first-comma-occurrence", target, "$this->alias($offset + $expression->occurrence)", "$this->alias($offset)", unfiltered, "meaning-mismatch"),
        RenderFault("erase-local-left-extension", target, "$source->kind->value . ' JOIN '",
                    "($source->kind->value === 'LEFT' ? 'INNER' : $source->kind->value) . ' JOIN '", unfiltered, "meaning-mismatch"),
        RenderFault("omit-after-product-filter", target, "if ($select->where !== null)", "if (false)", filtered, "meaning-mismatch"),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])
