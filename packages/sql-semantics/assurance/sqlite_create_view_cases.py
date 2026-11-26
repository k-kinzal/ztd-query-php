"""Fixed direct and analyzer inputs for SQLite's checked view declarations."""

from copy import deepcopy
from itertools import product
import random

SCHEMAS = ("main", "temp")
TABLES = ("base", "peer")
KINDS = ("projection", "bound-table", "joined-tables", "values")


def storage(schema):
    return "INTEGER" if schema == "main" else "TEXT"


DDL = [f"CREATE {'TEMP ' if schema == 'temp' else ''}TABLE {table} (id INTEGER, v {storage(schema)})"
       for schema in SCHEMAS for table in TABLES]
DDL.append("CREATE TEMP TABLE temp_only (id INTEGER)")


def integer(value):
    return ["integer", str(value)]


def literal(value):
    return ["null"] if value is None else ["text", value] if isinstance(value, str) else integer(value)


def col(slot, source=0):
    return ["column", slot, source] if source else ["column", slot]


def source(schema, name="base", columns=("id", "v")):
    return [schema, name, list(columns)]


def projection(expressions, first=None, joins=(), where=None, order=(), limit=None):
    return {"operation": "select", "source": first, "columns": expressions,
            "joins": list(joins), "where": where, "orderBy": list(order), "limit": limit, "distinct": False}


def values(rows):
    return {"operation": "values", "rows": rows}


def fixture(skew):
    result = {}
    for schema in SCHEMAS:
        base = [11 + skew, 11 + skew, None, 7 + skew]
        peer = [21 + skew, 23 + skew, 23 + skew, None]
        if schema == "temp":
            base = [None if value is None else f"base-{value}" for value in base]
            peer = [None if value is None else f"peer-{value}" for value in peer]
        result[schema] = {"base": list(zip([1, 2, 3, 4], base)),
                          "peer": list(zip([1, 1, 3, 5], peer))}
    return result


def item(schema, query, columns, guard=False, occupied=False, name="observed_view", skew=0):
    return {"operation": "create-view", "schema": schema, "view": name, "ifNotExists": guard,
            "columns": columns, "query": query, "occupied": occupied, "data": fixture(skew)}


def make_query(kind, source_schema, sample=None):
    width = 3 if sample is None else sample.randrange(1, 7)
    if kind == "values":
        rows = [[None, 11, "keep"], [None, 11, "keep"], [3, None, "side"]]
        if sample is not None:
            rows = [[sample.choice((None, 0, 7, -4, "a", 'a"b')) for _ in range(width)]
                    for _ in range(sample.randrange(1, 8))]
        return values([[literal(value) for value in row] for row in rows])
    if kind == "projection":
        terms = [["arithmetic", "-", integer(13), integer(5)], ["null"], ["text", "registered"]]
        if sample is not None:
            terms = [[
                "arithmetic", sample.choice(("+", "-")), integer(sample.randrange(-80, 80)), integer(sample.randrange(-80, 80))
            ] if index % 2 == 0 else literal(sample.choice((None, 0, -1, "registered")))
                     for index in range(width)]
        return projection([[term, f"inner{index}"] for index, term in enumerate(terms)])
    first = source(source_schema)
    joins = []
    terms = [col(1), col(0)]
    if kind == "joined-tables":
        joins.append(["INNER", source(source_schema, "peer"),
                      ["comparison", "=", col(0), col(0, 1)]])
        terms.extend([col(1, 1), col(0, 1)])
    count = len(terms) if sample is None else width
    fields = [[terms[index if sample is None else sample.randrange(len(terms))], f"inner{index}"] for index in range(count)]
    return projection(fields, first, joins, ["comparison", ">=", col(0), integer(1 if sample is None else sample.randrange(1, 4))])


def width(query):
    return len(query["rows"][0]) if query["operation"] == "values" else len(query["columns"])


def finite_case(schema, kind, guard, occupied):
    query = make_query(kind, schema)
    names = [f"name-{index}" for index in range(width(query))]
    return item(schema, query, names, guard, occupied)


def finite_ids(domain):
    return set(product(domain["schemas"], domain["queries"], domain["guard"], domain["occupied"]))


def kind(query):
    if query["operation"] == "values":
        return "values"
    if query["joins"]:
        return "joined-tables"

    return "projection" if query["source"] is None else "bound-table"


def finite_id(measured, _):
    return measured["schema"], kind(measured["query"]), measured["ifNotExists"], measured["occupied"]


def analyzed(schema, sql, query, names, guard=False, occupied=False):
    return {"sql": sql, "declarations": DDL}, item(schema, query, names, guard, occupied)


def analyzed_cases():
    main = projection([[col(0), "v"]], source("main", columns=("v",)))
    temporary = projection([[col(0), "v"]], source("temp", columns=("v",)))
    yield analyzed("main", "CREATE VIEW observed_view AS SELECT v FROM base", main, ["v"])
    yield analyzed("temp", "CREATE TEMP VIEW observed_view AS SELECT v FROM base", temporary, ["v"])
    yield analyzed("temp", "CREATE VIEW temp.observed_view AS SELECT v FROM main.base", main, ["v"])
    names = ["column1", 'a"b', "", "X", "x:1"]
    q = projection([[integer(index), str(index)] for index in range(1, 6)])
    yield analyzed("main", 'CREATE VIEW observed_view("true", "a""b", "", "X", "x") AS SELECT 1,2,3,4,5', q, names)
    q = projection([[col(0), "v"], [col(0), "v"], [integer(7), "False"]], source("main", columns=("v",)))
    yield analyzed("main", 'CREATE VIEW observed_view AS SELECT base.v, v, 7 AS "False" FROM base', q, ["v", "v:1", "column3"])
    joined = projection([[col(0, 1), "v"], [col(0), "first"]],
                        source("main", columns=("v", "id")),
                        [["INNER", source("main", "peer", ("v", "id")),
                          ["comparison", "=", col(1), col(1, 1)]]])
    yield analyzed("main", "CREATE VIEW observed_view AS SELECT p.v, b.v AS first FROM base b JOIN peer p ON b.id=p.id",
                   joined, ["v", "first"])
    q = values([[["null"], integer(3)], [integer(4), integer(7)], [integer(4), integer(7)]])
    yield analyzed("main", "CREATE VIEW observed_view AS VALUES (NULL,3), (4,7), (4,7)", q, ["column1", "column2"])
    yield analyzed("temp", 'CREATE TEMP VIEW observed_view("second", "first") AS VALUES (NULL,3), (4,7), (4,7)',
                   q, ["second", "first"])
    yield analyzed("main", "CREATE VIEW IF NOT EXISTS observed_view AS SELECT v FROM base", main, ["v"], True, True)
    yield analyzed("temp", "CREATE TEMP VIEW IF NOT EXISTS observed_view AS SELECT v FROM base", temporary, ["v"], True, True)
    q = projection([[["cast", "TEXT", integer(54)], "c"]])
    yield analyzed("main", "CREATE VIEW observed_view AS SELECT CAST(54 AS TEXT) AS c", q, ["c"])
    q = projection([[col(0), "id"], [col(1), "v"]], source("main"), order=[[1, True, False]], limit=[integer(2), None, False])
    yield analyzed("main", "CREATE VIEW observed_view AS SELECT id, v FROM base ORDER BY 1 DESC LIMIT 2", q, ["id", "v"])


def requests(specification):
    domain = specification["finite"]
    for index, case in enumerate(product(domain["schemas"], domain["queries"], domain["guard"], domain["occupied"])):
        value = finite_case(*case)
        yield f"finite/{index}", value, value
    for index, (query, value) in enumerate(analyzed_cases()):
        yield f"analyze/{index}", query, value
    for seed in specification["exploration"]["seeds"]:
        sample = random.Random(seed)
        for index in range(specification["exploration"]["casesPerSeed"]):
            schema, query_kind = sample.choice(SCHEMAS), sample.choice(KINDS)
            query = make_query(query_kind, "main" if schema == "main" else sample.choice(SCHEMAS), sample)
            names = [f"n{slot}" if slot % 2 else f'n"{slot}' for slot in range(width(query))]
            value = item(schema, query, names, sample.choice((False, True)), sample.choice((False, True)),
                         f'view "{sample.randrange(10000)}', sample.randrange(1, 100))
            yield f"seed/{seed}/{index}", value, value


def negatives():
    base = finite_case("main", "projection", False, False)
    for change, reason in [
        ({"schema": ""}, "nonempty schema identifier"),
        ({"view": "SQLite_reserved"}, "reserved SQLite namespace"),
        ({"columns": ["first"]}, "match the query width"),
        ({"columns": ["x", "X", "other"]}, "must be unique"),
        ({"columns": ["True", "x", "other"]}, "effective name of a truth word"),
    ]:
        yield {**base, **change}, "rejected", reason
    for operand in [["parameter", "1"], ["folded-membership", False, ["07"]]]:
        yield item("main", projection([[operand, "name"]]), ["name"]), "rejected", "cannot contain a parameter"
    yield item("main", values([[integer(1)], [["parameter", "1"]]]), ["x"]), "rejected", "cannot contain a parameter"
    local = {**projection([[col(0), "x"]]), "local": {"names": ["a"], "rows": [[["parameter", "7"]]]}}
    yield item("main", local, ["x"]), "rejected", "cannot contain a parameter"
    joined = deepcopy(make_query("joined-tables", "main"))
    joined["joins"][0][1][0] = "temp"
    yield item("main", joined, [f"x{index}" for index in range(width(joined))]), "rejected", "tables in its schema"
    for sql, status, reason in [
        ("SELECT v FROM temp.base", "rejected", "tables in its schema"),
        ("SELECT id FROM temp_only", "analysis-rejected", "temp_only"),
        ("SELECT ?01", "rejected", "cannot contain a parameter"),
        ("SELECT (?07 IN ())", "rejected", "cannot contain a parameter"),
        ("SELECT absent FROM base", "analysis-rejected", "absent"),
        ("SELECT 1, 2", "analysis-rejected", "column"),
        ("SELECT 1 AS x,2 AS x,3 AS x,4 AS x,5 AS x,6 AS x", "rejected", "match the query width"),
    ]:
        declaration = "observed_view(A)" if sql == "SELECT 1, 2" else "observed_view"
        yield {"sql": f"CREATE VIEW {declaration} AS {sql}", "declarations": DDL}, status, reason
