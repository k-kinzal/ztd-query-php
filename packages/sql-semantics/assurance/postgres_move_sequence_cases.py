"""Independent PostgreSQL sequence relocation rules and finite inputs."""

from postgres_create_sequence_cases import sequence
from postgres_drop_sequence_cases import DATABASE, EXISTING, LOCAL, OTHER, ROW_HEADING, analyzed as context
from postgres_drop_sequence_cases import generator_spec, row_values, targets, trial as drop_trial
from postgres_reindex_cases import qualified, quoted

STATUS = "sequence-move"
PRESENT = ("local", "other")
MISSING = ("missing-scoped", "missing-bare", "missing-namespace")
ROLES = PRESENT + MISSING
DESTINATIONS = ("same", "first", "second")
DESTINATION_NAMES = {"first": "ir_counter_move_dest", "second": 'Outside."棚"'}
SCOPE_MAP = {"missing-scoped": "missing-relation", "missing-bare": "unqualified-missing",
             "missing-namespace": "missing-schema", "missing-temporary": "temporary-missing"}
SKIP_DESTINATION = "pg_catalog"
OWNER_TABLE = "sequence_owner"


def trial(tag, role, destination, guard, active, owned, *, coordinate=None, quoted_name=False,
          base=10, local_catalog=False, dependent=True, occupied=False):
    item = drop_trial(tag, [SCOPE_MAP.get(role, role)], guard, False, active, dependent,
                      base=base, quoted_name=quoted_name, catalog=local_catalog)
    return {**item, "role": role, "destinationChoice": destination, "owned": owned,
            "occupied": occupied, "coordinate": coordinate}


def source(item):
    return targets(item)[0]


def destination(item):
    choice = item["destinationChoice"]
    if choice == "same":
        return source(item)[0] or item["namespaces"]["local"]
    return DESTINATION_NAMES.get(choice, choice)


def owner_role(item):
    return item["role"] if item["role"] in PRESENT else "local"


def sql(item, *, canonical=False, change=None):
    if not canonical and "command" in item:
        return item["command"]
    namespace, name, presence = source(item)
    if change == "decoy":
        namespace = item["namespaces"]["other"]
    selector = qualified(namespace, name)
    if namespace not in (None, "pg_temp") and item["catalog"] and not canonical:
        selector = quoted(DATABASE) + "." + selector
    target = SKIP_DESTINATION if change == "stock" or canonical and presence != "existing" else destination(item)
    guard = "IF EXISTS " if item["guard"] and change != "unguarded" else ""
    return f"ALTER SEQUENCE {guard}{selector} SET SCHEMA {quoted(target)}"


def analyzed(item):
    result = context(item)
    result["declarations"] += [f"CREATE SCHEMA {quoted(name)}" for name in DESTINATION_NAMES.values()]
    return {**result, "sql": sql(item)}


def direct(item):
    selector = source(item)
    return {"dialect": "postgres", "release": item["release"], "moveSequence": {
        "source": selector, "destination": destination(item) if selector[2] == "existing" else None, "ifExists": item["guard"]}}


def state(item):
    if "nativeRefusal" in item:
        return item["nativeRefusal"]
    if source(item)[2] != "existing":
        if item["guard"]:
            return "00000"
        return "3F000" if item["role"] == "missing-namespace" else "42P01"
    if item["owned"]:
        return "0A000"
    return "42P07" if item["occupied"] else "00000"


def expected(item):
    namespaces = item["namespaces"]
    owned = owner_role(item) if item["owned"] else None
    prior = {role: {
        "scope": scope, "name": item["name"], "value": {key: value for key, value in sequence(generator_spec(item, role)).items() if key != "ownership"},
        "ownership": [scope, OWNER_TABLE, "anchor", 2, "a"] if role == owned else None,
    } for role, scope in namespaces.items()}
    occupied = None
    if item["occupied"]:
        occupied = {"kind": "r", "persistence": "p", "owner": "postgres", "rows": [[31]], "columns": [["retained", 23]]}
    dependency = [namespaces["local"], item["name"]] if item["dependent"] else None
    before = {"sequences": prior, "default": dependency, "rows": row_values(item), "columns": ROW_HEADING, "occupied": occupied}
    code = state(item)
    after, notices = None, []
    if code == "00000":
        if source(item)[2] == "existing":
            role = item["role"]
            values = {**prior, role: {**prior[role], "scope": destination(item)}}
            if role == "local" and dependency is not None:
                dependency = [destination(item), item["name"]]
            after = {**before, "sequences": values, "default": dependency}
        else:
            after = before
            notices.append(["NOTICE", "00000", f'relation "{source(item)[1]}" does not exist, skipping', None, None])
    return {"status": STATUS, "sqlState": code, "command": "ALTER SEQUENCE" if code == "00000" else None,
            "before": before, "during": after, "settled": before if item["active"] or after is None else after,
            "transactionAfter": ("active" if after is not None else "failed") if item["active"] else "idle",
            "notices": notices}
