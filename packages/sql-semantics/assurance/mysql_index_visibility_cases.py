"""Independent ordered MySQL secondary-index visibility and native policy laws."""

from itertools import product
from random import Random

SCHEMA = "ir_fixture"
TABLE = "ir_visibility"
LIFETIMES = ("persistent", "temporary")
FUNCTIONAL_COLUMN_NAME = {
    "mysql-8.0.44": {'persistent': None, 'temporary': None},
    "mysql-8.1.0": {'persistent': None, 'temporary': ''},
    "mysql-8.2.0": {'persistent': None, 'temporary': ''},
    "mysql-8.3.0": {'persistent': None, 'temporary': ''},
    "mysql-8.4.7": {'persistent': None, 'temporary': None},
    "mysql-9.0.1": {'persistent': None, 'temporary': ''},
    "mysql-9.1.0": {'persistent': None, 'temporary': None},
}
KEY_KINDS = {"none": "ordinary", "prefix": "ordinary", "functional": "ordinary", "fulltext": "fulltext", "spatial": "spatial"}
POLICIES = {
    "persistent": {"default": ("default", "none", "shared", "exclusive"),
                   "copy": ("default", "shared", "exclusive"), "inplace": ("default", "none", "shared", "exclusive")},
    "temporary": {"default": ("default", "none", "shared", "exclusive"), "copy": ("default", "none", "shared", "exclusive")},
}
DIAGNOSTICS = {
    "instant": ["db-error", "0A000", 1845], "instant-with-lock": ["db-error", "HY000", 1221],
    "temporary-inplace": ["db-error", "0A000", 1845], "copy-none": ["db-error", "0A000", 1846],
    "nonsecondary": ["db-error", "HY000", 3522], "missing": ["db-error", "42000", 1176],
}
STATUS = "mysql-index-visibility"
PROFILE = "/utf8mb4/lower-case-table-names=0/sql-mode=empty/native/innodb/index-visibility"


def quote(name):
    return f"`{name}`"


def names(position):
    return [f"pv_A{position}", f"pv_B{position}", f"pv_C{position}"]


def roles(family):
    return ["ordinary", "unique", KEY_KINDS[family]]


def policy_rejection(lifetime, algorithm, lock):
    if algorithm == "instant":
        return "instant" if lock == "default" else "instant-with-lock"
    if lifetime == "temporary" and algorithm == "inplace":
        return "temporary-inplace"
    if lifetime == "persistent" and algorithm == "copy" and lock == "none":
        return "copy-none"
    return None


def model(tag, lifetime, algorithm, lock, changes, family="none", variant=0, flags=(True, True, True), invalid=None, background=False):
    return {"dialect": "mysql", "release": tag, "lifetime": lifetime, "policy": [algorithm, lock],
            "edits": [list(change) for change in changes], "variant": variant, "initial": list(flags),
            "family": family, "invalid": invalid or policy_rejection(lifetime, algorithm, lock),
            "background": background}


def finite_coords(finite):
    grid = ("lifetimes", "algorithms", "locks", "flags")
    return set(product(*(finite[axis] for axis in grid)))


def coordinate(tag, point):
    lifetime, algorithm, lock, flag = point
    return model(tag, lifetime, algorithm, lock, [(0, flag == "visible")])


def explored(tag, seed, count):
    random = Random(seed)
    for _ in range(count):
        lifetime = random.choice(LIFETIMES)
        algorithm = random.choice(tuple(POLICIES[lifetime]))
        lock = random.choice(POLICIES[lifetime][algorithm])
        families = tuple(KEY_KINDS) if lifetime == "persistent" else ("none", "prefix", "functional", "spatial")
        family = random.choice(families)
        width = 2 if family == "none" else 3
        changes = [(random.randrange(width), random.choice((True, False))) for _ in range(random.randrange(1, 8))]
        flags = tuple(random.choice((True, False)) for _ in range(3))
        yield model(tag, lifetime, algorithm, lock, changes, family, random.randrange(1, 20), flags,
                    background=random.choice((False, True)))


def selected_name(item, position):
    return ("PRIMARY" if position == -1 else "pv_missing" if position == -2 else names(item["variant"])[position])


def term_specs(item, temporary):
    first, second, third = names(item["variant"])
    a, b = ("b", "a") if temporary else ("a", "b")
    functional_label = FUNCTIONAL_COLUMN_NAME[item["release"]]["temporary" if temporary else "persistent"]
    return {
        "PRIMARY": ("BTREE", 0, "z", "A", None, None),
        first: ("BTREE", 1, a, "A", None, None),
        second: ("BTREE", 0, b, "A", None, None),
        "prefix": ("BTREE", 1, "payload", "A", 2, None),
        "functional": ("BTREE", 1, functional_label, "A", None, "(`a` + 10)"),
        "fulltext": ("FULLTEXT", 1, "payload", None, None, None),
        "spatial": ("SPATIAL", 1, "geom", "A", 32, None),
    }, third


def definitions(item):
    first, second, third = names(item["variant"])
    family = item["family"]

    def declaration(temporary, use_special):
        a, b = ("b","a") if temporary else ("a","b")
        flags = lambda i: "" if item["initial"][i] else " INVISIBLE"
        spatial = ", geom GEOMETRY NOT NULL" if family == "spatial" else ""
        special = {
            "none": "", "prefix": f", KEY {quote(third)} (payload(2))",
            "functional": f", KEY {quote(third)} ((a+10))",
            "fulltext": f", FULLTEXT KEY {quote(third)} (payload)",
            "spatial": f", SPATIAL KEY {quote(third)} (geom)",
        }[family]
        subject = "CREATE TEMPORARY TABLE" if temporary else "CREATE TABLE"
        keys = f", PRIMARY KEY (z), KEY {quote(first)} ({a}){flags(0)}, UNIQUE KEY {quote(second)} ({b}){flags(1)}"
        if item["invalid"] == "implicit-primary":
            keys = f", KEY {quote(first)} ({a}), UNIQUE KEY {quote(second)} ({b})"
        return (f"{subject} {quote(SCHEMA)}.{quote(TABLE)} (a INT NOT NULL, b INT NOT NULL, z INT NOT NULL, payload VARCHAR(12){spatial}"
                + keys + (special + flags(2) if use_special and special else "") + ") ENGINE=InnoDB")

    shadow = item["lifetime"] == "temporary"
    return [declaration(False, not shadow), declaration(True, True)] if shadow else [declaration(False, True)]


def sql(item, original=False):
    algorithm, lock = item["policy"]
    updates = []
    for position, visible in item["edits"]:
        operand = selected_name(item, position)
        updates.append("ALTER INDEX " + quote(operand.lower() if original and position >= 0 else operand) + (" VISIBLE" if visible else " INVISIBLE"))
    options = ([f"ALGORITHM={algorithm.upper()}"] if algorithm != "default" else []) + ([f"LOCK={lock.upper()}"] if lock != "default" else [])
    if original and item["background"]:
        options = ["ALGORITHM=INSTANT", "LOCK=EXCLUSIVE",
                   f"ALGORITHM={algorithm.upper()}", f"LOCK={lock.upper()}"]
        options = [options[0], options[1], *updates, options[2], options[3]]
    else:
        options = [*updates, *options]
    return f"ALTER TABLE {quote(SCHEMA)}.{quote(TABLE)} " + ", ".join(options)


def analyze(item):
    return {"dialect": "mysql", "release": item["release"], "sql": sql(item, True), "declarations": definitions(item), "complete": True}


def direct(item):
    owner = [SCHEMA, TABLE, "temporary-table" if item["lifetime"] == "temporary" else "table"]
    edit_roles = roles(item["family"])
    edits = [[selected_name(item, slot), edit_roles[slot] if slot >= 0 else "ordinary", visible] for slot, visible in item["edits"]]
    return {"dialect": "mysql", "release": item["release"],
            "mysqlIndexVisibility": {"owner": owner, "edits": edits, "policy": [value.upper() for value in item["policy"]]}}


def layout(item, temporary, include_special, after):
    terms, third = term_specs(item, temporary)
    flags = dict(zip(names(item["variant"]), item["initial"]))
    flags["PRIMARY"] = True
    if after and item["invalid"] is None:
        for slot, flag in item["edits"]:
            flags[selected_name(item, slot)] = flag
    selected = ["PRIMARY", *names(item["variant"])[:2]]
    if item["invalid"] == "implicit-primary":
        selected.remove("PRIMARY")
    family = item["family"]
    if include_special and family != "none":
        terms[third] = terms[family]
        selected.append(third)
    return [[name, *terms[name], "YES" if flags[name] else "NO"] for name in sorted(selected)]


def observer_request(item, command):
    return {"sql": command, "declarations": definitions(item), "temporary": item["lifetime"] == "temporary",
            "variant": item["variant"], "family": item["family"]}


def expected(item):
    shadow = item["lifetime"] == "temporary"
    before = {"persistent": layout(item, False, not shadow, False),
              "temporary": layout(item, True, True, False) if shadow else None,
              "control": [["PRIMARY", "BTREE", 0, "z", "A", None, None, "YES"],
                          ["preserved_only", "BTREE", 1, "a", "A", None, None, "NO"]]}
    after = {**before, ("temporary" if shadow else "persistent"): layout(item, shadow, True, True)}
    reason = item["invalid"]
    if reason == "implicit-primary":
        reason = "nonsecondary"
    return {"status": STATUS, "before": before, "after": after, "settings": [1, "OFF"],
            "effect": ["executed"] if reason is None else DIAGNOSTICS[reason],
            "uniqueness": ["db-error", "23000", 1062],
            "data": [[17+item["variant"], 23+item["variant"]], [59+item["variant"], 67+item["variant"]] if shadow else None]}
