"""Check complete VALUES statements against an independent typed bag oracle."""

import itertools
import random
import time
from collections import Counter

from run import Ledger, Worker, compare, require, write_json
from sqlite_domain import RenderFault, check_render_faults, complete_observations, run_domain
from sqlite_engine import typed
from sqlite_relational_cases import children, evaluate

CONTRACT = "2c1f212e5fae261258dc8c0fb450fd663a41fffc965c7988346b6dcfa39c6ad3"
ATOMS = [["integer", "0"], ["integer", "-2"], ["null"], ["parameter", "02"]]
SHAPES = [(1, 1), (2, 1), (1, 2), (2, 2)]


def case(rows, bindings=None):
    return {"operation": "values", "rows": rows, "bindings": {} if bindings is None else bindings}


def finite_cases():
    for height, width in SHAPES:
        for flat in itertools.product(ATOMS, repeat=height * width):
            rows = [list(flat[start:start + width]) for start in range(0, len(flat), width)]
            yield case(rows, {"2": 7} if ATOMS[-1] in flat else {})


def finite_id(rows):
    code = 0
    for row in rows:
        for atom in row:
            code = code * 4 + ATOMS.index(atom)
    return (len(rows), len(rows[0]), code)


def expected_ids():
    return {(height, width, index) for height, width in SHAPES for index in range(4 ** (height * width))}


def names(rows):
    bindings = {}
    for row in rows:
        for value in row:
            for operand in children(value):
                if operand[0] == "parameter":
                    bindings.setdefault(str(int(operand[1])), "?" + operand[1])
    return bindings


def seeded(seed, size):
    rng = random.Random(seed)

    def scalar(depth):
        if depth and rng.getrandbits(1):
            return ["arithmetic", rng.choice(("+", "-", "*")), scalar(depth - 1), scalar(depth - 1)]
        return rng.choice(ATOMS + [["parameter", "2"], ["parameter", "5"], ["integer", str(rng.randrange(-10, 11))]])

    for _ in range(size):
        width = rng.randrange(1, 6)
        rows = [[scalar(rng.randrange(4)) for _ in range(width)] for _ in range(rng.randrange(1, 6))]
        yield case(rows, {slot: rng.choice((None, 1, 2.5, "-5", "some text")) for slot in names(rows)})


def expected(request):
    rows = request["rows"]
    parameters = names(rows)
    return {
        "names": [f"column{column}" for column in range(1, len(rows[0]) + 1)],
        "parameters": parameters,
        "parameterCount": max(map(int, parameters), default=0),
        "rows": sorted([typed(evaluate(value, (), request["bindings"])) for value in row] for row in rows),
        "readOnly": True, "changes": 0,
    }


def probe(request, response, db, ledger=None, key=None, original=None):
    require(response["status"] == "constructed", "values-positive-rejected", response)
    if ledger is not None:
        ledger.record(key, "constructed", response=response)
    require(response["operation"] == "values" and response["rows"] == request["rows"], "values-shape-mismatch", response)
    observations = {"observation": db.observe(response["sql"], request["bindings"])}
    if original is not None:
        observations["original"] = db.observe(original, request["bindings"])
    if ledger is not None:
        ledger.record(key, "observed", **observations)
    for result in observations.values():
        compare(expected(request), result)
    if ledger is not None:
        ledger.record(key, "compared")


def analyzed():
    yield "VALUES (0), (0), (NULL)", case([[ATOMS[0]], [ATOMS[0]], [ATOMS[2]]])
    yield ("VALUES (?02, 1 + 2), (NULL, -3)",
           case([[ATOMS[3], ["arithmetic", "+", ["integer", "1"], ["integer", "2"]]], [ATOMS[2], ["integer", "-3"]]], {"2": "3"}))
    yield "VALUES (?02, ?2), (?5, ?002)", case([[ATOMS[3], ["parameter", "2"]], [["parameter", "5"], ["parameter", "002"]]], {"2": 4, "5": None})
    yield "VALUES ((-2) * +3)", case([[["arithmetic", "*", ["integer", "-2"], ["integer", "3"]]]])


def negatives():
    yield case([]), "nonempty list of rows"
    yield case([[]]), "1 to 2000 operands"
    yield case([[ATOMS[0]], [ATOMS[0], ATOMS[1]]]), "same width"
    yield case([[["arithmetic", "+", ATOMS[0], ["column", 0]]]]), "visible occurrence"


def execute(args, specification, db, worker_factory=Worker):
    settings = specification["exploration"]
    items = [(f"finite/{index}", request) for index, request in enumerate(finite_cases())]
    items.extend((f"seed/{seed}/{index}", request) for seed in settings["seeds"]
                 for index, request in enumerate(seeded(seed, settings["casesPerSeed"])))
    observed = Counter()
    forms = Counter()
    current = None
    with (args.out / "events.jsonl").open("w") as stream, worker_factory(args, time.monotonic() + settings["timeoutSeconds"]) as worker:
        ledger = Ledger(stream)
        try:
            for start in range(0, len(items), 16):
                batch = items[start:start + 16]
                current = {"batch": [request for _, request in batch]}
                for key, request in batch:
                    ledger.record(key, "attempted", input=request)
                replies = worker.request(current["batch"])
                require(isinstance(replies, list) and len(replies) == len(batch), "values-batch-incomplete", replies)
                for (key, current), response in zip(batch, replies):
                    probe(current, response, db, ledger, key)
                    if key.startswith("finite/"):
                        observed[finite_id(response["rows"])] += 1
                    forms.update(operand[0] for row in response["rows"] for value in row for operand in children(value))
            require(set(observed) == expected_ids() and set(observed.values()) == {1}, "values-finite-incomplete", list(observed.items()))
            require(set(forms) == {"integer", "null", "parameter", "arithmetic"}, "values-structure-not-observed", dict(forms))
            for index, (sql, reference) in enumerate(analyzed()):
                current = {"sql": sql, "declarations": [], "bindings": reference["bindings"]}
                key = f"analysis/{index}"
                ledger.record(key, "attempted", input=current, expected=reference)
                probe(reference, worker.request(current), db, ledger, key, original=sql)
            for index, (current, message) in enumerate(negatives()):
                key = f"negative/{index}"
                ledger.record(key, "attempted", input=current)
                response = worker.request(current)
                require(response["status"] == "rejected" and message in response["message"], "values-negative-boundary-mismatch", response)
                ledger.record(key, "rejected", response=response)
            counts = complete_observations(ledger, settings)
        except Exception as error:
            write_json(args.out / "counterexample.json", {"original": current, "failure": str(error), "shrunk": None})
            raise
        return {"counts": counts, "finiteIds": sorted(observed), "operands": dict(forms), "php": worker.environment}


def check_faults(args, db, runner=check_render_faults):
    fixtures = [
        RenderFault("drop-row", "sql-semantics-sqlite/src/Ir/Rows/ValuesQuery.php", "$rows[] = $row->toString();",
                    "if ($rows === []) { $rows[] = $row->toString(); }", case([[ATOMS[0]], [ATOMS[0]]]), "meaning-mismatch"),
        RenderFault("swap-positions", "sql-semantics-sqlite/src/Ir/Rows/ValueRow.php", "implode(', ', $operands)",
                    "implode(', ', array_reverse($operands))", case([[ATOMS[0], ATOMS[2]]]), "meaning-mismatch"),
    ]
    return runner(args, db, fixtures, probe)


if __name__ == "__main__":
    run_domain("VALUES", "sqlite-values-v1.json", CONTRACT, "", execute, check_faults)
