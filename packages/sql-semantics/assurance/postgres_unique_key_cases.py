"""Define ordered native uniqueness effects and independent write observations."""

from collections import defaultdict
from copy import deepcopy

from postgres_create_btree_cases import sql as btree_sql
from postgres_primary_key_cases import FIXED_TYPES, TIMINGS, VALUES, constraint as primary_sql, effective, native_name, wire
from postgres_reindex_cases import qualified, quoted
from postgres_schema_tables_cases import OIDS, default_sql, table
from server_create_table_cases import default_value

STATUS = "ordinary-unique-keys"
NS = "ir_uk_namespace"
SINGLE = "ir_uk_single"
TARGET = "ir_uk_target"
KINDS = ("inline", "ordered", "reversed")
NAMES = ("automatic", "explicit")
EQUALITY = ("distinct", "equal")
PAYLOADS = ("none", "twice-overlap")


def key(keys, included=(), name=None, timing="nondeferrable", nulls_equal=False):
    return {"keys": list(keys), "included": list(included), "name": name, "timing": TIMINGS[timing], "nullsNotDistinct": nulls_equal}


def recipe(release, kind, nullable, equality, timing, naming, include, *, coordinate=None):
    labels = {"inline": ["a"], "ordered": ["a", "b"], "reversed": ["b", "a"]}[kind]
    payload = [] if include == "none" else ["extra", labels[0], "extra"]
    fields = [["a", "INTEGER", nullable, None], ["b", "SMALLINT", nullable, None],
              ["extra", "pg_catalog.text", True, None], ["other", "pg_catalog.bool", True, None]]
    unique = key(labels, payload, 'Unique."釘"' if naming == "explicit" else None, timing, equality == "equal")
    member = {**table(release, (SINGLE, TARGET), fields, False), "uniqueKeys": [unique]}
    return {"release": release, "member": member, "prefix": [], "suffix": [], "compose": False, "inline": kind == "inline",
            "coordinate": coordinate}


def signature(value):
    return tuple(value["keys"]), tuple(value["included"]), value["timing"], value.get("nullsNotDistinct", False)


def resolve(member):
    result = effective(member)
    raw = result.pop("uniqueKeys", [])
    primary = result.pop("primaryKey", None)
    merged = defaultdict(list)
    for is_primary, key in ([(True, primary)] if primary is not None else []) + [(False, value) for value in raw]:
        merged[signature(key)].append((is_primary, key))
    uniques = []
    for group in merged.values():
        primary_role, first = group[0]
        selected = next((candidate["name"] for _, candidate in group if candidate["name"] is not None), None)
        resolved = {**first, "name": selected}
        if primary_role:
            result["primaryKey"] = resolved
        else:
            uniques.append(resolved)
    if uniques:
        result["uniqueKeys"] = uniques
    return result


def unique_sql(key, *, inline=False, canonical=False):
    name = f"CONSTRAINT {quoted(key['name'])} " if key["name"] is not None else ""
    treatment = " NULLS NOT DISTINCT" if key["nullsNotDistinct"] else (" NULLS DISTINCT" if canonical else "")
    columns = "" if inline else " (" + ", ".join(map(quoted, key["keys"])) + ")"
    included = key["included"]
    if included:
        columns += " INCLUDE (" + ", ".join(map(quoted, included)) + ")"
    timing = key["timing"] if canonical else {
        "NOT DEFERRABLE": "", "DEFERRABLE INITIALLY IMMEDIATE": "DEFERRABLE", "DEFERRABLE INITIALLY DEFERRED": "INITIALLY DEFERRED",
    }[key["timing"]]
    return name + "UNIQUE" + treatment + columns + (" " + timing if timing else "")


def member_sql(member, *, canonical=False, inline=False, change=None, local=False):
    body = resolve(member) if canonical else member
    primary = body.get("primaryKey")
    unique = deepcopy(body.get("uniqueKeys", []))
    if change == "null-policy":
        unique[0]["nullsNotDistinct"] = False
    elif change == "omit":
        unique = unique[1:]
    elif change == "include":
        unique[0]["included"] = []
    elif change == "order":
        unique.reverse()
    elif change == "merge-policy":
        unique = unique[:1]
    elif change == "last-name":
        unique[0]["name"] = member["uniqueKeys"][-1]["name"]

    columns = []
    for label, kind, nullable, default in body["columns"]:
        column = quoted(label) + " " + kind + ("" if nullable else " NOT NULL")
        if nullable and canonical:
            column += " NULL"
        if default is not None:
            column += " DEFAULT " + ("NULL" if default == ["null"] else default_sql(default, canonical))
        if inline:
            for index_constraint in unique:
                if index_constraint["keys"] == [label]:
                    column += " " + unique_sql(index_constraint, inline=True)
        columns.append(column)
    if primary is not None:
        columns.append(primary_sql(primary, canonical=canonical))
    if not inline:
        columns.extend(unique_sql(value, canonical=canonical) for value in unique)
    schema, name = member["target"]
    target = quoted(name) if local else qualified(schema, name)
    guard = "IF NOT EXISTS " if member["ifNotExists"] else ""
    return f"CREATE TABLE {guard}{target} (" + ", ".join(columns) + ")" + (" USING heap" if canonical else "")


def sql(item, *, canonical=False, change=None):
    if "command" in item:
        return item["command"]
    main = member_sql(item["member"], canonical=canonical, inline=item["inline"] and not canonical,
                      change=change, local=item["compose"] and not canonical)
    if not item["compose"]:
        return main
    header = "CREATE SCHEMA " + quoted(NS) + (" AUTHORIZATION CURRENT_USER" if canonical else "")
    prefix = [member_sql(child, canonical=canonical, local=not canonical) for child in item["prefix"]]
    suffix = [member_sql(child, canonical=canonical, local=not canonical) for child in item["suffix"]]
    indexes = [btree_sql({"createBtree": value, "coordinate": None}, unqualified=not canonical) for value in item.get("indexes", [])]
    return " ".join([header, *prefix, main, *suffix, *indexes])


def analyzed(item):
    return {"dialect": "postgres", "release": item["release"], "sql": sql(item),
            "declarations": ["CREATE TABLE public.ir_uk_binding_anchor (id integer)"]}


def direct(item):
    result = {"dialect": "postgres", "release": item["release"]}
    if item["compose"]:
        result["createSchema"] = {"name": NS, "owner": {"session": "CURRENT_USER"}, "ifNotExists": False,
                                  "tables": [*item["prefix"], item["member"], *item["suffix"]],
                                  "indexes": item.get("indexes", [])}
    else:
        result["create"] = {name: value for name, value in item["member"].items() if name != "release"}
    return result


def prefix_bytes(value, length):
    return value[:length].decode("utf-8", "ignore")


def index_labels(key):
    result = []
    for label in (*key["keys"], *key["included"]):
        alternatives = [label] + [prefix_bytes(label.encode(), 63 - len(str(n))) + str(n) for n in range(1, len(result) + 2)]
        result.append(next(item for item in alternatives if item not in result))
    return result


def allocated_unique(name, labels, occupied):
    left, right = name.encode(), "_".join(labels).encode()
    for index in range(len(occupied) + 1):
        suffix = f"key{index or ''}"
        total = 61 - len(suffix)
        second = min(len(right), total // 2)
        first = min(len(left), total - second)
        second = min(len(right), total - first)
        candidate = "_".join((prefix_bytes(left, first), prefix_bytes(right, second), suffix))
        if candidate not in occupied:
            return candidate
    raise ValueError("Native uniqueness allocation exceeded the prior occupant count.")


def catalog(item):
    relations = {}
    notices = []
    for member in (*item["prefix"], item["member"], *item["suffix"]):
        resolved = resolve(member)
        name = member["target"][1]
        if name in relations:
            if not member["ifNotExists"]:
                raise ValueError("A registered positive has an unguarded occupied relation.")
            notices.append(["NOTICE", "42P07", f'relation "{name}" already exists, skipping', None, None])
            continue
        fields = resolved["columns"]
        relations[name] = ["r", "heap", [[pos, label, OIDS[kind], nullable, default not in (None, ["null"])]
                                           for pos, (label, kind, nullable, default) in enumerate(fields, 1)]]
        slots = {field[0]: i for i, field in enumerate(fields, 1)}
        primary = resolved.get("primaryKey")
        unique = resolved.get("uniqueKeys", [])
        effects = [(False, value) for value in unique]
        if primary is not None:
            effects.insert(0, (True, primary))
        for is_primary, value in effects:
            labels = index_labels(value)
            allocated = value["name"]
            if allocated is None:
                allocated = native_name(name, relations) if is_primary else allocated_unique(name, labels, relations)
            if allocated in relations:
                raise ValueError("A registered positive contains a conflicting explicit index name.")
            keys = [slots[field] for field in value["keys"]]
            payload = [slots[field] for field in value["included"]]
            immediate = value["timing"] == TIMINGS["nondeferrable"]
            deferred = value["timing"] == TIMINGS["deferred"]
            relations[allocated] = ["i", "btree", name, allocated, keys, [*keys, *payload], len(keys), len(keys) + len(payload),
                                    is_primary, True, immediate, not immediate, deferred, value.get("nullsNotDistinct", False),
                                    labels, "p" if is_primary else "u"]
    for index in item.get("indexes", []):
        name = index["name"]
        if name not in relations or not index["ifNotExists"]:
            raise ValueError("An index guard witness must address a backing index already created by an earlier member.")
        notices.append(["NOTICE", "42P07", f'relation "{name}" already exists, skipping', None, None])
    return relations, notices


def probe_cases(member):
    fields = member["columns"]
    low = [VALUES[kind][0] for _, kind, _, _ in fields]
    high = [VALUES[kind][1] for _, kind, _, _ in fields]
    yield "normal-duplicate", low, low
    yield "distinct-tuple", low, high
    for index, (label, _, _, _) in enumerate(fields):
        left = [*low]
        left[index] = "NULL"
        yield f"null{index}/{label}", left, left


def decode_value(kind, value):
    if value == "NULL":
        return None
    if kind == "pg_catalog.bool":
        return "t" if value == "TRUE" else "f"
    if kind == "pg_catalog.text":
        return value[1:-1]
    return value


def witnesses(member):
    value = resolve(member)
    fields = value["columns"]
    heading = [[label, OIDS[kind]] for label, kind, _, _ in fields]
    keys = [*value.get("uniqueKeys", [])]
    primary = value.get("primaryKey")
    if primary is not None:
        keys.insert(0, primary)
    positions = {field[0]: i for i, field in enumerate(fields)}
    defaults = [wire(default_value(kind, default)) for _, kind, _, default in fields]
    timeout_null = any(not nullable and data is None for (_, _, nullable, _), data in zip(fields, defaults))
    output = {"default": ["23502", None] if timeout_null else ["00000", [heading, [defaults]]]}
    for label, first, second in probe_cases(member):
        first_value, second_value = ([decode_value(field[1], cell) for field, cell in zip(fields, row)] for row in (first, second))
        prohibited = {i for i, (_, _, nullable, _) in enumerate(fields) if not nullable}
        if any(first_value[i] is None for i in prohibited):
            output[label] = ["23502", None, None]
            continue
        if any(second_value[i] is None for i in prohibited):
            output[label] = [[heading, [first_value]], "23502", None]
            continue
        duplicate_modes = set()
        for key in keys:
            i = [positions[field] for field in key["keys"]]
            left, right = ([row[ind] for ind in i] for row in (first_value, second_value))
            if left == right and (None not in left or key.get("nullsNotDistinct", False)):
                duplicate_modes.add(key["timing"])
        if duplicate_modes - {TIMINGS["deferred"]}:
            output[label] = [[heading, [first_value]], "23505", None]
        else:
            output[label] = [[heading, [first_value]], [heading, [second_value]], "23505" if duplicate_modes else "00000"]
    return output


def expected(item):
    if item.get("nativeRefusal"):
        return {"status": STATUS, "sqlState": item["nativeRefusal"], "notices": [],
                "relations": None, "transaction": "failed", "afterRollback": {}, "witnesses": None}
    relations, notices = catalog(item)
    return {"status": STATUS, "sqlState": "00000", "notices": notices, "relations": relations,
            "transaction": "active", "afterRollback": {},
            "witnesses": None if item.get("skipProbe") else witnesses(item["member"])}
