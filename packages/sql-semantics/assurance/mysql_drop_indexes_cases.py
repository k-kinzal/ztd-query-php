"""Independent catalog effects of a single atomic MySQL index-removal set."""

from itertools import product
from random import Random

from mysql_alter_index_cases import GRAMMAR_ERROR, LEGACY_IGNORE
from mysql_drop_index_cases import ABSENT, LIFETIMES
from mysql_index_auto_increment_cases import LAST_SUPPORT, native_key, native_rows, state
from mysql_index_policy_cases import refusal
from mysql_innodb_declaration_width_cases import SCHEMA, quote

TABLE = "ir_index_set"
STATUS = "mysql-index-removal-set"
ROLE_CASES = ("secondary-only", "explicit-primary-and-secondary", "clustered-unique-and-secondary")
ORDERS = ("forward", "reverse")
SET_POLICY = "The algorithm and lock cannot remove these native InnoDB indexes."
SET_AUTO = "Atomic removal must retain a backing key for the AUTO_INCREMENT field."


def regular_keys(size, roles, stem):
    keys = [native_key(f"{stem}_{index}", "ordinary" if index % 3 != 2 else "unique", ["c"] if index % 2 else ["c", "b"] if index % 3 == 2 else ["a", "b"])
            for index in range(size)]
    if roles != "secondary-only":
        keys[0] = native_key("PRIMARY" if roles == "explicit-primary-and-secondary" else f"{stem}_cluster",
                             "primary" if roles == "explicit-primary-and-secondary" else "clustered-unique", ["a"])
    if size < 64:
        keys.append(native_key("retained_by_b", "ordinary", ["b", "c"]))
    return keys


def definition(tag, life, keys, chosen, *, generated=False, policy=("DEFAULT", "DEFAULT"), ignore=False, offset=0):
    owner = [SCHEMA, TABLE, "temporary-table" if life != "persistent" else "table"]
    used = [key["name"] for key in keys if generated and key["fields"][0] == "a"]
    targets = [{"owner": owner.copy(), "name": key["name"], "role": key["role"],
                "autoIncrement": {"field": "a", "alternates": [name for name in used if name != key["name"]]} if key["name"] in used else None}
               for key in chosen]
    written = [key["name"].swapcase() for key in chosen]
    return {"dialect": "mysql", "release": tag, "life": life, "declaration": "attribute" if generated else "plain", "offset": offset,
            "keys": keys, "mysqlDropIndexes": {"targets": targets, "policy": list(policy), "ignore": ignore}, "written": written}


def coordinate(tag, point):
    life, size, roles, order = point
    keys = regular_keys(size, roles, "selected")
    chosen = keys[:size]
    if order == "reverse":
        chosen.reverse()
    return definition(tag, life, keys, chosen)


def finite_points(finite):
    return set(product(finite["lifetimes"], finite["sizes"], finite["physicalRoleCases"], ORDERS))


def backing_case(tag, life, kept, offset=0):
    keys = [native_key("PRIMARY", "primary", ["a"]), native_key("another_support", "ordinary", ["a", "b"]),
            native_key("late_position", "ordinary", ["c", "a"])]
    if kept:
        keys.append(native_key("kept_support", "ordinary", ["a", "c"]))
    return definition(tag, life, keys, keys[:2], generated=True, offset=offset)


def generated(tag, seed, count):
    rng = Random(seed)
    for ordinal in range(count):
        life = LIFETIMES[(ordinal + seed) % len(LIFETIMES)]
        if ordinal % 4 == 3:
            yield backing_case(tag, life, True, offset=rng.randrange(25))
            continue
        roles = ROLE_CASES[(ordinal // 2 + seed) % len(ROLE_CASES)]
        keys = regular_keys(rng.randrange(2, 15), roles, f"choisiÉ {seed}")
        chosen = keys[:-1]
        rng.shuffle(chosen)
        yield definition(tag, life, keys, chosen, offset=rng.randrange(25))


def ddl(model, temporary):
    field = "a INT NOT NULL" + (" AUTO_INCREMENT" if model["declaration"] != "plain" else "")
    terms = [field, "b INT NOT NULL", "c INT NULL"]
    for key in model["keys"]:
        role = key["role"]
        label = "PRIMARY KEY" if role == "primary" else ("KEY " if role == "ordinary" else "UNIQUE KEY ") + quote(key["name"])
        terms.append(f"{label} (" + ", ".join(key["fields"]) + ")")
    return f"CREATE {'TEMPORARY ' if temporary else ''}TABLE {quote(SCHEMA)}.{quote(TABLE)} ({', '.join(terms)}) ENGINE=InnoDB"


def sibling(model):
    keys = [native_key("sibling_primary", "clustered-unique", ["b"]), native_key("sibling_keep", "ordinary", ["c"])]
    return definition(model["release"], "persistent", keys, keys[:1], offset=model["offset"])


def declarations(model):
    if model["life"] == "temporary-shadow":
        return [ddl(sibling(model), False), ddl(model, True)]
    return [ddl(model, model["life"] == "temporary")]


def sql(model, original=False):
    shape = model["mysqlDropIndexes"]
    style = "ALTER IGNORE TABLE " if shape["ignore"] else "ALTER TABLE "
    table = f"{quote(SCHEMA)}.{quote(TABLE)}"
    names = model["written"] if original else [target["name"] for target in shape["targets"]]
    actions = []
    for ordinal, name in enumerate(names):
        if original and name == "primary":
            actions.append("DROP PRIMARY KEY")
        else:
            actions.append(("DROP KEY " if original and ordinal % 2 == 0 else "DROP INDEX ") + quote(name))
    algorithm, lock = shape["policy"]
    options = [f"{key}={value}" for key, value in (("ALGORITHM", algorithm), ("LOCK", lock)) if value != "DEFAULT"]
    clauses = ([*options[:1], *actions, *options[1:]] if original else [*actions, *options])
    return style + table + " " + ", ".join(clauses)


def analyze(model):
    return {"dialect": "mysql", "release": model["release"], "currentDatabase": SCHEMA,
            "declarations": declarations(model), "sql": sql(model, True)}


def policy_cases(tag):
    for life, roles, (algorithm, lock) in product(LIFETIMES, ROLE_CASES, (("INPLACE", "NONE"), ("COPY", "SHARED"), ("COPY", "NONE"))):
        keys = regular_keys(2, roles, "transition")
        model = definition(tag, life, keys, keys[:2], policy=(algorithm, lock))
        native_error = refusal(tag, "drop-ordinary" if roles == "secondary-only" else "drop-primary", life, algorithm, lock)
        yield f"policy/{life}/{roles}/{algorithm}/{lock}", model, native_error, SET_POLICY
    for life in LIFETIMES:
        keys = regular_keys(2, "secondary-only", "ignored")
        yield f"ignore/{life}", definition(tag, life, keys, keys[:2], ignore=True), (
            None if tag == LEGACY_IGNORE else GRAMMAR_ERROR), "Unexpected 'IGNORE'"
        yield f"last-backing/{life}", backing_case(tag, life, False), LAST_SUPPORT, SET_AUTO
        yield f"kept-backing/{life}", backing_case(tag, life, True), None, ""


def mutation_model(tag):
    return coordinate(tag, ("temporary-shadow", 3, "secondary-only", "reverse"))


def native_negatives(tag):
    bad = mutation_model(tag)
    bad["written"][1] = bad["written"][0]
    yield "duplicate-target", bad, ABSENT, "cannot repeat a physical key"
    bad = mutation_model(tag)
    bad["written"][1] = "missing"
    yield "missing-target", bad, ABSENT, "requires one resolved ordinary index"


def prediction(model, failure):
    temporary = model["life"] != "persistent"
    before = state(model, temporary)
    removed = {target["name"] for target in model["mysqlDropIndexes"]["targets"]}
    keys = model["keys"] if failure else [key for key in model["keys"] if key["name"] not in removed]
    after = state(model, temporary, keys=keys)
    next_id = before["rows"][-1][0] + 1
    post = state(model, temporary, keys=keys, rows=[*before["rows"], [next_id, 79, 5]]) if model["declaration"] != "plain" else None
    catalog = state(sibling(model), False) if model["life"] == "temporary-shadow" else None if temporary else post or after
    early = failure == GRAMMAR_ERROR
    return {"status": STATUS, "initial": before, "effect": failure or ["executed"], "after": after, "afterGenerated": post,
            "catalog": catalog, "generated": None if post is None else str(next_id), "marker": 0 if early else 7, "active": early}
