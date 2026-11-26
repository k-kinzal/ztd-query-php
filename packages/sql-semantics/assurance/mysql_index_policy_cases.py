"""Independent native index policy and capacity input domain."""

from itertools import product
from random import Random

import mysql_create_btree_index_cases as creation
import mysql_drop_index_cases as removal
from mysql_innodb_declaration_width_cases import OLD, SCHEMA, quote

NEEDS_SPARE = "CREATE INDEX requires a free position among the 64 native keys."
IDENTITY_COUNTS = "A prior declared identity occupies a native key position."
CAPACITY_TABLE = "ir_index_capacity"
CAPACITY_SQL = "CREATE INDEX `new_key` ON `ir_fixture`.`ir_index_capacity` (`target_field`)"
TOO_MANY_KEYS = ["db-error", "42000", 1069]
CLUSTERED = {"drop-primary", "drop-clustered-unique"}
ORDERED_LAYOUTS = (
    ("explicit-primary-overrides-an-earlier-unique", "primary", "drop-unique"),
    ("earlier-required-unique-is-clustered", "later", "drop-clustered-unique"),
    ("later-required-unique-is-secondary", "prior", "drop-unique"),
)


def policy_points(finite, both_orders):
    axes = (finite[key] for key in ("operationRoles", "owners", "algorithms", "locks"))
    return product(*axes, finite["orders"] if both_orders else finite["orders"][:1])


def refusal(tag, role, life, algorithm, lock):
    if algorithm == "INSTANT":
        if tag in OLD:
            return ["db-error", "HY000", 1800]
        if lock != "DEFAULT":
            return ["db-error", "HY000", 1221]
        return ["db-error", "0A000", 1846 if life == "persistent" and role in CLUSTERED else 1845]
    if life != "persistent":
        return ["db-error", "0A000", 1845] if algorithm == "INPLACE" else None
    if role in CLUSTERED:
        forbidden = algorithm == "INPLACE" or lock == "NONE"
    else:
        forbidden = algorithm == "COPY" and lock == "NONE"
    return ["db-error", "0A000", 1846] if forbidden else None


def options(algorithm, lock, order, explicit):
    parts = [f" ALGORITHM={algorithm}" if explicit or algorithm != "DEFAULT" else "",
             f" LOCK={lock}" if explicit or lock != "DEFAULT" else ""]
    return "".join(reversed(parts) if order == "lock-first" else parts)


def policy_model(tag, point, rng=None, anchor=None):
    role, life, algorithm, lock, order = point
    if role.startswith("create-"):
        fields = [2, 0] if rng is None else rng.sample(range(18), rng.randint(1, 9))
        directions = [False, True] if rng is None else [bool(rng.randrange(2)) for _ in fields]
        model = creation.definition(tag, life, fields, directions, role.endswith("unique"), True, "ordered_key")
        key = "mysqlCreateIndex"
    else:
        kind = "unique" if role == "drop-clustered-unique" else role.removeprefix("drop-")
        origin = "table" if kind == "primary" else "explicit"
        qualifier = "explicit" if rng is None else rng.choice(removal.QUALIFIERS)
        model = removal.definition(tag, (life, kind, origin, "permitted-case-variant", qualifier),
                                   offset=0 if rng is None else rng.randrange(20), composite=anchor is None,
                                   nullable=role == "drop-unique" and anchor is None, anchor=anchor)
        key = "mysqlDropIndex"
    model[key]["policy"] = [algorithm, lock]
    return {"fixture": "create" if "mysqlCreateIndex" in model else "drop", "point": list(point),
            "model": model, "nativeError": refusal(tag, role, life, algorithm, lock)}


def policy_sql(item, original):
    _, _, algorithm, lock, order = item["point"]
    module = creation if item["fixture"] == "create" else removal
    return module.command(item["model"], original=original) + options(
        algorithm, lock, order if original else "algorithm-first", original)


def analysis_request(item):
    if item["fixture"] == "capacity":
        return {**creation.analyze(item["model"], sql=CAPACITY_SQL), "declarations": [capacity_declaration(item)]}
    module = creation if item["fixture"] == "create" else removal
    return module.analyze(item["model"], sql=policy_sql(item, True))


def generated(tag, finite, seed, count):
    rng = Random(seed)
    axes = list(finite["operationRoles"]), list(finite["owners"]), finite["orders"]
    for ordinal in range(count):
        role = axes[0][ordinal % len(axes[0])]
        life = axes[1][(ordinal + ordinal // len(axes[0]) + seed) % len(axes[1])]
        pairs = [(algorithm, lock) for algorithm, lock in product(finite["algorithms"], finite["locks"])
                 if refusal(tag, role, life, algorithm, lock) is None]
        # Reserve witnesses for all effective algorithm and lock values. Randomize role details and remaining pairs.
        witness = [("DEFAULT", "DEFAULT"), ("COPY", "NONE"), ("COPY", "SHARED"),
                   ("INPLACE", "EXCLUSIVE"), ("DEFAULT", "NONE")]
        pair = witness[ordinal] if ordinal < len(witness) and witness[ordinal] in pairs else rng.choice(pairs)
        yield policy_model(tag, (role, life, *pair, rng.choice(axes[2])), rng=rng)


def capacity_model(tag, count, primary):
    model = creation.definition(tag, "persistent", [0], [False], False, True, "new_key",
                                identity="explicit" if primary else "none")
    model["mysqlCreateIndex"]["target"].update(table=CAPACITY_TABLE, columns=["target_field"])
    prior = ["PRIMARY"] if primary and count else []
    prior.extend(f"retained_{n}" for n in range(count - len(prior)))
    model["mysqlCreateIndex"].update(priorKeyCount=count, priorKeys=prior)
    return {"fixture": "capacity", "point": ["capacity", str(count), str(primary)], "model": model,
            "count": count, "primary": primary, "nativeError": TOO_MANY_KEYS if count >= 64 else None}


def capacity_declaration(item):
    fields = ["rid INT NOT NULL", "target_field INT NOT NULL", *(f"k{n} INT NOT NULL" for n in range(65))]
    keys = ["PRIMARY KEY (rid)"] if item["primary"] else []
    keys.extend(f"KEY retained_{n} (k{n})" for n in range(item["count"] - int(item["primary"])))
    return f"CREATE TABLE {quote(SCHEMA)}.{quote(CAPACITY_TABLE)} ({', '.join(fields + keys)}) ENGINE=InnoDB"


def capacity_state(item, inserted):
    keys = creation.key_rows("PRIMARY", [("rid", False)], True, True) if item["primary"] else []
    for n in range(item["count"] - int(item["primary"])):
        keys += creation.key_rows(f"retained_{n}", [(f"k{n}", False)], False, True)
    if inserted:
        keys += creation.key_rows("new_key", [("target_field", False)], False, True)
    return {"rows": [[1, 67, *range(65)], [2, 71, *range(65)]], "indexes": sorted(keys, key=lambda row: (row[0], row[2]))}


def capacity_prediction(item):
    if item["count"] == 65:
        return {"status": "mysql-index-capacity", "declaration": TOO_MANY_KEYS, "catalog": None}
    return {"status": "mysql-index-capacity", "declaration": ["executed"], "initial": capacity_state(item, False),
            "effect": item["nativeError"] or ["executed"], "catalog": capacity_state(item, not item["nativeError"]),
            "marker": 7, "active": False}


def native_prediction(item):
    if item["fixture"] == "capacity":
        return capacity_prediction(item)
    module = creation if item["fixture"] == "create" else removal
    expected = module.prediction(item["model"], item["nativeError"])
    if item["nativeError"] and item["nativeError"][2] == 1800:
        expected.update(marker=0, active=True)
    return expected
