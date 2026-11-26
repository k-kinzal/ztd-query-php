"""Finite and generated declaration layouts for unsigned ENUM and SET references."""

from random import Random

from mysql_foreign_key_string_cases import key
from mysql_foreign_key_scalar_cases import LEGACY, PROFILE, integer, scalar

DB = "ir_fixture"
CHILD = "ir_fk_enumeration_child"
PARENT = "ir_fk_enumeration_parent"
SYMBOL = "Native_Enumeration"
STATUS = "mysql-foreign-key-enumeration-fields"
COLLATIONS = ("utf8mb4_bin", "utf8mb4_general_ci", "utf8mb4_unicode_ci")


def enumeration(kind, labels, collation=COLLATIONS[0]):
    return ["enumeration", kind, collation, labels]


def named(kind, length, collation=COLLATIONS[0]):
    return enumeration(kind, [f"m{index:03}" for index in range(1, length + 1)], collation)


def width(field):
    role, kind, *parts = field
    if role == "enumeration":
        size = len(parts[1])
        if kind == "ENUM":
            return 1 if size < 256 else 2
        return next(n for n in (1, 2, 3, 4, 8) if size <= n * 8) if size <= 32 else 8
    if role == "integer" and parts[0]:
        return {"TINYINT": 1, "SMALLINT": 2, "MEDIUMINT": 3, "INT": 4, "BIGINT": 8}[kind]
    return 1 if role == "scalar" and kind == "YEAR" else None


def model(release, child, parent, inputs=None, syntax=None):
    if not child or len(child) != len(parent):
        raise ValueError("Enumeration references require equally sized ordered sides.")
    return {"release": release, "child": child, "parent": parent, "admitted": all(
        width(x) is not None and width(x) == width(y) for x, y in zip(child, parent)),
        "inputs": inputs, "syntax": syntax}


def finite_points(spec):
    fields = spec["finite"]["fields"]
    return [(left, right) for left, a in enumerate(fields) for right, b in enumerate(fields)
            if a[0] == "enumeration" or b[0] == "enumeration"]


def coordinate(spec, tag, point):
    fields = spec["finite"]["fields"]
    return model(tag, [fields[point[0]]], [fields[point[1]]])


def explored(spec, tag, seed):
    rng = Random(seed)
    shape = spec["exploration"]
    pairs = [coordinate(spec, tag, point) for point in finite_points(spec)]
    pairs = [item for item in pairs if item["admitted"]]
    for _ in range(shape["casesPerSeed"]):
        axes = [rng.choice(pairs) for _ in range(rng.randint(1, shape["maxWidth"]))]
        yield model(tag, [item["child"][0] for item in axes], [item["parent"][0] for item in axes])


def authored(tag):
    standard = enumeration("ENUM", ["red", "green"])
    mask = enumeration("SET", ["red", "green"])
    tiny = integer("TINYINT", True)
    empty = enumeration("ENUM", ["", "green"])
    large = named("SET", 64)
    legacy = tag in LEGACY
    for label, child, parent, inputs, syntax in (
        ("swapped-labels", enumeration("ENUM", ["green", "red"]), standard, None, None),
        ("case-folded-input", enumeration("ENUM", ["red", "green"], COLLATIONS[2]), standard, ["'RED'", 1, 2], None),
        ("ordinal-outside-labels", standard, tiny, [1, 1, 255], None),
        ("mask-with-hidden-bits", mask, tiny, [1, 1, 255], None),
        ("mask-to-unlisted-ordinal", standard, mask, [1, 1, 3], None),
        ("declared-empty-member", empty, tiny, None, None),
        ("implicit-ordinal-zero", empty, tiny, [0, 0, 2], None),
        ("leading-space-and-trim", enumeration("SET", [" red", "green"]), standard, None,
         [[" red ", "green  "], None, False]),
        ("embedded-nul", enumeration("ENUM", ["red " if legacy else "red \0tail", "green"]), standard, None,
         [["red \0tail ", "green"], None, False]),
        ("supplementary-character", enumeration("SET", ["😀", "green"]), standard, None,
         [["😀 ", "green"], None, False]),
        ("set-over-sixty-four-with-repeats", enumeration("SET", large[3] + [large[3][0]]),
         integer("BIGINT", True), None, None),
        ("set-last-bit", large, integer("BIGINT", True), [1, 1, 2**63], None),
        ("set-before-last-bit", large, integer("BIGINT", True), [1, 1, 2**63-1], None),
        ("set-last-and-first-bit", large, integer("BIGINT", True), [1, 1, 2**63+1], None),
        ("set-all-bits", large, integer("BIGINT", True), [1, 1, 2**64-1], None),
        ("collation-table-default", standard, standard, None, [None, None, True]),
        ("enum-two-byte-repeated-label", enumeration("ENUM", named("ENUM", 255)[3] + ["m001"]),
         integer("SMALLINT", True), None, None),
        ("set-last-three-byte-position", named("SET", 24), integer("MEDIUMINT", True), [1, 1, 2**23], None),
    ):
        yield label, model(tag, [child], [parent], inputs, syntax)


def tab(name):
    return f"`{DB}`.`{name}`"


def identity(name):
    return [DB, name, "table"]


def columns(item, role):
    return [f"{role}{index}" for index in range(len(item["child"]))]


def sql(item, qualified=False):
    child_names = ", ".join(f"`{name}`" for name in columns(item, "x"))
    parent_names = ", ".join(f"`{name}`" for name in columns(item, "p"))
    parent = tab(PARENT) if qualified else f"`{PARENT}`"
    return (f"ALTER TABLE {tab(CHILD)} ADD CONSTRAINT `{SYMBOL}` FOREIGN KEY ({child_names}) "
            f"REFERENCES {parent} ({parent_names}) ON UPDATE CASCADE ON DELETE SET NULL")


def quote(label):
    escaped = label.replace("\\", "\\\\").replace("'", "''").replace("\0", "\\0")
    return "'" + escaped + "'"


def field_type(field, syntax=None, implicit=False):
    role, kind, *details = field
    if role == "integer":
        return kind + (" UNSIGNED" if details[0] else "")
    if role == "scalar":
        return kind if details[0] is None else f"{kind}({details[0]})"
    collation, labels = details
    members = ",".join(quote(label) for label in (labels if syntax is None else syntax))
    return f"{kind}({members})" + ("" if implicit else f" CHARACTER SET utf8mb4 COLLATE {collation}")


def declarations(item):
    types = item["syntax"] or [None, None, False]
    suffix = " ENGINE=InnoDB" + (" DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin" if types[2] else "")
    parents, children = columns(item, "p"), columns(item, "x")
    declarations = [
        ", ".join(f"{name} {field_type(field, syntax, types[2])} {nullable}" for name, field in zip(names, item[side]))
        for names, side, syntax, nullable in ((parents, "parent", types[1], "NOT NULL"), (children, "child", types[0], "NULL"))
    ]
    return [f"CREATE TABLE {tab(PARENT)} ({declarations[0]}, PRIMARY KEY ({', '.join(parents)})){suffix}",
            f"CREATE TABLE {tab(CHILD)} ({declarations[1]}, z INT PRIMARY KEY){suffix}"]


def analyze(item):
    return {"dialect": "mysql", "release": item["release"], "declarations": declarations(item), "sql": sql(item),
            "database": DB, "complete": True}


def direct(item):
    owner, parent = identity(CHILD), identity(PARENT)
    children, parents = columns(item, "x"), columns(item, "p")
    pairs = [[x, p, "enumeration", wire_field(left), wire_field(right), True, False]
             for x, p, left, right in zip(children, parents, item["child"], item["parent"])]
    return {"dialect": "mysql", "release": item["release"], "mysqlAddForeignKeys": {
        "owner": owner, "policy": ["DEFAULT", "DEFAULT"], "ignore": False, "additions": [{
            "parent": parent, "symbol": SYMBOL, "generated": False, "pairs": pairs, "effects": ["CASCADE", "SET NULL", "NONE"],
            "created": SYMBOL, "indexName": None}]},
        "mysqlForeignKeyCatalog": {"keys": [key(owner, ["z"]), key(parent, parents)], "constraints": []}}


def wire_field(field):
    if field[:2] == ["integer", "INT"]:
        return ["integer", "INTEGER", field[2]]
    return field
