"""Independent native B-tree key, tuple and catalog transition rules."""

from itertools import product

from postgres_drop_index_cases import qualified, quoted
from run import require

SOURCE = "orders"
SCHEMAS = {"persistent": "fixture", "temporary": "pg_temp"}
HEADINGS = ["a", "b", "payload"]
KINDS = ["int4", "int8", "bool"]
SQL_TYPES = {"int4": "integer", "int8": "bigint", "bool": "boolean"}
OPCLASSES = {"int4": "int4_ops", "int8": "int8_ops", "bool": "bool_ops"}
ROWS = [[1, 17, False], [2, 19, True], [None, 21, True], [None, 31, None]]
OLD_INDEX = [[1, "DESC NULLS LAST"]]
BITS = {"ASC NULLS LAST": 0, "DESC NULLS LAST": 1, "ASC NULLS FIRST": 2, "DESC NULLS FIRST": 3}
FIRST = {
    "default": ("ASC NULLS LAST", ""),
    "ascending-nulls-first": ("ASC NULLS FIRST", " ASC NULLS FIRST"),
    "descending-default": ("DESC NULLS FIRST", " DESC"),
    "descending-nulls-last": ("DESC NULLS LAST", " DESC NULLS LAST"),
}
KEY_AXES = ("keys", "firstOrder", "included", "uniqueNullPolicies")
ROUTE_AXES = ("targetScopes", "concurrently", "transaction", "guard", "occupancy")
PATH = ["fixture", "public", "pg_catalog"]
PREFIX = "SqlSemantics\\Platform\\PostgreSql\\Model\\Diagnostic\\"
TRUTH_VALUES = {"TRUE": True, "FALSE": False, "UNKNOWN": None}


def refusal(message, exception="InvalidConstruction"):
    return {"message": message, "exception": PREFIX + exception}


def case(tag, keys, included=(), scope="persistent", occupied="vacant", *,
         guard=False, unique=False, nulls=False, concurrently=False, transaction=False, only=False,
         name="idx", columns=HEADINGS, kinds=KINDS, rows=ROWS, coordinate=None, predicate=None, probe_rows=(0, 2)):
    return {
        "dialect": "postgres", "release": tag, "coordinate": coordinate,
        "createBtree": {
            "table": [SCHEMAS[scope], SOURCE, list(columns), kinds, only],
            "name": name, "keys": [list(key) for key in keys], "included": list(included),
            "presence": "vacant" if occupied == "vacant" else "occupied",
            "ifNotExists": guard, "unique": unique, "nullsNotDistinct": nulls, "concurrently": concurrently,
            "predicate": predicate,
        },
        "occupancy": occupied, "transaction": transaction, "rows": rows, "probeRows": list(probe_rows),
    }


def grid(spec, axes):
    return product(*(spec["finite"][axis] for axis in axes))


def key_case(tag, keys, order, included, policy):
    unique, nulls = policy
    ordering = [FIRST[order][0]] + ["ASC NULLS LAST"] * (len(keys) - 1)
    coordinate = ["key", tuple(keys), order, tuple(included), tuple(policy)]
    return case(tag, zip(keys, ordering), included, unique=unique, nulls=nulls == "not-distinct", coordinate=coordinate)


def route_case(tag, scope, concurrently, transaction, guard, occupied):
    coordinate = ["route", scope, concurrently, transaction, guard, occupied]
    return case(tag, [(0, "ASC NULLS LAST")], scope=scope, occupied=occupied,
                guard=guard, concurrently=concurrently, transaction=transaction, coordinate=coordinate)


def predicate_sql(predicate, field):
    if len(predicate) == 2:
        slot, test = predicate
        if isinstance(test, str):
            return "(" + field(slot) + " " + test + ")"
        return "(" + field(slot) + (" IS NOT NULL)" if test else " IS NULL)")
    operator, left, right = predicate
    if operator == "NOT":
        return "(NOT " + predicate_sql(left, field) + ")"
    return "(" + predicate_sql(left, field) + " " + operator + " " + predicate_sql(right, field) + ")"


def sql(item, *, original=False, unqualified=False):
    index = item["createBtree"]
    schema, table, columns, _, only = index["table"]
    words = ("CREATE " + ("UNIQUE " if index["unique"] else "") + "INDEX " +
             ("CONCURRENTLY " if index["concurrently"] else "") + ("IF NOT EXISTS " if index["ifNotExists"] else ""))
    target = (("ONLY " if only else "") + qualified(None if unqualified else schema, table))
    coord = item["coordinate"]
    orders = [" " + direction for _, direction in index["keys"]]
    if original and coord and coord[0] == "key":
        orders = [FIRST[coord[2]][1], *([""] * (len(index["keys"]) - 1))]
    keys = [quoted(columns[slot]) + order for (slot, _), order in zip(index["keys"], orders)]
    covered = index["included"]
    include = (" INCLUDE (" + ", ".join(quoted(columns[slot]) for slot in covered) + ")") if covered else ""
    nulls = " NULLS NOT DISTINCT" if index["nullsNotDistinct"] else ""
    if original and coord and coord[0] == "key" and coord[-1][1] == "distinct":
        nulls = " NULLS DISTINCT"

    def field(slot):
        spelling = coord[-1] if original and coord and coord[0] == "partial" else "unqualified"
        if original:
            spelling = item.get("predicateSpelling", {}).get(str(slot), spelling)
        if spelling == "schema":
            return qualified(schema, table) + "." + quoted(columns[slot])
        if spelling == "table":
            return qualified(table, columns[slot])
        return quoted(columns[slot])

    predicate = index["predicate"]
    where = "" if predicate is None else " WHERE " + predicate_sql(predicate, field)
    return words + quoted(index["name"]) + " ON " + target + " USING btree (" + ", ".join(keys) + ")" + include + nulls + where


def declarations(item):
    index = item["createBtree"]
    schema, relation, columns, kinds, _ = index["table"]
    table = qualified(schema, relation)
    column_sql = ", ".join(quoted(name) + " " + SQL_TYPES[kind] for name, kind in zip(columns, kinds))
    target_schema = "CREATE SCHEMA fixture"
    if schema == "pg_temp":
        targets = [f"CREATE TABLE fixture.{quoted(relation)} (decoy integer)", f"CREATE TEMP TABLE {table} ({column_sql})"]
    else:
        targets = [f"CREATE TABLE {table} ({column_sql})"]
    name = index["name"]
    occupants = []
    if item["occupancy"] == "existing-table":
        occupants.append(f"CREATE TABLE {qualified(schema, name)} (decoy boolean)")
    if item["occupancy"] == "existing-index":
        occupants.append(f"CREATE INDEX {quoted(name)} ON {table} ({quoted(columns[1])} DESC NULLS LAST)")

    return [target_schema, *targets, *occupants]


def analyzed(item, *, unqualified=False):
    return {"dialect": "postgres", "release": item["release"], "sql": sql(item, original=True, unqualified=unqualified),
            "schemaPath": PATH, "declarations": declarations(item)}


def qualifies(predicate, row):
    if predicate is None:
        return True
    if len(predicate) == 2:
        slot, test = predicate
        if isinstance(test, str):
            prefix, value = test.rsplit(" ", 1)
            require(prefix in ("IS", "IS NOT") and value in TRUTH_VALUES, "postgres-btree-oracle-truth-test", test)
            return (row[slot] is TRUTH_VALUES[value]) == (prefix == "IS")
        return (row[slot] is not None) == test
    operator, left, right = predicate
    if operator == "NOT":
        return not qualifies(left, row)
    if operator == "AND":
        return all(qualifies(side, row) for side in (left, right))
    require(operator == "OR", "postgres-btree-oracle-operator", predicate)
    return any(qualifies(side, row) for side in (left, right))


def conflicts(keys, rows, nulls_not_distinct, predicate):
    seen = set()
    for row in rows:
        if not qualifies(predicate, row):
            continue
        values = tuple(row[column] for column, _ in keys)
        if not nulls_not_distinct and None in values:
            continue
        if values in seen:
            return True
        seen.add(values)
    return False


def index_record(item, valid=None, previous=False):
    index = item["createBtree"]
    schema, table, names, kinds, _ = index["table"]
    if previous:
        keys, includes, unique, nulls = OLD_INDEX, [], False, False
    else:
        keys, includes, unique, nulls = index["keys"], index["included"], index["unique"], index["nullsNotDistinct"]

    predicate = None if previous else index["predicate"]
    observed = None
    if predicate is not None:
        observed = ([qualifies(predicate, row) for row in item["rows"]] if item.get("observeMembership")
                    else [names[predicate[0]], predicate[1]])
    return {
        "predicate": observed,
        "schema": schema, "index": index["name"], "table": table, "accessMethod": "btree",
        "unique": unique, "nullsNotDistinct": nulls, "valid": valid, "ready": valid, "live": True,
        "keyCount": len(keys), "memberCount": len(keys) + len(includes),
        "positions": [slot + 1 for slot, _ in keys] + [slot + 1 for slot in includes],
        "classNames": [OPCLASSES[kinds[slot]] for slot, _ in keys],
        "collations": [0 for _ in keys], "orders": [BITS[direction] for _, direction in keys],
    }


def snapshot(item, index, marker=False):
    schema, table, columns, kinds, _ = item["createBtree"]["table"]
    occupied = item["occupancy"]
    rows = item["rows"]
    competitor = {"name": item["createBtree"]["name"], "rows": [[True]]} if occupied == "existing-table" else None
    return {
        "owner": [schema, table, [[name, kind] for name, kind in zip(columns, kinds)]],
        "rows": rows, "otherRows": [[97]] if schema == "pg_temp" else None, "marker": [0, 7] if marker else [0],
        "competitor": competitor, "index": index,
    }


def future_rows(item):
    return [item["rows"][position] for position in item["probeRows"]] + item.get("probeValues", [])


def expected(item):
    index = item["createBtree"]
    scope = index["table"][0]
    active = item["transaction"]
    occupied = item["occupancy"] != "vacant"
    concurrent = index["concurrently"]
    previous = index_record(item, True, previous=True) if item["occupancy"] == "existing-index" else None
    initial = snapshot(item, previous)
    created = previous
    notices = []
    state = "00000"
    if concurrent and active:
        state = "25001"
    elif occupied and not index["ifNotExists"]:
        state = "42P07"
    elif occupied:
        notices = [["NOTICE", "42P07", f'relation "{index["name"]}" already exists, skipping', None, None]]
    elif index["unique"] and conflicts(index["keys"], item["rows"], index["nullsNotDistinct"], index["predicate"]):
        state = "23505"
        if concurrent and scope != "pg_temp":
            created = index_record(item, False)
    else:
        created = index_record(item, True)

    success = state == "00000"
    probe = None
    if success or (item.get("probeFailedBuild") and not active and state == "23505"):
        probe = [
            "23505" if created and created["ready"] and created["unique"] and conflicts(index["keys"], [*item["rows"], row], index["nullsNotDistinct"], index["predicate"])
            else "00000"
            for row in future_rows(item)
        ]
    result = {
        "status": "btree-creation", "sqlState": state, "command": "CREATE INDEX" if success else None, "notices": notices,
        "before": initial, "after": snapshot(item, created, active) if success or not active else None,
        "settled": initial if active else snapshot(item, created),
        "transactionAfter": ("active" if success else "failed") if active else "idle",
        "probe": probe,
    }
    if item.get("observeMembership"):
        result["membership"] = [qualifies(index["predicate"], row) for row in item["rows"]]
    return result


def binding_coordinates(item, actual):
    coord = item["coordinate"]
    require(coord is not None and actual["createBtree"] == item["createBtree"], "postgres-btree-observed-coordinate")
    # Explicit SQL spelling is a coordinate even when two spellings give the same native bits.
    return tuple(tuple(value) if isinstance(value, list) else value for value in coord)
