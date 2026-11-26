"""Model checked default expressions at declaration, old-row access and insertion."""

from itertools import product
from random import Random

from run_sqlite_create_table import case as create, quoted
from run_sqlite_scalars import evaluate
from sqlite_add_column_cases import SCHEMAS, STRICT_CHECK_HEADING, addition, convert, error, expected_class, normalized, statement
from sqlite_coalesce_cases import evaluate as coalesced
from sqlite_engine import typed
from sqlite_generated_column_cases import arithmetic, integer

TABLE = "ir_schema_default"
BASE_NAMES = ("id", "note")
AXES = ("statements", "schemas", "strict", "types", "expressions", "nullPolicies")
NONEXTRACTABLE = "Cannot add a column with non-constant default"


def item(place, schema="main", strict=False, kind="INTEGER", expression=None, policy=None, name="new_value"):
    if expression is None:
        expression = arithmetic("+", integer(2), integer(3))
    return {"placement": place, "schema": schema, "table": TABLE, "strict": strict,
            "column": [name, kind, policy or False, ["computed", expression], "BINARY"]}


def direct(plan):
    schema, table, field = plan["schema"], plan["table"], plan["column"]
    if plan["placement"] == "create":
        return {**create([[BASE_NAMES[0], "INTEGER", False], [BASE_NAMES[1], "TEXT", False], field[:4]], schema, table),
                "strict": plan["strict"]}
    return addition(schema, heading=BASE_NAMES, name=field[0], kind=field[1], policy=field[2], default=field[3],
                    rows=plan["placement"] == "alter-populated", strict=plan["strict"], table=table)


def finite(domain):
    for values in product(*(domain[axis] for axis in AXES)):
        yield item(*values)


def finite_ids(domain):
    return set(product(*(range(len(domain[key])) for key in AXES)))


def observed_fields(response):
    if response["operation"] == "create-table":
        return response["columns"][-1]
    return response["column"][:4]


def finite_id(measured, domain):
    response, observed = measured
    schema = response["schema"]
    before = observed["before"][schema]
    placement = ("create" if before is None else "alter-populated" if before["rows"] else "alter-empty")
    _, kind, policy, (_, expression) = observed_fields(response)
    strict = observed["after"][schema]["strict"] if before is None else before["strict"]
    parts = placement, schema, strict, kind, expression, policy or None
    return tuple(domain[key].index(value) for key, value in zip(AXES, parts))


def searches(domain, seed, count):
    rng = Random(seed)
    for i in range(count):
        placement, schema, strict = (rng.choice(domain[key]) for key in AXES[:3])
        policy = rng.choice((None, "FAIL", "IGNORE", "REPLACE"))
        kind = rng.choice(domain["types"])
        if i % 6 == 0:
            expression = ["folded-membership", bool(i % 2), ["08", "003", "8"]]
        elif i % 6 == 1:
            expression = ["concat", ["text", "left "], ["text", f"'{i}日"]]
            kind = "TEXT"
        elif i % 6 == 2:
            expression = ["cast", rng.choice(("INTEGER", "TEXT")), ["text", str(rng.randrange(-110, 111))]]
        else:
            expression = integer(rng.randrange(-70, 71))
            for _ in range(1 + i % 6):
                value = rng.choice([integer(rng.randrange(-9, 10)), ["null"]])
                expression = arithmetic(rng.choice(("+", "-", "*", "/", ">>")), expression, value)
        label = ("", "née", 'field"quote', f"named {i}")[i % 4]
        yield item(placement, schema, strict, kind, expression, policy, label)


def base_columns():
    return '"id" INTEGER, "note" TEXT'


def declaration(plan, schema):
    strict = plan["strict"] if schema == plan["schema"] else not plan["strict"]
    return f"CREATE TABLE {schema}.{quoted(plan['table'])} ({base_columns()})" + (" STRICT" if strict else "")


def declarations(plan):
    return [declaration(plan, schema) for schema in SCHEMAS
            if plan["placement"] != "create" or plan["schema"] != schema]


def written(plan, expression):
    schema, name, kind, policy = plan["schema"], *plan["column"][:3]
    field = f"{quoted(name)} {kind}" + (" NOT NULL ON CONFLICT " + policy if policy else "")
    field += f" DEFAULT ({expression})"
    subject = f"{schema}.{quoted(plan['table'])}"
    if plan["placement"] == "create":
        return f"CREATE TABLE {subject} ({base_columns()}, {field})" + (" STRICT" if plan["strict"] else "")
    return f"ALTER TABLE {subject} ADD COLUMN {field}"


def analyzed():
    plus = arithmetic("+", integer(1), integer(2))
    for position in ("create", "alter-empty", "alter-populated"):
        yield item(position, expression=plus), "1 + 2"
    yield item("alter-populated", kind="TEXT", expression=["unary", "-", integer(-5)]), "-(-5)"
    yield item("alter-populated", kind="TEXT", policy="FAIL", expression=["unary", "-", ["null"]]), "-NULL"
    yield item("alter-populated", "temp", kind="TEXT", policy="FAIL", expression=["cast", "INTEGER", ["null"]]), "CAST(NULL AS INTEGER)"
    yield item("alter-populated", strict=True, kind="TEXT", policy="FAIL", expression=["cast", "INTEGER", ["null"]]), "CAST(NULL AS INTEGER)"
    folded = ["folded-membership", True, ["09", "001", "009"]]
    yield item("create", "temp", kind="TEXT", expression=folded), "(?09 + ?001 + ?009) NOT IN ()"
    yield item("alter-populated", "temp", strict=True, kind="TEXT", expression=folded), "(?09 + ?001 + ?009) NOT IN ()"


def requests(contract):
    domain = contract["finite"]
    for ordinal, plan in enumerate(finite(domain)):
        yield f"finite/{ordinal}", direct(plan), plan
    for seed in contract["exploration"]["seeds"]:
        for i, plan in enumerate(searches(domain, seed, contract["exploration"]["casesPerSeed"])):
            yield f"seed/{seed}/{i}", direct(plan), plan
    for i, (plan, expression) in enumerate(analyzed()):
        yield f"analyze/{i}", {"sql": written(plan, expression), "declarations": declarations(plan)}, plan


def negatives():
    yield direct(item("create", expression=arithmetic("+", ["column", 0], integer(3)))), "rejected", "visible occurrence"
    yield direct(item("alter-empty", expression=arithmetic("+", ["parameter", "2"], integer(3)))), "rejected", "cannot contain a parameter"
    conflict = direct(item("alter-empty"))
    conflict["column"].append(["VIRTUAL", integer(1)])
    yield conflict, "rejected", "cannot specify a default"
    unsupported = direct(item("alter-empty", expression=integer(2)))
    yield unsupported, "type-rejected", (r"SqlSemantics\Platform\Sqlite\Ir\Schema\ColumnDefaultExpression::__construct(): Argument #1 ($expression)",
                                       r"SqlSemantics\Platform\Sqlite\Ir\IntegerLiteral given")
    for expression, status, reason in (
        ("?3", "analysis-rejected", "not constant"),
        ("id + 2", "analysis-rejected", "not constant"),
        ("(SELECT 7)", "analysis-rejected", "not constant"),
        ("CURRENT_TIMESTAMP", "rejected", "no complete SQLite scalar representation"),
    ):
        plan = item("alter-empty")
        yield {"sql": written(plan, expression), "declarations": declarations(plan)}, status, reason


def old_rows(plan, schema):
    if schema == plan["schema"] and plan["placement"] != "alter-populated":
        return []
    base = 5 if schema == "main" else 51
    return [(base, f"{schema} original"), (base + 4, None)]


def value(plan):
    _, kind, _, (_, term), _ = plan["column"]
    resolved = coalesced(term, (), {}) if term[0] == "coalesce" else evaluate(term, {})
    return convert(resolved, kind, plan["strict"])


def extractable(term):
    if term[0] == "cast" or term[:2] in (["unary", "+"], ["unary", "-"]):
        return extractable(term[2])
    return term[0] in ("integer", "hex", "real", "text", "blob", "null", "folded-membership")


def strict_invalid(plan, default):
    expected = expected_class(plan["column"][1])
    return plan["strict"] and expected is not None and default is not None and type(default) is not expected


def registrations(term):
    if term[0] == "folded-membership":
        yield from term[2]
    elif term[0] == "coalesce":
        for child in term[1]:
            yield from registrations(child)
    elif term[0] in ("unary", "cast"):
        yield from registrations(term[2])
    elif term[0] == "arithmetic":
        for child in term[2:]:
            yield from registrations(child)


def ddl_effect(plan, default):
    placement = plan["placement"]
    term = plan["column"][3][1]
    problem, labels = None, ()
    if placement != "create":
        supported = extractable(term)
        if not supported:
            labels = (f"raise(ABORT,'{NONEXTRACTABLE}')",)
        elif plan["strict"]:
            labels = (STRICT_CHECK_HEADING,)
        if placement == "alter-populated":
            if not supported:
                problem = NONEXTRACTABLE
            elif strict_invalid(plan, default):
                problem = "type mismatch on DEFAULT"
            elif plan["strict"] and plan["column"][2] and default is None:
                problem = "NOT NULL constraint failed"
    expected = statement(labels, error(problem, 1) if problem else None)
    slots = {}
    for label in registrations(term):
        slots.setdefault(str(int(label)), "?" + label)
    expected.update(parameters=slots, parameterCount=max(map(int, slots), default=0))
    return expected


def snapshot(plan, applied=False, writes=()):
    name, kind, policy, _, _ = plan["column"]
    picture = {}
    for schema in SCHEMAS:
        selected = schema == plan["schema"]
        changed = selected and applied
        if selected and plan["placement"] == "create" and not changed:
            picture[schema] = None
            continue
        fields = [*BASE_NAMES]
        catalog = [[0, "id", "INTEGER", 0, False, 0, 0], [1, "note", "TEXT", 0, False, 0, 0]]
        rows = old_rows(plan, schema)
        if changed:
            fields.append(name)
            catalog.append([2, name, kind, int(bool(policy)), True, 0, 0])
            rows = [(*row, value(plan)) for row in rows] + list(writes)
        picture[schema] = {"root": [typed("table"), typed(plan["table"]), typed(plan["table"]), typed(2)],
                           "fields": fields, "strict": plan["strict"] if selected else not plan["strict"],
                           "columns": normalized(catalog), "rows": [list(map(typed, row)) for row in rows]}
    return picture


def write_result(plan, default):
    name, kind, policy, _, _ = plan["column"]
    place = f"{plan['table']}.{name}"
    null_error = None if policy == "IGNORE" else error(f"NOT NULL constraint failed: {place}")
    if strict_invalid(plan, default):
        storage = {int: "INT", float: "REAL", str: "TEXT", bytes: "BLOB"}[type(default)]
        first = statement(error=error(f"cannot store {storage} value in {kind} column {place}"))
        rows = []
    elif default is None and policy:
        first, rows = statement(error=null_error), []
    else:
        first, rows = statement(changes=1), [(99, None, default)]
    if policy == "REPLACE" and default is not None and not strict_invalid(plan, default):
        extra, rows = statement(changes=1), rows + [(101, None, default)]
    elif policy:
        extra = statement(error=null_error)
    else:
        extra, rows = statement(changes=1), rows + [(101, None, None)]
    return {"omitted": first, "null": extra, "rows": snapshot(plan, True, rows)}


def expected(plan):
    default = value(plan)
    first = ddl_effect(plan, default)
    applied = first["evaluationError"] is None
    before = snapshot(plan)
    return {"before": before, "statement": first, "after": snapshot(plan, applied), "rolledBack": before,
            "transaction": True, "writes": write_result(plan, default) if applied else None}


def forms(measured):
    reply, observation = measured
    name, kind, policy, (_, expression) = observed_fields(reply)
    before = observation["before"][reply["schema"]]
    after = observation["after"][reply["schema"]]
    yield f"schema/{reply['schema']}"
    yield f"placement/{'create' if before is None else 'alter-populated' if before['rows'] else 'alter-empty'}"
    yield f"kind/{kind}"
    yield f"policy/{policy}"
    yield f"strict/{(after if before is None else before)['strict']}"
    yield f"root/{expression[0]}"
    yield f"result/{observation['statement']['evaluationError'] is None}"
    yield f"empty-name/{name == ''}"


def required(domain):
    return {*(f"placement/{name}" for name in domain["statements"]), "schema/main", "schema/temp",
            "strict/True", "strict/False", "kind/TEXT", "kind/INTEGER", "root/cast", "root/arithmetic", "root/concat",
            "root/folded-membership", "result/True", "result/False", "empty-name/True", "empty-name/False"}
