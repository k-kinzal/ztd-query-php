"""Check signed group comparisons against independent HAVING and native row observations."""

from itertools import product
import json
import random
import sys

import run_server_grouped as groups
from run import TOOL, Failure, digest, require
from run_server import rejects, same
from run_server_join import OBSERVER, main, probe
from run_server_postgres_grouped_result_order import DIAGNOSTIC, SEMANTIC, TYPES, ordered
from server_delete_cases import literal
from server_grouped_cases import aggregate, expected, field_value
from server_join_cases import NAMES_BY_TABLE, compare, declarations, slot, table
from server_result_cases import match, result_window
from server_truth_model import admits
from sqlite_domain import RenderFault

LABEL = "postgres-grouped-having"
CONTRACT_NAME = "postgres-grouped-having-v1.json"
CONTRACT = "4993af9c2d074291e62f9a17fcfdcf403e5294a1e813b7948229ff181932f012"
COMPLETE_SEEDS = True
AXES = ("source", "aggregate", "operator", "operands")
OPS = ("=", "<>", "<", "<=", ">", ">=")
AGGREGATES = ("count", "sum", "min", "max")
TABLE = "public." + NAMES_BY_TABLE["accounts"]
EMPTY = "public." + NAMES_BY_TABLE["empty"]


def specifications():
    path = TOOL / CONTRACT_NAME
    require(digest(path) == CONTRACT, "postgres-grouped-having-contract-mismatch")
    return json.loads(path.read_text()), {"postgres": groups.specifications()[1]["postgres"]}


def query(tag, source, keys, fields, having, *, where=None, order=(), limit=None):
    item = ordered(tag, source, keys, fields, limit, where=where, sort=order)
    item["grouped"]["having"] = having
    return item


def finite(tag, role, operation, symbol, operands):
    term = aggregate(operation, slot(1) if operation != "count" else None)
    left, right = {"aggregate-literal": (term, literal(1)), "literal-aggregate": (literal(1), term),
                   "key-aggregate": (slot(0), term), "aggregate-key": (term, slot(0))}[operands]
    return query(tag, table("postgres", role), [slot(0)], [[slot(0), "k"], [term, "a"]], compare(symbol, left, right))


def finite_ids(_, contract, _tag):
    scope = contract["finite"]
    ids = set(product(*(scope[axis] for axis in AXES)))
    require(len(ids) == scope["identities"], "postgres-grouped-having-finite-size")
    return ids


def finite_id(_, reply, _item):
    model = reply["grouped"]
    _, symbol, left, right = model["having"]
    roles = {"slot": "key", "integer": "literal", "aggregate": "aggregate"}
    relation = next(role for role, name in NAMES_BY_TABLE.items() if name == model["source"]["table"])
    operation = next(term[1] for term in (left, right) if term[0] == "aggregate")
    return relation, operation, symbol, roles[left[0]] + "-" + roles[right[0]]


def request(tag, sql):
    return {"dialect": "postgres", "release": tag, "sql": sql, "declarations": declarations("postgres")}


def analyzed(tag):
    physical = table("postgres", "accounts")
    key_only = table("postgres", "accounts", columns=["id"])
    count = aggregate("count")
    summed = aggregate("sum", slot(1))
    yield f"SELECT id AS k FROM {TABLE} GROUP BY id HAVING pg_catalog.count(*) > 1", query(
        tag, key_only, [slot(0)], [[slot(0), "k"]], compare(">", count, literal(1)))
    yield f"SELECT id AS k, sum(delta) AS s FROM {TABLE} GROUP BY id HAVING id < sum(delta)", query(
        tag, physical, [slot(0)], [[slot(0), "k"], [summed, "s"]], compare("<", slot(0), summed))
    yield (f"SELECT id AS k, sum(delta) AS s FROM {TABLE} WHERE delta > 0 GROUP BY id HAVING sum(delta) >= 3 ORDER BY k LIMIT 1",
           query(tag, physical, [slot(0)], [[slot(0), "k"], [summed, "s"]], compare(">=", summed, literal(3)),
                 where=compare(">", slot(1), literal(0)), order=[[1, False, False]], limit=[1, 0]))
    yield f"SELECT count(*) AS n FROM {EMPTY} HAVING count(*) = 0", query(
        tag, table("postgres", "empty", columns=[]), [], [[count, "n"]], compare("=", count, literal(0)))
    empty_sum = aggregate("sum", slot(0))
    yield f"SELECT sum(delta) AS s FROM {EMPTY} HAVING sum(delta) > -1", query(
        tag, table("postgres", "empty", columns=["delta"]), [], [[empty_sum, "s"]], compare(">", empty_sum, literal(-1)))
    yield f"SELECT count(*) AS n FROM {TABLE} HAVING NULL", query(
        tag, table("postgres", "accounts", columns=[]), [], [[count, "n"]], ["null"])
    yield f"SELECT count(*) AS n FROM {EMPTY} HAVING TRUE", query(
        tag, table("postgres", "empty", columns=[]), [], [[count, "n"]], ["boolean", True])
    yield f"SELECT count(*) AS n FROM {TABLE} HAVING 2147483651 > pg_catalog.count(*)", query(
        tag, table("postgres", "accounts", columns=[]), [], [[count, "n"]], compare(">", ["wide-integer", "2147483651"], count))
    yield (f"SELECT id AS k, count(*) AS n FROM {TABLE} GROUP BY id HAVING pg_catalog.count(DISTINCT delta) OPERATOR(pg_catalog.>) 1",
           query(tag, physical, [slot(0)], [[slot(0), "k"], [count, "n"]], compare(">", aggregate("count", slot(1), True), literal(1))))


def native_negatives():
    return (
        (f"SELECT id AS k FROM {TABLE} GROUP BY id HAVING delta > 0", DIAGNOSTIC, "A plain grouped result must read a grouping key.", "42803"),
        (f"SELECT id AS output_k FROM {TABLE} GROUP BY id HAVING output_k > 0", SEMANTIC, "output_k", "42703"),
    )


def prepare(engine, db):
    return {"grouping": groups.prepare(engine, db), "errors": [db.request({"sql": sql}) for sql, *_ in native_negatives()]}


def check_preparation(engine, environment, response):
    groups.check_preparation(engine, environment, response["grouping"])
    same([{"status": "db-error", "state": state} for _, _, _, state in native_negatives()], response["errors"])
    require(environment["database"][-1] == "pg_catalog, public", "postgres-grouped-having-native-profile")


def negatives(tag):
    source = table("postgres", "accounts")
    for bad, message in (
        (slot(1), "A plain grouped result must read a grouping key."),
        (slot(0, 1), "A plain grouped result must read a grouping key."),
        (aggregate("sum", slot(2)), "The aggregate slot must belong to the group input."),
    ):
        yield query(tag, source, [slot(0)], [[slot(0), "k"]], compare(">", bad, literal(1))), {
            "exception": DIAGNOSTIC, "message": message}
    for sql, reason, message, _ in native_negatives():
        yield request(tag, sql), {"exception": reason, "message": message}
    yield request(tag, f"SELECT count(*) AS n FROM {TABLE} HAVING count(*) + 1 > 3"), {
        "exception": DIAGNOSTIC, "message": "A HAVING comparison needs signed group values or signed literals."}


def seeded(tag, seed, amount):
    rng = random.Random(seed)
    for index in range(amount):
        source = table("postgres", rng.choice(("accounts", "empty", "independent")), bool(rng.getrandbits(1)))
        keys = rng.sample([slot(0), slot(1)], index % 3)

        def sum_or_count():
            operator = rng.choice(AGGREGATES)
            argument = None if operator == "count" and rng.randrange(2) == 0 else slot(rng.randrange(2))
            return aggregate(operator, argument, argument is not None and bool(rng.getrandbits(1)))

        threshold = rng.choice((literal(rng.randrange(-3, 6)), ["wide-integer", rng.choice(("2147483649", "-2147483649"))]))
        if index % 8 == 0:
            having = ["null"]
        elif index % 8 == 1:
            having = ["boolean", bool(rng.getrandbits(1))]
        else:
            choices = [threshold, sum_or_count(), *keys]
            other = sum_or_count() if index % 8 == 2 else rng.choice(choices)
            operands = [sum_or_count(), other]
            rng.shuffle(operands)
            having = compare(rng.choice(OPS), *operands)
        fields = [[term, f"key_{pos}"] for pos, term in enumerate(keys)]
        fields += [[sum_or_count(), rng.choice(("amount", "again", '値 " x'))] for _ in range(1 + rng.randrange(4))]
        rng.shuffle(fields)
        positions = rng.sample(list(range(1, 1 + len(fields))), rng.randrange(1 + len(fields)))
        order = [[position, bool(rng.getrandbits(1)), bool(rng.getrandbits(1))] for position in positions]
        where = rng.choice((None, ["boolean", False], ["null-test", True, slot(0)],
                            ["logic", "AND", ["null-test", True, slot(0)], compare("<", slot(1), literal(10))]))
        yield query(tag, source, keys, fields, having, where=where, order=order, limit=rng.choice((None, [None, 1], [2, 0])))


def requests(_, tag, contract):
    scope = contract["finite"]
    for index, coordinates in enumerate(product(*(scope[key] for key in AXES))):
        item = finite(tag, *coordinates)
        yield f"finite/{index}", item, item, None
    inputs = list(analyzed(tag))
    require(len(inputs) == len(contract["analysis"]), "postgres-grouped-having-analysis-count")
    for index, (sql, item) in enumerate(inputs):
        yield f"analyze/{index}", request(tag, sql), item, None
    for seed in contract["exploration"]["seeds"]:
        for index, item in enumerate(seeded(tag, seed, contract["exploration"]["casesPerSeed"])):
            yield f"seed/{seed}/{index}", item, item, None
    for index, (item, reason) in enumerate(negatives(tag)):
        yield f"negative/{index}", item, None, reason


def prediction(item):
    group = item["grouped"]

    def passes(rows):
        def evaluate(term):
            return int(term[1]) if term[0] in ("integer", "wide-integer") else field_value(term, rows, "postgres")

        return admits(group["having"], evaluate)

    relation = expected(item, passes)
    fields = [[name, native, TYPES[native]] for name, native in relation["fields"]]
    return result_window(fields, relation["rows"], group["orderBy"], group["limit"])


def observer_request(_, sql):
    return {"sql": sql, "observePostgresTypeIds": True}


def observation(raw):
    return raw


def check_observation(item, actual, sql):
    match(prediction(item), actual, sql)


def check_value(item, reply):
    groups.check_value(item, reply)


def required_forms(*_):
    return {"global", "keyed", "both-aggregates", "null", "boolean", "comparison", "slot", "integer", "wide-integer",
            "distinct", "nested-where", "no-where", "order", "bounds", "empty-source", *AGGREGATES}


def sample_forms(forms, reply, *_):
    query_ = reply["grouped"]
    forms["global" if not query_["keys"] else "keyed"] += 1
    forms[query_["having"][0]] += 1
    if query_["having"][0] == "comparison":
        operands = query_["having"][2:]
        if all(term[0] == "aggregate" for term in operands):
            forms["both-aggregates"] += 1
        for term in operands:
            if term[0] == "aggregate":
                forms[term[1]] += 1
                if term[3]:
                    forms["distinct"] += 1
            else:
                forms[term[0]] += 1
    if query_["where"] is None:
        forms["no-where"] += 1
    elif query_["where"][0] == "logic":
        forms["nested-where"] += 1
    if query_["orderBy"]:
        forms["order"] += 1
    if query_["limit"]:
        forms["bounds"] += 1
    if query_["source"]["table"] == NAMES_BY_TABLE["empty"]:
        forms["empty-source"] += 1


def controls():
    item = finite("pg-17.2", "accounts", "count", ">", "aggregate-literal")
    outcome = prediction(item)
    raw = {"status": "rows", "fields": outcome["fields"], "rows": [row for band in outcome["bands"] for row in band["members"]]}
    check_observation(item, raw, "group predicate control")
    cases = {
        "erase-admitted-group": {**raw, "rows": raw["rows"][1:]},
        "change-count-width": {**raw, "fields": [raw["fields"][0], ["a", "int4", 23]]},
        "wrong-group-value": {**raw, "rows": [[raw["rows"][0][0], -15], *raw["rows"][1:]]},
    }
    evidence = {}
    for name, actual in cases.items():
        try:
            check_observation(item, actual, name)
        except Failure as error:
            require(error.signal == "meaning-mismatch", "postgres-grouped-having-control-signal", name)
            evidence[name] = {"input": item, **error.detail}
        else:
            raise Failure("postgres-grouped-having-control-accepted", name)
    return evidence


def boundary(item, reply):
    if rejects(reply, item["boundary"]):
        return
    require(reply["status"] == "constructed", "postgres-grouped-having-mutant-response", reply)
    raise Failure("postgres-grouped-having-published-invalid", {"sql": reply["sql"], "expected": item["boundary"], "observed": reply})


def fault_probe(item, reply, db):
    if "boundary" in item:
        boundary(item, reply)
    else:
        probe(item, reply, db, sys.modules[__name__])


def replay_fault(rule, record):
    witness = record["detail"]
    try:
        if "boundary" in rule.item:
            boundary(rule.item, record["healthyResponse"])
            boundary(rule.item, record["mutantResponse"])
        else:
            check_value(rule.item, record["healthyResponse"])
            check_value(rule.item, record["mutantResponse"])
            same(prediction(rule.item), witness["expected"])
            check_observation(rule.item, witness["observed"], witness["sql"])
    except Failure as error:
        same(rule.signal, error.signal)
        same(witness, error.detail)
        return
    raise Failure("postgres-grouped-having-undetected-fault", rule.name)


def faults(_, tag):
    root = "sql-semantics-postgres/src/Ir/Aggregation/"
    renderer = root + "GroupedSql.php"
    filtered = finite(tag, "accounts", "count", ">", "aggregate-literal")
    distinct = query(tag, table("postgres", "accounts"), [slot(0)], [[slot(0), "k"]],
                     compare(">", aggregate("count", slot(1), True), literal(2)))
    invalid, reason = next(negatives(tag))
    return [
        RenderFault("drop-having-group-test", renderer,
                    "$sql .= ' HAVING (' . $left . ' OPERATOR(pg_catalog.' . $query->having->operator->value . ') ' . $right . ')';",
                    "", filtered, "meaning-mismatch"),
        RenderFault("swap-having-comparison-operands", renderer,
                    "$left . ' OPERATOR(pg_catalog.' . $query->having->operator->value . ') ' . $right",
                    "$right . ' OPERATOR(pg_catalog.' . $query->having->operator->value . ') ' . $left", filtered, "meaning-mismatch"),
        RenderFault("change-having-signed-operator", renderer, "$query->having->operator->value", "'='", filtered, "meaning-mismatch"),
        RenderFault("bypass-having-field-membership", root + "GroupedSelect.php", "isset($members[$value->slot])", "true",
                    {"boundary": reason}, "postgres-grouped-having-published-invalid", invalid),
        RenderFault("omit-having-aggregate-distinct", renderer, "($value->distinct ? 'DISTINCT ' : '')", "''", distinct, "meaning-mismatch"),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])
