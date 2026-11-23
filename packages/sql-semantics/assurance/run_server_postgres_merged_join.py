"""Compare PostgreSQL int4 merged joins, output headings and inheritance with native results."""

from itertools import product
import json
import random
import sys

from run import TOOL, Failure, digest, require
from run_server import CONTRACT as PROJECTION, same
from run_server_join import OBSERVER, check_preparation, main, observer_request
from server_cases import observation
from server_join_cases import expected as join_expected
from server_join_cases import join, named_binary_graph as graph, slot
from sqlite_domain import RenderFault

LABEL = "postgres-merged-join"
CONTRACT = "6a42d825607c6acff928cd80fac716da6d5e3a5d1654a36ef47c145ec16c387f"
KINDS = ("INNER", "LEFT", "RIGHT")
MASKS = {"id": ("id",), "lane": ("lane",), "both": ("id", "lane")}
SOURCES = LEFT, RIGHT, EMPTY, WIDE, CHILD = (
    "ir_merge_left", "ir_merge_right", "ir_merge_empty", "ir_merge_wide", "ir_merge_child"
)
HEADINGS = {LEFT: ("id", "lane", "left_value"), RIGHT: ("lane", "right_value", "id"), EMPTY: ("other",), WIDE: ("id",)}
DATA = {
    LEFT: [(1, 4, 103), (1, 4, 103), (2, 7, 104), (3, None, 105), (4, 8, 106), (None, 4, 107)],
    RIGHT: [(4, 201, 1), (7, 202, 15), (None, 203, 3), (9, 204, 8), (4, 205, None), (5, 206, 1), (7, 207, 2)],
    EMPTY: [],
    WIDE: [(1,)],
    CHILD: [(8, 9, 300)],
}
DECLARATIONS = [
    *(f"CREATE TABLE public.{name} (" + ", ".join(f"{column} INTEGER" for column in HEADINGS[name]) + ")"
      for name in (LEFT, RIGHT, EMPTY)),
    f"CREATE TABLE public.{WIDE} (id BIGINT)",
]
DIAGNOSTICS = "SqlSemantics\\Platform\\PostgreSql\\Model\\Diagnostic\\"


def specifications():
    path, profile = TOOL / "postgres-merged-join-v2.json", TOOL / "server-projection-v1.json"
    require(digest(path) == CONTRACT and digest(profile) == PROJECTION, "postgres-merged-contract-mismatch")
    return json.loads(path.read_text()), {"postgres": json.loads(profile.read_text())["profiles"]["postgres"]}


def fixture():
    yield from (f"DROP TABLE IF EXISTS public.{name}" for name in (CHILD, LEFT, RIGHT, EMPTY, WIDE))
    yield from DECLARATIONS
    yield f"CREATE TABLE public.{CHILD} () INHERITS (public.{LEFT})"
    for name in SOURCES:
        if DATA[name]:
            rows = ",".join("(" + ",".join("NULL" if value is None else str(value) for value in row) + ")" for row in DATA[name])
            yield f"INSERT INTO public.{name} VALUES {rows}"


def prepare(_, db):
    result = db.request({"fixture": list(fixture())})
    same({"status": "fixture"}, result)
    return result


def physical_fields(inputs):
    return [(index, name, name) for index, table in enumerate(inputs) for name in HEADINGS[table]]


def visible_fields(inputs, kind, keys):
    origin = int(kind == "RIGHT")
    return [(origin, key, key) for key in keys] + [field for field in physical_fields(inputs) if field[1] not in keys]


def case(tag, inputs, kind, keys, fields, only=(False, False)):
    sources = [{"schema": "public", "table": name, "columns": list(HEADINGS[name]), "only": own}
               for name, own in zip(inputs, only)]

    def reference(occurrence, name):
        return slot(HEADINGS[inputs[occurrence]].index(name), occurrence)

    on = None
    for key in keys:
        equal = ["comparison", "=", reference(0, key), reference(1, key)]
        on = equal if on is None else ["logic", "AND", on, equal]
    if on is None and kind in ("LEFT", "RIGHT"):
        on = ["boolean", True]
    columns = [[reference(index, name), label] for index, name, label in fields]
    return {"dialect": "postgres", "release": tag, "source": join(kind, *sources, on), "columns": columns, "where": None}


def finite(tag, kind, mask, orientation, projection):
    inputs = (LEFT, RIGHT) if orientation == "left-first" else (RIGHT, LEFT)
    keys = MASKS[mask]
    fields = physical_fields(inputs) if projection == "physical" else visible_fields(inputs, kind, keys)
    return case(tag, inputs, kind, keys, fields)


def analyzed(tag):
    left, right, empty = (f"public.{name}" for name in (LEFT, RIGHT, EMPTY))
    inputs = (LEFT, RIGHT)
    keys, backwards = MASKS["both"], MASKS["both"][::-1]
    yield f"SELECT * FROM {left} a JOIN {right} b USING (lane,id)", case(
        tag, inputs, "INNER", backwards, visible_fields(inputs, "INNER", backwards))
    yield f"SELECT * FROM {left} a NATURAL INNER JOIN {right} b", case(
        tag, inputs, "INNER", keys, visible_fields(inputs, "INNER", keys))
    yield f"SELECT * FROM {left} a NATURAL RIGHT JOIN {right} b", case(
        tag, inputs, "RIGHT", keys, visible_fields(inputs, "RIGHT", keys))
    yield f"SELECT * FROM {left} a RIGHT JOIN {right} b ON a.id=b.id", case(
        tag, inputs, "RIGHT", ("id",), physical_fields(inputs))
    for kind in ("LEFT", "RIGHT"):
        yield f"SELECT a.*,b.* FROM {left} a NATURAL {kind} JOIN {right} b", case(
            tag, inputs, kind, keys, physical_fields(inputs))
    yield f"SELECT m.*, a.id AS left_id, b.id AS right_id FROM {left} a RIGHT JOIN {right} b USING (lane,id) AS m", case(
        tag, inputs, "RIGHT", backwards, [(1, "lane", "lane"), (1, "id", "id"), (0, "id", "left_id"), (1, "id", "right_id")])
    yield f"SELECT * FROM {left} NATURAL LEFT JOIN {empty}", case(
        tag, (LEFT, EMPTY), "LEFT", (), physical_fields((LEFT, EMPTY)))
    for prefix, only in (("ONLY ", True), ("", False)):
        yield f"SELECT * FROM {prefix}{left} NATURAL INNER JOIN {right}", case(
            tag, inputs, "INNER", keys, visible_fields(inputs, "INNER", keys), (only, False))
    yield f"SELECT a.id, b.id, id AS merged_id FROM {left} a RIGHT JOIN {right} b USING (id)", case(
        tag, inputs, "RIGHT", ("id",), [(0, "id", "id"), (1, "id", "id"), (1, "id", "merged_id")])


def analysis_request(tag, sql):
    return {"dialect": "postgres", "release": tag, "sql": sql, "declarations": DECLARATIONS}


def negatives(tag):
    bad_on = finite(tag, "LEFT", "id", "left-first", "visible")
    bad_on["source"]["on"][-1] = slot(0, 2)
    yield bad_on, {"exception": DIAGNOSTICS + "InvalidConstruction", "message": "visible input occurrence"}
    bad_field = finite(tag, "LEFT", "id", "left-first", "visible")
    bad_field["columns"][0][0] = slot(99, 1)
    yield bad_field, {"exception": DIAGNOSTICS + "InvalidConstruction", "message": "must belong to the SELECT source"}
    left, right, empty, wide = (f"public.{name}" for name in (LEFT, RIGHT, EMPTY, WIDE))
    for sql, exception, message in (
        (f"SELECT 1 FROM ({left} a JOIN {empty} c ON true) NATURAL JOIN {right} b", "InvalidConstruction", "requires two ordinary table inputs"),
        (f"SELECT 1 FROM {left} JOIN {right} USING(absent)", "SemanticException", "absent"),
        (f"SELECT 1 FROM {left} JOIN {wide} USING(id)", "InvalidConstruction", "signed 32-bit integer type"),
        (f"SELECT 1 FROM {left} JOIN {right} USING(id,id)", "SemanticException", "id"),
        (f"SELECT 1 FROM {left} FULL JOIN {right} USING(id) WHERE id=1", "InvalidConstruction", "not a physical int4 operand"),
    ):
        yield analysis_request(tag, sql), {"exception": DIAGNOSTICS + exception, "message": message}


def requests(_, tag, spec):
    bounded = spec["finite"]
    for index, layout in enumerate(product(bounded["joinKinds"], bounded["keys"], bounded["orientation"], bounded["projection"])):
        item = finite(tag, *layout)
        yield f"finite/{index}", item, item, None
    analyzed_cases = list(analyzed(tag))
    require(len(analyzed_cases) == len(spec["analysis"]), "incomplete-postgres-merged-analysis")
    for index, (sql, item) in enumerate(analyzed_cases):
        yield f"analyze/{index}", analysis_request(tag, sql), item, None
    for seed in spec["exploration"]["seeds"]:
        sample = random.Random(seed)
        for index in range(spec["exploration"]["casesPerSeed"]):
            inputs = tuple(sample.choices((LEFT, RIGHT), k=2))
            keys = sample.sample(("id", "lane"), sample.randrange(3))
            kind = sample.choice(KINDS)
            if not keys and kind == "INNER":
                kind = "CROSS"
            fields = [(*field[:2], sample.choice((field[2], "値", 'x " field')))
                      for field in sample.choices(physical_fields(inputs), k=sample.randrange(1, 8))]
            item = case(tag, inputs, kind, keys, fields, (sample.choice((True, False)), sample.choice((True, False))))
            yield f"seed/{seed}/{index}", item, item, None
    for index, (request, rule) in enumerate(negatives(tag)):
        yield f"negative/{index}", request, None, rule


def finite_ids(_, spec, _tag):
    fields = spec["finite"]
    return set(product(fields["joinKinds"], fields["keys"], fields["orientation"], fields["projection"]))


def finite_id(_, reply, _item):
    kind, names, condition, fields = graph(reply)
    mask = "both" if condition[0] == "logic" else condition[2][-1]
    direction = "left-first" if names[0][1] == LEFT else "right-first"
    return kind, mask, direction, "physical" if len(fields) == 6 else "visible"


def heading_and_rows(source):
    table = source["table"]
    descendants = DATA[CHILD] if table == LEFT and not source["only"] else []
    return HEADINGS[table], DATA[table] + descendants


def expected(item):
    return join_expected(item, heading_and_rows)


def check_value(item, reply):
    require(reply["status"] == "constructed", "postgres-merged-positive-rejected", reply)
    require(reply["profile"].startswith(item["release"] + "/") and reply["where"] is None
            and graph(reply) == graph(item), "postgres-merged-graph-differs", {"expected": item, "actual": reply})


def check_observation(item, actual, sql):
    prediction = expected(item)
    require(actual["status"] == "rows", "postgres-merged-native-rejection", {"sql": sql, "actual": actual})
    require(prediction == actual, "meaning-mismatch", {"sql": sql, "expected": prediction, "observed": actual})


def check_fault_witness(item, witness):
    same(expected(item), witness["expected"])
    require(witness["observed"]["status"] == "rows", "postgres-merged-fault-native")


def sample_forms(forms, reply, *_):
    kind, inputs, on, fields = graph(reply)
    forms[f"kind/{kind}"] += 1
    forms["predicate/" + ("none" if on is None else on[0])] += 1
    if inputs[0][:2] == inputs[1][:2]:
        forms["self"] += 1
    for _, _, only in inputs:
        forms[f"only/{only}"] += 1
    for term, name in fields:
        forms[f"occurrence/{term[1]}"] += 1
        if '"' in name:
            forms["quoted-label"] += 1


def required_forms(*_):
    return {"kind/CROSS", *(f"kind/{kind}" for kind in KINDS), "predicate/none", "predicate/boolean", "predicate/comparison",
            "predicate/logic", "self", "occurrence/0", "occurrence/1", "quoted-label", "only/True", "only/False"}


def controls():
    item = finite("pg-17.2", "RIGHT", "both", "left-first", "visible")
    prediction = expected(item)
    check_observation(item, prediction, "known preserved heading")
    altered = {**prediction, "fields": list(reversed(prediction["fields"]))}
    try:
        check_observation(item, altered, "wrong output order")
    except Failure as failure:
        require(failure.signal == "meaning-mismatch", "wrong-postgres-merged-control", failure.detail)
    else:
        raise Failure("postgres-merged-comparator-accepted")
    return {"healthy": prediction, "wrongOrder": altered}


def faults(_, tag):
    target = "sql-semantics-postgres/src/Ir/SelectSql.php"
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
