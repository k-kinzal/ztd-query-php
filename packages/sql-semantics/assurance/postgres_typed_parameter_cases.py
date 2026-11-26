"""Independent signatures, cast chains and finite witnesses for native positional bindings."""

from collections import Counter
from itertools import product
from random import Random

from evidence_transcript import canonical
from postgres_int4_cases import EXCEPTION
from server_cases import analyzed, observation
from server_parameter_cases import case

OIDS = {"int4": "23", "int8": "20", "bool": "16"}
PARAMETER_OIDS = OIDS | {"text": "25"}
EDGES = {"int4": ("int4", "int8", "bool"), "int8": ("int8", "int4"), "bool": ("bool", "int4")}


def ref(position, input_type):
    return ["parameter", position] if input_type == "int4" else ["parameter", position, input_type]


def cast(target, value):
    return ["builtin-cast", target, value]


def convert_to(target, expression):
    return expression if output_type(expression) == target else cast(target, expression)


def select(tag, values, bindings, labels=None):
    return {**case("postgres", tag, values, bindings, labels), "distinct": False, "orderBy": [], "limit": None}


def pair(left, right):
    assert left["bindings"] == right["bindings"]
    return {key: left[key] for key in ("dialect", "release", "bindings")} | {
        "compound": {"operator": "UNION", "all": True, "left": left, "right": right},
    }


def shape(item):
    if "compound" in item:
        operation = item["compound"]
        return {"compound": {"operator": operation["operator"], "all": operation["all"],
                             "left": shape(operation["left"]), "right": shape(operation["right"])}}
    return {key: item[key] for key in ("columns", "source", "where", "distinct", "orderBy", "limit")}


def case_for(tag, original, target, placement, binding):
    parameter = ref(1, original)
    result = convert_to(target, parameter)
    bindings = [binding]
    if placement == "sole-projection":
        return select(tag, [result], bindings)
    if placement == "first-then-repeated":
        return select(tag, [parameter, result], bindings)
    return pair(select(tag, [result], bindings), select(tag, [result], bindings))


def finite(tag, specification):
    for original, bindings in specification["inputs"].items():
        for target, value, placement in product(specification["targets"][original], bindings, specification["placements"]):
            yield case_for(tag, original, target, placement, value)


def finite_id(_, response, item):
    graph = shape(response)
    return canonical(graph), canonical(item["bindings"])


def finite_ids(_, specification, _tag):
    data = specification["finite"]
    expected = set()

    def projection(columns):
        return {"columns": columns, "source": None, "where": None, "distinct": False, "orderBy": [], "limit": None}

    for source in OIDS:
        original = ["parameter", 1] + ([] if source == "int4" else [source])
        for target in EDGES[source]:
            result = original if target == source else ["builtin-cast", target, original]
            single = projection([[result, "a0"]])
            shapes = (single, projection([[original, "a0"], [result, "a1"]]),
                      {"compound": {"operator": "UNION", "all": True, "left": single, "right": single}})
            expected.update((canonical(node), canonical([value])) for node in shapes for value in data["inputs"][source])
    assert len(expected) == 120
    return expected


def seeded(tag, data, seed, count):
    rng = Random(seed)

    def chain(value, depth):
        for _ in range(depth):
            value = cast(rng.choice(EDGES[output_type(value)]), value)
        return value

    for _ in range(count):
        types = rng.choices(tuple(OIDS), k=rng.randrange(1, 5))
        bindings = [rng.choice(data["inputs"][item]) for item in types]
        slots = [ref(position, item) for position, item in enumerate(types, 1)]
        next_fields = slots + rng.choices(slots, k=rng.randrange(5))
        rng.shuffle(next_fields)
        expressions = [chain(slot, rng.randrange(5)) for slot in next_fields]
        left = select(tag, expressions, bindings)
        if rng.choice((False, True)):
            # Preserve legal native output categories independently of the left arm's values.
            right = []
            for expression, slot in zip(expressions, next_fields):
                target = output_type(expression)
                identity = output_type(slot)
                bridge = cast("int4", slot) if target not in EDGES[identity] else slot
                right.append(convert_to(target, bridge))
            yield pair(left, select(tag, right, bindings))
        else:
            yield left


def analyzed_cases(tag):
    integer, wide, boolean = ref(1, "int4"), ref(1, "int8"), ref(1, "bool")
    other = ref(2, "int4")
    yield "SELECT CAST($1 AS bigint) AS a0", select(tag, [wide], [2 ** 62])
    yield "SELECT $1::bigint AS a0, $1::integer AS a1", select(tag, [wide, cast("int4", wide)], [2147483648])
    yield "SELECT $1::integer AS a0, $1::bigint AS a1", select(tag, [integer, cast("int8", integer)], [-9])
    yield "SELECT $1::boolean AS a0, $1::integer AS a1", select(tag, [boolean, cast("int4", boolean)], [True])
    yield "SELECT CAST(CAST($1 AS bigint) AS integer) AS a0", select(tag, [cast("int4", wide)], [None])
    yield "SELECT ($2)::integer AS a0, $1::bigint AS a1, $2::bigint AS a2", select(tag, [other, wide, cast("int8", other)], [-(2 ** 63), 9])
    yield "SELECT $1::bigint AS a0 UNION ALL SELECT $1::integer AS a0", pair(
        select(tag, [wide], [9]), select(tag, [cast("int4", wide)], [9]))
    yield "SELECT $1::integer AS a0 UNION ALL SELECT $1::bigint AS a0", pair(
        select(tag, [integer], [None]), select(tag, [cast("int8", integer)], [None]))


def negatives(tag):
    integer, wide = ref(1, "int4"), ref(1, "int8")
    yield select(tag, [integer, wide], [0]), "parameter position must have one input type"
    yield pair(select(tag, [integer], [0]), select(tag, [wide], [0])), "parameter position must have one input type"
    yield select(tag, [ref(2, "int8")], [0, 0]), "contiguous bound slots"
    yield analyzed("postgres", "SELECT $1::bigint AS a0, $1::boolean AS a1", version=tag), "no direct cast between bigint and boolean"
    yield analyzed("postgres", "SELECT $1::boolean AS a0, $1::bigint AS a1", version=tag), "no direct cast between bigint and boolean"
    text = analyzed("postgres", "SELECT CAST($1 AS text) AS a0", schemas=False, version=tag)
    yield {**text, "complete": False}, "The cast must resolve to a scalar pg_catalog target."
    yield {**text, "declarations": ["CREATE TEMP TABLE text (x integer)"]}, "The cast must resolve to a scalar pg_catalog target."


def requests(_, tag, specification):
    data, exploration = specification["finite"], specification["exploration"]
    for index, record in enumerate(finite(tag, data)):
        yield f"finite/{index}", record, record, None
    for seed in exploration["seeds"]:
        for index, record in enumerate(seeded(tag, data, seed, exploration["casesPerSeed"])):
            yield f"seed/{seed}/{index}", record, record, None
    for index, (sql, record) in enumerate(analyzed_cases(tag)):
        yield f"analysis/{index}", analyzed("postgres", sql, version=tag), record, None
    for index, value in enumerate(("bound '値'", None)):
        request = analyzed("postgres", "SELECT CAST($1 AS text) AS a0", schemas=False, version=tag)
        yield f"closed-catalog-text/{index}", request, select(tag, [ref(1, "text")], [value]), None
    for index, (request, reason) in enumerate(negatives(tag)):
        yield f"negative/{index}", request, None, {"exception": EXCEPTION, "message": reason}


def output_type(expression):
    if expression[0] == "builtin-cast":
        return expression[1]
    assert expression[0] == "parameter"
    return expression[2] if len(expression) == 3 else "int4"


def inputs(item, signature=None):
    if signature is None:
        signature = {}
    if "compound" in item:
        for arm in ("left", "right"):
            inputs(item["compound"][arm], signature)
    else:
        for expression, _ in item["columns"]:
            while expression[0] == "builtin-cast":
                expression = expression[2]
            position = expression[1]
            original = output_type(expression)
            assert signature.setdefault(position, original) == original
    return signature


def evaluated(expression, bindings):
    if expression[0] == "parameter":
        return bindings[expression[1] - 1]
    value = evaluated(expression[2], bindings)
    if value is None:
        return None
    if expression[1] == "bool":
        return value != 0
    integer = int(value)
    if expression[1] == "int4" and not (-(2 ** 31) <= integer < 2 ** 31):
        raise OverflowError
    return integer


def measured(item, bindings):
    if "compound" in item:
        left, fields = measured(item["compound"]["left"], bindings)
        right, other = measured(item["compound"]["right"], bindings)
        for index, (_, kind) in enumerate(other):
            if kind != fields[index][1]:
                assert {kind, fields[index][1]} == {"int4", "int8"}
                fields[index][1] = "int8"
        return left + right, fields
    fields = [[label, output_type(expression)] for expression, label in item["columns"]]
    result = [evaluated(expression, bindings) for expression, _ in item["columns"]]
    return [result], fields


def expected(item):
    signature = inputs(item)
    bindings = item["bindings"]
    assert set(signature) == set(range(1, len(bindings) + 1))
    oids = [PARAMETER_OIDS[signature[index]] for index in range(1, len(bindings) + 1)]
    try:
        values, fields = measured(item, bindings)
    except OverflowError:
        return {"status": "db-error", "state": "22003", "inputOids": oids}
    return observation({"status": "rows", "rows": values, "fields": fields, "inputOids": oids})


def coverage(response):
    forms = Counter()

    def scan(node):
        if "compound" in node:
            forms["union"] += 1
            for side in ("left", "right"):
                scan(node["compound"][side])
            return
        repeated = set()
        for expression, _ in node["columns"]:
            forms["output/" + output_type(expression)] += 1
            depth = 0
            while expression[0] == "builtin-cast":
                expression = expression[2]
                depth += 1
            forms["input/" + output_type(expression)] += 1
            forms[f"depth/{depth}"] += 1
            forms[f"position/{expression[1]}"] += 1
            if expression[1] in repeated:
                forms["repeated-position"] += 1
            repeated.add(expression[1])

    scan(response)
    return forms
