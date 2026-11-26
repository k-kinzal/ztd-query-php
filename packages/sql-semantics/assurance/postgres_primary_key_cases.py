"""Describe checked table keys independently of PHP lowering and rendering."""

from copy import deepcopy

from postgres_reindex_cases import qualified, quoted
from postgres_schema_tables_cases import OIDS, default_sql, table
from server_create_table_cases import default_value

STATUS = "ordinary-primary-key"
NS = "ir_pk_namespace"
SINGLE = "ir_pk_single"
TARGET = "ir_pk_target"
FORMS = ("inline-single", "table-forward", "table-reverse")
TIMINGS = {
    "nondeferrable": "NOT DEFERRABLE",
    "immediate": "DEFERRABLE INITIALLY IMMEDIATE",
    "deferred": "DEFERRABLE INITIALLY DEFERRED",
}
NAMES = ("automatic", "quoted-explicit")
PAYLOADS = ("none", "separate", "repeat-and-overlap")
NULLS = ("implicit", "explicit-null")
FIXED_TYPES = ("INTEGER", "SMALLINT", "pg_catalog.text", "pg_catalog.bool", "BIGINT")
VALUES = {
    "INTEGER": ("101", "102"), "SMALLINT": ("-3", "8"), "BIGINT": ("4294967296", "4294967297"),
    "pg_catalog.text": ("'alpha'", "'zulu'"), "pg_catalog.bool": ("FALSE", "TRUE"),
}


def recipe(tag, form, timing, name, payload, nullable, *, coordinate=None):
    fields = [["left", "INTEGER", True, None], ["right", "SMALLINT", True, None],
              ["unkeyed", "pg_catalog.text", True, None]]
    keys = {"inline-single": ["left"], "table-forward": ["left", "right"],
            "table-reverse": ["right", "left"]}[form]
    included = {"none": [], "separate": ["unkeyed"],
                "repeat-and-overlap": ["unkeyed", keys[0], "unkeyed"]}[payload]
    key = {"keys": keys, "included": included, "name": 'Key."棚"' if name == "quoted-explicit" else None,
           "timing": TIMINGS[timing]}
    member = {**table(tag, (SINGLE, TARGET), fields, False), "primaryKey": key}
    return {"release": tag, "form": form, "nullable": nullable, "coordinate": coordinate, "member": member,
            "prefix": [], "suffix": [], "compose": False}


def effective(member):
    value = deepcopy(member)
    keys = value.get("primaryKey", {}).get("keys", ())
    for column in value["columns"]:
        if column[0] in keys:
            column[2] = False
    return value


def constraint(key, *, inline=False, change=None, canonical=False):
    keys = key["keys"]
    payload = key["included"]
    timing = key["timing"]
    if change == "reverse":
        keys = list(reversed(keys))
    elif change == "drop-payload":
        payload = []
    elif change == "erase-timing":
        timing = "NOT DEFERRABLE"
    elif change == "wrong-column":
        keys = ["unkeyed", *keys[1:]]
    if not canonical:
        timing = {"NOT DEFERRABLE": "", "DEFERRABLE INITIALLY IMMEDIATE": "DEFERRABLE",
                  "DEFERRABLE INITIALLY DEFERRED": "INITIALLY DEFERRED"}[timing]
    head = f"CONSTRAINT {quoted(key['name'])} " if key["name"] is not None else ""
    body = "" if inline else " (" + ", ".join(map(quoted, keys)) + ")"
    if payload:
        body += " INCLUDE (" + ", ".join(map(quoted, payload)) + ")"
    return head + "PRIMARY KEY" + body + (" " + timing if timing else "")


def table_sql(member, form=None, nullable="implicit", *, canonical=False, change=None, local=False):
    body = effective(member) if canonical else member
    key = member.get("primaryKey")
    inline = form == "inline-single" and not canonical
    fields = []
    for name, kind, optional, default in body["columns"]:
        column = quoted(name) + " " + kind
        column += " NOT NULL" if not optional else (" NULL" if canonical or nullable == "explicit-null" else "")
        if default is not None:
            column += " DEFAULT " + ("NULL" if default == ["null"] else default_sql(default, canonical))
        if inline and key is not None and name == key["keys"][0]:
            column += " " + constraint(key, inline=True)
        fields.append(column)
    if key is not None and not inline and change != "drop-key":
        fields.append(constraint(key, change=change, canonical=canonical))
    schema, target = member["target"]
    name = quoted(target) if local else qualified(schema, target)
    guard = "IF NOT EXISTS " if member["ifNotExists"] else ""
    return f"CREATE TABLE {guard}{name} (" + ", ".join(fields) + ")" + (" USING heap" if canonical else "")


def sql(item, *, canonical=False, change=None):
    if "command" in item:
        return item["command"]
    member = item["member"]
    command = table_sql(member, item["form"], item["nullable"], canonical=canonical,
                        change=change, local=item["compose"] and not canonical)
    if not item["compose"]:
        return command
    header = "CREATE SCHEMA " + quoted(NS) + (" AUTHORIZATION CURRENT_USER" if canonical else "")
    parts = [table_sql(value, canonical=canonical, local=not canonical) for value in item["prefix"]]
    suffix = [table_sql(value, canonical=canonical, local=not canonical) for value in item["suffix"]]
    return " ".join([header, *parts, command, *suffix])


def analyzed(item):
    return {"dialect": "postgres", "release": item["release"], "sql": sql(item),
            "declarations": ["CREATE TABLE public.ir_pk_binding_anchor (id INTEGER)"]}


def direct(item):
    request = {"dialect": "postgres", "release": item["release"]}
    if not item["compose"]:
        request["create"] = {key: value for key, value in item["member"].items() if key != "release"}
    else:
        request["createSchema"] = {"name": NS, "owner": {"session": "CURRENT_USER"}, "ifNotExists": False,
                                   "tables": [*item["prefix"], item["member"], *item["suffix"]]}
    return request


def native_name(table_name, occupied):
    for index in range(len(occupied) + 1):
        suffix = "_pkey" + (str(index) if index else "")
        prefix = table_name.encode()[:63 - len(suffix)].decode(errors="ignore")
        if prefix + suffix not in occupied:
            return prefix + suffix
    raise ValueError("Native name allocation exceeds the number of occupied indexes.")


def catalog(item):
    visible = {}
    notices = []
    for member in (*item["prefix"], item["member"], *item["suffix"]):
        target = member["target"][1]
        if target in visible:
            if not member["ifNotExists"]:
                return None, []
            notices.append(["NOTICE", "42P07", f'relation "{target}" already exists, skipping', None, None])
            continue
        value = effective(member)
        fields = value["columns"]
        visible[target] = ["r", "heap",
                           [[n, name, OIDS[kind], nullable, default not in (None, ["null"])]
                            for n, (name, kind, nullable, default) in enumerate(fields, 1)]]
        if (key := value.get("primaryKey")) is not None:
            name = key["name"] or native_name(target, visible)
            if name in visible:
                return None, []
            slots = {field[0]: i for i, field in enumerate(fields, 1)}
            keys = [slots[field] for field in key["keys"]]
            included = [slots[field] for field in key["included"]]
            immediate = key["timing"] == "NOT DEFERRABLE"
            deferred = key["timing"] == "DEFERRABLE INITIALLY DEFERRED"
            visible[name] = ["i", "btree", target, name, keys, [*keys, *included],
                             len(keys), len(keys) + len(included), True, True, immediate, not immediate, deferred]
    return visible, notices


def wire(value):
    if value is None:
        return None
    return ("t" if value else "f") if isinstance(value, bool) else str(value)


def witnesses(member):
    key = member["primaryKey"]
    fields = effective(member)["columns"]
    heading = [[name, OIDS[kind]] for name, kind, _, _ in fields]
    key_set = set(key["keys"])
    defaults = [default_value(kind, default) for _, kind, _, default in fields]
    default_failure = any(not nullable and value is None for (_, _, nullable, _), value in zip(fields, defaults))
    deferred = key["timing"] == "DEFERRABLE INITIALLY DEFERRED"
    nondeferrable = key["timing"] == "NOT DEFERRABLE"
    return {
        "default": ["23502", None] if default_failure else ["00000", [heading, [list(map(wire, defaults))]]],
        "duplicate": ["00000", "23505"] if deferred else ["23505", None],
        "nullableKey": "23502",
        "distinct": "00000",
        "swap": "23505" if nondeferrable else "00000",
        "requiredCoordinates": sorted(key_set),
    }


def expected(item):
    seen, notices = catalog(item)
    failure = item.get("nativeRefusal")
    if failure:
        return {"status": STATUS, "sqlState": failure, "notices": [], "relations": None,
                "transaction": "failed", "afterRollback": {}, "witnesses": None}
    if seen is None:
        raise ValueError("An invalid key recipe cannot be a positive.")
    state = witnesses(item["member"]) if item["member"].get("primaryKey") and not item.get("skipProbe") else None
    return {"status": STATUS, "sqlState": "00000", "notices": notices, "relations": seen,
            "transaction": "active", "afterRollback": {}, "witnesses": state}
