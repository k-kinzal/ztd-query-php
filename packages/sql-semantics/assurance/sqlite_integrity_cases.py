"""Construct audit coordinates and predict the native scope and reports independently."""

from itertools import product
from random import Random

from run import require
from run_sqlite_create_table import quoted
from sqlite_flag_pragma_cases import result
from sqlite_integrity_fixture import CHECKED, INDEX, declarations, fixture_rows, guards

AXES = ("kinds", "selectors", "temp", "data", "ignoreCheck")
MAXIMUM = 2147483647
UNKNOWN = "requires a resolved table, view or schema catalog"


def selectors(item):
    child, attachment = item["child"], item["attachment"]
    number = item["number"]
    return {
        "implicit-default": (None, None, None, ["limit", 100]),
        "main-default": ("main", None, None, ["limit", 100]),
        "temp-default": ("temp", None, None, ["limit", 100]),
        "attachment-default": (attachment, None, None, ["limit", 100]),
        "numeric-one": (None, str(number), str(number), ["limit", number]),
        "signed-one": (None, "-" + str(number), "-" + str(number), ["limit", number]),
        "explicit-plus": (None, "+2", "2", ["limit", 2]),
        "decimal-prefix": (None, "1.9", "1.9", ["limit", 1]),
        "exponent-prefix": (None, "1e4", "1e4", ["limit", 1]),
        "zero-budget": (None, "0", "0", ["limit", 100]),
        "bounded-max": ("main", str(MAXIMUM), str(MAXIMUM), ["limit", MAXIMUM]),
        "hex-two": (None, "0x2", "0x2", ["limit", 2]),
        "hex-zero": (None, "0x0", "0x0", ["limit", 100]),
        "decimal-overflow-name": (None, "2147483648", "2147483648", ["relation", "main", "2147483648", "table"]),
        "hex-overflow-name": (None, "0x80000000", "0x80000000", ["relation", "main", "0x80000000", "table"]),
        "signed-overflow-name": (None, "-2147483648", "-2147483648", ["relation", "main", "-2147483648", "table"]),
        "dot-name": (None, ".5", ".5", ["relation", "main", ".5", "table"]),
        "quoted-numeric-name": (None, quoted("1"), "1", ["relation", "main", "1", "table"]),
        "implicit-guard": (None, quoted(child), child, ["relation", "temp" if item["temp"] else "main", child, "table"]),
        "main-guard": ("main", quoted(child), child, ["relation", "main", child, "table"]),
        "attachment-guard": (attachment, quoted(child), child, ["relation", attachment, child, "table"]),
        "named-view": (None, "guard_view", "guard_view", ["relation", "main", "guard_view", "view"]),
        "implicit-main-catalog": (None, "sqlite_schema", "sqlite_schema", ["catalog", "main"]),
        "implicit-temp-catalog": (None, "sqlite_temp_schema", "sqlite_temp_schema", ["catalog", "temp"]),
        "qualified-temp-catalog": ("temp", "sqlite_schema", "sqlite_schema", ["catalog", "temp"]),
        "qualified-attachment-catalog": (attachment, "sqlite_schema", "sqlite_schema", ["catalog", attachment]),
        "stale-relation": ("main", CHECKED, CHECKED, ["relation", "main", CHECKED, "table"]),
    }


def case(kind="integrity_check", role="implicit-default", temp="unopened", data="check-violations", ignore=False,
         *, child="guard", attachment="extension", number=1, counts=(3, 1, 4)):
    item = {"operation": "integrity-audit", "pragma": kind, "temp": temp == "shadowed", "data": data, "ignore": ignore,
            "child": child, "attachment": attachment, "number": number, "counts": counts}
    schema, lexeme, native, target = selectors(item)[role]
    return {**item, "schema": schema, "lexeme": lexeme, "argument": native, "target": target}


def source(item, *, uppercase=False, parentheses=True):
    schema, lexeme = item["schema"], item["lexeme"]
    name = item["pragma"]
    start = "PRAGMA " + ("" if schema is None else quoted(schema) + ".") + (quoted(name.upper()) if uppercase else name)
    if lexeme is None:
        return start
    return start + (f" ( {lexeme} )" if parentheses else f" = {lexeme}")


def analysis(item, statement, *, complete=True):
    return {"sql": statement, "catalogComplete": complete, "declarations": list(declarations(item)),
            "searchPath": ["main", item["attachment"]]}


def requests(specification):
    finite = specification["finite"]
    for index, axes in enumerate(product(*(finite[field] for field in AXES))):
        item = case(*axes)
        yield f"finite/{index}", analysis(item, source(item)), item
        yield f"direct/{index}", item, item
    for seed in specification["exploration"]["seeds"]:
        rng = Random(seed)
        order = list(finite["selectors"])
        rng.shuffle(order)
        for index in range(specification["exploration"]["casesPerSeed"]):
            role = order[index % len(order)]
            scope = "" if index % 11 == 0 else rng.choice(("Archive", 'extra "db"', "保管")) + str(index)
            child = rng.choice(('guard "q"', "vérification", "監査")) + str(index)
            item = case(rng.choice(finite["kinds"]), role, rng.choice(finite["temp"]), rng.choice(finite["data"]),
                        rng.choice(finite["ignoreCheck"]), child=child, attachment=scope, number=rng.randint(1, 9),
                        counts=tuple(rng.randint(1, 7) for _ in range(3)))
            if role in ("numeric-one", "signed-one"):
                # Preserve the verified prefix while exercising lexical spellings from the fixed grammar.
                spelling = "0" * rng.randint(1, 20) + str(item["number"])
                if index % 4 == 0:
                    spelling = f"0x{spelling}"
                elif index % 4 == 1:
                    spelling += ".9"
                elif index % 4 == 2:
                    spelling += "e-26"
                if role == "signed-one":
                    spelling = "-" + spelling
                item = {**item, "lexeme": spelling, "argument": spelling}
            yield f"seed/{seed}/{index}", analysis(item, source(item, uppercase=index % 2 == 0, parentheses=index % 3 == 0)), item
            yield f"direct/{seed}-{index}", item, item


def negatives():
    item = case()
    for budget in (0, -1, MAXIMUM + 1):
        yield {**item, "target": ["limit", budget]}, "rejected", "must be a positive signed 32-bit integer"
    yield {**item, "schema": "temp", "target": ["relation", "main", "guard", "table"]}, "rejected", "must belong to its explicit schema"
    yield {**item, "schema": "main", "target": ["catalog", "temp"]}, "rejected", "must belong to its explicit schema"
    yield {**item, "target": ["catalog", "extension"]}, "rejected", "cannot select an attached builtin catalog"
    yield {**item, "target": ["relation", "main", "sqlite_schema", "table"]}, "rejected", "requires a catalog role"
    yield analysis(item, "PRAGMA unknown.integrity_check"), "rejected", "requires a known schema qualifier"
    yield analysis(item, "PRAGMA main.integrity_check(missing_table)"), "rejected", UNKNOWN
    yield analysis(item, "PRAGMA quick_check(guard)", complete=False), "rejected", "cannot skip an open relation namespace"


def finite_ids(domain):
    return set(product(*(range(len(domain[key])) for key in AXES)))


def finite_id(measured, domain):
    _reply, observation = measured
    kind, scope, argument = observation["invocation"]
    lookup = selectors(case())
    roles = [role for role, (schema, lexeme, native, _) in lookup.items()
             if (schema, native) == (scope, argument)
             and observation["input"] == source({**case(), "pragma": kind, "schema": schema, "lexeme": lexeme})]
    require(len(roles) == 1, "sqlite-integrity-native-finite-selector", [observation["invocation"], roles])
    data, ignore = observation["fixture"]
    temp = "shadowed" if len(observation["attachedBefore"]) == 3 else "unopened"
    return tuple(domain[key].index(value) for key, value in zip(AXES, (kind, roles[0], temp, data, ignore)))


def forms(measured):
    reply, native = measured
    yield "algorithm/" + reply["pragma"]
    yield "target/" + reply["target"][0]
    if reply["target"][0] == "relation":
        yield "relation/" + reply["target"][3]
    yield "data/" + native["fixture"][0]
    if native["attachedBefore"] != native["attachedAfter"]:
        yield "effect/native-temp-open"
    if reply["schema"] == "":
        yield "scope/empty"
    if '"' in (reply["schema"] or ""):
        yield "scope/quote"
    if len({str(row) for row in native["statement"]["rows"]}) < len(native["statement"]["rows"]):
        yield "result/multiplicity"


def required(domain):
    return ({"target/limit", "target/relation", "target/catalog", "relation/table", "relation/view", "effect/native-temp-open",
             "scope/empty", "scope/quote", "result/multiplicity"} | {"algorithm/" + mode for mode in domain["kinds"]}
            | {"data/" + data for data in domain["data"]})


def expected(item):
    mode, scope, target, attachment = (item[key] for key in ("pragma", "schema", "target", "attachment"))
    direct_limit = target[0] == "limit"
    budget = target[1] if direct_limit else 100
    ids = {"main": 0, "temp": 1, attachment: 2}
    slots = [0, 1, 2] if scope is None else [ids[scope]]
    physical = slots if direct_limit else [ids[target[1]]]
    initial = [(0, "main", ""), (2, attachment, "")]
    if item["temp"]:
        initial.insert(1, (1, "temp", ""))
    final = initial.copy()
    if not item["temp"] and 1 in slots:
        final.insert(1, (1, "temp", ""))
    reports = []
    if item["data"] == "check-violations" and not item["ignore"]:
        for schema, table, count in guards(item):
            if ids[schema] in physical and (direct_limit or target == ["relation", schema, table, "table"]):
                reports.extend([f"CHECK constraint failed in {table}"] * count)
    if item["data"] == "stale-unique-index" and mode == "integrity_check" and 0 in physical:
        if direct_limit or target == ["relation", "main", CHECKED, "table"]:
            reports.append(f"non-unique entry in index {INDEX}")
    statement = {**result((mode,), ((label,) for label in (reports[:budget] or ["ok"]))), "evaluationError": None}
    population = fixture_rows(item)
    return {"statement": statement, "slots": slots, "physical": physical, "budget": budget,
            "attachedBefore": result(rows=initial)["rows"], "attachedAfter": result(rows=final)["rows"],
            "fixture": [item["data"], item["ignore"]], "ignoreAfter": item["ignore"],
            "populationBefore": population, "populationAfter": population}
