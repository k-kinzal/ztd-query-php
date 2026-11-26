"""Check observed SQLite DELETE effects using an independent row selection model."""

import itertools
import json
import random
import time
from collections import Counter

from run import TOOL, Failure, Ledger, Worker, compare, digest, require, write_json
from sqlite_domain import RenderFault, check_render_faults, complete_observations, run_domain
from sqlite_engine import typed
from sqlite_relational_cases import children, evaluate, truth

CONTRACT = "60274d9a4bf82c40f5363ead740a7465bba8bca359dfe9ead7e35f8de185cb7b"
RETURNING_CONTRACT = "fc81fff184fd532c12fde78b63740676c6d40678331597a75409c95bdb6e97b0"
MAIN = [(0, "zero"), (1, "first"), (1, "first"), (None, "null"), (-1, b"minus"), ("1", "text"), (2.5, "real")]
TEMP = [(1, "temp"), (2, "safe"), (None, "temp-null")]
ROWS = {"main": MAIN, "temp": TEMP}
DECLARATIONS = ["CREATE TABLE ir_delete (key BLOB, value BLOB)", "CREATE TEMP TABLE ir_delete (key BLOB, value BLOB)"]
SCHEMA = "; ".join(DECLARATIONS) + """
;
CREATE TABLE ir_delete_log (key BLOB, value BLOB);
INSERT INTO main.ir_delete VALUES (0,'zero'),(1,'first'),(1,'first'),(NULL,'null'),(-1,X'6D696E7573'),('1','text'),(2.5,'real');
INSERT INTO temp.ir_delete VALUES (1,'temp'),(2,'safe'),(NULL,'temp-null');
CREATE TRIGGER main.ir_delete_audit AFTER DELETE ON main.ir_delete
BEGIN INSERT INTO ir_delete_log VALUES (OLD.key, OLD.value); END;
"""
PREDICATES = [
    None, ["integer", "0"], ["null"], ["column", 0],
    ["comparison", "=", ["column", 0], ["parameter", "02"]],
    ["comparison", "IS", ["column", 0], ["parameter", "02"]],
]
SETTINGS = (0, 1, None)


def item(schema="main", predicate=None, bindings=None, columns=("key", "value")):
    return {"operation": "delete", "target": [schema, "ir_delete", list(columns)],
            "where": predicate, "bindings": {} if bindings is None else bindings}


def finite_cases():
    for schema, predicate, setting in itertools.product(("main", "temp"), PREDICATES, SETTINGS):
        parameters = {"2": setting} if predicate in PREDICATES[4:] else {}
        yield dict(item(schema, predicate, parameters), bindingSetting=setting)


def finite_id(request, response):
    return (response["target"][0], PREDICATES.index(response["where"]), SETTINGS.index(request["bindingSetting"]))


def finite_ids():
    return {(("main", "temp")[code // 18], code // 3 % 6, code % 3) for code in range(36)}


def generated(seed, count):
    rng = random.Random(seed)

    def scalar(depth):
        if depth and rng.randrange(4) == 0:
            return ["arithmetic", rng.choice(("+", "-", "*")), scalar(depth - 1), scalar(depth - 1)]
        return rng.choice([["column", 0], ["column", 1], ["integer", str(rng.randrange(-3, 4))],
                           ["null"], ["parameter", rng.choice(("2", "02", "5"))]])

    def predicate(depth):
        kind = rng.randrange(3)
        if kind == 0:
            return ["comparison", rng.choice(("=", "<>", ">", "IS", "IS NOT")), scalar(2), scalar(2)]
        if depth and kind == 1:
            return ["logical", rng.choice(("AND", "OR")), predicate(depth - 1), predicate(depth - 1)]
        return scalar(2)

    for _ in range(count):
        where = predicate(3) if rng.getrandbits(1) else None
        names = parameter_names(where)
        values = {name: rng.choice((None, 0, 1, -2, "1", "5x", 2.5)) for name in names}
        yield item(rng.choice(("main", "temp")), where, values)


def parameter_names(where):
    names = {}
    for operand in children(where):
        if operand[0] == "parameter":
            names.setdefault(str(int(operand[1])), "?" + operand[1])
    return names


def typed_rows(rows):
    return sorted([typed(value) for value in row] for row in rows)


def partition(request):
    schema, _, columns = request["target"]
    slots = [("key", "value").index(column) for column in columns]
    removed = []
    remaining = []
    for row in ROWS[schema]:
        projected = (tuple(row[slot] for slot in slots),)
        where = request["where"]
        destinations = removed if where is None or truth(evaluate(where, projected, request["bindings"])) is True else remaining
        destinations.append(row)
    return removed, remaining


def expected(request):
    schema, _, _ = request["target"]
    removed, remaining = partition(request)
    names = parameter_names(request["where"])
    trigger_log = removed if schema == "main" else []
    return {
        "statement": {"names": [], "parameters": names, "parameterCount": max(map(int, names), default=0),
                      "readOnly": False, "changes": len(removed) + len(trigger_log), "rows": []},
        "main": typed_rows(remaining if schema == "main" else MAIN),
        "temp": typed_rows(remaining if schema == "temp" else TEMP),
        "log": typed_rows(trigger_log),
    }


def observe(db, sql, bindings):
    db.execute("SAVEPOINT effect_probe")
    try:
        statement = db.observe(sql, bindings)
        return {
            "statement": statement,
            "main": db.observe("SELECT key, value FROM main.ir_delete", {})["rows"],
            "temp": db.observe("SELECT key, value FROM temp.ir_delete", {})["rows"],
            "log": db.observe("SELECT key, value FROM main.ir_delete_log", {})["rows"],
        }
    finally:
        db.execute("ROLLBACK TO effect_probe; RELEASE effect_probe")


def probe(request, response, db, ledger=None, key=None, original=None):
    require(response["status"] == "constructed", "delete-positive-rejected", response)
    if ledger is not None:
        ledger.record(key, "constructed", response=response)
    for role in ("operation", "target", "where"):
        require(request[role] == response[role], "delete-shape-mismatch", {"role": role, "response": response})
    observed = observe(db, response["sql"], request["bindings"])
    comparison = {"observation": observed}
    if original is not None:
        comparison["original"] = observe(db, original, request["bindings"])
    if ledger is not None:
        ledger.record(key, "observed", **comparison)
    for effect in comparison.values():
        compare(expected(request), effect)
    if ledger is not None:
        ledger.record(key, "compared")


def analyzed():
    yield "DELETE FROM ir_delete", item("temp", columns=())
    yield ("DELETE FROM main.ir_delete AS d WHERE d.key = ?02",
           item("main", PREDICATES[4], {"2": 1}, ("key",)))
    yield ("DELETE FROM main.ir_delete WHERE ir_delete.key IS NULL",
           item("main", ["comparison", "IS", ["column", 0], ["null"]], columns=("key",)))
    yield ("DELETE FROM temp.ir_delete AS shadow WHERE shadow.value",
           item("temp", ["column", 0], columns=("value",)))
    yield ("DELETE FROM main.ir_delete AS d WHERE d.key IS ?02 OR d.key = ?2",
           item("main", ["logical", "OR", PREDICATES[5], ["comparison", "=", ["column", 0], ["parameter", "2"]]],
                {"2": None}, ("key",)))


def returning_contract():
    contract = TOOL / "sqlite-delete-returning-v1.json"
    require(digest(contract) == RETURNING_CONTRACT, "sqlite-returning-contract-mismatch")
    return json.loads(contract.read_text())


def negatives():
    yield item(predicate=["column", 0, 1]), "rejected", "visible occurrence"
    yield item(predicate=["column", 2]), "rejected", "visible occurrence"
    yield {"sql": "DELETE FROM unavailable", "declarations": []}, "rejected", "A mutation target requires a base table"
    transition = returning_contract()["baseSuiteTransition"]
    yield {"sql": transition["negative"], "declarations": DECLARATIONS}, "rejected", "requires an unqualified target column"


def execute(args, spec, db, worker_factory=Worker):
    settings = spec["exploration"]
    items = [(f"finite/{index}", request) for index, request in enumerate(finite_cases())]
    items.extend((f"seed/{seed}/{index}", request) for seed in settings["seeds"]
                 for index, request in enumerate(generated(seed, settings["casesPerSeed"])))
    observed = Counter()
    graph = Counter()
    current = None
    with (args.out / "events.jsonl").open("w") as stream, worker_factory(args, time.monotonic() + settings["timeoutSeconds"]) as worker:
        ledger = Ledger(stream)
        try:
            for start in range(0, len(items), 12):
                batch = items[start:start + 12]
                current = {"batch": [request for _, request in batch]}
                for key, request in batch:
                    ledger.record(key, "attempted", input=request)
                replies = worker.request(current["batch"])
                require(isinstance(replies, list) and len(replies) == len(batch), "delete-batch-incomplete", replies)
                for (key, current), response in zip(batch, replies):
                    probe(current, response, db, ledger, key)
                    if key.startswith("finite/"):
                        observed[finite_id(current, response)] += 1
                    graph.update(operand[0] for operand in children(response["where"]))
            require(set(observed) == finite_ids() and set(observed.values()) == {1}, "delete-finite-incomplete", list(observed.items()))
            require({"comparison", "logical", "arithmetic", "column", "parameter", "null", "integer"} <= graph.keys(),
                    "delete-exploration-incomplete", dict(graph))
            for index, (sql, reference) in enumerate(analyzed()):
                key = f"analysis/{index}"
                current = {"sql": sql, "declarations": DECLARATIONS, "bindings": reference["bindings"]}
                ledger.record(key, "attempted", input=current, expected=reference)
                probe(reference, worker.request(current), db, ledger, key, original=sql)
            for index, (current, status, message) in enumerate(negatives()):
                key = f"negative/{index}"
                ledger.record(key, "attempted", input=current)
                response = worker.request(current)
                require(response["status"] == status and message in response["message"], "delete-negative-boundary-mismatch", response)
                ledger.record(key, "rejected", response=response)
            counts = complete_observations(ledger, settings)
        except Exception as error:
            write_json(args.out / "counterexample.json", {"original": current, "failure": str(error), "shrunk": None})
            raise
        return {"counts": counts, "finiteIds": sorted(observed), "operands": dict(graph), "php": worker.environment}


def check_faults(args, db, runner=check_render_faults):
    delete = "sql-semantics-sqlite/src/Ir/Mutation/Delete.php"
    renderer = "sql-semantics-sqlite/src/Ir/SelectSql.php"
    fixtures = [
        RenderFault("schema", delete, "$renderer->source($this->target, 0)", "'\"temp\".\"ir_delete\" AS \"__ztd_source\"'",
                    item(predicate=PREDICATES[4], bindings={"2": 1}), "meaning-mismatch"),
        RenderFault("predicate", delete, "return 'DELETE FROM ' . $target . $where . $returning;", "return 'DELETE FROM ' . $target . $returning;",
                    item(predicate=["comparison", "=", ["column", 0], ["integer", "1"]]), "meaning-mismatch"),
        RenderFault("slot", renderer, "$source->columns[$expression->slot]->quoted()", "$source->columns[1 - $expression->slot]->quoted()",
                    item(predicate=["column", 0]), "meaning-mismatch"),
    ]
    return runner(args, db, fixtures, probe)


if __name__ == "__main__":
    run_domain("DELETE", "sqlite-delete-v1.json", CONTRACT, SCHEMA, execute, check_faults)
