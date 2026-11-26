"""Construct known trigger namespaces and independently predict the drop effects."""

from itertools import product
from random import Random
from string import ascii_lowercase, ascii_uppercase

from run_sqlite_create_table import quoted
from sqlite_add_column_cases import statement
from sqlite_engine import typed

AXES = ("layouts", "qualifiers", "spellings", "guards", "rollback")
SCHEMAS = ("main", "temp", "archive", "archive2")
SEARCH = (1, 0, 2, 3)
LAYOUTS = {
    "empty": (), "main": (0,), "archive": (2,), "temp": (1,),
    "temp-shadow": (1, 0, 2), "attached-shadow": (0, 2),
    "second-attachment": (2, 3), "temp-and-attached": (1, 3),
}
FIELDS = ("operation", "targetRole", "name", "schema", "watched", "verification", "ifExists")
UPPERCASE = str.maketrans(ascii_lowercase, ascii_uppercase)
LOWERCASE = str.maketrans(ascii_uppercase, ascii_lowercase)


def qualified(schema, name):
    return quoted(name) if schema is None else f"{quoted(schema)}.{quoted(name)}"


def finite_domain(domain):
    for axes in product(*(domain[axis] for axis in AXES)):
        yield make_case(*axes)


def make_case(layout, qualifier, spelling, guard, rollback, *, schemas=SCHEMAS, name="tracker",
              watched="watched", watched_temp=1, value=17, temporary_keyword=False):
    indexes = LAYOUTS[layout]
    slot = schemas.index(qualifier) if qualifier in schemas else None
    choice = SEARCH if qualifier is None else (() if slot is None else (slot,))
    selected = next((entry for entry in choice if entry in indexes), None) if (
        spelling.translate(LOWERCASE) == name.translate(LOWERCASE)
    ) else None
    watch = None if selected is None else [schemas[watched_temp if selected == 1 else selected], watched]
    scope = ("search-path" if qualifier is None else "unknown-schema" if slot is None else "known-schema")
    return {
        "operation": "drop-trigger", "ifExists": guard, "targetRole": "absent" if selected is None else "installed",
        "name": spelling if selected is None else name, "schema": qualifier if selected is None else schemas[selected],
        "watched": watch, "verification": scope if selected is None else None,
        "indexes": list(indexes), "schemas": list(schemas), "requestedName": spelling, "requestedSchema": qualifier,
        "baseName": name, "watchedName": watched, "tempWatched": watched_temp, "value": value, "rollback": rollback,
        "temporaryKeyword": temporary_keyword,
    }


def resolution(slot, item):
    return item["schemas"][item["tempWatched"] if slot == 1 else slot]


def definition(item, slot, name, sibling=False):
    schema = item["schemas"][slot]
    head = "CREATE TEMP TRIGGER " + quoted(name) if slot == 1 and item["temporaryKeyword"] else (
        "CREATE TRIGGER " + qualified(schema, name)
    )
    rest = f' AFTER INSERT ON {qualified(resolution(slot, item), item["watchedName"])} BEGIN '
    rest += f'SELECT NEW.id; END' if sibling else (
        f'INSERT INTO audit(input, marker) VALUES(NEW.id, {slot}); END'
    )
    return head + rest, "CREATE TRIGGER " + quoted(name) + rest


def declarations(item):
    for schema in item["schemas"]:
        yield f'CREATE TABLE {qualified(schema, item["watchedName"])}(id INTEGER PRIMARY KEY, marker)'
        yield f'CREATE TABLE {qualified(schema, "audit")}(input, marker)'
    for slot in range(len(item["schemas"])):
        if slot in item["indexes"]:
            yield definition(item, slot, item["baseName"])[0]
        yield definition(item, slot, "sentinel", True)[0]


def source(item):
    guard = "IF EXISTS " if item["ifExists"] else ""
    return "DROP TRIGGER " + guard + qualified(item["requestedSchema"], item["requestedName"])


def analyze(item, *, complete=True, extra=(), sql=None):
    return {"sql": source(item) if sql is None else sql, "declarations": [*declarations(item), *extra],
            "searchPath": [item["schemas"][slot] for slot in (0, 2, 3)], "catalogComplete": complete}


def catalog(item, live):
    result = {}
    for slot, schema in enumerate(item["schemas"]):
        names = [("sentinel", True)] + ([(item["baseName"], False)] if slot in live else [])
        rows = [(name, item["watchedName"], 0, definition(item, slot, name, sibling)[1])
                for name, sibling in sorted(names)]
        result[schema] = [[typed(value) for value in row] for row in rows]
    return result


def affected_rows(item, live):
    values = {
        schema: ([[typed(item["value"] + (item["tempWatched"] if slot == 1 else slot)), typed(slot)]]
                 if slot in live else [])
        for slot, schema in enumerate(item["schemas"])
    }
    return values


def expected(item):
    initial = set(item["indexes"])
    schema = item["schema"]
    installed = item["targetRole"] == "installed"
    selected = item["schemas"].index(schema) if schema in item["schemas"] else None
    live = initial - ({selected} if installed else set())
    final = initial if item["rollback"] else live
    authorization = [] if not installed else [[14 if schema == "temp" else 16, item["name"], item["watched"][1], schema]]
    verification = (list(range(len(item["schemas"]))) if schema is None
                    else [] if selected is None else [selected])
    return {
        "before": catalog(item, initial), "statement": {**statement(), "readOnly": not installed},
        "verification": verification, "authorization": authorization, "after": catalog(item, live),
        "settled": catalog(item, final), "transactionDuringDrop": item["rollback"], "transactionAfter": False,
        "audit": affected_rows(item, final),
    }


def finite_ids(domain):
    admitted, refused = set(), set()
    for indexes in product(*(range(len(domain[axis])) for axis in AXES)):
        layout, qualifier, spelling, guard, _ = (domain[axis][idx] for axis, idx in zip(AXES, indexes))
        available = frozenset(SCHEMAS[number] for number in LAYOUTS[layout])
        installed = spelling.lower() == "tracker" and (bool(available) if qualifier is None else qualifier in available)
        (admitted if installed or guard else refused).add(indexes)
    return admitted, refused


def identifier(item, domain):
    return tuple(domain[axis].index(value) for axis, value in zip(AXES, item))


def measured_id(measured, domain):
    reply, result, submitted = measured
    active = frozenset(i for i, schema in enumerate(SCHEMAS) if any(
        row[0] == ["text", "tracker"] for row in result["before"][schema]
    ))
    layout = next(label for label, mask in LAYOUTS.items() if active == frozenset(mask))
    qualifier, spelling = next((schema, name) for schema, name in product(domain["qualifiers"], domain["spellings"])
                               if submitted == "DROP TRIGGER " + ("IF EXISTS " if reply["ifExists"] else "")
                               + qualified(schema, name))
    return identifier((layout, qualifier, spelling, reply["ifExists"], result["transactionDuringDrop"]), domain)


def seeded(settings, seed):
    random = Random(seed)
    for index in range(settings["casesPerSeed"]):
        schemas = ("main", "temp", random.choice(("archive", 'stored "列"')), random.choice(("archive2", "")))
        name = ("", 'tag "ñ"', "tag_列", f"tag_{random.randrange(0, 1000)}")[index % 4]
        layout = ("temp-shadow", "main", "archive", "second-attachment", "empty", "empty", "empty", "temp")[index % 8]
        scope = (None, schemas[0], schemas[2], schemas[3], None, "gone", schemas[3], "temp")[index % 8]
        yield make_case(layout, scope, name.translate(UPPERCASE) if index % 2 else name,
                        index % 8 >= 4, bool(index % 2), schemas=schemas, name=name,
                        watched=random.choice(("watched", "record_列", 'parent "row"')),
                        watched_temp=random.randrange(4) if index % 8 != 0 else 0,
                        temporary_keyword=bool(index % 2), value=random.randrange(-800, 800))


def authored():
    yield make_case("temp-shadow", None, "TRACKER", False, False, watched_temp=0, temporary_keyword=True), False
    yield make_case("attached-shadow", "main", "TRACKER", False, False), False
    yield make_case("second-attachment", "", "", False, True, schemas=("main", "temp", "archive", ""), name=""), True
    yield make_case("empty", "", "", True, True, schemas=("main", "temp", "archive", "")), True


def requests(specification):
    for index, value in enumerate(finite_domain(specification["finite"])):
        if not value["ifExists"] and value["targetRole"] == "absent":
            continue
        yield f"finite/{index}", analyze(value), value
        yield f"direct/{index}", value, value
    for seed in specification["exploration"]["seeds"]:
        for index, value in enumerate(seeded(specification["exploration"], seed)):
            yield f"seed/{seed}/{index}", value, value
    for index, (value, complete) in enumerate(authored()):
        yield f"special/{index}/analyzed", analyze(value, complete=complete), value
        yield f"special/{index}/direct", value, value


def negatives():
    value = make_case("main", "main", "tracker", False, False)
    absent = make_case("empty", None, "tracker", True, False)
    yield {**absent, "ifExists": False}, "rejected", "absent DROP TRIGGER target requires IF EXISTS"
    yield {**absent, "schema": "main"}, "rejected", "requires an exact qualifier only for a qualified scope"
    yield {**absent, "verification": "known-schema"}, "rejected", "requires an exact qualifier only for a qualified scope"
    yield {**absent, "verification": "unknown-schema"}, "rejected", "requires an exact qualifier only for a qualified scope"
    for schema in ("MAIN", "Temp"):
        yield {**absent, "schema": schema, "verification": "unknown-schema"}, "rejected", "main and temp trigger schemas are always known"
    yield {**value, "name": "SQLITE_special"}, "rejected", "reserved SQLite namespace"
    yield {**value, "watched": ["temp", "watched"]}, "rejected", "stored trigger must watch a relation in its own schema"
    yield {**value, "schema": None}, "type-rejected", "must be of type string"
    yield analyze(absent, complete=False, sql="DROP TRIGGER IF EXISTS main.tracker"), "rejected", "known guarded absence"
    yield analyze(value, complete=False, sql="DROP TRIGGER tracker"), "rejected", "cannot skip an open schema"
    yield analyze(value, extra=(definition(value, 0, "TRACKER")[0],)), "rejected", "Conflicting declared triggers"
    yield {**analyze(absent), "searchPath": ["main", "archive", "ARCHIVE"]}, "rejected", "distinct schema names"
    yield analyze(absent, extra=("CREATE TRIGGER main.ghost AFTER INSERT ON main.unknown BEGIN SELECT NEW.id; END",)), "rejected", (
        "requires a resolved ordinary watched relation"
    )
    yield analyze(absent, extra=("CREATE TRIGGER gone.ghost AFTER INSERT ON watched BEGIN SELECT NEW.id; END",)), "rejected", (
        "requires a known owning schema"
    )
    yield {"declarations": ["CREATE TABLE main.watched(id)",
                            "CREATE TRIGGER late AFTER INSERT ON watched BEGIN SELECT NEW.id; END"],
           "sql": "DROP TRIGGER main.late", "catalogComplete": False}, "rejected", "needs a known temporary namespace"


def forms(measured):
    reply, outcome, _ = measured
    yield "target/" + reply["targetRole"]
    yield "qualification/" + str(reply["schema"] is not None)
    yield "guard/" + str(reply["ifExists"])
    yield "rollback/" + str(outcome["transactionDuringDrop"])
    yield f'verification/{len(outcome["verification"])}'
    if reply["watched"] is not None:
        yield "cross-schema/" + str(reply["schema"] != reply["watched"][0])
    if not reply["name"].isascii():
        yield "name/unicode"
    if '"' in reply["name"]:
        yield "name/escaped"
    if reply["name"] == "":
        yield "name/empty"


def required(_):
    return {"target/installed", "target/absent", "qualification/True", "qualification/False",
            "guard/True", "guard/False", "verification/0", "verification/1", "verification/4",
            "rollback/True", "rollback/False", "cross-schema/True", "cross-schema/False",
            "name/unicode", "name/escaped", "name/empty"}
