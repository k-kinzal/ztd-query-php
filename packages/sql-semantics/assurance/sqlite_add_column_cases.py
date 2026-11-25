"""Inputs and independent state transitions for SQLite ordinary column addition."""

import json
from itertools import product
from random import Random

from run import TOOL, digest, require
from run_sqlite_create_table import quoted
from run_sqlite_scalars import evaluate
from sqlite_default_cases import value_at_affinity
from sqlite_engine import typed

SCHEMAS = ("main", "temp")
TABLE = "ir_add"
AXES = ("schemas", "existingRows", "layout", "default", "notNull")
TARGET_FIELDS = ("operation", "schema", "table", "strict", "column")
COLLATIONS = ("BINARY", "NOCASE", "RTRIM")
REQUIRED_DEFAULT = "Cannot add a NOT NULL column with default value NULL"
STRICT_CHECK_HEADING = (
    "CASE WHEN quick_check GLOB 'CHECK*' THEN raise(ABORT,'CHECK constraint failed')"
    " WHEN quick_check GLOB 'non-* value in*' THEN raise(ABORT,'type mismatch on DEFAULT')"
    " ELSE raise(ABORT,'NOT NULL constraint failed') END"
)


def addition(schema="main", *, heading=("id", "note"), name="added", kind="INTEGER", default=None,
             policy=None, collation="BINARY", strict=False, rows=True, table=TABLE):
    return {"operation": "add-column", "schema": schema, "table": table, "priorHeading": list(heading),
            "strict": strict, "column": [name, kind, policy or False, default, collation], "rows": rows}


def operand_slots(operand):
    if not isinstance(operand, list):
        return set()
    if len(operand) >= 2 and operand[0] == "column" and type(operand[1]) is int:
        return {operand[1]}
    return set().union(*(operand_slots(child) for child in operand))


def check_binding_contract(specification):
    name = "sqlite-append-column-bindings-v1.json"
    expected = "a0e76514694986a9651b08bb646f108bb8cccfa7912fceea17e097d14072342c"
    files = specification["publicationContracts"]
    require(files[name] == expected, "sqlite-append-column-contract-pointer")
    for file, fingerprint in files.items():
        require(digest(TOOL / file) == fingerprint, "sqlite-append-column-contract-digest", file)
    for file, fingerprint in json.loads((TOOL / name).read_text())["references"].items():
        require(digest(TOOL / file) == fingerprint, "sqlite-append-column-prior-contract-digest", file)


def binding_projection(item):
    heading = item["priorHeading"]
    column = item["column"]
    operands = [check["condition"] for check in item.get("checks", ())]
    if len(column) == 6:
        operands.append(column[5][1])
    names = [*heading, column[0]]
    positions = set().union(*(operand_slots(operand) for operand in operands))
    return len(heading), [[slot, names[slot]] for slot in sorted(positions)]


def specifications(domain):
    for schema, rows, (kind, mode), default, policy in product(*(domain[axis] for axis in AXES)):
        yield addition(schema, rows=rows == "populated", kind=kind, strict=mode == "strict",
                       default=default, policy=policy)


def finite_ids(domain):
    return set(product(*(range(len(domain[axis])) for axis in AXES)))


def finite_id(measured, domain):
    response, observation = measured
    schema = response["schema"]
    before = observation["before"][schema]
    _, kind, policy, default, _ = response["column"]
    values = (schema, "populated" if before["rows"] else "empty",
              [kind, "strict" if before["strict"] else "ordinary"], default, policy or None)
    return tuple(domain[key].index(value) for key, value in zip(AXES, values))


def identifier(rng, index):
    return str(index) + "-" + "".join(rng.choice('aK_" 日') for _ in range(rng.randrange(1, 7)))


def explore(domain, seed, count):
    rng = Random(seed)
    more = [["text", "A "], ["text", " A"], ["text", "É"], ["text", "it’s \"quoted\""], ["integer", "0"],
            ["integer", "9223372036854775807"], ["hex", "C", True], ["real", "2", "0", None, False]]
    for index in range(count):
        width = 1 + index % 9
        heading = tuple(identifier(rng, slot) for slot in range(width))
        variant = index % 8
        collation = COLLATIONS[index % len(COLLATIONS)]
        kind, mode = rng.choice(domain["layout"])
        if index % 6 == 2:
            kind, mode = "TEXT", "ordinary"
        default = rng.choice(domain["default"] + more)
        name = ("", "rowid", "oid", "_rowid_", 'field"quoted', "نص", "Select", identifier(rng, width))[variant]
        yield addition(SCHEMAS[index % 2], heading=heading, name=name, kind=kind, strict=mode == "strict",
                       default=default, collation=collation, policy=rng.choice(domain["notNull"]),
                       rows=bool(rng.getrandbits(1)), table=TABLE if index % 3 else "Table " + identifier(rng, index))


def declarations(item):
    names = item["priorHeading"]
    first = quoted(names[0]) + " INTEGER PRIMARY KEY"
    fields = ", ".join([first, *(quoted(name) + " TEXT" for name in names[1:])])
    return [f"CREATE TABLE {schema}.{quoted(item['table'])} ({fields})" +
            (" STRICT" if prior_strict(item, schema) else "") for schema in SCHEMAS]


def analyzed():
    yield "ALTER TABLE ir_add ADD label TEXT DEFAULT 'A' COLLATE NOCASE", addition(
        "temp", name="label", kind="TEXT", default=["text", "A"], collation="NOCASE")
    yield "ALTER TABLE MAIN.ir_add ADD COLUMN counter int NOT NULL ON CONFLICT FAIL DEFAULT +7", addition(
        name="counter", kind="INT", strict=True, default=["integer", "7"], policy="FAIL")
    yield 'ALTER TABLE temp.ir_add ADD COLUMN "rowid" TEXT DEFAULT \'old\'', addition(
        "temp", name="rowid", kind="TEXT", default=["text", "old"])
    yield "ALTER TABLE main.ir_add ADD score REAL DEFAULT (2.75)", addition(
        name="score", kind="REAL", strict=True, default=["real", "2", "75", None, False])
    yield "ALTER TABLE main.ir_add ADD memo TEXT NOT NULL", addition(
        name="memo", kind="TEXT", policy="ABORT", rows=False)
    yield "ALTER TABLE temp.ir_add ADD \"\" ANY DEFAULT '0001'", addition(
        "temp", name="", kind="ANY", strict=True, default=["text", "0001"])
    yield "ALTER TABLE main.ir_add ADD tag BLOB DEFAULT x'00ff'", addition(
        name="tag", kind="BLOB", default=["blob", "00FF"])
    yield 'ALTER TABLE temp.ir_add ADD "quo""ted" INTEGER DEFAULT NULL NOT NULL ON CONFLICT IGNORE', addition(
        "temp", name='quo"ted', default=["null"], policy="IGNORE")
    yield "ALTER TABLE main.ir_add ADD value TEXT DEFAULT 1E2", addition(
        name="value", kind="TEXT", default=["real", "1", None, "2", False, True])
    yield "ALTER TABLE temp.ir_add ADD value TEXT DEFAULT 1E+2", addition(
        "temp", name="value", kind="TEXT", strict=True, default=["real", "1", None, "+2", False, True])


def requests(contract):
    domain = contract["finite"]
    for index, item in enumerate(specifications(domain)):
        yield f"finite/{index}", item, item
    for seed in contract["exploration"]["seeds"]:
        for index, item in enumerate(explore(domain, seed, contract["exploration"]["casesPerSeed"])):
            yield f"seed/{seed}/{index}", item, item
    for index, (sql, item) in enumerate(analyzed()):
        yield f"analyze/{index}", {"sql": sql, "declarations": declarations(item)}, item
    maximum = addition(heading=tuple(f"c{slot}" for slot in range(1999)), kind="INT", rows=False, default=["integer", "7"])
    yield "boundary/1999", maximum, maximum
    zero_byte = addition(kind="TEXT", default=["text", "a\0b"], strict=True)
    yield "boundary/zero-byte-text", zero_byte, zero_byte


def negatives():
    yield addition(""), "rejected", "nonempty schema identifier"
    yield addition(table="sQLiTe_resource"), "rejected", "reserved SQLite namespace"
    yield addition(heading=()), "rejected", "nonempty ordered prior heading"
    yield addition(heading=(f"c{slot}" for slot in range(2000))), "rejected", "2,000 declared columns"
    yield {**addition(), "priorHeading": {"2": "id", "8": "note"}}, "rejected", "ordered prior heading"
    yield addition(heading=("id", 2)), "rejected", "ordered prior heading"
    yield addition(heading=("id", "ID")), "rejected", "distinct declared names"
    yield addition(name="NoTe"), "rejected", "distinct declared name"
    yield addition(strict=True, kind=""), "rejected", "STRICT column addition requires a standard type"
    yield generated(), "rejected", "ordinary column addition cannot have a generation expression"

    ordinary, strict = declarations(addition()), declarations(addition(strict=True))
    for sql, catalog, outcome, detail in [
        ("ALTER TABLE missing ADD value TEXT", [], "rejected", "resolved ordinary table"),
        ("ALTER TABLE v ADD value TEXT", [*ordinary, "CREATE VIEW main.v AS SELECT 1 AS n"], "analysis-rejected", "view"),
        ("ALTER TABLE main.ir_add ADD ID TEXT", ordinary, "analysis-rejected", "defined more than once"),
        ("ALTER TABLE main.ir_add ADD untyped", strict, "analysis-rejected", "STRICT"),
        ("ALTER TABLE main.ir_add ADD value INT PRIMARY KEY", ordinary, "analysis-rejected", "PRIMARY KEY"),
        ("ALTER TABLE main.ir_add ADD value INT UNIQUE", ordinary, "analysis-rejected", "UNIQUE"),
        ("ALTER TABLE main.ir_add ADD value INT CHECK (value > 0 AND value < 9)", ordinary, "rejected", "checked implicit comparison"),
        ("ALTER TABLE main.ir_add ADD value INT DEFAULT CURRENT_TIMESTAMP", ordinary, "rejected", "no complete SQLite scalar representation"),
    ]:
        yield {"sql": sql, "declarations": catalog}, outcome, detail


def generated():
    item = addition()
    item["column"].append(["VIRTUAL", ["integer", "1"]])
    return item


def prior_strict(item, schema):
    return item["strict"] if schema == item["schema"] else not item["strict"]


def original_rows(item, schema):
    if schema == item["schema"] and not item["rows"]:
        return []
    base = 10 if schema == "main" else 70
    return [(base + row, *(None if row == 1 and slot % 2 else f"{schema} {slot}:{row}"
                           for slot in range(1, len(item["priorHeading"])))) for row in range(2)]


def convert(value, kind, strict):
    if kind == "ANY":
        return value if strict else value_at_affinity(value, "INTEGER")
    return value_at_affinity(value, "INTEGER" if kind == "INT" else kind)


def expected_class(kind):
    return {"INT": int, "INTEGER": int, "REAL": float, "TEXT": str, "BLOB": bytes, "ANY": None, "": None}[kind]


def default_value(item):
    _, kind, _, default, _ = item["column"]
    value = None if default is None else evaluate(default[:5] if default[0] == "real" else default, {})
    return convert(value, kind, item["strict"])


def old_default_value(item):
    _, kind, _, default, _ = item["column"]
    if kind == "TEXT" and default is not None and default[0] == "real":
        _, whole, fraction, exponent, negative, *flags = default
        point = "" if fraction is None else "." + fraction
        exponent_part = "" if exponent is None else ("E" if flags else "e") + exponent
        return ("-" if negative else "") + whole + point + exponent_part
    return default_value(item)


def mismatch(item, value):
    klass = expected_class(item["column"][1])
    return item["strict"] and value is not None and klass is not None and type(value) is not klass


def statement(columns=(), error=None, changes=0):
    return {"names": list(columns), "rows": [], "parameters": {}, "parameterCount": 0, "readOnly": False,
            "changes": changes, "evaluationError": error}


def error(message, code=19):
    return {"code": code, "message": message}


def adding(item, default_value):
    _, _, policy, _, _ = item["column"]
    required = default_value is None and policy
    labels = (f"raise(ABORT,'{REQUIRED_DEFAULT}')",) if required else (STRICT_CHECK_HEADING,) if item["strict"] else ()
    problem = REQUIRED_DEFAULT if required else "type mismatch on DEFAULT" if mismatch(item, default_value) else None
    return statement(labels, error(problem, 1) if item["rows"] and problem is not None else None)


def probe_values(item):
    kind = item["column"][1]
    if kind == "BLOB" and item["strict"]:
        return b"\x00\xfe", b"\x01"
    if kind in ("INT", "INTEGER", "REAL") and item["strict"]:
        return 2, 3
    return "A", "a "


def collates(value, operand, kind, strict, collation):
    if value is None:
        return None
    operand = convert(operand, kind, strict)
    if type(value) is str and type(operand) is str:
        if collation == "NOCASE":
            lower = str.maketrans("ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz")
            value, operand = value.translate(lower), operand.translate(lower)
        if collation == "RTRIM":
            value, operand = value.rstrip(" "), operand.rstrip(" ")
    return int(value == operand)


def state(item, added=False, written=()):
    heading = item["priorHeading"]
    result = {}
    name, kind, policy, default, collation = item["column"]
    for schema in SCHEMAS:
        strict = prior_strict(item, schema)
        fields = list(heading)
        columns = [[0, heading[0], "INTEGER", 0, False, 1, 0],
                   *[[slot, label, "TEXT", 0, False, 0, 0] for slot, label in enumerate(heading[1:], 1)]]
        rows = original_rows(item, schema)
        equals = None
        if schema == item["schema"] and added:
            fields.append(name)
            columns.append([len(heading), name, kind, int(bool(policy)), default is not None, 0, 0])
            value = old_default_value(item)
            rows = [(*row, value) for row in rows]
            rows.extend((ordinal, *([None] * (len(heading) - 1)), val) for ordinal, val in written)
            equals = [[typed(row[0]), *(typed(collates(row[-1], key, kind, strict, collation)) for key in ("a", "A"))]
                      for row in rows]
        result[schema] = {"root": [typed("table"), typed(item["table"]), typed(item["table"]), typed(2)],
                          "strict": strict, "fields": fields, "columns": normalized(columns),
                          "rows": [list(map(typed, row)) for row in rows], "equals": equals}
    return result


def normalized(columns):
    return [[field if index == 4 else typed(field) for index, field in enumerate(row)] for row in columns]


def omitted(item, value):
    name, kind, policy, _, _ = item["column"]
    origin = f"{item['table']}.{name}"
    if mismatch(item, value):
        storage = {int: "INT", float: "REAL", str: "TEXT", bytes: "BLOB"}[type(value)]
        return statement(error=error(f"cannot store {storage} value in {kind} column {origin}")), []
    if value is None and policy:
        reject = None if policy == "IGNORE" else error(f"NOT NULL constraint failed: {origin}")
        return statement(error=reject), []
    return statement(changes=1), [(89, value)]


def null_rows(item, value):
    name, kind, policy, _, _ = item["column"]
    first, last = (convert(val, kind, item["strict"]) for val in probe_values(item))
    left, right = [(91, first)], [(93, last)]
    inserted = {"ABORT": [], "FAIL": left, "IGNORE": left + right, "REPLACE": left + [(92, value)] + right,
                "ROLLBACK": [], False: left + [(92, None)] + right}[policy]
    reject = policy in ("ABORT", "FAIL", "ROLLBACK") or policy == "REPLACE" and value is None
    if policy == "REPLACE" and value is None:
        inserted = []
    if policy == "REPLACE" and mismatch(item, value):
        inserted = []
        storage = {int: "INT", float: "REAL", str: "TEXT", bytes: "BLOB"}[type(value)]
        return statement(error=error(f"cannot store {storage} value in {kind} column {item['table']}.{name}")), [], True
    outcome = error(f"NOT NULL constraint failed: {item['table']}.{name}") if reject else None
    return statement(error=outcome, changes=len(inserted)), inserted, policy != "ROLLBACK"


def expected(item):
    value = default_value(item)
    before, effect = state(item), adding(item, value)
    if effect["evaluationError"] is not None:
        return {"before": before, "statement": effect, "after": before, "rolledBack": before, "transaction": True, "writes": None}
    omitted_write, default_rows = omitted(item, value)
    null_write, explicit_rows, active = null_rows(item, value)
    return {"before": before, "statement": effect, "after": state(item, True), "rolledBack": before, "transaction": True,
            "writes": {"omitted": omitted_write, "nulls": null_write, "active": active,
                       "visible": state(item, True, default_rows + explicit_rows),
                       "rolledBack": state(item, True, default_rows)}}


def forms(measured):
    reply, observation = measured
    name, kind, policy, default, collation = reply["column"]
    before = observation["before"][reply["schema"]]
    yield f"schema/{reply['schema']}"
    yield f"width/{len(before['fields'])}"
    yield f"strict/{before['strict']}"
    yield f"rows/{bool(before['rows'])}"
    yield f"kind/{kind}"
    yield f"conflict/{policy}"
    yield f"collation/{collation}"
    yield f"default/{None if default is None else default[0]}"
    yield f"empty-name/{name == ''}"
    yield f"quoted-name/{chr(34) in name}"
    yield f"rowid-alias/{name in ('rowid', 'oid', '_rowid_')}"


def required(domain):
    return {
        *(f"schema/{schema}" for schema in SCHEMAS),
        *(f"width/{width}" for width in range(1, 10)),
        *(f"collation/{name}" for name in COLLATIONS),
        *(f"kind/{kind}" for kind, _ in domain["layout"]),
        *(f"conflict/{policy or False}" for policy in domain["notNull"]),
        *(f"default/{operand[0] if operand is not None else None}" for operand in domain["default"]),
        "strict/False", "strict/True", "rows/False", "rows/True", "empty-name/True", "quoted-name/True", "rowid-alias/True",
    }
