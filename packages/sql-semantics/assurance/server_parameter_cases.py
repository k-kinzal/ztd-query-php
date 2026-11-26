"""Independently look up bound native positions and compare typed projections."""

from itertools import product
import json
from random import Random

from run import TOOL, digest, require
from server_delete_cases import literal

LAYOUTS = {"mysql": ((1,), (1, 2)), "postgres": ((1,), (1, 1), (1, 2), (2, 1), (2, 1, 2))}
VALUES = (-2147483648, 0, 9, 2147483647, None)


def reconcile_catalog_rejections(specification):
    contract = TOOL / "native-context-diagnostics-v2.json"
    require(digest(contract) == "32a4db085bd1458c05d3e1b2de54c248a645702a59081aa9b9b297f2c0176044",
            "native-context-diagnostics-contract")
    schedule = json.loads(contract.read_text())["postgres"][specification["revision"]]
    key = "additionalPostgreSqlNegatives"
    rejections = [*specification.get(key, ()), *schedule["added"]]
    require(len(specification["negative"]) + len(rejections) == schedule["totalRejections"],
            "native-context-diagnostics-rejection-balance", schedule)
    return {**specification, key: rejections}


def ref(position):
    return ["parameter", position]


def case(engine, tag, expressions, bindings, labels=None):
    if labels is None:
        labels = [f"a{i}" for i in range(len(expressions))]
    return {"dialect": engine, "release": tag, "source": None, "where": None, "columns": [[expr, label] for expr, label in zip(expressions, labels)],
            "bindings": list(bindings)}


def finite_cases(engine, tag):
    for positions in LAYOUTS[engine]:
        for bindings in product(VALUES, repeat=max(positions)):
            yield case(engine, tag, [ref(position) for position in positions], bindings)


def finite_id(_, response, item):
    return tuple(expression[1] for expression, _ in response["columns"]), tuple(json.dumps(value) for value in item["bindings"])


def finite_ids(engine, specification, _tag):
    finite = specification["finite"]
    layouts = finite["layouts"][engine]
    return {(tuple(layout), tuple(json.dumps(value) for value in bindings))
            for layout in layouts for bindings in product(finite["bindings"], repeat=max(layout))}


def seeded(engine, tag, seed, count):
    rng = Random(seed)
    for _ in range(count):
        width = rng.randint(1, 6)
        positions = list(range(1, width + 1))
        if engine == "postgres":
            positions += rng.choices(positions, k=rng.randint(0, 3))
            rng.shuffle(positions)
        atoms = [ref(position) for position in positions]
        for _ in range(rng.randint(0, 3)):
            atoms.insert(rng.randrange(len(atoms) + 1), rng.choice((["null"], literal(rng.randrange(-20, 21)))))
        values = [rng.choice(VALUES + (rng.randrange(-100, 101),)) for _ in range(width)]
        yield case(engine, tag, atoms, values)


def analyzed(engine, tag):
    if engine == "mysql":
        sql = "SELECT CAST(? AS SIGNED) AS a0, CAST((?) AS SIGNED INTEGER) AS a1"
        indices = [1, 2]
    else:
        sql = "SELECT ($2)::pg_catalog.int4 AS a0, CAST($1 AS integer) AS a1, CAST($2 AS pg_catalog.int4) AS a2"
        indices = [2, 1, 2]
    for values in ((17, -3), (None, 9)):
        yield {"dialect": engine, "release": tag, "sql": sql, "declarations": []}, case(engine, tag, [ref(i) for i in indices], values)


def negatives(engine, tag, specification):
    for position in (0, 1025):
        yield case(engine, tag, [ref(position)], [0]), "between 1 and 1024"
    signal = "lexical projection order" if engine == "mysql" else "contiguous bound slots"
    yield case(engine, tag, [ref(2)], [0, 1]), signal
    boundary = specification["invalidCastBoundary"][engine]
    yield {"dialect": engine, "release": tag, "sql": boundary["sql"], "declarations": []}, {
        "exception": boundary["exception"], "message": boundary["message"],
    }
    if engine == "postgres":
        boundary = specification["unresolvedPostgreSqlCast"]
        request = {"dialect": engine, "release": tag, "sql": boundary["sql"]}
        failure = {"exception": boundary["exception"], "message": boundary["message"]}
        yield {**request, "declarations": [], "complete": False}, failure
        yield {**request, "declarations": ["CREATE TEMP TABLE text (x integer)"]}, failure
    if engine == "mysql":
        yield case(engine, tag, [ref(2), ref(1)], [0, 1]), signal
        yield case(engine, tag, [ref(1), ref(1)], [0]), signal


def requests(engine, tag, specification):
    for i, item in enumerate(finite_cases(engine, tag)):
        yield f"finite/{i}", item, item, None
    for seed in specification["exploration"]["seeds"]:
        for i, item in enumerate(seeded(engine, tag, seed, specification["exploration"]["casesPerSeed"])):
            yield f"seed/{seed}/{i}", item, item, None
    for i, (request, item) in enumerate(analyzed(engine, tag)):
        yield f"analyzed/{i}", request, item, None
    for i, (item, signal) in enumerate(negatives(engine, tag, specification)):
        yield f"negative/{i}", item, item, signal


def expected(item):
    mysql = item["dialect"] == "mysql"
    kind = "LONGLONG" if mysql else "int4"
    fields, row = [], []
    for expression, label in item["columns"]:
        if expression[0] == "parameter":
            value = item["bindings"][expression[1] - 1]
        elif expression[0] == "integer":
            value = int(expression[1])
        else:
            assert expression[0] == "null"
            value = None
        field = ("NULL" if mysql else "text") if expression[0] == "null" else kind
        fields.append([label, field])
        row.append(value)
    return {"status": "rows", "fields": fields, "rows": [row], "bindings": item["bindings"]}


def observation(raw):
    return raw
