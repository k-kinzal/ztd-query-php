"""Measure PostgreSQL namespace removal and surviving cross-namespace dependents."""

from itertools import product
import json
from platform import python_version
import sys

from postgres_drop_schema_cases import CATALOG, STATUS, data, declarations, sql
from postgres_libpq import LIB, Session
from postgres_reindex_cases import qualified, quoted
from run import require


def literal(value):
    return "'" + value.replace("'", "''") + "'"


def tuples(rows):
    return ", ".join("(" + ", ".join(str(value) if value is not None else "NULL" for value in row) + ")" for row in rows)


def setup(db, item):
    names = item["names"]
    schema_list = ", ".join(quoted(names[role]) for role in names)
    db.execute("SET client_min_messages=warning; DROP SCHEMA IF EXISTS " + schema_list + " CASCADE")
    for command in declarations(item):
        db.execute(command)
    selected, decoy = data(item)
    if item["family"] != "empty":
        db.execute(f"INSERT INTO {qualified(names['selected'], 'items')} VALUES " + tuples(selected))
    db.execute(f"INSERT INTO {qualified(names['external'], 'control')} VALUES " + tuples(decoy))
    db.execute("SET client_min_messages=notice")


def table_rows(db, name, fields):
    seen = db.execute(f"SELECT pg_catalog.to_regclass({literal(name)})")
    if seen == [[None]]:
        return None
    return [[None if value is None else int(value) for value in row]
            for row in db.execute(f"SELECT {fields} FROM {name} ORDER BY id")]


def snapshot(db, item):
    names = item["names"]
    indexed = {name: role for role, name in names.items()}
    listed = ", ".join(literal(name) for name in indexed)
    scopes = db.execute(f"SELECT nspname FROM pg_catalog.pg_namespace WHERE nspname IN ({listed})")
    decoy = qualified(names["external"], "control")
    references = db.execute(f"SELECT count(*) FROM pg_catalog.pg_constraint WHERE contype='f'"
                            f" AND conrelid=pg_catalog.to_regclass({literal(decoy)})")
    require(references in ([["0"]], [["1"]]), "postgres-drop-schema-fixture-reference-count")
    return {"namespaces": sorted(indexed[row[0]] for row in scopes),
            "selected": table_rows(db, qualified(names["selected"], "items"), "id, payload"),
            "decoy": table_rows(db, decoy, "id, ref"),
            "view": table_rows(db, qualified(names["external"], "rollup"), "id"),
            "foreignKey": references == [["1"]]}


def allowed(item, command):
    roles = tuple(item["roles"])
    alternative = tuple("other" if role == "selected" else role for role in roles)
    return command in {sql(item, sequence, guard, cascade, explicit)
                       for sequence, guard, cascade, explicit in product(
                           {roles, alternative, tuple(dict.fromkeys(roles))}, (False, True), (False, True), (False, True))}


def observe(db, request):
    item, statement = request["trial"], request["sql"]
    if not allowed(item, statement):
        return {"status": "outside-observer-scope"}
    setup(db, item)
    before = snapshot(db, item)
    if item["active"]:
        db.execute("BEGIN")
    result = db.run(statement, diagnostic_error=True)
    phase = db.transaction()
    during = snapshot(db, item) if result["sqlState"] == "00000" else None
    if item["active"]:
        db.execute("ROLLBACK")
    final = snapshot(db, item) if item["active"] or during is None else during
    return {"status": STATUS, "transactionAfter": phase, "before": before, "during": during, "settled": final,
            **{key: result[key] for key in ("command", "sqlState", "notices", "error")}}


def main():
    with Session() as db:
        settings = [db.execute("SHOW " + key)[0][0] for key in (
                    "server_version_num", "server_encoding", "client_encoding", "standard_conforming_strings", "search_path")]
        current = db.execute("SELECT current_database()")[0][0]
        require(current == CATALOG, "postgres-drop-schema-disposable-profile")
        print(json.dumps({"database": settings, "currentDatabase": current, "python": python_version(), "libpq": LIB.PQlibVersion()}),
              flush=True)
        for line in sys.stdin:
            request = json.loads(line)
            result = [observe(db, entry) for entry in request] if isinstance(request, list) else observe(db, request)
            print(json.dumps(result, ensure_ascii=False), flush=True)


if __name__ == "__main__":
    main()
