"""Compare SQLite attachment requests and detach selectors with native connection effects."""

import copy
import hashlib
import os
from pathlib import Path
from tempfile import TemporaryDirectory

from run import Failure, Worker, compare, require
from sqlite_connection_cases import STRUCTURE, analysis, case, expected, finite_id, finite_ids, forms, negatives, requests, required, source
from sqlite_connection_fixture import arm_transaction, existing_files, measure_transaction, prepare, snapshot
from sqlite_connection_program import inspect_program
from sqlite_domain import RenderFault, check_render_faults, run_domain
from sqlite_engine import Database
from sqlite_scalar_schedule import ScalarSchedule, execute_schedule

CONTRACT_NAME = "sqlite-connection-commands-v1.json"
CONTRACT = "1465b25a5e6233a2ad9a1fa746fe8eb6b39fe1d25e8777a94163e0409ac238bb"
FIXTURE = ""


class ConnectionDatabase(Database):
    """Allows only native attachment operations against verified fixture paths."""

    policy_digest = hashlib.sha256(f"{Database.policy_digest}:{CONTRACT}".encode()).hexdigest()

    def __init__(self, library, paths=()):
        super().__init__(library)
        self.calls = []
        self.paths = frozenset(path.encode() for path in paths)

    def authorize(self, _, action, first, second, schema, origin):
        record = [action, *(None if value is None else value.decode(errors="replace") for value in (first, second, schema, origin))]
        if origin is None and schema is None and second is None and (action == 25 or (action == 24 and (first is None or first in self.paths))):
            self.calls.append(record)
            return 0
        if self.denied is None:
            self.denied = (action, record[1:])
        return 1


def observe(template, item, sql):
    paths = (":memory:", "", item["filePath"], item["knownPath"])
    require(all(not Path(path).is_absolute() and Path(path).parent == Path(".") for path in paths),
            "sqlite-connection-unreviewed-fixture-path", paths)
    with TemporaryDirectory(prefix="semantics-ir-connection-") as place:
        previous = Path.cwd()
        os.chdir(place)
        database = ConnectionDatabase(template.library, paths)
        try:
            prepare(database, item)
            before = snapshot(database)
            before["transaction"] = arm_transaction(database, item)
            program = database.observe("EXPLAIN " + sql, {}, preserve_order=True)["rows"]
            engine, arguments = inspect_program(program, item["bind"], paths)
            require(len(database.calls) == 1, "sqlite-connection-missing-prepared-authorization", database.calls)
            authorization = database.calls[0]
            database.calls.clear()
            statement = database.observe(sql, item["bind"], preserve_order=True, capture_evaluation_error=True)
            require(database.calls == [authorization], "sqlite-connection-validating-different-operation", database.calls)
            active = measure_transaction(database, item)
            after = {**snapshot(database), "transaction": active}
            if item["transaction"] != "none":
                database.execute("ROLLBACK")
            settled = {**snapshot(database), "transaction": measure_transaction(database, item)}
            return {"before": before, "after": after, "settled": settled, "fileContents": existing_files(database, item),
                    "statement": statement, "authorization": authorization, "engine": engine,
                    "arguments": arguments, "program": program, "input": sql}
        finally:
            database.check(database.close(database.db))
            os.chdir(previous)


def semantic(native):
    return {key: value for key, value in native.items() if key not in ("program", "input")}


def probe(item, reply, db, ledger=None, key=None, original=None):
    require(reply["status"] == "constructed" and reply.get("operation") == "connection-command"
            and all(reply.get(field) == item[field] for field in STRUCTURE),
            "sqlite-connection-structure", {"expected": {field: item[field] for field in STRUCTURE}, "actual": reply})
    if ledger is not None:
        ledger.record(key, "constructed", response=reply)
    findings = {"observation": observe(db, item, reply["sql"])}
    if original is not None:
        findings["original"] = observe(db, item, original)
    if ledger is not None:
        ledger.record(key, "observed", **findings)
    for native in findings.values():
        compare(expected(item), semantic(native))
    if ledger is not None:
        ledger.record(key, "compared")
    return findings.get("original", findings["observation"])


def controls(db):
    item = case("detach-case", temp="warm", transaction="locked-alpha")
    healthy = observe(db, item, source(item))
    model = expected(item)
    compare(model, semantic(healthy))
    other_identity, changed_error, missing_file = (copy.deepcopy(healthy) for _ in range(3))
    other_identity["arguments"][0] = "beta"
    changed_error["statement"]["evaluationError"]["message"] = "database alpha is locked"
    missing_file["fileContents"].pop(item["knownPath"])
    failures = {}
    for name, altered in (("moved-detach-binding", other_identity), ("changed-native-locked-name", changed_error),
                          ("lost-independent-file", missing_file)):
        try:
            compare(model, semantic(altered))
        except Failure as failure:
            require(failure.signal == "meaning-mismatch", "sqlite-connection-comparator-signal", failure.signal)
            failures[name] = {"witness": altered, "signal": failure.signal}
        else:
            raise Failure("sqlite-connection-comparator-accepted", name)
    return {"healthy": healthy, "rejected": failures}


def pair(measured):
    reply, native = measured
    return reply, semantic(native)


def execute(args, contract, db, worker_factory=Worker):
    schedule = ScalarSchedule(requests, negatives, finite_id, finite_ids, forms, required, lambda: controls(db))
    return execute_schedule(args, contract, db, worker_factory, schedule, probe,
                            measured_input=lambda reply, observation: (reply, observation), paired_finite=pair)


def fault_probe(item, reply, db):
    if "refusal" in item:
        require(reply["status"] == "rejected" and item["refusal"] in reply.get("message", ""), "sqlite-connection-invalid-accepted", reply)
    else:
        probe(item, reply, db)


def check_faults(args, db, runner=check_render_faults):
    source_dir = "sql-semantics-sqlite/src/Ir/Connection/"
    attach, detach, target, lower = (source_dir + file for file in ("Attach.php", "Detach.php", "DetachTarget.php", "ConnectionLowering.php"))
    file = case("attach-file")
    locked = case("detach-case", transaction="locked-alpha")
    missing = {**case("detach-bare"), "refusal": "requires a known schema qualifier"}
    incorrect = {**case("detach-bare"), "resolved": "beta", "refusal": "must match its resolved database"}
    width = {**file, "fileOperand": [["text", "one"], ["text", "two"]], "refusal": "exactly one filename operand"}
    key = case("attach-file-and-key-parameters")
    # Bind only the filename so the lost key is observable as a marker-name mismatch, not a bind error.
    key["bind"].pop("7")
    return runner(args, db, [
        RenderFault("replace-attach-filename-with-empty", attach, "$this->file->toString()",
                    "\"('')\"", file, "meaning-mismatch"),
        RenderFault("detach-a-sibling-database", detach, "$this->target->name->toString()",
                    "\"'beta'\"", locked, "meaning-mismatch"),
        RenderFault("erase-attach-key", attach, "' KEY ' . $this->key->toString()", "''", key, "meaning-mismatch"),
        RenderFault("allow-conflicting-detach-identity", target, "$schema->key() === $name->name->key()", "true",
                    incorrect, "sqlite-connection-invalid-accepted"),
        RenderFault("attach-unknown-detach-to-an-existing-identity", lower,
                    "$scope = (new MaintenanceLookup())->scopes($operation->context, new Name($name->name->value))[0];",
                    r"$scope = new Name('alpha'); $name = $operands->name($operation, new \SqlSemantics\Platform\Sqlite\Statement\Connection\NameOperand($scope));",
                    missing, "sqlite-connection-invalid-accepted", analysis(missing, "DETACH unknown")),
        RenderFault("admit-a-multi-column-attach-path", attach, "count($file->values) === 1", "true", width,
                    "sqlite-connection-invalid-accepted"),
        RenderFault("discard-detach-requested-name-case", detach, "$this->target->name->toString()",
                    "strtolower($this->target->name->toString())", locked, "meaning-mismatch"),
    ], fault_probe)


if __name__ == "__main__":
    run_domain("connection-commands", CONTRACT_NAME, CONTRACT, FIXTURE, execute, check_faults, ConnectionDatabase)
