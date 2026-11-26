"""Independent PostgreSQL sequence logging effects and ordered action roles."""

from itertools import product

from postgres_drop_sequence_cases import DATABASE, EXISTING, LOCAL, OTHER, ROW_HEADING, row_values, targets, trial as drop_trial
from postgres_reconfigure_sequence_cases import OWNER_TABLE, SCOPE_MAP, active_role, analyzed as generator_context, old_values, owner_coordinate
from postgres_reindex_cases import qualified, quoted

STATUS = "sequence-logging"
PRESENT = ("local", "other")
MISSING = ("missing-scoped", "missing-bare", "missing-namespace")
ROLES = PRESENT + MISSING
MODES = {"logged": "p", "unlogged": "u"}
PLANS = tuple(plan for n in (1, 2, 3) for plan in product(MODES, repeat=n))


def trial(tag, role, modes, guard, active, *, coordinate=None, base=10, quoted_name=False, catalog=False, owned=False):
    binding = drop_trial(tag, [SCOPE_MAP.get(role, role)], guard, False, active, True,
                         base=base, quoted_name=quoted_name, catalog=catalog)
    return {**binding, "role": role, "modes": list(modes), "coordinate": coordinate,
            "oldOwner": owned, "warm": False, "descending": False}


def source(item):
    return targets(item)[0]


def sql(item, *, canonical=False, change=None):
    if not canonical and "command" in item:
        return item["command"]
    scope, name, presence = source(item)
    if change == "decoy":
        scope = item["namespaces"]["other"]
    selector = qualified(scope, name)
    if scope not in (None, "pg_temp") and item["catalog"] and not canonical:
        selector = quoted(DATABASE) + "." + selector
    modes = ["logged"] if canonical and presence != "existing" else item["modes"]
    if change == "reverse":
        modes = list(reversed(modes))
    if change == "drop-prefix":
        modes = modes[-1:]
    guard = "IF EXISTS " if item["guard"] and change != "unguarded" else ""
    return f"ALTER SEQUENCE {guard}{selector} " + ", ".join("SET " + mode.upper() for mode in modes)


def analyzed(item):
    return generator_context({**item, "command": sql(item)})


def direct(item):
    selector = source(item)
    return {"dialect": "postgres", "release": item["release"], "sequenceLogging": {
        "source": selector, "modes": item["modes"] if selector[2] == "existing" else [], "ifExists": item["guard"]}}


def expected(item):
    names = item["namespaces"]
    selected = item["role"] if item["role"] in PRESENT else "local"
    old = {role: {"value": old_values(item, role),
                  "ownership": owner_coordinate(item, role) if item["oldOwner"] and selected == role else None}
           for role in EXISTING}
    before = {"sequences": old, "default": [names["local"], item["name"]], "rows": row_values(item), "columns": ROW_HEADING}
    code = item.get("nativeRefusal", "00000")
    notices, current, rewritten = [], None, False
    if code == "00000":
        if item["role"] in PRESENT:
            previous = old[selected]["value"]
            modes = [MODES[mode] for mode in item["modes"]]
            if any(mode != previous["persistence"] for mode in modes[:-1]):
                code = "0A000"
            else:
                rewritten = modes[-1] != previous["persistence"]
                value = {**previous, "persistence": modes[-1]}
                current = {**before, "sequences": {**old, selected: {**old[selected], "value": value}}}
        elif item["guard"]:
            current = before
            notices.append(["NOTICE", "00000", f'relation "{source(item)[1]}" does not exist, skipping', None, None])
        else:
            code = "3F000" if item["role"] == "missing-namespace" else "42P01"

    return {"status": STATUS, "sqlState": code, "command": "ALTER SEQUENCE" if code == "00000" else None,
            "before": before, "during": current, "settled": before if item["active"] or current is None else current,
            "transactionAfter": ("active" if code == "00000" else "failed") if item["active"] else "idle",
            "notices": notices, "rewritten": rewritten}
