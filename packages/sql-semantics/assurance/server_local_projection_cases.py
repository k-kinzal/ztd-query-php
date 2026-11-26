"""Evaluate a local projection by selecting named ordinals from independent rows."""

from itertools import product
import json
from random import Random

from server_cases import NAMES, SCHEMAS, observation
from server_compound_cases import leaf, legacy, without_profiles
from server_delete_cases import literal
from server_join_cases import NAMES_BY_TABLE, expected as source_expected, slot, table

LAYOUTS = ((0,), (1,), (0, 1), (1, 0), (1, 1))
SOURCES = (None, "accounts", "independent", "empty")
WIDTH_EDGES = (-100000000, -99999999, -9999999, 99999999, 999999999, 1000000000)


def case(engine, tag, definition, slots, names=None):
    if names is None:
        names = [f"r{index}" for index in range(len(slots))]
    return {"dialect": engine, "release": tag, "local": {"definition": definition, "columns": [list(pair) for pair in zip(slots, names)]}}


def definition(engine, tag, source, width=2):
    relation = table(engine, source) if source is not None else None
    expressions = [literal(-3), literal(9)] if relation is None else [slot(0), slot(1)]
    return leaf(engine, tag, [[expressions[i % len(expressions)], f"c{i}"] for i in range(width)], relation)


def finite_cases(engine, tag):
    for depth, source, outputs in product((1, 2), SOURCES, LAYOUTS):
        base = definition(engine, tag, source)
        if depth == 2:
            base = case(engine, tag, base, [1, 0])
        yield case(engine, tag, base, outputs)


def base_definition(local):
    base, depth = local["definition"], 1
    while "local" in base:
        base = base["local"]["definition"]
        depth += 1
    return base, depth


def finite_id(_, response, _item):
    local = response["local"]
    base, depth = base_definition(local)
    if depth == 2 and [slot for slot, _ in local["definition"]["local"]["columns"]] != [1, 0]:
        raise ValueError("The nested finite definition must reverse the immediate layout")
    source = base["source"]
    kind = 0 if source is None else tuple(NAMES_BY_TABLE.values()).index(source["table"]) + 1
    return depth, kind, tuple(slot for slot, _ in local["columns"])


def finite_ids(_, specification, _tag):
    finite = specification["finite"]
    return set(product(finite["depths"], range(len(finite["definitions"])), map(tuple, finite["layouts"])))


def seeded(engine, tag, seed, count):
    rng = Random(seed)
    for _ in range(count):
        width = rng.randint(1, 4)
        role = rng.choice(SOURCES)
        definition_ = definition(engine, tag, role, width)
        for field in definition_["columns"]:
            if rng.randrange(3) == 0:
                field[0] = rng.choice((literal(rng.randrange(-7, 8)), ["null"]))
        definition_["distinct"] = bool(rng.getrandbits(1))
        definition_["where"] = ["boolean", False] if role is not None and rng.randrange(4) == 0 else None
        positions = [rng.randrange(width) for _ in range(rng.randint(1, 6))]
        yield case(engine, tag, definition_, positions)


def nested_seeded(engine, tag, seed, count):
    rng = Random(seed + 40009)
    for item in seeded(engine, tag, seed, count):
        for _ in range(rng.randint(1, 7)):
            width = len(item["local"]["columns"])
            item = case(engine, tag, item, [rng.randrange(width) for _ in range(rng.randint(1, 6))])
        yield item


def analyzed(engine, tag):
    scalar = definition(engine, tag, None)
    schema, column = SCHEMAS[engine], NAMES[engine][0]
    name = NAMES_BY_TABLE["accounts"]
    declarations = [f"CREATE TABLE {schema}.{table} ({column} INTEGER, delta INTEGER)"
                    for table in (name, NAMES_BY_TABLE["independent"])]
    base = definition(engine, tag, "accounts")
    forms = [
        ("SELECT q.c1 AS r0, c0 AS r1, c1 AS r2 FROM (SELECT -3 AS c0, 9 AS c1) AS q", case(engine, tag, scalar, [1, 0, 1])),
        (f"SELECT local.c0 AS r0, local.c0 AS r1 FROM (SELECT {column} AS c0, delta AS c1 FROM {schema}.{name}) AS local",
         case(engine, tag, base, [0, 0])),
    ]
    inner = case(engine, tag, scalar, [1, 0], ["c0", "c1"])
    outer = case(engine, tag, inner, [1, 0, 1])
    consume = "SELECT oq.c1 AS r0, oq.c0 AS r1, oq.c1 AS r2 FROM "
    wrapped = "SELECT iq.c1 AS c0, iq.c0 AS c1 FROM "
    literals = "SELECT -3 AS c0, 9 AS c1"
    forms.append((consume + f"({wrapped}({literals}) AS iq) AS oq", outer))
    if not legacy(tag):
        alias = NAMES_BY_TABLE["independent"]
        forms.append((f"WITH {alias}(selected, extra) AS (SELECT {column} AS c0, delta AS c1 FROM {schema}.{name}) "
                      f"SELECT extra AS r0, {alias}.selected AS r1, extra AS r2 FROM {alias}",
                      case(engine, tag, base, [1, 0, 1])))
        forms.append((f"WITH oq AS (WITH iq AS ({literals}) {wrapped}iq) {consume}oq", outer))
    if engine == "mysql":
        fields = [
            ("000000001", ["integer", "1", 8, False]),
            ("-00000001", ["integer", "-1", 7, False]),
            ("-000000001", ["integer", "-1", 8, False]),
            ("-0000000000", ["integer", "0", 9, True]),
            ("0" * 40 + "1", ["integer", "1", 32, False]),
        ]
        query = ", ".join(f"{spelling} AS c{i}" for i, (spelling, _) in enumerate(fields))
        output = ", ".join(f"q.c{i} AS r{i}" for i in range(len(fields)))
        padded = leaf(engine, tag, [[field, f"c{i}"] for i, (_, field) in enumerate(fields)])
        forms.append((f"SELECT {output} FROM (SELECT {query}) AS q", case(engine, tag, padded, range(len(fields)))))
    for sql, item in forms:
        yield {"dialect": engine, "release": tag, "sql": sql, "declarations": declarations}, item


def metadata_boundaries(engine, tag):
    for source, distinct in product((None, "accounts"), (False, True)):
        relation = None if source is None else table(engine, source)
        fields = [literal(value) for value in WIDTH_EDGES] + [["null"], ["boolean", True]]
        if relation is not None:
            fields.append(slot(0))
        definition_ = leaf(engine, tag, [[field, f"c{i}"] for i, field in enumerate(fields)], relation)
        definition_["distinct"] = distinct
        yield case(engine, tag, definition_, range(len(fields)))


def negatives(engine, tag):
    scalar = definition(engine, tag, None)
    yield case(engine, tag, scalar, []), "nonempty ordered result"
    yield case(engine, tag, scalar, [-1]), "must be nonnegative"
    yield case(engine, tag, scalar, [2]), "must belong to the definition"
    yield case(engine, tag, {**scalar, "limit": [1, 0]}, [0]), "unbounded projection"
    yield {"dialect": engine, "release": tag, "declarations": [],
           "sql": "SELECT q.x+1 AS r0 FROM (SELECT 1 AS x) AS q"}, "read a resolved local slot"
    yield case(engine, tag, case(engine, tag, scalar, [2]), [0]), "must belong to the definition"
    yield chain(engine, tag, scalar, 17), "at most sixteen definition boundaries"
    if engine == "mysql":
        padding = leaf(engine, tag, [[["integer", "1", -1, False], "c0"]])
        yield case(engine, tag, padding, [0]), "effective field width"
        padding = leaf(engine, tag, [[["integer", "1", 33, False], "c0"]])
        yield case(engine, tag, padding, [0]), "effective field width"
        sign = leaf(engine, tag, [[["integer", "1", 0, True], "c0"]])
        yield case(engine, tag, sign, [0]), "negative sign applies only to zero"


def requests(engine, tag, specification):
    for i, item in enumerate(finite_cases(engine, tag)):
        yield f"finite/{i}", item, item, None
    for seed in specification["exploration"]["seeds"]:
        for i, item in enumerate(seeded(engine, tag, seed, specification["exploration"]["casesPerSeed"])):
            yield f"seed/{seed}/{i}", item, item, None
        for i, item in enumerate(nested_seeded(engine, tag, seed, specification["exploration"]["nestedCasesPerSeed"])):
            yield f"seed/{seed}/nested/{i}", item, item, None
    for i, (request, item) in enumerate(analyzed(engine, tag)):
        yield f"analyzed/{i}", request, item, None
    for i, item in enumerate(metadata_boundaries(engine, tag)):
        yield f"metadata/{i}", item, item, None
    depth = chain(engine, tag, definition(engine, tag, None), 16)
    yield "depth/16", depth, depth, None
    for i, (item, signal) in enumerate(negatives(engine, tag)):
        yield f"negative/{i}", item, item, signal


def chain(engine, tag, base, depth):
    for _ in range(depth):
        base = case(engine, tag, base, [1, 0])
    return base


def mysql_materialized_tags(tag, columns):
    integer_width = 9 if legacy(tag) else 8
    null = "VAR_STRING" if tag.startswith(("mysql-8.4.", "mysql-9.")) else "STRING"

    def field(expression):
        if expression[0] == "null":
            return null
        if expression[0] == "boolean" or expression[0] == "slot":
            return "LONG"
        if expression[0] == "integer":
            decimal = expression[1]
            padding, negative_zero = expression[2:] if len(expression) == 4 else (0, False)
            sign = legacy(tag) and (decimal.startswith("-") or negative_zero)
            width = len(decimal.lstrip("-")) + padding + sign
            return "LONG" if width <= integer_width else "LONGLONG"
        raise ValueError(f"Unregistered materialized expression: {expression!r}")

    return [field(expression) for expression, _ in columns]


def expected(item):
    local = item["local"]
    definition_ = local["definition"]
    nested = "local" in definition_
    comparison = expected(definition_) if nested else source_expected(definition_)
    data = comparison["rows"]
    if not nested and definition_["distinct"]:
        data = [json.loads(row) for row in dict.fromkeys(json.dumps(row) for row in data)]
    columns = local["columns"]
    types = [tag for _, tag in comparison["fields"]]
    tag = item["release"]
    if not nested and item["dialect"] == "mysql" and (tag.startswith("mysql-5.6.") or definition_["source"] is None or definition_["distinct"]):
        types = mysql_materialized_tags(tag, definition_["columns"])
    return observation({"status": "rows", "fields": [[name, types[slot]] for slot, name in columns],
                        "rows": [[row[slot] for slot, _ in columns] for row in data]})


def structural_model(item):
    local = item["local"]
    definition_ = local["definition"]
    shape = {"local": structural_model(definition_)} if "local" in definition_ else without_profiles(definition_)
    return {**local, "definition": shape}
