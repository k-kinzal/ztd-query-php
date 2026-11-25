"""Independent native child index allocation and dependency effect model."""

from itertools import product
from random import Random

SCHEMA = "ir_fixture"
PARENT, CHILD, COPY = "ir_implicit_p", "ir_implicit_c", "ir_implicit_copy"
DECOY = "protect_y"
LEGACY = {"mysql-5.6.51", "mysql-5.7.44"}
INLINE = {"mysql-9.0.1", "mysql-9.1.0"}
NAV = ("first field", "written key", "constraint symbol", "both")
COVERAGE = ("absent", "explicit former", "explicit latter", "wrong direction", "wrong order", "whole longer")
OPERATIONS = ("required", "unrelated", "copied")
STATUS = "mysql-implicit-foreign-index"
BLOCKED = "Index removal must retain a supporting key for each native foreign-key side."
PROFILES = {
    "drop": "/utf8mb4/lower-case-table-names=0/sql-mode=empty/native/innodb/drop-index",
    "visibility": "/utf8mb4/lower-case-table-names=0/sql-mode=empty/native/innodb/index-visibility",
}
WIRES = {"drop": "mysqlDropIndex", "visibility": "mysqlIndexVisibility"}


def identifier(value):
    return f"`{value}`"


def table(name):
    return identifier(SCHEMA) + "." + identifier(name)


def index(name, *fields, desc=False):
    return {"name": name, "fields": list(fields), "descending": desc}


def model(tag, style, coverage, action, variant=0, reverse=False):
    first, second = ("Y", "X") if reverse else ("X", "Y")
    written, symbol, alternate = f"written_{variant}", f"symbol_{variant}", f"alternate_{variant}"
    requested = symbol if style in ("constraint symbol","both") else written if style == "written key" else first
    alternative = [] if coverage == "absent" else [index(
        alternate,
        *((second, first) if coverage == "wrong order" else (first,) if coverage in ("explicit former", "explicit latter", "wrong direction") else (first,second)),
        desc=coverage == "wrong direction" and tag not in LEGACY,
    )]
    prunes = coverage in ("explicit former","explicit latter","whole longer") or (coverage == "wrong direction" and tag in LEGACY)
    generated = [] if prunes else [index(requested, first)]
    initial = [index("PRIMARY","z"), index(DECOY,second), *alternative, *generated]
    assigned = alternate if prunes else requested
    direct_support = [assigned]
    if coverage == "wrong direction" and tag not in LEGACY:
        direct_support.append(alternate)
    physical = DECOY if action == "unrelated" else assigned
    recipient = COPY if action == "copied" else CHILD
    operation = "visibility" if action == "hide" else "drop"
    return {
        "dialect":"mysql", "release":tag, "style":style, "coverage":coverage, "action":action,
        "variant":variant,"first":first, "second":second, "written":written,"symbol":symbol,
        "initial":initial,"alternate":alternative,"recipient":recipient,"physical":physical,
        "requirements":direct_support,"operation":operation,
        "accepted":action != "required" or len(direct_support)>1,
    }


def finite_coords(spec):
    definition=spec["finite"]
    return set(product(definition["names"],definition["coverage"],definition["actions"]))


def coordinate(tag, point):
    return model(tag,*point)


def explored(tag, seed, count):
    random=Random(seed)
    operations=OPERATIONS if tag in LEGACY else (*OPERATIONS,"hide")
    for _ in range(count):
        yield model(tag,random.choice(NAV),random.choice(COVERAGE),random.choice(operations),
                    random.randrange(1,20),random.choice((True,False)))


def native_index(key, show_direction):
    terms=", ".join(identifier(field)+(" DESC" if show_direction and n==0 else "") for n,field in enumerate(key["fields"]))
    return "KEY "+identifier(key["name"])+" ("+terms+")"


def declarations(item):
    first, second = item["first"],item["second"]
    style, coverage = item["style"],item["coverage"]
    symbol = f"CONSTRAINT {identifier(item['symbol'])} " if style in ("constraint symbol","both") else ""
    assigned = f" {identifier(item['written'])}" if style in ("written key","both") else ""
    constraint = f"{symbol}FOREIGN KEY{assigned} ({identifier(first.lower())}) REFERENCES {table(PARENT)} (`a`)"
    other = f"KEY {identifier(DECOY)} ({identifier(second)})"
    alternatives = [native_index(key, coverage=="wrong direction") for key in item["alternate"]]
    details = ([*alternatives, constraint] if coverage!="explicit latter" else [constraint,*alternatives])
    parent=f"CREATE TABLE {table(PARENT)} (a INT NOT NULL PRIMARY KEY) ENGINE=InnoDB"
    child=f"CREATE TABLE {table(CHILD)} (`X` INT NOT NULL, `Y` INT NOT NULL, z INT NOT NULL, PRIMARY KEY (z), {other}, " + ", ".join(details) + ") ENGINE=InnoDB"
    return [parent,child,f"CREATE TABLE {table(COPY)} LIKE {table(CHILD)}"]


def sql(item, original=False):
    target=identifier(item["physical"].lower() if original else item["physical"])
    owner=table(item["recipient"])
    return f"ALTER TABLE {owner} ALTER INDEX {target} INVISIBLE" if item["operation"]=="visibility" else f"DROP INDEX {target} ON {owner}"


def analyze(item):
    return {"dialect":"mysql","release":item["release"],"sql":sql(item,True),"declarations":declarations(item),"complete":True}


def direct(item):
    owner=[SCHEMA,item["recipient"],"table"]
    policy=["DEFAULT","DEFAULT"]
    if item["operation"]=="visibility":
        content={"owner":owner,"edits":[[item["physical"],"ordinary",False]],"policy":policy}
        return {"dialect":"mysql","release":item["release"],WIRES[item["operation"]]:content}
    content={"owner":owner,"name":item["physical"],"role":"ordinary","autoIncrement":None,"policy":policy,"ignore":False}
    supports=[{"owner":owner,"keys":item["requirements"]}] if item["recipient"]==CHILD else []
    return {"dialect":"mysql","release":item["release"],WIRES[item["operation"]]:content,"mysqlIndexForeignKeys":supports}


def keys(item, indexes, hidden=None):
    terms=[]
    for key in sorted(indexes, key=lambda entry:entry["name"]):
        for position,field in enumerate(key["fields"],1):
            terms.append([key["name"],int(key["name"]!="PRIMARY"),position,field,
                          "D" if key["descending"] and position==1 else "A",None,"BTREE",
                          None if item["release"] in LEGACY else "NO" if key["name"]==hidden else "YES"])
    return terms


def observed_edges(item):
    style=item["style"]
    symbol=item["symbol"] if style in ("constraint symbol","both") else CHILD+"_ibfk_1"
    return [[symbol,1,item["first"],SCHEMA,PARENT,"a"]]


def expected(item):
    initial=item["initial"]
    before={"source":keys(item,initial),"copy":keys(item,initial),"foreignKeys":observed_edges(item),"copiedForeignKeys":[]}
    after={**before}
    if item["accepted"]:
        if item["operation"]=="visibility":
            after["source"]=keys(item,initial,item["physical"])
        else:
            position="copy" if item["recipient"]==COPY else "source"
            after[position]=keys(item,[key for key in initial if key["name"]!=item["physical"]])
    return {"status":STATUS,"before":before,"after":after,"foreign_key_checks":1,"marker":7,"active":False,
            "affected":0 if item["accepted"] else None,
            "effect":["executed"] if item["accepted"] else ["db-error","HY000",1553],
            "foreignKeyEnforcement":["db-error","23000",1452],
            "rows":{"parent":[[7]],"source":[[7,7,31]],"copy":[[7,7,47]]}}
