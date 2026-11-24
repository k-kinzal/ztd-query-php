"""Check DROP TABLE effects against an independent catalog and FK fixture."""

import itertools
import random
import time
from collections import Counter

from run import Ledger, Worker, compare, require, write_json
from run_sqlite_delete import typed_rows
from sqlite_domain import RenderFault, check_render_faults, complete_observations, run_domain

CONTRACT = "d01835110d442bdae141762032ad0904ad3174615cc6047786d42f23a8497050"
TABLES = ("ir_drop", "ir_drop_blocked", "ir_drop_absent")
SCHEMAS = ("main", "temp")
DECLARATIONS = [
    "CREATE TABLE ir_drop (id INTEGER PRIMARY KEY)",
    "CREATE TABLE ir_drop_blocked (id INTEGER PRIMARY KEY)",
    "CREATE TEMP TABLE ir_drop (id INTEGER PRIMARY KEY)",
    "CREATE TEMP TABLE ir_drop_blocked (id INTEGER PRIMARY KEY)",
]
FIXTURE = "PRAGMA foreign_keys = ON; " + "; ".join(DECLARATIONS) + """
;
CREATE INDEX main.ir_drop_lookup ON ir_drop (id);
CREATE INDEX temp.ir_drop_lookup ON ir_drop (id);
CREATE TABLE main.ir_drop_cascade (parent REFERENCES ir_drop(id) ON DELETE CASCADE);
CREATE TABLE main.ir_drop_restrict (parent REFERENCES ir_drop_blocked(id) ON DELETE RESTRICT);
CREATE TABLE main.ir_drop_log (id);
INSERT INTO main.ir_drop VALUES (1),(2);
INSERT INTO main.ir_drop_blocked VALUES (7),(9);
INSERT INTO main.ir_drop_cascade VALUES (1),(2),(2);
INSERT INTO main.ir_drop_restrict VALUES (7);
INSERT INTO temp.ir_drop VALUES (82),(83);
INSERT INTO temp.ir_drop_blocked VALUES (97);
CREATE TRIGGER main.ir_drop_audit AFTER DELETE ON main.ir_drop
BEGIN
  INSERT INTO ir_drop_log VALUES (OLD.id);
END;
"""
MAIN = [
    ("table", "ir_drop", "ir_drop"), ("table", "ir_drop_blocked", "ir_drop_blocked"),
    ("index", "ir_drop_lookup", "ir_drop"), ("trigger", "ir_drop_audit", "ir_drop"),
    ("table", "ir_drop_cascade", "ir_drop_cascade"), ("table", "ir_drop_restrict", "ir_drop_restrict"),
    ("table", "ir_drop_log", "ir_drop_log"),
]
TEMP = [
    ("table", "ir_drop", "ir_drop"), ("table", "ir_drop_blocked", "ir_drop_blocked"),
    ("index", "ir_drop_lookup", "ir_drop"),
]


def case(schema, table, guard, created=False):
    return {"operation": "drop-table", "schema": schema, "table": table, "ifExists": guard, "created": created}


def finite_cases():
    for schema, table, guard in itertools.product(SCHEMAS, TABLES, (False, True)):
        yield case(schema, table, guard)


def finite_id(record):
    return (record["schema"], record["table"], record["ifExists"])


def finite_ids():
    return {(SCHEMAS[number // 6], TABLES[number // 2 % 3], bool(number % 2)) for number in range(12)}


def seeded(seed, count):
    rng = random.Random(seed)
    for _ in range(count):
        schema = rng.choice(SCHEMAS)
        if rng.getrandbits(1):
            yield case(schema, rng.choice(TABLES), bool(rng.getrandbits(1)))
            continue
        name = 'ir_generated_' + ''.join(rng.choice('aB0-" é日') for _ in range(rng.randrange(1, 24)))
        yield case(schema, name, bool(rng.getrandbits(1)), created=bool(rng.getrandbits(1)))


def expected(request):
    schema, table = request["schema"], request["table"]
    original = {"main": MAIN.copy(), "temp": TEMP.copy()}
    if request["created"]:
        original[schema].append(("table", table, table))
    present = ("table", table, table) in original[schema]
    blocked = schema == "main" and table == "ir_drop_blocked"
    removed = present and not blocked
    error = None
    statement = {"names": [], "rows": [], "parameters": {}, "parameterCount": 0, "readOnly": False}
    if blocked:
        statement["evaluationError"] = {"code": 19, "message": "FOREIGN KEY constraint failed"}
    elif not present and not request["ifExists"]:
        error = f"SQLite 1: no such table: {schema}.{table}"
        statement = None
    else:
        statement["evaluationError"] = None
    if removed:
        original[schema] = [entry for entry in original[schema] if entry[2] != table]
    return {
        "statement": statement, "prepareError": error,
        "schema": {name: typed_rows(entries) for name, entries in original.items()},
        "cascade": typed_rows([] if removed and schema == "main" and table == "ir_drop" else [(1,), (2,), (2,)]),
        "restrict": typed_rows([(7,)]), "log": [],
    }


def observe(db, request, sql):
    db.execute("SAVEPOINT ddl_probe")
    try:
        if request["created"]:
            name = '"' + request["table"].replace('"', '""') + '"'
            db.execute(f'CREATE TABLE {request["schema"]}.{name} (column_value INTEGER)')
        error = None
        try:
            statement = db.observe(sql, {}, capture_evaluation_error=True)
            statement.pop("changes")
        except RuntimeError as failure:
            if not str(failure).startswith("SQLite 1: no such table: "):
                raise
            error = str(failure)
            statement = None
        return {
            "statement": statement, "prepareError": error,
            "schema": {
                schema: db.observe(f"SELECT type,name,tbl_name FROM {schema}.sqlite_schema", {})["rows"]
                for schema in SCHEMAS
            },
            "cascade": db.observe("SELECT parent FROM main.ir_drop_cascade", {})["rows"],
            "restrict": db.observe("SELECT parent FROM main.ir_drop_restrict", {})["rows"],
            "log": db.observe("SELECT id FROM main.ir_drop_log", {})["rows"],
        }
    finally:
        db.execute("ROLLBACK TO ddl_probe; RELEASE ddl_probe")


def probe(request, response, db, ledger=None, key=None, original=None):
    require(response["status"] == "constructed", "drop-table-positive-rejected", response)
    if ledger is not None:
        ledger.record(key, "constructed", response=response)
    for role in ("operation", "schema", "table", "ifExists"):
        require(response[role] == request[role], "drop-table-shape-mismatch", {"role": role, "response": response})
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
    yield "DROP TABLE ir_drop", case("temp", "ir_drop", False)
    yield "DROP TABLE IF EXISTS MAIN.IR_DROP", case("main", "ir_drop", True)
    yield "DROP TABLE main.ir_drop_blocked", case("main", "ir_drop_blocked", False)
    yield "DROP TABLE IF EXISTS main.ir_drop_absent", case("main", "ir_drop_absent", True)
    yield "DROP TABLE IF EXISTS TEMP.ir_drop_absent", case("temp", "ir_drop_absent", True)


def negatives():
    yield case("", "ir_drop", False), "nonempty schema identifier"
    yield case("main", "SQLite_master", True), "reserved SQLite namespace"
    yield {"sql": "DROP TABLE unavailable", "declarations": []}, "requires a resolved ordinary table"
    yield {"sql": "DROP TABLE IF EXISTS unavailable", "declarations": DECLARATIONS}, "absent DROP TABLE target requires an explicit schema"
    yield {"sql": "DROP INDEX ir_drop_lookup", "declarations": DECLARATIONS}, "DROP INDEX requires a resolved index or a known guarded absence"


def execute(args, specification, db, worker_factory=Worker):
    settings = specification["exploration"]
    enabled = db.metadata("foreign_keys")
    require(enabled == [[["integer", "1"]]], "drop-table-foreign-key-mode-mismatch", enabled)
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
                require(isinstance(replies, list) and len(replies) == len(batch), "drop-table-batch-incomplete", replies)
                for (key, current), response in zip(batch, replies):
                    probe(current, response, db, ledger, key)
                    if key.startswith("finite/"):
                        observed[finite_id(response)] += 1
                    if response["table"] not in TABLES:
                        forms[f"generated/created/{current['created']}"] += 1
                        forms[f"generated/quote/{chr(34) in response['table']}"] += 1
            require(set(observed) == finite_ids() and set(observed.values()) == {1}, "drop-table-finite-incomplete", list(observed.items()))
            required = {f"generated/{role}/{value}" for role in ("created", "quote") for value in (True, False)}
            require(required <= forms.keys(), "drop-table-structure-not-observed", dict(forms))
            for index, (sql, item) in enumerate(analyzed()):
                current = {"sql": sql, "declarations": DECLARATIONS}
                key = f"analysis/{index}"
                ledger.record(key, "attempted", input=current, expected=item)
                probe(item, worker.request(current), db, ledger, key, original=sql)
            for index, (current, message) in enumerate(negatives()):
                key = f"negative/{index}"
                ledger.record(key, "attempted", input=current)
                response = worker.request(current)
                require(response["status"] == "rejected" and message in response["message"], "drop-table-negative-boundary-mismatch", response)
                ledger.record(key, "rejected", response=response)
            counts = complete_observations(ledger, settings)
        except Exception as error:
            write_json(args.out / "counterexample.json", {"original": current, "failure": str(error), "shrunk": None})
            raise
        return {"counts": counts, "finiteIds": sorted(observed), "forms": dict(forms), "php": worker.environment,
                "foreignKeys": "enabled"}


def check_faults(args, db, runner=check_render_faults):
    path = "sql-semantics-sqlite/src/Ir/Schema/DropTable.php"
    fixtures = [
        RenderFault("omit-if-exists", path, "($this->ifExists ? 'IF EXISTS ' : '')", "''",
                    case("main", "ir_drop_absent", True), "meaning-mismatch"),
        RenderFault("choose-other-schema", path, "$this->schema->quoted()", "'temp'",
                    case("main", "ir_drop", False), "meaning-mismatch"),
    ]
    return runner(args, db, fixtures, probe)


if __name__ == "__main__":
    run_domain("DROP TABLE", "sqlite-drop-table-v2.json", CONTRACT, FIXTURE, execute, check_faults)
