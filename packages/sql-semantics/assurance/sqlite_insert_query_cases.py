"""Project the independent row source and apply the checked SQLite write assignment."""

from functools import cmp_to_key
from itertools import product
from random import Random

from run import require
from run_sqlite_delete import typed_rows
from run_sqlite_insert import DECLARATIONS as WRITE_DECLARATIONS, FIXTURE as WRITE_FIXTURE, MAIN, TEMP, expected_assignment
from sqlite_relational_cases import DECLARATIONS as READ_DECLARATIONS, SCHEMA, EMPTY, LEFT, RIGHT, case as select
from sqlite_relational_cases import expected as rows_from_source, untyped
from sqlite_result_cases import bound_value, integer, order_comparison, representatives, result_parameters, row_identity

FIXTURE = SCHEMA + WRITE_FIXTURE
DECLARATIONS = [*WRITE_DECLARATIONS, *READ_DECLARATIONS]
LAYOUTS = (("id",), ("payload",), ("id", "payload"), ("payload", "id"))
SOURCES = (None, LEFT, EMPTY)
REJECT_ALL = integer(0)
BASELINE_STATE = {"main": typed_rows(MAIN), "temp": typed_rows(TEMP), "log": []}


def column(index, occurrence=0):
    return ["column", index, *([occurrence] if occurrence else [])]


def case(query, target=("id", "payload"), schema="main", conflict="inherit"):
    return {"operation": "insert", "target": [schema, "ir_insert", list(target)], "conflict": conflict,
            "query": query, "bindings": query["bindings"]}


def query(source, columns, where=None, distinct=False, order=(), limit=None, join=(), bindings=None):
    return {**select(source, join, where, order, columns, bindings), "operation": "select",
            "distinct": distinct, "limit": limit, "local": None}


def finite_cases():
    for source, target, where, distinct in product(SOURCES, LAYOUTS, (None, REJECT_ALL), (False, True)):
        expressions = [integer(7), ["null"]] if source is None else [column(0), column(1)]
        columns = [[expr, f"p{index}"] for index, expr in enumerate(expressions[:len(target)])]
        orders = [[position, False, True] for position in range(1, len(target) + 1)]
        yield case(query(source, columns, where, distinct, orders), target)


def finite_id(reply):
    query_ = reply["query"]
    return SOURCES.index(query_["source"]), LAYOUTS.index(tuple(reply["target"][2])), query_["where"] is not None, query_["distinct"]


def finite_ids(spec):
    domain = spec["finite"]
    return set(product(range(len(domain["sources"])), range(len(domain["targetLayouts"])), (False, True), domain["distinct"]))


def seeded(seed, count):
    rng = Random(seed)
    for _ in range(count):
        relation = rng.choice(SOURCES)
        target = rng.choice(LAYOUTS)
        atoms = [integer(rng.randrange(-5, 10)), ["null"], ["text", "text"], ["parameter", "02"]]
        if relation is not None:
            atoms.extend((column(0), column(1)))
        fields = [[rng.choice(atoms), f"p{index}"] for index in range(len(target))]
        where_choices = [None, REJECT_ALL, integer(1), ["null"]]
        if relation is not None:
            where_choices.append(["comparison", "IS NOT", column(rng.randrange(2)), ["null"]])
        where = rng.choice(where_choices)
        ordering = [[slot, bool(rng.getrandbits(1)), bool(rng.getrandbits(1))] for slot in range(1, len(target) + 1)]
        if relation is None:
            ordering = rng.choice(([], ordering))
        bounded = None
        if rng.getrandbits(1):
            bounded = [integer(rng.randrange(0, 5)), rng.choice((None, integer(1))), False]
        bindings = {"2": rng.choice((None, -2, 3.0, "3", "text"))} if any(value[0] == "parameter" for value, _ in fields) else {}
        source = query(relation, fields, where, bool(rng.getrandbits(1)), ordering, bounded, bindings=bindings)
        yield case(source, target, rng.choice(("main", "temp")), rng.choice(("inherit", "abort", "ignore")))


def analyzed():
    raw = "INSERT OR ABORT INTO main.ir_insert (id,payload) SELECT a.key AS p0, a.value AS p1 FROM main.ir_left AS a ORDER BY 1,2"
    yield raw, case(query(LEFT, [[column(0), "p0"], [column(1), "p1"]], order=[[1,False,True],[2,False,True]]), conflict="abort")
    raw = "INSERT OR IGNORE INTO ir_insert (payload,id) SELECT ?02 AS p0, 8 AS p1 WHERE 1 LIMIT 1"
    yield raw, case(query(None, [[["parameter", "02"],"p0"], [integer(8),"p1"]], integer(1),
                           limit=[integer(1),None,False], bindings={"2": "3"}), ("payload","id"), "temp", "ignore")
    raw = ("INSERT INTO main.ir_insert (id,payload) SELECT a.key AS p0, b.value AS p1 "
           "FROM main.ir_left AS a LEFT JOIN main.ir_right AS b ON a.key = b.key WHERE a.key > 0 ORDER BY 1,2 LIMIT 4")
    left = ["main","ir_left",["key"]]
    right = ["main","ir_right",["value","key"]]
    fields = [[column(0),"p0"],[column(0,1),"p1"]]
    yield raw, case(query(left, fields, ["comparison",">",column(0),integer(0)], order=[[1,False,True],[2,False,True]],
                          limit=[integer(4),None,False], join=[["LEFT",right,["comparison","=",column(0),column(1,1)]]]))


def negatives():
    projection = query(None, [[integer(7),"p0"]])
    yield case(projection, ()), "rejected", "at least one target column"
    yield case(projection), "rejected", "target width must match the input rows"
    yield {"sql":"INSERT INTO main.ir_insert (unknown_column) SELECT 7 AS p0","declarations":DECLARATIONS}, \
        "analysis-rejected", "Column unknown_column does not exist"
    yield {"sql":"INSERT INTO main.ir_insert (computed) SELECT 7 AS p0","declarations":DECLARATIONS}, \
        "analysis-rejected", "cannot INSERT into generated column"
    nested = {"names":["n"],"rows":[[integer(7)]]}
    inner = {**query(None, [[column(0),"p0"]]),"local":nested}
    yield case(inner,("id",)), "rejected", "without a local definition"


def expected(item, omitted_id=None, after_main=None):
    source = item["query"]
    baseline = rows_from_source(source)
    measured = baseline["rows"]
    groups = representatives(measured) if source["distinct"] else [[row] for row in measured]
    require(all(len(set(map(row_identity, group))) == 1 for group in groups), "write-fixture-ambiguous-distinct-type", item)
    selected = [group[0] for group in groups]
    ordering = lambda a,b: order_comparison(a,b,source["orderBy"])
    selected.sort(key=cmp_to_key(ordering))
    require(all(ordering(a,b) != 0 or a == b for a,b in zip(selected,selected[1:])), "write-fixture-ambiguous-row-order", item)
    if source["limit"] is not None:
        count, offset, _ = source["limit"]
        count = bound_value(count, source["bindings"])
        offset = max(0,bound_value(offset,source["bindings"])) if offset is not None else 0
        selected = selected[offset:] if count < 0 else selected[offset:offset+count]
    effect = expected_assignment(item, ([untyped(value) for value in row] for row in selected), result_parameters(source), omitted_id, after_main)
    return {**effect, "restored": BASELINE_STATE}


def shape(item):
    return {key: value for key, value in item["query"].items() if key not in ("bindings", "local")}
