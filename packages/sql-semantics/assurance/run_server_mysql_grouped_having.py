"""Compare numeric MySQL HAVING with an independent grouped row evaluator."""

from collections import Counter
from itertools import product
import json
import operator
from random import Random
import sys

import mysql_group_name_cases as names
from run import TOOL, Failure, digest, require
from run_server import rejects, same
import run_server_grouped as grouped_domain
from run_server_join import OBSERVER, main, probe
from run_server_mysql_external_views import DIAGNOSTICS, COLLATION
from mysql_grouped_having_sql import analyzed_shape, command
from server_grouped_cases import aggregate, case, field_type, field_value, ordered
from server_join_cases import compare, slot, truth
from evidence_transcript import canonical
from sqlite_domain import RenderFault

LABEL = "mysql-grouped-having"
CONTRACT_NAME = "mysql-grouped-having-v4.json"
CONTRACT = "0890ac5e254cbe8a6836bf96ae3df0f2681b11eefea21e5f9af0c6d5c44dca3e"
INITIAL = "95ed6cfc882e67c88ff4496f339b98bdb4f8c324443da836e43ab7a9b7cb8a4f"
COMPLETE_SEEDS = True
AXES = ("source", "aggregate", "operator", "layout")
OPERATORS = {"=": operator.eq, "<>": operator.ne, "<": operator.lt, "<=": operator.le, ">": operator.gt, ">=": operator.ge}
NULL_LAYOUTS = ("aggregate-null", "null-aggregate")
AGGREGATES = ("count", "sum", "min", "max")
TABLE, EMPTY = "ir_hv_read", "ir_hv_empty"
NAMES = {"accounts": TABLE, "empty": EMPTY}
HEADING = ("id", "amount", "label")
SCHEMA = "ir_fixture"
FULL = SCHEMA + "." + TABLE
VOID = SCHEMA + "." + EMPTY
TYPES = {"id": "int", "amount": "int", "label": ["varchar", 8, COLLATION]}
DECLARATIONS = [f"CREATE TABLE {SCHEMA}.{name} (id INT, amount INT, label VARCHAR(8) CHARACTER SET utf8mb4 COLLATE {COLLATION})"
                for name in NAMES.values()]
DATA = [(2, -2, "sign"), (1, 2147483647, "a "), (1, 2147483647, "a"), (None, 3, "X"), (3, None, "present"),
        (1, 0, "β"), (2, -2, "sign"), (2, None, None), (None, 4, None), (4, 1, "x")]
INVALID = DIAGNOSTICS + "InvalidConstruction"
SEMANTIC = DIAGNOSTICS + "SemanticException"
COMPARATOR_FAULTS = {
    "Erase native legacy group order": "erase-legacy-order",
    "Erase exact DECIMAL group heading": "drop-decimal-type",
    "Ignore a NULL-producing accepted global group": "null-safe-global",
    "Ignore one occurrence in the known MySQL 5.7 diagnostic sequence": "duplicate-warning",
}


def specifications():
    path = TOOL / CONTRACT_NAME
    require(digest(path) == CONTRACT and digest(TOOL / "mysql-grouped-having-v1.json") == INITIAL, "mysql-having-contract-mismatch")
    scope = json.loads(path.read_text())
    base = scope["priorContract"]
    require(digest(TOOL / base["file"]) == base["sha256"] and scope["exploration"]["allowedExclusions"] == [],
            "mysql-having-prior-contract")
    return scope, {"mysql": grouped_domain.specifications()[1]["mysql"]}


def prepare(_, db):
    def sql_value(value):
        if value is None:
            return "NULL"
        if isinstance(value, int):
            return str(value)
        return "CONVERT(0x" + value.encode().hex() + " USING utf8mb4)"

    values = ", ".join("(" + ", ".join(map(sql_value, row)) + ")" for row in DATA)
    setup = db.request({"fixture": [*(f"DROP TABLE IF EXISTS {SCHEMA}.{name}" for name in NAMES.values()),
                                     *DECLARATIONS, f"INSERT INTO {FULL} VALUES {values}",
                                     f"CREATE TEMPORARY TABLE {SCHEMA}.ir_evidence_accounts (probe INTEGER)"]})
    unresolved = db.request({"sql": f"SELECT id AS k FROM {FULL} GROUP BY id HAVING missing_group > 0"})
    return {"fixture": setup, "unresolved": unresolved}


def check_preparation(_, _environment, prepared):
    same({"status": "fixture"}, prepared["fixture"])
    same({"status": "db-error", "state": "42S22"}, prepared["unresolved"])


def table(role="accounts", columns=HEADING):
    return {"schema": SCHEMA, "table": NAMES[role], "columns": list(columns),
            **({"columnTypes": [TYPES[name] for name in columns]} if "label" in columns else {})}


def integer(value):
    return ["integer", str(value)] if -(2**31) <= value < 2**31 else ["range-integer", str(value), 0, False]


def query(tag, source, keys, fields, having, where=None):
    value = case("mysql", tag, source, keys, fields, where)
    value["grouped"]["having"] = having
    return value


def finite(tag, role, operation, symbol, layout, group="keyed"):
    term = aggregate(operation, None if operation == "count" else slot(1))
    left, right = {"aggregate-literal": (term, integer(1)), "literal-aggregate": (integer(1), term),
                   "key-aggregate": (slot(0), term), "aggregate-key": (term, slot(0)),
                   "aggregate-null": (term, ["null"]), "null-aggregate": (["null"], term)}[layout]
    keys = [slot(0)] if group == "keyed" else []
    fields = [[key, "k"] for key in keys] + [[term, "reported"]]
    return query(tag, table(role), keys, fields, compare(symbol, left, right))


def finite_coordinates(spec):
    domain = spec["finite"]
    for group in domain["group"]:
        for role, aggregate_name, symbol, layout in product(*(domain[axis] for axis in AXES)):
            if group == "keyed" or layout in NULL_LAYOUTS:
                yield group, role, aggregate_name, symbol, layout


def finite_ids(_, spec, _tag):
    axes = (tuple(NAMES), AGGREGATES, (*OPERATORS, "<=>"))
    expected = {
        (context, role, operation, operator, layout)
        for role, operation, operator in product(*axes)
        for context, layouts in (
            ("keyed", ("aggregate-literal", "literal-aggregate", "key-aggregate", "aggregate-key", *NULL_LAYOUTS)),
            ("global", NULL_LAYOUTS),
        )
        for layout in layouts
    }
    ordered_grid = list(finite_coordinates(spec))
    extra = spec["collision"]["finite"]
    named_grid = list(names.coordinates(extra))
    new_expected = names.identities()
    require(set(ordered_grid) == expected and len(ordered_grid) == len(expected)
            and {("name", *coordinate) for coordinate in named_grid} == new_expected
            and len(named_grid) == len(new_expected) == extra["identitiesPerRoute"]
            and len(expected | new_expected) == spec["finite"]["identities"],
            "mysql-having-finite-definition")
    return expected | new_expected


def finite_id(_, observed, _item):
    shape = observed["grouped"]
    if names.in_domain(observed):
        return names.identity(shape)
    _, symbol, left, right = shape["having"]
    catalog = {physical: role for role, physical in NAMES.items()}
    classification = {"slot": "key", "integer": "literal", "aggregate": "aggregate", "null": "null"}
    operation = next(term[1] for term in (left, right) if term[0] == "aggregate")
    group = "keyed" if shape["keys"] else "global"
    layout = classification[left[0]] + "-" + classification[right[0]]
    return group, catalog[shape["source"]["table"]], operation, symbol, layout


def analysis_finite_id(engine, observed, item, sql):
    same(names.sql(item) if names.in_domain(item) else command(item), sql)
    return finite_id(engine, observed, item)


def direct_finite_id(engine, observed, item, original):
    same(item, original)
    return finite_id(engine, observed, item)


def analyzed(tag):
    key, amount = slot(0), slot(1)
    count, summed = aggregate("count"), aggregate("sum", amount)
    columns = [[key, "k"]]
    only_key = table(columns=("id",))
    classic = table(columns=("id", "amount"))
    yield f"SELECT id AS k FROM {FULL} GROUP BY id HAVING count(*) > 1", query(
        tag, only_key, [key], columns, compare(">", count, integer(1)))
    yield f"SELECT id AS k,sum(amount) AS total FROM {FULL} WHERE amount > 0 GROUP BY id HAVING sum(amount) > 3", query(
        tag, classic, [key], [*columns, [summed, "total"]], compare(">", summed, integer(3)), compare(">", amount, integer(0)))
    yield f"SELECT id AS k,sum(amount) AS total FROM {FULL} GROUP BY id HAVING total > 0", query(
        tag, classic, [key], [*columns, [summed, "total"]], compare(">", summed, integer(0)))
    yield f"SELECT count(*) AS id FROM {FULL} GROUP BY id HAVING id > 2", query(
        tag, only_key, [names.named("id")], [[count, "id"]], compare(">", names.named("id"), integer(2)))
    yield f"SELECT count(*) AS n FROM {FULL} t GROUP BY t.id HAVING t.id > 2", query(
        tag, only_key, [key], [[count, "n"]], compare(">", key, integer(2)))
    yield f"SELECT count(*) AS n FROM {VOID} HAVING count(*) = 0", query(
        tag, table("empty", ()), [], [[count, "n"]], compare("=", count, integer(0)))
    empty_sum = aggregate("sum", slot(0))
    yield f"SELECT sum(amount) AS total FROM {VOID} HAVING sum(amount) > -1", query(
        tag, table("empty", ("amount",)), [], [[empty_sum, "total"]], compare(">", empty_sum, integer(-1)))
    yield f"SELECT count(*) AS n FROM {VOID} HAVING TRUE", query(
        tag, table("empty", ()), [], [[count, "n"]], ["boolean", True])
    yield f"SELECT id AS k FROM {FULL} GROUP BY id HAVING count(DISTINCT label) > 1", query(
        tag, table(columns=("id", "label")), [key], columns, compare(">", aggregate("count", ["text-slot", 1, 0], True), integer(1)))
    yield f"SELECT id AS k,sum(amount) AS total FROM {FULL} GROUP BY id HAVING sum(amount) > 4294967292", query(
        tag, classic, [key], [*columns, [summed, "total"]], compare(">", summed, integer(4294967292)))
    yield f"SELECT count(*) AS n FROM {FULL} HAVING count(*) <=> 1", query(
        tag, table(columns=()), [], [[count, "n"]], compare("<=>", count, integer(1)))
    comparison = names.trial(tag, only_key, "collision", "qualified", "qualified")
    yield names.sql(comparison), comparison


def request(tag, sql):
    return {"dialect": "mysql", "release": tag, "sql": sql, "declarations": DECLARATIONS}


def negatives(tag):
    for operand, message in (
        (slot(1), "A plain grouped result must read a grouping key."),
        (slot(0, 1), "A plain grouped result must read a grouping key."),
        (aggregate("count", slot(4)), "The aggregate slot must belong to the group input."),
        (aggregate("min", ["text-slot", 2, 0]), "A numeric HAVING comparison needs a numeric aggregate result."),
    ):
        yield query(tag, table(), [slot(0)], [[slot(0), "k"]], compare(">", operand, integer(0))), {
            "exception": INVALID, "message": message}
    yield request(tag, f"SELECT id AS k FROM {FULL} GROUP BY id HAVING missing_group > 0"), {
        "exception": SEMANTIC, "message": "missing_group"}
    yield request(tag, f"SELECT count(*) AS n FROM {FULL} HAVING count(*)+1 > 1"), {
        "exception": INVALID, "message": "The operand has no complete MySQL projection representation."}
    text_min = aggregate("min", ["text-slot", 2, 0])
    yield query(tag, table(), [], [[aggregate("count"), "n"]], compare("<=>", text_min, ["null"])), {
        "exception": INVALID, "message": "A numeric HAVING comparison needs a numeric aggregate result."}
    source = table(columns=("id", "amount"))
    wrong_key = names.trial(tag, source, "collision", "bare-lower", "qualified")
    wrong_key["grouped"]["keys"][0][2] = "amount"
    yield wrong_key, {"exception": INVALID, "message": "A bare group name must match its physical input field."}
    wrong_operand = names.trial(tag, source, "different-label", "qualified", "bare-lower")
    yield wrong_operand, {"exception": INVALID, "message": "A bare group name must match a different projected value."}


def seeded(tag, seed, count):
    rng = Random(seed)
    for index in range(count):
        relation = table("empty" if index % 4 == 0 else "accounts")
        keys = rng.sample([slot(0), slot(1)], index % 3)
        groups = [[key, f"k{position}"] for position, key in enumerate(keys)]
        def term():
            operation = rng.choice(AGGREGATES)
            argument = rng.choice((None, slot(0), slot(1), ["text-slot", 2, 0])) if operation == "count" else slot(rng.randrange(2))
            return aggregate(operation, argument, argument is not None and bool(rng.getrandbits(1)))

        first = term()
        threshold = rng.choice((integer(rng.randrange(-6, 7)), integer(4294967292), integer(-4294967292), *keys))
        second = term() if index % 5 == 0 else threshold
        left, right = (first, second) if index % 2 == 0 else (second, first)
        having = (["null"] if index % 9 == 0 else ["boolean", bool(rng.getrandbits(1))] if index % 9 == 1
                  else compare(rng.choice(tuple(OPERATORS)), left, right))
        where = None if index % 3 else compare(rng.choice(tuple(OPERATORS)), slot(1), integer(rng.randrange(-2, 3)))
        yield query(tag, relation, keys, [*groups, [first, "result"], [term(), "other"]], having, where)


def seeded_nullable(tag, seed, count):
    rng = Random(seed)
    for index, item in enumerate(seeded(tag, seed + 1, count)):
        grouped = item["grouped"]
        aggregate_term = grouped["columns"][-1][0]
        other = ["null"] if index % 3 else integer(rng.choice((-4294967292, 0, 4294967292)))
        left, right = (aggregate_term, other) if index % 2 else (other, aggregate_term)
        symbol = "<=>" if index % 3 else rng.choice(tuple(OPERATORS))
        grouped["having"] = compare(symbol, left, right)
        yield item


def requests(_, tag, spec):
    for index, (group, *dimensions) in enumerate(finite_coordinates(spec)):
        item = finite(tag, *dimensions, group=group)
        yield f"finite/{index}", request(tag, command(item)), analyzed_shape(item), None
        yield f"direct/{index}", item, item, None
    first_name = spec["finite"]["identities"] - spec["collision"]["finite"]["identitiesPerRoute"]
    for index, coordinate in enumerate(names.coordinates(spec["collision"]["finite"]), first_name):
        item = names.trial(tag, table(columns=("id",)), *coordinate)
        yield f"finite/{index}", request(tag, names.sql(item)), item, None
        yield f"direct/{index}", item, item, None
    authored = list(analyzed(tag))
    require(len(authored) == len(spec["analysis"]), "mysql-having-analysis-count")
    for index, (sql, item) in enumerate(authored):
        yield f"analyze/{index}", request(tag, sql), item, None
    for seed in spec["exploration"]["seeds"]:
        for index, item in enumerate(seeded(tag, seed, spec["exploration"]["casesPerSeed"])):
            yield f"seed/{seed}/{index}", item, item, None
        for index, item in enumerate(seeded_nullable(tag, seed, spec["exploration"]["directCasesPerSeed"])):
            yield f"direct-seed/{seed}/{index}", item, item, None
    for index, (item, reason) in enumerate(negatives(tag)):
        yield f"negative/{index}", item, None, reason


def check_value(item, reply):
    require(reply["status"] == "constructed" and reply["profile"].startswith(item["release"] + "/"),
            "mysql-having-rejected", reply)
    require(item["grouped"] == reply["grouped"], "mysql-having-shape", {"expected": item, "observed": reply, "sql": reply["sql"]})


def evaluated(term, group):
    term = names.physical(term)
    if term[0] == "null":
        return None
    if term[0] in ("integer", "range-integer"):
        return int(term[1])
    if term[0] == "aggregate":
        _, operation, argument, distinct = term
        if argument is not None and argument[0] == "text-slot":
            require(operation == "count", "mysql-having-unexpected-text-aggregate", term)
            present = [row[argument[1]] for row in group if row[argument[1]] is not None]
            return len({value.rstrip(" ") for value in present}) if distinct else len(present)
    return field_value(term, group, "mysql")


def included(condition, group):
    if condition[0] != "comparison":
        return condition == ["boolean", True]
    _, symbol, left, right = condition
    a, b = evaluated(left, group), evaluated(right, group)
    if symbol == "<=>":
        return a is b if a is None or b is None else int(a) == int(b)
    return a is not None and b is not None and OPERATORS[symbol](int(a), int(b))


def prediction(item):
    select = item["grouped"]
    source = select["source"]
    keys = [names.physical(term)[1] for term in select["keys"]]
    source_data = ([tuple(row[HEADING.index(name)] for name in source["columns"]) for row in DATA]
                   if source["table"] == TABLE else [])
    groups = {} if keys else {(): []}
    for row in source_data:
        if truth(select["where"], (row,), "mysql"):
            groups.setdefault(tuple(row[key] for key in keys), []).append(row)
    visited = sorted(groups, key=lambda key: tuple((value is not None, value) for value in key)) if ordered(item) else groups
    warnings = names.diagnostics(item)
    return {"status": "rows", "warningCount": len(warnings), "nativeWarnings": warnings,
            "fields": [[name, field_type(term, "mysql")] for term, name in select["columns"]],
            "rows": [[evaluated(term, groups[key]) for term, _ in select["columns"]]
                     for key in visited if included(select["having"], groups[key])]}


def observer_request(_, sql):
    return {"sql": sql, "observeWarnings": True, "observeMysqlGroupWarnings": True}


def observation(raw):
    return raw


def check_observation(item, actual, sql):
    expected = prediction(item)
    witness = {"expected": expected, "observed": actual, "sql": sql}
    require(actual["status"] == "rows", "mysql-having-native-rejection", witness)
    rows_match = (canonical(actual["rows"]) == canonical(expected["rows"]) if ordered(item)
                  else Counter(map(canonical, actual["rows"])) == Counter(map(canonical, expected["rows"])))
    require(expected["fields"] == actual["fields"] and rows_match
            and expected["warningCount"] == actual["warningCount"] and expected["nativeWarnings"] == actual["nativeWarnings"],
            "meaning-mismatch", witness)


def sample_forms(forms, reply, *_):
    selected = reply["grouped"]
    forms["group/" + ("keyed" if selected["keys"] else "global")] += 1
    forms["input/" + selected["source"]["table"]] += 1
    forms["where/" + ("absent" if selected["where"] is None else "present")] += 1
    condition = selected["having"]
    forms["predicate/" + condition[0]] += 1
    if condition[0] == "comparison":
        forms["operator/" + condition[1]] += 1
        forms["operands/" + condition[2][0] + "/" + condition[3][0]] += 1
        for term in condition[2:]:
            if term[0] == "aggregate":
                forms["aggregate/" + term[1]] += 1
                forms["distinct/" + str(term[3])] += 1
                if term[2] is not None:
                    forms["argument/" + term[2][0]] += 1


def required_forms(*_):
    return {"group/keyed", "group/global", "where/absent", "where/present", "predicate/comparison",
            "predicate/null", "predicate/boolean", "distinct/True", "distinct/False", "argument/slot", "argument/text-slot",
            "operands/aggregate/aggregate", "operands/aggregate/range-integer", "operands/range-integer/aggregate",
            "operator/<=>", "operands/null/aggregate", "operands/aggregate/null",
            *("input/" + relation for relation in NAMES.values()), *("aggregate/" + term for term in AGGREGATES)}


def controls():
    item = finite("mysql-5.6.51", "accounts", "sum", "<>", "aggregate-literal")
    healthy = prediction(item)
    check_observation(item, healthy, "grouped correct")
    empty = finite("mysql-9.1.0", "empty", "sum", "<=>", "aggregate-null", "global")
    empty_healthy = prediction(empty)
    check_observation(empty, empty_healthy, "empty global group correct")
    named = names.trial("mysql-5.7.44", table(columns=("id",)), "collision", "repeated", "two-bare")
    named_healthy = prediction(named)
    check_observation(named, named_healthy, "repeated group diagnostics correct")
    variations = {
        "erase-legacy-order": (item, healthy, {**healthy, "rows": sorted(healthy["rows"], key=str, reverse=True)}),
        "drop-decimal-type": (item, healthy, {**healthy, "fields": [["k", "LONG"], ["reported", "LONGLONG"]]}),
        "null-safe-global": (empty, empty_healthy, {**empty_healthy, "rows": []}),
        "duplicate-warning": (named, named_healthy, {**named_healthy, "warningCount": named_healthy["warningCount"] - 1,
                                                    "nativeWarnings": named_healthy["nativeWarnings"][1:]}),
    }
    result = {}
    for label, (trial, correct, wrong) in variations.items():
        require(wrong != correct, "mysql-having-ineffective-control", label)
        try:
            check_observation(trial, wrong, label)
        except Failure as error:
            same("meaning-mismatch", error.signal)
            result[label] = {"input": trial, **error.detail}
        else:
            raise Failure("mysql-having-comparator-accepted", label)
    return result


def boundary(item, reply):
    if rejects(reply, item["boundary"]):
        return
    require(reply["status"] == "constructed", "mysql-having-unexpected-mutant-reply", reply)
    raise Failure("mysql-having-published-invalid", {"sql": reply["sql"], "observed": reply, "expected": item["boundary"]})


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
    raise Failure("mysql-having-undetected-fault", rule.name)


def faults(_, tag):
    root = "sql-semantics-mysql/src/Ir/Aggregation/"
    render = root + "GroupedSql.php"
    skew = finite(tag, "accounts", "count", ">", "aggregate-literal")
    null_safe = finite(tag, "empty", "sum", "<=>", "aggregate-null", "global")
    distinct = query(tag, table(), [slot(0)], [[slot(0), "k"]],
                     compare(">", aggregate("count", slot(1), True), integer(1)))
    invalid, reason = next(negatives(tag))
    raw, alias = list(analyzed(tag))[3]
    return [
        RenderFault("drop-mysql-having", render,
                    "$sql .= ' HAVING (' . $left . ' ' . $query->having->operator->value . ' ' . $right . ')';",
                    "$sql .= '';", skew, "meaning-mismatch"),
        RenderFault("swap-mysql-having-operands", render, "$left . ' ' . $query->having->operator->value . ' ' . $right",
                    "$right . ' ' . $query->having->operator->value . ' ' . $left", skew, "meaning-mismatch"),
        RenderFault("replace-mysql-null-safe-having", render, "$left . ' ' . $query->having->operator->value . ' ' . $right",
                    "$left . ' = ' . $right", null_safe, "meaning-mismatch"),
        RenderFault("erase-mysql-having-distinct", render, "($value->distinct ? 'DISTINCT ' : '')", "''",
                    distinct, "meaning-mismatch"),
        RenderFault("admit-mysql-ungrouped-having-slot", root + "GroupedSelect.php",
                    "$value->occurrence === 0 && isset($members[$value->slot]),", "true,",
                    {"boundary": reason}, "mysql-having-published-invalid", invalid),
        RenderFault("prefer-mysql-result-alias-over-grouping-field", root + "HavingLowering.php",
                    "            $column = $binding->reference($operand);", "            return $columns[0]->value;",
                    alias, "mysql-having-shape", request(tag, raw)),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])
