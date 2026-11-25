"""Independent ownership, naming and catalog predictions for DROP INDEX."""

from itertools import product
from random import Random

from mysql_innodb_declaration_width_cases import OLD, SCHEMA, quote


TABLE = "ir_index_removal"
MARKER = "ir_index_remove_marker"
STATUS = "mysql-drop-index"
LIFETIMES = ("persistent", "temporary", "temporary-shadow")
KINDS = {
    "primary": ("inline", "table"),
    "unique": ("explicit", "constraint", "inline", "automatic-in-order", "automatic-suffixed"),
    "ordinary": ("explicit", "automatic-in-order", "automatic-suffixed"),
}
SPELLINGS = ("declared", "permitted-case-variant")
QUALIFIERS = ("explicit", "current-database")
ABSENT = ["db-error", "42000", 1091]
DUPLICATE = ["db-error", "23000", 1062]


def coordinates():
    return {(life, kind, origin, spelling, qualifier) for life, spelling, qualifier in product(LIFETIMES, SPELLINGS, QUALIFIERS)
            for kind, origins in KINDS.items() for origin in origins}


def definition(tag, point, *, field="a", label="LiStÉ", composite=True, offset=0, option=""):
    life, kind, origin, spelling, qualifier = point
    if kind == "primary":
        name = "PRIMARY"
    elif origin in ("explicit", "constraint"):
        name = label
    elif origin == "automatic-suffixed":
        prefix = field.encode()[:30 if tag in OLD else 61].decode()
        name = prefix + "_2"
    else:
        name = field
    written = name if spelling == "declared" else name.swapcase()
    fields = [field, "c"] if origin != "inline" and composite else [field]
    target = [SCHEMA, TABLE, "table" if life == "persistent" else "temporary-table"]
    return {"dialect": "mysql", "release": tag, "mysqlDropIndex": {"owner": target, "role": kind, "name": name},
            "point": list(point), "written": written, "fields": fields, "field": field, "label": label,
            "offset": offset, "option": option}


def cases(tag, seed, count):
    rng = Random(seed)
    for ordinal in range(count):
        kind = tuple(KINDS)[ordinal % len(KINDS)]
        origin = rng.choice(KINDS[kind])
        point = (LIFETIMES[(ordinal // len(KINDS)) % len(LIFETIMES)], kind, origin,
                 rng.choice(SPELLINGS), rng.choice(QUALIFIERS))
        yield definition(tag, point, field=rng.choice(("Ab", "Avec", "AccentÉ", "NÅme", "Nom")) + str(ordinal),
                         label=f"résult {ordinal} ` {seed}", composite=bool(rng.getrandbits(1)), offset=rng.randrange(20),
                         option=rng.choice(("", " ALGORITHM=DEFAULT", " LOCK DEFAULT ALGORITHM=DEFAULT")))


def keys(item, sibling=False):
    _, kind, origin, _, _ = item["point"]
    name = item["mysqlDropIndex"]["name"]
    keep = [["retained_b", "unique", ["b"]], ["retained_c", "ordinary", ["c", "b"]]]
    if origin == "automatic-suffixed":
        keep.append([item["field"], "ordinary", ["b"]])
    if sibling:
        return [*keep, ["persistent_only", "ordinary", [item["field"]]]]
    return [*keep, [name, kind, item["fields"]]]


def table_definition(item, temporary, sibling=False):
    _, kind, origin, _, _ = item["point"]
    field, label = item["field"], item["label"]
    first = quote(field) + " INT NOT NULL"
    if not sibling and origin == "inline":
        first += " PRIMARY KEY" if kind == "primary" else " UNIQUE"
    columns = [first, "`b` INT NULL", "`c` INT NOT NULL",
               "UNIQUE INDEX `retained_b` (`b`)", "INDEX `retained_c` (`c`, `b`)"]
    if origin == "automatic-suffixed":
        columns.append("INDEX " + quote(field) + " (`b`)")
    if sibling:
        columns.append("INDEX `persistent_only` (" + quote(field) + ")")
    elif origin != "inline":
        starter = {"primary": "PRIMARY KEY", "unique": "UNIQUE INDEX", "ordinary": "INDEX"}[kind]
        if origin == "constraint":
            starter = "CONSTRAINT " + quote(label) + " UNIQUE"
        elif origin == "explicit":
            starter += " " + quote(label)
        columns.append(starter + " (" + ", ".join(map(quote, item["fields"])) + ")")
    return f"CREATE {'TEMPORARY ' if temporary else ''}TABLE {quote(SCHEMA)}.{quote(TABLE)} ({', '.join(columns)}) ENGINE=InnoDB"


def declarations(item):
    life = item["point"][0]
    if life == "temporary-shadow":
        return [table_definition(item, False, sibling=True), table_definition(item, True)]
    return [table_definition(item, life == "temporary")]


def command(item, original=False):
    qualifier = item["point"][4]
    table = quote(TABLE)
    if qualifier == "explicit" or not original:
        table = quote(SCHEMA) + "." + table
    name = item["written"] if original else item["mysqlDropIndex"]["name"]
    return "DROP INDEX " + quote(name) + " ON " + table + (item["option"] if original else "")


def analyze(item, sql=None, context=None):
    return {"dialect": "mysql", "release": item["release"], "currentDatabase": SCHEMA,
            "declarations": declarations(item) if context is None else context,
            "sql": command(item, original=True) if sql is None else sql}


def field_rows(item, temporary):
    base = 100 if temporary else 10
    base += item["offset"]
    return [[base+1, None, 5], [base+2, None, 7], [base+3, 3, 11]]


def metadata(item, retained, temporary, sibling=False, rows=None):
    index_values = []
    for name, kind, fields in keys(item, sibling):
        if retained and not sibling and name == item["mysqlDropIndex"]["name"]:
            continue
        index_values += [[name, int(kind == "ordinary"), i, field, "A", field == "b", "BTREE", True, None]
                         for i, field in enumerate(fields, 1)]
    native_type = "int(11)" if item["release"] in OLD else "int"
    extra = "NULL" if temporary and item["release"] not in OLD else ""
    columns = [[name, native_type, optional, None, extra, None] for name, optional in ((item["field"], False), ("b", True), ("c", False))]
    return {"rows": field_rows(item, temporary) if rows is None else sorted(rows, key=lambda r: (r[0], -1 if r[1] is None else r[1])),
            "columns": columns, "indexes": sorted(index_values, key=lambda part: (part[0], part[2]))}


def prediction(item, runtime_error=None):
    life = item["point"][0]
    temporary = life != "persistent"
    before = metadata(item, False, temporary)
    if runtime_error is None:
        rows = [*before["rows"], [before["rows"][0][0], 63, before["rows"][0][2]]]
        after = metadata(item, True, temporary, rows=rows)
    else:
        after = before
    unchanged = metadata(item, False, False, sibling=True) if life == "temporary-shadow" else None
    return {"status": STATUS, "initial": before, "effect": ["executed"] if runtime_error is None else runtime_error,
            "after": after, "catalog": after if not temporary else unchanged,
            "released": None if runtime_error is not None else ["executed"],
            "retained": None if runtime_error is not None else DUPLICATE, "marker": 7, "active": False}
