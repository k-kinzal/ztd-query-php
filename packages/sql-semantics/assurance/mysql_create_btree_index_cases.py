"""Independent recipes for ordinary MySQL native index declarations and effects."""

from itertools import product
from random import Random

from mysql_innodb_declaration_width_cases import COLLATION, OLD, SCHEMA, quote

NAME, MARKER = "ir_btree_subject", "ir_btree_marker"
STATUS = "mysql-create-btree"
LIFETIMES = ("persistent", "temporary", "temporary-shadow")
LAYOUTS = {
    "single-required": (0,),
    "single-nullable": (1,),
    "composite-reordered": (2, 1, 0),
    "sixteen-ordered": tuple(range(16)),
}
TYPES = tuple("int" if slot in (1, 3) else "int-not-null" for slot in range(18))
FIELD_NAMES = tuple(f"f{slot}" for slot in range(len(TYPES)))
IDENTITY = "An implicit primary index must be visible."


def field_type(name):
    return TYPES[FIELD_NAMES.index(name)]


def role(index):
    if not index["unique"]:
        return "ordinary"
    types = index["target"]["columnTypes"]
    required = all(types[slot] == "int-not-null" or
                   (isinstance(types[slot], list) and len(types[slot]) == 4 and types[slot][3] is False)
                   for slot, _ in index["columns"])
    return "clustered-unique" if required and not index["ownerHasIdentityKey"] else "unique"


def published_index(definition):
    return {key: definition[key] for key in ("target", "name", "visible", "policy", "columns")} | {"role": role(definition)}

def definition(tag, lifetime, fields, descends, unique, visible, name, identity="explicit", data="ordinary"):
    heading = sorted(fields)
    target = {"schema": SCHEMA, "table": NAME, "columns": [FIELD_NAMES[field] for field in heading],
              "columnTypes": [TYPES[field] for field in heading]}
    if lifetime != "persistent":
        target["relationKind"] = "temporary-table"
    prior = {"explicit": ["PRIMARY"], "required": ["prior_identity"],
             "nullable": ["prior_nullable"], "none": []}[identity]
    if data in ("known-occupied", "accent-distinct"):
        prior.append("occupied_ix")
    return {"dialect": "mysql", "release": tag, "identity": identity, "data": data, "lifetime": lifetime,
            "mysqlCreateIndex": {"target": target, "name": name, "unique": unique, "visible": visible,
                "ownerHasIdentityKey": identity in {"explicit", "required"},
                "priorKeyCount": len(prior), "priorKeys": prior, "policy": ["DEFAULT", "DEFAULT"],
                "columns": [[heading.index(field), written and tag not in OLD] for field, written in zip(fields, descends)]},
            "writtenDirections": list(descends)}


def coordinate(tag, point):
    lifetime, layout, orientation, unique, visibility = point
    fields = LAYOUTS[layout]
    descending = orientation == "descending"
    # Preserve a separately measured identity for the equivalent written legacy directions.
    name = "ix_d" if descending else "ix_a"
    return definition(tag, lifetime, fields, [descending] * len(fields), unique, visibility == "visible", name)


def coordinates(tag):
    return set(product(LIFETIMES, LAYOUTS, ("ascending", "descending"), (False, True),
                       ("visible",) if tag in OLD else ("visible", "invisible")))


def generated(tag, seed, count):
    rng = Random(seed)
    for index in range(count):
        clustered = index % 4 == 1
        eligible = [position for position, field in enumerate(TYPES) if not clustered or field == "int-not-null"]
        width = 1 + index if index < 8 else rng.randrange(9, 17)
        slots = rng.sample(eligible, width)
        directions = [bool(rng.randrange(2)) for _ in slots]
        identity = ("none" if index % 8 == 1 else "nullable") if clustered else "explicit"
        visible = clustered or tag in OLD or bool((index // 2) % 2)
        yield definition(tag, LIFETIMES[index % len(LIFETIMES)], slots, directions, bool(index % 2),
                         visible, f"ix quoted ` {seed}_{index}", identity=identity)


def native_edges(tag):
    yield "unicode-max", definition(tag, "persistent", [0], [False], False, True, "界" * 64)
    yield "collision-existing-name", definition(tag, "persistent", [0], [False], False, True, "occupied_ix", data="occupied")
    yield "accent-distinct-name", definition(tag, "persistent", [0], [False], False, True, "occupiÉd_ix", data="accent-distinct")
    yield "duplicate-populated-rows", definition(tag, "persistent", [0], [False], True, True, "duplicate_ix", data="duplicate")
    yield "temporary-qualified", definition(tag, "temporary-shadow", [17, 2], [True, False], True, True, "shadow_ix")
    yield "first-clustered-required", definition(tag, "persistent", [0], [False], True, True, "heap_to_cluster", identity="none")
    yield "first-clustered-after-nullable", definition(tag, "persistent", [0], [False], True, True, "cluster_after_nullable", identity="nullable")
    if tag not in OLD:
        for identity, fields, role in (("none", [1], "nullable-first"),
                                       ("required", [0], "after-required"),
                                       ("explicit", [0], "after-explicit")):
            yield role, definition(tag, "persistent", fields, [False], True, False, "hidden_ix", identity=identity)


def create_table(lifetime, identity, occupied=False):
    temporary = "TEMPORARY " if lifetime else ""
    fields = [quote("rid") + " INT NOT NULL"]
    for field, type_ in zip(FIELD_NAMES, TYPES):
        fields.append(quote(field) + (" INT NULL" if type_ == "int" else " INT NOT NULL"))
    key = {"explicit": "PRIMARY KEY (`rid`)", "required": "UNIQUE INDEX `prior_identity` (`f17`)",
           "nullable": "UNIQUE INDEX `prior_nullable` (`f3`)", "none": None}[identity]
    if key is not None:
        fields.append(key)
    if occupied:
        fields.append("INDEX `occupied_ix` (`f17`)")
    return (f"CREATE {temporary}TABLE {quote(SCHEMA)}.{quote(NAME)} ({', '.join(fields)}) "
            "ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin")


def declarations(item):
    lifetime, identity = item["lifetime"], item["identity"]
    if lifetime == "persistent":
        return [create_table(False, identity, item["data"] in ("known-occupied", "accent-distinct"))]
    owner = [create_table(True, identity, item["data"] in ("known-occupied", "accent-distinct"))]
    return [create_table(False, "explicit"), *owner] if lifetime == "temporary-shadow" else owner


def command(item, original=False, qualified=True, algorithm=None, spelling=None):
    index = item["mysqlCreateIndex"]
    target = index["target"]
    location = quote(target["table"])
    if qualified:
        location = quote(target["schema"]) + "." + location
    annotations = []
    for at, (slot, descends) in enumerate(index["columns"]):
        written = item["writtenDirections"][at] if original else descends
        tail = " DESC" if written else " ASC" if original else ""
        annotations.append(quote(target["columns"][slot]) + tail)
    uniqueness = "UNIQUE " if index["unique"] else ""
    before, after = (" USING BTREE" if algorithm == "before" else "",
                     " USING BTREE" if algorithm == "after" else "")
    visibility = " INVISIBLE" if not index["visible"] else ""
    if spelling:
        visibility = spelling
    return (f"CREATE {uniqueness}INDEX {quote(index['name'])}{before} ON {location}"
            f" ({', '.join(annotations)}){after}{visibility}")


def analyze(item, sql=None):
    return {"dialect": "mysql", "release": item["release"], "sql": command(item, original=True) if sql is None else sql,
            "currentDatabase": SCHEMA, "declarations": declarations(item)}


def probe_rows(temporary, conflict=False):
    base = 100 if temporary else 10
    rows = [[1 + ordinal, *(None if slot in (1, 3) else base + (slot * 7) + ordinal * 3
                            for slot in range(len(FIELD_NAMES)))] for ordinal in range(3)]
    if conflict:
        rows[1][1] = rows[0][1]
    return rows


def key_rows(name, columns, unique, visible):
    return [[name, 0 if unique else 1, i, field, "D" if descending else "A",
             field in FIELD_NAMES and field_type(field) == "int", "BTREE", visible, None]
            for i, (field, descending) in enumerate(columns, 1)]


def table_state(temporary, item, after=False, runtime_error=None, rows=None):
    if rows is None:
        rows = probe_rows(temporary, item["data"] == "duplicate")
    identity, meta = item["identity"], []
    if identity == "explicit":
        meta += key_rows("PRIMARY", [("rid", False)], True, True)
    elif identity == "required":
        meta += key_rows("prior_identity", [("f17", False)], True, True)
    elif identity == "nullable":
        meta += key_rows("prior_nullable", [("f3", False)], True, True)
    if item["data"] in ("occupied", "known-occupied", "accent-distinct"):
        meta += key_rows("occupied_ix", [("f17", False)], False, True)
    if after and runtime_error is None:
        index = item["mysqlCreateIndex"]
        fields = [(index["target"]["columns"][slot], direction) for slot, direction in index["columns"]]
        meta += key_rows(index["name"], fields, index["unique"], index["visible"])
    return {"rows": rows, "indexes": sorted(meta, key=lambda part: (part[0], part[2]))}


def physical_roles(item, failure):
    initial = {"explicit": [["PRIMARY", 3]], "required": [["prior_identity", 3]],
               "nullable": [["GEN_CLUST_INDEX", 1], ["prior_nullable", 2]], "none": [["GEN_CLUST_INDEX", 1]]}[item["identity"]]
    if item["data"] in ("occupied", "known-occupied", "accent-distinct"):
        initial.append(["occupied_ix", 0])
    result = list(initial)
    if failure is None:
        definition = item["mysqlCreateIndex"]
        kind = role(definition)
        if kind == "clustered-unique":
            result.remove(["GEN_CLUST_INDEX", 1])
        result.append([definition["name"], {"ordinary": 0, "unique": 2, "clustered-unique": 3}[kind]])
    return {"before": sorted(initial), "after": sorted(result)}


def prediction(item, construction_failure=None):
    temporary = item["lifetime"] != "persistent"
    prior = table_state(temporary, item)
    permanent = None if temporary else prior
    if item["lifetime"] == "temporary-shadow":
        permanent = table_state(False, {**item, "identity": "explicit", "data": "ordinary"})
    runtime_failure = {"duplicate": ["db-error", "23000", 1062], "occupied": ["db-error", "42000", 1061]}.get(item["data"])
    if construction_failure:
        runtime_failure = construction_failure
    result = runtime_failure or ["executed"]
    indexed = table_state(temporary, item, True, runtime_failure)
    prior_write = None
    if not construction_failure:
        index = item["mysqlCreateIndex"]
        fields = [index["target"]["columns"][slot] for slot, _ in index["columns"]]
        first = indexed["rows"][0]
        required = all(first[1 + FIELD_NAMES.index(field)] is not None for field in fields)
        # The probe copies the base row's indexed values while giving it a different primary or prior key value.
        if runtime_failure is None and index["unique"] and required:
            prior_write = ["db-error", "23000", 1062]
        else:
            added = [19, *first[1:]]
            if item["identity"] == "required":
                added[-1] = 997
            prior_write = ["executed"]
            indexed = {**indexed, "rows": [*indexed["rows"], added]}
    return {"status": STATUS, "initial": prior, "effect": result, "after": indexed,
            "catalog": permanent if temporary else indexed, "write": prior_write,
            "marker": 7, "active": False, "physicalRoles": None if temporary else physical_roles(item, runtime_failure)}
