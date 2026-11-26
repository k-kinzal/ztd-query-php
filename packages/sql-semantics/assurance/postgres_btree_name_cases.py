"""Independent recipes for PostgreSQL's automatic B-tree name and catalog."""

from itertools import product
from random import Random

from postgres_create_btree_cases import BITS, conflicts, published, qualifies, sql as btree_sql
from postgres_create_sequence_cases import sql as sequence_sql, trial as sequence
from postgres_drop_index_cases import qualified, quoted
from postgres_schema_sequences_cases import sequence_shape
from postgres_schema_tables_cases import table, table_sql
from run_server_postgres_with_projection import scan

NS = "binding_fixture"
DECOY = "alternate_fixture"
BATCH = "automatic_batch"
LAYOUTS = {
    "single": ("orders", ("id", "id1", "closed", "payload"), (0,), ()),
    "key-order": ("orders", ("id", "id1", "closed", "payload"), (3, 0), ()),
    "includes": ("orders", ("id", "id1", "closed", "payload"), (0,), (3, 2)),
    "label-collisions": ("orders", ("id", "id1", "closed", "payload"), (0, 0, 1), (0, 3)),
    "asymmetric-utf8": ("行" * 20 + "a", ("x", "id1", "closed", "payload"), (0,), ()),
    "equal-utf8-bytes": ("表" * 20 + "x", ("列" * 20 + "y", "id1", "closed", "payload"), (0, 0), (3,)),
}
ROLES = ("named-table", "generated-index", "sequence", "namespace-decoy")
SCHEMA_ROLES = ("none", "table", "sequence", "view", "index")
SUFFIXES = (0, 1, 3, 11)
POLICIES = ("plain", "unique")
ROWS = [[1, 10, 20, 30], [2, 20, None, 31], [3, 30, 20, 32], [None, 40, 0, 33]]
ASC = "ASC NULLS LAST"
DESC = "DESC NULLS LAST"
INDEX_ROLE = {"named-table": "r", "generated-index": "i", "sequence": "S", "namespace-decoy": "r"}


def identities():
    return {("standalone", *args) for args in product(LAYOUTS, ROLES, SUFFIXES, POLICIES)} | {
        ("schema", *args) for args in product(LAYOUTS, SCHEMA_ROLES)
    }


def enumerate_coordinates(contract):
    standalone = contract["standalone"]["axes"]
    schema = contract["schema"]["axes"]
    for axis in product(*(standalone[key] for key in ("layout", "existingRole", "occupiedPrefixLength", "policy"))):
        yield ("standalone", *axis)
    for axis in product(*(schema[key] for key in ("layout", "earlierOccupant"))):
        yield ("schema", *axis)


def clip(value, budget):
    return value.encode("utf-8")[:budget].decode("utf-8", errors="ignore")


def labels(fields):
    used = set()
    for field in fields:
        variants = (clip(field, 63 - len(str(i))) + str(i) for i in range(1, len(used) + 2))
        result = field if field not in used else next(candidate for candidate in variants if candidate not in used)
        used.add(result)
        yield result


def candidates(table_name, fields, number):
    suffix = "idx" + (str(number) if number else "")
    remaining = 61 - len(suffix)
    attributes = "_".join(labels(fields))
    one, two = len(table_name.encode()), len(attributes.encode())
    if one + two > remaining:
        one = min(one, max((remaining + 1) // 2, remaining - two))
        two = remaining - one
    return "_".join((clip(table_name, one), clip(attributes, two), suffix))


def index(tag, schema, table_name, columns, keys, include, name, negated, *, unique=False, descending=False):
    return {
        "release": tag, "table": [schema, table_name, list(columns), ["int4"] * len(columns), False],
        "keys": [[slot, DESC if descending and i == 0 else ASC] for i, slot in enumerate(keys)],
        "included": list(include), "predicate": [2, negated] if negated is not None else None,
        "name": name, "presence": "vacant", "ifNotExists": False, "unique": unique,
        "nullsNotDistinct": False, "concurrently": False,
    }


def as_sql(index, *, automatic, unqualified=False):
    key = index["name"]
    command = btree_sql({"createBtree": index, "coordinate": None}, unqualified=unqualified)
    return command.replace("INDEX " + quoted(key) + " ON ", "INDEX ON ", 1) if automatic else command


def declaration(tag, owner, names):
    return table(tag, list(owner), [[name, "INTEGER", True, None] for name in names], False)


def standalone(tag, layout, role, count, policy, chosen=None):
    name, all_fields, keys, include = LAYOUTS[layout] if chosen is None else chosen
    physical = [all_fields[slot] for slot in (*keys, *include)]
    occupant_schema = DECOY if role == "namespace-decoy" else NS
    taken = [candidates(name, physical, number) for number in range(count)]
    selected = candidates(name, physical, 0 if role == "namespace-decoy" else count)
    first = index(tag, NS, name, all_fields, keys, include, selected, True, unique=policy == "unique", descending=True)
    occupant = {
        "named-table": lambda n: "CREATE TABLE " + qualified(NS, n) + " (sentinel integer)",
        "generated-index": lambda n: "CREATE INDEX " + quoted(n) + " ON " + qualified(NS, name) + " (id1)",
        "sequence": lambda n: "CREATE SEQUENCE " + qualified(NS, n),
        "namespace-decoy": lambda n: "CREATE TABLE " + qualified(DECOY, n) + " (sentinel integer)",
    }[role]
    return {
        "coordinate": ["standalone", layout, role, count, policy], "tag": tag, "owner": [NS, name], "columns": list(all_fields),
        "original": as_sql(first, automatic=True), "direct": {"createBtree": first},
        "setup": ["CREATE SCHEMA " + NS, "CREATE SCHEMA " + DECOY, table_sql(declaration(tag, (NS, name), all_fields), True, False),
                  *map(occupant, taken)],
        "before": [[NS, name, "r"], *[[occupant_schema, old, INDEX_ROLE[role]] for old in taken]],
        "newRelations": [[NS, selected, "i"]], "indexes": [first], "data": ROWS, "scope": "standalone",
    }


def schema_member(tag, layout, role):
    table_name, columns, keys, include = LAYOUTS[layout]
    fields = [columns[slot] for slot in (*keys, *include)]
    names = [candidates(table_name, fields, ordinal) for ordinal in range(3)]
    owner = [BATCH, table_name]
    tables = [declaration(tag, owner, columns)]
    raw_sequence = sequence(tag, "bigint", 1, "default", "permanent")
    raw_sequence["target"] = [BATCH, names[0]]
    view = {"release": tag, "target": [BATCH, names[0]], "columns": ["decoy"],
            "query": scan(None, [[["integer", "8"], "decoy"]], source=False), "replace": False,
            "securityBarrier": None, "securityInvoker": None, "checkOption": ""}
    prior_indexes = [index(tag, BATCH, table_name, columns, (1,), (), names[0], None)] if role == "index" else []
    if role == "table":
        tables.append(declaration(tag, [BATCH, names[0]], ["decoy"]))
    occupied = int(role != "none")
    added = [index(tag, BATCH, table_name, columns, keys, include, names[i + occupied], bool(i)) for i in range(2)]
    created = [*prior_indexes, *added]
    setup = [table_sql(item, False, False) for item in tables]
    if role == "view":
        setup.append("CREATE VIEW " + quoted(names[0]) + " AS SELECT 8 AS " + quoted("decoy"))
    if role == "sequence":
        setup.append(sequence_sql(raw_sequence))
    statement = " ".join(["CREATE SCHEMA " + BATCH, *setup, *(as_sql(i, automatic=False, unqualified=True) for i in prior_indexes),
                          *(as_sql(i, automatic=True, unqualified=True) for i in added)])
    header = {"name": BATCH, "owner": {"session": "CURRENT_USER"}, "ifNotExists": False}
    direct = {**header, "tables": tables, "indexes": created, "views": [view] if role == "view" else [],
              "sequences": [sequence_shape(raw_sequence, True)] if role == "sequence" else []}
    namespace = [[BATCH, table_name, "r"], *[[BATCH, item["name"], "i"] for item in created]]
    if role not in ("none", "index"):
        namespace.append([BATCH, names[0], {"table": "r", "view": "v", "sequence": "S"}[role]])
    return {
        "coordinate": ["schema", layout, role], "tag": tag, "owner": owner, "columns": list(columns),
        "original": statement, "direct": {"createSchema": direct}, "setup": [], "before": [], "newRelations": namespace,
        "indexes": created, "data": ROWS, "scope": "schema",
    }


def index_shape(item, index):
    encoded = {key: value for key, value in index.items() if key != "release"}
    return {"release": item["tag"], **published(encoded)}


def graph(item, response):
    if item["scope"] == "standalone":
        return [{**response["createBtree"], "release": item["tag"]}]
    return response["schemaIndexes"]


def index_record(item, definition):
    schema, owner, names, _, _ = definition["table"]
    slots = [position for position, _ in definition["keys"]] + definition["included"]
    predicate = definition["predicate"]
    return {
        "name": definition["name"], "nameBytes": len(definition["name"].encode()), "owner": [schema, owner], "method": "btree",
        "orderedLabels": list(labels(names[slot] for slot in slots)), "positions": [slot + 1 for slot in slots],
        "keyCount": len(definition["keys"]), "classNames": ["int4_ops"] * len(definition["keys"]),
        "direction": [BITS[order] for _, order in definition["keys"]], "collations": [0] * len(definition["keys"]),
        "unique": definition["unique"], "nullsNotDistinct": False, "valid": True,
        "membership": None if predicate is None else [qualifies(predicate, row) for row in item["data"]],
    }


def proposals(item):
    return [*item["data"], [1, 90, None, 89], [2, 91, 0, 93]]


def expected(item):
    rows = item["data"]
    return {
        "status": "automatic-index-observation", "sqlState": "00000", "command": "CREATE SCHEMA" if item["scope"] == "schema" else "CREATE INDEX",
        "notices": [], "before": sorted(item["before"]), "after": sorted([*item["before"], *item["newRelations"]]),
        "indexes": [index_record(item, definition) for definition in item["indexes"]],
        "rows": rows, "heading": [[field, "int4"] for field in item["columns"]],
        "writes": [
            "23505" if any(ind["unique"] and conflicts(ind["keys"], [*rows, row], False, ind["predicate"])
                           for ind in item["indexes"]) else "00000"
            for row in proposals(item)
        ],
    }


def seeded(tag, seed, count):
    rng = Random(seed)
    for ordinal in range(count):
        selected = list(range(4))
        table_name = rng.choice(("ledger", "§", "棚" * 13, 'Q"')) + "x" * rng.randrange(18)
        fields = ("𠮷" * rng.randrange(1, 10) + str(ordinal), "id1", "closed", "amount" + rng.choice(("C", "書", " ")))
        keys = tuple(rng.choices(selected, k=1 + ordinal % 6))
        included = tuple(rng.choices(selected, k=ordinal % 5))
        # Rows are fixed independently of the name. Dynamic cases can repeat a key freely.
        yield standalone(tag, str(ordinal), ROLES[ordinal % 4], rng.choice(SUFFIXES), "plain",
                         chosen=(table_name, fields, keys, included))
