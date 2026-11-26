"""Observe resolved PostgreSQL Boolean fields and native predicate composition."""

import json
import random
import sys
from itertools import product

from run import TOOL, Failure, digest, require
from run_server import CONTRACT as PROJECTION, same
from run_server_join import OBSERVER, main
from server_cases import observation as unordered
from server_join_cases import case, compare, is_join, join, leaves, rows, slot, truth, value
from server_truth_model import predicate_value
from sqlite_domain import RenderFault

LABEL = "postgres-boolean-fields"
CONTRACT_NAME = "postgres-boolean-fields-v1.json"
CONTRACT = "be21037b2177f59687c4a02f8ef81142aa67bbc92263d2f5f6dc8e4a705b09c1"
LEFT, RIGHT, EMPTY = "ir_bool_left", "ir_bool_right", "ir_bool_empty"
KINDS = {"id": "int4", "enabled": "bool", "fallback": "bool"}
HEADINGS = {
    LEFT: ("id", "enabled", "fallback"),
    RIGHT: ("fallback", "id", "enabled"),
    EMPTY: ("enabled", "id", "fallback"),
}
DATA = {
    LEFT: [(1, True, False), (1, True, False), (2, False, None), (3, None, True), (4, False, True), (None, True, None)],
    RIGHT: [(False, 1, True), (None, 2, False), (None, 2, False), (True, 5, None), (True, None, False)],
    EMPTY: [],
}
DECLARATIONS = [
    f"CREATE TABLE public.{name} (" + ", ".join(f"{field} pg_catalog.{KINDS[field]}" for field in heading) + ")"
    for name, heading in HEADINGS.items()
]
FIELDS = ("enabled", "fallback")
FILTERS = ("absent", "direct", "is-null", "is-true", "is-false", "is-unknown")
TRUTH_TESTS = ("IS TRUE", "IS FALSE", "IS UNKNOWN", "IS NOT TRUE", "IS NOT FALSE", "IS NOT UNKNOWN")
RESULT_TYPES = {"slot": ("int4", 23), "bool-slot": ("bool", 16), "null-test": ("bool", 16), "truth-test": ("bool", 16)}
DIAGNOSTIC = "SqlSemantics\\Platform\\PostgreSql\\Model\\Diagnostic\\InvalidConstruction"


def specifications():
    scope, profile = TOOL / CONTRACT_NAME, TOOL / "server-projection-v1.json"
    require(digest(scope) == CONTRACT and digest(profile) == PROJECTION, "postgres-boolean-fields-contract-mismatch")
    return json.loads(scope.read_text()), {"postgres": json.loads(profile.read_text())["profiles"]["postgres"]}


def prepare(_, db):
    atoms = {False: "FALSE", True: "TRUE", None: "NULL"}

    def atom(cell):
        return str(cell) if type(cell) is int else atoms[cell]

    fixture = [*(f"DROP TABLE IF EXISTS public.{name}" for name in HEADINGS), *DECLARATIONS]
    for table, data in DATA.items():
        if data:
            values = ",".join("(" + ",".join(map(atom, row)) + ")" for row in data)
            fixture.append(f"INSERT INTO public.{table} VALUES {values}")
    return db.request({"fixture": fixture})


def check_preparation(_, environment, response):
    same({"status": "fixture"}, response)
    require(environment["database"][-1] == "pg_catalog, public", "postgres-boolean-fields-default-profile")


def table(name):
    return {"schema": "public", "table": name, "columns": list(HEADINGS[name]), "only": False,
            "columnKinds": [KINDS[field] for field in HEADINGS[name]]}


def field(table_name, occurrence, name):
    ordinal = HEADINGS[table_name].index(name)
    return slot(ordinal, occurrence) if name == "id" else ["bool-slot", ordinal, occurrence]


def query(tag, source, fields, where=None, distinct=False, order=()):
    return {**case("postgres", tag, source, fields, where), "distinct": distinct, "orderBy": list(order), "limit": None}


def filter_term(mode, field):
    if mode == "absent":
        return None
    if mode == "direct":
        return field
    if mode == "is-null":
        return ["null-test", False, field]
    require(mode in FILTERS, "postgres-boolean-fields-filter-mode", mode)
    return ["truth-test", mode.upper().replace("-", " "), field]


def finite(tag, source, name, mode, extent, distinct):
    projected = field(LEFT if source == "physical" else RIGHT, 0 if source == "physical" else 1, name)
    relation = table(LEFT) if source == "physical" else join(
        source.upper(), table(LEFT), table(RIGHT), compare("=", field(LEFT, 0, "id"), field(RIGHT, 1, "id")))
    order = [[1, False, True], [2, False, False]] if extent == "ordered" else []
    return query(tag, relation, [[projected, "result"], [field(LEFT, 0, "id"), "id"]],
                 filter_term(mode, projected), distinct, order)


def finite_ids(_, spec, _tag):
    grid = spec["finite"]
    ids = {(source, field_name, mode, extent, distinct)
           for source in grid["sources"] for field_name in grid["fields"] for mode in grid["filters"]
           for extent in grid["extent"] for distinct in grid["distinct"]}
    require(len(ids) == grid["identities"], "postgres-boolean-fields-finite-size", len(ids))
    return ids


def finite_id(_, reply, _item):
    source = reply["source"]
    projection = reply["columns"][0][0]
    scope = leaves(source)
    address = expression(projection, scope)
    where = reply["where"]
    name = address[2]
    filters = {json.dumps(filter_term(mode, projection)): mode for mode in FILTERS}
    return ("physical" if not is_join(source) else source["kind"].lower(), name, filters[json.dumps(where)],
            "ordered" if reply["orderBy"] else "unordered", reply["distinct"])


def analyzed(tag):
    relation = table(LEFT)
    bit = field(LEFT, 0, "enabled")
    key = field(LEFT, 0, "id")
    left, right, empty = (f"public.{name}" for name in (LEFT, RIGHT, EMPTY))
    yield f"SELECT enabled AS bit,a.enabled AS other FROM {left} a", query(
        tag, relation, [[bit, "bit"], [bit, "other"]])
    yield f"SELECT enabled AS bit,id FROM {left} WHERE enabled", query(
        tag, relation, [[bit, "bit"], [key, "id"]], bit)
    yield f"SELECT enabled AS bit FROM {left} WHERE fallback IS NULL", query(
        tag, relation, [[bit, "bit"]], ["null-test", False, field(LEFT, 0, "fallback")])
    yield (f"SELECT r.enabled AS bit,l.id FROM {left} l LEFT JOIN {right} r ON l.id=r.id",
           finite(tag, "left", "enabled", "absent", "unordered", False) | {
               "columns": [[field(RIGHT, 1, "enabled"), "bit"], [key, "id"]],
           })
    derived = {"derived": query(tag, table(RIGHT), [[field(RIGHT, 0, "fallback"), "p"], [field(RIGHT, 0, "id"), "k"]])}
    yield (f"SELECT q.p AS bit,l.id FROM {left} l LEFT JOIN (SELECT fallback AS p,id AS k FROM {right}) q "
           "ON l.id=q.k WHERE q.p IS UNKNOWN",
           query(tag, join("LEFT", relation, derived, compare("=", key, slot(1, 1))),
                 [[["bool-slot", 0, 1], "bit"], [key, "id"]], ["truth-test", "IS UNKNOWN", ["bool-slot", 0, 1]]))
    yield f"SELECT fallback AS bit,id FROM {empty}", query(
        tag, table(EMPTY), [[field(EMPTY, 0, "fallback"), "bit"], [field(EMPTY, 0, "id"), "id"]])
    yield f"SELECT DISTINCT enabled AS result,id FROM {left} ORDER BY 1 NULLS FIRST,2 NULLS LAST", finite(
        tag, "physical", "enabled", "absent", "ordered", True)


def negatives(tag):
    witness = finite(tag, "physical", "enabled", "absent", "unordered", False)
    layout = (
        (["bool-slot", -1, 0], "A column slot must be nonnegative."),
        (["bool-slot", 1, -1], "A table occurrence must be nonnegative."),
        (["bool-slot", 3, 0], "A Boolean slot must belong to the SELECT source with its native kind."),
        (["bool-slot", 1, 1], "A Boolean slot must belong to the SELECT source with its native kind."),
        (["bool-slot", 0, 0], "A Boolean slot must belong to the SELECT source with its native kind."),
        (slot(1), "Column slot must belong to the SELECT source."),
        (["text-slot", 1, 0], "A text slot must belong to the SELECT source with its native kind."),
    )
    for term, boundary in layout:
        yield {**witness, "columns": [[term, "result"]]}, {"exception": DIAGNOSTIC, "message": boundary}


def seeded(tag, seed, size):
    rng = random.Random(seed)
    for index in range(size):
        count = 1 + index % 3
        tables = tuple(rng.choice((LEFT, RIGHT, EMPTY)) for _ in range(count))
        source = table(tables[0])
        for occurrence, name in enumerate(tables[1:], 1):
            kind = rng.choice(("INNER", "LEFT", "CROSS"))
            on = None if kind == "CROSS" else compare("=", field(tables[0], 0, "id"), field(name, occurrence, "id"))
            source = join(kind, source, table(name), on)

        def read_bit():
            occurrence = rng.randrange(count)
            return field(tables[occurrence], occurrence, rng.choice(FIELDS))

        def term(depth):
            if depth == 0:
                return read_bit()
            role = rng.choice(("truth-test", "null-test", "logic", "logic", "bool-slot"))
            if role == "truth-test":
                return [role, rng.choice(TRUTH_TESTS), term(depth - 1)]
            if role == "null-test":
                return [role, rng.choice((False, True)), term(depth - 1)]
            if role == "logic":
                op = rng.choice(("AND", "OR", "NOT"))
                return [role, op, term(depth - 1), None if op == "NOT" else term(depth - 1)]
            return read_bit()

        projection = [[read_bit(), "flag"]]
        for _ in range(rng.randrange(1, 6)):
            operand = rng.choice((read_bit(), filter_term(rng.choice(FILTERS[2:]), read_bit()),
                                  field(tables[0], 0, "id")))
            projection.append([operand, rng.choice(("flag", "id", '値 " field'))])
        order = [[pos, rng.choice((False, True)), rng.choice((False, True))]
                 for pos in range(1, len(projection) + 1)] if index % 2 else []
        yield query(tag, source, projection, term(1 + index % 5) if index % 4 else None, index % 3 != 0, order)


def requests(_, tag, spec):
    limits = spec["finite"]
    for index, coordinates in enumerate(product(*(limits[name] for name in ("sources", "fields", "filters", "extent", "distinct")))):
        statement = finite(tag, *coordinates)
        yield f"finite/{index}", statement, statement, None
    scenarios = list(analyzed(tag))
    require(len(scenarios) == len(spec["analysis"]), "postgres-boolean-fields-analysis-size")
    for index, (sql, meaning) in enumerate(scenarios):
        yield f"analyze/{index}", {"dialect": "postgres", "release": tag, "sql": sql, "declarations": DECLARATIONS}, meaning, None
    settings = spec["exploration"]
    for seed in settings["seeds"]:
        for index, statement in enumerate(seeded(tag, seed, settings["casesPerSeed"])):
            yield f"seed/{seed}/{index}", statement, statement, None
    for index, (statement, boundary) in enumerate(negatives(tag)):
        yield f"negative/{index}", statement, None, boundary


def expression(term, scope):
    if term is None:
        return None
    role = term[0]
    if role in ("slot", "bool-slot"):
        ordinal, occurrence = term[1], term[2] if len(term) > 2 else 0
        parent = scope[occurrence]
        if "derived" in parent:
            child = parent["derived"]["columns"][ordinal][0]
            name, kind = ordinal, RESULT_TYPES[child[0]][0]
        else:
            name = parent["columns"][ordinal]
            kind = KINDS[name]
            require(kind == parent.get("columnKinds", ["int4"] * len(parent["columns"]))[ordinal],
                    "postgres-boolean-fields-wrong-native-layout", parent)
        require(kind == RESULT_TYPES[role][0], "postgres-boolean-fields-wrong-slot-role", term)
        return role, occurrence, name
    if role in ("null-test", "truth-test"):
        return role, term[1], expression(term[2], scope)
    require(role in ("comparison", "logic"), "postgres-boolean-fields-unexpected-expression", term)
    return role, term[1], expression(term[2], scope), expression(term[3], scope)


def graph(item):
    def origin(source):
        if "derived" in source:
            return "derived", graph(source["derived"])
        if is_join(source):
            return source["kind"], origin(source["left"]), origin(source["right"]), expression(source["on"], leaves(source))
        require(source["table"] in HEADINGS and all(name in HEADINGS[source["table"]] for name in source["columns"]),
                "postgres-boolean-fields-foreign-heading", source)
        return source["schema"], source["table"], source["only"]

    source = item["source"]
    scope = leaves(source)
    return (origin(source), tuple((expression(term, scope), label) for term, label in item["columns"]),
            expression(item["where"], scope), item["distinct"], item["orderBy"], item["limit"])


def check_value(item, reply):
    require(reply["status"] == "constructed" and reply["profile"].startswith(item["release"] + "/"),
            "postgres-boolean-fields-positive-rejected", reply)
    require(graph(item) == graph(reply), "postgres-boolean-fields-result-graph", {"expected": item, "actual": reply})


def result_rows(item):
    def relation(table):
        return HEADINGS[table["table"]], DATA[table["table"]]

    def evaluate(term, row):
        if term[0] in ("truth-test", "null-test"):
            return predicate_value(term, lambda child: value(child, row, "postgres"))
        return value(term, row, "postgres")

    data = [tuple(evaluate(term, row) for term, _ in item["columns"])
            for row in rows(item["source"], "postgres", result_rows, relation)
            if truth(item["where"], row, "postgres")]
    if item["distinct"]:
        data = list(dict.fromkeys(data))

    for position, descending, nulls_first in reversed(item["orderBy"]):
        def sort_key(row):
            cell = row[position - 1]
            return (0 if nulls_first else 2, 0) if cell is None else (1, -cell if descending else cell)

        data.sort(key=sort_key)
    return [list(row) for row in data]


def expected(item):
    return {"status": "rows", "fields": [[label, *RESULT_TYPES[term[0]]] for term, label in item["columns"]],
            "rows": result_rows(item)}


def observation(raw):
    return raw


def observer_request(_, sql):
    return {"sql": sql, "observePostgresTypeIds": True}


def normalize(item, raw):
    return raw if item["orderBy"] else unordered(raw)


def check_observation(item, raw, sql):
    expected_result = normalize(item, expected(item))
    observed = normalize(item, raw)
    differences = {"sql": sql, "expected": expected_result, "observed": observed}
    require(observed["status"] == "rows", "postgres-boolean-fields-native-rejection", differences)
    try:
        same(expected_result, observed)
    except Failure as error:
        raise Failure(error.signal, differences) from error


def check_fault_witness(item, witness):
    same(normalize(item, expected(item)), witness["expected"])
    require(witness["observed"]["status"] == "rows", "postgres-boolean-fields-fault-native")


def controls():
    item = finite("pg-17.2", "physical", "enabled", "absent", "ordered", False)
    observed = expected(item)
    check_observation(item, observed, "control")
    fields = [["result", "int4", 23], observed["fields"][1]]
    controls = {"value-type": {**observed, "rows": [[int(a) if a is not None else None, b] for a, b in observed["rows"]]},
                "native-type": {**observed, "fields": fields},
                "multiplicity": {**observed, "rows": [list(row) for row in dict.fromkeys(map(tuple, observed["rows"]))]},
                "order": {**observed, "rows": observed["rows"][::-1]}}
    results = {}
    for name, actual in controls.items():
        try:
            check_observation(item, actual, name)
        except Failure as error:
            require(error.signal == "meaning-mismatch", "postgres-boolean-fields-control-signal", name)
            results[name] = {"input": item, **error.detail}
        else:
            raise Failure("postgres-boolean-fields-control-accepted", name)
    return results


def required_forms(*_):
    return {"field/enabled", "field/fallback", "occurrence/0", "occurrence/later", "source/physical",
            "source/INNER", "source/LEFT", "source/CROSS", "source/nested", "filter/absent", "filter/present",
            "filter/nested", "operand/truth-test", "operand/logic", "operand/null-test", "duplicate-label",
            "distinct/True", "distinct/False", "order/True", "order/False", "null", "empty"}


def sample_forms(forms, response, _, item):
    def visit(term, scope, depth=0):
        if term is None:
            return
        role = term[0]
        forms["operand/" + role] += 1
        if role == "bool-slot":
            _, occurrence, name = expression(term, scope)
            forms["field/" + name] += 1
            forms["occurrence/" + ("later" if occurrence else "0")] += 1
        if depth >= 2:
            forms["filter/nested"] += 1
        if role in ("null-test", "truth-test", "logic"):
            for child in term[2:]:
                visit(child, scope, depth + 1)

    def source_roles(source, depth=0):
        if is_join(source):
            forms["source/" + source["kind"]] += 1
            if depth:
                forms["source/nested"] += 1
            source_roles(source["left"], depth + 1)
            source_roles(source["right"], depth + 1)
        else:
            forms["source/physical"] += 1

    source = response["source"]
    scope = leaves(source)
    source_roles(source)
    for term, _ in response["columns"]:
        visit(term, scope)
    visit(response["where"], scope)
    forms["filter/" + ("absent" if response["where"] is None else "present")] += 1
    forms[f"order/{bool(response['orderBy'])}"] += 1
    forms[f"distinct/{response['distinct']}"] += 1
    if len({label for _, label in response["columns"]}) < len(response["columns"]):
        forms["duplicate-label"] += 1
    data = result_rows(item)
    if not data:
        forms["empty"] += 1
    if any(None in row for row in data):
        forms["null"] += 1


def faults(_, tag):
    renderer = "sql-semantics-postgres/src/Ir/SelectSql.php"
    physical = finite(tag, "physical", "enabled", "absent", "unordered", False)
    read = '$name = $input->columns[$expression->slot]->quoted();'
    boolean = "$this->column($expression, $source, $offset, $qualify)"
    column_dispatch = "=> " + boolean + ","
    keep = "$sql .= ' WHERE ' . $this->predicate($select->where, $source);"
    truth_test = "$test->operator->value . ')'"
    return [
        RenderFault("wrong-boolean-slot", renderer, read,
                    read.replace("$expression->slot", "$expression instanceof BooleanColumnReference ? 2 : $expression->slot"),
                    physical, "meaning-mismatch"),
        RenderFault("erase-boolean-null-extension", renderer, "$source->kind->value . ' JOIN '",
                    "'INNER' . ' JOIN '", finite(tag, "left", "enabled", "absent", "unordered", False), "meaning-mismatch"),
        RenderFault("replace-boolean-with-integer", renderer, column_dispatch,
                    "=> $expression instanceof BooleanColumnReference ? 'CAST(' . " + boolean + " . ' AS pg_catalog.int4)' : " + boolean + ",",
                    physical, "meaning-mismatch"),
        RenderFault("erase-boolean-filter", renderer, keep, "$sql .= '';",
                    finite(tag, "physical", "enabled", "direct", "unordered", False), "meaning-mismatch"),
        RenderFault("change-boolean-truth-test", renderer, truth_test, "'IS FALSE' . ')'",
                    finite(tag, "physical", "enabled", "is-true", "unordered", False), "meaning-mismatch"),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])
