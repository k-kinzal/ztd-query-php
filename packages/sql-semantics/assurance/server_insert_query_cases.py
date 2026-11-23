"""Independently evaluate the query row bag and assign each value by target position."""

import json
from itertools import product
from random import Random

from evidence_transcript import canonical
from mysql_physical_text_admission import negative as mysql_text_negative
from run import TOOL, digest, require
from server_cases import SCHEMAS
from server_compound_cases import leaf, without_profiles
from server_delete_cases import literal
from server_insert_cases import INITIAL as BASE, LAYOUTS, case as literal_case, declarations, expected as effects, observation
from server_join_cases import compare, join, slot, truth, value

SCAN = [[2, 3], [2, 3], [2, 7], [-1, 4], [3, None], [None, 8]]
DATA = {"ir_insert_scan": SCAN, "ir_insert_zero": []}
INITIAL = {**BASE, "scan": SCAN, "zero": []}
FILTERS = (None, ["boolean", False])
SOURCES = (None, "ir_insert_scan", "ir_insert_zero")


def table(engine, name, columns=("id", "delta")):
    if name is None:
        return None
    return {"schema": SCHEMAS[engine], "table": name, "columns": list(columns),
            **({"only": False} if engine == "postgres" else {})}


def case(engine, tag, layout, query):
    return {"dialect": engine, "release": tag, "target": literal_case(engine, tag, layout, [])["target"], "insertQuery": query}


def typed_distinct_output(engine, query):
    if engine == "postgres" and query["distinct"]:
        query["columns"] = [
            [["builtin-cast", "int4", term] if term == ["null"] else term, name]
            for term, name in query["columns"]
        ]
    return query


def finite_cases(engine, tag):
    for layout, source, distinct, where in product(LAYOUTS, SOURCES, (False, True), FILTERS):
        values = [literal(3), ["null"]] if source is None else [slot(1), slot(0)]
        query = leaf(engine, tag, [[value, f"p{i}"] for i, value in enumerate(values[:len(layout)])], table(engine, source), where)
        query["distinct"] = distinct
        yield case(engine, tag, layout, typed_distinct_output(engine, query))


def finite_id(_, response, _item):
    query = response["insertQuery"]
    role = None if query["source"] is None else query["source"]["table"]
    return LAYOUTS.index(response["target"]["columns"]), SOURCES.index(role), query["distinct"], FILTERS.index(query["where"])


def finite_ids(_, specification, _tag):
    finite = specification["finite"]
    return set(product(range(len(finite["targetLayouts"])), range(len(finite["sourceRelations"])),
                       finite["distinctPolicies"], range(len(finite["predicates"]))))


def seeded(engine, tag, seed, maximum):
    rng = Random(seed)
    for _ in range(maximum):
        layout = rng.sample(["id", "delta", "stamp"], rng.randint(1, 3))
        relation = rng.choice(SOURCES)
        fields = [literal(rng.randint(-15, 20)), ["null"]]
        signed = [literal(rng.randint(-2, 4)), literal(rng.randint(0, 9))]
        if relation is not None:
            signed += [slot(0), slot(1)]
            fields += [slot(0), slot(1)]
        fields += [["operation", rng.choice(("+", "-", "*")), rng.choice(signed), rng.choice(signed)]]
        predicates = [None, ["null"], ["boolean", True], ["boolean", False]]
        if relation is not None:
            predicates.append(compare(rng.choice(("<", ">", "=")), rng.choice(signed), literal(2)))
        query = leaf(engine, tag, [[rng.choice(fields), f"p{i}"] for i in range(len(layout))],
                     table(engine, relation), rng.choice(predicates))
        query["distinct"] = bool(rng.getrandbits(1))
        yield case(engine, tag, layout, typed_distinct_output(engine, query))


def sql_declarations(engine):
    schema = SCHEMAS[engine]
    return [*declarations(engine), *(f"CREATE TABLE {schema}.{rel} (id INTEGER, delta INTEGER)" for rel in DATA)]


def analyzed(engine, tag):
    schema = SCHEMAS[engine]
    start = f"INSERT INTO {schema}.ir_insert_target "
    scan = table(engine, "ir_insert_scan")
    query = leaf(engine, tag, [[slot(0), "left_field"], [slot(1), "right_field"]], scan, compare(">", slot(0), literal(0)))
    query["distinct"] = True
    forms = [
        ("(delta,id) SELECT DISTINCT s.id AS left_field, s.delta AS right_field "
         f"FROM {schema}.ir_insert_scan AS s WHERE s.id > 0", case(engine, tag, ["delta", "id"], query)),
        ("(stamp) SELECT 13 AS p0", case(engine, tag, ["stamp"], leaf(engine, tag, [[literal(13), "p0"]]))),
    ]
    if engine == "postgres":
        forms.append(("(id) SELECT NULL AS p0", case(engine, tag, ["id"], leaf(engine, tag, [[["null"], "p0"]]))))
    same = leaf(engine, tag, [[slot(0), "p0"]], table(engine, "ir_insert_target", ("id",)))
    forms.append((f"(id) SELECT s.id AS p0 FROM {schema}.ir_insert_target AS s", case(engine, tag, ["id"], same)))
    for sql, item in forms:
        yield {"dialect": engine, "release": tag, "sql": start + sql, "declarations": sql_declarations(engine)}, item


def negatives(engine, tag):
    plain = leaf(engine, tag, [[literal(1), "p0"]])
    case1 = lambda expression: case(engine, tag, ["id"], leaf(engine, tag, [[expression, "p0"]]))
    yield case(engine, tag, [], plain), "between one and 1024 target columns"
    yield case(engine, tag, ["id", "delta"], plain), "must match the target width"
    yield case1(["boolean", True]), "requires a signed value or NULL"
    yield case1(["parameter", 1]), "requires a signed value or NULL"
    scan = table(engine, "ir_insert_scan")
    query = {**plain, "source": join("CROSS", scan, scan)}
    yield case(engine, tag, ["id"], query), "must be independent, unbounded and unordered"
    yield case(engine, tag, ["id"], {**plain, "limit": [1, 0]}), "must be independent, unbounded and unordered"
    schema = SCHEMAS[engine]
    yield {"dialect": engine, "release": tag, "sql": f"INSERT INTO {schema}.ir_insert_target (unknown_column) SELECT 1 AS p0",
           "declarations": sql_declarations(engine)}, "unknown_column"
    if engine == "mysql":
        text_type, boundary = mysql_text_negative()
        yield {"dialect": engine, "release": tag, "sql": f"INSERT INTO {schema}.ir_insert_text (delta) SELECT 1 AS p0",
               "declarations": [f"CREATE TABLE {schema}.ir_insert_text (delta {text_type})"]}, boundary
    if engine == "postgres":
        contract = TOOL / "postgres-query-write-admission-v1.json"
        require(digest(contract) == "7d4965e16c5fdb36d2d56918bd36e5f683b18b3439dcc8e229219401b8a817ff",
                "postgres-query-write-admission-mismatch")
        negative = json.loads(contract.read_text())["negative"]
        yield {"dialect": engine, "release": tag, "sql": negative["sql"].format(schema=schema),
               "declarations": sql_declarations(engine)}, {"exception": negative["exception"], "message": negative["message"]}
        yield {"dialect": engine, "release": tag, "sql": f"INSERT INTO {schema}.ir_insert_target (id) SELECT DISTINCT NULL AS p0",
               "declarations": sql_declarations(engine)}, "cannot assign an untyped NULL"
        direct = leaf(engine, tag, [[literal(3), "p0"], [["null"], "p1"]])
        direct["distinct"] = True
        yield case(engine, tag, ["id", "delta"], direct), {
            "exception": "SqlSemantics\\Platform\\PostgreSql\\Model\\Diagnostic\\InvalidConstruction",
            "message": "A DISTINCT insert projection cannot assign an untyped NULL to a non-text column.",
        }


def requests(engine, tag, specification):
    for index, item in enumerate(finite_cases(engine, tag)):
        yield f"finite/{index}", item, item, None
    for seed in specification["exploration"]["seeds"]:
        for index, item in enumerate(seeded(engine, tag, seed, specification["exploration"]["casesPerSeed"])):
            yield f"seed/{seed}/{index}", item, item, None
    for index, (item, meaning) in enumerate(analyzed(engine, tag)):
        yield f"analysis/{index}", item, meaning, None
    for index, (item, reason) in enumerate(negatives(engine, tag)):
        yield f"negative/{index}", item, item, reason


def source_rows(query):
    source = query["source"]
    if source is None:
        return [()]
    columns = ("id", "delta")
    physical = BASE["target"] if source["table"] == "ir_insert_target" else DATA[source["table"]]
    return [(tuple(row[columns.index(name)] for name in source["columns"]),) for row in physical]


def expected(item):
    engine = item["dialect"]
    query = item["insertQuery"]
    rows = [[None if expression == ["builtin-cast", "int4", ["null"]] else value(expression, row, engine)
             for expression, _ in query["columns"]] for row in source_rows(query)
            if truth(query["where"], row, engine)]
    if query["distinct"]:
        rows = [json.loads(row) for row in dict.fromkeys(map(canonical, rows))]
    effect = effects({**item, "rows": rows})
    for phase in ("after", "restored"):
        effect[phase].update(scan=SCAN, zero=[])
    return observation(effect)


def structural_model(item):
    return {"target": item["target"], "insertQuery": without_profiles(item["insertQuery"])}
