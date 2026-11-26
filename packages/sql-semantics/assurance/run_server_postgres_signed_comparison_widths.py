"""Compare typed PostgreSQL signed comparisons over nullable row occurrences."""

import json
import random
import sys
from itertools import product

from evidence_transcript import canonical
from run import TOOL, Failure, digest, require
from run_server import rejects, same
from run_server_join import main, probe
from run_server_postgres_boolean_fields import DIAGNOSTIC, query
from run_server_postgres_int8_fields import (
    DATA, DECLARATIONS, HEADINGS, LEFT, MAX, MIN, NATIVE_FIELDS, OBSERVER, OID, RIGHT,
    address, evaluate as scalar_value, field, observer_request, prepare as prepare_inputs,
    specifications as signed_fields, table, type_of as scalar_type,
)
from run_server_postgres_output_order_names import SEMANTIC
from server_join_cases import compare, is_join, join, leaves
from server_truth_model import predicate_value
from sqlite_domain import RenderFault

LABEL = "postgres-signed-comparison-widths"
CONTRACT = "edd544fd6c147c8188524cd2003d2bcd1bbc18cc28fd4cf382340667fb72c5f7"
COMPLETE_SEEDS = True
L, R = f"public.{LEFT}", f"public.{RIGHT}"
OPERATORS = ("=", "<>", "<", "<=", ">", ">=")


def specifications():
    path = TOOL / "postgres-signed-comparison-widths-v1.json"
    require(digest(path) == CONTRACT, "postgres-signed-comparison-contract-mismatch")
    return json.loads(path.read_text()), signed_fields()[1]


def native_request(tag, sql):
    return {"dialect": "postgres", "release": tag, "sql": sql, "declarations": DECLARATIONS}


def source_value(occurrence, native):
    physical = LEFT if occurrence == 0 else RIGHT
    column = "id" if native == "int4" else "low" if occurrence == 0 else "high"
    return field(physical, occurrence, column)


def literal(native, occurrence):
    return ["integer", "1" if occurrence == 0 else "2"] if native == "int4" else ["wide-integer", str(MIN if occurrence == 0 else MAX)]


def finite(tag, widths, operator, layout, operands):
    left_type, _, right_type = widths.partition("-")
    left = literal(left_type, 0) if operands == "left literal" else source_value(0, left_type)
    right = literal(right_type, 1) if operands == "right literal" else source_value(1, right_type)
    term = compare(operator, left, right)
    inputs = join("LEFT" if layout == "join" else "CROSS", table(LEFT), table(RIGHT), term if layout == "join" else None)
    fields = [[field(LEFT, 0, "id"), "first"], [field(RIGHT, 1, "id"), "second"]]
    if layout == "output":
        fields.insert(0, [term, "comparison"])
    return query(tag, inputs, fields, term if layout == "filter" else None)


def finite_ids(_, contract, _tag):
    f = contract["finite"]
    coordinates = set(product(f["widths"], f["operator"], f["layout"], f["operands"]))
    require(len(coordinates) == f["identities"], "postgres-signed-comparison-finite-shape")
    return coordinates


def finite_id(_, observed, _expected):
    where, source = observed["where"], observed["source"]
    require(is_join(source), "postgres-signed-comparison-finite-join")
    if source["kind"] == "LEFT":
        layout, term = "join", source["on"]
    elif where is not None:
        layout, term = "filter", where
    else:
        layout, term = "output", observed["columns"][0][0]
    _, operator, left, right = term
    roles = "left literal" if left[0] in ("wide-integer", "integer") else "right literal" if right[0] in ("wide-integer", "integer") else "both fields"
    return f"{scalar_type(left)}-{scalar_type(right)}", operator, layout, roles


def analyzed(tag):
    left = table(LEFT)
    id_ = field(LEFT, 0, "id")
    low = field(LEFT, 0, "low")
    high = field(LEFT, 0, "high")
    right_id = field(RIGHT, 1, "id")
    right_high = field(RIGHT, 1, "high")
    yield (f"SELECT low < id AS smaller, id FROM {L}",
           query(tag, left, [[compare("<", low, id_), "smaller"], [id_, "id"]]))
    yield (f"SELECT low OPERATOR(pg_catalog.>=) {MIN} AS above_minimum FROM {L}",
           query(tag, left, [[compare(">=", low, ["wide-integer", str(MIN)]), "above_minimum"]]))
    yield (f"SELECT high != low AS changed FROM {L}", query(tag, left, [[compare("<>", high, low), "changed"]]))
    outer = join("LEFT", left, table(RIGHT), compare("=", id_, right_high))
    join_sql = f"{L} l LEFT JOIN {R} r ON l.id = r.high"
    yield (f"SELECT l.id AS owner, r.high AS inspected FROM {join_sql}",
           query(tag, outer, [[id_, "owner"], [right_high, "inspected"]]))
    maximum = ["wide-integer", str(MAX)]
    yield f"SELECT l.id FROM {L} l WHERE ((l.low)) < {MAX}", query(tag, left, [[id_, "id"]], compare("<", low, maximum))
    yield (f"SELECT low < id AS direction, id FROM {L} ORDER BY (direction), id",
           query(tag, left, [[compare("<", low, id_), "direction"], [id_, "id"]], order=[[1, False, False], [2, False, False]]))
    yield (f"SELECT l.id, r.id AS linked FROM {join_sql} WHERE r.high > 0",
           query(tag, outer, [[id_, "id"], [right_id, "linked"]], compare(">", right_high, ["integer", "0"])))
    yield f"SELECT id FROM {L} WHERE id OPERATOR(pg_catalog.<=) high", query(tag, left, [[id_, "id"]], compare("<=", id_, high))


def native_negatives():
    return (
        (f"SELECT id FROM {L} WHERE unknown_width < id", "Column unknown_width does not exist.", SEMANTIC, "42703"),
        (f"SELECT id FROM {L} WHERE low OPERATOR(public.<) id", "A predicate comparison must resolve to a pg_catalog operator.", DIAGNOSTIC, "42883"),
        (f"SELECT id FROM {L} WHERE label = low", "A predicate comparison needs two signed integer operands.", DIAGNOSTIC, "42883"),
    )


def prepare(engine, db):
    return {"fixture": prepare_inputs(engine, db),
            "errors": [db.request(observer_request({}, sql)) for sql, _, _, _ in native_negatives()]}


def check_preparation(_, environment, receipt):
    same({"status": "fixture"}, receipt["fixture"])
    require(environment["database"][-1] == "pg_catalog, public", "postgres-signed-comparison-profile")
    same([{"status": "db-error", "state": state} for _, _, _, state in native_negatives()], receipt["errors"])


def negatives(tag):
    one, two = ["integer", "1"], ["wide-integer", str(MAX)]
    left, right = table(LEFT), table(RIGHT)
    id_ = [[field(LEFT, 0, "id"), "result"]]
    yield (query(tag, left, [[compare("=", ["wide-slot", 0, 0], two), "illegal"]]),
           {"exception": DIAGNOSTIC, "message": "An int8 slot must belong to the SELECT source with its native kind."})
    yield (query(tag, left, id_, compare("=", ["slot", 1], one)),
           {"exception": DIAGNOSTIC, "message": "A predicate slot must belong to a visible input occurrence."})
    bad = join("LEFT", left, right, compare("=", ["wide-slot", 1, 2], one))
    yield (query(tag, bad, id_), {"exception": DIAGNOSTIC, "message": "A predicate slot must belong to a visible input occurrence."})
    for sql, message, exception, _ in native_negatives():
        yield native_request(tag, sql), {"exception": exception, "message": message}


def seeded(tag, seed, number):
    rng = random.Random(seed)
    for index in range(number):
        layout = rng.choice(("output", "filter", "join"))
        widths = rng.choice(("int4-int4", "int4-int8", "int8-int4", "int8-int8"))
        item = finite(tag, widths, rng.choice(OPERATORS), layout, rng.choice(("both fields", "left literal", "right literal")))
        if layout != "join":
            terminals = [source_value(position, width) for position, width in enumerate(widths.split("-"))]
            another = compare(rng.choice(OPERATORS), rng.choice(terminals), ["integer", str(rng.randrange(-13, 14))])
            alternate = compare("=", terminals[0], rng.choice(terminals))
            item["where"] = ["logic", rng.choice(("AND", "OR")), item["where"] or another, ["logic", "NOT", alternate, None]]
        item["columns"] = [[term, rng.choice(("result", "overlap", '値 " confirmed'))] for term, _ in item["columns"]]
        item["distinct"] = bool(rng.getrandbits(1))
        yield item


def requests(_, tag, contract):
    f = contract["finite"]
    for index, coord in enumerate(product(f["widths"], f["operator"], f["layout"], f["operands"])):
        case = finite(tag, *coord)
        yield f"finite/{index}", case, case, None
    examples = list(analyzed(tag))
    require(len(examples) == len(contract["analysis"]), "postgres-signed-comparison-analysis-cases")
    for index, (sql, meaning) in enumerate(examples):
        yield f"analyze/{index}", native_request(tag, sql), meaning, None
    for seed in contract["exploration"]["seeds"]:
        for index, case in enumerate(seeded(tag, seed, contract["exploration"]["casesPerSeed"])):
            yield f"seed/{seed}/{index}", case, case, None
    for index, (case, reason) in enumerate(negatives(tag)):
        yield f"negative/{index}", case, None, reason


def symbol(term, scope):
    if term is None:
        return None
    role = term[0]
    if role in NATIVE_FIELDS:
        return address(term, scope)
    if role in ("comparison", "logic"):
        return role, term[1], symbol(term[2], scope), symbol(term[3], scope)
    if role in ("int4-distinctness", "int4-range"):
        width = 2 if role == "int4-distinctness" else 3
        return *term[:-width], *(symbol(operand, scope) for operand in term[-width:])
    require(role in ("boolean", "integer", "wide-integer", "null", "wide-null"), "postgres-signed-comparison-scalar", term)
    return tuple(term)


def graph(item):
    def origin(source):
        if is_join(source):
            return source["kind"], origin(source["left"]), origin(source["right"]), symbol(source["on"], leaves(source))
        require(source["table"] in HEADINGS and source["schema"] == "public" and not source["only"],
                "postgres-signed-comparison-external-source", source)
        return source["schema"], source["table"], source["only"]

    inputs = leaves(item["source"])
    return (origin(item["source"]), tuple((symbol(term, inputs), name) for term, name in item["columns"]),
            symbol(item["where"], inputs), item["distinct"], item["orderBy"], item["limit"])


def evaluate(term, context):
    if term is None:
        return True
    if term[0] in ("comparison", "logic", "int4-distinctness", "int4-range"):
        return predicate_value(term, lambda operand: evaluate(operand, context))
    if term[0] in ("null", "wide-null"):
        return None
    return scalar_value(term, context)


def input_rows(source):
    if not is_join(source):
        heading = HEADINGS[source["table"]]
        slots = [heading.index(column) for column in source["columns"]]
        return [(tuple(values[index] for index in slots),) for values in DATA[source["table"]]]
    lhs = input_rows(source["left"])
    rhs = input_rows(source["right"])
    result = []
    for left in lhs:
        side = [left + right for right in rhs if evaluate(source["on"], left + right) is True]
        if not side and source["kind"] == "LEFT":
            side = [left + ((None,) * len(source["right"]["columns"]),)]
        result.extend(side)
    return result


def expected(item):
    rows = [tuple(evaluate(term, row) for term, _ in item["columns"]) for row in input_rows(item["source"])
            if evaluate(item["where"], row) is True]
    if item["distinct"]:
        rows = list(dict.fromkeys(rows))
    if item["orderBy"]:
        require(item["orderBy"] == [[1, False, False], [2, False, False]], "postgres-signed-comparison-unregistered-order")
        rows.sort(key=lambda row: tuple((slot is None, slot) for slot in row))
    fields = []
    for term, name in item["columns"]:
        native = "bool" if term[0] == "comparison" else scalar_type(term)
        fields.append([name, native, OID[native]])
    return {"status": "rows", "rows": [list(row) for row in rows], "fields": fields}


def observation(raw):
    return raw


def normalize(item, raw):
    return ({**raw, "rows": sorted(raw["rows"], key=canonical)}
            if raw["status"] == "rows" and not item["orderBy"] else raw)


def check_observation(item, actual, sql):
    predicted, actual = normalize(item, expected(item)), normalize(item, actual)
    try:
        same(predicted, actual)
    except Failure as error:
        raise Failure(error.signal, {"sql": sql, "expected": predicted, "observed": actual}) from error


def check_value(item, actual):
    require(actual["status"] == "constructed" and actual["profile"].startswith(item["release"] + "/"),
            "postgres-signed-comparison-positive-rejected", actual)
    require(graph(actual) == graph(item), "postgres-signed-comparison-graph", {"expected": item, "observed": actual})


def required_forms(*_):
    return {"projection", "where", "join", "nested", "int4-int4", "int4-int8", "int8-int4", "int8-int8",
            "field/0", "field/1", "long-literal", "null-observed", "false-observed", "true-observed"}


def sample_forms(forms, reply, *_):
    def visit(term):
        if term is None:
            return
        if term[0] == "logic":
            forms["nested"] += 1
            visit(term[2])
            visit(term[3])
        elif term[0] == "comparison":
            left, right = term[2:]
            forms[f"{scalar_type(left)}-{scalar_type(right)}"] += 1
            for operand in (left, right):
                if operand[0] in NATIVE_FIELDS:
                    forms[f"field/{operand[2] if len(operand) > 2 else 0}"] += 1
                elif operand[0] == "wide-integer":
                    forms["long-literal"] += 1

    for pos, (term, _) in enumerate(reply["columns"]):
        if term[0] == "comparison":
            forms["projection"] += 1
            visit(term)
            for row in expected(reply)["rows"]:
                forms[{True: "true", False: "false", None: "null"}[row[pos]] + "-observed"] += 1
    if reply["where"] is not None:
        forms["where"] += 1
        visit(reply["where"])
    if reply["source"]["on"] is not None:
        forms["join"] += 1
        visit(reply["source"]["on"])


def controls():
    item = finite("pg-17.2", "int8-int4", "<", "output", "both fields")
    normal = normalize(item, expected(item))
    check_observation(item, normal, "healthy")
    variants = {
        "wrong Boolean width": {**normal, "fields": [["comparison", "int4", 23], *normal["fields"][1:]]},
        "lost occurrence": {**normal, "rows": normal["rows"][1:]},
        "NULL made false": {**normal, "rows": [[False if row[0] is None else row[0], *row[1:]] for row in normal["rows"]]},
    }
    output = {}
    for name, actual in variants.items():
        try:
            check_observation(item, actual, name)
        except Failure as error:
            require(error.signal == "meaning-mismatch", "postgres-signed-comparison-control-signal", name)
            output[name] = {"input": item, **error.detail}
        else:
            raise Failure("postgres-signed-comparison-control-not-detected", name)
    return output


def check_negative(item, reply):
    if rejects(reply, item["boundary"]):
        return
    require(reply["status"] == "constructed", "postgres-signed-comparison-fault-unexpected", reply)
    raise Failure("postgres-signed-comparison-invalid-published", {"sql": reply["sql"], "observed": reply, "expected": item["boundary"]})


def fault_probe(item, reply, db):
    if "boundary" in item:
        check_negative(item, reply)
        return
    probe(item, reply, db, sys.modules[__name__])


def replay_fault(rule, record):
    witness = record["detail"]
    try:
        if "boundary" in rule.item:
            check_negative(rule.item, record["healthyResponse"])
            check_negative(rule.item, record["mutantResponse"])
        else:
            check_value(rule.item, record["healthyResponse"])
            check_value(rule.item, record["mutantResponse"])
            require(witness["observed"]["status"] == "rows" or witness["observed"] == {"status": "db-error", "state": "22003"},
                    "postgres-signed-comparison-fault-native", witness)
            check_observation(rule.item, witness["observed"], witness["sql"])
    except Failure as error:
        same(rule.signal, error.signal)
        same(witness, error.detail)
        return
    raise Failure("postgres-signed-comparison-undetected-fault", rule.name)


def faults(_, tag):
    root = "sql-semantics-postgres/src/Ir/"
    sql = root + "SelectSql.php"
    printing = "            return '(' . $left . ' ' . $operator . ' ' . $right . ')';"
    operator = "$operator = 'OPERATOR(pg_catalog.' . $condition->operator->value . ')';"
    pair = finite(tag, "int8-int4", "<", "output", "both fields")
    literal_term = finite(tag, "int4-int8", "<", "output", "right literal")
    on = finite(tag, "int4-int8", "<", "join", "both fields")
    invalid, boundary = next(negatives(tag))
    return [
        RenderFault("swap-signed-comparison-operands", sql, printing,
                    "            return '(' . $right . ' ' . $operator . ' ' . $left . ')';", pair, "meaning-mismatch"),
        RenderFault("reverse-signed-comparison-operator", sql, operator,
                    "$operator = 'OPERATOR(pg_catalog.>)';", pair, "meaning-mismatch"),
        RenderFault("narrow-wide-comparison-literal", sql, "' AS pg_catalog.int8)'", "' AS pg_catalog.int4)'", literal_term, "meaning-mismatch"),
        RenderFault("fetch-other-signed-join-occurrence", sql, "$this->alias($offset + $expression->occurrence)", "$this->alias($offset)", on, "meaning-mismatch"),
        RenderFault("skip-projected-signed-comparison-scope", root + "SelectBounds.php",
                    "$expression instanceof Arithmetic, $expression instanceof IntegerComparison => array_push",
                    "$expression instanceof IntegerComparison => null,\n                    $expression instanceof Arithmetic => array_push",
                    {"boundary": boundary}, "postgres-signed-comparison-invalid-published", invalid),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])
