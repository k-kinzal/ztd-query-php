"""Compare SQLite generation graphs against independent catalog and value expectations."""

from copy import deepcopy
import hashlib

from run import Failure, Worker, compare, require
from run_sqlite_create_table import quoted
from run_sqlite_folded_checks import binding_values
from run_sqlite_unique_keys import UniqueDatabase, values_sql
from sqlite_domain import RenderFault, check_render_faults, run_domain
from sqlite_engine import ObserverAuthorizationError
from sqlite_generated_column_cases import (
    TABLE, expected, finite_case, finite_id, finite_ids, key_kind, negatives, references, requests, structure, vectors,
)
from sqlite_scalar_schedule import ScalarSchedule, execute_schedule

CONTRACT_NAME = "sqlite-generated-columns-v2.json"
CONTRACT = "726bb6dedb51a5535f4494046043e5777b9f16984fbd12146c2c75ec61c9a9fe"
FIXTURE = "PRAGMA legacy_alter_table=OFF; PRAGMA writable_schema=OFF;"


class GeneratedColumnDatabase(UniqueDatabase):
    """Permits each fixed fixture's generated uniqueness index during candidate creation."""

    policy_digest = hashlib.sha256(f"{UniqueDatabase.policy_digest}:{CONTRACT}".encode()).hexdigest()

    def declare(self, sql, schema, bindings, has_index):
        require(schema in ("main", "temp"), "invalid-generated-column-schema", schema)
        if has_index:
            opcode = 1 if schema == "main" else 3
            self.implicit_indexes = {(opcode, f"sqlite_autoindex_{TABLE}_1".encode(), TABLE.encode(), schema.encode())}
        try:
            return self.observe(sql, bindings, capture_evaluation_error=True)
        finally:
            self.implicit_indexes = frozenset()


def rejected_write(db, sql):
    try:
        db.observe(sql, {}, capture_evaluation_error=True)
    except ObserverAuthorizationError:
        raise
    except RuntimeError as error:
        return str(error)
    return None


def drop_dependency(db, target):
    # SQLite 3.47.2 diagnoses the same decoded missing column with two spellings.
    sql = f"ALTER TABLE {target} DROP COLUMN shifted"
    try:
        db.execute(sql)
    except RuntimeError as error:
        prefix = f"SQLite 1: error in table {TABLE} after drop column: no such column: "
        require(str(error) in (prefix + "shifted", prefix + '"shifted" - should this be a string literal in single-quotes?'),
                "unexpected-generated-column-drop-error", str(error))
        return {"code": 1, "column": "shifted"}, str(error)
    return None, None


def observe(db, item, sql, bindings, prediction):
    schema = item["schema"]
    require(schema in ("main", "temp"), "invalid-generated-column-schema", schema)
    target = f"{schema}.{quoted(TABLE)}"
    db.execute("SAVEPOINT generated_column")
    try:
        unique = key_kind(item, "unique") is not None
        observed = {
            "create": db.declare(sql, schema, bindings, unique),
            "columns": db.metadata("table_xinfo", schema=schema, table=TABLE),
            "tableOptions": db.table_list(schema, TABLE),
        }
        compare({name: prediction[name] for name in observed}, observed)
        insert = f"INSERT INTO {target} (base, peer, seq) VALUES "
        select = f"SELECT * FROM {target} ORDER BY seq"
        write = db.observe(insert + values_sql(vectors(item)), {}, capture_evaluation_error=True)
        require(write["evaluationError"] is None, "unexpected-generated-column-insert-error", write)
        insert_changes = write["changes"]
        inserted = db.observe(select, {}, preserve_order=True)
        ordinary = "peer" if key_kind(item, "primary") else "base"
        changed = db.observe(f"UPDATE {target} SET {ordinary}={ordinary}+2 WHERE seq=2", {}, capture_evaluation_error=True)
        require(changed["evaluationError"] is None, "unexpected-generated-column-update-error", changed)
        updated = db.observe(select, {}, preserve_order=True)
        explicit = {
            "insert": rejected_write(db, f"INSERT INTO {target} (calc) VALUES (444)"),
            "update": rejected_write(db, f"UPDATE {target} SET calc=555 WHERE seq=1"),
        }
        constraints = []
        if unique:
            for data in ((None, 10, 22), (vectors(item)[0][0], 15, 23)):
                write = db.observe(insert + values_sql([data]), {}, capture_evaluation_error=True)
                constraints.append(write["evaluationError"])
        # These ALTER commands are authored by the verifier and run separately from candidate SQL.
        db.execute(f"ALTER TABLE {target} RENAME COLUMN {ordinary} TO shifted")
        names = db.observe(select, {}, preserve_order=True)["names"]
        db.execute("SAVEPOINT dependent_drop")
        try:
            trap, diagnostic = drop_dependency(db, target)
            transient = db.observe(select, {}, preserve_order=True)
        finally:
            # A failed ALTER can leave an inconsistent connection-local schema cache until rollback.
            db.execute("ROLLBACK TO dependent_drop; RELEASE dependent_drop")
        state = db.observe(select, {}, preserve_order=True)
        measured = {
            **observed, "names": inserted["names"], "inserted": inserted["rows"], "updated": updated["rows"],
            "insertChanges": insert_changes,
            "updateChanges": changed["changes"],
            "explicitWrite": explicit, "constraintErrors": constraints, "renamedNames": names,
            "afterFailedDrop": state["rows"], "dropDependency": trap,
        }
        return measured, {"dropDiagnostic": diagnostic, "beforeSavepointRecovery": transient}
    finally:
        db.execute("ROLLBACK TO generated_column; RELEASE generated_column")


def probe(item, reply, db, ledger=None, case=None, original=None):
    require(reply["status"] == "constructed", "sqlite-generated-column-positive-rejected", reply)
    model = structure(item)
    retained = {field: reply.get(field) for field in model}
    require(retained == model, "sqlite-generated-column-structure-mismatch", {"expected": model, "actual": retained})
    if ledger is not None:
        ledger.record(case, "constructed", response=reply)
    prediction = expected(item)
    all_bindings = [{}, *binding_values(prediction["create"]["parameters"])]
    results = {"observation": [observe(db, item, reply["sql"], bindings, prediction) for bindings in all_bindings]}
    if original is not None:
        results["original"] = [observe(db, item, original, bindings, prediction) for bindings in all_bindings]
    measured = {source: [outcome for outcome, _ in cases] for source, cases in results.items()}
    details = {source: [diagnostic for _, diagnostic in cases] for source, cases in results.items()}
    if ledger is not None:
        ledger.record(case, "observed", **measured, dropTrace=details)
    for scenarios in measured.values():
        compare([prediction] * len(all_bindings), scenarios)
    if ledger is not None:
        ledger.record(case, "compared")


def forms(reply):
    slots = {slot for slot, _, _ in reply["generated"]}
    yield f"schema/{reply['schema']}"
    yield f"strict/{reply['strict']}"
    yield f"generated-count/{len(slots)}"
    for slot, mode, expression in reply["generated"]:
        yield f"mode/{mode}"
        yield f"affinity/{reply['columns'][slot][1]}"
        yield f"root/{expression[0]}"
        if expression[0] == "arithmetic":
            yield f"operator/{expression[1]}"
        generated = set(references(expression)) & slots
        if len(generated) >= 2:
            yield "parents/multiple"
        for dependency in references(expression):
            if dependency in slots:
                yield "edge/forward" if dependency > slot else "edge/backward"
            else:
                yield f"ordinary/{dependency}"


def required(_domain):
    return {
        "schema/main", "schema/temp", "strict/True", "strict/False",
        "generated-count/2", "generated-count/9", "mode/VIRTUAL", "mode/STORED",
        "affinity/INTEGER", "affinity/TEXT", "root/arithmetic", "root/column", "operator/+", "operator/-",
        "edge/forward", "edge/backward", "ordinary/0", "ordinary/1", "parents/multiple",
    }


def controls():
    item = finite_case("STORED", False, "multiple", "difference", "INTEGER")
    healthy = expected(item)
    compare(healthy, deepcopy(healthy))
    broken = deepcopy(healthy)
    broken["columns"][-1][-1] = ["integer", "2"]
    try:
        compare(healthy, broken)
    except Failure as error:
        require(error.signal == "meaning-mismatch", "wrong-generated-column-control-signal", error.detail)
    else:
        raise Failure("generated-column-comparator-undetected")
    return {"input": item, "healthy": healthy, "mismatched": broken}


def execute(args, contract, db, worker_factory=Worker):
    schedule = ScalarSchedule(requests, negatives, finite_id, finite_ids, forms, required, controls)
    return execute_schedule(args, contract, db, worker_factory, schedule, probe)


def check_faults(args, db, runner=check_render_faults):
    scalar = "sql-semantics-sqlite/src/Ir/SelectSql.php"
    render = "sql-semantics-sqlite/src/Ir/Schema/TableDeclarationSql.php"
    item = finite_case("STORED", True, "multiple", "sum", "INTEGER")
    return runner(args, db, [
        RenderFault("discard-stored-mode", render, ". $generated->mode->value;", ". 'VIRTUAL';", item, "meaning-mismatch"),
        RenderFault("read-wrong-generated-source", scalar, "$name = $source->columns[$expression->slot]->quoted();",
                    "$name = $source->columns[$source instanceof DefinitionSource ? 1 : $expression->slot]->quoted();",
                    item, "meaning-mismatch"),
        RenderFault("omit-right-generation-dependency", render,
                    "$expressions->expression($generated->expression, $source)",
                    "$expressions->expression($generated->expression instanceof \\SqlSemantics\\Platform\\Sqlite\\Ir\\Arithmetic"
                    " ? $generated->expression->left : $generated->expression, $source)", item, "meaning-mismatch"),
        RenderFault("publish-generated-slot-as-ordinary", render, "$generation = $generated === null ?",
                    "$generation = true ?", item, "meaning-mismatch"),
    ], probe)


if __name__ == "__main__":
    run_domain("generated-columns", CONTRACT_NAME, CONTRACT, FIXTURE, execute, check_faults, GeneratedColumnDatabase)
