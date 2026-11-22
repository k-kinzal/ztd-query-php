"""Independent old-row and current-row effect rules for native UPDATE."""

import itertools
import random

from server_cases import SCHEMAS
from server_delete_cases import OPERATORS, ROWS, literal, ordered
from server_join_cases import slot, truth, value

LAYOUTS = [["id", "delta"], ["delta", "id"]]
ASSIGNMENTS = [
    [[slot(0), literal(7)]], [[slot(1), slot(0)]], [[slot(0), ["null"]]],
    [[slot(0), literal(5)], [slot(1), slot(0)]],
]
FILTERS = [
    None, ["comparison", "=", slot(0), literal(0)], ["comparison", "<", slot(1), literal(1)], ["boolean", False],
    ["null-test", False, slot(1)],
    ["logic", "AND", ["logic", "NOT", ["null-test", False, slot(0)], None], ["comparison", "<", slot(1), literal(2)]],
]


def case(engine, tag, columns, assignments, where=None, only=False, table="target"):
    return {"dialect": engine, "release": tag,
            "target": {"schema": SCHEMAS[engine], "table": "ir_update_" + table, "columns": list(columns),
                       **({"only": only} if engine == "postgres" else {})},
            "assignments": assignments, "where": where}


def finite_cases(engine, tag):
    policies = (False, True) if engine == "postgres" else (False,)
    for columns, assignments, where, only in itertools.product(LAYOUTS, ASSIGNMENTS, FILTERS, policies):
        yield case(engine, tag, columns, assignments, where, only)


def finite_id(_, response, _item):
    target = response["target"]
    return (LAYOUTS.index(target["columns"]), ASSIGNMENTS.index(response["assignments"]), FILTERS.index(response["where"]),
            target.get("only", False))


def finite_ids(engine, specification, _tag):
    finite = specification["finite"]
    return set(itertools.product(range(len(finite["layouts"])), range(len(finite["assignmentForms"])),
                                 range(len(finite["filters"])), (policy == "ONLY" for policy in finite["policies"][engine])))


def seeded(engine, tag, seed, count):
    rng = random.Random(seed)
    values = [slot(0), slot(1), literal(-2147483648), literal(2147483647), literal(0), literal(1), ["null"]]
    for _ in range(count):
        writes = rng.sample((0, 1), rng.randint(1, 2)) if engine == "postgres" else [rng.randrange(2) for _ in range(rng.randint(1, 4))]
        assignments = [[slot(destination), rng.choice(values)] for destination in writes]
        comparison = ["comparison", rng.choice(tuple(OPERATORS)), rng.choice(values[:-1]), rng.choice(values[:-1])]
        where = rng.choice((None, ["boolean", True], ["boolean", False], ["null"], comparison, comparison, comparison))
        yield case(engine, tag, rng.choice(LAYOUTS), assignments, where, bool(rng.getrandbits(1)),
                   rng.choice(("target", "target", "child", "control")))


def analyzed(engine, tag):
    relation = SCHEMAS[engine] + ".ir_update_target"
    declaration = f"CREATE TABLE {relation} (id INTEGER, delta INTEGER)"
    examples = [
        (f"UPDATE {relation} SET id=1, delta=id", ["id", "delta"], [[slot(0), literal(1)], [slot(1), slot(0)]], None, False),
        (f"UPDATE {relation} AS r SET delta=r.id WHERE r.id<2", ["id", "delta"], [[slot(1), slot(0)]],
         ["comparison", "<", slot(0), literal(2)], False),
        (f"UPDATE {relation} SET id=id", ["id"], [[slot(0), slot(0)]], None, False),
        (f"UPDATE {relation} SET id=7 WHERE (delta IS NULL OR NOT (id < 1))",
         ["delta", "id"], [[slot(1), literal(7)]],
         ["logic", "OR", ["null-test", False, slot(0)], ["logic", "NOT", ["comparison", "<", slot(1), literal(1)], None]], False),
    ]
    if engine == "postgres":
        examples.append((f"UPDATE ONLY {relation} SET delta=NULL", ["delta"], [[slot(0), ["null"]]], None, True))
    else:
        examples.append((f"UPDATE {relation} SET id=0, id=delta", ["id", "delta"], [[slot(0), literal(0)], [slot(0), slot(1)]], None, False))
    for sql, columns, assignments, where, only in examples:
        yield {"dialect": engine, "release": tag, "sql": sql, "declarations": [declaration]}, case(
            engine, tag, columns, assignments, where, only)


def negatives(engine, tag):
    columns = ["id", "delta"]
    yield case(engine, tag, columns, []), "nonempty assignment list"
    yield case(engine, tag, columns, [[slot(0), slot(2)]]), "read slot must belong"
    yield case(engine, tag, columns, [[slot(2), literal(1)]]), "write slot must belong"
    yield case(engine, tag, columns, [[slot(0, 1), literal(1)]]), "target slot must belong to occurrence zero"
    yield case(engine, tag, columns, [[slot(0), literal(1)]], ["comparison", "<", slot(2), literal(1)]), "predicate slot must belong"
    yield case(engine, tag, columns, [[slot(0), literal(1)]], ["logic", "NOT", ["null-test", False, slot(0, 1)], None]), "predicate slot must belong"
    relation = SCHEMAS[engine] + ".ir_update_target"
    yield {"dialect": engine, "release": tag, "declarations": [f"CREATE TABLE {relation} (id INTEGER, delta INTEGER)"],
           "sql": f"UPDATE {relation} SET unknown_column=1"}, "unknown_column"
    if engine == "postgres":
        yield case(engine, tag, columns, [[slot(0), literal(1)], [slot(0), literal(2)]]), "cannot repeat a target slot"


def observation(effect):
    if effect["status"] != "updated":
        return effect
    return {**effect, "after": ordered(effect["after"]), "restored": ordered(effect["restored"])}


def expected(item):
    dialect = item["dialect"]
    target = item["target"]
    role = target["table"].removeprefix("ir_update_")
    tested = [role]
    if dialect == "postgres" and role == "target" and not target["only"]:
        tested.append("child")
    index = [LAYOUTS[0].index(label) for label in target["columns"]]
    after = {key: [row.copy() for row in data] for key, data in ROWS.items()}
    affected = 0
    for name in tested:
        for number, before in enumerate(ROWS[name]):
            if truth(item["where"], ([before[slot] for slot in index],), dialect):
                after_row = after[name][number]
                for destination, expression in item["assignments"]:
                    read = before if dialect == "postgres" else after_row
                    assigned = value(expression, ([read[slot] for slot in index],), dialect)
                    after_row[index[destination[1]]] = assigned
                if dialect == "postgres" or before != after_row:
                    affected += 1
                after["events"].append(["ir_update_" + name, *before, *after_row])
    return observation({"status": "updated", "count": affected, "columns": 0, "after": after, "restored": ROWS})


def requests(engine, tag, specification):
    for index, item in enumerate(finite_cases(engine, tag)):
        yield f"finite/{index}", item, item, None
    exploration = specification["exploration"]
    for seed in exploration["seeds"]:
        for index, item in enumerate(seeded(engine, tag, seed, exploration["casesPerSeed"])):
            yield f"seed/{seed}/{index}", item, item, None
    for index, (item, meaning) in enumerate(analyzed(engine, tag)):
        yield f"analysis/{index}", item, meaning, None
    for index, (item, reason) in enumerate(negatives(engine, tag)):
        yield f"negative/{index}", item, None, reason
