"""Compare normalized RIGHT merges with independent right-side row and heading rules."""

import random

from run import Failure, Worker, compare, require
from run_sqlite_merged_join import DATA, DECLARATIONS, FIXTURE, HEADINGS, LAYOUTS, case, equal, field_names, structure
from sqlite_domain import RenderFault, check_render_faults, run_domain
from sqlite_engine import typed
from sqlite_scalar_schedule import ScalarSchedule, execute_schedule

CONTRACT_NAME = "sqlite-merged-right-join-v2.json"
CONTRACT = "1c90e1140ed5d14681bed8162eecaf3c64e89a97256f9bf7c8f2b1b05f7654ae"
INPUTS = ("merge_left", "merge_right")


def merged_fields(sources, keys):
    return [(1 if index == 0 and name.lower() in keys else index, name, label)
            for index, name, label in field_names(sources, keys, False)]


def layout(keys, orientation, projection):
    sources = INPUTS if orientation == "original" else INPUTS[::-1]
    keys = LAYOUTS[keys]
    fields = {
        "visible": merged_fields(sources, keys),
        "qualified": [*field_names(sources, keys, True), (0, HEADINGS[sources[0]][0], "original")],
        "representative": [(1, name, "representative") for name in ("key", "lane")],
    }
    return case("RIGHT", sources, keys, fields[projection])



def with_filter(query, name, text):
    slot = tuple(map(str.lower, query["joins"][0][1][2])).index(name)
    return {**query, "where": ["comparison", "=", ["column", slot, 1], ["text", text]]}


def analyzed_cases():
    yield "SELECT ir_left.key FROM ir_left RIGHT JOIN ir_right USING (key)", case(
        "RIGHT", ("ir_left", "ir_right"), ("key",), [(0, "key", "key")])
    keys = ("lane", "key")
    yield "SELECT * FROM merge_left NATURAL RIGHT JOIN merge_right", case(
        "RIGHT", INPUTS, keys, merged_fields(INPUTS, keys))
    qualified_star = [(1 if index == 0 and name.lower() in keys else index, name, label)
                      for index, name, label in field_names(INPUTS, keys, True)]
    yield "SELECT a.*, b.* FROM merge_left a NATURAL RIGHT JOIN merge_right b", case(
        "RIGHT", INPUTS, keys, qualified_star)
    yield "SELECT KEY, key, (Key), a.Key,b.key,lane,a.lane,b.lane FROM merge_left a RIGHT JOIN merge_right b USING (Key,lane)", case(
        "RIGHT", INPUTS, keys[::-1], [
            (1, "key", "key"), (1, "key", "key"), (1, "key", "key"), (0, "Key", "Key"),
            (1, "key", "key"), (1, "lane", "lane"), (0, "lane", "lane"), (1, "lane", "lane"),
        ])
    swapped = INPUTS[::-1]
    yield "SELECT * FROM merge_right NATURAL RIGHT JOIN merge_left", case(
        "RIGHT", swapped, keys[::-1], merged_fields(swapped, keys))
    free = ("merge_left", "merge_free")
    yield "SELECT * FROM merge_left NATURAL RIGHT JOIN merge_free", case("RIGHT", free, (), merged_fields(free, ()))
    base = case("RIGHT", INPUTS, keys[::-1], [(1, "key", "key"), (1, "lane", "lane")])
    yield "SELECT Key,lane FROM merge_left RIGHT JOIN merge_right USING (Key,lane) WHERE Key = 'alpha'", with_filter(base, "key", "alpha")
    yield "SELECT Key,lane FROM merge_left RIGHT JOIN merge_right USING (Key,lane) WHERE lane = '02'", with_filter(base, "lane", "02")


def requests(contract):
    for index, dimensions in enumerate(sorted(finite_ids(contract["finite"]))):
        query = layout(*dimensions)
        yield f"finite/{index}", query, query
    for index, (sql, query) in enumerate(analyzed_cases()):
        yield f"analyze/{index}", {"sql": sql, "declarations": DECLARATIONS}, query
    for seed in contract["exploration"]["seeds"]:
        sample = random.Random(seed)
        for index in range(contract["exploration"]["casesPerSeed"]):
            sources = INPUTS if sample.getrandbits(1) else INPUTS[::-1]
            keys = sample.choices(("lane", "key"), k=sample.randrange(4))
            candidates = field_names(sources, keys, True)
            fields = [(*field[:2], sample.choice((field[2], 'alias " q', "α", "")))
                      for field in sample.choices(candidates, k=sample.randrange(1, 8))]
            query = case("RIGHT", sources, keys, fields)
            yield f"seed/{seed}/{index}", query, query


def negatives():
    invalid = layout("key", "original", "visible")
    invalid["joins"][0][2][-1] = ["column", 40, 1]
    yield invalid, "rejected", "visible occurrence"
    clauses = (
        ("merge_left JOIN merge_free JOIN merge_right RIGHT JOIN ir_empty USING (key)", "rejected", "merged prefix requires"),
        ("merge_left NATURAL RIGHT JOIN local", "rejected", "requires complete base-table headings"),
        ("merge_left RIGHT JOIN merge_right USING (absent)", "analysis-rejected", "absent"),
    )
    for source, status, reason in clauses:
        prefix = "WITH local(key) AS (VALUES(4)) " if source.endswith("JOIN local") else ""
        yield {"sql": prefix + "SELECT merge_left.Key FROM " + source, "declarations": DECLARATIONS}, status, reason


def finite_ids(domain):
    return {(keys, orientation, projection) for keys in domain["keyLayouts"] for orientation in domain["orientation"]
            for projection in domain["projections"]}


def finite_id(query, _):
    _, sources, keys, fields = structure(query)
    layout_key_names = tuple(pair[0][1].lower() for pair in keys)
    label = next(name for name, values in LAYOUTS.items() if values == layout_key_names)
    orientation = "original" if sources[0][1] == INPUTS[0] else "swapped"
    projection = "qualified" if len(fields) == 7 else "representative" if len(fields) == 2 else "visible"
    return label, orientation, projection


def where_item(query):
    expression = query.get("where")
    if expression is None:
        return None
    require(expression[:2] == ["comparison", "="] and expression[2][0] == "column"
            and expression[3][0] == "text", "merged-right-fixture-filter", expression)
    address, label = expression[2], expression[3][1]
    occurrence = address[2] if len(address) == 3 else 0
    relation = query["source"] if occurrence == 0 else query["joins"][0][1]
    return occurrence, relation[2][address[1]].lower(), label


def passes_filter(rows, filter_):
    if filter_ is None:
        return True
    occurrence, field, label = filter_
    value = rows[occurrence].get(field)
    return value == (int(label) if field == "lane" else label)


def expected(query):
    _, ((_, left), (_, right)), keys, fields = structure(query)
    require(all(a == 0 and b == 1 and first.lower() == second.lower() for (a, first), (b, second) in keys),
            "merged-right-fixture-key-pairs", keys)
    names = tuple((first.lower(), second.lower()) for (_, first), (_, second) in keys)
    criteria = where_item(query)
    rows = []
    for right_row in DATA[right]:
        matched = [row for row in DATA[left]
                   if all(equal(left, row[a], right, right_row[b], a) for a, b in names)]
        for row in matched or [{}]:
            scoped = row, right_row
            if passes_filter(scoped, criteria):
                rows.append([typed(scoped[index].get(field.lower())) for index, field, _ in fields])
    return {"names": [label for _, _, label in fields], "rows": sorted(rows), "parameterCount": 0,
            "parameters": {}, "readOnly": True, "changes": 0}


def probe(query, response, db, ledger=None, key=None, original=None):
    require(response["status"] == "constructed", "merged-right-positive-rejected", response)
    require(structure(response) == structure(query) and where_item(response) == where_item(query)
            and response["orderBy"] == [] and not response["distinct"], "merged-right-graph-differs", response)
    if ledger is not None:
        ledger.record(key, "constructed", response=response)
    observations = {"observation": db.observe(response["sql"], {})}
    if original is not None:
        observations["original"] = db.observe(original, {})
    if ledger is not None:
        ledger.record(key, "observed", **observations)
    measurement = expected(query)
    for actual in observations.values():
        compare(measurement, actual)
    if ledger is not None:
        ledger.record(key, "compared")
    return observations["observation"]


def forms(query):
    kind, names, keys, fields = structure(query)
    yield f"kind/{kind}"
    yield f"left/{names[0][1]}"
    yield f"keyCount/{len(keys)}"
    for occurrence, _, _ in fields:
        yield f"reference/{occurrence}"
    if len(keys) != len(set(keys)):
        yield "repeated-key"
    if any('"' in label for _, _, label in fields):
        yield "quoted-label"


def required(_):
    return {"kind/RIGHT", "left/merge_left", "left/merge_right", "reference/0", "reference/1", "repeated-key", "quoted-label",
            *(f"keyCount/{count}" for count in range(4))}


def controls():
    correct = layout("both", "original", "representative")
    wrong = case("RIGHT", INPUTS, LAYOUTS["both"], [(0, name, "representative") for name in ("key", "lane")])
    prediction, mismatch = expected(correct), expected(wrong)
    compare(prediction, prediction)
    try:
        compare(prediction, mismatch)
    except Failure as failure:
        require(failure.signal == "meaning-mismatch", "wrong-merged-right-control-signal", failure.detail)
    else:
        raise Failure("merged-right-comparator-accepted", mismatch)
    return {"healthy": prediction, "wrongOrigin": mismatch}


def execute(args, contract, db, worker_factory=Worker):
    require(len(list(analyzed_cases())) == len(contract["analysis"]), "incomplete-merged-right-analysis")
    schedule = ScalarSchedule(requests, negatives, finite_id, finite_ids, forms, required, controls)
    return execute_schedule(args, contract, db, worker_factory, schedule, probe)


def check_faults(args, db, runner=check_render_faults):
    target = "sql-semantics-sqlite/src/Ir/SelectSql.php"
    shape = layout("both", "original", "qualified")
    source = "$this->expression($column->expression, $source, $select->joins)"
    predicate = "$this->expression($join->on, $source, $select->joins)"
    return runner(args, db, [
        RenderFault("read-left-merged-cell", target, source,
                    "$this->expression(new ColumnReference($column->expression->slot, 0), $source, $select->joins)",
                    layout("both", "original", "representative"), "meaning-mismatch"),
        RenderFault("use-left-join-operation", target, "$join->kind->value . ' JOIN '", "'LEFT' . ' JOIN '", shape, "meaning-mismatch"),
        RenderFault("drop-explicit-right-field", target, "foreach ($select->columns as $column)",
                    "foreach (array_slice($select->columns, 0, -1) as $column)", shape, "meaning-mismatch"),
        RenderFault("drop-second-right-key", target, predicate,
                    "$this->expression($join->on->left, $source, $select->joins)", shape, "meaning-mismatch"),
    ], probe)


if __name__ == "__main__":
    run_domain("merged-right-join", CONTRACT_NAME, CONTRACT, FIXTURE, execute, check_faults)
