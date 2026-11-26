"""Report physical identity changes and partition links for PostgreSQL schema rebuilds."""

import json
from platform import python_version
import sys

from postgres_libpq import LIB, Session
from postgres_reindex_cases import qualified, quoted
from postgres_reindex_schema_cases import DECOY, NATIVE_FORMS, analyzed, fixture, name, owners
from postgres_reindex_table_observer import read_owner

ATTACHMENTS = """SELECT pns.nspname, pc.relname, cns.nspname, cc.relname
FROM pg_catalog.pg_inherits link
JOIN pg_catalog.pg_class pc ON pc.oid=link.inhparent
JOIN pg_catalog.pg_class cc ON cc.oid=link.inhrelid
JOIN pg_catalog.pg_namespace pns ON pns.oid=pc.relnamespace
JOIN pg_catalog.pg_namespace cns ON cns.oid=cc.relnamespace
WHERE pns.nspname IN (SELECT nspname FROM pg_catalog.pg_namespace WHERE nspname NOT LIKE 'pg_%' AND nspname <> 'information_schema')
AND cns.nspname IN (SELECT nspname FROM pg_catalog.pg_namespace WHERE nspname NOT LIKE 'pg_%' AND nspname <> 'information_schema')"""


def admitted(item, sql):
    schema = item["reindexSchema"]["schema"]
    return sql in {
        analyzed(item)["sql"], "REINDEX TABLE " + qualified(schema, "orders"),
        *(prefix + quoted(target) for prefix in NATIVE_FORMS.values() for target in (schema, DECOY)),
    }


def snapshot(db, item):
    return {name(owner): read_owner(db, owner["schema"], owner["name"]) for owner in owners(item)}


def observe(db, request):
    item, sql = request["trial"], request["sql"]
    if not admitted(item, sql):
        return {"status": "outside-observer-scope"}
    schema = item["reindexSchema"]["schema"]
    setup = ["SET client_min_messages=warning", "RESET search_path",
             f"DROP SCHEMA IF EXISTS {quoted(schema)} CASCADE", "DROP SCHEMA IF EXISTS decoy CASCADE",
             *fixture(item, populate=True), "SET client_min_messages=notice"]
    db.execute(";".join(setup))
    before = snapshot(db, item)
    attachments = sorted(db.execute(ATTACHMENTS))
    if item["active"]:
        db.execute("BEGIN")
    result = db.run(sql)
    transaction = db.transaction()
    if item["active"]:
        db.execute("ROLLBACK")
    db.execute("SET client_min_messages=warning")
    output = {
        "status": "schema-rebuild", "before": before, "settled": snapshot(db, item), "transactionAfter": transaction,
        "attachmentsBefore": attachments, "attachmentsSettled": sorted(db.execute(ATTACHMENTS)),
        **{key: result[key] for key in ("sqlState", "command", "notices")},
    }
    db.execute(f"DROP SCHEMA {quoted(schema)} CASCADE; DROP SCHEMA decoy CASCADE")
    return output


def main():
    with Session() as db:
        settings = [db.execute("SHOW " + key)[0][0] for key in
                    ("server_version_num", "server_encoding", "client_encoding", "standard_conforming_strings", "search_path")]
        print(json.dumps({"database": settings, "python": python_version(), "libpq": LIB.PQlibVersion()}), flush=True)
        for line in sys.stdin:
            incoming = json.loads(line)
            result = [observe(db, item) for item in incoming] if isinstance(incoming, list) else observe(db, incoming)
            print(json.dumps(result, ensure_ascii=False), flush=True)


if __name__ == "__main__":
    main()
