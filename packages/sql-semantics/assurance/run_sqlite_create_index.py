"""Compare completed SQLite index definitions with the pinned native index and write behavior."""

import copy
import hashlib
import json

from run import Failure, TOOL, Worker, compare, digest, require
from run_sqlite_add_column import CATALOG, FIXTURE
from run_sqlite_create_table import quoted
from run_sqlite_unique_keys import values_sql
from sqlite_create_index_cases import (
    FIELDS, SCHEMAS, binding_projection, case, declarations, expected, finite_id, finite_ids, forms, input_rows, missing_slot,
    negatives, predicate_sql, requests, required, writes,
)
from sqlite_domain import RenderFault, check_render_faults, run_domain
from sqlite_engine import Database, ObserverAuthorizationError
from sqlite_generated_column_cases import column
from sqlite_scalar_schedule import ScalarSchedule, execute_schedule

CONTRACT_NAME = "sqlite-create-index-v6.json"
CONTRACT = "a6e90befc22a682f8c5bfb0b5c5d050f4440c1f7247ba662b02f7ca1576cfee0"
CREATE_ACTION = {b"main": 1, b"temp": 3}
INVALID = "SqlSemantics\\Platform\\Sqlite\\Model\\Diagnostic\\InvalidConstruction"


class IndexDatabase(Database):
    """Authorizes only the native scoped index operation and its declared inputs."""

    policy_digest = hashlib.sha256(f"{Database.policy_digest}:{CONTRACT}".encode()).hexdigest()

    def __init__(self, library):
        super().__init__(library)
        self.target = None
        self.authorized = False

    def authorize(self, _, action, first, second, schema, origin):
        target_schema, index, table, fields = self.target
        if origin is None and target_schema == schema:
            if action == 18 and first == CATALOG[schema] and second is None:
                return 0
            if action == CREATE_ACTION[target_schema] and first is not None and first.lower() == index and second is not None and second.lower() == table:
                self.authorized = True
                return 0
            if self.authorized:
                if action == 20 and first is not None and first.lower() == table and second in fields:
                    return 0
                if action == 27 and first is not None and first.lower() == index and second is None:
                    return 0
        if self.denied is None:
            self.denied = (action, [value.decode(errors="replace") if value is not None else None
                                    for value in (first, second, schema, origin)])
        return 1

    def create(self, sql, item, existing):
        fields = frozenset(name.encode() for name in item["heading"])
        self.target = (item["schema"].encode(), item["index"].encode().lower(), item["table"].encode().lower(), fields)
        try:
            observation = self.observe(sql, {}, capture_evaluation_error=True)
            require(self.authorized or (existing and item["ifNotExists"] and not observation["readOnly"]),
                    "sqlite-create-index-operation-not-observed", observation)
            return observation
        except ObserverAuthorizationError as error:
            if error.action in (1, 3, 18):
                raise Failure("sqlite-create-index-target-mismatch", error.arguments) from error
            raise
        finally:
            self.target = None
            self.authorized = False

    def read(self, sql, bindings=None):
        observed = self._observe(sql, {} if bindings is None else bindings, preserve_order=True)
        require(observed["readOnly"] and observed["changes"] == 0, "sqlite-create-index-observer-read", observed)
        return observed


def setup(db, item):
    db.execute(FIXTURE)
    db.execute("; ".join(declarations(item)))
    table = quoted(item["table"])
    for schema in SCHEMAS:
        rows = input_rows(item, schema)
        if rows:
            db.execute(f"INSERT INTO {schema}.{table} VALUES {values_sql(rows)}")
    if item["existing"]:
        name = quoted(item["index"])
        db.execute(f"CREATE INDEX {item['schema']}.{name} ON {table}({quoted(item['heading'][3])} COLLATE BINARY ASC)")


def metadata(db, schema, name):
    item = db.read(f"SELECT type, name, tbl_name, rootpage FROM {schema}.sqlite_schema WHERE name=?1", {"1": name})["rows"]
    require(len(item) <= 1, "sqlite-create-index-nonunique-catalog-entry", item)
    return item[0] if item else None


def snapshot(db, item):
    table = quoted(item["table"])
    index = quoted(item["index"])
    first = quoted(item["heading"][0])
    result = {}
    for schema in SCHEMAS:
        data = db.read(f"SELECT * FROM {schema}.{table} ORDER BY {first}")
        result[schema] = {
            "indexList": db.read(f"PRAGMA {schema}.index_list({table})")["rows"],
            "keys": db.read(f"PRAGMA {schema}.index_xinfo({index})")["rows"],
            "root": metadata(db, schema, item["table"]), "index": metadata(db, schema, item["index"]),
            "fields": data["names"], "rows": data["rows"],
        }
    return result


def later(db, item, predicate_of=predicate_sql):
    target = f"{item['schema']}.{quoted(item['table'])}"
    index = quoted(item["index"])
    outcomes = [db._observe(f"INSERT INTO {target} VALUES {values_sql((row,))}", {}, capture_evaluation_error=True)
                for row in writes(item)]
    sql = f"SELECT * FROM {target} INDEXED BY {index}" + predicate_of(item) + f" ORDER BY {quoted(item['heading'][0])}"
    try:
        visible = db.read(sql)["rows"]
    except RuntimeError as error:
        if str(error) != "SQLite 1: no query solution":
            raise
        visible = {"prepareError": str(error)}
    return {"writes": outcomes, "state": snapshot(db, item), "visible": visible}


def observe(template, item, sql, observe_later=later):
    db = type(template)(template.library)
    try:
        setup(db, item)
        before = snapshot(db, item)
        db.execute("BEGIN")
        result = db.create(sql, item, before[item["schema"]]["index"] is not None)
        after = snapshot(db, item)
        subsequent = observe_later(db, item) if result["evaluationError"] is None else None
        active = not bool(db.get_autocommit(db.db))
        if active:
            db.execute("ROLLBACK")
        return {"before": before, "statement": result, "after": after, "later": subsequent, "transaction": active,
                "rolledBack": snapshot(db, item)}
    finally:
        db.close(db.db)


def check_binding_contract(specification):
    name = "sqlite-index-operand-bindings-v1.json"
    fingerprint = "3339223aae208f8133fa3161eefceaed3b0e167afabca3ba0d14dc105e31e1f1"
    require(specification["bindingContract"] == {name: fingerprint} and digest(TOOL / name) == fingerprint,
            "sqlite-index-operand-binding-contract", name)
    for reference, expected_digest in json.loads((TOOL / name).read_text())["references"].items():
        require(digest(TOOL / reference) == expected_digest, "sqlite-index-prior-observation-contract", reference)


def check_binding_projection(item, reply):
    expected = binding_projection(item)
    require(reply.get("bindings") == expected, "sqlite-index-operand-bindings",
            {"expected": expected, "actual": reply.get("bindings")})


def probe(item, reply, db, ledger=None, key=None, original=None):
    require(reply["status"] == "constructed" and all(reply.get(field) == item[field] for field in FIELDS),
            "sqlite-create-index-structure", {"expected": item, "actual": reply})
    check_binding_projection(item, reply)
    if ledger is not None:
        ledger.record(key, "constructed", response=reply)
    result = {"observation": observe(db, item, reply["sql"])}
    if original is not None:
        result["original"] = observe(db, item, original)
    if ledger is not None:
        ledger.record(key, "observed", **result)
    prediction = expected(item)
    for observation in result.values():
        compare(prediction, observation)
    if ledger is not None:
        ledger.record(key, "compared")
    return result["observation"]


def controls(db):
    item = case(term="nocase-code-desc", predicate="positive", unique=True)
    healthy = observe(db, item, "CREATE UNIQUE INDEX main.tag ON ledger(code COLLATE NOCASE DESC) WHERE amount > 0")
    target = expected(item)
    compare(target, healthy)
    changed = copy.deepcopy(healthy)
    changed["after"]["main"]["keys"][0][3] = ["integer", "0"]
    wrong_sort = copy.deepcopy(healthy)
    wrong_sort["later"]["visible"] = wrong_sort["later"]["visible"][:-1]
    outcomes = {}
    for name, wrong in (("key-direction", changed), ("indexed-visible-rows", wrong_sort)):
        try:
            compare(target, wrong)
        except Failure as failure:
            require(failure.signal == "meaning-mismatch", "sqlite-create-index-invalid-comparator-signal", name)
            outcomes[name] = {"observed": failure.signal, "witness": wrong}
        else:
            raise Failure("sqlite-create-index-comparator-accepted", name)
    return {"normal": healthy, "rejected": outcomes}


def execute(args, specification, db, worker_factory=Worker):
    check_binding_contract(specification)
    schedule = ScalarSchedule(requests, negatives, finite_id, finite_ids, forms, required, lambda: controls(db))
    return execute_schedule(args, specification, db, worker_factory, schedule, probe,
                            measured_input=lambda reply, response: (reply, response))


def fault_probe(item, reply, db):
    if item.get("probe") != "create-index-construction":
        probe(item, reply, db)
        return
    if reply["status"] == "constructed":
        require(all(reply.get(field) == item[field] for field in FIELDS) and "sql" not in reply,
                "sqlite-create-index-unexpected-value", reply)
        raise Failure("sqlite-create-index-operand-scope-escaped", reply)
    require(reply["status"] == "rejected" and reply["exception"] == INVALID and "visible occurrence" in reply["message"],
            "sqlite-create-index-fault-wrong-rejection", reply)


def check_faults(args, db, runner=check_render_faults):
    base = "sql-semantics-sqlite/src/Ir/"
    index = base + "Schema/Index/CreateIndex.php"
    scalar = base + "SelectSql.php"
    multiple = {**case(), "terms": [[column(2), False], [column(1), True]]}
    return runner(args, db, [
        RenderFault("omit-index-operand-scope", index, "$references = (new ExpressionScope())->check([count($columns)], $operands, false);", "$references = [];",
                    {**missing_slot(), "probe": "create-index-construction"}, "sqlite-create-index-operand-scope-escaped"),
        RenderFault("index-other-schema", index, "$this->schema->quoted()", "'temp'", case(), "sqlite-create-index-target-mismatch"),
        RenderFault("reverse-index-keys", index, "foreach ($this->terms as $term) {", "foreach (array_reverse($this->terms) as $term) {",
                    multiple, "meaning-mismatch"),
        RenderFault("render-index-different-bound-field", index, "new DefinitionSource($this->bindings)",
                    "new DefinitionSource([2 => new Identifier('note')])", case(), "meaning-mismatch"),
        RenderFault("omit-index-filter", index, "$this->where === null ? '' : ' WHERE ' . $expressions->expression($this->where, $source)",
                    "''", case(term="inherited-code", predicate="positive", unique=True), "meaning-mismatch"),
        RenderFault("drop-index-explicit-collation", scalar, " . ' COLLATE ' . $expression->collation->value . ", " . ",
                    case("temp", "nocase-code-desc", unique=True), "meaning-mismatch"),
        RenderFault("erase-index-folded-registrations", scalar, "if ($expression->bindings === []) {", "if (true) {",
                    case(predicate="folded"), "meaning-mismatch"),
        RenderFault("collapse-marker-free-index-filter", index, "preserveEmptyMembership: true", "preserveEmptyMembership: false",
                    {**case(), "where": ["folded-membership", True, []]}, "meaning-mismatch"),
        RenderFault("omit-index-unique-policy", index, "($this->unique ? 'UNIQUE ' : '')", "''",
                    case(unique=True), "meaning-mismatch"),
    ], fault_probe)


if __name__ == "__main__":
    run_domain("create-index", CONTRACT_NAME, CONTRACT, FIXTURE, execute, check_faults, IndexDatabase)
