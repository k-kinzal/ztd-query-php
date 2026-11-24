"""Independent PostgreSQL sequence setting transitions and fixture inputs."""

from postgres_create_sequence_cases import COUNTER_COLUMNS, TYPES, sequence
from postgres_drop_sequence_cases import DATABASE, EXISTING, LOCAL, OTHER, ROW_HEADING, analyzed as context
from postgres_drop_sequence_cases import generator_spec, row_values, targets, trial as drop_trial
from postgres_reindex_cases import qualified, quoted

STATUS = "sequence-reconfiguration"
PRESENT = ("local", "other", "temporary")
MISSING = ("missing-scoped", "missing-bare", "missing-namespace")
ROLES = PRESENT + MISSING
SCOPE_MAP = dict(zip(MISSING, ("missing-relation", "unqualified-missing", "missing-schema"))) | {
    "missing-temporary": "temporary-missing"}
ASSIGNMENTS = ("restart-at-start", "explicit-restart", "start-and-restart", "numeric-configuration",
               "reset-bounds", "owner-link", "owner-clear")
ROLES_SQL = ("storage", "increment", "minimum", "maximum", "start", "restart", "cache", "cycle", "owner")
OWNER_TABLE = "sequence_owner"
TOKEN = "native-default"


def trial(tag, role, family, guard, active, *, coordinate=None, base=10, quoted_name=False, catalog=False,
          warm=False, varied=None, reversed_options=False, descending=False):
    item = drop_trial(tag, [SCOPE_MAP.get(role, role)], guard, False, active, True,
                      base=base, quoted_name=quoted_name, catalog=catalog)
    source_scope = item["namespaces"][role if role in PRESENT else "local"]
    roles = {
        "restart-at-start": {"restart": TOKEN},
        "explicit-restart": {"restart": str(base + 9)},
        "start-and-restart": {"start": str(base + 1), "restart": TOKEN},
        "numeric-configuration": {"storage": "INTEGER", "increment": "3", "minimum": "1", "maximum": "1024",
                                  "restart": "8", "cache": "2", "cycle": True},
        "reset-bounds": {"minimum": TOKEN, "maximum": TOKEN},
        "owner-link": {"owner": [source_scope, OWNER_TABLE, "anchor", "table"]},
        "owner-clear": {"owner": "remove"},
    }
    changes = roles[family] if varied is None else varied
    return {**item, "role": role, "family": family, "changes": changes, "warm": warm,
            "coordinate": coordinate, "reversed": reversed_options, "descending": descending,
            "oldOwner": family in ("owner-link", "owner-clear")}


def source(item):
    return targets(item)[0]


def active_role(item):
    return item["role"] if item["role"] in PRESENT else "local"


def owner_coordinate(item, role, column="decoy"):
    return [item["namespaces"][role], OWNER_TABLE, column, 1 if column == "decoy" else 2, "a"]


def option_value(key, value):
    if key == "owner":
        if value == "remove":
            return "OWNED BY NONE"
        return "OWNED BY " + qualified(*value[:2]) + "." + quoted(value[2])
    if key == "cycle":
        return "CYCLE" if value else "NO CYCLE"
    if value == TOKEN:
        return {"minimum": "NO MINVALUE", "maximum": "NO MAXVALUE", "restart": "RESTART"}[key]
    return {"storage": "AS", "increment": "INCREMENT BY", "minimum": "MINVALUE", "maximum": "MAXVALUE",
            "start": "START WITH", "restart": "RESTART WITH", "cache": "CACHE"}[key] + " " + value


def sql(item, *, canonical=False, change=None):
    if not canonical and "command" in item:
        return item["command"]
    scope, name, presence = source(item)
    if change == "decoy":
        scope = item["namespaces"]["other"]
    if not canonical and scope == "pg_temp" and item["role"] == "temporary":
        scope = None
    selector = qualified(scope, name)
    if not canonical and item["catalog"] and scope not in (None, "pg_temp"):
        selector = quoted(DATABASE) + "." + selector
    guarded = "IF EXISTS " if item["guard"] and change != "unguarded" else ""
    if canonical and presence != "existing":
        return f"ALTER SEQUENCE {guarded}{selector} RESTART"
    options = item["changes"].copy()
    if change == "restart-instead-of-start":
        options["restart"] = options.pop("start")
    if change == "drop-cycle":
        del options["cycle"]
    order = list(ROLES_SQL)
    if not canonical and item["reversed"]:
        order.reverse()
    commands = [option_value(key, options[key]) for key in order if key in options]
    return f"ALTER SEQUENCE {guarded}{selector} " + " ".join(commands)


def analyzed(item):
    request = context(item)
    request["declarations"] += [
        f"CREATE {'TEMPORARY ' if role == 'temporary' else ''}TABLE {qualified(scope, OWNER_TABLE)} (decoy int, anchor int)"
        for role, scope in item["namespaces"].items()
    ]
    return {**request, "sql": sql(item)}


def direct(item):
    selector = source(item)
    return {"dialect": "postgres", "release": item["release"], "reconfigureSequence": {
        "source": selector, "changes": item["changes"] if selector[2] == "existing" else None, "ifExists": item["guard"]}}


def old_values(item, role):
    model = generator_spec(item, role)
    if item["descending"] and role == "local":
        model.update(increment=-1, minimum=-32768, maximum=-1, start=-4, initial=-4)
    data = sequence(model)
    if item["warm"] and role == active_role(item):
        data["tuple"][2] = True
    del data["ownership"]
    return data


def transition(current, changes):
    configured = {**current, "tuple": list(current["tuple"])}
    storage_change = "storage" in changes
    old_type = next(rule for rule in TYPES.values() if rule[0] == current["storage"])
    new_type = TYPES[changes["storage"].lower()] if storage_change else old_type
    configured["storage"] = new_type[0]
    for key in ("increment", "start", "cache", "cycle"):
        if key in changes:
            configured[key] = changes[key] if key == "cycle" else int(changes[key])

    affected = set(changes) - {"storage", "start", "owner"}
    for key, index in (("minimum", 1), ("maximum", 2)):
        value = changes.get(key)
        migrate_limit = storage_change and current[key] == old_type[index]
        if value is None and not migrate_limit:
            continue
        affected.add(key)
        if value in (None, TOKEN):
            descending = configured["increment"] < 0
            configured[key] = new_type[index] if migrate_limit or descending == (key == "minimum") else (1 if key == "minimum" else -1)
        else:
            configured[key] = int(value)
    if "restart" in changes:
        initial = configured["start"] if changes["restart"] == TOKEN else int(changes["restart"])
        configured["tuple"] = [initial, 0, False]
    if affected:
        configured["tuple"][1] = 0
    minimum, maximum = configured["minimum"], configured["maximum"]
    if not (new_type[1] <= minimum < maximum <= new_type[2]) or any(
        value < minimum or value > maximum for value in (configured["start"], configured["tuple"][0])
    ):
        return None
    return configured


def generated_values(value, count):
    last, _, called = value["tuple"]
    output = []
    for _ in range(count):
        if called:
            candidate = last + value["increment"]
            if not value["minimum"] <= candidate <= value["maximum"]:
                if not value["cycle"]:
                    output.append({"sqlState": "2200H", "columns": None, "rows": None})
                    continue
                candidate = value["minimum"] if value["increment"] > 0 else value["maximum"]
            last = candidate
        called = True
        output.append({"sqlState": "00000", "columns": COUNTER_COLUMNS, "rows": [[last]]})
    return output


def expected(item):
    names, selected = item["namespaces"], active_role(item)
    old = {role: {"value": old_values(item, role),
                  "ownership": owner_coordinate(item, role) if item["oldOwner"] and selected == role else None}
           for role in EXISTING}
    before = {"sequences": old, "default": [names["local"], item["name"]], "rows": row_values(item), "columns": ROW_HEADING}
    code = item.get("nativeRefusal", "00000")
    notices, current, calls, rewritten = [], None, [], False
    if code == "00000":
        if source(item)[2] != "existing":
            if item["guard"]:
                current = before
                notices.append(["NOTICE", "00000", f'relation "{source(item)[1]}" does not exist, skipping', None, None])
            else:
                code = "3F000" if item["role"] == "missing-namespace" else "42P01"
        else:
            changes = item["changes"]
            changed = transition(old[selected]["value"], changes)
            if changed is None:
                code = "22023"
            else:
                changed_owner = old[selected]["ownership"]
                if "owner" in changes:
                    changed_owner = None if changes["owner"] == "remove" else owner_coordinate(item, selected, "anchor")
                current = {**before, "sequences": {**old, selected: {"value": changed, "ownership": changed_owner}}}
                rewritten = bool(set(changes) - {"owner"})
                if item.get("probeValues"):
                    calls = generated_values(changed, item["probeValues"])
    return {"status": STATUS, "sqlState": code, "command": "ALTER SEQUENCE" if code == "00000" else None,
            "before": before, "during": current, "settled": before if item["active"] or current is None else current,
            "transactionAfter": ("active" if code == "00000" else "failed") if item["active"] else "idle",
            "notices": notices, "calls": calls,
            "currval": old[selected]["value"]["tuple"][0] if item["warm"] and current is not None and source(item)[2] == "existing" else None,
            "rewritten": rewritten}
