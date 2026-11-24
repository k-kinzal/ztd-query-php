"""Observe physical sequence logging, retained references and transaction effects."""

from hashlib import sha256
import json
from platform import python_version
import sys

from postgres_drop_sequence_observer import STOCK
from postgres_libpq import LIB, Session
from postgres_reconfigure_sequence_observer import prepare, snapshot
from postgres_sequence_logging_cases import DATABASE, STATUS, sql
from run import require


def read(db, item):
    state = snapshot(db, item)
    refs = db.execute(
        "SELECT d.refobjid FROM pg_catalog.pg_attrdef a JOIN pg_catalog.pg_depend d "
        "ON d.objid=a.oid AND d.classid='pg_attrdef'::regclass AND d.refclassid='pg_class'::regclass "
        "JOIN pg_catalog.pg_class c ON c.oid=d.refobjid AND c.relkind='S' "
        f"WHERE a.adrelid={state['consumerOid']} AND a.adnum=1"
    )
    (oid,), = refs
    return {**state, "defaultOid": int(oid)}


def permitted(item, command):
    if "nativeRefusal" in item:
        return command == item["command"]
    return command in {sql(item), sql(item, canonical=True),
                       *(sql(item, canonical=True, change=mode) for mode in ("decoy", "reverse", "drop-prefix", "unguarded"))}


def observe(db, request):
    item, command = request["trial"], request["sql"]
    if not permitted(item, command):
        return {"status": "outside-observer-scope"}
    prepare(db, item)
    before = read(db, item)
    if item["active"]:
        db.execute("BEGIN")
    result = db.run(command, diagnostic_error=True)
    phase = db.transaction()
    during = read(db, item) if result["sqlState"] == "00000" else None
    rewritten = during is not None and any(
        record["file"] != before["sequences"][role]["file"] for role, record in during["sequences"].items()
    )
    if item["active"]:
        db.execute("ROLLBACK")
    settled = during if during is not None and not item["active"] else read(db, item)
    return {"status": STATUS, "transactionAfter": phase, "before": before, "during": during, "settled": settled,
            "rewritten": rewritten, **{field: result[field] for field in ("command", "sqlState", "notices", "error")}}


def main():
    with Session() as db:
        settings = [db.execute("SHOW " + key)[0][0] for key in (
                    "server_version_num", "server_encoding", "client_encoding", "standard_conforming_strings", "search_path")]
        current = db.execute("SELECT current_database()")[0][0]
        require(current == DATABASE, "sequence-logging-fixture-coordinate")
        stock = db.execute(STOCK)
        stock_digest = sha256(json.dumps(stock, separators=(",", ":"), ensure_ascii=False).encode()).hexdigest()
        print(json.dumps({"database": settings, "python": python_version(), "libpq": LIB.PQlibVersion(),
                          "currentDatabase": current, "stockDigest": stock_digest, "stockCount": len(stock)}, ensure_ascii=False), flush=True)
        for line in sys.stdin:
            request = json.loads(line)
            result = [observe(db, entry) for entry in request] if isinstance(request, list) else observe(db, request)
            print(json.dumps(result, ensure_ascii=False), flush=True)


if __name__ == "__main__":
    main()
