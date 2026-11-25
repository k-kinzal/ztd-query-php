"""Independent ordered bindings and native metadata for explicit CTE alias lists."""

from itertools import product
from random import Random

import mysql_signed_projection_alias_cases as aliases
from run_server_mysql_with_projection import program, read, scan, with_scope

LABELS = {
    "wide": (" " + "漢" * 90, "a" * 257),
    "trim": (" original", " alternate"),
    "empty": ("   ", " next"),
    "duplicate": (" duplicate", " duplicate"),
    "unmodified": ("existing", "next"),
}
PLACEMENTS = ("definition", "nested", "body")
LAYOUTS = {"in-order": (0, 1), "reverse": (1, 0), "repeat": (1, 0, 0)}
ATOMS = ("00011", "-2147483649")
EXTRA_ATOMS = ("1", "-00001", "2147483648", "-9223372036854775808", "008", "-37")
VISIBLE = ("first", "second", "third", "fourth")


def grid(contract):
    finite = contract["finite"]
    yield from product(finite["aliasShapes"], finite["placement"], finite["layout"])


def independent_ids():
    return {(shape, placement, layout) for layout in LAYOUTS for placement in PLACEMENTS for shape in LABELS}


def finite(tag, shape, placement, layout):
    columns = [[token, label, "quoted-identifier"] for token, label in zip(ATOMS, LABELS[shape])]
    return {"tag": tag, "placement": placement, "columns": columns, "order": list(LAYOUTS[layout]), "preceding": 0}


def dummy(tag):
    return aliases.case(tag, ("1", "control", "quoted-identifier"))


def child(item):
    columns = item["columns"]
    if item["placement"] == "body":
        columns = [columns[position] for position in item["order"]]
    return aliases.case(item["tag"], *columns)


def heading_plan(item):
    return [[slot, VISIBLE[pos]] for pos, slot in enumerate(item["order"])]


def values_query(child, observed):
    return scan(None, aliases.allocated(child, observed))


def structure(item, observed=False):
    tag = item["tag"]
    value = values_query(child(item), observed)
    controls = [values_query(dummy(tag), observed) for _ in range(item["preceding"])]
    if item["placement"] == "body":
        return program(tag, [*controls, values_query(dummy(tag), observed)], value)
    if item["placement"] == "nested":
        value = with_scope([values_query(dummy(tag), observed)], value)
    return program(tag, [*controls, value], read(len(controls), heading_plan(item)))


def command(item):
    sql = aliases.command(child(item))
    definitions = [f"unused_{i} (guard) AS (SELECT 1 AS control)" for i in range(item["preceding"])]
    if item["placement"] == "body":
        definitions.append("guarded (guard) AS (SELECT 1 AS control)")
        return "WITH " + ", ".join(definitions) + " " + sql
    if item["placement"] == "nested":
        sql = "WITH inside (guard) AS (SELECT 1 AS control) " + sql
    names = [f"k{index}" for index in range(len(item["columns"]))]
    definitions.append("positions (" + ", ".join(names) + ") AS (" + sql + ")")
    result = [f"positions.{names[slot]} AS {heading}" for slot, heading in heading_plan(item)]
    return "WITH " + ", ".join(definitions) + " SELECT " + ", ".join(result) + " FROM positions"


def expected(item):
    observation = aliases.expected(child(item))
    if item["placement"] == "body":
        return observation
    for record in observation.values():
        fields, row = [], []
        for slot, heading in heading_plan(item):
            _, _, flags, width = record["fields"][slot]
            value = record["rows"][0][slot]
            native = "LONG" if -(2**31) <= value <= 2**31 - 1 else "LONGLONG"
            fields.append([heading.encode().hex(), native, flags, width])
            row.append(value)
        record["fields"] = fields
        record["rows"] = [row]
    return observation


def seeded(tag, seed, count):
    rng = Random(seed)
    names = [name for combination in LABELS.values() for name in combination]
    for index in range(count):
        width = 2 + index % 3
        columns = [[rng.choice(EXTRA_ATOMS), rng.choice(names), aliases.STYLES[rng.randrange(2)]] for _ in range(width)]
        order = list(range(width))
        rng.shuffle(order)
        if width < 4 and index % 2 == 1:
            order.append(rng.randrange(width))
        yield {"tag": tag, "placement": PLACEMENTS[index % len(PLACEMENTS)], "columns": columns,
               "order": order, "preceding": rng.randrange(3)}
