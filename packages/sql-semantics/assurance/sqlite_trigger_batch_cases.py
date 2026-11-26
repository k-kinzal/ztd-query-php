"""Generate native trigger batches and predict partial effects independently."""

from copy import deepcopy
from itertools import product
from random import Random

from run import require
from sqlite_add_column_cases import error, statement
from sqlite_engine import typed
from sqlite_trigger_cases import STRUCTURE, field, qualified
from sqlite_trigger_mutation_cases import literal
from run_sqlite_create_table import quoted

AXES = ("schemas", "groupings", "policies", "collisions", "transaction", "outer")
SCHEMAS = ("main", "temp", "archive")
IDS = {"none": (1, 3, 4), "first": (2, 1, 3), "middle": (1, 2, 3), "last": (1, 3, 2)}
WATCH = ("id", "payload")
LEDGER = ("k", "payload")
HEADINGS = {"watched": WATCH, "ledger": LEDGER, "effects": ("seq", "k", "stamp")}
HEADING_ERROR = "same ordered writable heading"
IMAGE_ERROR = "available event image and declared watched position"


def record(schema, name, rows, policy):
    return {"table": [schema, name, list(LEDGER)], "writable": [0, 1], "conflict": policy.lower(),
            **({"fields": rows[0]} if len(rows) == 1 else {"rows": rows})}


def make_case(schema="main", grouping="batch", policy="ABORT", collision="middle", transaction=False, outer="inherit", *,
              name="tracer", ledger="ledger", watched="watched", keys=None, inputs=None, value="payload", groups=None, swapped=False):
    watch = "main" if schema == "temp" else schema
    keys = keys if keys is not None else IDS[collision]
    values = inputs if inputs is not None else [field("NEW", 1), ["null"], ["text", "tail"]]
    rows = [[[0, ["integer", str(key)]], [1, cell]] for key, cell in zip(keys, values)]
    if swapped:
        rows = [list(reversed(row)) for row in rows]
    chosen = groups if groups is not None else [len(rows)] if grouping == "batch" else [1] * len(rows)
    cursor, program = 0, []
    for width in chosen:
        program.append(record(schema, ledger, rows[cursor:cursor + width], policy))
        cursor += width
    return {
        "operation": "create-trigger", "schema": schema, "name": name, "watched": [watch, watched, list(WATCH)],
        "event": "INSERT", "timing": "AFTER", "selectors": [], "condition": None, "ifNotExists": False,
        "placement": "temporary-keyword" if schema == "temp" else "stored", "program": program,
        "ledger": ledger, "transaction": transaction, "outer": outer, "activationValue": value,
    }


def tuples(insert):
    return insert["rows"] if "rows" in insert else [insert["fields"]]


def operand_sql(item, role):
    if role[0] == "field":
        return role[1] + "." + quoted(item["watched"][2][role[2]])
    if role[0] == "integer":
        return role[1]
    return literal(role[1] if role[0] == "text" else None)


def insertion_sql(item, insert):
    policy = "" if insert["conflict"] == "inherit" else " OR " + insert["conflict"].upper()
    rows = tuples(insert)
    columns = ", ".join(quoted(LEDGER[column]) for column, _ in rows[0])
    operands = ", ".join("(" + ", ".join(operand_sql(item, value) for _, value in row) + ")" for row in rows)
    return "INSERT" + policy + " INTO " + quoted(insert["table"][1]) + "(" + columns + ") VALUES " + operands


def source(item):
    name = item["name"]
    head = "CREATE TEMP TRIGGER " + quoted(name) if item["schema"] == "temp" else \
        "CREATE TRIGGER " + qualified(item["schema"], name)
    body = " ".join(insertion_sql(item, step) + ";" for step in item["program"])
    return f"{head} AFTER INSERT ON {qualified(*item['watched'][:2])} BEGIN {body} END"


def declarations(item):
    for schema in SCHEMAS:
        yield f'CREATE TABLE {qualified(schema, item["watched"][1])}(id INTEGER PRIMARY KEY, payload)'
        yield f'CREATE TABLE {qualified(schema, item["ledger"])}(k INTEGER PRIMARY KEY, payload)'


def analyze(item, sql=None):
    return {"sql": source(item) if sql is None else sql, "declarations": list(declarations(item)),
            "searchPath": ["main", "archive"], "catalogComplete": True}


def schema_state(item, marker):
    result = {schema: {
        "watched": [], "ledger": [(0, SCHEMAS.index(schema)), (2, "decoy")], "effects": []
    } for schema in SCHEMAS}
    if marker:
        slot = result[item["schema"]]
        slot["ledger"].append((99, "transaction-marker"))
        slot["effects"].append((1, 99, "transaction-marker"))
    return result


def materialize(item, row):
    cells = [None, None]
    for position, value in row:
        kind = value[0]
        if kind == "field":
            cells[position] = (101, item["activationValue"])[value[2]]
        elif kind == "integer":
            cells[position] = int(value[1])
        elif kind == "text":
            cells[position] = value[1]
    return tuple(cells)


def simulate(item):
    before = schema_state(item, marker=item["transaction"])
    state = deepcopy(before)
    state[item["watched"][0]]["watched"].append((101, item["activationValue"]))
    target = state[item["schema"]]
    records = dict(target["ledger"])
    witness = target["effects"]
    completed, active, changes = 0, item["transaction"], 1
    for step in item["program"]:
        policy = item["outer"].lower() if item["outer"] != "inherit" else step["conflict"]
        completed_in_step = 0
        for fields in tuples(step):
            key, payload = materialize(item, fields)
            duplicate = key in records
            if duplicate and policy == "ignore":
                continue
            if duplicate and policy != "replace":
                changes = 2 * completed + completed_in_step + int(policy == "fail")
                if policy == "fail":
                    target["ledger"] = sorted(records.items())
                elif policy == "rollback" and active:
                    active = False
                    state = schema_state(item, False)
                else:
                    state = deepcopy(before)
                return before, state, active, changes, error(f"UNIQUE constraint failed: {item['ledger']}.k")
            records[key] = payload
            witness.append((len(witness) + 1, key, payload))
            completed_in_step += 1
        completed += completed_in_step
    target["ledger"] = sorted(records.items())
    return before, state, active, changes + 2 * completed, None


def typed_state(data):
    return {schema: {name: {"names": list(HEADINGS[name]), "rows": [[typed(value) for value in row] for row in rows]}
                     for name, rows in relations.items()} for schema, relations in data.items()}


def expected(item):
    before, committed, active, changes, issue = simulate(item)
    target, name, relation = item["schema"], item["name"], item["watched"][1]
    fixed = {schema: [[typed(name), typed(relation)]] if schema == target else [] for schema in SCHEMAS}
    return {
        "before": typed_state(before), "after": typed_state(committed), "definition": statement(),
        "catalog": fixed, "authorization": [[5 if target == "temp" else 7, name, relation, target]],
        "activation": statement(changes=changes, error=issue), "transactionBefore": item["transaction"],
        "transactionAfter": active, "settled": typed_state(schema_state(item, False) if active else committed),
    }


def finite_ids(domain):
    return set(product(*(range(len(domain[axis])) for axis in AXES)))


def measured_id(measured, domain):
    reply, observed, submitted = measured
    before = observed["before"][reply["schema"]]["ledger"]["rows"]
    keys_before = {int(row[0][1]) for row in before}
    inserts = reply["program"]
    keys = tuple(int(row[0][1][1]) for step in inserts for row in tuples(step))
    sequence = next(label for label, old in IDS.items() if old == keys)
    require(2 in keys_before and (99 in keys_before) == observed["transactionBefore"],
            "sqlite-trigger-batch-transaction-control", sorted(keys_before))
    policy = inserts[0]["conflict"]
    parts = (reply["schema"], "batch" if len(inserts) == 1 else "separate",
             policy if policy == "inherit" else policy.upper(), sequence,
             observed["transactionBefore"], submitted)
    return tuple(domain[axis].index(value) for axis, value in zip(AXES, parts))


def seeded(configuration, seed):
    draw = Random(seed)
    for index in range(configuration["casesPerSeed"]):
        scope = SCHEMAS[index % len(SCHEMAS)]
        policy = ("inherit", "ABORT", "FAIL", "IGNORE", "REPLACE", "ROLLBACK")[(index // 2) % 6]
        width = 1 + index % 5
        keys = draw.sample([1, 3, 4, 5, 6, 7, 8], width)
        if index % 3:
            keys[draw.randrange(len(keys))] = 2
        groups = ([width] if index % 3 == 0 or width == 1 else [1] * width if index % 3 == 1
                  else [width - 1, 1])
        options = (field("NEW", 0), field("NEW", 1), ["null"], ["integer", "71"], ["text", "slow ' ñ"])
        operands = [options[(index + i) % len(options)] for i in range(width)]
        yield make_case(scope, policy=policy, transaction=bool(index % 2), outer="IGNORE" if index % 4 == 0 else "inherit",
                        name=f'tr "{seed}-{index}"', keys=keys, inputs=operands, ledger=draw.choice(("ledger", 'journal "列"')),
                        watched=draw.choice(("watched", "order ñ")), value=draw.choice((None, 83, "observed")),
                        groups=groups, swapped=bool(index % 2))


def requests(specification):
    domain = specification["finite"]
    for index, coordinate in enumerate(product(*(domain[axis] for axis in AXES))):
        item = make_case(*coordinate)
        yield f"finite/{index}", analyze(item), item
        yield f"direct/{index}", item, item
    for seed in specification["exploration"]["seeds"]:
        for index, item in enumerate(seeded(specification["exploration"], seed)):
            yield f"seed/{seed}/{index}", item, item


def negatives():
    base = make_case()
    origin = base["program"][0]
    rows = origin["rows"]
    def invalid(patch):
        return {**base, "program": [{**origin, "rows": patch}]}
    yield invalid([[]]), "rejected", "tuple needs an ordered nonempty list"
    yield invalid([[rows[0][0], rows[0][0]]]), "rejected", "write distinct available slots"
    yield invalid([]), "rejected", "nonempty ordered row list"
    yield invalid({"1": rows[0]}), "rejected", "nonempty ordered row list"
    yield invalid(["not-a-row"]), "rejected", "nonempty ordered row list"
    yield invalid([rows[0], list(reversed(rows[1]))]), "rejected", HEADING_ERROR
    yield invalid([rows[0], [rows[1][0]]]), "rejected", HEADING_ERROR
    yield invalid([[[2, rows[0][0][1]], rows[0][1]]]), "rejected", "write distinct available slots"
    yield invalid([rows[0], [rows[1][0], [1, field("OLD", 1)]]]), "rejected", IMAGE_ERROR
    yield invalid([rows[0], [rows[1][0], [1, field("NEW", 2)]]]), "rejected", IMAGE_ERROR
    prefix = "CREATE TRIGGER tr INSERT ON watched BEGIN INSERT INTO ledger VALUES (1,NEW.payload),"
    yield analyze(base, prefix + "(2); END"), "analysis-rejected", "All VALUES must have the same number of terms"
    yield analyze(base, prefix + "(2,NEW.unavailable); END"), "analysis-rejected", "Column NEW.unavailable does not exist"
    yield analyze(base, prefix + "(2,?1); END"), "analysis-rejected", "cannot use variables"


def forms(measured):
    reply, observed, outer = measured
    yield f"outer/{outer}"
    yield "schema/" + reply["schema"]
    yield f'transaction/{observed["transactionBefore"]}'
    yield f'execution-error/{observed["activation"]["evaluationError"] is not None}'
    yield "program/" + ("single" if len(reply["program"]) == 1 else "ordered")
    for step in reply["program"]:
        yield "policy/" + step["conflict"]
        yield f"tuples/{len(tuples(step))}"
        for row in tuples(step):
            yield "first-slot/" + str(row[0][0])
            for _, operand in row:
                yield "value/" + operand[0]


def required(_):
    return {
        "outer/inherit", "outer/IGNORE", "schema/main", "schema/temp", "schema/archive", "transaction/True",
        "transaction/False", "execution-error/True", "execution-error/False", "program/single", "program/ordered",
        "policy/inherit", "policy/abort", "policy/fail", "policy/ignore", "policy/replace", "policy/rollback",
        "tuples/1", "tuples/2", "tuples/3", "tuples/4", "tuples/5", "first-slot/0", "first-slot/1",
        "value/field", "value/null", "value/integer", "value/text",
    }
