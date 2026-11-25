"""Measure PostgreSQL table attributes and composed index effects."""

import json
from platform import python_version
import sys

from postgres_collated_table_cases import DECOY, setup, sql
from postgres_create_btree_observer import data_literal, literal, qualified, quoted
from postgres_libpq import LIB, Session
from run import require

ATTRIBUTES = """SELECT json_agg(json_build_array(a.attname,t.typname,a.attnotnull,
       CASE WHEN c.oid IS NULL THEN 'null'::json ELSE json_build_array(n.nspname,c.collname) END,
       (d.oid IS NOT NULL)) ORDER BY a.attnum)
   FROM pg_attribute a JOIN pg_type t ON t.oid=a.atttypid LEFT JOIN pg_collation c ON c.oid=a.attcollation
   LEFT JOIN pg_namespace n ON n.oid=c.collnamespace LEFT JOIN pg_attrdef d ON d.adrelid=a.attrelid AND d.adnum=a.attnum
   WHERE a.attrelid=to_regclass({owner}) AND a.attnum>0 AND NOT a.attisdropped"""

INDEXES = """SELECT json_build_array(c.relname,string_to_array(i.indkey::text,' ')::integer[],
   (SELECT json_agg(json_build_array(n.nspname,p.collname) ORDER BY k.ord)
      FROM unnest(i.indcollation::oid[]) WITH ORDINALITY k(oid,ord)
      JOIN pg_collation p ON p.oid=k.oid JOIN pg_namespace n ON n.oid=p.collnamespace),
   (SELECT json_agg(p.opcname ORDER BY k.ord) FROM unnest(i.indclass::oid[]) WITH ORDINALITY k(oid,ord)
      JOIN pg_opclass p ON p.oid=k.oid))
   FROM pg_index i JOIN pg_class c ON c.oid=i.indexrelid WHERE i.indrelid=to_regclass({owner})"""

OWNER = """SELECT n.nspname,c.relname FROM pg_class c JOIN pg_namespace n ON n.oid=c.relnamespace
   WHERE c.oid=to_regclass({owner})"""


def row(values):
    return "(" + ", ".join(data_literal(value) if type(value) is str else literal(value) for value in values) + ")"


def snapshot(db, item, owner):
    heading = ", ".join(map(quoted, item["columns"]))
    predicate = quoted(item["columns"][item["idSlot"]])
    order = ", ".join([quoted(item["columns"][item["codeSlot"]]), predicate])
    data = [json.loads(value) for (value,) in db.execute(f"SELECT json_build_array({heading}) FROM {owner} ORDER BY {order}")]
    index = db.execute(INDEXES.format(owner=data_literal(owner)))
    require(len(index) <= 1, "postgres-collated-table-native-index-count", index)
    fields = json.loads(db.execute(ATTRIBUTES.format(owner=data_literal(owner)))[0][0])
    identity = db.execute(OWNER.format(owner=data_literal(owner)))[0]
    db.execute("SAVEPOINT omitted_default")
    insertion = db.run(f"INSERT INTO {owner} ({predicate}) VALUES (9)", diagnostic_error=True)
    if insertion["sqlState"] != "00000":
        db.execute("ROLLBACK TO SAVEPOINT omitted_default")
    db.execute("RELEASE SAVEPOINT omitted_default")
    defaulted = db.execute(f"SELECT json_build_array({heading}) FROM {owner} WHERE {predicate}=9")
    decoy = db.execute(f"SELECT sentinel FROM {qualified(DECOY, item['table'])} ORDER BY sentinel")
    return {
        "owner": identity, "metadata": [field[:4] for field in fields], "defaultsPresent": [field[4] for field in fields],
        "index": None if not index else json.loads(index[0][0]), "ordered": data, "defaultStatus": insertion["sqlState"],
        "defaultRow": None if not defaulted else json.loads(defaulted[0][0]), "decoy": [[int(value)] for (value,) in decoy],
    }


def admitted(item, command):
    return command in {sql(item), sql(item, canonical=True),
                       sql({**item, "collation": None}, canonical=True),
                       sql({**item, "collation": "C"}, canonical=True)}


def observe(db, item, command):
    if not admitted(item, command):
        return {"status": "outside-postgres-collated-table-observer"}
    owner = qualified(item["namespace"], item["table"])
    db.execute("BEGIN")
    try:
        db.execute("; ".join(setup(item)))
        db.execute(f"INSERT INTO {qualified(DECOY, item['table'])} VALUES (914)")
        db.execute(f"SET LOCAL search_path = pg_temp, pg_catalog, {DECOY}, public")
        result = db.run(command, diagnostic_error=True)
        if result["sqlState"] != "00000":
            return {"status": "postgres-collated-table-native-refusal", "result": result,
                    "ownerAfterRollback": absent_after_refusal(db, owner)}
        data = ", ".join(map(row, item["rows"]))
        db.execute(f"INSERT INTO {owner} VALUES {data}")
        return {"status": "postgres-collated-table-observation", "command": result["command"],
                "sqlState": result["sqlState"], **snapshot(db, item, owner)}
    finally:
        if db.transaction() != "idle":
            db.execute("ROLLBACK")


def absent_after_refusal(db, owner):
    db.execute("ROLLBACK")
    return db.execute(f"SELECT to_regclass({data_literal(owner)})")[0][0] is None


def main():
    with Session() as db:
        environment = [db.execute("SHOW " + name)[0][0] for name in (
            "server_version_num", "server_encoding", "client_encoding", "standard_conforming_strings", "search_path")]
        print(json.dumps({"database": environment, "python": python_version(), "libpq": LIB.PQlibVersion()}), flush=True)

        def run(request):
            return observe(db, request["trial"], request["sql"])

        for line in sys.stdin:
            request = json.loads(line)
            actual = [run(entry) for entry in request] if isinstance(request, list) else run(request)
            print(json.dumps(actual, ensure_ascii=False), flush=True)


if __name__ == "__main__":
    main()
