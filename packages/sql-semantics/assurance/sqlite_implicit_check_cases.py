"""Independent rules for SQLite diagnostic text derived from checked comparison values."""

from itertools import product
from random import Random

from sqlite_engine import typed
from sqlite_named_check_cases import check, compare, evaluate, integer

SYMBOLS = {"=": "=", "==": "=", "<>": "<>", "!=": "<>", "<": "<", "<=": "<=", ">": ">", ">=": ">="}
RENAMES = {"original": None, "bare": "renamed", "quoted": "renamed slot"}


def case(glyph=">", *, quoted=False, name="A", declared=("a", "b"), owner=0, slot=0, kind="INTEGER",
         before="", after="", zeroes=0, boundary="0", table="ir_implicit", default=None):
    label = {"name": name, "glyph": glyph, "quoted": quoted, "before": before, "after": after, "zeroes": zeroes}
    condition = compare(SYMBOLS[glyph], ["column", slot], integer(boundary))
    definitions = [[column, kind, False] for column in declared]
    if default is not None:
        definitions[0].append(integer(default))
    return {"operation": "create-table", "table": table, "schema": "main", "columns": definitions,
            "strict": False, "ifNotExists": False, "withoutRowid": False, "keys": [],
            "checks": [check(label, condition, owner)]}


def finite(domain):
    for owner, quoting, glyph, layout, boundary in product(domain["owner"], domain["spelling"],
                                                            domain["symbols"], domain["layouts"], domain["values"]):
        yield case(glyph, quoted=quoting == "quoted", owner=None if owner == "table" else 0, boundary=boundary, **layout)


def finite_ids(domain):
    layouts = [(x["before"], x["after"], x["zeroes"]) for x in domain["layouts"]]
    return {(owner, spelling, symbol, *layout, boundary) for layout in layouts for boundary in domain["values"]
            for owner in ("inline", "table") for spelling in ("bare", "quoted") for symbol in SYMBOLS}


def finite_id(response, _domain):
    clause, = response["checks"]
    label = clause["label"]
    return ("table" if clause["column"] is None else "inline", "quoted" if label["quoted"] else "bare",
            label["glyph"], label["before"], label["after"], label["zeroes"], clause["condition"][3][1])


def explored(seed, amount, lexical):
    rng = Random(seed)
    names = ("c0", "Cased", "a$b", "", 'a"b', "Ä", "pi π", "before")
    values = ("0", "1", "2", "7", "2147483648", "9223372036854775807")
    for index in range(amount):
        size, slot = 2 + index % 4, rng.randrange(2)
        names_for_case = [f"n{position}" for position in range(size)]
        name = rng.choice(names)
        names_for_case[slot] = name
        quoted = name in ("", 'a"b', "pi π") or rng.choice((False, True))
        kwargs = {"name": name, "declared": tuple(names_for_case), "slot": slot,
                  "owner": rng.choice((None, rng.randrange(size))), "quoted": quoted,
                  "before": rng.choice(lexical["spaces"]), "after": rng.choice(lexical["spaces"]),
                  "zeroes": (0 if index % 8 == 0 else 32 if index % 8 == 1 else rng.randrange(33)), "boundary": rng.choice(values)}
        yield case(rng.choice(tuple(SYMBOLS)), **kwargs)


def analyzed():
    yield "CREATE TABLE t (a INTEGER CHECK (a > 0))", case(table="t", name="a", declared=("a",), before=" ", after=" ")
    yield "CREATE TABLE t (a CHECK (a > 0) DEFAULT 1)", case(table="t", name="a", declared=("a",), kind="", default=1, before=" ", after=" ")
    mixed = case("<", table="ir_checked", name="c0", declared=("c0",), boundary="10", owner=None)
    mixed["checks"].insert(0, check("L", compare(">", ["column", 0], integer(0))))
    yield "CREATE TABLE ir_checked(c0 INTEGER, CONSTRAINT L CHECK(c0>0), CHECK(c0<10))", mixed
    yield ('CREATE TABLE ir_implicit (a INTEGER, b INTEGER, CHECK (  A   ==\t0000  ))',
           case("==", owner=None, before="   ", after="\t", zeroes=3))
    yield ('CREATE TABLE ir_implicit (a INTEGER CHECK ([B] != 01), b INTEGER)',
           case("!=", name="B", quoted=True, slot=1, before=" ", after=" ", zeroes=1, boundary="1"))
    yield ('CREATE TABLE ir_implicit ("a""b" INTEGER CHECK ("a""b" > 0), b INTEGER)',
           case(name='a"b', declared=('a"b', "b"), quoted=True, before=" ", after=" "))
    yield ('CREATE TABLE ir_implicit ("a`b" INTEGER CHECK (`a``b` > 0009223372036854775807), b INTEGER)',
           case(name="a`b", declared=("a`b", "b"), quoted=True, before=" ", after=" ", zeroes=3, boundary="9223372036854775807"))
    yield ('CREATE TABLE ir_implicit ("true" INTEGER CHECK (TRUE>0), b INTEGER)',
           case(name="TRUE", declared=("true", "b")))
    yield ('CREATE TABLE ir_implicit ("Ä" INTEGER CHECK (Ä>0), b INTEGER)',
           case(name="Ä", declared=("Ä", "b")))


def requests(specification):
    for index, item in enumerate(finite(specification["finite"])):
        yield f"finite/{index}", item, item
    exploration = specification["exploration"]
    for seed in exploration["seeds"]:
        for index, item in enumerate(explored(seed, exploration["casesPerSeed"], specification["lexicalDomain"])):
            yield f"seed/{seed}/{index}", item, item
    for index, (sql, item) in enumerate(analyzed()):
        yield f"analyze/{index}", {"sql": sql, "declarations": []}, item


def negatives():
    yield case(name="SELECT"), "rejected", "checked bare column spelling"
    yield case(before="/**/"), "rejected", "within the whitespace profile"
    yield case(name="b"), "rejected", "must identify the resolved column"
    wrong_operation = case()
    wrong_operation["checks"][0]["condition"][1] = "<"
    yield wrong_operation, "rejected", "must present its resolved column comparison"
    yield case(boundary="-1"), "rejected", "nonnegative literal"
    yield ({"sql": "CREATE TABLE ir_implicit (a INTEGER CHECK (/* label */ a>0))", "declarations": []},
           "rejected", "explicit CHECK label or a checked implicit comparison")


def write_rows(item):
    refs = item["checks"][-1]["condition"][2][1]
    width = len(item["columns"])
    for index, value in enumerate((-2, 0, 1, 2, 9223372036854775807, None)):
        row = [width + 3 + index - slot for slot in range(width)]
        row[refs] = value
        yield row


def label_text(label, value, rename):
    replacement = RENAMES[rename]
    name = label["name"] if replacement is None else replacement
    if label["quoted"] or rename == "quoted":
        return name
    return f"{name}{label['before']}{label['glyph']}{label['after']}{'0' * label['zeroes']}{value}"


def expected(item):
    clause = item["checks"][-1]
    _, _, (_, slot), _ = clause["condition"]
    result = {}
    for mode, renamed in RENAMES.items():
        fields = [column[0] for column in item["columns"]]
        if renamed is not None:
            fields[slot] = renamed
        inserted = []
        for row in write_rows(item):
            error = None
            for condition in item["checks"]:
                label, expression = condition["label"], condition["condition"]
                if evaluate(expression, row) == 0:
                    display = label if isinstance(label, str) else label_text(label, expression[3][1], mode)
                    error = {"code": 19, "message": f"CHECK constraint failed: {display}"}
                    break
            inserted.append({"error": error, "rows": [[typed(value) for value in row]] if error is None else []})
        result[mode] = {"fields": fields, "writes": inserted}
    return result
