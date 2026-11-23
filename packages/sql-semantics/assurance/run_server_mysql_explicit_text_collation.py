"""Compare explicit MySQL text collation and coercibility by result position."""

from itertools import product
import json
from random import Random
import sys

from run import TOOL, Failure, digest, require
from run_server import CONTRACT as PROJECTION, same
from run_server_join import OBSERVER, main
from run_server_mysql_varchar_assignment import fixture_literal
from run_server_mysql_varchar_comparison import comparison, text
from run_server_mysql_varchar_inheritance import HEADING, ROWS, TYPES
from run_server_mysql_varchar_slots import COLLATION, DIAGNOSTICS, SCHEMA, field, project, query
from server_cases import observation as rows_observation
from server_truth_model import admits, predicate_value
from sqlite_domain import RenderFault

LABEL = "mysql-explicit-text-collation"
CONTRACT = "2d68bc937da34126ae9d7ec99613c713f4ce7831657af06834d539d758a22a07"
NAME = "ir_varchar_explicit"
TARGET = f"{SCHEMA}.{NAME}"
DECLARATION = (f"CREATE TABLE {TARGET} (label VARCHAR(8) COLLATE utf8mb4_bin, "
               "alternate VARCHAR(8) COLLATE utf8mb4_bin NOT NULL, id INT) ENGINE=InnoDB")


def specifications():
    path, profile = TOOL / "mysql-explicit-text-collation-v1.json", TOOL / "server-projection-v1.json"
    require(digest(path) == CONTRACT and digest(profile) == PROJECTION, "mysql-explicit-collation-contract-mismatch")
    return json.loads(path.read_text()), {"mysql": json.loads(profile.read_text())["profiles"]["mysql"]}


def observer_request(_item, sql):
    return {"sql": sql, "observeMysqlTextCollation": True}


def prepare(_engine, db):
    values = ", ".join("(" + ", ".join(map(fixture_literal, row)) + ")" for row in ROWS)
    fixture = db.request({"fixture": [f"DROP TABLE IF EXISTS {TARGET}", DECLARATION, f"INSERT INTO {TARGET} VALUES {values}"]})
    data = db.request({"sql": f"SELECT label, alternate, id FROM {TARGET}"})
    control = db.request(observer_request(None, f"SELECT label COLLATE utf8mb4_bin AS a, label AS a FROM {TARGET} WHERE FALSE"))
    return {"fixture": fixture, "data": rows_observation(data), "metadata": control}


def check_preparation(engine, _environment, actual):
    require(engine == "mysql", "mysql-explicit-collation-engine", engine)
    same({"status": "fixture"}, actual["fixture"])
    same(rows_observation({"status": "rows", "fields": [["label", "VAR_STRING"], ["alternate", "VAR_STRING"], ["id", "LONG"]],
                           "rows": ROWS}), actual["data"])
    same({"status": "rows", "fields": [["a", "VAR_STRING"], ["a", "VAR_STRING"]], "rows": [],
          "textMetadata": [["utf8mb4", COLLATION, 0], ["utf8mb4", COLLATION, 2]]}, actual["metadata"])


def source():
    return {"schema": SCHEMA, "table": NAME, "columns": list(HEADING), "columnTypes": list(TYPES)}


def collate(term):
    return ["collated-text", COLLATION, term]


def place(term, placement, peer, operator="<"):
    if placement == "result":
        return term
    left, right = (term, peer) if placement == "compare-left" else (peer, term)
    return comparison(operator, left, right)


def select(tag, outputs, where=None, physical=True):
    return {**project(tag, source() if physical else None, outputs), "where": where}


def finite(tag, operand, placement, selection, literal):
    leaf = field(0) if operand == "field" else text(literal)
    root = place(collate(leaf), placement, text("A"))
    witness = collate(text(literal))
    return select(tag, [[root, "result"], [witness, "literal"]], None if selection == "all" else ["boolean", False])


def finite_ids(_, spec, _tag):
    b = spec["finite"]
    identities = set(product(b["operand"], b["placement"], b["filter"], b["literalValue"]))
    require(len(identities) == b["identities"], "mysql-explicit-collation-finite-size", len(identities))
    return identities


def finite_id(_, actual, _item):
    (first, _), (witness, _) = actual["columns"]
    if first[0] == "collated-text":
        placement, explicit = "result", first
    else:
        require(first[:3] == ["text-comparison", "<", COLLATION], "mysql-explicit-collation-finite-comparison", first)
        placement, explicit = ("compare-left", first[3]) if first[3][0] == "collated-text" else ("compare-right", first[4])
    require(explicit[:2] == ["collated-text", COLLATION] and witness[:2] == explicit[:2], "mysql-explicit-collation-finite-type", actual)
    return ("field" if explicit[2][0] == "text-slot" else "literal", placement,
            "all" if actual["where"] is None else "empty", witness[2][1])


def analyzed(tag):
    yield "SELECT 'a ' COLLATE utf8mb4_bin AS value", select(tag, [[collate(text("a ")), "value"]], physical=False)
    yield f"SELECT r.label COLLATE utf8mb4_bin AS value FROM {TARGET} r", select(tag, [[collate(field(0)), "value"]])
    left, right = collate(text("a ")), text("a")
    yield ("SELECT ('a ' COLLATE utf8mb4_bin) = 'a' AS value",
           select(tag, [[comparison("=", left, right), "value"]], physical=False))
    yield (f"SELECT 'a ' < (r.label COLLATE utf8mb4_bin) AS value FROM {TARGET} r",
           select(tag, [[comparison("<", text("a "), collate(field(0))), "value"]]))
    test = comparison("<=>", collate(field(0)), ["null"])
    yield (f"SELECT alternate COLLATE utf8mb4_bin AS value FROM {TARGET} WHERE label COLLATE utf8mb4_bin <=> NULL",
           select(tag, [[collate(field(1)), "value"]], test))


def negative(tag):
    scope = "A VARCHAR slot must belong to a visible physical input occurrence."
    yield select(tag, [[collate(field(2)), "value"]]), scope
    yield select(tag, [[collate(field(0, 1)), "value"]]), scope
    yield query(tag, "SELECT 'a' COLLATE utf8mb4_general_ci AS value", []), "The explicit MySQL text collation must be utf8mb4_bin."
    yield query(tag, "SELECT 1 COLLATE utf8mb4_bin AS value", []), "An explicit MySQL text collation requires a native VARCHAR field or text literal."
    yield select(tag, [[comparison("=", text("a"), text("A")), "value"]], physical=False), "A text comparison needs a native VARCHAR field or explicit collation."


def seeded(tag, seed, budget):
    rng = Random(seed)
    texts = ("a", "A", "a ", "", "𐐷", "a\x00", "é", "a'b")
    for index in range(budget):
        physical = index % 4 != 0
        leaves = (field(0), field(1)) if physical else ()
        outputs = []
        for position in range(index % 3 + 1):
            explicit = collate(rng.choice([*(text(value) for value in texts), *leaves]))
            output = place(explicit, rng.choice(("result", "compare-left", "compare-right")),
                           rng.choice((text("a"), text("A "), ["null"], *leaves)), rng.choice(("=", "<", "<=>")))
            outputs.append([output, "value" if index % 2 else f"r{position}"])
        restriction = comparison("<=>", collate(field(0)), ["null"]) if physical and index % 5 == 1 else None
        yield select(tag, outputs, restriction, physical)


def requests(_, tag, spec):
    b = spec["finite"]
    for index, vector in enumerate(product(b["operand"], b["placement"], b["filter"], b["literalValue"])):
        item = finite(tag, *vector)
        yield f"finite/{index}", item, item, None
    inputs = list(analyzed(tag))
    require(len(inputs) == len(spec["analysis"]), "mysql-explicit-collation-analysis-incomplete")
    for index, (sql, item) in enumerate(inputs):
        yield f"analysis/{index}", query(tag, sql, [DECLARATION]), item, None
    for seed in spec["exploration"]["seeds"]:
        for index, item in enumerate(seeded(tag, seed, spec["exploration"]["casesPerSeed"])):
            yield f"seed/{seed}/{index}", item, item, None
    for index, (item, message) in enumerate(negative(tag)):
        yield f"negative/{index}", item, None, {"exception": DIAGNOSTICS + "InvalidConstruction", "message": message}


def address(term, table):
    name = table["columns"][term[1]]
    require(term[2] == 0 and table["columnTypes"][term[1]] == TYPES[HEADING.index(name)]
            and TYPES[HEADING.index(name)] != "int", "mysql-explicit-collation-field-role", [term, table])
    return name


def graph(item):
    table = item["source"]

    def resolve(node):
        if node is None:
            return None
        role, *parts = node
        if role == "text-slot":
            return role, address(node, table)
        if role == "text-comparison":
            return role, parts[0], parts[1], resolve(parts[2]), resolve(parts[3])
        if role == "collated-text":
            return role, parts[0], resolve(parts[1])
        require(role in ("text", "boolean", "null"), "mysql-explicit-collation-role", node)
        return tuple(node)

    return (None if table is None else (table["schema"], table["table"]),
            tuple((resolve(term), name) for term, name in item["columns"]), resolve(item["where"]))


def check_value(item, actual):
    require(actual["status"] == "constructed" and actual["profile"].startswith(item["release"] + "/"),
            "mysql-explicit-collation-positive-rejected", actual)
    same(graph(item), graph(actual))


def expected(item):
    source_ = item["source"]

    def atom(node, row):
        if node[0] == "collated-text":
            require(node[1] == COLLATION, "mysql-explicit-collation-oracle-role", node)
            return atom(node[2], row)
        if node[0] == "null":
            return None
        if node[0] == "text":
            return node[1]
        return row[HEADING.index(address(node, source_))]

    def value(term, row):
        if term[0] == "text-comparison":
            truth = predicate_value(term, lambda operand: atom(operand, row))
            return None if truth is None else int(truth)
        return atom(term, row)

    layout = item["columns"]
    pool = [()] if source_ is None else ROWS
    profile = [["utf8mb4", COLLATION, 0] if term[0] == "collated-text" else ["binary", "binary", 5] for term, _ in layout]
    raw = {"status": "rows", "fields": [[name, "VAR_STRING" if term[0] == "collated-text" else "LONGLONG"] for term, name in layout],
           "rows": [[value(term, row) for term, _ in layout]
                    for row in pool if admits(item["where"], lambda operand: atom(operand, row))]}
    return {**rows_observation(raw), "textMetadata": profile}


def observation(raw):
    if raw["status"] != "rows":
        return raw
    return {**rows_observation(raw), "textMetadata": raw["textMetadata"]}


def check_observation(item, actual, sql):
    wanted = expected(item)
    witness = {"sql": sql, "expected": wanted, "observed": actual}
    require(actual["status"] == "rows", "mysql-explicit-collation-native-rejection", witness)
    require(wanted == actual, "meaning-mismatch", witness)


def check_fault_witness(item, witness):
    same(expected(item), witness["expected"])
    require(witness["observed"]["status"] == "rows", "mysql-explicit-collation-fault-native")


def sample_forms(forms, observed, *_):
    source_, columns, where = graph(observed)
    forms["source/table" if source_ else "source/none"] += 1
    if where:
        forms["placement/where"] += 1
    if len({label for _, label in columns}) < len(columns):
        forms["label/repeated"] += 1

    def visit(term, placement):
        if term[0] == "collated-text":
            forms["placement/" + placement] += 1
            forms["operand/" + term[2][0]] += 1
            if term[2][0] == "text" and any(ord(char) > 127 for char in term[2][1]):
                forms["operand/unicode"] += 1
        elif term[0] == "text-comparison":
            forms["operator/" + term[1]] += 1
            visit(term[3], "left")
            visit(term[4], "right")

    for term, _ in columns:
        visit(term, "result")


def required_forms(*_):
    return {"source/table", "source/none", "label/repeated", "placement/where", "placement/result", "placement/left", "placement/right",
            "operand/text", "operand/text-slot", "operand/unicode", "operator/=", "operator/<", "operator/<=>"}


def controls():
    item = finite("mysql-9.1.0", "field", "result", "empty", "a ")
    wanted = expected(item)
    wrong = {**wanted, "textMetadata": [["utf8mb4", COLLATION, 2], wanted["textMetadata"][1]]}
    check_observation(item, wanted, "empty result inherits its explicit coercibility")
    try:
        check_observation(item, wrong, "replace explicit coercibility with the physical default")
    except Failure as error:
        require(error.signal == "meaning-mismatch", "mysql-explicit-collation-wrong-control", error.detail)
    else:
        raise Failure("mysql-explicit-collation-comparator-accepted")
    return {"input": item, "expected": wanted, "mismatch": wrong}


def faults(_, tag):
    renderer = "sql-semantics-mysql/src/Ir/SelectSql.php"
    comparison_render = "$left . ' ' . $comparison->operator->value . ' ' . $right"
    metadata = finite(tag, "field", "result", "empty", "a ")
    return [
        RenderFault("drop-explicit-varchar-collation", renderer, " . ' COLLATE ' . $expression->collation->value",
                    "", metadata, "meaning-mismatch"),
        RenderFault("change-explicit-varchar-collation", renderer, "$expression->collation->value",
                    "'utf8mb4_general_ci'", metadata, "meaning-mismatch"),
        RenderFault("select-wrong-explicit-varchar-field", renderer, "$input->columns[$expression->slot]->quoted()",
                    "$input->columns[$expression instanceof TextColumnReference ? 1 : $expression->slot]->quoted()",
                    finite(tag, "field", "result", "all", "a "), "meaning-mismatch"),
        RenderFault("swap-explicit-varchar-comparison", renderer, comparison_render,
                    "$right . ' ' . $comparison->operator->value . ' ' . $left",
                    finite(tag, "field", "compare-left", "all", "a "), "meaning-mismatch"),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])
