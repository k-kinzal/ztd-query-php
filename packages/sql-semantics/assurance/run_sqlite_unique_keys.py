"""Compare ordered SQLite uniqueness declarations, index layouts and write effects."""

import copy
import hashlib

from run import Failure, Worker, compare, require
from run_sqlite_create_table import quoted
from run_sqlite_strict_tables import StrictDatabase, literal
from sqlite_domain import RenderFault, check_render_faults, run_domain
from sqlite_engine import ObserverAuthorizationError
from sqlite_scalar_schedule import ScalarSchedule, execute_schedule
from sqlite_unique_key_cases import FIXTURE, expected, finite_id, finite_ids, fixture_rows, layout, negatives, nullable_pairs, requests, rowid_alias, structure

CONTRACT_NAME = "sqlite-unique-keys-v1.json"
CONTRACT = "926f08200f9d461f38153c2a342632d1fa176e13877544bc83a077dd3a99a457"
MARKER = "main.ir_unique_marker"
MODES = [(explicit, simultaneous) for explicit in (False, True) for simultaneous in (False, True)]


class UniqueDatabase(StrictDatabase):
    """Restricts implicit index creation to the expected table and constraint slots."""

    policy_digest = hashlib.sha256(f"{StrictDatabase.policy_digest}:{CONTRACT}".encode()).hexdigest()

    def __init__(self, library):
        super().__init__(library)
        self.implicit_indexes = frozenset()

    def authorize(self, context, action, first, second, schema, origin):
        if origin is None and (action, first, second, schema) in self.implicit_indexes:
            return 0
        return super().authorize(context, action, first, second, schema, origin)

    def create(self, sql, schema, table, key_count):
        require(schema in ("main", "temp"), "sqlite-unique-key-invalid-schema", schema)
        opcode = 1 if schema == "main" else 3
        self.implicit_indexes = {(opcode, f"sqlite_autoindex_{table}_{ordinal}".encode(), table.encode(), schema.encode())
                                 for ordinal in range(1, key_count + 1)}
        try:
            return self.observe(sql, {}, capture_evaluation_error=True)
        finally:
            self.implicit_indexes = frozenset()

    def indexes(self, schema, table):
        require(schema in ("main", "temp"), "sqlite-unique-key-invalid-schema", schema)
        indexes = self._observe(f"PRAGMA {schema}.index_list({quoted(table)})", {}, preserve_order=True)["rows"]
        details = {}
        for row in indexes:
            storage, name = row[1]
            require(storage == "text" and name.startswith(f"sqlite_autoindex_{table}_"), "sqlite-unique-key-unexpected-index", row)
            details[name] = self._observe(f"PRAGMA {schema}.index_xinfo({quoted(name)})", {}, preserve_order=True)["rows"]
        return indexes, details


def values_sql(rows):
    return ", ".join("(" + ", ".join(map(literal, row)) + ")" for row in rows)


def write(db, target, rows):
    if not rows:
        return {"changes": 0, "error": None}
    observed = db.observe(f"INSERT INTO {target} VALUES {values_sql(rows)}", {}, capture_evaluation_error=True)
    return {"changes": db.changes(db.db), "error": observed["evaluationError"]}


def observe(db, item, sql, explicit, simultaneous):
    schema, table = item["schema"], item["table"]
    table_ref = f"{schema}.{quoted(table)}"
    db.execute(f"DELETE FROM {MARKER}")
    created = False
    try:
        construction = db.create(sql, schema, table, len(item["keys"]))
        require(not construction["readOnly"] and construction["changes"] == 0
                and construction["evaluationError"] is None, "sqlite-unique-creation-failed", construction)
        created = True
        indexes, details = db.indexes(schema, table)
        catalog = {"table": db.table_list(schema, table),
                   "columns": db.metadata("table_xinfo", schema=schema, table=table),
                   "list": indexes, "terms": details}
        base, proposed = fixture_rows(item, simultaneous)
        query = f"SELECT * FROM {table_ref} ORDER BY {quoted(item['columns'][-1][0])}"
        require(write(db, table_ref, base) == {"changes": len(base), "error": None}, "sqlite-unique-fixture-rejected", base)
        nullable = []
        for key, mask, pair in nullable_pairs(item):
            effect = write(db, table_ref, pair)
            rows = db.observe(query, {}, preserve_order=True)["rows"]
            nullable.append({"key": key, "mask": mask, "write": effect, "state": rows})
            db.execute(f"DELETE FROM {table_ref}")
            require(write(db, table_ref, base) == {"changes": len(base), "error": None},
                    "sqlite-unique-fixture-reset-rejected", base)
        if explicit:
            db.execute(f"BEGIN; INSERT INTO {MARKER} VALUES (73)")
        conflicts = write(db, table_ref, proposed)
        rows = db.observe(query, {}, preserve_order=True)["rows"]
        return {"catalog": catalog, "nullableWrites": nullable, "conflict": conflicts,
                "state": rows, "active": not db.get_autocommit(db.db),
                "marker": db.observe(f"SELECT value FROM {MARKER}", {})["rows"]}
    finally:
        if not db.get_autocommit(db.db):
            db.execute("ROLLBACK")
        if created:
            db.execute(f"DROP TABLE {table_ref}")


def required_nullable_masks(reply, item):
    primary_slots = {slot for specification in reply["keys"] if specification["kind"] == "primary"
                     and (reply["withoutRowid"] or reply["strict"] or rowid_alias(item, specification))
                     for slot, _, _ in specification["terms"]}
    required = set()
    for index, specification in enumerate(reply["keys"]):
        if specification["kind"] == "primary":
            continue
        slots = sorted({slot for slot, _, _ in specification["terms"]
                        if slot not in primary_slots and not reply["columns"][slot][2]})
        for mask in range(1, 1 << len(slots)):
            required.add((index, tuple(slot for bit, slot in enumerate(slots) if mask & (1 << bit))))
    return required


def probe(item, reply, db, ledger=None, case=None, original=None):
    require(reply["status"] == "constructed" and reply["operation"] == "create-table",
            "sqlite-unique-positive-rejected", reply)
    prediction = structure(item)
    observed = {role: reply[role] for role in prediction}
    require(prediction == observed, "sqlite-unique-structure-mismatch", {"expected": prediction, "actual": observed})
    if ledger is not None:
        ledger.record(case, "constructed", response=reply)
    values = {"observation": [observe(db, item, reply["sql"], *mode) for mode in MODES]}
    if original is not None:
        values["original"] = [observe(db, item, original, *mode) for mode in MODES]
    if ledger is not None:
        ledger.record(case, "observed", **values)
    predicted = [expected(item, *mode) for mode in MODES]
    required = required_nullable_masks(reply, item)
    for observed in values.values():
        for outcome in observed:
            seen = {(record["key"], tuple(record["mask"])) for record in outcome["nullableWrites"]}
            require(seen == required and len(outcome["nullableWrites"]) == len(required),
                    "sqlite-unique-null-mask-incomplete", {"expected": sorted(required), "actual": sorted(seen)})
        compare(predicted, observed)
    if ledger is not None:
        ledger.record(case, "compared")


def forms(reply):
    keys = reply["keys"]
    yield f"schema/{reply['schema']}"
    yield f"strict/{reply['strict']}"
    yield f"without-rowid/{reply['withoutRowid']}"
    yield f"width/{len(reply['columns'])}"
    yield "keys/multiple" if len(keys) > 1 else "keys/single"
    for entry in keys:
        yield f"placement/{entry['inline']}"
        yield f"role/{entry['kind']}"
        yield f"policy/{entry['conflict']}"
        yield f"terms/{len(entry['terms'])}"
        for slot, descending, collation in entry["terms"]:
            yield f"slot/{slot}"
            yield f"collation/{collation}"
            yield f"direction/{descending}"


def required(domain):
    return {
        *(f"schema/{schema}" for schema in domain["schemas"]), *(f"policy/{policy}" for policy in domain["policies"]),
        "strict/True", "strict/False", "without-rowid/True", "without-rowid/False", "width/2", "width/6",
        "placement/True", "placement/False", "terms/1", "terms/2", "terms/3", "keys/single", "keys/multiple",
        "role/primary", "role/unique", "collation/NOCASE", "collation/BINARY", "collation/RTRIM",
        "direction/False", "direction/True", "slot/0", "slot/4",
    }


def controls(db):
    item = layout("ordered-conflicts", "IGNORE")
    normal = expected(item, True, True)
    compare(normal, copy.deepcopy(normal))
    altered = copy.deepcopy(normal)
    altered["catalog"]["list"].reverse()
    try:
        compare(normal, altered)
    except Failure as error:
        require(error.signal == "meaning-mismatch", "sqlite-unique-control-signal")
    else:
        raise Failure("sqlite-unique-control-accepted")
    sql = "CREATE INDEX ir_manual ON ir_unique_marker(value)"
    try:
        db.create(sql, "main", "ir_unique_marker", 4)
    except ObserverAuthorizationError as error:
        require(error.action == 1 and error.arguments[:2] == ["ir_manual", "ir_unique_marker"],
                "sqlite-unique-index-observer-control-signal", error.arguments)
    else:
        raise Failure("sqlite-unique-index-observer-control-accepted")
    return {"expected": normal, "orderMismatch": altered, "observer": {"sql": sql, "deniedAction": 1}}


def execute(args, contract, db, worker_factory=Worker):
    schedule = ScalarSchedule(requests, negatives, finite_id, finite_ids, forms, required, lambda: controls(db))
    return execute_schedule(args, contract, db, worker_factory, schedule, probe)


def check_faults(args, db, runner=check_render_faults):
    renderer = "sql-semantics-sqlite/src/Ir/Schema/Key/TableKeySql.php"
    declaration = "sql-semantics-sqlite/src/Ir/Schema/TableDeclarationSql.php"
    return runner(args, db, [
        RenderFault("drop-inline-unique", renderer, "return ' UNIQUE' . $conflict;", "return '';",
                    layout("inline", "ABORT"), "meaning-mismatch"),
        RenderFault("reverse-unique-constraints", declaration, "foreach ($keys as $key)", "foreach (array_reverse($keys) as $key)",
                    layout("ordered-conflicts", "IGNORE"), "meaning-mismatch"),
        RenderFault("replace-explicit-collation", renderer, " ? '' : ' COLLATE ' . $term->collation->value;", " ? '' : '';",
                    layout("table-binary-override", None), "meaning-mismatch"),
        RenderFault("replace-key-policy", renderer, "' ON CONFLICT ' . $conflict->value", "' ON CONFLICT ABORT'",
                    layout("inline", "IGNORE"), "meaning-mismatch"),
        RenderFault("erase-composite-term", renderer, "foreach ($key->terms as $term)", "foreach (array_slice($key->terms, 0, 1) as $term)",
                    layout("composite", None), "meaning-mismatch"),
    ], probe)


if __name__ == "__main__":
    run_domain("unique-keys", CONTRACT_NAME, CONTRACT, FIXTURE, execute, check_faults, UniqueDatabase)
