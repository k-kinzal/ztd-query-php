"""Construct checked trigger mutations and predict their row transitions."""

from copy import deepcopy
from itertools import product
from random import Random
from string import ascii_lowercase, ascii_uppercase

from run_sqlite_create_table import quoted
from sqlite_engine import typed
from sqlite_trigger_cases import STRUCTURE, field, qualified

AXES = ("schemas", "events", "timing", "actions", "filters", "payload", "rollback")
SCHEMAS = ("main", "temp", "archive")
WATCH = ("id", "binding", "payload")
LEDGER = ("id", "binding", "result", "observer")
FOLD = str.maketrans(ascii_uppercase, ascii_lowercase)
SCOPE_ERROR = "needs one consistent native scope"
IMAGE_ERROR = "available event image and declared watched position"
LOCAL_ERROR = "must read a declared position of its destination"
FILTER_ERROR = "A checked trigger mutation filter needs equality or a NULL test."


def make_case(schema="main", event="INSERT", timing="AFTER", steps="update", condition="match-key", payload=None,
              rollback=False, *, name="trace", ledger="ledger", relation="watched", collations=None, required=False):
    watch = "main" if schema == "temp" else schema
    image = "OLD" if event == "DELETE" else "NEW"
    target = {"table": [schema, ledger, list(LEDGER)], "writable": list(range(len(LEDGER)))}
    where = {
        "none": None, "match-key": ["equal", 1, field(image, 1), True],
        "missing-key": ["equal", 0, field(image, 0), True],
        "source-is-null": ["image-null", field(image, 2), True],
        "target-is-null": ["local-null", 2, True],
    }[condition]
    update = {"action": "update", **target, "where": where,
              "assignments": [[2, field(image, 2)], [3, field(image, 0)]], "conflict": "ignore" if required else "inherit"}
    delete = {"action": "delete", **target, "where": deepcopy(where)}
    program = {"update": [update], "delete": [delete], "update-delete": [update, delete],
               "delete-update": [delete, update]}[steps]
    return {
        "operation": "create-trigger", "schema": schema, "event": event, "timing": timing,
        "name": name, "placement": "temporary-keyword" if schema == "temp" else "stored",
        "watched": [watch, relation, list(WATCH)], "ifNotExists": False, "selectors": [], "condition": None,
        "program": program, "payload": payload, "rolledBack": rollback, "ledger": ledger, "required": required,
        "collations": collations, "eventId": 23 if event == "INSERT" else 19, "shadowSyntax": False,
    }


def literal(value):
    if value is None:
        return "NULL"
    if isinstance(value, int):
        return str(value)
    return "'" + value.replace("'", "''") + "'"


def value_sql(item, columns, value, *, local_shadow=False):
    kind, *parts = value
    if kind == "local":
        return ("NEW." if local_shadow else "") + quoted(columns[parts[0]])
    if kind == "field":
        return parts[0] + "." + quoted(item["watched"][2][parts[1]])
    if kind == "integer":
        return parts[0]
    return literal(parts[0] if kind == "text" else None)


def filter_sql(item, target, filter):
    if filter is None:
        return ""
    roles = target[2]
    kind, *args = filter
    if kind == "equal":
        terms = [quoted(target[1]) + "." + quoted(roles[args[0]]), value_sql(item, roles, args[1])]
        return " WHERE " + " == ".join(terms if args[2] else reversed(terms))
    if kind == "local-null":
        value = quoted(target[1]) + "." + quoted(roles[args[0]])
    else:
        value = value_sql(item, roles, args[0])
    return " WHERE ((" + value + ") IS " + ("NULL" if args[1] else "NOT NULL") + ")"


def step_sql(item, step):
    target = step["table"]
    name = quoted(target[1])
    kind = step.get("action", "insert")
    if kind == "delete":
        return "DELETE FROM " + name + filter_sql(item, target, step["where"])
    policy = "" if step["conflict"] == "inherit" else " OR " + step["conflict"].upper()
    fields = step["assignments" if kind == "update" else "fields"]
    if kind == "update":
        assignments = [quoted(target[2][index]) + "=" + value_sql(item, target[2], value,
                       local_shadow=item["shadowSyntax"] and value[0] == "local" and value[1] == 0)
                       for index, value in fields]
        return "UPDATE" + policy + " " + name + " SET " + ", ".join(assignments) + filter_sql(item, target, step["where"])
    heading = ", ".join(quoted(target[2][index]) for index, _ in fields)
    values = ", ".join(value_sql(item, target[2], value) for _, value in fields)
    return "INSERT" + policy + f" INTO {name}({heading}) VALUES({values})"


def source_sql(item):
    schema = item["schema"]
    target = quoted(item["name"]) if schema == "temp" else qualified(schema, item["name"])
    prefix = "CREATE TEMP TRIGGER " if schema == "temp" else "CREATE TRIGGER "
    rows = " ".join(step_sql(item, step) + ";" for step in item["program"])
    watched = qualified(*item["watched"][:2])
    return f'{prefix}{target} {item["timing"]} {item["event"]} ON {watched} BEGIN {rows} END'


def declared(item):
    watch_collate, target_collate = item["collations"] or (None, None)
    watch_type = "" if watch_collate is None else " TEXT COLLATE " + watch_collate
    target_type = "" if target_collate is None else " TEXT COLLATE " + target_collate
    constraint = " NOT NULL" if item["required"] else ""
    for schema in SCHEMAS:
        watched = qualified(schema, item["watched"][1])
        ledger = qualified(schema, item["ledger"])
        yield f"CREATE TABLE {watched}(id INTEGER PRIMARY KEY, binding{watch_type}, payload)"
        yield f"CREATE TABLE {ledger}(id INTEGER PRIMARY KEY, binding{target_type}, result{constraint}, observer)"


def analyze(item, sql=None, extra=()):
    return {"sql": source_sql(item) if sql is None else sql,
            "declarations": [*declared(item), *extra], "searchPath": ["main", "archive"], "catalogComplete": True}


def original_rows(item, schema):
    code = SCHEMAS.index(schema)
    first, second = (4, 5) if item["collations"] is None else ("X", "x")
    prior = "initial" if item["required"] else None
    return [(1, first, "before", code), (2, second, prior, code), (3, second, "duplicate", code)]


def watched_rows(item):
    prior = item["payload"] if item["event"] == "DELETE" else "prior"
    binding = 4 if item["collations"] is None else "X"
    old = [(19, binding, prior), (21, None, None)]
    after = list(old)
    active = item["event"]
    binding = 5 if item["collations"] is None else "x"
    if active == "INSERT":
        after.append((23, binding, item["payload"]))
    elif active == "UPDATE":
        after[0] = (19, binding, item["payload"])
    else:
        after.pop(0)
    return old, after


def read_value(value, row, images):
    kind = value[0]
    if kind == "local":
        return row[value[1]]
    if kind == "field":
        return images[value[1]][value[2]]
    if kind == "integer":
        return int(value[1])
    return None if kind == "null" else value[1]


def matches(condition, row, images, collations):
    if condition is None:
        return True
    kind, *args = condition
    if kind == "equal":
        local, image, target_first = args
        left, right = row[local], read_value(image, row, images)
        mode = None if collations is None else collations[1 if target_first else 0]
        if isinstance(left, str) and isinstance(right, str) and mode == "NOCASE":
            return left.translate(FOLD) == right.translate(FOLD)
        return left is not None and right is not None and type(left) is type(right) and left == right
    value = row[args[0]] if kind == "local-null" else read_value(args[0], row, images)
    return (value is None) == args[1]


def simulate(item):
    prior, later = watched_rows(item)
    phase = prior if item["timing"] == "BEFORE" else later
    visible = next((row[2] for row in phase if row[0] == item["eventId"]), None)
    images = {"OLD": prior[0], "NEW": later[-1] if item["event"] == "INSERT" else later[0]}
    ledger = {schema: original_rows(item, schema) for schema in SCHEMAS}
    receipts = {schema: [] for schema in SCHEMAS}
    changes = 1
    for step in item["program"]:
        schema = step["table"][0]
        kind = step.get("action", "insert")
        if kind == "insert":
            inserted = [None] * len(LEDGER)
            for position, value in step["fields"]:
                inserted[position] = read_value(value, None, images)
            ledger[schema] = sorted([*ledger[schema], tuple(inserted)])
            changes += 1
            continue
        resulting = []
        for row in ledger[schema]:
            if not matches(step["where"], row, images, item["collations"]):
                resulting.append(row)
                continue
            updated = list(row)
            if kind == "update":
                for position, value in step["assignments"]:
                    updated[position] = read_value(value, row, images)
                if item["required"] and updated[2] is None:
                    resulting.append(row)
                    continue
                resulting.append(tuple(updated))
            receipts[schema].append((kind, row[0], len(phase), visible))
            changes += 2
        ledger[schema] = resulting
    return ledger, receipts, changes


def typed_rows(rows):
    return [[typed(value) for value in row] for row in rows]


def finite_ids(domain):
    return set(product(*(range(len(domain[key])) for key in AXES)))


def measured_id(measurement, domain):
    reply, state = measurement
    kinds = "-".join(step["action"] for step in reply["program"])
    read = reply["program"][0]["where"]
    shape = ("none" if read is None else "target-is-null" if read[0] == "local-null"
             else "source-is-null" if read[0] == "image-null" else "match-key" if read[1] == 1 else "missing-key")
    driver = state["driver"]
    coordinates = (reply["schema"], reply["event"], reply["timing"], kinds, shape,
                   driver["payload"], driver["rollback"])
    return tuple(domain[axis].index(value) for axis, value in zip(AXES, coordinates))


def seeded(settings, seed):
    random = Random(seed)
    for index in range(settings["casesPerSeed"]):
        scope, event, timing = SCHEMAS[index % 3], ("INSERT", "UPDATE", "DELETE")[(index // 3) % 3], ("BEFORE", "AFTER")[index % 2]
        labels = ("none", "match-key", "missing-key", "source-is-null", "target-is-null")
        base = make_case(scope, event, timing, "update-delete", labels[(index // 2) % 5],
                         random.choice((None, 7, "x", "line ' ñ")), bool(index % 2), name=f'trace "{seed}-{index}"',
                         ledger=random.choice(("ledger", 'balance "列"')), relation=random.choice(("watched", 'account 受')))
        update, delete = base["program"]
        if update["where"] is not None and update["where"][0] == "equal":
            update["where"][3] = bool(index % 2)
        if delete["where"] is not None and delete["where"][0].endswith("-null"):
            delete["where"][-1] = bool(index % 2)
        if event == "UPDATE":
            update["assignments"][1][1] = field("OLD", 0)
            if update["where"] is not None and update["where"][0] == "equal":
                update["where"][2][1] = "OLD"
        if index % 4 == 1:
            update["assignments"][0][1] = ["local", 1]
        elif index % 4 == 2:
            update["assignments"][0][1] = ["integer", str(random.randrange(-100, 100))]
        elif index % 4 == 3:
            update["assignments"][0][1] = ["text", "value ' ñ"]
        update["conflict"] = ("inherit", "replace", "abort", "fail", "ignore", "rollback")[index % 6]
        if index % 3 == 1:
            update["assignments"].reverse()
        extra = {"table": update["table"], "writable": update["writable"], "conflict": "inherit",
                 "fields": [[0, ["integer", str(600 + index)]], [1, ["integer", "5"]], [2, ["text", "inserted"]], [3, ["null"]]]}
        mode = index % 5
        base["program"] = ([update, delete] if mode == 0 else [delete, update, delete] if mode == 1
                           else [extra, update, delete] if mode == 2 else [update, delete, extra, update] if mode == 3
                           else [deepcopy(random.choice((update, delete))) for _ in range(random.randint(1, 4))])
        yield base


def authored():
    item = make_case("main", "UPDATE", "AFTER", payload="live", collations=("NOCASE", "BINARY"))
    yield item
    reversed_order = deepcopy(item)
    reversed_order["program"][0]["where"][3] = False
    yield reversed_order
    item = make_case("main", "INSERT", "AFTER", payload=None, required=True)
    yield item
    item = make_case("main", "INSERT", "AFTER", condition="none", payload="image", ledger="new")
    item["program"][0]["assignments"] = [[2, ["local", 0]]]
    item["shadowSyntax"] = True
    yield item


def requests(specification):
    domain = specification["finite"]
    for index, axes in enumerate(product(*(domain[key] for key in AXES))):
        item = make_case(*axes)
        yield f"finite/{index}", analyze(item), item
        yield f"direct/{index}", item, item
    for seed in specification["exploration"]["seeds"]:
        for index, item in enumerate(seeded(specification["exploration"], seed)):
            yield f"seed/{seed}/{index}", item, item
    for index, item in enumerate(authored()):
        yield f"authored/{index}/analysis", analyze(item), item
        yield f"authored/{index}/direct", item, item


def forms(measured):
    reply, _ = measured
    yield "schema/" + reply["schema"]
    yield "event/" + reply["event"]
    yield "timing/" + reply["timing"]
    yield "program-length/" + ("long" if len(reply["program"]) > 2 else "short")
    for step in reply["program"]:
        operation = step.get("action", "insert")
        yield "action/" + operation
        if operation == "update":
            yield "conflict/" + step["conflict"]
            for _, value in step["assignments"]:
                yield "value/" + value[0]
        if operation != "insert" and step["where"] is not None:
            condition = step["where"]
            yield "filter/" + condition[0]
            if condition[0] == "equal":
                yield "target-first/" + str(condition[3])
                yield "image/" + condition[2][1]


def required(_):
    return {"schema/main", "schema/temp", "schema/archive", "event/INSERT", "event/UPDATE", "event/DELETE",
            "timing/BEFORE", "timing/AFTER", "program-length/short", "program-length/long",
            "action/insert", "action/update", "action/delete", "conflict/inherit", "conflict/abort", "conflict/ignore",
            "conflict/fail", "conflict/rollback", "conflict/replace", "value/field", "value/local", "value/integer", "value/text",
            "filter/equal", "filter/local-null", "filter/image-null", "target-first/True", "target-first/False", "image/OLD", "image/NEW"}


def negatives():
    def selected(**parts):
        return {**make_case(payload=9), **parts}

    update = make_case(payload=9)
    delete = make_case(steps="delete", payload=9)
    step = update["program"][0]
    mask = delete["program"][0]
    yield selected(program=[]), "rejected", "needs a nonempty list of ordered actions"
    yield selected(program=[{"action": "invalid"}]), "rejected", "contains only resolved ordered actions"
    yield selected(program={"1": step}), "rejected", "needs a nonempty list of ordered actions"
    yield selected(program=[{**step, "where": ["local-null", -1, True]}]), "rejected", "field position cannot be negative"
    yield selected(program=[{**step, "where": ["local-null", 4, False]}]), "rejected", LOCAL_ERROR
    yield selected(program=[{**mask, "where": ["local-null", 4, False]}]), "rejected", LOCAL_ERROR
    yield selected(program=[{**step, "assignments": [[2, ["null"]], [2, field("NEW", 2)]]}]), "rejected", "write distinct available slots"
    yield selected(program=[{**step, "writable": [0, 1, 3]}]), "rejected", "write distinct available slots"
    yield selected(program=[{**step, "assignments": [[2, field("OLD", 2)]]}]), "rejected", IMAGE_ERROR
    yield selected(program=[{**mask, "where": ["image-null", field("OLD", 2), True]}]), "rejected", IMAGE_ERROR
    yield selected(program=[{**step, "assignments": [[2, field("NEW", 3)]]}]), "rejected", IMAGE_ERROR
    yield selected(program=[{**step, "table": ["temp", "ledger", list(LEDGER)]}]), "rejected", SCOPE_ERROR
    temporary = make_case("temp")
    insert = {"table": ["temp", "ledger", list(LEDGER)], "writable": list(range(4)),
              "fields": [[0, ["integer", "7"]]], "conflict": "inherit"}
    yield {**temporary, "program": [insert, mask]}, "rejected", SCOPE_ERROR
    yield selected(program=[{**step, "assignments": [[2, ["local", 4]]]}]), "rejected", LOCAL_ERROR
    shadow = make_case(ledger="new", condition="none", payload=9)
    yield {**shadow, "program": [{**shadow["program"][0], "assignments": [[2, field("NEW", 0)]]}]}, (
        "rejected"
    ), "shadows this row-image field"
    oid = {**shadow, "watched": ["main", "watched", ["oid", "binding", "payload"]]}
    yield {**oid, "program": [{**shadow["program"][0], "assignments": [[2, field("NEW", 0)]]}]}, (
        "rejected"
    ), "shadow this row-image rowid alias"
    command = lambda body: "CREATE TRIGGER t AFTER INSERT ON main.watched BEGIN " + body + "; END"
    yield analyze(update, command("UPDATE unknown SET result=NEW.payload")), "analysis-rejected", "unknown"
    yield analyze(update, command("DELETE FROM ledger WHERE unavailable=NEW.id")), "analysis-rejected", "unavailable"
    yield analyze(update, command("DELETE FROM ledger WHERE OLD.id IS NULL")), "analysis-rejected", "OLD.id"
    yield analyze(update, command("UPDATE main.ledger SET result=NEW.payload")), "analysis-rejected", "qualified table names are not allowed"
    yield analyze(update, command("UPDATE ledger SET result=NEW.payload FROM watched")), "rejected", "without FROM"
    yield analyze(update, command("UPDATE ledger SET (result,observer)=(NEW.payload,NEW.id)")), "rejected", "requires scalar assignments"
    yield analyze(update, command("DELETE FROM ledger WHERE 1")), "rejected", FILTER_ERROR
