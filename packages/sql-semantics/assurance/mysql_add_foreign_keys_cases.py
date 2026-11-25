"""Independent finite constructors and effect model for native MySQL foreign-key additions."""

from itertools import product
from random import Random

DB, ALT = "ir_fixture", "ir_fk_add_alt"
CHILD, PARENT = "ir_fk_add_child", "ir_fk_add_parent"
LEGACY = {"mysql-5.6.51", "mysql-5.7.44"}
STRICT_PARENT = {"mysql-8.4.7", "mysql-9.0.1", "mysql-9.1.0"}
SHAPES = ("single", "paired", "siblings")
NAMING = ("symbol", "written-key", "automatic")
EFFECTS = ("default", "cascade-set-null", "match-full", "set-default")
COVERAGE = ("new-key", "original-key")
STATUS = "mysql-add-foreign-keys"
PROFILE = "/utf8mb4/lower-case-table-names=0/sql-mode=empty/native/innodb/add-foreign-keys"
SIGNED = "INTEGER"
PARENT_INITIAL = [[7, 11], [13, 19]]
CHILD_INITIAL = [[7, 11, 31]]


def quote(name):
    return "`" + name.replace("`", "``") + "`"


def table(schema, name):
    return quote(schema) + "." + quote(name)


def model(tag, shape, style, effects, coverage, *, scope="same", descending=False, policy=("DEFAULT", "DEFAULT"),
          ignore=False, offset=0, wrong_rows=False, parent_key="primary", required=False):
    pairs = {"single": [[["x", "a"]]], "paired": [[["y", "b"], ["x", "a"]]],
             "siblings": [[["x", "a"]], [["y", "b"]]]}[shape]
    legacy = tag in LEGACY
    targets = [f"Fk_{index + 1}_{offset}" for index in range(len(pairs))]
    allocated = [f"{CHILD}_ibfk_{index + 1}" for index in range(len(pairs))]
    labels = [f"key_{index + 1}_{offset}" for index in range(len(pairs))]
    symbols = targets if style == "symbol" else allocated
    existing = [[f"cover_{index + 1}", [p[0] for p in group]] for index, group in enumerate(pairs)] if coverage == "original-key" else []
    created = [None] * len(pairs) if existing else (
        symbols if style == "symbol" else labels if style == "written-key" else [group[0][0] for group in pairs])
    declared_update, declared_delete, written_match = {
        "default": (None, None, None),
        "cascade-set-null": ("CASCADE", "SET NULL", None),
        "match-full": ("CASCADE", "CASCADE", "FULL"),
        "match-partial": ("SET NULL", "CASCADE", "PARTIAL"),
        "match-simple": ("CASCADE", "RESTRICT", "SIMPLE"),
        "set-default": ("SET DEFAULT", "NO ACTION", None),
        "no-action": ("NO ACTION", "RESTRICT", None),
        "set-default-under-match": ("SET DEFAULT", "RESTRICT", "FULL"),
    }[effects]
    initial = "RESTRICT" if legacy else "NO ACTION"
    effective = [declared_update or initial, declared_delete or initial, written_match if written_match in {"FULL", "PARTIAL"} else "NONE"]
    if legacy and written_match is not None:
        effective = [initial, initial, "NONE"]
    violation = None
    if legacy and descending:
        violation = "legacy-direction"
    elif legacy and "SET DEFAULT" in effective[:2]:
        violation = "legacy-set-default"
    elif required and "SET NULL" in effective[:2]:
        violation = "required-set-null"
    elif parent_key == "absent" or (parent_key == "nonunique" and tag in STRICT_PARENT):
        violation = "parent-key"
    elif policy[0] == "INPLACE" or policy[1] == "NONE":
        violation = "copy-policy"
    owner = [DB, CHILD, "table"]
    parent_db = ALT if scope == "cross" else DB
    return {
        "release": tag, "shape": shape, "style": style, "effects": effects, "coverage": coverage,
        "owner": owner, "parent": [parent_db, PARENT, "table"], "scope": scope, "pairs": pairs,
        "symbols": symbols, "writtenKeys": labels, "existing": existing, "created": created,
        "writtenEffects": [declared_update, declared_delete, written_match], "effective": effective,
        "descending": descending, "policy": list(policy), "ignore": ignore, "offset": offset,
        "wrongRows": wrong_rows, "parentKey": parent_key, "required": required, "violation": violation,
    }


def finite_coords(spec):
    finite = spec["finite"]
    return set(product(finite["shapes"], finite["names"], finite["effects"], finite["childKeys"]))


def coordinate(tag, point):
    return model(tag, *point)


def explored(tag, seed, size):
    random = Random(seed)
    for _ in range(size):
        yield model(tag, random.choice(SHAPES), random.choice(NAMING), random.choice(
            (*EFFECTS, "match-partial", "match-simple", "no-action", "set-default-under-match")),
            random.choice(COVERAGE), scope=random.choice(("same", "cross", "parent-shadow", "child-database")),
            descending=tag not in LEGACY and random.choice((True, False)), offset=random.randrange(1, 25),
            policy=(random.choice(("DEFAULT", "COPY")), random.choice(("DEFAULT", "SHARED", "EXCLUSIVE"))),
            ignore=tag == "mysql-5.6.51" and random.choice((True, False)))


def native_terms(model, fields):
    return ", ".join(quote(field) + (" DESC" if model["descending"] and ordinal == 0 else "")
                     for ordinal, field in enumerate(fields))


def declarations(item):
    dialect_parent = item["parent"][0]
    owner_parent = table(dialect_parent, PARENT)
    if item["parentKey"] == "primary":
        parent_keys = ", PRIMARY KEY (b,a), UNIQUE KEY by_a (a), UNIQUE KEY by_b (b)"
    elif item["parentKey"] == "nonunique":
        parent_keys = ", KEY by_a (a), KEY by_b (b), KEY by_b_a (b,a)"
    else:
        parent_keys = ""
    owner = table(DB, CHILD)
    required = " NOT NULL" if item["required"] else ""
    first = f"CREATE TABLE {owner_parent} (a INT NOT NULL, b INT NOT NULL{parent_keys}) ENGINE=InnoDB"
    keys = "".join(f", KEY {quote(name)} ({native_terms(item, fields)})" for name, fields in item["existing"])
    child = f"CREATE TABLE {owner} (x INT{required}, y INT{required}, z INT PRIMARY KEY{keys}) ENGINE=InnoDB"
    decorations = []
    if item["scope"] == "parent-shadow":
        decorations.append(f"CREATE TEMPORARY TABLE {owner_parent} (wrong VARCHAR(9) PRIMARY KEY) ENGINE=InnoDB")
    if item["scope"] == "child-database":
        decorations.append(f"CREATE TABLE {table(ALT, PARENT)} (a INT PRIMARY KEY, b INT) ENGINE=InnoDB")
    return [first, child, *decorations]


def sql(item, original=False):
    verb = "ALTER IGNORE TABLE" if item["ignore"] else "ALTER TABLE"
    actions = []
    p_schema = item["parent"][0]
    parent = quote(PARENT) if original and item["scope"] != "cross" else table(p_schema, PARENT)
    for index, group in enumerate(item["pairs"]):
        explicit = f"CONSTRAINT {quote(item['symbols'][index])} " if item["style"] == "symbol" else ""
        use_label = item["style"] == "written-key" and (original or item["created"][index] is not None)
        key = quote(item["writtenKeys"][index]) + " " if use_label else ""
        left = native_terms(item, [pair[0] for pair in group])
        right = ", ".join(quote(pair[1]) for pair in group)
        update, delete, match = item["writtenEffects"] if original else item["effective"]
        parts = ([f" MATCH {match}"] if match not in (None, "NONE") else []) + (
            [f" ON UPDATE {update}"] if update else []) + ([f" ON DELETE {delete}"] if delete else [])
        actions.append(f"ADD {explicit}FOREIGN KEY {key}({left}) REFERENCES {parent} ({right})" + "".join(parts))
    actions.extend(f"{label}={value}" for label, value in zip(("ALGORITHM", "LOCK"), item["policy"]) if value != "DEFAULT")
    return f"{verb} {table(DB, CHILD)} " + ", ".join(actions)


def analyze(item):
    default = ALT if item["scope"] == "child-database" else DB
    return {"dialect": "mysql", "release": item["release"], "complete": True, "schemaPath": [default],
            "declarations": declarations(item), "sql": sql(item, True)}


def key_fact(owner, name, fields, item, role="ordinary"):
    return {"owner": owner, "name": name, "role": role, "autoIncrement": None, "full": True,
            "fields": [[field, item["descending"] and position == 0 and owner == item["owner"] and name != "PRIMARY"]
                       for position, field in enumerate(fields)]}


def direct(item):
    parent, owner = item["parent"], item["owner"]
    if item["parentKey"] == "primary":
        prior = [key_fact(parent, "PRIMARY", ["b", "a"], item, "primary"),
                 key_fact(parent, "by_a", ["a"], item, "unique"), key_fact(parent, "by_b", ["b"], item, "unique")]
    elif item["parentKey"] == "nonunique":
        prior = [key_fact(parent, name, fields, item) for name, fields in (("by_a", ["a"]), ("by_b", ["b"]), ("by_b_a", ["b", "a"]))]
    else:
        prior = []
    keys = [key_fact(owner, "PRIMARY", ["z"], item, "primary"),
            *[key_fact(owner, label, fields, item) for label, fields in item["existing"]], *prior]
    additions = []
    for index, pairs in enumerate(item["pairs"]):
        created = item["created"][index]
        additions.append({
            "parent": parent, "symbol": item["symbols"][index], "generated": item["style"] != "symbol",
            "pairs": [[child, target, SIGNED, False, not item["required"], item["descending"] and ordinal == 0]
                      for ordinal, (child, target) in enumerate(pairs)],
            "effects": item["effective"], "created": created,
            "indexName": item["writtenKeys"][index] if item["style"] == "written-key" and created is not None else None,
        })
    return {"dialect": "mysql", "release": item["release"], "mysqlAddForeignKeys": {
        "owner": owner, "additions": additions, "policy": item["policy"], "ignore": item["ignore"],
    }, "mysqlForeignKeyCatalog": {"keys": keys, "constraints": []}}


def keys(item, applied):
    measured = [["PRIMARY", 0, 1, "z", "A", "BTREE"]]
    new = [[label, [p[0] for p in item["pairs"][ordinal]]]
           for ordinal, label in enumerate(item["created"]) if label is not None] if applied else []
    for name, fields in item["existing"] + new:
        measured.extend([name, 1, order + 1, field, "D" if order == 0 and item["descending"] else "A", "BTREE"]
                        for order, field in enumerate(fields))
    return sorted(measured)


def constraints(item):
    rows = []
    for position, pairs in enumerate(item["pairs"]):
        rows.extend([item["symbols"][position], order + 1, child, item["parent"][0], PARENT, parent]
                    for order, (child, parent) in enumerate(pairs))
    return sorted(rows)


def parent_operation(item, event):
    row = list(CHILD_INITIAL[0])
    columns = {name for pairs in item["pairs"] for name, _ in pairs}
    action = item["effective"][0 if event == "update" else 1]
    update = ["executed", 1]
    initial_parent = [list(parent) for parent in PARENT_INITIAL]
    if action in {"NO ACTION", "RESTRICT", "SET DEFAULT"}:
        return [["db-error", "23000", 1451], initial_parent, [row]]
    if event == "delete":
        parent = [initial_parent[1]]
        if action == "CASCADE":
            return [update, parent, []]
    else:
        parent = [initial_parent[1], [47, 53]]
    for column, position, value in (("x", 0, 47), ("y", 1, 53)):
        if column in columns:
            row[position] = value if action == "CASCADE" else None
    return [update, parent, [row]]


def expected(item):
    applied = item["violation"] is None and not item["wrongRows"]
    before_rows = [[87, 89, 31]] if item["wrongRows"] else CHILD_INITIAL
    if not applied:
        return {"status": STATUS, "indexesBefore": keys(item, False), "indexesAfter": keys(item, False),
                "references": [], "rules": [], "childRows": before_rows, "probes": None,
                **({"effect": ["db-error", "23000", 1452]} if item["wrongRows"] else {})}
    probes = {
        "update": parent_operation(item, "update"),
        "delete": parent_operation(item, "delete"),
        "unmatched": ["db-error", "23000", 1452],
        "null": ["db-error", "23000", 1048] if item["required"] else ["executed", 1],
        "partial": (["db-error", "23000", 1048] if item["required"] else
                    ["db-error", "23000", 1452] if item["shape"] == "siblings" else ["executed", 1]),
    }
    return {"status": STATUS, "indexesBefore": keys(item, False), "indexesAfter": keys(item, True),
            "references": constraints(item), "rules": sorted([[name, *item["effective"]] for name in item["symbols"]]),
            "childRows": before_rows, "effect": ["executed"], "probes": probes}
