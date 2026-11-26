"""Observe native DROP SEQUENCE selectors, generator identity and dependent defaults."""

from hashlib import sha256
import json
from platform import python_version
import sys

from postgres_create_sequence_observer import relation
from postgres_drop_schema_observer import literal
from postgres_drop_sequence_cases import DATABASE, STATUS, TABLE, fixture_sql, sql
from postgres_libpq import LIB, Session
from postgres_reindex_cases import qualified, quoted
from run import require

STOCK = (
    "SELECT n.nspname,c.relname,c.relkind FROM pg_catalog.pg_class c JOIN pg_catalog.pg_namespace n ON n.oid=c.relnamespace "
    "WHERE n.nspname IN ('pg_catalog','information_schema') "
    'ORDER BY n.nspname COLLATE "C", c.relname COLLATE "C"'
)


def setup(db, item):
    db.execute("SET client_min_messages=warning")
    db.execute("DROP SEQUENCE IF EXISTS pg_temp.ticket, pg_temp." + quoted('Counter. "棚"'))
    for statement in [*fixture_sql(item), *item.get("extraFixture", ())]:
        db.execute(statement)
    path = [item["namespaces"]["local"], item["namespaces"]["other"], "public"]
    db.execute("SET search_path=" + ", ".join(quoted(name) for name in path) + "; SET client_min_messages=notice")


def consumer(db, item):
    namespace = item["namespaces"]["local"]
    target = qualified(namespace, TABLE)
    records = db.run(f"SELECT n,label FROM {target} ORDER BY n NULLS LAST,label NULLS LAST", describe=True)
    require(records["sqlState"] == "00000", "sequence-removal-table-unreadable", records)
    owner, = db.execute("SELECT c.oid FROM pg_catalog.pg_class c JOIN pg_catalog.pg_namespace n ON n.oid=c.relnamespace "
                        f"WHERE n.nspname={literal(namespace)} AND c.relname={literal(TABLE)}")[0]
    defaults = db.execute(
        "SELECT n.nspname,c.relname FROM pg_catalog.pg_attrdef a "
        "JOIN pg_catalog.pg_depend d ON d.objid=a.oid AND d.classid='pg_attrdef'::regclass AND d.refclassid='pg_class'::regclass "
        "JOIN pg_catalog.pg_class c ON c.oid=d.refobjid AND c.relkind='S' "
        "JOIN pg_catalog.pg_namespace n ON n.oid=c.relnamespace "
        f"WHERE a.adrelid={int(owner)} AND a.adnum=1 ORDER BY n.nspname,c.relname")
    default_count = int(db.execute(f"SELECT count(*) FROM pg_catalog.pg_attrdef WHERE adrelid={int(owner)} AND adnum=1")[0][0])
    require(default_count == len(defaults) and default_count <= 1, "sequence-removal-default-shape", defaults)
    rows = [[int(value) if value is not None else None, label] for value, label in records["rows"]]
    return {"default": defaults[0] if defaults else None, "consumerOid": int(owner), "rows": rows, "columns": records["columns"]}


def snapshot(db, item):
    sequences = {where: relation(db, scope, item["name"]) for where, scope in item["namespaces"].items()}
    return {**consumer(db, item), "sequences": {where: value for where, value in sequences.items() if value is not None}}


def permitted(item, command):
    if item.get("nativeRefusal"):
        return command == sql(item)
    return command in {sql(item), sql(item, canonical=True),
                       *(sql(item, canonical=True, change=change) for change in ("reverse", "decoy", "restrict"))}


def observe(db, request):
    item, command = request["trial"], request["sql"]
    if not permitted(item, command):
        return {"status": "outside-observer-scope"}
    setup(db, item)
    before = snapshot(db, item)
    if item["active"]:
        db.execute("BEGIN")
    result = db.run(command, diagnostic_error=True)
    phase = db.transaction()
    during = snapshot(db, item) if result["sqlState"] == "00000" else None
    if item["active"]:
        db.execute("ROLLBACK")
    settled = snapshot(db, item)
    return {"status": STATUS, "before": before, "during": during, "settled": settled, "transactionAfter": phase,
            **{key: result[key] for key in ("sqlState", "command", "notices", "error")}}


def main():
    with Session() as db:
        settings = [db.execute("SHOW " + key)[0][0] for key in (
                    "server_version_num", "server_encoding", "client_encoding", "standard_conforming_strings", "search_path")]
        current = db.execute("SELECT current_database()")[0][0]
        require(current == DATABASE, "postgres-drop-sequence-catalog")
        stock = db.execute(STOCK)
        stock_digest = sha256(json.dumps(stock, separators=(",", ":"), ensure_ascii=False).encode()).hexdigest()
        print(json.dumps({"database": settings, "currentDatabase": current, "python": python_version(),
                          "libpq": LIB.PQlibVersion(), "stockDigest": stock_digest,
                          "stockCount": len(stock)}, ensure_ascii=False), flush=True)
        for line in sys.stdin:
            request = json.loads(line)
            value = [observe(db, entry) for entry in request] if isinstance(request, list) else observe(db, request)
            print(json.dumps(value, ensure_ascii=False), flush=True)


if __name__ == "__main__":
    main()
