"""Independent table and schema models for explicitly declared PostgreSQL C columns."""

from itertools import product
from random import Random

from postgres_create_btree_cases import published
from postgres_reindex_cases import qualified, quoted

SCOPES = ("standalone", "schema-index")
FIELDS = {
    "one-text": ["id", "code"],
    "text-between-integers": ["id", "code", "number"],
    "adjacent-text": ["id", "code", "payload"],
}
DEFAULTS = ("omitted", "null", "quoted-text")
NULLS = ("nullable", "required")
DATA = [[1, "A"], [2, "a"], [3, "é"], [4, "e\u0301"], [5, "完"]]
ROLE_TYPE = {"id": "INTEGER", "code": "pg_catalog.text", "number": "INTEGER", "payload": "pg_catalog.text"}
ROLE_VALUES = {"number": 17, "payload": "plain"}
NS, DECOY = "collated_heap", "collated_decoy"


def coords(finite):
    return product(*(finite[axis] for axis in ("composition", "headings", "default", "nullability")))


def identities():
    return set(product(SCOPES, FIELDS, DEFAULTS, NULLS))


def case(tag, scope, layout, default, nullable, *, fields=None, names=None, number=0, table="orders", index="code_idx"):
    roles = FIELDS[layout] if fields is None else fields
    columns = (roles if names is None else names)
    chosen = "a'π" if number == 0 else f"b'{number}ä"
    rows = []
    for id, code in DATA:
        fixture = {"id": id, "code": code, **ROLE_VALUES}
        rows.append([fixture[field] for field in roles])
    return {
        "tag": tag, "coordinate": [scope, layout, default, nullable], "scope": scope, "namespace": NS,
        "table": table, "indexName": index, "columns": columns, "roles": roles, "rows": rows,
        "idSlot": roles.index("id"), "codeSlot": roles.index("code"), "nullable": nullable == "nullable",
        "defaultMode": default, "literal": chosen, "collation": "C", "codeKind": "pg_catalog.text",
    }


def setup(item):
    commands = [f"CREATE SCHEMA {DECOY}"]
    target = qualified(DECOY, item["table"])
    commands.append(f"CREATE TABLE {target} (sentinel integer)")
    if item["scope"] == "standalone":
        commands.append(f"CREATE SCHEMA {quoted(item['namespace'])}")
    return commands


def type_name(item, role):
    return item["codeKind"] if role == "code" else ROLE_TYPE[role]


def column_facts(item):
    columns = []
    for name, role in zip(item["columns"], item["roles"]):
        kind = type_name(item, role)
        nullable, default = role != "id", None
        if role == "code":
            default = {"omitted": None, "null": ["null"], "quoted-text": ["text", item["literal"]]}[item["defaultMode"]]
            nullable = item["nullable"]
            columns.append([name, kind, nullable, default, "C"])
        else:
            columns.append([name, kind, nullable, default])
    return columns


def data_literal(value):
    return "'" + value.replace("'", "''") + "'"


def column_sql(item, canonical):
    parts = []
    for name, role in zip(item["columns"], item["roles"]):
        kind = type_name(item, role)
        name_sql = quoted(name)
        if kind == "pg_catalog.text" and not canonical:
            kind = "TEXT"
        if role == "code" and item["collation"] is not None:
            kind += ' COLLATE pg_catalog.' + quoted(item["collation"])
        nullable = (item["nullable"] if role == "code" else role != "id")
        default = ""
        if role == "code":
            literal = data_literal(item["literal"])
            expression = f"CAST({literal} AS pg_catalog.text)" if canonical else literal
            default = {"omitted": "", "null": " DEFAULT NULL", "quoted-text": " DEFAULT " + expression}[item["defaultMode"]]
        parts.append(f"{name_sql} {kind}" + (" NULL" if nullable else " NOT NULL") + default)
    return ", ".join(parts)


def sql(item, canonical=False):
    schema, table = item["namespace"], item["table"]
    full = qualified(schema, table)
    columns = column_sql(item, canonical)
    if item["scope"] == "standalone":
        return f"CREATE TABLE {full} ({columns})" + (" USING heap" if canonical else "")
    code = quoted(item["columns"][item["codeSlot"]])
    index = "CREATE INDEX " + quoted(item["indexName"]) + " ON "
    if canonical:
        return f"CREATE SCHEMA {quoted(schema)} AUTHORIZATION CURRENT_USER CREATE TABLE {full} ({columns}) USING heap " + (
            index + full + f' USING btree ({code} COLLATE pg_catalog."C" pg_catalog.text_ops ASC NULLS LAST)')
    name = quoted(table)
    return f"CREATE SCHEMA {quoted(schema)} " + index + f"{name} ({code}) CREATE TABLE {name} ({columns})"


def analyzed(item):
    return {"dialect": "postgres", "release": item["tag"], "sql": sql(item), "declarations": setup(item), "schemaPath": ["pg_temp", "pg_catalog", DECOY, "public"]}


def table_input(item):
    return {"target": [item["namespace"], item["table"]], "columns": column_facts(item), "ifNotExists": False}


def direct(item):
    tag, table = item["tag"], table_input(item)
    header = {"dialect": "postgres", "release": tag}
    if item["scope"] == "standalone":
        return {**header, "create": table}
    schema, rel = table["target"]
    names = item["columns"]
    kinds = ["int4" if type_name(item, role) == "INTEGER" else "text" for role in item["roles"]]
    index = {"release": tag, "table": [schema, rel, names, kinds, False],
             "name": item["indexName"], "keys": [[item["codeSlot"], "ASC NULLS LAST", "C"]], "included": [],
             "predicate": None, "presence": "vacant", "ifNotExists": False, "unique": False,
             "nullsNotDistinct": False, "concurrently": False}
    root = {"name": schema, "owner": {"session": "CURRENT_USER"}, "ifNotExists": False}
    return {**header, "createSchema": {**root, "tables": [{**table, "release": tag}], "indexes": [index],
                                     "sequences": [], "views": [], "grants": []}}


def graph(item):
    specification = direct(item)
    if "create" in specification:
        return {"create": specification["create"]}
    scope = specification["createSchema"]
    return {"createSchema": {key: scope[key] for key in ("name", "owner", "ifNotExists")},
            "schemaTables": scope["tables"], "schemaIndexes": [published(index) for index in scope["indexes"]],
            "schemaViews": [], "schemaSequences": [], "schemaGrants": []}


def expected(item, *, source=False):
    id_slot, code = item["idSlot"], item["codeSlot"]
    collation = item["collation"] if source else "C"
    types = []
    for name, role in zip(item["columns"], item["roles"]):
        kind = type_name(item, role)
        native = "int4" if kind == "INTEGER" else "text"
        mandatory = role == "id" or role == "code" and not item["nullable"]
        comparison = None if native == "int4" else ["pg_catalog", (collation or "default") if role == "code" else "default"]
        types.append([name, native, mandatory, comparison])
    omitting = "00000" if item["nullable"] or item["defaultMode"] == "quoted-text" else "23502"
    filled = [None] * len(types)
    filled[id_slot] = 9
    filled[code] = item["literal"] if item["defaultMode"] == "quoted-text" else None
    composed = item["scope"] == "schema-index"
    return {
        "status": "postgres-collated-table-observation",
        "command": "CREATE SCHEMA" if composed else "CREATE TABLE", "sqlState": "00000",
        "owner": [item["namespace"], item["table"]], "metadata": types,
        "defaultsPresent": [role == "code" and item["defaultMode"] == "quoted-text" for role in item["roles"]],
        "index": [item["indexName"], [code + 1], [["pg_catalog", "C"]], ["text_ops"]] if composed else None,
        "ordered": sorted(item["rows"], key=lambda row: (row[code].encode("utf-8"), row[id_slot])),
        "defaultStatus": omitting, "defaultRow": filled if omitting == "00000" else None,
        "decoy": [[914]],
    }


def generated(tag, seed, count):
    rng = Random(seed)
    for i in range(count):
        scope = SCOPES[i % len(SCOPES)]
        layout = tuple(FIELDS)[i % len(FIELDS)]
        roles = list(FIELDS[layout])
        rng.shuffle(roles)
        nonce = rng.randrange(99, 9999)
        names = [f'{role}."{nonce}' for role in roles]
        yield case(tag, scope, layout, rng.choice(DEFAULTS), rng.choice(NULLS), fields=roles, names=names,
                   table=f'orders."{nonce}', index=f"key.鍵{nonce}", number=nonce)
