"""Independent native scalar foreign-key construction coordinates."""

import random

from mysql_foreign_key_string_cases import key

DB = "ir_fixture"
CHILD = "ir_fk_scalar_child"
PARENT = "ir_fk_scalar_parent"
SYMBOL = "Native_Scalar"
PROFILE = "/utf8mb4/lower-case-table-names=0/sql-mode=empty/native/innodb/add-foreign-keys"
STATUS = "mysql-foreign-key-scalar-fields"
LEGACY = {"mysql-5.6.51", "mysql-5.7.44"}


def scalar(name, width=None, scale=None, unsigned=False, zerofill=False):
    return ["scalar", name, width, scale, unsigned, zerofill]


def binary(width):
    return ["string", "VARBINARY", width, None]


def integer(name, unsigned):
    return ["integer", name, unsigned]


def family(field):
    if field[0] == "string":
        return "packed"
    if field[0] == "integer":
        return {"TINYINT": "year", "MEDIUMINT": "date"}[field[1]] if field[2] == (field[1] == "TINYINT") else field[1]
    kind = field[1]
    return "packed" if kind in {"DECIMAL", "BIT", "TIME", "DATETIME", "TIMESTAMP"} else kind.lower()


def coordinates(spec):
    fields = spec["finite"]["fields"]
    return [(i, j) for i, left in enumerate(fields) for j, right in enumerate(fields)
            if "scalar" in {left[0], right[0]}]


def model(release, child, parent, aliases=None):
    if not child or len(child) != len(parent):
        raise ValueError("Foreign-key roles must have the same nonempty width.")
    return {"release": release, "child": child, "parent": parent, "aliases": aliases,
            "admitted": all(family(left) == family(right) for left, right in zip(child, parent))}


def coordinate(spec, release, point):
    fields = spec["finite"]["fields"]
    return model(release, [fields[point[0]]], [fields[point[1]]])


def explored(spec, release, seed):
    rng = random.Random(seed)
    choices = [coordinate(spec, release, point) for point in coordinates(spec)]
    pairs = [(item["child"][0], item["parent"][0]) for item in choices if item["admitted"]]
    for sequence in range(spec["exploration"]["casesPerSeed"]):
        selected = [rng.choice(pairs) for _ in range(rng.randint(1, spec["exploration"]["maxWidth"]))]
        child, parent = zip(*selected)
        yield model(release, list(child), list(parent))


def authored(release):
    for name, child, parent, aliases in (
        ("decimal-default", scalar("DECIMAL", 10, 0), scalar("DECIMAL", 10, 0), ["DECIMAL(0,0)", "DECIMAL"]),
        ("decimal-shape", scalar("DECIMAL", 65, 30), scalar("DECIMAL", 4, 2), None),
        ("bit-extrema", scalar("BIT", 1), scalar("BIT", 64), ["BIT(0)" if release == "mysql-5.6.51" else "BIT", "BIT(64)"]),
        ("time-display", scalar("TIME", 1), scalar("TIME", 2), None),
        ("dated-time-display", scalar("DATETIME", 1), scalar("DATETIME", 2), None),
        ("timestamp-extrema", scalar("TIMESTAMP", 0), scalar("TIMESTAMP", 6), None),
        ("float-storage", scalar("FLOAT"), scalar("FLOAT", 255, 30), ["FLOAT(24)", "FLOAT(255,30)"]),
        ("double-storage", scalar("DOUBLE"), scalar("DOUBLE"), ["FLOAT(25)", "REAL"]),
        ("year-default", scalar("YEAR"), scalar("YEAR"), ["YEAR(2)" if release == "mysql-5.6.51" else "YEAR(4)", "YEAR"]),
        ("decimal-widths", scalar("DECIMAL", 8, 2), scalar("DECIMAL", 9, 2), None),
        ("decimal-unsigned", scalar("DECIMAL", 10, 2, True), scalar("DECIMAL", 10, 2), None),
        ("physical-width", binary(4), scalar("DECIMAL", 9, 0), None),
        ("narrow-binary-field", binary(1), scalar("TIME", 0), None),
    ):
        yield name, model(release, [child], [parent], aliases)


def tab(name):
    return f"`{DB}`.`{name}`"


def identity(name):
    return [DB, name, "table"]


def columns(item, prefix):
    return [f"{prefix}{index}" for index in range(len(item["child"]))]


def sql(item, qualified=False):
    children = ", ".join(f"`{name}`" for name in columns(item, "x"))
    parents = ", ".join(f"`{name}`" for name in columns(item, "p"))
    destination = tab(PARENT) if qualified else f"`{PARENT}`"
    return (f"ALTER TABLE {tab(CHILD)} ADD CONSTRAINT `{SYMBOL}` FOREIGN KEY ({children}) "
            f"REFERENCES {destination} ({parents}) ON UPDATE CASCADE ON DELETE SET NULL")


def field_type(field):
    role, name, *details = field
    if role == "integer":
        return name + (" UNSIGNED" if details[0] else "")
    if role == "string":
        return f"{name}({details[0]})"
    width, scale, unsigned, zerofill = details
    dimensions = ("(" + str(width) + (f",{scale}" if scale is not None else "") + ")") if width is not None else ""
    attributes = (" UNSIGNED" if unsigned and not zerofill else "") + (" ZEROFILL" if zerofill else "")
    return name + dimensions + attributes


def declarations(item):
    children = columns(item, "x")
    parents = columns(item, "p")
    alias = item["aliases"]
    child_types = [field_type(field) for field in item["child"]] if alias is None else [alias[0]]
    parent_types = [field_type(field) for field in item["parent"]] if alias is None else [alias[1]]
    parent = ", ".join(f"{name} {kind} NOT NULL" for name, kind in zip(parents, parent_types))
    child = ", ".join(f"{name} {kind} NULL" for name, kind in zip(children, child_types))
    return [f"CREATE TABLE {tab(PARENT)} ({parent}, PRIMARY KEY ({', '.join(parents)})) ENGINE=InnoDB",
            f"CREATE TABLE {tab(CHILD)} ({child}, z INT PRIMARY KEY) ENGINE=InnoDB"]


def analyze(item):
    return {"dialect": "mysql", "release": item["release"][6:], "declarations": declarations(item), "sql": sql(item),
            "database": DB, "complete": True}


def direct(item):
    children = columns(item, "x")
    parents = columns(item, "p")
    pairs = [[left, right, "scalar", child, parent, True, False] for left, right, child, parent in
             zip(children, parents, item["child"], item["parent"])]
    return {"dialect": "mysql", "release": item["release"], "mysqlAddForeignKeys": {
            "owner": identity(CHILD), "additions": [{"parent": identity(PARENT), "symbol": SYMBOL, "pairs": pairs,
                            "effects": ["CASCADE", "SET NULL", "NONE"], "generated": False, "indexName": None,
                            "created": SYMBOL}], "policy": ["DEFAULT", "DEFAULT"], "ignore": False},
            "mysqlForeignKeyCatalog": {"keys": [key(identity(CHILD), ["z"]), key(identity(PARENT), parents)],
                "constraints": []}}
