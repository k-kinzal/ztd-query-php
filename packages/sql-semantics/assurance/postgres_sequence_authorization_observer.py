"""Observe ordered sequence authorization and shared catalog dependencies."""

from hashlib import sha256
import json
from platform import python_version
import sys

from postgres_drop_sequence_observer import STOCK
from postgres_libpq import LIB, Session
from postgres_reconfigure_sequence_observer import prepare
from postgres_reindex_cases import qualified, quoted
from postgres_sequence_authorization_cases import (
    ACTOR, DATABASE, LOGIN, PRINCIPALS, QUOTED_PRINCIPALS, SCRATCH, STATUS, selected_role, sql,
)
from postgres_sequence_logging_observer import read as read_sequence
from run import require


def reset(db, item):
    db.execute("RESET ROLE")
    prepare(db, item)
    if item["acl"]:
        target = qualified(item["namespaces"][selected_role(item)], item["name"])
        first = quoted(item["principals"]["first"])
        db.execute(f"GRANT SELECT ON SEQUENCE {target} TO {first} WITH GRANT OPTION")
        db.execute(f"GRANT UPDATE,USAGE ON SEQUENCE {target} TO {quoted(SCRATCH)}")
    if item["effectiveRole"]:
        db.execute("SET ROLE " + quoted(ACTOR))


def read(db, item):
    state = read_sequence(db, item)
    rows = {sequence["value"]["oid"]: sequence for sequence in state["sequences"].values()}
    oids = ",".join(map(str, rows))
    for oid, absent, grantor, grantee, privilege, option in db.execute(
        "SELECT c.oid, c.relacl IS NULL, pg_catalog.pg_get_userbyid(x.grantor), pg_catalog.pg_get_userbyid(x.grantee), "
        "x.privilege_type,x.is_grantable FROM pg_catalog.pg_class c "
        "LEFT JOIN LATERAL pg_catalog.aclexplode(c.relacl) x ON true "
        f"WHERE c.oid IN ({oids})"
    ):
        sequence = rows[int(oid)]
        if absent == "t":
            sequence["acl"] = None
        else:
            entries = sequence.setdefault("acl", [])
            if grantor is not None:
                entries.append([grantor, grantee, privilege, option == "t"])
    for sequence in rows.values():
        sequence["shared"] = []
        if sequence["acl"] is not None:
            sequence["acl"].sort()
    for oid, role, relation in db.execute(
        "SELECT objid, pg_catalog.pg_get_userbyid(refobjid), deptype FROM pg_catalog.pg_shdepend "
        "WHERE dbid=(SELECT oid FROM pg_catalog.pg_database WHERE datname=current_database()) "
        "AND classid='pg_class'::regclass AND refclassid='pg_authid'::regclass "
        f"AND objid IN ({oids})"
    ):
        rows[int(oid)]["shared"].append([role, relation])
    for sequence in rows.values():
        sequence["shared"].sort()
    return state


def permitted(item, command):
    if "nativeRefusal" in item:
        return command == item["command"]
    changes = ["decoy", "session", "last"]
    if any(option not in ("logged", "unlogged") for option in item["plan"]):
        changes.append("omit-logging")
    return command in {sql(item), sql(item, canonical=True),
                       *(sql(item, canonical=True, change=mode) for mode in changes)}


def observe(db, request):
    item, command = request["trial"], request["sql"]
    if not permitted(item, command):
        return {"status": "outside-observer-scope"}
    reset(db, item)
    actor, login = db.execute("SELECT current_user, session_user")[0]
    before = read(db, item)
    if item["active"]:
        db.execute("BEGIN")
    result = db.run(command, diagnostic_error=True)
    phase = db.transaction()
    during = read(db, item) if result["sqlState"] == "00000" else None
    role = selected_role(item)
    rewritten = during is not None and during["sequences"][role]["file"] != before["sequences"][role]["file"]
    if item["active"]:
        db.execute("ROLLBACK")
    settled = during if during is not None and not item["active"] else read(db, item)
    return {"status": STATUS, "transactionAfter": phase, "before": before, "during": during, "settled": settled,
            "rewritten": rewritten, "currentRole": actor, "sessionRole": login,
            **{key: result[key] for key in ("command", "sqlState", "notices", "error")}}


def main():
    with Session() as db:
        settings = [db.execute("SHOW " + key)[0][0] for key in (
                    "server_version_num", "server_encoding", "client_encoding", "standard_conforming_strings", "search_path")]
        current, login = db.execute("SELECT current_database(), session_user")[0]
        require((current, login) == (DATABASE, LOGIN), "sequence-authorization-fixture-coordinate")
        stock = db.execute(STOCK)
        for role in (ACTOR, SCRATCH, *PRINCIPALS.values(), *QUOTED_PRINCIPALS.values()):
            db.execute("CREATE ROLE " + quoted(role) + (" SUPERUSER" if role == ACTOR else ""))
        stock_digest = sha256(json.dumps(stock, separators=(",", ":"), ensure_ascii=False).encode()).hexdigest()
        print(json.dumps({"database": settings, "python": python_version(), "libpq": LIB.PQlibVersion(),
                          "currentDatabase": current, "stockDigest": stock_digest, "stockCount": len(stock)}, ensure_ascii=False), flush=True)
        for line in sys.stdin:
            request = json.loads(line)
            result = [observe(db, entry) for entry in request] if isinstance(request, list) else observe(db, request)
            print(json.dumps(result, ensure_ascii=False), flush=True)


if __name__ == "__main__":
    main()
