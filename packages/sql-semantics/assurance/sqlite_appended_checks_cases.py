"""Independently model named CHECK constraints installed by SQLite column additions."""

from itertools import product
from random import Random

from run_sqlite_create_table import quoted
from run_sqlite_scalars import evaluate
from sqlite_add_column_cases import SCHEMAS, STRICT_CHECK_HEADING, addition, error, normalized, statement
from sqlite_add_generated_column_cases import STORED_ERROR, declarations as prior_definitions
from sqlite_engine import typed
from sqlite_generated_column_cases import arithmetic, column, integer

TABLE = "ir_add_checks"
AXES = ("schemas", "columnForms", "strict", "population", "predicates", "checkCounts")
FIELDS = ("operation", "schema", "table", "strict", "column", "checks")
LOADED = "CHECK constraint failed"


def compare(operation, left, right):
    return ["comparison", operation, left, right]


def condition(name, owner):
    return {
        "new-greater-than-old": compare(">", column(owner), column(1)),
        "new-positive": compare(">", column(owner), integer(0)),
        "old-positive": compare(">", column(1), integer(0)),
        "folded-registered": ["folded-membership", True, ["09", "001", "009"]],
    }[name]


def entry(label, owner, expression):
    return {"label": label, "column": owner, "condition": expression}


def case(schema="main", kind="ordinary-two", strict=False, population="null", predicate="new-greater-than-old",
         checks=1, heading=("id", "operand"), labels=("first constraint", "second constraint"), field="checked"):
    value = addition(schema, heading=heading, name=field, strict=strict, table=TABLE,
                     default=integer(2) if kind == "ordinary-two" else None)
    value.pop("rows")
    value.update(population=population, topology="rowid")
    if kind in ("virtual", "stored"):
        value["operation"] = "add-generated-column"
        value["column"].append([kind.upper(), arithmetic("+", column(1), integer(1))])
    owner = len(heading)
    value["checks"] = [entry(labels[0], owner, condition(predicate, owner))]
    if checks > 1:
        value["checks"].append(entry(labels[1], owner, compare("<", column(1), integer(8))))
    return value


def replacements(case):
    return case["column"][5][0] if len(case["column"]) == 6 else None


def finite(domain):
    for identity in product(*(domain[key] for key in AXES)):
        yield case(*identity)


def finite_ids(domain):
    return set(product(*(range(len(domain[key])) for key in AXES)))


def finite_id(measured, domain):
    reply, observed = measured
    schema = reply["schema"]
    before = observed["before"][schema]
    column, checks = reply["column"], reply["checks"]
    marker = column[5][0].lower() if len(column) == 6 else "ordinary-absent" if column[3] is None else "ordinary-two"
    examined = before["rows"]
    population = "empty" if not examined else {None: "null", "0": "below", "7": "mixed"}[examined[-1][1][1]]
    choice = next(name for name in domain["predicates"] if condition(name, len(before["fields"])) == checks[0]["condition"])
    fields = (schema, marker, before["strict"], population, choice, len(checks))
    return tuple(domain[key].index(value) for key, value in zip(AXES, fields))


def seeds(domain, seed, count):
    rng = Random(seed)
    for i in range(count):
        schema = rng.choice(SCHEMAS)
        width = 2 + i % 6
        prior = tuple(f'{slot} "{rng.choice(("a", "É", "日", "ح"))}"' for slot in range(width))
        labels = (f'condition "{i}日"', "" if i % 2 else f"α{i}")
        value = case(schema, rng.choice(domain["columnForms"]), bool(rng.getrandbits(1)),
                     domain["population"][i % 4], domain["predicates"][i % 4], 1 + i % 3, prior, labels,
                     "" if i % 8 == 0 else f'result "{i}"')
        owner = width
        if i % 4 != 3:
            inputs = arithmetic(rng.choice(("+", "-")), column(rng.randrange(width)), integer(rng.randrange(1, 10)))
            value["checks"][0]["condition"] = compare(rng.choice(("<", ">=", "<>")), column(owner), inputs)
        if i % 3 == 2:
            value["checks"].append(entry(f'last "{i}"', owner, compare(">=", column(0), integer(1))))
        yield value


def analyzed():
    def source(value, column_sql, terms, target=None):
        tail = " ".join(f'CONSTRAINT {quoted(check["label"])} CHECK ({expr})' for check, expr in zip(value["checks"], terms))
        absolute = f'{value["schema"]}.{quoted(value["table"])}'
        table = absolute if target is None else target
        return f'ALTER TABLE {table} ADD COLUMN {column_sql} {tail}', value

    yield source(case(), 'checked INTEGER DEFAULT 2', ["checked > operand"])
    yield source(case("temp", "virtual", strict=True, labels=('named "check"', ""), checks=2),
                 'checked INTEGER AS (operand+1) VIRTUAL', ["checked > operand", "operand < 8"], TABLE)
    yield source(case("main", "stored", population="empty", predicate="new-positive"),
                 'checked INTEGER AS (operand+1) STORED', ["checked > 0"])
    yield source(case("main", "stored", population="mixed", predicate="old-positive"),
                 'checked INTEGER AS (operand+1) STORED', ["operand > 0"])
    yield source(case("main", "ordinary-absent", population="mixed", predicate="folded-registered", checks=2),
                 'checked INTEGER', ["(?09+?001+?009) NOT IN ()", "operand < 8"])
    yield source(case("temp", "ordinary-two", population="mixed", labels=("", "tail"), checks=2),
                 'checked INTEGER DEFAULT 2', ["checked > operand", "operand < 8"])
    yield source(case("main", "ordinary-absent", population="below", predicate="new-positive", checks=2),
                 'checked INTEGER DEFAULT NULL', ["checked > 0", "operand < 8"])


def requests(contract):
    domain = contract["finite"]
    for index, value in enumerate(finite(domain)):
        yield f"finite/{index}", value, value
    for seed in contract["exploration"]["seeds"]:
        for index, value in enumerate(seeds(domain, seed, contract["exploration"]["casesPerSeed"])):
            yield f"seed/{seed}/{index}", value, value
    for index, (sql, value) in enumerate(analyzed()):
        if index == 6:
            value["column"][3] = ["null"]
        yield f"analyze/{index}", {"sql": sql, "declarations": prior_definitions(value)}, value


def negatives():
    value = case()
    yield {**value, "checks": [False]}, "rejected", "ordered list of checked conditions"
    yield {**value, "checks": {"2": value["checks"][0]}}, "rejected", "ordered list of checked conditions"
    yield {**value, "checks": [entry("wrong", 1, integer(1))]}, "rejected", "belong to the new column slot"
    yield {**value, "checks": [entry("table", None, integer(1))]}, "rejected", "belong to the new column slot"
    yield {**value, "checks": [entry("outside", 2, compare(">", column(3), integer(0)))]}, "rejected", "visible occurrence"
    implicit = {"name": "operand", "glyph": ">", "quoted": False, "before": " ", "after": " ", "zeroes": 0}
    yield {**value, "checks": [entry(implicit, 2, compare(">", column(2), integer(0)))]}, "rejected", "must identify the resolved column"
    declarations = prior_definitions(value)
    for sql, status, message in (
        ("ALTER TABLE main.ir_add_checks ADD checked INTEGER CHECK (checked>0 AND checked<8)", "rejected", "checked implicit comparison"),
        ("ALTER TABLE main.ir_add_checks ADD checked INTEGER CONSTRAINT limited NOT NULL", "rejected", "constraint names on inline CHECK"),
        ("ALTER TABLE main.ir_add_checks ADD checked INTEGER CONSTRAINT limited CHECK (missing>0)", "analysis-rejected", "Column missing does not exist"),
        ("ALTER TABLE main.ir_add_checks ADD checked INTEGER CONSTRAINT limited CHECK (?2>0)", "analysis-rejected", "Parameters are prohibited in CHECK"),
        ("ALTER TABLE missing ADD checked INTEGER CONSTRAINT limited CHECK (checked>0)", "analysis-rejected", "Relation missing does not exist"),
    ):
        yield {"sql": sql, "declarations": declarations}, status, message


def before_rows(value, schema):
    selected = schema == value["schema"]
    population = value["population"] if selected else "mixed"
    if population == "empty":
        return []
    index = 1 if schema == "main" else 40
    second = {"below": 0, "mixed": 7, "null": None}[population]
    tail = tuple(range(4, len(value["priorHeading"]) + 2))
    return [(index, 1, *tail), (index + 3, second, *tail)]


def computed(value, row, explicit=None, supplied=False):
    main = value["column"]
    if len(main) == 6:
        return evaluate(main[5][1], {}, row)
    if supplied:
        return explicit
    return None if main[3] is None else evaluate(main[3], {})


def row_values(value, row, explicit=None, supplied=False):
    return (*row, computed(value, row, explicit, supplied))


def rejected_by(value, row):
    condition = next((rule for rule in value["checks"] if evaluate(rule["condition"], {}, row) == 0), None)
    return condition


def status(value):
    rows = before_rows(value, value["schema"])
    stored = replacements(value) == "STORED"
    labels = (f"raise(ABORT,'{STORED_ERROR}')",) if stored else (STRICT_CHECK_HEADING,)
    problem = None
    if rows and stored:
        problem = STORED_ERROR
    elif any(rejected_by(value, row_values(value, row)) for row in rows):
        problem = LOADED
    record = statement(labels, error(problem, 1) if problem else None)
    slots = {}
    for check in value["checks"]:
        if check["condition"][0] == "folded-membership":
            for label in check["condition"][2]:
                slots.setdefault(str(int(label)), "?" + label)
    record.update(parameters=slots, parameterCount=max(map(int, slots), default=0))
    return record


def picture(value, applied=False, rewritten=None, renamed=None):
    camera = {}
    names = value["priorHeading"]
    for schema in SCHEMAS:
        selected = applied and schema == value["schema"]
        fields = list(names)
        strict = value["strict"] if schema == value["schema"] else not value["strict"]
        columns = [[n, label, "INTEGER", 0, False, int(n == 0), 0] for n, label in enumerate(names)]
        rows = before_rows(value, schema)
        if selected:
            name, kind, policy, default, *_ = value["column"]
            fields.append(name)
            mode = {None: 0, "VIRTUAL": 2, "STORED": 3}[replacements(value)]
            columns.append([len(names), name, kind, int(bool(policy)), default is not None, 0, mode])
            rows = [row_values(value, row) for row in rows] if rewritten is None else rewritten
            if renamed is not None:
                rename_slot, label, _quote = renamed
                fields[rename_slot] = label
                columns[rename_slot][1] = label
        rowset = [[typed(value) for value in row] for row in rows]
        camera[schema] = {"root": [typed("table"), typed(value["table"]), typed(value["table"]), typed(2)],
                          "strict": strict, "fields": fields, "columns": normalized(columns), "rows": rowset}
    return camera


def write_inputs(value):
    width = len(value["priorHeading"])
    spare = (None,) * (width - 2)
    writes = [
        ("insert-first", (90, 1, *spare), False, None, False),
        ("insert-large", (92, 9, *spare), False, None, False),
        ("insert-null", (94, None, *spare), False, None, False),
        ("update-base", (90, 13, *spare), True, None, False),
    ]
    if replacements(value) is None:
        writes.extend([
            ("insert-explicit-value", (96, 1, *spare), False, 20, True),
            ("insert-explicit-null", (98, 2, *spare), False, None, True),
        ])
    return writes


def named_label(check, _):
    return check["label"]


def after_writes(value, label_of=named_label, renamed=None):
    rows = {row[0]: row_values(value, row) for row in before_rows(value, value["schema"])}
    receipts = {}
    for name, row, update, explicit, supplied in write_inputs(value):
        if update and row[0] not in rows:
            receipts[name] = statement()
            continue
        if update and replacements(value) is None:
            supplied, explicit = True, rows[row[0]][-1]
        observed = row_values(value, row, explicit, supplied)
        blocker = rejected_by(value, observed)
        if blocker:
            receipts[name] = statement(error=error(f"{LOADED}: {label_of(blocker, renamed)}"))
        else:
            receipts[name] = statement(changes=1)
            rows[row[0]] = observed
    return {"statements": receipts, "rows": picture(value, True, [rows[key] for key in sorted(rows)], renamed),
            "rolledBack": picture(value, True, renamed=renamed), "transaction": True}


def expected(value, label_of=named_label):
    initial = picture(value)
    result = status(value)
    success = result["evaluationError"] is None
    return {"before": initial, "statement": result, "after": picture(value, success), "rolledBack": initial,
            "transaction": True, "writes": after_writes(value, label_of) if success else None}


def forms(measured):
    value, observed = measured
    prior = observed["before"][value["schema"]]
    yield f"schema/{value['schema']}"
    yield f"prior-width/{len(prior['fields'])}"
    yield f"mode/{value['column'][5][0] if len(value['column']) == 6 else 'ORDINARY'}"
    yield f"count/{len(value['checks'])}"
    yield f"strict/{prior['strict']}"
    yield f"empty-label/{any(check['label'] == '' for check in value['checks'])}"
    yield f"result/{observed['statement']['evaluationError'] is None}"


def required(_):
    return {"schema/main", "schema/temp", "strict/True", "strict/False", "mode/VIRTUAL", "mode/STORED", "mode/ORDINARY",
            "count/1", "count/2", "count/3", "result/True", "result/False", "empty-label/True", "empty-label/False",
            *(f"prior-width/{width}" for width in range(2, 8))}
