"""Bounded physical comparison and stored-row oracle for foreign-key field layouts."""

from datetime import date, datetime, timedelta, timezone
from decimal import Decimal
import struct

import mysql_foreign_key_scalar_cases as cases

ORIGIN = datetime(2020, 1, 2, 3, 4, 5, tzinfo=timezone.utc)
NOT_FOUND = ["db-error", "23000", 1452]
SUCCESS = ["executed", 1]
DECIMAL_EXTRA_BYTES = (0, 1, 1, 2, 2, 3, 3, 4, 4)


def dec_bytes(value, integral, scale):
    """Pack a nonnegative representable DECIMAL used by the fixture."""
    whole, _, fraction = format(value, f".{scale}f").partition(".")
    whole = whole.zfill(integral) if integral else ""
    result = bytearray()
    initial = integral % 9
    if initial:
        result.extend(int(whole[:initial]).to_bytes(DECIMAL_EXTRA_BYTES[initial], "big"))
    for pos in range(initial, integral, 9):
        result.extend(int(whole[pos:pos + 9]).to_bytes(4, "big"))
    for pos in range(0, scale, 9):
        digits = fraction[pos:pos + 9]
        width = 4 if len(digits) == 9 else DECIMAL_EXTRA_BYTES[len(digits)]
        result.extend(int(digits).to_bytes(width, "big"))
    result[0] ^= 128
    return bytes(result)


def time_bytes(value, kind, precision):
    clock = (value.hour << 12) + (value.minute << 6) + value.second
    if kind == "TIME":
        base = (0x800000 + clock).to_bytes(3, "big")
    elif kind == "DATETIME":
        calendar = ((value.year * 13 + value.month) << 22) + (value.day << 17)
        base = (0x8000000000 + calendar + clock).to_bytes(5, "big")
    else:
        assert kind == "TIMESTAMP", kind
        base = int(value.timestamp()).to_bytes(4, "big")
    digits = (precision + 1) // 2
    fraction = value.microsecond // (10 ** (6 - 2 * digits))
    return base + fraction.to_bytes(digits, "big")


def packed_date(day):
    return (day.year << 9) + (day.month << 5) + day.day


def encoded(field, value):
    role, kind, *details = field
    if role == "string":
        return value
    if role == "integer":
        return value.to_bytes(1, "big") if kind == "TINYINT" else (0x800000 + value).to_bytes(3, "big")
    width, scale, _, _ = details
    if kind == "DECIMAL":
        return dec_bytes(value, width - scale, scale)
    if kind == "BIT":
        return value.to_bytes((width + 7) // 8, "big")
    if kind == "DATE":
        return (0x800000 + packed_date(value)).to_bytes(3, "big")
    if kind == "YEAR":
        return (value - 1900).to_bytes(1, "big")
    if kind in {"FLOAT", "DOUBLE"}:
        return struct.pack(">f" if kind == "FLOAT" else ">d", float(value))
    assert kind in {"TIME", "DATETIME", "TIMESTAMP"}, kind
    return time_bytes(value, kind, width)


def samples(field):
    role, kind, *details = field
    if role == "string":
        return [b"\x01", b"\x02"]
    if kind == "TINYINT":
        return [120, 121]
    if kind == "MEDIUMINT":
        return [packed_date(date(2020, 1, day)) for day in (2, 3)]
    width, scale, _, _ = details
    if kind == "DECIMAL":
        return [Decimal("7.50"), Decimal("8.25")] if scale >= 2 else [Decimal(7), Decimal(8)]
    if kind in {"FLOAT", "DOUBLE"}:
        return [Decimal("7.50"), Decimal("8.25")]
    if kind == "BIT":
        return [1, 0] if width == 1 else ([1, 2] if width <= 8 else [257, 258])
    if kind == "DATE":
        return [date(2020, 1, day) for day in (2, 3)]
    if kind == "YEAR":
        return [2020, 2021]
    assert kind in {"TIME", "DATETIME", "TIMESTAMP"}, kind
    later = timedelta(seconds=1 if kind == "TIME" else 86400, microseconds=100000 if width else 0)
    return [ORIGIN, ORIGIN + later]


def pair_samples(child, parent):
    left, right = samples(child), samples(parent)
    if child[0] == "string":
        binary = [encoded(parent, value) for value in right]
        if all(len(value) <= child[2] for value in binary):
            left = binary
    elif parent[0] == "string":
        binary = [encoded(child, value) for value in left]
        if all(len(value) <= parent[2] for value in binary):
            right = binary
    return left, right


def display(field, value):
    if value is None:
        return None
    role, kind, *details = field
    if role == "string":
        return value.hex().upper()
    if role == "integer" or kind == "YEAR":
        return str(value)
    if kind == "BIT":
        return format(value, "X")
    if kind == "DATE":
        return value.isoformat()
    width, scale, _, zerofill = details
    if kind in {"DECIMAL", "FLOAT", "DOUBLE"}:
        shown = format(value, f".{scale}f") if scale is not None else format(value.normalize(), "f")
        return shown.zfill(width + (1 if scale else 0)) if zerofill else shown
    assert kind in {"TIME", "DATETIME", "TIMESTAMP"}, kind
    shown = value.strftime("%H:%M:%S" if kind == "TIME" else "%Y-%m-%d %H:%M:%S")
    return shown + ("." + f"{value.microsecond:06}"[:width] if width else "")


def literal(field, value):
    if value is None:
        return "NULL"
    if field[0] == "string":
        return f"X'{value.hex().upper()}'"
    if field[1] == "BIT":
        return str(value)
    shown = display(field, value)
    return "'" + shown + "'" if field[1] in {"DATE", "TIME", "DATETIME", "TIMESTAMP"} else shown


def pair_matches(child, parent, left, right):
    return encoded(child, left) == encoded(parent, right)


def measurements(item):
    child, parent = item["child"], item["parent"]
    terms = list(zip(child, parent))
    pairs = [pair_samples(left, right) for left, right in terms]
    left = [pair[0] for pair in pairs]
    right = [pair[1] for pair in pairs]
    initial = [values[0] for values in right]
    changed = [values[1] for values in right]
    matching = []
    admitted = []
    for index in range(2):
        attempt = [values[index] for values in left]
        matched = item["admitted"] and all(pair_matches(c, p, x, y) for (c, p), x, y in zip(terms, attempt, initial))
        admitted.append([SUCCESS, shown(child, attempt)] if matched else [NOT_FOUND, None])
        if matched:
            matching.append(attempt)
    nulls = [None] * len(child)
    admitted.append([SUCCESS, nulls])
    existing = matching[0] if matching else nulls
    propagated = [values[1] for values in left] if matching else nulls
    if matching:
        assert all(pair_matches(c, p, x, y) for (c, p), x, y in zip(terms, propagated, changed)), item
    return {
        "input": {
            "parentRows": [literals(parent, initial), literals(parent, changed)],
            "probes": [literals(child, [values[index] for values in left]) for index in range(2)] + [literals(child, nulls)],
            "eventChild": literals(child, existing),
        },
        "data": [shown(parent, initial), admitted],
        "events": [
            ["update", SUCCESS, [shown(parent, changed)], [shown(child, propagated)]],
            ["delete", SUCCESS, [], [nulls]],
        ],
        "matched": bool(matching),
    }


def literals(fields, values):
    return [literal(field, value) for field, value in zip(fields, values)]


def shown(fields, values):
    return [display(field, value) for field, value in zip(fields, values)]


def column_type(field, release):
    role, name, *details = field
    if role == "integer":
        width = {"TINYINT": 3 if details[0] else 4, "MEDIUMINT": 8 if details[0] else 9}[name]
        return name.lower() + (f"({width})" if release in cases.LEGACY else "") + (" unsigned" if details[0] else "")
    if name == "YEAR" and release in cases.LEGACY:
        return "year(4)"
    if name in {"TIME", "DATETIME", "TIMESTAMP"} and details[0] == 0:
        return name.lower()
    if role == "scalar" and details[3]:
        return f"{name.lower()}({details[0]},{details[1]}) unsigned zerofill"
    return cases.field_type(field).lower()
