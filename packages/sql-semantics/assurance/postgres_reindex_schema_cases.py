"""Author independent catalog membership and index identity expectations for REINDEX SCHEMA."""

from postgres_reindex_cases import FORMS, qualified, quoted
from postgres_reindex_table_cases import COLUMNS, literals

LAYOUTS = ("empty", "ordinary", "split-partitions", "materialized-view")
SCOPES = ("unquoted", "quoted")
PATH = ("public",)
DECOY = "decoy"
NATIVE_FORMS = {name: sql.replace(" INDEX", " SCHEMA") for name, sql in FORMS.items()}
COLUMNS_WITH_TOAST = [["id", "int4"], ["ref", "int4"], ["note", "text"]]


def trial(tag, layout, concurrent, active, spelling, *, schema="fixture", base=40, form=None, coordinate=None):
    return {
        "dialect": "postgres", "release": tag, "layout": layout, "base": base, "active": active,
        "spelling": spelling, "form": form or ("keyword" if concurrent else "ordinary"), "coordinate": coordinate,
        "reindexSchema": {"schema": schema, "mode": "concurrently" if concurrent else "standard"},
    }


def name(row):
    return qualified(row["schema"], row["name"])


def owners(item):
    schema, layout, base = item["reindexSchema"]["schema"], item["layout"], item["base"]
    decoy = {"schema": DECOY, "name": "orders", "kind": "r", "rows": [[3, -base]], "index": "by_key", "note": False}
    if layout == "empty":
        return [decoy]
    selected = {"schema": schema, "name": "orders", "kind": "m" if layout == "materialized-view" else "r",
                "rows": [[1, base, "selected"], [2, None, None]], "index": "by_key", "note": True}
    second = {"schema": schema, "name": "stats", "kind": "r", "rows": [[8, base + 10], [19, base - 4]],
              "index": "by_stat", "note": False}
    if layout != "split-partitions":
        return [selected, second, decoy]
    local_rows, remote_rows = [[12, base, "local"]], [[52, -base, "remote"]]
    parent = {"schema": schema, "name": "parent", "kind": "p", "rows": local_rows + remote_rows,
              "index": "parent_idx", "note": True}
    local = {"schema": schema, "name": "left_leaf", "kind": "r", "rows": local_rows, "index": "left_leaf_id_idx",
             "note": True, "parent": parent, "bounds": (0, 50)}
    remote = {"schema": DECOY, "name": "right_leaf", "kind": "r", "rows": remote_rows, "index": "right_leaf_id_idx",
              "note": True, "parent": parent, "bounds": (50, 100)}
    return [selected, parent, local, remote, decoy]


def columns(owner):
    return COLUMNS_WITH_TOAST if owner["note"] else COLUMNS


def owner_sql(owner):
    table = name(owner)
    cols = columns(owner)
    if owner["kind"] == "m":
        fields = ", ".join(f"{column}::{typ} AS {column}" for column, typ in cols)
        alias = ", ".join(column for column, _ in cols)
        return f"CREATE MATERIALIZED VIEW {table} AS SELECT {fields} FROM (VALUES {literals(owner['rows'])}) AS entries({alias})"
    if "parent" in owner:
        left, right = owner["bounds"]
        return f"CREATE TABLE {table} PARTITION OF {name(owner['parent'])} FOR VALUES FROM ({left}) TO ({right})"
    fields = ", ".join(f"{column} {typ}" for column, typ in cols)
    strategy = " PARTITION BY RANGE (id)" if owner["kind"] == "p" else ""
    return f"CREATE TABLE {table} ({fields}){strategy}"


def fixture(item, populate=False):
    commands = [f"CREATE SCHEMA {quoted(schema)}" for schema in (item["reindexSchema"]["schema"], DECOY)]
    for owner in owners(item):
        table = name(owner)
        commands.append(owner_sql(owner))
        if "parent" not in owner:
            commands.append(f"CREATE INDEX {owner['index']} ON {table} (id)")
        if populate and owner["kind"] == "r":
            commands.append(f"INSERT INTO {table} VALUES {literals(owner['rows'])}")
    return commands


def analyzed(item):
    schema = item["reindexSchema"]["schema"]
    local = quoted(schema) if item["spelling"] == "quoted" else schema
    return {"dialect": "postgres", "release": item["release"], "schemaPath": list(PATH),
            "declarations": fixture(item), "sql": NATIVE_FORMS[item["form"]] + local}


def index(slots, name=None):
    return {"method": "btree", "slots": slots, "sort": [0] * len(slots), "valid": True, "ready": True, "live": True,
            "unique": name is None, "nullsNotDistinct": False, **({"name": name} if name is not None else {})}


def expected(item):
    schema = item["reindexSchema"]["schema"]
    active, concurrent = item["active"], item["reindexSchema"]["mode"] == "concurrently"
    catalog = owners(item)
    has_toast = lambda owner: owner["note"] and owner["kind"] != "p"
    stable = {
        name(owner): {
            "kind": owner["kind"], "persistence": "p", "hasToast": has_toast(owner),
            "columns": columns(owner), "rows": owner["rows"], "indexes": [index([1], owner["index"])],
            "toastIndexes": [index([1, 2])] if has_toast(owner) else [],
        } for owner in catalog
    }
    touched = {name(owner) for owner in catalog if owner["schema"] == schema and owner["kind"] != "p"} if not active else set()
    delta = {
        key: {group: [[key in touched, key in touched and concurrent] for _ in state[group]]
              for group in ("indexes", "toastIndexes")} for key, state in stable.items()
    }
    topology = sorted(
        [parent["schema"], parent["name"], owner["schema"], owner["name"]]
        for owner in catalog if (parent := owner.get("parent")) is not None
    ) + sorted(
        [parent["schema"], parent["index"], owner["schema"], owner["index"]]
        for owner in catalog if (parent := owner.get("parent")) is not None
    )
    return {
        "status": "schema-rebuild", "sqlState": "25001" if active else "00000", "command": None if active else "REINDEX",
        "notices": [], "transactionAfter": "failed" if active else "idle",
        "before": stable, "settled": stable, "changed": delta,
        "attachmentsBefore": sorted(topology), "attachmentsSettled": sorted(topology),
    }
