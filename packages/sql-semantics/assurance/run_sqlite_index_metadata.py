"""Run independent SQLite index metadata, reference-binding and native lookup comparisons."""
import copy
import hashlib

from run import Failure, Worker, compare, require
from run_sqlite_create_table import quoted
from sqlite_domain import RenderFault, check_render_faults, run_domain
from sqlite_engine import Database
from sqlite_index_metadata_cases import (
    HEADS, analysis, case, expected, finite_id, finite_ids, forms, negatives, requests, required, source,
)
from sqlite_index_metadata_fixture import LAYOUTS, declarations
from sqlite_scalar_schedule import ScalarSchedule, execute_schedule

CONTRACT_NAME = "sqlite-index-metadata-v1.json"
CONTRACT = "0deb37e09f7e04f3fe46d4b19664c3289e52ba2104d4885290474403a1531da1"
FIXTURE = ""
PRAGMAS = frozenset(name.encode() for name in HEADS)


class IndexMetadataDatabase(Database):
    """Allows the selected index metadata operation and its physical database lookup."""

    policy_digest = hashlib.sha256(f"{Database.policy_digest}:{CONTRACT}".encode()).hexdigest()

    def __init__(self, library):
        super().__init__(library)
        self.schemata = frozenset()
        self.calls = []

    def authorize(self, _, action, first, second, schema, origin):
        if origin is None and action == 19 and first is not None and first.lower() in PRAGMAS and schema in self.schemata:
            self.calls.append([first.decode().lower(), None if schema is None else schema.decode(),
                               None if second is None else second.decode()])
            return 0
        if self.denied is None:
            self.denied = (action, [None if field is None else field.decode(errors="replace")
                                    for field in (first, second, schema, origin)])
        return 1


def read_schema(db, item):
    lookup = f"SELECT sql FROM {quoted(item['attachment'])}.sqlite_schema WHERE type = 'table' AND name = ?1"
    rows = db._observe(lookup, {"1": item["table"]}, preserve_order=True)["rows"]
    require(len(rows) == 1 and rows[0][0][0] == "text", "sqlite-index-metadata-fixture-definition", rows)
    definition = rows[0][0][1]
    mode = "without-rowid" if definition.endswith(" WITHOUT ROWID") else "rowid"
    prefix = definition.removesuffix(" WITHOUT ROWID")
    exact = [layout for layout, terms in LAYOUTS.items() if prefix.endswith("(" + terms + ")")]
    require(len(exact) == 1, "sqlite-index-metadata-fixture-layout", definition)
    return [exact[0], mode]


def observe(template, item, sql):
    db = IndexMetadataDatabase(template.library)
    try:
        db.execute("ATTACH ':memory:' AS " + quoted(item["attachment"]))
        db.execute("; ".join(declarations(item)))
        db.schemata = frozenset((None, b"main", b"temp", item["attachment"].encode()))
        layout = read_schema(db, item)
        before = db._observe("PRAGMA database_list", {}, preserve_order=True)["rows"]
        statement = db.observe(sql, {}, preserve_order=True, capture_evaluation_error=True)
        require(len(db.calls) == 1, "sqlite-index-metadata-native-operation", db.calls)
        invocation = db.calls[0]
        after = db._observe("PRAGMA database_list", {}, preserve_order=True)["rows"]
        db.calls.clear()
        program = db.observe("EXPLAIN " + sql, {}, preserve_order=True)["rows"]
        require(db.calls == [invocation], "sqlite-index-metadata-program-coordinate", db.calls)
        slots = [int(row[2][1]) for row in program if row[1] == ["text", "Transaction"]]
        return {"invocation": invocation, "before": before, "after": after, "statement": statement,
                "slots": slots, "tableDefinition": layout, "program": program}
    finally:
        db.check(db.close(db.db))


def semantic(item, observation):
    trimmed = {key: value for key, value in observation.items() if key != "program"}
    if item["target"][0] == "index":
        trimmed["invocation"] = trimmed["invocation"][:1]
    return trimmed


def probe(item, reply, db, ledger=None, key=None, original=None):
    require(reply["status"] == "constructed" and reply.get("operation") == "index-metadata"
            and all(reply.get(field) == item[field] for field in ("pragma", "target")),
            "sqlite-index-metadata-structure", {"expected": item["target"], "actual": reply})
    if ledger is not None:
        ledger.record(key, "constructed", response=reply)
    observations = {"observation": observe(db, item, reply["sql"])}
    if original is not None:
        observations["original"] = observe(db, item, original)
    if ledger is not None:
        ledger.record(key, "observed", **observations)
    for observed in observations.values():
        compare(expected(item), semantic(item, observed))
    if ledger is not None:
        ledger.record(key, "compared")
    return observations.get("original", observations["observation"])


def controls(db):
    item = case("index_xinfo", "expression", "main")
    healthy = observe(db, item, source(item))
    model = expected(item)
    compare(model, semantic(item, healthy))
    erased_expression, different_schema = copy.deepcopy(healthy), copy.deepcopy(healthy)
    erased_expression["statement"]["rows"][0][1] = ["integer", "-1"]
    erased_expression["statement"]["rows"][0][-1] = ["integer", "0"]
    different_schema["slots"] = [2]
    mismatches = {}
    for label, altered in (("confuse-expression-with-auxiliary-rowid", erased_expression), ("incorrect-physical-database", different_schema)):
        try:
            compare(model, semantic(item, altered))
        except Failure as failure:
            require(failure.signal == "meaning-mismatch", "sqlite-index-metadata-comparator-signal", failure.signal)
            mismatches[label] = {"witness": altered, "signal": failure.signal}
        else:
            raise Failure("sqlite-index-metadata-comparator-accepted", label)
    return {"healthy": healthy, "rejected": mismatches}


def execute(args, contract, db, worker_factory=Worker):
    schedule = ScalarSchedule(requests, negatives, finite_id, finite_ids, forms, required, lambda: controls(db))
    return execute_schedule(args, contract, db, worker_factory, schedule, probe,
                            measured_input=lambda reply, observation: (reply, observation))


def fault_probe(item, reply, db):
    if "refusal" in item:
        require(reply["status"] == "rejected" and item["refusal"] in reply.get("message", ""),
                "sqlite-index-metadata-accepted-invalid", reply)
    else:
        probe(item, reply, db)


def check_faults(args, db, runner=check_render_faults):
    root = "sql-semantics-sqlite/src/"
    metadata = root + "Ir/Maintenance/Metadata/"
    lower, output = metadata + "IndexMetadataLowering.php", metadata + "IndexInspection.php"
    preceding = case("index_xinfo", "same-name-index-before-table")
    reused = case("index_xinfo", "primary-table", "attached", "equivalent")
    separate = case("index_xinfo", "primary-table", "attached")
    unresolved = {**separate, "refusal": "cannot establish absence in an open namespace"}
    return runner(args, db, [
        RenderFault("choose-primary-before-explicit-index", lower, "$index = $lookup->index($context, $qualified, $scopes);",
                    "$index = $lookup->relation($context, $qualified, $scopes) === null ? $lookup->index($context, $qualified, $scopes) : null;",
                    preceding, "sqlite-index-metadata-structure", analysis(preceding, source(preceding))),
        RenderFault("lose-empty-index-scope", output, "$this->target->schema",
                    "$this->target instanceof IndexTarget ? $this->target->schema : null",
                    case("index_info", "none", "temp"), "meaning-mismatch"),
        RenderFault("duplicate-equivalent-storage-key", root + "Rules/Definition/Index/ConstraintIndexes.php",
                    "$keys[$this->signature($terms)] = true;", "$keys[] = true;",
                    reused, "sqlite-index-metadata-structure", analysis(reused, source(reused))),
        RenderFault("select-sibling-primary-storage-ordinal", root + "Platform.php", "$layout->primaryStorage === $ordinal",
                    "$ordinal === 1 && $layout->primaryStorage !== null",
                    separate, "sqlite-index-metadata-structure", analysis(separate, source(separate))),
        RenderFault("drop-extended-index-fields", output, "$this->kind->value",
                    "IndexInspectionKind::Basic->value", case("index_xinfo", "expression"), "meaning-mismatch"),
        RenderFault("treat-open-index-namespace-as-absent", lower,
                    "Check::input($context->complete, 'Index metadata cannot establish absence in an open namespace.');", "",
                    unresolved, "sqlite-index-metadata-accepted-invalid",
                    analysis(unresolved, "PRAGMA extension.index_info(unknown_index)", complete=False)),
    ], fault_probe)


if __name__ == "__main__":
    run_domain("index-metadata", CONTRACT_NAME, CONTRACT, FIXTURE, execute, check_faults, IndexMetadataDatabase)
