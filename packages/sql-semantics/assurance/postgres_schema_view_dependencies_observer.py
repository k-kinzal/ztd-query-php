"""Observe native PostgreSQL view dependencies and dependent row bags."""

import json
from platform import python_version
import sys

from postgres_create_schema_cases import CATALOG, EFFECTIVE, SESSION
from postgres_create_schema_observer import prepare_roles
from postgres_drop_schema_observer import literal
from postgres_libpq import LIB, Session
from postgres_reindex_cases import qualified, quoted
from postgres_schema_view_dependencies_cases import EXTERNAL, STATUS, base_table, coordinate, data, present_table, sql, view_sql
from postgres_schema_views_observer import data_sql, read, table_state, view_state
from run import require


def initialize(db, item):
    db.execute("RESET ROLE; RESET SESSION AUTHORIZATION; SET client_min_messages=warning")
    local, external = map(quoted, (item["namespace"], EXTERNAL))
    db.execute(f"DROP SCHEMA IF EXISTS {local}, {external} CASCADE; CREATE SCHEMA {external} AUTHORIZATION {quoted(EFFECTIVE)}")
    if item["standalone"]:
        db.execute(f"CREATE SCHEMA {local} AUTHORIZATION {quoted(EFFECTIVE)}")
    db.execute(f"SET ROLE {quoted(EFFECTIVE)}")
    db.execute(view_sql(item, -1, False))
    db.execute(f"GRANT USAGE ON SCHEMA {external} TO PUBLIC; GRANT SELECT ON TABLE {qualified(EXTERNAL, 'provider')} TO PUBLIC")
    db.execute(f"RESET ROLE; SET search_path={external}, public; SET client_min_messages=notice")


def relation_state(db, schema, name, oid, owner, options, read_data):
    state = view_state(db, oid, owner, sorted(json.loads(options)))
    if read_data:
        header, fetched = read(db, schema, name)
        state.update(heading=header, rows=fetched)
    return state


def snapshot(db, item):
    schema, external = item["namespace"], EXTERNAL
    coords = ", ".join(map(literal, (schema, external)))
    namespaces = {name: [int(oid), owner] for name, oid, owner in db.execute(
        "SELECT nspname,oid,pg_catalog.pg_get_userbyid(nspowner) FROM pg_catalog.pg_namespace "
        f"WHERE nspname IN ({coords})"
    )}
    objects = db.execute(
        "SELECT n.nspname,c.relname,c.oid,c.relkind,pg_catalog.pg_get_userbyid(c.relowner),m.amname,"
        "array_to_json(COALESCE(c.reloptions,ARRAY[]::text[]))::text FROM pg_catalog.pg_class c "
        "JOIN pg_catalog.pg_namespace n ON n.oid=c.relnamespace LEFT JOIN pg_catalog.pg_am m ON m.oid=c.relam "
        f"WHERE n.nspname IN ({coords})"
    )
    result = {"owner": namespaces.get(schema, [None, None])[1], "namespaces": namespaces, "relationOids": {},
              "table": None, "views": {}, "external": None}
    for scope, name, identity, kind, owner, access, options in objects:
        oid = int(identity)
        result["relationOids"].setdefault(scope, {})[name] = oid
        if scope == external:
            require(name == "provider" and kind == "v", "schema-view-dependency-foreign-relation", objects)
            result["external"] = relation_state(db, scope, name, oid, owner, options, True)
        elif kind == "r" and name == "feed_data":
            result["table"] = table_state(db, scope, name, oid, owner, access)
        elif kind == "v":
            result["views"][name] = relation_state(db, scope, name, oid, owner, options, False)
        else:
            require(False, "schema-view-dependency-unknown-relation", objects)
    require(result["external"] is not None, "schema-view-dependency-decoy-missing")
    return result


def probe(db, item, phase):
    schema = item["namespace"]
    active = item["active"]
    db.execute("SAVEPOINT dependent_rows" if active else "BEGIN")
    if present_table(item) and phase["table"] is not None:
        target = qualified(*base_table(item)["target"])
        values = ["(" + ", ".join(map(data_sql, row[:-1])) + ")" for row in data(item)]
        db.execute(f"INSERT INTO {target} VALUES " + ", ".join(values))
    for name, seen in phase["views"].items():
        heading, rows = read(db, schema, name)
        seen.update(heading=heading, rows=rows)
    db.execute("ROLLBACK TO SAVEPOINT dependent_rows; RELEASE SAVEPOINT dependent_rows" if active else "ROLLBACK")


def permitted(item, statement):
    if item.get("nativeRefusal"):
        return statement == item["command"]
    return statement in {sql(item), sql(item, canonical=True),
                         *(sql(item, canonical=True, change=mode) for mode in ("reverse", "decoy", "field"))}


def observe(db, request):
    item, statement = request["trial"], request["sql"]
    if not permitted(item, statement):
        return {"status": "outside-observer-scope"}
    initialize(db, item)
    before = snapshot(db, item)
    db.execute(f"SET SESSION AUTHORIZATION {quoted(SESSION)}; SET ROLE {quoted(EFFECTIVE)}")
    if item["active"]:
        db.execute("BEGIN")
    result = db.run(statement, diagnostic_error=True)
    phase = db.transaction()
    during = snapshot(db, item) if result["sqlState"] == "00000" else None
    if during is not None:
        probe(db, item, during)
    if item["active"]:
        db.execute("ROLLBACK")
    db.execute("RESET ROLE; RESET SESSION AUTHORIZATION")
    settled = snapshot(db, item)
    if not item["active"] and during is not None:
        for name, state in settled["views"].items():
            state.update({key: during["views"][name][key] for key in ("heading", "rows")})
    return {"status": STATUS, "transactionAfter": phase, "before": before, "during": during, "settled": settled,
            **{field: result[field] for field in ("command", "sqlState", "notices", "error")}}


def main():
    with Session() as db:
        settings = [db.execute("SHOW " + key)[0][0] for key in (
                    "server_version_num", "server_encoding", "client_encoding", "standard_conforming_strings", "search_path")]
        database = db.execute("SELECT current_database()")[0][0]
        require(database == CATALOG, "postgres-schema-view-dependencies-disposable-profile")
        roles = sorted(prepare_roles(db))
        print(json.dumps({"database": settings, "currentDatabase": database, "python": python_version(),
                          "libpq": LIB.PQlibVersion(), "roleCoordinates": roles}, ensure_ascii=False), flush=True)
        for line in sys.stdin:
            request = json.loads(line)
            measurements = [observe(db, entry) for entry in request] if isinstance(request, list) else observe(db, request)
            print(json.dumps(measurements, ensure_ascii=False), flush=True)


if __name__ == "__main__":
    main()
