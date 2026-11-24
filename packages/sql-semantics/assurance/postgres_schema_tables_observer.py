"""Observe native schema, ownership, ordered metadata and deferred defaults."""

import json
from platform import python_version
import sys

from postgres_create_schema_cases import CATALOG, EFFECTIVE, OWNER, SESSION
from postgres_create_schema_observer import prepare_roles
from postgres_drop_schema_observer import literal, table_rows
from postgres_libpq import LIB, Session
from postgres_reindex_cases import qualified, quoted
from postgres_schema_tables_cases import DECOY, STATUS, sql
from run import require


def prepare_state(db, item):
    namespace = item["namespace"]
    db.execute(f"SET client_min_messages=warning; DROP SCHEMA IF EXISTS {quoted(namespace)}, {quoted(DECOY)} CASCADE")
    decoy = qualified(DECOY, "items")
    db.execute(f"CREATE SCHEMA {quoted(DECOY)}; CREATE TABLE {decoy} (id integer); INSERT INTO {decoy} VALUES ({item['base'] + 100})")
    if item["occupied"]:
        prior = qualified(namespace, "items")
        db.execute(f"CREATE SCHEMA {quoted(namespace)} AUTHORIZATION {quoted(OWNER)}")
        db.execute(f"CREATE TABLE {prior} (prior integer NOT NULL); ALTER TABLE {prior} OWNER TO {quoted(OWNER)}")
        db.execute(f"INSERT INTO {prior} VALUES ({item['base']})")
    db.execute("SET client_min_messages=notice")


def snapshot(db, item):
    namespace = item["namespace"]
    schema = db.execute("SELECT oid, pg_catalog.pg_get_userbyid(nspowner) FROM pg_catalog.pg_namespace "
                        f"WHERE nspname={literal(namespace)}")
    owner, tables = None, []
    if schema:
        (schema_id, owner), = schema
        rows = db.execute(
            "SELECT c.oid, c.relname, pg_catalog.pg_get_userbyid(c.relowner), c.relkind, am.amname "
            "FROM pg_catalog.pg_class c LEFT JOIN pg_catalog.pg_am am ON am.oid=c.relam "
            f"WHERE c.relnamespace={int(schema_id)}",
        )
        for relation, local, owner_name, kind, method in rows:
            if kind != "r":
                tables.append([local, owner_name, kind, method, [], None])
                continue
            columns = db.execute(
                "SELECT a.attnum, a.attname, a.atttypid, NOT a.attnotnull, a.atthasdef "
                "FROM pg_catalog.pg_attribute a "
                f"WHERE a.attrelid={int(relation)} AND a.attnum>0 AND NOT a.attisdropped ORDER BY a.attnum",
            )
            heading = [[int(position), label, int(oid), nullable == "t", default == "t"]
                       for position, label, oid, nullable, default in columns]
            count = int(db.execute(f"SELECT count(*) FROM {qualified(namespace, local)}")[0][0])
            tables.append([local, owner_name, kind, method, heading, count])
    decoy = table_rows(db, qualified(DECOY, "items"), "id")
    return {"owner": owner, "tables": sorted(tables), "decoy": [row[0] for row in decoy]}


def typed(oid, value):
    if value is None:
        return None
    if oid in (20, 21, 23):
        return int(value)
    if oid == 16:
        return value == "t"
    require(oid == 25, "schema-tables-default-unexpected-storage", [oid, value])
    return value


def defaults(db, item, catalog):
    db.execute("BEGIN" if not item["active"] else "SAVEPOINT observe_schema_defaults")
    results = {}
    for local, _, kind, _, _, _ in catalog["tables"]:
        if kind != "r":
            continue
        source = qualified(item["namespace"], local)
        probe = db.run(f"INSERT INTO {source} DEFAULT VALUES RETURNING *", describe=True)
        require(probe["sqlState"] == "00000", "schema-tables-default-probe", probe)
        row, = probe["rows"]
        results[local] = [[oid, typed(oid, value)] for (_, oid), value in zip(probe["columns"], row)]
    db.execute("ROLLBACK" if not item["active"] else "ROLLBACK TO SAVEPOINT observe_schema_defaults")
    return results


def permitted(item, command):
    tables = item["members"]
    return command in {
        item.get("source") or sql(item), sql(item, canonical=True), sql(item, canonical=True, members=[]),
        sql(item, canonical=True, members=list(reversed(tables))), sql(item, canonical=True, schema=DECOY),
    }


def observe(db, request):
    item, command = request["trial"], request["sql"]
    if not permitted(item, command):
        return {"status": "outside-observer-scope"}
    prepare_state(db, item)
    before = snapshot(db, item)
    db.execute(f"SET SESSION AUTHORIZATION {quoted(SESSION)}; SET ROLE {quoted(EFFECTIVE)}")
    if item["active"]:
        db.execute("BEGIN")
    result = db.run(command, diagnostic_error=True)
    phase = db.transaction()
    during = snapshot(db, item) if result["sqlState"] == "00000" else None
    values = defaults(db, item, during) if during is not None else None
    if item["active"]:
        db.execute("ROLLBACK")
    db.execute("RESET ROLE; RESET SESSION AUTHORIZATION")
    final = snapshot(db, item) if item["active"] or during is None else during
    return {"status": STATUS, "transactionAfter": phase, "before": before, "during": during, "settled": final,
            "defaults": values, **{key: result[key] for key in ("command", "sqlState", "notices", "error")}}


def main():
    with Session() as db:
        settings = [db.execute("SHOW " + key)[0][0] for key in (
                    "server_version_num", "server_encoding", "client_encoding", "standard_conforming_strings", "search_path")]
        role_census = sorted(prepare_roles(db))
        print(json.dumps({"database": settings, "currentDatabase": CATALOG, "python": python_version(),
                          "libpq": LIB.PQlibVersion(), "roleCoordinates": role_census}, ensure_ascii=False),
              flush=True)
        for line in sys.stdin:
            request = json.loads(line)
            result = [observe(db, entry) for entry in request] if isinstance(request, list) else observe(db, request)
            print(json.dumps(result, ensure_ascii=False), flush=True)


if __name__ == "__main__":
    main()
