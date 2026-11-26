"""Compare SQLite STRICT and ordinary table declarations against native storage."""

import hashlib

from run import Failure, Worker, compare, require
from run_sqlite_create_table import quoted
from run_sqlite_defaults import catalogs, semantic
from sqlite_domain import RenderFault, check_render_faults, run_domain
from sqlite_engine import OBSERVER_CONTRACT, Database, ObserverAuthorizationError, typed
from sqlite_scalar_schedule import ScalarSchedule, execute_schedule
from sqlite_strict_tables_cases import (
    EXISTING, FIXTURE, SAMPLES, declaration, expected, finite_id, finite_ids, negatives, requests,
)

CONTRACT_NAME = "sqlite-strict-tables-v2.json"
CONTRACT = "0c9786f6e227573ed745d43d8b1c320b128b5b3fa4b23b3f6f40842098284302"


class StrictDatabase(Database):
    """Reads strictness through a trusted catalog query outside candidate execution."""

    policy_digest = hashlib.sha256(f"{OBSERVER_CONTRACT}:{CONTRACT}".encode()).hexdigest()

    def table_list(self, schema, name):
        require(schema in ("main", "temp"), "sqlite-strict-invalid-catalog-schema", schema)
        sql = f"PRAGMA {schema}.table_list({quoted(name)})"
        return self._observe(sql, {})["rows"]


def literal(value):
    if value is None:
        return "NULL"
    if type(value) is bytes:
        return "X'" + value.hex() + "'"
    if type(value) is str:
        return "'" + value.replace("'", "''") + "'"
    return str(value)


def measure_writes(db, target, width):
    statements = {"omitted": f"INSERT INTO {target} DEFAULT VALUES"}
    for name, value in SAMPLES.items():
        operands = ", ".join([literal(value)] * width)
        statements[name] = f"INSERT INTO {target} VALUES ({operands})"
    writes = {}
    for name, sql in statements.items():
        effect = db.observe(sql, {}, capture_evaluation_error=True)
        writes[name] = {"error": effect["evaluationError"], "changes": effect["changes"]}
    return writes


def observe(db, item, sql):
    schema, table = item["schema"], item["table"]
    target = f"{schema}.{quoted(table)}"
    db.execute("SAVEPOINT strict_table")
    try:
        creation = db.observe(sql, {}, capture_evaluation_error=True)
        require(not creation["readOnly"] and creation["changes"] == 0 and creation["evaluationError"] is None,
                "sqlite-strict-create-failed", creation)
        metadata = db.table_list(schema, table)
        columns = db.metadata("table_xinfo", schema=schema, table=table)
        require(bool(metadata) and bool(columns), "sqlite-strict-target-missing", target)
        writes = {} if table == EXISTING else measure_writes(db, target, len(item["columns"]))
        rows = db.observe(f"SELECT rowid, * FROM {target} ORDER BY rowid", {}, preserve_order=True)["rows"]
        return {"table": metadata, "columns": columns, "catalogs": catalogs(db), "writes": writes, "rows": rows}
    finally:
        db.execute("ROLLBACK TO strict_table; RELEASE strict_table")


def probe(item, reply, db, ledger=None, key=None, original=None):
    require(reply["status"] == "constructed", "sqlite-strict-positive-rejected", reply)
    roles = ("schema", "table", "columns", "strict", "ifNotExists", "operation")
    require(all(reply[role] == item[role] for role in roles), "sqlite-strict-structure-mismatch",
            {"expected": item, "actual": reply})
    if ledger is not None:
        ledger.record(key, "constructed", response=reply)
    results = {"observation": observe(db, item, reply["sql"])}
    if original is not None:
        results["original"] = observe(db, item, original)
    if ledger is not None:
        ledger.record(key, "observed", **results)
    for result in results.values():
        compare(expected(item), semantic(result))
    if ledger is not None:
        ledger.record(key, "compared")


def forms(reply):
    yield f"width/{len(reply['columns'])}"
    yield f"schema/{reply['schema']}"
    yield f"guard/{reply['ifNotExists']}"
    yield f"mode/{reply['strict']}"
    yield f"existing/{reply['table'] == EXISTING}"
    for column in reply["columns"]:
        yield f"type/{column[1]}"
        yield f"not-null/{column[2]}"
        yield "default/" + (column[3][0] if len(column) == 4 else "absent")


def required(domain):
    return {
        *(f"type/{kind}" for kind in domain["types"]), *(f"width/{width}" for width in range(1, 6)),
        "schema/main", "schema/temp", "guard/True", "guard/False", "mode/True", "mode/False",
        "existing/True", "existing/False", "not-null/True", "not-null/False", "default/absent",
        "default/null", "default/text", "default/integer", "default/blob",
    }


def controls(db):
    strict = expected(declaration([["value", "ANY", False]], True))
    ordinary = expected(declaration([["value", "ANY", False]]))
    other = {
        "lost-mode": {**strict, "table": ordinary["table"]},
        "lost-text-storage-class": {**strict, "rows": ordinary["rows"]},
        "lost-write": {**strict, "writes": {}},
    }
    compare(strict, strict)
    for name, difference in other.items():
        try:
            compare(strict, difference)
        except Failure as error:
            require(error.signal == "meaning-mismatch", "sqlite-strict-comparator-control", name)
        else:
            raise Failure("sqlite-strict-comparator-accepted-difference", name)
    catalog = db.table_list("main", EXISTING)
    require(catalog == [[typed(value) for value in ("main", EXISTING, "table", 1, 0, 0)]],
            "sqlite-strict-catalog-positive-control", catalog)
    metadata_sql = f"PRAGMA main.table_list({quoted(EXISTING)})"
    try:
        db.observe(metadata_sql, {})
    except ObserverAuthorizationError as denial:
        require(denial.action == 19, "sqlite-strict-catalog-control-denial", denial.arguments)
    else:
        raise Failure("sqlite-strict-catalog-authorization-allowed-candidate")
    return {"expected": strict, "rejected": other,
            "observer": {"trustedCatalog": catalog, "candidateSql": metadata_sql, "deniedAction": 19}}


def execute(args, specification, db, worker_factory=Worker):
    required_samples = ["omitted", *SAMPLES]
    require(required_samples == specification["finite"]["samples"], "sqlite-strict-sample-set", required_samples)
    schedule = ScalarSchedule(requests, negatives, finite_id, finite_ids, forms, required, lambda: controls(db))
    return execute_schedule(args, specification, db, worker_factory, schedule, probe)


def check_faults(args, db, runner=check_render_faults):
    table = "sql-semantics-sqlite/src/Ir/Schema/CreateTable.php"
    column = "sql-semantics-sqlite/src/Ir/Schema/TableDeclarationSql.php"
    suffix = "($this->strict ? ' STRICT' : '')"
    kind = "' ' . $column->type->value"
    strict_any = declaration([["value", "ANY", False]], True)
    ordinary_any = declaration([["value", "ANY", False]])
    strict_int = declaration([["value", "INT", False]], True)
    return runner(args, db, [
        RenderFault("drop-strict", table, suffix, "''", strict_any, "meaning-mismatch"),
        RenderFault("coerce-any-to-text", column, kind, "' TEXT'", strict_any, "meaning-mismatch"),
        RenderFault("forget-int-declared-name", column, kind, "' INTEGER'", strict_int, "meaning-mismatch"),
        RenderFault("make-ordinary-any-strict", table, suffix, "' STRICT'", ordinary_any, "meaning-mismatch"),
    ], probe)


if __name__ == "__main__":
    run_domain("strict-tables", CONTRACT_NAME, CONTRACT, FIXTURE, execute, check_faults, StrictDatabase)
