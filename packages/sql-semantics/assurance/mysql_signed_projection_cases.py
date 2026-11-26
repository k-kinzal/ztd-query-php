"""Independent signed projection tokens, native headings and finite identities."""

from itertools import product
from random import Random

VALUES = ("-9223372036854775808", "-9223372036854775807", "-2147483649", "-2147483648", "-2147483647",
          "-1", "-0", "0", "1", "2147483647", "2147483648", "9223372036854775807")
PADDING = (0, 1, 31, 32, 33, 68, 255, 256, 512, 4096)
HEADINGS = ("literal_value", "intrinsic")
WRAPPED_VALUES = ("-9223372036854775808", "-2147483649", "-1", "-0", "0", "1", "2147483648", "9223372036854775807")
WRAPPED_PADDING = (0, 33, 256, 4096)
WRAPPERS = {
    "group": "({signed})",
    "trivia": "(  {signed} /* tail */ )",
    "outer-plus": "+ ({signed})",
    "inner-plus": "(+ {signed})",
    "magnitude": "{sign} ({digits})",
    "sign-comment": "{sign}/* middle */{digits}",
    "nested-plus": "+ (((+ ({signed}))))",
}


def grid(contract):
    bounds = contract["finite"]
    yield from product(bounds["spellingValues"], bounds["padding"], bounds["headings"])


def independent_ids():
    return {(value, padding, label) for value in VALUES for padding in PADDING for label in HEADINGS}


def wrapped_grid(contract):
    bounds = contract["finite"]
    yield from product(bounds["wrappedStyles"], bounds["wrappedValues"], bounds["wrappedPadding"], bounds["headings"])


def independent_wrapped_ids():
    return {(wrapper, value, padding, label) for label in HEADINGS for value in WRAPPED_VALUES
            for wrapper in WRAPPERS for padding in WRAPPED_PADDING}


def term(value, padding, heading, sign_spaced=False, explicit_plus=False, wrapper="plain"):
    return [value, padding, heading, sign_spaced, explicit_plus, wrapper]


def case(tag, *columns):
    return {"tag": tag, "columns": list(columns)}


def token(value, padding):
    sign = "-" if value.startswith("-") else ""
    return sign + "0" * padding + value.lstrip("-")


def sql_expression(column):
    value, padding, _, sign_spaced, explicit_plus, wrapper = column
    lexeme = token(value, padding)
    if wrapper != "plain":
        sign = "-" if lexeme.startswith("-") else "+"
        return WRAPPERS[wrapper].format(signed=lexeme, sign=sign, digits=lexeme.lstrip("-"))
    if lexeme.startswith("-") and sign_spaced:
        return "- " + lexeme[1:]
    return ("+ " if explicit_plus else "") + lexeme


def sql_atom(column):
    expression = sql_expression(column)
    label = column[2]
    return expression if label == "intrinsic" else expression + " AS `" + label.replace("`", "``") + "`"


def command(item):
    return "SELECT " + ", ".join(map(sql_atom, item["columns"]))


def label_text(column):
    value, padding, label, *_ = column
    if label != "intrinsic":
        return label
    signed = token(value, padding)
    if signed.startswith("-"):
        return sql_expression(column)[:255]
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


def seeded(tag, seed, count, wrapped=False):
    rng = Random(seed)
    sizes = (*PADDING, 127, 234, 235, 257, 1024, 2048)
    for index in range(count):
        fields = []
        for position in range(1 + index % 4):
            value = rng.choice(VALUES) if index % 3 else str(rng.randint(-(2**63), 2**63 - 1))
            padding = sizes[(index + position) % len(sizes)]
            label = rng.choice(("intrinsic", "same", f"out_{position}"))
            styles = ("plain", *WRAPPERS)
            wrapper = styles[(index + position) % len(styles)] if wrapped else "plain"
            fields.append(term(value, padding, label, value.startswith("-") and bool(index % 2),
                               not value.startswith("-") and bool(index % 2), wrapper))
        yield case(tag, *fields)
