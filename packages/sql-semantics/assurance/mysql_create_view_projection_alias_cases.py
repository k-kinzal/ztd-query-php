"""Independent literal and persistent view observations for view aliases."""

from itertools import product
from random import Random

import mysql_signed_projection_alias_cases as aliases
from run_server_mysql_create_view import MAIN, SCHEMA, TARGET, case as view_case, query

LABELS = {
    "ordinary": "ordinary",
    "trim": "  head",
    "leading-del": "\x7ftail",
    "only-control": " \t\x7f",
    "quoted-delimiters": " a`'b",
    "nbsp": "\u00a0head",
    "ascii64": "n" * 64,
    "bmp64": "漢" * 64,
    "leading64": " " + "n" * 63,
    "intrinsic": None,
}
ATOMS = ("00011", "-2147483649", "-9223372036854775808")
ORDERS = ("source-order", "reverse")
INVALID_ALIASES = {"empty": "", "trailing-space": "name ", "long-ascii": "x" * 65, "long-bmp": "漢" * 65}


def grid(contract):
    bounds = contract["finite"]
    yield from product(bounds["aliasShapes"], bounds["atoms"], bounds["order"])


def independent_ids():
    return {(shape, atom, order) for order in ORDERS for shape in LABELS for atom in ATOMS}


def finite(tag, shape, atom, order):
    columns = [[atom, LABELS[shape], aliases.STYLES[0]], ["57", "reference", aliases.STYLES[1]]]
    if order == "reverse":
        columns.reverse()
    return {"tag": tag, "columns": columns, "fields": ["visible", "label"]}


def structure(item, observed=False):
    columns = [[aliases.wire_expression(token), token if name is None else aliases.wire_label(name, observed)]
               for token, name, _ in item["columns"]]
    return view_case(item["tag"], query(None, columns), item["fields"])


def command(item):
    columns = [token if name is None else token + " AS " + aliases.quote(name, style)
               for token, name, style in item["columns"]]
    return "CREATE VIEW " + TARGET + " (" + ", ".join(item["fields"]) + ") AS SELECT " + ", ".join(columns)


def expected(item):
    tag = item["tag"]
    newer = tag not in ("mysql-5.6.51", "mysql-5.7.44")
    columns, row = [], []
    values = [int(token) for token, _, _ in item["columns"]]
    for value, field in zip(values, item["fields"]):
        native = "LONG" if -(2**31) <= value <= 2**31 - 1 else "LONGLONG"
        width = len(str(abs(value))) + (value < 0 or newer)
        columns.append([field.encode().hex(), native, width, ["not_null"]])
        row.append(value)
    alias_inputs = aliases.case(tag, *((token, name if name is not None else token, style)
                                       for token, name, style in item["columns"]))
    warnings = aliases.expected(alias_inputs)["native-prepared"]["prepareWarnings"]
    result = {"status": "created", "algorithm": "UNDEFINED", "catalog": [[SCHEMA, MAIN, "NONE", "NO", "root@%", "DEFINER", "utf8mb4"]],
              "fields": columns, "rows": [row], "readWarnings": []}
    return {"native-prepared": {**result, "prepareWarnings": warnings, "executeWarnings": []},
            "text": {**result, "prepareWarnings": [], "executeWarnings": warnings}}


def refusal_text(tag, requested):
    prefix = requested.encode()[:100]
    displayed = prefix.decode("utf-8", errors="ignore")
    if len(displayed.encode()) != len(prefix) and tag not in ("mysql-5.6.51", "mysql-5.7.44"):
        displayed += "?"
    return f"Incorrect column name '{displayed}'"


def seeded(tag, seed, count):
    rng = Random(seed)
    options = tuple(LABELS.values())
    for index in range(count):
        width = 2 + index % 3
        repeated = rng.choice((" shared", " \t\x7f", " " + "名" * 63))
        columns = []
        for position in range(width):
            atom = rng.choice((*ATOMS, "1", "2147483648", "9223372036854775807", "-19"))
            alias = repeated if index % 4 == 1 else rng.choice(options)
            columns.append([atom, alias, aliases.STYLES[(index + position) % 2]])
        fields = [f"catalog_{position}" for position in range(width)]
        rng.shuffle(fields)
        yield {"tag": tag, "columns": columns, "fields": fields}
