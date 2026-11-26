"""Observe native sequence privilege changes and their catalog identities."""

from hashlib import sha256
import json
from platform import python_version
import sys

from postgres_create_sequence_observer import relation
from postgres_drop_sequence_cases import creation_sql
from postgres_drop_sequence_observer import STOCK, consumer, setup
from postgres_libpq import LIB, Session
from postgres_reindex_cases import qualified, quoted
from postgres_sequence_access_cases import (
    ACTOR, ALL_SEQUENCES, ALTERNATE, DATABASE, EMPTY, HEAD, LEAF, LOGIN, PRINCIPALS, ROLE_IDENTITIES, STATUS, TAIL,
    acl_order, sequence_spec, sql, target_coordinate,
)
from run import require


def prepare(db, item):
    db.execute("RESET ROLE")
    setup(db, item)
    empty = quoted(EMPTY)
    db.execute(f"CREATE SCHEMA IF NOT EXISTS {empty}")
    db.execute(creation_sql(sequence_spec(item, "bonus")))
    if item["chain"]:
        local = qualified(*target_coordinate(item, "local"))
        for field in ("local", "other"):
            schema = quoted(item["namespaces"][field])
            db.execute(f"GRANT USAGE ON SCHEMA {schema} TO {quoted(HEAD)}, {quoted(LEAF)}, {quoted(ALTERNATE)}")
        for role in ALL_SEQUENCES:
            target = qualified(*target_coordinate(item, role))
            db.execute(f"GRANT ALL ON SEQUENCE {target} TO {quoted(HEAD)} WITH GRANT OPTION")
        db.execute("SET ROLE " + quoted(HEAD))
        db.execute(f"GRANT SELECT ON SEQUENCE {local} TO {quoted(LEAF)} WITH GRANT OPTION")
        db.execute("SET ROLE " + quoted(LEAF))
        db.execute(f"GRANT SELECT ON SEQUENCE {local} TO {quoted(TAIL)}")
        db.execute("RESET ROLE")
        if item["alternate"]:
            db.execute(f"GRANT SELECT ON SEQUENCE {local} TO {quoted(ALTERNATE)} WITH GRANT OPTION")
            db.execute("SET ROLE " + quoted(ALTERNATE))
            db.execute(f"GRANT SELECT ON SEQUENCE {local} TO {quoted(LEAF)} WITH GRANT OPTION")
            db.execute("RESET ROLE")


def identities(db):
    names = ",".join("'" + name.replace("'", "''") + "'" for name in ROLE_IDENTITIES)
    return {name: int(oid) for name, oid in db.execute("SELECT rolname,oid FROM pg_catalog.pg_roles " f"WHERE rolname IN ({names})")}


def snapshot(db, item, principals):
    sequences = {}
    for role in ALL_SEQUENCES:
        value = relation(db, *target_coordinate(item, role))
        oid = value["oid"]
        file, = db.execute(f"SELECT pg_catalog.pg_relation_filenode({oid}::regclass)")[0]
        sequences[role] = {"value": value, "file": int(file), "acl": None, "aclOids": None, "shared": [], "sharedOids": []}
    rows = {sequence["value"]["oid"]: sequence for sequence in sequences.values()}
    oids = ",".join(map(str, rows))
    by_oid = {oid: name for name, oid in principals.items()}
    for oid, absent, grantor, grantee, privilege, option in db.execute(
        "SELECT c.oid, c.relacl IS NULL, x.grantor, x.grantee, x.privilege_type, x.is_grantable "
        "FROM pg_catalog.pg_class c LEFT JOIN LATERAL pg_catalog.aclexplode(c.relacl) x ON true "
        f"WHERE c.oid IN ({oids})"
    ):
        target = rows[int(oid)]
        if absent != "t" and target["acl"] is None:
            target["acl"], target["aclOids"] = [], []
        if grantor is not None:
            grantor, grantee = int(grantor), int(grantee)
            target["acl"].append([by_oid[grantor], None if grantee == 0 else by_oid[grantee], privilege, option == "t"])
            target["aclOids"].append([grantor, grantee, privilege, option == "t"])
    for oid, role, edge in db.execute(
        "SELECT objid, refobjid, deptype FROM pg_catalog.pg_shdepend "
        "WHERE dbid=(SELECT oid FROM pg_catalog.pg_database WHERE datname=current_database()) "
        "AND classid='pg_class'::regclass AND refclassid='pg_authid'::regclass "
        f"AND objid IN ({oids})"
    ):
        target = rows[int(oid)]
        target["shared"].append([by_oid[int(role)], edge])
        target["sharedOids"].append([int(role), edge])
    for target in sequences.values():
        if target["acl"] is not None:
            target["acl"].sort(key=acl_order)
            target["aclOids"].sort()
        target["shared"].sort()
        target["sharedOids"].sort()
    fixture = consumer(db, item)
    refs = db.execute(
        "SELECT d.refobjid FROM pg_catalog.pg_attrdef a JOIN pg_catalog.pg_depend d "
        "ON d.objid=a.oid AND d.classid='pg_attrdef'::regclass AND d.refclassid='pg_class'::regclass "
        "JOIN pg_catalog.pg_class c ON c.oid=d.refobjid AND c.relkind='S' "
        f"WHERE a.adrelid={fixture['consumerOid']} AND a.adnum=1"
    )
    (reference,), = refs
    return {**fixture, "sequences": sequences, "defaultOid": int(reference)}


def permitted(item, command):
    if "nativeRefusal" in item:
        return command == item["command"]
    return command in {sql(item), sql(item, canonical=True),
                       *(sql(item, canonical=True, change=mode) for mode in (
                           "decoy", "session", "drop-grant-option", "drop-only", "restrict"))}


def observe(db, request):
    item, command = request["trial"], request["sql"]
    if not permitted(item, command):
        return {"status": "outside-observer-scope"}
    prepare(db, item)
    principals = identities(db)
    before = snapshot(db, item, principals)
    if item["actor"] != LOGIN:
        db.execute("SET ROLE " + quoted(item["actor"]))
    actor, login = db.execute("SELECT current_user, session_user")[0]
    if item["active"]:
        db.execute("BEGIN")
    result = db.run(command, diagnostic_error=True)
    phase = db.transaction()
    during = None
    if result["sqlState"] == "00000":
        db.execute("RESET ROLE")
        during = snapshot(db, item, principals)
    if item["active"]:
        db.execute("ROLLBACK")
    db.execute("RESET ROLE")
    settled = during if during is not None and not item["active"] else snapshot(db, item, principals)
    return {"status": STATUS, "transactionAfter": phase, "before": before, "during": during, "settled": settled,
            "currentRole": actor, "sessionRole": login, "principalOids": principals,
            **{key: result[key] for key in ("command", "sqlState", "notices", "error")}}


def main():
    with Session() as db:
        settings = [db.execute("SHOW " + key)[0][0] for key in (
                    "server_version_num", "server_encoding", "client_encoding", "standard_conforming_strings", "search_path")]
        current, login = db.execute("SELECT current_database(), session_user")[0]
        require((current, login) == (DATABASE, LOGIN), "sequence-access-fixture-coordinate")
        stock = db.execute(STOCK)
        for role in PRINCIPALS:
            db.execute("CREATE ROLE " + quoted(role) + (" SUPERUSER" if role == ACTOR else ""))
        db.execute("CREATE SCHEMA " + quoted(EMPTY))
        stock_digest = sha256(json.dumps(stock, separators=(",", ":"), ensure_ascii=False).encode()).hexdigest()
        print(json.dumps({"database": settings, "python": python_version(), "libpq": LIB.PQlibVersion(),
                          "currentDatabase": current, "stockDigest": stock_digest, "stockCount": len(stock)}, ensure_ascii=False), flush=True)
        for line in sys.stdin:
            request = json.loads(line)
            replies = [observe(db, entry) for entry in request] if isinstance(request, list) else observe(db, request)
            print(json.dumps(replies, ensure_ascii=False), flush=True)


if __name__ == "__main__":
    main()
