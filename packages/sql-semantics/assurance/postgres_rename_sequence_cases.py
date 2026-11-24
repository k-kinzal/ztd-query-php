"""Independent fixed-scope PostgreSQL sequence rename meanings."""

from postgres_create_sequence_cases import sequence
from postgres_drop_sequence_cases import DATABASE, EXISTING, LOCAL, OTHER, TABLE, ROW_HEADING, analyzed as known_context
from postgres_drop_sequence_cases import generator_spec, row_values, targets, trial as drop_trial
from postgres_reindex_cases import qualified, quoted

STATUS = "sequence-rename"
PRESENT = ("local", "temporary", "other")
MISSING = ("missing-scoped", "missing-bare", "missing-namespace")
ROLES = PRESENT + MISSING
MAP = {"missing-scoped": "missing-relation", "missing-bare": "unqualified-missing",
       "missing-namespace": "missing-schema", "missing-temporary": "temporary-missing"}


def trial(tag, role, guard, active, occupied, dependent, *, coordinate=None, quoted_name=False, base=10,
          local_catalog=False, temp_written=False):
    case = drop_trial(tag, [MAP.get(role, role)], guard, False, active, dependent, base=base, quoted_name=quoted_name)
    return {**case, "role": role, "occupied": occupied, "destination": 'Next. "棚"' if quoted_name else "renamed",
            "quoted": quoted_name, "catalog": local_catalog, "tempWritten": temp_written, "coordinate": coordinate}


def source(item):
    return targets(item)[0]


def occupied_scope(item):
    chosen = source(item)[0]
    return chosen if chosen in item["namespaces"].values() else item["namespaces"]["local"]


def sql(item, *, canonical=False, change=None):
    if not canonical and "command" in item:
        return item["command"]
    scope, name, _ = source(item)
    if change == "decoy":
        scope = item["namespaces"]["other"]
    if item["role"] == "temporary" and not canonical and not item["tempWritten"]:
        scope = None
    at = qualified(scope, name)
    if scope is not None and scope != "pg_temp" and item["catalog"] and not canonical:
        at = quoted(DATABASE) + "." + at
    destination = name if change == "same-name" else item["destination"]
    return ("ALTER SEQUENCE " + ("IF EXISTS " if item["guard"] and change != "unguarded" else "")
            + at + " RENAME TO " + quoted(destination))


def analyzed(item):
    return {**known_context(item), "sql": sql(item)}


def direct(item):
    return {"dialect": "postgres", "release": item["release"],
            "renameSequence": {"source": source(item), "destination": item["destination"], "ifExists": item["guard"]}}


def state(item):
    if item.get("nativeRefusal"):
        return item["nativeRefusal"]
    if item["role"] not in PRESENT:
        if not item["guard"]:
            return "3F000" if item["role"] == "missing-namespace" else "42P01"
        return "00000"
    if item["occupied"] or item["destination"] == source(item)[1]:
        return "42P07"
    return "00000"


def expected(item):
    prior = {where: {"name": item["name"], "value": sequence(generator_spec(item, where))} for where in EXISTING}
    occupant = None
    if item["occupied"]:
        mode = "t" if occupied_scope(item) == "pg_temp" else "p"
        occupant = {"kind": "r", "persistence": mode, "owner": "postgres", "rows": [[31]], "columns": [["retained", 23]]}
    dependency = [item["namespaces"]["local"], item["name"]] if item["dependent"] else None
    before = {"sequences": prior, "default": dependency, "rows": row_values(item), "columns": ROW_HEADING, "occupied": occupant}
    code = state(item)
    done = code == "00000"
    selected = item["role"]
    notices = []
    after = None
    if done:
        if selected in PRESENT:
            values = {**prior, selected: {**prior[selected], "name": item["destination"]}}
            dependency = [dependency[0], item["destination"]] if selected == "local" and dependency else dependency
            after = {**before, "sequences": values, "default": dependency}
        else:
            after = before
            notices.append(["NOTICE", "00000", f'relation "{source(item)[1]}" does not exist, skipping', None, None])
    return {"status": STATUS, "sqlState": code, "command": "ALTER SEQUENCE" if done else None,
            "before": before, "during": after, "settled": before if item["active"] or not done else after,
            "transactionAfter": ("active" if done else "failed") if item["active"] else "idle",
            "notices": notices}
