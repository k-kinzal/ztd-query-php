"""Independent fixture and effect laws for ordinary InnoDB index dependencies."""

from itertools import product
from random import Random

LEGACY = {"mysql-5.6.51", "mysql-5.7.44"}
STRICT = {"mysql-8.4.7", "mysql-9.0.1", "mysql-9.1.0"}
NO_RENAME = "mysql-5.6.51"
SCHEMA, CROSS = "ir_fixture", "ir_ifk_other"
PARENT, CHILD = "ir_ixfk_p", "ir_ixfk_c"
SIDE = ("parent", "child")
SHAPE = ("exact", "unrelated", "long", "nonunique", "hidden", "permuted")
PLAN = ("single", "with-decoy", "remove-both")
NAMES = ("u", "v", "w", "moved")
PROFILE = {"single": "drop-index", "with-decoy": "drop-index-set", "remove-both": "drop-index-set", "mixed": "alter-indexes"}
WIRE = {"single": "mysqlDropIndex", "with-decoy": "mysqlDropIndexes", "remove-both": "mysqlDropIndexes", "mixed": "mysqlMixedIndexes"}
STATUS = "mysql-index-foreign-keys"
SURVIVOR = "Index removal must retain a supporting key for each native foreign-key side."


def name(coordinate):
    return ".".join(f"`{part}`" for part in coordinate)


def index(label, fields, unique=False, descending=False, visible=True):
    return {"label": label, "fields": list(fields), "unique": unique, "descending": descending, "visible": visible}


def model(tag, side, shape, plan, scope, variation=0, descending=False, invisible=False, swap=False):
    target = (PARENT if side == "parent" else CHILD)
    child_schema = CROSS if scope == "cross" else SCHEMA
    coordinate = [SCHEMA, PARENT] if side == "parent" else [child_schema, CHILD]
    p_fields, c_fields = ("b", "a", "z") if swap else ("a", "b", "z"), ("y", "x", "z") if swap else ("x", "y", "z")
    field_count = 2 if shape in ("hidden", "permuted") else 1
    p, c = p_fields[:field_count], c_fields[:field_count]
    support_fields = p if side == "parent" else c
    first, second, third = p_fields if side == "parent" else c_fields
    other = {
        "exact": support_fields, "unrelated": (second,), "long": (first, second, third),
        "nonunique": support_fields, "hidden": (first,), "permuted": (second, first),
    }[shape]
    labels = [f"{root}{variation}" for root in NAMES]
    u, v, w, moved = labels
    primary = second if shape == "hidden" else third
    altered = [
        index("PRIMARY", (primary,), True), index(u, support_fields, side == "parent"),
        index(v, other, side == "parent" and shape != "nonunique", descending, not invisible),
        index(w, (third,)),
    ]
    untouched = [index("PRIMARY", ("z",), True), index("stable_support", c if side == "parent" else p, side == "child")]
    parents = altered if side == "parent" else untouched
    children = altered if side == "child" else untouched
    shadow = scope == "shadow"
    if shadow:
        session = [index("PRIMARY", ("z",), True), index(u, (third,)), index(v, (second,)), index(w, (first,))]
        primary_target = session
    else:
        session = None
        primary_target = altered
    removed = {u, *( [w] if plan == "with-decoy" else [v] if plan == "remove-both" else [])}
    alternate = {
        "exact": True, "unrelated": False,
        "long": side == "child" or tag not in STRICT, "nonunique": side == "child" or tag not in STRICT,
        "hidden": tag in LEGACY if side == "child" else tag not in STRICT,
        "permuted": False,
    }[shape]
    supports = [u] + ([v] if alternate else [])
    legal = shadow or (alternate and v not in removed)
    final = primary_target if not legal else [
        {**key, "label": moved} if plan == "mixed" and key["label"] == v else key
        for key in primary_target if key["label"] not in removed
    ]
    return {"dialect": "mysql", "release": tag, "side": side, "shape": shape, "plan": plan, "scope": scope,
            "target": target, "owner": [*coordinate, "temporary-table" if shadow else "table"], "labels": labels,
            "parent": [SCHEMA, PARENT], "child": [child_schema, CHILD], "parentFields": p, "childFields": c,
            "parents": parents, "children": children, "session": session, "initial": primary_target, "final": final,
            "supports": [] if shadow else supports, "legal": legal, "offset": variation}


def finite_coords(finite):
    return set(product(finite["sides"], finite["shapes"], finite["plans"], finite["scopes"]))


def coordinate(tag, point):
    return model(tag, *point)


def explored(tag, seed, size):
    random = Random(seed)
    for _ in range(size):
        plan = random.choice(PLAN if tag == NO_RENAME else (*PLAN, "mixed"))
        yield model(tag, random.choice(SIDE), random.choice(SHAPE), plan,
                    random.choice(("same", "cross", "shadow")), random.randrange(1, 35),
                    random.choice((False, True)), tag not in LEGACY and random.choice((False, True)),
                    random.choice((False, True)))


def declaration(coordinate, fields, keys, constraint="", temporary=False):
    intro = "CREATE TEMPORARY TABLE " if temporary else "CREATE TABLE "
    column_sql = [f"`{field}` INT NOT NULL" for field in fields]
    key_sql = []
    for key in keys:
        fields_sql = ", ".join(f"`{part}`" + (" DESC" if key["descending"] else "") for part in key["fields"])
        shown = "" if key["visible"] else " INVISIBLE"
        key_role = "PRIMARY KEY" if key["label"] == "PRIMARY" else ("UNIQUE KEY " if key["unique"] else "KEY ") + name([key["label"]])
        key_sql.append(f"{key_role} ({fields_sql}){shown}")
    return intro + name(coordinate) + " (" + ", ".join([*column_sql, *key_sql]) + constraint + ") ENGINE=InnoDB"


def declarations(item):
    parent_fields = ", ".join(map(lambda s: name([s]), item["parentFields"]))
    child_fields = ", ".join(map(lambda s: name([s]), item["childFields"]))
    key = f", CONSTRAINT owned_fk FOREIGN KEY ({child_fields}) REFERENCES {name(item['parent'])} ({parent_fields})"
    persistent = [declaration(item["parent"], ("a", "b", "z"), item["parents"]), declaration(item["child"], ("x", "y", "z"), item["children"], key)]
    if item["session"] is None:
        return persistent
    fields = ("a", "b", "z") if item["side"] == "parent" else ("x", "y", "z")
    return [*persistent, declaration(item["owner"][:2], fields, item["session"], temporary=True)]


def sql(item, analysis=False):
    u, v, w, moved = item["labels"]
    owner = name(item["owner"][:2])
    if item["plan"] == "single":
        return f"DROP INDEX {name([u])} ON {owner}"
    key = "KEY" if analysis else "INDEX"
    end = {
        "with-decoy": f"DROP {key} {name([w])}",
        "remove-both": f"DROP {key} {name([v])}",
        "mixed": f"RENAME INDEX {name([v])} TO {name([moved])}",
    }[item["plan"]]
    return f"ALTER TABLE {owner} DROP {key} {name([u])}, {end}"


def analyze(item):
    return {"dialect": "mysql", "release": item["release"], "sql": sql(item, True), "declarations": declarations(item), "complete": True}


def direct(item):
    u, v, w, moved = item["labels"]
    policy = ["DEFAULT", "DEFAULT"]

    def drop(label):
        physical = next(key for key in item["initial"] if key["label"] == label)
        return {"owner": item["owner"], "name": label, "role": "unique" if physical["unique"] else "ordinary", "autoIncrement": None}

    shape = {
        "single": {**drop(u), "policy": policy, "ignore": False},
        "with-decoy": {"targets": [drop(u), drop(w)], "policy": policy, "ignore": False},
        "remove-both": {"targets": [drop(u), drop(v)], "policy": policy, "ignore": False},
        "mixed": {"owner": item["owner"], "actions": [{"drop": drop(u)}, {"rename": [v, moved]}],
                  "unmoved": ["PRIMARY", w], "policy": policy},
    }[item["plan"]]
    requirements = [{"owner": item["owner"], "keys": item["supports"]}] if item["supports"] else []
    return {"dialect": "mysql", "release": item["release"], WIRE[item["plan"]]: shape, "mysqlIndexForeignKeys": requirements}


def layouts(item, keys):
    rows = []
    for key in sorted(keys, key=lambda k: k["label"]):
        direction = "D" if key["descending"] and item["release"] not in LEGACY else "A"
        for position, field in enumerate(key["fields"], 1):
            rows.append([key["label"], int(not key["unique"]), position, field, None,
                         direction, "BTREE", None if item["release"] in LEGACY else "YES" if key["visible"] else "NO"])
    return rows


def expected(item):
    parent, child = item["parent"], item["child"]
    coordinates = [
        [*child, "owned_fk", order, child_field, *parent, parent_field]
        for order, (child_field, parent_field) in enumerate(zip(item["childFields"], item["parentFields"], strict=True), 1)
    ]
    initial = {
        "parents": layouts(item, item["parents"]), "children": layouts(item, item["children"]),
        "session": None if item["session"] is None else layouts(item, item["session"]),
        "edges": coordinates, "data": [11 + item["offset"], 11 + item["offset"], None if item["session"] is None else 61 + item["offset"]],
    }
    effect = "session" if item["session"] is not None else "parents" if item["side"] == "parent" else "children"
    result = {**initial, effect: layouts(item, item["final"])}
    return {"status": STATUS, "before": initial, "after": result,
            "effect": ["executed"] if item["legal"] else ["db-error", "HY000", 1553],
            "settings": [1, None if item["release"] not in STRICT else "ON"]}
