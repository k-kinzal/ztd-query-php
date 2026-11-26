"""Compare exported PostgreSQL text positions in independently defined join operands."""

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
from server_join_cases import (
    case, compare, expected as join_expected, is_join, join, leaves, rows, slot, truth, value,
)
from sqlite_domain import RenderFault

LABEL = "postgres-derived-text"
CONTRACT = "642645f03a2d0bee616130899a8e909d4ff0c45263d456ca619819d1923cbda1"


def specifications():
    path, profile = TOOL / "postgres-derived-text-v1.json", TOOL / "server-projection-v1.json"
    require(digest(path) == CONTRACT and digest(profile) == PROJECTION, "postgres-derived-text-contract-mismatch")
    return json.loads(path.read_text()), {"postgres": json.loads(profile.read_text())["profiles"]["postgres"]}


def text_slot(ordinal, occurrence):
    return ["text-slot", ordinal, occurrence]


def definition(tag, physical, columns, filtered=False, distinct=False, only=False):
    source = table(physical, only)
    where = ["null-test", True, field((physical,), 0, "label")] if filtered else None
    query = case("postgres", tag, source, [[field((physical,), 0, name), alias] for name, alias in columns], where)
    return {"derived": {**query, "distinct": distinct, "orderBy": [], "limit": None}}


def mixed(tag, physical, text_first, filtered=False):
    fields = [("label", "message"), ("id", "id")]
    return definition(tag, physical, fields if text_first else fields[::-1], filtered)


def finite(tag, kind, side, order, filter_):
    text_first = order == "text-first"
    left, right = (mixed(tag, LEFT, text_first, filter_ != "unfiltered"), table(RIGHT)) if side == "left" else (
        table(LEFT), mixed(tag, RIGHT, text_first, filter_ != "unfiltered"))
    number = 0 if side == "left" else 1
    nested_key = 1 if text_first else 0
    key = (slot(nested_key), field((LEFT, RIGHT), 1, "id")) if number == 0 else (
        field((LEFT,), 0, "id"), slot(nested_key, 1))
    source = join(kind.upper(), left, right, None if kind == "cross" else compare("=", *key))
    text = text_slot(0 if text_first else 1, number)
    return case("postgres", tag, source, [[text, "result"], [slot(nested_key, number), "derived_id"],
                                          [["null-test", False, text], "absent"]])


def finite_ids(_, spec, _tag):
    b = spec["finite"]
    coordinates = set(product(b["join"], b["derivedSide"], b["definitionOrder"], b["filter"]))
    require(len(coordinates) == b["identities"], "postgres-derived-text-finite-size", coordinates)
    return coordinates


def native_kind(expression):
    return {"slot": "int4", "integer": "int4", "text-slot": "text", "null-test": "bool"}[expression[0]]


def typed_address(term, sources):
    ordinal, occurrence = term[1], term[2] if len(term) == 3 else 0
    owner = sources[occurrence]
    if "derived" in owner:
        kind = native_kind(owner["derived"]["columns"][ordinal][0])
        selected = ordinal
    else:
        selected = owner["columns"][ordinal]
        kind = owner.get("columnKinds", ["int4"] * len(owner["columns"]))[ordinal]
        require(kind == KINDS[selected], "postgres-derived-text-external-native-kind", owner)
    require(kind == native_kind(term), "postgres-derived-text-export-kind", [term, owner])
    return term[0], occurrence, selected


def expression(term, inputs):
    if term is None:
        return None
    role = term[0]
    if role in ("slot", "text-slot"):
        return typed_address(term, inputs)
    if role == "null-test":
        return role, term[1], expression(term[2], inputs)
    if role == "comparison":
        return role, term[1], expression(term[2], inputs), expression(term[3], inputs)
    require(role in ("integer", "boolean", "null"), "postgres-derived-text-unknown-role", term)
    return tuple(term)


def graph(item):
    def origin(source):
        if "derived" in source:
            return "derived", graph(source["derived"])
        if is_join(source):
            return source["kind"], origin(source["left"]), origin(source["right"]), expression(source["on"], leaves(source))
        return source["schema"], source["table"], source["only"]

    source = item["source"]
    visible = leaves(source)
    return (origin(source), tuple((expression(expr, visible), label) for expr, label in item["columns"]),
            expression(item["where"], visible), item.get("distinct", False))


def finite_id(_, reply, _item):
    source = reply["source"]
    derived_sides = [side for side in ("left", "right") if "derived" in source[side]]
    require(len(derived_sides) == 1, "postgres-derived-text-finite-side", source)
    side = derived_sides[0]
    inner = source[side]["derived"]
    order = tuple(native_kind(term) for term, _ in inner["columns"])
    order_name = {("text", "int4"): "text-first", ("int4", "text"): "integer-first"}[order]
    return source["kind"].lower(), side, order_name, "unfiltered" if inner["where"] is None else "only-nonnull"


def analyzed(tag):
    l, r = (f"public.{name}" for name in (LEFT, RIGHT))
    nested = mixed(tag, RIGHT, True)
    conventional = join("CROSS", table(LEFT), nested)
    left_join = join("LEFT", table(LEFT), mixed(tag, RIGHT, True, filtered=True),
                     compare("=", field((LEFT,), 0, "id"), slot(1, 1)))
    yield (f"SELECT q.message AS first,message AS again FROM {l} l CROSS JOIN (SELECT label AS message,id FROM {r}) q",
           case("postgres", tag, conventional, [[text_slot(0, 1), "first"], [text_slot(0, 1), "again"]]))
    yield (f"SELECT q.message AS value,l.id AS id FROM {l} l LEFT JOIN "
           f"(SELECT label AS message,id FROM {r} WHERE label IS NOT NULL) q ON q.id=l.id",
           case("postgres", tag, {**left_join, "on": compare("=", slot(1, 1), field((LEFT,), 0, "id"))},
                [[text_slot(0, 1), "value"], [field((LEFT,), 0, "id"), "id"]]))
    yield (f"SELECT q.message AS value FROM {l} l LEFT JOIN "
           f"(SELECT label AS message,id FROM {r} WHERE label IS NOT NULL) q ON l.id=q.id WHERE q.message IS NULL",
           case("postgres", tag, left_join, [[text_slot(0, 1), "value"]], ["null-test", False, text_slot(0, 1)]))
    yield (f"SELECT a.message AS a,b.message AS b FROM (SELECT label AS message,id FROM {l}) a "
           f"CROSS JOIN (SELECT label AS message,id FROM {r}) b",
           case("postgres", tag, join("CROSS", mixed(tag, LEFT, True), nested),
                [[text_slot(0, 0), "a"], [text_slot(0, 1), "b"]]))


def negatives(tag):
    first = finite(tag, "cross", "right", "text-first", "unfiltered")
    first["columns"][0][0] = slot(0, 1)
    yield first, "Column slot must belong to the SELECT source."
    second = finite(tag, "cross", "right", "text-first", "unfiltered")
    second["columns"][0][0] = text_slot(1, 1)
    yield second, "A text slot must belong to the SELECT source with its native kind."
    third = finite(tag, "cross", "right", "text-first", "unfiltered")
    third["where"] = ["null-test", True, text_slot(2, 1)]
    yield third, "A predicate slot must belong to a visible input occurrence."
    fourth = finite(tag, "left", "right", "text-first", "unfiltered")
    fourth["source"]["on"] = compare("=", slot(0), slot(0, 1))
    yield fourth, "A predicate slot must belong to a visible input occurrence."


def exploratory(tag, seed, count):
    rng = random.Random(seed)

    def source(physical, derived):
        if not derived:
            return table(physical, bool(rng.getrandbits(1))), field((physical,), 0, "id")[1]
        columns = [("id", "id"), ("label", "duplicate")]
        columns += [(rng.choice(("label", "note", "weight")), "duplicate") for _ in range(rng.randrange(5))]
        rng.shuffle(columns)
        return (definition(tag, physical, columns, bool(rng.getrandbits(1)), bool(rng.getrandbits(1)),
                           bool(rng.getrandbits(1))), columns.index(("id", "id")))

    for _ in range(count):
        derived_sides = rng.choice(((True, False), (False, True), (True, True)))
        left, left_key = source(LEFT, derived_sides[0])
        right, right_key = source(RIGHT, derived_sides[1])
        kind = rng.choice(("CROSS", "LEFT", "RIGHT"))
        on = None if kind == "CROSS" else compare("=", slot(left_key), slot(right_key, 1))
        pool = []
        for number, input_ in enumerate((left, right)):
            layout = input_.get("derived")
            if layout:
                for i, (term, _) in enumerate(layout["columns"]):
                    pool.append(text_slot(i, number) if native_kind(term) == "text" else slot(i, number))
            else:
                physical = (LEFT, RIGHT)[number]
                pool.extend(field((LEFT, RIGHT), number, column) for column in input_["columns"])
        text = rng.choice([term for term in pool if term[0] == "text-slot"])
        fields = [[text, "native"], [text, "again"]]
        fields += [[term, rng.choice(("same", '値 " x'))] for term in rng.choices(pool, k=rng.randint(1, 5))]
        condition = ["null-test", bool(rng.getrandbits(1)), text] if rng.getrandbits(1) else None
        yield case("postgres", tag, join(kind, left, right, on), fields, condition)


def requests(_, tag, spec):
    b = spec["finite"]
    for index, cell in enumerate(product(b["join"], b["derivedSide"], b["definitionOrder"], b["filter"])):
        item = finite(tag, *cell)
        yield f"finite/{index}", item, item, None
    for index, (sql, item) in enumerate(analyzed(tag)):
        yield f"analyze/{index}", analysis_request(tag, sql), item, None
    for seed in spec["exploration"]["seeds"]:
        for index, item in enumerate(exploratory(tag, seed, spec["exploration"]["casesPerSeed"])):
            yield f"seed/{seed}/{index}", item, item, None
    for index, (item, reason) in enumerate(negatives(tag)):
        yield f"negative/{index}", item, None, {"exception": DIAGNOSTICS + "InvalidConstruction", "message": reason}


def expected(item):
    def materialize(query):
        values = join_expected(query, heading_and_rows)["rows"]
        if query.get("distinct", False):
            return [json.loads(value_) for value_ in dict.fromkeys(map(json.dumps, values))]
        return values

    visible_rows = rows(item["source"], "postgres", materialize, heading_and_rows)
    fields = [[name, native_kind(expr)] for expr, name in item["columns"]]
    projected = [[value(expr, row, "postgres") for expr, _ in item["columns"]] for row in visible_rows
                 if truth(item["where"], row, "postgres")]
    return observation({"status": "rows", "fields": fields, "rows": projected})


def check_value(item, reply):
    require(reply["status"] == "constructed" and reply["profile"].startswith(item["release"] + "/"),
            "postgres-derived-text-positive-rejected", reply)
    require(graph(item) == graph(reply), "postgres-derived-text-graph-differs", {"expected": item, "actual": reply})


def check_observation(item, actual, sql):
    prediction = expected(item)
    require(actual["status"] == "rows", "postgres-derived-text-native-rejection", {"sql": sql, "actual": actual})
    require(prediction == actual, "meaning-mismatch", {"sql": sql, "expected": prediction, "observed": actual})


def check_fault_witness(item, witness):
    same(expected(item), witness["expected"])
    require(witness["observed"]["status"] == "rows", "postgres-derived-text-fault-native")


def sample_forms(forms, reply, *_):
    inputs = leaves(reply["source"])
    seen = 0
    for position, input_ in enumerate(inputs):
        child = input_.get("derived")
        if not child:
            continue
        seen += 1
        forms[f"derived-side/{position}"] += 1
        forms[f"distinct/{child['distinct']}"] += 1
        roles = [native_kind(term) for term, _ in child["columns"]]
        forms[f"first/{roles[0]}"] += 1
        if len(roles) >= 3 and len({label for _, label in child["columns"]}) < len(roles):
            forms["repeated-heading"] += 1
    forms[f"derived-count/{seen}"] += 1
    forms[f"kind/{reply['source']['kind']}"] += 1
    forms[f"filter/{reply['where'] is not None}"] += 1


def required_forms(*_):
    return {"derived-side/0", "derived-side/1", "derived-count/1", "derived-count/2", "kind/CROSS", "kind/LEFT",
            "kind/RIGHT", "first/text", "first/int4", "distinct/True", "distinct/False", "repeated-heading",
            "filter/True", "filter/False"}


def controls():
    item = finite("pg-17.2", "left", "right", "text-first", "unfiltered")
    prediction = expected(item)
    check_observation(item, prediction, "known derived text")
    altered = {**prediction, "fields": [["result", "int4"], *prediction["fields"][1:]]}
    try:
        check_observation(item, altered, "derived text became integer")
    except Failure as failure:
        require(failure.signal == "meaning-mismatch", "postgres-derived-text-control-signal", failure.detail)
    else:
        raise Failure("postgres-derived-text-comparator-accepted")
    return {"healthy": prediction, "wrongDerivedKind": altered}


def faults(_, tag):
    target = "sql-semantics-postgres/src/Ir/SelectSql.php"
    dual = join("CROSS", mixed(tag, LEFT, True), mixed(tag, RIGHT, True))
    witness = case("postgres", tag, dual, [[text_slot(0, 1), "value"]])
    return [
        RenderFault("wrong-derived-text-slot", target, "$this->localColumn($expression->slot)",
                    "$this->localColumn($expression instanceof TextColumnReference ? 1 : $expression->slot)",
                    finite(tag, "cross", "right", "text-first", "unfiltered"), "meaning-mismatch"),
        RenderFault("wrong-derived-text-occurrence", target, "$this->alias($offset + $expression->occurrence)",
                    "$this->alias($offset)", witness, "meaning-mismatch"),
        RenderFault("discard-nested-text-test", target, "if ($select->where !== null)", "if (false)",
                    finite(tag, "left", "right", "text-first", "only-nonnull"), "meaning-mismatch"),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])
