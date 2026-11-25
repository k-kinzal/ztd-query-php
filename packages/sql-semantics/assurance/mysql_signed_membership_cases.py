"""Independent MySQL signed membership, source-coordinate, and effect model."""

from itertools import product
from random import Random

from server_result_cases import result_window

DATABASE = "ir_fixture"
LEFT = "sem_ir_signed_membership"
RIGHT = "sem_ir_signed_membership_peer"
NAMES = ("tiny_value", "small_value", "medium_value", "int_value", "wide_value")
WIDTHS = ("TINYINT", "SMALLINT", "MEDIUMINT", "INT", "BIGINT")
NATIVE = ("TINY", "SHORT", "INT24", "LONG", "LONGLONG")
NULLABILITY = {LEFT: (True, False, True, False, True), RIGHT: (False, True, False, True, False)}
RECORDS = {
    LEFT: [(2, 2, 2, 2, 2), (2, 2, 2, 2, 2), (None, 11, 23, 31, 47),
           (-128, -32768, -8388608, -2147483648, -9223372036854775808),
           (127, 32767, 8388607, 2147483647, 9223372036854775807),
           (0, 0, None, 0, 0), (2, -1, 214, 214, None), (1, 2, 47, 47, 2147483648),
           (-2, 1, 2, 11, -2147483649)],
    RIGHT: [(2, 11, 2, 31, 2), (127, None, 12, 47, -9223372036854775808),
            (1, 47, -4, None, 9223372036854775807), (-1, 1, 2, 2, 1), (0, 0, 0, 0, 0)],
}
TERM = "signed-membership"
INT = 3
UPDATED = 317
NULL = ["null"]
SOURCES = ("unit", "five-widths")
PLACEMENTS = ("projection", "filter")
CONSTANTS = (("range-integer", "2", 0, False), ("range-integer", "2147483648", 0, False), ("null",))


def literal(value, zeroes=0, negative_zero=False):
    return ["range-integer", str(value), zeroes, negative_zero]


def reference(slot, occurrence=0):
    if slot == INT:
        return ["slot", slot, occurrence] if occurrence else ["slot", slot]
    return ["integral-slot", slot, occurrence]


def q(identifier):
    return "`" + identifier.replace("`", "``") + "`"


def qualified(table):
    return q(DATABASE) + "." + q(table)


def ddl(name):
    fields = ", ".join(q(column) + " " + kind + ("" if nullable else " NOT NULL")
                       for column, kind, nullable in zip(NAMES, WIDTHS, NULLABILITY[name], strict=True))
    return f"CREATE TABLE {qualified(name)} ({fields}) ENGINE=InnoDB"


def declarations():
    return [ddl(table) for table in RECORDS]


def table(name=LEFT):
    kinds = []
    for width, nullable in zip(WIDTHS, NULLABILITY[name], strict=True):
        kinds.append(("int" if nullable else "int-not-null") if width == "INT" else ["signed-integral", width, nullable])
    return {"schema": DATABASE, "table": name, "columns": list(NAMES), "columnTypes": kinds}


def grid(spec):
    scope = spec["finite"]
    values = tuple(map(tuple, scope["literals"]))
    for source, role, negated in product(scope["sources"], scope["placements"], scope["negated"]):
        operands = values if source == "unit" else (*values, *(tuple(reference(slot)) for slot in range(len(scope["physicalRoles"]))))
        for size in scope["lengths"]:
            for probe, *choices in product(operands, repeat=size + 1):
                yield source, role, negated, probe, tuple(choices)


def independent_ids():
    seen = set()
    for source, symbols in (("unit", CONSTANTS), ("five-widths", (*CONSTANTS, *map(tuple, map(reference, range(5)))))):
        width = len(symbols)
        for arity in (1, 2):
            for label in range(width ** (arity + 1)):
                order = []
                for _ in range(arity + 1):
                    label, index = divmod(label, width)
                    order.append(symbols[index])
                for role in PLACEMENTS:
                    for negated in (False, True):
                        seen.add((source, role, negated, order[0], tuple(order[1:])))
    return seen


def membership(negated, probe, choices):
    return [TERM, negated, list(probe), [list(choice) for choice in choices]]


def case(tag, source, placement, term, *, label="decision", order=None):
    return {"tag": tag, "source": source, "placement": placement, "term": term,
            "label": label, "orderBy": [] if order is None else order}


def finite(tag, coordinate):
    source, role, polarity, probe, alternatives = coordinate
    return case(tag, source, role, membership(polarity, probe, alternatives))


def plain_fields(item):
    role, term = item["placement"], item["term"]
    if role == "projection":
        return [[term, item["label"]]]
    if role == "join":
        return [[reference(INT), "left_value"], [reference(INT, 1), "right_value"]]
    return [[["integer", "41"] if item["source"] == "unit" else reference(INT), "kept"]]


def render(term, joined):
    kind = term[0]
    if kind in ("slot", "integral-slot"):
        column = q(NAMES[term[1]])
        occurrence = term[2] if len(term) == 3 else 0
        return q(("left_input", "right_input")[occurrence]) + "." + column if joined else column
    if kind == "range-integer":
        _, decimal, zeros, negative_zero = term
        return ("-" if negative_zero or decimal.startswith("-") else "") + "0" * zeros + decimal.lstrip("-")
    if kind == "integer":
        return term[1]
    if kind == "null":
        return "NULL"
    if kind == TERM:
        _, negated, subject, alternatives = term
        choices = ", ".join(render(alternative, joined) for alternative in alternatives)
        return f"({render(subject, joined)} {'NOT IN' if negated else 'IN'} ({choices}))"
    if kind == "logic":
        return f"({render(term[2], joined)} {term[1]} {render(term[3], joined)})"
    if kind == "null-test":
        return f"({render(term[2], joined)} IS {'NOT ' if term[1] else ''}NULL)"
    if kind == "operation":
        return f"({render(term[2], joined)} {term[1]} {render(term[3], joined)})"
    raise ValueError(term)


def sql(item):
    role, term = item["placement"], item["term"]
    source, predicate = qualified(LEFT), render(term, role == "join")
    where = f" WHERE {predicate}" if role in ("filter", "update", "delete") else ""
    if role == "update":
        return f"UPDATE {source} SET {q(NAMES[INT])} = {UPDATED}{where}"
    if role == "delete":
        return "DELETE FROM " + source + where
    if role == "join":
        source += f" left_input LEFT JOIN {qualified(RIGHT)} right_input ON {predicate}"
    from_ = "" if item["source"] == "unit" else f" FROM {source}"
    if item["source"] == "unit" and where and item["tag"] in ("mysql-5.6.51", "mysql-5.7.44"):
        from_ = " FROM DUAL"
    selection = ", ".join(render(expr, role == "join") + " AS " + q(label) for expr, label in plain_fields(item))
    order = item["orderBy"]
    suffix = "" if not order else " ORDER BY " + ", ".join(f"{slot} {'DESC' if desc else 'ASC'}" for slot, desc in order)
    return "SELECT " + selection + from_ + where + suffix


def analysis(item):
    return {"dialect": "mysql", "release": item["tag"], "sql": sql(item), "declarations": declarations(),
            "currentDatabase": DATABASE, "observeExactIntegralSlots": True}


def allocated(term, columns):
    kind = term[0]
    if kind in ("slot", "integral-slot"):
        position = term[1]
        occurrence = term[2] if len(term) == 3 else 0
        indexes = columns[occurrence]
        if position not in indexes:
            indexes.append(position)
        return [kind, indexes.index(position), *term[2:]]
    if kind == TERM:
        return [kind, term[1], allocated(term[2], columns), [allocated(value, columns) for value in term[3]]]
    if kind == "logic":
        return [kind, term[1], allocated(term[2], columns), allocated(term[3], columns)]
    if kind == "null-test":
        return [kind, term[1], allocated(term[2], columns)]
    if kind in ("range-integer", "integer", "null", "boolean"):
        return term
    raise ValueError(term)


def heading(name, ordinals):
    source = table(name)
    source["columns"] = [source["columns"][ordinal] for ordinal in ordinals]
    types = source.pop("columnTypes")
    kept = [types[ordinal] for ordinal in ordinals]
    if kept != ["int"] * len(kept):
        source["columnTypes"] = kept
    return source


def direct(item):
    term, role = item["term"], item["placement"]
    slots = [[], []]
    where = allocated(term, slots) if role in ("filter", "update", "delete") else None
    common = {"dialect": "mysql", "release": item["tag"], "observeExactIntegralSlots": True}
    if role == "update":
        assignment = [[allocated(reference(INT), slots), ["integer", str(UPDATED)]]]
        return {**common, "kind": role, "target": heading(LEFT, slots[0]), "assignments": assignment, "where": where}
    if role == "delete":
        return {**common, "kind": role, "target": heading(LEFT, slots[0]), "where": where}
    fields = [[allocated(expr, slots), name] for expr, name in plain_fields(item)]
    if role == "join":
        on = allocated(term, slots)
        source = {"kind": "LEFT", "left": heading(LEFT, slots[0]), "right": heading(RIGHT, slots[1]), "on": on}
    else:
        source = None if item["source"] == "unit" else heading(LEFT, slots[0])
    return {**common, "source": source, "columns": fields, "where": where,
            "distinct": False, "orderBy": item["orderBy"], "limit": None}


def graph(item):
    result = {name: value for name, value in direct(item).items()
              if name not in ("dialect", "release", "kind", "observeExactIntegralSlots")}
    if "orderBy" in result:
        result["orderBy"] = [[slot, descending, not descending] for slot, descending in item["orderBy"]]
    return result


def observed_physical(term, source):
    if term[0] in ("slot", "integral-slot"):
        return term[0], NAMES.index(source["columns"][term[1]]), *term[2:]
    return tuple(term)


def evaluate(term, row):
    kind = term[0]
    if kind in ("slot", "integral-slot"):
        return row[term[2] if len(term) == 3 else 0][term[1]]
    if kind in ("integer", "range-integer"):
        return int(term[1])
    if kind == "null":
        return None
    if kind == TERM:
        value = evaluate(term[2], row)
        choices = [evaluate(child, row) for child in term[3]]
        if value is not None and value in choices:
            return int(not term[1])
        if value is None or None in choices:
            return None
        return int(term[1])
    if kind == "null-test":
        return int((evaluate(term[2], row) is None) != term[1])
    if kind == "logic":
        left, right = (evaluate(child, row) for child in term[2:])
        winning = int(term[1] == "OR")
        return winning if winning in (left, right) else None if None in (left, right) else 1 - winning
    if kind == "boolean":
        return int(term[1])
    raise ValueError(term)


def fixture_prediction(name):
    return result_window([[field, native] for field, native in zip(NAMES, NATIVE, strict=True)],
                         list(map(list, RECORDS[name])), [], None)


def expected(item):
    role, term = item["placement"], item["term"]
    rows = [()] if item["source"] == "unit" else [(row,) for row in RECORDS[LEFT]]
    if role in ("update", "delete"):
        affected, after = 0, []
        for (row,) in rows:
            if evaluate(term, (row,)) == 1:
                affected += 1
                if role == "update":
                    after.append([*row[:INT], UPDATED, *row[INT+1:]])
            else:
                after.append(list(row))
        fields = fixture_prediction(LEFT)["fields"]
        return {"role": role, "count": affected, "after": result_window(fields, after, [], None)}
    if role == "join":
        joined = []
        for left, in rows:
            matches = [(left, right) for right in RECORDS[RIGHT] if evaluate(term, (left, right)) == 1]
            joined.extend(matches if matches else [(left, (None,) * len(NAMES))])
        rows = joined
    if role == "filter":
        rows = [row for row in rows if evaluate(term, row) == 1]
    output = plain_fields(item)
    fields = [[label, "LONG" if expr[0] == "slot" else "LONGLONG"] for expr, label in output]
    values = [[evaluate(expr, row) for expr, _ in output] for row in rows]
    ordered = [[position, desc, not desc] for position, desc in item["orderBy"]]
    return result_window(fields, values, ordered, None)


def generated(tag, seed, budget):
    rng = Random(seed)
    placements = (*PLACEMENTS, "join", "update", "delete")
    leaves = [*map(list, CONSTANTS), literal(0, 2, True), literal(2, 3), literal(-9223372036854775808),
              literal(9223372036854775807), literal(8388607), literal(-8388608), literal(127), literal(-128)]
    for number in range(budget):
        role = placements[number % len(placements)]
        source = "unit" if role == "projection" and number % 2 == 0 else "five-widths"
        symbols = list(leaves)
        if source != "unit":
            symbols.extend(reference(slot) for slot in range(5))
        if role == "join":
            symbols.extend(reference(slot, 1) for slot in range(5))
        term = membership(bool(rng.getrandbits(1)), rng.choice(symbols), rng.choices(symbols, k=rng.randrange(1, 13)))
        order = None
        if role == "projection" and number % 3 == 1:
            term = ["null-test", bool(rng.getrandbits(1)), term]
        if role == "filter" and number % 2 == 0:
            second = membership(False, rng.choice(symbols), rng.choices(symbols, k=2))
            term = ["logic", rng.choice(("AND", "OR")), term, second]
            order = [[1, bool(rng.getrandbits(1))]]
        yield case(tag, source, role, term, label=rng.choice(("判定", "signed member")), order=order)
