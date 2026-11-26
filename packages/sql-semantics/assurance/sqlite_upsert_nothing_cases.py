"""Direct input shapes and an ordered-source model for SQLite targetless DO NOTHING."""

from itertools import product
from random import Random

from sqlite_insert_query_cases import query
from sqlite_mutation_conflict_cases import (
    COLUMNS, DECLARATION as TARGET_DDL, FIXTURE as WRITE_FIXTURE, INSERT_CASES, TABLE, expected as write_effect,
    field, insert, outputs, read_atom,
)
from sqlite_relational_cases import evaluate, truth
from sqlite_result_cases import integer

INPUT_TABLES = {
    "clean": [(3, 30, 303)],
    "rowid": [(1, 11, 111)],
    "unique": [(4, 10, 444)],
    "not-null": [(4, 40, None)],
    "multiple": [(5, 40, 504), (1, 11, 111), (4, 10, 444), (3, 30, None), (2, 82, 82), (6, 70, 701)],
}
NAMES = {role: "ir_upsert_" + role.replace("-", "_") for role in INPUT_TABLES}
SOURCE_DDL = "CREATE TABLE {} (id INTEGER, code INTEGER, payload INTEGER)"
DECLARATIONS = [TARGET_DDL, *(SOURCE_DDL.format(name) for name in NAMES.values())]


def data_sql():
    statements = [WRITE_FIXTURE]
    for role, name in NAMES.items():
        statements.append(SOURCE_DDL.format(name) + ";")
        rows = [tuple("NULL" if value is None else str(value) for value in row) for row in INPUT_TABLES[role]]
        statements.append("INSERT INTO " + name + " VALUES " + ", ".join("(" + ",".join(row) + ")" for row in rows) + ";")
    return "\n".join(statements)


FIXTURE = data_sql()


def values(rows, policy, handler, returning=(), columns=COLUMNS):
    return {**insert(rows, policy, returning, columns), "handler": handler}


def projection(role, policy, handler, returning=(), where=None, order=(), limit=None, columns=COLUMNS):
    fields = [[field(COLUMNS, name), "input_" + name] for name in columns]
    return {
        "operation": "insert", "target": ["main", TABLE, list(columns)], "conflict": policy, "handler": handler,
        "writes": [["column", index] for index in range(len(columns))], "returning": outputs(columns, returning), "bindings": {},
        "query": query(["main", NAMES[role], list(COLUMNS)], fields, where, order=order, limit=limit),
    }


def finite(domain):
    for input_, policy, handler, scenario, returned in product(domain["inputs"], domain["policies"], domain["handlers"],
                                                               domain["scenarios"], domain["returning"]):
        layout = [("code", "stored"), ("id", "key")] if returned else []
        if input_ == "values":
            yield values(INSERT_CASES[scenario], policy, handler, layout)
        else:
            yield projection(scenario, policy, handler, layout)


def finite_id(reply, _domain):
    if "query" in reply:
        name = reply["query"]["source"][1]
        scenario = next(key for key, table in NAMES.items() if table == name)
        input_ = "select"
    else:
        observed = [tuple(map(read_atom, row)) for row in reply["rows"]]
        scenario = next(key for key, rows in INSERT_CASES.items() if rows == observed)
        input_ = "values"
    return input_, reply["conflict"], reply["handler"], scenario, bool(reply["returning"])


def finite_ids(domain):
    return {
        (input_, policy, handler, scenario, returned)
        for input_ in domain["inputs"] for policy in domain["policies"] for handler in domain["handlers"]
        for scenario in domain["scenarios"] for returned in domain["returning"]
    }


def seeded(seed, count, domain):
    rng = Random(seed)
    filters = [None, integer(0), integer(1), ["comparison", ">", field(COLUMNS, "id"), integer(1)]]
    for _ in range(count):
        policy, handler = rng.choice(domain["policies"]), rng.choice(domain["handlers"])
        layout = rng.sample(COLUMNS, 3)
        returning = [(rng.choice(COLUMNS), rng.choice(("name", 'other"name', "same", "same"))) for _ in range(rng.randrange(4))]
        if rng.randrange(2):
            mode = rng.choice(("rowid", "unique", "not-null"))
            rows = []
            for index in range(rng.randrange(1, 6)):
                identity = rng.choice((1, 2, 3, 4, 5)) if mode == "rowid" else 20 + index
                code = rng.choice((10, 20, -7, 100)) if mode == "unique" else index + 41
                payload = None if mode == "not-null" and rng.randrange(2) else rng.randrange(-19, 21)
                rows.append((identity, code, payload))
            yield values(rows, policy, handler, returning, layout)
        else:
            limit = [integer(rng.randrange(0, 7)), integer(rng.randrange(0, 3)), False] if rng.randrange(2) else None
            order = [[layout.index("id") + 1, bool(rng.randrange(2)), True]]
            yield projection("multiple", policy, handler, returning, rng.choice(filters), order, limit, layout)


def original_sql():
    yield ("INSERT OR REPLACE INTO main.ir_conflict (id,code,payload) VALUES (4,10,17) ON CONFLICT DO NOTHING RETURNING id AS key",
           values([(4, 10, 17)], "replace", "do-nothing", [("id", "key")]))
    yield ("REPLACE INTO main.ir_conflict (id,code,payload) VALUES (4,40,NULL) ON CONFLICT DO NOTHING RETURNING payload AS value",
           values([(4, 40, None)], "replace", "do-nothing", [("payload", "value")]))
    yield (
        "INSERT OR FAIL INTO main.ir_conflict (id,code,payload) SELECT id AS input_id,code AS input_code,payload AS input_payload "
        "FROM main.ir_upsert_multiple WHERE 1 ORDER BY 1 DESC NULLS LAST LIMIT 4 OFFSET 1 ON CONFLICT DO NOTHING RETURNING id AS key",
        projection("multiple", "fail", "do-nothing", [("id", "key")], integer(1), [[1, True, False]],
                   [integer(4), integer(1), False]),
    )
    yield (
        "INSERT INTO main.ir_conflict (id,code,payload) VALUES (1,11,1),(7,70,7),(8,80,8) ON CONFLICT DO NOTHING",
        values([(1, 11, 1), (7, 70, 7), (8, 80, 8)], "inherit", "do-nothing"),
    )


def negatives():
    yield {
        "operation": "insert", "target": ["main", TABLE, []], "conflict": "inherit", "handler": "do-nothing",
        "writes": [], "returning": [], "defaults": True,
    }, "rejected", "DEFAULT VALUES cannot take an INSERT conflict handler"
    for clause in (
        "ON CONFLICT(id) DO NOTHING",
        "ON CONFLICT DO UPDATE SET payload = 13",
        "ON CONFLICT(id) DO NOTHING ON CONFLICT DO NOTHING",
    ):
        yield ({"sql": f"INSERT INTO {TABLE} (id,code,payload) VALUES (3,30,30) {clause}", "declarations": DECLARATIONS},
               "rejected", "requires a single targetless DO NOTHING clause")


def requests(specification):
    domain = specification["finite"]
    for index, request in enumerate(finite(domain)):
        yield f"finite/{index}", request, request
    schedule = specification["exploration"]
    for seed in schedule["seeds"]:
        for index, request in enumerate(seeded(seed, schedule["casesPerSeed"], domain)):
            yield f"seed/{seed}/{index}", request, request
    for index, (sql, request) in enumerate(original_sql()):
        yield f"analysis/{index}", {"sql": sql, "declarations": DECLARATIONS}, request


def selected(request):
    layout = request["query"]
    table = layout["source"][1]
    source = INPUT_TABLES[next(role for role, name in NAMES.items() if name == table)]
    predicate = layout["where"]
    values = [
        tuple(evaluate(operand, (row,), {}) for operand, _ in layout["columns"])
        for row in source if predicate is None or truth(evaluate(predicate, (row,), {})) is True
    ]
    for position, descending, _ in reversed(layout["orderBy"]):
        values.sort(key=lambda row: row[position - 1], reverse=descending)
    if layout["limit"] is not None:
        count, offset, _ = layout["limit"]
        start = 0 if offset is None else read_atom(offset)
        values = values[start:start + read_atom(count)]
    columns = request["target"][2]
    return [tuple(dict(zip(columns, row))[name] for name in COLUMNS) for row in values]


def expected(request, recursive, explicit):
    if "query" not in request:
        return write_effect(request, recursive, explicit)
    columns = request["target"][2]
    labels = [(columns[ref[1]], name) for ref, name in request["returning"]]
    converted = values(selected(request), request["conflict"], request["handler"], labels, tuple(columns))
    return write_effect(converted, recursive, explicit)
