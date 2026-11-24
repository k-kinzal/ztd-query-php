"""Observe the PostgreSQL catalog, dependency and generator transition."""

from hashlib import sha256
import json
from platform import python_version
import sys

from postgres_create_sequence_observer import relation
from postgres_drop_sequence_observer import STOCK, consumer, setup
from postgres_drop_schema_observer import literal
from postgres_libpq import LIB, Session
from postgres_move_sequence_observer import ownership
from postgres_reconfigure_sequence_cases import DATABASE, OWNER_TABLE, STATUS, active_role, old_values, source, sql
from postgres_reindex_cases import qualified
from run import require


def prepare(db, item):
    setup(db, item)
    db.execute("SET client_min_messages=warning")
    db.execute(f"DROP TABLE IF EXISTS {qualified('pg_temp', OWNER_TABLE)}")
    for role, scope in item["namespaces"].items():
        table = qualified(scope, OWNER_TABLE)
        temporary = "TEMPORARY " if role == "temporary" else ""
        db.execute(f"CREATE {temporary}TABLE {table} (decoy int, anchor int)")
    selected = active_role(item)
    if item["descending"]:
        name = qualified(item["namespaces"]["local"], item["name"])
        db.execute(f"ALTER SEQUENCE {name} INCREMENT -1 MINVALUE -32768 MAXVALUE -1 START -4 RESTART -4")
    target = qualified(item["namespaces"][selected], item["name"])
    if item["oldOwner"]:
        table = qualified(item["namespaces"][selected], OWNER_TABLE)
        db.execute(f"ALTER SEQUENCE {target} OWNED BY {table}.decoy")
    if item["warm"]:
        last = old_values(item, selected)["tuple"][0]
        db.execute(f"SELECT pg_catalog.setval({literal(target)}::regclass, {last}, true)")
    db.execute("SET client_min_messages=notice")


def snapshot(db, item):
    observed = {}
    for role, scope in item["namespaces"].items():
        value = relation(db, scope, item["name"])
        oid = value["oid"]
        filenode, = db.execute(f"SELECT pg_catalog.pg_relation_filenode({oid}::regclass)")[0]
        observed[role] = {"value": value, "file": int(filenode), "ownership": ownership(db, oid)}
    temporary = db.execute("SELECT nspname, oid FROM pg_catalog.pg_namespace WHERE oid=pg_catalog.pg_my_temp_schema()")
    (name, oid), = temporary
    return {**consumer(db, item), "sequences": observed, "temporaryNamespace": [name, int(oid)]}


def permitted(item, command):
    if "nativeRefusal" in item:
        return command == item["command"]
    accepted = {sql(item), sql(item, canonical=True), sql(item, canonical=True, change="decoy"),
                sql(item, canonical=True, change="unguarded")}
    if "start" in item["changes"]:
        accepted.add(sql(item, canonical=True, change="restart-instead-of-start"))
    if "cycle" in item["changes"]:
        accepted.add(sql(item, canonical=True, change="drop-cycle"))
    return command in accepted


def next_values(db, target, active, count):
    calls = []
    for _ in range(count):
        if active:
            db.execute("SAVEPOINT next_value")
        result = db.run(f"SELECT pg_catalog.nextval({literal(target)}::regclass) AS sequence_value", describe=True)
        if active:
            if result["sqlState"] != "00000":
                db.execute("ROLLBACK TO SAVEPOINT next_value")
            db.execute("RELEASE SAVEPOINT next_value")
        rows = result.get("rows")
        calls.append({"sqlState": result["sqlState"], "columns": result.get("columns"),
                      "rows": [[int(cell)] for (cell,) in rows] if rows is not None else None})
    return calls


def observe(db, request):
    item, command = request["trial"], request["sql"]
    if not permitted(item, command):
        return {"status": "outside-observer-scope"}
    prepare(db, item)
    before = snapshot(db, item)
    if item["active"]:
        db.execute("BEGIN")
    result = db.run(command, diagnostic_error=True)
    phase = db.transaction()
    role = active_role(item)
    target = qualified(item["namespaces"][role], item["name"])
    during = snapshot(db, item) if result["sqlState"] == "00000" else None
    rewritten = during is not None and during["sequences"][role]["file"] != before["sequences"][role]["file"]
    currval, calls = None, []
    if during is not None and source(item)[2] == "existing":
        if item["warm"]:
            current, = db.execute(f"SELECT pg_catalog.currval({literal(target)}::regclass)")[0]
            currval = int(current)
        if n := item.get("probeValues"):
            require(not item["active"] or rewritten, "sequence-reconfigure-probe-requires-rewrite")
            calls = next_values(db, target, item["active"], n)
    if item["active"]:
        db.execute("ROLLBACK")
    settled = during if during is not None and not item["active"] else snapshot(db, item)
    return {"status": STATUS, "transactionAfter": phase, "before": before, "during": during, "settled": settled,
            "calls": calls, "currval": currval, "rewritten": rewritten,
            **{field: result[field] for field in ("command", "sqlState", "notices", "error")}}


def main():
    with Session() as db:
        settings = [db.execute("SHOW " + key)[0][0] for key in (
                    "server_version_num", "server_encoding", "client_encoding", "standard_conforming_strings", "search_path")]
        current = db.execute("SELECT current_database()")[0][0]
        require(current == DATABASE, "sequence-reconfigure-fixture-coordinate")
        stock = db.execute(STOCK)
        stock_digest = sha256(json.dumps(stock, separators=(",", ":"), ensure_ascii=False).encode()).hexdigest()
        print(json.dumps({"database": settings, "python": python_version(), "libpq": LIB.PQlibVersion(),
                          "currentDatabase": current, "stockDigest": stock_digest, "stockCount": len(stock)}), flush=True)
        for line in sys.stdin:
            request = json.loads(line)
            result = [observe(db, entry) for entry in request] if isinstance(request, list) else observe(db, request)
            print(json.dumps(result, ensure_ascii=False), flush=True)


if __name__ == "__main__":
    main()
