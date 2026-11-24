"""Observe ordered native REINDEX diagnostics and catalog phases over disposable data."""

from contextlib import ExitStack
import json
from platform import python_version
import sys

from postgres_create_btree_observer import data_literal, decoded_rows, literal, rows_query
from postgres_libpq import LIB, Session
from postgres_reindex_cases import qualified
from postgres_reindex_verbose_cases import DATABASE, STATUS, declarations, emitted, query, refusal_query, table_data

OWNERS = """SELECT n.nspname, c.relname, c.oid::text, c.relpersistence, c.reltoastrelid::text
FROM pg_catalog.pg_class c JOIN pg_catalog.pg_namespace n ON n.oid=c.relnamespace
WHERE c.relkind IN ('r','m') AND (c.relpersistence<>'t' OR n.oid=pg_catalog.pg_my_temp_schema())
ORDER BY (c.oid=1259) DESC, c.ctid"""

INDEXES = """SELECT iname.nspname, idx.relname, idx.oid::text, pg_catalog.pg_relation_filenode(idx.oid)::text,
       i.indrelid::text, i.indisvalid, i.indisexclusion, i.indisunique
FROM pg_catalog.pg_index i JOIN pg_catalog.pg_class idx ON idx.oid=i.indexrelid
JOIN pg_catalog.pg_class owner ON owner.oid=i.indrelid
JOIN pg_catalog.pg_namespace iname ON iname.oid=idx.relnamespace
JOIN pg_catalog.pg_namespace n ON n.oid=owner.relnamespace
WHERE owner.relkind IN ('r','m','t') AND (owner.relpersistence<>'t'
      OR n.oid=pg_catalog.pg_my_temp_schema()
      OR n.nspname=pg_catalog.replace((SELECT nspname FROM pg_catalog.pg_namespace
                WHERE oid=pg_catalog.pg_my_temp_schema()),'pg_temp_','pg_toast_temp_'))
ORDER BY idx.oid"""

LINKS = """SELECT child.relkind, pn.nspname, parent.relname, cn.nspname, child.relname
FROM pg_catalog.pg_inherits i JOIN pg_catalog.pg_class parent ON i.inhparent=parent.oid
JOIN pg_catalog.pg_class child ON i.inhrelid=child.oid
JOIN pg_catalog.pg_namespace pn ON pn.oid=parent.relnamespace
JOIN pg_catalog.pg_namespace cn ON cn.oid=child.relnamespace
WHERE pn.nspname='fixture' ORDER BY child.oid"""


def allowed(item, sql):
    if item.get("nativeRejection"):
        return sql == refusal_query(item)[0]
    return sql in {query(item), *(emitted(item, verbose, concurrent) for verbose in (False, True)
                 for concurrent in (False, True))}


def setup(db, item):
    commands = ["SET lc_messages='C'", "SET client_min_messages=warning",
                *(f"DROP SCHEMA IF EXISTS {schema} CASCADE" for schema in ("fixture", "remote", "vacant")),
                *declarations(item)]
    for (schema, name), values in table_data(item).items():
        if name == "snapshot" and item["role"] == "materialized-table":
            continue
        rows = ["(" + ", ".join(data_literal(value) if type(value) is str else literal(value) for value in row) + ")"
                for row in values]
        commands.append(f"INSERT INTO {qualified(schema, name)} VALUES " + ", ".join(rows))
    commands.extend(["SET search_path=fixture,remote,public,pg_catalog", "SET client_min_messages=notice"])
    db.execute("; ".join(commands))


def snapshot(db, item):
    owners = [{ "schema": schema, "name": name, "id": int(oid), "mode": mode, "toast": int(toast) }
              for schema, name, oid, mode, toast in db.execute(OWNERS)]
    indexes = [{ "schema": schema, "name": name, "id": int(oid), "file": int(file), "owner": int(owner),
                 "valid": valid == "t", "exclusion": exclusion == "t", "unique": unique == "t" }
               for schema, name, oid, file, owner, valid, exclusion, unique in db.execute(INDEXES)]
    rows = {qualified(schema, name): decoded_rows(db.execute(rows_query(qualified(schema, name), "id"))[0][0])
            for schema, name in table_data(item)}
    return {"owners": owners, "indexes": indexes, "links": db.execute(LINKS), "rows": rows}


def observe_prepared(db, item, sql):
    before = snapshot(db, item)
    if item["active"]:
        db.execute("BEGIN")
    reply = db.run(sql, diagnostic_error=True)
    transaction = db.transaction()
    after = snapshot(db, item) if reply["sqlState"] == "00000" else None
    if item["active"]:
        db.execute("ROLLBACK")
    settled = snapshot(db, item) if item["active"] or after is None else after
    return {"status": STATUS, "before": before, "during": after, "settled": settled,
            "transactionAfter": transaction, **{key: reply[key] for key in ("notices", "error", "sqlState", "command")}}


def observe(item, sql):
    if not allowed(item, sql):
        return {"status": "outside-observer-scope"}
    with Session() as db:
        setup(db, item)
        return observe_prepared(db, item, sql)


def main():
    with Session() as db:
        db.execute("SET lc_messages='C'")
        settings = [db.execute("SHOW " + key)[0][0] for key in (
            "server_version_num", "server_encoding", "client_encoding", "standard_conforming_strings", "search_path")]
        locale = db.execute("SHOW lc_messages")[0][0]
        current = db.execute("SELECT current_database()")[0][0]
        assert current == DATABASE
    print(json.dumps({"database": settings, "currentDatabase": current, "locale": locale,
                      "fixtureSession": "reuse-exact-declarations-and-rows",
                      "python": python_version(), "libpq": LIB.PQlibVersion()}), flush=True)
    with ExitStack() as sessions:
        fixture, db = None, None

        def run(request):
            nonlocal fixture, db
            item, sql = request["trial"], request["sql"]
            if not allowed(item, sql):
                return {"status": "outside-observer-scope"}
            wanted = declarations(item), table_data(item)
            if wanted != fixture:
                sessions.close()
                db = sessions.enter_context(Session())
                setup(db, item)
                fixture = wanted
            return observe_prepared(db, item, sql)

        for line in sys.stdin:
            incoming = json.loads(line)
            result = [run(request) for request in incoming] if isinstance(incoming, list) else run(incoming)
            print(json.dumps(result, ensure_ascii=False), flush=True)


if __name__ == "__main__":
    main()
