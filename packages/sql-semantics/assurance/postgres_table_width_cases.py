"""Describe native heap declaration widths, physical headings and row effects."""

from postgres_primary_key_cases import wire
from postgres_reindex_cases import qualified, quoted
from postgres_schema_tables_cases import OIDS, default_sql, table
from server_create_table_cases import default_value

STATUS = "ordinary-native-table-width"
SINGLE = "ir_width_single"
NS = "ir_width_batch"
WIDTHS = (0, 1, 127, 128, 129, 255, 1600)
LAYOUTS = ("integer", "bigint", "mixed")
TYPES = ("INTEGER", "BIGINT", "SMALLINT", "pg_catalog.text", "pg_catalog.bool")


def recipe(tag, width, layout, guard, *, coordinate=None):
    fields = [[f"field_{slot+1}", TYPES[slot % len(TYPES)] if layout == "mixed" else layout.upper(), True, None]
              for slot in range(width)]
    target = [SINGLE, "native_" + layout]
    return {"release": tag, "member": table(tag, target, fields, guard), "coordinate": coordinate,
            "compose": False, "prior": False, "probe": None}


def member_sql(member, *, canonical=False, local=False, change=None):
    source = member["columns"]
    if change == "fabricate":
        source = [["fabricated", "INTEGER", True, None]]
    elif change == "last":
        source = source[:-1]
    elif change == "first-type":
        source = [[source[0][0], "BIGINT", *source[0][2:]], *source[1:]]
    columns = []
    for name, kind, nullable, default in source:
        text = quoted(name) + " " + kind
        text += (" NULL" if canonical else "") if nullable else " NOT NULL"
        if default is not None:
            text += " DEFAULT " + ("NULL" if default == ["null"] else default_sql(default, canonical))
        columns.append(text)
    scope, name = member["target"]
    target = quoted(name) if local else qualified(scope, name)
    guard = "IF NOT EXISTS " if member["ifNotExists"] else ""
    return f"CREATE TABLE {guard}{target} (" + ", ".join(columns) + ")" + (" USING heap" if canonical else "")


def sql(item, *, canonical=False, change=None):
    statement = member_sql(item["member"], canonical=canonical, change=change, local=item["compose"] and not canonical)
    if not item["compose"]:
        return statement
    return "CREATE SCHEMA " + quoted(NS) + (" AUTHORIZATION CURRENT_USER " if canonical else " ") + statement


def analyzed(item):
    return {"dialect": "postgres", "release": item["release"], "sql": sql(item),
            "declarations": ["CREATE TABLE public.ir_width_binding_anchor (id INTEGER)"]}


def direct(item):
    result = {"dialect": "postgres", "release": item["release"]}
    if item["compose"]:
        result["createSchema"] = {"name": NS, "owner": {"session": "CURRENT_USER"}, "ifNotExists": False,
                                  "tables": [item["member"]]}
    else:
        result["create"] = {key: value for key, value in item["member"].items() if key != "release"}
    return result


def existing(item):
    return table(item["release"], item["member"]["target"], [["prior_identity", "INTEGER", False, ["integer", "23"]]], False)


def expected(item):
    member = existing(item) if item["prior"] else item["member"]
    name = member["target"][1]
    fields = member["columns"]
    failure = item.get("nativeRefusal")
    if failure:
        return {"status": STATUS, "sqlState": failure, "notices": [], "relation": None, "transaction": "failed",
                "afterRollback": None, "writes": None}
    expected_catalog = [[slot, label, OIDS[kind], nullable, default not in (None, ["null"])]
                        for slot, (label, kind, nullable, default) in enumerate(fields, 1)]
    relation = {"kind": "r", "width": len(fields), "access": "heap", "fields": expected_catalog}
    heading = [[field[0], OIDS[field[1]]] for field in fields]
    default = [wire(default_value(kind, value)) for _, kind, _, value in fields]
    uses_returning = item["probe"] == "returning"
    packed = item["probe"] == "pack"
    attempt = ("54000" if fields[0][1] == "BIGINT" else "00000") if packed else "42601" if uses_returning else None
    rows = [default, default]
    if attempt == "00000":
        rows.append([str(int(slot) + 1) for slot in range(len(fields))])
    return {
        "status": STATUS, "sqlState": "00000", "notices": ([["NOTICE", "42P07", f'relation "{name}" already exists, skipping', None, None]]
                                                        if item["prior"] else []),
        "relation": relation, "transaction": "active", "afterRollback": None,
        "writes": {"default": ["00000", "00000"], "packedOrReturning": attempt, "heading": heading,
                   "rows": rows, "count": len(rows)},
    }
