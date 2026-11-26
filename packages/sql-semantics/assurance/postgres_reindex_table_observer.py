"""Observe the physical indexes, TOAST and typed rows for an isolated owner fixture."""

import json
from platform import python_version
import sys

from postgres_create_btree_observer import data_literal
from postgres_libpq import LIB, Session
from postgres_reindex_cases import qualified, quoted
from postgres_reindex_table_cases import NATIVE_FORMS, PATH, fixtures, schemas

HEAP = """SELECT pg_catalog.json_build_array(
    oid::text, pg_catalog.pg_relation_filenode(oid)::text, relkind, relpersistence, reltoastrelid::text)
    FROM pg_catalog.pg_class WHERE oid=pg_catalog.to_regclass({target})"""

ATTRIBUTES = """SELECT pg_catalog.json_agg(pg_catalog.json_build_array(a.attname,t.typname) ORDER BY a.attnum)
    FROM pg_catalog.pg_attribute a JOIN pg_catalog.pg_type t ON t.oid=a.atttypid
    WHERE a.attrelid=pg_catalog.to_regclass({target}) AND a.attnum>0 AND NOT a.attisdropped"""

INDEXES = """SELECT COALESCE(
    pg_catalog.json_agg(pg_catalog.json_build_object(
        'name', c.relname, 'oid', c.oid::text, 'file', pg_catalog.pg_relation_filenode(c.oid)::text,
        'method', am.amname, 'slots', i.indkey::text, 'sort', i.indoption::text,
        'valid', i.indisvalid, 'ready', i.indisready, 'live', i.indislive,
        'unique', i.indisunique, 'nullsNotDistinct', i.indnullsnotdistinct
    ) ORDER BY c.relname), '[]'::json)
    FROM pg_catalog.pg_index i JOIN pg_catalog.pg_class c ON c.oid=i.indexrelid
    JOIN pg_catalog.pg_am am ON am.oid=c.relam
    WHERE i.indrelid={owner}"""


def admitted(item, sql):
    _, name, _ = item["reindexTable"]["target"]
    for schema in (*schemas(item), None):
        if sql in {prefix + qualified(schema, name) for prefix in NATIVE_FORMS.values()}:
            return True
    selected = item["reindexTable"]["target"][0]
    return sql == "REINDEX INDEX " + qualified(selected, "by_key")


def initialize(db, item):
    commands = ["SET client_min_messages=warning", "RESET search_path",
                "DROP SCHEMA IF EXISTS fixture CASCADE", "DROP SCHEMA IF EXISTS decoy CASCADE",
                "CREATE SCHEMA fixture", "CREATE SCHEMA decoy"]
    for schema in schemas(item):
        owner, keys, populate = fixtures(item, schema)
        commands.extend([owner, *populate, *keys])
    commands.extend(["SET search_path=" + ", ".join(map(quoted, PATH)), "SET client_min_messages=notice"])
    db.execute(";".join(commands))


def read_indexes(raw):
    indexes = json.loads(raw)
    for entry in indexes:
        for field in ("slots", "sort"):
            entry[field] = [int(x) for x in entry[field].split()]
    return indexes


def read_owner(db, schema, name):
    table = qualified(schema, name)
    target = data_literal(table)
    regclass = f"pg_catalog.to_regclass({target})"
    toast_oid = f"(SELECT reltoastrelid FROM pg_catalog.pg_class WHERE oid={regclass})"
    columns = ATTRIBUTES.format(target=target)
    rows = f"SELECT pg_catalog.json_agg(pg_catalog.row_to_json(r) ORDER BY r.id) FROM {table} r"
    queries = (HEAP.format(target=target), columns, rows,
               INDEXES.format(owner=regclass), INDEXES.format(owner=toast_oid))
    heap, keys, content, indexes, toast = db.execute("SELECT " + ", ".join(f"({q})" for q in queries))[0]
    oid, file, kind, persistence, toast_oid = json.loads(heap)
    heading = json.loads(keys)
    source_rows = [] if content is None else json.loads(content)
    records = [[record[field] for field, _ in heading] for record in source_rows]
    return {
        "oid": oid, "file": file, "kind": kind, "persistence": persistence, "toastOid": toast_oid,
        "columns": heading, "rows": records, "indexes": read_indexes(indexes), "toastIndexes": read_indexes(toast),
    }


def snapshot(db, item):
    _, name, _ = item["reindexTable"]["target"]
    return {schema: read_owner(db, schema, name) for schema in schemas(item)}


def observe(db, request):
    item, sql = request["trial"], request["sql"]
    if not admitted(item, sql):
        return {"status": "outside-observer-scope"}
    initialize(db, item)
    before = snapshot(db, item)
    if item["active"]:
        db.execute("BEGIN")
    result = db.run(sql)
    transaction = db.transaction()
    during = snapshot(db, item) if result["sqlState"] == "00000" else None
    if item["active"]:
        db.execute("ROLLBACK")
    return {
        "status": "table-rebuild", "before": before, "during": during, "settled": snapshot(db, item),
        "transactionAfter": transaction, **{key: result[key] for key in ("sqlState", "command", "notices")},
    }


def main():
    with Session() as regular:
        settings = [regular.execute("SHOW " + key)[0][0] for key in
                    ("server_version_num", "server_encoding", "client_encoding", "standard_conforming_strings", "search_path")]
        print(json.dumps({"database": settings, "python": python_version(), "libpq": LIB.PQlibVersion()}), flush=True)

        def run(request):
            if request["trial"]["reindexTable"]["target"][0] == "pg_temp":
                with Session() as temporary:
                    return observe(temporary, request)
            return observe(regular, request)

        for line in sys.stdin:
            incoming = json.loads(line)
            result = [run(request) for request in incoming] if isinstance(incoming, list) else run(incoming)
            print(json.dumps(result, ensure_ascii=False), flush=True)


if __name__ == "__main__":
    main()
