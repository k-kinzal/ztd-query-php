"""Construct generation DAGs and interpret their bounded row and catalog effects."""

from itertools import product
from random import Random

from sqlite_engine import typed
from sqlite_unique_key_cases import key

TABLE = "ir_generated"
DIMENSIONS = ("storage", "strict", "dependencies", "expressions", "affinity")
ORDINARY = ("base", "peer", "seq")
ROWS = ((-4, 11, 1), (8, -3, 2), (None, 2, 3), (3, None, 4), (8, -3, 5))
CREATE = {"names": [], "rows": [], "changes": 0, "readOnly": False, "evaluationError": None}


def column(slot):
    return ["column", slot]


def integer(value):
    return ["integer", str(value)]


def arithmetic(operator, left, right):
    return ["arithmetic", operator, left, right]


def plan(columns, *, schema="main", strict=False, keys=()):
    return {
        "operation": "create-table", "schema": schema, "table": TABLE, "columns": columns,
        "strict": strict, "ifNotExists": False, "withoutRowid": False, "keys": list(keys), "checks": [],
    }


def generation(name, affinity, mode, expression, not_null=False):
    return [name, affinity, not_null, None, "BINARY", [mode, expression]]


def ordinary():
    return [[name, "INTEGER", False] for name in ORDINARY]


def expressions(item):
    return {slot: field[5] for slot, field in enumerate(item["columns"]) if len(field) > 5 and field[5] is not None}


def finite_case(mode, strict, dependencies, expression, affinity):
    fields = ordinary()
    if dependencies == "single":
        source = 0
    elif dependencies == "forward":
        source = 4
    elif dependencies == "linear":
        fields.append(generation("aux", "INTEGER", "VIRTUAL", arithmetic("+", column(0), integer(3))))
        source = 3
    else:
        fields.extend([
            generation("aux", "INTEGER", "VIRTUAL", arithmetic("+", column(0), integer(3))),
            generation("next", "INTEGER", "STORED", arithmetic("-", column(0), column(1))),
            generation("total", "INTEGER", "VIRTUAL", arithmetic("+", column(3), column(4))),
        ])
        source = 5
    value = column(source)
    if expression != "reference":
        value = arithmetic({"sum": "+", "difference": "-"}[expression], value, column(1))
    fields.append(generation("calc", affinity, mode, value))
    if dependencies == "forward":
        fields.append(generation("aux", "INTEGER", "VIRTUAL", arithmetic("+", column(0), integer(3))))
    return plan(fields, strict=strict)


def finite_ids(specification):
    return set(product(*(specification[dimension] for dimension in DIMENSIONS)))


def references(expression):
    if expression[0] == "column":
        yield expression[1]
    elif expression[0] == "arithmetic":
        for operand in expression[2:]:
            yield from references(operand)


def finite_id(reply, _domain):
    names = [field[0] for field in reply["columns"]]
    slots = {slot: (mode, value) for slot, mode, value in reply["generated"]}
    heads = {slot: set(references(value)) & slots.keys() for slot, (_, value) in slots.items()}
    root = names.index("calc")
    if not heads[root]:
        dependencies = "single"
    elif any(len(inputs) > 1 for inputs in heads.values()):
        dependencies = "multiple"
    elif next(iter(heads[root])) > root:
        dependencies = "forward"
    else:
        dependencies = "linear"
    mode, value = slots[root]
    form = "reference" if value[0] == "column" else {"+": "sum", "-": "difference"}[value[1]]
    return (mode, reply["strict"], dependencies, form, reply["columns"][root][1])


def seeded(seed, budget):
    rng = Random(seed)
    for index in range(budget):
        # Dependency construction follows topological order and SQL declaration order is separately permuted.
        # The independent observer later recovers both from the actual value.
        depth = 2 + index % 8
        generated = list(range(3, 3 + depth))
        order = generated.copy()
        rng.shuffle(order)
        slot = {identity: offset for offset, identity in enumerate([0, 1, 2, *order])}
        fields = {}
        for node in generated:
            parents = [0] if node == 3 else rng.sample([0, 1, *range(3, node)], rng.randrange(1, min(node - 1, 4)))
            terms = [column(slot[parent]) for parent in parents]
            if node % 3 == 0:
                terms.append(integer(rng.randrange(-13, 14)))
            value = terms[0]
            for operand in terms[1:]:
                value = arithmetic(rng.choice(("+", "-")), value, operand)
            name = "calc" if node == generated[-1] else f'gen "{node}"'
            fields[node] = generation(name, rng.choice(("INTEGER", "TEXT")), rng.choice(("VIRTUAL", "STORED")), value)
        yield plan([*ordinary(), *(fields[node] for node in order)], schema=rng.choice(("main", "temp")), strict=bool(rng.getrandbits(1)))


def parts():
    return f"CREATE TABLE {TABLE} (base INTEGER, peer INTEGER, seq INTEGER"


def analyzed():
    high, base = column(0), ordinary()
    declare = parts()
    yield (declare + ", calc INTEGER\n GENERATED\tALWAYS AS (base+peer) STORED)",
           plan([*base, generation("calc", "INTEGER", "STORED", arithmetic("+", high, column(1)))]))
    yield (declare + ', calc "INTEGER" /* read through quoted type */ GENERATED ALWAYS AS (base))',
           plan([*base, generation("calc", "INTEGER", "VIRTUAL", high)]))
    yield (declare + ", calc GENERATED ALWAYS AS (base))", plan([*base, generation("calc", "", "VIRTUAL", high)]))
    yield (declare + ", calc INTEGER AS (base), folded INT AS ((?09 + ?001 + ?009) NOT IN ()))",
           plan([*base, generation("calc", "INTEGER", "VIRTUAL", high),
                 generation("folded", "INT", "VIRTUAL", ["folded-membership", True, ["09", "001", "009"]])]))
    yield (declare + ", calc INTEGER AS (peer+aux) STORED, aux INTEGER AS (base+2) VIRTUAL) STRICT",
           plan([*base, generation("calc", "INTEGER", "STORED", arithmetic("+", column(1), column(4))),
                 generation("aux", "INTEGER", "VIRTUAL", arithmetic("+", high, integer(2)))], strict=True))
    yield (declare.replace("base INTEGER", "base INTEGER PRIMARY KEY") + ", calc INTEGER AS (base+peer) STORED)",
           plan([*base, generation("calc", "INTEGER", "STORED", arithmetic("+", high, column(1)))],
                keys=[key([(0, False, "BINARY")], kind="primary", inline=True)]))
    yield (declare + ", calc INTEGER NOT NULL UNIQUE GENERATED ALWAYS AS (base))",
           plan([*base, generation("calc", "INTEGER", "VIRTUAL", high, True)],
                keys=[key([(3, False, "BINARY")], inline=True)]))


def requests(contract):
    domain, settings = contract["finite"], contract["exploration"]
    for index, choices in enumerate(product(*(domain[dimension] for dimension in DIMENSIONS))):
        item = finite_case(*choices)
        yield f"finite/{index}", item, item
    for seed in settings["seeds"]:
        for index, item in enumerate(seeded(seed, settings["casesPerSeed"])):
            yield f"seed/{seed}/{index}", item, item
    for index, (sql, item) in enumerate(analyzed()):
        yield f"analyze/{index}", {"sql": sql, "declarations": []}, item


def negatives():
    base = ordinary()
    value = lambda term: generation("calc", "INTEGER", "VIRTUAL", term)
    cyclic = "cannot have a cycle"
    yield plan([*base, value(column(3))]), "rejected", cyclic
    yield plan([*base, value(column(4)), generation("aux", "TEXT", "STORED", column(3))]), "rejected", cyclic
    yield plan([value(integer(4))]), "rejected", "at least one ordinary column"
    yield plan([*base, [*value(column(0))[:3], integer(2), *value(column(0))[4:]]]), "rejected", "cannot specify a default"
    yield plan([*base, value(column(0))], keys=[key([(3, False, "BINARY")], kind="primary", inline=True)]), "rejected", "cannot belong to the primary key"
    yield plan([*base, value(column(4))]), "rejected", "must belong to the statement source"
    live = arithmetic("+", column(0), ["parameter", "1"])
    yield plan([*base, value(live)]), "rejected", "cannot contain a parameter"
    yield {"sql": parts() + ", calc INTEGER AS (rowid))", "declarations": []}, "analysis-rejected", "Column rowid does not exist"


def structure(item):
    return {
        **{name: item[name] for name in ("operation", "schema", "table", "strict", "keys", "withoutRowid", "ifNotExists", "checks")},
        "columns": [field[:3] for field in item["columns"]],
        "generated": [[slot, *definition] for slot, definition in expressions(item).items()],
        "collations": ["BINARY"] * len(item["columns"]),
    }


def parameter_map(item):
    slots = {}
    for _, expression in expressions(item).values():
        if expression[0] == "folded-membership":
            for label in expression[2]:
                slots.setdefault(str(int(label)), "?" + label)
    return {"parameterCount": max(map(int, slots), default=0), "parameters": slots}


def key_kind(item, kind):
    return next((item for item in item["keys"] if item["kind"] == kind), None)


def vectors(item):
    if item["keys"]:
        return tuple((seq * 11, peer, seq) for _, peer, seq in ROWS)
    return ROWS


def rows_for(item, rows):
    definitions = expressions(item)

    def calculate(data):
        values = dict(enumerate(data))

        def fetch(slot):
            if slot not in values:
                value = evaluate(definitions[slot][1])
                affinity = {"TEXT": str, "INT": int, "INTEGER": int}.get(item["columns"][slot][1])
                values[slot] = affinity(value) if affinity is not None and value is not None else value
            return values[slot]

        def evaluate(expression):
            role = expression[0]
            if role == "column":
                return fetch(expression[1])
            if role == "integer":
                return int(expression[1])
            if role == "folded-membership":
                return int(expression[1])
            if role == "arithmetic":
                left, right = map(evaluate, expression[2:])
                if left is None or right is None:
                    return None
                return {"+": int(left) + int(right), "-": int(left) - int(right)}[expression[1]]
            raise ValueError(f"Unspecified independent expression {role}")

        return [typed(fetch(slot)) for slot in range(len(item["columns"]))]

    return [calculate(row) for row in rows]


def expected(item):
    definitions = expressions(item)
    columns = item["columns"]
    names = [field[0] for field in columns]
    pk = key_kind(item, "primary")
    stats = [[typed(value) for value in (slot, name, kind, int(not_null), None, int(pk is not None and slot == 0),
              0 if slot not in definitions else {"VIRTUAL": 2, "STORED": 3}[definitions[slot][0]])]
             for slot, (name, kind, not_null, *_) in enumerate(columns)]
    target = 1 if pk else 0
    base = vectors(item)
    changed = [tuple(value + 2 if slot == target and row[2] == 2 else value for slot, value in enumerate(row)) for row in base]
    final_names = names.copy()
    final_names[target] = "shifted"
    return {
        "create": {**CREATE, **parameter_map(item)}, "columns": stats, "names": names,
        "tableOptions": [[typed(value) for value in (item["schema"], TABLE, "table", len(columns), 0, int(item["strict"]))]],
        "updateChanges": 1, "insertChanges": len(base),
        "inserted": rows_for(item, base), "updated": rows_for(item, changed),
        "explicitWrite": {
            "insert": 'SQLite 1: cannot INSERT into generated column "calc"',
            "update": 'SQLite 1: cannot UPDATE generated column "calc"',
        },
        "constraintErrors": [
            {"code": 19, "message": f"NOT NULL constraint failed: {TABLE}.calc"},
            {"code": 19, "message": f"UNIQUE constraint failed: {TABLE}.calc"},
        ] if key_kind(item, "unique") else [],
        "renamedNames": final_names,
        "afterFailedDrop": rows_for(item, changed),
        "dropDependency": {"code": 1, "column": "shifted"},
    }
