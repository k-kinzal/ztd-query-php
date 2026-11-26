"""Finite, structural and independent row selection rules for SQLite SELECT."""

import itertools
import random
from collections import Counter, defaultdict
from functools import cmp_to_key

from run import compare, require
from sqlite_relational_cases import DATA, case, expected, explore, order_value, parameter_names, untyped

PAGE = ["main", "ir_page", ["key", "value"]]
PAGE_DATA = [
    (1, "x"), (1.0, "x"), (1, "x"), (1, "y"), (None, "x"),
    (None, "x"), ("1", "x"), (b"1", "x"), (2, None), (-1, "z"),
]
RESULT_DATA = dict(DATA, ir_page=PAGE_DATA)
PAGE_DDL = "CREATE TABLE ir_page (key BLOB, value BLOB)"
PAGE_ROWS = """
INSERT INTO ir_page VALUES
 (1,'x'),(1.0,'x'),(1,'x'),(1,'y'),(NULL,'x'),(NULL,'x'),('1','x'),(X'31','x'),(2,NULL),(-1,'z');
"""


def integer(value):
    return ["integer", str(value)]


LIMITS = [
    None, [integer(0), None, False], [integer(2), None, False],
    [integer(-1), integer(-2), False], [integer(2), integer(1), False],
    [integer(3), integer(2), True],
]
ORDERS = [[], [[1, False, False]], [[1, True, True], [2, False, True]]]
PROJECTIONS = [
    [[["column", column], name] for column, name in zip(columns, ("p", "v"))]
    for columns in [(0, 1), (0, 0), (1, 0)]
]
GRAPH_FIELDS = ("source", "joins", "where", "orderBy", "columns", "distinct", "limit")


def finite_cases():
    for distinct, limit, orders, projection in itertools.product((False, True), LIMITS, ORDERS, PROJECTIONS):
        yield dict(case(source=PAGE, orders=orders, columns=projection), distinct=distinct, limit=limit)


def finite_id(value):
    return (value["distinct"], LIMITS.index(value["limit"]),
            ORDERS.index(value["orderBy"]), PROJECTIONS.index(value["columns"]))


def finite_ids():
    return {(bool(code // 54), code // 9 % 6, code // 3 % 3, code % 3) for code in range(108)}


def seeded_cases(seed, count):
    rng = random.Random(seed ^ 0x4C494D)
    for item in explore(seed, count, 2, 3, 3):
        item["distinct"] = bool(rng.getrandbits(1))
        item["limit"] = None
        if not rng.getrandbits(1):
            yield item
            continue
        count_expr = rng.choice((integer(rng.randrange(-3, 8)), ["parameter", "02"], ["parameter", "3"]))
        offset = rng.choice((None, integer(rng.randrange(-3, 8)), ["parameter", "2"], ["parameter", "001"]))
        for expr in (count_expr, offset):
            if expr is not None and expr[0] == "parameter":
                item["bindings"][str(int(expr[1]))] = rng.randrange(-3, 8)
        item["limit"] = [count_expr, offset, offset is not None and bool(rng.getrandbits(1))]
        yield item


def result_parameters(item):
    names = parameter_names(item)
    if item["limit"] is not None:
        count, offset, offset_first = item["limit"]
        roots = (offset, count) if offset_first else (count, offset)
        for root in roots:
            if root is not None and root[0] == "parameter":
                names.setdefault(str(int(root[1])), "?" + root[1])
    return names


def row_identity(row):
    return tuple(tuple(value) for value in row)


def duplicate_key(row):
    return tuple(order_value(untyped(value)) for value in row)


def order_comparison(first, second, orders):
    for position, descending, nulls_first in orders:
        left, right = untyped(first[position - 1]), untyped(second[position - 1])
        if left is None or right is None:
            if left is right:
                continue
            return -1 if (left is None) == nulls_first else 1
        a, b = order_value(left), order_value(right)
        if a != b:
            comparison = 1 if a > b else -1
            return -comparison if descending else comparison
    return 0


def representatives(rows):
    equivalent = defaultdict(list)
    for row in rows:
        equivalent[duplicate_key(row)].append(row)
    return list(equivalent.values())


def allowed_result(before, actual, distinct, orders, offset, count):
    compare({key: value for key, value in before.items() if key != "rows"},
            {key: value for key, value in actual.items() if key != "rows"})
    rows = before["rows"]
    groups = representatives(rows) if distinct else [[row] for row in rows]
    ordering = cmp_to_key(lambda a, b: order_comparison(a[0], b[0], orders))
    groups.sort(key=ordering)
    position = 0
    output = 0
    end = len(groups) if count < 0 else offset + count
    quantity = max(0, min(end, len(groups)) - offset)
    require(quantity == len(actual["rows"]), "row-bound-mismatch", {"expected": quantity, "actual": len(actual["rows"])})
    for _, tied in itertools.groupby(groups, key=ordering):
        band = list(tied)
        next_position = position + len(band)
        quantity = max(0, min(end, next_position) - max(offset, position))
        seen = actual["rows"][output:output + quantity]
        if distinct:
            available = {duplicate_key(group[0]): {row_identity(row) for row in group} for group in band}
            for row in seen:
                require(row_identity(row) in available.pop(duplicate_key(row), ()),
                        "distinct-class-mismatch", {"row": row, "eligible": band})
        else:
            eligible = Counter(row_identity(group[0]) for group in band)
            require(not (Counter(map(row_identity, seen)) - eligible), "ordered-band-mismatch", {"seen": seen, "eligible": band})
        position = next_position
        output += quantity


def check_meaning(item, observation):
    baseline = expected(item, RESULT_DATA)
    names = result_parameters(item)
    baseline.update(parameters=names, parameterCount=max(map(int, names), default=0))
    offset, count = 0, -1
    if item["limit"] is not None:
        count_expr, offset_expr, _ = item["limit"]
        count = bound_value(count_expr, item["bindings"])
        if offset_expr is not None:
            offset = max(0, bound_value(offset_expr, item["bindings"]))
    allowed_result(baseline, observation, item["distinct"], item["orderBy"], offset, count)


def bound_value(expression, bindings):
    kind, label = expression
    if kind == "integer":
        return int(label)
    assert kind == "parameter"
    value = bindings[str(int(label))]
    assert type(value) is int
    return value
