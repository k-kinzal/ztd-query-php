"""Independent three-valued interpretation of checked native predicates."""

import operator

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
    if role in ("slot", "integer"):
        return operand(condition)
    if role == "comparison":
        _, symbol, left, right = condition
        lhs, rhs = operand(left), operand(right)
        return None if lhs is None or rhs is None else COMPARISONS[symbol](lhs, rhs)
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
