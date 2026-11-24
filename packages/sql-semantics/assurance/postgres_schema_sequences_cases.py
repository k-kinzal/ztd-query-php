"""Independent sequence-first relation phases for PostgreSQL CREATE SCHEMA."""

from postgres_create_schema_cases import EFFECTIVE, OWNER, QUOTED_OWNER, spelling
from postgres_create_sequence_cases import direct as sequence_direct
from postgres_create_sequence_cases import generated_values, resolved, sequence, sql as sequence_sql, trial as sequence_trial
from postgres_reindex_cases import quoted
from postgres_schema_tables_cases import DECOY, expected as tables_expected, fields, table, table_sql

STATUS = "schema-sequence-composition"
LAYOUTS = ("independent", "repeated-sequence", "shared-name")


def trial(tag, authorization, order, layout, storage, active, *, coordinate=None, width=2, base=13, kind="smallint",
          direction=1, quoted_name=False, derived=False, explicit=False, occupied=False, policy="restart"):
    namespace = 'IR.Sequence "棚"' if quoted_name else "ir_ns_sequence_subject"
    named = (QUOTED_OWNER if quoted_name else OWNER) if authorization == "named" else None
    if derived:
        namespace = named
    first = 'Ticket. "棚"' if quoted_name else "ticket"
    plain = "payload"
    names = [first, *(f"seq_{i}" for i in range(1, width))]
    if layout == "repeated-sequence":
        names[-1] = first
    seen = set()
    sequences = []
    for index, name in enumerate(names):
        operand = sequence_trial(tag, kind, direction if index == 0 else -direction, policy, storage,
                                 span=abs(base) % 46 + 20 + index, cache=1 + index % 4, active=active,
                                 relative=not explicit, guard=name in seen, explicit_none=(index + base) % 2 == 0)
        sequences.append({**operand, "target": [namespace, name]})
        seen.add(name)
    table_names = [first if layout == "shared-name" else plain, *(f"batch_{i}" for i in range(1, width))]
    tables = [table(tag, [namespace, name], fields(base + i, bool(i % 2), quoted_name), name in seen)
              for i, name in enumerate(table_names)]
    return {"release": tag, "authorization": authorization, "order": order, "layout": layout, "storage": storage,
            "namespace": namespace, "named": named, "explicit": None if derived else namespace, "qualified": explicit,
            "active": active, "base": base, "occupied": occupied, "coordinate": coordinate, "quoted": quoted_name,
            "sequences": sequences, "members": tables}


def sql(item, *, canonical=False, change=None):
    if "command" in item and not canonical:
        return item["command"]
    role = quoted(item["named"]) if item["named"] else ("CURRENT_USER" if canonical else None)
    head = spelling(item["namespace"] if canonical else item["explicit"], role, False)
    descriptors = item["sequences"]
    if change == "discard":
        descriptors = []
    if change == "guard":
        descriptors = [{**child, "guard": False} for child in descriptors]
    if change == "namespace":
        descriptors = [{**child, "target": [DECOY, child["target"][1]]} for child in descriptors]
    sequences = [sequence_sql(child, canonical=canonical) for child in descriptors]
    tables = [table_sql(child, canonical or item["qualified"], canonical) for child in item["members"]]
    return " ".join([head, *(tables + sequences if not canonical and item["order"] == "table-first" else sequences + tables)])


def analyzed(item):
    return {"dialect": "postgres", "release": item["release"], "sql": sql(item),
            "declarations": [f"CREATE ROLE {quoted(OWNER)}", f"CREATE ROLE {quoted(QUOTED_OWNER)}"]}


def sequence_shape(child, effective):
    return {"release": child["release"], **sequence_direct(resolved(child) if effective else child)["createSequence"]}


def direct(item):
    owner = {"named": item["named"]} if item["named"] else {"session": "CURRENT_USER"}
    return {"dialect": "postgres", "release": item["release"], "createSchema": {
        "name": item["explicit"], "owner": owner, "ifNotExists": False, "tables": item["members"],
        "sequences": [sequence_shape(child, False) for child in item["sequences"]],
    }}


def expected(item):
    sequences = {}
    notices = []
    for child in item["sequences"]:
        local = child["target"][1]
        if local in sequences:
            notices.append(["NOTICE", "42P07", f'relation "{local}" already exists, skipping', None, None])
        else:
            sequences[local] = child
    admitted = []
    for child in item["members"]:
        local = child["target"][1]
        if local in sequences:
            notices.append(["NOTICE", "42P07", f'relation "{local}" already exists, skipping', None, None])
        else:
            admitted.append(child)
    model = tables_expected({**item, "members": admitted})
    model["status"] = STATUS
    model["before"]["sequences"] = {}
    # On rollback the base model aliases its settled and before maps.
    model["settled"].setdefault("sequences", {})
    model["generated"] = None
    problem = item.get("nativeRefusal")
    if problem is not None:
        model.update(during=None, settled=model["before"], defaults=None, command=None, notices=[],
                     sqlState=problem, transactionAfter="failed" if item["active"] else "idle")
    elif not item["occupied"]:
        owner = item["named"] or EFFECTIVE
        model["during"]["sequences"] = {name: {**sequence(child), "owner": owner} for name, child in sequences.items()}
        model["generated"] = {name: generated_values(child) for name, child in sequences.items()}
        model["notices"] = [*notices, *model["notices"]]
    return model
