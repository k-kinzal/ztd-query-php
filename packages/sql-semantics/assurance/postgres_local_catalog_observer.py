"""Measure resolved local targets, native result metadata and protected decoy rows."""

import json
from platform import python_version
import sys

from postgres_libpq import LIB, Session
from postgres_local_catalog_cases import BASE_PATH, POSITIONS, names, source_sql, start_values
from postgres_reindex_cases import quoted
from run import require


def insert(table, values):
    return f"INSERT INTO {table} VALUES " + ", ".join(f"({value})" for value in values)


def initialize(db, item):
    commands = ["SET client_min_messages=warning", "RESET search_path",
                "DROP SCHEMA IF EXISTS fixture CASCADE", "DROP SCHEMA IF EXISTS decoy CASCADE",
                "CREATE SCHEMA fixture", "CREATE SCHEMA decoy"]
    for schema in POSITIONS.values():
        table, snapshot = names(item, schema)
        initial, current = start_values(item, schema)
        commands.extend([f"CREATE TABLE {table} (id integer)", insert(table, initial),
                         f"CREATE MATERIALIZED VIEW {snapshot} AS SELECT id FROM {table}", insert(table, current[1:])])
    commands.extend(["SET search_path=" + ", ".join(map(quoted, BASE_PATH)), "SET client_min_messages=notice"])
    db.execute(";".join(commands))


def observe(db, request):
    item, sql = request["trial"], request["sql"]
    valid_prefixes = ("SELECT ", "INSERT INTO ", "UPDATE ", "DELETE FROM ", "REFRESH MATERIALIZED VIEW ")
    require(sql == source_sql(item) or sql.startswith(valid_prefixes), "postgres-local-catalog-observer-statement", sql)
    initialize(db, item)
    command = db.run(sql, describe=True)
    observed = {}
    for schema in POSITIONS.values():
        table, snapshot = names(item, schema)
        observed[schema] = {kind: [int(row[0]) for row in db.execute(f"SELECT id FROM {name} ORDER BY id")]
                            for kind, name in (("table", table), ("snapshot", snapshot))}
    return {"status": "catalog-binding", "objects": observed, "columns": None, **command}


def main():
    with Session() as db:
        settings = [db.execute("SHOW " + key)[0][0] for key in
                    ("server_version_num", "server_encoding", "client_encoding", "standard_conforming_strings", "search_path")]
        database = db.execute("SELECT current_database()")[0][0]
        print(json.dumps({"database": settings, "currentDatabase": database, "python": python_version(),
                          "libpq": LIB.PQlibVersion()}), flush=True)
        for line in sys.stdin:
            incoming = json.loads(line)
            result = [observe(db, item) for item in incoming] if isinstance(incoming, list) else observe(db, incoming)
            print(json.dumps(result, ensure_ascii=False), flush=True)


if __name__ == "__main__":
    main()
