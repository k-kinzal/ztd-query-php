"""Check recursive queue semantics, resolved slots, and native output observations."""

import ctypes as c
import hashlib
import itertools
import json
import random
import time
from collections import Counter, deque

from run import TOOL, Failure, Ledger, Worker, digest, require, write_json
from run_sqlite_values import names as parameter_names
from sqlite_domain import RenderFault, check_render_faults, complete_observations, run_domain
from sqlite_engine import OBSERVER_CONTRACT, Database, ObserverAuthorizationError, typed
from sqlite_relational_cases import children, evaluate, truth
from sqlite_result_cases import GRAPH_FIELDS, allowed_result, bound_value, duplicate_key, integer, result_parameters

CONTRACT_NAME = "sqlite-recursive-source-v1.json"
CONTRACT = "2c5b8873c4d6b80e6f81f3db5423539057e29117e8577bd3f887b6f43f3c52d3"
DECLARATION = "CREATE TABLE main.walk (n, p)"
FIXTURE = DECLARATION + "; INSERT INTO main.walk VALUES (937, 918), (-40, 83);"
NULL = ["null"]
ATOMS = (integer(-1), integer(0), NULL)
SELF = ["column", 0]
PROGRESS = c.CFUNCTYPE(c.c_int, c.c_void_p)


class RecursiveDatabase(Database):
    """Adds recursion authorization and a fixed VM work budget to this fixture."""

    policy_digest = hashlib.sha256(f"{OBSERVER_CONTRACT}:{CONTRACT}".encode()).hexdigest()

    def __init__(self, library):
        path = TOOL / CONTRACT_NAME
        require(digest(path) == CONTRACT, "contract-mismatch", CONTRACT_NAME)
        settings = json.loads(path.read_text())["observer"]
        super().__init__(library)
        self.allowed_actions |= {settings["additionalAuthorizationAction"]}
        self.interval = settings["progressCallbackInterval"]
        self.max_callbacks = settings["maxProgressCallbacksPerStatement"]
        self.progress_handler = self.lib.sqlite3_progress_handler
        self.progress_handler.argtypes = (c.c_void_p, c.c_int, c.c_void_p, c.c_void_p)
        self.progress_handler.restype = None

    def observe(self, sql, bindings, preserve_order=False, capture_evaluation_error=False):
        remaining = self.max_callbacks

        @PROGRESS
        def progress(_):
            nonlocal remaining
            remaining -= 1
            return int(remaining <= 0)

        self.progress_handler(self.db, self.interval, progress, None)
        try:
            observed = super().observe(sql, bindings, preserve_order, capture_evaluation_error)
        except RuntimeError as error:
            if remaining <= 0:
                raise Failure("recursive-observer-budget", sql) from error
            raise
        finally:
            self.progress_handler(self.db, 0, None, None)
        require(remaining > 0, "recursive-observer-budget", sql)
        return observed


def compare(operator, left, right):
    return ["comparison", operator, left, right]


def arithmetic(operator, left, right):
    return ["arithmetic", operator, left, right]


TRANSITIONS = (
    (arithmetic("+", SELF, integer(1)), compare("<", SELF, integer(1))),
    (arithmetic("-", SELF, integer(1)), compare(">", SELF, integer(-1))),
    (SELF, NULL),
    (NULL, compare("IS NOT", SELF, NULL)),
)


def case(rows, step, guard, union_all, names=("n",), **options):
    return dict(
        {"source": None, "joins": [], "where": None, "orderBy": [], "distinct": False, "limit": None, "bindings": {},
         "columns": [[SELF, "answer"]],
         "local": {"names": list(names), "rows": rows, "step": step, "where": guard, "unionAll": union_all}},
        **options,
    )


def finite_cases():
    for height, union_all, (successor, guard) in itertools.product((1, 2), (False, True), TRANSITIONS):
        for values in itertools.product(ATOMS, repeat=height):
            yield case([[value] for value in values], [successor], guard, union_all)


def finite_id(response):
    definition = response["local"]
    code = 0
    for row in definition["rows"]:
        code = code * 3 + ATOMS.index(row[0])
    transition = TRANSITIONS.index((definition["step"][0], definition["where"]))
    return (len(definition["rows"]), code, transition, definition["unionAll"])


def finite_ids():
    return {(height, code, transition, bool(policy)) for height in (1, 2)
            for code in range(3 ** height) for transition in range(4) for policy in range(2)}


def seeded(seed, count):
    rng = random.Random(seed)

    def payload(width, depth, allow_reference):
        if depth and rng.random() < 0.55:
            return arithmetic(rng.choice(("+", "-", "*")), payload(width, depth - 1, allow_reference),
                              payload(width, depth - 1, allow_reference))
        choices = [*ATOMS, integer(rng.randrange(-3, 5)), ["parameter", rng.choice(("2", "02", "5"))]]
        if allow_reference:
            choices.append(["column", rng.randrange(width)])
        return rng.choice(choices)

    for _ in range(count):
        width = rng.randrange(1, 6)
        height = rng.randrange(1, 6)
        names = [f'field "{index}"' for index in range(width)]
        direction = rng.choice((1, -1))
        rows = [[integer(rng.randrange(-3, 4)), *[payload(width, 2, False) for _ in range(width - 1)]] for _ in range(height)]
        cycle = rng.random() < 0.25
        successor = [SELF, *[["column", index] for index in range(1, width)]] if cycle else [
            arithmetic("+" if direction > 0 else "-", SELF, integer(rng.randrange(1, 4))),
            *[payload(width, 3, True) for _ in range(width - 1)],
        ]
        guard = None if cycle else compare("<" if direction > 0 else ">", SELF, integer(rng.randrange(-3, 5)))
        columns = [[payload(width, 2, True), f"result {index}"] for index in range(rng.randrange(1, 5))]
        where = rng.choice((None, compare("IS NOT", ["column", rng.randrange(width)], NULL)))
        order = [] if rng.random() < 0.4 else [[rng.randrange(1, len(columns) + 1), rng.choice((False, True)), rng.choice((False, True))]]
        item = case(rows, successor, guard, False if cycle else rng.choice((False, True)), names,
                    columns=columns, where=where, orderBy=order, distinct=rng.choice((False, True)))
        item["bindings"] = {index: rng.choice((None, -1, 0, 2)) for index in parameters(item)}
        yield item


def parameters(item):
    local = item["local"]
    result = parameter_names([*local["rows"], local["step"], [local["where"]]])
    for number, label in result_parameters(item).items():
        result.setdefault(number, label)
    return result


def expected(item):
    local, bindings = item["local"], item["bindings"]
    waiting, seen, rows = deque(), set(), []

    def enqueue(row):
        if not local["unionAll"]:
            key = duplicate_key([typed(value) for value in row])
            if key in seen:
                return
            seen.add(key)
        waiting.append(row)

    for row in local["rows"]:
        enqueue(tuple(evaluate(value, (), bindings) for value in row))
    count = 0
    while waiting:
        count += 1
        require(count <= 64, "recursive-oracle-nonterminating-fixture", item)
        current = (waiting.popleft(),)
        if item["where"] is None or truth(evaluate(item["where"], current, bindings)) is True:
            rows.append([typed(evaluate(value, current, bindings)) for value, _ in item["columns"]])
        if local["where"] is None or truth(evaluate(local["where"], current, bindings)) is True:
            enqueue(tuple(evaluate(value, current, bindings) for value in local["step"]))
    names = parameters(item)
    return {"names": [name for _, name in item["columns"]], "parameters": names,
            "parameterCount": max(map(int, names), default=0), "rows": rows, "readOnly": True, "changes": 0}


def probe(item, response, db, ledger=None, key=None, original=None):
    require(response["status"] == "constructed", "recursive-source-positive-rejected", response)
    if ledger is not None:
        ledger.record(key, "constructed", response=response)
    for field in ("local", *GRAPH_FIELDS):
        require(response[field] == item[field], "recursive-source-shape-mismatch", {"field": field, "response": response})
    observed = {"observation": db.observe(response["sql"], item["bindings"], preserve_order=True)}
    if original is not None:
        observed["original"] = db.observe(original, item["bindings"], preserve_order=True)
    if ledger is not None:
        ledger.record(key, "observed", **observed)
    oracle = expected(item)
    count, offset = -1, 0
    if item["limit"] is not None:
        count_operand, offset_operand, _ = item["limit"]
        count = bound_value(count_operand, item["bindings"])
        offset = 0 if offset_operand is None else max(0, bound_value(offset_operand, item["bindings"]))
    for value in observed.values():
        allowed_result(oracle, value, item["distinct"], item["orderBy"], offset, count)
    if ledger is not None:
        ledger.record(key, "compared")
    return observed["observation"]["rows"]


def analyzed():
    increment, guard = TRANSITIONS[0]
    yield (
        "WITH RECURSIVE walk(n) AS (VALUES (-1),(0) UNION ALL SELECT n+1 FROM walk WHERE n<1) SELECT n AS answer FROM walk",
        case([[ATOMS[0]], [ATOMS[1]]], [increment], guard, True),
    )
    yield (
        "WITH walk(n) AS (SELECT 0 UNION SELECT n+1 FROM walk WHERE n<1) SELECT n AS answer FROM walk",
        case([[ATOMS[1]]], [increment], guard, False),
    )
    yield (
        "WITH RECURSIVE walk(n) AS (VALUES (NULL),(NULL),(0) UNION SELECT NULL FROM walk WHERE n IS NOT NULL) SELECT n AS answer FROM walk ORDER BY 1",
        case([[NULL], [NULL], [ATOMS[1]]], [NULL], TRANSITIONS[3][1], False, orderBy=[[1, False, True]]),
    )
    yield (
        'WITH RECURSIVE walk("N") AS (VALUES (0) UNION SELECT w.n+1 FROM walk w WHERE w.n<1) SELECT c.N AS answer FROM walk c',
        case([[ATOMS[1]]], [increment], guard, False, ("N",)),
    )
    yield (
        "WITH RECURSIVE walk(n) AS (VALUES (?02) UNION ALL SELECT n+?2 FROM walk WHERE n<?5) SELECT n AS answer FROM walk ORDER BY 1 DESC",
        case([[["parameter", "02"]]], [arithmetic("+", SELF, ["parameter", "2"])],
             compare("<", SELF, ["parameter", "5"]), True, bindings={"2": 1, "5": 4}, orderBy=[[1, True, False]]),
    )
    yield (
        "WITH RECURSIVE walk(n) AS (VALUES (0),(0) UNION SELECT n FROM walk) SELECT n AS answer FROM walk",
        case([[ATOMS[1]], [ATOMS[1]]], [SELF], None, False),
    )
    yield (
        "WITH RECURSIVE walk(n,p) AS (VALUES (0,20),(0,30) UNION ALL SELECT n+1, p-n FROM walk WHERE n<1) SELECT p AS answer FROM walk ORDER BY 1 LIMIT 1 OFFSET 1",
        case([[ATOMS[1], integer(20)], [ATOMS[1], integer(30)]],
             [increment, arithmetic("-", ["column", 1], SELF)], guard, True, ("n", "p"),
             columns=[[["column", 1], "answer"]], orderBy=[[1, False, True]], limit=[integer(1), integer(1), False]),
    )
    yield (
        "WITH RECURSIVE walk(n) AS (VALUES (0),(NULL) UNION ALL SELECT n+1 FROM walk WHERE n<1) SELECT DISTINCT n AS answer FROM walk WHERE n IS NOT NULL ORDER BY 1 DESC",
        case([[ATOMS[1]], [NULL]], [increment], guard, True, distinct=True,
             where=compare("IS NOT", SELF, NULL), orderBy=[[1, True, False]]),
    )


def negatives():
    yield case([[ATOMS[1], ATOMS[0]]], [SELF], None, False, ("n", "p")), "must match the anchor width"
    yield case([[ATOMS[1]]], [["identifier", "n"]], NULL, False), "needs checked output columns"
    yield case([[ATOMS[1]]], [["column", 1]], NULL, True), "visible occurrence"
    yield case([[ATOMS[1]]], [SELF], ["column", 0, 1], True), "visible occurrence"
    yield case([[ATOMS[1], ATOMS[0]]], [SELF, SELF], NULL, True, ("n", "N")), "cannot repeat a column name"
    yield case([[ATOMS[1], ATOMS[0]]], [SELF, SELF], NULL, True), "must match the VALUES width"
    yield {
        "sql": "WITH RECURSIVE walk(n) AS (VALUES (0) UNION ALL SELECT n FROM main.walk) SELECT n FROM walk",
        "declarations": [DECLARATION],
    }, "recursive input must resolve to this local definition"
    yield {
        "sql": "WITH RECURSIVE walk(n) AS (VALUES (0) EXCEPT SELECT n FROM main.walk) SELECT n FROM walk",
        "declarations": [DECLARATION],
    }, "requires UNION or UNION ALL"
    yield {
        "sql": "WITH RECURSIVE walk(n) AS (VALUES (0) UNION SELECT n FROM walk LIMIT 4) SELECT n FROM walk",
        "declarations": [DECLARATION],
    }, "without queue ordering or bounds"


def sample_forms(forms, response, rows):
    source = response["local"]
    forms[f"height/{len(source['rows'])}"] += 1
    forms[f"width/{len(source['names'])}"] += 1
    forms[f"unionAll/{source['unionAll']}"] += 1
    forms[f"guard/{source['where'] is not None}"] += 1
    forms[f"order/{bool(response['orderBy'])}"] += 1
    forms[f"duplicates/{len({tuple(map(tuple, row)) for row in rows}) != len(rows)}"] += 1
    for root in source["step"]:
        for node in children(root):
            forms[f"operand/{node[0]}"] += 1


def observer_controls(db, specification):
    sql = "WITH RECURSIVE control(n) AS (VALUES (1) UNION ALL SELECT n+1 FROM control) SELECT n FROM control"
    expected_action = specification["observer"]["additionalAuthorizationAction"]
    ordinary = Database(db.library)
    try:
        try:
            ordinary.observe(sql + " LIMIT 1", {})
        except ObserverAuthorizationError as error:
            require(error.action == expected_action, "recursive-observer-control-action", error.action)
        else:
            raise Failure("recursive-policy-leaked", sql)
    finally:
        ordinary.close(ordinary.db)

    try:
        db.observe(sql, {})
    except Failure as failure:
        require(failure.signal == "recursive-observer-budget", "recursive-observer-control-signal", failure.signal)
    else:
        raise Failure("recursive-observer-budget-not-enforced", sql)
    require(db.observe("SELECT 1", {})["rows"] == [[["integer", "1"]]], "recursive-observer-post-interrupt")
    return {"sql": sql, "ordinaryAction": expected_action, "recursiveSignal": "recursive-observer-budget", "recovered": True}


def execute(args, specification, db, worker_factory=Worker):
    controls = observer_controls(db, specification)
    settings = specification["exploration"]
    items = [(f"finite/{index}", item) for index, item in enumerate(finite_cases())]
    items.extend((f"seed/{seed}/{index}", item) for seed in settings["seeds"]
                 for index, item in enumerate(seeded(seed, settings["casesPerSeed"])))
    observed, forms = Counter(), Counter()
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
                require(isinstance(replies, list) and len(replies) == len(batch), "recursive-source-batch-incomplete", replies)
                for (key, current), response in zip(batch, replies):
                    rows = probe(current, response, db, ledger, key)
                    if key.startswith("finite/"):
                        observed[finite_id(response)] += 1
                    else:
                        sample_forms(forms, response, rows)
            require(len(observed) == specification["finite"]["identities"] and set(observed) == finite_ids()
                    and set(observed.values()) == {1}, "recursive-source-finite-incomplete", list(observed.items()))
            required = {f"{field}/{value}" for field in ("unionAll", "guard", "order", "duplicates") for value in (False, True)}
            required |= {f"{field}/{number}" for field in ("height", "width") for number in range(1, 6)}
            required |= {f"operand/{role}" for role in ("arithmetic", "column", "integer", "null", "parameter")}
            require(required <= forms.keys(), "recursive-source-forms-missing", sorted(required - forms.keys()))
            analyzed_cases = list(analyzed())
            require(len(analyzed_cases) == specification["analyzedInputs"], "recursive-source-analyzed-count", len(analyzed_cases))
            for index, (sql, item) in enumerate(analyzed_cases):
                current = {"sql": sql, "declarations": [DECLARATION], "bindings": item["bindings"]}
                key = f"analysis/{index}"
                ledger.record(key, "attempted", input=current, expected=item)
                probe(item, worker.request(current), db, ledger, key, original=sql)
            for index, (current, message) in enumerate(negatives()):
                key = f"negative/{index}"
                ledger.record(key, "attempted", input=current)
                response = worker.request(current)
                require(response["status"] == "rejected" and message in response["message"],
                        "recursive-source-negative-boundary", response)
                ledger.record(key, "rejected", response=response)
            counts = complete_observations(ledger, settings)
        except Exception as failure:
            write_json(args.out / "counterexample.json", {"original": current, "failure": str(failure), "shrunk": None})
            raise
        return {"counts": counts, "finiteIds": sorted(observed), "forms": dict(forms), "php": worker.environment, "observerControls": controls}


def check_faults(args, db, runner=check_render_faults):
    path = "sql-semantics-sqlite/src/Ir/SelectSql.php"
    fixtures = [
        RenderFault("toggle-recursive-union", path, "$definition->unionAll ? ' UNION ALL SELECT ' : ' UNION SELECT '",
                    "$definition->unionAll ? ' UNION SELECT ' : ' UNION ALL SELECT '",
                    case([[ATOMS[1]], [ATOMS[1]]], [SELF], NULL, True), "row-bound-mismatch"),
        RenderFault("wrong-recursive-slot", path, "$this->expression($column->expression, $source);",
                    "$this->expression(new ColumnReference(0), $source);",
                    case([[ATOMS[1], ATOMS[0]]], [SELF, ["column", 1]], compare("<", ["column", 1], ATOMS[1]),
                         False, ("n", "p"), columns=[[["column", 1], "answer"]]), "row-bound-mismatch"),
        RenderFault("drop-recursive-guard", path, "$query .= ' WHERE ' . $this->expression($definition->where, $source);",
                    "$query .= '';", case([[ATOMS[1]]], [ATOMS[0]], NULL, False), "row-bound-mismatch"),
    ]
    return runner(args, db, fixtures, probe)


if __name__ == "__main__":
    run_domain("recursive source", CONTRACT_NAME, CONTRACT, FIXTURE, execute, check_faults, RecursiveDatabase)
