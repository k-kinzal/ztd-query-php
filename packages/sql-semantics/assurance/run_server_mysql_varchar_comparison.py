"""Compare native MySQL VARCHAR operations and their owning statement effects."""

from itertools import product
import json
from random import Random
import sys

from run import TOOL, Failure, digest, require
from run_server import CONTRACT as PROJECTION, same
from run_server_join import OBSERVER, main
from run_server_mysql_varchar_effects import (
    check_preparation, check_value as check_mutation, expected as mutation_expected, observation as mutation_observation, prepare, statement,
)
from run_server_mysql_varchar_presence import logical, null_test
from run_server_mysql_varchar_slots import (
    COLLATION, DECLARATIONS, DIAGNOSTICS, PRIMARY, SCHEMA, SECONDARY, check_value as check_select,
    field, project, query, row_stream, table, value,
)
from server_cases import observation as selected_rows
from server_join_cases import join, slot
from server_truth_model import admits, predicate_value
from sqlite_domain import RenderFault

LABEL = "mysql-varchar-comparison"
CONTRACT = "5170d65217583fbe3194f02d4186445facbfab35320d302230a132599db3b9e9"


def specifications():
    path, profile = TOOL / "mysql-varchar-comparison-v1.json", TOOL / "server-projection-v1.json"
    require(digest(path) == CONTRACT and digest(profile) == PROJECTION, "mysql-varchar-comparison-contract-mismatch")
    return json.loads(path.read_text()), {"mysql": json.loads(profile.read_text())["profiles"]["mysql"]}


def observer_request(item, sql):
    if "target" in item:
        return {"sql": sql, "observeMysqlVarcharEffect": True}
    return {"sql": sql}


def text(value):
    return ["text", value]


def comparison(operator, left, right):
    return ["text-comparison", operator, COLLATION, left, right]


def place(tag, placement, term, kind="LEFT"):
    source = table()
    fields = [[field(0), "value"], [slot(1), "id"]]
    if placement in ("delete", "update"):
        return statement(tag, placement, term, "changed" if placement == "update" else None)
    if placement == "on":
        source = join(kind, source, table(SECONDARY), term)
        fields.append([field(2, 1), "later"])
    if placement == "result":
        fields.append([term, "test"])
    item = project(tag, source, fields)
    return {**item, "where": term if placement == "where" else None}


def finite(tag, symbol, operands, placement):
    left = {"field": field(0), "literal": text("a"), "null": ["null"]}[operands[0]]
    right_field = field(2, 1) if placement == "on" else field(2)
    right = {"field": right_field, "literal": text("a "), "null": ["null"]}[operands[1]]
    return place(tag, placement, comparison(symbol, left, right))


def finite_ids(_, specification, _tag):
    b = specification["finite"]
    identities = {(symbol, *pair, placement) for symbol, pair, placement in product(b["operators"], b["operands"], b["placement"])}
    require(len(identities) == b["identities"], "mysql-varchar-comparison-finite-size", len(identities))
    return identities


def placed_term(item):
    if "target" in item:
        return ("update" if "assignments" in item else "delete"), item["where"]
    if "kind" in item["source"]:
        return "on", item["source"]["on"]
    if item["where"] is not None:
        return "where", item["where"]
    return "result", item["columns"][-1][0]


def finite_id(_, observed, _item):
    placement, (role, operator, collation, left, right) = placed_term(observed)
    require(role == "text-comparison" and collation == COLLATION, "mysql-varchar-comparison-finite-role", observed)
    terms = {"text-slot": "field", "text": "literal", "null": "null"}
    return operator, terms[left[0]], terms[right[0]], placement


def analyzed(tag):
    first, next_ = f"{SCHEMA}.{PRIMARY}", f"{SCHEMA}.{SECONDARY}"
    source = table(columns=("label",))
    yield f"SELECT label = 'a ' AS test FROM {first}", project(tag, source, [[comparison("=", field(0), text("a ")), "test"]])
    yield f"SELECT 'A' != label AS test FROM {first}", project(tag, source, [[comparison("<>", text("A"), field(0)), "test"]])
    yield (f"SELECT label = NULL AS ordinary_test, label <=> NULL AS null_safe FROM {first}",
           project(tag, source, [[comparison("=", field(0), ["null"]), "ordinary_test"],
                                 [comparison("<=>", field(0), ["null"]), "null_safe"]]))
    scope = join("LEFT", table(columns=("label",)), table(SECONDARY, ("alternate",)),
                 comparison("<", field(0), field(0, 1)))
    yield (f"SELECT a.label AS value, b.alternate AS later FROM {first} a LEFT JOIN {next_} b ON a.label < b.alternate",
           project(tag, scope, [[field(0), "value"], [field(0, 1), "later"]]))
    yield (f"UPDATE {first} SET id = 9901 WHERE label = 'a '",
           place(tag, "update", comparison("=", field(0), text("a "))))
    yield f"DELETE FROM {first} WHERE label <=> NULL", place(tag, "delete", comparison("<=>", field(0), ["null"]))


def negative(tag):
    possess = "A VARCHAR slot must belong to a visible physical input occurrence."
    yield place(tag, "result", comparison("=", text("a"), text("A"))), "A text comparison needs a native VARCHAR field or explicit collation."
    yield place(tag, "result", comparison("=", field(1), text("a"))), possess
    yield place(tag, "on", comparison("=", field(0, 2), field(2, 1))), possess
    origin = f"{SCHEMA}.{PRIMARY}"
    yield query(tag, f"SELECT label = 1 AS test FROM {origin}"), "A VARCHAR comparison requires two native text operands or NULL."
    unsupported = DECLARATIONS[0].replace(COLLATION, "utf8mb4_general_ci")
    yield query(tag, f"SELECT label = 'a' AS test FROM {origin}", [unsupported]), "The text slot requires an explicit utf8mb4_bin collation."


def seeded(tag, seed, budget, operators, placements):
    rng = Random(seed)
    values = ("", "A", "a ", "a\x00", "a  ", "é", "é", "𐐷", "𐐀", "quote's", "line\nbreak", "tab\t")
    fields = (0, 2, 3)

    def choose(placement, depth):
        occurrence = rng.randrange(2) if placement == "on" else 0
        first = field(rng.choice(fields), occurrence)
        second = rng.choice([["null"], field(rng.choice(fields), occurrence), *[text(payload) for payload in values]])
        ordered = (first, second) if rng.getrandbits(1) else (second, first)
        leaf = comparison(rng.choice(operators), *ordered)
        if depth == 0:
            return leaf
        operation = rng.choice(("AND", "OR", "XOR", "NOT", "NULL"))
        if operation == "NULL":
            return null_test(leaf, bool(rng.getrandbits(1)))
        return logical(operation, leaf, None if operation == "NOT" else choose(placement, depth - 1))

    for index in range(budget):
        placement = placements[index % len(placements)]
        yield place(tag, placement, choose(placement, 0 if placement == "result" else rng.randrange(3)), rng.choice(("INNER", "LEFT", "RIGHT")))


def requests(_, tag, spec):
    b = spec["finite"]
    for index, (operator, operands, placement) in enumerate(product(b["operators"], b["operands"], b["placement"])):
        item = finite(tag, operator, operands, placement)
        yield f"finite/{index}", item, item, None
    positives = list(analyzed(tag))
    require(len(positives) == len(spec["analysis"]), "mysql-varchar-comparison-analysis-incomplete")
    for index, (sql, item) in enumerate(positives):
        yield f"analysis/{index}", query(tag, sql), item, None
    for seed in spec["exploration"]["seeds"]:
        for index, item in enumerate(seeded(tag, seed, spec["exploration"]["casesPerSeed"], b["operators"], b["placement"])):
            yield f"seed/{seed}/{index}", item, item, None
    for index, (item, message) in enumerate(negative(tag)):
        yield f"negative/{index}", item, None, {"exception": DIAGNOSTICS + "InvalidConstruction", "message": message}


def expected(item):
    if "target" in item:
        return mutation_expected(item)
    fields = item["columns"]

    def evaluate(term, row):
        if term[0] != "text-comparison":
            return value(term, row)
        truth = predicate_value(term, lambda child: value(child, row))
        return None if truth is None else int(truth)

    kinds = {"text-comparison": "LONGLONG", "slot": "LONG", "text-slot": "VAR_STRING"}
    return selected_rows({"status": "rows", "fields": [[name, kinds[term[0]]] for term, name in fields],
                          "rows": [[evaluate(term, row) for term, _ in fields] for row in row_stream(item["source"])
                                   if admits(item["where"], lambda child: value(child, row))]})


def observation(raw):
    return mutation_observation(raw) if raw["status"] == "mutation" else selected_rows(raw)


def check_value(item, response):
    if "target" in item:
        check_mutation(item, response)
    else:
        check_select(item, response)


def check_observation(item, actual, sql):
    prediction = expected(item)
    witness = {"sql": sql, "expected": prediction, "observed": actual}
    require(prediction["status"] == actual["status"], "mysql-varchar-comparison-native-rejection", witness)
    require(prediction == actual, "meaning-mismatch", witness)


def check_fault_witness(item, witness):
    same(expected(item), witness["expected"])
    require(witness["observed"]["status"] == witness["expected"]["status"], "mysql-varchar-comparison-fault-native")


def sample_forms(forms, observed, *_):
    placement, term = placed_term(observed)
    forms["place/" + placement] += 1

    def visit(node):
        if node is None:
            return
        role = node[0]
        if role == "text-comparison":
            forms["operator/" + node[1]] += 1
            for operand in node[3:]:
                forms["kind/" + operand[0]] += 1
                if operand[0] == "text-slot":
                    forms[f"occurrence/{operand[2]}"] += 1
        elif role == "logic":
            forms["nested/" + node[1]] += 1
            visit(node[2])
            visit(node[3])
        elif role == "null-test":
            forms["nested/NULL"] += 1
            visit(node[2])
        else:
            raise Failure("mysql-varchar-comparison-unsampled-role", node)

    visit(term)


def required_forms(*_):
    return ({"kind/text", "kind/text-slot", "kind/null", "occurrence/0", "occurrence/1",
             "nested/AND", "nested/OR", "nested/XOR", "nested/NOT", "nested/NULL"}
            | {"operator/" + name for name in ("=", "<=>", "<>", "<", "<=", ">", ">=")}
            | {"place/" + name for name in ("result", "where", "on", "update", "delete")})


def controls():
    item = place("mysql-9.1.0", "result", comparison("=", field(0), ["null"]))
    wanted = expected(item)
    altered = {**wanted, "rows": [[*row[:-1], 0] for row in wanted["rows"]]}
    check_observation(item, wanted, "ordinary VARCHAR equality propagates NULL")
    try:
        check_observation(item, altered, "replace SQL NULL with zero")
    except Failure as failure:
        require(failure.signal == "meaning-mismatch", "mysql-varchar-comparison-wrong-control", failure.detail)
    else:
        raise Failure("mysql-varchar-comparison-comparator-accepted")
    return {"input": item, "expected": wanted, "mismatch": altered}


def faults(_, tag):
    src = "sql-semantics-mysql/src/Ir/"
    renderer = src + "SelectSql.php"
    operands = "$left . ' ' . $comparison->operator->value . ' ' . $right"
    return [
        RenderFault("swap-varchar-comparison-arguments", renderer, operands,
                    "$right . ' ' . $comparison->operator->value . ' ' . $left",
                    place(tag, "result", comparison("<", field(0), text("a"))), "meaning-mismatch"),
        RenderFault("erase-null-safe-varchar-equality", renderer, "$comparison->operator->value", "'='",
                    place(tag, "result", comparison("<=>", field(0), ["null"])), "meaning-mismatch"),
        RenderFault("use-case-insensitive-varchar-comparison", renderer, operands,
                    "$left . ' COLLATE utf8mb4_general_ci ' . $comparison->operator->value . ' ' . $right",
                    place(tag, "result", comparison("=", field(0), text("a"))), "meaning-mismatch"),
        RenderFault("compare-with-the-first-varchar-occurrence", renderer, "$this->alias($offset + $expression->occurrence)",
                    "$this->alias($offset + ($expression instanceof TextColumnReference ? 0 : $expression->occurrence))",
                    place(tag, "on", comparison("<", field(0), field(2, 1))), "meaning-mismatch"),
        RenderFault("drop-varchar-comparison-update-filter", src + "Mutation/Update.php", "if ($this->where !== null)",
                    "if (false)", place(tag, "update", comparison("=", field(0), text("a "))), "meaning-mismatch"),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])
