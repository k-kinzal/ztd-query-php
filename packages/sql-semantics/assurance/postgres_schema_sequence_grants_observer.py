"""Read ordered PostgreSQL namespace grants from native catalog and privilege queries."""

import json
from platform import python_version
import sys

from postgres_create_schema_cases import CATALOG, EFFECTIVE, SESSION
from postgres_create_schema_observer import prepare_roles
from postgres_create_sequence_observer import relation
from postgres_drop_schema_observer import literal
from postgres_libpq import LIB, Session
from postgres_reindex_cases import qualified, quoted
from postgres_schema_sequence_grants_cases import BITS, DECOY, ROLE_PROBES, ROLES, STATUS, acl_order, generators, owner, sql
from postgres_create_sequence_cases import sql as sequence_sql
from run import require


def initialize_roles(db):
    prepare_roles(db)
    db.execute("SET client_min_messages=warning")
    for role in ROLES:
        if db.execute(f"SELECT count(*) FROM pg_catalog.pg_roles WHERE rolname={literal(role)}") == [["0"]]:
            db.execute("CREATE ROLE " + quoted(role))
    rows = db.execute("SELECT rolname,oid,rolsuper FROM pg_catalog.pg_roles WHERE rolname IN ("
                      + ", ".join(map(literal, ROLES)) + ")")
    return {int(oid): name for name, oid, _ in rows}, sorted([[name, superuser] for name, _, superuser in rows])


def initialize(db, item):
    db.execute("RESET ROLE; RESET SESSION AUTHORIZATION; SET client_min_messages=warning")
    db.execute(f"DROP SCHEMA IF EXISTS {quoted(DECOY)}, {quoted(item['namespace'])} CASCADE")
    db.execute(f"CREATE SCHEMA {quoted(DECOY)} AUTHORIZATION {quoted(owner(item))}")
    db.execute(f"GRANT USAGE ON SCHEMA {quoted(DECOY)} TO PUBLIC")
    db.execute(f"SET ROLE {quoted(owner(item))}")
    for sequence in generators(item, True):
        db.execute(sequence_sql(sequence, canonical=True))
    db.execute(f"RESET ROLE; SET search_path={quoted(DECOY)}, public; SET client_min_messages=notice")


def rights(db, oid, names):
    by_name = {name: role for role, name in names.items()}
    expressions = ", ".join(f"pg_catalog.has_sequence_privilege({by_name[role]}::oid,{oid}::oid,{literal(kind)})"
                            for role in ROLE_PROBES for kind in BITS)
    flags, = db.execute("SELECT " + expressions)
    require(len(flags) == len(ROLE_PROBES) * len(BITS) and all(flag in ("t", "f") for flag in flags),
            "schema-grant-unexpected-effective-privilege", flags)
    return [[name, [flag == "t" for flag in flags[index:index + len(BITS)]]]
            for index, name in zip(range(0, len(flags), len(BITS)), ROLE_PROBES)]


def details(db, schema, name, identity, names):
    observed = relation(db, schema, name)
    require(observed is not None and observed["oid"] == identity, "schema-grant-sequence-identity", observed)
    flags = db.execute(
        "SELECT c.relacl IS NULL, a.grantor, a.grantee, a.privilege_type, a.is_grantable "
        "FROM pg_catalog.pg_class c LEFT JOIN LATERAL pg_catalog.aclexplode(c.relacl) a ON true "
        f"WHERE c.oid={identity}"
    )
    absent = flags[0][0] == "t"
    raw_acl = None if absent else sorted([[int(source), int(sink), right, option == "t"] for _, source, sink, right, option in flags
                                          if source is not None])
    acl = None if absent else sorted([[names[source], None if sink == 0 else names[sink], right, option]
                                      for source, sink, right, option in raw_acl], key=acl_order)
    dependencies = db.execute(
        "SELECT refobjid,deptype FROM pg_catalog.pg_shdepend "
        "WHERE dbid=(SELECT oid FROM pg_catalog.pg_database WHERE datname=current_database()) "
        "AND classid='pg_class'::regclass AND refclassid='pg_authid'::regclass "
        f"AND objid={identity}"
    )
    edges = sorted((int(role), dependency) for role, dependency in dependencies)
    return {"coordinate": [schema, name], "oid": identity, "acl": acl, "aclOids": raw_acl,
            "effective": rights(db, identity, names), "shared": sorted([[names[role], dependency] for role, dependency in edges]),
            "sharedOids": [list(edge) for edge in edges], "value": {k: v for k, v in observed.items() if k != "oid"}}


def snapshot(db, item, names):
    namespace = literal(item["namespace"])
    space = db.execute("SELECT oid,nspowner FROM pg_catalog.pg_namespace " f"WHERE nspname={namespace}")
    scope, owner = (None, None) if not space else tuple(map(int, space[0]))
    contents = db.execute(
        "SELECT n.nspname,c.relname,c.oid,c.relkind FROM pg_catalog.pg_class c "
        "JOIN pg_catalog.pg_namespace n ON n.oid=c.relnamespace "
        f"WHERE n.nspname IN ({namespace}, {literal(DECOY)})"
    )
    # The rejected fixtures can declare a table or an index. Unexpected success must remain observable.
    others = [[schema, name, int(oid), kind] for schema, name, oid, kind in contents if kind != "S"]
    return {"owner": None if owner is None else names[owner], "namespaceOid": scope, "others": sorted(others),
            "sequences": [details(db, schema, name, int(oid), names)
                          for schema, name, oid, kind in sorted(contents) if kind == "S"]}


def permitted(item, command):
    if item.get("nativeRefusal"):
        return command == item["command"]
    return command in {sql(item), sql(item, canonical=True),
                       *(sql(item, canonical=True, change=mode) for mode in ("phase", "decoy", "recipient", "option"))}


def observe(db, request, names):
    item, command = request["trial"], request["sql"]
    if not permitted(item, command):
        return {"status": "outside-observer-scope"}
    initialize(db, item)
    before = snapshot(db, item, names)
    db.execute(f"SET SESSION AUTHORIZATION {quoted(SESSION)}; SET ROLE {quoted(EFFECTIVE)}")
    actor, session = db.execute("SELECT current_user, session_user")[0]
    if item["active"]:
        db.execute("BEGIN")
    command = db.run(command, diagnostic_error=True)
    phase = db.transaction()
    during = snapshot(db, item, names) if command["sqlState"] == "00000" else None
    if item["active"]:
        db.execute("ROLLBACK")
    db.execute("RESET ROLE; RESET SESSION AUTHORIZATION")
    settled = snapshot(db, item, names)
    return {"status": STATUS, "transactionAfter": phase, "actor": actor, "session": session,
            "principalOids": {name: oid for oid, name in names.items()}, "before": before, "during": during, "settled": settled,
            **{field: command[field] for field in ("command", "sqlState", "notices", "error")}}


def main():
    with Session() as db:
        settings = [db.execute("SHOW " + key)[0][0] for key in (
                    "server_version_num", "server_encoding", "client_encoding", "standard_conforming_strings", "search_path")]
        database = db.execute("SELECT current_database()")[0][0]
        require(database == CATALOG, "postgres-schema-grant-disposable-profile")
        identities, roles = initialize_roles(db)
        print(json.dumps({"database": settings, "currentDatabase": database, "python": python_version(),
                          "libpq": LIB.PQlibVersion(), "roleCoordinates": roles}, ensure_ascii=False), flush=True)
        for line in sys.stdin:
            request = json.loads(line)
            measurements = [observe(db, entry, identities) for entry in request] if isinstance(request, list) else observe(db, request, identities)
            print(json.dumps(measurements, ensure_ascii=False), flush=True)


if __name__ == "__main__":
    main()
