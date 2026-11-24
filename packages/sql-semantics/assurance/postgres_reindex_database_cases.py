"""Interpret database-wide rebuild selectors over the fixed stock and application fixtures."""

from postgres_reindex_cases import qualified, quoted
from postgres_reindex_schema_cases import index
from postgres_reindex_table_cases import literals

DATABASE = "ir_fixture"
STATUS = "database-rebuild"
FIRST_UNPINNED = 12000
FIRST_APPLICATION = 16384
NATIVE_REFUSALS = {
    "foreign-database": "REINDEX DATABASE other",
    "foreign-system": "REINDEX SYSTEM other",
    "concurrent-system": "REINDEX SYSTEM CONCURRENTLY ir_fixture",
}

FORMS = {
    "ordinary-database": {
        "ordinary": "REINDEX DATABASE",
        "disabled": "REINDEX (CONCURRENTLY OFF) DATABASE",
        "last-disabled": "REINDEX (CONCURRENTLY TRUE, CONCURRENTLY FALSE) DATABASE",
    },
    "concurrent-database": {
        "keyword": "REINDEX DATABASE CONCURRENTLY",
        "enabled": "REINDEX (CONCURRENTLY ON) DATABASE",
        "keyword-wins": "REINDEX (CONCURRENTLY FALSE) DATABASE CONCURRENTLY",
        "value-default": "REINDEX (CONCURRENTLY) DATABASE",
    },
    "system-catalogs": {
        "system": "REINDEX SYSTEM",
        "system-disabled": "REINDEX (CONCURRENTLY FALSE) SYSTEM",
        "system-last-disabled": "REINDEX (CONCURRENTLY TRUE, CONCURRENTLY FALSE) SYSTEM",
    },
}
TABLES = {
    "persistent": ("fixture", "items", "persistent_idx", "r", "p"),
    "matview": ("fixture", "report", "materialized_idx", "m", "p"),
    "temporary": ("pg_temp", "items", "temporary_idx", "r", "t"),
    "exclusion": ("fixture", "app_exclusion", "avoid_overlap", "r", "p"),
}


def trial(release, selector, spelling, active, exclusion, *, form=None, base=17, quoted_name=True, coordinate=None):
    role = ("system" if selector == "system-catalogs" else "database")
    mode = "concurrently" if selector == "concurrent-database" else "standard"
    return {
        "dialect": "postgres", "release": release, "selection": selector, "spelling": spelling,
        "active": active, "exclusion": exclusion, "base": base, "quoted": quoted_name, "coordinate": coordinate,
        "form": form or next(iter(FORMS[selector])),
        "databaseRebuild": {"database": DATABASE, "selector": role, **({"mode": mode} if role == "database" else {})},
    }


def sql(item):
    prefix = FORMS[item["selection"]][item["form"]]
    if item["spelling"] == "implicit":
        return prefix
    return prefix + " " + (quoted(DATABASE) if item["quoted"] else DATABASE)


def analyzed(item):
    return {"dialect": "postgres", "release": item["release"], "schemaPath": ["public"],
            "declarations": [], "currentCatalog": DATABASE, "sql": sql(item)}


def owners(item):
    return {name: description for name, description in TABLES.items() if name != "exclusion" or item["exclusion"]}


def data(item, name):
    base = item["base"]
    return {
        "persistent": [[base, "stored"], [base + 1, None]],
        "matview": [[base + 30, None], [base + 33, "snapshot"]],
        "temporary": [[base + 78, "session"]],
        "exclusion": [[base + 46], [base + 49]],
    }[name]


def fixture(item):
    result = ["CREATE SCHEMA fixture"]
    for role, (scope, name, key, kind, persistence) in owners(item).items():
        target = qualified(scope, name)
        rows = literals(data(item, role))
        if role == "exclusion":
            result.extend([f"CREATE TABLE {target} (id integer, CONSTRAINT {key} EXCLUDE USING btree (id WITH =))",
                           f"INSERT INTO {target} VALUES {rows}"])
            continue
        if kind == "m":
            result.append(f"CREATE MATERIALIZED VIEW {target} AS SELECT id::int4 AS id, note::text AS note "
                          f"FROM (VALUES {rows}) AS input(id,note)")
        else:
            mode = "TEMP " if persistence == "t" else ""
            result.extend([f"CREATE {mode}TABLE {target} (id integer, note text)", f"INSERT INTO {target} VALUES {rows}"])
        result.append(f"CREATE INDEX {key} ON {target} (id)")
    return result


def expected_owners(item):
    return {
        qualified(schema, name): {
            "kind": kind, "persistence": persistence, "hasToast": role != "exclusion",
            "columns": [["id","int4"]] + ([] if role == "exclusion" else [["note","text"]]),
            "rows": data(item, role), "indexes": [index([1], key)],
            "toastIndexes": [] if role == "exclusion" else [index([1, 2])],
        }
        for role, (schema, name, key, kind, persistence) in owners(item).items()
    }


def outcome(item):
    active = item["active"]
    rejected = item.get("nativeRejection") in NATIVE_REFUSALS
    skip = item["selection"] == "concurrent-database" and item["exclusion"] and not active
    return {
        "status": STATUS, "command": None if active or rejected else "REINDEX",
        "sqlState": "25001" if active else ("0A000" if rejected else "00000"),
        "transactionAfter": "failed" if active else "idle",
        "notices": [["WARNING", "0A000", 'cannot reindex exclusion constraint index "fixture.avoid_overlap" concurrently, skipping',
                     None, None]] if skip and not rejected else [],
    }


def physical_changes(item, persistence, owner_oid, exclusion):
    system = item["selection"] == "system-catalogs"
    builtin = int(owner_oid) < FIRST_UNPINNED
    concurrent = item["selection"] == "concurrent-database"
    selected = not item["active"] and item.get("nativeRejection") not in NATIVE_REFUSALS and system == builtin and not (concurrent and exclusion)
    return [selected, selected and concurrent and persistence != "t"]
