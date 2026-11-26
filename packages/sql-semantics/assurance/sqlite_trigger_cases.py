"""Enumerate trigger placements and independently model native row events."""

from copy import deepcopy
from itertools import product
from random import Random

from run import require
from run_sqlite_create_table import quoted
from sqlite_flag_pragma_cases import result

PLACEMENTS = {
    "main-table-after": ("main", "implicit", "main", "main", "AFTER", "table"),
    "archive-table-after": ("archive", "implicit", "archive", "archive", "AFTER", "table"),
    "main-table-before": ("main", "main", "main", "main", "BEFORE", "table"),
    "main-table-implicit-before": ("implicit", "main", "main", "main", "", "table"),
    "named-temp-on-main": ("temp", "main", "temp", "main", "AFTER", "table"),
    "keyword-temp-on-main": ("TEMP", "main", "temp", "main", "AFTER", "table"),
    "keyword-temp-on-archive": ("TEMP", "archive", "temp", "archive", "AFTER", "table"),
    "unqualified-on-temp": ("implicit", "implicit", "temp", "temp", "AFTER", "table"),
    "main-view-instead": ("main", "implicit", "main", "main", "INSTEAD OF", "view"),
    "archive-view-instead": ("archive", "implicit", "archive", "archive", "INSTEAD OF", "view"),
    "keyword-temp-on-main-view": ("TEMP", "main", "temp", "main", "INSTEAD OF", "view"),
}
WATCH = ("id", "payload", "marker")
AUDIT = ("step", "id", "first", "second")
SELECTORS = {"any": [], "payload": [["slot", 1]], "marker": [["slot", 2]],
             "mixed": [["slot", 2], ["absent", "late"], ["slot", 1]],
             "absent": [["absent", "late"]]}
STRUCTURE = ("schema", "name", "placement", "watched", "timing", "event",
             "selectors", "condition", "program", "ifNotExists")
IMAGE_ERROR = "available event image and declared watched position"
PLACEMENT_ERROR = "stored trigger needs a watched relation in its owning schema"
KIND_ERROR = "INSTEAD OF watches a view"
RESERVED_ERROR = "cannot use the reserved SQLite prefix"
SELECTOR_ERROR = "declared column position or a known absent name"
FIELD_ERROR = "must write distinct available slots"
PROGRAM_ERROR = "nonempty list of ordered actions"
DESTINATION_ERROR = "needs one consistent native scope"


def names(item):
    return ("main", item["attachment"], "temp")


def field(image, position):
    return ["field", image, position]


def case(placement="main-table-after", event="insert", selector="any", condition="absent", steps=2,
         guard=False, *, attachment="archive", watched="watched", audit="audit", trigger="tracer", marker=0,
         rng=None, collision=False, evolution=False, reopen=False):
    written, watched_qualifier, owner, watch_schema, timing, kind = PLACEMENTS[placement]
    resolve = lambda scope: attachment if scope == "archive" else scope
    owner, watch_schema = resolve(owner), resolve(watch_schema)
    base_table = [watch_schema, watched, list(WATCH)]
    source = [*base_table, "view"] if kind == "view" else base_table
    first_image = "NEW" if event == "insert" else "OLD"
    first = field(first_image, 1)
    second = field("NEW", 1) if event == "update" else ["null"]
    row_id = field("OLD" if event == "delete" else "NEW", 0)
    steps_list = []
    for index in range(steps):
        label = ("a", "b", "c", "d", "e")[index] + ("" if rng is None else str(marker))
        columns = list(AUDIT)
        assignments = [[0, ["text", label]], [1, row_id], [2, first if index % 2 == 0 else second],
                       [3, second if index % 2 == 0 else first]]
        policy = "inherit"
        if rng is not None:
            rng.shuffle(assignments)
            policy = rng.choice(("inherit", "ignore", "abort", "replace", "fail", "rollback"))
            if index % 3 == 1:
                assignments[0][1] = ["integer", str(marker)]
        steps_list.append({"table": [owner, audit, columns], "writable": list(range(len(columns))),
                           "fields": assignments, "conflict": policy})
    return {
        "operation": "create-trigger", "schema": owner, "name": trigger,
        "placement": "temporary-keyword" if written == "TEMP" else "named-temporary" if owner == "temp" else "stored",
        "watched": source, "timing": timing or "BEFORE", "event": event.upper(),
        "selectors": deepcopy(SELECTORS[selector]),
        "condition": None if condition == "absent" else [first, condition == "is-null"],
        "program": steps_list, "ifNotExists": guard, "attachment": attachment, "audit": audit,
        "writtenOwner": resolve(written), "writtenWatched": resolve(watched_qualifier), "writtenTiming": timing,
        "fixture": placement, "selectorRole": selector, "assumedWhen": condition, "marker": marker,
        "collision": collision, "evolution": evolution, "reopen": reopen,
    }


def qualified(schema, name):
    return quoted(schema) + "." + quoted(name)


def field_sql(value, item, *, grouped=False):
    if value[0] == "field":
        sql = value[1] + "." + quoted(item["watched"][2][value[2]])
    elif value[0] == "text":
        sql = "'" + value[1].replace("'", "''") + "'"
    elif value[0] == "integer":
        sql = value[1]
    else:
        require(value[0] == "null", "sqlite-trigger-source-operand", value)
        sql = "NULL"
    return "(" + sql + ")" if grouped else sql


def source(item, *, alternate=False):
    prefix = "CREATE "
    owner = item["writtenOwner"]
    prefix += "TEMPORARY TRIGGER " if owner == "TEMP" and alternate else "TEMP TRIGGER " if owner == "TEMP" else "TRIGGER "
    if item["ifNotExists"]:
        prefix += "IF NOT EXISTS "
    prefix += quoted(item["name"]) if owner in ("implicit", "TEMP") else qualified(owner, item["name"])
    if item["writtenTiming"]:
        prefix += " " + item["writtenTiming"]
    prefix += " " + item["event"]
    if item["selectors"]:
        prefix += " OF " + ", ".join(quoted(item["watched"][2][name] if role == "slot" else name)
                                     for role, name in item["selectors"])
    watch = item["watched"][1]
    prefix += " ON " + (quoted(watch) if item["writtenWatched"] == "implicit" else qualified(item["writtenWatched"], watch))
    if alternate:
        prefix += " FOR EACH ROW"
    when = item["condition"]
    if when is not None:
        operand, is_null = when
        use = field_sql(operand, item, grouped=alternate)
        prefix += f" WHEN NULL {'IS' if is_null else 'IS NOT'} {use}" if alternate else \
                  " WHEN " + use + (" IS NULL" if is_null else " IS NOT NULL")
    steps = []
    for step in item["program"]:
        policy = step["conflict"]
        if alternate and policy == "replace":
            command = "REPLACE"
        else:
            command = "INSERT" + ("" if policy == "inherit" else " OR " + policy.upper())
        table, columns = step["table"][1:]
        written = ", ".join(quoted(columns[index]) for index, _ in step["fields"])
        operands = ", ".join(field_sql(value, item, grouped=alternate) for _, value in step["fields"])
        steps.append(f"{command} INTO {quoted(table)}({written}) VALUES({operands});")
    return prefix + " BEGIN " + " ".join(steps) + " END"


def declarations(item):
    result = []
    identifier = item["watched"][1]
    for schema in names(item):
        if schema == item["watched"][0] and len(item["watched"]) == 4:
            base = qualified(schema, "internal_data")
            result.append(f"CREATE TABLE {base}(id INTEGER PRIMARY KEY,payload,marker)")
            result.append(f"CREATE VIEW {qualified(schema, identifier)} (id,payload,marker) AS SELECT id,payload,marker FROM {base}")
        else:
            result.append(f"CREATE TABLE {qualified(schema, identifier)}(id INTEGER PRIMARY KEY,payload,marker)")
        result.append(f"CREATE TABLE {qualified(schema, item['audit'])}(step,id,first,second)")
    return result


def analysis(item, sql):
    return {"sql": sql, "catalogComplete": True, "declarations": declarations(item),
            "searchPath": ["main", item["attachment"]]}


def enumerate_finite(domain):
    for label, event in product(domain["placements"], domain["events"]):
        for choice in product(domain["selectors"][event], domain["conditions"], domain["steps"], domain["guard"]):
            yield label, event, *choice


def requests(contract):
    finite = contract["finite"]
    for index, coordinate in enumerate(enumerate_finite(finite)):
        item = case(*coordinate)
        yield f"finite/{index}", analysis(item, source(item)), item
        yield f"direct/{index}", item, item
    for seed in contract["exploration"]["seeds"]:
        rng = Random(seed)
        for index in range(contract["exploration"]["casesPerSeed"]):
            placement = finite["placements"][index % len(finite["placements"])]
            event = finite["events"][index % len(finite["events"])]
            selector = rng.choice(finite["selectors"][event])
            condition = rng.choice(finite["conditions"])
            aliases = ("archive", 'ar"chive', "保管庫")
            item = case(placement, event, selector, condition, rng.randrange(1, 6),
                        rng.choice(finite["guard"]), attachment=aliases[index % len(aliases)],
                        watched=("watched", 'row"view', "監視")[index // 3 % 3],
                        audit=("audit", 'wri"te', "監査")[index // 7 % 3],
                        trigger=("tracer", 'tri"gger', "追跡")[index // 11 % 3],
                        marker=rng.randrange(-50, 51), rng=rng)
            yield f"seed/{seed}/{index}", analysis(item, source(item, alternate=index % 2 == 0)), item
            yield f"direct/{seed}-{index}", item, item
    special = {
        "guard-retains-definition": case(guard=True, collision=True),
        "absent-selector-later-active": case(event="update", selector="absent", evolution=True),
        "stored-object-survives-reopen": case("archive-table-after", reopen=True),
        "temporary-object-does-not-survive-reopen": case("keyword-temp-on-main", reopen=True),
    }
    for label, item in special.items():
        yield f"analyze/{label}", analysis(item, source(item, alternate=True)), item
        yield f"direct/{label}", item, item


def finite_ids(domain):
    return set(enumerate_finite(domain))


def finite_id(measured, domain):
    reply, native = measured
    matches = []
    for label in domain["placements"]:
        for selector in domain["selectors"][reply["event"].lower()]:
            row = (label, reply["event"].lower(), selector,
                   "absent" if reply["condition"] is None else "is-null" if reply["condition"][1] else "is-not-null",
                   len(reply["program"]), reply["ifNotExists"])
            candidate = case(*row)
            if source(candidate) != native["input"]:
                continue
            if (candidate["schema"], candidate["watched"][0]) == (native["owner"], native["watchedScope"]):
                matches.append(row)
    require(len(matches) == 1, "sqlite-trigger-native-coordinate", matches)
    return matches[0]


def forms(measured):
    reply, native = measured
    yield "event/" + reply["event"]
    yield "timing/" + reply["timing"]
    yield "placement/" + reply["placement"]
    yield "condition/" + ("absent" if reply["condition"] is None else "is-null" if reply["condition"][1] else "is-not-null")
    if reply["selectors"]:
        for selector in reply["selectors"]:
            yield "update-name/" + selector[0]
    for step in reply["program"]:
        yield "conflict/" + step["conflict"]
    if any(row["hook"] for row in native["events"]):
        yield "effect/hook"
    if any(len(row["hook"]) > 3 for row in native["events"]):
        yield "effect/ordered-program"
    if '"' in reply["name"]:
        yield "identifier/quote"
    if any(ord(character) > 127 for character in reply["name"]):
        yield "identifier/unicode"


def required(domain):
    return ({"event/" + event.upper() for event in domain["events"]}
            | {"timing/BEFORE", "timing/AFTER", "timing/INSTEAD OF", "placement/stored", "placement/temporary-keyword",
               "placement/named-temporary", "condition/absent", "condition/is-null", "condition/is-not-null",
               "update-name/slot", "update-name/absent", "effect/hook", "effect/ordered-program",
               "identifier/quote", "identifier/unicode"}
            | {"conflict/" + mode for mode in ("inherit", "ignore", "abort", "replace", "fail", "rollback")})


def initial(item):
    watched, audit = {}, {}
    for index, schema in enumerate(names(item)):
        watched[schema] = {1: [1, 10 + index + item["marker"], "keep"], 2: [2, None, "delete"]}
        audit[schema] = []
    return watched, audit


def evaluate(operand, old, new):
    if operand[0] == "field":
        return (old if operand[1] == "OLD" else new)[operand[2]]
    if operand[0] == "integer":
        return int(operand[1])
    return None if operand[0] == "null" else operand[1]


def overlaps_update(item, column, evolving):
    return not item["selectors"] or any(
        selector == column if role == "slot" else evolving and selector == "late" and column == 3
        for role, selector in item["selectors"]
    )


def trigger_applies(item, active, event, column, old, new, evolving):
    if not active or event != item["event"] or not overlaps_update(item, column, evolving):
        return False
    condition = item["condition"]
    return condition is None or (evaluate(condition[0], old, new) is None) == condition[1]


def add_audit(item, audit, old, new):
    hooks = []
    for step in item["program"]:
        row = [None] * len(AUDIT)
        for slot, operand in step["fields"]:
            row[slot] = evaluate(operand, old, new)
        scope = step["table"][0]
        audit[scope].append(row)
        hooks.append([18, scope, step["table"][1], len(audit[scope])])
    return hooks


def drive(item, watched, audit, *, active=True):
    receipts = []
    for schema in names(item):
        is_watch = schema == item["watched"][0]
        is_view = is_watch and len(item["watched"]) == 4
        mutations = [("INSERT", None, 3, [3, None, "new"], False),
                     ("INSERT", None, 4, [4, 20 + item["marker"], "wide"], False),
                     ("UPDATE", 2, 1, "changed", False),
                     ("UPDATE", 1, 1, 30 + item["marker"], False),
                     ("DELETE", None, 2, None, False)]
        if item["evolution"] and is_watch:
            mutations.append(("UPDATE", 3, 1, 5, True))
        for event, column, rowid, value, evolving in mutations:
            if is_view and (not active or event != item["event"]):
                continue
            if is_view and not overlaps_update(item, column, evolving):
                receipts.append({"scope": schema, "event": event, "slot": column, "row": rowid, "hook": [],
                                 "statement": {"preparationError": f"SQLite 1: cannot modify {item['watched'][1]} because it is a view"}})
                continue
            local = watched[schema]
            old = list(local[rowid]) if rowid in local else None
            if event == "UPDATE":
                new = old.copy()
                if evolving:
                    new.append(value)
                else:
                    new[column] = value
            else:
                new = value
            hook = []
            run = is_watch and trigger_applies(item, active, event, column, old, new, evolving)
            if run and item["timing"] != "AFTER":
                hook.extend(add_audit(item, audit, old, new))
            if not is_view:
                if event == "DELETE":
                    local.pop(rowid)
                else:
                    local[rowid] = new[:len(WATCH)]
                hook.append([{"INSERT": 18, "UPDATE": 23, "DELETE": 9}[event], schema, item["watched"][1], rowid])
            if run and item["timing"] == "AFTER":
                hook.extend(add_audit(item, audit, old, new))
            receipts.append({"scope": schema, "event": event, "slot": column, "row": rowid,
                             "hook": hook, "statement": result(read_only=False, changes=len(hook))})
    return receipts


def state(item, watched, audit, active=True):
    output = {}
    for schema in names(item):
        target = schema == item["schema"] and (active or item["collision"])
        output[schema] = {"declarations": result(rows=((item["name"], item["watched"][1], 0),) if target else ())["rows"],
                          "watched": result(rows=[watched[schema][row] for row in sorted(watched[schema])])["rows"],
                          "audit": result(rows=audit[schema])["rows"], "decoy": []}
    return output


def expected(item):
    watched, audit = initial(item)
    before = state(item, watched, audit, active=False)
    active = not item["collision"]
    authorization = []
    if active:
        watch = item["watched"][0]
        temporary = item["placement"] == "temporary-keyword" or watch == "temp"
        authorization = [[5 if temporary else 7, item["name"], item["watched"][1],
                          "temp" if item["placement"] == "temporary-keyword" else watch]]
    events = drive(item, watched, audit, active=active)
    after = state(item, watched, audit, active=active)
    reopened = None
    if item["reopen"]:
        schema, table = item["watched"][:2]
        installed = item["schema"] != "temp"
        active_rows = deepcopy(watched)
        active_audit = deepcopy(audit)
        cold = state(item, active_rows, active_audit, active=installed)
        cold.pop("temp")
        row = [7, 73, "reopened"]
        active_rows[schema][7] = row
        hook = [[18, schema, table, 7]] + (add_audit(item, active_audit, None, row) if installed else [])
        ending = state(item, active_rows, active_audit, active=installed)
        ending.pop("temp")
        reopened = {"before": cold, "event": {"scope": schema, "event": "INSERT", "row": 7, "slot": None,
                    "hook": hook, "statement": result(read_only=False, changes=len(hook))}, "after": ending}
    return {"before": before, "owner": item["schema"], "watchedScope": item["watched"][0],
            "authorization": authorization, "definition": result(read_only=False if active else True), "events": events,
            "after": after, "reopened": reopened}


def with_field(item, column, value):
    changed = deepcopy(item)
    changed["program"][0]["fields"][0] = [column, value]
    return changed


def negatives():
    insert = case()
    update = case(event="update")
    delete = case(event="delete")
    yield with_field(insert, 0, field("OLD", 1)), "rejected", IMAGE_ERROR
    yield with_field(delete, 0, field("NEW", 1)), "rejected", IMAGE_ERROR
    yield with_field(insert, 0, field("NEW", 8)), "rejected", IMAGE_ERROR
    yield with_field(insert, 8, ["null"]), "rejected", FIELD_ERROR
    yield {**update, "selectors": [["slot", 9]]}, "rejected", SELECTOR_ERROR
    yield {**insert, "timing": "INSTEAD OF"}, "rejected", KIND_ERROR
    yield {**case("main-view-instead"), "timing": "AFTER"}, "rejected", KIND_ERROR
    yield {**insert, "schema": "archive"}, "rejected", PLACEMENT_ERROR
    yield {**insert, "name": "sqlite_reserved"}, "rejected", RESERVED_ERROR
    yield {**insert, "watched": ["main", "sqlite_schema", list(WATCH)]}, "rejected", RESERVED_ERROR
    yield {**insert, "program": []}, "rejected", PROGRAM_ERROR
    yield {**insert, "program": [{**insert["program"][0], "fields": []}]}, "rejected", "nonempty list of supplied fields"
    yield with_field(insert, 1, ["null"]), "rejected", FIELD_ERROR
    yield {**insert, "program": [{**insert["program"][0], "writable": [1, 2, 3]}]}, "rejected", FIELD_ERROR
    yield {**insert, "selectors": [["slot", 1]]}, "rejected", "Only an UPDATE trigger"
    yield {**update, "selectors": [["absent", "Payload"]]}, "rejected", SELECTOR_ERROR
    local = case("keyword-temp-on-main")
    local["program"][1]["table"][0] = "main"
    yield local, "rejected", DESTINATION_ERROR
    yield with_field(insert, 0, field("NEW", -1)), "rejected", "nonnegative watched column position"
    yield {**insert, "program": [{**insert["program"][0], "writable": [0, 0, 3]}]}, "rejected", "distinct, ordered members"
    yield analysis(insert, "CREATE TEMP TRIGGER main.tracer INSERT ON watched BEGIN INSERT INTO audit VALUES (1,2,3,4); END"), \
        "rejected", "TEMP keyword trigger cannot have a qualified name"
    yield analysis(insert, "CREATE TRIGGER tracer INSERT ON unavailable BEGIN INSERT INTO audit VALUES (1,2,3,4); END"), \
        "analysis-rejected", "Relation unavailable does not exist"
    yield analysis(insert, "CREATE TRIGGER tracer INSERT ON main.watched BEGIN INSERT INTO unavailable VALUES (1); END"), \
        "analysis-rejected", "Relation unavailable does not exist"
    yield analysis(insert, "CREATE TRIGGER tracer INSERT ON main.watched BEGIN INSERT INTO audit VALUES (1,2,3,?1); END"), \
        "analysis-rejected", "trigger cannot use variables"
    yield analysis(insert, "CREATE TRIGGER tracer INSERT ON main.watched BEGIN INSERT INTO audit(step) VALUES (NEW.unavailable); END"), \
        "analysis-rejected", "Column NEW.unavailable does not exist"
