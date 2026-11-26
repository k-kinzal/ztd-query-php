"""Direct SQLite write plans and an independent state model for conflict policies."""

from itertools import product
from random import Random

from run_sqlite_delete import typed_rows
from sqlite_engine import typed
from sqlite_result_cases import integer

TABLE = "ir_conflict"
COLUMNS = ("id", "code", "payload")
INITIAL = ((1, 10, 101), (2, 20, 202))
DECLARATION = "CREATE TABLE ir_conflict(id INTEGER PRIMARY KEY, code INTEGER UNIQUE ON CONFLICT IGNORE, payload INTEGER NOT NULL DEFAULT 70)"
FIXTURE = DECLARATION + """;
CREATE TABLE ir_conflict_log(kind TEXT, id INTEGER, code INTEGER, payload INTEGER);
CREATE TABLE ir_marker(value INTEGER);
CREATE TRIGGER add_conflict AFTER INSERT ON ir_conflict
BEGIN INSERT INTO ir_conflict_log VALUES ('insert',NEW.id,NEW.code,NEW.payload); END;
CREATE TRIGGER edit_conflict AFTER UPDATE ON ir_conflict
BEGIN INSERT INTO ir_conflict_log VALUES ('update',NEW.id,NEW.code,NEW.payload); END;
CREATE TRIGGER remove_conflict AFTER DELETE ON ir_conflict
BEGIN INSERT INTO ir_conflict_log VALUES ('delete',OLD.id,OLD.code,OLD.payload); END;
"""
RESET = """
DELETE FROM ir_conflict;
DELETE FROM ir_marker;
INSERT INTO ir_conflict VALUES (1,10,101),(2,20,202);
DELETE FROM ir_conflict_log;
"""
INSERT_CASES = {
    "clean": [(3, 30, 303)],
    "rowid": [(3, 30, 303), (1, 11, 111), (4, 40, 404)],
    "unique": [(3, 30, 303), (4, 10, 444), (5, 50, 505)],
    "not-null": [(3, 30, 303), (4, 40, None), (5, 50, 505)],
}
UPDATE_CASES = {
    "clean": ("payload", 33), "rowid": ("id", 1), "unique": ("code", 10), "not-null": ("payload", None),
}


def atom(value):
    return ["null"] if value is None else integer(value)


def field(columns, name):
    return ["column", columns.index(name)]


def outputs(columns, names):
    return [[field(columns, name), label] for name, label in names]


def insert(rows, policy, returning=(), columns=COLUMNS):
    positions = [COLUMNS.index(name) for name in columns]
    return {"operation": "insert", "target": ["main", TABLE, list(columns)], "conflict": policy,
            "rows": [[atom(row[index]) for index in positions] for row in rows],
            "writes": [["column", position] for position in range(len(columns))],
            "returning": outputs(columns, returning), "bindings": {}}


def update(selector, assignments, policy, returning=(), columns=COLUMNS):
    return {"operation": "update", "target": ["main", TABLE, list(columns)], "conflict": policy,
            "assignments": [[columns.index(name), atom(value)] for name, value in assignments],
            "where": ["comparison", "=", field(columns, "id"), integer(selector)],
            "returning": outputs(columns, returning), "bindings": {}}


def finite_cases(domain):
    returning = [("payload", "written"), ("id", "key")]
    for kind, policy, scenario, returned in product(domain["operations"], domain["policies"],
                                                   domain["scenarios"], domain["returning"]):
        columns = returning if returned else []
        if kind == "insert":
            yield insert(INSERT_CASES[scenario], policy, columns)
        else:
            yield update(2, [UPDATE_CASES[scenario]], policy, columns)


def finite_id(reply, _domain):
    kind = reply["operation"]
    if kind == "insert":
        observed = reply["rows"]
        scenario = next(name for name, rows in INSERT_CASES.items() if observed == [[atom(value) for value in row] for row in rows])
    else:
        slot, operand = reply["assignments"][0]
        key = (reply["target"][2][slot], read_atom(operand))
        scenario = next(name for name, change in UPDATE_CASES.items() if key == change)
    return kind, reply["conflict"], scenario, bool(reply["returning"])


def finite_ids(domain):
    return {(kind, policy, name, returned) for kind in domain["operations"] for policy in domain["policies"]
            for name in domain["scenarios"] for returned in domain["returning"]}


def seeded(seed, count, policies):
    rng = Random(seed)
    for _ in range(count):
        policy = rng.choice(policies)
        layout = rng.sample(COLUMNS, k=3)
        returning = [(rng.choice(COLUMNS), rng.choice(("first", "other", "same", "same"))) for _ in range(rng.randrange(4))]
        if rng.randrange(2):
            case = rng.choice(("rowid", "unique", "not-null"))
            rows = []
            for index in range(rng.randrange(1, 6)):
                identity = rng.choice((1, 2, 3, 4, 5)) if case == "rowid" else index + 41
                code = rng.choice((10, 20, 30, 40, -9)) if case == "unique" else index + 101
                payload = None if case == "not-null" and rng.randrange(2) else rng.randrange(-72, 73)
                rows.append((identity, code, payload))
            yield insert(rows, policy, returning, layout)
        else:
            name = rng.choice(COLUMNS)
            choices = {"id": (1, 2, 3, 71), "code": (10, 20, 30, -3), "payload": (None, -2, 19)}
            assignments = [(name, rng.choice(choices[name]))]
            if name != "payload" and rng.randrange(2):
                assignments.insert(rng.randrange(2), ("payload", rng.randrange(94)))
            yield update(rng.choice((1, 2, 99)), assignments, policy, returning, layout)


def analysis_cases():
    yield ("REPLACE INTO main.ir_conflict (id,code,payload) VALUES (4,10,42) RETURNING id AS winner",
           insert([(4, 10, 42)], "replace", [("id", "winner")]))
    yield ("INSERT OR FAIL INTO main.ir_conflict (id,code,payload) VALUES (3,30,303),(1,11,111),(4,40,404)",
           insert(INSERT_CASES["rowid"], "fail"))
    yield ("INSERT OR ROLLBACK INTO main.ir_conflict (id,code,payload) VALUES (3,30,303),(4,40,NULL),(5,50,505)",
           insert(INSERT_CASES["not-null"], "rollback"))
    yield ("UPDATE OR REPLACE main.ir_conflict SET code=10 WHERE id=2 RETURNING id AS retained",
           update(2, [("code", 10)], "replace", [("id", "retained")], ("code", "id")))
    yield ("UPDATE OR FAIL main.ir_conflict SET payload=NULL WHERE id=1",
           update(1, [("payload", None)], "fail", columns=("payload", "id")))


def requests(specification):
    domain = specification["finite"]
    for index, request in enumerate(finite_cases(domain)):
        yield f"finite/{index}", request, request
    exploration = specification["exploration"]
    for seed in exploration["seeds"]:
        for index, request in enumerate(seeded(seed, exploration["casesPerSeed"], domain["policies"])):
            yield f"seed/{seed}/{index}", request, request
    for index, (sql, request) in enumerate(analysis_cases()):
        yield f"analysis/{index}", {"sql": sql, "declarations": [DECLARATION]}, request


def negatives():
    wrong = insert(INSERT_CASES["clean"], "replace")
    yield {**wrong, "rows": [wrong["rows"][0][:-1]]}, "rejected", "target width must match"
    wrong = update(2, [("code", 10)], "replace")
    yield {**wrong, "assignments": [[3, integer(21)]]}, "rejected", "assignment slot must belong"
    yield ({"sql": "REPLACE INTO ir_conflict VALUES (3,30,30) ON CONFLICT(id) DO NOTHING",
            "declarations": [DECLARATION]}, "rejected", "requires a single targetless DO NOTHING clause")


def read_atom(operand):
    if operand == ["null"]:
        return None
    if operand[0] == "integer":
        return int(operand[1])
    raise ValueError(f"Unregistered fixture operand {operand}")


def actions(request):
    columns = request["target"][2]
    if request["operation"] == "insert":
        names = [columns[reference[1]] for reference in request["writes"]]
        for row in request["rows"]:
            layout = dict(zip(names, map(read_atom, row)))
            yield None, tuple(layout[name] for name in COLUMNS)
        return
    selector = read_atom(request["where"][3])
    original = next((row for row in INITIAL if row[0] == selector), None)
    if original is not None:
        changes = {columns[slot]: read_atom(value) for slot, value in request["assignments"]}
        yield original, tuple(changes.get(name, value) for name, value in zip(COLUMNS, original))


def conflict(rows, old, new):
    if new[2] is None:
        return "payload", None
    peers = [row for row in rows if row != old]
    for index, column in ((0, "id"), (1, "code")):
        blocked = next((row for row in peers if row[index] == new[index]), None)
        if blocked is not None:
            return column, blocked
    return None, None


def expected(request, recursive, explicit):
    rows, logs, completed = list(INITIAL), [], []
    active = explicit
    policy = request["conflict"]
    error = None
    for old, new in actions(request):
        column, blocked = conflict(rows, old, new)
        if column is not None:
            if column in ("id", "code") and request.get("handler") == "do-nothing":
                continue
            rule = ("ignore" if column == "code" else "abort") if policy == "inherit" else policy
            if rule == "replace":
                if column == "payload":
                    new = (new[0], new[1], 70)
                    _, blocked = conflict(rows, old, new)
                if blocked is not None:
                    rows.remove(blocked)
                    if recursive:
                        logs.append(("delete", *blocked))
            elif rule == "ignore":
                continue
            else:
                kind = "NOT NULL" if column == "payload" else "UNIQUE"
                error = {"code": 19, "message": f"{kind} constraint failed: {TABLE}.{column}"}
                if rule != "fail":
                    rows, logs, completed = list(INITIAL), [], []
                if rule == "rollback":
                    active = False
                break
        if old is not None:
            rows.remove(old)
        rows.append(new)
        completed.append(new)
        logs.append((request["operation"], *new))
    returning = request["returning"]
    layout = request["target"][2]
    slots = [COLUMNS.index(layout[ref[1]]) for ref, _ in returning]
    result = typed_rows(tuple(row[index] for index in slots) for row in completed) if returning and error is None else []
    return {
        "fixture": {"recursiveTriggers": recursive, "explicitTransaction": explicit},
        "statement": {"names": [label for _, label in returning], "parameters": {}, "parameterCount": 0, "readOnly": False,
                      "rows": result, "changes": len(completed), "evaluationError": error},
        "rows": typed_rows(rows),
        "events": [[typed(value) for value in row] for row in logs],
        "active": active,
        "marker": typed_rows([(61,)]) if explicit and active else [],
    }
