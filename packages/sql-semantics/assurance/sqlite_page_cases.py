"""Externally defined page-operation coordinates and independent effect predictions."""

import copy
from itertools import product
from random import Random

from run import require
from run_sqlite_create_table import quoted
from sqlite_flag_pragma_cases import result

AXES = ("kinds", "scopes", "values", "initial", "phases")
MAXIMUM = 4294967294
PAGE_SIZES = frozenset(1 << bits for bits in range(9, 17))
TOKENS = {
    "absent": (None, None, None, None),
    "zero": ("0", "0", 0, None),
    "floor": ("1", "1", 1, "1"),
    "valid-size": ("512", "512", 512, "512"),
    "larger-size": ("65536", "65536", 65536, "65536"),
    "invalid-size": ("724", "724", 724, "724"),
    "negative": ("-4", "-4", -4, None),
    "uint-max": ("4294967294", "4294967294", 0, "4294967294"),
    "above-native": ("4294967295", "4294967295", 0, "4294967294"),
    "int64-max": ("9223372036854775807", "9223372036854775807", 0, "4294967294"),
    "int64-overflow": ("9223372036854775808", "9223372036854775808", 0, None),
    "hex-trailing": ("'0x10 '", "0x10 ", 16, None),
    "prefix-tail": ("'500tail'", "500tail", 500, None),
    "whitespace-stop": ("'32\rtail'", "32\rtail", 32, "32"),
}
SPELLED = (
    ("-2147483648", "-2147483648", -2147483648, None),
    ("+0x200", "0x200", 512, "512"),
    ("-0x200", "-0x200", 0, None),
    ("0x7FFFFFFFFFFFFFFF", "0x7FFFFFFFFFFFFFFF", 0, "4294967294"),
    ("0x8000000000000000", "0x8000000000000000", 0, None),
    ("0xFFFFFFFFFFFFFFFF", "0xFFFFFFFFFFFFFFFF", 0, None),
    ("' 24\t '", " 24\t ", 0, "24"),
    ("'\r24'", "\r24", 0, None),
    ("'24\vtail'", "24\vtail", 24, "24"),
    ("'24\ftail'", "24\ftail", 24, "24"),
    ("2.8", "2.8", 2, None),
    ("2e8", "2e8", 2, None),
    ("DEFAULT", "DEFAULT", 0, None),
    ("ON", "ON", 0, None),
)


def case(kind="page_size", scope="implicit", role="absent", initial="file-populated", phase="step",
         *, attachment="archive", spelling=None):
    qualifier = {"implicit": None, "main": "main", "temp": "temp", "attached": attachment}[scope]
    lexeme, text, size, maximum = TOKENS[role] if spelling is None else spelling
    value = ["read"]
    if kind == "page_size" and size is not None:
        value = ["size", size]
    if kind == "max_page_count" and maximum is not None:
        value = ["limit", maximum]
    return {"operation": "page-pragma", "kind": kind, "qualifier": qualifier,
            "schema": "main" if qualifier is None else qualifier, "lexeme": lexeme, "text": text, "value": value,
            "attachment": attachment, "initial": initial, "phase": phase}


def source(item, *, uppercase=False, parentheses=True):
    qualifier, name = item["qualifier"], item["kind"]
    command = quoted(name.upper()) if uppercase else name
    statement = "PRAGMA " + ("" if qualifier is None else quoted(qualifier) + ".") + command
    value = item["lexeme"]
    return statement if value is None else statement + (f"({value})" if parentheses else f" = {value}")


def analysis(item, sql):
    return {"sql": sql, "catalogComplete": True, "declarations": [], "searchPath": ["main", item["attachment"]]}


def requests(contract):
    finite = contract["finite"]
    for index, coordinate in enumerate(product(*(finite[key] for key in AXES))):
        item = case(*coordinate)
        yield f"finite/{index}", analysis(item, source(item)), item
        yield f"direct/{index}", item, item
    for seed in contract["exploration"]["seeds"]:
        rng = Random(seed)
        for index in range(contract["exploration"]["casesPerSeed"]):
            mode = finite["kinds"][index % len(finite["kinds"])]
            scope = finite["scopes"][index % len(finite["scopes"])]
            alias = ("", 'Archive "slot"', "保管")[index % 3] + ("" if index % 3 == 0 else str(index))
            spelling = (tuple(TOKENS.values()) + SPELLED)[(index + seed) % (len(TOKENS) + len(SPELLED))]
            item = case(mode, scope, initial=rng.choice(finite["initial"]), phase=rng.choice(finite["phases"]),
                        attachment=alias, spelling=spelling)
            yield f"seed/{seed}/{index}", analysis(item, source(item, uppercase=index % 2 == 0, parentheses=index % 3 == 0)), item
            yield f"direct/{seed}-{index}", item, item
    item = {**case("max_page_count", spelling=("3", "3", 3, "3")), "growth": True}
    yield "analyze/native-cap-rollback", analysis(item, source(item)), item
    yield "direct/native-cap-rollback", item, item


def negatives():
    item = case()
    for kind, value in (("page_count", ["size", 512]), ("page_size", ["limit", "2"]), ("max_page_count", ["size", 2])):
        yield {**item, "kind": kind, "value": value}, "rejected", "must match its native operation"
    for value in ("0", "001", "4294967295"):
        yield {**item, "kind": "max_page_count", "value": ["limit", value]}, "rejected", "canonical decimal from 1"
    yield {**item, "value": ["size", 2147483648]}, "rejected", "needs a signed 32-bit input"
    yield analysis(item, "PRAGMA no_such.page_count"), "rejected", "requires a known schema qualifier"


def finite_ids(domain):
    return set(product(*(range(len(domain[key])) for key in AXES)))


def finite_id(measured, domain):
    _reply, observation = measured
    mode, schema, native = observation["invocation"]
    choices = [(scope, role) for scope, role in product(domain["scopes"], domain["values"])
               if (case(mode, scope, role)["qualifier"], TOKENS[role][1]) == (schema, native)
               and source(case(mode, scope, role)) == observation["input"]]
    require(len(choices) == 1, "sqlite-page-native-coordinate", [observation["invocation"], choices])
    before = observation["before"]["databases"]
    initial = "temp-warm" if "temp" in before else "empty" if before["main"]["page_count"] == 0 else "file-populated"
    coordinate = (mode, *choices[0], initial, observation["phase"])
    return tuple(domain[key].index(value) for key, value in zip(AXES, coordinate))


def forms(measured):
    reply, native = measured
    yield "operation/" + reply["kind"]
    yield "argument/" + reply["value"][0]
    yield "phase/" + native["phase"]
    if native["before"]["attached"] != native["after"]["attached"]:
        yield "effect/temp-opens"
    if native["compacted"]["databases"]["main"]["page_size"] != native["after"]["databases"]["main"]["page_size"]:
        yield "effect/pending-size"
    if reply["schema"] == "":
        yield "scope/empty"
    if '"' in reply["schema"]:
        yield "scope/quote"
    if any(ord(character) > 127 for character in reply["schema"]):
        yield "scope/unicode"
    if reply["value"] == ["limit", "4294967294"]:
        yield "argument/capped"


def required(domain):
    return ({f"operation/{kind}" for kind in domain["kinds"]}
            | {"argument/read", "argument/size", "argument/limit", "argument/capped", "phase/prepare", "phase/step",
               "effect/temp-opens", "effect/pending-size", "scope/empty", "scope/quote", "scope/unicode"})


def fixture_state(item):
    pages = 0 if item["initial"] == "empty" else 2
    schemata = {"main": {"page_size": 4096, "page_count": pages, "max_page_count": 70},
                item["attachment"]: {"page_size": 4096, "page_count": pages, "max_page_count": 110}}
    if item["initial"] == "temp-warm":
        schemata["temp"] = {"page_size": 4096, "page_count": 2, "max_page_count": 93}
    return schemata


def attachment_state(schemata, item):
    addresses = [(0, "main", "primary.sqlite"), (2, item["attachment"], "attached.sqlite")]
    if "temp" in schemata:
        addresses.insert(1, (1, "temp", ""))
    return {"attached": result(rows=addresses)["rows"], "databases": schemata}


def expected(item):
    kind, schema, value = (item[key] for key in ("kind", "schema", "value"))
    before = fixture_state(item)
    after = copy.deepcopy(before)
    pending = 0 if item["initial"] == "empty" else 1024
    if schema == "temp" and schema not in after:
        after[schema] = {"page_size": pending or 4096, "page_count": 0, "max_page_count": MAXIMUM}
    active = after[schema]
    size_write = value[0] == "size"
    if size_write:
        pending = value[1]
        if active["page_count"] == 0 and pending in PAGE_SIZES:
            active["page_size"] = pending
    requested = int(value[1]) if value[0] == "limit" else 0
    if requested and item["phase"] == "step":
        active["max_page_count"] = max(requested, active["page_count"])
    names = [] if size_write else [kind]
    if item["phase"] == "prepare":
        statement = {"names": names, "readOnly": True, "parameters": {}, "parameterCount": 0}
    else:
        statement = {**result(names, [] if size_write else ((active[kind],),)), "evaluationError": None}
    slot = {"main": 0, "temp": 1, item["attachment"]: 2}[schema]
    opcode = [] if size_write else ([["Int64", active["page_size"]]] if kind == "page_size" else
              [["Pagecount", slot]] if kind == "page_count" else [["MaxPgcnt", slot, requested]])
    compacted = copy.deepcopy(after)
    if item["initial"] != "empty" and pending in PAGE_SIZES:
        compacted["main"]["page_size"] = pending
    return {"before": attachment_state(before, item), "after": attachment_state(after, item),
            "compacted": attachment_state(compacted, item), "statement": statement, "opcode": opcode,
            "slots": [] if kind == "page_size" else [slot], "phase": item["phase"]}
