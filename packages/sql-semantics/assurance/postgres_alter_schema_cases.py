"""Predict PostgreSQL namespace identity and owning-role changes."""

from postgres_create_schema_cases import EFFECTIVE, OWNER, QUOTED_OWNER, SESSION
from postgres_reindex_cases import qualified, quoted

STATUS = "namespace-change"
DECOY = "ir_ns_alter_decoy"
DEPENDENTS = "ir_ns_alter_dependent"
READER = "ir_ns_alter_reader"
RENAMES = ("plain", "quoted")
OWNERS = ("same", "named", "current-role", "current-user", "session")
ROWS = ("nullable-bag", "distinct")


def rename(tag, spelling, occupied, active, data, *, base=17, coordinate=None, relation_bound=False):
    quoted_name = spelling == "quoted"
    old, new = ('IR old."棚"', 'IR new."棚"') if quoted_name else ("ir_ns_alter_subject", "ir_ns_alter_after")
    return {"mode": "rename", "release": tag, "old": old, "new": new, "active": active, "acl": True,
            "occupied": occupied, "data": data, "base": base, "coordinate": coordinate, "quoted": quoted_name,
            "relationBound": relation_bound}


def ownership(tag, mode, acl, active, *, base=17, quoted_name=False, data="nullable-bag", coordinate=None):
    item = rename(tag, "quoted" if quoted_name else "plain", False, active, data, base=base, coordinate=coordinate)
    return {**item, "mode": "owner", "ownerMode": mode, "acl": acl}


def role(item):
    return {"same": OWNER, "named": QUOTED_OWNER, "current-role": EFFECTIVE, "current-user": EFFECTIVE,
            "session": SESSION}[item["ownerMode"]]


def authorization(item, canonical):
    mode = item["ownerMode"]
    if mode in ("same", "named"):
        return quoted(role(item))
    return {"current-role": "CURRENT_USER" if canonical else "CURRENT_ROLE", "current-user": "CURRENT_USER",
            "session": "SESSION_USER"}[mode]


def sql(item, *, canonical=False, source=None, destination=None, principal=None):
    source = item["old"] if source is None else source
    secure = quoted if canonical or item["quoted"] or source == DECOY else lambda name: name
    if item["mode"] == "rename":
        destination = item["new"] if destination is None else destination
        return f"ALTER SCHEMA {secure(source)} RENAME TO {secure(destination)}"
    token = authorization(item, canonical) if principal is None else principal
    return f"ALTER SCHEMA {secure(source)} OWNER TO {token}"


def analyzed(item):
    declarations = [f"CREATE ROLE {quoted(OWNER)}", f"CREATE USER {quoted(QUOTED_OWNER)}"]
    if item["relationBound"]:
        declarations.append(f"CREATE TABLE {qualified(item['old'], 'items')} (id integer)")
    else:
        declarations.append(f"CREATE SCHEMA {quoted(item['old'])}")
    if item["occupied"]:
        declarations.append(f"CREATE SCHEMA {quoted(item['new'])}")
    return {"dialect": "postgres", "release": item["release"], "sql": sql(item),
            "declarations": declarations, "schemaPath": [DECOY, "public"]}


def direct(item):
    values = {"schema": item["old"], "destination": item["new"] if item["mode"] == "rename" else None, "owner": None}
    if item["mode"] == "owner":
        values["owner"] = ({"named": role(item)} if item["ownerMode"] in ("same", "named")
                           else {"session": authorization(item, canonical=True)})
    return {"dialect": "postgres", "release": item["release"], "alterSchema": values}


def records(item):
    base = item["base"]
    return [base, base + 1, base + 3] if item["data"] == "distinct" else [base, base, base + 2, None]


def acl_for(owner):
    return sorted([[owner, owner, "CREATE", False], [owner, owner, "USAGE", False],
                   [owner, READER, "USAGE", False]])


def state(item, subject, owner):
    names = [[subject, owner, acl_for(owner) if item["acl"] else None], [DECOY, OWNER, None]]
    relations = [[subject, "items", OWNER, records(item)], [DECOY, "items", OWNER, [item["base"] + 100]]]
    if item["occupied"]:
        names.append([item["new"], OWNER, None])
        relations.append([item["new"], "items", OWNER, [item["base"] + 200]])
    return {"namespaces": sorted(names), "relations": sorted(relations), "dependent": records(item)}


def expected(item):
    previous = state(item, item["old"], OWNER)
    conflict = item["mode"] == "rename" and (item["occupied"] or item["old"] == item["new"])
    if conflict:
        result = None
    elif item["mode"] == "rename":
        result = state(item, item["new"], OWNER)
    else:
        result = state(item, item["old"], role(item))
    return {"status": STATUS, "before": previous, "during": result,
            "settled": previous if item["active"] or conflict else result,
            "transactionAfter": ("failed" if conflict else "active") if item["active"] else "idle",
            "sqlState": "42P06" if conflict else "00000", "command": None if conflict else "ALTER SCHEMA",
            "notices": []}
