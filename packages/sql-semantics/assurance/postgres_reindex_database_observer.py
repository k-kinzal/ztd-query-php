"""Measure the complete stock index inventory and the explicit application fixture."""

import json
from platform import python_version
import sys

from postgres_libpq import LIB, Session
from postgres_reindex_cases import qualified, quoted
from postgres_reindex_database_cases import DATABASE, FORMS, NATIVE_REFUSALS, STATUS, fixture, owners, sql
from postgres_reindex_table_observer import read_owner
from postgres_stock_catalog import observe as stock_catalog

CATALOG = """SELECT pg_catalog.json_build_array(n.nspname, c.relname, owner.relname,
    owner.oid::text, owner.relisshared, owner.relpersistence, i.indisvalid, i.indisexclusion,
    c.oid::text, pg_catalog.pg_relation_filenode(c.oid)::text)
FROM pg_catalog.pg_index i JOIN pg_catalog.pg_class owner ON i.indrelid=owner.oid
JOIN pg_catalog.pg_class c ON c.oid=i.indexrelid
JOIN pg_catalog.pg_namespace n ON n.oid=owner.relnamespace
WHERE owner.relkind IN ('r','m','t')"""


def admitted(item, command):
    return command in {
        sql(item), 'REINDEX DATABASE "other"',
        *NATIVE_REFUSALS.values(),
        *(prefix + suffix for group in FORMS.values() for prefix in group.values()
          for suffix in ("", " " + DATABASE, " " + quoted(DATABASE))),
    }


def snapshot(db, item):
    tables = {qualified(schema, name): read_owner(db, schema, name)
              for schema, name, *_ in owners(item).values()}
    catalog = []
    for field, in db.execute(CATALOG):
        data = json.loads(field)
        if data[0].startswith("pg_temp_"):
            data[0] = "pg_temp"
        elif data[0].startswith("pg_toast_temp_"):
            data[0] = "pg_toast_temp"
        catalog.append(data)
    return {"owners": tables, "catalog": sorted(catalog), "stockCatalog": stock_catalog(db)}


def observe(db, request):
    item, command = request["trial"], request["sql"]
    if not admitted(item, command):
        return {"status": "outside-observer-scope"}
    setup = [
        "SET client_min_messages=warning", "DROP SCHEMA IF EXISTS fixture CASCADE",
        "DROP TABLE IF EXISTS pg_temp.items", *fixture(item), "SET client_min_messages=notice",
    ]
    db.execute(";".join(setup))
    before = snapshot(db, item)
    if item["active"]:
        db.execute("BEGIN")
    result = db.run(command)
    transaction = db.transaction()
    if item["active"]:
        db.execute("ROLLBACK")
    settled = snapshot(db, item)
    return {"status": STATUS, "before": before, "settled": settled, "transactionAfter": transaction,
            **{key: result[key] for key in ("sqlState", "command", "notices")}}


def main():
    with Session() as db:
        settings = [db.execute("SHOW " + key)[0][0] for key in
                    ("server_version_num", "server_encoding", "client_encoding", "standard_conforming_strings", "search_path")]
        database = db.execute("SELECT current_database()")[0][0]
        print(json.dumps({"database": settings, "currentDatabase": database, "python": python_version(), "libpq": LIB.PQlibVersion()}), flush=True)
        for line in sys.stdin:
            incoming = json.loads(line)
            result = [observe(db, item) for item in incoming] if isinstance(incoming, list) else observe(db, incoming)
            print(json.dumps(result, ensure_ascii=False), flush=True)


if __name__ == "__main__":
    main()
