"""Compare SQLite FULL merges to independently paired rows and view metadata."""

from itertools import product
import hashlib
import random

from run import Failure, Worker, compare, require
from run_sqlite_create_view import CreateViewDatabase, DDL_RESULT
from run_sqlite_merged_join import DATA, DECLARATIONS, FIXTURE, HEADINGS, LAYOUTS, case, equal, field_names, structure
from sqlite_domain import RenderFault, check_render_faults, run_domain
from sqlite_engine import typed
from sqlite_scalar_schedule import ScalarSchedule, execute_schedule

CONTRACT_NAME = "sqlite-merged-full-join-v1.json"
CONTRACT = "062375f0397ae9961c76709d17f5e5347a7fe3e0bebd9f65a74a93bd0e6a825d"
INPUTS = ("merge_left", "merge_right")
VIEW = "merged_output"


class FullMergeDatabase(CreateViewDatabase):
    """Authorize the bounded view fixture and the native FULL value expression."""

    policy_digest = hashlib.sha256(f"{CreateViewDatabase.policy_digest}:{CONTRACT}".encode()).hexdigest()

    def authorize(self, context, action, first, second, schema, origin):
        if (action, first, second, schema) == (31, None, b"coalesce", None) and origin in (None, VIEW.encode()):
            return 0
        return super().authorize(context, action, first, second, schema, origin)


def address(sources, occurrence, name):
    position = tuple(map(str.lower, HEADINGS[sources[occurrence]])).index(name.lower())
    return ["column", position, occurrence] if occurrence else ["column", position]


def selection(sources, occurrence, name):
    if occurrence == "merged":
        return ["coalesce", [address(sources, index, name) for index in (0, 1)]]
    return address(sources, occurrence, name)


def query(sources, keys, fields):
    base = case("FULL", sources, keys, [])
    return {**base, "columns": [[selection(sources, occurrence, name), label] for occurrence, name, label in fields]}


def star(sources, keys, qualified=False):
    return [("merged" if index == 0 and name.lower() in keys else index, name, label)
            for index, name, label in field_names(sources, keys, qualified)]


def layout(mask, orientation, projection):
    sources = INPUTS if orientation == "original" else INPUTS[::-1]
    keys = LAYOUTS[mask]
    fields = {
        "visible": star(sources, keys),
        "physical": [*field_names(sources, keys, True), (0, HEADINGS[sources[0]][0], "physical")],
        "combined": [("merged", name, "combined") for name in ("key", "lane")],
    }
    return query(sources, keys, fields[projection])


def with_filter(item, name, literal):
    return {**item, "where": ["comparison", "=", selection(INPUTS, "merged", name), ["text", literal]]}


def analyzed_cases():
    exact = [(0, "Key", "Key")]
    yield "SELECT merge_left.key FROM merge_left FULL JOIN merge_right USING (key)", query(INPUTS, ("key",), exact)
    yield "SELECT ir_left.key FROM ir_left NATURAL FULL JOIN ir_right", query(
        ("ir_left", "ir_right"), ("key", "value"), [(0, "key", "key")])
    keys = ("lane", "key")
    yield "SELECT merge_left.Key FROM merge_left NATURAL FULL JOIN merge_right", query(INPUTS, keys, exact)
    yield "SELECT *, a.*, b.* FROM merge_left a FULL JOIN merge_right b USING (lane,key)", query(
        INPUTS, keys, [*star(INPUTS, keys), *star(INPUTS, keys, True)])
    yield "SELECT KEY, (Key), key AS chosen,a.Key,b.key,lane,a.lane,b.lane FROM merge_left a FULL JOIN merge_right b USING (Key,lane)", query(
        INPUTS, keys[::-1], [("merged", "key", "KEY"), ("merged", "key", "(Key)"), ("merged", "key", "chosen"),
                            (0, "Key", "Key"), (1, "key", "key"), ("merged", "lane", "lane"),
                            (0, "lane", "lane"), (1, "lane", "lane")])
    base = query(INPUTS, keys[::-1], [("merged", "Key", "Key"), ("merged", "lane", "lane")])
    yield "SELECT Key,lane FROM merge_left FULL JOIN merge_right USING (Key,lane) WHERE Key = 'alpha'", with_filter(base, "key", "alpha")
    yield "SELECT Key,lane FROM merge_left FULL JOIN merge_right USING (Key,lane) WHERE lane = '02'", with_filter(base, "lane", "02")
    free = ("merge_left", "merge_free")
    yield "SELECT * FROM merge_left NATURAL FULL JOIN merge_free", query(free, (), star(free, ()))
    view = {"operation": "create-view", "schema": "main", "view": VIEW, "ifNotExists": False,
            "columns": ["Key", "lane", "left_value", "right_value"], "query": query(INPUTS, keys, star(INPUTS, keys))}
    yield f"CREATE VIEW {VIEW} AS SELECT * FROM merge_left NATURAL FULL JOIN merge_right", view


def requests(contract):
    domain = contract["finite"]
    for index, dimensions in enumerate(product(domain["keyLayouts"], domain["orientation"], domain["projections"])):
        item = layout(*dimensions)
        yield f"finite/{index}", item, item
    for index, (sql, item) in enumerate(analyzed_cases()):
        yield f"analyze/{index}", {"sql": sql, "declarations": DECLARATIONS}, item
    for seed in contract["exploration"]["seeds"]:
        sample = random.Random(seed)
        for index in range(contract["exploration"]["casesPerSeed"]):
            sources = INPUTS if sample.getrandbits(1) else INPUTS[::-1]
            keys = sample.choices(("lane", "key"), k=sample.randrange(4))
            candidates = [*field_names(sources, keys, True), ("merged", "key", "Key"), ("merged", "lane", "lane")]
            fields = [(*field[:2], sample.choice(("", "α", 'x " alias', field[2])))
                      for field in sample.choices(candidates, k=sample.randrange(1, 8))]
            item = query(sources, keys, fields)
            yield f"seed/{seed}/{index}", item, item


def negatives():
    missing_key = layout("key", "original", "visible")
    missing_key["joins"][0][2][-1] = ["column", 90, 1]
    yield missing_key, "rejected", "visible occurrence"
    wrong_output = layout("both", "original", "combined")
    wrong_output["columns"][0][0][1][-1] = ["column", 0, 2]
    yield wrong_output, "rejected", "visible occurrence"
    yield {"sql": "SELECT merge_left.Key FROM merge_left JOIN merge_free FULL JOIN merge_right USING(key)",
           "declarations": DECLARATIONS}, "rejected", "merged prefix requires"
    yield {"sql": "SELECT * FROM merge_left FULL JOIN merge_right USING(absent)",
           "declarations": DECLARATIONS}, "analysis-rejected", "absent"


def graph(query):
    kind, sources, keys, _ = structure({**query, "columns": []})
    relations = query["source"], query["joins"][0][1]

    def root(expression):
        if expression[0] == "coalesce":
            require(len(expression[1]) == 2, "merged-full-combine-arity", expression)
            return ("coalesce", *map(root, expression[1]))
        require(expression[0] == "column", "merged-full-projection-expression", expression)
        occurrence = expression[2] if len(expression) == 3 else 0
        return occurrence, relations[occurrence][2][expression[1]]

    where = query.get("where")
    if where is not None:
        require(where[:2] == ["comparison", "="] and where[3][0] == "text", "merged-full-filter-expression", where)
        where = root(where[2]), where[3][1]
    return (kind, sources, keys, tuple((root(expr), label) for expr, label in query["columns"]), where)


def finite_ids(domain):
    return {(mask, orientation, projected) for mask in domain["keyLayouts"]
            for orientation in domain["orientation"] for projected in domain["projections"]}


def finite_id(reply, _):
    _, sources, keys, fields, _ = graph(reply)
    mask = next(name for name, names in LAYOUTS.items() if names == tuple(pair[0][1].lower() for pair in keys))
    projection = "physical" if len(fields) == 7 else "combined" if len(fields) == 2 else "visible"
    return mask, "original" if sources[0][1] == INPUTS[0] else "swapped", projection


def value(expression, pair):
    if expression[0] == "coalesce":
        first = value(expression[1], pair)
        return value(expression[2], pair) if first is None else first
    index, name = expression
    return pair[index].get(name.lower())


def expected(query):
    kind, ((_, left), (_, right)), keys, fields, where = graph(query)
    require(kind == "FULL" and all(a == 0 and b == 1 and first.lower() == second.lower()
                                 for (a, first), (b, second) in keys), "merged-full-fixture-key-pairs", keys)
    paired = []
    matched_right = set()
    for lhs in DATA[left]:
        matched = False
        for position, rhs in enumerate(DATA[right]):
            if all(equal(left, lhs[first.lower()], right, rhs[second.lower()], first.lower())
                   for (_, first), (_, second) in keys):
                matched = True
                matched_right.add(position)
                paired.append((lhs, rhs))
        if not matched:
            paired.append((lhs, {}))
    paired.extend(({}, rhs) for position, rhs in enumerate(DATA[right]) if position not in matched_right)
    rows = [[typed(value(expression, pair)) for expression, _ in fields] for pair in paired
            if where is None or value(where[0], pair) == where[1]]
    return {"names": [label for _, label in fields], "rows": sorted(rows), "parameterCount": 0,
            "parameters": {}, "readOnly": True, "changes": 0}


def observe_view(db, sql):
    db.execute("SAVEPOINT observe_full_view")
    try:
        created = db.create(sql, VIEW)
        types = db.metadata("table_xinfo", schema="main", table=VIEW)
        read = db.observe(f"SELECT * FROM main.{VIEW}", {})
        return {"create": created, "types": types, "read": read}
    finally:
        db.execute("ROLLBACK TO observe_full_view; RELEASE observe_full_view")


def probe(item, reply, db, ledger=None, key=None, original=None):
    require(reply["status"] == "constructed", "merged-full-positive-rejected", reply)
    is_view = item["operation"] == "create-view"
    wanted, actual = (item["query"], reply["query"]) if is_view else (item, reply)
    if is_view:
        fields = ("schema", "view", "ifNotExists", "columns", "operation")
        require(all(item[field] == reply[field] for field in fields), "merged-full-view-destination", reply)
    require(graph(wanted) == graph(actual) and actual["orderBy"] == [] and not actual["distinct"],
            "merged-full-graph-differs", reply)
    if ledger is not None:
        ledger.record(key, "constructed", response=reply)
    def observation(sql):
        return observe_view(db, sql) if is_view else db.observe(sql, {})
    observations = {"observation": observation(reply["sql"])}
    if original is not None:
        observations["original"] = observation(original)
    if ledger is not None:
        ledger.record(key, "observed", **observations)
    prediction = expected(wanted)
    if is_view:
        prediction = {"create": DDL_RESULT, "read": prediction,
                      "types": [list(map(typed, (index, label, declared, 0, None, 0, 0)))
                                for index, (label, declared) in enumerate(zip(item["columns"], ("", "", "BLOB", "BLOB")))]}
    for measured in observations.values():
        compare(prediction, measured)
    if ledger is not None:
        ledger.record(key, "compared")
    return observations["observation"]


def forms(reply):
    kind, sources, keys, fields, _ = graph(reply)
    yield f"kind/{kind}"
    yield f"left/{sources[0][1]}"
    yield f"keyCount/{len(keys)}"
    for expression, label in fields:
        yield f"projection/{expression[0]}"
        if '"' in label:
            yield "quoted-label"
    if len(keys) != len(set(keys)):
        yield "repeated-key"


def required(_):
    return {"kind/FULL", "left/merge_left", "left/merge_right", "projection/0", "projection/1", "projection/coalesce",
            "quoted-label", "repeated-key", *(f"keyCount/{count}" for count in range(4))}


def controls():
    correct = layout("both", "original", "combined")
    changed = {**correct, "columns": [
        [[expr[0], list(reversed(expr[1]))], label] for expr, label in correct["columns"]
    ]}
    prediction, mismatch = expected(correct), expected(changed)
    compare(prediction, prediction)
    try:
        compare(prediction, mismatch)
    except Failure as failure:
        require(failure.signal == "meaning-mismatch", "wrong-merged-full-comparator-control", failure.detail)
    else:
        raise Failure("merged-full-comparator-accepted", mismatch)
    return {"healthy": prediction, "wrongOperandOrder": mismatch}


def execute(args, contract, db, worker_factory=Worker):
    require(len(list(analyzed_cases())) == len(contract["analysis"]), "incomplete-merged-full-analysis")
    schedule = ScalarSchedule(requests, negatives, finite_id, finite_ids, forms, required, controls)
    return execute_schedule(args, contract, db, worker_factory, schedule, probe)


def check_faults(args, db, runner=check_render_faults):
    target = "sql-semantics-sqlite/src/Ir/SelectSql.php"
    item = layout("both", "original", "visible")
    predicate = "$this->expression($join->on, $source, $select->joins)"
    return runner(args, db, [
        RenderFault("swap-full-value-candidates", target, "foreach ($expression->arguments as $argument)",
                    "foreach (array_reverse($expression->arguments) as $argument)", item, "meaning-mismatch"),
        RenderFault("skip-second-full-key", target, predicate,
                    "$this->expression($join->on->left, $source, $select->joins)", item, "meaning-mismatch"),
        RenderFault("drop-right-only-rows", target, "$join->kind->value . ' JOIN '", "'LEFT' . ' JOIN '", item, "meaning-mismatch"),
        RenderFault("remove-full-output-label", target, " . ' AS ' . $column->name->quoted()", "",
                    item, "meaning-mismatch"),
    ], probe)


if __name__ == "__main__":
    run_domain("merged-full-join", CONTRACT_NAME, CONTRACT, FIXTURE, execute, check_faults, FullMergeDatabase)
