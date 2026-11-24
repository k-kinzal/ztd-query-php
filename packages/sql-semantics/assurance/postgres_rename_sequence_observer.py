"""Observe native sequence names, storage identity, dependencies, and rollback."""

from hashlib import sha256
import json
from platform import python_version
import sys

from postgres_create_sequence_observer import relation
from postgres_drop_sequence_observer import STOCK, consumer, setup
from postgres_drop_schema_observer import literal
from postgres_libpq import LIB, Session
from postgres_reindex_cases import qualified
from postgres_rename_sequence_cases import DATABASE, STATUS, occupied_scope, sql
from run import require


def prepare(db, item):
    db.execute("SET client_min_messages=warning")
    for name in ("renamed", 'Next. "棚"'):
        roles = db.execute("SELECT relkind FROM pg_catalog.pg_class WHERE relnamespace=pg_catalog.pg_my_temp_schema() "
                           f"AND relname={literal(name)}")
        if roles:
            kind = "SEQUENCE" if roles[0][0] == "S" else "TABLE"
            db.execute(f"DROP {kind} {qualified('pg_temp', name)}")
    setup(db, item)
    target = qualified(occupied_scope(item), item["destination"])
    if item["occupied"]:
        temporary = "TEMPORARY " if occupied_scope(item) == "pg_temp" else ""
        db.execute(f"CREATE {temporary}TABLE {target} (retained integer); INSERT INTO {target} VALUES (31)")


def snapshot(db, item):
    observed = {}
    for role, schema in item["namespaces"].items():
        aliases = {name: relation(db, schema, name) for name in (item["name"], item["destination"])}
        names = [name for name, value in aliases.items() if value is not None and value["kind"] == "S"]
        require(len(names) == 1, "sequence-rename-native-census", [aliases, role])
        name, = names
        value = aliases[name]
        file, = db.execute(f"SELECT pg_catalog.pg_relation_filenode({value['oid']}::regclass)")[0]
        observed[role] = {"name": name, "value": value, "file": int(file)}
    occupant = relation(db, occupied_scope(item), item["destination"])
    table = occupant if occupant is not None and occupant["kind"] == "r" else None
    return {**consumer(db, item), "sequences": observed, "occupied": table}


def permitted(item, command):
    if item.get("nativeRefusal"):
        return command == item["command"]
    return command in {sql(item), sql(item, canonical=True),
                       *(sql(item, canonical=True, change=kind) for kind in ("decoy", "same-name", "unguarded"))}


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
    during = snapshot(db, item) if result["sqlState"] == "00000" else None
    if item["active"]:
        db.execute("ROLLBACK")
    settled = snapshot(db, item)
    return {"status": STATUS, "transactionAfter": phase, "before": before, "during": during, "settled": settled,
            **{field: result[field] for field in ("command", "sqlState", "notices", "error")}}


def main():
    with Session() as db:
        settings = [db.execute("SHOW " + key)[0][0] for key in (
                    "server_version_num", "server_encoding", "client_encoding", "standard_conforming_strings", "search_path")]
        current = db.execute("SELECT current_database()")[0][0]
        require(current == DATABASE, "sequence-rename-fixture-coordinate")
        stock = db.execute(STOCK)
        stock_digest = sha256(json.dumps(stock, separators=(",", ":"), ensure_ascii=False).encode()).hexdigest()
        print(json.dumps({"database": settings, "python": python_version(), "libpq": LIB.PQlibVersion(),
                          "currentDatabase": current, "stockDigest": stock_digest,
                          "stockCount": len(stock)}, ensure_ascii=False), flush=True)
        for line in sys.stdin:
            request = json.loads(line)
            result = [observe(db, entry) for entry in request] if isinstance(request, list) else observe(db, request)
            print(json.dumps(result, ensure_ascii=False), flush=True)


if __name__ == "__main__":
    main()
