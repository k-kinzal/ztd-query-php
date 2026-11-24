"""Independent effects of compatible ordinary view replacements within a schema."""

import json

from postgres_create_schema_cases import EFFECTIVE, OWNER, QUOTED_OWNER, spelling
from postgres_reindex_cases import qualified, quoted
from postgres_schema_view_dependencies_cases import slot_term, term_sql
from run_server_postgres_local_values import values
from run_server_postgres_with_projection import scan

STATUS = "schema-view-replacement"
NS = "ir_view_replacement"
OWNERS = ("effective", "named")
LAYOUTS = ("same-heading", "append-boolean", "explicit-collation")
POSITIONS = ("earlier", "earlier-and-later")


def trial(tag, ownership, layout, position, active, *, replacements=1, base=7, quoted_name=False, coordinate=None,
          reverse=False, select=False, option=None):
    return {"release": tag, "ownership": ownership, "layout": layout, "position": position, "active": active, "base": base,
            "namespace": 'Replacement. "棚"' if quoted_name else NS, "owner": (
                (QUOTED_OWNER if quoted_name else OWNER) if ownership == "named" else None),
            "quoted": quoted_name, "replacements": replacements, "reverse": reverse, "select": select,
            "option": option, "coordinate": coordinate}


def provider(item):
    return 'Feed. "棚"' if item["quoted"] else "feed"


def columns(item, version):
    quoted_name = item["quoted"]
    fields = [['Entry. "id"' if quoted_name else "n", 23, None], ["caption", 25,
               "C" if item["layout"] == "explicit-collation" else "default"]]
    if item["layout"] == "append-boolean":
        fields.extend([f"flag {position}" if quoted_name else f"flag{position}", 16, None] for position in range(1, version + 1))
    return fields


def records(item, version):
    number = item["base"] + 12 * version
    text = f"source-{version}"
    width = len(columns(item, version))
    row = [number, text] + [(version + i) % 2 == 0 for i in range(width - 2)]
    if item["layout"] == "explicit-collation" or item["select"]:
        return [row]
    return [row, row, [None] * width]


def literals(row):
    return [["null"] if value is None else ["boolean", value] if type(value) is bool else
            ["integer", str(value)] if type(value) is int else ["text", value] for value in row]


def query_source(item, version):
    records_ = records(item, version)
    if item["layout"] != "explicit-collation" and not item["select"]:
        return values([literals(row) for row in records_])
    projection = literals(records_[0])
    if item["layout"] == "explicit-collation":
        projection[1] = ["collated-text", "C", projection[1]]
    return scan(None, [[term, label] for term, (label, *_type) in zip(projection, columns(item, version))], source=False)


def definition(item, name, heading, body, *, replace=False, barrier=None, invoker=None):
    return {"release": item["release"], "target": [item["namespace"], name], "query": body, "columns": heading,
            "replace": replace, "securityBarrier": barrier, "securityInvoker": invoker, "checkOption": ""}


def root(item, version):
    names = [name for name, _, _ in columns(item, version)]
    return definition(item, provider(item), names, query_source(item, version), replace=version > 0,
                      barrier=True if version == 0 else item["option"],
                      invoker=(version % 2 == 1) if version > 0 else None)


def dependent(item, name, version, slots):
    refs = [columns(item, version)[slot] for slot in slots]
    labels = [f"taken {slot}" if item["quoted"] else field for slot, (field, *_type) in zip(slots, refs)]
    plan = scan(None, [[slot_term(kind, position), field] for position, (field, kind, _collation) in enumerate(refs)], source=False)
    native = {23: "int4", 25: "text", 16: "bool"}
    plan["source"] = {"schema": item["namespace"], "table": provider(item), "columns": [field for field, *_type in refs],
                      "columnKinds": [native[kind] for _, kind, _ in refs], "only": False, "relationKind": "view"}
    return definition(item, name, labels, plan)


def program(item):
    steps = [(root(item, 0), None)]
    earlier_order = (1, 0) if item["reverse"] else (0, 1)
    steps.append((dependent(item, "before_change", 0, earlier_order), earlier_order))
    for version in range(1, item["replacements"] + 1):
        steps.append((root(item, version), None))
        if item["position"] == "earlier-and-later" or version < item["replacements"]:
            slots = (len(columns(item, version)) - 1, 0)
            steps.append((dependent(item, f"after_change_{version}", version, slots), slots))
    return steps


def settings(view):
    return [key + ("=true" if view[attr] else "=false") for key, attr in (
            ("security_barrier", "securityBarrier"), ("security_invoker", "securityInvoker")) if view[attr] is not None]


def literal_sql(operand, canonical):
    if operand[0] == "collated-text":
        source = term_sql(operand[2], canonical)
        clause = source + ' COLLATE pg_catalog."C"'
        return f"({clause})" if canonical else clause
    return term_sql(operand, canonical)


def view_sql(view, canonical):
    target = qualified(view["target"][0] if canonical else None, view["target"][1])
    names = " (" + ", ".join(map(quoted, view["columns"])) + ")"
    options = settings(view)
    selection = "CREATE " + ("OR REPLACE " if view["replace"] else "") + "VIEW " + target + names
    if options:
        selection += " WITH (" + ", ".join(options) + ")"
    query = view["query"]
    if "postgresValues" in query:
        body = "VALUES " + ", ".join("(" + ", ".join(literal_sql(value, canonical) for value in row) + ")" for row in query["postgresValues"])
    elif query["source"] is None:
        body = "SELECT " + ", ".join(literal_sql(value, canonical) + " AS " + quoted(label) for value, label in query["columns"])
    else:
        alias = "__ztd_source"
        source = query["source"]
        target = qualified(source["schema"] if canonical else None, source["table"])
        terms = [qualified(alias, field) + " AS " + quoted(field) if canonical else quoted(field)
                 for field in source["columns"]]
        body = "SELECT " + ", ".join(terms) + " FROM " + target + (" AS " + quoted(alias) if canonical else "")
    return selection + " AS " + body


def mutation(item, mode):
    built = program(item)
    result = []
    for view, slots in built:
        if not view["replace"]:
            result.append(view)
        elif mode == "omit":
            continue
        elif mode == "guard":
            result.append({**view, "replace": False})
        elif mode == "old-value":
            result.append({**view, "query": query_source(item, 0)})
        elif mode == "append":
            result.append({**root(item, 0), "replace": True, "securityBarrier": view["securityBarrier"],
                           "securityInvoker": view["securityInvoker"]})
    return result


def sql(item, *, canonical=False, change=None):
    if not canonical and "command" in item:
        return item["command"]
    owner = quoted(item["owner"]) if item["owner"] is not None else "CURRENT_USER" if canonical else None
    header = spelling(item["namespace"], owner, False)
    views = [view for view, _ in program(item)] if change is None else mutation(item, change)
    return " ".join([header, *(view_sql(view, canonical) for view in views)])


def analyzed(item):
    return {"dialect": "postgres", "release": item["release"], "sql": sql(item),
            "declarations": [f"CREATE ROLE {quoted(role)}" for role in (OWNER, QUOTED_OWNER)]}


def direct(item):
    return {"dialect": "postgres", "release": item["release"], "createSchema": {
        "name": item["namespace"], "owner": {"session": "CURRENT_USER"} if item["owner"] is None else {"named": item["owner"]},
        "ifNotExists": False, "views": [view for view, _ in program(item)],
    }}


def view_state(view, parent, attributes, data, own):
    schema, name = view["target"]
    deps = [[schema, name, None, 0, "i"]]
    if parent is not None:
        table, physical, slots = parent
        deps += [[schema, table, physical[slot][0], slot + 1, "n"] for slot in slots]
    catalog_fields = [[number + 1, attr[0], attr[1], -1, attr[2]] for number, attr in enumerate(attributes)]
    return {"owner": own, "attributes": catalog_fields, "heading": [[label, oid] for label, oid, _ in attributes],
            "rows": sorted(data, key=json.dumps), "options": sorted(settings(view)), "dependencies": sorted(deps, key=json.dumps)}


def expected(item):
    baseline = {"owner": None, "views": {}}
    code = item.get("nativeRefusal", "00000")
    outcome = None
    if code == "00000":
        selected = item["owner"] or EFFECTIVE
        amount = item["replacements"]
        heading = columns(item, amount)
        data = records(item, amount)
        base = root(item, amount)
        views = {provider(item): view_state(base, None, heading, data, selected)}
        for view, slots in program(item):
            if slots is not None:
                projected = [[label, *heading[slot][1:]] for label, slot in zip(view["columns"], slots)]
                view_data = [[row[slot] for slot in slots] for row in data]
                views[view["target"][1]] = view_state(view, (provider(item), heading, slots), projected, view_data, selected)
        outcome = {"owner": selected, "views": views}
    return {"status": STATUS, "sqlState": code, "command": "CREATE SCHEMA" if outcome is not None else None,
            "transactionAfter": ("active" if outcome is not None else "failed") if item["active"] else "idle",
            "notices": [], "before": baseline, "during": outcome, "settled": baseline if item["active"] or outcome is None else outcome}
