"""Compare native MySQL VARCHAR NULL tests and placement with independent row semantics."""

from itertools import product
import json
from random import Random
import sys

from run import TOOL, Failure, digest, require
from run_server import CONTRACT as PROJECTION, same
from run_server_join import OBSERVER, main, observer_request
from run_server_mysql_varchar_slots import (
    COLLATION, DECLARATIONS, DIAGNOSTICS, HEADING, LENGTHS, PRIMARY, SCHEMA, SECONDARY,
    check_preparation as check_fixture, check_value, field, prepare as prepare_fixture, project, query,
    row_stream, table, value,
)
from server_cases import observation
from server_join_cases import compare, join, slot
from server_truth_model import admits, predicate_value
from sqlite_domain import RenderFault

LABEL = "mysql-varchar-presence"
CONTRACT = "c95df7be6ac93b7b423036791a4b967515d549f48002a342d67bcab4165c1e66"
JOINS = {"left": "LEFT", "right": "RIGHT"}


def specifications():
    path, profile = TOOL / "mysql-varchar-presence-v1.json", TOOL / "server-projection-v1.json"
    require(digest(path) == CONTRACT and digest(profile) == PROJECTION, "mysql-varchar-presence-contract-mismatch")
    return json.loads(path.read_text()), {"mysql": json.loads(profile.read_text())["profiles"]["mysql"]}


def prepare(engine, db):
    probe = f"SELECT label IS NULL AS missing, label IS NOT NULL AS present FROM {SCHEMA}.{PRIMARY} WHERE id = 8"
    return {"fixture": prepare_fixture(engine, db), "probe": db.request({"sql": probe})}


def check_preparation(engine, environment, prepared):
    check_fixture(engine, environment, prepared["fixture"])
    same({"status": "rows", "fields": [["missing", "LONGLONG"], ["present", "LONGLONG"]], "rows": [[1, 0]]},
         prepared["probe"])


def null_test(operand, negated=False):
    return ["null-test", negated, operand]


def logical(operator, left, right=None):
    return ["logic", operator, left, right]


def placed(tag, source, placement, predicate, *, other=None):
    lhs = table()
    rhs = table(SECONDARY) if source in JOINS else None
    fields = [[slot(1), "left_id"]]
    condition = predicate if placement == "where" else None
    if rhs is not None:
        keys = compare("=", slot(1), slot(1, 1))
        lhs = join(JOINS[source], lhs, rhs, logical("AND", keys, predicate) if placement == "on" else keys)
        fields.append([field(0, 1), "right_text"])
    if placement == "result":
        fields.append([predicate, "test"])
    if other is not None:
        condition = other if condition is None else logical("OR", condition, other)
    return {**project(tag, lhs, fields), "where": condition}


def finite(tag, source, placement, operand, polarity):
    owner = 0 if source == "ordinary" else 1
    return placed(tag, source, placement, null_test(field(HEADING.index(operand), owner), polarity == "not-null"))


def finite_ids(_, specification, _tag):
    b = specification["finite"]
    identities = {(source, placement, operand, polarity)
                  for source, placement, operand, polarity in product(b["source"], b["placement"], b["operands"], b["polarity"])
                  if (source, placement) != ("ordinary", "on")}
    require(len(identities) == b["identities"], "mysql-varchar-presence-finite-size", len(identities))
    return identities


def finite_id(_, response, _item):
    root = response["source"]
    source = root["kind"].lower() if "kind" in root else "ordinary"
    if response["columns"][-1][0][0] == "null-test":
        placement, predicate = "result", response["columns"][-1][0]
    elif response["where"] is not None:
        placement, predicate = "where", response["where"]
    else:
        placement, predicate = "on", root["on"][3]
    test, polarity, (role, index, occurrence) = predicate
    require(test == "null-test" and role == "text-slot" and occurrence == int(source != "ordinary"),
            "mysql-varchar-presence-invalid-finite-role", predicate)
    owner = root["right"] if occurrence else root
    require(owner["columnTypes"][index][0] == "varchar", "mysql-varchar-presence-finite-kind", owner)
    return source, placement, owner["columns"][index], "not-null" if polarity else "null"


def analyzed(tag):
    left, right = f"{SCHEMA}.{PRIMARY}", f"{SCHEMA}.{SECONDARY}"
    yield (f"SELECT label IS NOT NULL AS present FROM {left}",
           project(tag, table(columns=("label",)), [[null_test(field(0), True), "present"]]))
    item = project(tag, table(columns=("alternate", "id")), [[slot(1), "id"]])
    yield f"SELECT id FROM {left} WHERE alternate IS NULL", {**item, "where": null_test(field(0))}
    scope = join("LEFT", table(columns=("label", "id")), table(SECONDARY, ("label", "id", "alternate")),
                 logical("AND", compare("=", slot(1), slot(1, 1)), null_test(field(2, 1), True)))
    yield (f"SELECT a.label AS left_text, b.label AS right_text FROM {left} a LEFT JOIN {right} b "
           "ON a.id = b.id AND b.alternate IS NOT NULL",
           project(tag, scope, [[field(0), "left_text"], [field(0, 1), "right_text"]]))
    simple = null_test(field(0))
    yield (f"SELECT ((label IS NULL) IS NOT NULL) AS checked, (t.label IS NULL) AS missing FROM {left} t",
           project(tag, table(columns=("label",)), [[null_test(simple, True), "checked"], [simple, "missing"]]))


def negative(tag):
    diagnostic = "A VARCHAR slot must belong to a visible physical input occurrence."
    yield placed(tag, "ordinary", "result", null_test(field(1))), diagnostic
    yield placed(tag, "ordinary", "where", null_test(field(0, 1))), diagnostic
    yield placed(tag, "left", "on", null_test(field(0, 2))), diagnostic
    sql = f"SELECT label IS NULL AS missing FROM {SCHEMA}.{PRIMARY}"
    unsupported = DECLARATIONS[0].replace(COLLATION, "utf8mb4_general_ci")
    yield query(tag, sql, [unsupported]), "The text slot requires an explicit utf8mb4_bin collation."


def seeded(tag, seed, budget):
    rng = Random(seed)
    names = tuple(LENGTHS)
    forms = [(source, placement) for source in ("ordinary", "left", "right")
             for placement in ("result", "where", "on") if (source, placement) != ("ordinary", "on")]
    rng.shuffle(forms)

    def choose(source, depth):
        occurrence = rng.randrange(2) if source in JOINS else 0
        leaf = null_test(field(HEADING.index(rng.choice(names)), occurrence), bool(rng.getrandbits(1)))
        if depth == 0:
            return leaf
        symbol = rng.choice(("AND", "OR", "XOR", "NOT", "test"))
        if symbol == "test":
            return null_test(leaf, bool(rng.getrandbits(1)))
        return logical(symbol, leaf, None if symbol == "NOT" else choose(source, depth - 1))

    for index in range(budget):
        source, placement = forms[index % len(forms)]
        term = choose(source, 0 if placement == "result" else rng.randrange(4))
        extra = choose(source, 1) if rng.randrange(3) == 0 else None
        yield placed(tag, source, placement, term, other=extra)


def requests(_, tag, specification):
    b = specification["finite"]
    coordinates = ((s, p, op, polarity) for s, p, op, polarity in product(b["source"], b["placement"], b["operands"], b["polarity"])
                   if (s, p) != ("ordinary", "on"))
    for index, coordinate in enumerate(coordinates):
        item = finite(tag, *coordinate)
        yield f"finite/{index}", item, item, None
    cases = list(analyzed(tag))
    require(len(cases) == len(specification["analysis"]), "mysql-varchar-presence-analysis-incomplete")
    for index, (sql, item) in enumerate(cases):
        yield f"analysis/{index}", query(tag, sql), item, None
    for seed in specification["exploration"]["seeds"]:
        for index, item in enumerate(seeded(tag, seed, specification["exploration"]["casesPerSeed"])):
            yield f"seed/{seed}/{index}", item, item, None
    for index, (item, message) in enumerate(negative(tag)):
        yield f"negative/{index}", item, None, {"exception": DIAGNOSTICS + "InvalidConstruction", "message": message}


def expected(item):
    fields = item["columns"]

    def resolve(role, row):
        if role[0] == "null-test":
            return int(predicate_value(role, lambda operand: value(operand, row)))
        return value(role, row)

    kinds = {"slot": "LONG", "text-slot": "VAR_STRING", "null-test": "LONGLONG"}
    return observation({"status": "rows", "fields": [[name, kinds[role[0]]] for role, name in fields],
                        "rows": [[resolve(role, row) for role, _ in fields] for row in row_stream(item["source"])
                                 if admits(item["where"], lambda operand: value(operand, row))]})


def check_observation(item, actual, sql):
    prediction = expected(item)
    witness = {"sql": sql, "expected": prediction, "observed": actual}
    require(actual["status"] == "rows", "mysql-varchar-presence-native-rejection", witness)
    require(prediction == actual, "meaning-mismatch", witness)


def check_fault_witness(item, witness):
    same(expected(item), witness["expected"])
    require(witness["observed"]["status"] == "rows", "mysql-varchar-presence-fault-native")


def sample_forms(forms, observed, *_):
    def visit(term, owner, depth=0):
        if term is None:
            return
        if term[0] == "text-slot":
            forms[f"occurrence/{term[2]}"] += 1
        elif term[0] == "null-test":
            forms["owner/" + owner] += 1
            forms[f"polarity/{term[1]}"] += 1
            if depth:
                forms["nested"] += 1
            visit(term[2], owner, depth + 1)
        elif term[0] == "logic":
            forms["logic/" + term[1]] += 1
            visit(term[2], owner, depth + 1)
            visit(term[3], owner, depth + 1)

    source = observed["source"]
    forms["source/" + source.get("kind", "ordinary").lower()] += 1
    for term, _ in observed["columns"]:
        visit(term, "result")
    visit(observed["where"], "where")
    visit(source.get("on"), "on")


def required_forms(*_):
    return {"occurrence/0", "occurrence/1", "polarity/True", "polarity/False", "nested",
            "source/ordinary", "source/left", "source/right", "owner/result", "owner/where", "owner/on",
            "logic/AND", "logic/OR", "logic/XOR", "logic/NOT"}


def controls():
    item = finite("mysql-9.1.0", "ordinary", "result", "label", "null")
    wanted = expected(item)
    changed = {**wanted, "rows": [[*row[:-1], 1 - row[-1]] for row in wanted["rows"]]}
    check_observation(item, wanted, "known native text presence")
    try:
        check_observation(item, changed, "invert each native truth value")
    except Failure as failure:
        require(failure.signal == "meaning-mismatch", "mysql-varchar-presence-control-failure", failure.detail)
    else:
        raise Failure("mysql-varchar-presence-comparator-accepted")
    return {"input": item, "expected": wanted, "mismatch": changed}


def faults(_, tag):
    renderer = "sql-semantics-mysql/src/Ir/SelectSql.php"
    sample = finite(tag, "ordinary", "result", "label", "null")
    return [
        RenderFault("invert-varchar-presence", renderer, "$condition->negated ?",
                    "!$condition->negated ?", sample, "meaning-mismatch"),
        RenderFault("test-the-other-varchar-slot", renderer, "$input->columns[$expression->slot]->quoted()",
                    "$input->columns[$expression instanceof TextColumnReference ? 2 : $expression->slot]->quoted()",
                    sample, "meaning-mismatch"),
        RenderFault("test-the-first-varchar-occurrence", renderer, "$this->alias($offset + $expression->occurrence)",
                    "$this->alias($offset + ($expression instanceof TextColumnReference ? 0 : $expression->occurrence))",
                    finite(tag, "left", "on", "label", "not-null"), "meaning-mismatch"),
        RenderFault("drop-varchar-presence-filter", renderer, "if ($select->where !== null) {", "if (false) {",
                    finite(tag, "ordinary", "where", "label", "null"), "meaning-mismatch"),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])
