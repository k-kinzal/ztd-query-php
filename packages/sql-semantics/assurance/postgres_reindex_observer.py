"""Record native rebuild identity, storage, layout, unaffected relations and writes."""

import json
from platform import python_version
import sys

from postgres_create_btree_observer import data_literal, decoded_rows, literal, rows_query
from postgres_libpq import LIB, Session
from postgres_reindex_cases import FORMS, PATH, PROBES, declarations, namespaces, qualified, quoted, rows_for
from run import require

INDEX = """SELECT pg_catalog.json_build_object(
    'oid', c.oid::text, 'file', pg_catalog.pg_relation_filenode(c.oid)::text,
    'namespace', n.nspname, 'index', c.relname, 'owner', r.relname, 'accessMethod', a.amname,
    'unique', i.indisunique, 'nullsNotDistinct', i.indnullsnotdistinct, 'valid', i.indisvalid,
    'ready', i.indisready, 'live', i.indislive, 'keyCount', i.indnkeyatts,
    'primary', i.indisprimary, 'immediate', i.indimmediate,
    'indexSlots', i.indkey::text, 'orders', i.indoption::text, 'options', c.reloptions,
    'predicate', pg_catalog.pg_get_expr(i.indpred, i.indrelid, false))
FROM pg_catalog.pg_index i JOIN pg_catalog.pg_class c ON c.oid=i.indexrelid
JOIN pg_catalog.pg_class r ON r.oid=i.indrelid
JOIN pg_catalog.pg_namespace n ON n.oid=c.relnamespace
JOIN pg_catalog.pg_am a ON a.oid=c.relam
WHERE c.oid=pg_catalog.to_regclass({index})
"""


def admitted(sql, trial):
    _, index, _ = trial["reindex"]["target"]
    targets = (qualified(scope, index) for scope in (None, *namespaces(trial)))
    return sql in {prefix + name for name in targets for prefix in FORMS.values()}


def initialize(db, trial):
    _, _, table = trial["reindex"]["target"]
    commands = ["SET client_min_messages=warning", "DROP SCHEMA IF EXISTS fixture CASCADE", "DROP SCHEMA IF EXISTS decoy CASCADE"]
    commands.extend(declarations(trial))
    for schema in namespaces(trial):
        values = ", ".join("(" + ", ".join(map(literal, row)) + ")" for row in rows_for(trial, schema))
        commands.append(f"INSERT INTO {qualified(schema, table)} VALUES {values}")
    if trial.get("constraintPolicy") in ("primary", "unique", "unique-nulls"):
        scope = trial["reindex"]["target"][0]
        commands.append(f"INSERT INTO {qualified(scope, 'children')} VALUES (20),(19)")
    commands.extend(["SET search_path=" + ", ".join(map(quoted, PATH)), "SET client_min_messages=notice"])
    db.execute(";".join(commands))


def snapshot(db, trial):
    _, index, table = trial["reindex"]["target"]
    queries = []
    for schema in namespaces(trial):
        queries.extend([
            INDEX.format(index=data_literal(qualified(schema, index))),
            rows_query(qualified(schema, table), "state"),
        ])
    values = db.execute("SELECT " + ", ".join(f"({query})" for query in queries))[0]
    states = {}
    for number, schema in enumerate(namespaces(trial)):
        index_raw, rows = values[2 * number:2 * number + 2]
        fields = json.loads(index_raw) if index_raw is not None else None
        if fields is not None:
            if schema == "pg_temp" and fields["namespace"].startswith("pg_temp_"):
                fields["namespace"] = "pg_temp"
            for field in ("indexSlots", "orders"):
                fields[field] = [int(value) for value in fields[field].split()]
        states[schema] = {"index": fields, "rows": decoded_rows(rows)}
    if "constraintPolicy" in trial:
        scope = trial["reindex"]["target"][0]
        states[scope]["constraints"] = read_dependencies(db, trial, scope, table)
    return states


def read_dependencies(db, trial, schema, table):
    table_identity = data_literal(qualified(schema, table))
    shaped = (
        "SELECT pg_catalog.json_build_array(c.oid::text, c.contype, c.conindid::text, c.conrelid::text, "
        "c.confrelid::text, c.condeferrable, c.condeferred, c.convalidated) FROM pg_catalog.pg_constraint c "
    )
    key = db.execute(shaped + f"WHERE c.conrelid=pg_catalog.to_regclass({table_identity}) AND c.contype IN ('p','u')")[0][0]
    foreign, rows = None, None
    if trial["constraintPolicy"] != "deferrable":
        child = qualified(schema, "children")
        foreign = db.execute(shaped + f"WHERE c.conrelid=pg_catalog.to_regclass({data_literal(child)}) AND c.contype='f'")[0][0]
        rows = [int(row[0]) for row in db.execute(f"SELECT value FROM {child} ORDER BY value")]
    return {"key": json.loads(key), "foreign": json.loads(foreign) if foreign is not None else None, "rows": rows}


def probe_commands(trial):
    scope, _, name = trial["reindex"]["target"]
    table = qualified(scope, name)
    if "constraintPolicy" in trial:
        yield f"INSERT INTO {table} VALUES (105,20,TRUE)"
        yield f"INSERT INTO {table} VALUES (106,NULL,TRUE),(107,NULL,FALSE)"
        if trial["constraintPolicy"] != "deferrable":
            yield f"INSERT INTO {qualified(scope, 'children')} VALUES (99)"
        return
    for row in PROBES:
        values = ", ".join(map(literal, row))
        yield f"INSERT INTO {table} VALUES ({values})"


def write_probes(db, trial):
    active = trial["active"]
    outcomes = []
    for command in probe_commands(trial):
        db.execute("SAVEPOINT write_probe" if active else "BEGIN")
        outcomes.append(db.run(command)["sqlState"])
        db.execute("ROLLBACK TO SAVEPOINT write_probe; RELEASE SAVEPOINT write_probe" if active else "ROLLBACK")
    return outcomes


def observe(db, data):
    trial, sql = data["trial"], data["sql"]
    if not admitted(sql, trial):
        return {"status": "outside-observer-scope"}
    initialize(db, trial)
    before = snapshot(db, trial)
    if trial["active"]:
        db.execute("BEGIN")
    result = db.run(sql)
    transaction = db.transaction()
    success = result["sqlState"] == "00000"
    during = snapshot(db, trial) if success else None
    observed = write_probes(db, trial) if success else None
    if trial["active"]:
        db.execute("ROLLBACK")
    return {
        "status": "index-rebuild", "before": before, "during": during, "settled": snapshot(db, trial),
        "probe": observed, "transactionAfter": transaction,
        **{key: result[key] for key in ("sqlState", "command", "notices")},
    }


def main():
    with Session() as regular:
        settings = [regular.execute("SHOW " + key)[0][0] for key in (
            "server_version_num", "server_encoding", "client_encoding", "standard_conforming_strings", "search_path")]
        print(json.dumps({"database": settings, "python": python_version(), "libpq": LIB.PQlibVersion()}), flush=True)

        def run(request):
            if request["trial"]["reindex"]["target"][0] == "pg_temp":
                with Session() as temporary:
                    return observe(temporary, request)
            return observe(regular, request)

        for line in sys.stdin:
            incoming = json.loads(line)
            result = [run(request) for request in incoming] if isinstance(incoming, list) else run(incoming)
            print(json.dumps(result, ensure_ascii=False), flush=True)


if __name__ == "__main__":
    main()
