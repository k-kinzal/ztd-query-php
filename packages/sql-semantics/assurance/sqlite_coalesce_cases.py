"""Fixed first non-NULL schedules and independent lazy scalar observations."""

from itertools import product
from random import Random

from sqlite_engine import typed
from sqlite_relational_cases import evaluate as common

NAME = "ir_coalesce"
SOURCE = ["main", NAME, ["a", "b"]]
DATA = [("a", "A"), ("B", None), (None, "a"), ("a", "A")]
DECLARATION = f"CREATE TABLE {NAME} (a TEXT COLLATE NOCASE, b BLOB)"
NULL = ["null"]
ESCAPE_ERROR = {"code": 1, "message": "ESCAPE expression must be a single character"}
DIMENSIONS = ("arity", "layout", "placement")


def call(arguments):
    return ["coalesce", arguments]


def integer(value):
    return ["integer", str(value)]


def marker(name):
    return ["parameter", name]


def equality(left, right, operator="="):
    return ["comparison", operator, left, right]


def plan(value, *, source=None, where=None, name="value", bindings=None):
    return {"source": source, "columns": [[value, name]], "bindings": {} if bindings is None else bindings,
            "joins": [], "orderBy": [], "distinct": False, "limit": None, "where": where}


def finite_case(arity, layout, placement):
    arguments = [NULL] * arity
    bindings = {}
    if layout == "first-text":
        arguments[0] = ["text", "éA"]
    elif layout == "last-integer":
        arguments[-1] = integer(-79)
    elif layout == "dormant-marker":
        arguments[:2] = [["blob", "00FF"], marker("02")]
        bindings["2"] = "unread"
    elif layout == "bound-last":
        arguments[-1] = marker("7")
        bindings["7"] = 1.625
    elif layout == "null-last":
        arguments[-1] = marker("07")
        bindings["7"] = None
    value = call(arguments)
    if placement == "is-null":
        value = equality(value, NULL, "IS")
    return plan(value, bindings=bindings)


def finite_ids(domain):
    return set(product(*(domain[key] for key in DIMENSIONS)))


def finite_id(reply, _domain):
    expression = reply["columns"][0][0]
    placement = "result" if expression[0] == "coalesce" else "is-null"
    values = expression[1] if placement == "result" else expression[2][1]
    if values[0][0] == "text":
        layout = "first-text"
    elif values[0][0] == "blob":
        layout = "dormant-marker"
    elif values[-1][0] == "integer":
        layout = "last-integer"
    elif values[-1] == marker("7"):
        layout = "bound-last"
    elif values[-1] == marker("07"):
        layout = "null-last"
    else:
        assert all(value == NULL for value in values), values
        layout = "all-null"
    return len(values), layout, placement


def seeded(seed, count):
    rng = Random(seed)
    for case in range(count):
        source = SOURCE if case % 2 else None
        bindings = {}

        def build(depth):
            if depth and rng.randrange(4) == 0:
                return call([build(depth - 1) for _ in range(rng.randrange(2, 6))])
            atoms = [NULL, NULL, integer(rng.randrange(-10, 10)), ["text", rng.choice(("0", "José", "語", ""))],
                     ["real", "1", "25", None, False], ["blob", "0027FF"]]
            if source is not None:
                atoms.extend([["column", 0], ["column", 1]])
            label = rng.choice(("02", "2", "5", "005", "07"))
            bindings[str(int(label))] = rng.choice((None, 17, 0.25, "bound §"))
            atoms.append(marker(label))
            return rng.choice(atoms)

        width = 17 if case % 7 == 0 else rng.randrange(2, 10)
        value = call([build(case % 4) for _ in range(width)])
        where = equality(call([["column", 1], NULL]), NULL, "IS NOT") if source is not None and case % 3 == 0 else None
        if case % 6 == 0:
            value = equality(value, NULL, "IS")
        item = plan(value, source=source, where=where, name=rng.choice(("value", "coalesced", 'a"b')))
        item["bindings"] = {slot: bindings[slot] for slot in footprint(item)}
        yield item


def invalid_escape():
    return ["pattern", "LIKE", False, ["text", "a"], ["text", "a"], ["text", "bad"]]


def analyzed():
    yield "SELECT IFNULL(ALL NULL,?007) AS value", plan(call([NULL, marker("007")]), bindings={"7": "binary alias"})
    yield "SELECT COALESCE(DISTINCT NULL,1) AS value", plan(call([NULL, integer(1)]))
    yield "SELECT coalesce(NULL,3)", plan(call([NULL, integer(3)]), name="coalesce(NULL,3)")
    yield "SELECT COALESCE(1,'a' LIKE 'a' ESCAPE 'bad') AS value", plan(call([integer(1), invalid_escape()]))
    yield "SELECT COALESCE(NULL,'a' LIKE 'a' ESCAPE 'bad') AS value", plan(call([NULL, invalid_escape()]))
    yield "SELECT COALESCE('a', NULL COLLATE NOCASE)='A' AS value", plan(
        equality(call([["text", "a"], ["collate", "NOCASE", NULL]]), ["text", "A"]))
    yield (f"SELECT COALESCE(a,b)='A' AS value FROM {NAME}", plan(
        equality(call([["column", 0], ["column", 1]]), ["text", "A"]), source=SOURCE))
    yield (f"SELECT COALESCE(a COLLATE NOCASE,b)='A' AS value FROM {NAME}", plan(
        equality(call([["collate", "NOCASE", ["column", 0]], ["column", 1]]), ["text", "A"]), source=SOURCE))


def generated_and_checked():
    fallback = call([["column", 0], integer(-3)])
    check = equality(call([["column", 0], integer(1)]), integer(0), ">=")
    sql = ("CREATE TABLE ir_coalesce_schema (a INTEGER, b INTEGER AS (COALESCE(a,-3)) STORED, "
           "CONSTRAINT admitted CHECK(COALESCE(a,1)>=0))")
    return sql, {"operation": "create-table", "schema": "main", "table": "ir_coalesce_schema",
                 "columns": [["a", "INTEGER", False], ["b", "INTEGER", False]],
                 "generated": [[1, "STORED", fallback]],
                 "checks": [{"label": "admitted", "column": None, "condition": check}]}


def requests(specification):
    domain, settings = specification["finite"], specification["exploration"]
    for index, values in enumerate(product(*(domain[key] for key in DIMENSIONS))):
        item = finite_case(*values)
        yield f"finite/{index}", item, item
    for seed in settings["seeds"]:
        for index, item in enumerate(seeded(seed, settings["casesPerSeed"])):
            yield f"seed/{seed}/{index}", item, item
    for index, (sql, item) in enumerate(analyzed()):
        yield f"analyze/{index}", {"sql": sql, "declarations": [DECLARATION]}, item
    sql, item = generated_and_checked()
    yield "analyze/schema", {"sql": sql, "declarations": []}, item


def negatives():
    yield plan(call([NULL])), "rejected", "at least two checked scalar arguments"
    yield plan(call([NULL] * 128)), "rejected", "at most 127 arguments"
    yield plan(call({"0": NULL, "2": integer(8)})), "rejected", "in order"
    yield plan(call([NULL, ["wrong-argument"]])), "rejected", "in order"
    for sql, error in (
        ("SELECT COALESCE(NULL,1) FILTER (WHERE 1)", "FILTER may not be used with non-aggregate"),
        ("SELECT IFNULL(NULL,1,2)", "wrong number of arguments to function IFNULL()"),
        ("SELECT COALESCE(1,missing)", "Column missing does not exist."),
    ):
        yield {"sql": sql, "declarations": [DECLARATION]}, "analysis-rejected", error


def descendants(value):
    yield value
    kind = value[0]
    if kind == "coalesce":
        for argument in value[1]:
            yield from descendants(argument)
    elif kind in ("comparison", "collate"):
        for operand in value[2:]:
            yield from descendants(operand)
    elif kind == "pattern":
        for operand in value[3:]:
            if operand is not None:
                yield from descendants(operand)


def footprint(item):
    roots = [value for value, _ in item["columns"]]
    if item["where"] is not None:
        roots.append(item["where"])
    labels = {}
    for root in roots:
        for value in descendants(root):
            if value[0] == "parameter":
                labels.setdefault(str(int(value[1])), "?" + value[1])
    return labels


class InvalidEscape(Exception):
    """The selected pattern expression has an invalid escape length."""


def evaluate(value, row, bindings):
    kind = value[0]
    if kind == "coalesce":
        for argument in value[1]:
            resolved = evaluate(argument, row, bindings)
            if resolved is not None:
                return resolved
        return None
    if kind == "comparison":
        left, right = [evaluate(operand, row, bindings) for operand in value[2:]]
        if value[1] in ("IS", "IS NOT"):
            same = left is right if left is None or right is None else left == right
            return int(same if value[1] == "IS" else not same)
        assert value[1] == "=" and type(left) is str and type(right) is str
        collation = next((node[1] for node in descendants(value) if node[0] == "collate"), "BINARY")
        assert collation in ("BINARY", "NOCASE")
        if collation == "NOCASE":
            # The collation control uses ASCII text only.
            assert left.isascii() and right.isascii()
            left, right = left.lower(), right.lower()
        return int(left == right)
    if kind == "collate":
        return evaluate(value[2], row, bindings)
    if kind == "pattern":
        assert value == invalid_escape()
        raise InvalidEscape
    if kind == "real":
        assert value == ["real", "1", "25", None, False]
        return 1.25
    if kind == "blob":
        return bytes.fromhex(value[1])
    return common(value, row, bindings)


def expected(item):
    bindings, rows, error = item["bindings"], [], None
    try:
        for row in [()] if item["source"] is None else [(record,) for record in DATA]:
            if item["where"] is None or evaluate(item["where"], row, bindings):
                rows.append([typed(evaluate(value, row, bindings)) for value, _ in item["columns"]])
    except InvalidEscape:
        error = ESCAPE_ERROR
    names = footprint(item)
    return {"names": [name for _, name in item["columns"]], "rows": sorted(rows), "parameterCount": max(map(int, names), default=0),
            "parameters": names, "readOnly": True, "changes": 0, "evaluationError": error}
