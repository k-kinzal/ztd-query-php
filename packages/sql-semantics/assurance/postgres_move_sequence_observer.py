"""Observe native sequence placement, dependencies and storage identities."""

from hashlib import sha256
import json
from platform import python_version
import sys

from postgres_create_sequence_observer import relation
from postgres_drop_sequence_observer import STOCK, consumer, setup
from postgres_drop_schema_observer import literal
from postgres_libpq import LIB, Session
from postgres_move_sequence_cases import DATABASE, DESTINATION_NAMES, OWNER_TABLE, STATUS, destination, owner_role, sql
from postgres_reindex_cases import qualified, quoted
from run import require

STOCK_SCOPES = ("pg_catalog", "information_schema")
NAMES = ("ticket", 'Counter. "棚"')


def prepare(db, item):
    db.execute("SET client_min_messages=warning")
    for scope in STOCK_SCOPES:
        names = ", ".join(qualified(scope, name) for name in NAMES)
        db.execute(f"DROP SEQUENCE IF EXISTS {names} CASCADE")
    for scope in DESTINATION_NAMES.values():
        name = quoted(scope)
        db.execute(f"DROP SCHEMA IF EXISTS {name} CASCADE; CREATE SCHEMA {name}")
    setup(db, item)
    if item["owned"]:
        role = owner_role(item)
        scope = item["namespaces"][role]
        owner = qualified(scope, OWNER_TABLE)
        moved = qualified(scope, item["name"])
        db.execute(f"CREATE TABLE {owner} (decoy int, anchor int); ALTER SEQUENCE {moved} OWNED BY {owner}.anchor")
    if item["occupied"]:
        table = qualified(destination(item), item["name"])
        db.execute(f"CREATE TABLE {table} (retained integer); INSERT INTO {table} VALUES (31)")


def ownership(db, oid):
    records = db.execute(
        "SELECT n.nspname,t.relname,a.attname,a.attnum,d.deptype FROM pg_catalog.pg_depend d "
        "JOIN pg_catalog.pg_class t ON t.oid=d.refobjid JOIN pg_catalog.pg_namespace n ON t.relnamespace=n.oid "
        "JOIN pg_catalog.pg_attribute a ON a.attrelid=t.oid AND a.attnum=d.refobjsubid "
        f"WHERE d.classid='pg_class'::regclass AND d.refclassid='pg_class'::regclass AND d.objid={oid} "
        "AND d.deptype IN ('i','a') AND d.refobjsubid>0"
    )
    require(len(records) <= 1, "sequence-move-native-ownership-cardinality", records)
    return [*records[0][:3], int(records[0][3]), records[0][4]] if records else None


def snapshot(db, item, original=None):
    scopes = {*item["namespaces"].values(), *DESTINATION_NAMES.values(), *STOCK_SCOPES}
    reverse = {scope: role for role, scope in item["namespaces"].items()}
    name = item["name"]
    observed, occupant = {}, None
    for scope in sorted(scopes):
        value = relation(db, scope, name)
        if value is None:
            continue
        if value["kind"] == "r" and scope == destination(item):
            occupant = value
            continue
        require(value["kind"] == "S", "sequence-move-native-relation-kind", [scope, value])
        oid = value["oid"]
        role = original.get(oid) if original is not None else reverse.get(scope)
        require(role is not None and role not in observed, "sequence-move-native-census", [scope, oid])
        file, = db.execute(f"SELECT pg_catalog.pg_relation_filenode({oid}::regclass)")[0]
        observed[role] = {"scope": scope, "name": name, "value": value, "file": int(file), "ownership": ownership(db, oid)}
    consumer_record = consumer(db, item)
    default_ids = db.execute(
        "SELECT d.refobjid FROM pg_catalog.pg_attrdef a "
        "JOIN pg_catalog.pg_depend d ON d.objid=a.oid AND d.classid='pg_attrdef'::regclass "
        "JOIN pg_catalog.pg_class c ON c.oid=d.refobjid AND c.relkind='S' "
        f"WHERE a.adrelid={consumer_record['consumerOid']} AND a.adnum=1 AND d.refclassid='pg_class'::regclass"
    )
    require(len(default_ids) == int(consumer_record["default"] is not None), "sequence-move-native-default-id", default_ids)
    return {**consumer_record, "defaultOid": int(default_ids[0][0]) if default_ids else None, "sequences": observed, "occupied": occupant}


def permitted(item, command):
    if item.get("nativeRefusal"):
        return command == item["command"]
    return command in {sql(item), sql(item, canonical=True),
                       *(sql(item, canonical=True, change=mode) for mode in ("decoy", "stock", "unguarded"))}


def observe(db, request):
    item, command = request["trial"], request["sql"]
    if not permitted(item, command):
        return {"status": "outside-observer-scope"}
    prepare(db, item)
    before = snapshot(db, item)
    coordinates = {detail["value"]["oid"]: role for role, detail in before["sequences"].items()}
    if item["active"]:
        db.execute("BEGIN")
    result = db.run(command, diagnostic_error=True)
    phase = db.transaction()
    during = snapshot(db, item, coordinates) if result["sqlState"] == "00000" else None
    if item["active"]:
        db.execute("ROLLBACK")
    settled = snapshot(db, item, coordinates)
    return {"status": STATUS, "transactionAfter": phase, "before": before, "during": during, "settled": settled,
            **{field: result[field] for field in ("command", "sqlState", "notices", "error")}}


def main():
    with Session() as db:
        settings = [db.execute("SHOW " + key)[0][0] for key in (
                    "server_version_num", "server_encoding", "client_encoding", "standard_conforming_strings", "search_path")]
        current = db.execute("SELECT current_database()")[0][0]
        require(current == DATABASE, "sequence-move-fixture-coordinate")
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
