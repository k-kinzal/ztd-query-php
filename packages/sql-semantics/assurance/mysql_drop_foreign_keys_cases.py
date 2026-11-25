"""Independent native foreign-key removal and remaining-constraint laws."""

from itertools import product
from random import Random

SCHEMA, AUX = "ir_fixture", "ir_rc_aux"
PARENT, CHILD, INCOMING = "ir_rc_parent", "ir_rc_child", "ir_rc_other"
LEGACY = {"mysql-5.6.51", "mysql-5.7.44"}
PLANS = ("one-constraint", "two-constraints", "repeated", "constraint-then-index", "index-then-constraint",
         "remaining-foreign-key", "retained-incoming")
NAMES = ("explicit symbol", "generated name")
FIXTURES = ("persistent", "temporary-shadow")
STATUS = "mysql-drop-foreign-keys"
PROFILE = "/utf8mb4/lower-case-table-names=0/sql-mode=empty/native/innodb/drop-foreign-keys"
REASONS = {
    "temporary": ["db-error", "42000", 1091], "repeated": ["db-error", "42000", 1091],
    "dependency": ["db-error", "HY000", 1553], "copy-none": ["db-error", "0A000", 1846],
    "temporary-inplace": ["db-error", "0A000", 1845], "missing": ["db-error", "42000", 1091],
    "legacy-copy-dependency": ["db-error", "HY000", 1025],
}
BLOCKED = "Index removal must retain a supporting key for each native foreign-key side."


def quote(value):
    return "`" + value.replace("`", "``") + "`"


def table(name, schema=SCHEMA):
    return quote(schema) + "." + quote(name)


def model(tag, plan, style, fixture, offset=0, reversed_order=False, algorithm="DEFAULT", lock="DEFAULT", ignore=False):
    keys = [f"by_x{offset}", f"by_y{offset}"]
    names = [f"Fk_A{offset}", f"Fk_B{offset}"] if style == "explicit symbol" else [CHILD+"_ibfk_1", CHILD+"_ibfk_2"]
    wire = {
        "one-constraint": [("constraint",0)],
        "two-constraints": [("constraint",1),("constraint",0)],
        "repeated": [("constraint",0),("constraint",0)],
        "constraint-then-index": [("constraint",0),("index",0)],
        "index-then-constraint": [("index",0),("constraint",0)],
        "remaining-foreign-key": [("constraint",0),("index",1)],
        "retained-incoming": [("constraint",0),("index",0)],
        "missing": [("constraint",0),("constraint",2)],
    }[plan]
    if reversed_order:
        wire = list(reversed(wire))
    legacy = tag in LEGACY
    temporary = fixture == "temporary-shadow"
    violation = None
    clauses = [list(item) for item in wire]
    if temporary and not legacy:
        violation = "temporary"
    elif plan == "missing" and not temporary:
        violation = "missing"
    elif plan == "repeated" and not legacy:
        violation = "repeated"
    elif temporary and algorithm == "INPLACE":
        violation = "temporary-inplace"
    elif not temporary and algorithm == "COPY" and lock == "NONE" and (legacy or plan not in ("remaining-foreign-key","retained-incoming")):
        violation = "copy-none"
    elif not temporary and plan in ("remaining-foreign-key","retained-incoming"):
        violation = "legacy-copy-dependency" if legacy and algorithm == "COPY" else "dependency"
    return {"dialect":"mysql","release":tag,"plan":plan,"style":style,"fixture":fixture,"offset":offset,
            "temporary":temporary,"clauses":clauses,"indexes":keys,"foreign":names,"incoming":plan=="retained-incoming",
            "policy":[algorithm,lock],"ignore":ignore,"violation":violation}


def finite_coords(spec):
    finite=spec["finite"]
    return set(product(finite["actions"],finite["names"],finite["fixtures"]))


def coordinate(tag, point):
    return model(tag,*point)


def explored(tag, seed, size):
    rng=Random(seed)
    for _ in range(size):
        fixture=rng.choice(FIXTURES) if tag in LEGACY else "persistent"
        algorithm=rng.choice(("DEFAULT","COPY") if fixture=="temporary-shadow" else ("DEFAULT","INPLACE","COPY"))
        locks=("DEFAULT","SHARED","EXCLUSIVE") if fixture=="persistent" and algorithm=="COPY" else ("DEFAULT","NONE","SHARED","EXCLUSIVE")
        yield model(tag,rng.choice(PLANS),rng.choice(NAMES),fixture,rng.randrange(1,20),rng.choice((True,False)),
                    algorithm,rng.choice(locks),tag=="mysql-5.6.51" and rng.choice((True,False)))


def declarations(item):
    first,second=item["indexes"]
    explicit=item["style"]=="explicit symbol"
    symbols=[f"CONSTRAINT {quote(name)} " if explicit else "" for name in item["foreign"]]
    prefix="UNIQUE " if item["incoming"] else ""
    parent=f"CREATE TABLE {table(PARENT)} (a INT PRIMARY KEY, b INT NOT NULL UNIQUE) ENGINE=InnoDB"
    child=f"CREATE TABLE {table(CHILD)} (x INT NOT NULL, y INT NOT NULL, z INT PRIMARY KEY, {prefix}KEY {quote(first)} (x),"
    child+=f" KEY {quote(second)} (y), {symbols[0]}FOREIGN KEY (x) REFERENCES {table(PARENT)} (a),"
    child+=f" {symbols[1]}FOREIGN KEY (y) REFERENCES {table(PARENT)} (b)) ENGINE=InnoDB"
    sql=[parent,child]
    if item["incoming"]:
        other=f"CREATE TABLE {table(INCOMING,AUX)} (w INT, KEY {quote('supports_w')} (w), CONSTRAINT {quote(item['foreign'][0])}"
        sql.append(other+f" FOREIGN KEY (w) REFERENCES {table(CHILD)} (x)) ENGINE=InnoDB")
    if item["temporary"]:
        sql.append(f"CREATE TEMPORARY TABLE {table(CHILD)} (x INT NOT NULL, y INT NOT NULL, z INT PRIMARY KEY,"
                   f" KEY {quote(first)} (x), KEY {quote(second)} (y)) ENGINE=InnoDB")
    return sql


def name(item, role, n, original=False):
    if role=="index":
        return item["indexes"][n]
    if item["temporary"]:
        return (item.get("temporaryLabel", "fk_a0" if n == 0 else "missing_key") if original
                else "")
    planned = item["foreign"][n] if n < 2 else "missing_key"
    return planned.lower() if original else planned


def sql(item, original=False):
    command="ALTER IGNORE TABLE" if item["ignore"] else "ALTER TABLE"
    actions=[]
    for role, n in item["clauses"]:
        verb="DROP FOREIGN KEY " if role=="constraint" else "DROP KEY " if original else "DROP INDEX "
        actions.append(verb+quote(name(item,role,n,original)))
    for label, value in zip(("ALGORITHM","LOCK"),item["policy"]):
        if value!="DEFAULT":
            actions.append(label+"="+value)
    return command+" "+table(CHILD)+" "+", ".join(actions)


def analyze(item):
    return {"dialect":"mysql","release":item["release"],"sql":sql(item,True),"complete":True,"declarations":declarations(item)}


def direct(item):
    owner=[SCHEMA,CHILD,"temporary-table" if item["temporary"] else "table"]
    actions=[]
    for role,n in item["clauses"]:
        label=name(item,role,n)
        if role=="constraint":
            actions.append({"foreign":[None,"ignored-on-temporary"] if item["temporary"] else [label,"constraint"]})
        else:
            physical="unique" if item["incoming"] and not item["temporary"] and n==0 else "ordinary"
            actions.append({"index":{"owner":owner,"name":label,"role":physical,"autoIncrement":None}})
    shares=[]
    if not item["temporary"] and any(role=="index" for role,_ in item["clauses"]):
        removed={n for role,n in item["clauses"] if role=="constraint"}
        for slot,key in enumerate(item["indexes"]):
            if slot not in removed or (slot==0 and item["incoming"]):
                shares.append({"owner":owner,"keys":[key]})
    return {"dialect":"mysql","release":item["release"],"mysqlDropForeignKeys":{
        "owner":owner,"actions":actions,"policy":item["policy"],"ignore":item["ignore"],
    },"mysqlIndexForeignKeys":shares}


def keys(item, temporary, removed):
    return sorted([["PRIMARY",0,1,"z","A","BTREE"], *[
        [key, int(temporary or not item["incoming"] or n!=0), 1, field,"A","BTREE"]
        for n,(key,field) in enumerate(zip(item["indexes"],("x","y"))) if n not in removed
    ]])


def edges(item, dropped):
    observed=[]
    for n,(field,parent_field) in enumerate(zip(("x","y"),("a","b"))):
        if n not in dropped:
            observed.append([SCHEMA,CHILD,item["foreign"][n],1,field,SCHEMA,PARENT,parent_field])
    if item["incoming"]:
        observed.append([AUX,INCOMING,item["foreign"][0],1,"w",SCHEMA,CHILD,"x"])
    return sorted(observed)


def expected(item):
    temporary=item["temporary"]
    okay=item["violation"] is None
    constraints_applied=okay or item["violation"]=="legacy-copy-dependency"
    constraints={n for key,n in item["clauses"] if key=="constraint"} if constraints_applied and not temporary else set()
    dropped_indexes={n for key,n in item["clauses"] if key=="index"} if okay else set()
    denied=["db-error","23000",1452]
    before={"base":keys(item,False,set()),"temporary":keys(item,True,set()) if temporary else None,"edges":edges(item,set())}
    after={"base":keys(item,False,set() if temporary else dropped_indexes),
           "temporary":keys(item,True,dropped_indexes) if temporary else None,"edges":edges(item,constraints)}
    rows=[[7,11,31],[13,89,97],[87,11,98]]
    if 1 not in constraints:
        rows.remove([13,89,97])
    if 0 not in constraints:
        rows.remove([87,11,98])
    return {"status":STATUS,"before":before,"after":after,"settings":[1,1],
            "effect":["executed"] if okay else REASONS[item["violation"]],
            "marker":7,"active":False,"affected":int(temporary or item["policy"][0]=="COPY") if okay else None,
            "probeX":["executed",1] if 0 in constraints else denied,
            "probeY":["executed",1] if 1 in constraints else denied,
            "rows":rows,"temporaryRows":[[33,44,67]] if temporary else None}
