"""Inputs and independent row semantics for SQLite generated column addition."""

from itertools import product
from random import Random

from run_sqlite_create_table import quoted
from run_sqlite_scalars import evaluate
from sqlite_add_column_cases import SCHEMAS, STRICT_CHECK_HEADING, addition, collates, convert, error, normalized, statement
from sqlite_coalesce_cases import evaluate as evaluate_coalesced
from sqlite_engine import typed
from sqlite_generated_column_cases import arithmetic, column, integer

TABLE = "ir_append"
STORED_ERROR = "cannot add a STORED column"
NOT_NULL_ERROR = "NOT NULL constraint failed"
AXES = ("schemas", "storage", "strict", "topologies", "populations", "expressions", "resultTypes", "nullPolicy")
COALESCED = ["coalesce", [column(1), integer(17)]]
BASE_FIELDS = ("id", "operand")


def formula(kind, topology):
    if kind == "key-reference":
        return column(0)
    if kind == "nullable-reference":
        return column(1)
    if kind == "coalesced":
        return COALESCED
    extra = column(2) if topology == "prior-generated" else column(1)
    return arithmetic("-", arithmetic("+", column(0), extra), integer(3))


def item(*, schema="main", mode="VIRTUAL", strict=False, topology="rowid", population="non-null",
         expression=None, kind="INTEGER", policy=None, name="computed", heading=None, collation="BINARY"):
    if heading is None:
        heading = BASE_FIELDS + (("earlier",) if topology == "prior-generated" else ())
    value = addition(schema, heading=heading, name=name, kind=kind, policy=policy, collation=collation, strict=strict, table=TABLE)
    del value["rows"]
    value["operation"] = "add-generated-column"
    value["topology"] = topology
    value["population"] = population
    value["column"].append([mode, formula("nested", topology) if expression is None else expression])
    return value


def finite(domain):
    for schema, mode, strict, topology, pop, form, kind, policy in product(*(domain[axis] for axis in AXES)):
        yield item(schema=schema, mode=mode, strict=strict, topology=topology, population=pop,
                   expression=formula(form, topology), kind=kind, policy=policy)


def finite_ids(domain):
    return set(product(*(range(len(domain[key])) for key in AXES)))


def finite_id(measured, domain):
    reply, observation = measured
    schema = reply["schema"]
    before = observation["before"][schema]
    topology = ("without-rowid" if before["noRowid"] else "prior-generated"
                if any(field[-1] == typed(3) for field in before["columns"]) else "rowid")
    rows = before["rows"]
    population = "empty" if not rows else "with-null" if any(row[1] == typed(None) for row in rows) else "non-null"
    _, kind, policy, _, _, (mode, expression) = reply["column"]
    form = next(form for form in domain["expressions"] if formula(form, topology) == expression)
    dimensions = (schema, mode, before["strict"], topology, population, form, kind, policy or None)
    return tuple(domain[axis].index(value) for axis, value in zip(AXES, dimensions))


def explore(seed, count, domain):
    rng = Random(seed)
    for index in range(count):
        schema = SCHEMAS[index % 2]
        topology = domain["topologies"][index % 3]
        width = (3 if topology == "prior-generated" else 2) + index % 5
        names = tuple(f"slot {n} {rng.choice(('É', '日', 'd'))}" for n in range(width))
        kind = ("TEXT", "INTEGER", "ANY")[index % 3]
        term = column(rng.randrange(width))
        for _ in range(index % 5):
            operand = rng.choice([column(rng.randrange(width)), integer(rng.randrange(-9, 10))])
            term = arithmetic(rng.choice(("+", "-", "*")), term, operand)
        if index % 7 == 0:
            kind, term = "TEXT", ["text", "A " if index % 2 else "A"]
        yield item(schema=schema, mode=domain["storage"][index % 2], strict=bool(rng.getrandbits(1)),
                   topology=topology, population=domain["populations"][(index // 2) % 3],
                   expression=term, kind=kind, policy=rng.choice([None, "ABORT", "FAIL", "IGNORE", "REPLACE", "ROLLBACK"]),
                   heading=names, name=("" if index % 8 == 0 else f'x"{index}日'),
                   collation=("BINARY", "NOCASE", "RTRIM")[(index // 2) % 3])


def table_topology(case, schema):
    return case["topology"] if schema == case["schema"] else "rowid"


def table_strict(case, schema):
    return case["strict"] if schema == case["schema"] else not case["strict"]


def declarations(case):
    first, second, *rest = map(quoted, case["priorHeading"])
    definitions = []
    for schema in SCHEMAS:
        topology = table_topology(case, schema)
        fields = [first + " INTEGER PRIMARY KEY", second + " INTEGER"]
        for slot, label in enumerate(rest):
            spec = f" GENERATED ALWAYS AS ({second} * 2) STORED" if slot == 0 and topology == "prior-generated" else ""
            fields.append(label + " INTEGER" + spec)
        modes = (["WITHOUT ROWID"] if topology == "without-rowid" else []) + (["STRICT"] if table_strict(case, schema) else [])
        definitions.append(f"CREATE TABLE {schema}.{quoted(case['table'])} ({', '.join(fields)})"
                           + (" " + ", ".join(modes) if modes else ""))
    return definitions


def analyzed():
    yield "ALTER TABLE IR_APPEND ADD calc INTEGER AS (operand + id)", item(
        schema="temp", name="calc", expression=arithmetic("+", column(1), column(0)))
    yield "ALTER TABLE main.ir_append ADD COLUMN calc TEXT GENERATED ALWAYS AS (earlier+7) VIRTUAL", item(
        topology="prior-generated", name="calc", kind="TEXT", expression=arithmetic("+", column(2), integer(7)))
    yield "ALTER TABLE TEMP.ir_append ADD COLUMN calc INTEGER GENERATED ALWAYS AS (id) STORED", item(
        schema="temp", topology="without-rowid", name="calc", mode="STORED", population="empty", strict=True, expression=column(0))
    yield "ALTER TABLE main.ir_append ADD calc INTEGER AS (operand) STORED", item(
        name="calc", mode="STORED", strict=True, expression=column(1))
    yield 'ALTER TABLE main.ir_append ADD "new name" TEXT AS ("ROWID") VIRTUAL', item(
        name="new name", kind="TEXT", heading=("rowid", "operand"), expression=column(0))
    yield "ALTER TABLE main.ir_append ADD calc INTEGER AS (operand) VIRTUAL NOT NULL ON CONFLICT FAIL", item(
        name="calc", policy="FAIL", population="with-null", expression=column(1))
    yield "ALTER TABLE main.ir_append ADD calc INTEGER AS ((?09 + ?001 + ?009) NOT IN ()) VIRTUAL", item(
        name="calc", expression=["folded-membership", True, ["09", "001", "009"]])


def requests(contract):
    domain = contract["finite"]
    for ordinal, case in enumerate(finite(domain)):
        yield f"finite/{ordinal}", case, case
    for seed in contract["exploration"]["seeds"]:
        for i, case in enumerate(explore(seed, contract["exploration"]["casesPerSeed"], domain)):
            yield f"seed/{seed}/{i}", case, case
    for n, (sql, case) in enumerate(analyzed()):
        yield f"analyze/{n}", {"sql": sql, "declarations": declarations(case)}, case


def negatives():
    missing = item()
    missing["column"].pop()
    yield missing, "rejected", "needs a generation expression"
    unapproved = item()
    unapproved["priorHeading"][1] = False
    yield unapproved, "rejected", "ordered prior heading of identifiers"
    with_default = item()
    with_default["column"][3] = integer(7)
    yield with_default, "rejected", "cannot specify a default"
    yield item(name="OPERAND"), "rejected", "distinct declared name"
    yield item(expression=column(2)), "rejected", "visible occurrence"
    yield item(expression=column(99)), "rejected", "visible occurrence"
    yield item(expression=arithmetic("+", column(1), ["parameter", "1"])), "rejected", "cannot contain a parameter"
    yield item(kind="", strict=True), "rejected", "requires a standard type"

    fixtures = declarations(item())
    tests = (
        ("ALTER TABLE main.ir_append ADD x INTEGER AS (rowid)", fixtures, "analysis-rejected", "Column rowid does not exist"),
        ("ALTER TABLE main.ir_append ADD x INTEGER AS (x + 1)", fixtures, "rejected", "visible occurrence"),
        ("ALTER TABLE unresolved ADD x INTEGER AS (7)", [], "rejected", "resolved ordinary table"),
        ("ALTER TABLE v ADD x INTEGER AS (7)", [*fixtures, "CREATE VIEW v AS SELECT 1 AS base"], "analysis-rejected", "view"),
        ("ALTER TABLE main.ir_append ADD x INTEGER AS (operand) CHECK (x > 0 AND x < 9)", fixtures, "rejected", "checked implicit comparison"),
    )
    for sql, context, status, detail in tests:
        yield {"sql": sql, "declarations": context}, status, detail


def input_rows(case, schema):
    target = schema == case["schema"]
    population = case["population"] if target else "non-null"
    if population == "empty":
        return []
    low = 5 if schema == "main" else 55
    high = low + 6
    second = None if population == "with-null" else -1
    width = len(case["priorHeading"])
    generated_slot = table_topology(case, schema) == "prior-generated"
    extras = [slot for slot in range(2, width) if slot != 2 or not generated_slot]
    return [(low, 7, *(2 * slot + 4 for slot in extras)), (high, second, *(3 * slot - 2 for slot in extras))]


def result_row(case, schema, inputs, appended):
    first, second, *other = inputs
    prior = [first, second]
    if table_topology(case, schema) == "prior-generated":
        prior.append(None if second is None else 2 * second)
    prior.extend(other)
    if appended:
        _, kind, _, _, _, (_, term) = case["column"]
        value = evaluate_coalesced(term, (prior,), {}) if term[0] == "coalesce" else evaluate(term, {}, prior)
        prior.append(convert(value, kind, case["strict"]))
    return prior


def state(case, after=False, written=()):
    heading = case["priorHeading"]
    fields = {}
    name, type_name, policy, _, collation, (mode, _) = case["column"]
    for schema in SCHEMAS:
        topology = table_topology(case, schema)
        names = list(heading)
        columns = [[i, field, "INTEGER", int(i == 0 and topology == "without-rowid"), False,
                    int(i == 0), 3 if i == 2 and topology == "prior-generated" else 0]
                   for i, field in enumerate(heading)]
        appended = after and schema == case["schema"]
        rows = input_rows(case, schema) + (list(written) if appended else [])
        values = [result_row(case, schema, row, appended) for row in rows]
        equals = None
        if appended:
            names.append(name)
            columns.append([len(heading), name, type_name, int(bool(policy)), False, 0, 2 if mode == "VIRTUAL" else 3])
            equals = [[typed(row[0]), *(typed(collates(row[-1], key, type_name, case["strict"], collation)) for key in ("a", "A"))]
                      for row in values]
        fields[schema] = {"root": [typed("table"), typed(case["table"]), typed(case["table"]), typed(2)],
                          "strict": table_strict(case, schema), "noRowid": topology == "without-rowid", "fields": names,
                          "columns": normalized(columns), "rows": [[typed(value) for value in row] for row in values], "equals": equals}
    return fields


def alter_result(case):
    _, _, policy, _, _, (mode, term) = case["column"]
    rows = input_rows(case, case["schema"])
    refusal = None
    if mode == "STORED":
        columns = (f"raise(ABORT,'{STORED_ERROR}')",)
        if rows:
            refusal = error(STORED_ERROR, 1)
    else:
        columns = (STRICT_CHECK_HEADING,) if policy or case["strict"] else ()
        if policy and any(result_row(case, case["schema"], row, True)[-1] is None for row in rows):
            refusal = error(NOT_NULL_ERROR, 1)
    effect = statement(columns, refusal)
    if term[0] == "folded-membership":
        parameters = {}
        for label in term[2]:
            parameters.setdefault(str(int(label)), "?" + label)
        effect.update(parameters=parameters, parameterCount=max(map(int, parameters), default=0))
    return effect


def write_values(case):
    hidden = table_topology(case, case["schema"]) == "prior-generated"
    extras = [None] * (len(case["priorHeading"]) - 2 - int(hidden))
    return [(91, 9, *extras), (92, None, *extras), (93, 2, *extras)]


def write_result(case):
    policy = case["column"][2]
    schema = case["schema"]
    inserted = []
    failure = None
    active = True
    for row in write_values(case):
        if policy and result_row(case, schema, row, True)[-1] is None:
            if policy == "IGNORE":
                continue
            failure = error(f"{NOT_NULL_ERROR}: {case['table']}.{case['column'][0]}")
            if policy != "FAIL":
                inserted.clear()
            if policy == "ROLLBACK":
                active = False
            break
        inserted.append(row)
    first = next((row for row in inserted if row[0] == 91), None)
    updated = [(row[0], 20, *row[2:]) if row[0] == 91 else row for row in inserted]
    write = statement(error=failure, changes=len(inserted))
    edit = statement(changes=int(first is not None))
    return {"insert": write, "update": edit, "active": active, "rows": state(case, True, updated),
            "explicit": f'SQLite 1: cannot INSERT into generated column "{case["column"][0]}"',
            "rolledBack": state(case, True)}


def expected(case):
    before = state(case)
    alteration = alter_result(case)
    applied = alteration["evaluationError"] is None
    return {"before": before, "statement": alteration, "after": state(case, applied), "rolledBack": before,
            "transaction": True, "writes": write_result(case) if applied else None}


def forms(measured):
    reply, observation = measured
    before = observation["before"][reply["schema"]]
    name, kind, policy, _, collation, (mode, term) = reply["column"]
    yield f"schema/{reply['schema']}"
    yield f"storage/{mode}"
    yield f"prior-width/{len(before['fields'])}"
    yield f"expression/{term[0]}"
    yield f"mode/{before['strict']}"
    yield f"populated/{bool(before['rows'])}"
    yield f"without-rowid/{before['noRowid']}"
    yield f"collation/{collation}"
    yield f"kind/{kind}"
    yield f"policy/{policy}"
    yield f"empty/{name == ''}"


def required(domain):
    return {
        *(f"schema/{name}" for name in SCHEMAS), *(f"storage/{mode}" for mode in domain["storage"]),
        *(f"collation/{mode}" for mode in ("BINARY", "NOCASE", "RTRIM")),
        *(f"prior-width/{width}" for width in range(2, 8)),
        "mode/True", "mode/False", "populated/True", "populated/False",
        "without-rowid/True", "without-rowid/False", "expression/column", "expression/arithmetic", "expression/text",
        "kind/TEXT", "kind/INTEGER", "kind/ANY", "empty/True", "empty/False",
    }
