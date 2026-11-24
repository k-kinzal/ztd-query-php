"""Author relocation requests and check the detached constructor coordinates."""

from native_postgres_fixture import TABLESPACES
from postgres_reindex_cases import qualified, quoted
from postgres_reindex_verbose_cases import DATABASE, case as rebuild_case, coordinates, declarations, transport

ROLE_MAP = {
    "ordinary-index": "ordinary-index", "ordinary-table": "ordinary-table", "partition-index": "branch-index",
    "partition-table": "partition-branch", "schema": "ordinary-schema", "database": "database", "system": "system",
}
DESTINATIONS = {"pg_default": "pg_default", "application": TABLESPACES[0], "second-application": TABLESPACES[1]}
FORMS = ("identifier", "string", "repeated")
UNKNOWN = "ir_unregistered_relocation"
RESERVED = "pg_internal_relocation"
STORAGE_WARNING = ["WARNING", "42501", "cannot move system relations, skipping all", None, None]


def text_literal(value):
    return "'" + value.replace("'", "''") + "'"


def case(tag, role, destination, verbose, form, *, coordinate=None, label="", nested=False, extra=2,
         base=0, concurrent=False, active=False):
    item = rebuild_case(tag, ROLE_MAP[role], "option-combined" if concurrent else "bare" if verbose else "absent",
                        active, label=label, nested=nested, extra=extra, base=base)
    return {**item, "form": form, "verbose": verbose, "coordinate": coordinate, "relocationRole": role,
            "destination": destination, "tablespace": DESTINATIONS[destination]}


def source_space(item):
    return TABLESPACES[1] if item["tablespace"] == TABLESPACES[0] else TABLESPACES[0]


def target_sql(item, parts=None):
    selector, schema, *objects = coordinates(item)
    if parts is None:
        parts = []
    options = ("(" + ", ".join(parts) + ") ") if parts else ""
    locking = " CONCURRENTLY " if item["concurrent"] else " "
    target = qualified(schema, objects[0]) if objects else quoted(schema)
    return "REINDEX " + options + selector + locking + target


def sql(item):
    option = ["VERBOSE"] if item["verbose"] else []
    destination = item["tablespace"]
    form = item["form"]
    if form == "repeated":
        option.append(f"TABLESPACE {quoted(source_space(item))}")
    if form == "missing-argument":
        option.append("TABLESPACE")
    if form == "unknown-first":
        option.append(f"TABLESPACE {quoted(UNKNOWN)}")
    if form == "bare-default":
        spelling = "pg_default"
    else:
        spelling = text_literal(destination) if form == "string" else quoted(destination)
    return target_sql(item, [*option, f"TABLESPACE {spelling}"])


def emitted(item, destination, verbose):
    parameters = ["VERBOSE"] if verbose else []
    if destination is not None:
        parameters.append(f"TABLESPACE {quoted(destination)}")
    return target_sql(item, parameters)


def analyzed(item):
    storage = [f"CREATE TABLESPACE {quoted(name)} LOCATION '/external-assurance/{ordinal}'"
               for ordinal, name in enumerate(TABLESPACES)]
    return {"dialect": "postgres", "release": item["release"], "sql": sql(item), "currentCatalog": DATABASE,
            "declarations": [*storage, *declarations(item)], "schemaPath": ["fixture", "remote", "public", "pg_catalog"]}


def direct(item):
    return {"dialect": "postgres", "release": item["release"], "tablespace": item["tablespace"],
            "verbose": item["verbose"], **transport(item)}
