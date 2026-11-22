"""Independent SQLite quotient, remainder and signed bit operations."""

import math

from cases import MAX, MIN, numeric
from sqlite_cast_semantics import convert

NUMERIC_OPERATORS = frozenset(("/", "%", "&", "|", "<<", ">>"))
BITS = 64
MODULUS = 1 << BITS


def quotient(left, right):
    magnitude = abs(left) // abs(right)
    return -magnitude if (left < 0) != (right < 0) else magnitude


def signed_word(value):
    return (value - MIN) % MODULUS + MIN


def shift(operator, value, distance):
    left_shift = (operator == "<<") == (distance >= 0)
    distance = min(abs(distance), BITS)
    return signed_word(value * (1 << distance)) if left_shift else value // (1 << distance)


def evaluate_numeric(operator, left, right):
    if left is None or right is None:
        return None
    if operator in ("/", "%"):
        a, b = numeric(left), numeric(right)
        integer = type(a) is int and type(b) is int
        if operator == "/":
            if b == 0:
                return None
            result = quotient(a, b) if integer else float(a) / float(b)
            if integer and result > MAX:
                return float(result)
            return None if type(result) is float and math.isnan(result) else result
        p, q = convert("INTEGER", left), convert("INTEGER", right)
        if q == 0:
            return None
        result = p - q * quotient(p, q)
        return result if integer else float(result)
    p, q = convert("INTEGER", left), convert("INTEGER", right)
    if operator == "&":
        return p & q
    if operator == "|":
        return p | q
    assert operator in ("<<", ">>")
    return shift(operator, p, q)
