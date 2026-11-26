"""Independent view headings and their signed field projections."""

from itertools import product
from random import Random

SCHEMA = "ir_fixture"
BASE = "view_read_data"
FIELDS = ("a", "b", "c", "d", "e")
KINDS = ("int-not-null", "int", "int-not-null", "int-not-null", "int-not-null")
ROWS = ((11, 29, 41, 57, 83), (17, None, 43, 59, 89), (11, 29, 41, 57, 83))
BASE_DDL = (f"CREATE TABLE {SCHEMA}.{BASE} "
            "(a INT NOT NULL, b INT NULL, c INT NOT NULL, d INT NOT NULL, e INT NOT NULL)")
LEGACY = ("mysql-5.6.51", "mysql-5.7.44")
ALGORITHMS = ("UNDEFINED", "MERGE", "TEMPTABLE")
QUALIFIERS = ("bare", "alias")
READS = ("canonical", "folded", "twice-mixed")
DECLARED = {
    "exact-utf8": ("K", "É", "e", "œ", "oe"),
    "explicit-leading": (" K", "upper_name", "end_name"),
    "inherited-leading": (" K", " É", "e"),
    "inherited-empty": (" \t\x7f", "x", "z"),
    "over-display-width": ("a" * 65, "É", "x"),
    "explicit-empty": ("", "É", "x"),
    "trailing-whitespace": ("ed\t", "É", "x"),
    "distinct-input": ("K", "É", "e", "œ", "oe"),
}
LEGACY_SHAPES = ("over-display-width", "explicit-empty", "trailing-whitespace")
INHERITED = ("inherited-leading", "inherited-empty")
COMPARE_SPELLINGS = {"K": "k", "É": "é", "œ": "Œ"}


def quote(identifier):
    return "`" + identifier.replace("`", "``") + "`"


def shapes(tag):
    return tuple(shape for shape in DECLARED if shape not in LEGACY_SHAPES or tag in LEGACY)


def fixtures(tag, contract):
    finite = contract["finite"]
    shapes = finite["headingShapes"] + (finite["legacyAdditionalShapes"] if tag in LEGACY else [])
    yield from product(shapes, finite["algorithms"])


def finite_grid(tag, contract, direct=False):
    for shape, algorithm in fixtures(tag, contract):
        qualifiers = (None,) if direct else contract["finite"]["qualification"]
        yield from product((shape,), (algorithm,), contract["finite"]["references"], qualifiers)


def independent_ids(tag, direct=False):
    qualifiers = (None,) if direct else QUALIFIERS
    return {(shape, algorithm, pattern, qualifier) for qualifier in qualifiers for pattern in READS
            for shape in shapes(tag) for algorithm in ALGORITHMS}


def identity(shape, algorithm):
    return "view_read_" + shape.replace("-", "_") + "_" + algorithm.lower()


def headings(tag, shape):
    written = DECLARED[shape]
    if shape not in INHERITED and tag not in LEGACY:
        return written
    return tuple(name.lstrip("".join(map(chr, range(33))) + "\x7f") for name in written)


def declaration(shape, algorithm):
    written = DECLARED[shape]
    fields = list(FIELDS[:len(written)])
    header = ""
    if shape in INHERITED:
        fields = [f"{field} AS {quote(name)}" for field, name in zip(fields, written, strict=True)]
    else:
        header = " (" + ", ".join(map(quote, written)) + ")"
    distinct = "DISTINCT " if shape == "distinct-input" else ""
    return (f"CREATE ALGORITHM={algorithm} VIEW {SCHEMA}.{identity(shape, algorithm)}{header} AS SELECT {distinct}"
            + ", ".join(fields) + f" FROM {SCHEMA}.{BASE}")


def alternate(name):
    return "".join(COMPARE_SPELLINGS.get(char, char.upper() if char.isascii() else char) for char in name)


def finite(tag, shape, algorithm, pattern, qualifier):
    names = headings(tag, shape)
    if pattern == "canonical":
        positions = [[0, names[0], None], [1, names[1], None]]
    elif pattern == "folded":
        positions = [[0, alternate(names[0]), None], [1, alternate(names[1]), "typed"]]
    elif pattern == "twice-mixed":
        positions = [[2, alternate(names[2]), "third"], [0, names[0], None],
                     [0, alternate(names[0]), None], [1, names[1], None]]
    else:
        raise ValueError(pattern)
    return {"tag": tag, "shape": shape, "algorithm": algorithm, "qualified": qualifier == "alias", "projection": positions}


def sql(item):
    written = "v." if item["qualified"] else ""
    columns = ", ".join(written + quote(name) + ("" if label is None else " AS " + quote(label))
                        for _, name, label in item["projection"])
    target = f"{SCHEMA}.{identity(item['shape'], item['algorithm'])}"
    return f"SELECT {columns} FROM {target}" + (" AS v" if written else "")


def effective_result(item, index, requested, label):
    if label is not None:
        return label
    if materialized(item):
        return requested
    return headings(item["tag"], item["shape"])[index]


def materialized(item):
    return item["algorithm"] == "TEMPTABLE" or item["shape"] == "distinct-input"


def graph(item):
    target = identity(item["shape"], item["algorithm"])
    ordered = list(dict.fromkeys(index for index, _, _ in item["projection"]))
    names = headings(item["tag"], item["shape"])
    source = {"schema": SCHEMA, "table": target, "columns": [names[index] for index in ordered],
              "relationKind": "view"}
    kinds = [KINDS[index] for index in ordered]
    if any(kind != "int" for kind in kinds):
        source["columnTypes"] = kinds
    if materialized(item):
        source["materializedView"] = True
    selection = [
        [["slot", ordered.index(index)], effective_result(item, index, written, label)]
        for index, written, label in item["projection"]
    ]
    return {"source": source, "columns": selection, "distinct": False, "where": None, "orderBy": [], "limit": None}


def analyzed(item, statement=None):
    return {"dialect": "mysql", "release": item["tag"], "sql": sql(item) if statement is None else statement,
            "declarations": [BASE_DDL, declaration(item["shape"], item["algorithm"])], "observeExactIntegralSlots": True}


def direct(item):
    return {"dialect": "mysql", "release": item["tag"], "observeExactIntegralSlots": True, **graph(item)}


def predict(item):
    rows = ROWS[:2] if item["shape"] == "distinct-input" else ROWS
    return {
        "status": "rows", "fields": [[effective_result(item, index, requested, label), "LONG"]
                                     for index, requested, label in item["projection"]],
        "rows": [[row[index] for index, _, _ in item["projection"]] for row in rows],
    }


def seeded(tag, seed, count, distinct=False):
    rng = Random(seed)
    admitted = ("distinct-input",) if distinct else tuple(shape for shape in shapes(tag) if shape != "distinct-input")
    for step in range(count):
        shape = admitted[step % len(admitted)]
        algorithm = rng.choice(ALGORITHMS)
        names = headings(tag, shape)
        fields = []
        for output in range(rng.randint(2, 6)):
            index = rng.randrange(len(names))
            written = rng.choice((names[index], alternate(names[index])))
            label = rng.choice((None, None, f"label_{step}_{output}"))
            fields.append([index, written, label])
        yield {"tag": tag, "shape": shape, "algorithm": algorithm, "qualified": bool(rng.getrandbits(1)), "projection": fields}
