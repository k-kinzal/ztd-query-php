"""Verify native SQLite table catalog lookups against independent fixture metadata."""
import copy
import hashlib

from run import Failure, Worker, compare, require
from run_sqlite_create_table import quoted
from sqlite_domain import RenderFault, check_render_faults, run_domain
from sqlite_engine import Database
from sqlite_scalar_schedule import ScalarSchedule, execute_schedule
from sqlite_table_metadata_cases import (
    ASCII, HEADINGS, analysis, case, expected, finite_id, finite_ids, forms, negatives, requests, required, source,
)
from sqlite_table_metadata_fixture import declarations, fixture

CONTRACT_NAME = "sqlite-table-metadata-v2.json"
CONTRACT = "c33090421feb4d604fac00b2961f65f9c63db9ea619e9e6a061b05feded469b8"
FIXTURE = ""
PRAGMAS = frozenset(name.encode() for name in HEADINGS)


class TableMetadataDatabase(Database):
    """Allows only stock catalog observations and schema reads into declared fixture relations."""

    policy_digest = hashlib.sha256(f"{Database.policy_digest}:{CONTRACT}".encode()).hexdigest()

    def __init__(self, library):
        super().__init__(library)
        self.pragmas = []
        self.schemas = frozenset()
        self.owners = frozenset()

    def authorize(self, _, action, first, second, schema, origin):
        if origin is None and action == 19 and first is not None and first.lower() in PRAGMAS and schema in self.schemas:
            self.pragmas.append([first.decode().translate(ASCII), None if schema is None else schema.decode(),
                                 None if second is None else second.decode()])
            return 0
        if action == 21 and first is None and second is None and schema is None:
            return 0
        if action == 20 and (schema, first) in self.owners and second is not None:
            return 0
        if self.denied is None:
            self.denied = (action, [None if value is None else value.decode(errors="replace") for value in (first, second, schema, origin)])
        return 1


def observe(db, item, sql):
    native = TableMetadataDatabase(db.library)
    try:
        native.execute("ATTACH ':memory:' AS " + quoted(item["attachment"]))
        native.execute("; ".join(declarations(item)))
        if item["warm"]:
            native._observe("SELECT * FROM main.watch", {}, preserve_order=True)
        native.schemas = frozenset((None, b"main", b"temp", item["attachment"].encode()))
        native.owners = frozenset((table.schema.encode(), table.name.encode()) for table in fixture(item))
        before = native._observe("PRAGMA database_list", {}, preserve_order=True)["rows"]
        result = native.observe(sql, {}, preserve_order=True, capture_evaluation_error=True)
        require(len(native.pragmas) == 1, "sqlite-table-metadata-invocation", native.pragmas)
        pragma, schema, argument = native.pragmas[0]
        after = native._observe("PRAGMA database_list", {}, preserve_order=True)["rows"]
        return {"invocation": [pragma, schema, argument is not None], "rawInvocation": native.pragmas[0],
                "statement": result, "attachedBefore": before, "attachedAfter": after}
    finally:
        native.check(native.close(native.db))


def semantic(observation):
    result = {key: value for key, value in observation.items() if key != "rawInvocation"}
    if result["invocation"][0] == "table_list":
        result["statement"] = {**result["statement"], "rows": sorted(result["statement"]["rows"])}
    return result


def probe(item, reply, db, ledger=None, key=None, original=None):
    wanted = {"target": item["target"], "schema": item["schema"], "pragma": item["pragma"],
              "filter": item["argument"] if item["pragma"] == "table_list" else None}
    require(reply["status"] == "constructed" and reply.get("operation") == "table-metadata"
            and all(reply.get(field) == value for field, value in wanted.items()), "sqlite-table-metadata-structure",
            {"expected": wanted, "actual": reply})
    if ledger is not None:
        ledger.record(key, "constructed", response=reply)
    measurements = {"observation": observe(db, item, reply["sql"])}
    if original is not None:
        measurements["original"] = observe(db, item, original)
    if ledger is not None:
        ledger.record(key, "observed", **measurements)
    prediction = expected(item)
    for measurement in measurements.values():
        compare(prediction, semantic(measurement))
    if ledger is not None:
        ledger.record(key, "compared")
    return measurements.get("original", measurements["observation"])


def controls(db):
    item = case("table_xinfo", "shadow-name", "main")
    prediction = expected(item)
    healthy = observe(db, item, source(item))
    compare(prediction, semantic(healthy))
    omitted_label = copy.deepcopy(healthy)
    omitted_label["statement"]["names"][-1] = "public"
    reordered = copy.deepcopy(healthy)
    reordered["statement"]["rows"].reverse()
    altered_default = copy.deepcopy(healthy)
    altered_default["statement"]["rows"][-1][4] = ["null", None]
    findings = {}
    for label, witness in (("misnamed-heading", omitted_label), ("reordered-ordinal", reordered), ("missing-explicit-null-default", altered_default)):
        try:
            compare(prediction, semantic(witness))
        except Failure as error:
            require(error.signal == "meaning-mismatch", "sqlite-table-metadata-comparator-signal", error.signal)
            findings[label] = {"witness": witness, "signal": error.signal}
        else:
            raise Failure("sqlite-table-metadata-comparator-accepted", label)
    return {"healthy": healthy, "rejected": findings}


def execute(args, specification, db, worker_factory=Worker):
    schedule = ScalarSchedule(requests, negatives, finite_id, finite_ids, forms, required, lambda: controls(db))
    return execute_schedule(args, specification, db, worker_factory, schedule, probe,
                            measured_input=lambda reply, observation: (reply, observation))


def fault_probe(item, reply, db):
    if "refusal" in item:
        require(reply["status"] == "rejected" and item["refusal"] in reply.get("message", ""),
                "sqlite-table-metadata-accepted-invalid", reply)
    else:
        probe(item, reply, db)


def check_faults(args, db, runner=check_render_faults):
    root = "sql-semantics-sqlite/src/Ir/Maintenance/Metadata/"
    output, lower, listing = root + "TableInspection.php", root + "TableMetadataLowering.php", root + "TableListing.php"
    shadow = case("table_info", "shadow-name", temp="populated")
    invalid = {**case("table_info", "shadow-name"), "schema": "temp", "refusal": "outside the explicit schema"}
    named = case("table_list", "shadow-name", temp="populated")
    implicit_missing = {**case(), "refusal": "cannot prove absence in an open namespace"}
    return runner(args, db, [
        RenderFault("accept-metadata-contradictory-coordinate", output,
                    "$schema === null || $target->schema->key() === $schema->key()", "true",
                    invalid, "sqlite-table-metadata-accepted-invalid"),
        RenderFault("skip-temporary-metadata-precedence", lower, "$lookup->scopes($context, $qualifier)",
                    "array_reverse($lookup->scopes($context, $qualifier))", shadow, "sqlite-table-metadata-structure",
                    analysis(shadow, source(shadow))),
        RenderFault("restrict-listing-name-to-first-schema", listing, "$this->schema === null ? ''",
                    "$this->schema === null ? ($this->nameFilter === null ? '' : 'main.')", named, "meaning-mismatch"),
        RenderFault("omit-extended-column-output", output, "$this->kind->value",
                    "($this->kind === TableInspectionKind::ExtendedColumns ? TableInspectionKind::Columns : $this->kind)->value",
                    case("table_xinfo", "shadow-name"), "meaning-mismatch"),
        RenderFault("drop-table-metadata-qualifier", output, "$qualifier . $this->kind->value", "$this->kind->value",
                    case("table_info", "shadow-name", "main", "populated"), "meaning-mismatch"),
        RenderFault("emit-preferred-schema-table-filter", listing, "$this->nameFilter->quoted()",
                    "($this->nameFilter->key() === 'sqlite_master' ? new Identifier('sqlite_schema') : $this->nameFilter)->quoted()",
                    case("table_list", "ordinary-catalog"), "meaning-mismatch"),
        RenderFault("publish-unresolved-metadata-absence", lower,
                    "Check::input($context->complete, 'Metadata lookup cannot prove absence in an open namespace.');", "",
                    implicit_missing, "sqlite-table-metadata-accepted-invalid",
                    analysis(implicit_missing, "PRAGMA main.table_info(absent)", complete=False)),
    ], fault_probe)


if __name__ == "__main__":
    run_domain("table-metadata", CONTRACT_NAME, CONTRACT, FIXTURE, execute, check_faults, TableMetadataDatabase)
