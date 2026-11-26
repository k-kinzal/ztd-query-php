"""Compare MySQL binary merged joins with independently paired rows and native field metadata."""

from itertools import product
import json
import random
import sys

from run import TOOL, Failure, digest, require
from run_server import CONTRACT as PROJECTION, same
from run_server_join import OBSERVER, main, observer_request
from server_cases import observation
from server_delete_cases import literal
from server_join_cases import expected as join_expected
from server_join_cases import named_binary_graph as graph
from server_join_cases import DATA as BASE_DATA, NAMES_BY_TABLE, declarations as base_declarations, fixture as base_fixture, join, slot
from sqlite_domain import RenderFault

LABEL = "mysql-merged-join"
CONTRACT = "f6ba0df40b63e6aaf88b871fbc47b587576c1fef3af3f1a53487fd17dd440bb5"
KINDS = ("INNER", "LEFT", "RIGHT")
MASKS = {"id": ("id",), "lane": ("lane",), "both": ("id", "lane")}
SCHEMA = "ir_fixture"
LEFT, RIGHT, FREE, EMPTY, UNSIGNED = "merge_left", "merge_right", "merge_free", "merge_empty", "merge_unsigned"
HEADINGS = {
    LEFT: ("Id", "lane", "left_value"),
    RIGHT: ("lane", "right_value", "id"),
    FREE: ("isolated",),
    EMPTY: ("other",),
    UNSIGNED: ("id",),
    **{name: ("Id", "delta") for name in NAMES_BY_TABLE.values()},
}
DATA = {
    LEFT: [(1, 4, 103), (1, 4, 103), (2, 7, 104), (3, None, 105), (4, 8, 106), (None, 4, 107)],
    RIGHT: [(4, 201, 1), (7, 202, 15), (None, 203, 3), (9, 204, 8), (4, 205, None),
            (5, 206, 1), (7, 207, 2)],
    FREE: [(55,), (None,)],
    EMPTY: [],
    UNSIGNED: [(1,)],
    **{NAMES_BY_TABLE[key]: rows for key, rows in BASE_DATA.items()},
}
DECLARATIONS = [*base_declarations("mysql"), *(
    f"CREATE TABLE {SCHEMA}.{name} (" + ", ".join(f"`{key}` INT" for key in HEADINGS[name]) + ")"
    for name in (LEFT, RIGHT, FREE, EMPTY)
), f"CREATE TABLE {SCHEMA}.{UNSIGNED} (id INT UNSIGNED)"]
NATIVE_TABLES = (LEFT, RIGHT, FREE, EMPTY, UNSIGNED)
REFUSALS = "SqlSemantics\\Platform\\MySql\\Model\\Diagnostic\\"


def specifications():
    path, profile = TOOL / "mysql-merged-join-v3.json", TOOL / "server-projection-v1.json"
    require(digest(path) == CONTRACT and digest(profile) == PROJECTION, "mysql-merged-join-contract-mismatch")
    return json.loads(path.read_text()), {"mysql": json.loads(profile.read_text())["profiles"]["mysql"]}


def fixture():
    yield from base_fixture("mysql")
    yield from (f"DROP TABLE IF EXISTS {SCHEMA}.{name}" for name in NATIVE_TABLES)
    yield from DECLARATIONS[-len(NATIVE_TABLES):]
    for name in NATIVE_TABLES:
        if not DATA[name]:
            continue
        rows = ",".join("(" + ",".join("NULL" if value is None else str(value) for value in row) + ")" for row in DATA[name])
        yield f"INSERT INTO {SCHEMA}.{name} VALUES {rows}"


def prepare(_, db):
    result = db.request({"fixture": list(fixture())})
    same({"status": "fixture"}, result)
    syntax = specifications()[0]["nativeSyntax"]
    records = {key: {"sql": syntax[key], "observation": observation(db.request({
        "sql": syntax[key], "observeVendorCode": True,
    }))} for key in ("positive", "explicit")}
    return {"status": "fixture", "syntax": records}


def check_preparation(engine, environment, prepared):
    syntax = specifications()[0]["nativeSyntax"]
    release = environment["database"][0]
    require(engine == "mysql" and prepared["status"] == "fixture" and set(prepared["syntax"]) == {"positive", "explicit"},
            "mysql-merged-native-syntax-report", prepared)
    inputs, keys = (LEFT, RIGHT), MASKS["both"]
    item = case("mysql-" + release, inputs, "INNER", keys, projected(inputs, "INNER", keys))
    for key, witness in prepared["syntax"].items():
        require(witness["sql"] == syntax[key], "mysql-merged-native-syntax-input", witness)
        if key == "explicit" and release in syntax["legacyReleases"]:
            same(syntax["legacyDiagnostic"], witness["observation"])
        else:
            check_observation(item, witness["observation"], witness["sql"])


def reference(inputs, occurrence, name):
    position = tuple(map(str.lower, HEADINGS[inputs[occurrence]])).index(name.lower())
    return slot(position, occurrence)


def projected(inputs, kind, fields, qualified=False):
    if qualified:
        return [(index, name, name) for index in (0, 1) for name in HEADINGS[inputs[index]]]
    representative = int(kind == "RIGHT")
    first, second = HEADINGS[inputs[representative]], HEADINGS[inputs[1 - representative]]
    common = [(representative, name, name) for name in first if name.lower() in fields]
    remaining = [(representative, name, name) for name in first if name.lower() not in fields]
    return common + remaining + [(1 - representative, name, name) for name in second if name.lower() not in fields]


def case(tag, inputs, kind, keys, fields):
    sources = [{"schema": SCHEMA, "table": table, "columns": list(HEADINGS[table])} for table in inputs]
    on = None
    for key in keys:
        clause = ["comparison", "=", reference(inputs, 0, key), reference(inputs, 1, key)]
        on = clause if on is None else ["logic", "AND", on, clause]
    if on is None and kind in ("LEFT", "RIGHT"):
        on = ["boolean", True]
    columns = [[reference(inputs, index, field), name] for index, field, name in fields]
    return {"dialect": "mysql", "release": tag, "source": join(kind, *sources, on), "columns": columns, "where": None}


def finite(tag, kind, mask, orientation, shape):
    inputs = (LEFT, RIGHT) if orientation == "left-first" else (RIGHT, LEFT)
    keys = MASKS[mask]
    return case(tag, inputs, kind, keys, projected(inputs, kind, keys, shape == "physical"))


def analyzed(tag):
    def owner(name):
        return f"{SCHEMA}.{name}"
    left, right, free, empty = map(owner, (LEFT, RIGHT, FREE, EMPTY))
    a, b = owner("ir_join_accounts"), owner("ir_join_independent")
    old = case(tag, ("ir_join_accounts", "ir_join_independent"), "INNER", ("id", "delta"), [])
    old["columns"] = [[literal(1), "answer"]]
    yield f"SELECT 1 AS answer FROM {a} a NATURAL JOIN {b} b", old
    inputs = (LEFT, RIGHT)
    common = ("id", "lane")
    yield f"SELECT * FROM {left} a JOIN {right} b USING (lane,id)", case(
        tag, inputs, "INNER", common[::-1], projected(inputs, "INNER", common))
    syntax = specifications()[0]["nativeSyntax"]
    form = "positive" if tag.removeprefix("mysql-") in syntax["legacyReleases"] else "explicit"
    yield syntax[form], case(tag, inputs, "INNER", common, projected(inputs, "INNER", common))
    yield f"SELECT * FROM {left} a NATURAL RIGHT JOIN {right} b", case(
        tag, inputs, "RIGHT", common, projected(inputs, "RIGHT", common))
    yield f"SELECT * FROM {left} a RIGHT JOIN {right} b ON a.id=b.id", case(
        tag, inputs, "RIGHT", ("id",), projected(inputs, "RIGHT", (), qualified=True))
    for kind in ("LEFT", "RIGHT"):
        yield f"SELECT a.*,b.* FROM {left} a NATURAL {kind} JOIN {right} b", case(
            tag, inputs, kind, common, projected(inputs, kind, common, qualified=True))
    yield f"SELECT id,a.Id,b.id,lane,a.lane,b.lane FROM {left} a RIGHT JOIN {right} b USING (id,lane)", case(
        tag, inputs, "RIGHT", common, [(1, "id", "id"), (0, "Id", "Id"), (1, "id", "id"),
                                       (1, "lane", "lane"), (0, "lane", "lane"), (1, "lane", "lane")])
    yield f"SELECT * FROM {left} NATURAL LEFT JOIN {empty}", case(
        tag, (LEFT, EMPTY), "LEFT", (), projected((LEFT, EMPTY), "LEFT", ()))
    yield f"SELECT * FROM {left} NATURAL RIGHT JOIN {free}", case(
        tag, (LEFT, FREE), "RIGHT", (), projected((LEFT, FREE), "RIGHT", ()))


def analyzed_request(tag, sql):
    return {"dialect": "mysql", "release": tag, "sql": sql, "declarations": DECLARATIONS}


def negatives(tag):
    bad_on = finite(tag, "LEFT", "id", "left-first", "visible")
    bad_on["source"]["on"][-1] = slot(0, 2)
    yield bad_on, {"exception": REFUSALS + "InvalidConstruction", "message": "visible input occurrence"}
    bad_field = finite(tag, "LEFT", "id", "left-first", "visible")
    bad_field["columns"][0][0] = slot(99, 1)
    yield bad_field, {"exception": REFUSALS + "InvalidConstruction", "message": "must belong to the SELECT source"}
    left, right, free, unsigned = [f"{SCHEMA}.{name}" for name in (LEFT, RIGHT, FREE, UNSIGNED)]
    for sql, error, message in (
        (f"SELECT 1 FROM ({left} a JOIN {free} c ON 1) NATURAL JOIN {right} b", "InvalidConstruction", "two ordinary base-table inputs"),
        (f"SELECT 1 FROM {left} JOIN {right} USING(absent)", "SemanticException", "absent"),
        (f"SELECT 1 FROM {left} JOIN {unsigned} USING(id)", "InvalidConstruction", "signed 32-bit integer type"),
        (f"SELECT 1 FROM {left} JOIN {right} USING(id,ID)", "SemanticException", "ID"),
    ):
        yield analyzed_request(tag, sql), {"exception": REFUSALS + error, "message": message}
    syntax = specifications()[0]["nativeSyntax"]
    if tag.removeprefix("mysql-") in syntax["legacyReleases"]:
        yield analyzed_request(tag, syntax["explicit"]), {
            "exception": REFUSALS + "AnalysisException", "message": "Unexpected 'INNER'"}


def requests(_, tag, spec):
    bounded = spec["finite"]
    for index, layout in enumerate(product(bounded["joinKinds"], bounded["keys"], bounded["orientation"], bounded["projection"])):
        item = finite(tag, *layout)
        yield f"finite/{index}", item, item, None
    require(len(list(analyzed(tag))) == len(spec["analysis"]),
            "incomplete-mysql-merged-analysis")
    for index, (sql, item) in enumerate(analyzed(tag)):
        yield f"analyze/{index}", analyzed_request(tag, sql), item, None
    for seed in spec["exploration"]["seeds"]:
        sample = random.Random(seed)
        for index in range(spec["exploration"]["casesPerSeed"]):
            inputs = tuple(sample.choices((LEFT, RIGHT), k=2))
            keys = sample.sample(("id", "lane"), sample.randrange(3))
            kind = sample.choice(KINDS)
            if not keys and kind == "INNER":
                kind = "CROSS"
            choices = projected(inputs, kind, keys, qualified=True)
            fields = [(*field[:2], sample.choice((field[2], "", "値", 'x ` field')))
                      for field in sample.choices(choices, k=sample.randrange(1, 8))]
            item = case(tag, inputs, kind, keys, fields)
            yield f"seed/{seed}/{index}", item, item, None
    for index, (request, rule) in enumerate(negatives(tag)):
        yield f"negative/{index}", request, None, rule


def finite_ids(_, spec, _tag):
    fields = spec["finite"]
    return set(product(fields["joinKinds"], fields["keys"], fields["orientation"], fields["projection"]))


def finite_id(_, reply, _item):
    kind, names, condition, fields = graph(reply)
    mask = "both" if condition[0] == "logic" else condition[2][-1].lower()
    direction = "left-first" if names[0][1] == LEFT else "right-first"
    return kind, mask, direction, "physical" if len(fields) == 6 else "visible"


def expected(item):
    return join_expected(item, lambda source: (HEADINGS[source["table"]], DATA[source["table"]]))


def check_value(item, reply):
    require(reply["status"] == "constructed", "mysql-merged-positive-rejected", reply)
    require(reply["profile"].startswith(item["release"] + "/") and reply["where"] is None
            and graph(reply) == graph(item), "mysql-merged-graph-differs", {"expected": item, "actual": reply})


def check_observation(item, actual, sql):
    prediction = expected(item)
    require(actual["status"] == "rows", "mysql-merged-native-rejection", {"sql": sql, "actual": actual})
    require(prediction == actual, "meaning-mismatch", {"sql": sql, "expected": prediction, "observed": actual})


def check_fault_witness(item, witness):
    same(expected(item), witness["expected"])
    require(witness["observed"]["status"] == "rows", "mysql-merged-fault-native")


def sample_forms(forms, reply, *_):
    kind, inputs, on, fields = graph(reply)
    forms[f"kind/{kind}"] += 1
    forms["predicate/" + ("none" if on is None else on[0])] += 1
    if inputs[0] == inputs[1]:
        forms["self"] += 1
    for term, name in fields:
        forms[f"occurrence/{term[1]}"] += 1
        if '`' in name:
            forms["quoted-label"] += 1


def required_forms(*_):
    return {"kind/CROSS", *(f"kind/{kind}" for kind in KINDS), "predicate/none", "predicate/boolean", "predicate/comparison",
            "predicate/logic", "self", "occurrence/0", "occurrence/1", "quoted-label"}


def controls():
    item = finite("mysql-8.0.44", "RIGHT", "both", "left-first", "visible")
    prediction = expected(item)
    check_observation(item, prediction, "known preserved heading")
    altered = {**prediction, "fields": list(reversed(prediction["fields"]))}
    try:
        check_observation(item, altered, "wrong output order")
    except Failure as failure:
        require(failure.signal == "meaning-mismatch", "wrong-mysql-merged-control", failure.detail)
    else:
        raise Failure("mysql-merged-comparator-accepted")
    return {"healthy": prediction, "wrongOrder": altered}


def faults(_, tag):
    target = "sql-semantics-mysql/src/Ir/SelectSql.php"
    normal = finite(tag, "RIGHT", "both", "left-first", "visible")
    right_id = case(tag, (LEFT, RIGHT), "RIGHT", ("id",), [(1, "id", "answer")])
    return [
        RenderFault("misaddress-merged-origin", target, "$this->alias($offset + $expression->occurrence)", "$this->alias($offset)",
                    right_id, "meaning-mismatch"),
        RenderFault("drop-second-merged-key", target, "$this->predicate($source->on, $source, $offset)",
                    "$this->predicate($source->on->left, $source, $offset)", normal, "meaning-mismatch"),
        RenderFault("discard-right-rows", target, "$source->kind->value . ' JOIN '", "'INNER' . ' JOIN '", normal, "meaning-mismatch"),
        RenderFault("drop-projected-merge-field", target, "foreach ($select->columns as $slot => $column)", "foreach (array_slice($select->columns, 1) as $slot => $column)",
                    normal, "meaning-mismatch"),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])
