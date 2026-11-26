"""Read PostgreSQL namespace index catalog effects, identities, and insert outcomes."""

import json
from platform import python_version
import re
import sys

from postgres_create_btree_observer import CATALOG as INDEX_QUERIES, admit_condition
from postgres_create_schema_cases import CATALOG, EFFECTIVE, SESSION
from postgres_create_schema_observer import prepare_roles
from postgres_create_sequence_observer import relation
from postgres_libpq import LIB, Session
from postgres_reindex_cases import qualified, quoted
from postgres_schema_indexes_cases import DECOY, STATUS, proposals, rows, sql, tables
from postgres_schema_tables_cases import table_sql
from postgres_schema_views_observer import data_sql, read, table_state, view_state
from run import require


def insert(db, target, records):
    values = ["(" + ", ".join(map(data_sql, row)) + ")" for row in records]
    return db.run(f"INSERT INTO {target} VALUES " + ", ".join(values), diagnostic_error=True)


def initialize(db, item):
    db.execute("RESET ROLE; RESET SESSION AUTHORIZATION; SET client_min_messages=warning")
    db.execute(f"DROP SCHEMA IF EXISTS {quoted(DECOY)}, {quoted(item['namespace'])} CASCADE; CREATE SCHEMA {quoted(DECOY)}")
    target = qualified(DECOY, item["relation"])
    db.execute(table_sql(tables(item, DECOY)[0], True, False))
    seeded = insert(db, target, rows(item, True))
    require(seeded["sqlState"] == "00000", "schema-index-decoy-insert", seeded)
    db.execute(f"SET search_path={quoted(DECOY)}, public; SET client_min_messages=notice")


def membership(db, item, query, predicate):
    if predicate is None:
        return None
    fields = "(?:" + "|".join(re.escape(form) for name in item["columns"] for form in (quoted(name), name)
                             if form.startswith('"') or re.fullmatch(r"[a-z_][a-z_0-9]*", form)) + ")"
    require(admit_condition(predicate, fields, True), "schema-index-unsafe-catalog-expression", predicate)
    return [value == "t" for (value,) in db.execute(f"SELECT ({predicate}) FROM {query} ORDER BY ctid")]


def index_state(db, item, name, oid, owner):
    (scope, heap, target_oid), = db.execute(
        "SELECT n.nspname,c.relname,c.oid FROM pg_catalog.pg_index i "
        "JOIN pg_catalog.pg_class c ON c.oid=i.indrelid JOIN pg_catalog.pg_namespace n ON n.oid=c.relnamespace "
        f"WHERE i.indexrelid={oid}"
    )
    target, index = qualified(scope, heap), qualified(item["namespace"], name)
    (result,), = db.execute(INDEX_QUERIES.format(index=data_sql(index), table=data_sql(target)))
    row = json.loads(result)
    unique, nulls, valid, ready, live = (value == "true" for value in row[:5])
    positions, ordering, collations = (list(map(int, value.split())) for value in row[7:10])
    method, schema, seen, table = row[10:14]
    return {"owner": owner, "schema": schema, "index": seen, "table": table, "targetSchema": scope, "targetOid": int(target_oid),
            "accessMethod": method, "unique": unique, "nullsNotDistinct": nulls, "valid": valid, "ready": ready, "live": live,
            "keyCount": int(row[5]), "memberCount": int(row[6]), "positions": positions, "orders": ordering,
            "collations": collations, "classNames": json.loads(row[14]), "predicateSQL": row[15], "predicate": None}


def external(db, item):
    schema, name = DECOY, item["relation"]
    (oid, index_count), = db.execute(
        "SELECT c.oid,(SELECT count(*) FROM pg_catalog.pg_index WHERE indrelid=c.oid) "
        "FROM pg_catalog.pg_class c JOIN pg_catalog.pg_namespace n ON n.oid=c.relnamespace "
        f"WHERE n.nspname={data_sql(schema)} AND c.relname={data_sql(name)}"
    )
    target = qualified(schema, name)
    reply = db.run(f"SELECT * FROM {target} ORDER BY ctid", describe=True)
    require(reply["sqlState"] == "00000", "schema-index-external-rows", reply)
    heading = reply["columns"]
    require(heading == [[column, oid] for column, oid in zip(item["columns"], (23, 20, 16))],
            "schema-index-external-heading", reply)
    values = [[int(id) if id is not None else None, int(amount) if amount is not None else None,
               None if enabled is None else enabled == "t"] for id, amount, enabled in reply["rows"]]
    return int(oid), int(index_count), values


def snapshot(db, item):
    namespace = item["namespace"]
    external_id, external_count, values = external(db, item)
    namespace_rows = db.execute("SELECT oid,pg_catalog.pg_get_userbyid(nspowner) FROM pg_catalog.pg_namespace "
                                f"WHERE nspname={data_sql(namespace)}")
    details = {"owner": None, "table": None, "sequence": None, "view": None, "indexes": {},
               "namespaceOid": None, "relationOids": {}, "externalOid": external_id, "externalIndexCount": external_count, "external": values}
    if not namespace_rows:
        return details
    (oid, owner), = namespace_rows
    details.update(owner=owner, namespaceOid=int(oid))
    records = db.execute(
        "SELECT c.relname,c.oid,c.relkind,pg_catalog.pg_get_userbyid(c.relowner),am.amname "
        "FROM pg_catalog.pg_class c LEFT JOIN pg_catalog.pg_am am ON am.oid=c.relam "
        f"WHERE c.relnamespace={int(oid)}"
    )
    for name, ident, kind, principal, access in records:
        details["relationOids"][name] = int(ident)
        require(details["relationOids"][name] > 0, "schema-index-invalid-relation-oid", records)
        if kind == "r" and name == item["relation"]:
            details["table"] = table_state(db, namespace, name, int(ident), principal, access)
        elif kind == "S" and name == "tickets":
            observed = relation(db, namespace, name)
            details["sequence"] = {field: value for field, value in observed.items() if field != "oid"}
        elif kind == "v" and name == "report":
            observed = view_state(db, int(ident), principal, [])
            heading, values = read(db, namespace, name)
            details["view"] = {key: observed[key] for key in ("owner", "dependencies", "physicalDependencies")}
            details["view"].update(heading=heading, rows=values)
        elif kind == "i":
            details["indexes"][name] = index_state(db, item, name, int(ident), principal)
        else:
            require(False, "schema-index-unexpected-member", records)
    return details


def probe(db, item, state):
    target = qualified(item["namespace"], item["relation"])
    active = item["active"]
    db.execute("SAVEPOINT index_probe" if active else "BEGIN")
    seeded = insert(db, target, rows(item))
    require(seeded["sqlState"] == "00000", "schema-index-local-insert", seeded)
    for value in state["indexes"].values():
        indexed = qualified(value["targetSchema"], value["table"])
        value["predicate"] = membership(db, item, indexed, value["predicateSQL"])
    result = []
    for proposed in proposals(item):
        db.execute("SAVEPOINT write_probe")
        outcome = insert(db, target, [proposed])
        result.append({"sqlState": outcome["sqlState"], "diagnostic": outcome["error"]})
        db.execute("ROLLBACK TO SAVEPOINT write_probe; RELEASE SAVEPOINT write_probe")
    db.execute("ROLLBACK TO SAVEPOINT index_probe; RELEASE SAVEPOINT index_probe" if active else "ROLLBACK")
    return result


def permitted(item, command):
    if item.get("nativeRefusal"):
        return command == item["command"]
    return command in {sql(item), sql(item, canonical=True),
                       *(sql(item, canonical=True, change=mode) for mode in ("phase", "key", "nulls", "predicate"))}


def observe(db, request):
    item, command = request["trial"], request["sql"]
    if not permitted(item, command):
        return {"status": "outside-observer-scope"}
    initialize(db, item)
    before = snapshot(db, item)
    db.execute(f"SET SESSION AUTHORIZATION {quoted(SESSION)}; SET ROLE {quoted(EFFECTIVE)}")
    if item["active"]:
        db.execute("BEGIN")
    result = db.run(command, diagnostic_error=True)
    phase = db.transaction()
    success = result["sqlState"] == "00000"
    during = snapshot(db, item) if success else None
    writes = probe(db, item, during) if success else None
    if item["active"]:
        db.execute("ROLLBACK")
    db.execute("RESET ROLE; RESET SESSION AUTHORIZATION")
    settled = snapshot(db, item)
    if success and not item["active"]:
        for name, state in settled["indexes"].items():
            state["predicate"] = during["indexes"][name]["predicate"]
    return {"status": STATUS, "transactionAfter": phase, "before": before, "during": during, "settled": settled,
            "writeProbes": writes, **{field: result[field] for field in ("command", "sqlState", "notices", "error")}}


def main():
    with Session() as db:
        settings = [db.execute("SHOW " + key)[0][0] for key in (
                    "server_version_num", "server_encoding", "client_encoding", "standard_conforming_strings", "search_path")]
        database = db.execute("SELECT current_database()")[0][0]
        require(database == CATALOG, "postgres-schema-indexes-disposable-profile")
        roles = sorted(prepare_roles(db))
        print(json.dumps({"database": settings, "currentDatabase": database, "python": python_version(),
                          "libpq": LIB.PQlibVersion(), "roleCoordinates": roles}, ensure_ascii=False), flush=True)
        for line in sys.stdin:
            request = json.loads(line)
            measurements = [observe(db, entry) for entry in request] if isinstance(request, list) else observe(db, request)
            print(json.dumps(measurements, ensure_ascii=False), flush=True)


if __name__ == "__main__":
    main()
