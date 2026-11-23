"""Define direct derived-join cases and evaluate their typed occurrence layouts."""

import json
from itertools import product
from random import Random

from server_cases import NAMES, SCHEMAS, observation
from server_compound_cases import leaf, without_profiles
from server_delete_cases import literal
from server_join_cases import DATA, NAMES_BY_TABLE, case, compare, declarations, expected as project
from server_join_cases import is_join, join, leaves, rows, slot, table, truth, value
from server_local_projection_cases import definition, mysql_materialized_tags

DEFINITIONS = (None, "accounts", "empty")
SIDES = ("left", "right")
KINDS = ("CROSS", "INNER", "LEFT", "RIGHT")
LAYOUTS = ((0, 1), (1, 0))


def derived(columns):
    return {"derived": columns}


def binary(dialect, tag, role, side, kind, occurrences):
    definition_ = definition(dialect, tag, role)
    named = table(dialect, "accounts")
    left, right = (derived(definition_), named) if side == "left" else (named, derived(definition_))
    on = None if kind == "CROSS" else compare("=", slot(0), slot(0, 1))
    return case(dialect, tag, join(kind, left, right, on), [[slot(1, index), f"r{i}"] for i, index in enumerate(occurrences)])


def finite_cases(dialect, tag):
    for role, side, kind, occurrences in product(DEFINITIONS, SIDES, KINDS, LAYOUTS):
        yield binary(dialect, tag, role, side, kind, occurrences)


def finite_id(_, response, _item):
    source = response["source"]
    side = next(side for side in SIDES if "derived" in source[side])
    inner = source[side]["derived"]
    external = inner["source"]
    role = None if external is None else {name: role for role, name in NAMES_BY_TABLE.items()}[external["table"]]
    return DEFINITIONS.index(role), side, source["kind"], tuple(expr[2] if len(expr) == 3 else 0 for expr, _ in response["columns"])


def finite_ids(_, specification, _tag):
    finite = specification["finite"]
    return set(product(range(len(finite["definitions"])), finite["sides"], finite["joinKinds"], LAYOUTS))


def seeded(dialect, tag, seed, count):
    rng = Random(seed)
    kinds = KINDS + (("FULL",) if dialect == "postgres" else ())

    def tree(size, require_derived):
        if size == 1:
            if not require_derived and not rng.getrandbits(1):
                return table(dialect, rng.choice(tuple(DATA)))
            role = rng.choice((None, *DATA))
            size = rng.randint(2, 4)
            inner = definition(dialect, tag, role, size)
            for expression in inner["columns"]:
                if rng.getrandbits(1):
                    expression[0] = literal(rng.choice((-1000000000, -5, 0, 2, 9, 1000000000)))
            inner["distinct"] = bool(rng.getrandbits(1))
            if role is not None and rng.getrandbits(1):
                inner["where"] = compare(">", slot(1), literal(rng.choice((0, 10, 25))))
            return derived(inner)
        split = rng.randint(1, size - 1)
        place_left = bool(rng.getrandbits(1))
        kind = rng.choice(kinds)
        on = None if kind == "CROSS" else compare("=", slot(rng.randrange(2), rng.randrange(split)), slot(rng.randrange(2), rng.randrange(split, size)))
        return join(kind, tree(split, require_derived and place_left), tree(size - split, require_derived and not place_left), on)

    for _ in range(count):
        size = rng.randint(2, 4)
        source = tree(size, True)
        inputs = leaves(source)
        indexes = [rng.randrange(size) for _ in range(rng.randint(2, 5))]
        fields = []
        for ordinal, occurrence in enumerate(indexes):
            width = len(inputs[occurrence].get("derived", inputs[occurrence])["columns"])
            fields.append([slot(rng.randrange(width), occurrence), f"r{ordinal}"])
        where = ["null-test", bool(rng.getrandbits(1)), slot(0, rng.randrange(size))] if rng.randrange(4) == 0 else None
        yield case(dialect, tag, source, fields, where)


def analyzed(dialect, tag):
    schema, key = SCHEMAS[dialect], NAMES[dialect][0]
    name = NAMES_BY_TABLE["accounts"]
    probe = NAMES_BY_TABLE["independent"]
    query = (f"SELECT q.y AS selected, a.{key} AS left_id FROM {schema}.{name} AS a LEFT JOIN "
             f"(SELECT {key} AS x, delta AS y FROM {schema}.{probe} WHERE delta > 0) AS q ON a.{key} = q.x")
    inner = leaf(dialect, tag, [[slot(1), "x"], [slot(0), "y"]], table(dialect, "independent", columns=["delta", key]),
                 compare(">", slot(0), literal(0)))
    source = join("LEFT", table(dialect, "accounts", columns=[key]), derived(inner), compare("=", slot(0), slot(0, 1)))
    yield {"dialect": dialect, "release": tag, "sql": query, "declarations": declarations(dialect)}, case(
        dialect, tag, source, [[slot(1, 1), "selected"], [slot(0), "left_id"]])
    query = "SELECT q2.y AS b, q1.y AS a FROM (SELECT 7 AS x, 8 AS y) AS q1 CROSS JOIN (SELECT 7 AS x, 9 AS y) AS q2"
    a = leaf(dialect, tag, [[literal(7), "x"], [literal(8), "y"]])
    b = leaf(dialect, tag, [[literal(7), "x"], [literal(9), "y"]])
    yield {"dialect": dialect, "release": tag, "sql": query, "declarations": []}, case(
        dialect, tag, join("CROSS", derived(a), derived(b)), [[slot(1, 1), "b"], [slot(1), "a"]])


def negatives(dialect, tag):
    spec = definition(dialect, tag, None)
    from_ = table(dialect, "accounts")
    fields = [[slot(0), "v"]]
    mismatch = {**spec, "release": "mysql-9.1.0" if dialect == "mysql" and tag != "mysql-9.1.0" else
                "mysql-8.4.7" if dialect == "mysql" else "pg-16.6" if tag != "pg-16.6" else "pg-17.2"}
    null_message = (
        "A derived join field requires a signed literal or a supported native column."
        if dialect == "postgres" else "requires a signed literal or a signed column"
    )
    yield case(dialect, tag, join("CROSS", from_, derived({**spec, "columns": [[["null"], "v"]]})), fields), rejection(dialect, null_message)
    yield case(dialect, tag, join("CROSS", from_, derived({**spec, "limit": [1, 0]})), fields), "must be unordered and unbounded"
    yield case(dialect, tag, join("CROSS", from_, derived({**spec, "source": join("CROSS", from_, from_)})), fields), "independent of any joined input"
    yield case(dialect, tag, join("CROSS", from_, derived(spec)), [[slot(2, 1), "v"]]), "must belong to the SELECT source"
    yield case(dialect, tag, join("LEFT", from_, derived(spec), compare("=", slot(0, 2), literal(0))), fields), "must belong to a visible input occurrence"
    yield case(dialect, tag, join("CROSS", from_, derived(mismatch)), fields), "must use its owning SELECT release"
    sql = "SELECT a.x AS v FROM (SELECT 7 AS x) a CROSS JOIN LATERAL (SELECT 8 AS y) q"
    yield {"dialect": dialect, "release": tag, "sql": sql, "declarations": []}, lateral_rejection(dialect, tag)


def lateral_rejection(dialect, tag):
    if dialect == "mysql" and tag.startswith(("mysql-5.6.", "mysql-5.7.")):
        return rejection(dialect, "Unexpected '(' at line 1, column 59, expected", "AnalysisException")
    return rejection(dialect, "A derived join input cannot be lateral.")


def rejection(dialect, message, exception="InvalidConstruction"):
    platform = "MySql" if dialect == "mysql" else "PostgreSql"
    namespace = f"SqlSemantics\\Platform\\{platform}\\Model\\Diagnostic"
    return {"exception": namespace + "\\" + exception, "message": message}


def requests(dialect, tag, specification):
    for index, item in enumerate(finite_cases(dialect, tag)):
        yield f"finite/{index}", item, item, None
    exploration = specification["exploration"]
    for seed in exploration["seeds"]:
        for index, item in enumerate(seeded(dialect, tag, seed, exploration["casesPerSeed"])):
            yield f"seed/{seed}/{index}", item, item, None
    for index, (item, meaning) in enumerate(analyzed(dialect, tag)):
        yield f"analysis/{index}", item, meaning, None
    for index, (item, reason) in enumerate(negatives(dialect, tag)):
        yield f"negative/{index}", item, None, reason


def select_definition(item):
    result = project(item)
    if item["distinct"]:
        result["rows"] = [json.loads(row) for row in dict.fromkeys(map(json.dumps, result["rows"]))]
    tag = item["release"]
    if item["dialect"] == "mysql" and (tag.startswith("mysql-5.6.") or item["source"] is None or item["distinct"]):
        types = mysql_materialized_tags(tag, item["columns"])
    else:
        types = [type_ for _, type_ in result["fields"]]
    return types, result["rows"]


def expected(item):
    dialect = item["dialect"]
    inputs = leaves(item["source"])

    def field_tag(expression):
        target = inputs[expression[2] if len(expression) == 3 else 0]
        return select_definition(target["derived"])[0][expression[1]] if "derived" in target else ("int4" if dialect == "postgres" else "LONG")

    raw_rows = rows(item["source"], dialect, lambda definition_: select_definition(definition_)[1])
    return observation({"status": "rows", "fields": [[name, field_tag(expr)] for expr, name in item["columns"]],
                        "rows": [[value(expr, row, dialect) for expr, _ in item["columns"]] for row in raw_rows
                                 if truth(item["where"], row, dialect)]})


def structural_model(item):
    result = without_profiles(item)

    def shape(source):
        if "derived" in source:
            return {"derived": without_profiles(source["derived"])}
        if is_join(source):
            return {**source, "left": shape(source["left"]), "right": shape(source["right"])}
        return source

    return {**result, "source": shape(result["source"])}
