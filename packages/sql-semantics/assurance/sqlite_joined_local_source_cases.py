"""Independent scoped VALUES-join cases, parameter footprints, and row observations."""

from itertools import product
from random import Random

from sqlite_relational_cases import KINDS, children, evaluate, expected as relational, parameter_names

NAMES = ["key", "value"]
LOCAL = ["local", 0]
SHADOW = ["main", "__ztd_cte", NAMES]
OTHER = ["main", "ir_cte_peer", NAMES]
EMPTY = ["main", "ir_cte_empty", NAMES]
EXTERNAL = {
    SHADOW[1]: [(1, -31), (0, "external"), (1, -31), (-9, None), (None, 7)],
    OTHER[1]: [(0, 30), (1, 4), (2, 90), (None, -1)],
    EMPTY[1]: [],
}
DECLARATIONS = [f'CREATE TABLE main."{table}" ("key" BLOB, "value" BLOB)' for table in EXTERNAL]
DIMENSIONS = ("join", "placement", "condition", "rowShape", "distinct")


def scalar(value):
    if value is None:
        return ["null"]
    return ["integer", str(value)] if type(value) is int else ["text", value]


def column(slot, occurrence=0):
    return ["column", slot, occurrence] if occurrence else ["column", slot]


def match(occurrence, prior=0, operator="="):
    return ["comparison", operator, column(0, prior), column(0, occurrence)]


def fields(count):
    return [[column(slot, occurrence), "key" if slot == 0 else f"field{occurrence}"]
            for occurrence in range(count) for slot in range(2)]


def plan(source, joins, rows, *, columns=None, where=None, distinct=False, order=(), bindings=None):
    return {
        "source": source, "joins": joins, "locals": [{"names": NAMES, "rows": rows}],
        "columns": fields(len(joins) + 1) if columns is None else columns, "where": where,
        "orderBy": list(order), "distinct": distinct, "limit": None,
        "bindings": {} if bindings is None else bindings,
    }


def finite_case(kind, placement, condition, row_shape, distinct):
    source, right = {"first": (LOCAL, SHADOW), "right": (SHADOW, LOCAL), "repeat": (LOCAL, LOCAL)}[placement]
    rows = [[scalar(1), scalar(71)]]
    if row_shape == "duplicate-null":
        rows += [rows[0], [scalar(None), scalar(-60)]]
    return plan(source, [[kind, right, match(1) if condition == "explicit" else None]], rows, distinct=distinct)


def finite_ids(domain):
    return set(product(*(domain[name] for name in DIMENSIONS)))


def finite_id(value, _domain):
    kind, right, condition = value["joins"][0]
    source, = value["locals"]
    placement = "right" if value["source"] != LOCAL else "repeat" if right == LOCAL else "first"
    return (kind, placement, "explicit" if condition is not None else "unconditional",
            "single" if len(source["rows"]) == 1 else "duplicate-null", value["distinct"])


def marker_names(item):
    labels = {}
    for row in item["locals"][0]["rows"]:
        for term in row:
            for operand in children(term):
                if operand[0] == "parameter":
                    labels.setdefault(str(int(operand[1])), "?" + operand[1])
    for slot, spelling in parameter_names(item).items():
        labels.setdefault(slot, spelling)
    return labels


def seeded(seed, count):
    rng = Random(seed)
    for index in range(count):
        width = 2 + index % 4
        inputs = [rng.choice((LOCAL, SHADOW, OTHER, EMPTY)) for _ in range(width)]
        inputs[rng.randrange(width)] = LOCAL
        rows = [[scalar(rng.choice((None, -2, 0, 1, 2, 7, "2"))), scalar(rng.choice((None, 3, "語", "x", -42)))]
                for _ in range(rng.randrange(1, 5))]
        if index % 4 == 0:
            rows[0][0] = ["parameter", "02"]
            rows[-1][1] = ["parameter", "007"]
        if index % 5 == 0:
            rows.append(rows[0])
        joins = [[rng.choice(KINDS), inputs[occurrence],
                  None if rng.randrange(5) == 0 else match(occurrence, rng.randrange(occurrence), rng.choice(("=", "IS", "<>")))]
                 for occurrence in range(1, width)]
        columns = fields(width)
        if index % 2 == 0:
            columns.append([["parameter", "2"], "marker"])
        for _ in range(index % 3):
            chosen = rng.choice(columns)
            columns.append([chosen[0], rng.choice(("key", "é", '"notice"'))])
        where = None if index % 3 else ["comparison", "IS NOT", column(1, width - 1), ["null"]]
        order = [] if rng.randrange(3) == 0 else [[rng.randint(1, len(columns)), bool(rng.getrandbits(1)), bool(rng.getrandbits(1))]]
        item = plan(inputs[0], joins, rows, columns=columns, where=where, distinct=bool(rng.getrandbits(1)), order=order)
        item["bindings"] = {slot: rng.choice((None, 0, 1, 4, "2", "text")) for slot in marker_names(item)}
        yield item


def analyzed():
    repeated = finite_case("LEFT", "repeat", "explicit", "single", False)
    repeated["locals"][0]["rows"] = [[["parameter", "02"], scalar(71)], [scalar(1), scalar(71)]]
    repeated["columns"].append([["parameter", "2"], "bound"])
    repeated["orderBy"] = [[1, False, True], [3, True, False]]
    repeated["bindings"] = {"2": 1}
    selection = 'SELECT a.key AS key, a.value AS field0, b.key AS key, b.value AS field1'
    yield (
        'WITH __ztd_cte(key,value) AS (VALUES (?02,71),(1,71)) '
        + selection + ', ?2 AS bound FROM __ztd_cte a LEFT JOIN __ztd_cte b ON a.key=b.key ORDER BY 1,3 DESC',
        repeated,
    )
    for kind, placement in (("RIGHT", "right"), ("FULL", "first"), ("CROSS", "first"), ("INNER", "right")):
        item = finite_case(kind, placement, "explicit", "single", False)
        a, b = ("c", "main.__ztd_cte") if placement == "first" else ("main.__ztd_cte", "c")
        sql = 'WITH c(key,value) AS (VALUES (1,71)) ' + selection + f' FROM {a} a {kind} JOIN {b} b ON a.key=b.key'
        yield sql, item


def requests(contract):
    domain, settings = contract["finite"], contract["exploration"]
    for index, choices in enumerate(product(*(domain[name] for name in DIMENSIONS))):
        item = finite_case(*choices)
        yield f"finite/{index}", item, item
    for seed in settings["seeds"]:
        for index, item in enumerate(seeded(seed, settings["casesPerSeed"])):
            yield f"seed/{seed}/{index}", item, item
    for index, (sql, item) in enumerate(analyzed()):
        yield f"analyze/{index}", {"sql": sql, "declarations": DECLARATIONS}, item


def negatives():
    item = finite_case("LEFT", "repeat", "explicit", "single", False)
    yield {**item, "source": None}, "rejected", "Joins require a first table"
    yield {**item, "locals": item["locals"] * 2, "joins": [["LEFT", ["local", 1], match(1)]]}, "rejected", "must share one local definition"
    yield {**item, "columns": [[column(2), "out"]]}, "rejected", "must belong to the statement source"
    yield {**item, "joins": [["INNER", SHADOW, match(2)], ["LEFT", LOCAL, None]]}, "rejected", "must belong to the statement source"
    recursive = {"names": NAMES, "rows": [[scalar(1), scalar(2)]], "step": [column(0), column(1)],
                 "where": ["comparison", "<", column(0), scalar(0)], "unionAll": True}
    yield {**item, "locals": [recursive]}, "rejected", "recursive local definition requires a SELECT without joins"
    yield {"sql": "WITH c(key,value) AS (VALUES (1,4)) SELECT key FROM main.__ztd_cte",
           "declarations": DECLARATIONS}, "rejected", "must read its checked local definition"


def expected(item):
    virtual = ["main", "independent local rows", NAMES]
    rows = [tuple(evaluate(expression, (), item["bindings"]) for expression in row)
            for row in item["locals"][0]["rows"]]
    spec = {**item, "source": virtual if item["source"] == LOCAL else item["source"],
            "joins": [[kind, virtual if source == LOCAL else source, guard] for kind, source, guard in item["joins"]]}
    predicted = relational(spec, {**EXTERNAL, virtual[1]: rows})
    if item["distinct"]:
        unique = {tuple(map(tuple, row)) for row in predicted["rows"]}
        predicted["rows"] = [[list(value) for value in row] for row in sorted(unique)]
    names = marker_names(item)
    return {**predicted, "parameters": names, "parameterCount": max(map(int, names), default=0)}
