"""Independent inputs and semantic identities for SQLite's index expression spelling."""

from itertools import product
from random import Random

from run_sqlite_create_table import quoted
from sqlite_create_index_cases import case, comparison, declarations
from sqlite_generated_column_cases import arithmetic, column, integer

AXES = ("schemas", "placement", "predicates", "nativeNames", "population")
ROLES = ("direct-cast", "cast-through-ifnull", "cast-through-coalesce")
BUILTINS = dict(zip(ROLES[1:], ("if-null", "coalesce")))
PURPOSE = {value: key for key, value in BUILTINS.items()}
CONVERSIONS = (
    ("INTEGER", ("INT",)),
    ("TEXT", ("CHAR", "CLOB", "TEXT")),
    ("BLOB", ("BLOB",)),
    ("REAL", ("REAL", "FLOA", "DOUB")),
)
LOWER = str.maketrans("abcdefghijklmnopqrstuvwxyz", "ABCDEFGHIJKLMNOPQRSTUVWXYZ")


def conversion(name):
    value = name.translate(LOWER)
    return next((target for target, parts in CONVERSIONS if any(part in value for part in parts)), "NUMERIC")


def cast(name, operand=None):
    return ["cast", conversion(name), column(2) if operand is None else operand, name]


def term(role, name, operand, fallback):
    scalar = cast(name, operand)
    return scalar if role == ROLES[0] else [BUILTINS[role], [scalar, fallback]]


def value(schema, placement, role, name, population, *, operand=None, fallback=None, descending=False):
    result = case(schema, population=population)
    scalar = term(role, name, operand, integer(0) if fallback is None else fallback)
    if placement == "key":
        result["terms"] = [[scalar, descending]]
    else:
        result["where"] = comparison("IS NOT", scalar, ["null"])
    return result


def parts(item):
    placement = "key" if item["where"] is None else "partial"
    scalar = item["terms"][0][0] if placement == "key" else item["where"][2]
    return placement, scalar


def unpack(scalar):
    bare = scalar[0] == "cast"
    node = scalar if bare else scalar[1][0]
    return ROLES[0] if bare else PURPOSE[scalar[0]], node[3] if len(node) == 4 else node[1]


def finite_ids(domain):
    return set(product(*(range(len(domain[axis])) for axis in AXES)))


def finite_id(measured, domain):
    reply, observation = measured
    schema = reply["schema"]
    placement, scalar = parts(reply)
    role, name = unpack(scalar)
    population = "mixed" if observation["before"][schema]["rows"] else "empty"
    return tuple(domain[axis].index(choice) for axis, choice in zip(AXES, (schema, placement, role, name, population)))


def explored(domain, seed, amount):
    random = Random(seed)
    fragments = ('', ' x', ' "x"', ' (11, 2)', '列', ' /* note */', '  ')
    names = [*domain["nativeNames"], "DOUBLE PRECISION", "REAL", "FLOAT"]
    for index in range(amount):
        name = names[index % len(names)]
        name = name.swapcase() if random.getrandbits(1) else name
        name += random.choice(fragments)
        operand = column(2)
        for _ in range(index % 4):
            operand = arithmetic(random.choice(("+", "-")), operand, integer(random.choice((-2, 0, 3))))
        fallback = random.choice([["null"], integer(0), integer(4), ["text", 'x "列"'], ["blob", "4100"]])
        yield value(domain["schemas"][index % 2], domain["placement"][(index // 2) % 2],
                    domain["predicates"][(index // 4) % 3], name, domain["population"][index % 2],
                    operand=operand, fallback=fallback, descending=bool(random.getrandbits(1)))


def native_sql(expression, amount, raw_type=None):
    kind = expression[0]
    if kind == "column":
        assert expression[1] == 2
        return amount
    if kind == "cast":
        native = quoted(unpack(expression)[1]) if raw_type is None else raw_type
        return f"CAST({native_sql(expression[2], amount)} AS {native})"
    if kind in ("if-null", "coalesce"):
        left, right = expression[1]
        function = "IFNULL" if kind == "if-null" else "COALESCE"
        return f"{function}({native_sql(left, amount, raw_type)}, {native_sql(right, amount)})"
    if kind == "comparison":
        return f"({native_sql(expression[2], amount, raw_type)} {expression[1]} {native_sql(expression[3], amount)})"
    if kind == "arithmetic":
        return f"({native_sql(expression[2], amount)} {expression[1]} {native_sql(expression[3], amount)})"
    if kind == "null":
        return "NULL"
    if kind == "integer":
        return str(int(expression[1]))
    if kind == "blob":
        return "X'" + bytes.fromhex(expression[1]).hex() + "'"
    if kind == "text":
        return "'" + expression[1].replace("'", "''") + "'"
    raise ValueError(expression)


def authored():
    samples = (
        ("main", "partial", ROLES[1], "INTEGER", "INTEGER"),
        ("main", "key", ROLES[2], "INT", "INT"),
        ("main", "partial", ROLES[0], "integer", "integer"),
        ("temp", "key", ROLES[0], "int", "int"),
        ("main", "partial", ROLES[0], "FOO /* INT */ BAR", "FOO /* INT */ BAR"),
        ("temp", "key", ROLES[1], "DECIMAL (04, 02)", "DECIMAL (04, 02)"),
        ("main", "partial", ROLES[0], "", ""),
        ("temp", "key", ROLES[2], "", '""'),
        ("temp", "key", ROLES[0], "UNSIGNED", '"UNSIGNED" BIG INT'),
        ("main", "partial", ROLES[1], "INT", '"INT" (4)'),
        ("temp", "partial", ROLES[2], 'Name "with" INT', '"Name ""with"" INT"'),
        ("main", "key", ROLES[0], "χμε", "χμε"),
        ("temp", "partial", ROLES[0], "NAME", "'NAME' INT"),
        ("main", "key", ROLES[1], "BLOB", "`BLOB`"),
        ("temp", "key", ROLES[0], "TEXT", "[TEXT]"),
        ("main", "key", ROLES[2], "FLOATING POINT", "FLOATING POINT"),
    )
    for schema, placement, role, name, raw_type in samples:
        item = value(schema, placement, role, name, "mixed")
        if name == conversion(name):
            _, scalar = parts(item)
            (scalar if scalar[0] == "cast" else scalar[1][0]).pop()
        key = native_sql(item["terms"][0][0], "amount", raw_type)
        restriction = "" if placement == "key" else " WHERE " + native_sql(item["where"], "amount", raw_type)
        yield item, f"CREATE INDEX {schema}.tag ON ledger({key}){restriction}"

    item = value("main", "partial", ROLES[1], "INTEGER", "mixed")
    item["terms"] = [[column(1), False]]
    item["where"] = comparison(">", ["if-null", [column(2), integer(0)]], integer(0))
    yield item, "CREATE INDEX main.tag ON ledger(code) WHERE IFNULL(amount,0)>0"
    item = value("main", "partial", ROLES[0], "integer", "mixed")
    item["terms"] = [[column(1), False]]
    item["where"] = comparison(">=", cast("integer"), integer(2))
    yield item, "CREATE INDEX main.tag ON ledger(code) WHERE CAST(amount AS integer) >= 2"


def requests(specification):
    domain = specification["finite"]
    for index, choices in enumerate(product(*(domain[axis] for axis in AXES))):
        item = value(*choices)
        yield f"finite/{index}", item, item
    for seed in specification["exploration"]["seeds"]:
        for index, item in enumerate(explored(domain, seed, specification["exploration"]["casesPerSeed"])):
            yield f"seed/{seed}/{index}", item, item
    for index, (item, sql) in enumerate(authored()):
        yield f"analyze/{index}", {"sql": sql, "declarations": declarations(item)}, item


def negatives():
    def key(scalar):
        return {**case(), "terms": [[scalar, False]]}

    yield key(["cast", "BLOB", column(2), "INT"]), "rejected", "select the effective CAST target"
    yield key(cast("A\x00")), "rejected", "UTF-8 without NUL"
    yield key(["cast", "NUMERIC", column(2), ["ff"]]), "rejected", "UTF-8 without NUL"
    yield key(["if-null", [column(2), integer(0), integer(1)]]), "rejected", "IFNULL requires exactly two arguments"
    yield key(cast("INT", ["parameter", "01"])), "rejected", "cannot contain a parameter"


def forms(measured):
    reply, result = measured
    placement, scalar = parts(reply)
    role, name = unpack(scalar)
    native = scalar if role == ROLES[0] else scalar[1][0]
    yield f"placement/{placement}"
    yield f"native/{role}"
    yield f"conversion/{native[1]}"
    yield f"nested/{native[2][0]}"
    yield f"population/{bool(result['before'][reply['schema']]['rows'])}"
    if '"' in name:
        yield "name/embedded-quote"
    if not name.isascii():
        yield "name/non-ascii"


def required(_):
    return {"placement/key", "placement/partial", "nested/column", "nested/arithmetic",
            "population/True", "population/False", "name/embedded-quote", "name/non-ascii",
            *(f"native/{role}" for role in ROLES),
            *(f"conversion/{name}" for name in ("INTEGER", "NUMERIC", "TEXT", "BLOB", "REAL"))}
