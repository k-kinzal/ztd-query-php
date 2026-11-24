"""Specify finite source forms, expected header bytes and independent typed observations."""

from itertools import product
from random import Random

from run import require
from run_sqlite_create_table import quoted
from sqlite_flag_pragma_cases import result
from sqlite_header_fixture import COOKIE, empty_header, expected_headers, expected_population, initial_names

AXES = ("fields", "scopes", "arguments", "defensive", "transaction", "temp")
READ_ONLY = frozenset(("freelist_count", "data_version"))
TOKENS = {
    "read": (None, None, None),
    "signed": ("-7", "-7", -7),
    "zero": ("0", "0", 0),
    "minimum": ("-2147483648", "-2147483648", -2147483648),
    "maximum": ("2147483647", "2147483647", 2147483647),
    "positive-overflow": ("2147483648", "2147483648", 0),
    "negative-overflow": ("-2147483649", "-2147483649", 0),
    "hexadecimal": ("0x17", "0x17", 23),
    "signed-hexadecimal": ("-0x17", "-0x17", 0),
    "decimal-prefix": ("2.8", "2.8", 2),
    "quoted-prefix": ("'0012xyz'", "0012xyz", 12),
    "invalid-text": ("' 9'", " 9", 0),
}


def case(field="user_version", scope="implicit", role="signed", defensive=False, transaction=False, temp="unopened", *,
         attachment="archive", seed=7, spelling=None):
    qualifier = {"implicit": None, "main": "main", "temp": "temp", "attached": attachment}[scope]
    syntax, native, normalized = TOKENS[role] if spelling is None else spelling
    return {"operation": "header-pragma", "field": field, "qualifier": qualifier,
            "schema": "main" if qualifier is None else qualifier, "argument": native, "lexeme": syntax,
            "value": None if field in READ_ONLY else normalized, "attachment": attachment, "seed": seed,
            "defensive": defensive, "transaction": transaction, "temp": temp == "warm"}


def source(item, *, alternate=False, parentheses=True):
    name = quoted(item["field"].upper()) if alternate else item["field"]
    qualifier = item["qualifier"]
    prefix = "PRAGMA " + ("" if qualifier is None else quoted(qualifier) + ".") + name
    if item["lexeme"] is None:
        return prefix
    return prefix + (f" ( {item['lexeme']} )" if parentheses else f" = {item['lexeme']}")


def analysis(item, statement):
    return {"sql": statement, "declarations": [], "catalogComplete": True, "searchPath": ["main", item["attachment"]]}


def explore_token(index, rng):
    magnitude = rng.randint(1, 2147483647)
    zeros = "0" * rng.randint(0, 20)
    forms = (
        (f"+{zeros}{magnitude}", f"{zeros}{magnitude}", magnitude),
        (f"-{zeros}{magnitude}.6", f"-{zeros}{magnitude}.6", -magnitude),
        (f"0X{zeros}{magnitude:X}", f"0X{zeros}{magnitude:X}", magnitude),
        (f"'+0X{magnitude:X}'", f"+0X{magnitude:X}", 0),
        (f"+0x{magnitude:x}", f"0x{magnitude:x}", magnitude),
        (f"'-{magnitude} suffix'", f"-{magnitude} suffix", -magnitude),
        ("ON", "ON", 0), ("DEFAULT", "DEFAULT", 0), ("''", "", 0), ("'未設定'", "未設定", 0),
        (f"'{magnitude}e72'", f"{magnitude}e72", magnitude), TOKENS["read"],
    )
    return forms[index % len(forms)]


def requests(contract):
    finite = contract["finite"]
    for index, coordinate in enumerate(product(*(finite[key] for key in AXES))):
        item = case(*coordinate)
        yield f"finite/{index}", analysis(item, source(item)), item
        yield f"direct/{index}", item, item
    for seed in contract["exploration"]["seeds"]:
        rng = Random(seed)
        for index in range(contract["exploration"]["casesPerSeed"]):
            attachment = ("", f'quoted "{index}"', "保管" + str(index))[index % 3]
            item = case(finite["fields"][index % len(finite["fields"])], finite["scopes"][index % len(finite["scopes"])],
                        defensive=rng.choice(finite["defensive"]), transaction=rng.choice(finite["transaction"]),
                        temp=rng.choice(finite["temp"]), attachment=attachment, seed=rng.randint(-200, 200),
                        spelling=explore_token(index, rng))
            yield f"seed/{seed}/{index}", analysis(item, source(item, alternate=index % 2 == 0, parentheses=index % 3 == 0)), item
            yield f"direct/{seed}-{index}", item, item
    for scope in ("main", "attached"):
        item = {**case("data_version", scope, "read"), "fileHistory": True}
        yield f"analyze/history-{scope}", analysis(item, source(item)), item
        yield f"direct/history-{scope}", item, item


def negatives():
    item = case()
    yield {**item, "value": 2147483648}, "rejected", "requires a signed 32-bit integer"
    yield {**item, "value": -2147483649}, "rejected", "requires a signed 32-bit integer"
    for field in sorted(READ_ONLY):
        yield {**item, "field": field}, "rejected", "cannot carry a cookie write"
    yield analysis(item, "PRAGMA missing.user_version"), "rejected", "requires a known schema qualifier"


def finite_ids(domain):
    return set(product(*(range(len(domain[key])) for key in AXES)))


def finite_id(measured, domain):
    reply, native = measured
    field, qualifier, argument = native["invocation"]
    addresses = {None: "implicit", "main": "main", "temp": "temp", "archive": "attached"}
    options = [role for role, (syntax, raw, _) in TOKENS.items() if raw == argument and
               native["input"] == source({**case(), "field": field, "qualifier": qualifier, "lexeme": syntax})]
    require(len(options) == 1 and reply["field"] == field, "sqlite-header-finite-argument", native["invocation"])
    before = native["before"]
    coordinate = (field, addresses[qualifier], options[0], before["defensive"], before["transaction"],
                  "warm" if "temp" in before["headers"] else "unopened")
    return tuple(domain[key].index(member) for key, member in zip(AXES, coordinate))


def forms(measured):
    reply, native = measured
    yield "field/" + reply["field"]
    value = reply["value"]
    yield "action/read" if value is None else "action/zero" if value == 0 else "action/negative" if value < 0 else "action/positive"
    name = reply["schema"]
    if name == "":
        yield "scope/empty"
    if '"' in name:
        yield "scope/quote"
    if any(ord(character) > 127 for character in name):
        yield "scope/unicode"
    if native["before"]["attached"] != native["after"]["attached"]:
        yield "effect/temp-open"
    if native["after"]["headers"] != native["settled"]["headers"]:
        yield "effect/rollback"
    if native["opcode"][0] == "Noop":
        yield "effect/defensive-noop"


def required(domain):
    return ({"action/read", "action/zero", "action/negative", "action/positive", "scope/empty", "scope/quote",
             "scope/unicode", "effect/temp-open", "effect/rollback", "effect/defensive-noop"}
            | {"field/" + field for field in domain["fields"]})


def expected(item):
    field, schema, integer = (item[key] for key in ("field", "schema", "value"))
    ids = initial_names(item)
    final_ids = {**ids, **({"temp": 1} if schema == "temp" else {})}
    slot = final_ids[schema]
    initial = expected_headers(item)
    active = {name: header.copy() for name, header in initial.items()}
    active.setdefault(schema, empty_header())
    population = expected_population(item)
    rollback = {**initial, **({"temp": empty_header()} if schema == "temp" and "temp" not in initial else {})}
    write = integer is not None
    guarded = write and field == "schema_version" and item["defensive"]
    if write and not guarded:
        active[schema][field] = integer
    output = [] if write else [(1 if field == "data_version" else active[schema][field],)]
    opcode = ["Noop" if guarded else "SetCookie", slot, COOKIE[field], integer, 1] if write else ["ReadCookie", slot, 1, COOKIE[field], 0]
    committed = active if not item["transaction"] else rollback

    def state(names, headers, transaction):
        attached = result(rows=((number, name, "") for name, number in sorted(names.items(), key=lambda pair: pair[1])))["rows"]
        return {"attached": attached, "headers": headers, "population": population, "transaction": transaction,
                "defensive": item["defensive"]}

    statement = {**result(() if write else (field,), output), "readOnly": not write, "evaluationError": None}
    return {"statement": statement, "slots": [[slot, int(write)]], "opcode": opcode,
            "before": state(ids, initial, item["transaction"]), "after": state(final_ids, active, item["transaction"]),
            "settled": state(final_ids, committed, False)}
