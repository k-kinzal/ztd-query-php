"""SQLite view removal plans and independent catalog transitions."""

from itertools import product
from random import Random

from run_sqlite_create_table import quoted
from sqlite_engine import typed

SCHEMAS = ("main", "temp")
BASE, NEIGHBOR = "ir_view_base", "ir_view_neighbor"
FINITE_NAMES = {"view": "ir_plain", "owned-trigger": "ir_owned", "shadow-view": "ir_shared", "absent": "ir_missing"}
FIXED_ROWS = (-8, None, -8, 7)
STATEMENT = {"names": [], "rows": [], "parameters": {}, "parameterCount": 0, "readOnly": False,
             "changes": 0, "evaluationError": None}


def other(schema):
    return "temp" if schema == "main" else "main"


def case(schema, view, guard, *, present=True, shadow=None, triggers=0, depth=0, values=FIXED_ROWS):
    return {"operation": "drop-view", "schema": schema, "view": view, "ifExists": guard, "present": present,
            "shadow": shadow, "triggers": triggers, "depth": depth, "values": list(values)}


def finite_case(schema, layout, guard):
    return case(schema, FINITE_NAMES[layout], guard, present=layout != "absent",
                triggers=int(layout == "owned-trigger"), depth=int(layout == "owned-trigger"),
                shadow="view" if layout == "shadow-view" else None)


def finite_ids(domain):
    return set(product(domain["schemas"], domain["layouts"], domain["guard"]))


def finite_id(measured, _domain):
    return measured["schema"], measured["layout"], measured["ifExists"]


def seeded(seed, count):
    rng = Random(seed)
    for index in range(count):
        name = "ir_v_" + "".join(rng.choice('aB9 é列"') for _ in range(rng.randrange(1, 16)))
        present = index % 5 != 0
        yield case(
            rng.choice(SCHEMAS), name, bool(rng.getrandbits(1)), present=present, shadow=rng.choice((None, "table", "view")),
            triggers=rng.randrange(4) if present else 0, depth=rng.randrange(4) if present else 0,
            values=[rng.choice((None, *range(-9, 9))) for _ in range(rng.randrange(2, 7))],
        )


def watch(ordinal):
    return f"ir_watch_{ordinal}"


def trigger(ordinal):
    return f"ir_instead_{ordinal}"


def declarations(item):
    result = []
    for schema in SCHEMAS:
        result.append(f"CREATE TABLE {schema}.{BASE} (seq INTEGER, payload INTEGER)")
        result.append(f"CREATE VIEW {schema}.{NEIGHBOR} AS SELECT payload FROM {schema}.{BASE} ORDER BY seq")
        if schema == item["schema"]:
            kind = "view" if item["present"] else None
        else:
            kind = item["shadow"]
        target = schema + "." + quoted(item["view"])
        if kind == "view":
            result.append(f"CREATE VIEW {target} AS SELECT payload FROM {schema}.{BASE} ORDER BY seq")
        elif kind == "table":
            result.append(f"CREATE TABLE {target} (seq INTEGER, payload INTEGER)")
    return result


def analyzed():
    yield "DROP VIEW ir_shared", finite_case("temp", "shadow-view", False)
    yield "DROP VIEW IF EXISTS MAIN.IR_SHARED", finite_case("main", "shadow-view", True)
    yield "DROP VIEW IF EXISTS main.ir_missing", finite_case("main", "absent", True)
    yield "DROP VIEW IF EXISTS temp.ir_missing", finite_case("temp", "absent", True)
    yield 'DROP VIEW main."view with ""label"""', case("main", 'view with "label"', False, shadow="table", triggers=2, depth=3)
    yield "DROP VIEW temp.ir_owned", finite_case("temp", "owned-trigger", False)


def requests(contract):
    domain, settings = contract["finite"], contract["exploration"]
    for index, choices in enumerate(product(domain["schemas"], domain["layouts"], domain["guard"])):
        item = finite_case(*choices)
        yield f"finite/{index}", item, item
    for seed in settings["seeds"]:
        for index, item in enumerate(seeded(seed, settings["casesPerSeed"])):
            yield f"seed/{seed}/{index}", item, item
    for index, (sql, item) in enumerate(analyzed()):
        yield f"analysis/{index}", {"sql": sql, "declarations": declarations(item)}, item


def negatives():
    yield case("", "ir_view", False), "rejected", "nonempty schema identifier"
    yield case("main", "SQLITE_schema", True), "rejected", "reserved SQLite namespace"
    yield {"sql": "DROP VIEW unknown", "declarations": []}, "rejected", "requires a resolved view"
    yield {"sql": "DROP VIEW IF EXISTS unknown", "declarations": ["CREATE TABLE known (value INTEGER)"]}, "rejected", "requires an explicit schema"
    for where, checks in (
        ("main.ir_shared", ["CREATE TABLE main.ir_shared(i INTEGER)"]),
        ("ir_shared", ["CREATE VIEW main.ir_shared AS SELECT 1", "CREATE TABLE temp.ir_shared(i INTEGER)"]),
    ):
        yield {"sql": f"DROP VIEW IF EXISTS {where}", "declarations": checks}, "analysis-rejected", "is a table"


def catalogue(item, removed):
    scope, name = item["schema"], item["view"]
    records = {schema: [("table", BASE, BASE), ("view", NEIGHBOR, NEIGHBOR)] for schema in SCHEMAS}
    if item["shadow"] is not None:
        records[other(scope)].append((item["shadow"], name, name))
    if item["present"] and not removed:
        records[scope].append(("view", name, name))
        records[scope].extend(("trigger", trigger(i), name) for i in range(item["triggers"]))
    records[scope].extend(("view", watch(i), watch(i)) for i in range(item["depth"]))
    return {schema: [[typed(value) for value in row] for row in sorted(records[schema])] for schema in SCHEMAS}


def expected(item):
    scope, name = item["schema"], item["view"]
    failure = None if item["ifExists"] or item["present"] else f"SQLite 1: no such view: {scope}.{name}"
    rows = [[typed(value)] for value in item["values"]]
    return {
        "before": catalogue(item, False), "after": catalogue(item, item["present"]),
        "statement": STATEMENT if failure is None else None, "prepareError": failure,
        "base": {scope: rows, other(scope): [[typed(890)], [typed(891)]]},
        "neighbors": {scope: rows, other(scope): [[typed(890)], [typed(891)]]},
        "baseWrite": {"changes": 1, "rows": [*rows, [typed(701)]]},
        "dependents": [
            f"SQLite 1: no such table: {scope}.{name}" for _ in range(item["depth"])
        ],
    }
