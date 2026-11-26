"""Construct the fixed report coordinates and independent named rebuild fixtures."""

from postgres_reindex_cases import qualified, quoted

DATABASE = "ir_fixture"
STATUS = "rebuild-report"
ROLES = (
    "ordinary-index", "temporary-index", "branch-index", "leaf-index",
    "ordinary-table", "temporary-table", "partition-branch", "partition-leaf",
    "ordinary-schema", "vacant-schema", "database", "system",
)
EXPLORED = (*ROLES, "materialized-table", "key-index", "exclusion-index")
TOP_LEVEL = {"branch-index", "partition-branch", "ordinary-schema", "vacant-schema", "database", "system"}
OPTIONS = {
    "absent": ("", False, False),
    "bare": ("(VERBOSE) ", True, False),
    "true": ("(VERBOSE TRUE) ", True, False),
    "false": ("(VERBOSE FALSE) ", False, False),
    "one": ("(VERBOSE 1) ", True, False),
    "zero": ("(VERBOSE 0) ", False, False),
    "last-on": ("(VERBOSE FALSE, VERBOSE ON) ", True, False),
    "last-off": ("(VERBOSE TRUE, VERBOSE OFF) ", False, False),
    "option-combined": ("(CONCURRENTLY TRUE, VERBOSE ON) ", True, True),
    "keyword-combined": ("(VERBOSE OFF, CONCURRENTLY FALSE, VERBOSE TRUE) ", True, True),
}
NATIVE_REFUSALS = {
    "invalid-integer": ("REINDEX (VERBOSE 2) INDEX ", "42601", "verbose requires a Boolean value"),
    "invalid-text": ("REINDEX (VERBOSE 'bogus') INDEX ", "42601", "verbose requires a Boolean value"),
    "invalid-overridden": ("REINDEX (VERBOSE 2, VERBOSE TRUE) INDEX ", "42601", "verbose requires a Boolean value"),
    "system-concurrent": ("REINDEX (VERBOSE, CONCURRENTLY) SYSTEM", "0A000",
                          "cannot reindex system catalogs concurrently"),
}


def valid(role, form, _active):
    return role != "system" or not OPTIONS[form][2]


def coordinates(item):
    label = item["role"]
    source = item["tables"]["source"]
    partitions = item["tables"]["leaves"]
    branch = item["tables"]["branch"]
    local = item["tables"]["temp"]
    descriptions = {
        "ordinary-index": ("INDEX", "fixture", source["indexes"][0], source["name"]),
        "temporary-index": ("INDEX", "pg_temp", local["indexes"][0], local["name"]),
        "branch-index": ("INDEX", "fixture", item["branchIndex"], branch),
        "leaf-index": ("INDEX", "remote", partitions[1]["indexes"][0], partitions[1]["name"]),
        "ordinary-table": ("TABLE", "fixture", source["name"]),
        "temporary-table": ("TABLE", "pg_temp", local["name"]),
        "partition-branch": ("TABLE", "fixture", branch),
        "partition-leaf": ("TABLE", "remote", partitions[1]["name"]),
        "ordinary-schema": ("SCHEMA", "fixture"),
        "vacant-schema": ("SCHEMA", "vacant"),
        "database": ("DATABASE", DATABASE),
        "system": ("SYSTEM", DATABASE),
        "materialized-table": ("TABLE", "fixture", "snapshot"),
        "key-index": ("INDEX", "fixture", "owned_primary", "owned_key"),
        "exclusion-index": ("INDEX", "fixture", "owned_exclusion", "owned_exclusion_table"),
    }
    return descriptions[label]


def case(tag, role, form, active, *, coordinate=None, label="", nested=False, extra=2, base=0, explicit=True):
    first = "chosen" + label
    source = {"name": "source" + label, "indexes": [first, *("other_" + str(i) + label for i in range(extra))]}
    leaves = [
        {"schema": "fixture", "name": "low_leaf" + label, "indexes": ["low_id_idx" + label]},
        {"schema": "remote", "name": "high_leaf" + label, "indexes": ["high_id_idx" + label, "extra_high" + label]},
    ]
    _, verbose, concurrent = OPTIONS[form]
    trial = {
        "dialect": "postgres", "release": tag, "role": role, "form": form, "verbose": verbose, "concurrent": concurrent,
        "active": active, "coordinate": coordinate, "tables": {
            "source": source, "branch": "partitioned" + label, "leaves": leaves,
            "temp": {"name": "local_owner" + label, "indexes": ["local_idx" + label]}},
        "branchIndex": "branched" + label, "nested": nested, "base": base, "explicit": explicit,
    }
    mode = "concurrently" if concurrent else "standard"
    selector, schema, *names = coordinates(trial)
    if selector == "INDEX":
        metadata = ({"partitionKind": "branch"} if role == "branch-index" else
                    {"partitionKind": "leaf"} if role == "leaf-index" else
                    {"constraintOwned": True} if role == "key-index" else
                    {"exclusionOwned": True} if role == "exclusion-index" else {})
        trial["reindex"] = {"target": [schema, *names], "mode": mode, **metadata}
    elif selector == "TABLE" and role.startswith("partition-"):
        trial["reindexPartitionTable"] = {"target": [schema, *names], "mode": mode,
                                          "kind": "branch" if role == "partition-branch" else "leaf"}
    elif selector == "TABLE":
        trial["reindexTable"] = {"target": [schema, *names, "materialized-view" if role == "materialized-table" else "base-table"],
                                 "mode": mode}
    elif selector == "SCHEMA":
        trial["reindexSchema"] = {"schema": schema, "mode": mode}
    elif selector == "DATABASE":
        trial["databaseRebuild"] = {"database": schema, "selector": "database", "mode": mode}
    else:
        trial["databaseRebuild"] = {"database": schema, "selector": "system"}
    return trial


def query(item):
    selector, schema, *names = coordinates(item)
    option, _, _ = OPTIONS[item["form"]]
    keyword = " CONCURRENTLY" if item["form"] == "keyword-combined" else ""
    if not names:
        target = (" " + quoted(schema)) if selector == "SCHEMA" or item["explicit"] else ""
    else:
        target = " " + qualified(schema if item["explicit"] or schema == "remote" else None, names[0])
    return "REINDEX " + option + selector + keyword + target


def emitted(item, verbose=None, concurrent=None):
    selector, schema, *names = coordinates(item)
    reports = item["verbose"] if verbose is None else verbose
    locking = item["concurrent"] if concurrent is None else concurrent
    target = qualified(schema, names[0]) if names else quoted(schema)
    return "REINDEX " + ("(VERBOSE) " if reports else "") + selector + (" CONCURRENTLY " if locking else " ") + target


def refusal_query(item):
    prefix, state, message = NATIVE_REFUSALS[item["nativeRejection"]]
    return (prefix + qualified(*coordinates(item)[1:3]) if prefix.endswith(" ") else prefix), state, message


def transport(item):
    roots = ("reindex", "reindexTable", "reindexPartitionTable", "reindexSchema", "databaseRebuild")
    return {role: item[role] for role in roots if role in item}


def table_data(item):
    source = item["tables"]["source"]
    local = item["tables"]["temp"]
    low, high = item["tables"]["leaves"]
    base = item["base"]
    data = {
        ("fixture", source["name"]): [(base + 1, "first"), (base + 2, "second"), (base + 3, None)],
        ("fixture", "decoy"): [(base + 81,)],
        ("fixture", low["name"]): [(2, "low")],
        ("remote", high["name"]): [(12, "high")],
        ("pg_temp", local["name"]): [(base + 743, "temp")],
    }
    extras = {
        "materialized-table": (("fixture", "snapshot"), [(17, "created")]),
        "key-index": (("fixture", "owned_key"), [(17,), (18,)]),
        "exclusion-index": (("fixture", "owned_exclusion_table"), [(17,), (18,)]),
    }
    if item["role"] in extras:
        coordinate, rows = extras[item["role"]]
        data[coordinate] = rows
    return data


def declarations(item):
    source, local = (item["tables"][name] for name in ("source", "temp"))
    low, high = item["tables"]["leaves"]
    low_name, high_name = qualified("fixture", low["name"]), qualified("remote", high["name"])
    branch = qualified("fixture", item["tables"]["branch"])
    intermediate = (qualified("fixture", "inner_" + item["tables"]["branch"]) if item["nested"] else branch)
    primary = qualified("fixture", source["name"])
    temporary = qualified("pg_temp", local["name"])
    result = [
        "CREATE SCHEMA fixture", "CREATE SCHEMA remote", "CREATE SCHEMA vacant",
        f"CREATE TABLE {primary} (id int, payload text)",
        *(f"CREATE INDEX {quoted(name)} ON {primary} ({'id DESC' if i % 2 else 'id'})"
          for i, name in enumerate(source["indexes"])),
        "CREATE TABLE fixture.decoy (id int)", "CREATE INDEX decoy_idx ON fixture.decoy(id)",
        f"CREATE TABLE {branch} (id int, payload text) PARTITION BY RANGE (id)",
    ]
    if item["nested"]:
        result.append(f"CREATE TABLE {intermediate} PARTITION OF {branch} FOR VALUES FROM (0) TO (10) PARTITION BY RANGE(id)")
    result.extend([
        f"CREATE TABLE {low_name} PARTITION OF {intermediate} FOR VALUES FROM (0) TO (10)",
        f"CREATE TABLE {high_name} PARTITION OF {branch} FOR VALUES FROM (10) TO (20)",
        f"CREATE INDEX {quoted(low['indexes'][0])} ON {low_name} (id)",
        f"CREATE INDEX {quoted(high['indexes'][0])} ON {high_name} (id)",
        f"CREATE INDEX {quoted(high['indexes'][1])} ON {high_name} (id DESC)",
        f"CREATE INDEX {quoted(item['branchIndex'])} ON {branch} (id)",
        f"CREATE TEMP TABLE {temporary} (id int, payload text)",
        f"CREATE INDEX {quoted(local['indexes'][0])} ON {temporary} (id)",
    ])
    if item["role"] == "materialized-table":
        result.extend(["CREATE MATERIALIZED VIEW fixture.snapshot AS SELECT 17 AS id, 'created'::text AS payload",
                       "CREATE INDEX materialized_idx ON fixture.snapshot (id)"])
    if item["role"] == "key-index":
        result.append("CREATE TABLE fixture.owned_key (id int, CONSTRAINT owned_primary PRIMARY KEY(id))")
    if item["role"] == "exclusion-index":
        result.append("CREATE TABLE fixture.owned_exclusion_table (id int, CONSTRAINT owned_exclusion EXCLUDE USING btree (id WITH =))")
    return result


def analyzed(item):
    return {
        "dialect": "postgres", "release": item["release"], "sql": query(item), "currentCatalog": DATABASE,
        "declarations": declarations(item), "schemaPath": ["fixture", "remote", "public", "pg_catalog"],
    }


def outcome(item):
    selector = coordinates(item)[0]
    if item.get("nativeRejection"):
        _, state, message = refusal_query(item)
    elif item["active"] and (item["concurrent"] or item["role"] in TOP_LEVEL):
        designation = "REINDEX CONCURRENTLY" if item["concurrent"] else "REINDEX " + selector
        state, message = "25001", designation + " cannot run inside a transaction block"
    else:
        state, message = "00000", None
    return {
        "status": STATUS, "sqlState": state, "command": "REINDEX" if state == "00000" else None,
        "error": None if message is None else ["ERROR", state, message, None, None],
        "transactionAfter": ("active" if state == "00000" else "failed") if item["active"] else "idle",
    }
