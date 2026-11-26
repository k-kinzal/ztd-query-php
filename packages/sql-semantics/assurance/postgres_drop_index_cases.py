"""Relation namespace and catalog transition rules for the fixed index removal lane."""

from itertools import product

from run import require

AXES = ("layouts", "qualifiers", "guards", "behavior", "concurrent", "transaction")
PATH = ["front", "later", "public", "pg_catalog"]
ROWS = {
    "front": [[1, 17], [2, 19]],
    "later": [[1, 71], [2, 91]],
    "pg_temp": [[1, 41], [2, 49]],
}
DIAGNOSTICS = "SqlSemantics\\Platform\\PostgreSql\\Model\\Diagnostic\\"
INVALID = DIAGNOSTICS + "InvalidConstruction"
SEMANTIC = DIAGNOSTICS + "SemanticException"


def quoted(name):
    return '"' + name.replace('"', '""') + '"'


def qualified(schema, name):
    return (quoted(schema) + "." if schema else "") + quoted(name)


def sql(spellings, guarded, cascade, concurrent):
    return ("DROP INDEX " + ("CONCURRENTLY " if concurrent else "") +
            ("IF EXISTS " if guarded else "") +
            ", ".join(qualified(*name) for name in spellings) + (" CASCADE" if cascade else " RESTRICT"))


def fixture(layout, variants=(), temporary=False):
    schemas = ["front", "later"] + (["pg_temp"] if temporary else [])
    tables = [[schema, "parent", ROWS[schema]] for schema in schemas]
    # Schema, name, parent, unique, directly owned, supporting a foreign key.
    indexes = [[schema, "keeper", "parent", False, False, False] for schema in schemas]
    for schema in schemas:
        if schema != "pg_temp" and (layout in (schema, "both") or (schema == "later" and layout == "table-shadows")):
            indexes.append([schema, "idx", "parent", False, False, False])
    if layout == "owned-constraint":
        indexes.append(["later", "idx", "parent", True, True, False])
    if layout == "foreign-dependent":
        indexes.append(["front", "idx", "parent", True, False, True])
    if layout == "table-shadows":
        tables.append(["front", "idx", [[111]]])
    if temporary:
        indexes.append(["pg_temp", "idx", "parent", False, False, False])
    indexes.extend([schema, name, "parent", False, False, False] for schema, name in variants)

    return {"tables": tables, "indexes": indexes}


def declaration_commands(state):
    commands = ["CREATE SCHEMA front", "CREATE SCHEMA later"]
    for schema, table, _ in state["tables"]:
        name = qualified(schema, table)
        if table == "idx":
            commands.append(f"CREATE TABLE {name} (work integer)")
            continue
        owned = next((idx for src, idx, owner, _, protected, _ in state["indexes"]
                      if (src, owner) == (schema, table) and protected), None)
        definition = "id integer, slot integer"
        if owned is not None:
            definition += f", CONSTRAINT {quoted(owned)} PRIMARY KEY (slot)"
        persistence = "TEMP " if schema == "pg_temp" else ""
        commands.append(f"CREATE {persistence}TABLE {name} ({definition})")
    for schema, name, parent, unique, protected, _ in state["indexes"]:
        if protected:
            continue
        prefix = "UNIQUE " if unique else ""
        slot = "id" if name == "keeper" else "slot"
        commands.append(f"CREATE {prefix}INDEX {quoted(name)} ON {qualified(schema, parent)} ({slot})")
    if any(index[5] for index in state["indexes"]):
        commands.append("CREATE TABLE front.child (slot integer CONSTRAINT depend REFERENCES front.parent(slot))")

    return commands


def bind(state, path, selectors):
    scopes = {"public", "pg_catalog", "information_schema", "pg_toast", "front", "later"}
    scopes.update(schema for schema, _, _ in state["tables"])
    installed = {(i[0], i[1]): i for i in state["indexes"]}
    table_names = {(s, n) for s, n, _ in state["tables"]}
    roles = []
    for schema, name in selectors:
        for candidate in ([schema] if schema else ["pg_temp", *path]):
            key = candidate, name
            if key in table_names or key == ("pg_catalog", "pg_class"):
                roles.append(["wrong-kind", candidate, name, None])
                break
            if key in installed:
                entry = installed[key]
                roles.append(["protected" if entry[4] else "bound", candidate, name, entry[2]])
                break
        else:
            roles.append(["missing", schema, name, schema is not None and schema not in scopes])
    return roles


def boundary(message, exception=INVALID):
    return {"message": message, "exception": exception}


def rejection(roles, guarded, cascade, concurrent):
    if concurrent and len(roles) != 1:
        return boundary("does not support dropping multiple objects", SEMANTIC)
    if concurrent and cascade:
        return boundary("does not support CASCADE", SEMANTIC)
    for kind, _, _, _ in roles:
        if kind == "wrong-kind":
            return boundary("selected relation is not an ordinary index")
        if kind == "protected":
            return boundary("protected in the ordinary index profile")
        if kind == "missing" and not guarded:
            return boundary("requires a declared index")
    return None


def case(tag, state, selectors, guarded=False, cascade=False, concurrent=False, transaction=False, path=PATH):
    roles = bind(state, path, selectors)
    return {
        "dialect": "postgres", "release": tag,
        "dropIndex": {"targets": roles, "ifExists": guarded, "cascade": cascade,
                      "mode": "concurrently" if concurrent else "ordinary"},
        "fixture": state, "selectors": [list(name) for name in selectors],
        "schemaPath": list(path), "transaction": transaction,
    }


def query(case):
    drop = case["dropIndex"]
    return sql(case["selectors"], drop["ifExists"], drop["cascade"], drop["mode"] == "concurrently")


def analyzed(item):
    return {
        "dialect": item["dialect"], "release": item["release"], "sql": query(item),
        "schemaPath": item["schemaPath"], "declarations": declaration_commands(item["fixture"]),
    }


def exclusion(item):
    drop = item["dropIndex"]
    return rejection(drop["targets"], drop["ifExists"], drop["cascade"], drop["mode"] == "concurrently")


def finite(tag, layout, schema, guarded, behavior, concurrent, transaction):
    return case(tag, fixture(layout), [(schema, "idx")], guarded, behavior == "CASCADE", concurrent, transaction)


def coordinates(spec):
    return product(*(spec["finite"][axis] for axis in AXES))


def snapshots(state, removed=frozenset(), drop_constraint=False, marker=False):
    indexes = sorted([schema, name, parent, unique] for schema, name, parent, unique, _, _ in state["indexes"]
                     if (schema, name) not in removed)
    constraints = sorted([schema, name, "p"] for schema, name, _, _, owned, _ in state["indexes"] if owned)
    tables = [[s, n, rows] for s, n, rows in state["tables"]]
    if any(index[5] for index in state["indexes"]):
        tables.append(["front", "child", [[17]]])
        if not drop_constraint:
            constraints.append(["front", "depend", "f"])
    tables.append(["public", "ir_di_marker", [[0], [5]] if marker else [[0]]])
    return {"indexes": indexes, "constraints": sorted(constraints), "tables": sorted(tables)}


def result(item):
    drop = item["dropIndex"]
    concurrent = drop["mode"] == "concurrently"
    if concurrent and item["transaction"]:
        return "25001", [], set(), False
    if concurrent and (drop["cascade"] or len(drop["targets"]) != 1):
        return "0A000", [], set(), False
    notices, removed = [], set()
    for kind, schema, name, missing_schema in drop["targets"]:
        if kind == "wrong-kind":
            return "42809", notices, set(), False
        if kind == "missing":
            if not drop["ifExists"]:
                return ("3F000" if missing_schema else "42704"), notices, set(), False
            obj = f'schema "{schema}"' if missing_schema else f'index "{name}"'
            notices.append(["NOTICE", "00000", obj + " does not exist, skipping", None, None])
        else:
            removed.add((schema, name))
    blocked = [(schema, name) for schema, name, _, _, owned, _ in item["fixture"]["indexes"] if owned]
    dependent = [(schema, name) for schema, name, _, _, _, foreign in item["fixture"]["indexes"] if foreign]
    if removed.intersection(blocked) or (removed.intersection(dependent) and not drop["cascade"]):
        return "2BP01", notices, set(), False
    cascaded = bool(removed.intersection(dependent))
    if cascaded:
        notices.append(["NOTICE", "00000", "drop cascades to constraint depend on table child", None, None])
    return "00000", notices, removed, cascaded


def expected(item):
    state = item["fixture"]
    in_transaction = item["transaction"]
    status, notices, removed, cascaded = result(item)
    before = snapshots(state)
    ok = status == "00000"
    has_foreign = any(index[5] for index in state["indexes"])
    return {
        "status": "index-removal", "sqlState": status, "command": "DROP INDEX" if ok else None,
        "notices": notices, "before": before,
        "transactionAfter": ("active" if ok else "failed") if in_transaction else "idle",
        "effect": snapshots(state, removed, cascaded, in_transaction) if ok else None,
        "writeProbe": ("00000" if cascaded else "23503") if ok and has_foreign else None,
        "settled": before if in_transaction else snapshots(state, removed, cascaded),
    }


def coordinate_key(spec, item):
    layout = next((name for name in spec["finite"]["layouts"] if fixture(name) == item["fixture"]), None)
    require(layout is not None and len(item["selectors"]) == 1, "postgres-drop-index-finite-structure", item)
    drop = item["dropIndex"]
    schema, name = item["selectors"][0]
    require(name == "idx", "postgres-drop-index-finite-index", name)
    # None sorts with no string under Python 3; the fixed receipt uses an empty selector tag.
    return layout, schema or "", drop["ifExists"], "CASCADE" if drop["cascade"] else "RESTRICT", drop["mode"] == "concurrently", item["transaction"]
