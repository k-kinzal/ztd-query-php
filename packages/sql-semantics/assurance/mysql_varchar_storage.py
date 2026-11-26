"""Native utf8mb4 VARCHAR storage rules used by independent write evaluators."""

import re

from run import require

DIAGNOSTICS = {
    1048: r"Column '([_a-z]+)' cannot be null",
    1062: r"Duplicate entry '(-?[0-9]+)' for key '(?:ir_varchar_insert\.)?PRIMARY'",
    1265: r"Data truncated for column '([_a-z]+)' at row [1-9][0-9]*",
}


def truncated(value, width, fast_copy=False):
    if value is None or len(value) <= width:
        return value, None
    lost_data = any(char not in " \t\n\v\f\r" for char in value[width:])
    return value[:width], "Warning" if fast_copy or lost_data else "Note"


def normalize_diagnostics(raw, fields):
    normalized = []
    for level, code, message in raw:
        match = re.fullmatch(DIAGNOSTICS.get(code, "(?!)"), message)
        require(match is not None and (code == 1062 or match[1] in fields),
                "mysql-varchar-unclassified-diagnostic", [level, code, message])
        normalized.append([level, code, match[1]])
    return sorted(normalized)
