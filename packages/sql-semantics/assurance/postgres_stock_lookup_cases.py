"""Independent PostgreSQL selector, construction and observation rules."""

from itertools import product
from random import Random

from postgres_create_btree_cases import published, refusal
from postgres_drop_index_cases import qualified, quoted

PATHS = {
    "implicit-stock-first": ["hidden_names", "public"],
    "explicit-stock-first": ["pg_catalog", "hidden_names", "public"],
    "temporary-namesake": ["pg_temp", "pg_catalog", "hidden_names", "public"],
    "earlier-application": ["pg_temp", "hidden_names", "pg_catalog", "public"],
}
ROLES = ("column-collation", "index-collation", "text-operator-class")
SCOPES = ("standalone", "schema-member")
SPELLINGS = ("unqualified", "stock-qualified")
AXES = ("path", "selector", "composition", "spelling")
STOCK = "pg_catalog"
SHADOW = "hidden_names"
DEFAULT = "default"
ROWS = ["a", "A", "é", "e\u0301", "完", None, "A"]
COLUMN_REFUSAL = refusal("The column requires a resolved stock PostgreSQL collation.")
KEY_REFUSAL = refusal(
    "An explicitly collated PostgreSQL B-tree key requires stock pg_catalog.C and its default text operator class."
)


def identities():
    return set(product(PATHS, ROLES, SCOPES, SPELLINGS))


def coords(specification):
    return product(*(specification[axis] for axis in AXES))


def shadowed(coord):
    return coord[0] == "earlier-application" and coord[3] == "unqualified"


def case(tag, path, role, scope, spelling, *, columns=("id", "code"), code=1, rows=ROWS,
         owner="lookup_owner", table="actual_table", index="chosen_rule"):
    return {
        "tag": tag, "coordinate": [path, role, scope, spelling], "columns": list(columns), "code": code,
        "id": min(slot for slot in range(len(columns)) if slot != code), "data": list(rows),
        "owner": owner, "table": table, "index": index,
    }


def generated(tag, seed, budget):
    random = Random(seed)
    paths = tuple(PATHS)
    for number in range(budget):
        path, role, scope, spelling = (random.choice(axis) for axis in (paths, ROLES, SCOPES, SPELLINGS))
        if shadowed((path, role, scope, spelling)):
            spelling = "stock-qualified"
        width = random.randrange(2, 7)
        columns = [f'slot "{slot}" n{random.randrange(1000)}' for slot in range(width)]
        code = random.randrange(width)
        rows = [*ROWS, *(random.choice(["β", "B", "𝄞", "C'π", "z", None]) for _ in range(random.randrange(1, 7)))]
        random.shuffle(rows)
        yield case(tag, path, role, scope, spelling, columns=columns, code=code, rows=rows,
                   owner=f'scope "{number}"', table=f'native tést {random.randrange(1000)}',
                   index=f'physical "{random.randrange(1000)}"')


def selectors(item, canonical=False):
    _, role, _, spelling = item["coordinate"]
    collation = (STOCK, "C")
    operator = (STOCK, "text_ops")
    if not canonical and spelling == "unqualified":
        collation = (None, "C")
        operator = (None, "text_ops")
    if not canonical and spelling == "outside-stock":
        collation = (STOCK, "POSIX")
        operator = (SHADOW, "text_ops")
    return (collation if role == "column-collation" else None if role == "index-collation" else (STOCK, "C"),
            collation if role == "index-collation" else None,
            operator if role == "text-operator-class" else (STOCK, "text_ops") if role == "index-collation" else None)


def type_name(item, slot):
    return "pg_catalog.text" if slot == item["code"] else "INTEGER"


def use_collation(selector):
    return "" if selector is None else " COLLATE " + qualified(*selector)


def table_sql(item, canonical=False, local=False):
    declared, _, _ = selectors(item, canonical)
    columns = []
    for slot, name in enumerate(item["columns"]):
        column = quoted(name) + " " + type_name(item, slot)
        if slot == item["code"]:
            column += ' COLLATE pg_catalog."C"' if canonical and declared is not None else use_collation(declared)
        columns.append(column + (" NULL" if canonical else ""))
    full = qualified(None if local else item["owner"], item["table"])
    return f"CREATE TABLE {full} (" + ", ".join(columns) + ")" + (" USING heap" if canonical else "")


def index_sql(item, canonical=False, local=False):
    _, selected, op = selectors(item, canonical)
    key = quoted(item["columns"][item["code"]])
    if canonical:
        key += ' COLLATE pg_catalog."C" pg_catalog.text_ops ASC NULLS LAST'
    if not canonical:
        key += use_collation(selected)
        if op is not None:
            key += " " + ".".join(entry for entry in op if entry is not None)
    full = qualified(None if local else item["owner"], item["table"])
    using = " USING btree" if canonical else ""
    return f'CREATE INDEX {quoted(item["index"])} ON {full}{using} ({key})'


def sql(item, canonical=False):
    _, role, scope, _ = item["coordinate"]
    if scope == "standalone":
        return table_sql(item, canonical) if role == "column-collation" else index_sql(item, canonical)
    schema = f'CREATE SCHEMA {quoted(item["owner"])}'
    if canonical:
        return schema + " AUTHORIZATION CURRENT_USER " + table_sql(item, True) + " " + index_sql(item, True)
    return schema + " " + index_sql(item, local=True) + " " + table_sql(item, local=True)


def setup(item):
    _, role, scope, _ = item["coordinate"]
    if scope == "schema-member":
        return []
    commands = [f'CREATE SCHEMA {quoted(item["owner"])}']
    if role != "column-collation":
        commands.append(table_sql(item))
    return commands


def analysis(item):
    return {"dialect": "postgres", "release": item["tag"], "schemaPath": PATHS[item["coordinate"][0]],
            "sql": sql(item), "declarations": setup(item)}


def index_input(item):
    names = item["columns"]
    types = ["text" if slot == item["code"] else "int4" for slot in range(len(names))]
    return {
        "table": [item["owner"], item["table"], names, types, False], "name": item["index"],
        "keys": [[item["code"], "ASC NULLS LAST", "C"]], "included": [], "presence": "vacant",
        "ifNotExists": False, "unique": False, "nullsNotDistinct": False, "concurrently": False, "predicate": None,
    }


def table_input(item):
    declared, _, _ = selectors(item, canonical=True)
    columns = [[name, type_name(item, slot), True, None] for slot, name in enumerate(item["columns"])]
    if declared is not None:
        columns[item["code"]].append("C")
    return {"target": [item["owner"], item["table"]], "columns": columns, "ifNotExists": False}


def direct(item):
    _, role, scope, _ = item["coordinate"]
    table, index = table_input(item), index_input(item)
    header = {"dialect": "postgres", "release": item["tag"]}
    if scope == "standalone":
        return {**header, **({"create": table} if role == "column-collation" else {"createBtree": index})}
    target = {"name": item["owner"], "owner": {"session": "CURRENT_USER"}, "ifNotExists": False}
    return {**header, "createSchema": {**target, "tables": [{**table, "release": item["tag"]}],
                                     "indexes": [{**index, "release": item["tag"]}], "sequences": [], "views": [], "grants": []}}


def graph(item):
    representation = direct(item)
    if "create" in representation:
        return {"create": representation["create"]}
    if "createBtree" in representation:
        return {"createBtree": published(representation["createBtree"])}
    root = representation["createSchema"]
    return {"createSchema": {key: root[key] for key in ("name", "owner", "ifNotExists")},
            "schemaTables": root["tables"], "schemaIndexes": [published(index) for index in root["indexes"]],
            "schemaSequences": [], "schemaViews": [], "schemaGrants": []}


def inserted(item):
    return [[text if slot == item["code"] else number + 100 * slot
             for slot in range(len(item["columns"]))] for number, text in enumerate(item["data"], 1)]


def expected(item, source=True):
    coord = item["coordinate"]
    _, role, _, spelling = coord
    collation = [SHADOW, "C"] if source and shadowed(coord) else [STOCK, "C"]
    operator = [SHADOW, "text_ops"] if collation[0] == SHADOW else [STOCK, "text_ops"]
    if source and spelling == "outside-stock":
        collation, operator = [STOCK, "POSIX"], [SHADOW, "text_ops"]
    table_collation = collation if role == "column-collation" else [STOCK, DEFAULT if role == "index-collation" else "C"]
    fields = [[name, "text" if i == item["code"] else "int4", table_collation if i == item["code"] else None]
              for i, name in enumerate(item["columns"])]
    index = [item["index"], [item["code"] + 1], [STOCK, "C"] if role == "text-operator-class" else collation,
             operator if role == "text-operator-class" else [STOCK, "text_ops"]]
    code, identifier = item["code"], item["id"]
    rows = sorted(inserted(item), key=lambda row: (row[code] is None, str(row[code]).encode("utf-8"), row[identifier]))
    order = ["pg_temp", SHADOW, STOCK, "public"] if coord[0] == "earlier-application" else ["pg_temp", STOCK, SHADOW, "public"]
    return {"owner": [item["owner"], item["table"]], "fields": fields, "index": index, "rows": rows,
            "shadowObjects": [True, True, True, True], "namespaceOrder": order}


def guard(item):
    return COLUMN_REFUSAL if item["coordinate"][1] == "column-collation" else KEY_REFUSAL
