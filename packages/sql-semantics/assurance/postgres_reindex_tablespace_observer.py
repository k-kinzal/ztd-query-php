"""Observe physical index placement and diagnostics on stock PostgreSQL."""

from contextlib import ExitStack
import json
from platform import python_version
import sys

from native_postgres_fixture import TABLESPACES
from postgres_libpq import LIB, Session
from postgres_reindex_cases import qualified, quoted
from postgres_reindex_tablespace_cases import DATABASE, emitted, source_space, sql
from postgres_reindex_verbose_cases import declarations, table_data
from postgres_reindex_verbose_observer import setup, snapshot
from run import require

CATALOG = """SELECT n.nspname, c.relname, s.spcname, c.relkind, c.oid::text, pg_catalog.pg_relation_filenode(c.oid)::text
FROM pg_catalog.pg_class c
JOIN pg_catalog.pg_namespace n ON n.oid=c.relnamespace
JOIN pg_catalog.pg_database d ON d.datname=current_database()
JOIN pg_catalog.pg_tablespace s ON s.oid=coalesce(nullif(c.reltablespace,0),d.dattablespace)
WHERE c.relkind IN ('r','m','t','p','i','I')
  AND (c.relpersistence<>'t' OR n.oid=pg_catalog.pg_my_temp_schema()
      OR n.nspname=pg_catalog.replace((SELECT nspname FROM pg_catalog.pg_namespace
         WHERE oid=pg_catalog.pg_my_temp_schema()),'pg_temp_','pg_toast_temp_'))
ORDER BY n.nspname,c.relname"""

SPACE_NAMES = "SELECT spcname FROM pg_catalog.pg_tablespace WHERE spcname NOT IN ('pg_default','pg_global')"
APPLICATION = {"fixture", "remote", "pg_temp"}


def local_schema(schema):
    return "pg_temp" if schema.startswith("pg_temp_") else schema


def reset_placement(db, item):
    wanted = source_space(item)
    commands = []
    for schema, name, space, kind, _, _ in db.execute(CATALOG):
        logical = local_schema(schema)
        desired = wanted if logical in APPLICATION else "pg_default" if logical == "information_schema" else space
        if kind == "i" and desired != space:
            commands.append(f"ALTER INDEX {qualified(logical, name)} SET TABLESPACE {quoted(desired)}")
    if commands:
        db.execute("; ".join(commands))


def placed_snapshot(db, item):
    data = snapshot(db, item)
    data["placement"] = db.execute(CATALOG)
    return data


def observe(db, item, authored):
    reset_placement(db, item)
    before = placed_snapshot(db, item)
    if item["active"]:
        db.execute("BEGIN")
    reply = db.run(authored, diagnostic_error=True)
    transaction = db.transaction()
    after = placed_snapshot(db, item) if reply["sqlState"] == "00000" else None
    if item["active"]:
        db.execute("ROLLBACK")
    settled = placed_snapshot(db, item) if item["active"] or after is None else after
    return {"status": "rebuild-relocation", "before": before, "during": after, "settled": settled,
            "transactionAfter": transaction, **{key: reply[key] for key in ("notices", "error", "sqlState", "command")}}


def main():
    with Session() as db:
        settings = [db.execute("SHOW " + key)[0][0] for key in (
            "server_version_num", "server_encoding", "client_encoding", "standard_conforming_strings", "search_path")]
        current = db.execute("SELECT current_database()")[0][0]
        spaces = sorted(row[0] for row in db.execute(SPACE_NAMES))
        require(current == DATABASE and spaces == sorted(TABLESPACES), "postgres-relocation-native-fixture",
                {"database": current, "spaces": spaces})
    print(json.dumps({"database": settings, "currentDatabase": current, "tablespaces": spaces,
                      "python": python_version(), "libpq": LIB.PQlibVersion()}), flush=True)
    with ExitStack() as sessions:
        signature, db = None, None

        def run(request):
            nonlocal signature, db
            item, authored = request["trial"], request["sql"]
            allowed = {sql(item), *(emitted(item, destination, report)
                       for destination in (None, "pg_default", *TABLESPACES) for report in (False, True))}
            if authored not in allowed:
                return {"status": "outside-observer-scope"}
            target = declarations(item), table_data(item)
            if signature != target:
                sessions.close()
                db = sessions.enter_context(Session())
                setup(db, item)
                signature = target
            return observe(db, item, authored)

        for line in sys.stdin:
            request = json.loads(line)
            result = [run(item) for item in request] if isinstance(request, list) else run(request)
            print(json.dumps(result, ensure_ascii=False), flush=True)


if __name__ == "__main__":
    main()
