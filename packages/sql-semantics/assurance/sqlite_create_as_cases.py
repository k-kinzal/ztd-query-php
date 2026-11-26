"""Define source-free population queries and independently derive created table facts."""

from itertools import product
from random import Random

from cases import case as select_case
from run_sqlite_create_table import DECLARATIONS, EXISTING, FIXTURE
from run_sqlite_scalars import evaluate
from run_sqlite_values import case as value_case, names as value_bindings
from sqlite_default_cases import value_at_affinity
from sqlite_engine import typed
from sqlite_relational_cases import parameter_names, truth

TABLE = "ir_created"
DIMENSIONS = ("schemas", "guards", "forms", "widths", "affinityRoles", "inputs")
SPELLINGS = {"INTEGER": "INT", "NUMERIC": "NUM", "REAL": "REAL", "TEXT": "TEXT", "BLOB": "", "FLEXNUM": "NUM", None: ""}
NUMERIC = frozenset(("INTEGER", "NUMERIC", "REAL"))
FOLD = str.maketrans("ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz")


def record(query, schema="main", guard=False, table=TABLE):
    return {"operation": "create-as", "schema": schema, "table": table, "ifNotExists": guard, "query": query}


def derived(affinity, operand):
    return operand if affinity == "none" else ["cast", affinity.upper(), operand]


def finite_cases(domain):
    for schema, guard, form, width, affinity, input_role in product(*(domain[key] for key in DIMENSIONS)):
        bindings = {} if input_role == "literal" else {"2": None if input_role == "parameter-null" else "0027"}
        first = [["text", "00" + str(11 + slot)] if not bindings else ["parameter", "02"] for slot in range(width)]
        row = [derived(affinity, value) for value in first]
        if form == "select":
            query = select_case(None, row, bindings)
        elif form == "values-single":
            query = value_case([row], bindings)
        elif form == "values-compatible-pair":
            query = value_case([row, row], bindings)
        else:
            mixed = ["integer", "301"] if affinity == "text" else ["text", "alternate"]
            if affinity == "none":
                mixed = ["cast", "TEXT", ["text", "alternate"]]
            query = value_case([row, [mixed] * width], bindings)
        yield record(query, schema, guard)


def finite_ids(domain):
    return set(product(*(range(len(domain[key])) for key in DIMENSIONS)))


def query_form(query):
    if "rows" not in query:
        return "select"
    rows = query["rows"]
    if len(rows) == 1:
        return "values-single"
    return "values-compatible-pair" if rows[0] == rows[1] else "values-mixed-pair"


def finite_id(observed, domain):
    reply, evidence = observed
    query = reply["query"]
    first = query["rows"][0] if "rows" in query else [value for value, _ in query["columns"]]
    role = first[0][1].lower() if first[0][0] == "cast" else "none"
    leaf = first[0][-1] if first[0][0] == "cast" else first[0]
    mode = "literal"
    if leaf[0] == "parameter":
        mode = "parameter-null" if evidence["rows"][0][1][0] == "null" else "parameter-text"
    dimensions = (reply["schema"], reply["ifNotExists"], query_form(query), len(first), role, mode)
    return tuple(domain[key].index(value) for key, value in zip(DIMENSIONS, dimensions))


def seeded(domain, seed, cases):
    rng = Random(seed)
    literals = [["integer", "-3"], ["real", "2", "5", None, False], ["text", "007"], ["text", "hello"],
                ["blob", "00FF"], ["null"], ["parameter", "02"], ["parameter", "5"]]

    def scalar(depth):
        if not depth or rng.randrange(3) == 0:
            return rng.choice(literals)
        role = rng.choice(("cast", "collate", "unary", "arithmetic", "case", "concat"))
        if role == "cast":
            return ["cast", rng.choice(tuple(SPELLINGS)[:5]), scalar(depth - 1)]
        if role == "collate":
            return ["collate", rng.choice(("BINARY", "NOCASE", "RTRIM")), scalar(depth - 1)]
        if role == "unary":
            return ["unary", rng.choice(("+", "NOT", "~")), scalar(depth - 1)]
        if role == "arithmetic":
            return ["arithmetic", rng.choice(("+", "-", "*")), scalar(depth - 1), scalar(depth - 1)]
        if role == "case":
            return ["case", None, [[["integer", "1"], scalar(depth - 1)]], scalar(depth - 1)]
        return ["concat", scalar(depth - 1), scalar(depth - 1)]

    for _ in range(cases):
        width = rng.randrange(1, 6)
        if rng.randrange(2):
            rows = [[scalar(rng.randrange(4)) for _ in range(width)] for _ in range(rng.randrange(1, 6))]
            query = value_case(rows)
            slots = value_bindings(rows)
        else:
            labels = [rng.choice(("col", "col", "COL:1", "TRUE", "FALSE", 'quoted " key', f"r{i}", "日本語", ""))
                      for i in range(width)]
            query = select_case(None, [scalar(rng.randrange(4)) for _ in range(width)], labels=labels)
            query["where"] = rng.choice((None, ["integer", "1"], ["integer", "0"], ["parameter", "5"]))
            query["limit"] = rng.choice((None, [["integer", "0"], None, False], [["integer", "1"], None, False]))
            slots = parameters(query)
        query["bindings"] = {slot: rng.choice(("42", "named", 3, -1.5, None)) for slot in slots}
        existing = rng.randrange(7) == 0
        yield record(query, rng.choice(domain["schemas"]), existing or bool(rng.getrandbits(1)),
                     EXISTING if existing else TABLE)


def analyzed():
    yield "CREATE TABLE ir_created AS VALUES (1, NULL), (1, NULL), (2, NULL)", record(
        value_case([[["integer", "1"], ["null"]], [["integer", "1"], ["null"]], [["integer", "2"], ["null"]]]))
    yield "CREATE TEMP TABLE ir_created AS VALUES (CAST(?02 AS INTEGER)), (CAST(7 AS REAL))", record(
        value_case([[["cast", "INTEGER", ["parameter", "02"]]], [["cast", "REAL", ["integer", "7"]]]], {"2": "0027"}), "temp")
    yield "CREATE TABLE ir_created AS SELECT 1 AS TRUE, 2 AS False, 3 AS coLumn1, 4 AS 'Name:1', 5 AS 'name:1'", record(
        select_case(None, [["integer", str(value)] for value in range(1, 6)], labels=["TRUE", "False", "coLumn1", "Name:1", "name:1"]))
    yield "CREATE TABLE ir_created AS SELECT CAST('42' AS NUMERIC) AS num WHERE FALSE", record(
        {**select_case(None, [["cast", "NUMERIC", ["text", "42"]]], labels=["num"]), "where": ["integer", "0"]})
    yield f"CREATE TABLE IF NOT EXISTS temp.{EXISTING} AS VALUES (?02), (?2)", record(
        value_case([[["parameter", "02"]], [["parameter", "2"]]], {"2": None}), "temp", True, EXISTING)
    yield 'CREATE TABLE ir_created AS SELECT 1 AS "rowid", 2 as "_rowid_", 3 AS oid', record(
        select_case(None, [["integer", "1"], ["integer", "2"], ["integer", "3"]], labels=["rowid", "_rowid_", "oid"]))
    yield "CREATE TABLE ir_created AS VALUES ((CAST('2' AS INTEGER) COLLATE NOCASE)), (CAST('7' AS REAL))", record(
        value_case([[["collate", "NOCASE", ["cast", "INTEGER", ["text", "2"]]]], [["cast", "REAL", ["text", "7"]]]]))

    yield ("CREATE TABLE ir_created AS VALUES ('x' || NULL, CAST(NULL AS NUMERIC) COLLATE RTRIM), "
           "(CAST('y' AS TEXT), NOT 2.5)"), record(value_case([
        [["concat", ["text", "x"], ["null"]], ["collate", "RTRIM", ["cast", "NUMERIC", ["null"]]]],
        [["cast", "TEXT", ["text", "y"]], ["unary", "NOT", ["real", "2", "5", None, False]]],
    ]))


def requests(specification):
    domain, settings = specification["finite"], specification["exploration"]
    for index, case in enumerate(finite_cases(domain)):
        yield f"finite/{index}", case, case
    for seed in settings["seeds"]:
        for index, case in enumerate(seeded(domain, seed, settings["casesPerSeed"])):
            yield f"seed/{seed}/{index}", case, case
    for index, (sql, case) in enumerate(analyzed()):
        yield f"analysis/{index}", {"sql": sql, "declarations": DECLARATIONS + ["CREATE TABLE source_fixture (id INT)"]}, case


def negatives():
    yield record(select_case(["main", "source_fixture", ["id"]], [["column", 0]])), "rejected", "requires a source-free"
    yield {"sql": "CREATE TABLE t AS SELECT id FROM source_fixture", "declarations": ["CREATE TABLE source_fixture (id INT)"]}, (
        "rejected"), "requires a source-free"
    yield {"sql": "CREATE TABLE t AS SELECT 1 UNION ALL SELECT 2", "declarations": []}, "rejected", "requires a source-free"
    labels = ["x", "x:1", "x:2", "x:3", "x:4", "x"]
    yield record(select_case(None, [["integer", "0"]] * len(labels), labels=labels)), "rejected", "requires deterministic"
    yield record(value_case([[["integer", "1"]]]), table="SQLite_forbidden"), "rejected", "reserved SQLite namespace"


def affinity(term):
    while term[0] == "collate":
        term = term[2]
    return term[1] if term[0] == "cast" else None


def possible_types(term):
    kind = term[0]
    if kind == "collate" or (kind == "unary" and term[1] == "+"):
        return possible_types(term[2])
    if kind == "case":
        _, _, branches, fallback = term
        values = [value for _, value in branches] + ([] if fallback is None else [fallback])
        return set().union(*(possible_types(value) for value in values))
    if kind == "cast":
        if term[1] in NUMERIC:
            return {"number", "blob"}
        return {"text", "blob"} if term[1] == "TEXT" else {"number", "text", "blob"}
    if kind in ("parameter", "pattern"):
        return {"number", "text", "blob"}
    if kind == "concat":
        return {"text", "blob"}
    if kind in ("integer", "hex", "real", "arithmetic", "comparison", "logic", "truth", "unary",
                "between", "membership", "folded-membership"):
        return {"number"}
    return {"text": {"text"}, "blob": {"blob"}, "null": set()}[kind]


def values_affinity(operands, compound):
    if not compound:
        return affinity(operands[0])
    first_affinity = next(((index, affinity(value)) for index, value in enumerate(operands) if affinity(value) is not None), None)
    if first_affinity is None:
        return None
    index, selected = first_affinity
    other = set().union(*(possible_types(value) for other_index, value in enumerate(operands) if index != other_index))
    if (selected == "TEXT" and "number" in other) or (selected in NUMERIC and "text" in other):
        return None
    if selected in NUMERIC and operands[0][0] == "cast":
        return "FLEXNUM"
    return selected


def native_names(labels):
    fields = []
    used = set()
    for position, candidate in enumerate(labels, 1):
        if candidate.translate(FOLD) in ("true", "false"):
            candidate = f"column{position}"
        original = candidate
        prefix, separator, number = candidate.rpartition(":")
        root = prefix if separator and all(c in "0123456789" for c in number) else candidate
        alternatives = [original, *(f"{root}:{suffix}" for suffix in range(1, 5))]
        unique = next(name for name in alternatives if name.translate(FOLD) not in used)
        fields.append(unique)
        used.add(unique.translate(FOLD))
    return fields


def inferred_affinities(query):
    if "rows" in query:
        rows = query["rows"]
        compound = len(rows) > 1 and any(affinity(operand) is not None for operand in rows[0])
        return [values_affinity(operands, compound) for operands in zip(*rows)]
    return [affinity(value) for value, _ in query["columns"]]


def layout(query):
    if "rows" in query:
        names = [f"column{index + 1}" for index in range(len(query["rows"][0]))]
    else:
        names = native_names([label for _, label in query["columns"]])
    return [[name, SPELLINGS[inferred]] for name, inferred in zip(names, inferred_affinities(query))]


def parameters(query):
    if "rows" in query:
        return value_bindings(query["rows"])
    return parameter_names({"joins": [], "orderBy": [], "where": None, **query})


def selected(query):
    bindings = query["bindings"]
    if "rows" in query:
        return [[evaluate(value, bindings) for value in row] for row in query["rows"]]
    where, limit = query.get("where"), query.get("limit")
    if where is not None and truth(evaluate(where, bindings)) is not True:
        return []
    if limit is not None and evaluate(limit[0], bindings) == 0:
        return []
    return [[evaluate(value, bindings) for value, _ in query["columns"]]]


def stored_affinity(value, inferred):
    if inferred in ("INTEGER", "NUMERIC") or (inferred == "FLEXNUM" and type(value) is str):
        return value_at_affinity(value, "INTEGER")
    return value_at_affinity(value, inferred)


def populated(query):
    affinities = inferred_affinities(query)
    return [[stored_affinity(value, inferred) for value, inferred in zip(row, affinities)]
            for row in selected(query)]


def rowid_name(columns):
    used = {name.translate(FOLD) for name, _ in columns}
    return next((name for name in ("rowid", "_rowid_", "oid") if name not in used), None)


def expected(item):
    schema, table, query = item["schema"], item["table"], item["query"]
    exists = table == EXISTING
    columns = [["saved", "TEXT"]] if exists else layout(query)
    params = parameters(query)
    rows = [[schema + " sentinel"]] if exists else populated(query)
    alias = rowid_name(columns)
    stored = [[index, *row] for index, row in enumerate(rows, 1)] if alias else rows
    schema_tables = {scope: [[typed(EXISTING)]] for scope in ("main", "temp")}
    if not exists:
        schema_tables[schema] = sorted([*schema_tables[schema], [typed(table)]])
    rejected = exists and not item["ifNotExists"]
    return {
        "prepareError": "table-already-exists" if rejected else None,
        "statement": None if rejected else {"names": [], "rows": [], "parameters": params, "parameterCount": max(map(int, params), default=0),
                                             "readOnly": False, "changes": 0, "evaluationError": None},
        "columns": [[typed(value) for value in (index, name, kind, 0, None, 0, 0)] for index, (name, kind) in enumerate(columns)],
        "catalogs": schema_tables, "rowidAlias": alias, "rows": [[typed(value) for value in row] for row in stored],
    }
