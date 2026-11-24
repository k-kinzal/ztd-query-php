"""Compare complete SQLite VACUUM structure with the native filesystem and database effects."""

import copy
import hashlib
import os
import re
from pathlib import Path
from tempfile import TemporaryDirectory

from run import Failure, Worker, compare, require
from sqlite_domain import RenderFault, check_render_faults, run_domain
from sqlite_engine import Database
from sqlite_scalar_schedule import ScalarSchedule, execute_schedule
from sqlite_vacuum_cases import analysis, case, expected, finite_id, finite_ids, forms, negatives, requests, required, source
from sqlite_vacuum_fixture import fixture, prepare

CONTRACT_NAME = "sqlite-vacuum-v1.json"
CONTRACT = "fec5920ad7e98305751e10ffc6fe1fa76648ed261e491aa94d640e17a6a9c312"
FIXTURE = ""
ARCHIVE_NAME = re.compile(rb"vacuum_[0-9a-f]{16}")
MASTER_FIELDS = frozenset((b"type", b"name", b"tbl_name", b"rootpage", b"sql", b"ROWID"))
FUNCTIONS = frozenset((b"coalesce", b"ifnull", b"quote"))


class VacuumDatabase(Database):
    """Restricts SQLite to an isolated known VACUUM destination and native callbacks."""

    policy_digest = hashlib.sha256(f"{Database.policy_digest}:{CONTRACT}".encode()).hexdigest()

    def __init__(self, library):
        super().__init__(library)
        self.paths = frozenset()
        self.schemata = frozenset()
        self.native = False
        self.access = []

    def authorize(self, _, action, first, second, schema, origin):
        internal = schema is not None and ARCHIVE_NAME.fullmatch(schema) is not None
        if origin is None:
            if action == 31 and first is None and second in FUNCTIONS and schema is None:
                return 0
            if self.native:
                if action == 24 and first in self.paths and second is None and schema is None:
                    self.access.append(first.decode())
                    return 0
                if action == 22 and first == b"BEGIN" and second is None and schema is None:
                    return 0
                if action == 21 and first is None and second is None and schema is None:
                    return 0
                if action == 20 and first == b"sqlite_master" and second in MASTER_FIELDS and (internal or schema in self.schemata):
                    return 0
                if internal and ((action == 2 and first == b"entries") or
                                 (action == 18 and first in (b"sqlite_master", b"entries")) or
                                 (action == 23 and first == b"sqlite_master" and second in MASTER_FIELDS)):
                    return 0
        if self.denied is None:
            self.denied = (action, [None if field is None else field.decode(errors="replace")
                                    for field in (first, second, schema, origin)])
        return 1


def observe(template, item, sql):
    with TemporaryDirectory(prefix="semantics-ir-vacuum-") as location:
        previous = Path.cwd()
        os.chdir(location)
        try:
            return observe_in_directory(template, item, sql)
        finally:
            os.chdir(previous)


def observe_in_directory(template, item, sql):
    database = VacuumDatabase(template.library)
    try:
        prepare(database, item)
        database.paths = frozenset((b"", item["path"].encode()))
        database.schemata = frozenset((b"main", item["attachment"].encode()))
        original = fixture(database, item)
        program = database.observe("EXPLAIN " + sql, {}, preserve_order=True)["rows"]
        opcode = [[int(row[2][1]), int(row[3][1]) > 0] for row in program if row[1] == ["text", "Vacuum"]]
        require(len(opcode) <= 1, "sqlite-vacuum-unexpected-program", opcode)
        database.native = bool(opcode)
        bindings = {} if item["schema"] == "temp" else item["bindings"]
        statement = database.observe(sql, bindings, preserve_order=True, capture_evaluation_error=True)
        database.native = False
        return {"statement": statement, "opcode": opcode, "before": original, "after": fixture(database, item),
                "input": sql, "program": program, "access": database.access}
    finally:
        database.check(database.close(database.db))


def semantic(observation):
    return {key: value for key, value in observation.items() if key not in ("input", "program")}


def probe(item, reply, db, ledger=None, key=None, original=None):
    require(reply["status"] == "constructed" and reply.get("operation") == "vacuum"
            and all(reply.get(field) == item[field] for field in ("schema", "destination")),
            "sqlite-vacuum-structure", {"expected": item["destination"], "actual": reply})
    if ledger is not None:
        ledger.record(key, "constructed", response=reply)
    findings = {"observation": observe(db, item, reply["sql"])}
    if original is not None:
        findings["original"] = observe(db, item, original)
    if ledger is not None:
        ledger.record(key, "observed", **findings)
    for observation in findings.values():
        compare(expected(item), semantic(observation))
    if ledger is not None:
        ledger.record(key, "compared")
    return findings.get("original", findings["observation"])


def controls(db):
    item = case("attachment", "masked-numbered-pair")
    healthy = observe(db, item, source(item))
    model = expected(item)
    compare(model, semantic(healthy))
    wrong_schema, wrong_file, missing_parameter = (copy.deepcopy(healthy) for _ in range(3))
    wrong_schema["opcode"] = [[0, True]]
    wrong_file["after"]["destination"]["state"] = "empty"
    missing_parameter["statement"]["parameters"].pop("9")
    failures = {}
    for name, altered in (("read-wrong-database", wrong_schema), ("file-missing", wrong_file),
                          ("marker-lost", missing_parameter)):
        try:
            compare(model, semantic(altered))
        except Failure as failure:
            require(failure.signal == "meaning-mismatch", "sqlite-vacuum-comparator-signal", failure.signal)
            failures[name] = {"witness": altered, "signal": failure.signal}
        else:
            raise Failure("sqlite-vacuum-comparator-accepted", name)
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
        require(reply["status"] == "rejected" and item["refusal"] in reply.get("message", ""), "sqlite-vacuum-invalid-accepted", reply)
    else:
        probe(item, reply, db)


def check_faults(args, db, runner=check_render_faults):
    prefix = "sql-semantics-sqlite/src/Ir/Maintenance/Vacuum/"
    output, dest, registrations, lower = (prefix + name for name in ("Vacuum.php", "VacuumDestination.php",
                                                                     "VacuumRegistrations.php", "VacuumLowering.php"))
    attached = case("attachment", "literal")
    temp = case("temporary", "masked-numbered-pair")
    pass_arity = {**attached, "destination": ["evaluated", [["text", "one"], ["text", "two"]]],
                  "refusal": "exactly one source-free filename"}
    pass_role = {**attached, "schema": "temp", "refusal": "numbered registrations for temp"}
    missing = {**attached, "refusal": "requires a known schema qualifier"}
    return runner(args, db, [
        RenderFault("accept-multiple-vacuum-filenames", dest, "count($expression->values) === 1", "true", pass_arity,
                    "sqlite-vacuum-invalid-accepted"),
        RenderFault("redirect-vacuum-source-to-main", output, "$sql = 'VACUUM ' . $this->schema->quoted();",
                    "$sql = 'VACUUM main';", attached, "meaning-mismatch"),
        RenderFault("erase-vacuum-destination", output, "return $this->destination === null ? $sql : $sql . ' INTO ' . $this->destination->toString();",
                    "return $sql;", attached, "meaning-mismatch"),
        RenderFault("drop-last-inert-vacuum-parameter", registrations, r"        foreach ($this->bindings as $binding) {" + "\n            $markers[]",
                    r"        foreach (array_slice($this->bindings, 0, -1) as $binding) {" + "\n            $markers[]",
                    temp, "meaning-mismatch"),
        RenderFault("rebuild-main-from-temp-noop", output, "$sql = 'VACUUM ' . $this->schema->quoted();",
                    "$sql = 'VACUUM main';", case("temporary", "rewrite"), "meaning-mismatch"),
        RenderFault("allow-vacuum-temp-to-run-evaluated-filename", output,
                    "$destination === null || ($schema->key() === 'temp') === ($destination instanceof VacuumRegistrations)",
                    "true", pass_role, "sqlite-vacuum-invalid-accepted"),
        RenderFault("use-main-for-unknown-vacuum-scope", lower,
                    "(new PragmaScope())->resolve($operation->context, $statement->schema) ?? new Identifier('main')",
                    "new Identifier('main')", missing, "sqlite-vacuum-invalid-accepted",
                    analysis(missing, "VACUUM unknown")),
    ], fault_probe)


if __name__ == "__main__":
    run_domain("vacuum", CONTRACT_NAME, CONTRACT, FIXTURE, execute, check_faults, VacuumDatabase)
