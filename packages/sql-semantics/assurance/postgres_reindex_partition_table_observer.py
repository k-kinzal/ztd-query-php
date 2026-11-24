"""Measure physical catalog transitions beneath a point in the partition tree."""

import json
from platform import python_version
import sys

from postgres_libpq import LIB, Session
from postgres_reindex_cases import qualified, quoted
from postgres_reindex_partition_table_cases import DECOY, NATIVE_FORMS, analyzed, fixture, path, tree
from postgres_reindex_schema_cases import name
from postgres_reindex_schema_observer import ATTACHMENTS
from postgres_reindex_table_observer import read_owner


def admitted(item, sql):
    schema, relation = item["reindexPartitionTable"]["target"]
    return sql in {
        analyzed(item)["sql"], "REINDEX TABLE " + qualified("fixture", "low"), "REINDEX INDEX " + qualified("fixture", "root_key"),
        *(prefix + qualified(schema, relation) for prefix in NATIVE_FORMS.values()),
    }


def snapshot(db, item):
    return {name(owner): read_owner(db, owner["schema"], owner["name"]) for owner in tree(item)}


def observe(db, request):
    item, sql = request["trial"], request["sql"]
    if not admitted(item, sql):
        return {"status": "outside-observer-scope"}
    setup = ["SET client_min_messages=warning", "RESET search_path",
             "DROP SCHEMA IF EXISTS fixture CASCADE", f"DROP SCHEMA IF EXISTS {DECOY} CASCADE",
             *fixture(item, populate=True), "SET search_path=" + ",".join(map(quoted, path(item))),
             "SET client_min_messages=notice"]
    db.execute(";".join(setup))
    before = snapshot(db, item)
    before_links = sorted(db.execute(ATTACHMENTS))
    if item["active"]:
        db.execute("BEGIN")
    result = db.run(sql)
    transaction = db.transaction()
    success = result["sqlState"] == "00000"
    during = snapshot(db, item) if success else None
    during_links = sorted(db.execute(ATTACHMENTS)) if success else None
    if item["active"]:
        db.execute("ROLLBACK")
    return {
        "status": "partition-rebuild", "before": before, "during": during, "settled": snapshot(db, item),
        "transactionAfter": transaction, "attachmentsBefore": before_links, "attachmentsDuring": during_links,
        "attachmentsSettled": sorted(db.execute(ATTACHMENTS)), **{key: result[key] for key in ("sqlState", "command", "notices")},
    }


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
