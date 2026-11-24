"""Independent comparison spelling, native name inheritance and rename effects for appended CHECKs."""

from itertools import product
from random import Random

from run_sqlite_create_table import quoted
from sqlite_appended_checks_cases import (
    after_writes, case as named_case, compare, entry, expected as named_transition, prior_definitions,
)
from sqlite_generated_column_cases import column, integer
from sqlite_implicit_check_cases import SYMBOLS

AXES = ("schemas", "columnForms", "population", "referenceSlots", "layouts", "operators", "sequences")
BARE = {"quoted": False, "before": " ", "after": "", "zeroes": 2, "value": "0"}
DELIMITED = {"quoted": True, "before": "\t", "after": " ", "zeroes": 1, "value": "2"}


def case(schema="main", form="ordinary-two", population="null", role="prior", layout=None, operator="=",
         sequence="implicit", *, heading=("id", "operand"), field="checked", strict=False, prior_slot=1,
         spelled=None):
    item = named_case(schema, form, strict=strict, population=population, heading=heading, field=field)
    owner = len(heading)
    slot = prior_slot if role == "prior" else owner
    item["renameSlot"] = slot
    value = BARE if layout is None else layout
    name = spelled if spelled is not None else [*heading, field][slot]
    label = {"name": name, "glyph": operator, **{key: member for key, member in value.items() if key != "value"}}
    item["checks"] = [entry(label, owner, compare(SYMBOLS[operator], column(slot), integer(value["value"])))]
    if sequence == "implicit-then-named":
        item["checks"].append(entry("ceiling", owner, compare("<", column(1), integer(8))))
    return item


def finite(domain):
    for fields in product(*(domain[key] for key in AXES)):
        yield case(*fields)


def finite_ids(domain):
    return set(product(*(range(len(domain[key])) for key in AXES)))


def finite_id(measured, domain):
    response, observation = measured
    schema = response["schema"]
    before = observation["addition"]["before"][schema]
    field = response["column"]
    form = field[5][0].lower() if len(field) == 6 else "ordinary-two"
    inputs = before["rows"]
    population = "empty" if not inputs else {None: "null", "0": "below", "7": "mixed"}[inputs[-1][1][1]]
    implicit = response["checks"][0]
    slot = "appended" if implicit["condition"][2][1] == len(before["fields"]) else "prior"
    label = implicit["label"]
    layout = {key: label[key] for key in ("quoted", "before", "after", "zeroes")}
    layout["value"] = implicit["condition"][3][1]
    sequence = "implicit" if len(response["checks"]) == 1 else "implicit-then-named"
    coordinates = (schema, form, population, slot, layout, label["glyph"], sequence)
    return tuple(domain[key].index(value) for key, value in zip(AXES, coordinates))


def explored(domain, seed, amount):
    rng = Random(seed)
    for i in range(amount):
        width = 2 + i % 6
        prior_slot = 1 + i % (width - 1)
        headings = tuple(f'f{slot}_写' if i % 4 < 2 else f'f {slot}"写' for slot in range(width))
        field = 'Checked' if i % 4 < 2 else ("", "检查")[i % 2]
        quotes = i % 4 >= 2
        reference = domain["referenceSlots"][i % 2]
        local_name = headings[prior_slot] if reference == "prior" else field
        layout = {
            "quoted": quotes,
            "before": ("", " ", "\t", "\n", " " * 128)[i % 5],
            "after": ("", " ", "\t", "\r", " " * 128)[i % 5],
            "zeroes": (0, 32, rng.randrange(33))[i % 3],
            "value": ("0", "1", "2", "7", "9223372036854775807")[i % 5],
        }
        yield case(domain["schemas"][i % 2], rng.choice(domain["columnForms"]), domain["population"][i % 4],
                   reference, layout, domain["operators"][i % 8], domain["sequences"][(i // 2) % 2],
                   heading=headings, field=field, strict=(i % 3 == 1), prior_slot=prior_slot,
                   spelled=local_name.upper() if i % 5 == 0 else local_name)


def authored():
    table = "ir_add_checks"
    yield f"ALTER TABLE main.{table} ADD checked INTEGER DEFAULT 2 CHECK (Operand =000)", case(spelled="Operand")
    yield f"ALTER TABLE temp.{table} ADD checked INTEGER DEFAULT 2 CHECK ([CHECKED]\t>= 02)", case(
        "temp", role="appended", layout=DELIMITED, operator=">=", spelled="CHECKED")
    yield f"ALTER TABLE main.{table} ADD checked INTEGER AS (operand+1) VIRTUAL CHECK (`checked`\t!= 02)", case(
        form="virtual", layout=DELIMITED, role="appended", operator="!=")
    yield f"ALTER TABLE main.{table} ADD checked INTEGER AS (operand+1) STORED CHECK (operand <>000)", case(
        form="stored", population="empty", operator="<>")
    yield f"ALTER TABLE temp.{table} ADD checked INTEGER AS (operand+1) STORED CHECK (operand >000)", case(
        "temp", form="stored", population="mixed", operator=">")
    yield f"ALTER TABLE temp.{table} ADD checked INTEGER DEFAULT 2 CHECK (operand =000) CONSTRAINT ceiling CHECK (operand < 8)", case(
        "temp", sequence="implicit-then-named")
    yield f"ALTER TABLE main.{table} ADD checked INTEGER CONSTRAINT marked CHECK (0) CHECK (operand =000)", inherited()
    yield f"ALTER TABLE temp.{table} ADD checked INTEGER AS (operand+1) VIRTUAL CONSTRAINT marked CHECK (1) CHECK (operand =000)", inherited(
        schema="temp", generated=True)
    yield f'''ALTER TABLE main.{table} ADD "" INTEGER DEFAULT 2 CHECK (""\t<> 02)''', case(
        field="", role="appended", layout=DELIMITED, operator="<>")
    yield f"ALTER TABLE temp.{table} ADD checked INTEGER DEFAULT 2 CHECK (operand =000) CONSTRAINT ceiling CHECK ((?007 + ?7) NOT IN ())", folded()


def inherited(schema="main", generated=False):
    value = case(schema, "virtual" if generated else "ordinary-two", population="empty")
    if not generated:
        value["column"][3] = None
    unnamed = value["checks"][0]
    unnamed["label"] = "marked"
    value["checks"].insert(0, entry("marked", unnamed["column"], integer(1 if generated else 0)))
    return value


def folded():
    value = case("temp", population="empty", sequence="implicit-then-named")
    value["checks"][1]["condition"] = ["folded-membership", True, ["007", "7"]]
    return value


def inherited_request(item):
    sql, _ = tuple(authored())[7]
    return {"sql": sql, "declarations": prior_definitions(item)}


def requests(contract):
    domain = contract["finite"]
    for index, item in enumerate(finite(domain)):
        yield f"finite/{index}", item, item
    for seed in contract["exploration"]["seeds"]:
        for index, item in enumerate(explored(domain, seed, contract["exploration"]["casesPerSeed"])):
            yield f"seed/{seed}/{index}", item, item
    for index, (sql, item) in enumerate(authored()):
        yield f"analyze/{index}", {"sql": sql, "declarations": prior_definitions(item)}, item


def invalid_binding():
    return case(spelled="checked")


def invalid_order():
    request = case()
    request["checks"].insert(0, entry("named", 2, integer(1)))
    return request


def negatives():
    bad_owner = case()
    bad_owner["checks"][0]["column"] = 0
    yield bad_owner, "rejected", "belong to the new column slot"
    missing = case()
    missing["checks"][0]["condition"][2] = column(3)
    yield missing, "rejected", "must identify the resolved column"
    yield invalid_binding(), "rejected", "must identify the resolved column"
    reversed_operation = case()
    reversed_operation["checks"][0]["condition"][1] = ">"
    yield reversed_operation, "rejected", "must present its resolved column comparison"
    yield invalid_order(), "rejected", "cannot follow a named CHECK"
    yield {"operation": "create-table", "schema": "main", "table": "inline",
           "columns": [["id", "INTEGER", False], ["operand", "INTEGER", False], ["checked", "INTEGER", False]],
           "ifNotExists": False, "checks": invalid_order()["checks"]}, "rejected", "cannot follow a named CHECK"
    declarations = prior_definitions(case())
    for expression, status, reason in (
        ("operand > 0 AND operand < 9", "rejected", "checked implicit comparison"),
        ("unresolved > 0", "analysis-rejected", "Column unresolved does not exist"),
        ("operand > ?7", "analysis-rejected", "Parameters are prohibited in CHECK"),
    ):
        yield {"sql": f"ALTER TABLE main.ir_add_checks ADD checked INTEGER CHECK ({expression})",
               "declarations": declarations}, status, reason


def display(item, change=None):
    label = item["label"]
    if isinstance(label, str):
        return label
    slot = item["condition"][2][1]
    changed = change is not None and slot == change[0]
    member = change[1] if changed else label["name"]
    if label["quoted"] or (changed and change[2]):
        return member
    decimal = item["condition"][3][1]
    return f"{member}{label['before']}{label['glyph']}{label['after']}{'0' * label['zeroes']}{decimal}"


def rewrites(item):
    return (("bare", (item["renameSlot"], "rename_member", False)),
            ("delimited", (item["renameSlot"], 'new "member"', True)))


def expected(item):
    result = named_transition(item, display)
    installed = result["statement"]["evaluationError"] is None
    return {"addition": result,
            "rewrites": {kind: after_writes(item, display, targets) if installed else None for kind, targets in rewrites(item)}}


def forms(measured):
    value, observed = measured
    before = observed["addition"]["before"][value["schema"]]
    yield f"schema/{value['schema']}"
    yield f"prior-width/{len(before['fields'])}"
    yield f"strict/{before['strict']}"
    yield f"outcome/{observed['addition']['statement']['evaluationError'] is None}"
    yield f"kind/{value['column'][5][0] if len(value['column']) == 6 else 'ORDINARY'}"
    yield f"checks/{len(value['checks'])}"
    check = value["checks"][0]
    label = check["label"]
    yield f"quoted/{label['quoted']}"
    yield f"operator/{label['glyph']}"
    yield f"target/{'appended' if check['condition'][2][1] == len(before['fields']) else 'prior'}"
    if len(label["before"]) == len(label["after"]) == 128:
        yield "boundary/128-space"
    if label["zeroes"] in (0, 32):
        yield f"boundary/{label['zeroes']}-zeroes"
    if check["condition"][3][1] == "9223372036854775807":
        yield "boundary/max-int"


def required(_domain):
    return {"schema/main", "schema/temp", "strict/True", "strict/False", "outcome/True", "outcome/False",
            "kind/ORDINARY", "kind/VIRTUAL", "kind/STORED", "checks/1", "checks/2", "quoted/True", "quoted/False",
            "target/prior", "target/appended", "boundary/128-space", "boundary/0-zeroes", "boundary/32-zeroes",
            "boundary/max-int", *(f"operator/{glyph}" for glyph in SYMBOLS), *(f"prior-width/{width}" for width in range(2, 8))}
