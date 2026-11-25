"""Independent signed projection tokens, native headings and finite identities."""

from itertools import product
from random import Random

VALUES = ("-9223372036854775808", "-9223372036854775807", "-2147483649", "-2147483648", "-2147483647",
          "-1", "-0", "0", "1", "2147483647", "2147483648", "9223372036854775807")
PADDING = (0, 1, 31, 32, 33, 68, 255, 256, 512, 4096)
HEADINGS = ("literal_value", "intrinsic")


def grid(contract):
    bounds = contract["finite"]
    yield from product(bounds["spellingValues"], bounds["padding"], bounds["headings"])


def independent_ids():
    return {(value, padding, label) for value in VALUES for padding in PADDING for label in HEADINGS}


def term(value, padding, heading, sign_spaced=False, explicit_plus=False):
    return [value, padding, heading, sign_spaced, explicit_plus]


def case(tag, *columns):
    return {"tag": tag, "columns": list(columns)}


def token(value, padding):
    sign = "-" if value.startswith("-") else ""
    return sign + "0" * padding + value.lstrip("-")


def sql_atom(column):
    value, padding, label, sign_spaced, explicit_plus = column
    lexeme = token(value, padding)
    if lexeme.startswith("-") and sign_spaced:
        lexeme = "- " + lexeme[1:]
    elif explicit_plus:
        lexeme = "+ " + lexeme
    if label != "intrinsic":
        lexeme += " AS `" + label.replace("`", "``") + "`"
    return lexeme


def command(item):
    return "SELECT " + ", ".join(map(sql_atom, item["columns"]))


def label_text(column):
    value, padding, label, sign_spaced, _ = column
    if label != "intrinsic":
        return label
    signed = token(value, padding)
    if signed.startswith("-"):
        return (("- " + signed[1:]) if sign_spaced else signed)[:255]
    return signed


def allocated(column):
    value, padding, label, *_ = column
    signed = token(value, padding)
    canonical = str(int(value))
    if -(2**31) <= int(value) < 2**31 and padding <= 32:
        value = ["integer", canonical, padding, value == "-0"] if padding or value == "-0" else ["integer", canonical]
    else:
        value = ["projected-integer", signed]
    label = label_text(column)
    return [value, ["intrinsic-integer-name", label] if len(label) > 256 else label]


def direct(item):
    return {
        "dialect": "mysql", "release": item["tag"], "source": None,
        "columns": [allocated(column) for column in item["columns"]],
        "orderBy": [], "limit": None,
    }


def analyze(item):
    return {"dialect": "mysql", "release": item["tag"], "sql": command(item)}


def physical(column):
    value, label = column
    if value[0] == "projected-integer":
        digits = value[1]
    elif value[0] == "integer":
        _, decimal, *attributes = value
        padding, negative_zero = attributes or (0, False)
        digits = token("-0" if negative_zero else decimal, padding)
    else:
        raise ValueError(value)
    canonical = str(int(digits)) if int(digits) or not digits.startswith("-") else "-0"
    return canonical, len(digits) - len(canonical), "literal_value" if label == "literal_value" else "intrinsic"


def expected(item):
    fields, values = [], []
    legacy = item["tag"] in ("mysql-5.6.51", "mysql-5.7.44")
    for column in item["columns"]:
        value, padding, *_ = column
        digits = len(value.lstrip("-")) + padding
        width = digits + (not legacy or value.startswith("-"))
        fields.append([label_text(column), "LONGLONG", ["not_null"], width])
        values.append(int(value))
    return {"status": "rows", "fields": fields, "rows": [values], "warnings": []}


def seeded(tag, seed, count):
    rng = Random(seed)
    sizes = (*PADDING, 127, 234, 235, 257, 1024, 2048)
    for index in range(count):
        fields = []
        for position in range(1 + index % 4):
            value = rng.choice(VALUES) if index % 3 else str(rng.randint(-(2**63), 2**63 - 1))
            padding = sizes[(index + position) % len(sizes)]
            label = rng.choice(("intrinsic", "same", f"out_{position}"))
            fields.append(term(value, padding, label, value.startswith("-") and bool(index % 2),
                               not value.startswith("-") and bool(index % 2)))
        yield case(tag, *fields)
