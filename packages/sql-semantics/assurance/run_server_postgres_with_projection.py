"""Check PostgreSQL ordinary WITH against an independent ordered relation model."""

from itertools import product
import json
import random
import re
import sys

from run import TOOL, Failure, digest, require
from run_server import rejects, same
from run_server_join import OBSERVER, main, probe
from run_server_postgres_int8_fields import (
    DATA, DECLARATIONS, EMPTY, HEADINGS, LEFT, OID, address, check_preparation, evaluate, field, prepare, specifications as inputs,
    table, type_of,
)
from run_server_postgres_output_order_names import DIAGNOSTIC, SEMANTIC
from server_delete_cases import literal
from server_result_cases import match, result_window
from sqlite_domain import RenderFault

LABEL = "postgres-with-projection"
CONTRACT_NAME = "postgres-with-projection-v1.json"
CONTRACT = "01663f9eb25f39934ad35ea97de0074ffdaccceae02d2c29734ac54eb297657b"
COMPLETE_SEEDS = True
AXES = ("definitions", "policies", "input", "layout", "resultSelection")
POLICIES = {"default": "", "materialized": "MATERIALIZED", "not-materialized": "NOT MATERIALIZED"}
TYPES = {"slot": "int4", "wide-slot": "int8", "bool-slot": "bool", "text-slot": "text"}
PLANS = re.compile(r'"__ztd_cte_[0-9]+" AS (?:(NOT MATERIALIZED|MATERIALIZED) )?\(')
L = f"public.{LEFT}"


def specifications():
    path = TOOL / CONTRACT_NAME
    require(digest(path) == CONTRACT, "postgres-with-projection-contract-mismatch")
    return json.loads(path.read_text()), inputs()[1]


def scan(relation, fields, source=True):
    return {"source": table(relation) if source else None, "columns": fields, "where": None,
            "orderBy": [], "limit": None, "distinct": False}


def field_scan(relation, names=("id", "low"), labels=("id", "wide")):
    return scan(relation, [[field(relation, 0, key), label] for key, label in zip(names, labels)])


def literal_scan(first=1, second=2):
    return scan(None, [[literal(first), "id"], [literal(second), "wide"]], source=False)


def read(index, columns):
    return {"read": [index, columns]}


def program(tag, definitions, body):
    return {"dialect": "postgres", "release": tag, "with": {"definitions": definitions, "body": body}}


def nested(definitions, body):
    return {"with": {"definitions": definitions, "body": body}}


def width(member):
    if "with" in member:
        return width(member["with"]["body"])
    return len(member["read"][1] if "read" in member else member["columns"])


def finite(tag, amount, policy, data, layout, selected):
    source = EMPTY if data == "empty" else LEFT
    columns = [[1, "next"], [0 if layout == "reorder" else 1, "other"]]
    definitions = [[POLICIES[policy], field_scan(source)]]
    for position in range(1, amount):
        definitions.append(["", read(position - 1, columns)])
    return program(tag, definitions, read(0 if selected == "first" else amount - 1, [[0, "one"], [1, '値 " key']]))


def finite_ids(_, contract, _tag):
    specification = contract["finite"]
    ids = set(product(*(specification[axis] for axis in AXES)))
    require(len(ids) == specification["identities"], "postgres-with-projection-finite-size")
    return ids


def finite_id(_, reply, _item):
    core = reply["with"]
    policy, initial = core["definitions"][0]
    _, columns = core["definitions"][1][1]["read"]
    data = "empty" if initial["source"]["table"] == EMPTY else "populated"
    return (len(core["definitions"]), next(key for key, value in POLICIES.items() if value == policy), data,
            "reorder" if columns[1][0] == 0 else "repeat", "first" if core["body"]["read"][0] == 0 else "last")


def request(tag, sql):
    return {"dialect": "postgres", "release": tag, "sql": sql, "declarations": DECLARATIONS}


def analyzed(tag):
    stock = field_scan(LEFT)
    base_sql = f"SELECT id, low AS wide FROM {L}"
    result = read(0, [[1, "amount"], [0, "item"]])
    yield f"WITH a(first, second) AS ({base_sql}) SELECT second AS amount,first AS item FROM a", program(tag, [["", stock]], result)
    chain = ["", read(0, [[1, "m"], [0, "k"]])]
    answer = read(1, [[1, "item"], [0, "amount"]])
    yield f"WITH a AS ({base_sql}), b AS (SELECT wide AS m,id AS k FROM a) SELECT k AS item,m AS amount FROM b", program(
        tag, [["", stock], chain], answer)
    yield f"WITH a AS MATERIALIZED ({base_sql}), b AS (SELECT wide AS m,id AS k FROM a) SELECT k AS item,m AS amount FROM b", program(
        tag, [["MATERIALIZED", stock], chain], answer)
    yield f"WITH a AS NOT MATERIALIZED ({base_sql}) SELECT wide AS amount,id AS item FROM a", program(tag, [["NOT MATERIALIZED", stock]], result)
    yield f"WITH a AS ({base_sql}), unused AS MATERIALIZED (SELECT 8 AS id,9 AS wide) SELECT wide AS amount,id AS item FROM a", program(
        tag, [["", stock], ["MATERIALIZED", literal_scan(8, 9)]], result)
    yield f"WITH {LEFT} AS (SELECT 8 AS id,9 AS wide) SELECT {LEFT}.wide AS amount,id AS item FROM {LEFT}", program(
        tag, [["", literal_scan(8, 9)]], result)
    external = f"WITH {LEFT} AS (SELECT 8 AS id,9 AS wide), b AS ({base_sql}) SELECT wide AS amount,id AS item FROM b"
    yield external, program(tag, [["", literal_scan(8, 9)], ["", stock]], read(1, result["read"][1]))
    local = nested([["", stock]], read(0, [[1, "id"], [0, "wide"]]))
    yield (f"WITH b AS (WITH a AS ({base_sql}) SELECT wide AS id,id AS wide FROM a) SELECT wide AS amount,id AS item FROM b",
           program(tag, [["", local]], result))
    yield f"WITH a AS ({base_sql}) SELECT id AS item,a.id AS again,a.wide AS amount FROM a", program(
        tag, [["", stock]], read(0, [[0, "item"], [0, "again"], [1, "amount"]]))
    yield f"WITH a AS (SELECT 8 AS id,9 AS wide) {base_sql}", program(tag, [["", literal_scan(8, 9)]], stock)


def negatives(tag):
    body = literal_scan()
    source = read(0, [[0, "n"]])
    for definitions, consumer, message in (
        ([], body, "WITH needs a nonempty ordered definition list."),
        ([["", body], ["", read(1, [[0, "n"]])]], source, "A local read must select a visible WITH definition."),
        ([["", read(1, [[0, "n"]])], ["", body]], source, "A local read must select a visible WITH definition."),
        ([["", body]], read(0, [[2, "missing"]]), "A local read must select an existing WITH output slot."),
        ([["", {**body, "release": "pg-16.6" if tag == "pg-17.2" else "pg-17.2"}]], source, "Each WITH query must use its owning language profile."),
        ([["", scan(None, [[["parameter", 1], "n"]], source=False)], ["", scan(None, [[["parameter", 1, "bool"], "n"]], source=False)]],
         source, "A PostgreSQL parameter position must have one input type."),
    ):
        yield program(tag, definitions, consumer), {"exception": DIAGNOSTIC, "message": message}
    yield request(tag, "WITH a AS (SELECT 1 AS n) SELECT n FROM public.absent_cte"), {
        "exception": SEMANTIC, "message": "Relation absent_cte does not exist"}


def seeded(tag, seed, count):
    rng = random.Random(seed)
    labels = ("id", "again", "again", '値 " projected')
    for index in range(count):
        definitions = []
        for position in range(1 + index % 5):
            if position and rng.randrange(4):
                target = rng.randrange(position)
                columns = [[rng.randrange(width(definitions[target][1])), rng.choice(labels)] for _ in range(1 + rng.randrange(5))]
                value = read(target, columns)
            else:
                value = rng.choice((literal_scan(rng.randrange(-10, 11), rng.randrange(-10, 11)),
                                    field_scan(rng.choice((LEFT, EMPTY)), rng.choice((("id", "high"), ("enabled", "label"))))))
                if (index + position) % 4 == 0:
                    value = nested([[rng.choice(tuple(POLICIES.values())), value]], read(0, [[0, "n"], [1, "n"]]))
            definitions.append([rng.choice(tuple(POLICIES.values())), value])
        target = rng.randrange(len(definitions))
        selected = [[rng.randrange(width(definitions[target][1])), rng.choice(labels)] for _ in range(1 + rng.randrange(5))]
        body = literal_scan(index, -index) if index % 6 == 0 else read(target, selected)
        yield program(tag, definitions, body)


def binding_case(tag):
    first = scan(None, [[["parameter", 1], "a"]], source=False)
    second = scan(None, [[["parameter", 1], "b"]], source=False)
    return {**program(tag, [["", first], ["MATERIALIZED", second]], read(1, [[0, "bound"]])), "bindings": [19]}


def requests(_, tag, contract):
    f = contract["finite"]
    for index, coordinates in enumerate(product(*(f[axis] for axis in AXES))):
        item = finite(tag, *coordinates)
        yield f"finite/{index}", item, item, None
    inputs_ = list(analyzed(tag))
    require(len(inputs_) == len(contract["analysis"]), "postgres-with-projection-analysis-count")
    for index, (sql, item) in enumerate(inputs_):
        yield f"analyze/{index}", request(tag, sql), item, None
    for seed in contract["exploration"]["seeds"]:
        for index, item in enumerate(seeded(tag, seed, contract["exploration"]["casesPerSeed"])):
            yield f"seed/{seed}/{index}", item, item, None
    sample = binding_case(tag)
    sql = "WITH a AS (SELECT CAST($1 AS integer) AS a), b AS MATERIALIZED (SELECT CAST($1 AS integer) AS b) SELECT b AS bound FROM b"
    yield "binding/direct-shared-position", sample, sample, None
    yield "binding/shared-position", request(tag, sql), sample, None
    for index, (item, reason) in enumerate(negatives(tag)):
        yield f"negative/{index}", item, None, reason


def semantic_graph(node):
    if "with" in node:
        program_ = node["with"]
        return ("with", tuple((policy, semantic_graph(definition)) for policy, definition in program_["definitions"]), semantic_graph(program_["body"]))
    if "read" in node:
        return "read", node["read"]
    if "local" in node:
        return "local", semantic_graph(node["local"]["definition"]), node["local"]["columns"]
    source = node["source"]
    fields = tuple((address(term, [source]) if term[0] in TYPES else tuple(term), name) for term, name in node["columns"])
    location = None if source is None else (source["schema"], source["table"], source["only"])
    return "select", location, fields, node["where"], node["distinct"], node["orderBy"], node["limit"]


def policies(node):
    if "with" in node:
        for policy, definition in node["with"]["definitions"]:
            yield policy
            yield from policies(definition)
        yield from policies(node["with"]["body"])
    elif "local" in node:
        yield from policies(node["local"]["definition"])


def check_value(item, reply):
    require(reply["status"] == "constructed" and reply["profile"] == item["release"] + "/utf8/standard-conforming-strings/native",
            "postgres-with-projection-rejected", reply)
    details = {"expected": item, "observed": reply, "sql": reply["sql"]}
    require(semantic_graph(reply) == semantic_graph(item), "postgres-with-projection-graph", details)
    require(PLANS.findall(reply["sql"]) == list(policies(item)), "postgres-with-projection-policy", details)


def result(node, bindings, scope=()):
    if "with" in node:
        local = []
        for _, definition in node["with"]["definitions"]:
            local.append(result(definition, bindings, local))
        return result(node["with"]["body"], bindings, local)
    if "read" in node or "local" in node:
        if "read" in node:
            definition, columns = node["read"]
            fields, data = scope[definition]
        else:
            fields, data = result(node["local"]["definition"], bindings)
            columns = node["local"]["columns"]
        return ([[name, *fields[ordinal][1:]] for ordinal, name in columns],
                [[row[ordinal] for ordinal, _ in columns] for row in data])
    source = node["source"]
    if source is None:
        rows = [()]
    else:
        layout = [HEADINGS[source["table"]].index(name) for name in source["columns"]]
        rows = [(tuple(row[position] for position in layout),) for row in DATA[source["table"]]]
    fields = []
    for term, name in node["columns"]:
        kind = "int4" if term[0] == "parameter" else type_of(term)
        fields.append([name, kind, OID[kind]])
    return fields, [[bindings[term[1] - 1] if term[0] == "parameter" else evaluate(term, row)
                     for term, _ in node["columns"]] for row in rows]


def prediction(item):
    fields, data = result(item, item.get("bindings", []))
    return result_window(fields, data, [], None)


def observer_request(item, sql):
    return {"sql": sql, "observePostgresTypeIds": True, **({"withBindings": item["bindings"]} if "bindings" in item else {})}


def observation(raw):
    return raw


def check_observation(item, actual, sql):
    if "bindings" in item:
        require(actual.get("inputOids") == ["23"], "postgres-with-projection-parameter-signature", {"sql": sql, "observed": actual})
    match(prediction(item), actual, sql)


def required_forms(*_):
    return {"nested", "single", "multiple", "literal", "external", "local-read", "independent-body",
            "repeat", "earlier", "latest", "policy/", "policy/MATERIALIZED", "policy/NOT MATERIALIZED"}


def sample_forms(forms, reply, *_):
    def visit(node):
        if "with" in node:
            definitions = node["with"]["definitions"]
            forms["single" if len(definitions) == 1 else "multiple"] += 1
            for position, (policy, definition) in enumerate(definitions):
                forms["policy/" + policy] += 1
                if "with" in definition:
                    forms["nested"] += 1
                if "read" in definition:
                    forms["latest" if definition["read"][0] == position - 1 else "earlier"] += 1
                visit(definition)
            if "read" not in node["with"]["body"]:
                forms["independent-body"] += 1
            visit(node["with"]["body"])
        elif "read" in node:
            forms["local-read"] += 1
            columns = node["read"][1]
            if len(columns) != len({position for position, _ in columns}):
                forms["repeat"] += 1
        else:
            forms["literal" if node["source"] is None else "external"] += 1

    visit(reply)


def controls():
    item = finite("pg-17.2", 2, "default", "populated", "reorder", "last")
    expected = prediction(item)
    raw = {"status": "rows", "fields": expected["fields"], "rows": [row for band in expected["bands"] for row in band["members"]]}
    check_observation(item, raw, "with control")
    checks = {}
    for name, actual in (
        ("missing-row", {**raw, "rows": raw["rows"][1:]}),
        ("incorrect-width", {**raw, "fields": [[raw["fields"][0][0], "int4", 23], raw["fields"][1]]}),
        ("incorrect-label", {**raw, "fields": [["wrong", *raw["fields"][0][1:]], raw["fields"][1]]}),
    ):
        try:
            check_observation(item, actual, name)
        except Failure as error:
            require(error.signal == "meaning-mismatch", "postgres-with-projection-control-signal", name)
            checks[name] = {"input": item, **error.detail}
        else:
            raise Failure("postgres-with-projection-undetected-control", name)
    return checks


def boundary(item, response):
    if rejects(response, item["boundary"]):
        return
    require(response["status"] == "constructed", "postgres-with-projection-mutant-response", response)
    raise Failure("postgres-with-projection-invalid-published", {"sql": response["sql"], "observed": response, "expected": item["boundary"]})


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
            check_observation(rule.item, witness["observed"], witness["sql"])
    except Failure as error:
        same(rule.signal, error.signal)
        same(witness, error.detail)
        return
    raise Failure("postgres-with-projection-undetected-fault", rule.name)


def faults(_, tag):
    root = "sql-semantics-postgres/src/Ir/Rows/With/"
    item = finite(tag, 2, "materialized", "populated", "reorder", "last")
    invalid = list(negatives(tag))
    scope, scope_reason = invalid[2]
    params, params_reason = invalid[5]
    return [
        RenderFault("redirect-with-read", root + "WithSql.php", "$this->name($query->definition)", "$this->name(0)", item, "meaning-mismatch"),
        RenderFault("change-with-field", root + "WithSql.php", "$renderer->localColumn($column->slot)", "$renderer->localColumn(0)", item, "meaning-mismatch"),
        RenderFault("erase-with-materialization", root + "WithSql.php", "$definition->materialization->value", "''", item, "postgres-with-projection-policy"),
        RenderFault("skip-with-visibility", root + "WithScope.php", "if ($query instanceof WithRead) {",
                    "if ($query instanceof WithRead) { return count($query->columns); } if ($query instanceof WithRead) {",
                    {"boundary": scope_reason}, "postgres-with-projection-invalid-published", scope),
        RenderFault("omit-with-parameter-check", root + "WithProjection.php", "ParameterSignature::checkQueries($independent);", "",
                    {"boundary": params_reason}, "postgres-with-projection-invalid-published", params),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])
