"""Observe the catalog identities selected by PostgreSQL in an isolated fixture."""

import json
from platform import python_version
import sys

from postgres_collated_table_observer import OWNER, row
from postgres_create_btree_observer import data_literal, qualified, quoted
from postgres_libpq import LIB, Session
from postgres_stock_lookup_cases import PATHS, SHADOW, index_sql, inserted, setup, sql
from run import require

NAMESAKES = """
SELECT json_build_array(
  EXISTS(SELECT 1 FROM pg_collation WHERE collnamespace=pg_my_temp_schema() AND collname='C'),
  EXISTS(SELECT 1 FROM pg_opclass WHERE opcnamespace=pg_my_temp_schema() AND opcname='text_ops'),
  EXISTS(SELECT 1 FROM pg_collation WHERE collnamespace='hidden_names'::regnamespace AND collname='C'),
  EXISTS(SELECT 1 FROM pg_opclass WHERE opcnamespace='hidden_names'::regnamespace AND opcname='text_ops')
)
"""
ORDER = """
SELECT json_agg(CASE WHEN to_regnamespace(name)=pg_my_temp_schema() THEN 'pg_temp' ELSE name END ORDER BY ord)
FROM unnest(current_schemas(true)) WITH ORDINALITY ns(name,ord)
"""
FIELDS = """
SELECT json_agg(json_build_array(a.attname,t.typname,
   CASE WHEN p.oid IS NULL THEN 'null'::json ELSE json_build_array(n.nspname,p.collname) END)
   ORDER BY a.attnum)
FROM pg_attribute a JOIN pg_type t ON t.oid=a.atttypid
LEFT JOIN pg_collation p ON p.oid=a.attcollation LEFT JOIN pg_namespace n ON n.oid=p.collnamespace
WHERE a.attrelid=to_regclass({owner}) AND a.attnum>0 AND NOT a.attisdropped
"""
INDEX = """
SELECT json_build_array(c.relname,string_to_array(i.indkey::text,' ')::integer[],
                        json_build_array(n.nspname,p.collname),json_build_array(m.nspname,o.opcname))
FROM pg_index i JOIN pg_class c ON c.oid=i.indexrelid
JOIN pg_collation p ON p.oid=i.indcollation[0] JOIN pg_namespace n ON n.oid=p.collnamespace
JOIN pg_opclass o ON o.oid=i.indclass[0] JOIN pg_namespace m ON m.oid=o.opcnamespace
WHERE i.indrelid=to_regclass({owner})
"""


def prepare(db):
    db.execute("BEGIN")
    db.execute("CREATE TEMP TABLE namesake_anchor (id INTEGER)")
    db.execute(f"CREATE SCHEMA {SHADOW}")
    for schema in ("pg_temp", SHADOW):
        db.execute(f'CREATE COLLATION {schema}."C" FROM pg_catalog."POSIX"')
        operators = ", ".join(f"OPERATOR {i} pg_catalog.{op} (pg_catalog.text, pg_catalog.text)"
                              for i, op in enumerate(("<", "<=", "=", ">=", ">"), 1))
        db.execute(f"CREATE OPERATOR CLASS {schema}.text_ops FOR TYPE pg_catalog.text USING btree AS "
                   + operators + ", FUNCTION 1 pg_catalog.bttextcmp (pg_catalog.text, pg_catalog.text)")


def one(db, statement):
    (cell,), = db.execute(statement)
    return json.loads(cell)


def snapshot(db, item, owner):
    identity, = db.execute(OWNER.format(owner=data_literal(owner)))
    fields = one(db, FIELDS.format(owner=data_literal(owner)))
    index = one(db, INDEX.format(owner=data_literal(owner)))
    collation = index[2]
    require(collation in [["pg_catalog", "C"], ["pg_catalog", "POSIX"], [SHADOW, "C"]],
            "postgres-stock-lookup-unknown-physical-collation", collation)
    heading = ", ".join(map(quoted, item["columns"]))
    code = quoted(item["columns"][item["code"]])
    tie = quoted(item["columns"][item["id"]])
    observed = db.execute(f"SELECT json_build_array({heading}) FROM {owner} "
                          f"ORDER BY {code} COLLATE {qualified(*collation)} ASC NULLS LAST, {tie}")
    return {"owner": identity, "fields": fields, "index": index, "rows": [json.loads(value) for (value,) in observed],
            "shadowObjects": one(db, NAMESAKES), "namespaceOrder": one(db, ORDER)}


def observe(db, item, command):
    if command not in {sql(item), sql(item, canonical=True)}:
        return {"status": "outside-postgres-stock-lookup-observer"}
    path, role, scope, _ = item["coordinate"]
    owner = qualified(item["owner"], item["table"])
    db.execute("SAVEPOINT lookup_trial")
    try:
        db.execute("SET LOCAL search_path = " + ", ".join(map(quoted, PATHS[path])))
        for statement in setup(item):
            db.execute(statement)
        result = db.run(command, diagnostic_error=True)
        if result["sqlState"] != "00000":
            return {"status": "postgres-stock-lookup-native-error", "result": result}
        if scope == "standalone" and role == "column-collation":
            db.execute(index_sql(item))
        db.execute(f"INSERT INTO {owner} VALUES " + ", ".join(map(row, inserted(item))))
        return {"status": "postgres-stock-lookup-observation", **snapshot(db, item, owner)}
    finally:
        db.execute("ROLLBACK TO SAVEPOINT lookup_trial")
        db.execute("RELEASE SAVEPOINT lookup_trial")


def main():
    with Session() as db:
        prepare(db)
        environment = [db.execute("SHOW " + name)[0][0] for name in (
            "server_version_num", "server_encoding", "client_encoding", "standard_conforming_strings", "search_path")]
        print(json.dumps({"database": environment, "python": python_version(), "libpq": LIB.PQlibVersion()}), flush=True)

        def run(request):
            return observe(db, request["trial"], request["sql"])

        for line in sys.stdin:
            request = json.loads(line)
            result = [run(entry) for entry in request] if isinstance(request, list) else run(request)
            print(json.dumps(result, ensure_ascii=False), flush=True)


if __name__ == "__main__":
    main()
