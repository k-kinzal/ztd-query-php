"""Define and observe SQLite wildcard expansion with typed self-join rows."""

from itertools import product
from random import Random

from run import require
from server_wildcard_cases import PROJECTIONS, RELATIONS, written_form
from sqlite_relational_cases import DECLARATIONS, SCHEMA, case, expected

FIELDS = ("key", "value")


def physical(table, columns):
    return ["main", table, columns]


def shape(relation, projection, reverse=False, where=None, join_operator="LEFT"):
    wide = relation == "self-join"
    sides = ("a", "b") if wide else ("a",)
    focus = sides[-1] if not reverse else sides[0]
    table = "ir_empty" if relation == "empty" else "ir_left"
    allocations = {side: [] for side in sides}

    def position(side, field):
        layout = allocations[side]
        if field not in layout:
            layout.append(field)
        return ["column", layout.index(field), *([1] if side == "b" else [])]

    if projection == "unqualified":
        written = "*"
        fields = [(side, field, field) for side in sides for field in FIELDS]
    elif projection == "qualified":
        written = f"{focus}.*"
        fields = [(focus, field, field) for field in FIELDS]
    elif projection == "mixed":
        written = f"a.value AS chosen, {focus}.*, a.key AS tail"
        fields = [("a", "value", "chosen"), *((focus, field, field) for field in FIELDS), ("a", "key", "tail")]
    elif projection == "repeated":
        written = f"{focus}.*, a.*, {focus}.*"
        fields = [(side, field, field) for side in (focus, "a", focus) for field in FIELDS]
    else:
        raise ValueError(projection)
    columns = [[position(side, field), alias] for side, field, alias in fields]
    if where is None:
        predicate, condition = None, ""
    else:
        side, field, value = where
        predicate = ["comparison", ">", position(side, field), ["integer", str(value)]]
        condition = f" WHERE {side}.{field} > {value}"
    table_sql = f"main.{table} AS a"
    joins = []
    if wide:
        on = ["comparison", "<", position("a", "key"), position("b", "key")]
        table_sql += f" {join_operator} JOIN main.{table} AS b ON a.key < b.key"
        joins.append([join_operator, physical(table, allocations["b"]), on])
    sql = f"SELECT {written} FROM {table_sql}{condition}"
    item = case(physical(table, allocations["a"]), joins, predicate, columns=columns)
    return {"sql": sql, "declarations": DECLARATIONS, "bindings": {}}, item


def finite_cases():
    for relation, projection in product(RELATIONS, PROJECTIONS):
        yield shape(relation, projection)


def finite_id(actual, before):
    relation = "self-join" if actual["joins"] else "empty" if actual["source"][1] == "ir_empty" else "ordinary"
    return relation, written_form(before["sql"])


def finite_ids(specification):
    space = specification["finite"]
    return set(product(space["relations"], space["projections"]))


def seeded(seed, maximum):
    rng = Random(seed)
    for _ in range(maximum):
        relation = rng.choice(RELATIONS)
        predicate = None
        if rng.randrange(3) == 0:
            predicate = rng.choice(("a","b") if relation == "self-join" else ("a",)), rng.choice(FIELDS), rng.randrange(-5, 9)
        yield shape(relation, rng.choice(PROJECTIONS), bool(rng.getrandbits(1)), predicate, rng.choice(("LEFT","RIGHT","INNER")))


def analyzed():
    proof = case(physical("ir_left", list(FIELDS)), columns=[
        [["column", 0], "key"], [["column", 1], "value"], [["parameter", "02"], "p"], [["column", 1], "tail"]
    ], bindings={"2": 17})
    yield {"sql": "SELECT a.*, ?02 AS p, a.value AS tail FROM main.ir_left AS a", "declarations": DECLARATIONS,
           "bindings": proof["bindings"]}, proof


def negatives():
    yield {"sql": "SELECT * FROM unregistered_source", "declarations": DECLARATIONS}, "analysis-rejected", "unregistered_source"
    yield {"sql": "SELECT absent.* FROM ir_left", "declarations": DECLARATIONS}, "analysis-rejected", "absent"
    yield {"sql": "SELECT * FROM (SELECT key, value FROM ir_left) AS derived", "declarations": DECLARATIONS}, \
        "rejected", "must be an ordinary table input"
