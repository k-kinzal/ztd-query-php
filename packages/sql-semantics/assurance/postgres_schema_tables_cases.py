"""Independently describe the ordered PostgreSQL CREATE SCHEMA table phase."""

from postgres_create_schema_cases import EFFECTIVE, OWNER, QUOTED_OWNER, spelling
from postgres_reindex_cases import qualified, quoted
from server_create_table_cases import default_value
from server_delete_cases import literal

STATUS = "schema-table-composition"
DECOY = "ir_ns_members_decoy"
SHAPES = ("one", "independent", "reversed", "guarded-repeat")
OIDS = {"SMALLINT": 21, "INTEGER": 23, "BIGINT": 20, "pg_catalog.bool": 16, "pg_catalog.text": 25}


def fields(base, alternate=False, quoted_name=False):
    if alternate:
        return [["different", "BIGINT", False, ["wide-integer", str(4294967296 + base)]],
                ["done", "pg_catalog.bool", True, ["boolean", base % 2 != 0]]]
    return [["ID" if quoted_name else "n", "INTEGER", False, literal(base)],
            ["note", "pg_catalog.text", True, ["text", f"棚 {base}'"] if quoted_name else None]]


def table(tag, target, body, guarded):
    return {"release": tag, "target": list(target), "columns": body, "ifNotExists": guarded}


def trial(tag, layout, ownership, qualification, active, *, width=2, base=13, quoted_name=False, derived=False,
          occupied=False, coordinate=None, order=None):
    named = (QUOTED_OWNER if quoted_name else OWNER) if ownership == "named" else None
    explicit = ('IR schema."棚"' if quoted_name else "ir_ns_members_subject") if not derived else None
    namespace = explicit or named
    first = ('IR items."棚"' if quoted_name else "items")
    other = [f"batch_{index}" for index in range(1, width)]
    names = {"one": [first], "independent": [first, *other], "reversed": [*reversed(other), first],
             "guarded-repeat": [first, *other[:-1], first]}[layout]
    if order is not None:
        names = [names[index] for index in order]
    seen = set()
    members = []
    for index, name in enumerate(names):
        members.append(table(tag, (namespace, name), fields(base + index, bool(index % 2), quoted_name), name in seen))
        seen.add(name)
    return {"release": tag, "layout": layout, "owner": ownership, "named": named, "explicit": explicit,
            "namespace": namespace, "qualified": qualification == "written", "active": active, "base": base,
            "members": members, "occupied": occupied, "coordinate": coordinate, "quoted": quoted_name}


def default_sql(value, canonical):
    kind, *parts = value
    if kind in ("integer", "wide-integer"):
        return parts[0] if kind == "integer" else "CAST(" + parts[0] + " AS pg_catalog.int8)"
    if kind == "text":
        text = "'" + parts[0].replace("'", "''") + "'"
        return "CAST(" + text + " AS pg_catalog.text)" if canonical else text
    if kind == "boolean":
        return "TRUE" if parts[0] else "FALSE"
    raise ValueError(f"Unexpected default role {kind}")


def table_sql(member, explicit, canonical, schema=None):
    namespace, local = member["target"]
    target = qualified(schema or namespace, local) if explicit or schema is not None else quoted(local)
    columns = []
    for name, kind, nullable, default in member["columns"]:
        heading = quoted(name) + " " + kind
        heading += (" NULL" if canonical else "") if nullable else " NOT NULL"
        columns.append(heading + (" DEFAULT " + default_sql(default, canonical) if default is not None else ""))
    return "CREATE TABLE " + ("IF NOT EXISTS " if member["ifNotExists"] else "") + target + (
        " (" + ", ".join(columns) + ")" + (" USING heap" if canonical else ""))


def sql(item, *, canonical=False, members=None, schema=None):
    role = (quoted(item["named"]) if item["named"] is not None else "CURRENT_USER") if canonical else (
        quoted(item["named"]) if item["named"] is not None else None)
    subject = item["namespace"] if canonical else item["explicit"]
    prefix = spelling(subject, role, False)
    parts = item["members"] if members is None else members
    return " ".join([prefix, *(table_sql(member, canonical or item["qualified"], canonical, schema=schema) for member in parts)])


def analyzed(item):
    return {"dialect": "postgres", "release": item["release"], "sql": item.get("source") or sql(item),
            "declarations": [f"CREATE ROLE {quoted(OWNER)}", f"CREATE ROLE {quoted(QUOTED_OWNER)}"]}


def direct(item):
    owner = {"named": item["named"]} if item["named"] else {"session": "CURRENT_USER"}
    return {"dialect": "postgres", "release": item["release"], "createSchema": {
        "name": item["explicit"], "owner": owner, "ifNotExists": False, "tables": item["members"],
    }}


def physical_table(child, owner, rows=0):
    return [child["target"][1], owner, "r", "heap",
            [[position, name, OIDS[kind], nullable, default is not None]
             for position, (name, kind, nullable, default) in enumerate(child["columns"], 1)], rows]


def expected(item):
    owner = item["named"] or EFFECTIVE
    existing = item["occupied"]
    occupied_member = table(item["release"], (item["namespace"], "items"), [["prior", "INTEGER", False, None]], False)
    before = {"owner": OWNER if existing else None, "tables": [physical_table(occupied_member, OWNER, rows=1)] if existing else [],
              "decoy": [item["base"] + 100]}
    seen = {}
    notices = []
    defaults = {}
    for member in item["members"]:
        name = member["target"][1]
        if name in seen:
            frame = ["NOTICE", "42P07", f'relation "{name}" already exists, skipping', None, None]
            notices.append(frame)
            continue
        seen[name] = physical_table(member, owner)
        defaults[name] = [[OIDS[field[1]], default_value(field[1], field[3])] for field in member["columns"]]
    after = {"owner": owner, "tables": [seen[name] for name in sorted(seen)], "decoy": before["decoy"]}
    return {"status": STATUS, "before": before, "during": None if existing else after,
            "settled": before if item["active"] or existing else after,
            "transactionAfter": ("failed" if existing else "active") if item["active"] else "idle",
            "sqlState": "42P06" if existing else "00000", "command": None if existing else "CREATE SCHEMA",
            "notices": [] if existing else notices, "defaults": None if existing else defaults}
