"""Input and count-filter cases independent of SQLite IR construction."""

from itertools import product
from random import Random

from sqlite_grouped_cases import COLLATIONS, SCHEMA, binding_names, case, count, slot, source
from sqlite_result_cases import integer

FILTERS = (None, ["comparison", "IS NOT", slot(1), ["null"]], ["comparison", "=", slot(0), integer(1)])
THRESHOLDS = (integer(1), ["null"])


def grouped(relation, keys, columns, where, having):
    item = {**case(relation, keys, columns, where), "having": having}
    item["bindings"] = dict.fromkeys(binding_names(item), 1)
    return item


def finite_cases(domain):
    for relation, keyed, where, operator, threshold, first in product(
            domain["relations"], (False, True), FILTERS, domain["operators"], THRESHOLDS, (False, True)):
        keys = [[slot(0), "NOCASE"]] if keyed else []
        columns = ([[slot(0), "key"]] if keyed else []) + [[count(), "n"]]
        measure = count(slot(1)) if keyed else count()
        yield grouped(source(relation), keys, columns, where, [measure, operator, threshold, first])


def finite_id(reply):
    _, operator, threshold, first = reply["having"]
    return (reply["source"][1].removeprefix("ir_group_"), bool(reply["keys"]),
            FILTERS.index(reply["where"]), operator, THRESHOLDS.index(threshold), first)


def finite_ids(domain):
    return set(product(domain["relations"], (False, True), range(3), domain["operators"], range(2), (False, True)))


def seeded(domain, seed, repetitions):
    rng = Random(seed)

    def filter_term(atoms, depth):
        choice = rng.randrange(5) if depth else 0
        if choice == 1:
            return ["logical", rng.choice(("AND", "OR")), filter_term(atoms, depth - 1), filter_term(atoms, depth - 1)]
        if choice == 2:
            return ["comparison", rng.choice(domain["operators"]), rng.choice(atoms), integer(rng.randrange(-2, 4))]
        if choice == 3:
            return ["case", None, [[filter_term(atoms, depth - 1), rng.choice(atoms)]], integer(0)]
        if choice == 4:
            return ["cast", "INTEGER", rng.choice(atoms)]
        return rng.choice(atoms)

    for _ in range(repetitions):
        role = rng.choice((*domain["relations"], "unit"))
        no_input = role == "unit"
        keys = [] if no_input else [[slot(rng.randrange(2)), rng.choice(COLLATIONS)] for _ in range(rng.randrange(5))]
        atoms = [integer(-1), integer(0), integer(2), ["null"], ["text", "2u"], ["parameter", "02"], ["parameter", "2"]]
        counts = [count()] if no_input else [
            count(), count(slot(0)), count(slot(1)), *[count(slot(rng.randrange(2)), collation) for collation in COLLATIONS]
        ]
        fields = counts + [key[0] for key in keys]
        columns = [[rng.choice(fields), rng.choice(("n", "key", f"v{index}"))] for index in range(rng.randrange(1, 6))]
        where = None if rng.randrange(5) == 0 else filter_term(atoms + ([] if no_input else [slot(0), slot(1)]), 3)
        threshold = rng.choice(THRESHOLDS + (integer(0), integer(2), ["parameter", "2"], ["parameter", "02"], ["parameter", "8"]))
        having = None if rng.randrange(8) == 0 else [rng.choice(counts), rng.choice(domain["operators"]), threshold, bool(rng.getrandbits(1))]
        item = grouped(None if no_input else source(role), keys, columns, where, having)
        item["bindings"] = {number: rng.choice((None, 0, 2, -3, 2.5, "2", "bad")) for number in item["bindings"]}
        yield item


def boundaries():
    columns = [[count(), "n"]]
    yield grouped(None, [], columns, integer(0), [count(), "=", integer(0), False])
    yield grouped(source("empty"), [[slot(0), "NOCASE"]], columns, integer(-1), [count(), "=", integer(0), False])
    yield grouped(source("bag"), [], columns, ["parameter", "02"], [count(), "IS NOT", ["parameter", "2"], True])
    yield grouped(None, [], columns, nested_filter(32), [count(), "=", integer(1), False])


def analyzed():
    columns = [[count(), "n"]]
    yield ("SELECT count(*) AS n HAVING count(*) IS NOT NULL", [],
           grouped(None, [], columns, None, [count(), "IS NOT", ["null"], False]))
    yield ("SELECT b AS k,count(*) AS n FROM main.ir_group_bag WHERE a = ?02 GROUP BY b "
           "HAVING ?2 <= count(DISTINCT (a COLLATE BINARY))", list(SCHEMA),
           grouped(source("bag", ("b", "a")), [[slot(0), "RTRIM"]], [[slot(0), "k"], *columns],
                   ["comparison", "=", slot(1), ["parameter", "02"]],
                   [count(slot(1), "BINARY"), "<=", ["parameter", "2"], True]))
    yield ("SELECT count(*) AS n FROM main.ir_group_bag WHERE CASE WHEN b IS NULL THEN 1 ELSE 0 END HAVING count(a) > 0", list(SCHEMA),
           grouped(source("bag", ("b", "a")), [], columns,
                   ["case", None, [[["comparison", "IS", slot(0), ["null"]], integer(1)]], integer(0)],
                   [count(slot(1)), ">", integer(0), False]))
    yield ("SELECT a AS k FROM main.ir_group_bag GROUP BY a HAVING count(b) IS ?3", list(SCHEMA),
           grouped(source("bag"), [[slot(0), "NOCASE"]], [[slot(0), "k"]], None,
                   [count(slot(1)), "IS", ["parameter", "3"], False]))


def negatives():
    columns = [[count(), "n"]]
    yield grouped(source("bag"), [], columns, slot(2), None), "Column slot must belong to the statement source"
    yield {**case(source("bag"), [], columns, ["column", 0, 1]), "having": None}, "Column slot must belong to the statement source"
    yield grouped(source("bag"), [], columns, None, [count(slot(2)), ">", integer(0), False]), "HAVING count slot must belong to the group input"
    for operand in ("count(*) + 1", "count(*) > count(*)"):
        yield {"sql": f"SELECT count(*) AS n HAVING {operand}", "declarations": []}, "HAVING requires a comparison of one COUNT"
    yield grouped(None, [], columns, nested_filter(33), None), "Expression height or expanded node count"


def nested_filter(depth):
    predicate = integer(1)
    for _ in range(depth):
        predicate = ["logical", "AND", integer(1), predicate]
    return predicate
