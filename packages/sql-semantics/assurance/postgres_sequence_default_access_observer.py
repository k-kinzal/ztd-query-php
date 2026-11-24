"""Observe PostgreSQL default ACL entries and subsequently created objects."""

from hashlib import sha256
import json
from platform import python_version
import sys

from postgres_drop_schema_observer import literal
from postgres_drop_sequence_observer import STOCK
from postgres_libpq import LIB, Session
from postgres_reindex_cases import qualified, quoted
from postgres_sequence_default_access_cases import (
    ACTOR, BITS, CHAIN, CREATOR, DATABASE, DELIMITED, LOGIN, OTHER_CREATOR, PLAIN, POLICY_CREATORS, ROLE_IDENTITIES, STATUS, acl_order, sql,
)
from run import require


def granted_role(role):
    return "PUBLIC" if role is None else quoted(role)


def setup(db, item):
    db.execute("RESET ROLE; SET client_min_messages=warning")
    schemas = list(set(PLAIN.values()) | set(DELIMITED.values()))
    db.execute("DROP SCHEMA IF EXISTS " + ", ".join(map(quoted, sorted(schemas))) + " CASCADE")
    present = db.execute(
        "SELECT pg_catalog.pg_get_userbyid(defaclrole) FROM pg_catalog.pg_default_acl WHERE defaclnamespace=0 AND defaclobjtype='S'"
    )
    for (creator,) in present:
        require(creator in POLICY_CREATORS, "sequence-default-foreign-initial-creator", creator)
        recipients = ", ".join(map(granted_role, (*ROLE_IDENTITIES, None)))
        prefix = "ALTER DEFAULT PRIVILEGES FOR ROLE " + quoted(creator)
        db.execute(f"{prefix} REVOKE ALL ON SEQUENCES FROM {recipients} CASCADE")
        db.execute(f"{prefix} GRANT ALL ON SEQUENCES TO {quoted(creator)}")
    require(db.execute("SELECT count(*) FROM pg_catalog.pg_default_acl") == [["0"]], "sequence-default-unrestored-catalog")
    for schema in item["namespaces"].values():
        name = quoted(schema)
        db.execute(f"CREATE SCHEMA {name}; GRANT USAGE, CREATE ON SCHEMA {name} TO {quoted(CREATOR)}, {quoted(OTHER_CREATOR)}")
    existing = qualified(item["namespaces"]["west"], "old_sequence")
    db.execute(f"SET ROLE {quoted(CREATOR)}; CREATE SEQUENCE {existing} AS bigint START 17; RESET ROLE")
    if item["initialized"]:
        for creator, scope, recipient, rights, option in CHAIN:
            prefix = "ALTER DEFAULT PRIVILEGES FOR ROLE " + quoted(creator)
            if scope is not None:
                prefix += " IN SCHEMA " + quoted(item["namespaces"][scope])
            words = ", ".join(rights)
            optional = " WITH GRANT OPTION" if option else ""
            db.execute(f"{prefix} GRANT {words} ON SEQUENCES TO {granted_role(recipient)}{optional}")
    if item.get("negative") == "temp-alias":
        db.execute("CREATE TEMP SEQUENCE IF NOT EXISTS default_acl_temp_probe")
    db.execute("SET client_min_messages=notice")


def acl_rows(db, expression, source_sql, identities):
    raw = db.execute("SELECT e.grantor, e.grantee, e.privilege_type, e.is_grantable " + source_sql
                     + f" CROSS JOIN LATERAL pg_catalog.aclexplode({expression}) e")
    return sorted(([identities[int(owner)], None if grantee == "0" else identities[int(grantee)], right, option == "t"]
                   for owner, grantee, right, option in raw), key=acl_order)


def snapshot(db, identities):
    records = []
    for oid, author, node, schema, kind in db.execute(
        "SELECT d.oid,d.defaclrole,d.defaclnamespace,n.nspname,d.defaclobjtype "
        "FROM pg_catalog.pg_default_acl d LEFT JOIN pg_catalog.pg_namespace n ON n.oid=d.defaclnamespace"
    ):
        coordinate = int(oid)
        raw = db.execute(
            "SELECT refobjid,deptype FROM pg_catalog.pg_shdepend "
            "WHERE dbid=(SELECT oid FROM pg_catalog.pg_database WHERE datname=current_database()) "
            "AND classid='pg_default_acl'::regclass AND refclassid='pg_authid'::regclass "
            f"AND objid={coordinate} AND objsubid=0"
        )
        namespace = db.execute(
            "SELECT n.nspname,p.deptype FROM pg_catalog.pg_depend p JOIN pg_catalog.pg_namespace n ON n.oid=p.refobjid "
            "WHERE classid='pg_default_acl'::regclass AND refclassid='pg_namespace'::regclass "
            f"AND objid={coordinate} AND objsubid=0 AND refobjsubid=0"
        )
        grants = acl_rows(db, "d.defaclacl", "FROM (SELECT defaclacl FROM pg_catalog.pg_default_acl "
                          f"WHERE oid={coordinate}) d", identities)
        records.append({"oid": coordinate, "creator": identities[int(author)], "creatorOid": int(author),
                        "schema": schema, "schemaOid": int(node), "kind": kind, "acl": grants,
                        "shared": sorted([identities[int(role)], edge] for role, edge in raw),
                        "namespace": sorted(namespace)})
    return sorted(records, key=lambda row: (row["creator"], row["schema"] is not None, row["schema"] or ""))


def relation_acl(db, identities, schema, name):
    (oid, owner, kind, is_null), = db.execute(
        "SELECT c.oid,c.relowner,c.relkind,c.relacl IS NULL FROM pg_catalog.pg_class c "
        "JOIN pg_catalog.pg_namespace n ON n.oid=c.relnamespace "
        f"WHERE n.nspname={literal(schema)} AND c.relname={literal(name)}"
    )
    grants = None if is_null == "t" else acl_rows(db, "c.relacl", f"FROM (SELECT relacl FROM pg_catalog.pg_class WHERE oid={int(oid)}) c", identities)
    rights = None
    if kind == "S":
        probes = [f"pg_catalog.has_sequence_privilege({int(owner)}::oid,{int(oid)}::oid,{literal(right)})" for right in BITS]
        rights = [flag == "t" for flag in db.execute("SELECT " + ",".join(probes))[0]]
    return {"oid": int(oid), "creator": identities[int(owner)], "schema": schema, "kind": kind, "acl": grants, "ownerRights": rights}


def future(db, item, identities, active):
    db.execute("SAVEPOINT future_acl" if active else "BEGIN")
    result = []
    for ordinal, creator in enumerate(POLICY_CREATORS):
        name = "future_" + str(ordinal)
        db.execute("SET LOCAL ROLE " + quoted(creator))
        for scope in ("west", "east"):
            db.execute("CREATE SEQUENCE " + qualified(item["namespaces"][scope], name))
        db.execute("RESET ROLE")
        for scope in ("west", "east"):
            result.append(relation_acl(db, identities, item["namespaces"][scope], name))
    table = qualified(item["namespaces"]["west"], "future_table")
    db.execute(f"SET LOCAL ROLE {quoted(CREATOR)}; CREATE TABLE {table} (value int); RESET ROLE")
    result.append(relation_acl(db, identities, item["namespaces"]["west"], "future_table"))
    if active:
        db.execute("ROLLBACK TO SAVEPOINT future_acl; RELEASE SAVEPOINT future_acl")
    else:
        db.execute("ROLLBACK")
    return result


def permitted(item, statement):
    if item.get("nativeRefusal"):
        return statement == item["command"]
    return statement in {sql(item), sql(item, canonical=True),
                         *(sql(item, canonical=True, change=mutant) for mutant in (
                             "session-creator", "global", "drop-grant-option", "drop-option-only", "public"))}


def observe(db, request):
    item, statement = request["trial"], request["sql"]
    if not permitted(item, statement):
        return {"status": "outside-observer-scope"}
    setup(db, item)
    roles = db.execute("SELECT oid,rolname FROM pg_catalog.pg_roles WHERE rolname IN ("
                       + ",".join(map(literal, ROLE_IDENTITIES)) + ")")
    identities = {int(oid): name for oid, name in roles}
    schemas = db.execute("SELECT nspname,oid FROM pg_catalog.pg_namespace WHERE nspname IN ("
                         + ",".join(map(literal, item["namespaces"].values())) + ")")
    before = snapshot(db, identities)
    legacy = relation_acl(db, identities, item["namespaces"]["west"], "old_sequence")
    db.execute("SET ROLE " + quoted(item["actor"]))
    actor, login = db.execute("SELECT current_user, session_user")[0]
    if item["active"]:
        db.execute("BEGIN")
    result = db.run(statement, diagnostic_error=True)
    phase = db.transaction()
    during = future_during = legacy_during = None
    if result["sqlState"] == "00000":
        db.execute("RESET ROLE")
        during = snapshot(db, identities)
        legacy_during = relation_acl(db, identities, item["namespaces"]["west"], "old_sequence")
        future_during = future(db, item, identities, item["active"])
    if item["active"]:
        db.execute("ROLLBACK")
    db.execute("RESET ROLE")
    settled = during if during is not None and not item["active"] else snapshot(db, identities)
    future_settled = future_during if during is not None and not item["active"] else future(db, item, identities, False)
    return {"status": STATUS, "currentRole": actor, "sessionRole": login, "transactionAfter": phase,
            "before": before, "during": during, "settled": settled, "futureDuring": future_during, "futureSettled": future_settled,
            "legacyBefore": legacy, "legacyDuring": legacy_during,
            "legacySettled": relation_acl(db, identities, item["namespaces"]["west"], "old_sequence"),
            "principalOids": {name: oid for oid, name in identities.items()}, "namespaceOids": {name: int(oid) for name, oid in schemas},
            **{key: result[key] for key in ("command", "sqlState", "notices", "error")}}


def main():
    with Session() as db:
        settings = [db.execute("SHOW " + key)[0][0] for key in (
                    "server_version_num", "server_encoding", "client_encoding", "standard_conforming_strings", "search_path")]
        current, login = db.execute("SELECT current_database(), session_user")[0]
        require((current, login) == (DATABASE, LOGIN), "sequence-default-fixture-coordinate")
        stock = db.execute(STOCK)
        for role in ROLE_IDENTITIES:
            if role != LOGIN:
                db.execute("CREATE ROLE " + quoted(role) + (" SUPERUSER" if role == ACTOR else ""))
        db.execute(f"GRANT {quoted(CREATOR)} TO {quoted(OTHER_CREATOR)}")
        stock_digest = sha256(json.dumps(stock, separators=(",", ":"), ensure_ascii=False).encode()).hexdigest()
        print(json.dumps({"database": settings, "python": python_version(), "libpq": LIB.PQlibVersion(),
                          "currentDatabase": current, "stockDigest": stock_digest, "stockCount": len(stock)}, ensure_ascii=False), flush=True)
        for line in sys.stdin:
            request = json.loads(line)
            result = [observe(db, entry) for entry in request] if isinstance(request, list) else observe(db, request)
            print(json.dumps(result, ensure_ascii=False), flush=True)


if __name__ == "__main__":
    main()
