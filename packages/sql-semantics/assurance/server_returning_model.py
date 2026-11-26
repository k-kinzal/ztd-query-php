"""Positional projection of a fixture mutation's independently modeled rows."""


def fields_from(layout):
    return [[["slot", position], label] for position, label in layout]


def row_order(rows):
    return sorted(rows, key=lambda row: tuple((value is not None, value) for value in row))


def ordered_returning(effect):
    if "rows" not in effect:
        return effect
    return {**effect, "rows": row_order(effect["rows"])}


def project_returned(item, effects, affected_rows, physical_columns=("id", "delta")):
    names = item["target"]["columns"]
    layout = item["returning"]
    positions = [physical_columns.index(names[operand[1]]) for operand, _ in layout]
    return {**effects, "columns": len(layout), "fields": [[label, "int4"] for _, label in layout],
            "rows": row_order([[row[position] for position in positions] for row in affected_rows])}
