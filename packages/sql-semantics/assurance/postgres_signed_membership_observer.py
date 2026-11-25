"""Observe PostgreSQL signed predicate values and reversible fixture writes."""

import json
from platform import python_version
import re
import sys

from postgres_drop_index_cases import qualified
from postgres_libpq import LIB, Session
from postgres_signed_membership_cases import OIDS, ROWS, SAMPLE, SHADOW_SCHEMA, declarations
from run import require

TYPES = {oid: name for name, oid in OIDS.items()}
QUALIFIED_SAMPLE = qualified("public", SAMPLE)
SAFE_WRITE = {"update": f"UPDATE {QUALIFIED_SAMPLE}", "delete": f"DELETE FROM {QUALIFIED_SAMPLE}"}
FUNCTION = SHADOW_SCHEMA + ".comparison"


def prepare(db):
    db.execute("BEGIN")
    db.execute(f"CREATE SCHEMA {SHADOW_SCHEMA}")
    for declaration in declarations():
        db.execute(declaration)
    for name, data in ROWS.items():
        values = ", ".join("(" + ", ".join("NULL" if field is None else str(field) for field in row) + ")" for row in data)
        db.execute(f"INSERT INTO {qualified('public', name)} VALUES {values}")
    for rhs in ("int4", "int8"):
        for name, symbol, value in (("equal", "=", "FALSE"), ("different", "<>", "TRUE")):
            function = FUNCTION + "_" + name
            db.execute(f"CREATE FUNCTION {function}(pg_catalog.int4, pg_catalog.{rhs}) RETURNS boolean "
                       f"LANGUAGE SQL IMMUTABLE STRICT RETURN {value}")
            db.execute(f"CREATE OPERATOR {SHADOW_SCHEMA}.{symbol} (FUNCTION = {function}, LEFTARG = pg_catalog.int4, RIGHTARG = pg_catalog.{rhs})")


def scalar(value, oid):
    if value is None:
        return None
    if TYPES[oid] == "bool":
        require(value in ("t", "f"), "postgres-membership-native-boolean")
        return value == "t"
    return int(value)


def query(db, command):
    result = db.run(command, describe=True, diagnostic_error=True)
    if result["sqlState"] != "00000":
        return {"status": "postgres-membership-native-error", "result": result}
    columns = result["columns"]
    require({oid for _, oid in columns} <= TYPES.keys(), "postgres-membership-native-type-domain", columns)
    return {"status": "rows", "fields": [[name, TYPES[oid], oid] for name, oid in columns],
            "rows": [[scalar(value, oid) for value, (_, oid) in zip(row, columns, strict=True)] for row in result["rows"]]}


def observe(db, plan):
    sql, role, shadow = (plan[key] for key in ("sql", "placement", "shadow"))
    prefix = SAFE_WRITE.get(role)
    if ";" in sql or "--" in sql or "/*" in sql or (prefix and not sql.startswith(prefix + " ")):
        return {"status": "outside-postgres-membership-observer"}
    if prefix is None and re.match(r"\ASELECT\s", sql) is None:
        return {"status": "outside-postgres-membership-observer"}
    isolated = prefix is not None or shadow
    if isolated:
        db.execute("SAVEPOINT membership_probe")
    try:
        if shadow:
            db.execute(f"SET LOCAL search_path = {SHADOW_SCHEMA}, pg_catalog, public")
        result = query(db, sql)
        if prefix and result["status"] == "rows":
            result["after"] = query(db, f"SELECT a,b FROM {QUALIFIED_SAMPLE}")
        return result
    finally:
        if isolated:
            db.execute("ROLLBACK TO SAVEPOINT membership_probe")
            db.execute("RELEASE SAVEPOINT membership_probe")


def fixture(db):
    captured = {}
    for name in ROWS:
        captured[name] = query(db, f"SELECT a,b FROM {qualified('public', name)}")
    for mode in (False, True):
        captured[str(mode)] = observe(db, {
            "sql": "SELECT (2 IN (2)) AS positive, (2 NOT IN (2)) AS negative",
            "placement": "projection", "shadow": mode,
        })
    return captured


def main():
    with Session() as db:
        prepare(db)
        environment = [db.execute("SHOW " + name)[0][0] for name in (
            "server_version_num", "server_encoding", "client_encoding", "standard_conforming_strings", "search_path")]
        print(json.dumps({"database": environment, "python": python_version(), "libpq": LIB.PQlibVersion()}), flush=True)

        def run(request):
            return fixture(db) if request.get("verifyFixture") is True else observe(db, request)

        for line in sys.stdin:
            request = json.loads(line)
            actual = [run(entry) for entry in request] if isinstance(request, list) else run(request)
            print(json.dumps(actual, ensure_ascii=False), flush=True)


if __name__ == "__main__":
    main()
