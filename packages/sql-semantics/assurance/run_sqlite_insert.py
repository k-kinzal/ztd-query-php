"""Check complete SQLite INSERT effects, including constraint rollback and defaults."""

import itertools
import random
import time
from collections import Counter

from run import Ledger, Worker, compare, require, write_json
from run_sqlite_delete import typed_rows
from run_sqlite_values import names
from sqlite_domain import RenderFault, check_render_faults, complete_observations, run_domain
from sqlite_engine import typed
from sqlite_relational_cases import evaluate, numeric
from sqlite_result_cases import integer

CONTRACT = "a6ce28637ff31c61eb636d0e14170aea7c6dff83a2365c98bbdad94f8ec955da"
TAIL = "payload BLOB DEFAULT 73, computed GENERATED ALWAYS AS (payload + payload) STORED)"
DECLARATIONS = [
    "CREATE TABLE ir_insert (id INTEGER UNIQUE ON CONFLICT IGNORE, " + TAIL,
    "CREATE TEMP TABLE ir_insert (id INTEGER UNIQUE, " + TAIL,
]
FIXTURE = "; ".join(DECLARATIONS) + """
;
INSERT INTO main.ir_insert (id, payload) VALUES (1, 10), (9, 13);
INSERT INTO temp.ir_insert (id, payload) VALUES (1, 40), (-2, NULL);
CREATE TABLE ir_insert_log (id BLOB, payload BLOB, computed BLOB);
CREATE TRIGGER main.ir_insert_audit AFTER INSERT ON main.ir_insert
BEGIN
  INSERT INTO ir_insert_log VALUES (NEW.id, NEW.payload, NEW.computed);
END;
"""
MAIN = [(1, 10, 20), (9, 13, 26)]
TEMP = [(1, 40, 80), (-2, None, None)]
TABLES = ("main", "temp")
POLICIES = ("inherit", "abort", "ignore")
ATOMS = [integer(0), integer(1), ["null"]]


def case(rows, schema="main", columns=("id", "payload"), conflict="inherit", bindings=None):
    return {"operation": "insert", "target": [schema, "ir_insert", list(columns)], "conflict": conflict,
            "rows": rows, "bindings": {} if bindings is None else bindings}


def finite_cases():
    for schema, policy, values in itertools.product(TABLES, POLICIES, itertools.product(ATOMS, repeat=2)):
        yield case([list(values)], schema, conflict=policy)


def finite_id(response):
    first, second = response["rows"][0]
    return (response["target"][0], response["conflict"], ATOMS.index(first) * 3 + ATOMS.index(second))


def finite_ids():
    return {(TABLES[index // 27], POLICIES[index // 9 % 3], index % 9) for index in range(54)}


def seeded(seed, count):
    rng = random.Random(seed)

    def scalar(depth):
        if depth and rng.getrandbits(1):
            return ["arithmetic", rng.choice(("+", "-", "*")), scalar(depth - 1), scalar(depth - 1)]
        return rng.choice(ATOMS + [integer(rng.randrange(-4, 7)), ["parameter", rng.choice(("1", "02", "2", "5"))]])

    for _ in range(count):
        columns = rng.choice((("id", "payload"), ("payload", "id"), ("id",), ("payload",)))
        rows = [[scalar(rng.randrange(4)) for _ in columns] for _ in range(rng.randrange(1, 6))]
        bindings = {slot: rng.choice((None, 1, -2, 3.0, 1.75, "3", "text")) for slot in names(rows)}
        yield case(rows, rng.choice(TABLES), columns, rng.choice(POLICIES), bindings)


def integer_affinity(value):
    if type(value) is str and value == "3":
        return 3
    if type(value) is float and value.is_integer():
        return int(value)
    return value


def expected(request, omitted_id=None, after_main=None):
    rows = ((evaluate(value, (), request["bindings"]) for value in row) for row in request["rows"])
    return expected_assignment(request, rows, names(request["rows"]), omitted_id, after_main)


def expected_assignment(request, rows, parameters, omitted_id=None, after_main=None):
    schema, _, columns = request["target"]
    writes = [columns[reference[1]] for reference in request["writes"]] if "writes" in request else columns
    returning = request.get("returning", [])
    physical = ("id", "payload", "computed")
    result_slots = [physical.index(columns[reference[1]]) for reference, _ in returning]
    policy = request["conflict"]
    old = MAIN if schema == "main" else TEMP
    accepted = []
    error = None
    known = {record[0] for record in old}
    for row in rows:
        values = dict(zip(writes, row))
        key = integer_affinity(values.get("id", omitted_id))
        if key is not None and key in known:
            if policy == "ignore" or (policy == "inherit" and schema == "main"):
                continue
            accepted = []
            error = {"code": 19, "message": "UNIQUE constraint failed: ir_insert.id"}
            break
        known.add(key)
        payload = values.get("payload", 73)
        computed = None if payload is None else 2 * numeric(payload)
        accepted.append((key, payload, computed))
    log = accepted if schema == "main" else []
    stored = [after_main(row) for row in accepted] if schema == "main" and after_main is not None else accepted
    returned = typed_rows(tuple(row[index] for index in result_slots) for row in accepted) if returning else []
    return {
        "statement": {"names": [label for _, label in returning], "parameters": parameters, "parameterCount": max(map(int, parameters), default=0),
                      "readOnly": False, "changes": len(accepted), "rows": returned, "evaluationError": error},
        "main": typed_rows(old + stored if schema == "main" else MAIN),
        "temp": typed_rows(old + stored if schema == "temp" else TEMP),
        "log": [[typed(value) for value in row] for row in log],
    }


def observe(db, sql, bindings):
    db.execute("SAVEPOINT write_probe")
    try:
        statement = db.observe(sql, bindings, capture_evaluation_error=True)
        statement["changes"] = db.changes(db.db)
        return {
            "statement": statement,
            "main": db.observe("SELECT id,payload,computed FROM main.ir_insert", {})["rows"],
            "temp": db.observe("SELECT id,payload,computed FROM temp.ir_insert", {})["rows"],
            "log": db.observe("SELECT id,payload,computed FROM main.ir_insert_log ORDER BY rowid", {}, preserve_order=True)["rows"],
        }
    finally:
        db.execute("ROLLBACK TO write_probe; RELEASE write_probe")


def probe(request, response, db, ledger=None, key=None, original=None):
    require(response["status"] == "constructed", "insert-positive-rejected", response)
    if ledger is not None:
        ledger.record(key, "constructed", response=response)
    for role in ("operation", "target", "conflict", "rows"):
        require(response[role] == request[role], "insert-shape-mismatch", {"role": role, "response": response})
    checks = {"observation": observe(db, response["sql"], request["bindings"])}
    if original is not None:
        checks["original"] = observe(db, original, request["bindings"])
    if ledger is not None:
        ledger.record(key, "observed", **checks)
    result = expected(request)
    for observation in checks.values():
        compare(result, observation)
    if ledger is not None:
        ledger.record(key, "compared")
    return checks["observation"]


def analyzed():
    yield "INSERT INTO ir_insert (id) VALUES (3)", case([[integer(3)]], schema="temp", columns=("id",))
    yield "INSERT INTO main.ir_insert (ID) VALUES (3), (1), (5)", case(
        [[integer(3)], [ATOMS[1]], [integer(5)]], columns=("id",))
    yield "INSERT OR ABORT INTO main.ir_insert (id) VALUES (3), (1), (5)", case(
        [[integer(3)], [ATOMS[1]], [integer(5)]], columns=("id",), conflict="abort")
    yield "INSERT OR IGNORE INTO temp.ir_insert (payload, id) VALUES (?02, 1), (?2, 3)", case(
        [[["parameter", "02"], ATOMS[1]], [["parameter", "2"], integer(3)]],
        schema="temp", columns=("payload", "id"), conflict="ignore", bindings={"2": "text"})
    yield "INSERT INTO main.ir_insert (payload) VALUES (NULL), (NULL)", case(
        [[ATOMS[2]], [ATOMS[2]]], columns=("payload",))


def negatives():
    yield case([[ATOMS[0]]], columns=()), "rejected", "at least one target column"
    yield case([[ATOMS[0], ATOMS[1]]], columns=("id", "ID")), "rejected", "cannot repeat a column"
    yield case([[ATOMS[0]]]), "rejected", "target width must match"
    yield {"sql": "INSERT INTO main.ir_insert (computed) VALUES (1)", "declarations": DECLARATIONS}, "analysis-rejected", "cannot INSERT into generated column"
    yield {"sql": "INSERT INTO main.ir_insert (missing) VALUES (1)", "declarations": DECLARATIONS}, "analysis-rejected", "Column missing does not exist"


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
                require(isinstance(replies, list) and len(replies) == len(batch), "insert-batch-incomplete", replies)
                for (key, current), response in zip(batch, replies):
                    outcome = probe(current, response, db, ledger, key)
                    if key.startswith("finite/"):
                        observed[finite_id(response)] += 1
                    forms[f"height/{len(response['rows'])}"] += 1
                    forms[f"columns/{','.join(response['target'][2])}"] += 1
                    forms[f"error/{outcome['statement']['evaluationError'] is not None}"] += 1
            require(set(observed) == finite_ids() and set(observed.values()) == {1}, "insert-finite-incomplete", list(observed.items()))
            required = {f"height/{number}" for number in range(1, 6)}
            required |= {"columns/id,payload", "columns/payload,id", "columns/id", "columns/payload", "error/True", "error/False"}
            require(required <= forms.keys(), "insert-structure-not-observed", dict(forms))
            for index, (sql, item) in enumerate(analyzed()):
                current = {"sql": sql, "declarations": DECLARATIONS, "bindings": item["bindings"]}
                key = f"analysis/{index}"
                ledger.record(key, "attempted", input=current, expected=item)
                probe(item, worker.request(current), db, ledger, key, original=sql)
            for index, (current, status, message) in enumerate(negatives()):
                key = f"negative/{index}"
                ledger.record(key, "attempted", input=current)
                response = worker.request(current)
                require(response["status"] == status and message in response["message"], "insert-negative-boundary-mismatch", response)
                ledger.record(key, "rejected", response=response)
            counts = complete_observations(ledger, settings)
        except Exception as failure:
            write_json(args.out / "counterexample.json", {"original": current, "failure": str(failure), "shrunk": None})
            raise
        return {"counts": counts, "finiteIds": sorted(observed), "forms": dict(forms), "php": worker.environment}


def check_faults(args, db, runner=check_render_faults):
    path = "sql-semantics-sqlite/src/Ir/Mutation/Insert.php"
    fixtures = [
        RenderFault("reverse-target-order", path, "implode(', ', $columns)", "implode(', ', array_reverse($columns))",
                    case([[integer(2), integer(8)]]), "meaning-mismatch"),
        RenderFault("override-inherited-conflict", path, "InsertConflict::Inherit => ''", "InsertConflict::Inherit => ' OR ABORT'",
                    case([[integer(2), integer(8)], [ATOMS[1], ATOMS[0]]]), "meaning-mismatch"),
        RenderFault("discard-target-schema", path, "$this->target->schema->quoted()", "'temp'",
                    case([[integer(2), integer(8)]]), "meaning-mismatch"),
    ]
    return runner(args, db, fixtures, probe)


if __name__ == "__main__":
    run_domain("INSERT", "sqlite-insert-v1.json", CONTRACT, FIXTURE, execute, check_faults)
