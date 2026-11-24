"""Fixed case coordinates and an independent meaning for SQLite index metadata."""
from itertools import product
from random import Random

from run_sqlite_create_table import quoted
from sqlite_flag_pragma_cases import result
from sqlite_index_metadata_fixture import LAYOUTS, automatic, declarations, indexes, primary_ordinal

AXES = ("operations", "selectors", "scopes", "primaryLayouts", "storage", "temporary")
ASCII = str.maketrans("ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz")
HEADS = {"index_info": ("seqno", "cid", "name"), "index_xinfo": ("seqno", "cid", "name", "desc", "coll", "key")}


def arguments(table, layout):
    return {"none": None, "named": "named", "expression": "expression_index", "physical-primary": automatic(table, primary_ordinal(layout)),
            "primary-table": table, "same-name-index-before-table": "collision", "rowid-table": "stock", "missing": "unknown_index"}


def case(operation="index_xinfo", selector="primary-table", scope="implicit", layout="separate", storage="without-rowid",
         temporary="unopened", *, attachment="extension", table="composite", descending=True, offset=9):
    request_schema = None if scope == "implicit" else attachment if scope == "attached" else scope
    item = {"operation": "index-metadata", "pragma": operation, "argument": arguments(table, layout)[selector],
            "schema": request_schema, "layout": layout, "storage": storage == "without-rowid", "temp": temporary == "with-index",
            "attachment": attachment, "table": table, "descending": descending, "offset": offset}
    return with_argument(item, item["argument"])


def selected(item):
    operand = item["argument"]
    if operand is None:
        return None
    name = operand.translate(ASCII)
    choices = list(indexes(item))
    scopes = (item["schema"],) if item["schema"] is not None else ("temp", "main", item["attachment"])
    for schema in scopes:
        match = next((value for value in choices if value.schema == schema and value.name.translate(ASCII) == name), None)
        if match is not None:
            return match
    relations = {("main", "stock"), (item["attachment"], item["table"].translate(ASCII)), (item["attachment"], "collision")}
    if item["temp"]:
        relations.update((("temp", "local_input"), ("temp", item["table"].translate(ASCII))))
    for schema in scopes:
        if (schema, name) in relations:
            return next((value for value in choices if value.schema == schema and value.storage
                         and value.owner.translate(ASCII) == name), None)
    return None


def with_argument(item, operand):
    item = {**item, "argument": operand}
    found = selected(item)
    target = ["empty", item["schema"], operand] if found is None else ["index", found.schema, found.name, found.owner]
    return {**item, "target": target}


def analysis(item, sql, *, complete=True, extra=()):
    return {"sql": sql, "declarations": [*declarations(item), *extra], "catalogComplete": complete,
            "searchPath": ["main", item["attachment"]]}


def source(item, *, lexeme=None, parentheses=True, uppercase=False):
    name, schema, arg = item["pragma"], item["schema"], item["argument"]
    if uppercase:
        name = quoted(name.upper())
    prefix = "PRAGMA " + ("" if schema is None else quoted(schema) + ".") + name
    if arg is None:
        return prefix
    chunk = quoted(arg) if lexeme is None else lexeme
    return prefix + (f" ( {chunk} )" if parentheses else f" = {chunk}")


def requests(specification):
    finite = specification["finite"]
    for ordinal, parameters in enumerate(product(*(finite[axis] for axis in AXES))):
        item = case(*parameters)
        yield f"finite/{ordinal}", analysis(item, source(item, parentheses=ordinal % 2 == 0)), item
        yield f"direct/{ordinal}", item, item
    for seed in specification["exploration"]["seeds"]:
        random = Random(seed)
        for ordinal in range(specification["exploration"]["casesPerSeed"]):
            kind = finite["operations"][ordinal % len(finite["operations"])]
            role = finite["selectors"][(ordinal // 2) % len(finite["selectors"])]
            scope = finite["scopes"][(ordinal // 5) % len(finite["scopes"])]
            layout = tuple(LAYOUTS)[ordinal % len(LAYOUTS)]
            namespace = "" if scope == "attached" and ordinal % 3 == 0 else random.choice(('idx "archive"', "接続", "older store")) + str(ordinal)
            item = case(kind, role, scope, layout, random.choice(finite["storage"]), random.choice(finite["temporary"]),
                        attachment=namespace, table=random.choice(("composite", 'ordered "key"', "履歴")) + str(ordinal),
                        descending=bool(ordinal % 3), offset=random.randint(1, 12))
            yield f"seed/{seed}/{ordinal}", analysis(item, source(item, uppercase=ordinal % 2 == 0)), item
            yield f"direct/{seed}-{ordinal}", item, item
    for ordinal, (kind, layout, role) in enumerate(product(HEADS, ("duplicate-primary-term", "unique-after", "unique-inline-before"),
                                                           ("primary-table", "physical-primary", "same-name-index-before-table"))):
        item = case(kind, role, "attached", layout)
        yield f"analyze/storage-{ordinal}", analysis(item, source(item)), item
    for ordinal, (kind, (argument, lexeme)) in enumerate(product(HEADS, (("1", "+1"), ("+1", "'+1'"), ("-1", "-1")))):
        item = with_argument(case(kind), argument)
        yield f"analyze/number-{ordinal}", analysis(item, source(item, lexeme=lexeme)), item


def finite_ids(domain):
    return set(product(*(range(len(domain[axis])) for axis in AXES)))


def finite_id(measured, domain):
    reply, native = measured
    pragma, schema, text = native["invocation"]
    scope = "implicit" if schema is None else "attached" if schema == "extension" else schema
    layout, storage = native["tableDefinition"]
    temporary = "with-index" if any(row[1][1] == "temp" for row in native["before"]) else "unopened"
    role = next(name for name, operand in arguments("composite", layout).items() if operand == text)
    return tuple(domain[axis].index(value) for axis, value in zip(AXES, (pragma, role, scope, layout, storage, temporary)))


def forms(measured):
    reply, native = measured
    pragma, raw_scope, _ = native["invocation"]
    layout, storage = native["tableDefinition"]
    yield "kind/" + pragma
    yield "layout/" + layout
    yield "store/" + storage
    yield "result/" + reply["target"][0]
    yield "scope/" + ("implicit" if raw_scope is None else raw_scope if raw_scope in ("temp", "main") else "attached")
    if raw_scope == "":
        yield "scope/empty"
    if native["slots"] == [1]:
        yield "index/temp"
    if native["slots"] == [2]:
        yield "index/attached"
    if "key" in native["statement"]["names"]:
        for row in native["statement"]["rows"]:
            if row[1] == ["integer", "-2"]:
                yield "term/expression"
            if row[1] == ["integer", "-1"]:
                yield "term/rowid"
            if row[5] == ["integer", "0"]:
                yield "term/auxiliary"


def required(domain):
    return ({"kind/" + name for name in domain["operations"]} | {"layout/" + name for name in LAYOUTS}
            | {"store/" + name for name in domain["storage"]} | {"scope/" + name for name in domain["scopes"]}
            | {"result/index", "result/empty", "scope/empty", "index/temp", "index/attached", "term/expression",
               "term/rowid", "term/auxiliary"})


def negatives():
    item = case()
    yield analysis(item, "PRAGMA absent.index_info(named)"), "rejected", "requires a known schema qualifier"
    yield analysis(item, "PRAGMA index_xinfo(composite)", complete=False), "rejected", "cannot skip an open index namespace"
    yield analysis(item, "PRAGMA extension.index_info(composite)", complete=False), "rejected", "cannot establish absence in an open namespace"
    yield analysis(item, "PRAGMA main.index_info(named)", extra=("CREATE INDEX main.named ON stock(flag)",)), \
        "rejected", "Conflicting declared indexes"
    yield analysis(item, "PRAGMA main.index_info(sqlite_autoindex_opaque_1)", extra=("CREATE VIRTUAL TABLE main.opaque USING fts5(a)",)), \
        "rejected", "cannot skip an opaque constraint index namespace"
    yield {**item, "pragma": {"kind": "index_info"}}, "type-rejected", "IndexInspectionKind::from()"


def expected(item):
    operation, target = item["pragma"], item["target"]
    chosen = selected(item)
    qualifier = item["schema"] if chosen is None else chosen.schema
    before = [(0, "main", "")]
    after = before.copy()
    if item["temp"]:
        before.append((1, "temp", ""))
    if item["temp"] or qualifier == "temp":
        after.append((1, "temp", ""))
    attachment = (2, item["attachment"], "")
    rows = []
    extended = operation == "index_xinfo"
    if chosen is not None:
        for i, term in enumerate((*chosen.keys, *chosen.auxiliary) if extended else chosen.keys):
            prefix = (i, term.slot, term.name)
            rows.append((*prefix, term.descending, term.collate, int(i < len(chosen.keys))) if extended else prefix)
    slots = [] if chosen is None else [{"main": 0, "temp": 1, item["attachment"]: 2}[chosen.schema]]
    arm = [operation, item["schema"], item["argument"]] if target[0] == "empty" else [operation]
    return {"before": result(rows=(*before, attachment))["rows"], "after": result(rows=(*after, attachment))["rows"],
            "invocation": arm, "slots": slots, "statement": result(HEADS[operation], rows),
            "tableDefinition": [item["layout"], "without-rowid" if item["storage"] else "rowid"]}
