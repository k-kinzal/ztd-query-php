"""Independently observe exact PostgreSQL constraint metadata and write effects."""

import json
from platform import python_version
import sys

from postgres_drop_schema_observer import literal
from postgres_libpq import LIB, Session
from postgres_primary_key_cases import NS, SINGLE, STATUS, VALUES, effective, sql, table_sql
from postgres_reindex_cases import qualified, quoted
from run import require

FAULTS = ("reverse", "drop-payload", "erase-timing", "drop-key", "wrong-column")


def snapshot(db, schema, physical_index_details=False):
    records = db.execute(
        "SELECT c.relname,c.oid,c.relkind,am.amname FROM pg_catalog.pg_class c "
        "JOIN pg_catalog.pg_namespace n ON n.oid=c.relnamespace "
        "LEFT JOIN pg_catalog.pg_am am ON am.oid=c.relam "
        f"WHERE n.nspname={literal(schema)} ORDER BY c.relname"
    )
    identities = {int(oid): name for name, oid, _, _ in records}
    values = {}
    links = {}
    for name, raw_oid, kind, access in records:
        oid = int(raw_oid)
        if kind == "r":
            columns = db.execute(
                "SELECT a.attnum,a.attname,a.atttypid,a.attnotnull,a.atthasdef FROM pg_catalog.pg_attribute a "
                f"WHERE a.attrelid={oid} AND a.attnum>0 AND NOT a.attisdropped ORDER BY a.attnum"
            )
            values[name] = [kind, access, [[int(n), label, int(type_oid), nn == "f", default == "t"]
                                          for n, label, type_oid, nn, default in columns]]
        elif kind == "i":
            (row,) = db.execute(
                "SELECT i.indrelid,i.indexrelid,i.indkey::text,i.indnkeyatts,i.indnatts,i.indisprimary,"
                "i.indisunique,i.indimmediate,p.conrelid,p.conindid,p.conname,"
                "array_to_json(p.conkey)::text,p.condeferrable,p.condeferred,p.contype,i.indnullsnotdistinct "
                "FROM pg_catalog.pg_index i LEFT JOIN pg_catalog.pg_constraint p ON p.conindid=i.indexrelid "
                f"AND p.conrelid=i.indrelid WHERE i.indexrelid={oid}"
            )
            (table_oid, index_oid, indkey, key_count, count, primary, unique, immediate,
             owner_oid, physical_oid, constraint, keys, deferrable, deferred, ctype, nulls_equal) = row
            values[name] = [kind, access, identities.get(int(table_oid)), constraint, json.loads(keys) if keys else None,
                            [int(x) for x in indkey.split()], int(key_count), int(count), primary == "t", unique == "t",
                            immediate == "t", deferrable == "t", deferred == "t"]
            links[name] = [int(x) if x is not None else None for x in (table_oid, index_oid, owner_oid, physical_oid)] + [ctype]
            if physical_index_details:
                attributes = db.execute(
                    "SELECT attname FROM pg_catalog.pg_attribute "
                    f"WHERE attrelid={oid} AND attnum>0 AND NOT attisdropped ORDER BY attnum"
                )
                values[name].extend([nulls_equal == "t", [label for (label,) in attributes], ctype])
        else:
            values[name] = [kind, access]
    return values, {name: oid for oid, name in identities.items()}, links


def probe(db, callback):
    db.execute("SAVEPOINT pk_probe")
    result = callback()
    db.execute("ROLLBACK TO SAVEPOINT pk_probe; RELEASE SAVEPOINT pk_probe")
    return result


def writes(db, member):
    key = member["primaryKey"]
    fields = effective(member)["columns"]
    target = qualified(*member["target"])
    field, *remaining = key["keys"]
    positions = {label: (i, kind) for i, (label, kind, _, _) in enumerate(fields)}
    coordinate, kind = positions[field]
    low, high = VALUES[kind]
    row = [VALUES[kind][0] if label in key["keys"] or not nullable else "NULL"
           for label, kind, nullable, _ in fields]
    alternate = [*row]
    alternate[coordinate] = high

    def insert(values):
        return db.run(f"INSERT INTO {target} VALUES (" + ", ".join(values) + ")")["sqlState"]

    def defaults():
        result = db.run(f"INSERT INTO {target} DEFAULT VALUES RETURNING *", describe=True)
        return [result["sqlState"], None if result["rows"] is None else [result["columns"], result["rows"]]]

    def duplicates():
        first, second = insert(row), insert(row)
        require(first == "00000", "postgres-primary-key-first-insert", first)
        resolution = db.run("SET CONSTRAINTS ALL IMMEDIATE")["sqlState"] if second == "00000" else None
        return [second, resolution]

    def distinct(swap=False):
        first, second = insert(row), insert(alternate)
        require(first == "00000" and second == "00000", "postgres-primary-key-distinct-insert", [first, second])
        if swap:
            label = quoted(field)
            return db.run(f"UPDATE {target} SET {label} = CASE WHEN {label} = {low} THEN {high} ELSE {low} END")["sqlState"]
        return db.run("SET CONSTRAINTS ALL IMMEDIATE")["sqlState"]

    def null_at(label):
        index, _ = positions[label]
        values = [*row]
        values[index] = "NULL"
        return insert(values)

    nulls = {label: probe(db, lambda: null_at(label)) for label in (field, *remaining)}
    return {"default": probe(db, defaults), "duplicate": probe(db, duplicates), "distinct": probe(db, distinct),
            "swap": probe(db, lambda: distinct(swap=True)), "nullableKey": nulls[field],
            "requiredCoordinates": sorted(name for name, status in nulls.items() if status == "23502")}


def observe(db, request):
    if "fixture" in request:
        return {"status": "fixture", "namespaces": db.execute("SELECT nspname FROM pg_catalog.pg_namespace "
                f"WHERE nspname IN ({literal(SINGLE)}, {literal(NS)}) ORDER BY nspname")}
    item, statement = request["item"], request["sql"]
    admitted = {sql(item), sql(item, canonical=True), *(sql(item, canonical=True, change=mode) for mode in FAULTS)}
    if statement not in admitted:
        return {"status": "outside-observer-scope", "permitted": sorted(admitted)}
    namespace = NS if item["compose"] else SINGLE
    db.execute("BEGIN; SET LOCAL client_min_messages=notice")
    try:
        if not item["compose"]:
            for first in item["prefix"]:
                db.execute(table_sql(first, canonical=True))
        command = db.run(statement, diagnostic_error=True)
        transaction = db.transaction()
        if command["sqlState"] == "00000":
            relations, identities, links = snapshot(db, namespace)
            member = item["member"]
            can_probe = not item.get("skipProbe") and (statement in (sql(item), sql(item, canonical=True))
                       or statement == sql(item, canonical=True, change="erase-timing"))
            witness = writes(db, member) if can_probe else None
        else:
            relations, identities, links, witness = None, {}, {}, None
    finally:
        db.execute("ROLLBACK")
    after, _, _ = snapshot(db, namespace)
    return {"status": STATUS, "sqlState": command["sqlState"], "notices": command["notices"],
            "error": command["error"], "relations": relations, "physicalOids": identities, "links": links,
            "transaction": transaction, "afterRollback": after, "witnesses": witness}


def main():
    with Session() as db:
        db.execute(f"CREATE SCHEMA {quoted(SINGLE)}")
        settings = [db.execute("SHOW " + key)[0][0] for key in (
            "server_version_num", "server_encoding", "client_encoding", "standard_conforming_strings", "search_path"
        )]
        print(json.dumps({"database": settings, "python": python_version(), "libpq": LIB.PQlibVersion()}), flush=True)
        for line in sys.stdin:
            value = json.loads(line)
            measured = [observe(db, entry) for entry in value] if isinstance(value, list) else observe(db, value)
            print(json.dumps(measured, ensure_ascii=False), flush=True)


if __name__ == "__main__":
    main()
