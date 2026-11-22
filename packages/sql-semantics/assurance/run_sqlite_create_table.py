"""Compare CREATE TABLE catalog and data effects against an independent fixture model."""

import itertools
import random
import time
from collections import Counter

from run import Ledger, Worker, compare, require, write_json
from run_sqlite_delete import typed_rows
from sqlite_domain import RenderFault, check_render_faults, complete_observations, run_domain
from sqlite_engine import typed

CONTRACT = "a2e7d433392ad397b62f58e5e0dc45f2307c591ad9d59c5317b49362545390c3"
SCHEMAS = ("main", "temp")
TYPES = ("", "INTEGER", "TEXT", "REAL", "BLOB")
EXISTING = "ir_create_existing"
DECLARATIONS = [f"CREATE TABLE {EXISTING} (saved TEXT)", f"CREATE TEMP TABLE {EXISTING} (saved TEXT)"]
FIXTURE = "; ".join(DECLARATIONS) + f"; INSERT INTO main.{EXISTING} VALUES ('main sentinel'); INSERT INTO temp.{EXISTING} VALUES ('temp sentinel');"


def case(columns, schema="main", table="ir_create_new", guard=False):
    return {"operation": "create-table", "schema": schema, "table": table, "columns": columns, "ifNotExists": guard}


def finite_cases():
    for schema, declared_type, not_null, guard in itertools.product(SCHEMAS, TYPES, (False, True), (False, True)):
        yield case([["value", declared_type, not_null]], schema, guard=guard)


def finite_id(record):
    _, declared_type, not_null = record["columns"][0]
    return record["schema"], TYPES.index(declared_type), not_null, record["ifNotExists"]


def finite_ids():
    return {(SCHEMAS[index // 20], index // 4 % 5, bool(index // 2 % 2), bool(index % 2)) for index in range(40)}


def seeded(seed, count):
    rng = random.Random(seed)
    for _ in range(count):
        schema = rng.choice(SCHEMAS)
        table = 'ir_create_' + ''.join(rng.choice('aB0-" é日') for _ in range(rng.randrange(1, 10)))
        columns = [[f'c{index} "' + rng.choice('ρ日a'), rng.choice(TYPES), bool(rng.getrandbits(1))]
                   for index in range(rng.randrange(1, 6))]
        if rng.randrange(5) == 0:
            table = EXISTING
        yield case(columns, schema, table, bool(rng.getrandbits(1)))


def table_info(columns):
    return [[typed(value) for value in (cid, name, declaration, int(not_null), None, 0, 0)]
            for cid, (name, declaration, not_null) in enumerate(columns)]


def expected(request):
    schema, table, columns = (request[key] for key in ("schema", "table", "columns"))
    exists = table == EXISTING
    rejected = exists and not request["ifNotExists"]
    catalogs = {name: typed_rows([(EXISTING,)]) for name in SCHEMAS}
    if not exists:
        catalogs[schema] = typed_rows([(EXISTING,), (table,)])
    types = {"INTEGER": 7, "REAL": 7.0, "TEXT": "7", "BLOB": "7", "": "7"}
    failed_null = next((name for name, _, not_null in columns if not_null), None)
    nullable_error = None if failed_null is None else {"code": 19, "message": f"NOT NULL constraint failed: {table}.{failed_null}"}
    inserted = [[typed(value) for value in [1, *[types[declaration] for _, declaration, _ in columns]]]]
    if nullable_error is None:
        inserted.append([typed(value) for value in [2, *[None for _ in columns]]])
    result = {
        "prepareError": "table-already-exists" if rejected else None,
        "statement": None if rejected else {"names": [], "rows": [], "parameters": {}, "parameterCount": 0,
                                             "readOnly": False, "evaluationError": None},
        "catalogs": catalogs, "columns": table_info([["saved", "TEXT", False]] if exists else columns),
        "existingRows": {name: typed_rows([(name + " sentinel",)]) for name in SCHEMAS},
        "writes": None if exists else {"nullError": nullable_error, "rows": inserted},
    }
    return result


def quoted(identifier):
    return '"' + identifier.replace('"', '""') + '"'


def observe(db, request, sql):
    db.execute("SAVEPOINT create_probe")
    try:
        table = request["table"]
        error = None
        try:
            statement = db.observe(sql, {}, capture_evaluation_error=True)
            statement.pop("changes")
        except RuntimeError as failure:
            acceptable = {f"SQLite 1: table {table} already exists", f"SQLite 1: table {quoted(table)} already exists"}
            if str(failure) not in acceptable:
                raise
            error, statement = "table-already-exists", None
        schema = request["schema"]
        columns = db.metadata("table_xinfo", schema=schema, table=table)
        writes = None
        if table != EXISTING and columns:
            width = len(request["columns"])
            markers = ", ".join(f"?{slot}" for slot in range(1, width + 1))
            target = f"{schema}.{quoted(table)}"
            insert = f"INSERT INTO {target} VALUES ({markers})"
            db.observe(insert, {str(slot): "7" for slot in range(1, width + 1)})
            null_probe = db.observe(insert, {str(slot): None for slot in range(1, width + 1)}, capture_evaluation_error=True)
            writes = {"nullError": null_probe["evaluationError"],
                      "rows": db.observe(f"SELECT rowid, * FROM {target} ORDER BY rowid", {}, preserve_order=True)["rows"]}
        return {
            "prepareError": error, "statement": statement, "columns": columns, "writes": writes,
            "catalogs": {name: db.observe(f"SELECT name FROM {name}.sqlite_schema WHERE type = 'table'", {})["rows"]
                         for name in SCHEMAS},
            "existingRows": {name: db.observe(f"SELECT saved FROM {name}.{EXISTING}", {})["rows"] for name in SCHEMAS},
        }
    finally:
        db.execute("ROLLBACK TO create_probe; RELEASE create_probe")


def probe(request, response, db, ledger=None, key=None, original=None):
    require(response["status"] == "constructed", "create-table-positive-rejected", response)
    if ledger is not None:
        ledger.record(key, "constructed", response=response)
    for role in ("operation", "schema", "table", "ifNotExists", "columns"):
        require(response[role] == request[role], "create-table-shape-mismatch", {"role": role, "response": response})
    checks = {"observation": observe(db, request, response["sql"])}
    if original is not None:
        checks["original"] = observe(db, request, original)
    if ledger is not None:
        ledger.record(key, "observed", **checks)
    for result in checks.values():
        compare(expected(request), result)
    if ledger is not None:
        ledger.record(key, "compared")


def analyzed():
    yield 'CREATE TABLE t (one integer, two "tEXt" NOT NULL, extra)', case(
        [["one", "INTEGER", False], ["two", "TEXT", True], ["extra", "", False]], table="t")
    yield 'CREATE TEMP TABLE IF NOT EXISTS a ("1" bLOB, "2" real)', case(
        [["1", "BLOB", False], ["2", "REAL", False]], schema="temp", table="a", guard=True)
    yield f"CREATE TABLE IF NOT EXISTS MAIN.{EXISTING} (unwritten INTEGER NOT NULL)", case(
        [["unwritten", "INTEGER", True]], table=EXISTING, guard=True)
    yield f"CREATE TEMP TABLE {EXISTING} (unwritten)", case(
        [["unwritten", "", False]], schema="temp", table=EXISTING)
    yield 'CREATE TABLE temp."na ""me" ("v ""x" NOT NULL)', case(
        [['v "x', "", True]], schema="temp", table='na "me')


def negatives():
    yield case([]), "nonempty ordered column list"
    yield case([["a", "INTEGER", True], ["A", "TEXT", False]]), "cannot repeat a column name"
    yield case([["a", "", False]], table="sQLite_foo"), "reserved SQLite namespace"
    yield {"sql": "CREATE TABLE t (a INTEGER CHECK (/* label */ a > 0))", "declarations": []}, "column constraint has no complete"
    yield {"sql": "CREATE TABLE unavailable.t (a INTEGER)", "declarations": []}, "requires a known schema"


def execute(args, specification, db, worker_factory=Worker):
    settings = specification["exploration"]
    items = [(f"finite/{index}", item) for index, item in enumerate(finite_cases())]
    items.extend((f"seed/{seed}/{index}", item) for seed in settings["seeds"]
                 for index, item in enumerate(seeded(seed, settings["casesPerSeed"])))
    observed = Counter()
    forms = Counter()
    current = None
    with (args.out / "events.jsonl").open("w") as stream, worker_factory(args, time.monotonic() + settings["timeoutSeconds"]) as worker:
        ledger = Ledger(stream)
        try:
            for start in range(0, len(items), 16):
                batch = items[start:start + 16]
                current = {"batch": [item for _, item in batch]}
                for key, item in batch:
                    ledger.record(key, "attempted", input=item)
                replies = worker.request(current["batch"])
                require(isinstance(replies, list) and len(replies) == len(batch), "create-table-batch-incomplete", replies)
                for (key, current), response in zip(batch, replies):
                    probe(current, response, db, ledger, key)
                    if key.startswith("finite/"):
                        observed[finite_id(response)] += 1
                    forms[f"columns/{len(response['columns'])}"] += 1
                    if response["table"] == EXISTING:
                        forms[f"existing/{response['ifNotExists']}"] += 1
            require(set(observed) == finite_ids() and set(observed.values()) == {1}, "create-table-finite-incomplete", list(observed.items()))
            required = {f"columns/{number}" for number in range(1, 6)} | {"existing/True", "existing/False"}
            require(required <= forms.keys(), "create-table-structure-not-observed", dict(forms))
            for index, (sql, item) in enumerate(analyzed()):
                current = {"sql": sql, "declarations": DECLARATIONS}
                key = f"analysis/{index}"
                ledger.record(key, "attempted", input=current, expected=item)
                probe(item, worker.request(current), db, ledger, key, original=sql)
            for index, (current, message) in enumerate(negatives()):
                key = f"negative/{index}"
                ledger.record(key, "attempted", input=current)
                response = worker.request(current)
                require(response["status"] == "rejected" and message in response["message"], "create-table-negative-boundary-mismatch", response)
                ledger.record(key, "rejected", response=response)
            counts = complete_observations(ledger, settings)
        except Exception as error:
            write_json(args.out / "counterexample.json", {"original": current, "failure": str(error), "shrunk": None})
            raise
        return {"counts": counts, "finiteIds": sorted(observed), "forms": dict(forms), "php": worker.environment}


def check_faults(args, db, runner=check_render_faults):
    table = "sql-semantics-sqlite/src/Ir/Schema/CreateTable.php"
    column = "sql-semantics-sqlite/src/Ir/Schema/TableDeclarationSql.php"
    fixtures = [
        RenderFault("change-declared-type", column, "' ' . $column->type->value", "' TEXT'",
                    case([["a", "INTEGER", False]]), "meaning-mismatch"),
        RenderFault("drop-not-null", column, "$type . $constraint . $default . $collation . $generation;", "$type . $default . $collation . $generation;",
                    case([["a", "INTEGER", True]]), "meaning-mismatch"),
        RenderFault("omit-if-not-exists", table, "($this->ifNotExists ? 'IF NOT EXISTS ' : '')", "''",
                    case([["a", "INTEGER", False]], table=EXISTING, guard=True), "meaning-mismatch"),
        RenderFault("choose-other-schema", table, "$this->schema->quoted()", "'temp'",
                    case([["a", "INTEGER", False]]), "meaning-mismatch"),
    ]
    return runner(args, db, fixtures, probe)


if __name__ == "__main__":
    run_domain("CREATE TABLE", "sqlite-create-table-v3.json", CONTRACT, FIXTURE, execute, check_faults)
