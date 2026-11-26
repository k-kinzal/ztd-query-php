"""Independent SQLite COUNT, builtin collation and group representative rules."""

from itertools import product
from random import Random

from evidence_transcript import canonical
from run import require
from run_sqlite_scalars import evaluate
from sqlite_builtin_collations import collation_key
from sqlite_engine import typed
from sqlite_relational_cases import children, truth
from sqlite_result_cases import integer

COLLATIONS = ("BINARY", "NOCASE", "RTRIM")
DATA = ((1, "A"), (1.0, "a"), ("A", "be "), ("a", "be"), ("a ", None),
        ("x\x00a", "a"), ("X\x00B", "a"), ("X\x00", "A "), (b"A", "A"),
        (None, "A"), (None, None), (2147483647, "é"), (2147483647.0, "É"))
SCHEMA = tuple(f"CREATE TABLE main.{name} (a COLLATE NOCASE, b COLLATE RTRIM)"
               for name in ("ir_group_bag", "ir_group_empty"))
CUSTOM = "CREATE TABLE main.ir_group_custom(a COLLATE unknown_profile)"


def sql_literal(value):
    if isinstance(value, str):
        return "CAST(X'" + value.encode().hex() + "' AS TEXT)"
    if isinstance(value, bytes):
        return "X'" + value.hex() + "'"
    return "NULL" if value is None else str(value)


FIXTURE = ";".join((*SCHEMA, "INSERT INTO main.ir_group_bag VALUES " +
                    ",".join("(" + ",".join(map(sql_literal, row)) + ")" for row in DATA)))


def source(role, names=("a", "b")):
    return ["main", "ir_group_" + role, list(names)]


def slot(index):
    return ["column", index]


def count(argument=None, collation=None):
    return ["count", argument, collation]


def case(relation, keys, columns, where=None):
    return {"operation": "grouped", "source": relation, "keys": keys, "columns": columns, "where": where}


def finite_cases():
    for role, key, measure, where in product(("bag", "empty"), (None, *COLLATIONS), [count(), count(slot(1)), *(count(slot(1), name) for name in COLLATIONS)],
                                             (None, integer(0))):
        keys = [] if key is None else [[slot(0), key]]
        columns = [] if key is None else [[slot(0), "k"]]
        yield case(source(role), keys, [*columns, [measure, "n"]], where)


def finite_id(response):
    _, role, _ = response["source"]
    coll = None if not response["keys"] else response["keys"][0][1]
    _, argument, distinct = response["columns"][-1][0]
    measure = "row" if argument is None else distinct or "field"
    return role, coll or "global", measure, "false" if response["where"] is not None else "all"


def finite_ids():
    return set(product(("ir_group_bag", "ir_group_empty"), ("global", *COLLATIONS), ("row", "field", *COLLATIONS), ("all", "false")))


def seeded(seed, maximum):
    rng = Random(seed)
    for _ in range(maximum):
        role = rng.choice(("bag", "bag", "empty", "unit"))
        keys = [[slot(rng.randrange(2)), rng.choice(COLLATIONS)] for _ in range(rng.randrange(5))] if role != "unit" else []
        columns = []
        for index in range(rng.randint(1, 7)):
            argument = None if role == "unit" or rng.randrange(4) == 0 else slot(rng.randrange(2))
            measure = count(argument, rng.choice((None, *COLLATIONS)) if argument is not None else None)
            expression = rng.choice(keys)[0] if keys and rng.randrange(3) == 0 else measure
            columns.append([expression, rng.choice(("key", "n", f"r{index}"))])
        where = rng.choice((None, integer(0), integer(-3), ["null"]))
        yield case(None if role == "unit" else source(role), keys, columns, where)


def boundaries():
    yield case(None, [], [[count(), "n"]])
    yield case(source("bag"), [[slot(0), "BINARY"]] * 16, [[count(), "n"]])
    yield case(None, [], [[count(), "n"]] * 1024)


def analyzed():
    yield "SELECT count(*) AS n", case(None, [], [[count(), "n"]])
    yield ("SELECT a AS k,count(DISTINCT b) AS n FROM main.ir_group_bag GROUP BY a",
           case(source("bag"), [[slot(0), "NOCASE"]], [[slot(0), "k"], [count(slot(1), "RTRIM"), "n"]]))
    yield ("SELECT b AS k,count(DISTINCT (a COLLATE RTRIM)) AS n FROM main.ir_group_bag GROUP BY b COLLATE BINARY",
           case(source("bag", ("b", "a")), [[slot(0), "BINARY"]], [[slot(0), "k"], [count(slot(1), "RTRIM"), "n"]]))
    yield ("SELECT a AS k,count(*) AS n FROM main.ir_group_empty WHERE NULL GROUP BY a",
           case(source("empty", ("a",)), [[slot(0), "NOCASE"]], [[slot(0), "k"], [count(), "n"]], ["null"]))


def negatives():
    reference = [[count(), "n"]]
    yield case(None, [], [[count(None, "BINARY"), "n"]]), "A distinct count requires a field argument."
    yield case(source("bag"), [], [[count(slot(2)), "n"]]), "A count slot must belong to the group input."
    yield case(source("bag"), [[slot(0), "BINARY"]], [[slot(1), "v"]]), "must read a grouping key"
    yield case(None, [[slot(0), "BINARY"]], reference), "must belong to its input"
    yield case(source("bag"), [[["column", 0, 1], "BINARY"]], reference), "must read the single input occurrence"
    yield {"sql": "SELECT a AS k,count(*) FROM main.ir_group_custom GROUP BY a", "declarations": [CUSTOM]}, "requires a resolved builtin collation"
    yield {"sql": "SELECT count(*) FILTER (WHERE 1) AS n FROM main.ir_group_bag", "declarations": list(SCHEMA)}, "requires builtin COUNT without a window, filter or ordering"
    yield case(source("bag"), [[slot(0), "BINARY"]] * 17, reference), "exceeds the SQLite group profile"
    yield case(None, [], reference * 1025), "exceeds the SQLite group profile"


def key_value(value, collation):
    if isinstance(value, (int, float)):
        return "number", value
    if isinstance(value, str):
        return "text", collation_key(value, collation)
    return ("null",) if value is None else ("blob", value)


def projected_count(value, rows):
    _, arg, distinct = value
    if arg is None:
        return len(rows)
    cells = [row[arg[1]] for row in rows if row[arg[1]] is not None]
    return len(cells) if distinct is None else len({key_value(cell, distinct) for cell in cells})


def expected(item):
    relation, where = item["source"], item["where"]
    bindings = item.get("bindings", {})
    having = item.get("having")
    if relation is None:
        entries = [()]
    elif relation[1] == "ir_group_empty":
        entries = []
    else:
        positions = ["ab".index(name) for name in relation[2]]
        entries = [tuple(row[position] for position in positions) for row in DATA]
    if where is not None:
        entries = [row for row in entries if truth(evaluate(where, bindings, row)) is True]
    keys = item["keys"]
    groups = {} if keys else {(): []}
    for row in entries:
        group = tuple(key_value(row[column[1]], collation) for column, collation in keys)
        groups.setdefault(group, []).append(row)
    output = []
    for members in groups.values():
        if having is not None:
            measure, operator, threshold, threshold_first = having
            count_term = integer(projected_count(measure, members))
            left, right = (threshold, count_term) if threshold_first else (count_term, threshold)
            if truth(evaluate(["comparison", operator, left, right], bindings)) is not True:
                continue
        measured = [projected_count(value, members) if value[0] == "count" else None for value, _ in item["columns"]]
        reps = members or [()]
        output.append([[typed(rep[value[1]] if value[0] == "column" else fixed)
                        for (value, _), fixed in zip(item["columns"], measured)] for rep in reps])
    parameters = binding_names(item)
    metadata = {"names": [label for _, label in item["columns"]], "parameters": parameters,
                "parameterCount": max(map(int, parameters), default=0), "readOnly": True, "changes": 0}
    return metadata, output


def binding_names(item):
    parameters = {}
    having = item.get("having")
    for root in (item["where"], None if having is None else having[2]):
        for member in children(root):
            if member[0] == "parameter":
                parameters.setdefault(str(int(member[1])), "?" + member[1])
    return parameters


def matches(choices, observed):
    if len(choices) != len(observed):
        return False
    assignments = {}
    options = [{canonical(row) for row in group} for group in choices]

    def place(row, seen):
        for key, allowed in enumerate(options):
            if key in seen or row not in allowed:
                continue
            seen.add(key)
            if key not in assignments or place(assignments[key], seen):
                assignments[key] = row
                return True
        return False

    return all(place(canonical(row), set()) for row in observed)


def compare(item, actual, sql):
    metadata, choices = expected(item)
    witness = {"sql": sql, "expected": {"metadata": metadata, "representatives": choices}, "observed": actual}
    require(all(actual.get(key) == value for key, value in metadata.items()), "meaning-mismatch", witness)
    require(matches(choices, actual["rows"]), "meaning-mismatch", witness)
