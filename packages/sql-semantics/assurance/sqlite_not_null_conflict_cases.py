"""Generate ordinary declarations and independently predict NULL-write effects."""

from itertools import product
from random import Random

from run_sqlite_create_table import case
from sqlite_default_cases import columns_of, encoded
from sqlite_engine import typed

FIXTURE = "CREATE TABLE ir_nn_policy_marker (value INTEGER)"
TABLE = "ir_nn_policy"
DIMENSIONS = ("schemas", "constraints", "defaults", "writes", "overrides", "transaction")
EXCEPTION = "SqlSemantics\\Platform\\Sqlite\\Model\\Diagnostic\\"


def plan(declaration, write="insert", override="inherit", explicit=False, slot=0, prefix=1):
    return {**declaration, "probe": {"write": write, "override": override, "transaction": explicit,
                                   "slot": slot, "prefix": prefix}}


def finite_cases(domain):
    for schema, constraint, default, write, override, transaction in product(*(domain[key] for key in DIMENSIONS)):
        declaration = case([encoded("value", "INTEGER", constraint, default)], schema, TABLE)
        yield plan(declaration, write, override, transaction)


def finite_ids(domain):
    return set(product(*(range(len(domain[key])) for key in DIMENSIONS)))


def finite_id(response, measurement, domain):
    _, _, constraint, default = next(columns_of(response))
    probe = measurement["profile"]
    values = (response["schema"], constraint, default, probe["write"], probe["override"], probe["transaction"])
    return tuple(domain[key].index(value) for key, value in zip(DIMENSIONS, values))


def seeded(domain, seed, count):
    random = Random(seed)
    label_suffixes = ("é", "日", "with space", '"quoted"')
    for _ in range(count):
        width = random.randint(1, 4)
        default_set = domain["defaults"] + [["integer", str(random.randint(1, 91))]]
        columns = [encoded(f"c{index} {random.choice(label_suffixes)}",
                           random.choice(("", "INTEGER")), random.choice(domain["constraints"]),
                           random.choice(default_set)) for index in range(width)]
        declaration = case(columns, random.choice(domain["schemas"]), TABLE, bool(random.getrandbits(1)))
        yield plan(declaration, random.choice(domain["writes"]), random.choice(domain["overrides"]),
                   bool(random.getrandbits(1)), random.randrange(width), random.randint(0, 4))


def analyzed():
    yield "CREATE TABLE t (a NOT NULL ON CONFLICT REPLACE DEFAULT 1)", plan(
        case([["a", "", "REPLACE", ["integer", "1"]]], table="t"))
    yield "CREATE TEMP TABLE ir_nn_policy (x INTEGER NOT NULL ON CONFLICT FAIL, y NOT NULL ON CONFLICT IGNORE)", plan(
        case([["x", "INTEGER", "FAIL"], ["y", "", "IGNORE"]], "temp", TABLE), explicit=True, prefix=2)
    yield "CREATE TABLE ir_nn_policy (x INTEGER DEFAULT NULL NOT NULL ON CONFLICT ABORT)", plan(
        case([["x", "INTEGER", True, ["null"]]], table=TABLE), "update", "IGNORE")
    yield "CREATE TABLE ir_nn_policy (x NOT NULL ON CONFLICT ROLLBACK)", plan(
        case([["x", "", "ROLLBACK"]], table=TABLE), "update", explicit=True)
    yield "CREATE TEMP TABLE IF NOT EXISTS ir_nn_policy (x NOT NULL ON CONFLICT IGNORE)", plan(
        case([["x", "", "IGNORE"]], "temp", TABLE, True))
    yield 'CREATE TABLE ir_nn_policy ("a""b" NOT NULL, v INTEGER DEFAULT -12 NOT NULL ON CONFLICT REPLACE)', plan(
        case([['a"b', "", True], ["v", "INTEGER", "REPLACE", ["integer", "-12"]]], table=TABLE),
        "update", slot=1)


def requests(specification):
    domain, schedule = specification["finite"], specification["exploration"]
    for index, item in enumerate(finite_cases(domain)):
        yield f"finite/{index}", item, item
    for seed in schedule["seeds"]:
        for index, item in enumerate(seeded(domain, seed, schedule["casesPerSeed"])):
            yield f"seed/{seed}/{index}", item, item
    for index, (sql, item) in enumerate(analyzed()):
        yield f"analysis/{index}", {"sql": sql, "declarations": []}, item


def negatives():
    yield {"sql": "CREATE TABLE t (a NOT NULL ON CONFLICT IGNORE NOT NULL ON CONFLICT FAIL)", "declarations": []}, (
        "rejected", EXCEPTION + "InvalidConstruction", "can declare one NOT NULL constraint")
    yield case([["value", "", "UNKNOWN"]], table=TABLE), (
        "error", "TypeError", "TableColumn::__construct(): Argument #3 ($notNull) must be of type ?"
        + "SqlSemantics\\Platform\\Sqlite\\Ir\\Schema\\NotNullConflict, string given")
    yield {"sql": "CREATE TABLE t (a CHECK (/* label */ a > 0) DEFAULT 1)", "declarations": []}, (
        "rejected", EXCEPTION + "InvalidConstruction", "column constraint has no complete")


def payload(width, token):
    return [token + 2 * index for index in range(width)]


def insert_rows(item):
    probe, width = item["probe"], len(item["columns"])
    rows = [payload(width, 10 * (index + 1)) for index in range(probe["prefix"] + 2)]
    rows[probe["prefix"]][probe["slot"]] = None
    return rows


def expected(item):
    probe, columns = item["probe"], list(columns_of(item))
    active = probe["slot"]
    name, _, constraint, default = columns[active]
    policy = None if not constraint else "ABORT" if constraint is True else constraint
    if policy and probe["override"] != "inherit":
        policy = probe["override"]
    replacement = int(default[1]) if default and default[0] == "integer" else None
    if policy == "REPLACE" and replacement is None:
        policy = "ABORT"

    rejected = policy in ("ABORT", "FAIL", "ROLLBACK")
    rolled_back = policy == "ROLLBACK" and probe["transaction"]
    error = {"code": 19, "message": f"NOT NULL constraint failed: {item['table']}.{name}"} if rejected else None
    if probe["write"] == "insert":
        rows = insert_rows(item)
        index = probe["prefix"]
        if policy == "REPLACE":
            rows[index][active] = replacement
        elif policy == "IGNORE":
            del rows[index]
        elif policy == "FAIL":
            rows = rows[:index]
        elif rejected:
            rows = []
        changed = len(rows)
    else:
        rows = [payload(len(columns), 41), payload(len(columns), 81)]
        changed = int(policy in (None, "REPLACE"))
        if changed:
            rows[0][active] = replacement if policy == "REPLACE" else None

    return {
        "catalog": [[typed(index), typed(label), typed(kind), typed(int(bool(algorithm))), operand is not None, typed(0), typed(0)]
                    for index, (label, kind, algorithm, operand) in enumerate(columns)],
        "rows": [[typed(index), *[typed(value) for value in row]] for index, row in enumerate(rows, 1)],
        "statement": {"error": error, "changes": changed},
        "marker": [[typed(61)]] if probe["transaction"] and not rolled_back else [],
        "active": probe["transaction"] and not rolled_back,
    }
