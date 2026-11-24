"""Define the native reclamation domain, fixture transitions and independent coordinates."""

import copy
from itertools import product
from random import Random

from run import require
from run_sqlite_create_table import quoted
from sqlite_flag_pragma_cases import result

AXES = ("operations", "scopes", "values", "initial", "phases")
AUTOMATIC, INCREMENTAL = "auto_vacuum", "incremental_vacuum"
MAXIMUM = 2147483647
NONE, FULL, DEFERRED = 0, 1, 2
MODES = {"empty-none": NONE, "filled-none": NONE, "filled-full": FULL, "filled-incremental": DEFERRED}
TOKENS = {
    "absent": (None, None, None, MAXIMUM),
    "zero": ("0", "0", "0", MAXIMUM),
    "one": ("1", "1", "1", 1),
    "two": ("2", "2", "2", 2),
    "three": ("3", "3", "0", 3),
    "negative": ("-1", "-1", "0", MAXIMUM),
    "overrange": ("2147483648", "2147483648", "0", MAXIMUM),
    "decoded-prefix": ("'2tail'", "2tail", "2", 2),
    "hex": ("0x2", "0x2", "2", 2),
}
SPELLED = (
    ("'NoNe'", "NoNe", "0", MAXIMUM),
    ("'FuLl'", "FuLl", "1", MAXIMUM),
    ("'InCrEmEnTaL'", "InCrEmEnTaL", "2", MAXIMUM),
    ("-2147483648", "-2147483648", "0", MAXIMUM),
    ("'2147483647tail'", "2147483647tail", "0", MAXIMUM),
    ("'+0002tail'", "+0002tail", "2", 2),
    ("' 2'", " 2", "0", MAXIMUM),
    ("+0x0001", "0x0001", "1", 1),
    ("-0x2", "-0x2", "0", MAXIMUM),
    ("0x80000000", "0x80000000", "0", MAXIMUM),
    ("2e9", "2e9", "2", 2),
    ("1.9", "1.9", "1", 1),
    ("DEFAULT", "DEFAULT", "0", MAXIMUM),
)
LIMIT_ERROR = "must be a positive signed 32-bit integer"
SCOPE_ERROR = "requires a known schema qualifier"


def case(kind=AUTOMATIC, scope="implicit", role="absent", initial="filled-incremental", phase="step",
         *, attachment="archive", spelling=None, marker=0, transaction=False):
    qualifier = {"implicit": None, "main": "main", "temp": "temp", "attached": attachment}[scope]
    lexeme, text, automatic, limit = TOKENS[role] if spelling is None else spelling
    return {
        "operation": "reclaim-pragma", "kind": kind, "schema": "main" if qualifier is None else qualifier,
        "value": automatic if kind == AUTOMATIC else limit, "qualifier": qualifier, "lexeme": lexeme, "text": text,
        "phase": phase, "initial": initial, "attachment": attachment, "marker": marker, "transaction": transaction,
    }


def source(item, *, uppercase=False, parentheses=True):
    qualifier, kind, lexeme = (item[field] for field in ("qualifier", "kind", "lexeme"))
    command = "PRAGMA " + ("" if qualifier is None else quoted(qualifier) + ".") + (kind.upper() if uppercase else kind)
    return command if lexeme is None else command + (f"({lexeme})" if parentheses else f" = {lexeme}")


def analysis(item, sql):
    return {"sql": sql, "catalogComplete": True, "declarations": [], "searchPath": ["main", item["attachment"]]}


def requests(contract):
    finite = contract["finite"]
    for index, coordinate in enumerate(product(*(finite[axis] for axis in AXES))):
        item = case(*coordinate)
        yield f"finite/{index}", analysis(item, source(item)), item
        yield f"direct/{index}", item, item
    for seed in contract["exploration"]["seeds"]:
        rng = Random(seed)
        for index in range(contract["exploration"]["casesPerSeed"]):
            scope = finite["scopes"][index % len(finite["scopes"])]
            kind = finite["operations"][index // len(finite["scopes"]) % len(finite["operations"])]
            alias = ("", 'Archive "slot"', "保管庫")[(index // 4) % 3]
            spelling = (tuple(TOKENS.values()) + SPELLED)[(index + seed) % (len(TOKENS) + len(SPELLED))]
            item = case(kind, scope, initial=rng.choice(finite["initial"]), phase=rng.choice(finite["phases"]),
                        attachment=alias, spelling=spelling, marker=rng.randrange(-100, 101))
            yield f"seed/{seed}/{index}", analysis(item, source(item, uppercase=index % 2 == 0, parentheses=index % 3 == 0)), item
            yield f"direct/{seed}-{index}", item, item
    for kind, scope in product(finite["operations"], ("main", "attached")):
        item = case(kind, scope, "one", transaction=True)
        yield f"analyze/rollback-{kind}-{scope}", analysis(item, source(item)), item
        yield f"direct/rollback-{kind}-{scope}", item, item


def negatives():
    item = case(INCREMENTAL)
    for limit in (0, -1, MAXIMUM + 1):
        yield {**item, "value": limit}, "rejected", LIMIT_ERROR
    for kind in (AUTOMATIC, INCREMENTAL):
        yield analysis(item, f"PRAGMA unavailable.{kind}(2)"), "rejected", SCOPE_ERROR


def finite_ids(domain):
    return set(product(*(range(len(domain[key])) for key in AXES)))


def finite_id(measured, domain):
    _reply, observation = measured
    kind, schema, text = observation["invocation"]
    coordinate = [(scope, role) for scope, role in product(domain["scopes"], domain["values"])
                  if (case(kind, scope, role)["qualifier"], TOKENS[role][1]) == (schema, text)
                  and source(case(kind, scope, role)) == observation["input"]]
    require(len(coordinate) == 1, "sqlite-reclaim-native-coordinate", [observation["invocation"], coordinate])
    initial = observation["before"]["databases"]["main"]
    state = "empty-none" if not initial["pages"] else ("filled-none", "filled-full", "filled-incremental")[initial["mode"]]
    return tuple(domain[axis].index(value) for axis, value in
                 zip(AXES, (kind, *coordinate[0], state, observation["phase"])))


def forms(measured):
    reply, native = measured
    kind, value, schema = (reply[field] for field in ("kind", "value", "schema"))
    yield "operation/" + kind
    yield "phase/" + native["phase"]
    if kind == AUTOMATIC:
        yield "mode/" + ("read" if value is None else value)
        if native["after"]["databases"]["main"]["mode"] != native["compacted"]["databases"]["main"]["mode"]:
            yield "effect/pending-restored-on-rewrite"
    else:
        yield "limit/" + ("maximum" if value == MAXIMUM else "finite")
        count = len(native["statement"].get("rows", ()))
        if count:
            yield "effect/reclamation-row"
        if count > 1:
            yield "effect/reclamation-multiplicity"
    if not schema:
        yield "scope/empty"
    if '"' in schema:
        yield "scope/quote"
    if any(ord(character) > 127 for character in schema):
        yield "scope/unicode"


def required(domain):
    return ({f"operation/{kind}" for kind in domain["operations"]}
            | {"phase/prepare", "phase/step", "mode/read", "mode/0", "mode/1", "mode/2", "limit/maximum",
               "limit/finite", "effect/reclamation-row", "effect/reclamation-multiplicity",
               "effect/pending-restored-on-rewrite", "scope/empty", "scope/quote", "scope/unicode"})


def header(mode, pages, free):
    return {"pages": pages, "free": free,
            "root": min(pages, 3) if mode else 0, "incremental": int(mode == DEFERRED)}


def region(mode, pages, free, population=(), physical=True):
    return {
        "mode": mode, "pages": pages, "free": free, "tables": result(rows=(("kept",),) if population else ())["rows"],
        "population": result(rows=population)["rows"], "physical": header(mode, pages, free) if physical and pages else None,
    }


def starting_region(item, index, mode, filled):
    if not filled:
        return region(mode, 0, 0)
    marker = item["marker"]
    pages, free = ((7, 5), (3, 0), (8, 5))[mode]
    data = ((index + 1, 9 + marker), (index + 3, None))
    return region(mode, pages, free, data)


def enclosure(item, schemata):
    addresses = [(0, "main", "primary.sqlite"), (2, item["attachment"], "attached.sqlite")]
    if "temp" in schemata:
        addresses.insert(1, (1, "temp", ""))
    return {"attached": result(rows=addresses)["rows"], "databases": schemata}


def expected(item):
    kind, schema, value, phase = (item[field] for field in ("kind", "schema", "value", "phase"))
    mode = MODES[item["initial"]]
    filled = item["initial"] != "empty-none"
    transaction = item["transaction"]
    before = {name: starting_region(item, index, mode, filled) for index, name in enumerate(("main", item["attachment"]))}
    after = copy.deepcopy(before)
    if schema == "temp":
        after[schema] = region(NONE, 0, 0, physical=False)
    active = after[schema]
    slot = {"main": 0, "temp": 1, item["attachment"]: 2}[schema]
    names, emitted, program, pending = (), (), [], mode
    write = False
    if kind == INCREMENTAL:
        write = True
        program = [["Integer", value, 1], ["IncrVacuum", slot], ["ResultRow", 0], ["Transaction", slot, 1]]
        if phase == "step":
            reclaimed = min(active["free"], value) if active["mode"] == DEFERRED else 0
            active["pages"] = max(active["pages"] - reclaimed, 1)
            active["free"] -= reclaimed
            emitted = [()] * reclaimed
    elif value is None:
        names, emitted = (AUTOMATIC,), ((active["mode"],),)
        program = [["Int64", active["mode"], 1], ["ResultRow", 1]]
    else:
        pending = int(value)
        allowed = active["pages"] == 0 or bool(active["mode"]) == bool(pending)
        if allowed:
            active["mode"] = pending
        if allowed and pending:
            write = True
            program = [["Transaction", slot, 1], ["ReadCookie", slot, 4], ["SetCookie", slot, 7, pending - 1]]
            if phase == "step":
                active["pages"] = max(active["pages"], 1)
                if pending == FULL and not transaction:
                    active["pages"] -= active["free"]
                    active["free"] = 0
    if write and phase == "step" and not transaction and schema != "temp":
        active["physical"] = header(active["mode"], active["pages"], active["free"])
    if phase == "prepare":
        statement = {"names": list(names), "readOnly": not write, "parameters": {}, "parameterCount": 0}
    else:
        statement = result(names, emitted, read_only=not write)
    settled = copy.deepcopy(after)
    if transaction and kind == INCREMENTAL:
        settled[schema] = copy.deepcopy(before[schema])
    compacted = copy.deepcopy(settled)
    if filled:
        if schema == item["attachment"] and (phase == "prepare" or transaction):
            compacted[schema]["mode"] = mode
        pages = 3 if pending else 2
        compacted["main"].update(mode=pending, pages=pages, free=0, physical=header(pending, pages, 0))
    return {
        "before": enclosure(item, before), "after": enclosure(item, after),
        "rollback": enclosure(item, settled) if transaction else None, "compacted": enclosure(item, compacted),
        "statement": statement, "program": program, "phase": phase,
    }
