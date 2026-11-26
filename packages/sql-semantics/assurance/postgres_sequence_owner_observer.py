"""Read actual automatic dependency coordinates and PostgreSQL's deletion effects."""

import json
from platform import python_version
import sys

from postgres_create_sequence_cases import DATABASE, DECOY
from postgres_create_sequence_observer import calls, initialize, relation, snapshot as generator_snapshot
from postgres_drop_schema_observer import literal
from postgres_libpq import LIB, Session
from postgres_reindex_cases import qualified, quoted
from postgres_sequence_owner_cases import ALTERNATE, FDW, OTHER_OWNER, SERVER, STATUS, relations, sql
from run import require


def setup(db, item):
    initialize(db, item)
    db.execute("SET client_min_messages=warning")
    for name in ("owner", 'Owner."棚"', ALTERNATE):
        db.execute(f"DROP TABLE IF EXISTS {qualified('pg_temp', name)}")
    for ddl in [*relations(item), *item.get("extraRelations", ())]:
        db.execute(ddl)
    if item.get("roleMismatch"):
        schema, name, _, _ = item["ownership"]
        db.execute(f"ALTER TABLE {qualified(schema, name)} OWNER TO {quoted(OTHER_OWNER)}")
    db.execute(f"SET search_path={quoted(item['target'][0])}, {quoted(DECOY)}, public; SET client_min_messages=notice")


def sequence_present(db, schema, name):
    return relation(db, schema, name) is not None


def follow_dependencies(db, snapshot):
    value = snapshot["selected"]
    if value is None or value["kind"] != "S":
        return snapshot
    dependencies = db.execute(
        "SELECT d.refobjid, d.refobjsubid, d.deptype, "
        "CASE WHEN c.relnamespace=pg_catalog.pg_my_temp_schema() THEN 'pg_temp' ELSE n.nspname::text END, "
        "c.relname, a.attname, c.relkind FROM pg_catalog.pg_depend d "
        "JOIN pg_catalog.pg_class c ON c.oid=d.refobjid "
        "JOIN pg_catalog.pg_namespace n ON n.oid=c.relnamespace "
        "JOIN pg_catalog.pg_attribute a ON a.attrelid=c.oid AND a.attnum=d.refobjsubid "
        f"WHERE d.objid={value['oid']} AND d.classid='pg_class'::regclass AND d.refclassid='pg_class'::regclass "
        "ORDER BY d.refobjid,d.refobjsubid,d.deptype")
    require([row[:3] for row in dependencies] == value["ownership"], "sequence-native-owner-dependency-shape", dependencies)
    value["ownership"] = [[scope, name, field, kind, int(sub), dep] for _, sub, dep, scope, name, field, kind in dependencies]
    return snapshot


def snapshot(db, item):
    return follow_dependencies(db, generator_snapshot(db, item))


def drop_commands(item):
    schema, owner, column, kind = item["ownership"]
    name = qualified(schema, owner)
    if kind == "view":
        return f"DROP TABLE {qualified(schema, ALTERNATE)}", f"DROP VIEW {name}"
    table = "FOREIGN TABLE" if kind == "foreign" else "TABLE"
    other = next(field for field in item["ownerColumns"] if field != column)
    leave = f"ALTER {table} {name} DROP COLUMN {quoted(other)}"
    remove = (f"DROP {table} {name}" if item["columnRole"] == "system"
              else f"ALTER {table} {name} DROP COLUMN {quoted(column)}")
    return leave, remove


def observe_dependency_deletion(db, item):
    target = item["target"]
    decoy = [DECOY, target[1]]
    begin = "SAVEPOINT observe_owned" if item["active"] else "BEGIN"
    end = "ROLLBACK TO SAVEPOINT observe_owned" if item["active"] else "ROLLBACK"
    db.execute(begin)
    for_other, for_owner = drop_commands(item)
    db.execute(for_other)
    after_other, decoy_after_other = sequence_present(db, *target), sequence_present(db, *decoy)
    db.execute(for_owner)
    after_owner, decoy_after_owner = sequence_present(db, *target), sequence_present(db, *decoy)
    db.execute(end)
    if item["active"]:
        db.execute("RELEASE SAVEPOINT observe_owned")
    return {"afterOther": after_other, "afterOwner": after_owner, "afterRollback": sequence_present(db, *target),
            "decoyNeverRemoved": decoy_after_other and decoy_after_owner and sequence_present(db, *decoy)}


def permitted(item, command):
    if item.get("inputRefusal"):
        return command == item["command"]
    return command in {sql(item), sql(item, canonical=True),
                       *(sql(item, canonical=True, change=change) for change in ("omit", "wrong-column", "wrong-relation"))}


def observe(db, request):
    item, command = request["trial"], request["sql"]
    if not permitted(item, command):
        return {"status": "outside-observer-scope"}
    setup(db, item)
    before = snapshot(db, item)
    if item["active"]:
        db.execute("BEGIN")
    output = db.run(command, diagnostic_error=True)
    phase = db.transaction()
    during = snapshot(db, item) if output["sqlState"] == "00000" else None
    generated, removed = [], None
    if during is not None and item["occupied"] is None and during["selected"] is not None:
        removed = observe_dependency_deletion(db, item)
        generated = calls(db, item)
    if item["active"]:
        db.execute("ROLLBACK")
    return {"status": STATUS, "before": before, "during": during, "rollback": snapshot(db, item) if item["active"] else None,
            "transactionAfter": phase, "calls": generated, "removed": removed,
            **{name: output[name] for name in ("sqlState", "command", "notices", "error")}}


def main():
    with Session() as db:
        settings = [db.execute("SHOW " + key)[0][0] for key in (
                    "server_version_num", "server_encoding", "client_encoding", "standard_conforming_strings", "search_path")]
        database, owner = db.execute("SELECT current_database(),current_user")[0]
        require([database, owner] == [DATABASE, "postgres"], "sequence-owner-fixture-authority")
        db.execute(f"CREATE FOREIGN DATA WRAPPER {FDW}; CREATE SERVER {SERVER} FOREIGN DATA WRAPPER {FDW}")
        exists = db.execute(f"SELECT rolname FROM pg_catalog.pg_roles WHERE rolname={literal(OTHER_OWNER)}")
        if not exists:
            db.execute(f"CREATE ROLE {quoted(OTHER_OWNER)}")
        installed = db.execute("SELECT fdwname, fdwhandler=0 FROM pg_catalog.pg_foreign_data_wrapper "
                               f"WHERE fdwname={literal(FDW)}")
        require(installed == [[FDW, "t"]], "sequence-owner-fixture-fdw", installed)
        print(json.dumps({"database": settings, "currentDatabase": database, "python": python_version(),
                          "libpq": LIB.PQlibVersion(), "fixtureWrapper": installed, "checkedOwner": owner}, ensure_ascii=False), flush=True)
        for line in sys.stdin:
            request = json.loads(line)
            result = [observe(db, entry) for entry in request] if isinstance(request, list) else observe(db, request)
            print(json.dumps(result, ensure_ascii=False), flush=True)


if __name__ == "__main__":
    main()
