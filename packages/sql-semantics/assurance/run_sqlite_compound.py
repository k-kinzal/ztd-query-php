"""Evaluate complete, left-associated SQLite compound queries."""

import functools
import itertools
import random
import time
from collections import Counter

from run import Ledger, Worker, compare, require, write_json
from run_sqlite_scalars import evaluate, PROFILE
from run_sqlite_values import names
from sqlite_domain import RenderFault, check_render_faults, complete_observations, run_domain
from sqlite_engine import typed
from sqlite_relational_cases import order_value

CONTRACT = "c814b6f5abcd51569cd56a6ce03743c595d513964a6191fef253c488176dc24f"
DDL = "CREATE TABLE ir_compound (word TEXT COLLATE NOCASE)"
FIXTURE = DDL + "; INSERT INTO ir_compound VALUES ('Alpha'),('beta'),('Alpha');"


def arm(form, rows, labels=None):
    if form == "values":
        return {"operation": form, "rows": rows}
    assert form == "select" and len(rows) == 1
    labels = [f"item{index}" for index in range(len(rows[0]))] if labels is None else labels
    return {"operation": form, "source": None, "joins": [], "where": None, "orderBy": [],
            "limit": None, "distinct": False, "columns": [[value, label] for value, label in zip(rows[0], labels)]}


def singleton(form, value):
    return arm(form, [[value]] if form == "select" else [[value], [value]])


def query(first, steps, order=(), limit=None, bindings=None):
    return {"operation": "compound", "first": first, "steps": list(steps),
            "orderBy": list(order), "limit": limit, "bindings": {} if bindings is None else bindings}


def finite_cases(domain):
    forms, operators, atoms = domain["armForms"], domain["operators"], domain["atoms"]
    for first, second, operator, left, right in itertools.product(forms, forms, operators, atoms, atoms):
        yield query(singleton(first, left), [[operator, singleton(second, right)]])
    for first, second, left, middle, right in itertools.product(operators, operators, atoms[:3], atoms[:3], atoms[:3]):
        yield query(singleton("select", left), [[first, singleton("select", middle)], [second, singleton("select", right)]])


def finite_ids(domain):
    forms, operators, atoms = domain["armForms"], domain["operators"], domain["atoms"]
    return {(2, item) for item in range(len(forms) ** 2 * len(operators) * len(atoms) ** 2)} | {
        (3, item) for item in range(len(operators) ** 2 * 3 ** 3)
    }


def identity(response, domain):
    forms, operators, atoms = domain["armForms"], domain["operators"], domain["atoms"]

    def atom(member):
        return atoms.index(member["columns"][0][0] if member["operation"] == "select" else member["rows"][0][0])

    first = response["first"]
    operator, second = response["steps"][0]
    if len(response["steps"]) == 1:
        prefix = forms.index(first["operation"]) * len(forms) + forms.index(second["operation"])
        offset = prefix * len(operators) + operators.index(operator)
        return (2, (offset * len(atoms) + atom(first)) * len(atoms) + atom(second))
    last_operator, last = response["steps"][1]
    offset = operators.index(operator) * len(operators) + operators.index(last_operator)
    return (3, ((offset * 3 + atom(first)) * 3 + atom(second)) * 3 + atom(last))


def seeded(domain, settings, seed):
    rng = random.Random(seed)

    for _ in range(settings["casesPerSeed"]):
        width = rng.randint(1, settings["width"])
        count = rng.randint(2, settings["arms"])
        members = []
        for _ in range(count):
            form = rng.choice(domain["armForms"])
            height = 1 if form == "select" else rng.randint(1, settings["valuesRows"])
            rows = [[rng.choice(domain["atoms"]) for _ in range(width)] for _ in range(height)]
            labels = rng.choices(["a", "雪", 'b"c', "dup"], k=width)
            members.append(arm(form, rows, labels))

        steps = [[rng.choice(domain["operators"]), member] for member in members[1:]]
        order, limit = [], None
        if members[-1]["operation"] == "select" and rng.randrange(3):
            positions = rng.sample(range(1, width + 1), width)
            order = [[position, bool(rng.getrandbits(1)), bool(rng.getrandbits(1))] for position in positions]
            if rng.getrandbits(1):
                limit = [["integer", str(rng.randint(0, 7))], ["integer", str(rng.randint(0, 3))], bool(rng.getrandbits(1))]
        yield query(members[0], steps, order, limit)


def expected(request, read_select=None):
    first, steps = request["first"], request["steps"]
    bindings = request["bindings"]

    def terms(member):
        return member["rows"] if member["operation"] == "values" else [[value for value, _ in member["columns"]]]

    def arm_rows(member):
        if read_select is not None and member["operation"] == "select":
            return [tuple(row) for row in read_select(member)]
        return [tuple(evaluate(value, bindings) for value in row) for row in terms(member)]

    def representatives(rows):
        return {tuple(order_value(value) for value in row): row for row in rows}

    data = arm_rows(first)
    for operator, member in steps:
        right = arm_rows(member)
        if operator == "UNION ALL":
            data.extend(right)
            continue
        a, b = representatives(data), representatives(right)
        if operator == "UNION":
            data = list((a | b).values())
        elif operator == "INTERSECT":
            data = [a[key] for key in a if key in b]
        else:
            assert operator == "EXCEPT"
            data = [a[key] for key in a if key not in b]

    order = request["orderBy"]

    def compare_rows(left, right):
        for position, descending, nulls_first in order:
            a, b = left[position - 1], right[position - 1]
            if a is None or b is None:
                if a is b:
                    continue
                return -1 if (a is None) == nulls_first else 1
            a, b = order_value(a), order_value(b)
            if a != b:
                return -1 if (a < b) != descending else 1
        return 0

    data = sorted(data, key=functools.cmp_to_key(compare_rows)) if order else data
    labels = ([label for _, label in first["columns"]] if first["operation"] == "select" else
              [f"column{index + 1}" for index in range(len(first["rows"][0]))])
    roots = [row for member in [first, *(member for _, member in steps)] for row in terms(member)]
    if request["limit"] is not None:
        count, offset, offset_first = request["limit"]
        expressions = [offset, count] if offset_first else [count, offset]
        roots.append([expression for expression in expressions if expression is not None])
        skip = max(evaluate(offset, bindings), 0) if offset is not None else 0
        count = evaluate(count, bindings)
        data = data[skip:] if count < 0 else data[skip:skip + count]
    parameters = names(roots)
    rows = [[typed(value) for value in row] for row in data]
    return {"names": labels, "parameters": parameters, "parameterCount": max(map(int, parameters), default=0),
            "rows": rows if order else sorted(rows), "readOnly": True, "changes": 0}


def probe(request, response, db, ledger=None, key=None, original=None):
    require(response["status"] == "constructed" and response.get("operation") == "compound"
            and response["profile"] == PROFILE, "compound-positive-rejected", response)
    if ledger is not None:
        ledger.record(key, "constructed", response=response)
    for field in ("first", "steps", "orderBy", "limit"):
        require(request[field] == response[field], "compound-shape-mismatch", {"field": field, "response": response})
    order = bool(request["orderBy"])
    observations = {"observation": db.observe(response["sql"], request["bindings"], preserve_order=order)}
    if original is not None:
        observations["original"] = db.observe(original, request["bindings"], preserve_order=order)
    if ledger is not None:
        ledger.record(key, "observed", **observations)
    for observation in observations.values():
        compare(expected(request), observation)
    if ledger is not None:
        ledger.record(key, "compared")


def analyzed():
    integer = lambda value: ["integer", str(value)]
    yield ("SELECT X'31' AS a UNION ALL SELECT '1' AS b UNION ALL SELECT 1 AS c",
           query(arm("select", [[["blob", "31"]]], ["a"]),
                 [["UNION ALL", arm("select", [[["text", "1"]]], ["b"])],
                  ["UNION ALL", arm("select", [[integer(1)]], ["c"])]]))
    yield ("SELECT 1 AS a UNION SELECT 2 AS b EXCEPT SELECT 1 AS c ORDER BY 1 DESC LIMIT 1",
           query(arm("select", [[integer(1)]], ["a"]),
                 [["UNION", arm("select", [[integer(2)]], ["b"])], ["EXCEPT", arm("select", [[integer(1)]], ["c"])]],
                 [[1, True, False]], [integer(1), None, False]))
    yield ("VALUES (1), (1) UNION ALL SELECT 1 AS b",
           query(arm("values", [[integer(1)], [integer(1)]]), [["UNION ALL", arm("select", [[integer(1)]], ["b"])]]))
    yield ("SELECT ?02 AS a UNION ALL SELECT ?2 AS b ORDER BY 1 LIMIT ?5 OFFSET ?002",
           query(arm("select", [[["parameter", "02"]]], ["a"]),
                 [["UNION ALL", arm("select", [[["parameter", "2"]]], ["b"])]],
                 [[1, False, True]], [["parameter", "5"], ["parameter", "002"], False], {"2": 1, "5": 2}))


def equivalent_inputs():
    yield "SELECT 1 AS n UNION SELECT 1.0 AS m", []
    yield "SELECT word AS item FROM ir_compound UNION SELECT 'alpha' AS other", [DDL]


def compare_equivalent(key, current, response, db, ledger):
    require(response["status"] == "constructed" and response.get("operation") == "compound"
            and response["profile"] == PROFILE, "compound-analysis-rejected", response)
    ledger.record(key, "constructed", response=response)
    original = db.observe(current["sql"], {})
    rendered = db.observe(response["sql"], {})
    ledger.record(key, "observed", original=original, observation=rendered)
    compare(original, rendered)
    ledger.record(key, "compared")


def negatives():
    base = singleton("select", ["integer", "1"])
    values = singleton("values", ["integer", "2"])
    order = [[1, False, True]]
    limit = [["integer", "1"], None, False]
    yield query(base, []), "requires a second arm"
    yield query(base, [["UNION", arm("values", [[["integer", "2"], ["null"]]])]]), "same width"
    yield query(base | {"orderBy": order}, [["UNION", base]]), "ORDER BY and LIMIT belong to the outer"
    yield query(base, [["UNION", base | {"limit": limit}]]), "ORDER BY and LIMIT belong to the outer"
    local = base | {"local": {"names": ["v"], "rows": [[["integer", "1"]]]}}
    yield query(local, [["UNION", base]]), "cannot contain WITH"
    yield query(base, [["UNION", values]], order), "requires a final SELECT"
    yield query(base, [["UNION", values]], limit=limit), "requires a final SELECT"
    yield query(base, [["UNION", base]], [[2, True, False]]), "order position must belong"
    yield query(base, [["UNION ALL", base]] * 500), "at most 500 arms"


def execute(args, specification, db, worker_factory=Worker):
    domain, settings = specification["finite"], specification["exploration"]
    items = [(f"finite/{index}", request) for index, request in enumerate(finite_cases(domain))]
    items.extend((f"seed/{seed}/{index}", request) for seed in settings["seeds"]
                 for index, request in enumerate(seeded(domain, settings, seed)))
    visited, structures = Counter(), Counter()
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
                require(isinstance(replies, list) and len(replies) == len(batch), "compound-batch-incomplete", replies)
                for (key, current), response in zip(batch, replies):
                    probe(current, response, db, ledger, key)
                    if key.startswith("finite/"):
                        visited[identity(response, domain)] += 1
                    structures.update(member["operation"] for member in [response["first"], *[row[1] for row in response["steps"]]])
                    structures.update(operator for operator, _ in response["steps"])
            require(set(visited) == finite_ids(domain) and set(visited.values()) == {1}, "compound-finite-incomplete", len(visited))
            require(set(structures) == {*domain["operators"], *domain["armForms"]}, "compound-unobserved-arm", dict(structures))
            base = singleton("select", ["integer", "1"])
            current = query(base, [["UNION ALL", base]] * 499)
            ledger.record("boundary/500", "attempted", input=current)
            probe(current, worker.request(current), db, ledger, "boundary/500")
            for index, (sql, expected_query) in enumerate(analyzed()):
                current = {"sql": sql, "declarations": [], "bindings": expected_query["bindings"]}
                key = f"analysis/{index}"
                ledger.record(key, "attempted", input=current, expected=expected_query)
                probe(expected_query, worker.request(current), db, ledger, key, sql)
            for index, (sql, declarations) in enumerate(equivalent_inputs()):
                current = {"sql": sql, "declarations": declarations, "bindings": {}}
                key = f"equivalence/{index}"
                ledger.record(key, "attempted", input=current)
                compare_equivalent(key, current, worker.request(current), db, ledger)
            for index, (current, message) in enumerate(negatives()):
                key = f"negative/{index}"
                ledger.record(key, "attempted", input=current)
                outcome = worker.request(current)
                require(outcome["status"] == "rejected" and message in outcome["message"], "compound-negative-boundary-mismatch", outcome)
                ledger.record(key, "rejected", response=outcome)
            counts = complete_observations(ledger, settings)
        except Exception as failure:
            write_json(args.out / "counterexample.json", {"original": current, "failure": str(failure), "shrunk": None})
            raise
        return {"counts": counts, "finiteIds": sorted(visited), "structures": dict(structures), "php": worker.environment}


def check_faults(args, db, runner=check_render_faults):
    left, right = singleton("select", ["integer", "1"]), singleton("select", ["integer", "2"])
    path = "sql-semantics-sqlite/src/Ir/Rows/Compound.php"
    fixtures = [
        RenderFault("union-all-to-union", path, "$step->operator->value", "str_replace(' ALL', '', $step->operator->value)",
                    query(left, [["UNION ALL", left]]), "meaning-mismatch"),
        RenderFault("reverse-compound-operands", path, "$sql .= ' ' . $step->operator->value . ' ' . $step->query->toString();",
                    "$sql = $step->query->toString() . ' ' . $step->operator->value . ' ' . $sql;",
                    query(left, [["EXCEPT", right]]), "meaning-mismatch"),
        RenderFault("drop-outer-order", path, "$renderer->ordering($this->orderBy)", "''",
                    query(left, [["UNION ALL", right]], [[1, True, False]]), "meaning-mismatch"),
    ]
    return runner(args, db, fixtures, probe)


if __name__ == "__main__":
    run_domain("compound", "sqlite-compound-v1.json", CONTRACT, FIXTURE, execute, check_faults)
