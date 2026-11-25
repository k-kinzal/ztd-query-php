"""Independent initial and resulting native MySQL index namespaces."""

from itertools import product
from random import Random

from mysql_drop_index_cases import LIFETIMES
from mysql_drop_indexes_cases import native_rows, sibling
from mysql_index_auto_increment_cases import native_key, state
from mysql_innodb_declaration_width_cases import SCHEMA, quote

TABLE = "ir_index_set"
STATUS = "mysql-index-renames"
TOPOLOGIES = ("fresh", "closed-cycle", "open-chain")
ORDERS = ("forward", "reverse")


def operation(tag, life, keys, moves, *, offset=0, generated=False, policy=("DEFAULT", "DEFAULT")):
    selected = {source for source, _ in moves}
    shape = {"owner": [SCHEMA, TABLE, "temporary-table" if life != "persistent" else "table"],
             "moves": list(map(list, moves)), "unmoved": [key["name"] for key in keys if key["name"] not in selected],
             "policy": list(policy)}
    written = [[source.swapcase(), target] for source, target in moves]
    return {"dialect": "mysql", "release": tag, "keys": keys, "mysqlIndexRenames": shape, "life": life,
            "declaration": "attribute" if generated else "plain", "offset": offset, "written": written}


def movement(keys, topology, suffix):
    sequence = [key["name"] for key in keys]
    if topology == "fresh":
        destinations = [f"fresh_{suffix}_{index}" for index in range(len(keys))]
    elif topology == "closed-cycle":
        destinations = [*sequence[1:], sequence[0]]
    else:
        destinations = [*sequence[1:], f"chain_end_{suffix}"]
    return list(zip(sequence, destinations))


def finite_points(finite):
    return set(product(finite["lifetimes"], finite["widths"], finite["topologies"], finite["orders"]))


def coordinate(tag, point):
    life, count, topology, order = point
    stem = "idx_reverse" if count == 1 and order == "reverse" else "idx"
    keys = [native_key(f"{stem}_{index:02}", "ordinary", [["a", "b"], ["b", "c"], ["c", "a"]][index % 3])
            for index in range(count)]
    moves = movement(keys, topology, "finite")
    if order == "reverse":
        moves.reverse()
    if count < 64:
        keys.append(native_key("untouched", "clustered-unique", ["b"]))
    return operation(tag, life, keys, moves)


def explored(tag, sample_seed, count):
    rng = Random(sample_seed)
    for index in range(count):
        life = LIFETIMES[(index + sample_seed) % len(LIFETIMES)]
        topology = TOPOLOGIES[(index // len(LIFETIMES)) % len(TOPOLOGIES)]
        generated = index % 4 == 0
        size = rng.randrange(2, 14)
        keys = [native_key(f"Key_É{sample_seed}_{n}", "ordinary", [rng.choice("abc")],
                           descending=bool(rng.randrange(2)), visible=tag == "mysql-5.7.44" or bool(rng.randrange(2)))
                for n in range(size)]
        if generated:
            keys[0] = native_key("sole_support", "ordinary", ["a", "b"])
        moves = movement(keys, topology, f"É{sample_seed}")
        rng.shuffle(moves)
        keys.append(native_key("untouched", "clustered-unique", ["b"]))
        yield operation(tag, life, keys, moves, generated=generated, offset=rng.randrange(31))


def ddl(item, temporary):
    terms = ["a INT NOT NULL" + (" AUTO_INCREMENT" if item["declaration"] != "plain" else ""), "b INT NOT NULL", "c INT NULL"]
    for key in item["keys"]:
        keyword = "PRIMARY KEY" if key["role"] == "primary" else ("KEY " if key["role"] == "ordinary" else "UNIQUE KEY ") + quote(key["name"])
        fields = [field + (" DESC" if index == 0 and key["descending"] else "") for index, field in enumerate(key["fields"])]
        visibility = "" if key["visible"] else " INVISIBLE"
        terms.append(f"{keyword} ({', '.join(fields)}){visibility}")
    return f"CREATE {'TEMPORARY ' if temporary else ''}TABLE {quote(SCHEMA)}.{quote(TABLE)} ({', '.join(terms)}) ENGINE=InnoDB"


def declarations(item):
    temporary = item["life"] != "persistent"
    prefix = [ddl(sibling(item), False)] if item["life"] == "temporary-shadow" else []
    return [*prefix, ddl(item, temporary)]


def sql(item, original=False):
    shape = item["mysqlIndexRenames"]
    options = [f"{kind}={value}" for kind, value in zip(("ALGORITHM", "LOCK"), shape["policy"]) if value != "DEFAULT"]
    selected = item["written"] if original else shape["moves"]
    actions = []
    for index, (source, target) in enumerate(selected):
        qualifier = f"{quote(SCHEMA)}.{quote(TABLE)}."
        label = "KEY" if original and index % 2 == 0 else "INDEX"
        qualified = original and item.get("qualify", False)
        if qualified:
            actions.append(f"RENAME {label} {qualifier}{quote(source)} TO {qualifier}{quote(target)}")
        else:
            actions.append(f"RENAME {label} {quote(source)} TO {quote(target)}")
    commands = [*options[:1], *actions, *options[1:]] if original else [*actions, *options]
    return f"ALTER TABLE {quote(SCHEMA)}.{quote(TABLE)} " + ", ".join(commands)


def analyze(item):
    return {"dialect": "mysql", "release": item["release"], "currentDatabase": SCHEMA,
            "declarations": declarations(item), "sql": sql(item, True)}


def topology_seen(moves):
    sources = {source for source, _ in moves}
    destinations = {target for _, target in moves}
    if sources == destinations:
        return "closed-cycle"
    if any(target.startswith("chain_end_") for target in destinations):
        return "open-chain"
    return "fresh"


def prediction(item, failure):
    temporary = item["life"] != "persistent"
    before = state(item, temporary)
    labels = dict(item["mysqlIndexRenames"]["moves"])
    keys = [{**key, "name": labels.get(key["name"], key["name"])} for key in item["keys"]] if failure is None else item["keys"]
    after = state(item, temporary, keys=keys)
    generated = item["declaration"] != "plain"
    next_id = before["rows"][-1][0] + 1
    advanced = state(item, temporary, keys=keys, rows=[*before["rows"], [next_id, 79, 5]]) if generated else None
    catalog = state(sibling(item), False) if item["life"] == "temporary-shadow" else None if temporary else advanced or after
    early = failure is not None and failure[-1] in {1064, 1102, 1103, 1800}
    copies_rows = temporary or item["mysqlIndexRenames"]["policy"][0] == "COPY"
    affected = None if failure is not None else len(before["rows"]) if copies_rows else 0
    return {"status": STATUS, "initial": before, "effect": failure or ["executed"], "after": after,
            "afterGenerated": advanced, "catalog": catalog, "generated": str(next_id) if generated else None,
            "marker": 0 if early else 7, "active": early, "affected": affected}
