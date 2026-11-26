"""Independent ACL and role scopes for nested PostgreSQL sequence grants."""

from postgres_create_schema_cases import EFFECTIVE, OWNER, QUOTED_OWNER, SESSION, spelling
from postgres_create_sequence_cases import sequence, sql as sequence_sql, trial as sequence_trial
from postgres_reindex_cases import qualified, quoted
from postgres_schema_sequences_cases import sequence_shape
from postgres_sequence_access_cases import BITS, PUBLIC, acl_order, named

STATUS = "schema-sequence-grants"
NS = "ir_grant_namespace"
DECOY = "ir_grant_decoy"
READER = "ir_schema_reader"
OTHER = "ir_schema_other"
ANON = "ir_schema_guest"
ACTOR = {"session": "CURRENT_USER"}
LOGIN = {"session": "SESSION_USER"}
OWNERS = ("effective", "named")
TARGETS = ("named-local", "named-mixed", "schema-local", "schema-mixed")
ROLES = (OWNER, QUOTED_OWNER, EFFECTIVE, SESSION, READER, OTHER, ANON, "PUBLIC")
ROLE_RECORDS = sorted([[role, "t" if role in (EFFECTIVE, SESSION) else "f"] for role in ROLES])
ROLE_PROBES = (READER, OTHER, ANON, "PUBLIC", OWNER, QUOTED_OWNER, EFFECTIVE, SESSION)


def trial(tag, owner, target, option, active, *, width=1, quoted_name=False, base=31, qualified_target=False,
          written_first=True, derived=False, settings=None, coordinate=None, empty=False):
    selected = (QUOTED_OWNER if quoted_name else OWNER) if owner == "named" else None
    namespace = selected if derived else ('Grant batch."棚"' if quoted_name else NS)
    names = ['Live."棚"', 'Reserve. 帳'] if quoted_name else ["tickets", "reserve"]
    policies = settings if settings is not None else [
        {"target": target, "rights": ["USAGE", "SELECT"], "grantees": [named(READER), LOGIN] if option else [PUBLIC, named("PUBLIC"), LOGIN],
         "grantor": ACTOR, "option": option, "repeat": False}
    ]
    return {"release": tag, "owner": owner, "named": selected, "namespace": namespace, "target": target, "option": option,
            "active": active, "width": width, "policies": policies, "names": names, "quoted": quoted_name, "base": base,
            "qualified": qualified_target, "first": written_first, "derived": derived, "coordinate": coordinate, "empty": empty}


def owner(item):
    return item["named"] or EFFECTIVE


def generators(item, external=False):
    names = item["names"]
    if item["empty"] and not external:
        return []
    schema = DECOY if external else item["namespace"]
    start = item["base"] + (1000 if external else 0)
    return [{**sequence_trial(item["release"], kind, 1, "default", "permanent"), "target": [schema, name],
             "start": start + index, "initial": start + index}
            for index, (kind, name) in enumerate(zip(("bigint", "integer"), names))]


def addresses(item, target, repeat):
    scope = item["namespace"]
    first, other = item["names"]
    definitions = {
        "named-local": ("named", [[scope, first, "existing"]]),
        "named-mixed": ("named", [[DECOY, first, "existing"], [scope, first, "existing"], [scope, other, "existing"]]),
        "schema-local": ("schemas", [scope]),
        "schema-mixed": ("schemas", [DECOY, scope]),
        "foreign-only": ("named", [[DECOY, first, "existing"]]),
    }
    kind, selected = definitions[target]
    return {kind: selected + [selected[0]] if repeat else selected}


def grants(item):
    return [{"release": item["release"], "target": addresses(item, p["target"], p["repeat"]), "rights": p["rights"],
             "grantees": p["grantees"], "grantor": p["grantor"], "grantOption": p["option"]} for p in item["policies"]]


def role_sql(role, canonical):
    if role == PUBLIC:
        return "PUBLIC"
    if role == ACTOR:
        return "CURRENT_USER" if canonical else "CURRENT_ROLE"
    if role == LOGIN:
        return "SESSION_USER"
    return quoted(role["name"])


def grant_sql(grant, item, canonical, change=None):
    rights = ", ".join(grant["rights"]) if grant["rights"] else "ALL"
    if "schemas" in grant["target"]:
        target = "ALL SEQUENCES IN SCHEMA " + ", ".join(map(quoted, grant["target"]["schemas"]))
    else:
        coordinates = []
        for scope, relation, _presence in grant["target"]["named"]:
            if change == "decoy" and scope == item["namespace"]:
                scope = DECOY
            space = scope if canonical or item["qualified"] or scope != item["namespace"] else None
            coordinates.append(qualified(space, relation))
        target = "SEQUENCE " + ", ".join(coordinates)
    recipients = ", ".join(role_sql(ACTOR if change == "recipient" and role == LOGIN else role, canonical) for role in grant["grantees"])
    option = " WITH GRANT OPTION" if grant["grantOption"] and change != "option" else ""
    grantor = " GRANTED BY " + role_sql(grant["grantor"], canonical) if grant["grantor"] is not None else ""
    return "GRANT " + rights + " ON " + target + " TO " + recipients + option + grantor


def sql(item, *, canonical=False, change=None):
    if not canonical and "command" in item:
        return item["command"]
    header = spelling(item["namespace"] if canonical or not item["derived"] else None,
                      quoted(item["named"]) if item["named"] is not None else "CURRENT_USER" if canonical else None, False)
    members = [sequence_sql(value, canonical=canonical) for value in generators(item)]
    access = [] if change == "phase" else [grant_sql(grant, item, canonical, change) for grant in grants(item)]
    return " ".join([header, *(access + members if item["first"] and not canonical else members + access)])


def analyzed(item):
    return {"dialect": "postgres", "release": item["release"], "sql": sql(item), "schemaPath": [DECOY, "public"],
            "declarations": [f"CREATE ROLE {quoted(role)}" for role in ROLES]
            + [f"CREATE SCHEMA {quoted(DECOY)}", *(sequence_sql(value) for value in generators(item, True))]}


def direct(item):
    return {"dialect": "postgres", "release": item["release"], "createSchema": {
        "name": None if item["derived"] else item["namespace"], "ifNotExists": False,
        "owner": {"session": "CURRENT_USER"} if item["named"] is None else {"named": item["named"]},
        "sequences": [sequence_shape(value, False) for value in generators(item)], "grants": grants(item),
    }}


def principal(item, role):
    if role == ACTOR:
        return owner(item)
    if role == LOGIN:
        return SESSION
    return None if role == PUBLIC else role["name"]


def requested(item, target):
    if "named" in target:
        return {(schema, name) for schema, name, _ in target["named"]}
    return {(schema, name) for schema in target["schemas"] for name in item["names"]
            if schema == DECOY or (schema == item["namespace"] and not item["empty"])}


def state(item, created=False):
    role = owner(item)
    baseline = [{"value": {**sequence(value), "owner": role}, "coordinate": value["target"],
                 "acl": None, "shared": [[role, "o"]]} for value in [*generators(item, True), *(generators(item) if created else [])]]
    results = {tuple(entry["coordinate"]): entry for entry in baseline}
    if created:
        for grant in grants(item):
            rights = set(grant["rights"]) & set(BITS) if grant["rights"] else set(BITS)
            if not rights:
                continue
            for coordinate in requested(item, grant["target"]):
                current = results[coordinate]
                historic = [[role, role, right, False] for right in BITS] if current["acl"] is None else current["acl"]
                edges = {(source, dest, right): option for source, dest, right, option in historic}
                for target in grant["grantees"]:
                    recipient = principal(item, target)
                    for right in rights:
                        edge = (role, recipient, right)
                        edges[edge] = edges.get(edge, False) or grant["grantOption"]
                current["acl"] = sorted(([a, b, right, bit] for (a, b, right), bit in edges.items()), key=acl_order)
                roles = {endpoint for source, dest, _ in edges for endpoint in (source, dest)} - {None, role}
                current["shared"] = sorted([[role, "o"], *([recipient, "a"] for recipient in roles)])
    for view in results.values():
        entries = view["acl"] or [[role, role, key, False] for key in BITS]
        view["effective"] = [[principal, [
            principal in (EFFECTIVE, SESSION) or any(sink in (None, principal) and which == right for _, sink, which, _ in entries)
            for right in BITS
        ]] for principal in ROLE_PROBES]
    return {"owner": role if created else None, "sequences": [results[key] for key in sorted(results)]}


def expected(item):
    code = item.get("nativeRefusal", "00000")
    baseline = state(item)
    created = state(item, True) if code == "00000" else None
    return {"status": STATUS, "sqlState": code, "command": "CREATE SCHEMA" if created else None, "notices": [],
            "transactionAfter": ("active" if created else "failed") if item["active"] else "idle", "before": baseline,
            "during": created, "settled": baseline if item["active"] or created is None else created}
