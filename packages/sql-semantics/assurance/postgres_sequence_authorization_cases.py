"""Independent PostgreSQL sequence authorization and ACL transitions."""

from postgres_drop_sequence_cases import DATABASE, EXISTING, LOCAL, OTHER, ROW_HEADING, row_values, targets, trial as drop_trial
from postgres_reconfigure_sequence_cases import SCOPE_MAP, analyzed as generator_context, old_values, owner_coordinate
from postgres_reindex_cases import qualified, quoted

STATUS = "sequence-authorization"
PRESENT = ("local", "other", "temporary")
MISSING = ("missing-scoped", "missing-bare", "missing-namespace")
ROLES = PRESENT + MISSING
PLANS = (("first",), ("effective",), ("session",), ("first", "second"), ("session", "first", "effective"),
         ("logged", "first", "unlogged"), ("first", "unlogged", "logged"))
LOGGING = {"logged": "p", "unlogged": "u"}
ACTOR = "ir_sequence_effective"
SCRATCH = "ir_sequence_grantee"
LOGIN = "postgres"
PRINCIPALS = {"first": "ir_sequence_first", "second": "ir_sequence_second"}
QUOTED_PRINCIPALS = {"first": 'Archive. "棚"', "second": 'Second."権"'}
TAGS = {"effective": "CURRENT_USER", "session": "SESSION_USER"}
PRIVILEGES = ("SELECT", "UPDATE", "USAGE")


def trial(tag, role, plan, guard, active, *, coordinate=None, base=10, quoted_name=False, catalog=False, owned=False,
          acl=False, effective=True, current_role_token=False):
    binding = drop_trial(tag, [SCOPE_MAP.get(role, role)], guard, False, active, True,
                         base=base, quoted_name=quoted_name, catalog=catalog)
    return {**binding, "role": role, "plan": list(plan), "coordinate": coordinate, "oldOwner": owned,
            "warm": False, "descending": False, "acl": acl, "effectiveRole": effective,
            "currentRoleToken": current_role_token, "principals": QUOTED_PRINCIPALS if quoted_name else PRINCIPALS}


def source(item):
    return targets(item)[0]


def selected_role(item):
    return item["role"] if item["role"] in PRESENT else "local"


def role_of(item, choice):
    if choice == "effective":
        return ACTOR if item["effectiveRole"] else LOGIN
    if choice == "session":
        return LOGIN
    return item["principals"][choice]


def spell(item, option, canonical):
    if option in LOGGING:
        return "SET " + option.upper()
    if option in TAGS:
        token = "CURRENT_ROLE" if option == "effective" and item["currentRoleToken"] and not canonical else TAGS[option]
    else:
        token = quoted(item["principals"][option])
    return "OWNER TO " + token


def sql(item, *, canonical=False, change=None):
    if not canonical and "command" in item:
        return item["command"]
    scope, name, presence = source(item)
    if change == "decoy":
        scope = item["namespaces"]["other"]
    selector = qualified(scope, name)
    if not canonical and item["catalog"] and scope not in (None, "pg_temp"):
        selector = quoted(DATABASE) + "." + selector
    if not canonical and item["role"] == "temporary":
        selector = qualified(None, name)
    guard = "IF EXISTS " if item["guard"] else ""
    options = ["effective"] if canonical and presence != "existing" else item["plan"]
    if change == "session":
        options = ["session" if kind == "effective" else kind for kind in options]
    if change == "last":
        options = options[-1:]
    if change == "omit-logging":
        options = [kind for kind in options if kind not in LOGGING]
    return f"ALTER SEQUENCE {guard}{selector} " + ", ".join(spell(item, option, canonical) for option in options)


def analyzed(item):
    base = generator_context({**item, "command": sql(item)})
    base["declarations"] += [f"CREATE ROLE {quoted(name)}" for name in (ACTOR, SCRATCH, LOGIN, *item["principals"].values())]
    return base


def action(item, option):
    if option in LOGGING:
        return {"logging": option}
    if option in TAGS:
        return {"owner": {"session": TAGS[option]}}
    return {"owner": {"name": item["principals"][option]}}


def direct(item):
    selector = source(item)
    return {"dialect": "postgres", "release": item["release"], "sequenceAuthorization": {
        "source": selector, "ifExists": item["guard"],
        "actions": [action(item, option) for option in item["plan"]] if selector[2] == "existing" else []}}


def initial_acl(item, role):
    if not item["acl"] or selected_role(item) != role:
        return None
    acl = [[LOGIN, LOGIN, privilege, False] for privilege in PRIVILEGES]
    acl += [[LOGIN, item["principals"]["first"], "SELECT", True],
            [LOGIN, SCRATCH, "UPDATE", False], [LOGIN, SCRATCH, "USAGE", False]]
    return sorted(acl)


def dependencies(owner, acl):
    result = {(role, "a") for grantor, grantee, _, _ in acl or [] for role in (grantor, grantee) if role not in (owner, LOGIN)}
    if owner != LOGIN:
        result.add((owner, "o"))
    return [list(record) for record in sorted(result)]


def transfer(entries, previous, current):
    if entries is None or previous == current:
        return entries
    privileges = {}
    for grantor, grantee, name, option in entries:
        choice = tuple(current if role == previous else role for role in (grantor, grantee)) + (name,)
        privileges[choice] = privileges.get(choice, False) or option
    return [list(record) + [option] for record, option in sorted(privileges.items())]


def recover_state(item, old):
    current = old["value"]["persistence"]
    modes = [LOGGING[mode] for mode in item["plan"] if mode in LOGGING]
    if modes and item["role"] == "temporary":
        return "42P16", None
    if any(mode != current for mode in modes[:-1]):
        return "0A000", None
    owner = LOGIN
    acl = old["acl"]
    for option in item["plan"]:
        if option not in LOGGING:
            chosen = role_of(item, option)
            if item["oldOwner"] and chosen != owner:
                return "0A000", None
            acl = transfer(acl, owner, chosen)
            owner = chosen
    new = {**old["value"], "owner": owner, "persistence": modes[-1] if modes else current}
    return "00000", {**old, "value": new, "acl": acl, "shared": dependencies(owner, acl)}


def expected(item):
    names, selected = item["namespaces"], selected_role(item)
    old = {role: {"value": old_values(item, role),
                  "ownership": owner_coordinate(item, role) if item["oldOwner"] and role == selected else None,
                  "acl": initial_acl(item, role), "shared": dependencies(LOGIN, initial_acl(item, role))}
           for role in EXISTING}
    before = {"sequences": old, "default": [names["local"], item["name"]], "rows": row_values(item), "columns": ROW_HEADING}
    code = item.get("nativeRefusal", "00000")
    notices, current, rewritten = [], None, False
    if code == "00000":
        if source(item)[2] != "existing":
            if item["guard"]:
                current = before
                notices.append(["NOTICE", "00000", f'relation "{source(item)[1]}" does not exist, skipping', None, None])
            else:
                code = "3F000" if item["role"] == "missing-namespace" else "42P01"
        else:
            code, changed = recover_state(item, old[selected])
            if changed is not None:
                current = {**before, "sequences": {**old, selected: changed}}
                rewritten = changed["value"]["persistence"] != old[selected]["value"]["persistence"]
    return {"status": STATUS, "command": "ALTER SEQUENCE" if code == "00000" else None, "sqlState": code,
            "before": before, "during": current, "settled": before if item["active"] or current is None else current,
            "transactionAfter": ("active" if code == "00000" else "failed") if item["active"] else "idle",
            "rewritten": rewritten, "notices": notices,
            "currentRole": ACTOR if item["effectiveRole"] else LOGIN, "sessionRole": LOGIN}
