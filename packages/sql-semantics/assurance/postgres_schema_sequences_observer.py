"""Observe sequence and table phases, generators, and rollback inside CREATE SCHEMA."""

import json
from platform import python_version
import sys

from postgres_create_schema_cases import CATALOG, EFFECTIVE, SESSION
from postgres_create_schema_observer import prepare_roles
from postgres_create_sequence_observer import calls, relation
from postgres_libpq import LIB, Session
from postgres_reindex_cases import quoted
from postgres_schema_sequences_cases import STATUS, sql
from postgres_schema_tables_observer import defaults, prepare_state, snapshot as table_snapshot
from run import require


def snapshot(db, item):
    structures = table_snapshot(db, item)
    sequences = {}
    tables = []
    for entry in structures["tables"]:
        local, _, kind, *_ = entry
        if kind == "S":
            sequences[local] = relation(db, item["namespace"], local)
        else:
            tables.append(entry)
    return {**structures, "tables": tables, "sequences": sequences}


def permitted(item, command):
    if item.get("nativeRefusal"):
        return item["command"] == command
    return command in {sql(item), sql(item, canonical=True),
                       *(sql(item, canonical=True, change=change) for change in ("discard", "guard", "namespace"))}


def observe(db, request):
    item, command = request["trial"], request["sql"]
    if not permitted(item, command):
        return {"status": "outside-observer-scope"}
    prepare_state(db, item)
    before = snapshot(db, item)
    db.execute(f"SET SESSION AUTHORIZATION {quoted(SESSION)}; SET ROLE {quoted(EFFECTIVE)}")
    if item["active"]:
        db.execute("BEGIN")
    result = db.run(command, diagnostic_error=True)
    phase = db.transaction()
    during = snapshot(db, item) if result["sqlState"] == "00000" else None
    values = defaults(db, item, during) if during is not None else None
    generated = ({name: calls(db, {"target": [item["namespace"], name], "active": item["active"]})
                  for name in during["sequences"]} if during is not None else None)
    if item["active"]:
        db.execute("ROLLBACK")
    db.execute("RESET ROLE; RESET SESSION AUTHORIZATION")
    settled = snapshot(db, item) if item["active"] or during is None else during
    return {"status": STATUS, "before": before, "during": during, "settled": settled,
            "transactionAfter": phase, "defaults": values, "generated": generated,
            **{key: result[key] for key in ("sqlState", "command", "notices", "error")}}


def main():
    with Session() as db:
        settings = [db.execute("SHOW " + key)[0][0] for key in (
                    "server_version_num", "server_encoding", "client_encoding", "standard_conforming_strings", "search_path")]
        database = db.execute("SELECT current_database()")[0][0]
        require(database == CATALOG, "postgres-schema-sequences-disposable-profile")
        roles = sorted(prepare_roles(db))
        print(json.dumps({"database": settings, "currentDatabase": database, "python": python_version(),
                          "libpq": LIB.PQlibVersion(), "roleCoordinates": roles}, ensure_ascii=False), flush=True)
        for line in sys.stdin:
            request = json.loads(line)
            result = [observe(db, entry) for entry in request] if isinstance(request, list) else observe(db, request)
            print(json.dumps(result, ensure_ascii=False), flush=True)


if __name__ == "__main__":
    main()
