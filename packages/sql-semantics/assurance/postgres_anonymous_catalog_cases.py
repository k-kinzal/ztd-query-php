"""Finite and seeded recipes for externally declared PostgreSQL index names."""

from itertools import product
from random import Random

from postgres_btree_name_cases import LAYOUTS, as_sql, candidates, index, labels, qualified, quoted
from postgres_create_btree_cases import published

PLACEMENTS = {"permanent": "app_catalog", "temporary": "pg_temp", "schema-member": "declared_catalog"}
PREFIXES = (0, 1, 3)
OPERATIONS = ("reindex", "drop", "create")
SPELLINGS = {"REINDEX INDEX": "reindex", "DROP INDEX": "drop", "CREATE INDEX": "create"}
DECOY = "alter_catalog_decoy"
PATH = ["pg_temp", "app_catalog", "declared_catalog", "public", "pg_catalog"]
ROWS = [[1, 10, 20, 30], [1, 10, None, 31], [None, 40, 0, 33]]
COMMAND = {"reindex": "REINDEX", "drop": "DROP INDEX", "create": "CREATE INDEX"}


def coordinates(bound):
    axes = bound["axes"]
    return product(axes["layout"], axes["placement"], axes["occupiedPrefixLength"],
                   map(SPELLINGS.__getitem__, bound["subsequentOperations"]))


def identities():
    return set(product(LAYOUTS, PLACEMENTS, PREFIXES, OPERATIONS))


def make_case(tag, layout, placement, prefix, operation, *, operands=None, earlier=2, data=None):
    name, columns, keys, include = LAYOUTS[layout] if operands is None else operands
    schema = PLACEMENTS[placement]
    labeled = [columns[slot] for slot in (*keys, *include)]
    names = [candidates(name, labeled, n) for n in range(prefix + earlier + 1)]
    original, fresh = names[prefix:-1], names[-1]
    owner = qualified(schema, name)
    heading = ", ".join(f"{quoted(value)} integer" for value in columns)
    relative = owner if placement == "permanent" else quoted(name)
    target = index(tag, schema, name, columns, keys, include, fresh, None)
    del target["release"]
    automatic = as_sql(target, automatic=True, unqualified=placement != "permanent")
    temporary = "TEMP " if placement == "temporary" else ""
    table = f"CREATE {temporary}TABLE {relative} ({heading})"
    setup = [f"CREATE SCHEMA {DECOY}", f"CREATE TABLE {DECOY}.orders (id integer)",
             *(f"CREATE INDEX {quoted(value)} ON {DECOY}.orders(id)" for value in (*original, fresh))]
    if placement == "schema-member":
        occupants = [f"CREATE SEQUENCE {quoted(value)}" for value in names[:prefix]]
        setup += [f"CREATE SCHEMA {schema} " + " ".join([table, *occupants, *[automatic] * earlier])]
    else:
        if placement == "permanent":
            setup.append(f"CREATE SCHEMA {schema}")
        occupants = [quoted(value) if temporary else qualified(schema, value) for value in names[:prefix]]
        setup += [table, *(f"CREATE {temporary}TABLE {value} (sentinel integer)" for value in occupants), *[automatic] * earlier]

    variants = {
        "create": (as_sql(target, automatic=True), "createBtree", target),
        "reindex": ("REINDEX INDEX " + qualified(schema, original[-1]), "reindex",
                    {"target": [schema, original[-1], name], "mode": "standard"}),
        "drop": ("DROP INDEX " + qualified(schema, original[0]), "dropIndex",
                 {"targets": [["bound", schema, original[0], name]], "ifExists": False, "cascade": False, "mode": "ordinary"}),
    }
    statement, key, body = variants[operation]
    return {
        "coordinate": [layout, placement, prefix, operation], "tag": tag, "operation": operation,
        "owner": [schema, name], "setup": setup, "original": statement, "direct": {key: body},
        "columns": list(columns), "keys": list(keys), "include": list(include), "data": ROWS if data is None else data,
        "initial": original, "fresh": fresh,
    }


def direct(item):
    return {"dialect": "postgres", "release": item["tag"], **item["direct"]}


def analyzed(item):
    return {"dialect": "postgres", "release": item["tag"], "sql": item["original"],
            "declarations": item["setup"], "schemaPath": PATH}


def value_shape(item):
    ((key, value),) = item["direct"].items()
    return key, published(value) if key == "createBtree" else value


def physical(item, label):
    slots = item["keys"] + item["include"]
    fields = [item["columns"][slot] for slot in slots]
    return [label, "btree", len(item["keys"]), [slot + 1 for slot in slots], list(labels(fields)),
            [0] * len(item["keys"]), ["int4_ops"] * len(item["keys"]), True, False]


def expected(item):
    selected = item["operation"]
    initial = item["initial"]
    remaining = {"reindex": initial, "drop": initial[1:], "create": [*initial, item["fresh"]]}[selected]
    other = [[name, "btree", 1, [1], ["id"], [0], ["int4_ops"], True, False] for name in (*initial, item["fresh"])]
    return {
        "status": "anonymous-catalog-observation", "command": COMMAND[selected], "sqlState": "00000",
        "before": [[item["owner"], [physical(item, name) for name in initial], item["data"]],
                   [[DECOY, "orders"], other, [[976]]]],
        "after": [[item["owner"], [physical(item, name) for name in remaining], item["data"]],
                  [[DECOY, "orders"], other, [[976]]]],
        "changedFiles": [[item["owner"][0], initial[-1]]] if selected == "reindex" else [],
    }


def generated(tag, seed, count):
    rng = Random(seed)
    assert count % len(OPERATIONS) == 0
    for n in range(count // len(OPERATIONS)):
        template = n % 4
        width = rng.randint(4, 7)
        stem = f'{rng.randrange(100, 9999)}."'
        owner = "テ" * rng.randint(12, 18) + stem if template >= 2 else "orders" + stem
        columns = [f"c{n}_{rng.randrange(200, 999)}" for n in range(width)]
        picks = rng.sample(range(width - 1), rng.randint(1, width - 1))
        keys = [*picks, picks[0]] if template in (1, 3) else picks
        include = rng.sample(range(width - 1), rng.randint(1, 3)) if template >= 2 else []
        data = [[rng.choice((None, 0, 1, -10, 2147483647)) for _ in range(width - 1)] + [row]
                for row in range(4)]
        placement = tuple(PLACEMENTS)[n % len(PLACEMENTS)]
        prefix = 0 if n == 0 else rng.randint(1, 10)
        for operation in OPERATIONS:
            yield make_case(tag, "seeded", placement, prefix, operation,
                            operands=(owner, columns, keys, include), earlier=rng.randint(2, 4), data=data)
