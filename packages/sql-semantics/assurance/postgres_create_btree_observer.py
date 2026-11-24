"""Observe one admitted PostgreSQL B-tree creation and its ordered catalog effects."""

import json
from platform import python_version
import re
import sys

from postgres_create_btree_cases import declarations, qualified, quoted
from postgres_libpq import LIB, Session
from run import require

CATALOG = """SELECT pg_catalog.json_build_array(i.indisunique::text, i.indnullsnotdistinct::text,
       i.indisvalid::text, i.indisready::text, i.indislive::text,
       i.indnkeyatts::text, i.indnatts::text, i.indkey::text, i.indoption::text, i.indcollation::text,
       a.amname, n.nspname, c.relname, r.relname,
       (SELECT pg_catalog.json_agg(op.opcname ORDER BY x.n)
        FROM pg_catalog.unnest(i.indclass::oid[]) WITH ORDINALITY AS x(oid, n)
        JOIN pg_catalog.pg_opclass op ON op.oid=x.oid)::text,
       pg_catalog.pg_get_expr(i.indpred, i.indrelid, false))
FROM pg_catalog.pg_index i JOIN pg_catalog.pg_class c ON c.oid=i.indexrelid
JOIN pg_catalog.pg_class r ON r.oid=i.indrelid
JOIN pg_catalog.pg_namespace n ON n.oid=c.relnamespace
JOIN pg_catalog.pg_am a ON a.oid=c.relam
WHERE i.indexrelid=pg_catalog.to_regclass({index}) AND i.indrelid=pg_catalog.to_regclass({table})
"""
ATTRIBUTE = """SELECT pg_catalog.json_agg(pg_catalog.json_build_array(a.attname, t.typname) ORDER BY a.attnum) FROM pg_catalog.pg_attribute a JOIN pg_catalog.pg_type t ON t.oid=a.atttypid
WHERE a.attrelid=pg_catalog.to_regclass({relation}) AND a.attnum>0 AND NOT a.attisdropped"""


def literal(value):
    if value is None:
        return "NULL"
    if type(value) is bool:
        return "TRUE" if value else "FALSE"
    require(type(value) is int, "postgres-btree-observer-fixture-type", value)
    return str(value)


def data_literal(value):
    # Standard-conforming fixture session; quote a catalog lookup as a string.
    return "'" + value.replace("'", "''") + "'"


def admit_sql(command, data):
    trial = data["trial"]
    root = trial["createBtree"]
    _, table, columns, _, _ = root["table"]
    heading = "(?:" + "|".join(map(re.escape, map(quoted, columns))) + ")"
    atom = heading + r"(?: (?:ASC|DESC)(?: NULLS (?:FIRST|LAST))?)?"
    ident = re.escape(quoted(root["name"]))
    target = r'(?:(?:"fixture"|"pg_temp")[.])?' + re.escape(quoted(table))
    key_list = rf"\({atom}(?:, {atom}){{0,31}}\)"
    payload = rf"(?: INCLUDE \({heading}(?:, {heading}){{0,31}}\))?"
    field = rf"(?:{target}[.])?{heading}"
    where = rf"(?: WHERE \({field} IS (?:NOT )?NULL\))?"
    pattern = rf"CREATE (?:UNIQUE )?INDEX (?:CONCURRENTLY )?(?:IF NOT EXISTS )?{ident} ON (?:ONLY )?{target} USING btree {key_list}{payload}(?: NULLS (?:NOT )?DISTINCT)?{where}"

    return re.fullmatch(pattern, command) is not None


def chosen(item):
    index = item["createBtree"]
    schema, table, columns, _, _ = index["table"]

    return qualified(schema, table), qualified(schema, index["name"]), quoted(columns[1])


def initialize(db, trial, path):
    db.execute("SET client_min_messages=warning; DROP SCHEMA IF EXISTS fixture CASCADE;"
               "DROP TABLE IF EXISTS public.ir_btree_marker; CREATE TABLE public.ir_btree_marker(n integer);"
               "INSERT INTO public.ir_btree_marker VALUES (0);" + ";".join(declarations(trial)))
    table, index, _ = chosen(trial)
    values = ["(" + ", ".join(map(literal, row)) + ")" for row in trial["rows"]]
    commands = [f"INSERT INTO {table} VALUES " + ", ".join(values)]
    if trial["createBtree"]["table"][0] == "pg_temp":
        commands.append("INSERT INTO fixture.orders VALUES (97)")
    if trial["occupancy"] == "existing-table":
        commands.append(f"INSERT INTO {index} VALUES (TRUE)")
    commands.append("SET search_path=" + ", ".join(map(quoted, path)))
    commands.append("SET client_min_messages=notice")
    db.execute(";".join(commands))


def observed_predicate(expression):
    if expression is None:
        return None
    form = re.fullmatch(r'\(("(?:[^"]|"")+"|[^() "]+) IS (NOT )?NULL\)', expression)
    require(form is not None, "postgres-btree-native-predicate-form", expression)
    name = form[1][1:-1].replace('""', '"') if form[1].startswith('"') else form[1]

    return [name, form[2] is not None]


def indexed(raw, scope):
    if raw is None:
        return None
    row = json.loads(raw)
    unique, nulls, valid, ready, live = (value == "true" for value in row[:5])
    key_count, member_count = map(int, row[5:7])
    positions, orders, collations = (list(map(int, value.split())) for value in row[7:10])
    method, schema, name, owner = row[10:14]

    return {
        "schema": "pg_temp" if scope == "pg_temp" and schema.startswith("pg_temp_") else schema,
        "index": name, "table": owner, "accessMethod": method, "unique": unique, "nullsNotDistinct": nulls,
        "valid": valid, "ready": ready, "live": live, "keyCount": key_count, "memberCount": member_count,
        "positions": positions, "classNames": json.loads(row[14]), "collations": collations, "orders": orders,
        "predicate": observed_predicate(row[15]),
    }


def rows_query(table, order):
    return f"""SELECT pg_catalog.json_agg(sub ORDER BY {order})
               FROM (SELECT * FROM {table}) AS sub"""


def decoded_rows(raw):
    return [list(row.values()) for row in json.loads(raw)]


def snapshot(db, trial):
    table, index, order = chosen(trial)
    scope, name, _, _, _ = trial["createBtree"]["table"]
    occupant = trial["occupancy"] == "existing-table"
    queries = [
        ATTRIBUTE.format(relation=data_literal(table)),
        rows_query(table, order),
        'SELECT pg_catalog.json_agg(n ORDER BY n) FROM public.ir_btree_marker',
        rows_query("fixture.orders", "decoy") if scope == "pg_temp" else "SELECT NULL",
        rows_query(index, "decoy") if occupant else "SELECT NULL",
        CATALOG.format(index=data_literal(index), table=data_literal(table)),
    ]
    # All six views of the fixture share one database snapshot and one client round trip.
    heading, rows, marker, decoy, competitor, catalog = db.execute(
        "SELECT " + ", ".join(f"({query})" for query in queries)
    )[0]
    contestant = {"name": trial["createBtree"]["name"], "rows": decoded_rows(competitor)} if occupant else None

    return {
        "owner": [scope, name, json.loads(heading)], "rows": decoded_rows(rows),
        "otherRows": None if decoy is None else decoded_rows(decoy), "marker": json.loads(marker),
        "competitor": contestant, "index": indexed(catalog, scope),
    }


def detect_conflict(db, trial):
    table, _, _ = chosen(trial)
    active = trial["transaction"]
    findings = []
    for number in trial["probeRows"]:
        db.execute("SAVEPOINT observe_write" if active else "BEGIN")
        values = ", ".join(map(literal, trial["rows"][number]))
        findings.append(db.run(f"INSERT INTO {table} VALUES ({values})")["sqlState"])
        db.execute("ROLLBACK TO SAVEPOINT observe_write; RELEASE SAVEPOINT observe_write" if active else "ROLLBACK")

    return findings


def observe(db, data):
    if not admit_sql(data["sql"], data):
        return {"status": "outside-observer-scope"}
    trial = data["trial"]
    initialize(db, trial, data["path"])
    before = snapshot(db, trial)
    active = trial["transaction"]
    if active:
        db.execute("BEGIN; INSERT INTO public.ir_btree_marker VALUES (7)")
    result = db.run(data["sql"])
    transaction = db.transaction()
    success = result["sqlState"] == "00000"
    after = snapshot(db, trial) if success or not active else None
    probe = detect_conflict(db, trial) if success else None
    if active:
        db.execute("ROLLBACK")
    return {
        "status": "btree-creation", "before": before, "after": after, "probe": probe,
        "settled": snapshot(db, trial), "transactionAfter": transaction,
        **{name: result[name] for name in ("sqlState", "command", "notices")},
    }


def main():
    with Session() as regular:
        settings = [regular.execute("SHOW " + name)[0][0] for name in (
            "server_version_num", "server_encoding", "client_encoding", "standard_conforming_strings", "search_path")]
        print(json.dumps({"database": settings, "python": python_version(), "libpq": LIB.PQlibVersion()}), flush=True)

        def run(request):
            if request["trial"]["createBtree"]["table"][0] == "pg_temp":
                with Session() as db:
                    return observe(db, request)
            return observe(regular, request)

        for line in sys.stdin:
            request = json.loads(line)
            response = [run(record) for record in request] if isinstance(request, list) else run(request)
            print(json.dumps(response, ensure_ascii=False), flush=True)


if __name__ == "__main__":
    main()
