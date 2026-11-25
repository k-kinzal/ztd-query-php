"""Independent native string foreign-key storage, comparison and full-key sizes."""

from itertools import product
from random import Random

from mysql_add_foreign_keys_cases import LEGACY, PROFILE, quote, table

DB = "ir_fixture"
CHILD, PARENT, SYMBOL = "ir_fk_string_child", "ir_fk_string_parent", "Native_String"
COLLATIONS = ("utf8mb4_bin", "utf8mb4_general_ci", "utf8mb4_unicode_ci")
TEXT = ("CHAR", "VARCHAR")
BINARY = ("BINARY", "VARBINARY")
STATUS = "mysql-foreign-key-string-fields"
TEST_HEX = ("4162", "416220", "6162", "41620000", "4162000000000000", "6165", None)


def storage(kind, length, collation=None):
    return [kind, length, collation]


def full_width(spec):
    return spec[1] * (4 if spec[2] is not None else 1)


def model(tag, child, parent, layout="default"):
    limit = 767 if tag == "mysql-5.6.51" or layout == "compact" else 3072
    total = sum(map(full_width, child))
    violation = None
    if any(left[2] != right[2] for left, right in zip(child, parent)):
        violation = "collation"
    elif any(left[1] == 0 for left in child):
        violation = "zero"
    elif any(full_width(left) > limit for left in child):
        violation = "term-capacity"
    elif total > 3072:
        violation = "key-capacity"
    return {"release": tag, "child": child, "parent": parent, "layout": layout, "nativeLimit": limit, "violation": violation}


def finite_coords(spec):
    grid = set()
    for left, right, lcoll, rcoll in product(spec["finite"]["characterKinds"], spec["finite"]["characterKinds"],
                                             spec["finite"]["collations"], spec["finite"]["collations"]):
        grid.add(("character", left, right, lcoll, rcoll))
    for left, right, widths in product(spec["finite"]["binaryKinds"], spec["finite"]["binaryKinds"], spec["finite"]["widthPairs"]):
        grid.add(("binary", left, right, *widths))
    return grid


def coordinate(tag, point):
    shape, left, right, first, second = point
    if shape == "character":
        return model(tag, [storage(left, 8, first)], [storage(right, 4, second)])
    return model(tag, [storage(left, first)], [storage(right, second)])


def explored(tag, seed, amount):
    rng = Random(seed)
    for _ in range(amount):
        size = rng.randint(1, 3)
        family = rng.choice(("text", "binary"))
        if family == "text":
            domain = rng.choice(COLLATIONS)
            terms = [[storage(rng.choice(TEXT), rng.randint(4, 30), domain) for _ in range(size)] for _ in range(2)]
        else:
            terms = [[storage(rng.choice(BINARY), rng.randint(4, 12)) for _ in range(size)] for _ in range(2)]
        yield model(tag, *terms, layout=rng.choice(("default", "compact", "dynamic")))


def width_cases(tag):
    for name, terms, layout in (
        ("zero-width", [storage("VARBINARY", 0)], "default"),
        ("exact-compact-column", [storage("VARBINARY", 767)], "compact"),
        ("above-compact-column", [storage("VARBINARY", 768)], "compact"),
        ("total-above-per-column", [storage("VARBINARY", 767)] * 2, "compact"),
        ("total-largest-compact-four", [storage("VARBINARY", 767)] * 4, "compact"),
        ("total-exact-native-bound", [storage("VARBINARY", 768)] * 4, "dynamic"),
        ("total-above-native-bound", [storage("VARBINARY", 768)] * 4 + [storage("VARBINARY", 1)], "dynamic"),
        ("total-above-native-bound-compact", [storage("VARBINARY", 767)] * 5, "compact"),
        ("mixed-byte-and-character", [storage("VARBINARY", 16)], "default"),
        ("column-exact-character-bound", [storage("VARCHAR", 191, COLLATIONS[0])], "compact"),
        ("column-above-character-bound", [storage("VARCHAR", 192, COLLATIONS[0])], "compact"),
        ("same-character-width-in-dynamic", [storage("VARCHAR", 192, COLLATIONS[0])], "dynamic"),
    ):
        parent = [storage(kind, 4, collation) for kind, _, collation in terms]
        if name == "mixed-byte-and-character":
            parent = [storage("VARCHAR", 4, COLLATIONS[0])]
        yield name, model(tag, terms, parent, layout)


def columns(item, role):
    return [f"{role}{pos}" for pos in range(len(item["child"]))]


def render_storage(domain):
    family, length, collation = domain
    tail = f" CHARACTER SET utf8mb4 COLLATE {collation}" if collation is not None else ""
    return f"{family}({length})" + tail


def declarations(item):
    left, right = columns(item, "x"), columns(item, "p")
    parent = ", ".join(f"{name} {render_storage(spec)} NOT NULL" for name, spec in zip(right, item["parent"]))
    child = ", ".join(f"{name} {render_storage(spec)}" for name, spec in zip(left, item["child"]))
    suffix = " ENGINE=InnoDB"
    layout = "" if item["layout"] == "default" else f" ROW_FORMAT={item['layout'].upper()}"
    return [
        f"CREATE TABLE {table(DB,PARENT)} ({parent}, PRIMARY KEY ({', '.join(right)})){suffix}",
        f"CREATE TABLE {table(DB,CHILD)} ({child}, z INT PRIMARY KEY){suffix}{layout}",
    ]


def sql(item, original=False):
    parent = quote(PARENT) if original else table(DB,PARENT)
    left, right = (", ".join(map(quote, columns(item, role))) for role in ("x", "p"))
    return f"ALTER TABLE {table(DB,CHILD)} ADD CONSTRAINT {quote(SYMBOL)} FOREIGN KEY ({left}) REFERENCES {parent} ({right}) ON UPDATE CASCADE ON DELETE SET NULL"


def analyze(item):
    return {"dialect":"mysql","release":item["release"],"schemaPath":[DB],"complete":True,
            "declarations":declarations(item),"sql":sql(item,True)}


def key(owner, names):
    return {"owner":owner,"name":"PRIMARY","role":"primary","autoIncrement":None,"full":True,"fields":[[n,False] for n in names]}


def direct(item):
    owner, parent = [DB,CHILD,"table"],[DB,PARENT,"table"]
    pairs = [[left,right,"string",child,target,True,False] for left,right,child,target in
             zip(columns(item,"x"),columns(item,"p"),item["child"],item["parent"])]
    entry = {"parent":parent,"symbol":SYMBOL,"generated":False,"pairs":pairs,"effects":["CASCADE","SET NULL","NONE"],
             "created":SYMBOL,"indexName":None}
    facts = {"keys":[key(owner,["z"]),key(parent,columns(item,"p"))],"constraints":[],
             "keyLimit":"compact" if item["nativeLimit"]==767 else "large-prefix"}
    return {"dialect":"mysql","release":item["release"],
            "mysqlAddForeignKeys":{"owner":owner,"additions":[entry],"policy":["DEFAULT","DEFAULT"],"ignore":False},
            "mysqlForeignKeyCatalog":facts}


def store(spec, octets):
    kind, width, _ = spec
    if octets is None:
        return None
    word = bytes.fromhex(octets)[:width]
    if kind == "BINARY":
        word = word.ljust(width, b"\x00")
    if kind == "CHAR":
        word = word.rstrip(b" ")
    return word.hex().upper()


def compares(spec, hexed):
    word=bytes.fromhex(hexed)
    collation=spec[2]
    if collation is not None:
        word=word.rstrip(b" ")
        if collation == "utf8mb4_unicode_ci":
            word = word.replace(b"\x00", b"")
        if collation!="utf8mb4_bin":
            word=word.lower()
    return word


def probes(item):
    parent = [store(domain, "4162") for domain in item["parent"]]
    result=[]
    for octets in TEST_HEX:
        child=[store(domain, octets) for domain in item["child"]]
        matches=octets is None or all(compares(domain, value)==compares(target,right)
                                       for domain, value, target, right in zip(item["child"],child,item["parent"],parent))
        # The stock non-strict profile may warn about truncation of test bytes.
        # The observer retains warnings separately from the write outcome and rows.
        result.append([["executed",1], child] if matches else [["db-error","23000",1452],None])
    return result


def matching_input(item):
    if item["violation"]:
        return None
    return next((datum for datum, (outcome, _) in zip(TEST_HEX, probes(item))
                 if datum is not None and outcome[0] == "executed"), None)


def events(item):
    matching = matching_input(item)
    if matching is None:
        return []
    parent = [store(domain, "4163") for domain in item["parent"]]
    physical = [value + "20" * (domain[1] - len(value) // 2) if domain[0] == "CHAR" else value
                for domain, value in zip(item["parent"], parent)]
    if any(len(value) // 2 > domain[1] for domain, value in zip(item["child"], physical)):
        original_parent = [store(domain, "4162") for domain in item["parent"]]
        original_child = [store(domain, matching) for domain in item["child"]]
        update = ["update", ["db-error", "23000", 1451], [original_parent], [original_child]]
    else:
        propagated = [store(domain, value) for domain, value in zip(item["child"], physical)]
        update = ["update", ["executed", 1], [parent], [propagated]]
    return [update, ["delete", ["executed", 1], [], [[None] * len(parent)]]]


def expected(item):
    okay=item["violation"] is None
    before=[["PRIMARY",1,"z",0,"A"]]
    index=before+([[SYMBOL, i+1, name, 1, "A"] for i,name in enumerate(columns(item,"x"))] if okay else [])
    edges=[[SYMBOL,i+1,name,DB,PARENT,target] for i,(name,target) in enumerate(zip(columns(item,"x"),columns(item,"p")))] if okay else []
    data=[[],[]] if not okay else [[store(t,"4162") for t in item["parent"]],probes(item)]
    types=[[table_name,name,f"{domain[0].lower()}({domain[1]})",domain[2]]
           for table_name, role, domains in ((CHILD,"x",item["child"]),(PARENT,"p",item["parent"]))
           for name,domain in zip(columns(item,role),domains)]
    rules = [[SYMBOL,"CASCADE","SET NULL","NONE"]] if okay else []
    return {"status":STATUS,"rules":rules,"events":events(item),"before":before,"after":sorted(index),"edges":edges,"data":data,"types":types,
            "format":"Compact" if item["nativeLimit"]==767 else "Dynamic","checks":[1,1]}
