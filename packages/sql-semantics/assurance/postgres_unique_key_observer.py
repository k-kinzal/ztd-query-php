"""Observe physical native unique-index storage and constraint timing."""

import json
from platform import python_version
import sys

from postgres_drop_schema_observer import literal
from postgres_libpq import LIB, Session
from postgres_primary_key_observer import probe, snapshot
from postgres_reindex_cases import qualified, quoted
from postgres_unique_key_cases import NS, SINGLE, STATUS, member_sql, probe_cases, sql

FAULTS = ("null-policy", "omit", "include", "order", "merge-policy", "last-name")


def native_writes(db, member):
    target = qualified(*member["target"])

    def returning(result):
        return result["sqlState"] if result["rows"] is None else [result["columns"], result["rows"]]

    def insert(values):
        command = f"INSERT INTO {target} VALUES (" + ", ".join(values) + ") RETURNING *"
        return db.run(command, describe=True)

    def pair(left, right):
        first = insert(left)
        if first["sqlState"] != "00000":
            return [returning(first), None, None]
        second = insert(right)
        verdict = db.run("SET CONSTRAINTS ALL IMMEDIATE")["sqlState"] if second["sqlState"] == "00000" else None
        return [returning(first), returning(second), verdict]

    def defaults():
        result = db.run(f"INSERT INTO {target} DEFAULT VALUES RETURNING *", describe=True)
        return [result["sqlState"], returning(result) if result["rows"] is not None else None]

    result = {"default": probe(db, defaults)}
    for name, left, right in probe_cases(member):
        result[name] = probe(db, lambda: pair(left, right))
    return result


def observe(db, request):
    if "fixture" in request:
        return {"status": "fixture", "namespaces": db.execute("SELECT nspname FROM pg_catalog.pg_namespace "
                f"WHERE nspname IN ({literal(SINGLE)}, {literal(NS)}) ORDER BY nspname")}
    item, statement = request["item"], request["sql"]
    admitted = {sql(item), sql(item, canonical=True)}
    admitted.update(sql(item, canonical=True, change=mode) for mode in item.get("faultModes", ()))
    if statement not in admitted:
        return {"status": "outside-observer-scope", "permitted": sorted(admitted)}
    namespace = NS if item["compose"] else SINGLE
    db.execute("BEGIN; SET LOCAL client_min_messages=notice")
    try:
        if not item["compose"]:
            for member in item["prefix"]:
                db.execute(member_sql(member, canonical=True))
        command = db.run(statement, diagnostic_error=True)
        transaction = db.transaction()
        if command["sqlState"] == "00000":
            relations, identities, links = snapshot(db, namespace, physical_index_details=True)
            witness = None if item.get("skipProbe") else native_writes(db, item["member"])
        else:
            relations, identities, links, witness = None, {}, {}, None
    finally:
        db.execute("ROLLBACK")
    after, _, _ = snapshot(db, namespace, physical_index_details=True)
    return {"status": STATUS, "sqlState": command["sqlState"], "notices": command["notices"],
            "error": command["error"], "relations": relations, "physicalOids": identities,
            "links": links, "transaction": transaction, "afterRollback": after, "witnesses": witness}


def main():
    with Session() as db:
        db.execute(f"CREATE SCHEMA {quoted(SINGLE)}")
        settings = [db.execute("SHOW " + key)[0][0] for key in (
            "server_version_num", "server_encoding", "client_encoding", "standard_conforming_strings", "search_path"
        )]
        print(json.dumps({"database": settings, "python": python_version(), "libpq": LIB.PQlibVersion()}), flush=True)
        for line in sys.stdin:
            value = json.loads(line)
            observed = [observe(db, entry) for entry in value] if isinstance(value, list) else observe(db, value)
            print(json.dumps(observed, ensure_ascii=False), flush=True)


if __name__ == "__main__":
    main()
