"""Independent native reference and row effects for MySQL persistent truncation."""

from itertools import product
from random import Random

SCHEMA = "ir_fixture"
OTHER = "ir_fk_decoy"
TABLES = (SCHEMA, OTHER)
STATUS = "mysql-truncate-foreign-keys"
OLDER = ("mysql-5.6.51", "mysql-5.7.44", "mysql-8.0.44", "mysql-8.1.0", "mysql-8.2.0", "mysql-8.3.0", "mysql-8.4.7")
FORMS = ("none", "same", "cross-explicit", "cross-local", "self", "inline", "ignored-engine", "shadow-child", "like")
RESTRICTED = ["db-error", "42000", 1701]


def profile_case(tag, form, schema, scope, occupied=True, offset=0):
    parent = [schema, "ir_truncate_parent", "temporary-table" if scope == "temporary-shadow" else "table"]
    return {"dialect": "mysql", "release": tag, "form": form, "schema": schema, "scope": scope,
            "mysqlTruncate": parent, "occupied": occupied, "offset": offset}


def reference(form, tag):
    if form in ("same", "shadow-child") or form == "inline" and tag not in OLDER:
        return [SCHEMA, "ir_truncate_child", SCHEMA, "ir_truncate_parent"]
    if form in ("cross-explicit", "like"):
        return [OTHER, "ir_truncate_child", SCHEMA, "ir_truncate_parent"]
    if form == "cross-local":
        return [OTHER, "ir_truncate_child", OTHER, "ir_truncate_parent"]
    if form == "self":
        return [SCHEMA, "ir_truncate_parent", SCHEMA, "ir_truncate_parent"]
    return None


def refs(model):
    edge = reference(model["form"], model["release"])
    return [] if edge is None or model["scope"] == "temporary-shadow" or edge[2] != model["schema"] else [[edge[0], edge[1], "table"]]


def coordinates(finite):
    return set(product(finite["forms"], finite["schemas"], finite["lifetimes"]))


def shape(tag, coord):
    form, schema, scope = coord
    return profile_case(tag, form, schema, scope)


def seed_cases(tag, seed, count):
    rng = Random(seed)
    for index in range(count):
        # Cycle every edge kind and mix real and empty child tables and selected namespaces.
        form = FORMS[index % len(FORMS)]
        scope = "temporary-shadow" if index % 2 == 0 else "persistent"
        schema = TABLES[(index // 3 + seed) % len(TABLES)]
        yield profile_case(tag, form, schema, scope, bool(rng.randrange(2)), rng.randrange(5, 71))


def qualified(schema, name):
    return f"\x60{schema}\x60.\x60{name}\x60"


def statement(model, original=False):
    target = qualified(model["schema"], "ir_truncate_parent")
    if original and model["schema"] == SCHEMA:
        return "TRUNCATE \x60ir_truncate_parent\x60"
    return "TRUNCATE TABLE " + target


def declarations(model):
    form = model["form"]
    child_db = OTHER if form in ("cross-local", "cross-explicit", "like") else SCHEMA
    parent = "ir_truncate_parent"
    target = qualified(SCHEMA, parent)
    decoy = qualified(OTHER, parent)
    child = qualified(child_db, "ir_truncate_child")
    layout = "id INT AUTO_INCREMENT PRIMARY KEY, pid INT"
    self_reference = f", FOREIGN KEY (pid) REFERENCES {target}(id)" if form == "self" else ""
    entries = [f"CREATE TABLE {target} ({layout}{self_reference}) ENGINE=InnoDB",
               f"CREATE TABLE {decoy} ({layout}) ENGINE=InnoDB"]
    if form not in ("none", "self"):
        unqualified = "\x60ir_truncate_parent\x60"
        child_layout = layout
        if form == "inline":
            child_layout += f" REFERENCES {unqualified}(id)"
        else:
            referenced = unqualified if form in ("cross-local", "same", "shadow-child", "ignored-engine") else target
            child_layout += f", FOREIGN KEY (pid) REFERENCES {referenced}(id)"
        engine = "MyISAM" if form == "ignored-engine" else "InnoDB"
        entries.append(f"CREATE TABLE {child} ({child_layout}) ENGINE={engine}")
    if form == "like":
        entries.append(f"CREATE TABLE {qualified(SCHEMA, 'ir_truncate_copy')} LIKE {child}")
    if form == "shadow-child":
        entries.append(f"CREATE TEMPORARY TABLE {child} ({layout}) ENGINE=InnoDB")
    if model["scope"] == "temporary-shadow":
        entries.append(f"CREATE TEMPORARY TABLE {qualified(model['schema'], parent)} ({layout}) ENGINE=InnoDB")
    return entries


def wire(model):
    return {**model, "referrers": refs(model)}


def analyzed(model):
    return {"dialect": "mysql", "release": model["release"], "currentDatabase": SCHEMA, "declarations": declarations(model),
            "sql": statement(model, True)}


def expected(model):
    form, schema, offset = (model[key] for key in ("form", "schema", "offset"))
    edge = reference(form, model["release"])
    temporary = model["scope"] == "temporary-shadow"
    blocker = not temporary and bool(refs(model)) and refs(model)[0] != model["mysqlTruncate"]
    first_rows = [[101 + offset, None], [102 + offset, 101 + offset if form == "self" else None]]
    other_rows = [[201 + offset, None], [202 + offset, None]]
    child_parent = 201 + offset if form == "cross-local" else 101 + offset
    child = None if form in ("none","self") else ([[301 + offset, child_parent]] if model["occupied"] else [])
    before = {"first": first_rows, "other": other_rows, "child": child, "copy": [] if form == "like" else None,
              "shadow": [[401 + offset, None], [402 + offset, None]] if temporary else None,
              "referrers": [] if edge is None else [edge]}
    outcome = "shadow" if temporary else "first" if schema == SCHEMA else "other"
    after = before.copy()
    if not blocker:
        after[outcome] = []
    return {"status": STATUS, "before": before, "after": after, "effect": RESTRICTED if blocker else ["executed"],
            "settings": [1, 1, 1, 1],
            "affected": None if blocker else 0, "active": False, "marker": 7,
            "next": before[outcome][-1][0]+1 if blocker else 1}
