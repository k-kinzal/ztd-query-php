"""Fixed PostgreSQL conflict-arbiter observations for registered Boolean forms."""

_P = [2, "column"]
_Q = [3, "column"]
_OR = ["OR", _P, _Q]
_AND = ["AND", _P, _Q]
_MIXED = ["AND", _P, [3, "IS NOT FALSE"]]

EXPRESSIONS = {
    "p": _P,
    "NOT p": ["NOT", _P, None],
    "p IS TRUE": [2, "IS TRUE"],
    "p IS FALSE": [2, "IS FALSE"],
    "p OR q": _OR,
    "p AND q": _AND,
    "q AND p": ["AND", _Q, _P],
    "NOT (p OR q)": ["NOT", _OR, None],
    "NOT (p AND q)": ["NOT", _AND, None],
    "p AND q IS NOT FALSE": _MIXED,
}

_INFERRED = {
    "p": {"p", "p AND q", "q AND p", "p AND q IS NOT FALSE"},
    "NOT p": {"NOT p"},
    "p IS TRUE": {"p IS TRUE"},
    "p IS FALSE": {"p IS FALSE"},
    "p OR q": {"p", "p OR q", "p AND q", "q AND p", "p AND q IS NOT FALSE"},
    "p AND q": {"p AND q", "q AND p"},
    "NOT (p OR q)": set(),
    "NOT (p AND q)": {"NOT p"},
    "p AND q IS NOT FALSE": {"p AND q IS NOT FALSE"},
}


def infer_conflict(index, requested, included):
    """The fixture has one unique index and inserts a row with the same key."""
    if requested not in _INFERRED[index]:
        return {"sqlState": "42P10", "insertCount": None}
    return {"sqlState": "00000", "insertCount": 0 if included else 1}
