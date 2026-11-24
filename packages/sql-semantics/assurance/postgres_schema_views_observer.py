"""Observe PostgreSQL schema view dependencies, metadata and rows."""

import json
from platform import python_version
import sys

from postgres_create_schema_cases import CATALOG, EFFECTIVE, SESSION
from postgres_create_schema_observer import prepare_roles
from postgres_create_sequence_observer import relation
from postgres_drop_schema_observer import literal
from postgres_libpq import LIB, Session
from postgres_reindex_cases import qualified, quoted
from postgres_schema_tables_cases import table, table_sql
from postgres_schema_views_cases import EXTERNAL, FIELDS, STATUS, members, rows, sql
from run import require


def data_sql(value):
    if value is None:
        return "NULL"
    if type(value) is bool:
        return "TRUE" if value else "FALSE"
    if type(value) is int:
        return str(value)
    require(type(value) is str, "schema-view-fixture-value", value)
    return literal(value)


def populate(db, schema, item, external):
    tuples = ["(" + ", ".join(map(data_sql, row)) + ")" for row in rows(item, external)]
    db.execute(f"INSERT INTO {qualified(schema, item['relation'])} VALUES " + ", ".join(tuples))


def initialize(db, item):
    db.execute("RESET ROLE; RESET SESSION AUTHORIZATION; SET client_min_messages=warning")
    db.execute(f"DROP SCHEMA IF EXISTS {quoted(EXTERNAL)}, {quoted(item['namespace'])} CASCADE")
    db.execute(f"CREATE SCHEMA {quoted(EXTERNAL)}")
    description = table(item["release"], (EXTERNAL, item["relation"]),
                        [[name, kind, True, None] for name, (kind, *_rest) in zip(item["columns"], FIELDS)], False)
    db.execute(table_sql(description, True, False))
    populate(db, EXTERNAL, item, True)
    db.execute(f"GRANT USAGE ON SCHEMA {quoted(EXTERNAL)} TO PUBLIC; GRANT SELECT ON TABLE {qualified(EXTERNAL, item['relation'])} TO PUBLIC")
    db.execute(f"SET search_path={quoted(EXTERNAL)}, public; SET client_min_messages=notice")


def convert(oid, value):
    if value is None or oid == 25:
        return value
    if oid == 16:
        require(value in ("f", "t"), "schema-view-boolean-observation", value)
        return value == "t"
    require(oid in (20, 23), "schema-view-column-type", oid)
    return int(value)


def read(db, schema, name):
    answer = db.run(f"SELECT * FROM {qualified(schema, name)}", describe=True)
    require(answer["sqlState"] == "00000", "schema-view-unreadable", answer)
    heading = answer["columns"]
    require(all(len(row) == len(heading) for row in answer["rows"]), "schema-view-row-width", answer)
    values = [[convert(oid, value) for (_, oid), value in zip(heading, row)] for row in answer["rows"]]
    return heading, sorted(values, key=json.dumps)


def table_state(db, schema, name, oid, owner, access):
    attributes = db.execute(
        "SELECT attnum,attname,atttypid,attnotnull,atthasdef FROM pg_catalog.pg_attribute "
        f"WHERE attrelid={oid} AND attnum>0 AND NOT attisdropped ORDER BY attnum"
    )
    fields = [[int(slot), name, int(kind), present != "t", default == "t"]
              for slot, name, kind, present, default in attributes]
    (count,), = db.execute(f"SELECT count(*) FROM {qualified(schema, name)}")
    return [name, owner, "r", access, fields, int(count)]


def view_state(db, oid, owner, opts):
    edges = db.execute(
        "SELECT n.nspname,c.relname,a.attname,p.refobjsubid,p.deptype,c.oid "
        "FROM pg_catalog.pg_rewrite r JOIN pg_catalog.pg_depend p ON p.objid=r.oid "
        "AND p.classid='pg_rewrite'::regclass AND p.refclassid='pg_class'::regclass "
        "JOIN pg_catalog.pg_class c ON c.oid=p.refobjid JOIN pg_catalog.pg_namespace n ON n.oid=c.relnamespace "
        "LEFT JOIN pg_catalog.pg_attribute a ON a.attrelid=c.oid AND a.attnum=p.refobjsubid "
        f"WHERE r.ev_class={oid} AND r.rulename='_RETURN'"
    )
    return {"owner": owner, "options": opts,
            "physicalDependencies": [[scope, name, col, int(slot), kind, int(target)] for scope, name, col, slot, kind, target in edges],
            "dependencies": sorted(([scope, name, col, int(slot), kind] for scope, name, col, slot, kind, _ in edges), key=json.dumps)}


def snapshot(db, item):
    name = item["namespace"]
    namespace = db.execute("SELECT oid,pg_catalog.pg_get_userbyid(nspowner) FROM pg_catalog.pg_namespace " f"WHERE nspname={literal(name)}")
    external, = db.execute(
        "SELECT c.oid FROM pg_catalog.pg_class c JOIN pg_catalog.pg_namespace n ON n.oid=c.relnamespace "
        f"WHERE n.nspname={literal(EXTERNAL)} AND c.relname={literal(item['relation'])}"
    )
    _, values = read(db, EXTERNAL, item["relation"])
    details = {"owner": None, "namespaceOid": None, "relationOids": {}, "table": None, "sequences": {}, "views": {},
               "external": values, "externalOid": int(external[0])}
    if not namespace:
        return details
    (oid, owner), = namespace
    details.update(owner=owner, namespaceOid=int(oid))
    records = db.execute(
        "SELECT c.relname,c.oid,c.relkind,pg_catalog.pg_get_userbyid(c.relowner),am.amname,"
        "array_to_json(COALESCE(c.reloptions,ARRAY[]::text[]))::text "
        "FROM pg_catalog.pg_class c LEFT JOIN pg_catalog.pg_am am ON am.oid=c.relam "
        f"WHERE c.relnamespace={int(oid)}"
    )
    for local, ident, kind, principal, access, options in records:
        details["relationOids"][local] = int(ident)
        if kind == "r":
            require(details["table"] is None, "schema-view-unexpected-table", records)
            details["table"] = table_state(db, name, local, int(ident), principal, access)
        elif kind == "S":
            sequence = relation(db, name, local)
            details["sequences"][local] = {field: value for field, value in sequence.items() if field != "oid"}
        elif kind == "v":
            details["views"][local] = view_state(db, int(ident), principal, sorted(json.loads(options)))
        else:
            require(False, "schema-view-unknown-member-kind", records)
    return details


def probe_views(db, item, phase):
    active = item["active"]
    db.execute("SAVEPOINT view_rows" if active else "BEGIN")
    if members(item) and phase["table"] is not None:
        populate(db, item["namespace"], item, False)
    for name, value in phase["views"].items():
        heading, observed = read(db, item["namespace"], name)
        value.update(heading=heading, rows=observed)
    if active:
        db.execute("ROLLBACK TO SAVEPOINT view_rows; RELEASE SAVEPOINT view_rows")
    else:
        db.execute("ROLLBACK")


def permitted(item, command):
    if item.get("nativeRefusal"):
        return command == item["command"]
    return command in {sql(item), sql(item, canonical=True),
                       *(sql(item, canonical=True, change=mode) for mode in ("omit-view-phase", "decoy", "omit-heading"))}


def observe(db, request):
    item, command = request["trial"], request["sql"]
    if not permitted(item, command):
        return {"status": "outside-observer-scope"}
    initialize(db, item)
    before = snapshot(db, item)
    db.execute(f"SET SESSION AUTHORIZATION {quoted(SESSION)}; SET ROLE {quoted(EFFECTIVE)}")
    if item["active"]:
        db.execute("BEGIN")
    result = db.run(command, diagnostic_error=True)
    phase = db.transaction()
    during = snapshot(db, item) if result["sqlState"] == "00000" else None
    if during is not None:
        probe_views(db, item, during)
    if item["active"]:
        db.execute("ROLLBACK")
    db.execute("RESET ROLE; RESET SESSION AUTHORIZATION")
    settled = snapshot(db, item) if item["active"] or during is None else during
    return {"status": STATUS, "transactionAfter": phase, "before": before, "during": during, "settled": settled,
            **{field: result[field] for field in ("command", "sqlState", "notices", "error")}}


def main():
    with Session() as db:
        settings = [db.execute("SHOW " + key)[0][0] for key in (
                    "server_version_num", "server_encoding", "client_encoding", "standard_conforming_strings", "search_path")]
        database = db.execute("SELECT current_database()")[0][0]
        require(database == CATALOG, "postgres-schema-views-disposable-profile")
        roles = sorted(prepare_roles(db))
        print(json.dumps({"database": settings, "currentDatabase": database, "python": python_version(),
                          "libpq": LIB.PQlibVersion(), "roleCoordinates": roles}, ensure_ascii=False), flush=True)
        for line in sys.stdin:
            request = json.loads(line)
            measurements = [observe(db, entry) for entry in request] if isinstance(request, list) else observe(db, request)
            print(json.dumps(measurements, ensure_ascii=False), flush=True)


if __name__ == "__main__":
    main()
