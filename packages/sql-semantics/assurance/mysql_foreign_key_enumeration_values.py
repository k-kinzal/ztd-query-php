"""Independent bounded member, ordinal and mask observations for native references."""

import mysql_foreign_key_enumeration_cases as laws

SUCCESS = ["executed", 1]
NOT_FOUND = ["db-error", "23000", 1452]


def sql_literal(field, encoded):
    if field[1] == "DATE":
        return "'2020-01-02'"
    if field[1] == "YEAR":
        return str(encoded + 1900 if encoded else 0)
    return str(encoded)


def show(field, code):
    if code is None:
        return [None, None]
    role, kind, *parts = field
    if kind == "YEAR":
        code = code + 1900 if code else 0
    if role != "enumeration":
        return [format(code, "X"), str(code)]
    labels = parts[1]
    if kind == "ENUM":
        stored = labels[code - 1] if 0 < code <= len(labels) else ""
    else:
        stored = ",".join(value for bit, value in enumerate(labels[:64]) if (code >> bit) & 1)
    return [stored.encode("utf-8").hex().upper(), str(code)]


def row(fields, codes):
    return [value for field, code in zip(fields, codes) for value in show(field, code)]


def literal_row(fields, code):
    return [sql_literal(field, code) for field in fields]


def prepared(model):
    child, parent = model["child"], model["parent"]
    first, stock, changed = model["inputs"] or [1, 1, 2]
    baseline = [first] if isinstance(first, str) else literal_row(child, first)
    return {"parentRows": [literal_row(parent, stock), literal_row(parent, changed)],
            "probes": [baseline, literal_row(child, 2), ["NULL"] * len(child)], "eventChild": baseline}


def measure(model):
    child, parent = model["child"], model["parent"]
    first, stock, changed = model["inputs"] or [1, 1, 2]
    stored_first = 1 if first == "'RED'" else first
    if stored_first != stock:
        raise ValueError("The fixed event input must bind to the parent's stored key.")
    accepted = [SUCCESS, row(child, [stock] * len(child))]
    if stored_first == 0:
        truncated = sum(field[1] == "ENUM" for field in child)
        notes = [["Warning", 1265]] * truncated
    else:
        notes = []
    alternate = [SUCCESS, row(child, [2] * len(child)), []] if stock == 2 else [NOT_FOUND, None, [["Error", 1452]]]
    nulls = row(child, [None] * len(child))
    return {
        "data": [row(parent, [stock] * len(parent)), [accepted + [notes], alternate, [SUCCESS, nulls, []]]],
        "events": [["update", SUCCESS, [row(parent, [changed] * len(parent))], [row(child, [changed] * len(child))], []],
                   ["delete", SUCCESS, [], [nulls], []]],
    }


def native_type(field, tag):
    role, kind, *parts = field
    if role == "enumeration":
        collation, labels = parts
        metadata = [laws.quote("".join(ch if ord(ch) <= 0xFFFF else "?" for ch in value)) for value in labels]
        return [f"{kind.lower()}({','.join(metadata)})", collation]
    if role == "scalar":
        if kind == "YEAR":
            return ["year(4)" if tag in laws.LEGACY else "year", None]
        return [laws.field_type(field).lower(), None]
    digits = {"TINYINT": (4, 3), "SMALLINT": (6, 5), "MEDIUMINT": (9, 8), "INT": (11, 10), "BIGINT": (20, 20)}
    unsigned = parts[0]
    width = f"({digits[kind][int(unsigned)]})" if tag in laws.LEGACY else ""
    return [kind.lower() + width + (" unsigned" if unsigned else ""), None]
