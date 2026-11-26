"""Independent native output and occurrence bindings for derived alias lists."""

from itertools import product
from random import Random

import mysql_signed_projection_alias_cases as aliases
from mysql_with_projection_alias_cases import LABELS, LAYOUTS, dummy, values_query
from run_server_mysql_with_projection import scan, with_scope

PLACEMENTS = ("local", "nested", "with-local", "join")
JOIN_LAYOUTS = {
    "in-order": ((0, 0), (0, 1), (1, 0), (1, 1)),
    "reverse": ((1, 1), (0, 1), (1, 0), (0, 0)),
    "repeat": ((1, 0), (0, 1), (1, 0), (0, 0)),
}
ATOMS = ("00011", "-2147483649")
NARROW = ("00011", "-7", "21", "33")
LARGE = ("2147483648", "-9223372036854775808", "9223372036854775807")


def grid(contract):
    finite = contract["finite"]
    yield from product(finite["aliasShapes"], finite["placement"], finite["layout"])


def independent_ids():
    return {(alias, placement, layout) for layout in LAYOUTS for alias in LABELS for placement in PLACEMENTS}


def finite(tag, alias, placement, layout):
    atom = NARROW[:2] if placement == "join" else ATOMS
    primary = aliases.case(tag, *zip(atom, LABELS[alias], [aliases.STYLES[0]] * 2))
    sources = [primary]
    if placement == "join":
        sources.append(aliases.case(tag, *zip(NARROW[2:], reversed(LABELS[alias]), [aliases.STYLES[1]] * 2)))
        order = JOIN_LAYOUTS[layout]
    else:
        order = [(0, slot) for slot in LAYOUTS[layout]]
    return {"tag": tag, "placement": placement, "sources": sources, "order": [list(binding) for binding in order]}


def read_positions(item):
    return [[occurrence, slot, f"out_{index}"] for index, (occurrence, slot) in enumerate(item["order"])]


def local(definition, columns):
    return {"local": {"definition": definition, "columns": columns}}


def nested_names(width):
    return [[slot, f"n{slot}"] for slot in reversed(range(width))]


def structure(item, observed=False):
    profile = {"dialect": "mysql", "release": item["tag"]}
    positions = read_positions(item)
    inputs = [values_query(child, observed) for child in item["sources"]]
    if item["placement"] == "join":
        left, right = inputs if observed else [value | profile for value in inputs]
        sources = {"kind": "CROSS", "left": {"derived": left}, "right": {"derived": right}, "on": None}
        projected = [[["slot", slot, occurrence] if occurrence else ["slot", slot], label]
                     for occurrence, slot, label in positions]
        return profile | scan(None, projected, source=sources)
    child = inputs[0]
    if item["placement"] == "nested":
        child = local(child, nested_names(len(item["sources"][0]["columns"])))
    if item["placement"] == "with-local":
        child = with_scope([values_query(dummy(item["tag"]), observed)], child)
    return profile | local(child, [[slot, label] for _, slot, label in positions])


def command(item):
    inputs = []
    for index, child in enumerate(item["sources"]):
        name = f"d{index}"
        names = [f"k{slot}" for slot in range(len(child["columns"]))]
        sql = aliases.command(child)
        if item["placement"] == "with-local":
            sql = "WITH dummy(n) AS (SELECT 1 AS control) " + sql
        source = "(" + sql + ") AS " + name + " (" + ", ".join(names) + ")"
        if item["placement"] == "nested":
            projected = ", ".join(f"{name}.{names[slot]} AS {heading}" for slot, heading in nested_names(len(names)))
            names = [heading for _, heading in nested_names(len(names))]
            source = "(SELECT " + projected + " FROM " + source + ") AS " + name
        inputs.append((source, names))
    projection = ", ".join(f"d{occurrence}.{inputs[occurrence][1][slot]} AS {heading}"
                           for occurrence, slot, heading in read_positions(item))
    return "SELECT " + projection + " FROM " + " CROSS JOIN ".join(sql for sql, _ in inputs)


def expected(item):
    operands = item["sources"]
    widths = [len(child["columns"]) for child in operands]
    total = aliases.case(item["tag"], *(column for child in operands for column in child["columns"]))
    observations = aliases.expected(total)
    for outcome in observations.values():
        fields, row = [], []
        for occurrence, slot, heading in read_positions(item):
            if item["placement"] == "nested":
                slot = widths[occurrence] - 1 - slot
            index = sum(widths[:occurrence]) + slot
            _, _, flags, width = outcome["fields"][index]
            value = outcome["rows"][0][index]
            native = "LONG" if -(2**31) <= value <= 2**31 - 1 else "LONGLONG"
            fields.append([heading.encode().hex(), native, flags, width])
            row.append(value)
        outcome["fields"] = fields
        outcome["rows"] = [row]
    return observations


def seeded(tag, seed, count):
    rng = Random(seed)
    labels = sorted({label for combination in LABELS.values() for label in combination})
    for index in range(count):
        placement = PLACEMENTS[index % len(PLACEMENTS)]
        atom = NARROW if placement == "join" else (*NARROW, *LARGE)
        sources, positions = [], []
        for side in range(2 if placement == "join" else 1):
            width = rng.randint(2, 4)
            columns = [[rng.choice(atom), rng.choice(labels), rng.choice(aliases.STYLES)] for _ in range(width)]
            sources.append(aliases.case(tag, *columns))
            positions.extend([side, slot] for slot in range(width))
        rng.shuffle(positions)
        if index % 3 == 1:
            positions.insert(1, list(positions[0]))
        yield {"tag": tag, "placement": placement, "sources": sources, "order": positions}
