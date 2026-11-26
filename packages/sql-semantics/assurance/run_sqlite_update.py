"""Compare complete SQLite UPDATE effects against a fixture-specific old-row evaluator."""

import itertools
import random
import time
from collections import Counter

from run import Ledger, Worker, compare, require, write_json
from run_sqlite_delete import typed_rows
from run_sqlite_values import names
from sqlite_domain import RenderFault, check_render_faults, complete_observations, run_domain
from sqlite_engine import typed
from sqlite_relational_cases import evaluate, numeric, truth
from sqlite_result_cases import integer

CONTRACT = "71d081d6b35c5b38855b343617660ebb07e49f5aeb5775c6feed047208478f1d"
COLUMNS = ("left_value", "right_value", "key", "computed")
TAIL = ", right_value BLOB, computed GENERATED ALWAYS AS (left_value + right_value) STORED)"
DECLARATIONS = [
    "CREATE TABLE ir_update (key BLOB, left_value BLOB NOT NULL ON CONFLICT IGNORE" + TAIL,
    "CREATE TEMP TABLE ir_update (key BLOB, left_value BLOB NOT NULL" + TAIL,
]
FIXTURE = "; ".join(DECLARATIONS) + """
;
INSERT INTO main.ir_update (key,left_value,right_value) VALUES (1,7,3),(1,7,3),(2,11,NULL),(NULL,'7',5),('1',1.5,2);
INSERT INTO temp.ir_update (key,left_value,right_value) VALUES (1,23,17),(2,27,NULL),(NULL,5,19);
CREATE TABLE ir_update_log (key BLOB, old_left BLOB, old_right BLOB, new_left BLOB, new_right BLOB);
CREATE TRIGGER main.ir_update_audit AFTER UPDATE ON main.ir_update
BEGIN
  INSERT INTO ir_update_log VALUES (NEW.key, OLD.left_value, OLD.right_value, NEW.left_value, NEW.right_value);
END;
"""
MAIN = [(7, 3, 1), (7, 3, 1), (11, None, 2), ("7", 5, None), (1.5, 2, "1")]
TEMP = [(23, 17, 1), (27, None, 2), (5, 19, None)]
TABLES = ("main", "temp")
POLICIES = ("inherit", "abort", "ignore")
LEFT = [["null"], integer(0), ["column", 1]]
RIGHT = [["null"], integer(0), ["column", 0]]


def case(assignments, schema="main", columns=COLUMNS[:2], where=None, conflict="inherit", bindings=None):
    return {"operation": "update", "target": [schema, "ir_update", list(columns)], "assignments": assignments,
            "where": where, "conflict": conflict, "bindings": {} if bindings is None else bindings}


def finite_cases():
    for schema, policy, left, right in itertools.product(TABLES, POLICIES, LEFT, RIGHT):
        yield case([[0, left], [1, right]], schema, conflict=policy)


def finite_id(response):
    (_, left), (_, right) = response["assignments"]
    return (response["target"][0], response["conflict"], LEFT.index(left) * 3 + RIGHT.index(right))


def finite_ids():
    return {(TABLES[index // 27], POLICIES[index // 9 % 3], index % 9) for index in range(54)}


def parameters(assignments, where):
    roots = [value for _, value in assignments]
    if where is not None:
        roots.append(where)
    return names([roots])


def seeded(seed, count):
    rng = random.Random(seed)

    def scalar(depth):
        if depth and rng.getrandbits(1):
            return ["arithmetic", rng.choice(("+", "-", "*")), scalar(depth - 1), scalar(depth - 1)]
        return rng.choice([["null"], integer(rng.randrange(-4, 7)), ["column", rng.randrange(4)],
                           ["parameter", rng.choice(("1", "02", "2", "5"))]])

    for _ in range(count):
        assigned = [[rng.randrange(2), scalar(rng.randrange(4))] for _ in range(rng.randrange(1, 5))]
        where = rng.choice((None, ["column", 2], ["comparison", "IS", ["column", 2], scalar(0)],
                            ["comparison", ">", ["column", 1], scalar(1)]))
        bindings = {slot: rng.choice((None, 0, 1, "3", "text", 1.75)) for slot in parameters(assigned, where)}
        yield case(assigned, rng.choice(TABLES), COLUMNS, where, rng.choice(POLICIES), bindings)


def physical_row(row):
    left, right, key = row
    return (left, right, key, None if right is None else numeric(left) + numeric(right))


def expected(request, after_main=None):
    schema, _, columns = request["target"]
    policy = request["conflict"]
    old = MAIN if schema == "main" else TEMP
    transformed = []
    count, log, error = 0, [], None
    returning = request.get("returning", [])
    returned = []
    result_positions = [COLUMNS.index(columns[value[1]]) for value, _ in returning]
    for record in old:
        left, right, key, computed = physical_row(record)
        values = dict(zip(COLUMNS, (left, right, key, computed)))
        scope = (tuple(values[name] for name in columns),)
        if request["where"] is not None and truth(evaluate(request["where"], scope, request["bindings"])) is not True:
            transformed.append(record)
            continue
        writes = {columns[slot]: value for slot, value in request["assignments"]}
        replacements = {name: evaluate(value, scope, request["bindings"]) for name, value in writes.items()}
        new_left = replacements.get("left_value", left)
        new_right = replacements.get("right_value", right)
        if new_left is None:
            if policy == "ignore" or (policy == "inherit" and schema == "main"):
                transformed.append(record)
                continue
            error = {"code": 19, "message": "NOT NULL constraint failed: ir_update.left_value"}
            count, log, returned, transformed = 0, [], [], old
            break
        updated = (new_left, new_right, key)
        if returning:
            row = physical_row(updated)
            returned.append(tuple(row[index] for index in result_positions))
        transformed.append(after_main(updated) if schema == "main" and after_main is not None else updated)
        count += 1
        if schema == "main":
            log.append((key, left, right, new_left, new_right))
    labels = parameters(request["assignments"], request["where"])
    return {
        "statement": {"names": [label for _, label in returning], "parameters": labels, "parameterCount": max(map(int, labels), default=0),
                      "readOnly": False, "changes": count, "rows": typed_rows(returned), "evaluationError": error},
        "main": typed_rows(physical_row(row) for row in (transformed if schema == "main" else MAIN)),
        "temp": typed_rows(physical_row(row) for row in (transformed if schema == "temp" else TEMP)),
        "log": typed_rows(log),
    }


def observe(db, sql, bindings):
    db.execute("SAVEPOINT update_probe")
    try:
        statement = db.observe(sql, bindings, capture_evaluation_error=True)
        statement["changes"] = db.changes(db.db)
        return {
            "statement": statement,
            "main": db.observe("SELECT left_value,right_value,key,computed FROM main.ir_update", {})["rows"],
            "temp": db.observe("SELECT left_value,right_value,key,computed FROM temp.ir_update", {})["rows"],
            "log": db.observe("SELECT key,old_left,old_right,new_left,new_right FROM main.ir_update_log", {})["rows"],
        }
    finally:
        db.execute("ROLLBACK TO update_probe; RELEASE update_probe")


def probe(request, response, db, ledger=None, key=None, original=None):
    require(response["status"] == "constructed", "update-positive-rejected", response)
    if ledger is not None:
        ledger.record(key, "constructed", response=response)
    for role in ("operation", "target", "conflict", "assignments", "where"):
        require(response[role] == request[role], "update-shape-mismatch", {"role": role, "response": response})
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
    yield "UPDATE main.ir_update AS a SET left_value = a.right_value, right_value = a.left_value", case(
        [[0, LEFT[2]], [1, RIGHT[2]]])
    yield "UPDATE main.ir_update SET right_value = ?02, right_value = ?2 WHERE key IS ?5", case(
        [[0, ["parameter", "02"]], [0, ["parameter", "2"]]], columns=("right_value", "key"),
        where=["comparison", "IS", ["column", 1], ["parameter", "5"]], bindings={"2": "text", "5": 1})
    yield "UPDATE OR ABORT main.ir_update SET left_value = NULL WHERE key IS ?02", case(
        [[0, LEFT[0]]], columns=("left_value", "key"), conflict="abort",
        where=["comparison", "IS", ["column", 1], ["parameter", "02"]], bindings={"2": 1})
    yield "UPDATE OR IGNORE ir_update SET left_value = 0, right_value = NULL WHERE key", case(
        [[0, LEFT[1]], [1, RIGHT[0]]], schema="temp", columns=COLUMNS[:3], conflict="ignore", where=["column", 2])
    yield "UPDATE OR ABORT temp.ir_update SET right_value = right_value + ?02 WHERE key IS ?2", case(
        [[0, ["arithmetic", "+", ["column", 0], ["parameter", "02"]]]], schema="temp", columns=("right_value", "key"),
        where=["comparison", "IS", ["column", 1], ["parameter", "2"]], conflict="abort", bindings={"2": 1})
    yield "UPDATE main.ir_update SET right_value = computed WHERE key IS NULL", case(
        [[0, ["column", 1]]], columns=("right_value", "computed", "key"),
        where=["comparison", "IS", ["column", 2], ["null"]])


def negatives():
    yield case([]), "rejected", "nonempty ordered assignment list"
    yield case([[-1, LEFT[0]]]), "rejected", "slot must be nonnegative"
    yield case([[2, LEFT[0]]]), "rejected", "slot must belong to the UPDATE target"
    yield case([[0, ["column", 0, 1]]]), "rejected", "visible occurrence"
    yield case([[0, LEFT[0]]], where=["column", 2]), "rejected", "visible occurrence"
    yield {"sql": "UPDATE main.ir_update SET computed = 3", "declarations": DECLARATIONS}, "analysis-rejected", "cannot UPDATE generated column"
    yield {"sql": "UPDATE main.ir_update SET missing = 3", "declarations": DECLARATIONS}, "analysis-rejected", "Column missing does not exist"


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
                require(isinstance(replies, list) and len(replies) == len(batch), "update-batch-incomplete", replies)
                for (key, current), response in zip(batch, replies):
                    outcome = probe(current, response, db, ledger, key)
                    if key.startswith("finite/"):
                        observed[finite_id(response)] += 1
                    writes = [slot for slot, _ in response["assignments"]]
                    forms[f"count/{len(writes)}"] += 1
                    forms[f"repeated/{len(set(writes)) < len(writes)}"] += 1
                    forms[f"filter/{response['where'] is not None}"] += 1
                    forms[f"error/{outcome['statement']['evaluationError'] is not None}"] += 1
            require(set(observed) == finite_ids() and set(observed.values()) == {1}, "update-finite-incomplete", list(observed.items()))
            required = {f"count/{number}" for number in range(1, 5)}
            required |= {f"{role}/{included}" for role in ("repeated", "filter", "error") for included in (True, False)}
            require(required <= forms.keys(), "update-structure-not-observed", dict(forms))
            for index, (sql, item) in enumerate(analyzed()):
                current = {"sql": sql, "declarations": DECLARATIONS, "bindings": item["bindings"]}
                key = f"analysis/{index}"
                ledger.record(key, "attempted", input=current, expected=item)
                probe(item, worker.request(current), db, ledger, key, original=sql)
            for index, (current, status, message) in enumerate(negatives()):
                key = f"negative/{index}"
                ledger.record(key, "attempted", input=current)
                response = worker.request(current)
                require(response["status"] == status and message in response["message"], "update-negative-boundary-mismatch", response)
                ledger.record(key, "rejected", response=response)
            counts = complete_observations(ledger, settings)
        except Exception as error:
            write_json(args.out / "counterexample.json", {"original": current, "failure": str(error), "shrunk": None})
            raise
        return {"counts": counts, "finiteIds": sorted(observed), "forms": dict(forms), "php": worker.environment}


def check_faults(args, db, runner=check_render_faults):
    update = "sql-semantics-sqlite/src/Ir/Mutation/Update.php"
    render = "sql-semantics-sqlite/src/Ir/SelectSql.php"
    fixtures = [
        RenderFault("discard-later-assignment", update, "implode(', ', $assignments)", "$assignments[0]",
                    case([[1, RIGHT[0]], [1, RIGHT[2]]]), "meaning-mismatch"),
        RenderFault("read-other-slot", render, "$source->columns[$expression->slot]->quoted()",
                    "$source->columns[1 - $expression->slot]->quoted()",
                    case([[1, RIGHT[2]]]), "meaning-mismatch"),
        RenderFault("override-inherited-conflict", update, "UpdateConflict::Inherit => ''", "UpdateConflict::Inherit => ' OR ABORT'",
                    case([[0, LEFT[0]]]), "meaning-mismatch"),
        RenderFault("choose-other-schema", update, "$renderer->source($this->target, 0)", "'temp.ir_update AS \"__ztd_source\"'",
                    case([[0, integer(8)]]), "meaning-mismatch"),
    ]
    return runner(args, db, fixtures, probe)


if __name__ == "__main__":
    run_domain("UPDATE", "sqlite-update-v1.json", CONTRACT, FIXTURE, execute, check_faults)
