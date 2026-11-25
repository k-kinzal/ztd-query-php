"""Independent finite model of simultaneous removals and renames of native MySQL keys."""

from itertools import product
from random import Random

from mysql_drop_index_cases import LIFETIMES
from mysql_drop_indexes_cases import native_rows, sibling
from mysql_index_auto_increment_cases import native_key, state
from mysql_index_rename_cases import declarations, quote, TABLE, SCHEMA

STATUS = "mysql-mixed-index-alteration"
COMPOSITIONS = ("free-removed-label", "fresh-destination", "open-chain")
ORDERING = ("remove-first", "rename-first")


def make(tag, life, keys, changes, *, policy=("DEFAULT", "DEFAULT"), generated=False, offset=0):
    owner = [SCHEMA, TABLE, "temporary-table" if life != "persistent" else "table"]
    by_name = {key["name"]: key for key in keys}
    support = [key["name"] for key in keys if generated and key["fields"][0] == "a"]
    acted = {entry[1] for entry in changes}
    operations = []
    written = []
    for kind, source, *args in changes:
        if kind == "drop":
            dependency = {"field": "a", "alternates": [name for name in support if name != source]} if source in support else None
            operations.append({"drop": {"owner": owner.copy(), "name": source, "role": by_name[source]["role"],
                                         "autoIncrement": dependency}})
        else:
            operations.append({"rename": [source, args[0]]})
        written.append([kind, source.swapcase(), *args])
    stable = [key["name"] for key in keys if key["name"] not in acted]
    return {"dialect": "mysql", "release": tag, "keys": keys, "life": life, "offset": offset,
            "declaration": "attribute" if generated else "plain", "written": written,
            "mysqlMixedIndexes": {"owner": owner, "actions": operations, "unmoved": stable, "policy": list(policy)}}


def coordinate(tag, point, *, stem="key", order=None, offset=0):
    life, size, kind, direction = point
    keys = [native_key(f"{stem}_{i:02}", "ordinary", [["a", "b"], ["b", "c"], ["c", "a"]][i % 3])
            for i in range(size)]
    depth = min(size - 1, 1 + size // 7)
    drops = [("drop", key["name"]) for key in keys[:depth]]
    selected = [key["name"] for key in keys[depth:]]
    if kind == "free-removed-label":
        destinations = [keys[0]["name"], *[f"fresh_{stem}_{index}" for index in range(1,len(selected))]]
    elif kind == "fresh-destination":
        destinations = [f"fresh_{stem}_{index}" for index in range(len(selected))]
    else:
        destinations = [*selected[1:], f"chain_end_{stem}"]
    renames = [("rename", source, dest) for source, dest in zip(selected, destinations)]
    actions = drops + renames if direction == "remove-first" else renames[::-1] + drops
    if order is not None:
        order.shuffle(actions)
    if size < 64:
        keys.append(native_key("unmodified", "ordinary", ["b","a"]))
    return make(tag, life, keys, actions, offset=offset)


def finite_points(form):
    return set(product(form["lifetimes"], form["totalCoordinates"], form["composition"], form["actionOrder"]))


def generating(tag, life, keep, *, direction="remove-first", offset=0):
    keys = [native_key("PRIMARY", "primary", ["a"]), native_key("moved_support", "ordinary", ["a" if keep else "b", "c"]),
            native_key("unmoved", "ordinary", ["c","a"])]
    actions = [("drop", "PRIMARY"), ("rename", "moved_support", "final_label")]
    return make(tag, life, keys, actions if direction == "remove-first" else actions[::-1], generated=True, offset=offset)


def explored(tag, seed, limit):
    rng = Random(seed)
    for i in range(limit):
        life = LIFETIMES[(i + seed) % len(LIFETIMES)]
        form = COMPOSITIONS[(i // len(LIFETIMES)) % len(COMPOSITIONS)]
        direction = ORDERING[i % 2]
        offset = rng.randrange(31)
        if i % 8 == 7:
            yield generating(tag, life, True, direction=direction, offset=offset)
        else:
            yield coordinate(tag, (life,rng.randrange(2,15),form,direction), stem=f"clé_{seed}_{i}", order=rng, offset=offset)


def kind(actions):
    removed = {action["drop"]["name"] for action in actions if "drop" in action}
    destinations = {action["rename"][1] for action in actions if "rename" in action}
    if removed & destinations:
        return "free-removed-label"
    if any(name.startswith("chain_end_") for name in destinations):
        return "open-chain"
    return "fresh-destination"


def sql(model, original=False):
    action_list = model["mysqlMixedIndexes"]["actions"]
    if original:
        source = model["written"]
    else:
        source = [["drop", entry["drop"]["name"]] if "drop" in entry else ["rename", *entry["rename"]] for entry in action_list]
    commands = []
    qualifier = f"{quote(SCHEMA)}.{quote(TABLE)}." if original and model.get("qualify", False) else ""
    for index, (action, name, *rest) in enumerate(source):
        if action == "drop":
            label = "KEY" if original and index % 2 == 0 else "INDEX"
            commands.append("DROP PRIMARY KEY" if original and name == "primary" else f"DROP {label} {qualifier}{quote(name)}")
        else:
            label = "KEY" if original and index % 2 == 1 else "INDEX"
            commands.append(f"RENAME {label} {qualifier}{quote(name)} TO {qualifier}{quote(rest[0])}")
    options = [f"{label}={value}" for label,value in zip(("ALGORITHM","LOCK"),model["mysqlMixedIndexes"]["policy"]) if value != "DEFAULT"]
    effective = [*options[:1], *commands, *options[1:]] if original else [*commands, *options]
    return f"ALTER TABLE {quote(SCHEMA)}.{quote(TABLE)} " + ", ".join(effective)


def analysis(model):
    return {"dialect": "mysql", "release": model["release"], "currentDatabase": SCHEMA,
            "declarations": declarations(model), "sql": sql(model, True)}


def physical(model):
    actions = model["mysqlMixedIndexes"]["actions"]
    return [action["drop"] for action in actions if "drop" in action], [action["rename"] for action in actions if "rename" in action]


def prediction(model, failure):
    drops, renames = physical(model)
    removed = {operation["name"] for operation in drops}
    replacements = dict(renames)
    temporary = model["life"] != "persistent"
    before = state(model, temporary)
    keys = [{**key, "name": replacements.get(key["name"],key["name"])} for key in model["keys"] if key["name"] not in removed] if failure is None else model["keys"]
    after = state(model, temporary, keys=keys)
    generated = model["declaration"] != "plain"
    next_id = before["rows"][-1][0] + 1
    advanced = state(model, temporary, keys=keys, rows=[*before["rows"], [next_id,79,5]]) if generated else None
    catalog = state(sibling(model),False) if model["life"] == "temporary-shadow" else None if temporary else advanced or after
    policy = model["mysqlMixedIndexes"]["policy"]
    copies = temporary or policy[0] == "COPY" or any(drop["role"] in ("primary","clustered-unique") for drop in drops)
    early = failure is not None and failure[-1] in {1064,1102,1103,1800}
    return {"status": STATUS, "initial": before, "effect": failure or ["executed"], "after": after, "afterGenerated": advanced,
            "catalog": catalog, "generated": str(next_id) if generated else None, "marker": 0 if early else 7, "active": early,
            "affected": None if failure is not None else len(before["rows"]) if copies else 0}
