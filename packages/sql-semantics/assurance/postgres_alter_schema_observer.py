"""Measure PostgreSQL namespace identity, ownership, ACL rows and dependent reads."""

import json
from platform import python_version
import sys

from postgres_alter_schema_cases import DECOY, DEPENDENTS, READER, STATUS, records, sql
from postgres_create_schema_cases import CATALOG, EFFECTIVE, OWNER, SESSION
from postgres_create_schema_observer import prepare_roles
from postgres_drop_schema_observer import literal, table_rows, tuples
from postgres_libpq import LIB, Session
from postgres_reindex_cases import qualified, quoted
from run import require


def coordinates(item):
    return sorted({item["old"], item["new"], DECOY})


def setup(db, item):
    cleanup = ", ".join(quoted(name) for name in (*coordinates(item), DEPENDENTS))
    db.execute(f"SET client_min_messages=warning; DROP SCHEMA IF EXISTS {cleanup} CASCADE")
    origins = [(item["old"], records(item)), (DECOY, [item["base"] + 100])]
    if item["occupied"]:
        origins.append((item["new"], [item["base"] + 200]))
    for name, rows in origins:
        target = qualified(name, "items")
        db.execute(f"CREATE SCHEMA {quoted(name)} AUTHORIZATION {quoted(OWNER)}; CREATE TABLE {target} (id integer)")
        db.execute(f"ALTER TABLE {target} OWNER TO {quoted(OWNER)}; INSERT INTO {target} VALUES {tuples((row,) for row in rows)}")
    if item["acl"]:
        db.execute(f"SET ROLE {quoted(OWNER)}; GRANT USAGE ON SCHEMA {quoted(item['old'])} TO {quoted(READER)}; RESET ROLE")
    db.execute(f"CREATE SCHEMA {quoted(DEPENDENTS)}; CREATE VIEW {qualified(DEPENDENTS, 'reads')} AS "
               f"SELECT id FROM {qualified(item['old'], 'items')}; SET client_min_messages=notice")


def values(db, table):
    result = table_rows(db, table, "id")
    require(result is not None, "postgres-alter-schema-fixture-relation", table)
    return [row[0] for row in result]


def snapshot(db, item):
    names = ", ".join(literal(name) for name in coordinates(item))
    namespaces = db.execute("SELECT oid,nspname,pg_get_userbyid(nspowner),nspacl IS NULL FROM pg_namespace "
                            f"WHERE nspname IN ({names})")
    relation_records, namespace_records = [], []
    ids = {}
    for oid, name, owner, implicit in namespaces:
        acl = None
        if implicit != "t":
            acl = [[grantor, grantee, privilege, granted == "t"] for grantor, grantee, privilege, granted in
                   db.execute("SELECT pg_get_userbyid(grantor),pg_get_userbyid(grantee),privilege_type,is_grantable "
                              f"FROM pg_namespace, LATERAL aclexplode(nspacl) WHERE oid={int(oid)}")]
        physical = db.execute("SELECT oid,relname,pg_get_userbyid(relowner) FROM pg_class "
                              f"WHERE relnamespace={int(oid)} AND relkind='r'")
        namespace_records.append([name, owner, sorted(acl) if acl is not None else None])
        for relation_oid, local, relation_owner in physical:
            rows = values(db, qualified(name, local))
            relation_records.append([name, local, relation_owner, rows])
        ids[name] = [int(oid), sorted(int(relation_oid) for relation_oid, _, _ in physical)]
    view = qualified(DEPENDENTS, "reads")
    view_id = int(db.execute(f"SELECT {literal(view)}::pg_catalog.regclass::oid")[0][0])
    return {"namespaces": sorted(namespace_records), "relations": sorted(relation_records),
            "dependent": values(db, view), "identities": ids, "viewIdentity": view_id}


def permitted(item, command):
    good = {sql(item), sql(item, canonical=True), sql(item, canonical=True, source=DECOY)}
    if item["mode"] == "owner":
        good.add(sql(item, canonical=True, principal="CURRENT_USER"))
    return command in good


def observe(db, request):
    item, command = request["trial"], request["sql"]
    if not permitted(item, command):
        return {"status": "outside-observer-scope"}
    setup(db, item)
    before = snapshot(db, item)
    db.execute(f"SET SESSION AUTHORIZATION {quoted(SESSION)}; SET ROLE {quoted(EFFECTIVE)}")
    if item["active"]:
        db.execute("BEGIN")
    result = db.run(command, diagnostic_error=True)
    timing = db.transaction()
    during = snapshot(db, item) if result["sqlState"] == "00000" else None
    if item["active"]:
        db.execute("ROLLBACK")
    db.execute("RESET ROLE; RESET SESSION AUTHORIZATION")
    settled = snapshot(db, item) if item["active"] or during is None else during
    return {"status": STATUS, "transactionAfter": timing, "before": before, "during": during, "settled": settled,
            **{key: result[key] for key in ("command", "sqlState", "notices", "error")}}


def main():
    with Session() as db:
        settings = [db.execute("SHOW " + key)[0][0] for key in (
                    "server_version_num", "server_encoding", "client_encoding", "standard_conforming_strings", "search_path")]
        role_census = sorted(prepare_roles(db))
        if db.execute(f"SELECT count(*) FROM pg_roles WHERE rolname={literal(READER)}") == [["0"]]:
            db.execute(f"CREATE ROLE {quoted(READER)}")
        print(json.dumps({"database": settings, "currentDatabase": CATALOG, "python": python_version(),
                          "libpq": LIB.PQlibVersion(), "roleCoordinates": role_census}, ensure_ascii=False), flush=True)
        for line in sys.stdin:
            request = json.loads(line)
            result = [observe(db, entry) for entry in request] if isinstance(request, list) else observe(db, request)
            print(json.dumps(result, ensure_ascii=False), flush=True)


if __name__ == "__main__":
    main()
