"""Define cache coordinates and the independent pager and connection model."""

from copy import deepcopy
from itertools import product
from random import Random

from run import require
from run_sqlite_create_table import quoted
from sqlite_flag_pragma_cases import result

LIVE, DEFAULT, SPILL = ("cache_size", "default_cache_size", "cache_spill")
AXES = ("operations", "scopes", "values", "pageSizes", "initialSpilling", "phases")
ON, OFF = "ON", "OFF"
STOCK_CAPACITY, OVERHEAD = -2000, 136
FLOOR, CEILING = -2147483648, 2147483647
TOKENS = {
    "absent": (None, None, None, None),
    "zero": ("0", "0", 0, ["switch", OFF]),
    "one": ("1", "1", 1, ["threshold", 1, ON]),
    "threshold": ("13", "13", 13, ["threshold", 13, ON]),
    "low-byte-zero": ("256", "256", 256, ["threshold", 256, OFF]),
    "quoted-plus": ("'+256'", "+256", 256, ["threshold", 256, ON]),
    "negative": ("-128", "-128", -128, ["threshold", -128, ON]),
    "minimum": ("-2147483648", "-2147483648", FLOOR, ["threshold", FLOOR, ON]),
    "overflow": ("2147483648", "2147483648", 0, ["switch", OFF]),
    "hex": ("0x100", "0x100", 256, ["threshold", 256, OFF]),
    "true": ("ON", "ON", 0, ["switch", ON]),
    "false": ("NO", "NO", 0, ["switch", OFF]),
    "fallback": ("RESET", "RESET", 0, ["switch", ON]),
}
SPELLED = (
    ("+256", "256", 256, ["threshold", 256, OFF]),
    ("'+2147483648'", "+2147483648", 0, ["switch", ON]),
    ("'-0'", "-0", 0, ["switch", OFF]),
    ("'+0'", "+0", 0, ["switch", OFF]),
    ("'FaLsE'", "FaLsE", 0, ["switch", OFF]),
    ("'YeS'", "YeS", 0, ["switch", ON]),
    ("'TrUe'", "TrUe", 0, ["switch", ON]),
    ("'256tail'", "256tail", 256, ["threshold", 256, OFF]),
    ("' 256'", " 256", 0, ["switch", ON]),
    ("0x100000000", "0x100000000", 0, ["switch", OFF]),
    ("0x7fffffff", "0x7fffffff", CEILING, ["threshold", CEILING, ON]),
    ("-0x100", "-0x100", 0, ["switch", OFF]),
    ("2e9", "2e9", 2, ["threshold", 2, ON]),
    ("1.9", "1.9", 1, ["threshold", 1, ON]),
    ("'-000128 units'", "-000128 units", -128, ["threshold", -128, ON]),
)
AMOUNT_ERROR = "cache amount must be a signed 32-bit integer"
DEFAULT_ERROR = "needs a nonnegative signed 32-bit page count"
ZERO_ERROR = "needs a nonzero quantity"
PAIR_ERROR = "needs a positive multiple of 256"
SCOPE_ERROR = "requires a known schema qualifier"


def case(kind=LIVE, scope="implicit", role="absent", page_size=4096, spilling=True, phase="step",
         *, attachment="archive", marker=0, spelling=None, transaction=False, pressure=None):
    qualifier = {"implicit": None, "main": "main", "temp": "temp", "attached": attachment}[scope]
    lexeme, text, amount, threshold = TOKENS[role] if spelling is None else spelling
    if kind == SPILL:
        value = threshold
    else:
        value = min(abs(amount), CEILING) if kind == DEFAULT and amount is not None else amount
    return {
        "operation": "cache-pragma", "kind": kind, "schema": "main" if qualifier is None else qualifier,
        "value": value, "qualifier": qualifier, "lexeme": lexeme, "text": text, "phase": phase,
        "pageSize": page_size, "spilling": spilling, "attachment": attachment, "marker": marker,
        "transaction": transaction, "pressure": pressure,
    }


def source(item, *, uppercase=False, parentheses=True):
    qualifier, kind, lexeme = (item[field] for field in ("qualifier", "kind", "lexeme"))
    command = "PRAGMA " + ("" if qualifier is None else quoted(qualifier) + ".") + (kind.upper() if uppercase else kind)
    return command if lexeme is None else command + (f"({lexeme})" if parentheses else f" = {lexeme}")


def analysis(item, sql):
    return {"sql": sql, "catalogComplete": True, "declarations": [], "searchPath": ["main", item["attachment"]]}


def requests(contract):
    finite = contract["finite"]
    for index, coord in enumerate(product(*(finite[axis] for axis in AXES))):
        item = case(*coord)
        yield f"finite/{index}", analysis(item, source(item)), item
        yield f"direct/{index}", item, item
    for seed in contract["exploration"]["seeds"]:
        rng = Random(seed)
        for index in range(contract["exploration"]["casesPerSeed"]):
            scope = finite["scopes"][index % 4]
            kind = finite["operations"][index // 4 % 3]
            alias = ("", 'Archive "slot"', "保管庫")[index // 16 % 3]
            spelling = (tuple(TOKENS.values()) + SPELLED)[(index + seed) % (len(TOKENS) + len(SPELLED))]
            item = case(kind, scope, page_size=rng.choice(finite["pageSizes"]), spilling=bool(rng.getrandbits(1)),
                        phase=rng.choice(finite["phases"]), attachment=alias, marker=rng.randrange(-100, 101), spelling=spelling)
            yield f"seed/{seed}/{index}", analysis(item, source(item, uppercase=index % 2 == 0, parentheses=index % 3 == 0)), item
            yield f"direct/{seed}-{index}", item, item
    for kind, scope in product(finite["operations"], ("main", "attached")):
        item = case(kind, scope, "negative", transaction=True)
        yield f"analyze/rollback-{kind}-{scope}", analysis(item, source(item)), item
        yield f"direct/rollback-{kind}-{scope}", item, item
    for role, when in product(("one", "false", "quoted-plus", "low-byte-zero"), ("before", "inside")):
        item = case(SPILL, "attached", role, pressure=when)
        yield f"analyze/pressure-{role}-{when}", analysis(item, source(item)), item
        yield f"direct/pressure-{role}-{when}", item, item


def negatives():
    for amount in (FLOOR - 1, CEILING + 1):
        yield {**case(), "value": amount}, "rejected", AMOUNT_ERROR
    for amount in (-1, CEILING + 1):
        yield {**case(DEFAULT), "value": amount}, "rejected", DEFAULT_ERROR
    for amount, reason in ((0, ZERO_ERROR), (-256, PAIR_ERROR), (13, PAIR_ERROR)):
        yield {**case(SPILL), "value": ["threshold", amount, OFF]}, "rejected", reason
    for kind in (LIVE, DEFAULT, SPILL):
        yield analysis(case(), f"PRAGMA unavailable.{kind}(13)"), "rejected", SCOPE_ERROR


def finite_ids(domain):
    return set(product(*(range(len(domain[key])) for key in AXES)))


def finite_id(measured, domain):
    _reply, observation = measured
    kind, schema, text = observation["invocation"]
    coordinate = [(scope, role) for scope, role in product(domain["scopes"], domain["values"])
                  if (case(kind, scope, role)["qualifier"], TOKENS[role][1]) == (schema, text)
                  and source(case(kind, scope, role)) == observation["input"]]
    require(len(coordinate) == 1, "sqlite-cache-native-coordinate", [observation["invocation"], coordinate])
    before = observation["before"]["databases"]["main"]
    values = (kind, *coordinate[0], before["pageSize"], before["spill"] != 0, observation["phase"])
    return tuple(domain[axis].index(value) for axis, value in zip(AXES, values))


def forms(measured):
    reply, native = measured
    kind, value, schema = (reply[field] for field in ("kind", "value", "schema"))
    yield "operation/" + kind
    yield "phase/" + native["phase"]
    yield "initial/" + ("enabled" if native["before"]["databases"]["main"]["spill"] else "disabled")
    if value is None:
        yield "argument/read"
    elif kind == SPILL:
        yield "argument/" + value[0] + "/" + value[-1]
    else:
        yield "argument/zero" if not value else "argument/positive" if value > 0 else "argument/negative"
    if not schema:
        yield "scope/empty"
    if '"' in schema:
        yield "scope/quote"
    if any(ord(character) > 127 for character in schema):
        yield "scope/unicode"


def required(domain):
    return ({f"operation/{kind}" for kind in domain["operations"]}
            | {"phase/prepare", "phase/step", "argument/read", "argument/zero", "argument/positive", "argument/negative",
               "argument/switch/ON", "argument/switch/OFF", "argument/threshold/ON", "argument/threshold/OFF",
               "initial/enabled", "initial/disabled", "scope/empty", "scope/quote", "scope/unicode"})


def native_pages(amount, page_size):
    return amount if amount >= 0 else -1024 * amount // (page_size + OVERHEAD)


def initial_state(item, reopened=False):
    registers = {}
    for index, schema in enumerate(("main", item["attachment"])):
        cookie = 13 + index
        data = ((index + 1, 11 + item["marker"]), (index + 3, None))
        registers[schema] = {"live": cookie if reopened else 17 + index, "capacity": cookie if reopened else 17 + index,
                             "threshold": 1 if reopened else 32 + index, "pageSize": item["pageSize"],
                             "persistent": cookie, "physical": cookie, "rows": result(rows=data)["rows"]}
    return registers


def with_temp(registers, page_size):
    registers["temp"] = {"live": 0, "capacity": STOCK_CAPACITY, "threshold": 1, "pageSize": page_size,
                         "persistent": 0, "physical": None, "rows": []}


def state(item, registers, enable):
    addresses = [(0, "main", "primary.sqlite"), (2, item["attachment"], "attached.sqlite")]
    if "temp" in registers:
        addresses.insert(1, (1, "temp", ""))
    measurements = {}
    for schema, fields in registers.items():
        size = fields["pageSize"]
        capacity = min(native_pages(fields["capacity"], size), 1000000000) if fields["capacity"] < 0 else fields["capacity"]
        measurements[schema] = {
            "pageSize": size,
            "live": fields["live"],
            "default": fields["persistent"] or STOCK_CAPACITY,
            "spill": max(capacity, fields["threshold"]) if enable else 0,
            "cookie": fields["physical"],
            "tables": result(rows=(("kept",),) if fields["rows"] else ())["rows"],
            "population": fields["rows"],
        }
    return {"attached": result(rows=addresses)["rows"], "databases": measurements}


def expected(item):
    kind, schema, value, phase = (item[field] for field in ("kind", "schema", "value", "phase"))
    enabled = item["spilling"]
    registers = initial_state(item)
    before = state(item, registers, enabled)
    if schema == "temp":
        with_temp(registers, item["pageSize"])
    target = registers[schema]
    slot = {"main": 0, "temp": 1, item["attachment"]: 2}[schema]
    write = kind == DEFAULT and value is not None
    column = LIVE if kind == DEFAULT else kind
    names, emitted, program = (), (), []
    old_cookie = target["persistent"]
    if value is None:
        names = (column,)
        emitted = ((state(item, registers, enabled)["databases"][schema][{LIVE: "live", DEFAULT: "default", SPILL: "spill"}[kind]],),)
        if kind == DEFAULT:
            program = [["Transaction", slot, 0], ["ReadCookie", slot, 3], ["ResultRow", 1]]
        else:
            program = [["Int64", emitted[0][0]], ["ResultRow", 1]]
    elif kind == SPILL:
        enabled = value[-1] == ON
        if value[0] == "threshold":
            target["threshold"] = native_pages(value[1], target["pageSize"])
    else:
        target.update(live=value, capacity=value)
        if kind == DEFAULT:
            program = [["SetCookie", slot, 3, value], ["Transaction", slot, 1]]
            if phase == "step":
                target["persistent"] = value
                if not item["transaction"] and schema != "temp":
                    target["physical"] = value
    if phase == "prepare":
        statement = {"names": list(names), "readOnly": not write, "parameters": {}, "parameterCount": 0}
    else:
        statement = result(names, emitted, read_only=not write)
    after = state(item, registers, enabled)
    restored = None
    if item["transaction"]:
        target["persistent"] = old_cookie
        restored = state(item, registers, enabled)
    reopened = initial_state(item, reopened=True)
    if schema != "temp" and kind == DEFAULT and phase == "step" and value is not None and not item["transaction"]:
        reopened[schema].update(live=value or STOCK_CAPACITY, capacity=value or STOCK_CAPACITY, persistent=value, physical=value)
    return {"before": before, "after": after, "rollback": restored, "revealed": state(item, registers, True),
            "reopened": state(item, reopened, True), "statement": statement, "program": program, "phase": phase}


def pressure_expected(item):
    change = item["value"]
    threshold = change[1] if change[0] == "threshold" else 1
    flag = change[-1] == ON
    active = flag or item["pressure"] == "inside"
    out = {}
    for scope in ("main", item["attachment"]):
        limit = threshold if scope == item["schema"] else 1
        out[scope] = {
            "spills": 5 if active and limit <= 6 else 0,
            "visible": max(6, limit) if flag else 0,
            "rowCount": 9,
            "restored": result(rows=((0, bytes([1])),))["rows"],
        }
    return out
