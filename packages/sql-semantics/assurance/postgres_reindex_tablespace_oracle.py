"""Check independent storage transitions and native diagnostics for relocation."""

from postgres_reindex_tablespace_cases import STORAGE_WARNING, source_space
from postgres_reindex_verbose_cases import outcome as rebuild_outcome
from postgres_reindex_verbose_oracle import check_fixture, check_storage, observed_notices, trace
from run import Failure, require
from run_server import same

APPLICATION = {"fixture", "remote", "pg_temp"}


def logical(schema):
    return "pg_temp" if schema.startswith("pg_temp_") else schema


def placements(state):
    rows = {tuple(fields[:2]): fields[2:] for fields in state["placement"]}
    same(len(rows), len(state["placement"]))
    return rows


def check_initial(item, before):
    found = placements(before)
    target = source_space(item)
    for (schema, _), (space, kind, _, _) in found.items():
        if kind == "i" and logical(schema) in APPLICATION:
            same(target, space)
        if kind == "i" and schema == "information_schema":
            same("pg_default", space)


def compare_placement(item, before, after, plan):
    check_storage(before, after, plan)
    initial, final = placements(before), placements(after)
    same(sorted(initial), sorted(final))
    for key, (space, kind, oid, file) in initial.items():
        schema, _ = key
        next_space, next_kind, next_oid, next_file = final[key]
        same(kind, next_kind)
        if kind != "i":
            same([space, oid, file], [next_space, next_oid, next_file])
        else:
            movable = int(oid) in plan and not schema.startswith("pg_toast")
            wanted = item["tablespace"] if movable else space
            require(wanted == next_space, "postgres-relocation-storage", {"index": key, "expected": wanted, "observed": next_space})


def outcome(item):
    predicted = rebuild_outcome(item)
    predicted["status"] = "rebuild-relocation"
    if "nativeFailure" in item:
        code, message = item["nativeFailure"]
        predicted.update(sqlState=code, command=None, error=["ERROR", code, message, None, None])
    return predicted


def check_observation(item, actual, command):
    target = outcome(item)
    require(actual.get("status") == target["status"], "postgres-relocation-observer-scope", {"sql": command, "observed": actual})
    try:
        same(target, {key: actual[key] for key in target})
        before = actual["before"]
        check_fixture(item, before)
        check_initial(item, before)
        if target["sqlState"] != "00000":
            same(None, actual["during"])
            same([], actual["notices"])
            compare_placement(item, before, actual["settled"], {})
            return
        if item["relocationRole"] == "system":
            plan, notices = {}, [STORAGE_WARNING]
        else:
            plan, notices = trace(item, before)
        same(notices, observed_notices(actual["notices"]))
        compare_placement(item, before, actual["during"], plan)
        compare_placement(item, before, actual["settled"], {} if item["active"] else plan)
    except Failure as error:
        raise Failure("meaning-mismatch", {"sql": command, "expected": target, "observed": actual,
                                          "difference": error.detail}) from error
