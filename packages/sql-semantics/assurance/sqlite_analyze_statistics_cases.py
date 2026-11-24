"""Independent SQLite ANALYZE target and exact STAT1 prediction model."""

from itertools import product
from random import Random
from typing import NamedTuple

from run_sqlite_create_table import quoted
from sqlite_add_column_cases import statement
from sqlite_engine import typed

AXES = ("targets", "history", "attachments")
SCHEMAS = ("main", "temp", "extension")
DATABASE_SLOT = {"main": 0, "temp": 1, "extension": 2}
CASEFOLD = str.maketrans("ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz")


class Index(NamedTuple):
    name: str
    columns: tuple
    partial: bool = False
    table_key: bool = False


def automatic(table):
    return f"sqlite_autoindex_{table}_1"


def schema_at(item, slot):
    return item["attachment"] if slot == "extension" else slot


def targets(item):
    name, attached = item["table"], item["attachment"]
    return {
        "all": ["all"], "schema-main": ["schema", "main"], "schema-temp": ["schema", "temp"],
        "schema-attached": ["schema", attached], "main-table": ["relation", "main", name, "table"],
        "temp-table": ["relation", "temp", name, "table"], "attached-table": ["relation", attached, name, "table"],
        "unqualified-index-priority": ["index", "temp", "shadow", name],
        "main-index": ["index", "main", "col", name],
        "without-rowid-index": ["index", attached, automatic("no_rowid"), "no_rowid"],
        "ordinary-implicit-index": ["index", attached, automatic("hidden"), "hidden"],
        "no-index-table": ["relation", "main", "no_keys", "table"],
        "empty-table": ["relation", "main", "empty", "table"], "view": ["relation", "main", "watch", "view"],
    }


def case(role="all", history="stale", attachment="named", *, table="records", marker="", shift=0):
    item = {"operation": "analyze-statistics", "history": history, "attachment": "extension" if attachment == "named" else "",
            "table": table, "marker": marker, "shift": shift}
    return {**item, "target": targets(item)[role]}


def declarations(item):
    table = quoted(item["table"])
    for slot in SCHEMAS:
        schema = quoted(schema_at(item, slot))
        yield f"CREATE TABLE {schema}.{table} (id INTEGER PRIMARY KEY, key TEXT, code INT)"
        yield f"CREATE INDEX {schema}.col ON {table} (key COLLATE NOCASE, code)"
        yield f"CREATE INDEX {schema}.partial ON {table} (code) WHERE code IS NOT NULL"
    yield f"CREATE INDEX temp.shadow ON {table} (code)"
    yield "CREATE TABLE main.shadow (code INT)"
    yield "CREATE TABLE main.extension (code INT)"
    yield "CREATE TABLE main.no_keys (code INT)"
    yield "CREATE TABLE main.empty (id INT UNIQUE)"
    yield f"CREATE VIEW main.watch AS SELECT code FROM main.{table}"
    schema = quoted(item["attachment"])
    yield f"CREATE TABLE {schema}.no_rowid (a TEXT PRIMARY KEY,b INT) WITHOUT ROWID"
    yield f"CREATE TABLE {schema}.hidden (a INT UNIQUE,b INT)"


def population(item):
    mono = {
        "main": (1, 2, 3, None, 1),
        "temp": (2, 2, 2, None, 2),
        "extension": (1, 2, 3, None, 4),
    }
    marker, shift, table = item["marker"], item["shift"], item["table"]
    for slot in SCHEMAS:
        keys = ["A" + marker, "a" + marker, "Z" + marker, "Q" + marker, None]
        rows = [(position + 1, key, None if code is None else code + shift)
                for position, (key, code) in enumerate(zip(keys, mono[slot]))]
        indexes = [Index("col", ((1, "NOCASE"), (2, "BINARY"))), Index("partial", ((2, "BINARY"),), True)]
        if slot == "temp":
            indexes.append(Index("shadow", ((2, "BINARY"),)))
        yield slot, table, ["id", "key", "code"], rows, indexes
    yield "main", "shadow", ["code"], [(2,), (4,)], []
    yield "main", "extension", ["code"], [(11,), (12,), (13,)], []
    yield "main", "no_keys", ["code"], [(7,), (8,)], []
    yield "main", "empty", ["id"], [], [Index(automatic("empty"), ((0, "BINARY"),))]
    yield "extension", "no_rowid", ["a", "b"], [("a", 1), ("b", 2), ("c", 3)], [
        Index(automatic("no_rowid"), ((0, "BINARY"),), table_key=True),
    ]
    yield "extension", "hidden", ["a", "b"], [(1, 2), (2, 3)], [Index(automatic("hidden"), ((0, "BINARY"),))]


def normalize_value(value, coll):
    return value.translate(CASEFOLD) if isinstance(value, str) and coll == "NOCASE" else value


def index_stat(table, index, stored):
    keys = [tuple(normalize_value(row[slot], coll) for slot, coll in index.columns)
            for row in stored if not index.partial or row[2] is not None]
    if not keys:
        return []
    count = len(keys)
    groups = [len({key[:size] for key in keys}) for size in range(1, len(index.columns) + 1)]
    stat = " ".join(map(str, [count, *((count + group - 1) // group for group in groups)]))
    return [(table, table if index.table_key else index.name, stat)]


def table_stat(table, rows, indexes):
    if indexes:
        return [entry for index in indexes for entry in index_stat(table, index, rows)]
    return [(table, None, str(len(rows)))] if rows else []


def schemata(item):
    selector = item["target"]
    if selector[0] == "all":
        return ["main", "extension"]
    return [next(slot for slot in SCHEMAS if schema_at(item, slot) == selector[1])]


def initial(item):
    if item["history"] == "fresh":
        return {slot: None for slot in SCHEMAS}
    stats = {slot: [] for slot in SCHEMAS}
    for slot, table, _, rows, indexes in population(item):
        stats[slot].extend((table, index, "99 11" if table == item["table"] else stat)
                           for table, index, stat in table_stat(table, rows, indexes))
    return stats


def gather(item, stats):
    selection = item["target"]
    selected = schemata(item)
    events = []
    for slot in selected:
        state = stats[slot] or []
        if selection[0] in ("all", "schema"):
            retained = []
        elif selection[0] == "relation":
            retained = [entry for entry in state if entry[0] != selection[2]]
        else:
            retained = [entry for entry in state if entry[1] != selection[2]]
        for owner_slot, table, _, rows, indexes in population(item):
            if owner_slot != slot:
                continue
            if selection[0] in ("all", "schema") or (selection[0] == "relation" and selection[2] == table):
                events.append([schema_at(item, slot), table])
                retained.extend(table_stat(table, rows, indexes))
            elif selection[0] == "index" and selection[3] == table:
                index = next(index for index in indexes if index.name == selection[2])
                events.append([schema_at(item, slot), table])
                retained.extend(index_stat(table, index, rows))
        stats[slot] = retained
    return sorted(events)


def rows_of(stats):
    return {slot: None if rows is None else [[typed(value) for value in row]
                for row in sorted(rows, key=lambda row: (row[0], row[1] is not None, row[1] or "", row[2]))]
            for slot, rows in stats.items()}


def expected(item):
    stats = initial(item)
    before = rows_of(stats)
    cycles = []
    for _ in range(2 if item["history"] == "twice" else 1):
        events = gather(item, stats)
        cycles.append({"statement": statement(), "events": events,
                       "loaded": [DATABASE_SLOT[slot] for slot in schemata(item)], "statistics": rows_of(stats)})
    table_rows = [{"schema": schema_at(item, slot), "name": name, "fields": fields,
                   "rows": [[typed(value) for value in row] for row in rows]}
                  for slot, name, fields, rows, _ in population(item)]
    return {"before": before, "rounds": cycles, "after": rows_of(stats), "restored": before,
            "active": True, "attachment": item["attachment"], "tablesBefore": table_rows,
            "tablesAfter": table_rows, "tablesRestored": table_rows}


def analysis(item, sql, *, complete=True, extra=()):
    return {"sql": sql, "declarations": [*declarations(item), *extra], "catalogComplete": complete,
            "searchPath": ["main", item["attachment"]]}


def authored():
    yield case("schema-attached"), "ANALYZE EXTENSION"
    yield case("schema-attached", attachment="empty-name"), 'ANALYZE ""'
    yield case("schema-temp"), "ANALYZE Temp"
    yield case("temp-table"), "ANALYZE records"
    yield case("unqualified-index-priority"), "ANALYZE shadow"
    yield case("main-index"), "ANALYZE main.col"
    yield case("without-rowid-index", "twice"), "ANALYZE extension.sqlite_autoindex_no_rowid_1"
    yield case("ordinary-implicit-index", attachment="empty-name"), 'ANALYZE ""."sqlite_autoindex_hidden_1"'
    yield case("empty-table", "fresh"), "ANALYZE main.empty"
    yield case("view", "fresh"), "ANALYZE main.watch"


def requests(specification):
    domain = specification["finite"]
    for index, choices in enumerate(product(*(domain[name] for name in AXES))):
        item = case(*choices)
        yield f"finite/{index}", item, item
    for seed in specification["exploration"]["seeds"]:
        random = Random(seed)
        for index in range(specification["exploration"]["casesPerSeed"]):
            target = domain["targets"][index % len(domain["targets"])]
            history = "twice" if target == "without-rowid-index" else random.choice(domain["history"])
            attachment = domain["attachments"][index % 2]
            name = random.choice(('stock "列" ', "stat ", "values_")) + str(index)
            item = case(target, history, attachment, table=name, marker=random.choice(("", "é", "日")), shift=random.randrange(-3, 3))
            yield f"seed/{seed}/{index}", item, item
    for index, (item, sql) in enumerate(authored()):
        yield f"analyze/{index}", analysis(item, sql), item


def finite_ids(domain):
    return set(product(*(range(len(domain[name])) for name in AXES)))


def finite_id(measured, domain):
    reply, native = measured
    attached = native["attachment"]
    attachment = "named" if attached else "empty-name"
    history = "fresh" if all(value is None for value in native["before"].values()) else (
        "twice" if len(native["rounds"]) == 2 else "stale")
    role = next(name for name, target in targets(case(attachment=attachment)).items() if target == reply["target"])
    return tuple(domain[name].index(choice) for name, choice in zip(AXES, (role, history, attachment)))


def negatives():
    item = case()
    yield analysis(item, "ANALYZE main.missing"), "rejected", "requires a resolved schema, relation or index"
    yield analysis(item, "ANALYZE missing.records"), "rejected", "requires a known schema qualifier"
    yield analysis(item, "ANALYZE records", complete=False), "rejected", "cannot skip an open index namespace"
    yield analysis(item, "ANALYZE no_keys", complete=False), "rejected", "cannot skip an open index namespace"
    yield analysis(item, "ANALYZE main.col", extra=("CREATE INDEX main.col ON records(code)",)), "rejected", "Conflicting declared indexes"
    yield analysis(item, "ANALYZE main.sqlite_autoindex_opaque_1", extra=("CREATE VIRTUAL TABLE main.opaque USING fts5(a)",)), \
        "rejected", "cannot skip an opaque constraint index namespace"


def forms(measured):
    reply, native = measured
    yield "role/" + reply["target"][0]
    yield f"attachment/{native['attachment'] != ''}"
    yield f"rounds/{len(native['rounds'])}"
    yield f"fresh/{all(rows is None for rows in native['before'].values())}"
    if reply["target"][0] == "index" and reply["target"][2].startswith("sqlite_autoindex_"):
        yield "index/implicit"
    if any(rows is not None and len({tuple(tuple(cell) for cell in row) for row in rows}) < len(rows)
           for rows in native["after"].values()):
        yield "stat/duplicates"
    if reply["target"][-1] == "view":
        yield "relation/view"


def required(_):
    return {"role/all", "role/schema", "role/relation", "role/index", "attachment/True", "attachment/False",
            "rounds/1", "rounds/2", "fresh/True", "fresh/False", "index/implicit", "stat/duplicates", "relation/view"}
