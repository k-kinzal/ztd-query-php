"""Independent key-retention and generated-value model for native index removal."""

from itertools import product
from random import Random

from mysql_drop_index_cases import LIFETIMES
from mysql_innodb_declaration_width_cases import OLD, SCHEMA, quote

TABLE = "ir_index_sequence"
TEMPLATE = TABLE + "_template"
ROLES = ("primary", "clustered-unique", "unique", "ordinary")
USES = ("unrelated", "sole-leading", "leading-with-distinct-alternate")
DECLARATIONS = {"attribute": "INT NOT NULL AUTO_INCREMENT", "serial-type": "SERIAL", "serial-default-value": "INT SERIAL DEFAULT VALUE"}
ROUTES = ("direct", "standalone-drop-index", "alter-table-drop-key")
LAST_SUPPORT = ["db-error", "42000", 1075]
DIAGNOSTIC = "SqlSemantics\\Platform\\MySql\\Model\\Diagnostic\\InvalidConstruction"
RETAINS = "DROP INDEX must retain a backing key for the AUTO_INCREMENT field."
STATUS = "mysql-index-auto-increment"


def native_key(name, role, fields, *, implicit=False, descending=False, visible=True):
    return {"name": name, "role": role, "fields": fields, "implicit": implicit, "descending": descending, "visible": visible}


def declared_layout(declaration, role, use, name, alternate):
    serial = declaration != "attribute"
    supports = use != "unrelated"
    keys = []
    if serial:
        clustered = role == "clustered-unique" or (role == "ordinary" and use == "unrelated")
        builtin = native_key("a", "clustered-unique" if clustered else "unique", ["a"], implicit=True)
        keys.append(builtin)
    if role == "primary":
        name = "PRIMARY"
    elif serial and supports:
        name = "a"
    if role == "unique" and name != "PRIMARY":
        keys.append(native_key("PRIMARY", "primary", ["b"]))
    if not serial or not supports:
        field = "a" if supports else "b" if role in ("primary", "clustered-unique") else "c"
        keys.append(native_key(name, role, [field]))
    keys.append(native_key("late_position", "ordinary", ["b", "a"]))
    if use == "leading-with-distinct-alternate" or (not serial and not supports):
        keys.append(native_key(alternate, "ordinary", ["a", "b"]))
    return keys, name


def model(tag, life, role, use, declaration, *, name="chosen", alternate="supports", offset=0, clone=False,
          invisible=False, descending=False, written_name=None, extra_alternates=0):
    keys, selected = declared_layout(declaration, role, use, name, alternate)
    for key in keys:
        if key["name"] == alternate:
            key.update(visible=not invisible, descending=descending)
    keys.extend(native_key(f"retained_extra_{idx}", "ordinary", ["a", "c"]) for idx in range(extra_alternates))
    alternatives = [key["name"] for key in keys if key["fields"][0] == "a" and key["name"] != selected]
    support = None if use == "unrelated" else {"field": "a", "alternates": alternatives}
    temporary = life != "persistent"
    target = {"owner": [SCHEMA, TABLE, "temporary-table" if temporary else "table"], "name": selected, "role": role,
              "policy": ["DEFAULT", "DEFAULT"], "ignore": False, "autoIncrement": support}
    return {"dialect": "mysql", "release": tag, "mysqlDropIndex": target, "life": life, "declaration": declaration, "use": use,
            "keys": keys, "written": written_name or selected.swapcase(), "offset": offset, "clone": clone}


def layouts(finite):
    for role, use in product(finite["targetRoles"], finite["targetUse"]):
        yield role, use, "attribute"
    for declaration in finite["declarationForms"][1:]:
        for role, use in product(("clustered-unique", "unique"), finite["targetUse"][1:]):
            yield role, use, declaration
        for role in ("primary", "unique", "ordinary"):
            yield role, "unrelated", declaration


def finite_points(finite):
    return {(life, role, use, declaration) for life in finite["ownership"] for role, use, declaration in layouts(finite)}


def generated(tag, finite, seed, count):
    rng = Random(seed)
    for ordinal in range(count):
        role = ROLES[ordinal % len(ROLES)]
        life = LIFETIMES[(ordinal + ordinal // len(ROLES) + seed) % len(LIFETIMES)]
        use = ("unrelated", "leading-with-distinct-alternate")[(ordinal // len(ROLES)) % 2]
        declaration = rng.choice(list(DECLARATIONS)) if (role in ("clustered-unique", "unique") and use != "unrelated") else "attribute"
        offset = rng.randrange(11, 65)
        yield model(tag, life, role, use, declaration, name=f"ChoisiÉ {seed}_{ordinal}", alternate=f"retainÀ_{offset}",
                    offset=offset, invisible=tag not in OLD and ordinal % 2 == 0, descending=bool(ordinal % 2), clone=ordinal % 3 == 0, extra_alternates=rng.randrange(3))


def declaration(item, table=TABLE, temporary=None):
    temporary = item["life"] != "persistent" if temporary is None else temporary
    terms = ["a " + DECLARATIONS[item["declaration"]], "b INT NOT NULL", "c INT NULL"]
    for key in item["keys"]:
        if key["implicit"]:
            continue
        columns = [field + (" DESC" if index == 0 and key["descending"] else "") for index, field in enumerate(key["fields"])]
        role = key["role"]
        keyword = "PRIMARY KEY" if role == "primary" else ("KEY " if role == "ordinary" else "UNIQUE KEY ") + quote(key["name"])
        terms.append(keyword + " (" + ", ".join(columns) + ")" + (" INVISIBLE" if not key["visible"] else ""))
    return f"CREATE {'TEMPORARY ' if temporary else ''}TABLE {quote(SCHEMA)}.{quote(table)} ({', '.join(terms)}) ENGINE=InnoDB AUTO_INCREMENT=101"


def sibling(item):
    return model(item["release"], "persistent", "ordinary", "leading-with-distinct-alternate", "attribute",
                 name="sibling_leading", alternate="sibling_other", offset=item["offset"])


def declarations(item):
    out = [declaration(sibling(item))] if item["life"] == "temporary-shadow" else []
    if item["clone"]:
        out.append(declaration(item, TEMPLATE, False))
        temporary = "TEMPORARY " if item["life"] != "persistent" else ""
        out.append(f"CREATE {temporary}TABLE {quote(SCHEMA)}.{quote(TABLE)} LIKE {quote(SCHEMA)}.{quote(TEMPLATE)}")
    else:
        out.append(declaration(item))
    return out


def command(item, *, route="direct"):
    index = item["mysqlDropIndex"]
    owner = f"{quote(SCHEMA)}.{quote(TABLE)}"
    if route == "alter-table-drop-key":
        if index["role"] == "primary":
            return f"ALTER TABLE {owner} DROP PRIMARY KEY"
        return f"ALTER TABLE {owner} DROP KEY {quote(item['written'])}"
    name = index["name"] if route == "direct" else item["written"]
    return f"DROP INDEX {quote(name)} ON {owner}"


def analyze(item, route):
    return {"dialect": "mysql", "release": item["release"], "currentDatabase": SCHEMA,
            "declarations": declarations(item), "sql": command(item, route=route)}


def native_rows(item, temporary):
    base = (200 if temporary else 100) + item["offset"]
    return [[base + 1, 11, None], [base + 2, 17, 4]]


def state(item, temporary, keys=None, rows=None):
    legacy = item["release"] in OLD
    signed = "int(11)" if legacy else "int"
    serial = "bigint(20) unsigned" if legacy else "bigint unsigned"
    generated = serial if item["declaration"] == "serial-type" else signed
    extra = "NULL" if temporary and not legacy else ""
    columns = [["a", generated, False, None, "auto_increment", None],
               ["b", signed, False, None, extra, None], ["c", signed, True, None, extra, None]]
    metadata = []
    for key in item["keys"] if keys is None else keys:
        for position, field in enumerate(key["fields"], 1):
            direction = "D" if position == 1 and key["descending"] and not legacy else "A"
            metadata.append([key["name"], int(key["role"] == "ordinary"), position, field, direction,
                             field == "c", "BTREE", key["visible"], None])
    return {"rows": native_rows(item, temporary) if rows is None else rows,
            "columns": columns, "indexes": sorted(metadata, key=lambda entry: (entry[0], entry[2]))}


def prediction(item, failed):
    temporary = item["life"] != "persistent"
    before = state(item, temporary)
    keys = item["keys"] if failed else [key for key in item["keys"] if key["name"] != item["mysqlDropIndex"]["name"]]
    after = state(item, temporary, keys=keys)
    next_id = before["rows"][-1][0] + 1
    inserted = state(item, temporary, keys=keys, rows=[*before["rows"], [next_id, 79, 5]])
    if item["life"] == "temporary-shadow":
        catalog = state(sibling(item), False)
    else:
        catalog = None if temporary else inserted
    return {"status": STATUS, "initial": before, "effect": LAST_SUPPORT if failed else ["executed"], "after": after,
            "afterGenerated": inserted, "catalog": catalog, "generated": str(next_id), "marker": 7, "active": False}
