"""Generate independently known SQLite index identities and predict native removal effects."""

from itertools import product
from random import Random

from run_sqlite_create_table import quoted
from sqlite_add_column_cases import error, statement
from sqlite_engine import typed

AXES = ("coordinates", "names", "siblings", "statistics")
SCHEMAS = ("temp", "main", "extension")
DATABASE_SLOTS = {"temp": 1, "main": 0, "extension": 2}
OWNERS = dict(zip(SCHEMAS, ("local_rows", "main_rows", "extra_rows")))
MASK = {"none": (False, False), "earlier": (True, False), "later": (False, True), "both": (True, True)}
FIELDS = ("operation", "targetRole", "schema", "name", "table", "ifExists")
HEADING = ("id", "slot", "code", "amount")


def other_schemas(selected):
    return [schema for schema in SCHEMAS if schema != (selected if selected in SCHEMAS else "main")]


def case(coordinate="main-required", name="Tag", siblings="none", statistics="collected", *,
         extension="extension", unique=True, partial=True, prefix="x"):
    selected, policy = coordinate.split("-")
    if selected == "all":
        selected = None
    present = policy != "absence"
    indexes = {slot: None for slot in SCHEMAS}
    if present:
        indexes[selected] = name
    for schema, active in zip(other_schemas(selected), MASK[siblings]):
        if active:
            indexes[schema] = name + "_neighbor" if selected is None else name

    return {
        "operation": "drop-index",
        "targetRole": "installed" if present else "absent",
        "schema": extension if selected == "extension" else selected,
        "name": name, "table": OWNERS[selected] if present else None, "ifExists": policy != "required",
        "indexes": indexes, "extension": extension, "statistics": statistics == "collected",
        "unique": unique, "partial": partial, "prefix": prefix,
    }


def schema_name(item, scope):
    return item["extension"] if scope == "extension" else scope


def schema_sql(item, scope):
    return quoted(schema_name(item, scope))


def table_sql(item, scope):
    return f"{schema_sql(item, scope)}.{quoted(OWNERS[scope])}"


def resolved_scope(item):
    name = item["schema"]
    return next((scope for scope in SCHEMAS if schema_name(item, scope) == name), name)


def control(scope):
    return f"control_{scope}"


def autoindex(scope):
    return "sqlite_autoindex_" + OWNERS[scope] + "_1"


def declarations(item):
    for scope in SCHEMAS:
        table, schema = quoted(OWNERS[scope]), schema_sql(item, scope)
        yield f"CREATE TABLE {schema}.{table}(id INTEGER PRIMARY KEY, slot INTEGER UNIQUE, code TEXT, amount INTEGER)"
        yield f"CREATE INDEX {schema}.{quoted(control(scope))} ON {table}(amount)"
        select = item["indexes"][scope]
        if select is not None:
            unique = "UNIQUE " if item["unique"] else ""
            predicate = " WHERE amount IS NOT NULL" if item["partial"] else ""
            yield f"CREATE {unique}INDEX {schema}.{quoted(select)} ON {table}(code){predicate}"


def initial_rows(item, scope):
    row = 10 * SCHEMAS.index(scope)
    prefix = item["prefix"]
    return [(row + 1, 101, prefix, 1), (row + 2, 102, None if item["unique"] and not item["partial"] else prefix, None)]


def future_rows(item, scope):
    row = 10 * SCHEMAS.index(scope)
    prefix = item["prefix"]
    return [(row + 8, 103, prefix, 3),
            (row + 9, 104, None if item["unique"] and not item["partial"] else prefix, None),
            (row + 10, 101, prefix + "_different", 5)]


def typed_rows(rows):
    return [[typed(value) for value in row] for row in rows]


def index_list(item, scope, selectable):
    entries = [(control(scope), False, "c", False), (autoindex(scope), True, "u", False)]
    if selectable is not None:
        entries.insert(0, (selectable, item["unique"], "c", item["partial"]))
    return typed_rows((index, name, int(unique), origin, int(partial))
                      for index, (name, unique, origin, partial) in enumerate(entries))


def snapshot(item, scope, selectable, rows):
    table = OWNERS[scope]
    indexes = sorted([control(scope), autoindex(scope)] + ([] if selectable is None else [selectable]))
    stats = None
    if item["statistics"]:
        stats = {control(scope): "2 1", autoindex(scope): "2 1"}
        if selectable is not None:
            stats[selectable] = "1 1" if item["partial"] else ("2 2" if not item["unique"] else "2 1")
        stats = typed_rows((table, name, stats[name]) for name in sorted(stats))
    return {
        "catalog": typed_rows((name, table) for name in indexes),
        "indexes": index_list(item, scope, selectable),
        "statistics": stats,
        "heading": list(HEADING),
        "rows": typed_rows(rows),
    }


def expected(item):
    selected = resolved_scope(item)
    installed = item["targetRole"] == "installed"
    before, after, later, writes = {}, {}, {}, {}
    for scope in SCHEMAS:
        native_name, rows = item["indexes"][scope], initial_rows(item, scope)
        before[scope] = snapshot(item, scope, native_name, rows)
        live_name = None if installed and scope == selected else native_name
        after[scope] = snapshot(item, scope, live_name, rows)
        writes[scope] = []
        for index, row in enumerate(future_rows(item, scope)):
            if index == 2:
                problem = error(f"UNIQUE constraint failed: {OWNERS[scope]}.slot")
            elif index == 0 and item["unique"] and live_name is not None:
                problem = error(f"UNIQUE constraint failed: {OWNERS[scope]}.code")
            else:
                problem = None
                rows.append(row)
            writes[scope].append(statement(error=problem, changes=int(problem is None)))
        later[scope] = snapshot(item, scope, live_name, rows)
    if selected is None:
        verification = [0, 1, 2]
    else:
        verification = [DATABASE_SLOTS[selected]] if selected in SCHEMAS else []
    return {
        "before": before, "statement": statement(), "verification": verification, "after": after, "writes": writes,
        "later": later, "rolledBack": before, "active": True, "authorization": [] if not installed else [
            [12 if selected == "temp" else 10, item["name"], OWNERS[selected], item["schema"]]
        ],
    }


def finite_ids(domain):
    return set(product(*(range(len(domain[axis])) for axis in AXES)))


def finite_id(measured, domain):
    reply, result = measured
    selected = reply["schema"]
    role = "all" if selected is None else selected
    policy = ("guarded" if reply["ifExists"] else "required") if reply["targetRole"] == "installed" else "absence"
    before = result["before"]
    scope = next(index for index, mask in MASK.items() if mask == tuple(len(before[site]["indexes"]) == 3
                                                                       for site in other_schemas(selected)))
    stats = "collected" if before["main"]["statistics"] is not None else "uncollected"
    inputs = (role + "-" + policy, reply["name"], scope, stats)
    return tuple(domain[axis].index(value) for axis, value in zip(AXES, inputs))


def seeded(domain, seed, size):
    random = Random(seed)
    parts = ('tag', '标记', 'marker "x"', 'value', 'ñ')
    for index in range(size):
        coordinate = domain["coordinates"][index % len(domain["coordinates"])]
        mask = domain["siblings"][(index // 2) % 4]
        stats = domain["statistics"][index % 2]
        name = "" if index % 7 == 0 else random.choice(parts) + f" {index}"
        extension = "" if index % 8 == 2 else random.choice(("extension", 'extra "space"'))
        yield case(coordinate, name, mask, stats, extension=extension,
                   unique=index % 3 != 2, partial=index % 5 != 3, prefix=random.choice(("old", "", "éc")))


def analysis(item, sql, *, complete=True, extra=()):
    return {"sql": sql, "declarations": [*declarations(item), *extra],
            "catalogComplete": complete, "searchPath": ["main", item["extension"]]}


def authored():
    item = case("temp-required", "Tag", "both")
    yield item, analysis(item, "DROP INDEX tag")
    yield item, analysis(item, "DROP INDEX tag", complete=False)
    item = case("main-required", "Tag", "both")
    yield item, analysis(item, "DROP INDEX MAIN.tag")
    yield item, analysis(item, "DROP INDEX MAIN.tag", complete=False)
    item = case("main-required", "Tag", "later")
    yield item, analysis(item, "DROP INDEX tag")
    item = case("extension-required", 'quote "列"')
    yield item, analysis(item, 'DROP INDEX "quote ""列"""')
    item = case("extension-guarded", "", "both", extension="")
    yield item, analysis(item, 'DROP INDEX IF EXISTS "".""')
    item = case("extension-required", 'Quote "列"', extension="")
    yield item, analysis(item, 'DROP INDEX "quote ""列"""')
    for coordinate in ("temp-absence", "main-absence", "extension-absence", "unknown-absence", "all-absence"):
        item = case(coordinate, 'tag "列"', "both")
        qualifier = "" if item["schema"] is None else quoted(item["schema"]) + "."
        yield item, analysis(item, f'DROP INDEX IF EXISTS {qualifier}{quoted(item["name"])}')
    item = case("extension-absence", "", "both", extension="")
    yield item, analysis(item, 'DROP INDEX IF EXISTS "".""')
    item = case("all-absence", "", "none", "uncollected")
    yield item, analysis(item, 'DROP INDEX IF EXISTS ""')
    item = case("unknown-absence", "Tag", "both")
    item["schema"] = "Q"
    yield item, analysis(item, "DROP INDEX IF EXISTS Q.Tag")


def requests(specification):
    domain = specification["finite"]
    for index, axes in enumerate(product(*(domain[axis] for axis in AXES))):
        value = case(*axes)
        yield f"finite/{index}", value, value
    for seed in specification["exploration"]["seeds"]:
        for index, value in enumerate(seeded(domain, seed, specification["exploration"]["casesPerSeed"])):
            yield f"seed/{seed}/{index}", value, value
    for index, (value, request) in enumerate(authored()):
        yield f"analyze/{index}", request, value


def negatives():
    value = case()
    absent = case("all-absence")
    yield {**absent, "ifExists": False}, "rejected", "absent DROP INDEX target requires IF EXISTS"
    yield {**value, "name": "SQLITE_AUTOINDEX_main_rows_1"}, "rejected", "reserved SQLite namespace"
    yield {**value, "schema": None}, "type-rejected", "must be of type string"
    yield {**value, "table": []}, "type-rejected", "must be of type string"
    yield analysis(absent, "DROP INDEX main.Tag"), "rejected", "requires a resolved index or a known guarded absence"
    yield analysis(absent, "DROP INDEX IF EXISTS main.Tag", complete=False), "rejected", "known guarded absence"
    yield analysis(value, "DROP INDEX tag", complete=False), "rejected", "cannot skip an open schema"
    yield analysis(value, "DROP INDEX MAIN.tag", extra=("CREATE INDEX main.tag ON main_rows(slot)",)), "rejected", "Conflicting declared indexes"
    yield {**analysis(value, "DROP INDEX MAIN.tag"), "foreignProfile": True}, "rejected", "must match the selected language profile"
    yield analysis(value, "DROP INDEX IF EXISTS main.sqlite_autoindex_main_rows_1"), "rejected", "cannot drop a UNIQUE or PRIMARY KEY constraint index"


def forms(measured):
    reply, result = measured
    yield "role/" + reply["targetRole"]
    yield f"qualification/{reply['schema'] is not None}"
    yield f"guard/{reply['ifExists']}"
    yield f"verification/{len(result['verification'])}"
    yield f"statistics/{result['before']['main']['statistics'] is not None}"
    if not reply["name"].isascii():
        yield "name/unicode"
    if '"' in reply["name"]:
        yield "name/escaped"
    if reply["name"] == "":
        yield "name/empty"


def required(_):
    return {"role/installed", "role/absent", "qualification/True", "qualification/False",
            "guard/True", "guard/False", "statistics/True", "statistics/False",
            "verification/0", "verification/1", "verification/3", "name/unicode", "name/escaped", "name/empty"}
