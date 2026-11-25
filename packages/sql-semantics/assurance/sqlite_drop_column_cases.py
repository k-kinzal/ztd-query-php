"""Independent declaration and row transitions for SQLite DROP COLUMN."""

from itertools import product
from random import Random

from run_sqlite_create_table import quoted
from sqlite_add_column_cases import SCHEMAS, error, normalized, statement
from sqlite_engine import typed

TABLE = "ir_drop"
AXES = ("schemas", "topology", "positions", "population", "definitions", "externalEffects")
FIELDS = ("operation", "schema", "table", "slot")
SIBLING = ("id", "other_note")
DEPENDENT = "dependent_object"


def case(schema="main", topology="rowid-key", slot=1, population="typed-null", definition="plain",
         dependency="none", *, names=("id", "old", "amount", "total"), generated_mode="STORED", sibling=SIBLING):
    survivors = [index for index in range(len(names)) if index != slot]
    base = survivors[0]
    generated = {"surviving-generated": survivors[-1], "removed-generated": slot}.get(definition)
    guard = {"own-check": slot, "independent-check": survivors[-1], "rowid-check": slot}.get(definition)
    retained = next((index for index in survivors if index not in (0, generated)), base)
    protected = {0} if topology != "rowid" else set()
    if generated is not None:
        protected.add(base)
    if definition == "independent-check":
        protected.add(guard)
    return {
        "operation": "drop-column", "schema": schema, "table": TABLE, "slot": slot,
        "heading": [[name, index == generated, index in protected] for index, name in enumerate(names)],
        "topology": topology, "population": population, "definition": definition, "dependency": dependency,
        "guardSlot": guard, "generatedSlot": generated, "generatedInput": base, "generatedMode": generated_mode,
        "retainedIndexSlot": retained, "sibling": list(sibling),
    }


def finite(domain):
    for identity in product(*(domain[key] for key in AXES)):
        yield case(*identity)


def finite_ids(domain):
    return set(product(*(range(len(domain[key])) for key in AXES)))


def finite_id(measured, domain):
    response, observed = measured
    before = observed["before"][response["schema"]]
    catalogue = before["columns"]
    topology = "strict" if before["strict"] else "without-rowid" if before["withoutRowid"] else "rowid-key" if catalogue[0][5] == typed(1) else "rowid"
    rows = before["rows"]
    population = "empty" if not rows else "typed-null" if any(typed(None) in row for row in rows) else "duplicate"
    slot = response["slot"]
    generated = next((index for index, column in enumerate(catalogue) if column[6] != typed(0)), None)
    rejected = [index for index, result in enumerate(before["guards"]) if result is not None and result["evaluationError"] is not None]
    if generated is not None:
        definition = "removed-generated" if generated == slot else "surviving-generated"
    elif rejected:
        definition = "own-check" if rejected == [slot] else "independent-check"
    else:
        definition = "plain"
    dependency = before["dependency"]
    if dependency is None:
        effect = "none"
    elif dependency["kind"] == "view":
        effect = "blocked-view"
    else:
        effect = "blocked-index" if dependency["column"] == slot else "retained-index"
    outcome = (response["schema"], topology, slot, population, definition, effect)
    return tuple(domain[key].index(value) for key, value in zip(AXES, outcome))


def seeded(domain, seed, amount):
    rng = Random(seed)
    for i in range(amount):
        width = 3 + i % 6
        topology = domain["topology"][i % 4]
        names = tuple(f'{position}-"{rng.choice(("a","日","É","ح"))}"' for position in range(width))
        position = rng.randrange(0 if topology == "rowid" else 1, width)
        dependency = domain["externalEffects"][i % 4]
        if dependency == "blocked-index" and i % 3 == 2:
            dependency = "blocked-view"
        yield case(domain["schemas"][i % 2], topology, position, domain["population"][i % 3],
                   domain["definitions"][i % 5], dependency, names=names, generated_mode="VIRTUAL" if i % 3 else "STORED")


def column_kinds(item):
    return ["INTEGER"] * len(item["heading"])


def inline_guard(item):
    return {"own-check": "owned_guard", "independent-check": "retained_guard", "rowid-check": "rowid_rebound"}.get(item["definition"])


def definitions(item):
    target = quoted(item["table"])
    names = [member[0] for member in item["heading"]]
    membership = item["definition"]
    parts = []
    for ordinal, (name, storage) in enumerate(zip(names, column_kinds(item))):
        field = f"{quoted(name)} {storage}"
        if ordinal == 0 and item["topology"] != "rowid":
            field += " PRIMARY KEY"
        if ordinal == item["generatedSlot"]:
            operand = quoted(names[item["generatedInput"]])
            field += f" GENERATED ALWAYS AS ({operand}+100) {item['generatedMode']}"
        if item["guardSlot"] == ordinal and membership == "own-check":
            field += f" CONSTRAINT {inline_guard(item)} CHECK ({quoted(name)}>=0)"
        parts.append(field)
    if membership == "independent-check":
        guarded = quoted(names[item["guardSlot"]])
        parts.append(f"CONSTRAINT retained_guard CHECK ({guarded}>=0)")
    if membership == "rowid-check":
        parts.append("CONSTRAINT rowid_rebound CHECK (rowid >= 0)")
    if membership == "folded-check":
        parts.append(f"CONSTRAINT folded CHECK (({quoted(names[item['slot']])}+1) NOT IN ())")
    suffix = {"strict": " STRICT", "without-rowid": " WITHOUT ROWID"}.get(item["topology"], "")
    selected = item["schema"]
    sibling = next(schema for schema in SCHEMAS if schema != selected)
    return (f"CREATE TABLE {selected}.{target} ({', '.join(parts)}){suffix}",
            f"CREATE TABLE {sibling}.{target} (id INTEGER PRIMARY KEY, {quoted(item['sibling'][1])} TEXT)")


def authored():
    yield case("main", "rowid", 0, "duplicate"), 'ALTER TABLE MAIN.ir_drop DROP id'
    yield case("temp", "rowid-key", 2, names=("id", "amount", 'old "表"', "left")), 'ALTER TABLE ir_drop DROP COLUMN "old ""表"""'
    yield case("main", "rowid-key", 1, "typed-null", "rowid-check", names=("id", "RowID", "a", "b")), 'ALTER TABLE main.IR_DROP DROP COLUMN ROWID'
    yield case("main", "strict", 2, "typed-null", "folded-check"), 'ALTER TABLE main.IR_DROP DROP AMOUNT'
    yield case("temp", "without-rowid", 3, "typed-null", "surviving-generated", "retained-index"), 'ALTER TABLE ir_drop DROP TOTAL'
    yield case("main", "rowid-key", 1, "typed-null", "removed-generated", generated_mode="VIRTUAL"), 'ALTER TABLE main.ir_drop DROP old'
    yield case("temp", "rowid", 2, "empty", "own-check", "blocked-index"), 'ALTER TABLE ir_drop DROP COLUMN amount'
    yield case("main", "rowid", 1, "duplicate", "independent-check", "blocked-view"), 'ALTER TABLE main.ir_drop DROP old'
    yield case("main", dependency="retained-index", sibling=("id", "amount")), 'ALTER TABLE main.ir_drop DROP old'
    yield case("main", population="duplicate", dependency="blocked-index", sibling=("id", "old")), 'ALTER TABLE main.ir_drop DROP old'


def requests(contract):
    for ordinal, value in enumerate(finite(contract["finite"])):
        yield f"finite/{ordinal}", value, value
    for seed in contract["exploration"]["seeds"]:
        for ordinal, value in enumerate(seeded(contract["finite"], seed, contract["exploration"]["casesPerSeed"])):
            yield f"seed/{seed}/{ordinal}", value, value
    for ordinal, (value, sql) in enumerate(authored()):
        yield f"analyze/{ordinal}", {"sql": sql, "declarations": definitions(value)}, value
    maximum = case("main", "rowid", 1949, "empty", names=tuple(f"c{index}" for index in range(2000)))
    yield "boundary/2000", maximum, maximum


def malformed_protected():
    value = case()
    value["slot"] = 0
    return value


def malformed_generated():
    value = case("main", "rowid", 0)
    for field in value["heading"][1:]:
        field[1] = True
    return value


def negatives():
    item = case()
    yield {**item, "heading": {"1": item["heading"][0], "2": item["heading"][1]}}, "rejected", "requires an ordered heading"
    yield {**item, "heading": [*item["heading"], ["OLD", False, False]]}, "rejected", "distinct declared names"
    yield {**item, "heading": [*item["heading"], False]}, "rejected", "requires an ordered heading"
    yield {**item, "slot": 4}, "rejected", "must select a declared member"
    yield case(names=tuple(f"c{n}" for n in range(2001))), "rejected", "2,000-column profile"
    yield {**item, "heading": [item["heading"][0]]}, "rejected", "at least two declared members"
    yield malformed_generated(), "rejected", "must retain an ordinary column"
    yield malformed_protected(), "rejected", "declaration-local removal restriction"
    protected = "declaration-local removal restriction"
    for sql, declarations, outcome, text in [
        ("ALTER TABLE missing DROP obsolete", (), "rejected", "resolved ordinary table"),
        ("ALTER TABLE v DROP old", (*definitions(item), "CREATE VIEW v AS SELECT old FROM main.ir_drop"), "analysis-rejected", "view"),
        ("ALTER TABLE main.ir_drop DROP rowid", definitions(item), "analysis-rejected", "Column rowid does not exist"),
        ("ALTER TABLE main.t DROP value", ("CREATE TABLE t(value)",), "analysis-rejected", "only column"),
        ("ALTER TABLE main.t DROP key_part", ("CREATE TABLE t(key_part INTEGER PRIMARY KEY, other INTEGER)",), "rejected", protected),
        ("ALTER TABLE main.t DROP key_part", ("CREATE TABLE t(key_part UNIQUE, other INTEGER)",), "rejected", protected),
        ("ALTER TABLE main.t DROP key_part", ("CREATE TABLE t(key_part, other INTEGER, UNIQUE(key_part,other))",), "rejected", protected),
        ("ALTER TABLE main.t DROP key_part", ("CREATE TABLE t(key_part, other INTEGER, PRIMARY KEY(key_part,other))",), "rejected", protected),
        ("ALTER TABLE main.t DROP checked", ("CREATE TABLE t(checked, other INTEGER, CHECK (checked>0))",), "rejected", protected),
        ("ALTER TABLE main.t DROP checked", ("CREATE TABLE t(checked, other INTEGER AS (checked+1))",), "rejected", protected),
        ("ALTER TABLE main.t DROP checked", ("CREATE TABLE t(checked, other INTEGER, FOREIGN KEY (checked) REFERENCES parent(id))",), "rejected", protected),
        ("ALTER TABLE main.t DROP a", ("CREATE TABLE t(a INTEGER, b INTEGER AS (7))",), "rejected", "must retain an ordinary column"),
    ]:
        yield {"sql": sql, "declarations": declarations}, outcome, text


def input_rows(item):
    if item["population"] == "empty":
        return []
    width = len(item["heading"])
    typed_null = item["population"] == "typed-null"
    counts = (0, 1, 2) if typed_null else (0, 0, 0)
    rows = []
    for row, group in enumerate(counts):
        values = [None if typed_null and index != 0 and (group + index) % 3 == 0 else 4 + group * 10 + index for index in range(width)]
        if item["topology"] != "rowid":
            values[0] = 10 + row
        rows.append(values)
    return rows


def projected_row(item, row):
    values = list(row)
    generated = item["generatedSlot"]
    base = row[item["generatedInput"]]
    if generated is not None:
        values[generated] = None if base is None else base + 100
    return values


def positions(item, dropped):
    return [slot for slot in range(len(item["heading"])) if not dropped or slot != item["slot"]]


def guard_receipt(name, violated):
    return statement(error=error(f"CHECK constraint failed: {name}")) if violated else statement(changes=1)


def dependency_state(item, actual):
    effect = item["dependency"]
    if effect == "none":
        return None
    if effect == "blocked-view":
        return {"kind": "view", "name": DEPENDENT, "column": None}
    slot = item["slot"] if effect == "blocked-index" else item["retainedIndexSlot"]
    return {"kind": "index", "name": DEPENDENT, "column": actual.index(slot) if slot in actual else -2}


def target_state(item, applied=False, appended=False):
    actual = positions(item, applied)
    names = [item["heading"][slot][0] for slot in actual]
    topology = item["topology"]
    keyed = topology != "rowid"
    without = topology == "without-rowid"
    columns = [[position, name, "INTEGER", int(slot == 0 and without), False, int(slot == 0 and keyed),
                (2 if item["generatedMode"] == "VIRTUAL" else 3) if slot == item["generatedSlot"] else 0]
               for position, (slot, name) in enumerate(zip(actual, names))]
    inputs = input_rows(item)
    existing_count = len(inputs)
    if appended:
        inputs.append([97 + slot for slot in range(len(item["heading"]))])
    rows, aliases = [], []
    alias_slot = next((index for index in actual if item["heading"][index][0].lower() == "rowid"), None)
    for position, source in enumerate(inputs):
        row = projected_row(item, source)
        rows.append([typed(row[slot]) for slot in actual])
        if not without:
            physical = source[0] if keyed else (97 if appended and position == len(inputs) - 1 else 20 + position)
            aliases.append([typed(row[alias_slot] if alias_slot is not None else physical), typed(physical), typed(physical)])
    restricted = 0 if applied and item["definition"] == "rowid-check" and keyed else item["guardSlot"]
    guards = None if len(columns) >= 1999 else [
        None if slot == item["generatedSlot"] else guard_receipt(inline_guard(item), slot == restricted) for slot in actual
    ]
    dependency = dependency_state(item, actual)
    integrity = [typed("ok")]
    if dependency is not None and dependency["column"] == -2 and existing_count:
        integrity = [typed(f"row {index + 1} missing from index {DEPENDENT}") for index in range(existing_count)]
    return {"root": [typed("table"), typed(item["table"]), typed(item["table"]), typed(2)],
            "integrity": [[message] for message in integrity],
            "strict": topology == "strict", "withoutRowid": without, "columns": normalized(columns),
            "fields": names, "rows": rows, "aliases": None if without else aliases, "guards": guards,
            "dependency": dependency}


def other_state(item):
    return {"root": [typed("table"), typed(item["table"]), typed(item["table"]), typed(2)],
            "strict": False, "withoutRowid": False, "columns": normalized([[0, item["sibling"][0], "INTEGER", 0, False, 1, 0],
                                                                           [1, item["sibling"][1], "TEXT", 0, False, 0, 0]]),
            "fields": item["sibling"], "rows": [[typed(73), typed("shadow")]],
            "aliases": [[typed(73)] * 3], "guards": [statement(changes=1), statement(changes=1)],
            "dependency": None, "integrity": [[typed("ok")]]}


def picture(item, applied=False, appended=False):
    return {schema: target_state(item, applied, appended) if schema == item["schema"] else other_state(item)
            for schema in SCHEMAS}


def native_error(item):
    effect = item["dependency"]
    name = item["heading"][item["slot"]][0]
    if effect in ("retained-index", "blocked-index"):
        indexed = name if effect == "blocked-index" else item["heading"][item["retainedIndexSlot"]][0]
        if item["schema"] == "main":
            return None if indexed in item["sibling"] else error(
                f"error in index {DEPENDENT} after drop column: no such column: {indexed}", 1)
        if effect == "blocked-index":
            return error(f'error in index {DEPENDENT} after drop column: no such column: "{name}"'
                         " - should this be a string literal in single-quotes?", 1)
    if effect == "blocked-view":
        return error(f"error in view {DEPENDENT} after drop column: no such column: {item['table']}.{name}", 1)
    return None


def expected(item):
    before = picture(item)
    problem = native_error(item)
    return {"before": before, "statement": statement(error=problem), "after": picture(item, problem is None),
            "write": {"result": statement(changes=1), "state": picture(item, True, True)} if problem is None else None,
            "transaction": True, "rolledBack": before}


def forms(measured):
    built, observed = measured
    prior = observed["before"][built["schema"]]
    yield f"schema/{built['schema']}"
    yield f"width/{len(prior['fields'])}"
    yield f"strict/{prior['strict']}"
    yield f"without-rowid/{prior['withoutRowid']}"
    yield f"success/{observed['statement']['evaluationError'] is None}"
    yield f"selected-generated/{built['column'][1]}"
    if prior["dependency"] is not None:
        yield f"dependency/{prior['dependency']['kind']}"


def required(_domain):
    return {"schema/main", "schema/temp", "strict/True", "strict/False", "without-rowid/True", "without-rowid/False",
            "success/True", "success/False", "selected-generated/True", "selected-generated/False",
            "dependency/index", "dependency/view", *(f"width/{width}" for width in range(3, 9))}
