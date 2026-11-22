"""Independent observations and direct inputs for the SQLite relational domain."""

import itertools
import random

from cases import numeric
from sqlite_engine import typed

LEFT = ["main", "ir_left", ["key", "value"]]
RIGHT = ["main", "ir_right", ["key", "value"]]
EMPTY = ["main", "ir_empty", ["key", "value"]]
DATA = {
    "ir_left": [(-2, 10), (0, 8), (1, 2), (1, 2), (None, 14), (2, None), ("2z", b"two"), (1.5, "skip")],
    "ir_right": [(1, 1), (1, 3), (0, None), (None, 8), (5, 9), (1.5, -1)],
    "ir_empty": [],
}
SCHEMA = """
PRAGMA encoding = 'UTF-8';
PRAGMA short_column_names = ON;
PRAGMA full_column_names = OFF;
CREATE TABLE ir_left (key BLOB, value BLOB);
CREATE TABLE ir_right (key BLOB, value BLOB);
CREATE TABLE ir_empty (key BLOB, value BLOB);
CREATE TABLE ir_text (word TEXT COLLATE NOCASE);
INSERT INTO ir_left VALUES (-2,10),(0,8),(1,2),(1,2),(NULL,14),(2,NULL),('2z',X'74776F'),(1.5,'skip');
INSERT INTO ir_right VALUES (1,1),(1,3),(0,NULL),(NULL,8),(5,9),(1.5,-1);
INSERT INTO ir_text VALUES ('alpha'),('Zulu'),('Alpha');
"""
DECLARATIONS = [f"CREATE TABLE {table} (key BLOB, value BLOB)" for table in DATA] + ["CREATE TABLE ir_text (word TEXT COLLATE NOCASE)"]
KINDS = ("INNER", "CROSS", "LEFT", "RIGHT", "FULL")
JOINS = [None, ["comparison", "=", ["column", 0], ["column", 0, 1]], ["comparison", "IS", ["column", 0], ["column", 0, 1]]]
FILTERS = [None, ["null"], ["comparison", ">=", ["column", 1, 1], ["integer", "1"]]]
ORDERS = [[], [[1, False, False]], [[1, True, True], [2, False, True]]]
COMPARISONS = ("=", "<>", "<", "<=", ">", ">=", "IS", "IS NOT")


def case(source=LEFT, joins=(), where=None, orders=(), columns=None, bindings=None):
    return {
        "source": source, "joins": list(joins), "where": where, "orderBy": list(orders),
        "columns": [[["column", 0], "key"], [["column", 0, 1], "key"]] if columns is None else columns,
        "bindings": {} if bindings is None else bindings,
    }


def finite_cases():
    for kind in KINDS:
        for on, where, orders in itertools.product(JOINS, FILTERS, ORDERS):
            yield case(joins=[[kind, RIGHT, on]], where=where, orders=orders)


def finite_id(value):
    return (value["joins"][0][0], JOINS.index(value["joins"][0][2]), FILTERS.index(value["where"]), ORDERS.index(value["orderBy"]))


def finite_ids():
    return {
        (kind, code // 9, code // 3 % 3, code % 3)
        for kind in ("INNER", "CROSS", "LEFT", "RIGHT", "FULL")
        for code in range(27)
    }


def children(expression):
    if expression is None:
        return
    yield expression
    if expression[0] in ("arithmetic", "comparison", "logical"):
        yield from children(expression[2])
        yield from children(expression[3])
    elif expression[0] == "concat":
        yield from children(expression[1])
        yield from children(expression[2])
    elif expression[0] == "pattern":
        for term in expression[3:]:
            yield from children(term)
    elif expression[0] == "case":
        yield from children(expression[1])
        for when, result in expression[2]:
            yield from children(when)
            yield from children(result)
        yield from children(expression[3])
    elif expression[0] in ("cast", "collate", "unary"):
        yield from children(expression[2])
    elif expression[0] == "truth":
        yield from children(expression[3])
    elif expression[0] == "folded-membership":
        for label in expression[2]:
            yield ["parameter", label]
    elif expression[0] == "membership":
        yield from children(expression[2])
        for term in expression[3]:
            yield from children(term)
    elif expression[0] == "between":
        for term in expression[2:]:
            yield from children(term)


def parameter_names(item):
    roots = [expr for expr, _ in item["columns"]] + [step[2] for step in item["joins"]] + [item["where"]]
    first = {}
    for expr in roots:
        for member in children(expr):
            if member[0] == "parameter":
                first.setdefault(str(int(member[1])), "?" + member[1])
    return first


def explore(seed, count, max_joins, depth, max_order):
    rng = random.Random(seed)

    def scalar(available, level):
        if level and rng.random() < 0.35:
            return ["arithmetic", rng.choice(("+", "-", "*")), scalar(available, level - 1), scalar(available, level - 1)]
        types = [["null"], ["integer", str(rng.randrange(-3, 4))], ["parameter", rng.choice(("2", "02", "3"))]]
        types += [["column", slot, occurrence] if occurrence else ["column", slot]
                  for occurrence in range(available) for slot in range(2)]
        return rng.choice(types)

    def predicate(available, level):
        choice = rng.randrange(4)
        if level and choice == 0:
            return ["logical", rng.choice(("AND", "OR")), predicate(available, level - 1), predicate(available, level - 1)]
        if choice in (1, 2):
            return ["comparison", rng.choice(COMPARISONS), scalar(available, 2), scalar(available, 2)]
        return scalar(available, 2)

    for _ in range(count):
        source = rng.choice((LEFT, RIGHT, EMPTY, None))
        joins = []
        if source is not None:
            for occurrence in range(1, rng.randint(0, max_joins) + 1):
                joins.append([rng.choice(KINDS), rng.choice((LEFT, RIGHT, EMPTY)),
                              None if rng.random() < 0.2 else predicate(occurrence + 1, depth)])
        width = rng.randrange(1, 6)
        available = 0 if source is None else len(joins) + 1
        columns = [[scalar(available, 2), rng.choice(("key", "key", "値", 'a"b', ""))] for _ in range(width)]
        order = [[rng.randrange(1, width + 1), rng.choice((True, False)), rng.choice((True, False))]
                 for _ in range(rng.randint(0, max_order))]
        where = None if rng.random() < 0.2 else predicate(available, depth)
        item = case(source, joins, where, order, columns)
        item["bindings"] = {slot: rng.choice((None, 0, 2, -1.5, "1z", "none")) for slot in parameter_names(item)}
        yield item


def truth(value):
    return value if value is None or type(value) is bool else numeric(value) != 0


def order_value(value):
    if value is None:
        return (0, 0)
    if type(value) in (int, float):
        return (1, value)
    if type(value) is str:
        return (2, value.encode(errors="surrogateescape"))
    assert type(value) is bytes
    return (3, value)


def evaluate(expression, row, bindings):
    kind = expression[0]
    if kind == "column":
        source = expression[2] if len(expression) == 3 else 0
        return row[source][expression[1]]
    if kind == "integer":
        return int(expression[1])
    if kind == "null":
        return None
    if kind == "text":
        return expression[1]
    if kind == "parameter":
        return bindings.get(str(int(expression[1])))
    operator = expression[1]
    left, right = (evaluate(child, row, bindings) for child in expression[2:])
    if kind == "logical":
        a, b = truth(left), truth(right)
        if operator == "AND":
            return False if False in (a, b) else (None if None in (a, b) else True)
        assert operator == "OR"
        return True if True in (a, b) else (None if None in (a, b) else False)
    if kind == "comparison":
        if operator == "IS":
            return order_value(left) == order_value(right)
        if operator == "IS NOT":
            return order_value(left) != order_value(right)
        if left is None or right is None:
            return None
        a, b = order_value(left), order_value(right)
        return {"=": a == b, "<>": a != b, "<": a < b, "<=": a <= b, ">": a > b, ">=": a >= b}[operator]
    assert kind == "arithmetic"
    if left is None or right is None:
        return None
    a, b = numeric(left), numeric(right)
    if operator == "+":
        return a + b
    if operator == "-":
        return a - b
    assert operator == "*"
    return a * b


def table_rows(source, data):
    assert source[0] == "main"
    positions = [("key", "value").index(name) for name in source[2]]
    return [tuple(row[position] for position in positions) for row in data[source[1]]]


def expected(item, data=DATA):
    base = item["source"]
    rowsets = [()] if base is None else [(row,) for row in table_rows(base, data)]
    widths = [] if base is None else [len(base[2])]
    for kind, table, predicate in item["joins"]:
        right = table_rows(table, data)
        rows = []
        matched_right = set()
        for prefix in rowsets:
            matched = False
            for offset, last in enumerate(right):
                combined = prefix + (last,)
                if predicate is None or truth(evaluate(predicate, combined, item["bindings"])) is True:
                    matched = True
                    matched_right.add(offset)
                    rows.append(combined)
            if not matched and kind in ("LEFT", "FULL"):
                rows.append(prefix + ((None,) * len(table[2]),))
        if kind in ("RIGHT", "FULL"):
            null_prefix = tuple((None,) * width for width in widths)
            rows.extend(null_prefix + (last,) for offset, last in enumerate(right) if offset not in matched_right)
        rowsets = rows
        widths.append(len(table[2]))
    rows = []
    for row in rowsets:
        if item["where"] is None or truth(evaluate(item["where"], row, item["bindings"])) is True:
            rows.append([typed(evaluate(expression, row, item["bindings"])) for expression, _ in item["columns"]])
    names = parameter_names(item)
    return {
        "names": [name for _, name in item["columns"]], "rows": sorted(rows),
        "parameters": names, "parameterCount": max(map(int, names), default=0),
        "readOnly": True, "changes": 0,
    }


def untyped(typed_value):
    storage, value = typed_value
    return {"integer": int, "real": float.fromhex, "blob": bytes.fromhex,
            "null": lambda x: x, "text": lambda x: x}[storage](value)


def ordered(rows, orders):
    for first, second in zip(rows, rows[1:]):
        for one_based, descending, nulls_first in orders:
            a, b = untyped(first[one_based - 1]), untyped(second[one_based - 1])
            if a is None and b is None:
                continue
            if a is None or b is None:
                if (a is None) != nulls_first:
                    return False
                break
            left, right = order_value(a), order_value(b)
            if left != right:
                if (left > right) != descending:
                    return False
                break
    return True
