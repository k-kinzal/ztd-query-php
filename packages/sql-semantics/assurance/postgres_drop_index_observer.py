"""Execute exactly one admitted DROP INDEX in the disposable stock PostgreSQL fixture."""

import re
import json
import sys

from postgres_drop_index_cases import declaration_commands, qualified, quoted
from postgres_libpq import LIB, Session
from run import require

CATALOG = """SELECT CASE WHEN n.oid=pg_my_temp_schema() THEN 'pg_temp' ELSE n.nspname END AS namespace,
                     idx.relname, owner.relname, i.indisunique
FROM pg_index i JOIN pg_class idx ON idx.oid=i.indexrelid
JOIN pg_namespace n ON n.oid=idx.relnamespace JOIN pg_class owner ON owner.oid=i.indrelid
WHERE n.nspname IN ('front','later') OR n.oid=pg_my_temp_schema()"""
CONSTRAINTS = """SELECT CASE WHEN n.oid=pg_my_temp_schema() THEN 'pg_temp' ELSE n.nspname END AS namespace,
                         c.conname, c.contype
FROM pg_constraint c JOIN pg_namespace n ON n.oid=c.connamespace
WHERE n.nspname IN ('front','later') OR n.oid=pg_my_temp_schema()"""
STOCK = """SELECT n.nspname, c.relname FROM pg_class c JOIN pg_namespace n ON n.oid=c.relnamespace
WHERE n.nspname IN ('pg_catalog','information_schema') ORDER BY n.nspname COLLATE "C", c.relname COLLATE "C" """


def admit_sql(text, data):
    # Only the fixture's names, the absent test names, and one known wrong-kind
    # system table can be addressed by the candidate command.
    names = {"idx", "keeper", "missing", "pg_class"}
    names.update([entry[1] for entry in data["fixture"]["indexes"]])
    identifier = "|".join(re.escape(quoted(name)) for name in sorted(names))
    namespace = r'(?:"front"|"later"|"public"|"pg_temp"|"absent_schema")'
    target = rf"(?:{namespace}[.])?(?:{identifier})"

    return re.fullmatch(rf"DROP INDEX (?:CONCURRENTLY )?(?:IF EXISTS )?{target}(?:, {target}){{0,3}} (?:RESTRICT|CASCADE)", text)


def initialize(db, state, path):
    db.execute("SET client_min_messages=warning;"
               "DROP SCHEMA IF EXISTS front CASCADE; DROP SCHEMA IF EXISTS later CASCADE;"
               "DROP TABLE IF EXISTS public.ir_di_marker; CREATE TABLE public.ir_di_marker(n integer);"
               "INSERT INTO public.ir_di_marker VALUES(0);" +
               ";".join(declaration_commands(state)))
    commands = []
    for schema, table, rows in state["tables"]:
        values = ",".join("(" + ",".join(map(str, row)) + ")" for row in rows)
        commands.append(f"INSERT INTO {qualified(schema, table)} VALUES {values}")
    if any(index[5] for index in state["indexes"]):
        commands.append("INSERT INTO front.child VALUES (17)")
    commands.append("SET search_path=" + ", ".join(map(quoted, path)))
    commands.append("SET client_min_messages=notice")
    db.execute(";".join(commands))


def snapshot(db, state):
    indexes = sorted([namespace, index, owner, unique == "t"] for namespace, index, owner, unique in db.execute(CATALOG))
    constraints = sorted(db.execute(CONSTRAINTS))
    table_names = [(schema, table) for schema, table, _ in state["tables"]] + [("public", "ir_di_marker")]
    if any(index[5] for index in state["indexes"]):
        table_names.append(("front", "child"))
    rows = db.execute(" UNION ALL ".join(
        "SELECT coalesce(pg_catalog.json_agg(t ORDER BY t), '[]') FROM (SELECT * FROM "
        + qualified(schema, name) + ") t" for schema, name in table_names))
    require(len(rows) == len(table_names), "postgres-index-observer-table-shape", rows)
    tables = sorted([schema, table, [list(row.values()) for row in json.loads(body)]]
                    for (schema, table), (body,) in zip(table_names, rows))
    return {"indexes": indexes, "constraints": constraints, "tables": tables}


def write_probe(db, transaction):
    db.execute("SAVEPOINT verify_effect" if transaction else "BEGIN")
    state = db.run("INSERT INTO front.child VALUES (999999)")["sqlState"]
    db.execute("ROLLBACK TO SAVEPOINT verify_effect" if transaction else "ROLLBACK")
    return state


def observe(db, data):
    if not admit_sql(data["sql"], data):
        return {"status": "outside-observer-scope"}
    state = data["fixture"]
    initialize(db, state, data["path"])
    before = snapshot(db, state)
    if data["transaction"]:
        db.execute("BEGIN; INSERT INTO public.ir_di_marker VALUES(5)")
    command = db.run(data["sql"])
    transaction = db.transaction()
    success = command["sqlState"] == "00000"
    effect = snapshot(db, state) if success else None
    has_foreign = any(index[5] for index in state["indexes"])
    probe = write_probe(db, data["transaction"]) if success and has_foreign else None
    if data["transaction"]:
        db.execute("ROLLBACK")
    return {"status": "index-removal", "before": before, "transactionAfter": transaction,
            "effect": effect, "writeProbe": probe, "settled": snapshot(db, state),
            **{name: command[name] for name in ("command", "sqlState", "notices")}}


def contains_temp(data):
    return any(table[0] == "pg_temp" for table in data["fixture"]["tables"])


def main():
    with Session() as regular:
        settings = [regular.execute("SHOW " + name)[0][0] for name in (
            "server_version_num", "server_encoding", "client_encoding", "standard_conforming_strings", "search_path")]
        print(json.dumps({"database": settings, "libpq": LIB.PQlibVersion(), "stock": sorted(regular.execute(STOCK))}), flush=True)

        def run(data):
            if contains_temp(data):
                with Session() as connection:
                    return observe(connection, data)
            return observe(regular, data)

        for line in sys.stdin:
            request = json.loads(line)
            reply = [run(entry) for entry in request] if isinstance(request, list) else run(request)
            print(json.dumps(reply, ensure_ascii=False), flush=True)


if __name__ == "__main__":
    main()
