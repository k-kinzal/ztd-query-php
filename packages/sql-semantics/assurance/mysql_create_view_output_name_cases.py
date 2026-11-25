"""Independent meanings of explicit and inherited MySQL view output names."""

from itertools import product
from random import Random

import mysql_signed_projection_alias_cases as aliases
import mysql_create_view_projection_alias_cases as view
from run_server_mysql_create_view import TARGET, case as view_case, query

LEGACY = ("mysql-5.6.51", "mysql-5.7.44")
NAMES = {
    "case-distinct": ("É", "e"),
    "non-expansion": ("œ", "oe"),
    "unicode-64": ("漢" * 64, "界"),
    "leading-control": (" \t\x7fḄ", "name"),
    "ordinary": ("first", "end"),
    "empty-inherited": (" \t\x7f", "other"),
    "trailing-control-valid": ("edge\x7f", "ends\xa0"),
}
LEGACY_EDGES = {
    "ascii-65": ("a" * 65, "epoch"),
    "utf8-native-256": ("漢" * 85 + "x", "next"),
    "ascii-native-clip": ("Q" * 257, "leaf"),
}
LAYOUTS = ("written", "reverse")
STRATEGIES = ("explicit", "inherited")
ATOMS = ("00011", "-2147483649", "57", "-9223372036854775808")


def grid(contract, tag):
    finite = contract["finite"]
    yield from product(finite["headingShapes"], finite["sources"], finite["order"])
    if tag in LEGACY:
        yield from product(finite["legacyExplicitEdges"], ("explicit",), finite["order"])


def independent_ids(tag):
    ordinary = {(shape, source, layout) for layout in LAYOUTS for source in STRATEGIES for shape in NAMES}
    if tag in LEGACY:
        ordinary.update((shape, "explicit", layout) for shape in LEGACY_EDGES for layout in LAYOUTS)
    return ordinary


def finite(tag, shape, source, order):
    names = (NAMES | LEGACY_EDGES)[shape]
    cells = [[atom, name, f" inner_{index}", aliases.STYLES[0]] for index, (atom, name) in enumerate(zip(ATOMS[:2], names))]
    if order == "reverse":
        cells.reverse()
    return {"tag": tag, "strategy": source, "cells": cells}


def graph_body(item):
    return [[atom, rendered if item["strategy"] == "explicit" else name, style]
            for atom, name, rendered, style in item["cells"]]


def effective(item):
    return [name if item["strategy"] == "explicit" and item["tag"] not in LEGACY else aliases.effective(name)
            for _, name, _, _ in item["cells"]]


def structure(item, observed=False):
    selection = [[aliases.wire_expression(atom), aliases.wire_label(name, observed)] for atom, name, _ in graph_body(item)]
    heading = effective(item)
    if item["strategy"] == "explicit" and item["tag"] in LEGACY:
        heading = [aliases.wire_label(name, observed) for _, name, _, _ in item["cells"]]
    return view_case(item["tag"], query(None, selection), heading)


def command(item):
    body = ", ".join(atom + " AS " + aliases.quote(name, style) for atom, name, style in graph_body(item))
    heading = ""
    if item["strategy"] == "explicit":
        heading = " (" + ", ".join(aliases.quote(name, aliases.STYLES[0]) for _, name, _, _ in item["cells"]) + ")"
    return "CREATE VIEW " + TARGET + heading + " AS SELECT " + body


def expected(item):
    tag = item["tag"]
    names = effective(item)
    decoded_body = graph_body(item)
    baseline = view.expected({"tag": tag, "columns": decoded_body, "fields": names})
    legacy = tag in LEGACY
    declared = []
    if legacy and item["strategy"] == "explicit":
        explicit = aliases.case(tag, *[(atom, name, style) for atom, name, _, style in item["cells"]])
        declared = aliases.expected(explicit)["native-prepared"]["prepareWarnings"]
    catalog = []
    for index, (name, row) in enumerate(zip(names, item["cells"], strict=True), start=1):
        field = name[:64] if legacy else name
        value = int(row[0])
        sql_type = "int" if -(2**31) <= value <= 2**31 - 1 else "bigint"
        catalog.append([field.encode().hex().upper(), index, sql_type, "NO"])
    baseline["native-prepared"]["executeWarnings"] = declared
    baseline["text"]["executeWarnings"] = baseline["text"]["executeWarnings"] + declared
    for record in baseline.values():
        record["catalogColumns"] = catalog
    return baseline


def seeded(tag, seed, count):
    random = Random(seed)
    for index in range(count):
        source = STRATEGIES[index % len(STRATEGIES)]
        cells = []
        for slot in range(random.randint(2, 4)):
            base = f"{random.choice(('aβ', 'أ', 'Ő', 'résultat', '角', 'x`'))}_{slot}"
            name = random.choice(("", " ", "\x7f", " \t")) + base
            if slot == 0 and index % 3 == 0:
                name = " \t\x7f"
            if slot == 1 and source == "explicit" and tag in LEGACY and index % 4 == 1:
                name = random.choice([heading[0] for heading in LEGACY_EDGES.values()])
            body = random.choice(("", " ", "\x7f")) + f"body_{slot}"
            cells.append([random.choice(ATOMS), name, body, random.choice(aliases.STYLES)])
        random.shuffle(cells)
        yield {"tag": tag, "strategy": source, "cells": cells}
