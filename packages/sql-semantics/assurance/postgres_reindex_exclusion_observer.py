"""Observe exclusion ownership, catalog transitions and typed writes using stock PostgreSQL."""

import json
from platform import python_version
import sys

from postgres_create_btree_observer import data_literal, decoded_rows, literal, rows_query
from postgres_libpq import LIB, Session
from postgres_reindex_cases import qualified, quoted
from postgres_reindex_exclusion_cases import DECOY_ROWS, STATUS, fixture, namespace_path, permitted_sql, probe_commands

INDEX = """SELECT pg_catalog.json_build_object(
    'oid', c.oid::text, 'file', pg_catalog.pg_relation_filenode(c.oid)::text, 'owner', h.oid::text,
    'name', c.relname, 'ownerName', h.relname,
    'namespace', CASE WHEN n.oid=pg_catalog.pg_my_temp_schema() THEN 'pg_temp' ELSE n.nspname END,
    'accessMethod', a.amname, 'exclusion', i.indisexclusion, 'immediate', i.indimmediate,
    'unique', i.indisunique, 'valid', i.indisvalid, 'ready', i.indisready, 'live', i.indislive,
    'persistence', c.relpersistence, 'slots', i.indkey::text, 'options', c.reloptions,
    'predicate', pg_catalog.pg_get_expr(i.indpred, i.indrelid, false),
    'constraint', CASE WHEN k.oid IS NULL THEN NULL ELSE pg_catalog.json_build_object(
        'oid', k.oid::text, 'index', k.conindid::text, 'owner', k.conrelid::text, 'name', k.conname,
        'kind', k.contype, 'operators', k.conexclop::text, 'defined', pg_catalog.pg_get_constraintdef(k.oid),
        'valid', k.convalidated, 'deferrable', k.condeferrable, 'deferred', k.condeferred) END)
FROM pg_catalog.pg_index i JOIN pg_catalog.pg_class c ON c.oid=i.indexrelid
JOIN pg_catalog.pg_class h ON h.oid=i.indrelid
JOIN pg_catalog.pg_namespace n ON n.oid=c.relnamespace
JOIN pg_catalog.pg_am a ON a.oid=c.relam
LEFT JOIN pg_catalog.pg_constraint k ON k.conrelid=h.oid AND k.conname={name}
WHERE c.oid=pg_catalog.to_regclass({index})"""


def initialize(db, item):
    schema, _, table = item["reindex"]["target"]
    commands = ["SET client_min_messages=warning", "DROP SCHEMA IF EXISTS decoy CASCADE"]
    if schema != "pg_temp":
        commands.append("DROP SCHEMA IF EXISTS " + quoted(schema) + " CASCADE")
    commands.extend(fixture(item))
    for namespace, rows in ((schema, item["rows"]), ("decoy", DECOY_ROWS)):
        values = ["(" + ", ".join(map(literal, row)) + ")" for row in rows]
        commands.append("INSERT INTO " + qualified(namespace, table) + " VALUES " + ", ".join(values))
    commands.extend(["SET search_path=" + ", ".join(map(quoted, namespace_path(item))), "SET client_min_messages=notice"])
    db.execute("; ".join(commands))


def snapshot(db, item):
    schema, index, table = item["reindex"]["target"]
    result = {}
    for name in (schema, "decoy"):
        identity, owner = qualified(name, index), qualified(name, table)
        catalog = INDEX.format(index=data_literal(identity), name=data_literal(index))
        fields, rows = db.execute(f"SELECT ({catalog}), ({rows_query(owner, 'id')})")[0]
        result[name] = {"index": json.loads(fields), "rows": decoded_rows(rows)}
    return result


def observe(db, request):
    trial, sql = request["trial"], request["sql"]
    if sql not in permitted_sql(trial):
        return {"status": "outside-observer-scope"}
    initialize(db, trial)
    before = snapshot(db, trial)
    if trial["active"]:
        db.execute("BEGIN")
    outcome = db.run(sql)
    transaction = db.transaction()
    during = snapshot(db, trial) if outcome["sqlState"] == "00000" else None
    if trial["active"]:
        db.execute("ROLLBACK")
    settled = snapshot(db, trial)
    probes = []
    for statement in probe_commands(trial):
        db.execute("BEGIN")
        probes.append(db.run(statement)["sqlState"])
        db.execute("ROLLBACK")
    return {"status": STATUS, "before": before, "during": during, "settled": settled, "probes": probes,
            "transactionAfter": transaction, **{key: outcome[key] for key in ("sqlState", "command", "notices")}}


def main():
    with Session() as regular:
        settings = [regular.execute("SHOW " + key)[0][0] for key in (
            "server_version_num", "server_encoding", "client_encoding", "standard_conforming_strings", "search_path")]
        print(json.dumps({"database": settings, "python": python_version(), "libpq": LIB.PQlibVersion()}), flush=True)

        def run(request):
            if request["trial"]["storage"] == "temporary":
                with Session() as temporary:
                    return observe(temporary, request)
            return observe(regular, request)

        for line in sys.stdin:
            incoming = json.loads(line)
            result = [run(request) for request in incoming] if isinstance(incoming, list) else run(incoming)
            print(json.dumps(result, ensure_ascii=False), flush=True)


if __name__ == "__main__":
    main()
