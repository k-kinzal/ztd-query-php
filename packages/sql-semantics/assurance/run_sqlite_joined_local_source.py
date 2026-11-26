"""Verify shared local VALUES definitions over native SQLite join chains."""

from copy import deepcopy
import hashlib

from run import Failure, Worker, compare, require
from run_sqlite_unique_keys import values_sql
from sqlite_domain import RenderFault, check_render_faults, run_domain
from sqlite_engine import Database
from sqlite_joined_local_source_cases import (
    DECLARATIONS, EXTERNAL, LOCAL, expected, finite_case, finite_id, finite_ids, negatives, requests,
)
from sqlite_relational_cases import children, ordered
from sqlite_scalar_schedule import ScalarSchedule, execute_schedule

CONTRACT_NAME = "sqlite-joined-local-source-v1.json"
CONTRACT = "8fbd98e0fb1f51a16b7e0aa992967b177d2c3a502ec248d330c8b6435253a2a8"
FIXTURE = "; ".join([
    "PRAGMA short_column_names=ON", "PRAGMA full_column_names=OFF", *DECLARATIONS,
    *(f'INSERT INTO main."{table}" VALUES {values_sql(rows)}' for table, rows in EXTERNAL.items() if rows),
])


class JoinedLocalDatabase(Database):
    """Uses the fixed native read-only observer and bounded VALUES fixtures."""

    policy_digest = hashlib.sha256(f"{Database.policy_digest}:{CONTRACT}".encode()).hexdigest()


def probe(item, reply, db, ledger=None, case=None, original=None):
    require(reply["status"] == "constructed", "sqlite-joined-local-positive-rejected", reply)
    fields = ("source", "joins", "locals", "columns", "where", "distinct", "orderBy", "limit")
    require(all(reply.get(field) == item[field] for field in fields), "sqlite-joined-local-structure-mismatch",
            {"expected": item, "actual": reply})
    if ledger is not None:
        ledger.record(case, "constructed", response=reply)
    observations = {"observation": db.observe(reply["sql"], item["bindings"], preserve_order=bool(item["orderBy"]))}
    if original is not None:
        observations["original"] = db.observe(original, item["bindings"], preserve_order=bool(item["orderBy"]))
    if ledger is not None:
        ledger.record(case, "observed", **observations)
    prediction = expected(item)
    for result in observations.values():
        require(ordered(result["rows"], item["orderBy"]), "sqlite-joined-local-order-mismatch", result)
        compare(prediction, {**result, "rows": sorted(result["rows"])})
    if ledger is not None:
        ledger.record(case, "compared")


def forms(reply):
    yield f"inputs/{1 + len(reply['joins'])}"
    yield f"distinct/{reply['distinct']}"
    if reply["orderBy"]:
        yield "ordered"
    locals_seen = [reply["source"], *(join[1] for join in reply["joins"])]
    for slot, source in enumerate(locals_seen):
        role = "local" if source == LOCAL else "external"
        yield f"{role}/{slot}"
    if locals_seen.count(LOCAL) > 1:
        yield "local/reused"
    for kind, _source, on in reply["joins"]:
        yield f"operation/{kind}"
        if on is None:
            yield "without-on"
        for value in children(on):
            if value[0] == "column":
                yield f"on-reference/{value[2] if len(value) == 3 else 0}"
    rows = reply["locals"][0]["rows"]
    yield f"local-rows/{len(rows)}"
    if len({repr(row) for row in rows}) < len(rows):
        yield "local/duplicates"
    for row in rows:
        for expression in row:
            yield f"value/{expression[0]}"


def required(domain):
    return {
        "inputs/2", "inputs/5", "local/0", "local/1", "local/4", "external/0", "external/1",
        "distinct/True", "distinct/False", "ordered", "local/reused", "without-on", "on-reference/0", "on-reference/4",
        "local-rows/1", "local-rows/5", "local/duplicates", "value/parameter", "value/null", "value/text",
        *(f"operation/{kind}" for kind in domain["join"]),
    }


def controls():
    item = finite_case("LEFT", "repeat", "explicit", "duplicate-null", False)
    normal = expected(item)
    compare(normal, deepcopy(normal))
    wrong = {**normal, "rows": normal["rows"][:-1]}
    try:
        compare(normal, wrong)
    except Failure as error:
        require(error.signal == "meaning-mismatch", "wrong-joined-local-control-signal", error.detail)
    else:
        raise Failure("joined-local-comparator-ignores-rows")
    return {"input": item, "healthy": normal, "mismatched": wrong}


def execute(args, contract, db, worker_factory=Worker):
    schedule = ScalarSchedule(requests, negatives, finite_id, finite_ids, forms, required, controls)
    return execute_schedule(args, contract, db, worker_factory, schedule, probe)


def check_faults(args, db, runner=check_render_faults):
    render = "sql-semantics-sqlite/src/Ir/SelectSql.php"
    repeat = finite_case("LEFT", "repeat", "explicit", "duplicate-null", False)
    right = finite_case("LEFT", "right", "explicit", "single", False)
    marker = finite_case("INNER", "repeat", "unconditional", "single", False)
    marker["locals"][0]["rows"][0][0] = ["parameter", "02"]
    return runner(args, db, [
        RenderFault("merge-local-occurrence-aliases", render,
                    "$this->alias($expression->source) . '.' . $name",
                    "$this->alias($source instanceof LocalSource ? 0 : $expression->source) . '.' . $name",
                    repeat, "meaning-mismatch"),
        RenderFault("use-shadowing-external-source", render,
                    "$source instanceof LocalSource ? self::CTE_NAME :",
                    "$source instanceof LocalSource ? '\"main\".' . self::CTE_NAME :", right, "meaning-mismatch"),
        RenderFault("ignore-on-for-local-source", render, "if ($join->on !== null)",
                    "if ($join->on !== null && !$join->source instanceof LocalSource)", right, "meaning-mismatch"),
        RenderFault("erase-first-local-marker", render, "$query = $source->query->toString();",
                    """$query = preg_replace('/\\?[0-9]+/', '0', $source->query->toString(), 1);""",
                    marker, "meaning-mismatch"),
    ], probe)


if __name__ == "__main__":
    run_domain("joined-local-source", CONTRACT_NAME, CONTRACT, FIXTURE, execute, check_faults, JoinedLocalDatabase)
