"""Interpret the checked SQLite CAST targets on the observer's scalar values."""

import math
import re

MIN = -(2 ** 63)
MAX = 2 ** 63 - 1
INTEGER_PREFIX = re.compile(r"[+-]?[0-9]+", re.ASCII)
REAL_PREFIX = re.compile(r"[+-]?(?:[0-9]+(?:\.[0-9]*)?|\.[0-9]+)(?:[eE][+-]?[0-9]+)?", re.ASCII)
NUMERIC = {"INTEGER", "REAL", "NUMERIC"}
SPACES = " \t\n\r\v\f"


def text_value(value):
    if type(value) is bytes:
        return value.decode(errors="surrogateescape")
    if type(value) is str:
        return value
    if type(value) is int:
        return str(value)
    assert type(value) is float
    if math.isinf(value):
        return "-Inf" if value < 0 else "Inf"
    if value == 0:
        return "0.0"
    digits, exponent, suffix = format(value, ".15g").partition("e")
    return digits + ("" if "." in digits else ".0") + exponent + suffix


def numeric_value(prefix):
    if not prefix:
        return 0
    if INTEGER_PREFIX.fullmatch(prefix):
        integer = int(prefix)
        return integer if MIN <= integer <= MAX else float(prefix)
    number = float(prefix)
    if -(2 ** 50) <= number < 2 ** 50 and number.is_integer():
        return int(number)
    return number


def convert(target, value):
    if value is None:
        return None
    if target == "TEXT":
        return text_value(value)
    if target == "BLOB":
        return value if type(value) is bytes else text_value(value).encode(errors="surrogateescape")
    if type(value) in (int, float):
        if target == "NUMERIC":
            return value
        if target == "REAL":
            return float(value)
        assert target == "INTEGER"
        return int(max(MIN, min(MAX, value)))
    text = text_value(value).lstrip(SPACES)
    lexer = INTEGER_PREFIX if target == "INTEGER" else REAL_PREFIX
    match = lexer.match(text)
    prefix = match.group() if match is not None else ""
    if target == "INTEGER":
        return max(MIN, min(MAX, int(prefix) if prefix else 0))
    if target == "REAL":
        return float(prefix) if prefix else 0.0
    assert target == "NUMERIC"
    return numeric_value(prefix)


def comparison_values(left, right, a, b):
    left_affinity = left[1] if left[0] == "cast" else None
    right_affinity = right[1] if right[0] == "cast" else None

    def value(own, other, result):
        if other in NUMERIC and own not in NUMERIC and type(result) is str:
            text = result.strip(SPACES)
            if REAL_PREFIX.fullmatch(text):
                return numeric_value(text)
        if other == "TEXT" and own is None and type(result) in (int, float):
            return text_value(result)
        return result

    return value(left_affinity, right_affinity, a), value(right_affinity, left_affinity, b)
