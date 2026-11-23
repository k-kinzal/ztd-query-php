"""Compare MySQL ordinary WITH bindings and marker order with a lexical row model."""

from collections import Counter
from itertools import product
import json
from random import Random
import sys

from run import TOOL, Failure, digest, require
from run_server import rejects, same
from run_server_join import OBSERVER, main, probe
from run_server_mysql_external_views import (
    BASE, MERGE, DECLARATIONS as TABLES, DIAGNOSTICS, TYPES_SQL,
    check_preparation as view_catalog, external, prepare as views, source, specifications as view_specs,
)
from server_join_cases import value
from server_local_projection_cases import mysql_materialized_tags
from sqlite_domain import RenderFault

LABEL = "mysql-with-projection"
CONTRACT_NAME = "mysql-with-projection-v1.json"
CONTRACT = "1bcb961bb9c33ed3c8464175e9fd413adc01ad5322a4a45ef81156e145b82f58"
COMPLETE_SEEDS = True
AXES = ("definitions", "input", "layout", "resultSelection")
EMPTY = "ir_fixture.ir_with_empty"
INPUT = {"populated": BASE, "view": MERGE, "empty": EMPTY}
DECLARATIONS = [*TABLES, f"CREATE TABLE {EMPTY} ({TYPES_SQL})"]


def specifications():
    path = TOOL / CONTRACT_NAME
    require(digest(path) == CONTRACT, "mysql-with-contract-mismatch")
    spec = json.loads(path.read_text())
    profile = view_specs()[1]["mysql"]
    releases = [tag.removeprefix("mysql-") for tag in spec["profiles"]]
    require(set(profile["releases"]) == set(releases) | {tag.removeprefix("mysql-") for tag in spec["rejectedReleases"]},
            "mysql-with-release-partition")
    return spec, {"mysql": {**profile, "releases": releases}}


def prepare(engine, db):
    catalog = views(engine, db)
    setup = db.request({"fixture": [f"DROP TABLE IF EXISTS {EMPTY}", DECLARATIONS[-1],
                                     "SET SESSION optimizer_switch = 'derived_merge=on'"]})
    optimizer = db.request({"sql": "SELECT @@SESSION.optimizer_switch AS options"})
    return {"catalog": catalog, "empty": setup, "optimizer": optimizer}


def check_preparation(engine, environment, prepared):
    view_catalog(engine, environment, prepared["catalog"])
    same({"status": "fixture"}, prepared["empty"])
    optimizer = prepared["optimizer"]
    require(optimizer["status"] == "rows" and "derived_merge=on" in optimizer["rows"][0][0].split(","), "mysql-with-optimizer")


def scan(origin, columns, **options):
    return {"source": source(origin) if origin else None, "columns": columns, "distinct": False,
            "where": None, "orderBy": [], "limit": None, **options}


def stock(origin=BASE):
    return scan(origin, [[["slot", 0], "id"], [["text-slot", 1, 0], "label"]])


def constant(key=8, label=9):
    return scan(None, [[["integer", str(key)], "id"], [["integer", str(label)], "label"]])


def read(position, columns):
    return {"read": [position, columns]}


def with_scope(definitions, body):
    return {"with": {"definitions": [["", definition] for definition in definitions], "body": body}}


def program(tag, definitions, body, **parameters):
    return {"dialect": "mysql", "release": tag, **with_scope(definitions, body), **parameters}


def finite(tag, count, origin, layout, selected):
    columns = [[1, "next"], [0 if layout == "reorder" else 1, "other"]]
    return program(tag, [stock(INPUT[origin]), *(read(index, columns) for index in range(count - 1))],
                   read(0 if selected == "first" else count - 1, [[0, "one"], [1, '値 " key']]))


def finite_ids(_, spec, _tag):
    grid = set(product(*(spec["finite"][axis] for axis in AXES)))
    require(len(grid) == spec["finite"]["identities"], "mysql-with-finite-size")
    return grid


def relation_id(table):
    return table["schema"] + "." + table["table"]


def finite_id(_, reply, _item):
    core = reply["with"]
    definitions = core["definitions"]
    origin = relation_id(definitions[0][1]["source"])
    _, columns = definitions[1][1]["read"]
    return (len(definitions), next(key for key, identity in INPUT.items() if identity == origin),
            "reorder" if columns[1][0] == 0 else "repeat", "first" if core["body"]["read"][0] == 0 else "last")


def request(tag, sql, declarations=DECLARATIONS):
    return {"dialect": "mysql", "release": tag, "sql": sql, "declarations": declarations}


def analyzed(tag):
    base = f"SELECT id,label FROM {BASE}"
    result = read(0, [[1, "text_value"], [0, "key_value"]])
    yield f"WITH a(k,v) AS ({base}) SELECT v AS text_value,k AS key_value FROM a", program(tag, [stock()], result)
    yield f"WITH a AS ({base}), b AS (SELECT label,id FROM a) SELECT id,label FROM b", program(
        tag, [stock(), read(0, [[1, "label"], [0, "id"]])], read(1, [[1, "id"], [0, "label"]]))
    yield f"WITH a AS ({base}), unused AS (SELECT 8 AS id,9 AS label) SELECT label AS text_value,id AS key_value FROM a", program(
        tag, [stock(), constant()], result)
    short = BASE.split(".")[1]
    yield f"WITH {short} AS (SELECT 8 AS id,9 AS label) SELECT label AS text_value,id AS key_value FROM {short}", program(
        tag, [constant()], result)
    yield f"WITH {short} AS (SELECT 8 AS id,9 AS label), b AS ({base}) SELECT label AS text_value,id AS key_value FROM b", program(
        tag, [constant(), stock()], read(1, result["read"][1]))
    nested = with_scope([stock()], read(0, [[0, "id"], [1, "label"]]))
    yield f"WITH b AS (WITH a AS ({base}) SELECT id,label FROM a) SELECT label AS text_value,id AS key_value FROM b", program(tag, [nested], result)
    yield f"WITH a AS ({base}) SELECT id AS first,x.id AS again FROM a x", program(tag, [stock()], read(0, [[0, "first"], [0, "again"]]))
    yield f"WITH a AS (SELECT 8 AS id,9 AS label) {base}", program(tag, [constant()], stock())
    yield f"WITH a AS (SELECT id,label FROM {MERGE}) SELECT label AS text_value,id AS key_value FROM a", program(tag, [stock(MERGE)], result)
    yield f"SELECT q.label AS text_value,q.id AS key_value FROM (WITH a AS ({base}) SELECT id,label FROM a) q", {
        "dialect": "mysql", "release": tag, "local": {"definition": nested, "columns": result["read"][1]}}


def parameters(tag):
    first = scan(None, [[["parameter", 1], "n"]])
    second = scan(None, [[["parameter", 1], "n"]])
    for index in (0, 1):
        item = program(tag, [first, second], read(index, [[0, "value"]]), bindings=[12, -25])
        relation = ("a", "b")[index]
        sql = f"WITH a AS (SELECT CAST(? AS SIGNED) AS n), b AS (SELECT CAST(? AS SIGNED) AS n) SELECT n AS value FROM {relation}"
        yield f"parameter/direct/{index}", item, item, None
        yield f"parameter/analyze/{index}", request(tag, sql), item, None


def negatives(tag):
    valid = constant()
    read_first = read(0, [[0, "n"]])
    other = "mysql-8.1.0" if tag == "mysql-8.0.44" else "mysql-8.0.44"
    for definitions, body, message in (
        ([], valid, "WITH needs a nonempty ordered definition list."),
        ([read_first], read_first, "A local read must select a visible WITH definition."),
        ([read(1, [[0, "n"]]), valid], read_first, "A local read must select a visible WITH definition."),
        ([valid], read(0, [[2, "missing"]]), "A local read must select an existing WITH output slot."),
        ([{**valid, "release": other}], read_first, "Each WITH query must use its owning language profile."),
        ([{**valid, "orderBy": [[1, False, True]]}], read_first, "An ordinary WITH projection needs unbounded unordered SELECT members."),
    ):
        yield program(tag, definitions, body), {"exception": DIAGNOSTICS + "InvalidConstruction", "message": message}
    yield request(tag, "WITH a AS (SELECT 1 AS n) SELECT n FROM ir_fixture.ir_unbound_cte"), {
        "exception": DIAGNOSTICS + "SemanticException", "message": "ir_unbound_cte"}


def seeded(tag, seed, count):
    rng = Random(seed)
    for index in range(count):
        origins = tuple(INPUT.values())
        definitions = [stock(origins[index % len(origins)])]
        for position in range(1, 2 + rng.randrange(4)):
            if (index + position) % 4 == 0:
                member = with_scope([stock(rng.choice(origins))], read(0, [[1, "description"], [0, "k"]]))
            elif rng.randrange(4) == 0:
                member = constant(rng.randrange(-16, 17), index)
            else:
                previous = rng.randrange(position)
                member = read(previous, [[rng.randrange(2), rng.choice(("field", "同じ", "repeat"))] for _ in range(2)])
            definitions.append(member)
        body = stock(rng.choice(origins)) if index % 5 == 0 else read(
            rng.randrange(len(definitions)), [[rng.randrange(2), rng.choice(("same", "same", "値"))] for _ in range(1 + rng.randrange(5))])
        yield program(tag, definitions, body)


def requests(_, tag, spec):
    for index, dimensions in enumerate(product(*(spec["finite"][axis] for axis in AXES))):
        item = finite(tag, *dimensions)
        yield f"finite/{index}", item, item, None
    authored = list(analyzed(tag))
    require(len(authored) == len(spec["analysis"]), "mysql-with-analysis-count")
    for index, (sql, item) in enumerate(authored):
        yield f"analyze/{index}", request(tag, sql), item, None
    yield from parameters(tag)
    for seed in spec["exploration"]["seeds"]:
        for index, item in enumerate(seeded(tag, seed, spec["exploration"]["casesPerSeed"])):
            yield f"seed/{seed}/{index}", item, item, None
    for index, (item, reason) in enumerate(negatives(tag)):
        yield f"negative/{index}", item, None, reason


def core(item):
    if "with" in item:
        program_ = item["with"]
        return "with", tuple((policy, core(member)) for policy, member in program_["definitions"]), core(program_["body"])
    if "local" in item:
        local = item["local"]
        return "local", core(local["definition"]), local["columns"]
    if "read" in item:
        return "read", item["read"]
    return "select", tuple(item[key] for key in ("source", "columns", "where", "distinct", "orderBy", "limit"))


def check_value(item, reply):
    require(reply["status"] == "constructed" and reply["profile"] == item["release"] + "/utf8mb4/lower-case-table-names=0/sql-mode=empty/native/derived-merge=on",
            "mysql-with-rejected", reply)
    require(core(reply) == core(item), "mysql-with-graph", {"expected": item, "observed": reply, "sql": reply["sql"]})


def select_value(item, tag, bindings):
    expressions = [term for term, _ in item["columns"]]
    if item["source"] is None:
        next_markers = {term[1]: next(bindings) for term in expressions if term[0] == "parameter"}
        types = ["LONGLONG" if term[0] == "parameter" else mysql_materialized_tags(tag, [[term, "unused"]])[0] for term in expressions]
        row = [next_markers[term[1]] if term[0] == "parameter" else int(term[1]) for term in expressions]
        rows = [row]
    else:
        table = item["source"]
        data = [] if relation_id(table) == EMPTY else external(table)[1]
        types = ["VAR_STRING" if role[0] == "text-slot" else "LONG" for role in expressions]
        rows = [[value(term, (tuple(row),), "mysql") for term in expressions] for row in data]
    return [[name, kind] for (_, name), kind in zip(item["columns"], types)], rows


def evaluate(item, tag, bindings, scope=()):
    if "with" in item:
        model = item["with"]
        declarations = []
        for _, definition in model["definitions"]:
            declarations.append(evaluate(definition, tag, bindings, declarations))
        return evaluate(model["body"], tag, bindings, declarations)
    if "local" in item or "read" in item:
        if "local" in item:
            local = item["local"]
            fields, rows = evaluate(local["definition"], tag, bindings)
            columns = local["columns"]
        else:
            index, columns = item["read"]
            fields, rows = scope[index]
        return [[name, fields[slot][1]] for slot, name in columns], [[row[slot] for slot, _ in columns] for row in rows]
    return select_value(item, tag, bindings)


def prediction(item):
    bindings = iter(item.get("bindings", ()))
    fields, values = evaluate(item, item["release"], bindings)
    require(not list(bindings), "mysql-with-unused-model-binding")
    return {"status": "rows", "fields": fields, "rows": values,
            **({"bindings": item["bindings"]} if "bindings" in item else {})}


def observer_request(item, sql):
    return {"sql": sql, **({"mysqlWithBindings": item["bindings"]} if "bindings" in item else {})}


def observation(raw):
    return raw


def check_observation(item, actual, sql):
    expected = prediction(item)
    witness = {"expected": expected, "observed": actual, "sql": sql}
    require(actual["status"] == "rows", "mysql-with-native-rejection", witness)
    require(actual["fields"] == expected["fields"] and actual.get("bindings") == expected.get("bindings")
            and Counter(map(tuple, actual["rows"])) == Counter(map(tuple, expected["rows"])), "meaning-mismatch", witness)


def sample_forms(forms, reply, *_):
    def visit(node):
        if "with" in node:
            scope = node["with"]
            forms["definition-count/" + str(len(scope["definitions"]))] += 1
            for _, member in scope["definitions"]:
                if "with" in member:
                    forms["nested-with"] += 1
                visit(member)
            forms["body/" + ("local-read" if "read" in scope["body"] else "independent")] += 1
            visit(scope["body"])
        elif "read" in node:
            position, columns = node["read"]
            forms["predecessor/" + ("first" if position == 0 else "later")] += 1
            for ordinal, _ in columns:
                forms["slot/" + str(ordinal)] += 1
        else:
            source = node["source"]
            forms["input/" + (relation_id(source) if source else "literal")] += 1

    visit(reply)


def required_forms(*_):
    return {"nested-with", "body/local-read", "body/independent", "predecessor/first", "predecessor/later",
            "slot/0", "slot/1", "definition-count/2", "definition-count/4", "input/literal",
            *("input/" + identity for identity in INPUT.values())}


def controls():
    item = finite("mysql-9.1.0", 2, "populated", "repeat", "last")
    healthy = prediction(item)
    check_observation(item, healthy, "ordinary WITH control")
    wrong = {**healthy, "rows": [list(row) for row in dict.fromkeys(map(tuple, healthy["rows"]))]}
    try:
        check_observation(item, wrong, "duplicate rows")
    except Failure as error:
        same("meaning-mismatch", error.signal)
        return {"erase-duplicate-rows": {"input": item, **error.detail}}
    raise Failure("mysql-with-comparator-accepted")


def boundary(item, reply):
    if rejects(reply, item["boundary"]):
        return
    require(reply["status"] == "constructed", "mysql-with-unexpected-mutant-reply", reply)
    raise Failure("mysql-with-published-invalid", {"sql": reply["sql"], "observed": reply, "expected": item["boundary"]})


def fault_probe(item, reply, db):
    if "boundary" in item:
        boundary(item, reply)
    else:
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
    raise Failure("mysql-with-undetected-fault", rule.name)


def faults(_, tag):
    root = "sql-semantics-mysql/src/Ir/Rows/With/"
    model = program(tag, [constant(8, 9), constant(18, 19)], read(1, [[1, "selected"], [1, "same"]]))
    invalid = list(negatives(tag))
    return [
        RenderFault("read-mysql-with-first-binding", root + "WithSql.php", "$this->name($query->definition)", "$this->name(0)",
                    model, "meaning-mismatch"),
        RenderFault("read-mysql-with-first-field", root + "WithSql.php", "$renderer->localColumn($column->slot)", "$renderer->localColumn(0)",
                    model, "meaning-mismatch"),
        RenderFault("remove-mysql-with-output-position", root + "WithSql.php", "foreach ($query->columns as $position => $column)", "foreach (array_slice($query->columns, 0, 1) as $position => $column)",
                    model, "meaning-mismatch"),
        RenderFault("allow-mysql-with-future-reference", root + "WithScope.php", "$headings[$query->definition] ?? null", "$headings[$query->definition] ?? 1024",
                    {"boundary": invalid[2][1]}, "mysql-with-published-invalid", invalid[2][0]),
        RenderFault("allow-mysql-with-release-mismatch", root + "WithScope.php", "Check::input($query->profileId() === $profile,", "Check::input(true,",
                    {"boundary": invalid[4][1]}, "mysql-with-published-invalid", invalid[4][0]),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])
