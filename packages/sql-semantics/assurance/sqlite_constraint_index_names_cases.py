"""Fixed native key identities and independently generated SQLite constraint indexes."""

from itertools import product
from random import Random

from run_sqlite_create_table import quoted

AXES = ("schemas", "tables", "layouts", "ordinals")
SCHEMAS = ("main", "temp", "extension")
DB_SLOTS = {name: ordinal for ordinal, name in enumerate(SCHEMAS)}
# The sizes come from the recorded native grammar calibration and probe the upper name boundary.
LAYOUTS = {
    "rowid-alias": ("a INTEGER PRIMARY KEY,b TEXT,c TEXT", 0),
    "inline-desc": ("a INTEGER PRIMARY KEY DESC,b TEXT UNIQUE,c TEXT", 2),
    "table-desc-alias": ("a INTEGER,b TEXT UNIQUE,c TEXT,PRIMARY KEY(a DESC)", 1),
    "same-key": ("a TEXT,b TEXT,c TEXT,UNIQUE(a),UNIQUE(a DESC),UNIQUE(a COLLATE BINARY)", 1),
    "shared-without-rowid": ("a INTEGER UNIQUE,b TEXT UNIQUE,c TEXT,PRIMARY KEY(a)) WITHOUT ROWID", 2),
    "without-rowid-duplicate-terms": ("a TEXT,b TEXT,c TEXT,PRIMARY KEY(a,a),UNIQUE(a)) WITHOUT ROWID", 2),
    "distinct-collations": ("a TEXT COLLATE NOCASE UNIQUE,b TEXT,c TEXT,UNIQUE(a COLLATE BINARY),UNIQUE(a COLLATE NOCASE DESC)", 2),
    "reverse-columns": ("a TEXT UNIQUE,b TEXT COLLATE RTRIM UNIQUE,c TEXT,UNIQUE(a,b),UNIQUE(b,a)", 4),
    "later-column-collation": ("a TEXT UNIQUE COLLATE NOCASE,b TEXT,c TEXT,UNIQUE(a COLLATE NOCASE DESC)", 1),
    "two-equivalent-effects": ("a TEXT UNIQUE,b TEXT,c TEXT,UNIQUE(a COLLATE BINARY) ON CONFLICT IGNORE", 1),
    "no-keys": ("a TEXT,b TEXT,c TEXT", 0),
    "distinct-exact-type": ("a INT PRIMARY KEY,b TEXT,c TEXT,UNIQUE(a)) STRICT", 1),
}


def selector(schema, name, guard=True):
    prefix = "" if schema is None else quoted(schema) + "."
    return "DROP INDEX " + ("IF EXISTS " if guard else "") + prefix + quoted(name)


def assemble(schema, table, layout, ordinal, definition, count, *, qualifier=True, requested=None):
    source = f"CREATE TABLE {quoted(schema)}.{quoted(table)}({definition}"
    source += "" if definition.endswith(("WITHOUT ROWID", "STRICT")) else ")"
    name = f"sqlite_autoindex_{table}_{ordinal}" if requested is None else requested
    location = schema if qualifier else None
    return {
        "schema": schema, "table": table, "layout": layout, "ordinal": ordinal, "source": source,
        "count": count, "protected": requested is None and ordinal <= count,
        "selector": name, "qualifier": location, "dropped": selector(location, name),
        "withoutRowid": definition.endswith("WITHOUT ROWID"),
    }


def finite_case(schema, table, layout, ordinal):
    definition, count = LAYOUTS[layout]
    return assemble(schema, table, layout, ordinal, definition, count)


def generated(seed, count):
    random = Random(seed)
    for number in range(count):
        schema = random.choice(SCHEMAS)
        table = random.choice(("live", "", '界 "quote"_2', "other_3"))
        columns = ("a", "b", "c")
        collations = ("BINARY", "NOCASE", "RTRIM")
        without = number % 3 == 1
        forms = ["a TEXT PRIMARY KEY" if without else "a INTEGER PRIMARY KEY", "b TEXT", "c TEXT"]
        terms = set()
        if without:
            terms.add((("a", "BINARY"),))
        for index in range(1 + number % 5):
            members = tuple((random.choice(columns), random.choice(collations)) for _ in range(1 + random.randrange(3)))
            terms.add(members)
            sort = (" ASC", " DESC")[index % 2]
            rendered = ", ".join(f"{column} COLLATE {collation}{sort}" for column, collation in members)
            forms.append("UNIQUE(" + rendered + ")")
            if index % 2:
                forms.append("UNIQUE(" + rendered.replace(sort, "") + ")")
        definition = ",".join(forms) + (") WITHOUT ROWID" if without else "")
        ordinal = 1 + random.randrange(len(terms)) if number % 2 else len(terms) + 1 + random.randrange(3)
        yield assemble(schema, table, "seeded", ordinal, definition, len(terms), qualifier=number % 4 != 2)


def authored():
    yield assemble("main", "Items", "case-folded", 1, "a TEXT UNIQUE,b TEXT,c TEXT", 1)
    # A differently spelled reserved coordinate denotes the same known constraint index.
    case = assemble("temp", "Items", "identifier-fold", 1, "a TEXT UNIQUE,b TEXT,c TEXT", 1)
    case["selector"] = "SQLITE_AUTOINDEX_ITEMS_1"
    case["dropped"] = selector("TEMP", case["selector"])
    yield case
    yield assemble("extension", "Items", "unqualified-after-keys", 4,
                   "a TEXT,b TEXT,c TEXT,UNIQUE(a),UNIQUE(b),UNIQUE(c)", 3, qualifier=False)
    yield assemble("main", "Items", "unallocated-reserved", 1, "a TEXT UNIQUE,b TEXT,c TEXT", 1,
                   requested="sqlite_statistic_absent")


def analysis(item, *, complete=True, declarations=None):
    return {"sql": item["dropped"], "declarations": [item["source"]] if declarations is None else declarations,
            "catalogComplete": complete, "searchPath": ["main", "extension"]}


def direct(item):
    return {"operation": "drop-index", "targetRole": "absent", "schema": item["qualifier"], "name": item["selector"],
            "table": None, "ifExists": True}


def requests(specification):
    domain = specification["finite"]
    for number, axes in enumerate(product(*(domain[axis] for axis in AXES))):
        yield f"finite/{number}", finite_case(*axes)
    for seed in specification["exploration"]["seeds"]:
        yield from ((f"seed/{seed}/{number}", item)
                    for number, item in enumerate(generated(seed, specification["exploration"]["casesPerSeed"])))
    for number, item in enumerate(authored()):
        yield f"authored/{number}", item


def finite_ids(domain):
    return set(product(*(range(len(domain[axis])) for axis in AXES)))


def finite_id(item, observation, domain):
    fixture = observation["declared"]
    ordinal = int(item["selector"].rsplit("_", 1)[1])
    choices = (fixture["schema"], fixture["table"], fixture["layout"], ordinal)
    return tuple(domain[axis].index(choice) for axis, choice in zip(AXES, choices))


def negatives():
    item = finite_case("main", "items", "inline-desc", 1)
    yield analysis(item), "cannot drop a UNIQUE or PRIMARY KEY constraint index", item
    item = finite_case("main", "items", "rowid-alias", 1)
    yield {**analysis(item), "sql": selector("main", item["selector"], guard=False)}, "requires a resolved index", None
    yield analysis(item, complete=False), "requires a resolved index", None
    yield analysis(item, declarations=[item["source"], "CREATE VIRTUAL TABLE main.opaque USING fts5(a)"]), "complete implicit index namespace", None
    yield {**direct(item), "ifExists": False}, "absent DROP INDEX target requires IF EXISTS", None
