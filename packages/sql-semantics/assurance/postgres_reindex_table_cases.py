"""Author standalone relation rebuild fixtures independently of PHP."""

from postgres_reindex_cases import FORMS, qualified, quoted

OWNERS = {
    "persistent-table": ("fixture", "base-table"),
    "temporary-table": ("pg_temp", "base-table"),
    "materialized-view": ("fixture", "materialized-view"),
}
LAYOUTS = {
    "empty-index-set": (False, ()),
    "one-index": (False, ("by_key",)),
    "two-indexes": (False, ("by_key", "by_value")),
    "toast-only": (True, ()),
    "toast-and-index": (True, ("by_key",)),
}
PATH = ("fixture", "decoy", "pg_catalog")
DECOY = "decoy"
NATIVE_FORMS = {key: value.replace(" INDEX", " TABLE") for key, value in FORMS.items()}
COLUMNS = [["id", "int4"], ["ref", "int4"]]


def trial(release, owner, concurrent, active, layout, spelling, *, name="orders",
          base=20, form=None, coordinate=None):
    schema, kind = OWNERS[owner]
    form = form or ("keyword" if concurrent else "ordinary")
    return {
        "dialect": "postgres", "release": release, "layout": layout, "active": active, "form": form,
        "spelling": spelling, "owner": owner, "base": base, "coordinate": coordinate,
        "reindexTable": {"target": [schema, name, kind], "mode": "concurrently" if concurrent else "standard"},
    }


def data(item, schema):
    offset = item["base"] if schema != DECOY else -item["base"]
    selected = schema != DECOY
    rows = [[1, offset], [2, offset - 1], [3, None]] if selected else [[1, offset], [4, offset - 1]]
    toasted, _ = LAYOUTS[item["layout"] if selected else "one-index"]
    return [row + [value] for row, value in zip(rows, ["toasted 名", None, "distinct"])] if toasted else rows


def literals(rows):
    def val(value):
        if value is None:
            return "NULL"
        if type(value) is int:
            return str(value)
        return "'" + value.replace("'", "''") + "'"
    return ", ".join("(" + ", ".join(map(val, row)) + ")" for row in rows)


def column_shape(item, schema):
    return COLUMNS + ([["note", "text"]] if schema != DECOY and LAYOUTS[item["layout"]][0] else [])


def schemas(item):
    return item["reindexTable"]["target"][0], DECOY


def fixtures(item, schema):
    _, name, kind = item["reindexTable"]["target"]
    target = qualified(schema, name)
    cols = column_shape(item, schema)
    rows = literals(data(item, schema))
    if schema != DECOY and kind == "materialized-view":
        fields = ", ".join(f"{column}::{typ} AS {column}" for column, typ in cols)
        alias = ", ".join(column for column, _ in cols)
        owner = f"CREATE MATERIALIZED VIEW {target} AS SELECT {fields} FROM (VALUES {rows}) AS entries({alias})"
        populate = []
    else:
        cols_sql = ", ".join(f"{column} {typ}" for column, typ in cols)
        qualifier = "TEMP " if schema == "pg_temp" else ""
        owner = f"CREATE {qualifier}TABLE {target} ({cols_sql})"
        populate = [f"INSERT INTO {target} VALUES {rows}"]
    keys = LAYOUTS[item["layout"] if schema != DECOY else "one-index"][1]
    indexes = [f"CREATE UNIQUE INDEX by_key ON {target} (id)" if key == "by_key"
               else f"CREATE INDEX by_value ON {target} (ref DESC NULLS LAST)" for key in keys]
    return owner, indexes, populate


def declarations(item):
    commands = ["CREATE SCHEMA fixture", "CREATE SCHEMA decoy"]
    for schema in schemas(item):
        relation, indexes, _ = fixtures(item, schema)
        commands += [relation, *indexes]
    return commands


def analyzed(item):
    schema, name, _ = item["reindexTable"]["target"]
    target = qualified(schema if item["spelling"] == "qualified" else None, name)
    return {"dialect": "postgres", "release": item["release"], "declarations": declarations(item),
            "schemaPath": list(PATH), "sql": NATIVE_FORMS[item["form"]] + target}


def index_metadata(item, schema):
    toasted, keys = LAYOUTS[item["layout"] if schema != DECOY else "one-index"]
    visible = [
        {"name": name, "method": "btree", "slots": [1 if name == "by_key" else 2], "sort": [0 if name == "by_key" else 1],
         "valid": True, "ready": True, "live": True, "unique": name == "by_key", "nullsNotDistinct": False}
        for name in keys
    ]
    auxiliary = ([{"method": "btree", "slots": [1, 2], "sort": [0, 0], "valid": True,
                   "ready": True, "live": True, "unique": True, "nullsNotDistinct": False}] if toasted else [])
    return visible, auxiliary


def stable_snapshot(item):
    return {
        schema: {
            "kind": "m" if schema != DECOY and item["owner"] == "materialized-view" else "r",
            "persistence": "t" if schema == "pg_temp" else "p",
            "hasToast": schema != DECOY and LAYOUTS[item["layout"]][0],
            "columns": column_shape(item, schema), "rows": data(item, schema),
            "indexes": index_metadata(item, schema)[0], "toastIndexes": index_metadata(item, schema)[1],
        } for schema in schemas(item)
    }


def expected(item):
    selected, name, _ = item["reindexTable"]["target"]
    concurrent, active = item["reindexTable"]["mode"] == "concurrently", item["active"]
    fails = concurrent and active
    physical_concurrent = concurrent and selected != "pg_temp"
    stable = stable_snapshot(item)
    notices = []
    if not fails and item["layout"] == "empty-index-set":
        suffix = "that can be reindexed concurrently" if physical_concurrent else "to reindex"
        notices = [["NOTICE", "00000", f'table "{name}" has no indexes {suffix}', None, None]]

    def changes(applies):
        return {
            schema: {key: [[True, physical_concurrent and schema == selected] if applies and schema == selected else [False, False]
                           for _ in snapshot[key]] for key in ("indexes", "toastIndexes")}
            for schema, snapshot in stable.items()
        }

    return {
        "status": "table-rebuild", "command": None if fails else "REINDEX",
        "sqlState": "25001" if fails else "00000", "notices": notices,
        "transactionAfter": "failed" if fails else ("active" if active else "idle"),
        "before": stable, "during": None if fails else stable, "settled": stable,
        "changedDuring": None if fails else changes(True), "changedSettled": changes(not active),
    }
