"""Measure wide native rows, zero-field tuples and the physical heap heading."""

import json
from platform import python_version
import sys

from postgres_drop_schema_observer import literal
from postgres_libpq import LIB, Session
from postgres_primary_key_observer import probe
from postgres_reindex_cases import qualified, quoted
from postgres_table_width_cases import NS, SINGLE, STATUS, existing, member_sql, sql
from run import require


def catalog(db, coordinate):
    schema, relation = coordinate
    rows = db.execute("SELECT c.oid,c.relkind,c.relnatts,a.amname FROM pg_catalog.pg_class c "
                      "JOIN pg_catalog.pg_namespace n ON n.oid=c.relnamespace "
                      "LEFT JOIN pg_catalog.pg_am a ON a.oid=c.relam "
                      f"WHERE n.nspname={literal(schema)} AND c.relname={literal(relation)}")
    if not rows:
        return None, None
    (oid, relation_kind, width, method), = rows
    identifier = int(oid)
    fields = db.execute("SELECT attnum,attname,atttypid,attnotnull,atthasdef FROM pg_catalog.pg_attribute "
                        f"WHERE attrelid={identifier} AND attnum>0 AND NOT attisdropped ORDER BY attnum")
    return {"kind": relation_kind, "width": int(width), "access": method,
            "fields": [[int(slot), label, int(type_oid), required == "f", default == "t"]
                       for slot, label, type_oid, required, default in fields]}, identifier


def writes(db, item, relation):
    target = qualified(*item["member"]["target"])
    default = [db.run(f"INSERT INTO {target} DEFAULT VALUES")["sqlState"] for _ in range(2)]
    attempt = None
    if item["probe"] == "returning":
        attempt = probe(db, lambda: db.run(f"INSERT INTO {target} DEFAULT VALUES RETURNING *")["sqlState"])
    elif item["probe"] == "pack":
        terms = ", ".join(str(index) for index in range(1, relation["width"] + 1))
        db.execute("SAVEPOINT packed_width")
        attempt = db.run(f"INSERT INTO {target} VALUES ({terms})")["sqlState"]
        if attempt != "00000":
            db.execute("ROLLBACK TO SAVEPOINT packed_width")
        db.execute("RELEASE SAVEPOINT packed_width")
    selected = db.run(f"SELECT * FROM {target}" +
                      (f" ORDER BY {quoted(relation['fields'][0][1])} NULLS FIRST" if relation["fields"] else ""), describe=True)
    require(selected["sqlState"] == "00000", "postgres-width-native-observation", selected)
    count = int(db.execute(f"SELECT count(*) FROM {target}")[0][0])
    return {"default": default, "packedOrReturning": attempt, "heading": selected["columns"], "rows": selected["rows"], "count": count}


def observe(db, request):
    if "fixture" in request:
        return {"status": "fixture", "blockSize": int(db.execute("SHOW block_size")[0][0]),
                "heap": db.execute("SHOW default_table_access_method")[0][0]}
    item, statement = request["item"], request["sql"]
    allowed = {sql(item), sql(item, canonical=True)}
    if item.get("faultMode") is not None:
        allowed.add(sql(item, canonical=True, change=item["faultMode"]))
    if statement not in allowed:
        return {"status": "outside-observer-scope"}
    target = item["member"]["target"]
    db.execute("BEGIN; SET LOCAL client_min_messages=notice")
    prior_oid = None
    try:
        if item["prior"]:
            db.execute(member_sql(existing(item), canonical=True))
            _, prior_oid = catalog(db, target)
        result = db.run(statement, diagnostic_error=True)
        transaction = db.transaction()
        if result["sqlState"] == "00000":
            relation, oid = catalog(db, target)
            effects = writes(db, item, relation) if relation is not None else None
        else:
            relation = oid = effects = None
    finally:
        db.execute("ROLLBACK")
    rolled_back, _ = catalog(db, target)
    return {"status": STATUS, "sqlState": result["sqlState"], "notices": result["notices"],
            "error": result["error"], "relation": relation, "transaction": transaction,
            "afterRollback": rolled_back, "writes": effects, "relationOid": oid, "priorOid": prior_oid}


def main():
    with Session() as db:
        db.execute(f"CREATE SCHEMA {quoted(SINGLE)}")
        fields = ("server_version_num", "server_encoding", "client_encoding", "standard_conforming_strings", "search_path")
        settings = [db.execute("SHOW " + key)[0][0] for key in fields]
        print(json.dumps({"database": settings, "python": python_version(), "libpq": LIB.PQlibVersion()}), flush=True)
        for line in sys.stdin:
            value = json.loads(line)
            observed = [observe(db, entry) for entry in value] if isinstance(value, list) else observe(db, value)
            print(json.dumps(observed, ensure_ascii=False), flush=True)


if __name__ == "__main__":
    main()
