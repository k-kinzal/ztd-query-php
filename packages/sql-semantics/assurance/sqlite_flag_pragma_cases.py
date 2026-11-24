"""Define flag-pragma input witnesses and predictions without using the candidate."""
from itertools import product
from random import Random

from run_sqlite_create_table import quoted
from sqlite_engine import typed

AXES = ("flags", "changes", "scopes", "initial", "transactions")
SPELLINGS = {
    "read": (None,),
    "0": ("OFF", "'FALSE'", "DELETE", "DEFAULT", "256", "0x100", "'0256z'", "'0x80000001'",
          "' true'", "'+1'", "'-1'", "-1", ".5", "0.5", "0e4", "2147483648", "0x80000000000000000",
          "''", "'日本語'", "'FULL'", "'NORMAL'", "'0xZ'"),
    "1": ("ON", "'YES'", "'TRUE'", "2", "255", "257", "0x101", "'0257z'", "2147483647",
          "+1", "1.9", "1e99999", "'1e-2'", "0x000000000000ffff", "1.e-50", "'0X101tail'"),
    "RESET": ("RESET", "'reset'", '"ReSeT"'),
}


def case(flag="automatic_index", change="1", scope="implicit", initial=0, transaction=False, attachment="extension"):
    schema = {"implicit": None, "main": "main", "temp": "temp", "attached": attachment}[scope]
    return {"operation": "flag-pragma", "flag": flag, "change": None if change == "read" else change,
            "schema": schema, "initial": initial, "transaction": transaction, "attachment": attachment}


def analyzed(item, sql):
    return {"sql": sql, "declarations": [], "catalogComplete": True, "searchPath": ["main", item["attachment"]]}


def sql_for(item, lexeme, *, parentheses=False, alternate=False):
    schema, name = item["schema"], item["flag"]
    if alternate:
        name = quoted(name.upper())
        schema = schema.upper() if schema is not None and schema.isascii() else schema
    prefix = "PRAGMA " + ("" if schema is None else quoted(schema) + ".") + name
    if lexeme is None:
        return prefix
    return prefix + (f" ( {lexeme} )" if parentheses else f" = {lexeme}")


def coordinates(domain):
    yield from product(*(domain[name] for name in AXES))
    yield from product((domain["extra"]["flag"],), (domain["extra"]["change"],), *(domain[name] for name in AXES[2:]))


def requests(specification):
    domain = specification["finite"]
    for index, row in enumerate(coordinates(domain)):
        item = case(*row)
        yield f"finite/{index}", item, item
        source = sql_for(item, item["change"], parentheses=index % 2 == 0, alternate=index % 3 == 0)
        yield f"analyze/finite-{index}", analyzed(item, source), item
    for seed in specification["exploration"]["seeds"]:
        random = Random(seed)
        flags = list(domain["flags"])
        random.shuffle(flags)
        for index in range(specification["exploration"]["casesPerSeed"]):
            flag = flags[index % len(flags)]
            changes = list(domain["changes"]) + (["RESET"] if flag == "writable_schema" else [])
            change = "RESET" if flag == "writable_schema" and index < len(flags) else random.choice(changes)
            attachment = random.choice(("ext", 'db "new"', "", "保管", "space ")) + (str(index) if index % 2 else "")
            item = case(flag, change, random.choice(domain["scopes"]), random.choice(domain["initial"]),
                        random.choice(domain["transactions"]), attachment)
            yield f"seed/{seed}/{index}", item, item
            source = sql_for(item, random.choice(SPELLINGS[change]), parentheses=index % 2 == 0, alternate=index % 3 == 0)
            yield f"analyze/{seed}-{index}", analyzed(item, source), item
    index = 0
    for change, spellings in SPELLINGS.items():
        for rhs in spellings:
            for initial in (0, 1):
                flag = "writable_schema" if change == "RESET" else "automatic_index"
                item = case(flag, change, "attached", initial, False, 'witness "quoted"')
                yield f"analyze/operand-{index}", analyzed(item, sql_for(item, rhs, parentheses=index % 3 == 0)), item
                index += 1


def finite_ids(domain):
    flags, scopes = range(len(domain["flags"])), range(len(domain["scopes"]))
    ordinary = set(product(flags, range(len(domain["changes"])), scopes, (0, 1), (0, 1)))
    return ordinary | set(product((domain["flags"].index(domain["extra"]["flag"]),),
                                  (len(domain["changes"]),), scopes, (0, 1), (0, 1)))


def finite_id(measured, domain):
    reply, native = measured
    _, schema, wrote = native["authorization"]
    scope = "implicit" if schema is None else schema if schema in ("temp", "main") else "attached"
    change = reply["change"] if wrote else "read"
    return (
        domain["flags"].index(reply["flag"]),
        len(domain["changes"]) if change == domain["extra"]["change"] else domain["changes"].index(change),
        domain["scopes"].index(scope), int(native["initial"][0][0][1]), int(native["active"]),
    )


def forms(measured):
    reply, native = measured
    yield "name/" + native["authorization"][0]
    yield "change/" + (reply["change"] or "read")
    yield "target/" + ("implicit" if reply["schema"] is None else reply["schema"] if reply["schema"] in ("main", "temp") else "attached")
    yield f"initial/{native['initial'][0][0][1]}"
    yield f"active/{native['active']}"
    if reply["schema"] is not None and '"' in reply["schema"]:
        yield "scope/embedded-quote"
    if reply["schema"] == "":
        yield "scope/empty"


def required(domain):
    return ({"name/" + flag for flag in domain["flags"]}
            | {"change/" + value for value in (*domain["changes"], "RESET")}
            | {"target/" + scope for scope in domain["scopes"]}
            | {"initial/0", "initial/1", "active/False", "active/True", "scope/embedded-quote", "scope/empty"})


def negatives():
    item = case()
    yield {**item, "change": "RESET"}, "rejected", "Only writable_schema"
    yield {**item, "flag": {"mutable": "foreign_keys"}}, "type-rejected", "FlagName::from()"
    yield analyzed(item, "PRAGMA absent.automatic_index"), "rejected", "requires a known schema qualifier"
    yield {**item, "flag": "page_size"}, "enum-rejected", ("page_size", "not a valid backing value")
    yield analyzed(item, "PRAGMA missing_pragma = 1"), "rejected", "no complete flag representation"
    yield analyzed(item, "PRAGMA automatic_index = 2_56"), "syntax-rejected", ("Unexpected", "2_56")


def result(names=(), rows=(), *, read_only=True, changes=0, error=None):
    return {"names": list(names), "rows": [[typed(cell) for cell in row] for row in rows], "parameters": {},
            "parameterCount": 0, "readOnly": read_only, "changes": changes, "evaluationError": error}


def flag_row(value):
    return [[typed(value)]]


def expected(item, effects):
    name, change, initial = item["flag"], item["change"], item["initial"]
    scopes = [[0, "main", ""], [2, item["attachment"], ""]]
    transition = initial if change is None or (name == "foreign_keys" and item["transaction"]) else int(change == "1")
    after_schemas = scopes.copy()
    if item["schema"] == "temp":
        after_schemas.insert(1, [1, "temp", ""])
    return {
        "initial": flag_row(initial), "afterPrepare": flag_row(transition), "after": flag_row(transition),
        "authorization": [name, item["schema"], change is not None],
        "schemasBefore": [[typed(value) for value in row] for row in scopes],
        "schemasAfter": [[typed(value) for value in row] for row in after_schemas],
        "active": item["transaction"],
        "statement": result((name,), ((initial,),)) if change is None else result(),
        "effects": effects(name, transition, change, item["transaction"]),
    }
