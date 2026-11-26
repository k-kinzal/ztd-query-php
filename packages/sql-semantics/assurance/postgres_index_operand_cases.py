"""Finite recipes for a native index over an otherwise unrelated heading."""

from itertools import product

from postgres_create_btree_cases import UNRELATED_TYPES, case
from run import require

AXES = ("position", "unrelatedType", "operands")
TYPED_ROWS = (
    (3, True, 110, False), (3, False, 220, True), (8, None, 330, False),
    (None, True, 440, None), (9, True, 550, True),
)


def recipes(finite):
    axes = finite["operandProjection"]["axes"]
    return product(*(axes[name] for name in AXES))


def identities(finite):
    axes = finite["operandProjection"]["axes"]
    independent = {
        ("projection", position, kind, shape)
        for position in ("first", "middle", "last")
        for kind in UNRELATED_TYPES
        for shape in ("ordered-keys", "covering", "truth-test", "nested-logical", "unique")
    }
    require(set(axes) == set(AXES) and finite["operandProjection"]["coordinates"] == len(independent),
            "postgres-btree-projection-schedule")
    return independent


def projected_case(tag, position, kind, shape):
    noise_slot = {"first": 0, "middle": 2, "last": 4}[position]
    catalog_kind, _ = UNRELATED_TYPES[kind]
    names = ["small", "active", "payload", "paused"]
    kinds = ["int4", "bool", "int8", "bool"]
    names.insert(noise_slot, "spare value")
    kinds.insert(noise_slot, catalog_kind)
    small, active, payload, paused = (names.index(field) for field in ("small", "active", "payload", "paused"))
    rows = [list(row) for row in TYPED_ROWS]
    probe = [3, True, 665, True]
    for row in [*rows, probe]:
        row.insert(noise_slot, None)
    asc = "ASC NULLS LAST"
    keys = [(small, asc)]
    include = [payload]
    predicate = None
    if shape == "ordered-keys":
        keys = [(small, "DESC NULLS LAST"), (active, asc)]
        include = []
    elif shape == "covering":
        keys = [(active, asc), (small, asc)]
    elif shape == "truth-test":
        predicate = [active, "IS UNKNOWN"]
    elif shape == "nested-logical":
        keys = [(small, "ASC NULLS FIRST")]
        predicate = ["AND", ["OR", [active, "column"], [paused, "column"]], ["NOT", [payload, False], None]]
    elif shape == "unique":
        predicate = ["AND", [active, "IS TRUE"], ["OR", [paused, "IS FALSE"], [paused, "IS UNKNOWN"]]]
    unique = shape == "unique"
    item = case(tag, keys, include, scope="temporary", unique=unique, nulls=unique, columns=names,
                kinds=kinds, rows=rows, coordinate=["projection", position, kind, shape], predicate=predicate,
                probe_rows=(0, 2, 3))
    return {
        **item, "sortSlot": payload, "fixtureTypes": {str(noise_slot): kind},
        "observeMembership": predicate is not None, "observeTruthValues": predicate is not None,
        "probeValues": [probe],
    }


def direct(item):
    """Encode unknown fixture types only at the constructor input boundary."""
    index = item["createBtree"]
    schema, owner, names, kinds, only = index["table"]
    represented = [kind if kind in ("int4", "int8", "bool", "text") else None for kind in kinds]
    return {**item, "createBtree": {**index, "table": [schema, owner, names, represented, only]}}
