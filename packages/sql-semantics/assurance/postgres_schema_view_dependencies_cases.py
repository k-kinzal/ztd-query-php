"""Independent PostgreSQL view dependency, heading and bag semantics."""

import json

from postgres_create_schema_cases import EFFECTIVE, OWNER, QUOTED_OWNER, spelling
from postgres_reindex_cases import qualified, quoted
from postgres_schema_tables_cases import physical_table, table, table_sql
from postgres_schema_views_cases import FIELDS as TABLE_FIELDS
from run_server_postgres_local_values import values
from run_server_postgres_with_projection import scan

STATUS = "schema-view-dependencies"
NS = "ir_view_chain"
EXTERNAL = "ir_view_chain_decoy"
PROVIDERS = ("constant-values", "base-table", "external-view")
OWNERS = ("effective", "named")
NATIVE = (*TABLE_FIELDS, ("pg_catalog.text", 25, "text", "text-slot"))
NAMES = ("n", "wide", "enabled", "note", "mystery")
ORDER = (0, 1, 2, 3, 4)


def trial(tag, owner, provider, width, active, *, quoted_name=False, roles=None, base=7, coordinate=None, explicit=False):
    named = (QUOTED_OWNER if quoted_name else OWNER) if owner == "named" else None
    ns = 'View chain."棚"' if quoted_name else NS
    plans = roles if roles is not None else [
        {"parent": part - 1, "order": list(ORDER), "rename": False, "filter": True}
        for part in range(1, width)
    ]
    return {"release": tag, "owner": owner, "named": named, "namespace": ns, "provider": provider, "width": width,
            "active": active, "base": base, "quoted": quoted_name, "plans": plans, "coordinate": coordinate,
            "qualified": explicit, "standalone": False}


def view_name(item, step):
    if step < 0:
        return "provider"
    return ("Provider." if item["quoted"] else "provider") if step == 0 else (f'Level. "{step}"' if item["quoted"] else f"level{step}")


def fields(item, step):
    if step <= 0:
        return [[key, NATIVE[ordinal][1]] for ordinal, key in enumerate(NAMES)]
    plan = item["plans"][step - 1]
    inherited = fields(item, plan["parent"])
    return [[f'heading {step}/{position}' if plan["rename"] else inherited[slot][0], inherited[slot][1]]
            for position, slot in enumerate(plan["order"])]


def coordinate(item, step):
    return [EXTERNAL if step < 0 else item["namespace"], view_name(item, step)]


def base_table(item):
    target = [item["namespace"], "feed_data"]
    return table(item["release"], target, [[name, kind, True, None] for name, (kind, *_rest) in zip(NAMES, TABLE_FIELDS)], False)


def present_table(item):
    return not item["standalone"] and item["provider"] == "base-table"


def data(item, external=False):
    value = item["base"] + (600 if external else 0)
    marker = "external" if external else "actual"
    return [[value, 4_000_000_000 + value, True, marker, None],
            [value, 4_000_000_000 + value, True, marker, None],
            [None, 4_000_011_000 + value, True, None, None],
            [value + 1, None, False, "hidden", None], [None, None, None, None, None]]


def row_terms(record):
    kinds = ("integer", "wide-integer", "boolean", "text")
    return [["null"] if value is None else [kind, str(value) if type(value) is int else value]
            for kind, value in zip(kinds, record)] + [["null"]]


def slot_term(kind, position):
    token = {23: "slot", 20: "wide-slot", 16: "bool-slot", 25: "text-slot"}[kind]
    return [token, position] if kind == 23 else [token, position, 0]


def query_plan(item, step):
    if step < 0 or step == 0 and item["provider"] == "constant-values" and not item["standalone"]:
        return values([row_terms(record) for record in data(item, step < 0)])
    if step == 0 and present_table(item):
        target, incoming = base_table(item)["target"], fields(item, 0)[:-1]
        selected, filter_slot, view_kind = [0, 1, 2, 3], None, False
    elif step == 0:
        target, incoming = coordinate(item, -1), fields(item, -1)
        selected, filter_slot, view_kind = list(ORDER), (2 if item["standalone"] else None), True
    else:
        plan = item["plans"][step - 1]
        target, incoming = coordinate(item, plan["parent"]), fields(item, plan["parent"])
        selected = plan["order"]
        filter_slot = next(slot for slot, (_, kind) in enumerate(incoming) if kind == 16) if plan["filter"] else None
        view_kind = True
    required = list(dict.fromkeys(([filter_slot] if filter_slot is not None else []) + selected))
    projection = [[slot_term(incoming[slot][1], required.index(slot)), incoming[slot][0]] for slot in selected]
    if step == 0 and present_table(item):
        projection += [[["null"], NAMES[-1]]]
    definition = scan(None, projection, source=False)
    by_native = {spec[1]: spec[2] for spec in NATIVE}
    definition["source"] = {"schema": target[0], "table": target[1], "columns": [incoming[i][0] for i in required],
                            "columnKinds": [by_native[incoming[i][1]] for i in required], "only": False}
    if view_kind:
        definition["source"]["relationKind"] = "view"
    if filter_slot is not None:
        definition["where"] = ["bool-slot", 0, 0]
    return definition


def view(item, step):
    return {"release": item["release"], "target": coordinate(item, step), "query": query_plan(item, step),
            "columns": [column for column, _ in fields(item, step)],
            "replace": False, "securityBarrier": None, "securityInvoker": None, "checkOption": ""}


def term_sql(term, canonical):
    kind, *body = term
    if kind == "null":
        return "NULL"
    value, = body
    if kind == "integer":
        return f"CAST({value} AS pg_catalog.int4)" if canonical else value
    if kind == "wide-integer":
        return f"CAST({value} AS pg_catalog.int8)" if canonical else value
    if kind == "boolean":
        return "TRUE" if value else "FALSE"
    literal = "'" + value.replace("'", "''") + "'"
    return f"CAST({literal} AS pg_catalog.text)" if canonical else literal


def view_sql(item, step, canonical, change=None):
    spec = view(item, step)
    schema, name = spec["target"]
    location = qualified(schema if canonical or item["qualified"] or step < 0 or item["standalone"] else None, name)
    heading = " (" + ", ".join(map(quoted, spec["columns"])) + ")"
    plan = spec["query"]
    if "postgresValues" in plan:
        tuples = ["(" + ", ".join(term_sql(term, canonical) for term in record) + ")" for record in plan["postgresValues"]]
        return "CREATE VIEW " + location + heading + " AS VALUES " + ", ".join(tuples)
    source = plan["source"]
    same_name_decoy = change == "decoy" and step == 1
    source_schema = EXTERNAL if same_name_decoy else source["schema"]
    target = qualified(source_schema if canonical or item["qualified"] or source_schema == EXTERNAL else None, source["table"])
    alias = "__ztd_source"

    def field(name):
        return qualified(alias if canonical else None, name)

    expressions = []
    for term, label in plan["columns"]:
        if term[0] == "null":
            expr = "NULL"
        else:
            chosen = source["columns"][term[1]]
            if change == "field" and step == 1 and chosen == "mystery":
                chosen = "note"
            expr = field(chosen)
        expressions.append(expr + " AS " + quoted(label) if canonical or term[0] == "null" else expr)
    query = "SELECT " + ", ".join(expressions) + " FROM " + target + (" AS " + quoted(alias) if canonical else "")
    if plan["where"] is not None:
        query += " WHERE " + field(source["columns"][0])
    return "CREATE VIEW " + location + heading + " AS " + query


def sql(item, *, canonical=False, change=None):
    if not canonical and "command" in item:
        return item["command"]
    if item["standalone"]:
        return view_sql(item, 0, canonical, change)
    header = spelling(item["namespace"], quoted(item["named"]) if item["named"] is not None else "CURRENT_USER" if canonical else None, False)
    ascending = range(item["width"])
    view_steps = reversed(ascending) if change == "reverse" else ascending
    views = [view_sql(item, step, canonical, change) for step in view_steps]
    tables = [table_sql(base_table(item), canonical or item["qualified"], canonical)] if present_table(item) else []
    return " ".join([header, *(tables + views if canonical else views + tables)])


def analyzed(item):
    declarations = [f"CREATE ROLE {quoted(role)}" for role in (OWNER, QUOTED_OWNER)]
    declarations += [f"CREATE SCHEMA {quoted(EXTERNAL)}", view_sql(item, -1, False)]
    if item["standalone"]:
        declarations.append(f"CREATE SCHEMA {quoted(item['namespace'])}")
    return {"dialect": "postgres", "release": item["release"], "sql": sql(item), "schemaPath": [EXTERNAL, "public"],
            "declarations": declarations}


def direct(item):
    common = {"dialect": "postgres", "release": item["release"]}
    if item["standalone"]:
        return {**common, "createView": view(item, 0)}
    return {**common, "createSchema": {
        "name": item["namespace"], "ifNotExists": False,
        "owner": {"session": "CURRENT_USER"} if item["named"] is None else {"named": item["named"]},
        "views": [view(item, step) for step in range(item["width"])], "tables": [base_table(item)] if present_table(item) else [],
    }}


def rows(item, step):
    if step < 0 or step == 0 and item["provider"] in ("constant-values", "base-table") and not item["standalone"]:
        return data(item, step < 0)
    if step == 0:
        return [row for row in data(item, True) if not item["standalone"] or row[2] is True]
    plan = item["plans"][step - 1]
    parent = plan["parent"]
    flag = next(slot for slot, (_, kind) in enumerate(fields(item, parent)) if kind == 16)
    return [[row[slot] for slot in plan["order"]] for row in rows(item, parent) if not plan["filter"] or row[flag] is True]


def view_state(item, step):
    schema, name = coordinate(item, step)
    deps = [[schema, name, None, 0, "i"]]
    if step == 0 and present_table(item):
        deps.extend([schema, "feed_data", col, slot + 1, "n"] for slot, col in enumerate(NAMES[:-1]))
    elif step >= 0 and (step > 0 or item["provider"] == "external-view" or item["standalone"]):
        parent = item["plans"][step - 1]["parent"] if step > 0 else -1
        source, relation = coordinate(item, parent)
        deps.extend([source, relation, col, slot + 1, "n"] for slot, (col, _) in enumerate(fields(item, parent)))
    owner = EFFECTIVE if step < 0 or item["standalone"] else item["named"] or EFFECTIVE
    return {"owner": owner, "heading": fields(item, step), "rows": sorted(rows(item, step), key=json.dumps),
            "dependencies": sorted(deps, key=json.dumps), "options": []}


def expected(item):
    owner = item["named"] or EFFECTIVE
    before = {"owner": owner if item["standalone"] else None, "table": None, "views": {}, "external": view_state(item, -1)}
    code = item.get("nativeRefusal", "00000")
    phase = None if code != "00000" else {**before, "owner": owner,
        "table": physical_table(base_table(item), owner) if present_table(item) else None,
        "views": {coordinate(item, step)[1]: view_state(item, step) for step in range(item["width"])}}
    return {"status": STATUS, "sqlState": code, "command": ("CREATE VIEW" if item["standalone"] else "CREATE SCHEMA") if phase else None,
            "transactionAfter": ("active" if phase is not None else "failed") if item["active"] else "idle",
            "notices": [], "before": before, "during": phase, "settled": before if item["active"] or phase is None else phase}
