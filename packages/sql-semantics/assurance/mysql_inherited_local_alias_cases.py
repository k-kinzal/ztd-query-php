"""Independent ASCII local names, lexical scopes and positional observations."""

from itertools import product
from random import Random

import mysql_derived_projection_alias_cases as derived
import mysql_signed_projection_alias_cases as aliases
from mysql_with_projection_alias_cases import LAYOUTS, dummy, values_query
from run_server_mysql_with_projection import program, read, scan, with_scope

NAMES = {
    "plain": (("First", "Next"), ("First", "Next")),
    "trim": (("  First", " Next"), ("First", "Next")),
    "control": (("\x7f\tfirst\t", "\x0c\nnext "), ("first\t", "next ")),
    "delimiter": (("  a`'b", "  c`d"), ("a`'b", "c`d")),
    "wide-ascii": ((" " + "a" * 270, " next"), ("a" * 256, "next")),
    "empty": ((" \t\x7f", "  next"), ("", "next")),
}
CTE = ("definition", "nested-definition")
PLACEMENTS = (*derived.PLACEMENTS, *CTE)
STYLE = "quoted-identifier"


def grid(contract):
    finite = contract["finite"]
    yield from product(finite["aliasShapes"], finite["placement"], finite["layout"])


def independent_ids():
    return {(shape, place, order) for order in LAYOUTS for place in PLACEMENTS for shape in NAMES}


def finite(tag, shape, place, layout):
    requested, visible = NAMES[shape]
    if place == "join":
        sources = [aliases.case(tag, *zip(atoms, names, (STYLE, STYLE)))
                   for atoms, names in ((derived.NARROW[:2], requested), (derived.NARROW[2:], requested[::-1]))]
        exposed, order = [list(visible), list(visible[::-1])], derived.JOIN_LAYOUTS[layout]
    else:
        sources = [aliases.case(tag, *zip(derived.ATOMS, requested, (STYLE, STYLE)))]
        exposed, order = [list(visible)], tuple((0, slot) for slot in LAYOUTS[layout])
    return {"tag": tag, "placement": place, "sources": sources, "exposed": exposed,
            "order": [list(binding) for binding in order], "natural": layout == "repeat"}


def source_names(item, side):
    if item["placement"] == "nested":
        return [name for _, name in derived.nested_names(len(item["exposed"][side]))]
    return item["exposed"][side]


def reads(item):
    for position, (side, slot) in enumerate(item["order"]):
        name = source_names(item, side)[slot]
        written = name.swapcase() if position % 2 == 0 else name
        inherited = name if item["placement"] == "nested" else written
        yield side, slot, written, inherited if item["natural"] and position == 0 else f"out_{position}"


def structure(item, observed=False):
    profile = {"dialect": "mysql", "release": item["tag"]}
    inputs = [values_query(child, observed) for child in item["sources"]]
    local_columns = [[slot, output] for _, slot, _, output in reads(item)]
    place = item["placement"]
    if place == "join":
        left, right = inputs if observed else [member | profile for member in inputs]
        source = {"kind": "CROSS", "left": {"derived": left}, "right": {"derived": right}, "on": None}
        columns = [[["slot", slot, side] if side else ["slot", slot], output] for side, slot, _, output in reads(item)]
        return profile | scan(None, columns, source=source)
    child = inputs[0]
    if place == "with-local" or place == "nested-definition":
        child = with_scope([values_query(dummy(item["tag"]), observed)], child)
    if place in CTE:
        return program(item["tag"], [child], read(0, local_columns))
    if place == "nested":
        child = derived.local(child, derived.nested_names(len(item["exposed"][0])))
    return profile | derived.local(child, local_columns)


def quote(value):
    return aliases.quote(value, STYLE)


def command(item):
    sources = []
    place = item["placement"]
    for side, child in enumerate(item["sources"]):
        body = aliases.command(child)
        name = f"d{side}"
        if place == "with-local" or place == "nested-definition":
            body = "WITH inner_d(n) AS (SELECT 1 AS control) " + body
        source = f"({body}) AS {name}"
        if place == "nested":
            layout = derived.nested_names(len(item["exposed"][side]))
            fields = ", ".join(f"{name}.{quote(item['exposed'][side][slot])} AS {label}" for slot, label in layout)
            source = f"(SELECT {fields} FROM {source}) AS {name}"
        sources.append(source)
    columns = [f"d{side}.{quote(written)}" + ("" if item["natural"] and index == 0 else " AS " + quote(output))
               for index, (side, _, written, output) in enumerate(reads(item))]
    projection = "SELECT " + ", ".join(columns)
    if place in CTE:
        body = sources[0].removesuffix(" AS d0")
        return "WITH d0 AS " + body + " " + projection + " FROM d0"
    return projection + " FROM " + " CROSS JOIN ".join(sources)


def expected(item):
    result = derived.expected(item)
    labels = [output for _, _, _, output in reads(item)]
    for outcome in result.values():
        for field, label in zip(outcome["fields"], labels, strict=True):
            field[0] = label.encode().hex()
    return result


def seeded(tag, seed, count):
    rng = Random(seed)
    places = list(PLACEMENTS)
    rng.shuffle(places)
    for index in range(count):
        place = places[index % len(places)]
        atoms = derived.NARROW if place == "join" else (*derived.NARROW, *derived.LARGE)
        sources, exposed, order = [], [], []
        for side in range(2 if place == "join" else 1):
            names, columns = [], []
            for slot in range(rng.randint(2, 4)):
                stem = f"{rng.choice(('left', 'VALUe', 'a `b', 'signed'))}_{slot}"
                requested = rng.choice(("", "  ", "\x7f", "\t ")) + stem
                names.append(stem)
                columns.append([rng.choice(atoms), requested, rng.choice(aliases.STYLES)])
                order.append([side, slot])
            exposed.append(names)
            sources.append(aliases.case(tag, *columns))
        rng.shuffle(order)
        if index % 3 == 1:
            order.append(list(order[0]))
        yield {"tag": tag, "placement": place, "sources": sources, "exposed": exposed,
               "order": order, "natural": index % 2 == 0}
