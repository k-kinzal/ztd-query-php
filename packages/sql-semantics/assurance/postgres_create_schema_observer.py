"""Read exact PostgreSQL namespace names, owners, ACLs, data and diagnostic states."""

from itertools import product
import json
from platform import python_version
import sys

from postgres_create_schema_cases import (CATALOG, DECOY, EFFECTIVE, OWNER, QUOTED_OWNER, SESSION, STATUS,
                                          name, observed_coordinates, original, spelling)
from postgres_drop_schema_observer import literal, table_rows
from postgres_libpq import LIB, Session
from postgres_reindex_cases import qualified, quoted
from run import require


def prepare_roles(db):
    require(db.execute("SELECT current_user, session_user, current_database()") == [["postgres", "postgres", CATALOG]],
            "postgres-create-schema-disposable-authority")
    for role in (OWNER, QUOTED_OWNER, EFFECTIVE, SESSION):
        if db.execute(f"SELECT count(*) FROM pg_catalog.pg_roles WHERE rolname={literal(role)}") == [["0"]]:
            privilege = " SUPERUSER" if role in (EFFECTIVE, SESSION) else ""
            db.execute(f"CREATE ROLE {quoted(role)}{privilege}")
    return db.execute("SELECT rolname, rolsuper FROM pg_catalog.pg_roles WHERE rolname IN (" +
                      ", ".join(literal(role) for role in (OWNER, QUOTED_OWNER, EFFECTIVE, SESSION)) + ")")


def prepare_state(db, item):
    db.execute("SET client_min_messages=warning")
    coordinates = ", ".join(quoted(value) for value in observed_coordinates(item))
    db.execute(f"DROP SCHEMA IF EXISTS {coordinates} CASCADE")
    names = [DECOY] + ([name(item)] if item["occupied"] else [])
    for coordinate in names:
        db.execute(f"CREATE SCHEMA {quoted(coordinate)} AUTHORIZATION {quoted(OWNER)}")
        db.execute(f"CREATE TABLE {qualified(coordinate, 'preserved')} (id integer)")
    db.execute(f"INSERT INTO {qualified(DECOY, 'preserved')} VALUES ({item['base'] + 100})")
    if item["occupied"]:
        db.execute(f"INSERT INTO {qualified(name(item), 'preserved')} VALUES ({item['base']}), (NULL)")
    db.execute("SET client_min_messages=notice")


def values(db, coordinate):
    rows = table_rows(db, qualified(coordinate, "preserved"), "id")
    if rows is None:
        return None
    return sorted((row[0] for row in rows), key=lambda value: (value is None, value))


def snapshot(db, item):
    coordinates = ", ".join(literal(value) for value in observed_coordinates(item))
    namespaces = db.execute("SELECT nspname, pg_catalog.pg_get_userbyid(nspowner), nspacl::text "
                            f"FROM pg_catalog.pg_namespace WHERE nspname IN ({coordinates})")
    return {"namespaces": sorted(namespaces), "decoy": values(db, DECOY), "targetRows": values(db, name(item))}


def allowed(item, command):
    written, named = item["name"], item["named"]
    targets = {written, written or named, named, None}
    authorizations = {None, "CURRENT_USER", "CURRENT_ROLE", "SESSION_USER", *(quoted(named) for _ in [0] if named is not None)}
    return command == original(item) or command in {
        spelling(target, authorization, guard)
        for target, authorization, guard in product(targets, authorizations, (False, True))
        if target is not None or authorization is not None
    }


def observe(db, request):
    item, statement = request["trial"], request["sql"]
    if not allowed(item, statement):
        return {"status": "outside-observer-scope"}
    prepare_state(db, item)
    before = snapshot(db, item)
    db.execute(f"SET SESSION AUTHORIZATION {quoted(SESSION)}")
    db.execute(f"SET ROLE {quoted(EFFECTIVE)}")
    if item["active"]:
        db.execute("BEGIN")
    result = db.run(statement, diagnostic_error=True)
    phase = db.transaction()
    during = snapshot(db, item) if result["sqlState"] == "00000" else None
    if item["active"]:
        db.execute("ROLLBACK")
    db.execute("RESET ROLE")
    db.execute("RESET SESSION AUTHORIZATION")
    final = snapshot(db, item) if item["active"] or during is None else during
    return {"status": STATUS, "transactionAfter": phase, "before": before, "during": during, "settled": final,
            **{key: result[key] for key in ("command", "sqlState", "notices", "error")}}


def main():
    with Session() as db:
        settings = [db.execute("SHOW " + key)[0][0] for key in (
                    "server_version_num", "server_encoding", "client_encoding", "standard_conforming_strings", "search_path")]
        current = db.execute("SELECT current_database()")[0][0]
        require(current == CATALOG, "postgres-create-schema-disposable-profile")
        roles = sorted(prepare_roles(db))
        print(json.dumps({"database": settings, "currentDatabase": current, "python": python_version(),
                          "libpq": LIB.PQlibVersion(), "roleCoordinates": roles}, ensure_ascii=False), flush=True)
        for line in sys.stdin:
            request = json.loads(line)
            result = [observe(db, entry) for entry in request] if isinstance(request, list) else observe(db, request)
            print(json.dumps(result, ensure_ascii=False), flush=True)


if __name__ == "__main__":
    main()
