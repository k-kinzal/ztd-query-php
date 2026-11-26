"""Construct bounded rename inputs and model their catalog and data effects."""

import itertools
import random

from run_sqlite_create_table import quoted
from sqlite_engine import typed

SCHEMAS = "main", "temp"
TABLE = "ir_rename"
INDEX, CHILD, VIEW, LOG, TRIGGER = ("ir_rename_index", "ir_rename_child", "ir_rename_view", "ir_rename_log", "ir_rename_trigger")
DESTINATIONS = {"ascii": "moved", "quoted": 'to"right', "empty": "", "unicode": "移動 حد"}
FIXTURE = "PRAGMA foreign_keys=ON; PRAGMA legacy_alter_table=OFF; PRAGMA writable_schema=OFF;"
RESULT = {"names": [], "rows": [], "parameters": {}, "parameterCount": 0, "readOnly": False,
          "changes": 0, "evaluationError": None}


def rename(schema, destination, slot=None, *, columns=("c0", "c1"), table=TABLE, topology="simple", quote=True):
    return {"operation": "rename", "schema": schema, "table": table, "column": None if slot is None else columns[slot],
            "destination": destination, "quoteReferences": None if slot is None else quote, "columns": list(columns), "topology": topology}


def fixture_table(item, schema):
    if item["topology"] == "dependent" and schema != item["schema"]:
        return "other_" + item["table"]
    return item["table"]


def declarations(item):
    names = [quoted(name) for name in item["columns"]]
    definitions = [names[0] + " INTEGER PRIMARY KEY", *(name + " INTEGER" for name in names[1:])]
    return [f"CREATE TABLE {schema}.{quoted(fixture_table(item, schema))} ({', '.join(definitions)})" for schema in SCHEMAS]


def finite_ids(domain):
    return set(itertools.product(domain["schemas"], domain["targets"], domain["destinationKinds"], domain["topologies"]))


def finite_id(measured, domain):
    result, observation = measured
    role = {None: "table", "c0": "first-column", "c1": "second-column"}[result["column"]]
    destination = next(name for name in domain["destinationKinds"] if DESTINATIONS[name] == result["destination"])
    kind = "dependent" if observation["after"]["dependencies"]["key"] else "simple"
    return result["schema"], role, destination, kind


def visit(slot, rng):
    return f"c{slot}_" + "".join(rng.choice('aB٠"日 -') for _ in range(rng.randrange(1, 10)))


def explore(seed, number):
    rng = random.Random(seed)
    for ordinal in range(number):
        width = 2 + ordinal % 5
        columns = tuple(visit(slot, rng) for slot in range(width))
        target = width - 1 if ordinal == 4 else rng.choice((None, *range(width)))
        destination = "change " + visit(width, rng)
        if ordinal % 7 == 0:
            destination = "" if ordinal % 14 == 0 else "Unicode移"
        quote = target is None or ordinal % 6 != 0
        if not quote:
            destination = f"bare_${ordinal}移"
        yield rename(rng.choice(SCHEMAS), destination, target, columns=columns, quote=quote,
                     table="ir_" + visit(0, rng), topology=rng.choice(("simple", "dependent")))


def analyzed():
    yield "ALTER TABLE ir_rename RENAME TO via_temp", rename("temp", "via_temp")
    yield "ALTER TABLE MAIN.ir_rename RENAME C1 TO COL", rename("main", "COL", 1, topology="dependent", quote=False)
    yield 'ALTER TABLE temp.ir_rename RENAME COLUMN C0 TO ""', rename("temp", "", 0)
    yield "ALTER TABLE main.ir_rename RENAME COLUMN C0 TO C0", rename("main", "C0", 0, quote=False)
    yield "ALTER TABLE main.ir_rename RENAME COLUMN rowid TO renamed", rename("main", "renamed", 0, columns=("rowid", "n"), quote=False)
    yield "ALTER TABLE main.ir_rename RENAME c1 TO other", rename("main", "other", 1, topology="ambiguous-view", quote=False)
    yield "ALTER TABLE ir_rename RENAME c1 TO new_value", rename("temp", "new_value", 1, topology="temp-view", quote=False)
    yield 'ALTER TABLE ir_rename RENAME c1 TO "new_value"', rename("temp", "new_value", 1, topology="temp-view")
    yield 'ALTER TABLE main.ir_rename RENAME TO ""', rename("main", "", topology="dependent")
    yield 'ALTER TABLE main.ir_rename RENAME TO "moved"', rename("main", "moved", topology="collision")
    yield "ALTER TABLE main.ir_rename RENAME TO moved", rename("main", "moved", topology="shadow-trigger")
    yield "ALTER TABLE main.ir_rename RENAME c1 TO moved", rename("main", "moved", 1, topology="shadow-index", quote=False)


def requests(contract):
    domain, exploration = contract["finite"], contract["exploration"]
    for index, (schema, indicator, destination, topology) in enumerate(itertools.product(
            domain["schemas"], domain["targets"], domain["destinationKinds"], domain["topologies"])):
        slot = {"table": None, "first-column": 0, "second-column": 1}[indicator]
        item = rename(schema, DESTINATIONS[destination], slot, topology=topology)
        yield f"finite/{index}", item, item
    for seed in exploration["seeds"]:
        for index, item in enumerate(explore(seed, exploration["casesPerSeed"])):
            yield f"seed/{seed}/{index}", item, item
    for word in contract["lexicalDomain"]["bareAllowed"]:
        item = rename("main", word, 1, quote=False)
        yield f"keyword/{word}", item, item
    for index, (sql, item) in enumerate(analyzed()):
        yield f"analyze/{index}", {"sql": sql, "declarations": declarations(item)}, item


def negatives(contract):
    yield rename("", "ok"), "rejected", "nonempty schema identifier"
    yield rename("main", "ok", table="sQLite_owned"), "rejected", "reserved SQLite namespace"
    yield rename("main", "SQLITE_new"), "rejected", "reserved SQLite namespace"
    yield rename("main", "IR_RENAME"), "rejected", "distinct destination name"
    for word in ("a;b", "\ufeffbad", *contract["lexicalDomain"]["bareRejected"]):
        yield rename("main", word, 1, quote=False), "rejected", "bare rename destination"
    item = rename("main", "ok")
    yield {"sql": "ALTER TABLE main.not_known RENAME TO changed", "declarations": []}, "rejected", "resolved ordinary table"
    examples = [
        ("ALTER TABLE main.view_target RENAME TO renamed", [*declarations(item),
         "CREATE VIEW main.view_target AS SELECT c0 FROM main.ir_rename"], "renames only a table"),
        ("ALTER TABLE main.ir_rename RENAME COLUMN rowid TO new_label", declarations(item), "does not exist"),
        ("ALTER TABLE main.ir_rename RENAME c1 TO c0", declarations(item), "defined more than once"),
    ]
    for sql, layout, reason in examples:
        yield {"sql": sql, "declarations": layout}, "analysis-rejected", reason


def data(schema, width):
    base = 10 if schema == "main" else 70
    return [(base + row, *(None if row == 1 and slot % 2 else base + row * 10 + slot
                          for slot in range(1, width))) for row in range(2)]


def typed_rows(rows):
    return [[typed(value) for value in row] for row in rows]


def as_subject(columns, lines):
    catalog = [(position, name, "INTEGER", 0, None, int(position == 0), 0)
               for position, name in enumerate(columns)]
    return {"fields": columns, "columns": typed_rows(catalog), "rows": typed_rows(lines)}


def problem(item):
    topology = item["topology"]
    if topology == "collision":
        return "prepare", f"SQLite 1: there is already another table or index with this name: {item['destination']}"
    if topology == "ambiguous-view":
        return "step", f"error in view {VIEW} after rename: ambiguous column name: other"
    if topology == "shadow-trigger":
        return "step", f"error in trigger {TRIGGER} after rename: no such table: main.{item['table']}"
    if topology == "temp-view" and not item["quoteReferences"]:
        return "step", f"error in view {VIEW}: no such column: {item['destination']}"
    return None, None


def snapshot(item, transitioned=False, written=False):
    table, destination, schema = item["table"], item["destination"], item["schema"]
    table_rename = transitioned and item["column"] is None
    current = destination if table_rename else table
    common = list(item["columns"])
    names = list(common)
    if transitioned and item["column"] is not None:
        names[names.index(item["column"])] = destination
    width = len(names)
    fresh_row = (99, *(120 + index for index in range(1, width)))
    catalog = {}
    objects = {}
    for namespace in SCHEMAS:
        same = namespace == schema
        subject = current if same else fixture_table(item, namespace)
        fields = names if same else common
        rows = data(namespace, width) + ([fresh_row] if same and written else [])
        objects[namespace] = {name: None for name in (table, destination)}
        objects[namespace][subject] = as_subject(fields, rows)
        entries = [("table", subject, subject)]
        if same and item["topology"] in ("dependent", "shadow-trigger", "shadow-index"):
            entries += [("index", INDEX, subject), ("table", CHILD, CHILD)]
            if namespace == "main":
                entries += [("view", VIEW, VIEW), ("trigger", TRIGGER, subject), ("table", LOG, LOG)]
        if same and item["topology"] in ("temp-view", "ambiguous-view"):
            entries += [("view", VIEW, VIEW)]
        if same and item["topology"] == "ambiguous-view":
            entries += [("table", LOG, LOG)]
        if same and item["topology"] == "collision":
            entries += [("index", destination, subject)]
        catalog[namespace] = typed_rows(sorted(entries))
    key = link = views = log = child_rows = []
    if item["topology"] in ("dependent", "shadow-trigger", "shadow-index"):
        key = typed_rows([(0, -2, None)] if transitioned and item["topology"] == "shadow-index" else [(0, 1, names[1])])
        link = typed_rows([(0, 0, current, "parent", names[0], "NO ACTION", "NO ACTION", "NONE")])
        child_rows = typed_rows([(data(schema, width)[0][0],)] + ([(99,)] if written else []))
        if schema == "main":
            views = typed_rows([(row[1],) for row in data(schema, width)] + ([(fresh_row[1],)] if written else []))
            log = typed_rows([(fresh_row[1],)] if written else [])
    elif item["topology"] == "temp-view":
        views = typed_rows([(row[1],) for row in data(schema, width)] + ([(fresh_row[1],)] if written else []))
    elif item["topology"] == "ambiguous-view":
        views = typed_rows([(13,), (13,)])
    return {"catalog": catalog, "subjects": objects,
            "dependencies": {"key": key, "foreign": link, "view": views, "log": log, "children": child_rows}}


def expected(item):
    phase, message = problem(item)
    transition = phase is None
    update = None if phase == "prepare" else {**RESULT, "evaluationError": {"code": 1, "message": message}
                                               if phase == "step" else None}
    return {"before": snapshot(item), "statement": update, "prepare": message if phase == "prepare" else None,
            "after": snapshot(item, transition), "written": snapshot(item, transition, True) if transition else None,
            "rolledBack": snapshot(item), "transaction": True}
