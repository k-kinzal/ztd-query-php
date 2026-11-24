"""Independent finite targets, typed data and expected native SQLite index rebuilds."""

from itertools import product
from random import Random
from typing import NamedTuple

from run_sqlite_create_table import quoted
from sqlite_add_column_cases import error, statement
from sqlite_engine import typed

AXES = ("targets", "storage", "attachment")
SCHEMAS = ("main", "temp", "extension")
CASEFOLD = str.maketrans("ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz")


class Index(NamedTuple):
    schema: str
    owner: str
    name: str
    column: str
    collation: str
    unique: bool = False

    def coordinate(self):
        return self.schema, self.name


def index_name(table):
    return f"sqlite_autoindex_{table}_1"


def namespace(item, role):
    return item["attached"] if role == "extension" else role


def names(item):
    table = item["table"]
    for role in SCHEMAS:
        schema = namespace(item, role)
        yield Index(schema, table, f"rev_{role}", "code", "NOCASE", item["storage"] == "unique-conflict" and role == "main")
        yield Index(schema, table, f"trim_{role}", "note", "RTRIM")
        yield Index(schema, table, index_name(table), "input", "BINARY", True)
    yield Index("temp", table, "shadow", "code", "NOCASE")
    yield Index("main", "shadow", "shadow_rebuild", "code", "NOCASE")
    yield Index("main", "NOCASE", "collation_rebuild", "code", "NOCASE")
    yield Index(item["attached"], "hidden", index_name("hidden"), "id", "BINARY", True)


def roles(item):
    table, attached = item["table"], item["attached"]
    return {
        "all": ["all"],
        "nocase": ["collation", "NOCASE"],
        "rtrim": ["collation", "RTRIM"],
        "binary": ["collation", "BINARY"],
        "main-table": ["relation", "main", table, "table"],
        "temp-table": ["relation", "temp", table, "table"],
        "attached-table": ["relation", attached, table, "table"],
        "unqualified-table-priority": ["relation", "main", "shadow", "table"],
        "temp-shadow-index": ["index", "temp", "shadow", table],
        "qualify-collation-name-table": ["relation", "main", "NOCASE", "table"],
        "without-rowid-index": ["index", attached, index_name("hidden"), "hidden"],
        "ordinary-implicit-index": ["index", "main", index_name(table), table],
        "view-without-index": ["relation", "main", "watch", "view"],
        "table-without-index": ["relation", "main", "no_keys", "table"],
    }


def case(target="all", storage="both-stale", attachment="named", *, table="records", marker=""):
    item = {"operation": "reindex", "storage": storage, "attached": "extension" if attachment == "named" else "",
            "table": table, "marker": marker}
    return {**item, "target": roles(item)[target]}


def declaration(item):
    name = quoted(item["table"])
    for role in SCHEMAS:
        schema = quoted(namespace(item, role))
        yield f"CREATE TABLE {schema}.{name} (id INTEGER PRIMARY KEY, input TEXT UNIQUE, code TEXT, note TEXT)"
        unique = "UNIQUE " if item["storage"] == "unique-conflict" and role == "main" else ""
        yield f"CREATE {unique}INDEX {schema}.rev_{role} ON {name}(code COLLATE NOCASE)"
        yield f"CREATE INDEX {schema}.trim_{role} ON {name}(note COLLATE RTRIM)"
    yield f"CREATE INDEX temp.shadow ON {name}(code COLLATE NOCASE)"
    yield "CREATE TABLE main.shadow (code TEXT)"
    yield "CREATE INDEX main.shadow_rebuild ON shadow(code COLLATE NOCASE)"
    yield "CREATE TABLE main.NOCASE (code TEXT)"
    yield "CREATE INDEX main.collation_rebuild ON NOCASE(code COLLATE NOCASE)"
    yield f'CREATE TABLE {quoted(item["attached"])}.hidden (id TEXT PRIMARY KEY) WITHOUT ROWID'
    yield "CREATE TABLE main.no_keys (id INTEGER PRIMARY KEY)"
    yield f"CREATE VIEW main.watch AS SELECT code FROM main.{name}"


def population(item):
    marker = item["marker"]
    for role in SCHEMAS:
        suffix = "ALPHA" if item["storage"] == "unique-conflict" and role == "main" else "Coffee"
        yield namespace(item, role), item["table"], ["id", "input", "code", "note"], [
            (1, "1", "alpha" + marker, "Z"),
            (2, "2", "b" + marker, "w  "),
            (3, "3", suffix + marker, "X"),
        ]
    yield "main", "shadow", ["code"], [("d",), ("A",), ("c",)]
    yield "main", "NOCASE", ["code"], [("d",), ("A",), ("c",)]
    yield item["attached"], "hidden", ["id"], [("A",), ("b",)]
    yield "main", "no_keys", ["id"], [(10,), (20,)]


def rebuild(item, indexes):
    target = item["target"]
    if target[0] == "all":
        return indexes
    if target[0] == "collation":
        return [index for index in indexes if index.collation == target[1]]
    if target[0] == "relation":
        return [index for index in indexes if (index.schema, index.owner) == tuple(target[1:3])]
    return [index for index in indexes if index.coordinate() == tuple(target[1:3])]


def index_values(item, indexes):
    data = {(schema, table): dict(zip(fields, zip(*rows))) for schema, table, fields, rows in population(item)}
    return {index.coordinate(): list(data[index.schema, index.owner][index.column]) for index in indexes}


def occurrence(item, index, values, *, fresh):
    name = index.collation
    reverse = not fresh and (name == "NOCASE" and item["storage"] != "consistent"
                             or name == "RTRIM" and item["storage"] in ("both-stale", "unique-conflict"))
    sensitive = not fresh and name == "NOCASE" and item["storage"] == "unique-conflict"

    def key(value):
        if name == "NOCASE" and not sensitive:
            return value.translate(CASEFOLD)
        return value.rstrip(" ") if name == "RTRIM" else value

    return {"schema": index.schema, "index": index.name, "owner": index.owner, "names": [index.column],
            "rows": [[typed(value)] for value in sorted(values, key=key, reverse=reverse)]}


def expected(item):
    indexes = list(names(item))
    selected = rebuild(item, indexes)
    values = index_values(item, indexes)
    failed = any(index.unique and index.name == "rev_main" and item["storage"] == "unique-conflict" for index in selected)
    problem = error(f"UNIQUE constraint failed: {item['table']}.code") if failed else None

    def pictures(refreshed):
        return [occurrence(item, index, values[index.coordinate()], fresh=index in refreshed) for index in indexes]

    old = pictures(())
    tables = [{"schema": schema, "name": table, "names": heading, "rows": [[typed(value) for value in row] for row in rows]}
              for schema, table, heading, rows in population(item)]
    return {
        "statement": {**statement(error=problem), "readOnly": not selected},
        "events": sorted([index.schema, index.name] for index in selected),
        "attached": item["attached"], "before": old, "after": pictures(() if failed else selected),
        "initialTables": tables, "finalTables": tables, "rolledBack": old, "restoredTables": tables, "active": True,
        "mainUnique": item["storage"] == "unique-conflict",
    }


def analysis(item, sql, *, complete=True, extra=()):
    return {"sql": sql, "declarations": [*declaration(item), *extra],
            "catalogComplete": complete, "searchPath": ["main", item["attached"]]}


def authored():
    yield case("nocase"), 'REINDEX "nocase"'
    yield case("temp-table"), "REINDEX records"
    yield case("unqualified-table-priority"), "REINDEX shadow"
    yield case("temp-shadow-index"), "REINDEX TEMP.shadow"
    yield case("qualify-collation-name-table"), "REINDEX MAIN.nocase"
    yield case("attached-table", attachment="empty-name"), 'REINDEX "".records'
    yield case("without-rowid-index"), "REINDEX extension.sqlite_autoindex_hidden_1"
    yield case("ordinary-implicit-index"), "REINDEX main.sqlite_autoindex_records_1"
    yield case("view-without-index"), "REINDEX watch"
    yield case("table-without-index"), "REINDEX main.no_keys"
    yield case("all", "unique-conflict"), "REINDEX"


def requests(specification):
    finite = specification["finite"]
    for index, axes in enumerate(product(*(finite[axis] for axis in AXES))):
        value = case(*axes)
        yield f"finite/{index}", value, value
    for seed in specification["exploration"]["seeds"]:
        random = Random(seed)
        for index in range(specification["exploration"]["casesPerSeed"]):
            scope = finite["targets"][index % len(finite["targets"])]
            storage = "unique-conflict" if index == 0 else random.choice(finite["storage"])
            attach = finite["attachment"][(index // 3) % 2]
            table = random.choice(('record "列" ', 'inventory ', 'records_')) + str(index)
            item = case(scope, storage, attach, table=table, marker=random.choice(("", "é", "日")))
            yield f"seed/{seed}/{index}", item, item
    for index, (item, sql) in enumerate(authored()):
        yield f"analyze/{index}", analysis(item, sql), item


def finite_id(measured, domain):
    reply, observation = measured
    target = next(role for role, selection in roles(case(attachment="empty-name" if observation["attached"] == "" else "named")).items()
                  if selection == reply["target"])
    state = "unique-conflict" if observation["mainUnique"] else "consistent"
    if not observation["mainUnique"]:
        main = {index["index"]: index["rows"] for index in observation["before"] if index["schema"] == "main"}
        state = "both-stale" if main["trim_main"][0] == [["text", "w  "]] else (
            "nocase-stale" if main["rev_main"][0] == [["text", "Coffee"]] else "consistent")
    attachment = "empty-name" if observation["attached"] == "" else "named"
    return tuple(domain[axis].index(value) for axis, value in zip(AXES, (target, state, attachment)))


def finite_ids(domain):
    return set(product(*(range(len(domain[axis])) for axis in AXES)))


def negatives():
    base = case()
    yield analysis(base, "REINDEX lost"), "rejected", "requires a resolved collation, relation or index"
    yield analysis(base, "REINDEX unknown.records"), "rejected", "requires a known schema qualifier"
    yield analysis(base, "REINDEX no_keys", complete=False), "rejected", "cannot skip an open relation namespace"
    yield analysis(base, "REINDEX shadow_rebuild", complete=False), "rejected", "cannot skip an open relation namespace"
    yield analysis(base, "REINDEX main.shadow", extra=("CREATE TABLE main.shadow (code TEXT)",)), "rejected", "Conflicting declared relations"
    yield analysis(base, "REINDEX temp.shadow", extra=("CREATE INDEX temp.shadow ON records(note)",)), "rejected", "Conflicting declared indexes"


def forms(measured):
    reply, observation = measured
    yield "role/" + reply["target"][0]
    yield f"attachment/{observation['attached'] == ''}"
    yield f"readOnly/{observation['statement']['readOnly']}"
    yield f"failure/{observation['statement']['evaluationError'] is not None}"
    if reply["target"][0] == "index" and reply["target"][2].startswith("sqlite_autoindex_"):
        yield "index/implicit"
    if reply["target"][0] == "relation" and '"' in reply["target"][2]:
        yield "relation/escaped"


def required(_):
    return {"role/all", "role/collation", "role/relation", "role/index", "attachment/True", "attachment/False",
            "readOnly/True", "readOnly/False", "failure/True", "failure/False", "index/implicit", "relation/escaped"}
