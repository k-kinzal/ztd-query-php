"""Generate exact native registry reads with distinguishable connection state."""
from itertools import product
from random import Random

from run_sqlite_create_table import quoted
from sqlite_flag_pragma_cases import result

AXES = ("operations", "scopes", "registries", "arguments")
HEADINGS = {
    "database_list": ("seq", "name", "file"),
    "collation_list": ("seq", "name"),
    "compile_options": ("compile_options",),
    "function_list": ("name", "builtin", "type", "enc", "narg", "flags"),
    "module_list": ("name",),
    "pragma_list": ("name",),
}
UNORDERED = frozenset(("function_list", "module_list"))
SAMPLE_ARGUMENTS = {"none": None, "text": "'marker'", "number": "+1"}


def case(pragma="database_list", scope="implicit", registry="stock", argument="none", *, attachment="extension",
         temp_open=False, persistent=False):
    schema = attachment if scope == "attached" else None if scope == "implicit" else scope
    return {"operation": "connection-inventory", "pragma": pragma, "schema": schema, "registry": registry,
            "argument": argument, "attachment": attachment, "tempOpen": temp_open, "file": persistent}


def sql_for(item, lexeme, *, uppercase=False, parentheses=False):
    selector = item["pragma"]
    if uppercase:
        selector = quoted(selector.upper())
    scope = "" if item["schema"] is None else quoted(item["schema"]) + "."
    lead = "PRAGMA " + scope + selector
    if lexeme is None:
        return lead
    return lead + (f" ( {lexeme} )" if parentheses else f" = {lexeme}")


def analyzed(item, sql):
    return {"sql": sql, "declarations": [], "catalogComplete": True, "searchPath": ["main", item["attachment"]]}


def requests(contract):
    finite = contract["finite"]
    for ordinal, state in enumerate(product(*(finite[field] for field in AXES))):
        item = case(*state)
        raw = sql_for(item, SAMPLE_ARGUMENTS[item["argument"]], uppercase=ordinal % 3 == 0, parentheses=ordinal % 2 == 0)
        yield f"finite/{ordinal}", analyzed(item, raw), item
        yield f"direct/{ordinal}", item, item
    for seed in contract["exploration"]["seeds"]:
        rng = Random(seed)
        for ordinal in range(contract["exploration"]["casesPerSeed"]):
            scope = finite["scopes"][ordinal % len(finite["scopes"])]
            kind = finite["operations"][ordinal % len(finite["operations"])]
            mode = finite["registries"][ordinal % len(finite["registries"])]
            arg = rng.choice(finite["arguments"])
            lexeme = rng.choice({
                "none": (None,), "text": ("'marker'", "ON", "'+1'", "'日本語'", "''", "RESET"),
                "number": ("+1", "-1", "1.9", ".5", "0x101", "0x80000000000000000"),
            }[arg])
            alias = "" if ordinal % 12 == 3 else rng.choice(('native "archive"', "別名", "other db")) + str(ordinal)
            item = case(kind, scope, mode, arg, attachment=alias, temp_open=ordinal % 7 == 0, persistent=ordinal % 3 == 1)
            yield f"seed/{seed}/{ordinal}", analyzed(item, sql_for(item, lexeme, uppercase=ordinal % 3 == 0,
                                                                  parentheses=ordinal % 2 == 0)), item
            yield f"direct/{seed}-{ordinal}", item, item


def finite_ids(domain):
    limits = tuple(range(len(domain[axis])) for axis in AXES)
    return set(product(*limits))


def finite_id(measured, domain):
    _reply, native = measured
    kind, schema, received = native["raw"]
    scope = "implicit" if schema is None else schema if schema in ("main", "temp") else "attached"
    mode = native["registration"]
    operand = {None: "none", "marker": "text", "1": "number"}[received]
    return tuple(domain[axis].index(value) for axis, value in zip(AXES, (kind, scope, mode, operand)))


def forms(measured):
    reply, native = measured
    yield "inventory/" + reply["pragma"]
    yield "registration/" + native["registration"]
    qualifier = reply["schema"]
    yield "scope/" + ("implicit" if qualifier is None else qualifier if qualifier in ("temp", "main") else "attached")
    yield "argument/" + ("omitted" if native["raw"][2] is None else "ignored")
    yield "source/" + ("file" if native["before"][2][2] else "memory")
    if qualifier == "":
        yield "schema/empty"
    if qualifier is not None and '"' in qualifier:
        yield "schema/quoted"
    if native["before"][1][2] is not None:
        yield "temp/previously-open"
    if native["before"][1][2] is None and native["after"][1][2] is not None:
        yield "temp/activated"


def required(domain):
    return ({"inventory/" + kind for kind in domain["operations"]} | {"registration/" + mode for mode in domain["registries"]}
            | {"scope/" + scope for scope in domain["scopes"]}
            | {"argument/omitted", "argument/ignored", "source/file", "source/memory", "schema/empty", "schema/quoted",
               "temp/previously-open", "temp/activated"})


def negatives():
    item = case()
    yield {**item, "pragma": {"injected": "database_list"}}, "type-rejected", "InventoryKind::from()"
    yield analyzed(item, "PRAGMA absent.function_list"), "rejected", "requires a known schema qualifier"
    yield analyzed(item, "PRAGMA no_such_registry"), "rejected", "no complete flag representation"
    yield analyzed(item, "PRAGMA function_list = 2_56"), "syntax-rejected", ("Unexpected", "2_56")


def registry_rows(item, baseline, options, pragmas, after):
    mode = item["registry"]
    operations = {
        "database_list": [tuple(row) for row in after if row[2] is not None],
        "collation_list": list(enumerate((["Case Sort", "local_key"] if mode == "extended" else []) + baseline["collations"])),
        "compile_options": [(option,) for option in options],
        "function_list": baseline["functions"] + ([
            ["abs", 0, "s", "utf8", 1, 0x80000],
            ["local_query", 0, "s", "utf8", 2, 0x200800],
        ] if mode == "extended" else []),
        "module_list": [(module,) for module in baseline["modules"] + (["pragma_table_info"] if mode == "lazy" else [])],
        "pragma_list": [(name,) for name in pragmas],
    }
    return operations[item["pragma"]]


def expected(item, baseline, options, pragmas, filename):
    schema, pragma = item["schema"], item["pragma"]
    before = [[0, "main", ""], [1, "temp", "" if item["tempOpen"] else None], [2, item["attachment"], filename]]
    after = [row.copy() for row in before]
    if schema == "temp":
        after[1][2] = ""
    statement = result(HEADINGS[pragma], registry_rows(item, baseline, options, pragmas, after))
    if pragma in UNORDERED:
        statement["rows"] = sorted(statement["rows"])
    return {"invocation": [pragma, schema], "before": before, "after": after,
            "registration": item["registry"], "statement": statement}
