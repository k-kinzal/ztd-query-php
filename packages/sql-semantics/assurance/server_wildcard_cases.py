"""Specify wildcard inputs and independently assign their expanded source positions."""

from itertools import product
from random import Random

from mysql_physical_text_admission import negative as mysql_text_negative
from run import require
from postgres_physical_text_admission import negative as postgres_text_negative
from server_cases import NAMES, SCHEMAS, observation
from server_delete_cases import literal
from server_join_cases import NAMES_BY_TABLE, case, compare, declarations, expected as projected, join, slot, table

RELATIONS = ("ordinary", "empty", "self-join")
PROJECTIONS = ("unqualified", "qualified", "mixed", "repeated")


def shape(engine, tag, relation, projection, reverse=False, where=None, join_operator="LEFT"):
    names = NAMES[engine]
    wide = relation == "self-join"
    sides = ("a", "b") if wide else ("a",)
    focus = sides[-1] if not reverse else sides[0]
    root = "accounts" if relation != "empty" else "empty"
    external = f"{SCHEMAS[engine]}.{NAMES_BY_TABLE[root]}"
    allocations = {side: [] for side in sides}

    def position(side, field):
        layout = allocations[side]
        if field not in layout:
            layout.append(field)
        return slot(layout.index(field), sides.index(side))

    if where is None:
        predicate, condition = None, ""
    else:
        side, field, bound = where
        predicate = compare(">", position(side, field), literal(bound))
        condition = f" WHERE {side}.{field} > {bound}"

    if projection == "unqualified":
        written = "*"
        fields = [(side, field, field) for side in sides for field in names]
    elif projection == "qualified":
        written = f"{focus}.*"
        fields = [(focus, field, field) for field in names]
    elif projection == "mixed":
        written = f"a.delta AS chosen, {focus}.*, a.{names[0]} AS tail"
        fields = [("a", "delta", "chosen"), *((focus, field, field) for field in names), ("a", names[0], "tail")]
    elif projection == "repeated":
        written = f"{focus}.*, a.*, {focus}.*"
        fields = [(side, field, field) for side in (focus, "a", focus) for field in names]
    else:
        raise ValueError(projection)
    columns = [[position(side, field), alias] for side, field, alias in fields]
    table_sql = f"{external} AS a"
    if wide:
        test = compare("<", position("a", names[0]), position("b", names[0]))
        table_sql += f" {join_operator} JOIN {external} AS b ON a.{names[0]} < b.{names[0]}"
        source = join(join_operator, table(engine, root, columns=allocations["a"]), table(engine, root, columns=allocations["b"]), test)
    else:
        source = table(engine, root, columns=allocations["a"])
    raw_sql = f"SELECT {written} FROM {table_sql}{condition}"
    item = {**case(engine, tag, source, columns, predicate), "wildcardSql": raw_sql, "projection": projection}
    return {"dialect": engine, "release": tag, "sql": raw_sql, "declarations": declarations(engine)}, item


def finite_cases(engine, tag):
    for relation, projection in product(RELATIONS, PROJECTIONS):
        yield shape(engine, tag, relation, projection)


def written_form(sql):
    column_list = sql.removeprefix("SELECT ").split(" FROM ", 1)[0]
    require("*" in column_list, "native-wildcard-input-not-exercised", sql)
    if column_list == "*":
        return "unqualified"
    if column_list.count("*") == 1:
        return "mixed" if "," in column_list else "qualified"
    return "repeated"


def finite_id(_, response, item):
    actual = response["source"]
    relation = "self-join" if "kind" in actual else "empty" if actual["table"] == NAMES_BY_TABLE["empty"] else "ordinary"
    mode = written_form(item["wildcardSql"])
    require(mode == item["projection"], "native-wildcard-form-mismatch", item["wildcardSql"])
    return relation, mode


def finite_ids(_, specification, _tag):
    finite = specification["finite"]
    return set(product(finite["relations"], finite["projections"]))


def seeded(engine, tag, seed, maximum):
    rng = Random(seed)
    for _ in range(maximum):
        relation = rng.choice(RELATIONS)
        predicate = None
        if rng.randrange(3) == 0:
            predicate = rng.choice(("a", "b") if relation == "self-join" else ("a",)), rng.choice(NAMES[engine]), rng.randint(-5, 9)
        yield shape(engine, tag, relation, rng.choice(PROJECTIONS), bool(rng.getrandbits(1)), predicate,
                    rng.choice(("LEFT", "RIGHT", "INNER")))


LABEL_RELATION = "ir_wildcard_labels"
LABEL_SOURCE = ["PA", " lead"]
LABEL_DDL = "CREATE TABLE ir_fixture." + LABEL_RELATION + " (PA INT, " + chr(96) + " lead" + chr(96) + " INT)"
LABEL_ROWS = ((1, 2), (3, None), (1, 2))


def named_slot(tag, columns, fields):
    item = case("mysql", tag, {"schema": "ir_fixture", "table": LABEL_RELATION, "columns": columns}, fields)
    return {**item, "labelBoundary": True}


def labels(tag):
    quote = chr(96)
    field = quote + " lead" + quote
    query = " FROM ir_fixture." + LABEL_RELATION
    ordered = [[slot(0), "PA"], [slot(1), " lead"]]
    queries = [
        ("SELECT *" + query, named_slot(tag, LABEL_SOURCE, ordered)),
        ("SELECT " + field + query, named_slot(tag, [" lead"], [[slot(0), " lead"]])),
        ("SELECT " + field + " AS " + field + query, named_slot(tag, [" lead"], [[slot(0), "lead"]])),
    ]
    for sql, item in queries:
        yield {"dialect": "mysql", "release": tag, "sql": sql, "declarations": [LABEL_DDL]}, item
    direct = named_slot(tag, LABEL_SOURCE, ordered)
    yield direct, direct


def expected(item):
    if not item.get("labelBoundary", False):
        return projected(item)
    positions = [LABEL_SOURCE.index(name) for name in item["source"]["columns"]]
    fields = [[name, "LONG"] for _, name in item["columns"]]
    return observation({"status": "rows", "fields": fields,
                        "rows": [[row[positions[column[1]]] for column, _ in item["columns"]] for row in LABEL_ROWS]})


def negatives(engine, tag):
    schema = SCHEMAS[engine]
    base = {"dialect": engine, "release": tag, "declarations": declarations(engine)}
    yield {**base, "sql": f"SELECT * FROM {schema}.unregistered_source"}, "unregistered_source"
    yield {**base, "sql": f"SELECT absent.* FROM {schema}.ir_join_accounts"}, "absent"
    kind, boundary = postgres_text_negative("wildcard") if engine == "postgres" else mysql_text_negative()
    yield {**base, "declarations": [f"CREATE TABLE {schema}.wrong_shape (payload {kind})"],
           "sql": f"SELECT * FROM {schema}.wrong_shape"}, boundary

    if engine == "mysql":
        yield named_slot(tag, LABEL_SOURCE, [[literal(1), " invalid"]]), "result name must begin with a native graphic character"
        yield named_slot(tag, LABEL_SOURCE, [[slot(0), " invalid"]]), "must equal its physical input field"


def requests(engine, tag, specification):
    for index, (sql, item) in enumerate(finite_cases(engine, tag)):
        yield f"finite/{index}", sql, item, None
    for seed in specification["exploration"]["seeds"]:
        for index, (sql, item) in enumerate(seeded(engine, tag, seed, specification["exploration"]["casesPerSeed"])):
            yield f"seed/{seed}/{index}", sql, item, None
    if engine == "mysql":
        for index, (sql, item) in enumerate(labels(tag)):
            yield f"label/{index}", sql, item, None
    for index, (sql, reason) in enumerate(negatives(engine, tag)):
        yield f"negative/{index}", sql, None, reason
