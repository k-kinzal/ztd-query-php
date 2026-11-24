"""Define direct, analytical and bounded SQLite table metadata cases."""
from itertools import product
from random import Random

from run_sqlite_create_table import quoted
from sqlite_flag_pragma_cases import result
from sqlite_table_metadata_fixture import declarations, fixture, system_tables

AXES = ("operations", "selectors", "scopes", "temporary")
HEADINGS = {
    "table_info": ("cid", "name", "type", "notnull", "dflt_value", "pk"),
    "table_xinfo": ("cid", "name", "type", "notnull", "dflt_value", "pk", "hidden"),
    "index_list": ("seq", "name", "unique", "origin", "partial"),
    "foreign_key_list": ("id", "seq", "table", "from", "to", "on_update", "on_delete", "match"),
    "table_list": ("schema", "name", "type", "ncol", "wr", "strict"),
}
ASCII = str.maketrans("ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz")
CATALOG_KEYS = {"sqlite_schema": "sqlite_master", "sqlite_temp_schema": "sqlite_temp_master"}
CATALOG_LABELS = {value: key for key, value in CATALOG_KEYS.items()}


def selector_map(shadow, quote_name):
    return {"none": None, "shadow-name": shadow, "view": "watch", "without-rowid": "norow", "foreign-key-table": "child",
            "missing": "unavailable", "ordinary-catalog": "sqlite_master", "temp-catalog": "sqlite_temp_master", "quoted-name": quote_name}


def case(operation="table_info", role="shadow-name", scope="implicit", temp="unopened", *, attachment="extension", shadow="accounts",
         quote_name='archive "items"', marker="éa", warm=False):
    offered = selector_map(shadow, quote_name)[role]
    schema = attachment if scope == "attached" else None if scope == "implicit" else scope
    item = {"operation": "table-metadata", "pragma": operation, "argument": offered, "schema": schema, "attachment": attachment,
            "temp": temp == "populated", "warm": warm, "shadow": shadow, "quoted": quote_name, "marker": marker}
    return with_target(item, offered)


def relation(item):
    argument = item["argument"]
    if argument is None:
        return None
    key = argument.translate(ASCII)
    schema = item["schema"]
    catalog = {table.schema: table for table in system_tables(item["attachment"])}
    if key in ("sqlite_master", "sqlite_schema"):
        return catalog["main" if schema is None else schema]
    if key in ("sqlite_temp_master", "sqlite_temp_schema"):
        return catalog["temp"] if schema is None or schema == "temp" else None
    for scope in (("temp", "main", item["attachment"]) if schema is None else (schema,)):
        for table in fixture(item):
            if table.schema == scope and table.name.translate(ASCII) == key:
                return table
    return None


def with_target(item, argument):
    item = {**item, "argument": argument}
    table = relation(item)
    if argument is None or item["pragma"] == "table_list":
        target = None
    elif table is None:
        target = ["missing", argument]
    elif table.kind == "catalog":
        target = ["catalog", table.schema]
    else:
        target = ["relation", table.schema, table.name, table.kind]
    return {**item, "target": target}


def analysis(item, source, *, complete=True, extra=()):
    return {"sql": source, "declarations": [*declarations(item), *extra], "catalogComplete": complete,
            "searchPath": ["main", item["attachment"]]}


def source(item, *, form="parentheses", lexeme=None, uppercase=False):
    scope = "" if item["schema"] is None else quoted(item["schema"]) + "."
    pragma, argument = item["pragma"], item["argument"]
    if uppercase:
        pragma = quoted(pragma.upper())
    lead = "PRAGMA " + scope + pragma
    if argument is None:
        return lead
    value = quoted(argument) if lexeme is None else lexeme
    return lead + (f" ( {value} )" if form == "parentheses" else f" = {value}")


def requests(contract):
    finite = contract["finite"]
    for ordinal, settings in enumerate(product(*(finite[name] for name in AXES))):
        item = case(*settings)
        yield f"finite/{ordinal}", analysis(item, source(item)), item
        yield f"direct/{ordinal}", item, item
    for seed in contract["exploration"]["seeds"]:
        rng = Random(seed)
        for ordinal in range(contract["exploration"]["casesPerSeed"]):
            kind = finite["operations"][ordinal % len(finite["operations"])]
            scope = finite["scopes"][ordinal % len(finite["scopes"])]
            role = rng.choice(finite["selectors"])
            attachment = "" if ordinal % 12 == 3 else rng.choice(("Données", 'quoted "db"', "a b")) + str(ordinal)
            item = case(kind, role, scope, rng.choice(finite["temporary"]), attachment=attachment,
                        shadow=rng.choice(('items "x"', "📦 records", "Account")) + str(ordinal),
                        quote_name=rng.choice(("", "two 'quotes'", "経理", 'one "quote"')), marker=rng.choice(("", "猫", "a'β")), warm=ordinal % 3 == 0)
            yield f"seed/{seed}/{ordinal}", analysis(item, source(item, form=rng.choice(("equals", "parentheses")),
                                                                 uppercase=ordinal % 2 == 0)), item
            yield f"direct/{seed}-{ordinal}", item, item
    for ordinal, (kind, argument) in enumerate(product(HEADINGS, ("1", "+1", "-1", "sqlite_schema", "sqlite_temp_schema"))):
        actual_lexeme = {"1": "+1", "+1": "'+1'", "-1": "-1"}.get(argument)
        item = with_target(case(kind), argument)
        yield f"analyze/argument-{ordinal}", analysis(item, source(item, lexeme=actual_lexeme)), item

    for ordinal, (scope, warm) in enumerate(product(("implicit", "main"), (False, True))):
        item = case("table_list", "view", scope, "populated", warm=warm)
        yield f"analyze/view-cache-{ordinal}", analysis(item, source(item)), item


def finite_ids(domain):
    return set(product(*(range(len(domain[name])) for name in AXES)))


def finite_id(measured, domain):
    reply, native = measured
    scope, argument = native["rawInvocation"][1:]
    role = next(name for name, value in selector_map("accounts", 'archive "items"').items() if value == argument)
    requested = "implicit" if scope is None else "attached" if scope == "extension" else scope
    initial = "populated" if any(row[1][1] == "temp" for row in native["attachedBefore"]) else "unopened"
    forms = (reply["pragma"], role, requested, initial)
    return tuple(domain[axis].index(state) for axis, state in zip(AXES, forms))


def forms(measured):
    reply, native = measured
    yield "operation/" + native["invocation"][0]
    yield "scope/" + ("implicit" if reply["schema"] is None else reply["schema"] if reply["schema"] in ("main", "temp") else "attached")
    yield "result/" + ("rows" if native["statement"]["rows"] else "empty")
    yield "target/" + (reply["target"][0] if reply["target"] is not None else "none")
    if reply["schema"] == "":
        yield "scope/empty"
    if reply["schema"] is not None and '"' in reply["schema"]:
        yield "scope/quote"
    if native["attachedBefore"] != native["attachedAfter"]:
        yield "activates/temp"


def required(domain):
    return ({"operation/" + name for name in domain["operations"]} | {"scope/" + scope for scope in domain["scopes"]}
            | {"target/catalog", "target/relation", "target/missing", "target/none", "scope/empty", "scope/quote",
               "result/rows", "result/empty", "activates/temp"})


def negatives():
    item = case()
    yield {**item, "schema": "temp"}, "rejected", "outside the explicit schema"
    yield {**item, "target": ["catalog", "extension"]}, "rejected", "cannot specify an attached schema catalog"
    yield {**item, "target": ["missing", "sqlite_schema"]}, "rejected", "cannot establish the absence"
    yield analysis(item, "PRAGMA main.table_info(absent)", complete=False), "rejected", "cannot prove absence in an open namespace"
    yield analysis(item, "PRAGMA unknown.table_info(accounts)"), "rejected", "requires a known schema qualifier"
    yield analysis(item, "PRAGMA main.table_info(accounts)", extra=("CREATE TABLE main.accounts (private INT)",)), \
        "rejected", "Conflicting declared relations"
    yield analysis(item, "PRAGMA table_xinfo(json_each)"), "rejected", "cannot establish the absence"


def contents(item):
    operation, schema = item["pragma"], item["schema"]
    if operation == "table_list":
        result = []
        name_filter = item["argument"]
        for table in (*fixture(item), *system_tables(item["attachment"])):
            if schema is not None and schema != table.schema:
                continue
            if name_filter is not None and name_filter.translate(ASCII) != table.name.translate(ASCII):
                continue
            builtin = table.kind == "catalog"
            main_view_shadowed = table.kind == "view" and table.schema == "main" and item["temp"] and not item["warm"]
            width = 0 if main_view_shadowed else len(table.fields)
            result.append((table.schema, CATALOG_LABELS[table.name] if builtin else table.name, "table" if builtin else table.kind,
                           width, table.without_rowid, table.strict))
        return result
    table = relation(item)
    if table is None:
        return []
    if operation == "index_list":
        return [(i, *idx) for i, idx in enumerate(table.indexes)]
    if operation == "foreign_key_list":
        return table.foreign_keys
    extended = operation == "table_xinfo"
    columns = [column for column in table.fields if extended or column[-1] == 0]
    return [(i, *(column if extended else column[:-1])) for i, column in enumerate(columns)]


def expected(item):
    pragma, schema = item["pragma"], item["schema"]
    before = [(0, "main", "")]
    after = before.copy()
    if item["temp"]:
        before.append((1, "temp", ""))
    if item["temp"] or schema == "temp" or (pragma == "index_list" and item["target"] == ["catalog", "temp"]):
        after.append((1, "temp", ""))
    attachment = (2, item["attachment"], "")
    statement = result(HEADINGS[pragma], contents(item))
    if pragma == "table_list":
        statement["rows"] = sorted(statement["rows"])
    return {
        "invocation": [pragma, schema, item["argument"] is not None], "statement": statement,
        "attachedBefore": result(rows=(*before, attachment))["rows"],
        "attachedAfter": result(rows=(*after, attachment))["rows"],
    }
