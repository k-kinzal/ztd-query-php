"""Independent relational semantics and direct cases for native joined projections."""

import itertools
import random

from run import require
from server_cases import CHILD_ROWS, NAMES, ROWS, SCHEMAS, evaluate, observation, validate_width
from server_delete_cases import OPERATORS, literal
from server_truth_model import admits, predicate_value

DATA = {"accounts": ROWS, "independent": [(3, 15), (2, 11), (2, 11), (None, 31)], "empty": []}
NAMES_BY_TABLE = {"accounts": "ir_join_accounts", "independent": "ir_join_independent", "empty": "ir_join_empty"}


def slot(column, occurrence=0):
    return ["slot", column, occurrence] if occurrence else ["slot", column]


def compare(operator, left, right):
    return ["comparison", operator, left, right]


def table(dialect, name, only=False, columns=None):
    return {
        "schema": SCHEMAS[dialect], "table": NAMES_BY_TABLE[name],
        "columns": list(NAMES[dialect]) if columns is None else list(columns),
        **({"only": only} if dialect == "postgres" else {}),
    }


def join(kind, left, right, on=None):
    return {"kind": kind, "left": left, "right": right, "on": on}


def case(dialect, tag, source, fields, where=None):
    return {"dialect": dialect, "release": tag, "source": source, "columns": fields, "where": where}


def is_join(source):
    return "kind" in source


def leaves(source):
    if source is None:
        return []
    return leaves(source["left"]) + leaves(source["right"]) if is_join(source) else [source]


def registered(dialect):
    equal = compare("=", slot(0), slot(0, 1))
    less = compare("<", slot(0), slot(0, 1))
    conditions = [equal, less, ["boolean", False], ["null"]]
    return [("CROSS", None), *((kind, on) for kind in ("INNER", "LEFT", "RIGHT") for on in conditions),
            *([("FULL", equal)] if dialect == "postgres" else [])]


FILTERS = (None, compare("<", slot(1), literal(3)), ["boolean", False])
PROJECTION = [[slot(0), "left"], [slot(1, 1), "right"], [["operation", "-", slot(1), slot(1, 1)], "difference"]]


def finite_cases(dialect, tag):
    policies = (False, True) if dialect == "postgres" else (False,)
    for name, (kind, on), where, only in itertools.product(("independent", "empty", "accounts"), registered(dialect), FILTERS, policies):
        source = join(kind, table(dialect, "accounts", only), table(dialect, name), on)
        yield case(dialect, tag, source, PROJECTION, where)


def finite_id(dialect, response, _):
    source = response["source"]
    return (source["right"]["table"], registered(dialect).index((source["kind"], source["on"])),
            FILTERS.index(response["where"]), bool(source["left"].get("only")))


def finite_ids(dialect, specification, _tag):
    policy_count = len(specification["finite"]["onlyPolicies"][dialect])
    variants = specification["finite"]["identities"][dialect] // (9 * policy_count)
    return {(table, variant, filter_id, bool(policy))
            for table in ("ir_join_independent", "ir_join_empty", "ir_join_accounts")
            for variant in range(variants) for filter_id in range(3) for policy in range(policy_count)}


def seeded(dialect, tag, seed, count):
    rng = random.Random(seed)
    kinds = ("CROSS", "INNER", "LEFT", "RIGHT") + (("FULL",) if dialect == "postgres" else ())

    def tree(size):
        if size == 1:
            return table(dialect, rng.choice(tuple(DATA)), bool(rng.getrandbits(1)))
        split = rng.randint(1, size - 1)
        kind = rng.choice(kinds)
        if kind == "CROSS":
            on = None
        elif kind == "FULL":
            on = compare("=", slot(0, rng.randrange(split)), slot(0, rng.randrange(split, size)))
        else:
            on = rng.choice((["boolean", True], ["boolean", False], ["null"],
                             compare(rng.choice(tuple(OPERATORS)), slot(rng.randrange(2), rng.randrange(size)),
                                     slot(rng.randrange(2), rng.randrange(size)))))
        return join(kind, tree(split), tree(size - split), on)

    def expr(width, depth):
        if depth and rng.random() < 0.5:
            return ["operation", rng.choice(("+", "-", "*")), expr(width, depth - 1), expr(width, depth - 1)]
        return rng.choice((slot(rng.randrange(2), rng.randrange(width)), literal(rng.randrange(-2, 4))))

    for _ in range(count):
        width = rng.randint(2, 5)
        fields = [[rng.choice((["boolean", False], ["null"], expr(width, 3), expr(width, 2), expr(width, 1))), label]
                  for label in ("k", "k", "値")]
        where = rng.choice((None, None, ["boolean", False], ["null"],
                            compare(rng.choice(tuple(OPERATORS)), slot(rng.randrange(2), rng.randrange(width)),
                                    literal(rng.randrange(-1, 17)))))
        yield case(dialect, tag, tree(width), fields, where)


def declarations(dialect):
    schema = SCHEMAS[dialect]
    key = NAMES[dialect][0]
    return [f"CREATE TABLE {schema}.{name} ({key} INTEGER, delta INTEGER)" for name in NAMES_BY_TABLE.values()]


def fixture(dialect):
    schema = SCHEMAS[dialect]
    child = schema + ".ir_join_child"
    setup = [*([f"DROP TABLE IF EXISTS {child}"] if dialect == "postgres" else []),
             *(f"DROP TABLE IF EXISTS {schema}.{table}" for table in NAMES_BY_TABLE.values()), *declarations(dialect)]
    for role, data in DATA.items():
        if data:
            values = ",".join("(" + ",".join("NULL" if part is None else str(part) for part in row) + ")" for row in data)
            setup.append(f"INSERT INTO {schema}.{NAMES_BY_TABLE[role]} VALUES {values}")
    if dialect == "postgres":
        setup += [f"CREATE TABLE {child} () INHERITS ({schema}.ir_join_accounts)",
                  f"INSERT INTO {child} VALUES (-1,2),(1,3)"]
        setup.extend(f"ANALYZE {schema}.{name}" for name in NAMES_BY_TABLE.values())
        setup.append(f"ANALYZE {child}")
    return setup


def analyzed(dialect, tag):
    schema = SCHEMAS[dialect]
    one, two, three = (f"{schema}.{name}" for name in NAMES_BY_TABLE.values())
    identifier = NAMES[dialect][0]
    scenarios = [
        (f"SELECT a.{identifier} AS x,b.delta AS y FROM {one} a LEFT JOIN {two} b ON a.{identifier}=b.{identifier} WHERE a.delta<2",
         join("LEFT", table(dialect, "accounts", columns=["delta", identifier]), table(dialect, "independent", columns=["delta", identifier]),
              compare("=", slot(1), slot(1, 1))), [[slot(1), "x"], [slot(0, 1), "y"]], compare("<", slot(0), literal(2))),
        (f"SELECT a.{identifier} AS x,b.{identifier} AS y,a.{identifier} AS again FROM {one} a JOIN {one} b ON a.{identifier}=b.{identifier}",
         join("INNER", table(dialect, "accounts", columns=[identifier]), table(dialect, "accounts", columns=[identifier]),
              compare("=", slot(0), slot(0, 1))), [[slot(0), "x"], [slot(0, 1), "y"], [slot(0), "again"]], None),
        (f"SELECT a.{identifier} AS x,c.delta AS y FROM {one} a LEFT JOIN ({two} b RIGHT JOIN {three} c ON b.{identifier}=c.{identifier}) ON a.{identifier}=b.{identifier}",
         join("LEFT", table(dialect, "accounts", columns=[identifier]),
              join("RIGHT", table(dialect, "independent", columns=[identifier]), table(dialect, "empty", columns=["delta", identifier]),
                   compare("=", slot(0), slot(1, 1))), compare("=", slot(0), slot(0, 1))),
         [[slot(0), "x"], [slot(0, 2), "y"]], None),
        (f"SELECT b.{identifier} AS x,c.delta AS y FROM ({one} a RIGHT JOIN {two} b ON a.{identifier}=b.{identifier}) CROSS JOIN {three} c",
         join("CROSS", join("RIGHT", table(dialect, "accounts", columns=[identifier]), table(dialect, "independent", columns=[identifier]),
                            compare("=", slot(0), slot(0, 1))), table(dialect, "empty", columns=["delta"])),
         [[slot(0, 1), "x"], [slot(0, 2), "y"]], None),
        (f"SELECT {identifier} AS x FROM {one} WHERE NULL", table(dialect, "accounts", columns=[identifier]),
         [[slot(0), "x"]], ["null"]),
    ]
    if dialect == "postgres":
        scenarios.append((f"SELECT a.id AS x,b.id AS y FROM ONLY {one} a FULL JOIN {two} b ON a.id=b.id",
                          join("FULL", table(dialect, "accounts", True, ["id"]), table(dialect, "independent", columns=["id"]),
                               compare("=", slot(0), slot(0, 1))), [[slot(0), "x"], [slot(0, 1), "y"]], None))
    for sql, source, fields, where in scenarios:
        yield {"dialect": dialect, "release": tag, "sql": sql, "declarations": declarations(dialect)}, case(dialect, tag, source, fields, where)


def negatives(dialect, tag):
    left, right = table(dialect, "accounts"), table(dialect, "independent")
    fields = [[slot(0), "answer"]]
    product = join("CROSS", left, right)
    yield case(dialect, tag, join("CROSS", left, right, ["boolean", True]), fields), "Only CROSS has no join predicate."
    yield case(dialect, tag, join("LEFT", left, right), fields), "Only CROSS has no join predicate."
    yield case(dialect, tag, product, [[slot(2, 1), "answer"]]), "Column slot must belong to the SELECT source."
    subquery = join("INNER", left, right, compare("=", slot(0), slot(0, 2)))
    yield case(dialect, tag, join("CROSS", right, subquery), fields), "must belong to a visible input occurrence"
    yield case(dialect, tag, product, [[slot(0, 2), "answer"]]), "Column slot must belong to the SELECT source."
    yield case(dialect, tag, product, fields, compare("=", slot(2), literal(0))), "must belong to a visible input occurrence"
    oversized = left
    for _ in range(61):
        oversized = join("CROSS", oversized, right)
    yield case(dialect, tag, oversized, fields), "at most 61 input occurrences"
    if dialect == "postgres":
        yield case(dialect, tag, join("FULL", left, right, compare("<", slot(0), slot(0, 1))), fields), "requires integer equality between opposite inputs"
    else:
        yield {
            "dialect": dialect, "release": tag,
            "declarations": [*declarations(dialect), f"CREATE TABLE {SCHEMAS[dialect]}.ir_join_distinct (other INT)"],
            "sql": f"SELECT 1 AS answer FROM ({SCHEMAS[dialect]}.ir_join_accounts a JOIN {SCHEMAS[dialect]}.ir_join_distinct c ON 1) NATURAL JOIN {SCHEMAS[dialect]}.ir_join_independent b",
        }, "two ordinary base-table inputs"


def value(expression, row, dialect):
    role = expression[0]
    if role in ("slot", "text-slot", "bool-slot", "wide-slot"):
        return row[expression[2] if len(expression) == 3 else 0][expression[1]]
    if role == "int4-case":
        _, arms, otherwise = expression
        for condition, result in arms:
            if truth(condition, row, dialect):
                return value(result, row, dialect)
        return None if otherwise is None else value(otherwise, row, dialect)
    if role == "null-test":
        return predicate_value(expression, lambda operand: value(operand, row, dialect))
    if role != "operation":
        return evaluate(expression, None, dialect)
    _, symbol, left, right = expression
    a, b = value(left, row, dialect), value(right, row, dialect)
    if a is None or b is None:
        return None
    return validate_width({"+": lambda: a + b, "-": lambda: a - b, "*": lambda: a * b}[symbol](), dialect)


def truth(condition, row, dialect):
    return admits(condition, lambda operand: value(operand, row, dialect))


def input_width(source):
    return len((source["derived"] if "derived" in source else source)["columns"])


def rows(source, dialect, derived_rows=None, table_rows=None):
    if source is None:
        return [()]
    if "derived" in source:
        return [(tuple(row),) for row in derived_rows(source["derived"])]
    if not is_join(source):
        if table_rows is None:
            role = next(role for role, relation in NAMES_BY_TABLE.items() if relation == source["table"])
            physical = DATA[role] + (CHILD_ROWS if role == "accounts" and dialect == "postgres" and not source["only"] else [])
            heading = NAMES[dialect]
        else:
            heading, physical = table_rows(source)
        columns = [heading.index(column) for column in source["columns"]]
        return [(tuple(row[index] for index in columns),) for row in physical]
    left = rows(source["left"], dialect, derived_rows, table_rows)
    right = rows(source["right"], dialect, derived_rows, table_rows)
    joined = []
    remaining = set(range(len(right)))
    for lhs in left:
        matches = [(index, lhs + rhs) for index, rhs in enumerate(right) if truth(source["on"], lhs + rhs, dialect)]
        joined.extend(row for _, row in matches)
        remaining.difference_update(index for index, _ in matches)
        if not matches and source["kind"] in ("LEFT", "FULL"):
            joined.append(lhs + tuple((None,) * input_width(item) for item in leaves(source["right"])))
    if source["kind"] in ("RIGHT", "FULL"):
        nulls = tuple((None,) * input_width(item) for item in leaves(source["left"]))
        joined.extend(nulls + right[index] for index in sorted(remaining))
    return joined


def expected(item, table_rows=None):
    dialect = item["dialect"]
    default = "int4" if dialect == "postgres" else "LONGLONG"
    metadata = {"boolean": "bool", "null": "text", "text-slot": "text", "null-test": "bool"} if dialect == "postgres" else {"slot": "LONG", "null": "NULL"}
    fields = [[name, metadata.get(expr[0], default)] for expr, name in item["columns"]]
    observed = [[value(expr, row, dialect) for expr, _ in item["columns"]] for row in rows(item["source"], dialect, table_rows=table_rows)
                if truth(item["where"], row, dialect)]
    return observation({"status": "rows", "fields": fields, "rows": observed})


def named_binary_graph(item):
    source = item["source"]
    origins = source["left"], source["right"]

    def address(term):
        if term[0] == "int4-case":
            _, arms, otherwise = term
            return term[0], tuple((predicate(condition), address(value)) for condition, value in arms), (
                None if otherwise is None else address(otherwise))
        if term[0] != "slot":
            return tuple(term)
        occurrence = term[2] if len(term) == 3 else 0
        return "slot", occurrence, origins[occurrence]["columns"][term[1]]

    def predicate(term):
        if term is None:
            return None
        if term[0] == "comparison":
            return term[0], term[1], address(term[2]), address(term[3])
        if term[0] == "logic":
            return term[0], term[1], predicate(term[2]), predicate(term[3])
        if term[0] == "null-test":
            return term[0], term[1], address(term[2])
        return tuple(term)

    return (source["kind"], tuple((entry["schema"], entry["table"], entry.get("only", False)) for entry in origins),
            predicate(source["on"]), tuple((address(term), label) for term, label in item["columns"]))


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
