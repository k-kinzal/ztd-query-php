"""Compare scoped PostgreSQL text presence and outer join effects on result rows."""

from itertools import product
import json
import random
import sys

from run import TOOL, Failure, digest, require
from run_server import CONTRACT as PROJECTION, same
from run_server_join import OBSERVER, check_preparation, main, observer_request
from run_server_postgres_typed_columns import (
    DIAGNOSTICS, KINDS, LEFT, RIGHT, analysis_request, field, heading_and_rows, prepare, table,
)
from server_cases import observation
from server_join_cases import case, compare, expected as join_expected, is_join, join, leaves
from sqlite_domain import RenderFault

LABEL = "postgres-text-null-tests"
CONTRACT = "44beded1705fbf79c15ebfb252cce16faf6f9d38a771787b64b5956dd615955f"
SOURCES = {"ordinary": None, "left-join": "LEFT", "right-join": "RIGHT"}
KINDS_BY_SOURCE = {kind: source for source, kind in SOURCES.items()}


def specifications():
    path, profile = TOOL / "postgres-text-null-tests-v1.json", TOOL / "server-projection-v1.json"
    require(digest(path) == CONTRACT and digest(profile) == PROJECTION, "postgres-text-null-tests-contract-mismatch")
    return json.loads(path.read_text()), {"postgres": json.loads(profile.read_text())["profiles"]["postgres"]}


def null_test(term, negated=False):
    return ["null-test", negated, term]


def logic(operator, left, right=None):
    return ["logic", operator, left, right]


def query(tag, source_kind, placement, make_test, label="present", only=False):
    inputs = (LEFT,) if source_kind == "ordinary" else (LEFT, RIGHT)
    source = table(LEFT, only)
    predicate = make_test(inputs)
    columns = [[field(inputs, 0, "id"), "left_id"]]
    condition = predicate if placement == "where" else None
    if placement == "projection":
        columns.append([predicate, label])
    if len(inputs) == 2:
        columns.append([field(inputs, 1, "label"), "right_text"])
        keys = compare("=", field(inputs, 0, "id"), field(inputs, 1, "id"))
        on = logic("AND", keys, predicate) if placement == "on" else keys
        source = join(SOURCES[source_kind], source, table(RIGHT), on)
    return case("postgres", tag, source, columns, condition)


def finite(tag, source_kind, placement, polarity, name):
    def predicate(inputs):
        return null_test(field(inputs, len(inputs) - 1, name), polarity == "not-null")

    return query(tag, source_kind, placement, predicate)


def finite_ids(_, spec, _tag):
    b = spec["finite"]
    coordinates = {(source, placement, polarity, name)
                   for source in b["source"] for placement in b["placement"]
                   if placement != "on" or source != "ordinary"
                   for polarity in b["polarity"] for name in b["field"]}
    require(len(coordinates) == b["identities"], "wrong-postgres-text-null-tests-domain", coordinates)
    return coordinates


def typed_address(term, sources):
    role, ordinal, occurrence = term[0], term[1], term[2] if len(term) == 3 else 0
    layout = sources[occurrence]
    name = layout["columns"][ordinal]
    observed_kind = layout.get("columnKinds", ["int4"] * len(layout["columns"]))[ordinal]
    require(KINDS[name] == observed_kind == {"slot": "int4", "text-slot": "text"}[role],
            "postgres-text-null-tests-invalid-coordinate", [term, layout])
    return role, occurrence, name


def normalized(term, sources):
    if term is None:
        return None
    role = term[0]
    if role in ("slot", "text-slot"):
        return typed_address(term, sources)
    if role == "null-test":
        return role, term[1], normalized(term[2], sources)
    if role in ("logic", "comparison"):
        return role, term[1], normalized(term[2], sources), normalized(term[3], sources)
    require(role in ("null", "boolean", "integer"), "postgres-text-null-tests-unknown-term", term)
    return tuple(term)


def graph(item):
    source = item["source"]
    inputs = leaves(source)
    return (
        tuple((leaf["schema"], leaf["table"], leaf["only"]) for leaf in inputs),
        (source["kind"], normalized(source["on"], inputs)) if is_join(source) else None,
        tuple((normalized(expression, inputs), name) for expression, name in item["columns"]),
        normalized(item["where"], inputs),
    )


def finite_id(_, reply, _item):
    _, join_node, columns, predicate = graph(reply)
    projections = [expr for expr, _ in columns if expr[0] == "null-test"]
    position = "where" if predicate is not None else "projection" if projections else "on"
    if position == "projection":
        require(len(projections) == 1, "postgres-text-null-tests-finite-projection", projections)
        tested = projections[0]
    elif position == "on":
        require(join_node is not None and join_node[1][:2] == ("logic", "AND"),
                "postgres-text-null-tests-finite-on", join_node)
        key = join_node[1][2]
        require(key == ("comparison", "=", ("slot", 0, "id"), ("slot", 1, "id")),
                "postgres-text-null-tests-finite-join-key", join_node)
        tested = join_node[1][3]
    else:
        tested = predicate
    role, polarity, address = tested
    kind = KINDS_BY_SOURCE[join_node[0] if join_node else None]
    require(role == "null-test" and address[:2] == ("text-slot", 0 if join_node is None else 1),
            "postgres-text-null-tests-finite-operand", tested)
    return kind, position, "not-null" if polarity else "null", address[2]


def analyzed(tag):
    l, r = (f"public.{name}" for name in (LEFT, RIGHT))
    first = finite(tag, "ordinary", "projection", "null", "label")
    first["columns"].append([null_test(field((LEFT,), 0, "note"), True), "has_note"])
    yield f"SELECT id AS left_id,label IS NULL AS present,note IS NOT NULL AS has_note FROM {l}", first
    yield f"SELECT id AS left_id FROM {l} WHERE label IS NOT NULL", finite(tag, "ordinary", "where", "not-null", "label")
    yield (f"SELECT a.id AS left_id,b.label AS right_text FROM {l} a LEFT JOIN {r} b ON a.id=b.id WHERE b.note IS NULL",
           finite(tag, "left-join", "where", "null", "note"))
    yield (f"SELECT a.id AS left_id,b.label AS right_text FROM {l} a LEFT JOIN {r} b ON a.id=b.id AND b.label IS NOT NULL",
           finite(tag, "left-join", "on", "not-null", "label"))


def negatives(tag):
    projected = finite(tag, "ordinary", "projection", "null", "label")
    projected["columns"][1][0][2] = ["text-slot", 0, 0]
    yield projected, "A tested predicate slot must belong to the SELECT source."
    filtered = finite(tag, "ordinary", "where", "null", "label")
    filtered["where"][2][2] = 1
    yield filtered, "A predicate slot must belong to a visible input occurrence."
    on = finite(tag, "left-join", "on", "null", "label")
    on["source"]["on"][3][2][2] = 2
    yield on, "A predicate slot must belong to a visible input occurrence."
    wrong_kind = finite(tag, "left-join", "where", "null", "label")
    wrong_kind["where"][2] = ["text-slot", 1, 1]
    yield wrong_kind, "A predicate slot must belong to a visible input occurrence."


def exploratory(tag, seed, count):
    rng = random.Random(seed)

    def test(inputs, depth):
        if depth and rng.getrandbits(1):
            op = rng.choice(("NOT", "AND", "OR"))
            left = test(inputs, depth - 1)
            return logic(op, left, None if op == "NOT" else test(inputs, depth - 1))
        term = field(inputs, rng.randrange(len(inputs)), rng.choice(("label", "note")))
        return null_test(term, bool(rng.getrandbits(1)))

    for _ in range(count):
        source = rng.choice(tuple(SOURCES))
        placement = rng.choice(("projection", "where") if source == "ordinary" else ("projection", "where", "on"))
        # SELECT directly owns a presence test. Its operand may be a Boolean predicate.
        create = (lambda inputs: null_test(test(inputs, 3), bool(rng.getrandbits(1)))) if placement == "projection" else (
            lambda inputs: test(inputs, 3))
        yield query(tag, source, placement, create, rng.choice(('x " y', "present")), bool(rng.getrandbits(1)))


def requests(_, tag, spec):
    b = spec["finite"]
    coordinates = (c for c in product(b["source"], b["placement"], b["polarity"], b["field"])
                   if c[0] != "ordinary" or c[1] != "on")
    for index, coordinates_value in enumerate(coordinates):
        item = finite(tag, *coordinates_value)
        yield f"finite/{index}", item, item, None
    for index, (sql, item) in enumerate(analyzed(tag)):
        yield f"analyze/{index}", analysis_request(tag, sql), item, None
    for seed in spec["exploration"]["seeds"]:
        for index, item in enumerate(exploratory(tag, seed, spec["exploration"]["casesPerSeed"])):
            yield f"seed/{seed}/{index}", item, item, None
    for index, (item, reason) in enumerate(negatives(tag)):
        yield f"negative/{index}", item, None, {"exception": DIAGNOSTICS + "InvalidConstruction", "message": reason}


def expected(item):
    return join_expected(item, heading_and_rows)


def check_value(item, reply):
    require(reply["status"] == "constructed" and reply["profile"].startswith(item["release"] + "/"),
            "postgres-text-null-tests-positive-rejected", reply)
    require(graph(reply) == graph(item), "postgres-text-null-tests-binding-differs", {"expected": item, "actual": reply})


def check_observation(item, actual, sql):
    prediction = expected(item)
    require(actual["status"] == "rows", "postgres-text-null-tests-native-rejection", {"sql": sql, "actual": actual})
    require(prediction == actual, "meaning-mismatch", {"sql": sql, "expected": prediction, "observed": actual})


def check_fault_witness(item, witness):
    same(expected(item), witness["expected"])
    require(witness["observed"]["status"] == "rows", "postgres-text-null-tests-fault-native")


def sample_forms(forms, reply, *_):
    _, join_node, columns, predicate = graph(reply)

    def visit(node, placement, depth=0):
        if node is None:
            return
        if node[0] == "null-test":
            forms[f"polarity/{node[1]}"] += 1
            forms[f"placement/{placement}"] += 1
            if depth >= 2:
                forms["nested"] += 1
            visit(node[2], placement, depth + 1)
        elif node[0] == "logic":
            forms[f"logic/{node[1]}"] += 1
            visit(node[2], placement, depth + 1)
            visit(node[3], placement, depth + 1)
        elif node[0] == "text-slot":
            forms[f"occurrence/{node[1]}"] += 1
            forms[f"field/{node[2]}"] += 1

    for expression, _ in columns:
        if expression[0] == "null-test":
            visit(expression, "projection")
    visit(predicate, "where")
    if join_node is not None:
        visit(join_node[1], "on")
    forms[f"source/{join_node[0] if join_node else 'ordinary'}"] += 1


def required_forms(*_):
    return {"placement/projection", "placement/where", "placement/on", "polarity/True", "polarity/False",
            "occurrence/0", "occurrence/1", "field/label", "field/note", "logic/NOT", "logic/AND", "logic/OR",
            "source/LEFT", "source/RIGHT", "source/ordinary", "nested"}


def controls():
    item = finite("pg-17.2", "ordinary", "projection", "null", "label")
    prediction = expected(item)
    check_observation(item, prediction, "known Boolean text presence")
    altered = {**prediction, "fields": [[prediction["fields"][0][0], "int4"], ["present", "text"]]}
    try:
        check_observation(item, altered, "wrong native Boolean result kind")
    except Failure as failure:
        require(failure.signal == "meaning-mismatch", "postgres-text-null-tests-control-signal", failure.detail)
    else:
        raise Failure("postgres-text-null-tests-comparator-accepted")
    return {"healthy": prediction, "wrongBooleanKind": altered}


def faults(_, tag):
    target = "sql-semantics-postgres/src/Ir/SelectSql.php"
    ordinary = finite(tag, "ordinary", "projection", "null", "label")
    outer = finite(tag, "left-join", "where", "null", "label")
    return [
        RenderFault("invert-text-presence", target,
                    "$condition->negated ? ' IS NOT NULL)' : ' IS NULL)'",
                    "$condition->negated ? ' IS NULL)' : ' IS NOT NULL)'", ordinary, "meaning-mismatch"),
        RenderFault("test-the-other-text-slot", target, "$input->columns[$expression->slot]->quoted()",
                    "$input->columns[$expression instanceof TextColumnReference ? 3 : $expression->slot]->quoted()",
                    ordinary, "meaning-mismatch"),
        RenderFault("test-the-other-occurrence", target, "$this->alias($offset + $expression->occurrence)",
                    "$this->alias($offset)", outer, "meaning-mismatch"),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])
