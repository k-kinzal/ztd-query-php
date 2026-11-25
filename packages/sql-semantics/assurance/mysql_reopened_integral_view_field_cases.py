"""Define signed readings of reopened MySQL views and base fields."""

from itertools import product
from random import Random

from mysql_external_view_read_name_cases import LEGACY, SCHEMA, quote

VALUES = ("0", "-0", "17", "-42", "99999999", "100000000", "-99999999", "-100000000",
          "999999999", "1000000000", "-2147483648", "2147483647", "-9223372036854775808", "9223372036854775807")
FORMS = ("plain", "grouped-plus", "zero-padded")
ALGORITHMS = ("UNDEFINED", "TEMPTABLE", "MERGE")
READS = ("bare", "alias", "wildcard")
BODIES = ("implicit-unit", "dual")
WIDTHS = ("TINYINT", "SMALLINT", "MEDIUMINT", "INT", "BIGINT")
EXTERNAL_TYPES = {
    "TINYINT": ("TINY", 4, -128, 127),
    "SMALLINT": ("SHORT", 6, -32768, 32767),
    "MEDIUMINT": ("INT24", 9, -8388608, 8388607),
    "INT": ("LONG", 11, -2147483648, 2147483647),
    "BIGINT": ("LONGLONG", 20, -9223372036854775808, 9223372036854775807),
}
VIEW_NAMES = ("p", "q", "r")
FIXED_VALUES = ("29", "-920019999888899999")


def view_coordinates(contract):
    finite = contract["finite"]
    return product(finite["values"], finite["tokenForms"], finite["algorithms"], finite["bodySources"])


def grid(contract, _tag):
    for value, spelling, mode, body in view_coordinates(contract):
        for read in contract["finite"]["bindings"]:
            yield ("view", value, spelling, mode, body, read)
    for width, read in product(contract["finite"]["baseWidths"], contract["finite"]["bindings"]):
        yield ("base", width, read)


def independent_ids():
    view = {("view", value, form, algorithm, body, read) for read in READS for body in BODIES for algorithm in ALGORITHMS
            for form in FORMS for value in VALUES}
    return view | {("base", width, read) for read in READS for width in WIDTHS}


def view_name(value, form, algorithm, body):
    return f"sir_vi_{VALUES.index(value):02}_{FORMS.index(form)}_{ALGORITHMS.index(algorithm)}_{BODIES.index(body)}"


def token(value, spelling):
    if spelling == "plain":
        return value
    if spelling == "grouped-plus":
        return f"(+(({value})))"
    if spelling == "zero-padded":
        return ("-" if value.startswith("-") else "") + "0" * 12 + value.lstrip("-")
    raise ValueError(spelling)


def declaration(value, spelling, algorithm, body):
    name = view_name(value, spelling, algorithm, body)
    from_dual = " FROM DUAL" if body == "dual" else ""
    return (f"CREATE ALGORITHM={algorithm} VIEW {SCHEMA}.{name} AS "
            f"SELECT {token(value, spelling)} AS p, {FIXED_VALUES[0]} AS q, {FIXED_VALUES[1]} AS r{from_dual}")


def view_width(tag, value):
    return len(str(abs(int(value)))) + int(value.startswith("-") or tag not in LEGACY)


def catalog(tag, value, spelling, algorithm, body):
    name = view_name(value, spelling, algorithm, body)
    for index, (column, literal) in enumerate(zip(VIEW_NAMES, (value, *FIXED_VALUES), strict=True), 1):
        stored_width = view_width(tag, literal)
        padded = index == 1 and tag not in LEGACY and spelling == "zero-padded"
        storage = "bigint" if padded or stored_width >= 10 else "int"
        yield [name, column, index, storage, "NO"]


def base_layout(width):
    first = WIDTHS.index(width)
    storage = WIDTHS[first:] + WIDTHS[:first]
    fields = [(f"f{index}", kind, index in (1, 4)) for index, kind in enumerate(storage)]
    return fields


def base_rows(width):
    fields = base_layout(width)
    low = [EXTERNAL_TYPES[kind][2] for _, kind, _ in fields]
    high = [EXTERNAL_TYPES[kind][3] for _, kind, _ in fields]
    mixed = [None if nullable else index + 11 for index, (_, _, nullable) in enumerate(fields)]
    return [low, mixed, high, low]


def base_ddl(width):
    columns = ", ".join(f"{name} {kind} " + ("NULL" if nullable else "NOT NULL")
                        for name, kind, nullable in base_layout(width))
    return f"CREATE TABLE {SCHEMA}.sir_bi_{width.lower()} ({columns})"


def base_fixture(width):
    values = ", ".join("(" + ", ".join("NULL" if value is None else str(value) for value in row) + ")" for row in base_rows(width))
    return [base_ddl(width), f"INSERT INTO {SCHEMA}.sir_bi_{width.lower()} VALUES {values}"]


def fixture(coordinate):
    if coordinate[0] == "base":
        width = coordinate[1]
        return f"sir_bi_{width.lower()}", [base_ddl(width)], base_layout(width), base_rows(width)
    value, form, algorithm, body = coordinate[1:5]
    return (view_name(value, form, algorithm, body), [declaration(value, form, algorithm, body)], None,
            [[int(value), *map(int, FIXED_VALUES)]])


def physical(tag, coordinate):
    table, declarations, fields, rows = fixture(coordinate)
    if fields is not None:
        return table, declarations, [(name, storage, *EXTERNAL_TYPES[storage][:2], nullable)
                                      for name, storage, nullable in fields], rows
    layout = []
    for name, literal in zip(VIEW_NAMES, (coordinate[1], *FIXED_VALUES), strict=True):
        width = view_width(tag, literal)
        storage = "INT" if width < 10 else "BIGINT"
        layout.append((name, storage, EXTERNAL_TYPES[storage][0], width, False))
    return table, declarations, layout, rows


def coordinate_case(tag, coordinate, projection=None):
    _, _, layout, _ = physical(tag, coordinate)
    view = coordinate[0] == "view"
    read = coordinate[-1]
    if projection is None:
        if read == "wildcard":
            projection = [[index, row[0], None] for index, row in enumerate(layout)]
        elif read == "bare":
            projection = [[0, "P" if view else "f0", None], [1, layout[1][0], "neighbor"], [0, layout[0][0], "earlier"]]
        else:
            last = len(layout) - 1
            projection = [[last, layout[last][0], "selected"], [0, "P" if view else "f0", None], [last, layout[last][0], "later"]]
    return {"tag": tag, "coordinate": list(coordinate), "projection": projection}


def graph(item):
    tag, coordinate = item["tag"], item["coordinate"]
    table, _, layout, _ = physical(tag, coordinate)
    owned = list(dict.fromkeys(index for index, _, _ in item["projection"]))
    columns, types = [], []
    for index in owned:
        name, storage, _, _, nullable = layout[index]
        columns.append(name)
        types.append(("int" if nullable else "int-not-null") if storage == "INT" else ["signed-integral", storage, nullable])
    source = {"schema": SCHEMA, "table": table, "columns": columns}
    if types != ["int"] * len(columns):
        source["columnTypes"] = types
    if coordinate[0] == "view":
        source.update(relationKind="view", materializedView=True)
    entries = []
    for index, spelling, alias in item["projection"]:
        slot = owned.index(index)
        ref = ["slot", slot] if layout[index][1] == "INT" else ["integral-slot", slot, 0]
        entries.append([ref, spelling if alias is None else alias])
    return {"source": source, "columns": entries, "where": None, "distinct": False, "orderBy": [], "limit": None}


def sql(item):
    coordinate = item["coordinate"]
    table, _, _, _ = fixture(coordinate)
    read = coordinate[-1]
    explicit = "v." if read == "alias" else ""
    projection = "v.*" if read == "wildcard" else ", ".join(
        explicit + quote(spelling) + ("" if label is None else " AS " + quote(label))
        for _, spelling, label in item["projection"]
    )
    return f"SELECT {projection} FROM {SCHEMA}.{table}" + (" AS v" if read != "bare" else "")


def analyzed(item):
    _, definitions, _, _ = fixture(item["coordinate"])
    return {"dialect": "mysql", "release": item["tag"], "sql": sql(item), "declarations": definitions,
            "observeExactIntegralSlots": True}


def direct(item):
    return {"dialect": "mysql", "release": item["tag"], "observeExactIntegralSlots": True, **graph(item)}


def predict(item):
    _, _, layout, rows = physical(item["tag"], item["coordinate"])
    fields, positions = [], []
    for index, spelling, alias in item["projection"]:
        _, _, native, width, nullable = layout[index]
        fields.append([spelling if alias is None else alias, native, width, not nullable])
        positions.append(index)
    return {"status": "rows", "fields": fields, "rows": [[row[index] for index in positions] for row in rows]}


def seeded(tag, seed, cases):
    rng = Random(seed)
    for index in range(cases):
        binding = rng.choice(("bare", "alias"))
        if index % 3 == 0:
            coordinate = ("base", rng.choice(WIDTHS), binding)
        else:
            coordinate = ("view", rng.choice(VALUES), rng.choice(FORMS), rng.choice(ALGORITHMS), rng.choice(BODIES), binding)
        _, _, layout, _ = physical(tag, coordinate)
        projection = []
        for position in range(rng.randrange(1, 9)):
            slot = rng.randrange(len(layout))
            name = layout[slot][0]
            if coordinate[0] == "view" and rng.randrange(2):
                name = name.upper()
            alias = f"picked_{position}" if rng.randrange(3) == 0 else None
            projection.append([slot, name, alias])
        yield coordinate_case(tag, coordinate, projection)
