"""Independent identity and write rules for one resolved PostgreSQL index rebuild."""

from postgres_drop_index_cases import qualified, quoted

FORMS = {
    "ordinary": "REINDEX INDEX ",
    "keyword": "REINDEX INDEX CONCURRENTLY ",
    "option-enabled": "REINDEX (CONCURRENTLY TRUE) INDEX ",
    "option-disabled": "REINDEX (CONCURRENTLY FALSE) INDEX ",
    "last-disabled": "REINDEX (CONCURRENTLY 1, CONCURRENTLY 0) INDEX ",
    "last-enabled": "REINDEX (CONCURRENTLY FALSE, CONCURRENTLY ON) INDEX ",
    "keyword-wins": "REINDEX (CONCURRENTLY FALSE) INDEX CONCURRENTLY ",
    "value-default": "REINDEX (CONCURRENTLY) INDEX ",
    "off-word": "REINDEX (CONCURRENTLY OFF) INDEX ",
}
CONCURRENT = {"keyword", "option-enabled", "last-enabled", "keyword-wins", "value-default"}
POLICIES = {"plain": (False, False), "unique": (True, False), "unique-null-equal": (True, True)}
PATH = ["fixture", "decoy", "public", "pg_catalog"]
FIELDS = ("state", "ref", "ready")
ORDERS = {"ASC NULLS LAST": 0, "DESC NULLS FIRST": 3}
DECOY = [[99, 112, True]]
PROBES = [[105, 20, True], [106, 20, False], [107, None, True]]
CONSTRAINT_DDL = {
    "primary": "PRIMARY KEY (ref)",
    "unique": "UNIQUE (ref)",
    "unique-nulls": "UNIQUE NULLS NOT DISTINCT (ref)",
    "deferrable": "UNIQUE (ref) DEFERRABLE INITIALLY IMMEDIATE",
}


def case(tag, scope, form, active, spelling, policy, filtered, *, index="cover", table="orders",
         order="DESC NULLS FIRST", covering=(0,), fill=80, coordinate=None):
    rows = [[1, 20, True], [2, 19, False], [3, None, None],
            [4, 18 if policy == "unique-null-equal" else None, False]]
    return {
        "dialect": "postgres", "release": tag,
        "reindex": {"target": [scope, index, table], "mode": "concurrently" if form in CONCURRENT else "standard"},
        "coordinate": coordinate, "form": form, "spelling": spelling, "active": active,
        "policy": policy, "filtered": filtered, "order": order, "covering": list(covering), "fill": fill, "rows": rows,
    }


def original(item):
    schema, index, _ = item["reindex"]["target"]
    return FORMS[item["form"]] + qualified(schema if item["spelling"] == "absolute" else None, index)


def fixture_index(item, schema):
    scope, index, table = item["reindex"]["target"]
    selected = schema == scope
    unique, nulls = POLICIES[item["policy"]] if selected else (False, False)
    order = item["order"] if selected else "ASC NULLS LAST"
    included = item["covering"] if selected else [0]
    suffix = " INCLUDE (" + ", ".join(FIELDS[position] for position in included) + ")" if included else ""
    modes = (" NULLS NOT DISTINCT" if nulls else "") + f" WITH (fillfactor={item['fill'] if selected else 65})"
    predicate = " WHERE ready IS NOT FALSE" if selected and item["filtered"] else ""
    return ("CREATE " + ("UNIQUE " if unique else "") + "INDEX " + quoted(index) + " ON "
            + qualified(schema, table) + f" USING btree (ref {order})" + suffix + modes + predicate)


def namespaces(item):
    scope = item["reindex"]["target"][0]
    return ["fixture", "decoy"] if scope == "fixture" else ["fixture", "decoy", "pg_temp"]


def declarations(item):
    scope, index, table = item["reindex"]["target"]
    commands = ["CREATE SCHEMA fixture", "CREATE SCHEMA decoy"]
    for name in namespaces(item):
        keyword = "CREATE TEMP TABLE" if name == "pg_temp" else "CREATE TABLE"
        heading = "state integer, ref bigint, ready boolean"
        if name == scope and "constraintPolicy" in item:
            kind = item["constraintPolicy"]
            commands.append(f"{keyword} {qualified(name, table)} ({heading}, CONSTRAINT {quoted(index)} {CONSTRAINT_DDL[kind]})")
            if kind != "deferrable":
                commands.append(f"{keyword} {qualified(name, 'children')} (value bigint REFERENCES {qualified(name, table)} (ref))")
        else:
            commands.append(f"{keyword} {qualified(name, table)} ({heading})")
            commands.append(fixture_index(item, name))
    return commands


def analyzed(item, **changes):
    return {"dialect": "postgres", "release": item["release"], "sql": original(item),
            "schemaPath": PATH, "declarations": declarations(item), **changes}


def rows_for(item, schema):
    return item["rows"] if schema == item["reindex"]["target"][0] else DECOY


def index_metadata(item, schema):
    scope, name, table = item["reindex"]["target"]
    selected = schema == scope
    unique, nulls = POLICIES[item["policy"]] if selected else (False, False)
    positions = [position + 1 for position in item["covering"]] if selected else [1]
    kind = item.get("constraintPolicy") if selected else None
    return {
        "namespace": schema, "index": name, "owner": table, "accessMethod": "btree",
        "unique": unique, "nullsNotDistinct": nulls, "valid": True, "ready": True, "live": True,
        "primary": kind == "primary", "immediate": kind != "deferrable",
        "indexSlots": [2, *positions], "keyCount": 1, "orders": [ORDERS[item["order"]] if selected else 0],
        "options": None if kind is not None else [f"fillfactor={item['fill'] if selected else 65}"],
        "predicate": "(ready IS NOT FALSE)" if selected and item["filtered"] else None,
    }


def expected(item):
    scope = item["reindex"]["target"][0]
    active = item["active"]
    concurrent = item["reindex"]["mode"] == "concurrently"
    success = not (active and concurrent)
    metadata = {name: {"index": index_metadata(item, name), "rows": rows_for(item, name)} for name in namespaces(item)}
    steps = {name: [success and name == scope and concurrent and name != "pg_temp", success and name == scope]
             for name in namespaces(item)} if success else None
    end = {name: pair if not active else [False, False] for name, pair in (steps or {}).items()}
    if active:
        end = {name: [False, False] for name in namespaces(item)}
    unique, nulls = POLICIES[item["policy"]]
    probes = None
    if success:
        if "constraintPolicy" in item:
            kind = item["constraintPolicy"]
            probes = ["23505", {"primary": "23502", "unique-nulls": "23505"}.get(kind, "00000")]
            if kind != "deferrable":
                probes.append("23503")
        else:
            probes = ["23505" if unique else "00000", "23505" if unique and not item["filtered"] else "00000",
                      "23505" if unique and nulls else "00000"]
    return {
        "status": "index-rebuild", "sqlState": "00000" if success else "25001", "command": "REINDEX" if success else None,
        "notices": [], "before": metadata, "during": metadata if success else None, "settled": metadata,
        "changedDuring": steps, "changedSettled": end, "probe": probes,
        "transactionAfter": ("active" if success else "failed") if active else "idle",
    }
