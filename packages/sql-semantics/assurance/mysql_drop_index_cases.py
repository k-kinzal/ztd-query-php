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
# The releases are pinned individually. The 8.0 and 8.4 maintenance branches
# contain the wider native buffer, as does 9.1.0.
SUFFIX_BYTES = {
    "mysql-5.6.51": 30, "mysql-5.7.44": 30, "mysql-8.0.44": 61,
    "mysql-8.1.0": 30, "mysql-8.2.0": 30, "mysql-8.3.0": 30,
    "mysql-8.4.7": 61, "mysql-9.0.1": 30, "mysql-9.1.0": 61,
}
ABSENT = ["db-error", "42000", 1091]
DUPLICATE = ["db-error", "23000", 1062]


def coordinates():
    return {(life, kind, origin, spelling, qualifier) for life, spelling, qualifier in product(LIFETIMES, SPELLINGS, QUALIFIERS)
            for kind, origins in KINDS.items() for origin in origins}


def definition(tag, point, *, field="a", label="LiStÉ", composite=True, offset=0, option="", suffix=2, nullable=False, anchor=None):
    life, kind, origin, spelling, qualifier = point
    occupied = None
    if kind == "primary":
        name = "PRIMARY"
    elif origin in ("explicit", "constraint"):
        name = label
    elif origin == "automatic-suffixed":
        prefix = field.encode()[:SUFFIX_BYTES[tag]].decode()
        name = f"{prefix}_{suffix}"
        occupied = prefix + "_2" if suffix == 3 else None
    else:
        name = field
    written = name if spelling == "declared" else name.swapcase()
    fields = [field, "c"] if origin != "inline" and composite else [field]
    role = "clustered-unique" if kind == "unique" and not (nullable and "c" in fields) and anchor not in ("primary", "prior") else kind
    target = [SCHEMA, TABLE, "table" if life == "persistent" else "temporary-table"]
    return {"dialect": "mysql", "release": tag, "mysqlDropIndex": {"owner": target, "role": role, "name": name, "policy": ["DEFAULT", "DEFAULT"]},
            "point": list(point), "written": written, "fields": fields, "field": field, "label": label,
            "offset": offset, "option": option, "occupied": occupied, "nullable": nullable, "anchor": anchor}


def cases(tag, seed, count):
    rng = Random(seed)
    for ordinal in range(count):
        kind = tuple(KINDS)[ordinal % len(KINDS)]
        origin = rng.choice(KINDS[kind])
        nullable = ordinal == 1
        if nullable and origin == "inline":
            origin = "automatic-in-order"
        point = (LIFETIMES[(ordinal // len(KINDS)) % len(LIFETIMES)], kind, origin,
                 rng.choice(SPELLINGS), rng.choice(QUALIFIERS))
        yield definition(tag, point, field=rng.choice(("Ab", "Avec", "AccentÉ", "NÅme", "Nom")) + str(ordinal),
                         label=f"résult {ordinal} ` {seed}", composite=bool(rng.getrandbits(1)) or nullable, offset=rng.randrange(20),
                         option=rng.choice(("", " ALGORITHM=DEFAULT", " LOCK DEFAULT ALGORITHM=DEFAULT")), nullable=nullable)


def keys(item, sibling=False):
    _, kind, origin, _, _ = item["point"]
    name = item["mysqlDropIndex"]["name"]
    keep = [["retained_b", "unique", ["b"]], ["retained_c", "ordinary", ["c", "b"]]]
    if origin == "automatic-suffixed":
        keep.append([item["field"], "ordinary", ["b"]])
        if item["occupied"] is not None:
            keep.append([item["occupied"], "ordinary", ["b"]])
    if not sibling and item["anchor"] is not None:
        keep.append(["PRIMARY" if item["anchor"] == "primary" else "required_c",
                     "primary" if item["anchor"] == "primary" else "unique", ["c"]])
    if sibling:
        return [*keep, ["persistent_only", "ordinary", [item["field"]]]]
    return [*keep, [name, kind, item["fields"]]]


def table_definition(item, temporary, sibling=False):
    _, kind, origin, _, _ = item["point"]
    field, label = item["field"], item["label"]
    first = quote(field) + " INT NOT NULL"
    if not sibling and origin == "inline":
        first += " PRIMARY KEY" if kind == "primary" else " UNIQUE"
    c = "`c` INT NULL" if item["nullable"] and not sibling else "`c` INT NOT NULL"
    columns = [first, "`b` INT NULL", c,
               "UNIQUE INDEX `retained_b` (`b`)", "INDEX `retained_c` (`c`, `b`)"]
    if origin == "automatic-suffixed":
        columns.append("INDEX " + quote(field) + " (`b`)")
        if item["occupied"] is not None:
            columns.append("INDEX " + quote(item["occupied"]) + " (`b`)")
    if not sibling and item["anchor"] == "prior":
        columns.append("UNIQUE INDEX `required_c` (`c`)")
    if sibling:
        columns.append("INDEX `persistent_only` (" + quote(field) + ")")
    elif origin != "inline":
        starter = {"primary": "PRIMARY KEY", "unique": "UNIQUE INDEX", "ordinary": "INDEX"}[kind]
        if origin == "constraint":
            starter = "CONSTRAINT " + quote(label) + " UNIQUE"
        elif origin == "explicit":
            starter += " " + quote(label)
        columns.append(starter + " (" + ", ".join(map(quote, item["fields"])) + ")")
    if not sibling and item["anchor"] in ("primary", "later"):
        columns.append(("PRIMARY KEY" if item["anchor"] == "primary" else "UNIQUE INDEX `required_c`") + " (`c`)")
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
    nullable = {"b", "c"} if item["nullable"] and not sibling else {"b"}
    index_values = []
    for name, kind, fields in keys(item, sibling):
        if retained and not sibling and name == item["mysqlDropIndex"]["name"]:
            continue
        index_values += [[name, int(kind == "ordinary"), i, field, "A", field in nullable, "BTREE", True, None]
                         for i, field in enumerate(fields, 1)]
    native_type = "int(11)" if item["release"] in OLD else "int"
    extra = "NULL" if temporary and item["release"] not in OLD else ""
    columns = [[name, native_type, optional, None, extra, None] for name, optional in ((item["field"], False), ("b", True), ("c", "c" in nullable))]
    return {"rows": field_rows(item, temporary) if rows is None else sorted(rows, key=lambda r: (r[0], -1 if r[1] is None else r[1])),
            "columns": columns, "indexes": sorted(index_values, key=lambda part: (part[0], part[2]))}


def probe_last(item):
    return 97 if item["anchor"] is not None else field_rows(item, item["point"][0] != "persistent")[0][2]


def prediction(item, runtime_error=None):
    life = item["point"][0]
    temporary = life != "persistent"
    before = metadata(item, False, temporary)
    if runtime_error is None:
        rows = [*before["rows"], [before["rows"][0][0], 63, probe_last(item)]]
        after = metadata(item, True, temporary, rows=rows)
    else:
        after = before
    unchanged = metadata(item, False, False, sibling=True) if life == "temporary-shadow" else None
    return {"status": STATUS, "initial": before, "effect": ["executed"] if runtime_error is None else runtime_error,
            "after": after, "catalog": after if not temporary else unchanged,
            "released": None if runtime_error is not None else ["executed"],
            "retained": None if runtime_error is not None else DUPLICATE, "marker": 7, "active": False}
