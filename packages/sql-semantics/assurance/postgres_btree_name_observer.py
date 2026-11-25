"""Record PostgreSQL automatic index names, physical fields and write effects."""

import json
from platform import python_version
import re
import sys

from postgres_btree_name_cases import BATCH, DECOY, NS, proposals
from postgres_create_btree_observer import ATTRIBUTE, admit_condition, data_literal, literal, qualified, quoted
from postgres_libpq import LIB, Session
from run import require

WORDS = set("""CREATE SCHEMA AUTHORIZATION CURRENT_USER TABLE INDEX UNIQUE VIEW SEQUENCE ON USING BTREE HEAP
  INCLUDE NULLS FIRST LAST NOT NULL WHERE IS ASC DESC AS SELECT CAST PG_CATALOG INT4 INTEGER BIGINT
  NO MINVALUE MAXVALUE CYCLE START WITH INCREMENT BY CACHE OWNED NONE""".split())
TOKEN = re.compile(r'"(?:[^"\x00]|"")+"|[A-Za-z_][A-Za-z_0-9]*|[-+]?[0-9]+|[.,()]| +')
NAMESPACES = (NS, DECOY, BATCH)

INDEXES = """SELECT i.indexrelid,t.relname,n.nspname,c.relname,a.amname,i.indnkeyatts,
 i.indkey::text,i.indoption::text,i.indcollation::text,i.indisunique,i.indnullsnotdistinct,i.indisvalid,
 pg_get_expr(i.indpred,i.indrelid),
 (SELECT json_agg(a.attname ORDER BY a.attnum) FROM pg_attribute a
  WHERE a.attrelid=c.oid AND a.attnum>0 AND NOT a.attisdropped),
 (SELECT json_agg(c.opcname ORDER BY p.number)
  FROM unnest(i.indclass::oid[]) WITH ORDINALITY p(oid,number) JOIN pg_opclass c ON c.oid=p.oid)
FROM pg_index i JOIN pg_class c ON c.oid=i.indexrelid JOIN pg_namespace n ON n.oid=c.relnamespace
 JOIN pg_class t ON t.oid=i.indrelid JOIN pg_am a ON a.oid=c.relam
WHERE i.indrelid=to_regclass({}) ORDER BY i.indexrelid"""


def admitted(statement, scope):
    prefix = r"CREATE SCHEMA " if scope == "schema" else r"CREATE (?:UNIQUE )?INDEX "
    if re.match(prefix, statement) is None:
        return False
    tokens = TOKEN.findall(statement)
    if not tokens or "".join(tokens) != statement:
        return False
    if any(token[0].isascii() and token[0].isalpha()
           and token.upper() not in WORDS and token not in NAMESPACES for token in tokens):
        return False
    return scope == "schema" or tokens.count("CREATE") == 1


def namespace(db):
    values = ", ".join(map(data_literal, NAMESPACES))
    records = db.execute("SELECT n.nspname,c.relname,c.relkind FROM pg_catalog.pg_class c "
                         "JOIN pg_catalog.pg_namespace n ON n.oid=c.relnamespace "
                         f"WHERE n.nspname IN ({values})")
    return sorted(records)


def native_indexes(db, table):
    return db.execute(INDEXES.format(data_literal(table)))


def membership(db, predicate, item, table, order):
    if predicate is None:
        return None
    allowed = "(?:" + "|".join(re.escape(form) for field in item["columns"] for form in (quoted(field), field)
                              if form.startswith('"') or re.fullmatch(r"[a-z_][a-z_0-9]*", form)) + ")"
    require(admit_condition(predicate, allowed), "automatic-index-native-predicate", predicate)
    return [value == "t" for (value,) in db.execute(f"SELECT ({predicate}) FROM {table} ORDER BY {order}")]


def index_values(db, raw, item, table, order):
    _, table_name, schema, name, method, key_count, positions, direction, collations, unique, distinct, valid, predicate, labels, classes = raw
    return {
        "name": name, "nameBytes": len(name.encode()), "owner": [schema, table_name], "method": method,
        "keyCount": int(key_count), "positions": list(map(int, positions.split())),
        "direction": list(map(int, direction.split())), "collations": list(map(int, collations.split())),
        "unique": unique == "t", "nullsNotDistinct": distinct == "t", "valid": valid == "t",
        "orderedLabels": json.loads(labels), "classNames": json.loads(classes),
        "membership": membership(db, predicate, item, table, order),
    }


def insert(table, row):
    return f"INSERT INTO {table} VALUES (" + ", ".join(map(literal, row)) + ")"


def observe(db, item, statement):
    if not admitted(statement, item["scope"]):
        return {"status": "outside-automatic-index-observer"}
    db.execute("BEGIN")
    try:
        db.execute("SET LOCAL search_path = pg_catalog,public")
        if item["setup"]:
            db.execute(";".join(item["setup"]))
        table = qualified(*item["owner"])
        order = quoted(item["columns"][3])
        previous = set()
        if item["scope"] == "standalone":
            for row in item["data"]:
                db.execute(insert(table, row))
            previous = {raw[0] for raw in native_indexes(db, table)}
        before = namespace(db)
        db.execute("SET LOCAL client_min_messages=notice")
        result = db.run(statement, diagnostic_error=True)
        if result["sqlState"] != "00000":
            return {"status": "automatic-index-native-refusal", "before": before, "result": result}
        if item["scope"] == "schema":
            for row in item["data"]:
                db.execute(insert(table, row))
        after = namespace(db)
        indexes = [index_values(db, row, item, table, order) for row in native_indexes(db, table) if row[0] not in previous]
        fields = ATTRIBUTE.format(relation=data_literal(table))
        heading = json.loads(db.execute(fields)[0][0])
        columns = ", ".join(map(quoted, item["columns"]))
        raw = db.execute(f"SELECT {columns} FROM {table} ORDER BY {order}")
        rows = [[None if value is None else int(value) for value in row] for row in raw]
        writes = []
        for row in proposals(item):
            db.execute("SAVEPOINT automatic_write")
            writes.append(db.run(insert(table, row))["sqlState"])
            db.execute("ROLLBACK TO SAVEPOINT automatic_write; RELEASE SAVEPOINT automatic_write")
        return {"status": "automatic-index-observation", "sqlState": result["sqlState"], "command": result["command"],
                "notices": result["notices"], "before": before, "after": after, "indexes": indexes,
                "rows": rows, "heading": heading, "writes": writes}
    finally:
        db.execute("ROLLBACK")


def main():
    with Session() as db:
        settings = [db.execute("SHOW " + name)[0][0] for name in (
            "server_version_num", "server_encoding", "client_encoding", "standard_conforming_strings", "search_path")]
        print(json.dumps({"database": settings, "python": python_version(), "libpq": LIB.PQlibVersion()}), flush=True)

        def run(record):
            return observe(db, record["trial"], record["sql"])

        for line in sys.stdin:
            batch = json.loads(line)
            reply = [run(record) for record in batch] if isinstance(batch, list) else run(batch)
            print(json.dumps(reply, ensure_ascii=False), flush=True)


if __name__ == "__main__":
    main()
