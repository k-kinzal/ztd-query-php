"""Check normalized SQLite merged joins against typed pairs of fixture rows."""

import random
from itertools import product

from run import Failure, Worker, compare, require
from run_sqlite_unique_keys import values_sql
from sqlite_domain import RenderFault, check_render_faults, run_domain
from sqlite_engine import typed
from sqlite_relational_cases import DATA as RELATIONAL_ROWS, DECLARATIONS as RELATIONAL_DDL, SCHEMA as RELATIONAL_FIXTURE
from sqlite_scalar_schedule import ScalarSchedule, execute_schedule

CONTRACT_NAME = "sqlite-merged-join-v3.json"
CONTRACT = "691715c82cbafd5f102cd3d696822afe399d7931412902b971ac9d871a42d81b"
KINDS = ("INNER", "CROSS", "LEFT")
LAYOUTS = {"none": (), "key": ("key",), "lane": ("lane",), "both": ("key", "lane")}
HEADINGS = {
    "merge_left": ("Key", "lane", "left_value"),
    "merge_right": ("lane", "key", "right_value"),
    "merge_free": ("free",),
    **{name: ("key", "value") for name in RELATIONAL_ROWS},
}
DECLARATIONS = [
    *RELATIONAL_DDL,
    "CREATE TABLE merge_left(Key TEXT COLLATE NOCASE,lane TEXT,left_value BLOB)",
    "CREATE TABLE merge_right(lane NUMERIC,key TEXT COLLATE BINARY,right_value BLOB)",
    "CREATE TABLE merge_free(free BLOB)",
]
ROWS = {
    "merge_left": [
        ("Alpha", "02", "L1"), ("Alpha", "02", "L1"), ("beta", "3", "L2"), ("beta", "8", "L3"),
        ("orphan", "2", "L4"), (None, "2", "LN"), ("same", None, "LS"),
    ],
    "merge_right": [
        (2, "ALPHA", "R1"), (9, "alpha", "R2"), (3, "beta", "R3"), (7, "BETA", "R4"), (2, None, "RN"),
        (None, "same", "RS"), (42, "alone", "RX"),
    ],
    "merge_free": [(13,), (None,)],
    **RELATIONAL_ROWS,
}
FIXTURE = (RELATIONAL_FIXTURE + "; ".join(DECLARATIONS[-3:]) + "; " +
           "; ".join(f"INSERT INTO {source} VALUES {values_sql(ROWS[source])}" for source in tuple(HEADINGS)[:3]))
DATA = {source: [dict(zip(map(str.lower, HEADINGS[source]), row)) for row in rows] for source, rows in ROWS.items()}


def field_names(sources, keys, qualified):
    return [(occurrence, name, name) for occurrence, source in enumerate(sources) for name in HEADINGS[source]
            if qualified or occurrence == 0 or name.lower() not in keys]


def case(kind, sources, keys, fields):
    left, right = sources

    def slot(occurrence, name):
        heading = HEADINGS[sources[occurrence]]
        index = tuple(map(str.lower, heading)).index(name.lower())
        return ["column", index] if occurrence == 0 else ["column", index, occurrence]

    predicate = None
    for key in keys:
        equal = ["comparison", "=", slot(0, key), slot(1, key)]
        predicate = equal if predicate is None else ["logical", "AND", predicate, equal]
    return {"operation": "select", "source": ["main", left, list(HEADINGS[left])],
            "joins": [[kind, ["main", right, list(HEADINGS[right])], predicate]],
            "columns": [[slot(index, field), label] for index, field, label in fields]}


def layout(kind, mask, orientation, projection):
    sources = ("merge_left", "merge_right")
    if orientation == "swapped":
        sources = sources[::-1]
    keys = LAYOUTS[mask]
    fields = field_names(sources, keys, projection == "qualified")
    if projection == "qualified":
        fields.append((1, HEADINGS[sources[1]][0], "repeated"))
    return case(kind, sources, keys, fields)


def analyzed_cases():
    fixtures = ("ir_left", "ir_right")
    yield "SELECT ir_left.key FROM ir_left JOIN ir_right USING (key)", case("INNER", fixtures, ("key",), [(0, "key", "key")])
    yield "SELECT ir_left.key FROM ir_left NATURAL LEFT JOIN ir_right", case("LEFT", fixtures, ("key", "value"), [(0, "key", "key")])
    pair, keys = ("merge_left", "merge_right"), ("lane", "key")
    yield "SELECT * FROM merge_left NATURAL JOIN merge_right", case("INNER", pair, keys, field_names(pair, keys, False))
    yield "SELECT a.*, b.* FROM merge_left a NATURAL LEFT JOIN merge_right b", case("LEFT", pair, keys, field_names(pair, keys, True))
    yield "SELECT key,a.key,b.key,lane,a.lane,b.lane FROM merge_left a NATURAL LEFT JOIN merge_right b", case(
        "LEFT", pair, keys, [(0, "Key", "Key"), (0, "Key", "Key"), (1, "key", "key"),
                             (0, "lane", "lane"), (0, "lane", "lane"), (1, "lane", "lane")])
    yield "SELECT * FROM merge_right NATURAL LEFT JOIN merge_left", case(
        "LEFT", pair[::-1], keys[::-1], field_names(pair[::-1], keys, False))
    free = ("merge_left", "merge_free")
    yield "SELECT * FROM merge_left NATURAL CROSS JOIN merge_free", case("CROSS", free, (), field_names(free, (), False))
    yield "SELECT * FROM merge_left JOIN merge_right USING (kEy,KEY)", case(
        "INNER", pair, ("key", "key"), field_names(pair, ("key",), False))
    yield "SELECT * FROM merge_left, merge_right USING (Key,lane)", case(
        "INNER", pair, keys[::-1], field_names(pair, keys, False))
    yield "SELECT * FROM merge_left CROSS JOIN merge_right USING (lane,key)", case(
        "CROSS", pair, keys, field_names(pair, keys, False))


def requests(contract):
    domain = contract["finite"]
    for index, dimensions in enumerate(product(*(domain[key] for key in ("kinds", "keyLayouts", "orientation", "projections")))):
        item = layout(*dimensions)
        yield f"finite/{index}", item, item
    for index, (sql, item) in enumerate(analyzed_cases()):
        yield f"analyze/{index}", {"sql": sql, "declarations": DECLARATIONS}, item
    for seed in contract["exploration"]["seeds"]:
        sample = random.Random(seed)
        for index in range(contract["exploration"]["casesPerSeed"]):
            sources = ["merge_left", "merge_right"]
            sample.shuffle(sources)
            keys = sample.choices(("key", "lane"), k=sample.randrange(4))
            fields = field_names(sources, keys, True)
            projection = [(*field[:2], sample.choice(("", "α", 'field " quote', field[2])))
                          for field in sample.choices(fields, k=sample.randrange(1, 8))]
            item = case(sample.choice(KINDS), sources, keys, projection)
            yield f"seed/{seed}/{index}", item, item


def negatives():
    for operand in (["column", 40, 1], ["column", 0, 2]):
        invalid = layout("LEFT", "key", "original", "qualified")
        invalid["joins"][0][2][-1] = operand
        yield invalid, "rejected", "visible occurrence"
    sql_cases = (
        ("merge_left JOIN merge_right USING (absent)", "analysis-rejected", "absent"),
        ("merge_left NATURAL JOIN merge_right ON 1", "analysis-rejected", "NATURAL"),
        ("merge_left NATURAL JOIN merge_right USING (key)", "analysis-rejected", "NATURAL"),
        ("merge_left JOIN merge_free RIGHT JOIN merge_right USING (key)", "rejected", "merged prefix requires"),
        ("merge_left FULL JOIN local USING (key)", "rejected", "requires complete base-table headings"),
    )
    for source, status, reason in sql_cases:
        prefix = "WITH local(key) AS (VALUES(4)) " if source.endswith("JOIN local USING (key)") else ""
        yield {"sql": prefix + "SELECT merge_left.key FROM " + source, "declarations": DECLARATIONS}, status, reason


def structure(query):
    kind, right, predicate = query["joins"][0]
    sources = query["source"], right

    def location(column):
        occurrence = column[2] if len(column) == 3 else 0
        return (occurrence, sources[occurrence][2][column[1]])

    def pairs(expression):
        if expression is None:
            return ()
        if expression[:2] == ["logical", "AND"]:
            return (*pairs(expression[2]), *pairs(expression[3]))
        require(expression[:2] == ["comparison", "="], "merged-join-predicate", expression)
        return ((location(expression[2]), location(expression[3])),)

    return (kind, tuple(tuple(source[:2]) for source in sources), pairs(predicate),
            tuple((*location(field), label) for field, label in query["columns"]))


def finite_ids(domain):
    return set(product(domain["kinds"], domain["keyLayouts"], domain["orientation"], domain["projections"]))


def finite_id(query, _):
    kind, sources, keys, fields = structure(query)
    spellings = tuple(pair[0][1].lower() for pair in keys)
    orientation = "original" if sources[0][1] == "merge_left" else "swapped"
    label = next(name for name, keys_ in LAYOUTS.items() if keys_ == spellings)
    return kind, label, orientation, "qualified" if len(fields) == 7 else "visible"


def equal(left_name, first, right_name, second, key):
    if first is None or second is None:
        return False
    if key == "key" and left_name == "merge_left":
        return first.lower() == second.lower()  # ASCII-only NOCASE for the fixture keys.
    if key == "lane" and {left_name, right_name} == {"merge_left", "merge_right"}:
        return int(first) == int(second)  # The fixture lane strings all carry integers.
    return first == second


def expected(query):
    kind, ((_, left), (_, right)), keys, fields = structure(query)
    left_rows, right_rows = DATA[left], DATA[right]
    key_names = tuple((first[1].lower(), second[1].lower()) for first, second in keys)
    require(all(first[0] == 0 and second[0] == 1 and first[1].lower() == second[1].lower()
                for first, second in keys), "invalid-fixture-keys", keys)
    rows = []
    for left_row in left_rows:
        matching = [right_row for right_row in right_rows
                    if all(equal(left, left_row[first], right, right_row[second], first) for first, second in key_names)]
        for right_row in matching or ([{}] if kind == "LEFT" else []):
            scoped = left_row, right_row
            rows.append([typed(scoped[index].get(field.lower())) for index, field, _ in fields])
    return {"names": [label for _, _, label in fields], "rows": sorted(rows), "parameterCount": 0,
            "parameters": {}, "readOnly": True, "changes": 0}


def probe(item, response, db, ledger=None, key=None, original=None):
    require(response["status"] == "constructed", "merged-join-positive-rejected", response)
    require(response["where"] is None and response["orderBy"] == [] and not response["distinct"],
            "merged-join-unexpected-clauses", response)
    require(structure(response) == structure(item), "merged-join-graph-differs", {"input": item, "observed": response})
    if ledger is not None:
        ledger.record(key, "constructed", response=response)
    observations = {"observation": db.observe(response["sql"], {})}
    if original is not None:
        observations["original"] = db.observe(original, {})
    if ledger is not None:
        ledger.record(key, "observed", **observations)
    prediction = expected(item)
    for observation in observations.values():
        compare(prediction, observation)
    if ledger is not None:
        ledger.record(key, "compared")
    return observations["observation"]


def forms(query):
    kind, names, keys, fields = structure(query)
    yield f"kind/{kind}"
    yield f"left/{names[0][1]}"
    yield f"keyCount/{len(keys)}"
    if len(keys) != len(set(keys)):
        yield "repeated-key"
    if len(fields) > len({field[:2] for field in fields}):
        yield "repeated-slot"
    if any('"' in label for _, _, label in fields):
        yield "quoted-label"
    if tuple(key[0][1].lower() for key in keys) == ("lane", "key"):
        yield "lane-before-key"


def required(domain):
    return ({f"kind/{kind}" for kind in domain["kinds"]} |
            {f"keyCount/{count}" for count in range(4)} |
            {"left/merge_left", "left/merge_right", "repeated-key", "repeated-slot", "quoted-label", "lane-before-key"})


def controls():
    input_ = layout("LEFT", "both", "original", "qualified")
    healthy = expected(input_)
    wrong_pairs = expected(layout("LEFT", "key", "original", "qualified"))
    wrong_shape = {**healthy, "names": healthy["names"][::-1]}
    compare(healthy, healthy)
    for changed in (wrong_pairs, wrong_shape):
        try:
            compare(healthy, changed)
        except Failure as failure:
            require(failure.signal == "meaning-mismatch", "wrong-merged-join-control-signal", failure.detail)
        else:
            raise Failure("merged-join-comparator-accepted", changed)
    return {"healthy": healthy, "wrongPairs": wrong_pairs, "wrongShape": wrong_shape}


def execute(args, contract, db, worker_factory=Worker):
    require(len(list(analyzed_cases())) == len(contract["analysis"]), "incomplete-merged-join-analysis")
    schedule = ScalarSchedule(requests, negatives, finite_id, finite_ids, forms, required, controls)
    return execute_schedule(args, contract, db, worker_factory, schedule, probe)


def check_faults(args, db, runner=check_render_faults):
    target = "sql-semantics-sqlite/src/Ir/SelectSql.php"
    normal = layout("LEFT", "both", "original", "qualified")
    predicate = "$this->expression($join->on, $source, $select->joins)"
    binary = ("'(' . $this->expression($expression->left, $source, $joins) . ' ' . $expression->operator->value"
              " . ' ' . $this->expression($expression->right, $source, $joins) . ')'")
    return runner(args, db, [
        RenderFault("reverse-merged-equality", target, binary,
                    binary.replace("->left", "->SECOND").replace("->right", "->left").replace("->SECOND", "->right"),
                    normal, "meaning-mismatch"),
        RenderFault("drop-second-merged-key", target, predicate,
                    "$this->expression($join->on->left, $source, $select->joins)", normal, "meaning-mismatch"),
        RenderFault("lose-null-extension", target, "$join->kind->value . ' JOIN '", "'INNER' . ' JOIN '",
                    normal, "meaning-mismatch"),
        RenderFault("drop-merged-output-field", target, "foreach ($select->columns as $column)",
                    "foreach (array_slice($select->columns, 0, -1) as $column)", normal, "meaning-mismatch"),
    ], probe)


if __name__ == "__main__":
    run_domain("merged-join", CONTRACT_NAME, CONTRACT, FIXTURE, execute, check_faults)
