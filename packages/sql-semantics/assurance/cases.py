"""Independent SQLite SELECT observations and direct-construction input domains."""

import itertools
import random
import re

from sqlite_engine import typed

MIN, MAX = -(2**63), 2**63 - 1
ROWS = [
    (4, 23), (4, 23), (None, -2), (3, None), (-2, 0),
    ("not numeric", 2), (" 4.5tail", -1), (MAX, 1), (2.5, MIN),
    (b"2\x00tail", b"abc"),
]
SCHEMA = """
PRAGMA encoding = 'UTF-8';
PRAGMA short_column_names = ON;
PRAGMA full_column_names = OFF;
CREATE TABLE main.accounts (Id BLOB, delta BLOB);
INSERT INTO main.accounts VALUES (4,23),(4,23),(NULL,-2),(3,NULL),(-2,0),
 ('not numeric',2),(' 4.5tail',-1),(9223372036854775807,1),
 (2.5,-9223372036854775808),(X'32007461696c',X'616263');
CREATE TEMP TABLE accounts (Id BLOB, delta BLOB);
INSERT INTO temp.accounts VALUES (77,88);
CREATE TABLE main."odd"" table" ("a"" b" BLOB);
INSERT INTO main."odd"" table" VALUES (14),(NULL);
"""
MAIN = ["main", "accounts", ["Id", "delta"]]
TEMP = ["temp", "accounts", ["Id", "delta"]]
ODD = ["main", 'odd" table', ['a" b']]
TABLES = {
    ("main", "accounts"): [{"Id": row[0], "delta": row[1]} for row in ROWS],
    ("temp", "accounts"): [{"Id": 77, "delta": 88}],
    ("main", 'odd" table'): [{'a" b': 14}, {'a" b': None}],
}
ATOMS = [["integer", "-2"], ["integer", "0"], ["integer", "3"], ["null"], ["parameter", "2"]]
REFERENCES = [["column", 0], ["column", 1]]
OPERATORS = ["+", "-", "*"]


def case(source, expressions, bindings=None, labels=None):
    return {
        "source": source,
        "columns": [[expression, labels[index] if labels is not None else f"r{index}"]
                    for index, expression in enumerate(expressions)],
        "bindings": {} if bindings is None else bindings,
    }


def finite_cases():
    for source, atoms in [(None, ATOMS), (MAIN, ATOMS + REFERENCES)]:
        for atom in atoms:
            yield case(source, [atom], {"2": -6} if atom[0] == "parameter" else {})
        for left, right in itertools.product(atoms, repeat=2):
            bound = {"2": -6} if ["parameter", "2"] in (left, right) else {}
            yield case(source, [left, right], bound)
            for operator in OPERATORS:
                yield case(source, [["arithmetic", operator, left, right]], bound)


def finite_identity(source, columns):
    def symbol(value):
        return ":".join(map(str, value))

    key = []
    for expr, _ in columns:
        key.append((expr[1], symbol(expr[2]), symbol(expr[3])) if expr[0] == "arithmetic" else (symbol(expr),))
    return (source is not None, tuple(key))


def expected_finite_ids():
    # The completion check uses a separately specified domain of symbolic IDs.
    expected = set()
    symbols = ["integer:-2", "integer:0", "integer:3", "null", "parameter:2", "column:0", "column:1"]
    for has_source in (False, True):
        leaves = symbols if has_source else symbols[:5]
        expected.update((has_source, ((leaf,),)) for leaf in leaves)
        for i in range(len(leaves) ** 2):
            left, right = leaves[i // len(leaves)], leaves[i % len(leaves)]
            expected.add((has_source, ((left,), (right,))))
            for operator in ("*", "-", "+"):
                expected.add((has_source, ((operator, left, right),)))
    return expected


def walk(expression):
    yield expression
    if expression[0] == "arithmetic":
        yield from walk(expression[2])
        yield from walk(expression[3])


def slots(columns):
    names = {}
    for expression, _ in columns:
        for operand in walk(expression):
            if operand[0] == "parameter":
                names.setdefault(str(int(operand[1])), "?" + operand[1])
    return names


def structural_cases(seed, count, height, width):
    rng = random.Random(seed)
    for _ in range(count):
        source = rng.choice([None, MAIN, TEMP, ODD, ["main", "accounts", ["delta", "Id"]]])
        leaves = ATOMS + [["parameter", "03"], ["parameter", "2"], ["parameter", "0002"],
                          ["integer", str(MIN)], ["integer", str(MAX)]]
        if source:
            leaves += [["column", slot] for slot in range(len(source[2]))]

        def generate(depth):
            if depth and rng.randrange(100) < 56:
                return ["arithmetic", rng.choice(OPERATORS), generate(depth - 1), generate(depth - 1)]
            return rng.choice(leaves)

        columns = [generate(rng.randrange(height + 1)) for _ in range(rng.randint(1, width))]
        names = [rng.choice(["same", "select", 'a"b', "", "値", "line\nname", "01"]) for _ in columns]
        item = case(source, columns, labels=names)
        item["bindings"] = {slot: rng.choice([-6, None, " 3.25junk", -0.5, MAX]) for slot in slots(item["columns"])}
        yield item


def numeric(value):
    if type(value) in (int, float):
        return value
    if type(value) is bytes:
        value = value.decode("latin1")
    prefix = re.match(r"[ \t\r\n\f\v]*([+-]?(?:[0-9]+(?:\.[0-9]*)?|\.[0-9]+)(?:[eE][+-]?[0-9]+)?)", value)
    if not prefix:
        return 0
    text = prefix[1]
    if not any(symbol in text for symbol in ".eE"):
        integer = int(text)
        if MIN <= integer <= MAX:
            return integer
    return float(text)


def evaluate(expression, source, row, bindings):
    tag = expression[0]
    if tag == "integer":
        return int(expression[1])
    if tag == "null":
        return None
    if tag == "column":
        return row[source[2][expression[1]]]
    if tag == "parameter":
        return bindings.get(str(int(expression[1])))
    assert tag == "arithmetic"
    op = expression[1]
    left = evaluate(expression[2], source, row, bindings)
    right = evaluate(expression[3], source, row, bindings)
    if left is None or right is None:
        return None
    a, b = numeric(left), numeric(right)
    if op == "+":
        integer_result = a + b
    elif op == "-":
        integer_result = a - b
    else:
        assert op == "*"
        integer_result = a * b
    if type(integer_result) is int and not MIN <= integer_result <= MAX:
        # SQLite promotes the operands and retries with REAL arithmetic.
        if op == "+":
            return float(a) + float(b)
        if op == "-":
            return float(a) - float(b)
        return float(a) * float(b)
    return integer_result


def expected(item):
    source, fields = item["source"], item["columns"]
    data = [{}] if source is None else TABLES[tuple(source[:2])]
    parameters = slots(fields)
    return {
        "names": [name for _, name in fields],
        "parameters": parameters,
        "parameterCount": max(map(int, parameters), default=0),
        "rows": sorted([[typed(evaluate(expr, source, row, item["bindings"])) for expr, _ in fields] for row in data]),
        "readOnly": True,
        "changes": 0,
    }
