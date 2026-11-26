"""Independent finite domain and evaluator for the two signed-integer projections."""

import itertools
import json
import random

from server_truth_model import admits, predicate_value

ATOMS = [["integer", "-2"], ["integer", "0"], ["integer", "3"]]
REFERENCES = [["slot", 0], ["slot", 1]]
OTHER_LEAVES = [["boolean", False], ["boolean", True], ["null"]]
OPERATORS = ("+", "-", "*")
ROWS = [(2, 3), (2, 3), (None, -2), (3, None), (-2, 0), (1, -3)]
CHILD_ROWS = [(-1, 2), (1, 3)]
POISON_VALUES = ("a", "b", "c", "d", "e", "f", "child", "child")
VERSIONS = {"mysql": "mysql-9.1.0", "postgres": "pg-17.2"}
NAMES = {"mysql": ["Id", "delta"], "postgres": ["id", "delta"]}
SCHEMAS = {"mysql": "ir_fixture", "postgres": "public"}


def source(dialect, only=False):
    value = {"schema": SCHEMAS[dialect], "table": "ir_evidence_accounts", "columns": NAMES[dialect]}
    if dialect == "postgres":
        value["only"] = only
    return value


def sources(dialect):
    return [None, source(dialect), source(dialect, only=True)] if dialect == "postgres" else [None, source(dialect)]


def case(dialect, table, expressions, labels=None):
    return {
        "dialect": dialect, "release": VERSIONS[dialect], "source": table,
        "columns": [[expr, f"r{index}" if labels is None else labels[index]] for index, expr in enumerate(expressions)],
    }


def direct_cases(dialect):
    for table in sources(dialect):
        integers = ATOMS + REFERENCES if table is not None else ATOMS
        for leaf in integers + OTHER_LEAVES:
            yield case(dialect, table, [leaf])
        for operator, left, right in itertools.product(OPERATORS, integers, integers):
            yield case(dialect, table, [["operation", operator, left, right]])


def node_key(operand):
    return json.dumps(operand, separators=(",", ":"))


def source_key(table):
    if table is None:
        return "none"
    return "own" if table.get("only") else "all"


def finite_ids(dialect):
    identities = set()
    for key in ["none", "all", "own"] if dialect == "postgres" else ["none", "all"]:
        atoms = ['["integer","-2"]', '["integer","0"]', '["integer","3"]']
        if key != "none":
            atoms += ['["slot",0]', '["slot",1]']
        identities.update((key, atom) for atom in atoms + ['["boolean",false]', '["boolean",true]', '["null"]'])
        for index in range(len(atoms) ** 2):
            left, right = atoms[index // len(atoms)], atoms[index % len(atoms)]
            for sign in "*-+":
                identities.add((key, f'["operation","{sign}",{left},{right}]'))
    return identities


def explore(dialect, seed, count, height, width):
    rng = random.Random(seed)
    for _ in range(count):
        table = rng.choice(sources(dialect))
        integers = ATOMS if table is None else ATOMS + REFERENCES

        def integer_expression(depth):
            if depth and rng.random() < 0.65:
                return ["operation", rng.choice(OPERATORS), integer_expression(depth - 1), integer_expression(depth - 1)]
            return rng.choice(integers)

        fields = []
        labels = []
        for _ in range(rng.randint(1, width)):
            fields.append(integer_expression(rng.randint(0, height)) if rng.random() < 0.75 else rng.choice(OTHER_LEAVES))
            labels.append(rng.choice(["id", "id", "NULL", 'a"`b', "値", "one\ntwo", "trailing "]))
        yield case(dialect, table, fields, labels)


def walk(operand):
    yield operand[0]
    if operand[0] == "operation":
        yield from walk(operand[2])
        yield from walk(operand[3])


def validate_width(result, dialect):
    minimum, maximum = (-(2 ** 31), 2 ** 31 - 1) if dialect == "postgres" else (-(2 ** 63), 2 ** 63 - 1)
    if result < minimum or result > maximum:
        raise OverflowError("signed arithmetic overflow")
    return result


def evaluate(operand, row, dialect, release=None):
    tag = operand[0]
    if tag == "slot":
        return row[operand[1]]
    if tag in ("integer", "range-integer"):
        return int(operand[1])
    if tag == "boolean":
        return operand[1] if dialect == "postgres" else int(operand[1])
    if tag == "null":
        return None
    if tag in ("int4-range", "signed-range", "int4-distinctness", "truth-test"):
        result = predicate_value(operand, lambda leaf: evaluate(leaf, row, dialect, release))
        return int(result) if dialect == "mysql" and result is not None else result
    if tag == "int4-case":
        _, arms, otherwise = operand
        for condition, result in arms:
            if predicate_value(condition, lambda value: evaluate(value, row, dialect, release)) is True:
                return evaluate(result, row, dialect, release)
        return None if otherwise is None else evaluate(otherwise, row, dialect, release)
    assert tag == "operation"
    left, right = [evaluate(child, row, dialect, release) for child in operand[2:]]
    if left is None or right is None:
        return None
    if operand[1] == "+":
        return validate_width(left + right, dialect)
    if operand[1] == "-":
        return validate_width(left - right, dialect)
    if operand[1] == "*":
        product = validate_width(left * right, dialect)
        minimum = -(2 ** 63)
        if dialect == "mysql" and release and not release.startswith("mysql-5.") and product == minimum and min(left, right) != minimum:
            raise OverflowError("MySQL signed product magnitude")
        return product
    operator = operand[1]
    if dialect == "mysql":
        assert operator in ("DIV", "%")
        if right == 0:
            return None
        absolute = abs(left) // abs(right)
        signed = -absolute if (left < 0) != (right < 0) else absolute
        if operator == "%":
            return left - right * signed
        if absolute > (2 ** 63 - 1):
            raise OverflowError("MySQL signed quotient magnitude")
        return signed
    if operator in ("/", "%"):
        magnitude = abs(left) // abs(right)
        quotient = -magnitude if (left < 0) != (right < 0) else magnitude
        return validate_width(quotient, dialect) if operator == "/" else left - right * quotient
    if operator == "&":
        return left & right
    if operator == "|":
        return left | right
    assert operator == "#"
    return left ^ right


def observation(observed):
    if observed["status"] == "rows":
        observed = {**observed, "rows": sorted(observed["rows"], key=json.dumps)}
    return observed


def expected(item):
    dialect, table = item["dialect"], item["source"]
    rows = [None] if table is None else ROWS + (CHILD_ROWS if dialect == "postgres" and not table["only"] else [])
    fields = item["columns"]
    mapping = ({"boolean": "bool", "int4-range": "bool", "int4-distinctness": "bool", "truth-test": "bool", "null": "text"} if dialect == "postgres"
               else {"slot": "LONG", "null": "NULL"})
    default = "int4" if dialect == "postgres" else "LONGLONG"
    try:
        values = [
            [evaluate(expression, row, dialect, item["release"]) for expression, _ in fields]
            for row in rows if admits(item.get("where"), lambda leaf: evaluate(leaf, row, dialect, item["release"]))
        ]
    except OverflowError:
        return {"status": "db-error", "state": "22003"}
    except ZeroDivisionError:
        return {"status": "db-error", "state": "22012"}
    return observation({"status": "rows", "fields": [[name, mapping.get(expr[0], default)] for expr, name in fields], "rows": values})


def declarations(dialect):
    schema = SCHEMAS[dialect]
    name = NAMES[dialect][0]
    root = f"CREATE TABLE {schema}.ir_evidence_accounts ({name} INT, delta INT, poison TEXT)"
    if dialect == "mysql":
        return [root]
    return [root, f"CREATE TABLE {schema}.ir_evidence_child () INHERITS ({schema}.ir_evidence_accounts)"]


def fixtures(dialect):
    schema = SCHEMAS[dialect]
    parent = f"{schema}.ir_evidence_accounts"
    child = f"{schema}.ir_evidence_child"
    return [
        *([f"DROP TABLE IF EXISTS {child}"] if dialect == "postgres" else []),
        f"DROP TABLE IF EXISTS {parent}",
        *declarations(dialect),
        f"INSERT INTO {parent} VALUES (2,3,'a'),(2,3,'b'),(NULL,-2,'c'),(3,NULL,'d'),(-2,0,'e'),(1,-3,'f')",
        *([f"INSERT INTO {child} VALUES (-1,2,'child'),(1,3,'child')"] if dialect == "postgres" else []),
    ]


def analyzed(dialect, sql, schemas=True, version=None):
    return {"dialect": dialect, "release": version or VERSIONS[dialect], "sql": sql,
            "declarations": declarations(dialect)[:1] if schemas else []}
