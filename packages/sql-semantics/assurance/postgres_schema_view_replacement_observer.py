"""Observe installed physical view headings, dependents and rows from libpq."""

import json
from platform import python_version
import sys

from postgres_create_schema_cases import CATALOG, EFFECTIVE, SESSION
from postgres_create_schema_observer import prepare_roles
from postgres_drop_schema_observer import literal
from postgres_libpq import LIB, Session
from postgres_reindex_cases import quoted
from postgres_schema_view_replacement_cases import STATUS, sql
from postgres_schema_views_observer import read, view_state
from run import require


def snapshot(db, item):
    schema = item["namespace"]
    namespace = db.execute("SELECT oid,pg_catalog.pg_get_userbyid(nspowner) FROM pg_catalog.pg_namespace "
                           f"WHERE nspname={literal(schema)}")
    result = {"owner": None, "namespaceOid": None, "relationOids": {}, "views": {}}
    if not namespace:
        return result
    (oid, owner), = namespace
    result.update(owner=owner, namespaceOid=int(oid))
    rows = db.execute(
        "SELECT c.relname,c.oid,c.relkind,pg_catalog.pg_get_userbyid(c.relowner),"
        "array_to_json(COALESCE(c.reloptions,ARRAY[]::text[]))::text FROM pg_catalog.pg_class c "
        f"WHERE c.relnamespace={int(oid)}"
    )
    for name, identity, kind, principal, options in rows:
        require(kind == "v", "replacement-unexpected-relation", rows)
        observed = view_state(db, int(identity), principal, sorted(json.loads(options)))
        fields = db.execute(
            "SELECT a.attnum,a.attname,a.atttypid,a.atttypmod,a.attcollation,c.collname,n.nspname "
            "FROM pg_catalog.pg_attribute a LEFT JOIN pg_catalog.pg_collation c ON c.oid=a.attcollation "
            "LEFT JOIN pg_catalog.pg_namespace n ON n.oid=c.collnamespace "
            f"WHERE a.attrelid={int(identity)} AND a.attnum>0 AND NOT a.attisdropped ORDER BY a.attnum"
        )
        observed["attributes"] = [[int(slot), label, int(type_oid), int(modifier), collation]
                                  for slot, label, type_oid, modifier, _oid, collation, _schema in fields]
        observed["physicalCollations"] = [[int(slot), int(collation), label, namespace]
                                          for slot, _label, _kind, _modifier, collation, label, namespace in fields]
        heading, values = read(db, schema, name)
        observed.update(heading=heading, rows=values)
        result["relationOids"][name] = int(identity)
        result["views"][name] = observed
    return result


def permitted(item, statement):
    if item.get("nativeRefusal"):
        return statement == item["command"]
    changes = ("omit", "guard")
    if item["layout"] != "append-boolean":
        changes += ("old-value",)
    else:
        changes += ("append",)
    return statement in {sql(item), sql(item, canonical=True), *(sql(item, canonical=True, change=mode) for mode in changes)}


def observe(db, request):
    item, statement = request["trial"], request["sql"]
    if not permitted(item, statement):
        return {"status": "outside-observer-scope"}
    db.execute("RESET ROLE; RESET SESSION AUTHORIZATION; SET client_min_messages=warning")
    db.execute(f"DROP SCHEMA IF EXISTS {quoted(item['namespace'])} CASCADE")
    db.execute("SET search_path=pg_catalog, public; SET client_min_messages=notice")
    before = snapshot(db, item)
    db.execute(f"SET SESSION AUTHORIZATION {quoted(SESSION)}; SET ROLE {quoted(EFFECTIVE)}")
    if item["active"]:
        db.execute("BEGIN")
    command = db.run(statement, diagnostic_error=True)
    phase = db.transaction()
    during = snapshot(db, item) if command["sqlState"] == "00000" else None
    if item["active"]:
        db.execute("ROLLBACK")
    db.execute("RESET ROLE; RESET SESSION AUTHORIZATION")
    settled = snapshot(db, item)
    return {"status": STATUS, "transactionAfter": phase, "before": before, "during": during, "settled": settled,
            **{key: command[key] for key in ("sqlState", "command", "notices", "error")}}


def main():
    with Session() as db:
        settings = [db.execute("SHOW " + key)[0][0] for key in (
                    "server_version_num", "server_encoding", "client_encoding", "standard_conforming_strings", "search_path")]
        database = db.execute("SELECT current_database()")[0][0]
        require(database == CATALOG, "postgres-schema-view-replacement-disposable-profile")
        roles = sorted(prepare_roles(db))
        print(json.dumps({"database": settings, "currentDatabase": database, "python": python_version(),
                          "libpq": LIB.PQlibVersion(), "roleCoordinates": roles}, ensure_ascii=False), flush=True)
        for line in sys.stdin:
            value = json.loads(line)
            measurements = [observe(db, entry) for entry in value] if isinstance(value, list) else observe(db, value)
            print(json.dumps(measurements, ensure_ascii=False), flush=True)


if __name__ == "__main__":
    main()
