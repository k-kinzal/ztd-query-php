"""Independent schema member and view dependency rules for PostgreSQL."""

import json

from postgres_create_schema_cases import EFFECTIVE, OWNER, QUOTED_OWNER, spelling
from postgres_create_sequence_cases import sequence, sql as sequence_sql, trial as sequence_trial
from postgres_reindex_cases import qualified, quoted
from postgres_schema_sequences_cases import sequence_shape
from postgres_schema_tables_cases import physical_table, table, table_sql
from run_server_postgres_local_values import values
from run_server_postgres_with_projection import scan

STATUS = "schema-view-composition"
LAYOUTS = ("values", "local", "external", "guarded-local")
OWNERS = ("effective", "named")
ORDERS = ("view-first", "table-first")
NS = "ir_view_namespace"
EXTERNAL = "ir_view_source"
FIELDS = (("INTEGER", 23, "int4", "slot"), ("BIGINT", 20, "int8", "wide-slot"),
          ("pg_catalog.bool", 16, "bool", "bool-slot"), ("pg_catalog.text", 25, "text", "text-slot"))


def trial(tag, owner, layout, order, active, *, coordinate=None, width=1, base=13, quoted_name=False,
          explicit=False, derived=False, barrier=None, invoker=None, replace=False, filtered=False, heading=False):
    named = (QUOTED_OWNER if quoted_name else OWNER) if owner == "named" else None
    namespace = named if derived else ('IR.View "棚"' if quoted_name else NS)
    relation = 'Invoice. "records"' if quoted_name else "accounts"
    columns = ["invoice id", "Total.税", "Enabled?", 'Item "code"'] if quoted_name else ["id", "amount", "enabled", "note"]
    return {"release": tag, "owner": owner, "layout": layout, "order": order, "active": active, "coordinate": coordinate,
            "width": width, "base": base, "quoted": quoted_name, "qualified": explicit, "derived": derived, "named": named,
            "namespace": namespace, "relation": relation, "columns": columns, "barrier": barrier, "invoker": invoker,
            "replace": replace, "filtered": filtered, "heading": heading}


def members(item):
    if item.get("constant"):
        return []
    target = (item["namespace"], item["relation"])
    fields = [[name, kind, True, None] for name, (kind, *_other) in zip(item["columns"], FIELDS)]
    result = [table(item["release"], target, fields, False)]
    if item["layout"] == "guarded-local":
        result.append(table(item["release"], target, [["incompatible_later", "BIGINT", True, None]], True))
    return result


def generators(item):
    if item.get("constant"):
        return []
    sequence = sequence_trial(item["release"], "bigint", 1, "default", "permanent")
    return [{**sequence, "target": [item["namespace"], "tickets"]}]


def provider(item, ordinal):
    layout = item["layout"]
    return "values" if layout == "values" or (item["width"] > 1 and ordinal % 3 == 2) else layout


def field_order(item, ordinal):
    first = ordinal % 4
    return ((first + 1) % 4, first) if item["heading"] else (first, (first + 1) % 4)


def source_schema(item, layout):
    return EXTERNAL if layout == "external" else item["namespace"]


def body(item, ordinal):
    if item.get("constant"):
        return scan(None, [[["integer", "1"], "?column?"]], source=False)
    layout = provider(item, ordinal)
    if layout == "values":
        return values([[["integer", str(item["base"] + ordinal)]], [["integer", str(item["base"] + ordinal)]], [["null"]]])
    projected = field_order(item, ordinal)
    consumed = list(dict.fromkeys(([2] if item["filtered"] else []) + list(projected)))
    ordered = [[FIELDS[part][3], consumed.index(part), *([0] if part != 0 else [])] for part in projected]
    plan = scan(None, [[term, item["columns"][part]] for term, part in zip(ordered, projected)], source=False)
    plan["source"] = {"schema": source_schema(item, layout), "table": item["relation"],
                      "columns": [item["columns"][part] for part in consumed],
                      "columnKinds": [FIELDS[part][2] for part in consumed], "only": False}
    plan["where"] = ["bool-slot", 0, 0] if item["filtered"] else None
    return plan


def child(item, ordinal):
    query = body(item, ordinal)
    layout = provider(item, ordinal)
    name = "feed" if item.get("constant") else ('Report. "棚"' if item["quoted"] else "report") + str(ordinal)
    if item.get("constant"):
        names = ["?column?"]
    elif layout == "values":
        names = ["value"]
    else:
        names = [f"field {ordinal}", "selected"] if item["heading"] else [item["columns"][part] for part in field_order(item, ordinal)]
    return {"release": item["release"], "target": [item["namespace"], name], "query": query, "columns": names,
            "replace": item["replace"], "securityBarrier": item["barrier"], "securityInvoker": item["invoker"], "checkOption": ""}


def child_sql(item, ordinal, canonical, change):
    view = child(item, ordinal)
    schema, name = view["target"]
    target = qualified(schema if canonical or item["qualified"] else None, name)
    labels = "" if change == "omit-heading" or not (canonical or item["heading"] or provider(item, ordinal) == "values") else (
        " (" + ", ".join(map(quoted, view["columns"])) + ")")
    attrs = []
    for key, flag in (("security_barrier", item["barrier"]), ("security_invoker", item["invoker"])):
        if flag is not None:
            attrs.append(key + "=" + ("true" if flag else "false"))
    opts = " WITH (" + ", ".join(attrs) + ")" if attrs else ""
    if item.get("constant"):
        query = 'SELECT CAST(1 AS pg_catalog.int4) AS "?column?"' if canonical else "SELECT 1"
    elif provider(item, ordinal) == "values":
        token = str(item["base"] + ordinal)
        scalar = f"CAST({token} AS pg_catalog.int4)" if canonical else token
        query = f"VALUES ({scalar}), ({scalar}), (NULL)"
    else:
        layout = provider(item, ordinal)
        source = EXTERNAL if change == "decoy" and layout != "external" else source_schema(item, layout)
        rendered = qualified(source if canonical or item["qualified"] or layout == "external" else None, item["relation"])
        names = [item["columns"][part] for part in field_order(item, ordinal)]
        alias = '"__ztd_source"'
        base = [qualified(alias.strip('"'), name) + " AS " + quoted(name) if canonical else
                qualified(item["relation"] if item["qualified"] else None, name) for name in names]
        query = "SELECT " + ", ".join(base) + " FROM " + rendered + (f" AS {alias}" if canonical else "")
        if item["filtered"]:
            query += " WHERE " + qualified("__ztd_source" if canonical else None, item["columns"][2])
    return "CREATE " + ("OR REPLACE " if view["replace"] else "") + "VIEW " + target + labels + opts + " AS " + query


def sql(item, *, canonical=False, change=None):
    if not canonical and "command" in item:
        return item["command"]
    base = spelling(item["namespace"] if canonical or not item["derived"] else None,
                    quoted(item["named"]) if item["named"] is not None else "CURRENT_USER" if canonical else None, False)
    sequence_parts = [sequence_sql(value, canonical=canonical) for value in generators(item)]
    table_parts = [table_sql(value, item["qualified"] or canonical, canonical) for value in members(item)]
    view_parts = [] if change == "omit-view-phase" else [child_sql(item, i, canonical, change) for i in range(item["width"])]
    children = sequence_parts + table_parts + view_parts
    if not canonical and item["order"] == "view-first":
        children = view_parts + table_parts + sequence_parts
    return " ".join([base, *children])


def analyzed(item):
    external = table(item["release"], (EXTERNAL, item["relation"]),
                     [[name, kind, True, None] for name, (kind, *_rest) in zip(item["columns"], FIELDS)], False)
    return {"dialect": "postgres", "release": item["release"], "sql": sql(item), "schemaPath": [EXTERNAL, "public"],
            "declarations": [f"CREATE ROLE {quoted(role)}" for role in (OWNER, QUOTED_OWNER)]
            + [f"CREATE SCHEMA {quoted(EXTERNAL)}", table_sql(external, True, False)]}


def direct(item):
    return {"dialect": "postgres", "release": item["release"], "createSchema": {
        "name": None if item["derived"] else item["namespace"], "ifNotExists": False,
        "owner": {"session": "CURRENT_USER"} if item["named"] is None else {"named": item["named"]},
        "tables": members(item), "sequences": [sequence_shape(value, False) for value in generators(item)],
        "views": [child(item, ordinal) for ordinal in range(item["width"])],
    }}


def rows(item, external=False):
    base = item["base"] + (700 if external else 0)
    return [[base, 4000000000 + base, True, "shared"], [base, 4000000000 + base, True, "shared"],
            [base - 1, None, True, None], [base + 3, 5000000000 + base, False, "filtered"],
            [None, 9000000000 + base, None, "unknown"]]


def view_state(item, ordinal):
    definition = child(item, ordinal)
    schema, name = definition["target"]
    layout = provider(item, ordinal)
    own = [schema, name, None, 0, "i"]
    if item.get("constant"):
        shape, data, deps = [[definition["columns"][0], 23]], [[1]], [own]
    elif layout == "values":
        shape, data, deps = [[definition["columns"][0], 23]], [[item["base"] + ordinal], [item["base"] + ordinal], [None]], [own]
    else:
        selected = field_order(item, ordinal)
        checked = set(selected) | ({2} if item["filtered"] else set())
        shape = [[label, FIELDS[slot][1]] for label, slot in zip(definition["columns"], selected)]
        data = [[row[index] for index in selected] for row in rows(item, layout == "external")
                if row[2] is True or not item["filtered"]]
        deps = [own, *([source_schema(item, layout), item["relation"], item["columns"][part], part + 1, "n"] for part in checked)]
    options = sorted(key + ("=true" if value else "=false") for key, value in
                     (("security_barrier", item["barrier"]), ("security_invoker", item["invoker"])) if value is not None)
    return {"heading": shape, "rows": sorted(data, key=json.dumps), "dependencies": sorted(deps, key=json.dumps), "options": options}


def expected(item):
    owner = item["named"] or EFFECTIVE
    baseline = {"owner": None, "table": None, "sequences": {}, "views": {}, "external": sorted(rows(item, True), key=json.dumps)}
    code = item.get("nativeRefusal", "00000")
    after = None
    notices = []
    if code == "00000":
        after = {**baseline, "owner": owner,
                 "table": physical_table(members(item)[0], owner) if members(item) else None,
                 "sequences": {value["target"][1]: {**sequence(value), "owner": owner} for value in generators(item)},
                 "views": {child(item, index)["target"][1]: {"owner": owner, **view_state(item, index)} for index in range(item["width"])}}
        if item["layout"] == "guarded-local":
            notices.append(["NOTICE", "42P07", f'relation "{item["relation"]}" already exists, skipping', None, None])
    return {"status": STATUS, "sqlState": code, "command": "CREATE SCHEMA" if after is not None else None,
            "transactionAfter": ("active" if after is not None else "failed") if item["active"] else "idle",
            "before": baseline, "during": after, "settled": baseline if item["active"] or after is None else after, "notices": notices}
