"""Read the native sequence catalog, tuple, ownership and generated values."""

import json
from platform import python_version
import sys

from postgres_create_sequence_cases import DATABASE, DECOY, NAME, QUOTED, SCHEMA, STATUS, sql
from postgres_drop_schema_observer import literal
from postgres_libpq import LIB, Session
from postgres_reindex_cases import qualified, quoted
from run import require


def initialize(db, item):
    db.execute(f"SET client_min_messages=warning; DROP SCHEMA IF EXISTS {quoted(SCHEMA)}, {quoted(DECOY)} CASCADE; "
               f"CREATE SCHEMA {quoted(SCHEMA)}; CREATE SCHEMA {quoted(DECOY)}")
    for name in (NAME, QUOTED):
        if not db.execute("SELECT 1 FROM pg_catalog.pg_class WHERE relnamespace=pg_catalog.pg_my_temp_schema() "
                          f"AND relname={literal(name)}"):
            continue
        value = qualified("pg_temp", name)
        kind, = db.execute("SELECT relkind FROM pg_catalog.pg_class WHERE relnamespace=pg_catalog.pg_my_temp_schema() "
                           f"AND relname={literal(name)}")[0]
        db.execute(f"DROP {'SEQUENCE' if kind == 'S' else 'TABLE'} {value}")
    schema, name = item["target"]
    decoy = qualified(DECOY, name)
    db.execute(f"CREATE SEQUENCE {decoy} AS smallint INCREMENT 5 MINVALUE 50 MAXVALUE 70 START 61 RESTART 63")
    target = qualified(schema, name)
    mode = {"permanent": "", "unlogged": "UNLOGGED ", "temporary": "TEMPORARY ", "global": "TEMPORARY "}[item["persistence"]]
    if item["occupied"] == "sequence":
        db.execute(f"CREATE {mode}SEQUENCE {target} AS INTEGER INCREMENT -3 MINVALUE -40 MAXVALUE -12 "
                   "START -15 RESTART -17 CACHE 2")
    elif item["occupied"] == "table":
        db.execute(f"CREATE {mode}TABLE {target} (retained integer); INSERT INTO {target} VALUES (29)")
    db.execute("SET client_min_messages=notice")


def relation(db, schema, name):
    namespace = ("n.oid=pg_catalog.pg_my_temp_schema()" if schema == "pg_temp" else f"n.nspname={literal(schema)}")
    rows = db.execute("SELECT c.oid, c.relkind, c.relpersistence, pg_catalog.pg_get_userbyid(c.relowner) "
                      "FROM pg_catalog.pg_class c JOIN pg_catalog.pg_namespace n ON n.oid=c.relnamespace "
                      f"WHERE {namespace} AND c.relname={literal(name)}")
    if not rows:
        return None
    (identity, kind, mode, owner), = rows
    identity = int(identity)
    source = qualified(schema, name)
    result = {"oid": identity, "kind": kind, "persistence": mode, "owner": owner}
    if kind == "r":
        readable = db.run(f"SELECT retained FROM {source}", describe=True)
        require(readable["sqlState"] == "00000", "sequence-occupied-table-unreadable", readable)
        return {**result, "rows": [[int(cell) for cell in row] for row in readable["rows"]], "columns": readable["columns"]}
    require(kind == "S", "sequence-observer-object-kind", result)
    parameters, = db.execute(
        "SELECT seqtypid, seqincrement, seqmin, seqmax, seqstart, seqcache, seqcycle "
        f"FROM pg_catalog.pg_sequence WHERE seqrelid={identity}")
    numbers = {key: int(val) for key, val in zip(("storage", "increment", "minimum", "maximum", "start", "cache"), parameters[:-1])}
    ownership = db.execute(
        "SELECT refobjid, refobjsubid, deptype FROM pg_catalog.pg_depend "
        f"WHERE objid={identity} AND classid='pg_class'::regclass AND refclassid='pg_class'::regclass "
        "ORDER BY refobjid,refobjsubid,deptype")
    state = db.run(f"SELECT last_value, log_cnt, is_called FROM {source}", describe=True)
    require(state["sqlState"] == "00000", "sequence-observer-tuple-unreadable", state)
    (last, log, called), = state["rows"]
    return {**result, **numbers, "cycle": parameters[-1] == "t", "ownership": ownership,
            "tuple": [int(last), int(log), called == "t"], "columns": state["columns"]}


def snapshot(db, item):
    schema, name = item["target"]
    return {"selected": relation(db, schema, name), "decoy": relation(db, DECOY, name)}


def calls(db, item):
    source = qualified(*item["target"])
    gathered = []
    for _ in range(4):
        if item["active"]:
            db.execute("SAVEPOINT observe_next")
        result = db.run(f"SELECT pg_catalog.nextval({literal(source)}::regclass) AS sequence_value", describe=True)
        if item["active"]:
            if result["sqlState"] != "00000":
                db.execute("ROLLBACK TO SAVEPOINT observe_next")
            db.execute("RELEASE SAVEPOINT observe_next")
        rows = result["rows"]
        gathered.append({"sqlState": result["sqlState"], "columns": result.get("columns"),
                         "rows": [[int(row[0])] for row in rows] if rows is not None else None})
    return gathered


def permitted(item, command):
    if item.get("nativeRefusal"):
        return command == item["command"]
    return command in {item.get("command"), sql(item), sql(item, canonical=True),
                       *(sql(item, canonical=True, change=change) for change in ("reverse", "restart", "cycle"))}


def observe(db, request):
    item, command = request["trial"], request["sql"]
    if not permitted(item, command):
        return {"status": "outside-observer-scope"}
    initialize(db, item)
    before = snapshot(db, item)
    if item["active"]:
        db.execute("BEGIN")
    outcome = db.run(command, diagnostic_error=True)
    phase = db.transaction()
    during = snapshot(db, item) if outcome["sqlState"] == "00000" else None
    generated = calls(db, item) if during is not None and during["selected"] is not None and before["selected"] is None else []
    if item["active"]:
        db.execute("ROLLBACK")
    return {"status": STATUS, "before": before, "during": during, "rollback": snapshot(db, item) if item["active"] else None,
            "transactionAfter": phase, "calls": generated,
            **{key: outcome[key] for key in ("sqlState", "command", "notices", "error")}}


def main():
    with Session() as db:
        settings = [db.execute("SHOW " + key)[0][0] for key in (
                    "server_version_num", "server_encoding", "client_encoding", "standard_conforming_strings", "search_path")]
        # Use the same first declaration schema and decoy ordering as the analyzed request.
        db.execute(f"SET search_path={quoted(SCHEMA)}, {quoted(DECOY)}, public")
        database, owner = db.execute("SELECT current_database(), current_user")[0]
        require(database == DATABASE, "sequence-observer-database", database)
        print(json.dumps({"database": settings, "currentDatabase": database, "owner": owner, "python": python_version(),
                          "libpq": LIB.PQlibVersion()}, ensure_ascii=False), flush=True)
        for line in sys.stdin:
            request = json.loads(line)
            result = [observe(db, item) for item in request] if isinstance(request, list) else observe(db, request)
            print(json.dumps(result, ensure_ascii=False), flush=True)


if __name__ == "__main__":
    main()
