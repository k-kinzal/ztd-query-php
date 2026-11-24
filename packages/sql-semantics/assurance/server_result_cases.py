"""Native result ordering with a relational oracle for possible tie selections."""

import itertools
import random
from collections import Counter

from evidence_transcript import canonical
from run import require
from server_cases import NAMES, SCHEMAS, evaluate
from server_join_cases import (
    case as projection, declarations, expected as projected, is_join, join, slot, table,
)

LAYOUTS = (
    [[slot(0), "key1"], [slot(1), "payload"]],
    [[slot(1), "payload"], [slot(0), "key2"]],
    [[slot(0), "k"], [slot(0), "k"], [slot(1), "payload"]],
)


def order(position, descending, first):
    return [position, descending, first]


def orders(engine):
    descending_first = engine == "postgres"
    return [[], [order(1, False, not descending_first)], [order(1, True, descending_first)],
            [order(1, True, descending_first), order(2, False, not descending_first)],
            *([[order(1, False, True)], [order(1, True, False)]] if engine == "postgres" else [])]


def bounds(engine):
    return [None, [0, 0], [3, 0], [2, 2], [2, 5], [2147483647, 0],
            *([[None, 2]] if engine == "postgres" else [])]


def case(engine, tag, source, columns, distinct, order_by, limit, where=None):
    return {**projection(engine, tag, source, columns, where),
            "distinct": distinct, "orderBy": order_by, "limit": limit}


def finite_cases(engine, tag):
    source = table(engine, "accounts")
    for columns, distinct, sort, limit in itertools.product(LAYOUTS, (False, True), orders(engine), bounds(engine)):
        yield case(engine, tag, source, columns, distinct, sort, limit)


def finite_id(engine, response, _):
    return (LAYOUTS.index(response["columns"]), int(response["distinct"]),
            orders(engine).index(response["orderBy"]), bounds(engine).index(response["limit"]))


def finite_ids(engine, specification, _tag):
    finite = specification["finite"]
    return set(itertools.product(range(len(finite["projections"])), range(len(finite["quantifiers"])),
                                 range(len(finite["orders"][engine])), range(len(finite["bounds"][engine]))))


def seeded(engine, tag, seed, count):
    rng = random.Random(seed)
    for _ in range(count):
        source = table(engine, "accounts", bool(rng.getrandbits(1)))
        if rng.getrandbits(1):
            source = join(rng.choice(("CROSS", "LEFT")), source, table(engine, rng.choice(("empty", "independent"))), None)
            if source["kind"] == "LEFT":
                source["on"] = ["comparison", "=", slot(0), slot(0, 1)]
        width = rng.randrange(2, 5)
        fields = []
        for _ in range(width):
            choices = [slot(0), slot(1), ["boolean", True], ["null"]]
            if is_join(source):
                choices += [slot(0, 1), slot(1, 1)]
            fields.append([rng.choice(choices), rng.choice(("k", "k", "payload"))])
        keys = []
        for _ in range(rng.randrange(4)):
            desc = bool(rng.getrandbits(1))
            first = bool(rng.getrandbits(1)) if engine == "postgres" else not desc
            keys.append(order(rng.randint(1, width), desc, first))
        bound = rng.choice([None, [rng.randrange(8), rng.randrange(7)], [1, 1], [2, 0]])
        yield case(engine, tag, source, fields, bool(rng.getrandbits(1)), keys, bound)


def analyzed(engine, tag):
    schema, key = SCHEMAS[engine], NAMES[engine][0]
    query = f"SELECT DISTINCT {key} AS k, delta AS payload FROM {schema}.ir_join_accounts"
    source = table(engine, "accounts")
    columns = [[slot(0), "k"], [slot(1), "payload"]]
    if engine == "mysql":
        examples = [
            (query + " ORDER BY 1 DESC, 2 LIMIT 1, 3", [order(1, True, False), order(2, False, True)], [3, 1]),
            (query + " ORDER BY 2 ASC LIMIT 4 OFFSET 2", [order(2, False, True)], [4, 2]),
        ]
    else:
        examples = [
            (query + " ORDER BY 1 DESC NULLS LAST, 2 LIMIT 3 OFFSET 1", [order(1, True, False), order(2, False, False)], [3, 1]),
            (query + " ORDER BY 2 LIMIT ALL OFFSET 2", [order(2, False, False)], [None, 2]),
            (query + " ORDER BY 1 LIMIT ALL", [order(1, False, False)], None),
        ]
    for sql, sorting, window in examples:
        yield {"dialect": engine, "release": tag, "sql": sql, "declarations": declarations(engine)}, case(
            engine, tag, source, columns, True, sorting, window)
    if engine == "postgres":
        sorting = [order(["operation", "+", ["integer", "1"], ["integer", "2"]], False, False)]
        yield {"dialect": engine, "release": tag, "sql": "SELECT 1 AS answer ORDER BY 1+2", "declarations": []}, case(
            engine, tag, None, [[["integer", "1"], "answer"]], False, sorting, None)


def negatives(engine, tag):
    healthy = case(engine, tag, None, [[["integer", "1"], "answer"]], False, [], None)
    yield {**healthy, "orderBy": [[0, False, True]]}, "order position must be positive"
    yield {**healthy, "orderBy": [[2, False, True]]}, "order position must belong"
    if engine == "postgres":
        yield {"dialect": engine, "release": tag, "declarations": [], "sql": "SELECT 1 AS answer ORDER BY 1 USING >"}, "requires a supported default sort operator"
    else:
        yield {"dialect": engine, "release": tag, "declarations": [], "sql": "SELECT 1 AS answer ORDER BY 1+2"}, "requires a supported output position"
    yield {**healthy, "limit": [-1, 0]}, "row count must be between"
    yield {**healthy, "limit": [2, 2147483648]}, "row offset must be between"
    list_error = "owned list of sort keys" if engine == "postgres" else "owned list of output keys"
    yield {**healthy, "orderBy": [None]}, list_error


def key(row, sorting):
    values = []
    for position, descending, nulls_first in sorting:
        value = row[position - 1] if type(position) is int else evaluate(position, (), "postgres")
        if value is None:
            values.append((0 if nulls_first else 1, 0))
        else:
            values.append((1 if nulls_first else 0, -value if descending else value))
    return tuple(values)


def expected(item):
    base = projected(item)
    rows = base["rows"]
    if item["distinct"]:
        rows = list({canonical(row): row for row in rows}.values())
    return result_window(base["fields"], rows, item["orderBy"], item["limit"])


def result_window(fields, rows, sorting, bounds, ordering_key=key):
    ordered = sorted(rows, key=lambda row: ordering_key(row, sorting))
    upper, offset = bounds or (None, 0)
    start, stop = offset, offset + upper if upper is not None else len(ordered)
    bands = []
    index = 0
    for _, tied in itertools.groupby(ordered, key=lambda row: ordering_key(row, sorting)):
        members = list(tied)
        end = index + len(members)
        selected = max(0, min(end, stop) - max(index, start))
        bands.append({"multiplicity": selected, "members": members})
        index = end
    return {"fields": fields, "total": sum(band["multiplicity"] for band in bands), "bands": bands}


def match(prediction, actual, sql):
    detail = {"sql": sql, "expected": prediction, "observed": actual}
    require(actual["status"] == "rows", "server-result-native-rejection", detail)
    require(canonical(actual["fields"]) == canonical(prediction["fields"]) and len(actual["rows"]) == prediction["total"],
            "meaning-mismatch", detail)
    offset = 0
    for band in prediction["bands"]:
        stop = offset + band["multiplicity"]
        observed = Counter(canonical(row) for row in actual["rows"][offset:stop])
        eligible = Counter(canonical(row) for row in band["members"])
        require(not (observed - eligible), "meaning-mismatch", detail)
        offset = stop


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
