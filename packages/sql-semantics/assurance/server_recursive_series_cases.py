"""Generate signed recursive queries and independently compute their rows."""

from itertools import product
from random import Random

from server_cases import SCHEMAS, observation
from server_delete_cases import literal
from server_join_cases import declarations

STARTS = (-2, 0, 2)
INCREMENTS = (-2, -1, 1, 2)
BOUNDARIES = (-3, 0, 3)


def case(engine, tag, start, increment, boundary, all_rows=True, width=1):
    return {
        "dialect": engine, "release": tag,
        "series": {
            "start": literal(start), "increment": literal(increment), "boundary": literal(boundary),
            "comparison": "<" if increment > 0 else ">", "all": all_rows,
            "columns": [[0, "value" if index == 0 else f"value_{index}"] for index in range(width)],
        },
    }


def finite_cases(engine, tag):
    for start, increment, boundary, all_rows in product(STARTS, INCREMENTS, BOUNDARIES, (False, True)):
        yield case(engine, tag, start, increment, boundary, all_rows)


def finite_id(_engine, observed, _item):
    value = observed["series"]
    roles = tuple(int(value[name][1]) for name in ("start", "increment", "boundary"))
    return (*roles, value["all"], value["comparison"], tuple(tuple(field) for field in value["columns"]))


def finite_ids(_engine, specification, _tag):
    finite = specification["finite"]
    return {
        (start, step, threshold, quantifier == "UNION ALL", "<" if step > 0 else ">", ((0, "value"),))
        for start, step, threshold, quantifier in product(finite["starts"], finite["increments"], finite["boundaries"], finite["quantifiers"])
    }


def seeded(engine, tag, seed, count):
    rng = Random(seed)
    for _ in range(count):
        yield case(engine, tag, rng.randrange(-30, 31), rng.choice((-11, -5, -3, -1, 1, 3, 5, 11)),
                   rng.randrange(-50, 51), bool(rng.getrandbits(1)), rng.randrange(1, 7))


def analyzed(engine, tag):
    cte = "ir_join_accounts"
    original = f"WITH RECURSIVE {cte}(n) AS (SELECT -3 UNION ALL SELECT t.n + 2 FROM {cte} t WHERE n < 5)"
    yield {"dialect": engine, "release": tag, "declarations": declarations(engine),
           "sql": f"{original} SELECT q.n AS value, n AS value_1 FROM {cte} q"}, case(engine, tag, -3, 2, 5, True, 2)
    original = "WITH RECURSIVE walk(n) AS (SELECT 5 UNION SELECT (n + -2) FROM walk WHERE n > -3) SELECT n AS value FROM walk"
    yield {"dialect": engine, "release": tag, "declarations": [], "sql": original}, case(engine, tag, 5, -2, -3, False)


def negative(engine, tag):
    yield case(engine, tag, 0, 0, 5), "requires a nonzero increment"
    for operation, signal in ((">", "advance toward its boundary"), ("<=", "requires a strict bound")):
        item = case(engine, tag, 0, 1, 5)
        item["series"]["comparison"] = operation
        yield item, signal
    empty = case(engine, tag, 0, 1, 5, width=0)
    yield empty, "requires a nonempty result"
    absent = case(engine, tag, 0, 1, 5)
    absent["series"]["columns"][0][0] = 1
    yield absent, "must read the single working column"
    physical = SCHEMAS[engine] + ".ir_recursive_external"
    yield {"dialect": engine, "release": tag, "declarations": [f"CREATE TABLE {physical} (n INTEGER)"],
           "sql": f"WITH RECURSIVE walk(n) AS (SELECT 1 UNION ALL SELECT n + 1 FROM {physical} WHERE n < 4) SELECT n AS value FROM walk"},         "resolve to its own one-column definition"


def requests(engine, tag, specification):
    for index, item in enumerate(finite_cases(engine, tag)):
        yield f"finite/{index}", item, item, None
    for seed in specification["exploration"]["seeds"]:
        for index, item in enumerate(seeded(engine, tag, seed, specification["exploration"]["casesPerSeed"])):
            yield f"seed/{seed}/{index}", item, item, None
    for index, (request, item) in enumerate(analyzed(engine, tag)):
        yield f"analyzed/{index}", request, item, None
    for index, (start, increment, boundary) in enumerate(((-2147483648, 2, -2147483644), (2147483647, -2, 2147483643), (0, 3, 0))):
        for all_rows in (False, True):
            item = case(engine, tag, start, increment, boundary, all_rows)
            yield f"boundary/{index}/{all_rows}", item, item, None
    for index, (item, signal) in enumerate(negative(engine, tag)):
        yield f"negative/{index}", item, item, signal


def expected(item):
    value = item["series"]
    start, increment, boundary = [int(value[name][1]) for name in ("start", "increment", "boundary")]
    distance = max(0, boundary - start if value["comparison"] == "<" else start - boundary)
    stride = abs(increment)
    iterations = (distance + stride - 1) // stride
    metadata = "int4" if item["dialect"] == "postgres" else "LONGLONG"
    columns = value["columns"]
    return observation({
        "status": "rows",
        "fields": [[name, metadata] for _, name in columns],
        "rows": [[start + i * increment] * len(columns) for i in range(iterations + 1)],
    })
