"""Author ordered schema removals and independently predict their dependent effects."""

from postgres_reindex_cases import qualified, quoted

ROLES = ("selected", "other", "missing")
PLANS = (("selected",), ("other",), ("selected", "other"), ("other", "selected"),
         ("selected", "selected"), ("missing",), ("missing", "selected", "missing"))
CATALOG = "ir_fixture"
BASE_NAMES = {"selected": "ir_schema_drop_front", "other": "ir_schema_drop_later",
              "missing": "ir_schema_drop_absent", "external": "ir_schema_drop_reference"}
STATUS = "schema-removal"


def case(tag, selectors, guard, cascade, active, *, quoted_names=False, family="external", base=11, coordinate=None):
    names = {role: (f'IR {role}."棚"' if quoted_names else name) for role, name in BASE_NAMES.items()}
    return {"dialect": "postgres", "release": tag, "roles": list(selectors), "names": names,
            "ifExists": guard, "cascade": cascade, "active": active, "family": family, "base": base,
            "coordinate": coordinate, "quotedNames": quoted_names}


def sql(item, roles=None, guard=None, cascade=None, explicit=True):
    roles = item["roles"] if roles is None else roles
    guard = item["ifExists"] if guard is None else guard
    cascade = item["cascade"] if cascade is None else cascade
    names = ", ".join(quoted(item["names"][role]) for role in roles)
    policy = " CASCADE" if cascade else " RESTRICT" if explicit else ""
    return "DROP SCHEMA " + ("IF EXISTS " if guard else "") + names + policy


def declarations(item, explicit=True):
    names = item["names"]
    selected = qualified(names["selected"], "items")
    decoy = qualified(names["external"], "control")
    parts = [f"CREATE SCHEMA {quoted(names[role])}" for role in ("selected", "other", "external")]
    if item["family"] != "empty":
        parts.append(f"CREATE TABLE {selected} (id integer PRIMARY KEY, payload integer)")
    constraint = f", CONSTRAINT dependent_ref FOREIGN KEY (ref) REFERENCES {selected}(id)" if item["family"] == "external" else ""
    parts.append(f"CREATE TABLE {decoy} (id integer, ref integer{constraint})")
    if item["family"] == "external":
        parts.append(f"CREATE VIEW {qualified(names['external'], 'rollup')} AS SELECT id FROM {selected}")
    if not explicit:
        parts.remove(f"CREATE SCHEMA {quoted(names['selected'])}")
    return parts


def analyzed(item, explicit=True):
    return {"dialect": "postgres", "release": item["release"], "schemaPath": [item["names"]["other"], "public"],
            "declarations": declarations(item, explicit), "sql": sql(item, explicit=False)}


def direct(item):
    targets = [[item["names"][role], role != "missing"] for role in item["roles"]]
    return {"dialect": "postgres", "release": item["release"],
            "dropSchema": {"targets": targets, "ifExists": item["ifExists"], "cascade": item["cascade"]}}


def data(item):
    base = item["base"]
    return [[base, 91], [base + 1, None]], [[base + 100, base], [base + 101, None]]


def initial(item):
    selected, decoy = data(item)
    return {
        "namespaces": ["external", "other", "selected"],
        "selected": None if item["family"] == "empty" else selected,
        "decoy": decoy,
        "view": [[row[0]] for row in selected] if item["family"] == "external" else None,
        "foreignKey": item["family"] == "external",
    }


def outcome(item):
    state, notices = "00000", []
    cascades = False
    for role in item["roles"]:
        if role != "missing":
            continue
        if not item["ifExists"]:
            state = "3F000"
            break
        notices.append(["NOTICE", "00000", f'schema "{item["names"][role]}" does not exist, skipping'])
    selected = "selected" in item["roles"]
    nonempty = selected and item["family"] != "empty"
    if state == "00000" and nonempty:
        if item["cascade"]:
            cascades = True
        else:
            state = "2BP01"
    first = initial(item)
    after = None
    if state == "00000":
        after = {**first, "namespaces": [role for role in first["namespaces"] if role not in item["roles"]]}
        if selected:
            after.update(selected=None, view=None, foreignKey=False)
    transaction = ("active" if state == "00000" else "failed") if item["active"] else "idle"
    return {"status": STATUS, "sqlState": state, "command": "DROP SCHEMA" if state == "00000" else None,
            "transactionAfter": transaction, "before": first, "during": after,
            "settled": first if item["active"] or after is None else after, "missingNotices": notices,
            "cascadeCount": (3 if item["family"] == "external" else 1) if cascades else 0}
