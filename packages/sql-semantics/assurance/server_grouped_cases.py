"""Direct cases and independent grouping and aggregate semantics."""

from itertools import product
from random import Random

from run_server import grouped_admission
from server_cases import CHILD_ROWS, NAMES, ROWS, SCHEMAS, analyzed as projection_request, observation, source as projection_source
from server_compound_cases import legacy
from server_delete_cases import literal
from server_join_cases import NAMES_BY_TABLE, compare, declarations, rows, slot, table, truth
from server_wildcard_cases import LABEL_DDL, LABEL_RELATION, LABEL_ROWS, LABEL_SOURCE

OPERATORS = ("count", "sum", "min", "max")
POLICIES = (("count", False, False), ("count", True, False), ("count", True, True),
            ("sum", True, False), ("sum", True, True), ("min", True, False), ("max", True, False))
ROLES = ("accounts", "empty")
KEYS = ((), (0,), (1,), (0, 1))
FILTERS = (None, ["boolean", False])
ADDITIONAL_ROWS = ((2, 15), (3, 3), (3, -2147483648), (-2, 2147483647))


def aggregate(operator, argument=None, distinct=False):
    return ["aggregate", operator, argument, distinct]


def case(engine, release, source, keys, columns, where=None):
    return {"dialect": engine, "release": release, "grouped": {
        "source": source, "keys": keys, "columns": columns, "where": where,
        **({"orderBy": [], "limit": None} if engine == "postgres" else {}),
    }}


def finite_cases(engine, tag):
    for role, keys, (operator, uses_slot, distinct), where in product(ROLES, KEYS, POLICIES, FILTERS):
        source = table(engine, role)
        fields = [[slot(key), f"k{i}"] for i, key in enumerate(keys)]
        fields.append([aggregate(operator, slot(1) if uses_slot else None, distinct), "value"])
        yield case(engine, tag, source, [slot(key) for key in keys], fields, where)


def finite_id(_, response, _item):
    model = response["grouped"]
    role = next(role for role, name in NAMES_BY_TABLE.items() if name == model["source"]["table"])
    keys = tuple(key[1] for key in model["keys"])
    _, operator, argument, distinct = model["columns"][-1][0]
    return ROLES.index(role), keys, POLICIES.index((operator, argument is not None, distinct)), FILTERS.index(model["where"])


def finite_ids(_, specification, _tag):
    finite = specification["finite"]
    return set(product(range(len(finite["relations"])), map(tuple, finite["keyLayouts"]),
                       range(len(finite["argumentPolicies"])), range(len(finite["filters"]))))


def seeded(engine, tag, seed, count):
    rng = Random(seed)
    for _ in range(count):
        role = rng.choice(("accounts", "empty", "independent"))
        only = engine == "postgres" and bool(rng.getrandbits(1))
        keys = [slot(rng.randrange(2)) for _ in range(rng.randrange(5))]
        fields = []
        for index in range(rng.randint(1, 7)):
            operator = rng.choice(OPERATORS)
            operand = None if operator == "count" and rng.randrange(3) == 0 else slot(rng.randrange(2))
            value = aggregate(operator, operand, operand is not None and bool(rng.getrandbits(1)))
            if keys and rng.randrange(4) == 0:
                value = rng.choice(keys)
            fields.append([value, rng.choice(("again", "amount", f"r{index}"))])
        condition = rng.choice((None, ["null"], compare(">", slot(0), literal(1)),
                                ["null-test", bool(rng.getrandbits(1)), slot(rng.randrange(2))]))
        yield case(engine, tag, table(engine, role, only), keys, fields, condition)


def boundaries(engine, tag):
    source = table(engine, "accounts")
    yield case(engine, tag, source, [slot(0)] * 16, [[aggregate("count"), "n"]])
    yield case(engine, tag, source, [], [[aggregate("sum", slot(1)), "n"]] * 1024)
    yield case(engine, tag, source, [], [[aggregate("count", slot(1)), "c"], [aggregate("max", slot(1)), "m"]],
               ["null-test", False, slot(1)])


def analyzed(engine, tag):
    schema, key = SCHEMAS[engine], NAMES[engine][0]
    name = NAMES_BY_TABLE["accounts"]
    qualifier = 'pg_catalog.' if engine == "postgres" else ''
    source = table(engine, "accounts", columns=[])
    query = f"SELECT count(*) AS n FROM {schema}.{name}"
    yield {"dialect": engine, "release": tag, "sql": query, "declarations": declarations(engine)}, case(
        engine, tag, source, [], [[aggregate("count"), "n"]])
    source = table(engine, "accounts", engine == "postgres", [key, "delta"])
    only = 'ONLY ' if engine == "postgres" else ''
    query = (f"SELECT t.{key} AS k, {qualifier}count(DISTINCT t.delta) AS n, sum(t.delta) AS total, min(t.delta) AS m "
             f"FROM {only}{schema}.{name} t WHERE t.delta > -5 GROUP BY t.{key}")
    columns = [[slot(0), "k"], [aggregate("count", slot(1), True), "n"],
               [aggregate("sum", slot(1)), "total"], [aggregate("min", slot(1)), "m"]]
    yield {"dialect": engine, "release": tag, "sql": query, "declarations": declarations(engine)}, case(
        engine, tag, source, [slot(0)], columns, compare(">", slot(1), literal(-5)))
    admitted = grouped_admission()["admit"]
    projection = projection_source(engine)
    projection["columns"] = [key]
    query = admitted["sql"].format(table=f"{schema}.{projection['table']}")
    yield projection_request(engine, query, version=tag), case(
        engine, tag, projection, [slot(0)], [[slot(0), admitted["name"]]])
    if engine == "mysql":
        role = {"schema": schema, "table": LABEL_RELATION, "columns": [" lead"]}
        word = chr(96) + " lead" + chr(96)
        raw = f"SELECT {word}, count(*) AS n FROM {schema}.{LABEL_RELATION} GROUP BY {word}"
        yield {"dialect": engine, "release": tag, "sql": raw, "declarations": [LABEL_DDL]}, case(
            engine, tag, role, [slot(0)], [[slot(0), " lead"], [aggregate("count"), "n"]])


def negatives(engine, tag):
    source = table(engine, "accounts")
    count = [[aggregate("count"), "n"]]
    route = {"dialect": engine, "release": tag, "declarations": declarations(engine)}
    name = f"{SCHEMAS[engine]}.{NAMES_BY_TABLE['accounts']}"
    key = NAMES[engine][0]
    yield case(engine, tag, source, [], [[aggregate("count", None, True), "n"]]), "Only a nondistinct COUNT"
    yield case(engine, tag, source, [], [[aggregate("sum"), "n"]]), "Only a nondistinct COUNT"
    yield case(engine, tag, source, [], [[aggregate("max", slot(2)), "n"]]), "The aggregate slot must belong to the group input."
    yield case(engine, tag, source, [slot(0)], [[slot(1), "n"]]), "A plain grouped result must read a grouping key."
    yield case(engine, tag, source, [slot(2)], count), "Each grouping key must belong to its input."
    yield case(engine, tag, source, [], [[slot(0), "n"]]), "A plain grouped result must read a grouping key."
    yield {**route, "sql": f"SELECT count(*) AS n FROM {name} GROUP BY {key}+1"}, "The column must resolve to the projection source."
    yield case(engine, tag, source, [slot(0)] * 17, count), "count exceeds the grouped profile."
    yield case(engine, tag, source, [], count * 1025), "count exceeds the grouped profile."
    yield case(engine, tag, source, [], [[aggregate("min", slot(1, 1)), "n"]]), "argument must read its single input occurrence"
    window = f"SELECT count(*) OVER () AS n FROM {name}"
    if engine == "mysql" and legacy(tag):
        yield {**route, "sql": window}, {"message": "Unexpected '(' at line 1, column 22, expected ;", "exception": diagnostic(engine, "AnalysisException")}
    else:
        yield {**route, "sql": window}, "The grouped aggregate requires one supported builtin without a window"
    if engine == "postgres":
        yield {**route, "sql": f"SELECT count(delta) AS n FROM {name}"}, "exclusive pg_catalog function search path"
    else:
        yield case(engine, tag, source, [], [[aggregate("count"), " invalid"]]), "grouped result name cannot start"
        yield case(engine, tag, source, [slot(0)], [[slot(0), " invalid"]]), "must equal its physical input field"


def diagnostic(engine, name="InvalidConstruction"):
    platform = "MySql" if engine == "mysql" else "PostgreSql"
    return f"SqlSemantics\\Platform\\{platform}\\Model\\Diagnostic\\{name}"


def requests(engine, tag, specification):
    for index, item in enumerate(finite_cases(engine, tag)):
        yield f"finite/{index}", item, item, None
    for seed in specification["exploration"]["seeds"]:
        for index, item in enumerate(seeded(engine, tag, seed, specification["exploration"]["casesPerSeed"])):
            yield f"seed/{seed}/{index}", item, item, None
    for index, item in enumerate(boundaries(engine, tag)):
        yield f"boundary/{index}", item, item, None
    for index, (item, meaning) in enumerate(analyzed(engine, tag)):
        yield f"analysis/{index}", item, meaning, None
    for index, (item, boundary) in enumerate(negatives(engine, tag)):
        if isinstance(boundary, str):
            boundary = {"exception": diagnostic(engine), "message": boundary}
        yield f"negative/{index}", item, item, boundary


def field_value(expression, group, engine):
    if expression[0] == "slot":
        return group[0][expression[1]]
    _, operator, reference, distinct = expression
    if reference is None:
        return len(group)
    args = [row[reference[1]] for row in group if row[reference[1]] is not None]
    if distinct:
        args = set(args)
    if operator == "count":
        return len(args)
    if not args:
        return None
    if operator == "sum":
        total = sum(args)
        return str(total) if engine == "mysql" else total
    return {"min": min, "max": max}[operator](args)


def field_type(expression, engine):
    signed, wide, exact = ("LONG", "LONGLONG", "NEWDECIMAL") if engine == "mysql" else ("int4", "int8", "int8")
    if expression[0] == "slot":
        return signed
    return {"min": signed, "max": signed, "count": wide, "sum": exact}[expression[1]]


def ordered(item):
    return bool(item["grouped"]["keys"]) and legacy(item["release"])


def expected(item, admits=None):
    engine = item["dialect"]
    query = item["grouped"]
    keys = [key[1] for key in query["keys"]]
    groups = {} if keys else {(): []}
    for entry in fixture_rows(query["source"], engine):
        if truth(query["where"], entry, engine):
            row = entry[0]
            group_key = tuple(row[key] for key in keys)
            groups.setdefault(group_key, []).append(row)
    partition = sorted(groups, key=lambda values: tuple((value is not None, value) for value in values)) if ordered(item) else groups
    result = {"status": "rows", "fields": [[name, field_type(expr, engine)] for expr, name in query["columns"]],
              "rows": [[field_value(expr, groups[key], engine) for expr, _ in query["columns"]] for key in partition
                       if admits is None or admits(groups[key])]}
    return result if ordered(item) else observation(result)


def fixture_rows(source, engine):
    if source["table"] == projection_source(engine)["table"]:
        data = ROWS + (CHILD_ROWS if engine == "postgres" else [])
        yield from (((row[0],),) for row in data)
        return
    if source["table"] == LABEL_RELATION:
        positions = [LABEL_SOURCE.index(name) for name in source["columns"]]
        yield from ((tuple(row[position] for position in positions),) for row in LABEL_ROWS)
        return
    yield from rows(source, engine)
    if source["table"] == NAMES_BY_TABLE["accounts"]:
        positions = [NAMES[engine].index(name) for name in source["columns"]]
        yield from ((tuple(row[position] for position in positions),) for row in ADDITIONAL_ROWS)
