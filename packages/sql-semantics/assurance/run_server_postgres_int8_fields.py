"""Compare native int8 field resolution, narrowing and exact returned metadata."""

import json
import random
import sys
from functools import cmp_to_key
from itertools import product

from run import TOOL, Failure, digest, require
from run_server import CONTRACT as PROJECTION, same
from run_server_join import OBSERVER, main
from run_server_postgres_boolean_fields import DIAGNOSTIC, query
from server_cases import observation as unordered
from server_join_cases import compare, is_join, join, leaves, rows, slot, truth
from server_truth_model import predicate_value
from sqlite_domain import RenderFault

LABEL = "postgres-int8-fields"
CONTRACT_NAME = "postgres-int8-fields-v1.json"
CONTRACT = "233ffe316162ba2bd1d4b5e8d0e2306c8af183c0293ad4c7941d710f97e2c744"
LEFT, RIGHT, EMPTY = "ir_int8_left", "ir_int8_right", "ir_int8_empty"
MIN, MAX = -9223372036854775808, 9223372036854775807
HEADINGS = {
    LEFT: ("id", "low", "high", "enabled", "label"),
    RIGHT: ("label", "high", "id", "low", "enabled"),
    EMPTY: ("id", "low", "high", "enabled", "label"),
}
TYPES = {"id": "int4", "low": "int8", "high": "int8", "enabled": "bool", "label": "text"}
OID = {"int4": 23, "int8": 20, "bool": 16, "text": 25}
FIELD = {"int4": "slot", "int8": "wide-slot", "bool": "bool-slot", "text": "text-slot"}
DATA = {
    LEFT: [(1, MIN, MAX, False, "left α"), (1, MIN, MAX, False, "left α"), (2, -7, 21, True, "q'a"),
           (3, None, 2147483648, None, ""), (4, 0, None, False, None), (None, -2147483649, 2, True, "unpaired")],
    RIGHT: [("right", MIN + 1, 1, MAX - 1, True), ("two", 7, 2, -11, False), ("two", 7, 2, -11, False),
            (None, None, 5, 3, None), ("null id", 13, None, -5, True)],
    EMPTY: [],
}
DECLARATIONS = [
    f"CREATE TABLE public.{name} (" + ", ".join(f"{field} pg_catalog.{TYPES[field]}" for field in fields) + ")"
    for name, fields in HEADINGS.items()
]
NATIVE_FIELDS = tuple(FIELD.values())


def specifications():
    scope, projection = TOOL / CONTRACT_NAME, TOOL / "server-projection-v1.json"
    require(digest(scope) == CONTRACT and digest(projection) == PROJECTION, "postgres-int8-fields-contract-mismatch")
    return json.loads(scope.read_text()), {"postgres": json.loads(projection.read_text())["profiles"]["postgres"]}


def prepare(_, db):
    def literal(value):
        if type(value) is int:
            return str(value)
        if type(value) is str:
            return "'" + value.replace("'", "''") + "'"
        return {True: "TRUE", False: "FALSE", None: "NULL"}[value]

    statements = [*(f"DROP TABLE IF EXISTS public.{name}" for name in HEADINGS), *DECLARATIONS]
    for name, raw in DATA.items():
        if raw:
            values = ",".join("(" + ",".join(map(literal, row)) + ")" for row in raw)
            statements.append(f"INSERT INTO public.{name} VALUES {values}")
    return db.request({"fixture": statements})


def check_preparation(_, environment, reply):
    same({"status": "fixture"}, reply)
    require(environment["database"][-1] == "pg_catalog, public", "postgres-int8-fields-profile")


def table(name):
    return {"schema": "public", "table": name, "columns": list(HEADINGS[name]), "only": False,
            "columnKinds": [TYPES[key] for key in HEADINGS[name]]}


def field(table_name, occurrence, name):
    role, index = FIELD[TYPES[name]], HEADINGS[table_name].index(name)
    return slot(index, occurrence) if role == "slot" else [role, index, occurrence]


def cast(target, operand):
    return ["builtin-cast", target, operand]


def test(operand):
    return ["null-test", False, operand]


def mode(role, operand):
    return {"read": operand, "presence": test(operand), "identity-cast": cast("int8", operand),
            "narrow-cast": cast("int4", operand)}[role]


def derived(tag):
    output = [[field(RIGHT, 0, "low"), "low"], [field(RIGHT, 0, "high"), "high"], [field(RIGHT, 0, "id"), "id"]]
    return {"derived": query(tag, table(RIGHT), output)}


def finite(tag, source_role, name, consumer, distinct):
    left, right = table(LEFT), table(RIGHT)
    key = field(LEFT, 0, "id")
    if source_role == "physical":
        source = left
        reading = field(LEFT, 0, name)
    elif source_role == "derived":
        source = join("LEFT", left, derived(tag), compare("=", key, slot(2, 1)))
        reading = ["wide-slot", ("low", "high").index(name), 1]
    else:
        source = join(source_role.upper(), left, right, compare("=", key, field(RIGHT, 1, "id")))
        reading = field(RIGHT, 1, name)
    return query(tag, source, [[mode(consumer, reading), "result"], [key, "id"]], distinct=distinct)


def finite_ids(_, frozen, _tag):
    f = frozen["finite"]
    grid = {(source, name, consumer, distinct) for source in f["source"] for name in f["field"]
            for consumer in f["operand"] for distinct in f["distinct"]}
    require(len(grid) == f["identities"], "postgres-int8-fields-finite-size", len(grid))
    return grid


def address(term, scope):
    role, subscript = term[:2]
    occurrence = term[2] if len(term) == 3 else 0
    source = scope[occurrence]
    if "derived" in source:
        selected, _ = source["derived"]["columns"][subscript]
        kind = type_of(selected)
        physical = subscript
    else:
        physical = source["columns"][subscript]
        kind = TYPES[physical]
        require(kind == source.get("columnKinds", ["int4"] * len(source["columns"]))[subscript],
                "postgres-int8-fields-physical-kind", source)
    require(FIELD[kind] == role, "postgres-int8-fields-reference-kind", [term, source])
    return role, occurrence, physical


def symbol(term, scope):
    if term is None:
        return None
    role = term[0]
    if role in NATIVE_FIELDS:
        return address(term, scope)
    if role in ("null-test", "builtin-cast"):
        return role, term[1], symbol(term[2], scope)
    if role == "comparison":
        return role, term[1], symbol(term[2], scope), symbol(term[3], scope)
    require(role in ("boolean", "integer", "wide-integer"), "postgres-int8-fields-unknown-operand", term)
    return tuple(term)


def graph(item):
    def origin(source):
        if "derived" in source:
            return "derived", graph(source["derived"])
        if is_join(source):
            return source["kind"], origin(source["left"]), origin(source["right"]), symbol(source["on"], leaves(source))
        require(source["table"] in HEADINGS and all(name in HEADINGS[source["table"]] for name in source["columns"]),
                "postgres-int8-fields-foreign-heading", source)
        return source["schema"], source["table"], source["only"]

    source = item["source"]
    inputs = leaves(source)
    return (origin(source), tuple((symbol(term, inputs), label) for term, label in item["columns"]),
            symbol(item["where"], inputs), item["distinct"], item["orderBy"], item["limit"])


def finite_id(_, reply, _item):
    source = reply["source"]
    head = reply["columns"][0][0]
    role = head[0]
    if role == "null-test":
        consumer, read = "presence", head[2]
    elif role == "builtin-cast":
        consumer, read = {"int4": "narrow-cast", "int8": "identity-cast"}[head[1]], head[2]
    else:
        consumer, read = "read", head
    _, occurrence, origin = address(read, leaves(source))
    if not is_join(source):
        source_role = "physical"
    elif "derived" in source["right"]:
        source_role = "derived"
        selected, _ = source["right"]["derived"]["columns"][origin]
        _, _, origin = address(selected, leaves(source["right"]["derived"]["source"]))
    else:
        source_role = source["kind"].lower()
    require(occurrence == (0 if source_role == "physical" else 1), "postgres-int8-fields-finite-occurrence", reply)
    return source_role, origin, consumer, reply["distinct"]


def analyzed(tag):
    l, r = (f"public.{name}" for name in (LEFT, RIGHT))
    source = table(LEFT)
    low = field(LEFT, 0, "low")
    id_two = compare("=", field(LEFT, 0, "id"), ["integer", "2"])
    yield (f"SELECT ((low)) AS grouped, a.low AS qualified, low FROM {l} a",
           query(tag, source, [[low, "grouped"], [low, "qualified"], [low, "low"]]))
    yield f"SELECT (enabled) FROM {l}", query(tag, source, [[field(LEFT, 0, "enabled"), "enabled"]])
    yield f"SELECT ((label)) FROM {l}", query(tag, source, [[field(LEFT, 0, "label"), "label"]])
    yield f"SELECT * FROM {l}", query(tag, source, [[field(LEFT, 0, name), name] for name in HEADINGS[LEFT]])
    yield (f"SELECT r.low AS result,l.id FROM {l} l LEFT JOIN {r} r ON l.id=r.id",
           finite(tag, "left", "low", "read", False))
    independent = finite(tag, "derived", "high", "read", False)
    independent["source"]["right"]["derived"]["columns"].append([["wide-integer", str(MAX)], "edge"])
    independent["columns"].append([["wide-slot", 3, 1], "edge"])
    yield (f"SELECT q.high AS result,l.id,q.edge FROM {l} l LEFT JOIN "
           f"(SELECT low,high,id,{MAX} AS edge FROM {r}) q ON l.id=q.id", independent)
    yield (f"SELECT CAST(low AS integer) AS narrow FROM {l} WHERE id=2",
           query(tag, source, [[cast("int4", low), "narrow"]], id_two))
    yield f"SELECT CAST(low AS integer) AS narrow FROM {l}", query(tag, source, [[cast("int4", low), "narrow"]])
    yield f"SELECT CAST(enabled AS integer) AS bit FROM {l}", query(tag, source, [[cast("int4", field(LEFT, 0, "enabled")), "bit"]])
    yield f"SELECT CAST(label AS text) AS text FROM {l}", query(tag, source, [[cast("text", field(LEFT, 0, "label")), "text"]])


def negatives(tag):
    source, wrong = table(LEFT), "An int8 slot must belong to the SELECT source with its native kind."
    points = (
        (["wide-slot", -1, 0], "A column slot must be nonnegative."),
        (["wide-slot", 1, -1], "A table occurrence must be nonnegative."),
        (["wide-slot", 5, 0], wrong),
        (["wide-slot", 1, 1], wrong),
        (["wide-slot", 0, 0], wrong),
        (slot(1), "Column slot must belong to the SELECT source."),
        (["text-slot", 1, 0], "A text slot must belong to the SELECT source with its native kind."),
        (cast("bool", field(LEFT, 0, "low")), "PostgreSQL has no direct cast between bigint and boolean."),
    )
    for term, reason in points:
        yield query(tag, source, [[term, "result"]]), {"exception": DIAGNOSTIC, "message": reason}


def seeded(tag, seed, count):
    rng = random.Random(seed)
    for index in range(count):
        source_role = ("physical", "inner", "left", "derived")[index % 4]
        selected = finite(tag, source_role, rng.choice(("low", "high")), rng.choice(("read", "presence", "identity-cast", "narrow-cast")), bool(rng.getrandbits(1)))
        if index % 8 == 0:
            selected["source"] = table(EMPTY)
        if index % 4 != 0:
            key = field(LEFT, 0, "id")
            compare_to = ["integer", str(rng.randrange(0, 5))]
            selected["where"] = rng.choice((None, compare("=", key, compare_to)))
        selected["columns"] = [[operand, rng.choice(("count", "same", '值 " name'))] for operand, _ in selected["columns"]]
        if index % 3:
            selected["orderBy"] = [[i, bool(rng.getrandbits(1)), bool(rng.getrandbits(1))]
                                   for i in range(1, len(selected["columns"]) + 1)]
        yield selected


def requests(_, tag, contract):
    f = contract["finite"]
    for index, coordinates in enumerate(product(f["source"], f["field"], f["operand"], f["distinct"])):
        item = finite(tag, *coordinates)
        yield f"finite/{index}", item, item, None
    positives = list(analyzed(tag))
    require(len(positives) == len(contract["analysis"]), "postgres-int8-fields-analysis-count")
    for index, (sql, item) in enumerate(positives):
        yield f"analyze/{index}", {"dialect": "postgres", "release": tag, "sql": sql, "declarations": DECLARATIONS}, item, None
    exploration = contract["exploration"]
    for seed in exploration["seeds"]:
        for index, item in enumerate(seeded(tag, seed, exploration["casesPerSeed"])):
            yield f"seed/{seed}/{index}", item, item, None
    for index, (item, reason) in enumerate(negatives(tag)):
        yield f"negative/{index}", item, None, reason


def type_of(term):
    role = term[0]
    if role == "builtin-cast":
        return term[1]
    if role in ("null-test", "boolean"):
        return "bool"
    if role == "wide-integer":
        return "int8"
    if role == "integer":
        return "int4"
    return {value: key for key, value in FIELD.items()}[role]


def evaluate(term, row):
    role = term[0]
    if role in NATIVE_FIELDS:
        return row[term[2] if len(term) == 3 else 0][term[1]]
    if role in ("integer", "wide-integer"):
        return int(term[1])
    if role == "boolean":
        return term[1]
    if role == "null-test":
        return predicate_value(term, lambda child: evaluate(child, row))
    require(role == "builtin-cast", "postgres-int8-fields-oracle-operand", term)
    value = evaluate(term[2], row)
    if term[1] == "int4" and value is not None:
        if not -(2**31) <= value <= 2**31 - 1:
            raise OverflowError("The expected signed narrowing overflows.")
        return int(value)
    return value


def result_rows(item):
    raw = rows(item["source"], "postgres", result_rows, lambda source: (HEADINGS[source["table"]], DATA[source["table"]]))
    results = [tuple(evaluate(term, row) for term, _ in item["columns"]) for row in raw
               if truth(item["where"], row, "postgres")]
    if item["distinct"]:
        results = list(dict.fromkeys(results))

    def sort_order(left, right):
        for position, descending, first in item["orderBy"]:
            lhs, rhs = left[position - 1], right[position - 1]
            if lhs == rhs:
                continue
            if lhs is None or rhs is None:
                return -1 if (lhs is None) == first else 1
            return -1 if (lhs < rhs) != descending else 1
        return 0

    if item["orderBy"]:
        results.sort(key=cmp_to_key(sort_order))
    return [list(row) for row in results]


def expected(item):
    try:
        values = result_rows(item)
    except OverflowError:
        return {"status": "db-error", "state": "22003"}
    return {"status": "rows", "fields": [[name, type_of(term), OID[type_of(term)]] for term, name in item["columns"]], "rows": values}


def observation(raw):
    return raw


def observer_request(_, sql):
    return {"sql": sql, "observePostgresTypeIds": True}


def normalize(item, actual):
    return actual if item["orderBy"] else unordered(actual)


def check_value(item, reply):
    require(reply["status"] == "constructed" and reply["profile"].startswith(item["release"] + "/"),
            "postgres-int8-fields-positive-rejected", reply)
    require(graph(item) == graph(reply), "postgres-int8-fields-graph", {"expected": item, "observed": reply})


def check_observation(item, actual, sql):
    expected_result, actual = normalize(item, expected(item)), normalize(item, actual)
    try:
        same(expected_result, actual)
    except Failure as error:
        raise Failure(error.signal, {"sql": sql, "expected": expected_result, "observed": actual}) from error


def check_fault_witness(item, witness):
    same(normalize(item, expected(item)), witness["expected"])
    require(witness["observed"]["status"] in ("rows", "db-error"), "postgres-int8-fields-fault-unobserved")


def controls():
    direct = finite("pg-17.2", "physical", "low", "read", False)
    narrowed = finite("pg-17.2", "physical", "low", "narrow-cast", False)
    normal = expected(direct)
    cases = [("wrong width", direct, {**normal, "fields": [["result", "int4", 23], normal["fields"][1]]}),
             ("wrong overflow", narrowed, {"status": "db-error", "state": "42804"}),
             ("wrong signed value", direct, {**normal, "rows": normal["rows"][1:]})]
    records = {}
    for name, item, other in cases:
        check_observation(item, expected(item), "healthy")
        try:
            check_observation(item, other, name)
        except Failure as error:
            require(error.signal == "meaning-mismatch", "postgres-int8-fields-control-signal", error.detail)
            records[name] = {"input": item, **error.detail}
        else:
            raise Failure("postgres-int8-fields-control-accepted", name)
    return records


def required_forms(*_):
    return {"source/physical", "source/inner", "source/left", "source/derived", "kind/wide-slot",
            "kind/builtin-cast", "kind/null-test", "order/True", "order/False", "rows/empty", "rows/overflow", "rows/null"}


def sample_forms(forms, reply, _, item):
    source = reply["source"]
    label = "physical" if not is_join(source) else "derived" if "derived" in source["right"] else source["kind"].lower()
    forms["source/" + label] += 1
    forms[f"order/{bool(reply['orderBy'])}"] += 1
    for term, _ in reply["columns"]:
        forms["kind/" + term[0]] += 1
    visible = expected(item)
    if visible["status"] == "db-error":
        forms["rows/overflow"] += 1
    else:
        data = visible["rows"]
        if not data:
            forms["rows/empty"] += 1
        if any(None in row for row in data):
            forms["rows/null"] += 1


def faults(_, tag):
    renderer = "sql-semantics-postgres/src/Ir/SelectSql.php"
    sql_field = '$name = $input->columns[$expression->slot]->quoted();'
    dispatch = '=> $this->column($expression, $source, $offset, $qualify),'
    target = "$expression->target->value"
    cast_expression = "'CAST(' . $this->expression($expression->operand, $source, $offset, $qualify) . ' AS pg_catalog.' . $expression->target->value . ')'"
    read = finite(tag, "physical", "low", "read", False)
    bounded = compare("=", field(LEFT, 0, "id"), ["integer", "2"])
    boolean = query(tag, table(LEFT), [[cast("int4", field(LEFT, 0, "enabled")), "converted"]])
    return [
        RenderFault("read-another-int8-field", renderer, sql_field,
                    sql_field.replace("$expression->slot", "$expression instanceof WideIntegerColumnReference ? 2 : $expression->slot"),
                    read, "meaning-mismatch"),
        RenderFault("insert-unrequested-int8-narrowing", renderer, dispatch,
                    "=> $expression instanceof WideIntegerColumnReference ? 'CAST(' . $this->column($expression, $source, $offset, $qualify) . ' AS pg_catalog.int4)' : $this->column($expression, $source, $offset, $qualify),",
                    {**read, "where": bounded}, "meaning-mismatch"),
        RenderFault("erase-int8-null-extension", renderer, "$source->kind->value . ' JOIN '",
                    "'INNER' . ' JOIN '", finite(tag, "left", "high", "read", False), "meaning-mismatch"),
        RenderFault("cast-native-boolean-to-wrong-type", renderer, target,
                    "($expression->operand instanceof BooleanColumnReference ? 'bool' : $expression->target->value)",
                    boolean, "meaning-mismatch"),
        RenderFault("erase-native-int8-narrowing", renderer, cast_expression,
                    "$this->expression($expression->operand, $source, $offset, $qualify)",
                    finite(tag, "physical", "low", "narrow-cast", False), "meaning-mismatch"),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])
