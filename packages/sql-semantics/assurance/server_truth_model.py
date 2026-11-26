"""Independent three-valued interpretation of checked native predicates."""

from itertools import product
import operator

from evidence_transcript import canonical

COMPARISONS = {
    "=": operator.eq, "<>": operator.ne, "<": operator.lt, "<=": operator.le,
    ">": operator.gt, ">=": operator.ge,
}

TRUTH = (False, True, None)
TABLES = {
    "AND": ((False, False, False), (False, True, None), (False, None, None)),
    "OR": ((False, True, None), (True, True, True), (None, True, None)),
    "XOR": ((False, True, None), (True, False, None), (None, None, None)),
}


def predicate_value(condition, operand):
    role = condition[0]
    if role == "boolean":
        return condition[1]
    if role == "null":
        return None
    if role in ("slot", "text-slot", "bool-slot", "wide-slot", "integer"):
        return operand(condition)
    if role == "comparison":
        _, symbol, left, right = condition
        lhs, rhs = operand(left), operand(right)
        return None if lhs is None or rhs is None else COMPARISONS[symbol](lhs, rhs)
    if role == "text-comparison":
        _, symbol, collation, left, right = condition
        if collation not in ("C", "utf8mb4_bin"):
            raise ValueError(f"Unregistered text comparison collation {collation}")
        lhs, rhs = operand(left), operand(right)
        if lhs is None or rhs is None:
            return lhs is rhs if symbol == "<=>" else None
        if collation == "utf8mb4_bin":
            width = max(len(lhs), len(rhs))
            lhs, rhs = lhs.ljust(width, " "), rhs.ljust(width, " ")
        relation = COMPARISONS["=" if symbol == "<=>" else symbol]
        return relation(lhs.encode("utf-8"), rhs.encode("utf-8"))
    if role == "truth-test":
        _, mode, tested = condition
        value = predicate_value(tested, operand)
        required = {"TRUE": True, "FALSE": False, "UNKNOWN": None}
        parts = mode.split()
        matches = value is required[parts[-1]]
        return not matches if len(parts) == 3 else matches
    if role == "int4-distinctness":
        _, null_safe_equality, left, right = condition
        same = operand(left) == operand(right)
        return same if null_safe_equality else not same
    if role in ("int4-range", "signed-range"):
        negated = condition[1]
        symmetric = role == "int4-range" and condition[2]
        subject, lower, upper = condition[-3:]
        subject, lower, upper = map(operand, (subject, lower, upper))

        def interval(first, last):
            above = None if subject is None or first is None else subject >= first
            below = None if subject is None or last is None else subject <= last
            return TABLES["AND"][TRUTH.index(above)][TRUTH.index(below)]

        value = interval(lower, upper)
        if symmetric:
            value = TABLES["OR"][TRUTH.index(value)][TRUTH.index(interval(upper, lower))]
        return (True, False, None)[TRUTH.index(value)] if negated else value
    if role == "null-test":
        _, negated, tested = condition
        return (predicate_value(tested, operand) is None) != negated
    if role == "logic":
        _, symbol, left, right = condition
        lhs = predicate_value(left, operand)
        if symbol == "NOT":
            return (True, False, None)[TRUTH.index(lhs)]
        rhs = predicate_value(right, operand)
        return TABLES[symbol][TRUTH.index(lhs)][TRUTH.index(rhs)]
    raise ValueError(f"Unregistered predicate {role}")


def admits(condition, operand):
    return condition is None or predicate_value(condition, operand) is True


def pad_space_distinct_rows(projected):
    """Enumerate the stored whole-row representatives of each PAD SPACE class."""
    classes = {}
    for values in projected:
        row = tuple(values)
        key = tuple(value.rstrip(" ") if type(value) is str else value for value in row)
        classes.setdefault(key, set()).add(row)
    return product(*(sorted(rows, key=canonical) for rows in classes.values()))
