"""Generate checked trigger controls and predict native interruption effects."""

from copy import deepcopy
from itertools import product
from random import Random

from sqlite_add_column_cases import error, statement
from sqlite_engine import typed
from sqlite_trigger_cases import STRUCTURE, field, qualified
from sqlite_trigger_mutation_cases import literal
from run_sqlite_create_table import quoted

AXES = ("schemas", "timings", "actions", "conditions", "transaction", "outer")
SCHEMAS = ("main", "temp", "archive")
WATCH = ("id", "guard", "message")
AUDIT = ("label", "source")
IMAGE_ERROR = "available event image and declared watched position"
POLICY_ERROR = "needs a message for every policy except IGNORE"
QUERY_ERROR = "needs one source-free projection without additional evaluation clauses"


def audit_step(schema, audit, name, image):
    return {"table": [schema, audit, list(AUDIT)], "writable": [0, 1], "conflict": "inherit",
            "fields": [[0, ["text", name]], [1, field(image, 0)]]}


def make_case(schema="main", timing="AFTER", action=("FAIL", "text"), predicate="selected", transaction=False,
              outer="inherit", *, event="INSERT", name="marker", audit="audit", watched="watched",
              before=("before",), after=("after",), use_old=False, guard_false_polarity=False, override_message=None):
    policy, mode = action
    scope = "main" if schema == "temp" else schema
    image = "OLD" if event == "DELETE" or use_old else "NEW"
    image_id = "OLD" if event == "DELETE" else "NEW"
    operand, value = {
        "absent": (None, None), "literal": (["text", "literal note"], None),
        "text": (field(image, 2), "拒否 ñ"), "null": (field(image, 2), None), "integer": (field(image, 2), -128),
        "literal-null": (["null"], None), "literal-int": (["integer", "-21"], None),
    }[mode]
    if override_message is not None:
        value = override_message
    where = None if predicate == "unconditional" else [field(image, 1), not guard_false_polarity]
    guard = 1 if (predicate == "not-selected") != guard_false_polarity else None
    candidate = (17, guard, value)
    other = (17, 1 if guard is None else None, "earlier message")
    prior = [] if event == "INSERT" else [candidate if image == "OLD" else other]
    final = [] if event == "DELETE" else [other if image == "OLD" else candidate]
    setup = [audit_step(schema, audit, part, image_id) for part in before]
    later = [audit_step(schema, audit, part, image_id) for part in after]
    return {
        "operation": "create-trigger", "schema": schema, "name": name, "watched": [scope, watched, list(WATCH)],
        "event": event, "timing": timing, "selectors": [], "condition": None, "ifNotExists": False,
        "placement": "temporary-keyword" if schema == "temp" else "stored",
        "program": [*setup, {"action": "raise", "policy": policy, "message": operand, "where": where}, *later],
        "transaction": transaction, "outer": outer, "audit": audit,
        "prior": prior, "final": final, "bound": candidate,
    }


def operand_sql(item, node):
    if node[0] == "field":
        return node[1] + "." + quoted(item["watched"][2][node[2]])
    if node[0] == "integer":
        return node[1]
    return literal(node[1] if node[0] == "text" else None)


def action_sql(item, action):
    if action.get("action") != "raise":
        values = ", ".join(operand_sql(item, value) for _, value in action["fields"])
        return f"INSERT INTO {quoted(action['table'][1])} VALUES ({values})"
    message = "" if action["message"] is None else ", " + operand_sql(item, action["message"])
    where = action["where"]
    condition = "" if where is None else (
        " WHERE (" + operand_sql(item, where[0]) + ") IS " + ("NULL" if where[1] else "NOT NULL")
    )
    return f'SELECT RAISE({action["policy"]}{message})' + condition


def source(item):
    schema, name = item["schema"], item["name"]
    head = "CREATE TEMP TRIGGER " + quoted(name) if schema == "temp" else "CREATE TRIGGER " + qualified(schema, name)
    watch = qualified(*item["watched"][:2])
    body = " ".join(action_sql(item, action) + ";" for action in item["program"])
    return f'{head} {item["timing"]} {item["event"]} ON {watch} BEGIN {body} END'


def declarations(item):
    for schema in SCHEMAS:
        watched = qualified(schema, item["watched"][1])
        audit = qualified(schema, item["audit"])
        yield f"CREATE TABLE {watched}(id INTEGER PRIMARY KEY, guard, message)"
        yield f"CREATE TABLE {audit}(label,source)"


def analyze(item, sql=None):
    return {"sql": source(item) if sql is None else sql, "declarations": list(declarations(item)),
            "searchPath": ["main", "archive"], "catalogComplete": True}


def read_value(item, node):
    kind = node[0]
    if kind == "field":
        return (item["prior"] if node[1] == "OLD" else item["final"])[0][node[2]]
    if kind == "integer":
        return int(node[1])
    return None if kind == "null" else node[1]


def snapshot(item, recorded, marker, wrote):
    report = {}
    for index, schema in enumerate(SCHEMAS):
        watched = item["prior"] if schema != item["watched"][0] or not wrote else item["final"]
        audit = [("fixture", index)]
        if schema == item["schema"]:
            audit += ([("transaction", 99)] if marker else []) + recorded
        report[schema] = {
            "watched": {"names": list(WATCH), "rows": [[typed(value) for value in row] for row in watched]},
            "audit": {"names": list(AUDIT), "rows": [[typed(value) for value in row] for row in audit]},
        }
    return report


def expected(item):
    marker = item["transaction"]
    before = snapshot(item, [], marker, False)
    effects, executed, interrupted, failure, aborted = [], 0, False, None, False
    wrote = item["timing"] == "AFTER"
    for step in item["program"]:
        if step.get("action") != "raise":
            effects.append(tuple(read_value(item, value) for _, value in step["fields"]))
            executed += 1
            continue
        condition = step["where"]
        if condition is not None and (read_value(item, condition[0]) is None) != condition[1]:
            continue
        interrupted = True
        policy = step["policy"]
        if policy != "IGNORE":
            message = read_value(item, step["message"])
            failure = error("" if message is None else str(message))
        if policy in ("ABORT", "ROLLBACK"):
            effects = []
            wrote = False
            aborted = policy == "ROLLBACK" and marker
        break
    wrote = wrote or not interrupted
    active_after = marker and not aborted
    after = snapshot(item, effects, active_after, wrote)
    command = statement(changes=executed + int(wrote), error=failure)
    command["parameterCount"] = 1 if item["event"] == "DELETE" else 3
    schema, name, watched = item["schema"], item["name"], item["watched"][1]
    return {
        "before": before, "definition": statement(), "after": after, "activation": command,
        "catalog": {slot: [[typed(name), typed(watched)]] if slot == schema else [] for slot in SCHEMAS},
        "authorization": [[5 if schema == "temp" else 7, name, watched, schema]],
        "bound": [typed(value) for value in item["bound"]],
        "transactionBefore": marker, "transactionAfter": active_after,
        "settled": snapshot(item, [], False, False) if active_after else after,
    }


def finite_ids(domain):
    return set(product(*(range(len(domain[axis])) for axis in AXES)))


def raised(program):
    return next(action for action in program if action.get("action") == "raise")


def measured_id(measured, domain):
    reply, actual, outer = measured
    effect = raised(reply["program"])
    policy, value = effect["policy"], effect["message"]
    if value is None:
        form = "absent"
    elif value[0] == "text":
        form = "literal"
    else:
        form = actual["bound"][2][0]
    guard = effect["where"]
    filter = "unconditional" if guard is None else "selected" if actual["bound"][1] == ["null", None] else "not-selected"
    coordinate = (reply["schema"], reply["timing"], [policy, form], filter, actual["transactionBefore"], outer)
    return tuple(domain[axis].index(value) for axis, value in zip(AXES, coordinate))


def seeded(specification, seed):
    random = Random(seed)
    for index in range(specification["casesPerSeed"]):
        event = ("INSERT", "UPDATE", "DELETE")[index % 3]
        kind = ("IGNORE", "FAIL", "ABORT", "ROLLBACK")[(index // 2) % 4]
        mode = "absent" if kind == "IGNORE" else ("text", "null", "integer", "literal", "literal-null", "literal-int")[index % 6]
        before = tuple(f"starting {index}-{step}" for step in range(1 + (index // 3) % 3))
        after = tuple(f"later {index}-{step}" for step in range(1 + index % 2))
        yield make_case(SCHEMAS[(index // 3) % 3], "AFTER" if index % 2 else "BEFORE", (kind, mode),
                        ("selected", "unconditional", "not-selected")[(index // 2) % 3],
                        bool(index % 3), "IGNORE" if index % 4 == 0 else "inherit",
                        event=event, name=f'control "{seed}-{index}"', audit=random.choice(("audit", "日誌 ñ")),
                        watched=random.choice(("watched", 'accounts "ledger"')), before=before, after=after,
                        use_old=event == "UPDATE" and index % 2 == 0, guard_false_polarity=bool(index % 2))


def requests(specification):
    domain = specification["finite"]
    for index, axis in enumerate(product(*(domain[key] for key in AXES))):
        item = make_case(*axis)
        yield f"finite/{index}", analyze(item), item
        yield f"direct/{index}", item, item
    for seed in specification["exploration"]["seeds"]:
        for index, item in enumerate(seeded(specification["exploration"], seed)):
            yield f"seed/{seed}/{index}", item, item


def with_raise(item, **overrides):
    copy = deepcopy(item)
    raised(copy["program"]).update(overrides)
    return copy


def negatives():
    fault = make_case()
    yield with_raise(fault, policy="IGNORE"), "rejected", POLICY_ERROR
    for policy in ("FAIL", "ABORT", "ROLLBACK"):
        yield with_raise(fault, policy=policy, message=None), "rejected", POLICY_ERROR
    yield with_raise(fault, message=field("OLD", 2)), "rejected", IMAGE_ERROR
    yield with_raise(fault, message=field("NEW", 3)), "rejected", IMAGE_ERROR
    yield with_raise(fault, where=[field("OLD", 1), True]), "rejected", IMAGE_ERROR
    yield with_raise(fault, where=[field("NEW", 3), True]), "rejected", IMAGE_ERROR
    yield analyze(fault, "SELECT RAISE(FAIL, 'fixed')"), "analysis-rejected", "RAISE() may only be used within a trigger-program"
    prefix = "CREATE TRIGGER tr AFTER INSERT ON watched BEGIN SELECT "
    for tail in ("RAISE(FAIL, NEW.message) FROM audit", "RAISE(FAIL, NEW.message), 1", "RAISE(FAIL, NEW.message) LIMIT 0"):
        yield analyze(fault, prefix + tail + "; END"), "rejected", QUERY_ERROR
    yield analyze(fault, prefix + "RAISE(FAIL, NEW.unavailable); END"), "analysis-rejected", "Column NEW.unavailable does not exist"
    yield analyze(fault, prefix + "RAISE(FAIL, ?1); END"), "analysis-rejected", "trigger cannot use variables"


def forms(measured):
    reply, observed, _ = measured
    action = raised(reply["program"])
    yield "scope/" + reply["schema"]
    yield "event/" + reply["event"]
    yield "timing/" + reply["timing"]
    yield "policy/" + action["policy"]
    yield f'execution-error/{observed["activation"]["evaluationError"] is not None}'
    yield f'transaction/{observed["transactionBefore"]}'
    yield "program/" + ("long" if len(reply["program"]) > 3 else "short")
    if action["message"] is not None:
        value = action["message"]
        yield "message/" + value[0]
        if value[0] == "field":
            yield "image/" + value[1]
    if action["where"] is not None:
        yield "polarity/" + str(action["where"][1])


def required(_):
    return {"scope/main", "scope/temp", "scope/archive", "event/INSERT", "event/UPDATE", "event/DELETE",
            "timing/BEFORE", "timing/AFTER", "policy/IGNORE", "policy/FAIL", "policy/ABORT", "policy/ROLLBACK",
            "execution-error/True", "execution-error/False", "transaction/True", "transaction/False",
            "program/long", "program/short", "message/text", "message/null", "message/integer", "message/field",
            "image/OLD", "image/NEW", "polarity/True", "polarity/False"}
