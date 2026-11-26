"""Compare the closed SQLite scalar operators and literal storage classes."""

import itertools
import math
import random
import time
from collections import Counter

from cases import MAX, MIN, numeric
from run import Ledger, Worker, compare, require, write_json
from run_sqlite_values import names
from sqlite_cast_semantics import comparison_values, convert, text_value
from sqlite_domain import RenderFault, check_render_faults, complete_observations, run_domain
from sqlite_engine import typed
from sqlite_numeric_semantics import NUMERIC_OPERATORS, evaluate_numeric
from sqlite_pattern_semantics import NO_ESCAPE, evaluate_pattern
from sqlite_relational_cases import case as select_case, children, order_value, truth

CONTRACT_NAME = "sqlite-scalars-v2.json"
CONTRACT = "79cf2debd501cb0f486a9d84876fd118d84fd580b60a022eeaba556d86cdf48e"
DDL = "CREATE TABLE ir_scalars (id BLOB, value BLOB)"
DATA = [(1, "keep"), (1, "keep"), (2, b"\xff"), (2, 1.5), (3, None)]
FIXTURE = DDL + "; INSERT INTO ir_scalars VALUES (1,'keep'),(1,'keep'),(2,X'FF'),(2,1.5),(3,NULL);"
PROFILE = "sqlite-3.47.2/utf8/short-column-names/numbered-parameters"


def evaluate(term, bindings, row=(), case_equal=None, comparison_inputs=comparison_values):
    kind, *arguments = term
    if kind == "integer":
        return int(arguments[0])
    if kind == "null":
        return None
    if kind == "parameter":
        return bindings.get(str(int(arguments[0])))
    if kind == "text":
        return arguments[0]
    if kind == "blob":
        return bytes.fromhex(arguments[0])
    if kind == "hex":
        digits, negate = arguments
        value = int(digits, 16)
        signed = value if value <= MAX else value - 2 ** 64
        return -signed if negate else signed
    if kind == "real":
        whole, fraction, exponent, negative = arguments
        text = ("-" if negative else "") + whole
        if fraction is not None:
            text += "." + fraction
        if exponent is not None:
            text += "e" + exponent
        return float(text)
    if kind == "column":
        return row[arguments[0]]

    if kind == "unary":
        operator, operand = arguments
        value = evaluate(operand, bindings, row, case_equal, comparison_inputs)
        if operator == "+" or value is None:
            return value
        if operator == "NOT":
            return int(not truth(value))
        if operator == "-":
            literal = operand
            while literal[:2] == ["unary", "+"]:
                literal = literal[2]
            if literal[0] == "real" and not literal[4]:
                if literal[2:4] == [None, None] and literal[1].lstrip("0") == str(-MIN):
                    return MIN
                return -value
            result = 0 - numeric(value)
            return float(result) if type(result) is int and not MIN <= result <= MAX else result
        assert operator == "~"
        return ~convert("INTEGER", value)
    if kind == "truth":
        desired, negated, operand = arguments
        decision = truth(evaluate(operand, bindings, row, case_equal, comparison_inputs))
        return int((decision is desired) != negated)
    if kind == "cast":
        return convert(arguments[0], evaluate(arguments[1], bindings, row, case_equal, comparison_inputs))
    if kind in ("coalesce", "if-null"):
        for operand in arguments[0]:
            result = evaluate(operand, bindings, row, case_equal, comparison_inputs)
            if result is not None:
                return result
        return None
    if kind == "case":
        base, branches, fallback = arguments
        value = None if base is None else evaluate(base, bindings, row, case_equal, comparison_inputs)
        result = fallback
        if base is None or value is not None:
            for when, then in branches:
                decision = evaluate(when, bindings, row, case_equal, comparison_inputs)
                if base is None:
                    matches = truth(decision) is True
                elif case_equal is None:
                    a, b = comparison_values(base, when, value, decision)
                    matches = order_value(a) == order_value(b)
                else:
                    matches = case_equal(base, when, value, decision)
                if matches:
                    result = then
                    break
        return None if result is None else evaluate(result, bindings, row, case_equal, comparison_inputs)

    if kind == "concat":
        left = evaluate(arguments[0], bindings, row, case_equal, comparison_inputs)
        right = evaluate(arguments[1], bindings, row, case_equal, comparison_inputs)
        if left is None or right is None:
            return None
        combined = text_value(left).encode(errors="surrogateescape") + text_value(right).encode(errors="surrogateescape")
        return combined.decode(errors="surrogateescape")
    if kind == "pattern":
        operation, negated, subject, pattern, escape = arguments
        value = evaluate(subject, bindings, row, case_equal, comparison_inputs)
        rule = evaluate(pattern, bindings, row, case_equal, comparison_inputs)
        delimiter = NO_ESCAPE if escape is None else evaluate(escape, bindings, row, case_equal, comparison_inputs)
        return evaluate_pattern(operation, value, rule, negated, delimiter)
    if kind == "collate":
        return evaluate(arguments[1], bindings, row, case_equal, comparison_inputs)
    if kind == "folded-membership":
        return int(arguments[0])
    if kind == "membership":
        negated, operand, items = arguments
        if not items:
            return int(negated)
        value = evaluate(operand, bindings, row, case_equal, comparison_inputs)
        if value is None:
            return None
        undecided = False
        for member in items:
            candidate = evaluate(member, bindings, row, case_equal, comparison_inputs)
            if candidate is None:
                undecided = True
                continue
            left, right = comparison_inputs(operand, ("membership-item", member, len(items) == 1), value, candidate)
            if order_value(left) == order_value(right):
                return int(not negated)
        return None if undecided else int(negated)

    if kind == "between":
        negated, operand, lower, upper = arguments
        value = evaluate(operand, bindings, row, case_equal, comparison_inputs)

        def comparison(bound, relation):
            other = evaluate(bound, bindings, row, case_equal, comparison_inputs)
            if value is None or other is None:
                return None
            left, right = comparison_inputs(operand, bound, value, other)
            return relation(order_value(left), order_value(right))

        below = comparison(lower, lambda left, right: left >= right)
        above = comparison(upper, lambda left, right: left <= right)
        truth_value = False if False in (below, above) else None if None in (below, above) else True
        return None if truth_value is None else int(truth_value != negated)

    operator, left, right = arguments
    a = evaluate(left, bindings, row, case_equal, comparison_inputs)
    b = evaluate(right, bindings, row, case_equal, comparison_inputs)
    if kind == "logical":
        p, q = truth(a), truth(b)
        if operator == "AND":
            return 0 if False in (p, q) else None if None in (p, q) else 1
        assert operator == "OR"
        return 1 if True in (p, q) else None if None in (p, q) else 0
    if kind == "comparison":
        if operator not in ("IS", "IS NOT") and (a is None or b is None):
            return None
        a, b = comparison_inputs(left, right, a, b)
        p, q = order_value(a), order_value(b)
        return int({"=": p == q, "IS": p == q, "IS NOT": p != q, "<>": p != q,
                    "<": p < q, "<=": p <= q, ">": p > q, ">=": p >= q}[operator])
    assert kind == "arithmetic"
    if operator in NUMERIC_OPERATORS:
        return evaluate_numeric(operator, a, b)
    if a is None or b is None:
        return None
    p, q = numeric(a), numeric(b)
    operations = {"+": lambda x, y: x + y, "-": lambda x, y: x - y, "*": lambda x, y: x * y}
    operation = operations[operator]
    value = operation(p, q)
    if type(value) is int and not MIN <= value <= MAX:
        value = operation(float(p), float(q))
    return None if type(value) is float and math.isnan(value) else value


def query(target, rows, labels=None):
    bindings = {slot: 3 for slot in names(rows)}
    if target == "values":
        return {"operation": "values", "rows": rows, "bindings": bindings}
    assert target == "select" and len(rows) == 1
    labels = [f"item{index}" for index in range(len(rows[0]))] if labels is None else labels
    return select_case(source=None, columns=list(map(list, zip(rows[0], labels))), bindings=bindings)


def finite_cases(domain):
    for target in domain["targets"]:
        for term in domain["atoms"]:
            yield query(target, [[term]])
        for operator, left, right in itertools.product(domain["operators"], domain["atoms"], domain["atoms"]):
            yield query(target, [[[*operator, left, right]]])


def finite_ids(domain):
    width = len(domain["atoms"])
    size = width + len(domain["operators"]) * width ** 2
    return {(target, index) for target in domain["targets"] for index in range(size)}


def identity(response, domain):
    target = response.get("operation", "select")
    term = response["rows"][0][0] if target == "values" else response["columns"][0][0]
    atoms = domain["atoms"]
    if len(term) <= 3 or term[0] == "real":
        return target, atoms.index(term)
    kind, operator, left, right = term
    offset = domain["operators"].index([kind, operator])
    width = len(atoms)
    return target, width + offset * width ** 2 + atoms.index(left) * width + atoms.index(right)


def seeded(domain, settings, seed):
    rng = random.Random(seed)
    operators = domain["operators"] + [["arithmetic", "-"], ["arithmetic", "*"], ["comparison", "<"], ["comparison", "IS NOT"]]

    def operand(height):
        if height and rng.randrange(3):
            return [*rng.choice(operators), operand(height - 1), operand(height - 1)]
        if rng.getrandbits(1):
            return rng.choice(domain["atoms"])
        return ["real", str(rng.randrange(8)), rng.choice(("0", "125", "5", "75")), None, bool(rng.getrandbits(1))]

    for _ in range(settings["casesPerSeed"]):
        target = rng.choice(domain["targets"])
        width = rng.randint(1, settings["width"])
        height = 1 if target == "select" else rng.randint(1, 3)
        yield query(target, [[operand(settings["height"]) for _ in range(width)] for _ in range(height)])


def expected(request):
    if request.get("operation") == "values":
        rows = request["rows"]
        labels = [f"column{index}" for index in range(1, len(rows[0]) + 1)]
    else:
        fields = request["columns"]
        rows, labels = [[expr for expr, _ in fields]], [label for _, label in fields]
    parameters = names(rows)
    bindings = request["bindings"]
    return {
        "names": labels, "parameters": parameters, "parameterCount": max(map(int, parameters), default=0),
        "rows": sorted([typed(evaluate(expr, bindings)) for expr in row] for row in rows),
        "readOnly": True, "changes": 0,
    }


def probe(request, response, db, ledger=None, key=None, original=None):
    require(response["status"] == "constructed" and response["profile"] == PROFILE, "scalar-positive-rejected", response)
    if ledger is not None:
        ledger.record(key, "constructed", response=response)
    if request.get("operation") == "values":
        require(response.get("operation") == "values" and request["rows"] == response["rows"], "scalar-shape-mismatch", response)
    else:
        require(response.get("operation") is None and response["source"] is None and response["columns"] == request["columns"],
                "scalar-shape-mismatch", response)
    observations = {"observation": db.observe(response["sql"], request["bindings"])}
    if original is not None:
        observations["original"] = db.observe(original, request["bindings"])
    if ledger is not None:
        ledger.record(key, "observed", **observations)
    for observation in observations.values():
        compare(expected(request), observation)
    if ledger is not None:
        ledger.record(key, "compared")


def analyzed():
    yield ("SELECT 'it''s' AS quote, X'00ff' AS bytes, .125e1 AS ratio, -0xFFFFFFFFFFFFFFFF AS n",
           query("select", [[["text", "it's"], ["blob", "00FF"], ["real", "", "125", "1", False], ["hex", "FFFFFFFFFFFFFFFF", True]]],
                 ["quote", "bytes", "ratio", "n"]))
    yield ("SELECT -0.0 AS zero, -9223372036854775808 AS smallest, -9223372036854775809 AS overflow, 1e999 AS infinity",
           query("select", [[["real", "0", "0", None, True], ["integer", "-9223372036854775808"],
                             ["real", "9223372036854775809", None, None, True], ["real", "1", None, "999", False]]],
                 ["zero", "smallest", "overflow", "infinity"]))
    yield ("SELECT ((1.5 > 1) + (X'00' IS X'01')) AS n, ('a' = 'b') OR (NULL IS NULL) AS yes",
           query("select", [[["arithmetic", "+", ["comparison", ">", ["real", "1", "5", None, False], ["integer", "1"]],
                              ["comparison", "IS", ["blob", "00"], ["blob", "01"]]],
                             ["logical", "OR", ["comparison", "=", ["text", "a"], ["text", "b"]],
                              ["comparison", "IS", ["null"], ["null"]]]]], ["n", "yes"]))
    yield ("VALUES (X'', ''), (X'3031', ?02 + 1.5)",
           query("values", [[["blob", ""], ["text", ""]],
                            [["blob", "3031"], ["arithmetic", "+", ["parameter", "02"], ["real", "1", "5", None, False]]]]))


def negatives():
    yield ["text-bytes", "FF"], "TEXT literal requires valid UTF-8"
    yield ["blob", "A"], "pairs of upper-case hexadecimal"
    yield ["blob", "X7"], "pairs of upper-case hexadecimal"
    yield ["real", "", "", None, False], "at least one significand digit"
    yield ["real", "10", None, None, False], "exceed its signed INTEGER boundary"
    yield ["real", "1", "5", "e1", False], "optionally signed decimal digits"
    yield ["hex", "10000000000000000", False], "sixteen significant upper-case digits"
    yield ["hex", "8000000000000000", True], "cannot be negated"


def mutations():
    target = ["main", "ir_scalars", ["id", "value"]]
    yield ("INSERT INTO ir_scalars (id,value) VALUES (0.5,'in''sert')",
           {"operation": "insert", "target": target, "conflict": "inherit",
            "rows": [[["real", "0", "5", None, False], ["text", "in'sert"]]], "bindings": {}})
    yield ("UPDATE ir_scalars SET value = -0xFFFFFFFFFFFFFFFF WHERE id = 2 AND value IS X'FF'",
           {"operation": "update", "target": target, "conflict": "inherit",
            "assignments": [[1, ["hex", "FFFFFFFFFFFFFFFF", True]]],
            "where": ["logical", "AND", ["comparison", "=", ["column", 0], ["integer", "2"]],
                      ["comparison", "IS", ["column", 1], ["blob", "FF"]]], "bindings": {}})
    yield ("DELETE FROM ir_scalars WHERE value = 'keep' OR id = 0.5",
           {"operation": "delete", "target": target,
            "where": ["logical", "OR", ["comparison", "=", ["column", 1], ["text", "keep"]],
                      ["comparison", "=", ["column", 0], ["real", "0", "5", None, False]]], "bindings": {}})


def expected_mutation(request):
    operation = request["operation"]
    bindings = request["bindings"]
    if operation == "insert":
        additional = [tuple(evaluate(term, bindings) for term in row) for row in request["rows"]]
        rows, affected = DATA + additional, len(additional)
    else:
        rows = []
        affected = 0
        for row in DATA:
            if truth(evaluate(request["where"], bindings, row)) is not True:
                rows.append(row)
                continue
            affected += 1
            if operation == "update":
                values = list(row)
                for slot, operand in request["assignments"]:
                    values[slot] = evaluate(operand, bindings, row)
                rows.append(values)
            else:
                assert operation == "delete"
    return {
        "statement": {"names": [], "parameters": {}, "parameterCount": 0, "rows": [], "readOnly": False, "changes": affected},
        "rows": sorted([typed(value) for value in row] for row in rows),
    }


def measure_mutation(db, sql, bindings):
    db.execute("SAVEPOINT scalar_probe")
    try:
        statement = db.observe(sql, bindings)
        state = db.observe("SELECT id,value FROM main.ir_scalars", {})["rows"]
        return {"statement": statement, "rows": state}
    finally:
        db.execute("ROLLBACK TO scalar_probe; RELEASE scalar_probe")


def mutate(key, input_case, reference, ledger, worker, db, original=None):
    ledger.record(key, "attempted", input=input_case, expected=reference)
    response = worker.request(input_case)
    require(response["status"] == "constructed" and response["profile"] == PROFILE,
            "scalar-mutation-construction-failed", response)
    ledger.record(key, "constructed", response=response)
    require(response.get("operation") == reference["operation"] and response["target"][:2] == reference["target"][:2],
            "scalar-mutation-target-mismatch", response)
    if original is None:
        keys = ("target", *[name for name in ("rows", "assignments", "where", "conflict") if name in reference])
        require(all(response[name] == reference[name] for name in keys), "scalar-mutation-shape-mismatch", response)
    observations = {"observation": measure_mutation(db, response["sql"], reference["bindings"])}
    if original is not None:
        observations["original"] = measure_mutation(db, original, reference["bindings"])
    ledger.record(key, "observed", **observations)
    for observation in observations.values():
        compare(expected_mutation(reference), observation)
    ledger.record(key, "compared")


def execute(args, specification, db, worker_factory=Worker):
    settings = specification["exploration"]
    domain = specification["finite"]
    finite = [(f"finite/{i}", request) for i, request in enumerate(finite_cases(domain))]
    seeds = [(f"seed/{seed}/{i}", request) for seed in settings["seeds"]
             for i, request in enumerate(seeded(domain, settings, seed))]
    cases = finite + seeds
    visited, forms = Counter(), Counter()
    current = None
    with (args.out / "events.jsonl").open("w") as stream, worker_factory(args, time.monotonic() + settings["timeoutSeconds"]) as worker:
        ledger = Ledger(stream)
        try:
            for start in range(0, len(cases), 16):
                batch = cases[start:start + 16]
                current = {"batch": [request for _, request in batch]}
                for key, request in batch:
                    ledger.record(key, "attempted", input=request)
                replies = worker.request(current["batch"])
                require(isinstance(replies, list) and len(replies) == len(batch), "scalar-batch-incomplete", replies)
                for (key, current), response in zip(batch, replies):
                    probe(current, response, db, ledger, key)
                    if key.startswith("finite/"):
                        visited[identity(response, domain)] += 1
                    rows = response["rows"] if response.get("operation") == "values" else [[field[0] for field in response["columns"]]]
                    forms.update(node[0] for row in rows for root in row for node in children(root))
            expected_ids = finite_ids(domain)
            require(set(visited) == expected_ids and set(visited.values()) == {1}, "scalar-finite-incomplete",
                    {"missing": sorted(expected_ids - set(visited)), "extra": sorted(set(visited) - expected_ids)})
            require(set(forms) == {"integer", "null", "parameter", "text", "blob", "real", "hex", "comparison", "logical", "arithmetic"},
                    "scalar-unobserved-operands", dict(forms))
            current = query("select", [[["text", "abc\0d"], ["comparison", "<", ["text", "1\0"], ["integer", "2"]]]])
            ledger.record("boundary/nul-text", "attempted", input=current)
            probe(current, worker.request(current), db, ledger, "boundary/nul-text")
            for index, (sql, expected_query) in enumerate(analyzed()):
                current = {"sql": sql, "declarations": [], "bindings": expected_query["bindings"]}
                key = f"analysis/{index}"
                ledger.record(key, "attempted", input=current, expected=expected_query)
                probe(expected_query, worker.request(current), db, ledger, key, sql)
            for index, (sql, reference) in enumerate(mutations()):
                current = reference
                mutate(f"mutation-direct/{index}", current, reference, ledger, worker, db)
                current = {"sql": sql, "declarations": [DDL], "bindings": reference["bindings"]}
                mutate(f"mutation-analysis/{index}", current, reference, ledger, worker, db, sql)
            for index, (term, message) in enumerate(negatives()):
                current = query("select", [[term]])
                key = f"negative/{index}"
                ledger.record(key, "attempted", input=current)
                response = worker.request(current)
                require(response["status"] == "rejected" and message in response["message"], "scalar-negative-boundary-mismatch", response)
                ledger.record(key, "rejected", response=response)
            counts = complete_observations(ledger, settings)
        except Exception as failure:
            write_json(args.out / "counterexample.json", {"original": current, "failure": str(failure), "shrunk": None})
            raise
        return {"counts": counts, "finiteIds": sorted(visited), "operands": dict(forms), "php": worker.environment}


def check_faults(args, db, runner=check_render_faults):
    renderer = "sql-semantics-sqlite/src/Ir/SelectSql.php"
    real = "sql-semantics-sqlite/src/Ir/Literal/RealLiteral.php"
    hex_integer = "sql-semantics-sqlite/src/Ir/Literal/HexIntegerLiteral.php"
    text = "sql-semantics-sqlite/src/Ir/Literal/TextLiteral.php"
    grouped = ("$expression instanceof HexIntegerLiteral, $expression instanceof RealLiteral, $expression instanceof TextLiteral"
               "\n                => $expression->toString(),")
    changed = ("$expression instanceof HexIntegerLiteral, $expression instanceof RealLiteral => $expression->toString(),"
               "\n            $expression instanceof TextLiteral => 'CAST(' . $expression->toString() . ' AS BLOB)',")
    cases = [
        ("text-to-blob", renderer, grouped, changed, ["text", "it's"]),
        ("add-nul-text-affinity", text, '"(+CAST(X\'"', '"(CAST(X\'"', ["comparison", "<", ["text", "1\0"], ["integer", "2"]]),
        ("drop-real-sign", real, "($this->negative ? '-' : '') . $this->whole", "$this->whole", ["real", "0", "0", None, True]),
        ("drop-hex-sign", hex_integer, "($this->negated ? '-' : '') . '0x'", "'0x'", ["hex", "FFFFFFFFFFFFFFFF", True]),
    ]
    return runner(args, db, [
        RenderFault(name, path, before, after, query("select", [[term]]), "meaning-mismatch")
        for name, path, before, after, term in cases
    ], probe)


if __name__ == "__main__":
    run_domain("scalars", CONTRACT_NAME, CONTRACT, FIXTURE, execute, check_faults)
