"""Check a single ordinary CTE with a VALUES definition and a resolved consumer."""

import itertools
import random
import time
from collections import Counter

from run import Ledger, Worker, compare, require, write_json
from run_sqlite_values import names as definition_parameters, seeded as seeded_values
from sqlite_domain import RenderFault, check_render_faults, complete_observations, run_domain
from sqlite_engine import typed
from sqlite_relational_cases import evaluate, truth
from sqlite_result_cases import GRAPH_FIELDS, allowed_result, bound_value, integer, result_parameters

CONTRACT = "8ab3e4c2986b7c9eb88beab723422db103913de303c57f11c25059553d9e0ea1"
ATOMS = [integer(0), integer(-2), ["null"]]
SCHEMA = "CREATE TABLE c (a, b)"
DATA = SCHEMA + "; INSERT INTO c VALUES (542, 918), (83, 43);"


def case(rows, slot=0, names=("a", "b"), columns=None, **options):
    return dict(
        {"source": None, "local": {"names": list(names), "rows": rows},
         "columns": [[["column", slot], "answer"]] if columns is None else columns,
         "joins": [], "where": None, "orderBy": [], "distinct": False, "limit": None, "bindings": {}},
        **options,
    )


def finite_cases():
    for height, slot in itertools.product((1, 2), (0, 1)):
        for flat in itertools.product(ATOMS, repeat=height * 2):
            yield case([list(flat[start:start + 2]) for start in range(0, len(flat), 2)], slot)


def finite_id(response):
    rows = response["local"]["rows"]
    code = 0
    for row in rows:
        for operand in row:
            code = code * 3 + ATOMS.index(operand)
    return (len(rows), response["columns"][0][0][1], code)


def finite_ids():
    return {(height, slot, index) for height in (1, 2) for slot in (0, 1) for index in range(9 ** height)}


def seeded(seed, count):
    rng = random.Random(seed)
    for definition in seeded_values(seed ^ 391, count):
        rows = definition["rows"]
        width = len(rows[0])
        expressions = [["column", rng.randrange(width)], ["arithmetic", "-", ["column", rng.randrange(width)], integer(2)]]
        columns = [[expression, name] for expression, name in zip(expressions, ("p", "q"))]
        where = rng.choice((None, ["comparison", "IS NOT", ["column", rng.randrange(width)], ["null"]]))
        order = rng.choice(([], [[rng.randint(1, 2), bool(rng.getrandbits(1)), bool(rng.getrandbits(1))]]))
        limit = rng.choice((None, [integer(rng.randrange(-1, 6)), integer(rng.randrange(-1, 4)), bool(rng.getrandbits(1))]))
        names = [f'Col "{index}"' for index in range(width)]
        yield case(rows, names=names, columns=columns, where=where, orderBy=order, limit=limit,
                   distinct=bool(rng.getrandbits(1)), bindings=definition["bindings"])


def expected(item):
    bindings = item["bindings"]
    definition = item["local"]["rows"]
    native = definition_parameters(definition)
    for slot, label in result_parameters(item).items():
        native.setdefault(slot, label)
    rows = []
    for input_row in definition:
        scope = (tuple(evaluate(value, (), bindings) for value in input_row),)
        if item["where"] is None or truth(evaluate(item["where"], scope, bindings)) is True:
            rows.append([typed(evaluate(value, scope, bindings)) for value, _ in item["columns"]])
    return {"names": [name for _, name in item["columns"]], "parameters": native,
            "parameterCount": max(map(int, native), default=0), "rows": rows, "readOnly": True, "changes": 0}


def probe(item, response, db, ledger=None, key=None, original=None):
    require(response["status"] == "constructed", "local-source-positive-rejected", response)
    if ledger is not None:
        ledger.record(key, "constructed", response=response)
    for field in ("local", *GRAPH_FIELDS):
        require(response[field] == item[field], "local-source-shape-mismatch", {"field": field, "response": response})
    observed = {"observation": db.observe(response["sql"], item["bindings"], preserve_order=True)}
    if original is not None:
        observed["original"] = db.observe(original, item["bindings"], preserve_order=True)
    if ledger is not None:
        ledger.record(key, "observed", **observed)
    count, offset = -1, 0
    if item["limit"] is not None:
        limit_count, limit_offset, _ = item["limit"]
        count = bound_value(limit_count, item["bindings"])
        offset = 0 if limit_offset is None else max(0, bound_value(limit_offset, item["bindings"]))
    for observation in observed.values():
        allowed_result(expected(item), observation, item["distinct"], item["orderBy"], offset, count)
    if ledger is not None:
        ledger.record(key, "compared")


def analyzed():
    yield "WITH c(a, b) AS (VALUES (0, -2), (0, -2), (NULL, 0)) SELECT c.b AS answer FROM c", case(
        [[ATOMS[0], ATOMS[1]], [ATOMS[0], ATOMS[1]], [ATOMS[2], ATOMS[0]]], 1)
    yield "WITH c(a, b) AS (VALUES (?02, 0), (NULL, -2)) SELECT v.b AS answer FROM c AS v WHERE a IS ?2 ORDER BY 1 DESC", case(
        [[["parameter", "02"], ATOMS[0]], [ATOMS[2], ATOMS[1]]], 1, where=["comparison", "IS", ["column", 0], ["parameter", "2"]],
        orderBy=[[1, True, False]], bindings={"2": 3})
    yield 'WITH c("ID", b) AS (VALUES (-2, 0), (0, -2), (0, -2)) SELECT DISTINCT c.id AS answer FROM c ORDER BY 1 LIMIT 1 OFFSET 1', case(
        [[ATOMS[1], ATOMS[0]], [ATOMS[0], ATOMS[1]], [ATOMS[0], ATOMS[1]]], names=("ID", "b"),
        distinct=True, orderBy=[[1, False, True]], limit=[integer(1), integer(1), False])
    yield "WITH c(a, b) AS (VALUES (NULL, 3), (5, NULL)) SELECT b AS answer FROM c ORDER BY 1 NULLS LAST", case(
        [[ATOMS[2], integer(3)], [integer(5), ATOMS[2]]], 1, orderBy=[[1, False, False]])


def negatives():
    yield case([[ATOMS[0], ATOMS[1]]], names=("a",)), "must match the VALUES width"
    yield case([[ATOMS[0], ATOMS[1]]], names=("a", "A")), "cannot repeat a column name"
    yield case([[ATOMS[0], ATOMS[1]]], slot=2), "visible occurrence"
    yield case([[ATOMS[0], ATOMS[1]]], columns=[[["column", 0, 1], "answer"]]), "visible occurrence"


def expanded_admission(item, db, worker, ledger):
    key = "join/previously-refused"
    ledger.record(key, "attempted", input=item)
    response = worker.request(item)
    require(response["status"] == "constructed" and response["source"] == ["local", 0]
            and response["locals"] == [item["local"]] and response["joins"] == item["joins"]
            and response["columns"] == item["columns"], "joined-local-admission-mismatch", response)
    ledger.record(key, "constructed", response=response)
    result = db.observe(response["sql"], {})
    ledger.record(key, "observed", observation=result)
    compare({"names": ["answer"], "parameters": {}, "parameterCount": 0,
             "rows": [[typed(0)], [typed(0)]], "readOnly": True, "changes": 0}, result)
    ledger.record(key, "compared")



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
                require(isinstance(replies, list) and len(replies) == len(batch), "local-source-batch-incomplete", replies)
                for (key, current), response in zip(batch, replies):
                    probe(current, response, db, ledger, key)
                    if key.startswith("finite/"):
                        observed[finite_id(response)] += 1
                    forms[f"width/{len(response['local']['names'])}"] += 1
                    forms[f"height/{len(response['local']['rows'])}"] += 1
                    for field in ("orderBy", "where", "distinct", "limit"):
                        forms[f"{field}/{bool(response[field])}"] += 1
            require(set(observed) == finite_ids() and set(observed.values()) == {1}, "local-source-finite-incomplete", list(observed.items()))
            required = {f"{field}/{present}" for field in ("orderBy", "where", "distinct", "limit") for present in (True, False)}
            required |= {f"{field}/{number}" for field in ("width", "height") for number in range(1, 6)}
            require(required <= forms.keys(), "local-source-structure-not-observed", dict(forms))
            for index, (sql, item) in enumerate(analyzed()):
                current = {"sql": sql, "declarations": [SCHEMA], "bindings": item["bindings"]}
                key = f"analysis/{index}"
                ledger.record(key, "attempted", input=current, expected=item)
                probe(item, worker.request(current), db, ledger, key, original=sql)
            current = case([[ATOMS[0], ATOMS[1]]], joins=[["INNER", ["main", "c", ["a", "b"]], None]])
            expanded_admission(current, db, worker, ledger)
            for index, (current, message) in enumerate(negatives()):
                key = f"negative/{index}"
                ledger.record(key, "attempted", input=current)
                response = worker.request(current)
                require(response["status"] == "rejected" and message in response["message"] and
                        response["exception"] == r"SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction",
                        "local-source-negative-boundary-mismatch", response)
                ledger.record(key, "rejected", response=response)
            counts = complete_observations(ledger, settings)
        except Exception as failure:
            write_json(args.out / "counterexample.json", {"original": current, "failure": str(failure), "shrunk": None})
            raise
        return {"counts": counts, "finiteIds": sorted(observed), "forms": dict(forms), "php": worker.environment}


def check_faults(args, db, runner=check_render_faults):
    path = "sql-semantics-sqlite/src/Ir/SelectSql.php"
    fixtures = [
        RenderFault("reverse-definition-slots", path, "implode(', ', $names)", "implode(', ', array_reverse($names))",
                    case([[ATOMS[0], ATOMS[1]]]), "ordered-band-mismatch"),
        RenderFault("rename-first-definition-parameter", path, "$source->query->toString()",
                    "str_replace('?02', '?2', $source->query->toString())",
                    case([[["parameter", "02"], ATOMS[1]]], columns=[[["parameter", "2"], "answer"]], bindings={"2": 3}),
                    "meaning-mismatch"),
    ]
    return runner(args, db, fixtures, probe)


if __name__ == "__main__":
    run_domain("local source", "sqlite-local-source-v2.json", CONTRACT, DATA, execute, check_faults)
