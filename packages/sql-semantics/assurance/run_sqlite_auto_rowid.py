"""Measure native rowid allocation, persistent sequences and transaction effects."""

from run import Failure, Worker, compare, require
from run_sqlite_create_table import quoted
from run_sqlite_defaults import semantic
from run_sqlite_primary_keys import KeyDatabase, arguments, measure_write
from sqlite_auto_rowid_cases import (
    FIXTURE, MAXIMUM, expected, finite_id, finite_ids, item, negatives, placement_of, requests, values,
)
from sqlite_domain import RenderFault, check_render_faults, run_domain
from sqlite_scalar_schedule import ScalarSchedule, execute_schedule

CONTRACT_NAME = "sqlite-auto-rowid-v1.json"
CONTRACT = "530a93ca4c873c4560f3401a65d5aeb396a2ff5275881c76c4a089e39e89d4c7"


def has_sequence(db, schema):
    sql = f"SELECT name FROM {schema}.sqlite_schema WHERE name = 'sqlite_sequence' AND type = 'table'"
    return bool(db.observe(sql, {})["rows"])


def sequence(db, schema, table, exists):
    if not exists:
        return []
    sql = f"SELECT name, seq FROM {schema}.sqlite_sequence WHERE name = ?1"
    return db.observe(sql, {"1": table})["rows"]


def allocate_statement(request, target, slot, token):
    supplied = values(request, token, None)
    if request["history"]["input"] == "null":
        return f"INSERT INTO {target} VALUES {arguments(supplied)}"
    names = [quoted(field[0]) for index, field in enumerate(request["columns"]) if index != slot]
    data = [value for index, value in enumerate(supplied) if index != slot]
    if not names:
        return f"INSERT INTO {target} DEFAULT VALUES"
    return f"INSERT INTO {target} (" + ", ".join(names) + ") VALUES " + arguments(data)


def observe(db, request, sql):
    schema, table, key = request["schema"], request["table"], request["primaryKey"]
    target = f"{schema}.{quoted(table)}"
    slot = key["terms"][0][0]
    high = request["history"]["high"]
    history = request["history"]
    # A disposable connection gives the observation its own committed history.
    fixture = KeyDatabase(db.library)
    try:
        creation = fixture.create(sql, schema, table)
        require(not creation["readOnly"] and creation["evaluationError"] is None and creation["changes"] == 0,
                "sqlite-auto-rowid-create-failed", creation)
        catalog = {"table": fixture.table_list(schema, table),
                   "columns": fixture.metadata("table_xinfo", schema=schema, table=table),
                   "index": fixture.key_index(schema, table)}
        require(bool(catalog["columns"]), "sqlite-auto-rowid-target-mismatch", {"target": target, "catalog": catalog})
        exists = has_sequence(fixture, schema)
        seq = {"created": sequence(fixture, schema, table, exists)}

        def write(token, rowid):
            return measure_write(fixture, f"INSERT INTO {target} VALUES {arguments(values(request, token, rowid))}")

        writes = {"seed": write(100, 2), "highest": write(200, high)}
        historical = fixture.observe(f"SELECT rowid FROM {target} WHERE rowid = ?1", {"1": high})["rows"]
        require(historical == [[["integer", str(high)]]], "sqlite-auto-rowid-highest-mismatch", historical)
        writes["delete-highest"] = measure_write(fixture, f"DELETE FROM {target} WHERE rowid = {high}")
        seq["deleted"] = sequence(fixture, schema, table, exists)
        first = allocate_statement(request, target, slot, 300)
        writes["first"] = measure_write(fixture, first)
        seq["first"] = sequence(fixture, schema, table, exists)

        fixture.execute("BEGIN")
        require(not fixture.get_autocommit(fixture.db), "sqlite-auto-rowid-missing-transaction")
        writes["bump"] = write(400, 1000)
        writes["delete-bump"] = measure_write(fixture, f"DELETE FROM {target} WHERE rowid = 1000")
        seq["insideBump"] = sequence(fixture, schema, table, exists)
        action = "ROLLBACK" if history["rollback"] else "COMMIT"
        fixture.execute(action)
        seq["afterTransaction"] = sequence(fixture, schema, table, exists)
        writes["second"] = measure_write(fixture, allocate_statement(request, target, slot, 600))
        seq["final"] = sequence(fixture, schema, table, exists)
        state = fixture.observe(f"SELECT rowid, * FROM {target} ORDER BY rowid", {}, preserve_order=True)
        return {"catalog": catalog, "writes": writes, "sequenceExists": exists, "sequence": seq,
                "state": {"names": state["names"], "rows": state["rows"]},
                "history": {"rollback": action == "ROLLBACK", "high": int(historical[0][0][1]),
                            "input": "null" if first.startswith(f"INSERT INTO {target} VALUES (") else "omitted"},
                "autocommit": bool(fixture.get_autocommit(fixture.db))}
    finally:
        fixture.close(fixture.db)


def effects(observation):
    return {**observation, "catalog": semantic(observation["catalog"])}


def probe(record, reply, db, ledger=None, key=None, original=None):
    require(reply["status"] == "constructed", "sqlite-auto-rowid-positive-rejected", reply)
    roles = ("schema", "table", "columns", "strict", "withoutRowid", "primaryKey", "ifNotExists", "operation")
    require(all(reply[role] == record[role] for role in roles), "sqlite-auto-rowid-structure-mismatch",
            {"expected": record, "actual": reply})
    if ledger is not None:
        ledger.record(key, "constructed", response=reply)
    results = {"observation": observe(db, record, reply["sql"])}
    if original is not None:
        results["original"] = observe(db, record, original)
    if ledger is not None:
        ledger.record(key, "observed", **results)
    prediction = expected(record)
    for observation in results.values():
        compare(prediction, effects(observation))
    if ledger is not None:
        ledger.record(key, "compared")
    return results["observation"]


def controls():
    example = item(rollback=True)
    correct = expected(example)
    alternatives = {
        "forget-deleted-highest": {**correct, "state": expected(item(automatic=False, rollback=True))["state"]},
        "keep-rolled-back-sequence": {**correct, "sequence": expected(item())["sequence"]},
        "drop-sequence-record": {**correct, "sequenceExists": False},
    }
    compare(correct, correct)
    for name, altered in alternatives.items():
        try:
            compare(correct, altered)
        except Failure as error:
            require(error.signal == "meaning-mismatch", "sqlite-auto-rowid-comparator-signal", name)
        else:
            raise Failure("sqlite-auto-rowid-comparator-accepted", name)
    return {"input": example, "expected": correct, "rejected": alternatives}


def forms(observed):
    reply, observation = observed
    key = reply["primaryKey"]
    yield "schema/" + reply["schema"]
    yield f"strict/{reply['strict']}"
    yield "placement/" + placement_of(reply)
    yield f"allocation/{key['autoIncrement']}"
    yield "conflict/" + key["conflict"]
    yield f"width/{len(reply['columns'])}"
    yield f"slot/{key['terms'][0][0]}"
    yield "input/" + observation["history"]["input"]
    yield f"rollback/{observation['history']['rollback']}"
    yield f"maximum/{observation['history']['high'] == MAXIMUM}"


def required(domain):
    return {
        *(f"width/{length}" for length in range(1, 6)),
        *(f"schema/{schema}" for schema in domain["schemas"]),
        *("placement/" + placement for placement in domain["placement"]),
        "slot/0", "slot/3", "strict/True", "strict/False", "allocation/True", "allocation/False",
        "input/omitted", "input/null", "rollback/True", "rollback/False", "maximum/True", "maximum/False",
        "conflict/ABORT", "conflict/FAIL", "conflict/IGNORE", "conflict/REPLACE", "conflict/ROLLBACK",
    }


def execute(args, specification, db, worker_factory=Worker):
    schedule = ScalarSchedule(requests, negatives, finite_id, finite_ids, forms, required, controls)
    return execute_schedule(args, specification, db, worker_factory, schedule, probe,
                            measured_input=lambda reply, observation: (reply, observation))


def check_faults(args, db, runner=check_render_faults):
    key = "sql-semantics-sqlite/src/Ir/Schema/Key/TableKeySql.php"
    table = "sql-semantics-sqlite/src/Ir/Schema/CreateTable.php"
    inline = ". ($key->autoIncrement ? ' AUTOINCREMENT' : '');"
    subclause = " . $auto . ')'"
    return runner(args, db, [
        RenderFault("drop-inline-autoincrement", key, inline, ". '';", item(), "meaning-mismatch"),
        RenderFault("drop-table-autoincrement", key, subclause, " . ')'",
                    item(placement="table-descending", high=MAXIMUM), "meaning-mismatch"),
        RenderFault("force-automatic-allocation", key, inline, ". ' AUTOINCREMENT';",
                    item(automatic=False), "meaning-mismatch"),
        RenderFault("move-auto-sequence-to-other-schema", table, "$this->schema->quoted()", "'temp'",
                    item(), "sqlite-auto-rowid-target-mismatch"),
    ], probe)


if __name__ == "__main__":
    run_domain("auto-rowid", CONTRACT_NAME, CONTRACT, FIXTURE, execute, check_faults, KeyDatabase)
