"""Independent automatic owner-column dependency and generator meanings."""

from postgres_create_sequence_cases import DATABASE, DECOY, SCHEMA, direct as sequence_direct
from postgres_create_sequence_cases import expected as generator_expected, sql as generator_sql, trial as generator_trial
from postgres_reindex_cases import qualified, quoted

STATUS = "sequence-owner-dependency"
ROLES = ("table", "foreign", "view", "partitioned", "temporary-table")
COLUMNS = ("leading", "trailing")
FIXATION = ("relative", "schema", "catalog")
KINDS = {"table": "r", "foreign": "f", "view": "v", "partitioned": "p"}
FDW = "ir_sequence_owner_without_handler"
SERVER = "ir_sequence_owner_local_handle"
OTHER_OWNER = "ir_sequence_other_owner"
ALTERNATE = "other_owner_candidate"


def trial(tag, role, column, fixation, guard, active, *, coordinate=None, storage="smallint",
          direction=1, policy="restart", quoted_name=False, occupied=None, base=19):
    temporary = role == "temporary-table"
    kind = "table" if temporary else role
    scope = "pg_temp" if temporary else SCHEMA
    name = 'Owner."棚"' if quoted_name else "owner"
    fields = ['First.棚', 'Last."値"'] if quoted_name else ["leading", "trailing"]
    selected = fields[COLUMNS.index(column)]
    base_case = generator_trial(tag, storage, direction, policy, "temporary" if temporary else "permanent",
                                guard=guard, active=active, occupied=occupied, quoted_name=quoted_name, span=base)
    return {**base_case, "ownerRole": role, "ownership": [scope, name, selected, kind], "fixation": fixation,
            "ownerColumns": fields, "columnRole": column, "coordinate": coordinate, "quoted": quoted_name}


def sql(item, *, canonical=False, change=None):
    if "command" in item and not canonical:
        return item["command"]
    sequence = generator_sql(item, canonical=canonical)
    if change == "omit":
        return sequence
    namespace, relation, column, _ = item["ownership"]
    if change == "wrong-relation":
        relation = ALTERNATE
    if change == "wrong-column":
        column = next(other for other in item["ownerColumns"] if other != column)
    qualifier = item["fixation"] if not canonical else "schema"
    written = qualified(namespace if qualifier != "relative" else None, relation)
    if qualifier == "catalog":
        written = quoted(DATABASE) + "." + written
    return sequence + " OWNED BY " + written + "." + quoted(column)


def owner_decl(item):
    schema, relation, _, kind = item["ownership"]
    loc = qualified(schema, relation)
    col1, col2 = map(quoted, item["ownerColumns"])
    if kind == "view":
        return f"CREATE VIEW {loc} AS SELECT CAST(NULL AS integer) AS {col1}, CAST(NULL AS text) AS {col2}"
    columns = f"{col1} integer, {col2} text"
    if kind == "foreign":
        return f"CREATE FOREIGN TABLE {loc} ({columns}) SERVER {SERVER}"
    if kind == "partitioned":
        return f"CREATE TABLE {loc} ({columns}, part_key integer) PARTITION BY RANGE (part_key)"
    storage = "TEMPORARY " if schema == "pg_temp" else ""
    return f"CREATE {storage}TABLE {loc} ({columns})"


def table_decl(namespace, relation, fields):
    col1, col2 = map(quoted, fields)
    prefix = "TEMPORARY " if namespace == "pg_temp" else ""
    return f"CREATE {prefix}TABLE {qualified(namespace, relation)} ({col1} integer, {col2} text)"


def relations(item):
    schema, name, _, _ = item["ownership"]
    fields = item["ownerColumns"]
    return [owner_decl(item), table_decl(schema, ALTERNATE, fields), table_decl(DECOY, name, fields)]


def analyzed(item):
    return {"dialect": "postgres", "release": item["release"], "sql": sql(item), "currentCatalog": DATABASE,
            "schemaPath": [SCHEMA, DECOY, "public"], "declarations": [
                f"CREATE SCHEMA {SCHEMA}", f"CREATE SCHEMA {DECOY}", *relations(item), *item.get("extraRelations", ()),
            ]}


def direct(item):
    return sequence_direct(item)


def owner_dependency(item):
    scope, name, column, kind = item["ownership"]
    attribute = -1 if item["columnRole"] == "system" else 1 + COLUMNS.index(item["columnRole"])
    return [scope, name, column, KINDS[kind], attribute, "a"]


def expected(item):
    prediction = generator_expected(item)
    prediction["status"] = STATUS
    prediction["removed"] = None
    during = prediction["during"]
    if during is not None and item["occupied"] is None:
        during["selected"]["ownership"] = [owner_dependency(item)]
        prediction["removed"] = {"afterOther": True, "afterOwner": False, "afterRollback": True, "decoyNeverRemoved": True}
    return prediction
