"""Check left-associated SQLite merge predicates and observable prefix identity."""

from itertools import product
import random

from run import Failure, Worker, compare, require
from run_sqlite_merged_join import DATA as BASE_ROWS, DECLARATIONS as BASE_DECLARATIONS, FIXTURE as BASE_FIXTURE, HEADINGS as BASE_HEADINGS, equal
from run_sqlite_unique_keys import values_sql
from sqlite_domain import RenderFault, check_render_faults, run_domain
from sqlite_engine import typed
from sqlite_scalar_schedule import ScalarSchedule, execute_schedule

CONTRACT_NAME = "sqlite-merged-prefix-join-v1.json"
CONTRACT = "00a4e5e70f376fd54722298ee21a0d403d5b7a68d52d11f78e1d077027f166ef"
KINDS = ("INNER", "CROSS", "LEFT")
LEFT, MIDDLE, TAIL, GAP = "prefix_left", "prefix_middle", "prefix_tail", "prefix_gap"
HEADINGS = {
    LEFT: ("key", "other", "left_value"),
    MIDDLE: ("key", "other", "mid", "mid_value"),
    TAIL: ("key", "mid", "tail_value"),
    GAP: ("gap",),
    **BASE_HEADINGS,
}
ROWS = {
    LEFT: [(1, 10, "L1"), (1, 10, "L1"), (2, None, "L2"), (99, 9, "L99"), (None, 10, "LN")],
    MIDDLE: [(1, 10, 101, "M1"), (2, 10, 102, "M2"), (4, 9, None, "M4"), (None, None, 150, "MN")],
    TAIL: [(1, 101, "T101"), (2, 102, "T102"), (1, 102, "Tmix"), (3, 150, "TN"), (99, 999, "T99"), (None, None, "TNULL")],
    GAP: [(13,), (None,)],
}
DDL = [f"CREATE TABLE {name} (" + ", ".join(f"{field} BLOB" for field in HEADINGS[name]) + ")" for name in ROWS]
DECLARATIONS = [*BASE_DECLARATIONS, *DDL]
FIXTURE = BASE_FIXTURE + "; " + "; ".join([*DDL, *(f"INSERT INTO {name} VALUES {values_sql(rows)}" for name, rows in ROWS.items())])
DATA = {**BASE_ROWS, **{source: [dict(zip(HEADINGS[source], row)) for row in rows] for source, rows in ROWS.items()}}


def reference(relations, location):
    occurrence, name = location
    slot = tuple(map(str.lower, HEADINGS[relations[occurrence]])).index(name.lower())
    return ["column", slot, occurrence] if occurrence else ["column", slot]


def case(relations, steps, fields):
    sources = [["main", name, list(HEADINGS[name])] for name in relations]
    joins = []
    for index, (kind, terms) in enumerate(steps, 1):
        predicate = None
        for left, right in terms:
            comparison = ["comparison", "=", reference(relations, left), reference(relations, right)]
            predicate = comparison if predicate is None else ["logical", "AND", predicate, comparison]
        joins.append([kind, sources[index], predicate])
    columns = [[reference(relations, (index, field)), label] for index, field, label in fields]
    return {"operation": "select", "source": sources[0], "joins": joins, "columns": columns}


def finite(first, second, key, second_source):
    return case((LEFT, MIDDLE, TAIL), [
        (first, [((0, key), (1, key))]), (second, [((second_source, "key"), (2, "key"))]),
    ], [(0, "key", "first"), (1, "key", "second"), (2, "tail_value", "last")])


def analyzed():
    old = ("merge_left", "merge_free", "merge_right")
    yield "SELECT merge_left.key FROM merge_left JOIN merge_free JOIN merge_right USING (key)", case(
        old, [("INNER", []), ("INNER", [((0, "key"), (2, "key"))])], [(0, "Key", "Key")])
    yield "SELECT p.key,g.gap,t.tail_value FROM prefix_left p CROSS JOIN prefix_gap g JOIN prefix_tail t USING (key)", case(
        (LEFT, GAP, TAIL), [("CROSS", []), ("INNER", [((0, "key"), (2, "key"))])],
        [(0, "key", "key"), (1, "gap", "gap"), (2, "tail_value", "tail_value")])
    sources = (LEFT, MIDDLE, TAIL)
    first_key = [((0, "key"), (1, "key"))]
    second_mid = [((1, "mid"), (2, "mid"))]
    middle_fields = [(0, "key", "key"), (1, "mid", "mid"), (2, "tail_value", "tail_value")]
    yield "SELECT p.key,mid,t.tail_value FROM prefix_left p JOIN prefix_middle m USING (key) JOIN prefix_tail t USING (mid)", case(
        sources, [("INNER", first_key), ("INNER", second_mid)], middle_fields)
    yield "SELECT p.key,m.key,t.tail_value FROM prefix_left p JOIN prefix_middle m JOIN prefix_tail t USING (key)", case(
        sources, [("INNER", []), ("INNER", [((0, "key"), (2, "key"))])],
        [(0, "key", "key"), (1, "key", "key"), (2, "tail_value", "tail_value")])
    yield "SELECT p.key,m.mid,t.tail_value FROM prefix_left p JOIN prefix_middle m USING (other) NATURAL JOIN prefix_tail t", case(
        sources, [("INNER", [((0, "other"), (1, "other"))]), ("INNER", [((0, "key"), (2, "key")), *second_mid])],
        middle_fields)
    yield "SELECT p.key,m.mid,t.tail_value FROM prefix_left p LEFT JOIN prefix_middle m USING (key) LEFT JOIN prefix_tail t USING (mid)", case(
        sources, [("LEFT", first_key), ("LEFT", second_mid)], middle_fields)
    qualified = [(index, name, name) for index, table in enumerate(sources) for name in HEADINGS[table]]
    visible = [field for field in qualified if field[:2] not in ((1, "key"), (2, "mid"))]
    yield "SELECT *,p.*,m.*,t.* FROM prefix_left p LEFT JOIN prefix_middle m USING (key) CROSS JOIN prefix_tail t USING (mid)", case(
        sources, [("LEFT", first_key), ("CROSS", second_mid)], [*visible, *qualified])


def requests(contract):
    domain = contract["finite"]
    for index, dimensions in enumerate(product(domain["first"], domain["second"], domain["firstKey"], domain["secondSource"])):
        item = finite(*dimensions)
        yield f"finite/{index}", item, item
    for index, (sql, item) in enumerate(analyzed()):
        yield f"analyze/{index}", {"sql": sql, "declarations": DECLARATIONS}, item
    for seed in contract["exploration"]["seeds"]:
        sample = random.Random(seed)
        for index in range(contract["exploration"]["casesPerSeed"]):
            sources = [sample.choice((LEFT, MIDDLE, TAIL, GAP)) for _ in range(sample.randint(3, 6))]
            slots = [[(position, name) for name in HEADINGS[table]] for position, table in enumerate(sources)]
            steps = []
            for position in range(1, len(sources)):
                prior = [slot for group in slots[:position] for slot in group]
                terms = [(sample.choice(prior), sample.choice(slots[position])) for _ in range(sample.randrange(3))]
                steps.append((sample.choice(KINDS), terms))
            fields = [(*slot, sample.choice(("", "α", 'field " name', slot[1])))
                      for slot in sample.choices([item for group in slots for item in group], k=sample.randrange(1, 8))]
            item = case(sources, steps, fields)
            yield f"seed/{seed}/{index}", item, item


def negatives():
    for operand in (["column", 0, 2], ["column", 41, 1]):
        wrong = finite("LEFT", "LEFT", "key", 1)
        wrong["joins"][0][2][-1] = operand
        yield wrong, "rejected", "visible occurrence"
    for operation in ("RIGHT", "FULL"):
        yield {"sql": f"SELECT p.key FROM prefix_left p JOIN prefix_gap g {operation} JOIN prefix_tail t USING (key)",
               "declarations": DECLARATIONS}, "rejected", "merged prefix requires"
    yield {"sql": "WITH c(key) AS (VALUES(4)) SELECT p.key FROM prefix_left p JOIN c USING (key) JOIN prefix_tail t USING (key)",
           "declarations": DECLARATIONS}, "rejected", "requires complete base-table headings"
    yield {"sql": "SELECT p.key FROM prefix_left p JOIN prefix_gap g JOIN prefix_tail t USING (mid)",
           "declarations": DECLARATIONS}, "analysis-rejected", "mid"


def graph(query):
    sources = [query["source"], *(step[1] for step in query["joins"])]

    def address(column):
        position = column[2] if len(column) == 3 else 0
        return position, sources[position][2][column[1]]

    def pairs(expression):
        if expression is None:
            return ()
        if expression[:2] == ["logical", "AND"]:
            return (*pairs(expression[2]), *pairs(expression[3]))
        require(expression[:2] == ["comparison", "="], "merged-prefix-predicate", expression)
        return ((address(expression[2]), address(expression[3])),)

    return (tuple(tuple(source[:2]) for source in sources),
            tuple((kind, pairs(operand)) for kind, _, operand in query["joins"]),
            tuple((address(expr), label) for expr, label in query["columns"]))


def finite_ids(domain):
    return {(first, second, key, source) for first in domain["first"] for second in domain["second"]
            for key in domain["firstKey"] for source in domain["secondSource"]}


def finite_id(reply, _):
    _, ((first, left_pairs), (second, right_pairs)), _ = graph(reply)
    return first, second, left_pairs[0][0][1], right_pairs[0][0][0]


def expected(item):
    sources, steps, fields = graph(item)
    rows = [(row,) for row in DATA[sources[0][1]]]
    for occurrence, (kind, pairs) in enumerate(steps, 1):
        earlier = rows
        rows = []
        for prefix in earlier:
            joined = [(*prefix, row) for row in DATA[sources[occurrence][1]]
                      if all(equal(sources[index][1], prefix[index].get(name.lower()), sources[occurrence][1],
                                   row.get(right.lower()), name.lower())
                             for (index, name), (_, right) in pairs)]
            rows.extend(joined or ([(*prefix, {})] if kind == "LEFT" else []))
    result = [[typed(row[index].get(field.lower())) for (index, field), _ in fields] for row in rows]
    return {"names": [label for _, label in fields], "rows": sorted(result), "parameterCount": 0,
            "parameters": {}, "readOnly": True, "changes": 0}


def probe(item, response, db, ledger=None, key=None, original=None):
    require(response["status"] == "constructed", "merged-prefix-positive-rejected", response)
    require(graph(response) == graph(item) and response["where"] is None and response["orderBy"] == [] and not response["distinct"],
            "merged-prefix-graph-differs", {"input": item, "observed": response})
    if ledger is not None:
        ledger.record(key, "constructed", response=response)
    results = {"observation": db.observe(response["sql"], {})}
    if original is not None:
        results["original"] = db.observe(original, {})
    if ledger is not None:
        ledger.record(key, "observed", **results)
    prediction = expected(item)
    for measured in results.values():
        compare(prediction, measured)
    if ledger is not None:
        ledger.record(key, "compared")
    return results["observation"]


def forms(query):
    sources, steps, fields = graph(query)
    yield f"steps/{len(steps)}"
    if len(set(sources)) < len(sources):
        yield "reused-identity"
    if len(fields) > len({field[0] for field in fields}):
        yield "repeated-slot"
    for idx, (kind, pairs) in enumerate(steps, 1):
        yield f"kind/{kind}"
        yield f"width/{len(pairs)}"
        for (slot, _), _ in pairs:
            if slot < idx - 1:
                yield "nonadjacent-key"


def required(_):
    return {"reused-identity", "repeated-slot", "nonadjacent-key", *(f"steps/{count}" for count in range(2, 6)),
            *(f"width/{count}" for count in range(3)), *(f"kind/{name}" for name in KINDS)}


def controls():
    correct = finite("LEFT", "LEFT", "other", 1)
    prediction = expected(correct)
    modified = expected(finite("LEFT", "LEFT", "other", 0))
    compare(prediction, prediction)
    try:
        compare(prediction, modified)
    except Failure as failure:
        require(failure.signal == "meaning-mismatch", "wrong-merged-prefix-comparator-control", failure.detail)
    else:
        raise Failure("merged-prefix-comparator-accepted", modified)
    return {"healthy": prediction, "wrongOccurrence": modified}


def execute(args, contract, db, worker_factory=Worker):
    require(len(list(analyzed())) == len(contract["analysis"]), "incomplete-merged-prefix-analysis")
    schedule = ScalarSchedule(requests, negatives, finite_id, finite_ids, forms, required, controls)
    return execute_schedule(args, contract, db, worker_factory, schedule, probe)


def check_faults(args, db, runner=check_render_faults):
    renderer = "sql-semantics-sqlite/src/Ir/SelectSql.php"
    normal = finite("LEFT", "LEFT", "other", 1)
    on = "$this->expression($join->on, $source, $select->joins)"
    changed = "($index === 1 ? new Comparison($join->on->operator, new ColumnReference($join->on->left->slot, 0), $join->on->right) : $join->on)"
    return runner(args, db, [
        RenderFault("wrong-prefix-key-occurrence", renderer, on,
                    f"$this->expression({changed}, $source, $select->joins)", normal, "meaning-mismatch"),
        RenderFault("remove-second-join-predicate", renderer, "if ($join->on !== null)",
                    "if ($join->on !== null && $index !== 1)", normal, "meaning-mismatch"),
        RenderFault("lose-first-left-preservation", renderer, "$join->kind->value . ' JOIN '",
                    "($index === 0 ? 'INNER' : $join->kind->value) . ' JOIN '", normal, "meaning-mismatch"),
        RenderFault("drop-prefix-result-operand", renderer, "foreach ($select->columns as $column)",
                    "foreach (array_slice($select->columns, 1) as $column)", normal, "meaning-mismatch"),
    ], probe)


if __name__ == "__main__":
    run_domain("merged-prefix-join", CONTRACT_NAME, CONTRACT, FIXTURE, execute, check_faults)
