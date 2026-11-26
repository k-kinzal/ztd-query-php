"""Independent ACL graph and native sequence privilege operands."""

from postgres_create_sequence_cases import sequence
from postgres_drop_sequence_cases import (
    DATABASE, EXISTING, LOCAL, OTHER, ROW_HEADING, analyzed as declaration_fixture, generator_spec, row_values, trial as fixture,
)
from postgres_reindex_cases import qualified, quoted

STATUS = "sequence-access"
LOGIN = "postgres"
ACTOR = "ir_acl_effective"
HEAD = "ir_acl_head"
LEAF = "ir_acl_leaf"
TAIL = "ir_acl_tail"
ALTERNATE = "ir_acl_alternate"
ESCAPED = 'Role. "棚"'
PRINCIPALS = (ACTOR, HEAD, LEAF, TAIL, ALTERNATE, "PUBLIC", ESCAPED)
ROLE_IDENTITIES = (LOGIN, *PRINCIPALS)
EMPTY = "ir_acl_empty"
BONUS = "extra_sequence"
OPERATIONS = ("grant", "grant-option", "restrict", "cascade-option")
TARGETS = {
    "local": ("named", ("local",)),
    "cross-schema-list": ("named", ("other", "local", "other")),
    "temporary": ("named", ("temporary",)),
    "all-in-two": ("schemas", ("other", "local")),
    "all-in-empty": ("schemas", ("empty",)),
}
RIGHTS = {"usage": ("USAGE",), "ordered-read-write": ("UPDATE", "SELECT"), "all": (), "rule": ("RULE",)}
OPTION_ONLY = ("restrict-option", "cascade-option")
RESTRICT = ("restrict", "restrict-option")
BITS = ("SELECT", "UPDATE", "USAGE")
ALL_SEQUENCES = (*EXISTING, "bonus")
PUBLIC = {"universal": True}
EFFECTIVE = {"session": "CURRENT_USER"}
SESSION = {"session": "SESSION_USER"}


def named(role):
    return {"name": role}


def trial(tag, operation, selected, rights, active, *, coordinate=None, base=19, quoted_name=False,
          catalog=False, grantees=None, grantor=None, actor=LOGIN, chain=None, alternate=False, current_role=False):
    target, addresses = TARGETS[selected] if isinstance(selected, str) else selected
    privilege = RIGHTS[rights] if isinstance(rights, str) else rights
    binding = fixture(tag, ["local"], False, False, active, True, base=base, quoted_name=quoted_name, catalog=catalog)
    return {**binding, "operation": operation, "targetKind": target, "addresses": list(addresses),
            "rights": list(privilege), "grantees": list(grantees) if grantees is not None else [named(HEAD)],
            "grantor": grantor, "actor": actor, "chain": not operation.startswith("grant") if chain is None else chain,
            "alternate": alternate, "currentRoleToken": current_role, "coordinate": coordinate}


def scope(item, role):
    return EMPTY if role == "empty" else item["namespaces"]["local" if role == "bonus" else role]


def sequence_spec(item, role):
    value = generator_spec(item, "local" if role == "bonus" else role)
    if role == "bonus":
        value.update(target=[scope(item, role), BONUS], start=item["base"] + 7, initial=item["base"] + 12)
    return value


def target_coordinate(item, role):
    return [scope(item, role), BONUS if role == "bonus" else item["name"]]


def affected(item):
    if item["targetKind"] == "named":
        return item["addresses"]
    return [sequence_role for ns in item["addresses"] for sequence_role in
            {"local": ("local", "bonus"), "other": ("other",), "temporary": ("temporary",), "empty": ()}[ns]]


def grant(item):
    return item["operation"] in ("grant", "grant-option")


def outline(item):
    return {"rights": item["rights"],
            "target": {item["targetKind"]: [
                [*target_coordinate(item, role), "existing"] if item["targetKind"] == "named" else scope(item, role)
                for role in item["addresses"]]},
            "grantees": item["grantees"], "grantor": item["grantor"],
            **({"grantOption": item["operation"] == "grant-option"} if grant(item) else {
                "grantOptionOnly": item["operation"] in OPTION_ONLY,
                "dependency": "RESTRICT" if item["operation"] in RESTRICT else "CASCADE"})}


def direct(item):
    return {"dialect": "postgres", "release": item["release"], "sequenceAccess": outline(item)}


def spell_role(item, role, canonical):
    if "name" in role:
        return quoted(role["name"])
    if role == PUBLIC:
        return "PUBLIC"
    return "CURRENT_ROLE" if role == EFFECTIVE and item["currentRoleToken"] and not canonical else role["session"]


def sql(item, *, canonical=False, change=None):
    if not canonical and "command" in item:
        return item["command"]
    operands = outline(item)
    targets = []
    if item["targetKind"] == "named":
        for role, (schema, name, _presence) in zip(item["addresses"], operands["target"]["named"]):
            scope_name = item["namespaces"]["other"] if change == "decoy" and role == "local" else schema
            if not canonical and role == "temporary":
                scope_name = None
            spelling = qualified(scope_name, name)
            if not canonical and item["catalog"] and scope_name not in (None, "pg_temp"):
                spelling = quoted(DATABASE) + "." + spelling
            targets.append(spelling)
        target = "SEQUENCE " + ", ".join(targets)
    else:
        target = "ALL SEQUENCES IN SCHEMA " + ", ".join(map(quoted, operands["target"]["schemas"]))
    recipients = [SESSION if change == "session" and role == EFFECTIVE else role for role in item["grantees"]]
    names = ", ".join(spell_role(item, role, canonical) for role in recipients)
    grantor = "" if item["grantor"] is None else " GRANTED BY " + spell_role(item, item["grantor"], canonical)
    rights = ", ".join(item["rights"]) or ("ALL" if canonical else "ALL PRIVILEGES")
    if grant(item):
        option = " WITH GRANT OPTION" if operands["grantOption"] and change != "drop-grant-option" else ""
        return f"GRANT {rights} ON {target} TO {names}{option}{grantor}"
    option = "GRANT OPTION FOR " if operands["grantOptionOnly"] and change != "drop-only" else ""
    behavior = "RESTRICT" if change == "restrict" else operands["dependency"]
    return f"REVOKE {option}{rights} ON {target} FROM {names}{grantor} {behavior}"


def analyzed(item):
    base = declaration_fixture(item)
    base["declarations"] += [f"CREATE SCHEMA {quoted(EMPTY)}",
                             f"CREATE SEQUENCE {qualified(scope(item, 'bonus'), BONUS)}"]
    base["declarations"] += [f"CREATE ROLE {quoted(role)}" for role in ROLE_IDENTITIES]
    return {**base, "sql": sql(item)}


def resolve(item, role):
    if role == PUBLIC:
        return None
    if role == EFFECTIVE:
        return item["actor"]
    if role == SESSION:
        return LOGIN
    return role["name"]


def acl_order(row):
    source, recipient, right, option = row
    return source, recipient is not None, recipient or "", right, option


def owner_acl():
    return [[LOGIN, LOGIN, right, False] for right in BITS]


def seeded_acl(item, role):
    if not item["chain"]:
        return None
    rows = owner_acl() + [[LOGIN, HEAD, right, True] for right in BITS]
    if role == "local":
        rows += [[HEAD, LEAF, "SELECT", True], [LEAF, TAIL, "SELECT", False]]
        if item["alternate"]:
            rows += [[LOGIN, ALTERNATE, "SELECT", True], [ALTERNATE, LEAF, "SELECT", True]]
    return sorted(rows, key=acl_order)


def shared(acl):
    dependent = {role for grantor, grantee, _, _ in acl or [] for role in (grantor, grantee) if role not in (None, LOGIN)}
    return [[role, "a"] for role in sorted(dependent)]


def remove_dependents(graph, role, privilege, cascade_mode):
    if role == LOGIN or any(recipient == role and right == privilege and option
                            for (_, recipient, right), option in graph.items()):
        return True
    outgoing = [edge for edge in graph if edge[0] == role and edge[2] == privilege]
    if outgoing and not cascade_mode:
        return False
    for edge in outgoing:
        if graph.pop(edge, False) and not remove_dependents(graph, edge[1], privilege, cascade_mode):
            return False
    return True


def apply(item, before):
    if item.get("nativeRefusal"):
        return item["nativeRefusal"], None
    if item["grantor"] is not None and resolve(item, item["grantor"]) != item["actor"]:
        return "0A000", None
    rights = tuple(dict.fromkeys(right for right in item["rights"] if right in BITS)) if item["rights"] else BITS
    recipients = [resolve(item, role) for role in item["grantees"]]
    roles = affected(item)
    if rights and roles and item["operation"] == "grant-option" and None in recipients:
        return "0LP01", None
    if not rights:
        return "00000", before
    result = {**before}
    grantor = HEAD if item["actor"] == HEAD else LOGIN
    for role in roles:
        graph = {(source, recipient, right): option for source, recipient, right, option in result[role]["acl"] or owner_acl()}
        for recipient in recipients:
            for privilege in rights:
                edge = (grantor, recipient, privilege)
                if grant(item):
                    graph[edge] = graph.get(edge, False) or item["operation"] == "grant-option"
                elif edge in graph:
                    lost_option = graph.pop(edge)
                    if item["operation"] in OPTION_ONLY:
                        graph[edge] = False
                    if lost_option and not remove_dependents(graph, recipient, privilege, item["operation"] not in RESTRICT):
                        return "2BP01", None
        acl = sorted(([source, recipient, right, option] for (source, recipient, right), option in graph.items()), key=acl_order)
        result[role] = {**result[role], "acl": acl, "shared": shared(acl)}
    return "00000", result


def expected(item):
    old = {role: {"value": sequence(sequence_spec(item, role)), "acl": seeded_acl(item, role),
                  "shared": shared(seeded_acl(item, role))} for role in ALL_SEQUENCES}
    before = {"sequences": old, "default": target_coordinate(item, "local"), "rows": row_values(item), "columns": ROW_HEADING}
    state, changed = apply(item, old)
    during = {**before, "sequences": changed} if changed is not None else None
    return {"status": STATUS, "command": ("GRANT" if grant(item) else "REVOKE") if state == "00000" else None,
            "sqlState": state, "currentRole": item["actor"], "sessionRole": LOGIN, "notices": [],
            "transactionAfter": ("active" if state == "00000" else "failed") if item["active"] else "idle",
            "before": before, "during": during, "settled": before if item["active"] or during is None else during}
