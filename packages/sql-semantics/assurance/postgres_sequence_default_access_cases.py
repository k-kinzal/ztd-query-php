"""Independent policies for future PostgreSQL sequence ACLs."""

from postgres_reindex_cases import quoted
from postgres_sequence_access_cases import BITS, EFFECTIVE, LOGIN, PUBLIC, SESSION, acl_order, named

STATUS = "sequence-default-access"
DATABASE = "ir_fixture"
ACTOR = "ir_default_engine"
CREATOR = "ir_default_creator"
OTHER_CREATOR = "ir_default_other"
READER = "ir_default_reader"
QUOTED = 'Policy. "棚"'
POLICY_CREATORS = (LOGIN, ACTOR, CREATOR, OTHER_CREATOR)
ROLE_IDENTITIES = (*POLICY_CREATORS, READER, QUOTED)
OPERATIONS = ("grant", "grant-option", "revoke", "revoke-option")
SCOPE_PLANS = {
    "implicit-global": ((), ()),
    "effective-local": ((EFFECTIVE,), ("west",)),
    "two-creators-global": ((named(CREATOR), named(OTHER_CREATOR)), ()),
    "session-creator-two-schemas": ((SESSION, named(CREATOR)), ("east", "west")),
}
RIGHTS = {"usage": ("USAGE",), "ordered-select-update": ("SELECT", "UPDATE"), "all": (), "rule": ("RULE",)}
PLAIN = {"west": "ir_default_west", "east": "ir_default_east"}
DELIMITED = {"west": 'Policy. "west"', "east": 'Policy. "east"'}
CHAIN = (
    (ACTOR, None, READER, ("USAGE",), False),
    (CREATOR, None, READER, BITS, True),
    (CREATOR, "west", None, ("SELECT",), False),
    (CREATOR, "east", QUOTED, ("UPDATE",), True),
    (LOGIN, "west", READER, ("SELECT",), False),
)


def trial(tag, operation, scope, rights, active, *, coordinate=None, quoted_name=False, actor=ACTOR,
          grantees=None, initialized=None, reverse=False, user_word=False, current_role=False, cascade=False):
    creators, schemas = SCOPE_PLANS[scope] if isinstance(scope, str) else scope
    return {"release": tag, "operation": operation, "creators": list(creators), "schemas": list(schemas),
            "namespaces": DELIMITED if quoted_name else PLAIN, "rights": list(RIGHTS[rights] if isinstance(rights, str) else rights),
            "active": active, "actor": actor, "grantees": [named(READER)] if grantees is None else list(grantees),
            "initialized": operation.startswith("revoke") if initialized is None else initialized, "quoted": quoted_name,
            "reverseClauses": reverse, "userWord": user_word, "currentRoleToken": current_role, "cascade": cascade,
            "coordinate": coordinate}


def grant(item):
    return item["operation"] in ("grant", "grant-option")


def default_options(item):
    return {"grantOption": item["operation"] == "grant-option"} if grant(item) else {
        "grantOptionOnly": item["operation"] == "revoke-option", "dependency": "CASCADE" if item["cascade"] else "RESTRICT"}


def outline(item):
    return {"creators": item["creators"], "schemas": [item["namespaces"][role] for role in item["schemas"]],
            "rights": item["rights"], "grantees": item["grantees"], **default_options(item)}


def direct(item):
    return {"dialect": "postgres", "release": item["release"], "sequenceDefaultAccess": outline(item)}


def spell_role(item, role, canonical):
    if role == PUBLIC:
        return "PUBLIC"
    if "name" in role:
        return quoted(role["name"])
    return "CURRENT_ROLE" if role == EFFECTIVE and not canonical and item["currentRoleToken"] else role["session"]


def sql(item, *, canonical=False, change=None):
    if not canonical and "command" in item:
        return item["command"]
    parts = []
    body = outline(item)
    if change == "session-creator":
        parts.append("FOR ROLE SESSION_USER")
    elif item["creators"]:
        word = "USER" if item["userWord"] and not canonical else "ROLE"
        parts.append("FOR " + word + " " + ", ".join(spell_role(item, role, canonical) for role in item["creators"]))
    if body["schemas"] and change != "global":
        parts.append("IN SCHEMA " + ", ".join(map(quoted, body["schemas"])))
    if item["reverseClauses"] and not canonical:
        parts.reverse()
    rights = ", ".join(item["rights"]) or ("ALL" if canonical else "ALL PRIVILEGES")
    recipients = [PUBLIC] if change == "public" else item["grantees"]
    names = ", ".join(spell_role(item, role, canonical) for role in recipients)
    if grant(item):
        options = " WITH GRANT OPTION" if body["grantOption"] and change != "drop-grant-option" else ""
        action = f"GRANT {rights} ON SEQUENCES TO {names}{options}"
    else:
        option = "GRANT OPTION FOR " if body["grantOptionOnly"] and change != "drop-option-only" else ""
        action = f"REVOKE {option}{rights} ON SEQUENCES FROM {names} {body['dependency']}"
    return " ".join(["ALTER DEFAULT PRIVILEGES", *parts, action])


def analyzed(item):
    declarations = [f"CREATE ROLE {quoted(name)}" for name in ROLE_IDENTITIES]
    declarations += [f"CREATE SCHEMA {quoted(name)}" for name in item["namespaces"].values()]
    return {"dialect": "postgres", "release": item["release"], "sql": sql(item), "declarations": declarations}


def resolve(item, role):
    if role == PUBLIC:
        return None
    if role == EFFECTIVE:
        return item["actor"]
    if role == SESSION:
        return LOGIN
    return role["name"]


def base_graph(creator, scope):
    return {(creator, privilege): False for privilege in BITS} if scope is None else {}


def old_policy(item):
    policy = {}
    if item["initialized"]:
        for creator, scope, grantee, privileges, options in CHAIN:
            layer = policy.setdefault((creator, scope), base_graph(creator, scope))
            layer.update({(grantee, privilege): options for privilege in privileges})
    return policy


def apply(item, old):
    if item.get("nativeRefusal"):
        return item["nativeRefusal"], None
    creators = [resolve(item, role) for role in item["creators"]] if item["creators"] else [item["actor"]]
    actor = item["actor"]
    permitted = set(POLICY_CREATORS) if actor in (ACTOR, LOGIN) else {actor, CREATOR} if actor == OTHER_CREATOR else {actor}
    if not set(creators) <= permitted:
        return "42501", None
    rights = tuple(dict.fromkeys(right for right in item["rights"] if right in BITS)) if item["rights"] else BITS
    recipients = [resolve(item, role) for role in item["grantees"]]
    result = {key: layer.copy() for key, layer in old.items()}
    for creator in creators:
        for scope in item["schemas"] or [None]:
            implicit = base_graph(creator, scope)
            graph = result.get((creator, scope), implicit.copy())
            for grantee in recipients:
                for right in rights:
                    edge = (grantee, right)
                    if grant(item):
                        graph[edge] = graph.get(edge, False) or item["operation"] == "grant-option"
                    elif item["operation"] == "revoke-option":
                        if edge in graph:
                            graph[edge] = False
                    else:
                        graph.pop(edge, None)
            if graph == implicit:
                result.pop((creator, scope), None)
            else:
                result[creator, scope] = graph
    return "00000", result


def acl_rows(creator, graph):
    return sorted(([creator, who, privilege, option] for (who, privilege), option in graph.items()), key=acl_order)


def shares(creator, graph):
    roles = {who for who, _ in graph if who not in (None, LOGIN, creator)}
    edges = [[who, "a"] for who in roles]
    if creator != LOGIN:
        edges.append([creator, "o"])
    return sorted(edges)


def catalog(item, policy):
    return sorted(({"creator": creator, "schema": None if scope is None else item["namespaces"][scope], "kind": "S",
                    "acl": acl_rows(creator, graph), "shared": shares(creator, graph),
                    "namespace": [] if scope is None else [[item["namespaces"][scope], "a"]]}
                   for (creator, scope), graph in policy.items()),
                  key=lambda row: (row["creator"], row["schema"] is not None, row["schema"] or ""))


def owner_rights(creator, graph):
    recipients = {None, creator}
    if creator == OTHER_CREATOR:
        recipients.add(CREATOR)
    return [creator in (LOGIN, ACTOR) or any((role, right) in graph for role in recipients) for right in BITS]


def future(item, policy):
    entries = []
    for creator in POLICY_CREATORS:
        for scope in ("west", "east"):
            implicit = base_graph(creator, None)
            graph = policy.get((creator, None), implicit).copy()
            for edge, flag in policy.get((creator, scope), {}).items():
                graph[edge] = graph.get(edge, False) or flag
            materialized = bool(graph) and graph != implicit
            entries.append({"creator": creator, "schema": item["namespaces"][scope], "kind": "S",
                            "acl": acl_rows(creator, graph) if materialized else None,
                            "ownerRights": owner_rights(creator, graph if materialized else implicit)})
    entries.append({"creator": CREATOR, "schema": item["namespaces"]["west"], "kind": "r", "acl": None, "ownerRights": None})
    return entries


def expected(item):
    old = old_policy(item)
    code, current = apply(item, old)
    durable = old if item["active"] or current is None else current
    return {"status": STATUS, "sqlState": code, "command": "ALTER DEFAULT PRIVILEGES" if code == "00000" else None,
            "notices": [], "currentRole": item["actor"], "sessionRole": LOGIN,
            "transactionAfter": ("active" if code == "00000" else "failed") if item["active"] else "idle",
            "before": catalog(item, old), "during": None if current is None else catalog(item, current), "settled": catalog(item, durable),
            "futureDuring": None if current is None else future(item, current), "futureSettled": future(item, durable)}
