"""Independent coordinate, fixture and outcome rules for PostgreSQL exclusion-index rebuilds."""

from postgres_reindex_cases import CONCURRENT, FORMS, qualified, quoted

STATUS = "exclusion-index-rebuild"
AXES = ("storage", "coverage", "concurrently", "active", "spelling")
PERSISTENCE = {"persistent": "p", "unlogged": "u", "temporary": "t"}
CREATE = {"persistent": "CREATE TABLE ", "unlogged": "CREATE UNLOGGED TABLE ", "temporary": "CREATE TEMP TABLE "}
SPELLINGS = ("qualified", "search-path")
COVERAGE = ("whole", "partial")
DECOY_ROWS = [[4, -301, True], [8, 801, False]]


def case(tag, storage, coverage, concurrently, active, spelling, *, form=None, coordinate=None,
         schema="fixture", index="checked", table="orders", base=10):
    if storage == "temporary":
        schema = "pg_temp"
    return {
        "dialect": "postgres", "release": tag,
        "reindex": {"target": [schema, index, table], "mode": "concurrently" if concurrently else "standard",
                    "exclusionOwned": True},
        "storage": storage, "coverage": coverage, "active": active, "spelling": spelling,
        "form": form or ("keyword" if concurrently else "ordinary"), "coordinate": coordinate,
        "rows": [[1, base, True], [2, base + 1, False], [3, None, True]],
    }


def valid(storage, _, concurrently, *_unused):
    return not concurrently or storage == "temporary"


def original(item):
    schema, index, _ = item["reindex"]["target"]
    return FORMS[item["form"]] + qualified(schema if item["spelling"] == "qualified" else None, index)


def namespace_path(item):
    schema = item["reindex"]["target"][0]
    return [schema, "decoy", "public", "pg_catalog"]


def fixture(item):
    schema, index, table = item["reindex"]["target"]
    target = qualified(schema, table)
    decoy = qualified("decoy", table)
    columns = "id int, amount int, ready bool"
    partial = " WHERE (ready)" if item["coverage"] == "partial" else ""
    return [
        *([] if schema == "pg_temp" else ["CREATE SCHEMA " + quoted(schema)]),
        "CREATE SCHEMA decoy",
        CREATE[item["storage"]] + target + f" ({columns}, CONSTRAINT {quoted(index)} "
        f"EXCLUDE USING btree (amount WITH =){partial})",
        f"CREATE TABLE {decoy} ({columns})",
        f"CREATE INDEX {quoted(index)} ON {decoy} (amount)",
    ]


def analyzed(item):
    return {"dialect": "postgres", "release": item["release"],
            "schemaPath": [name for name in namespace_path(item) if name != "pg_temp"],
            "sql": original(item), "declarations": fixture(item)}


def rules(item):
    active = item["active"]
    concurrent = item["reindex"]["mode"] == "concurrently"
    partial = item["coverage"] == "partial"
    state = "25001" if active and concurrent else "0A000" if concurrent and item["storage"] != "temporary" else "00000"
    success = state == "00000"
    conflict = "00000" if partial else "23P01"
    return {
        "status": STATUS, "sqlState": state, "command": "REINDEX" if success else None, "notices": [],
        "transactionAfter": ("active" if success else "failed") if active else "idle",
        "probes": ["23P01", conflict, "00000", conflict, "2BP01"],
    }


def probe_commands(item):
    schema, index, table = item["reindex"]["target"]
    owner = qualified(schema, table)
    base = item["rows"][0][1]
    return (
        f"INSERT INTO {owner} VALUES (9,{base},TRUE)",
        f"INSERT INTO {owner} VALUES (9,{base + 1},TRUE)",
        f"INSERT INTO {owner} VALUES (9,NULL,TRUE)",
        f"INSERT INTO {owner} VALUES (9,{base},NULL)",
        "DROP INDEX " + qualified(schema, index),
    )


def permitted_sql(item):
    _, index, _ = item["reindex"]["target"]
    return {pattern + qualified(schema, index) for schema in (None, *namespace_path(item)[:2])
            for pattern in FORMS.values()}


def concurrent_forms(storage):
    return tuple(form for form in FORMS if form not in CONCURRENT or storage == "temporary")
