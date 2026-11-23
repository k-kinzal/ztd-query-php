"""Compare native WHERE and ON predicates against independent truth tables."""

import sys
import json

from evidence_transcript import canonical
from run import TOOL, Failure, digest, require
from run_server import CONTRACT as PROJECTION, same
from run_server_join import CONTRACT as JOIN, OBSERVER, check_preparation, main, observer_request, prepare
from server_delete_cases import literal
from server_compound_cases import leaf
from server_join_cases import expected, is_join, join, observation, slot, table
from server_truth_cases import VALUES, binary, finite_id, finite_ids, logic, null_test, requests, scalar
from sqlite_domain import RenderFault

LABEL = "truth"
CONTRACT = "8ecb535370bab902634dc06671fd19fecd1bc9d1e770e501a1cb212365738f61"


def specifications():
    name = TOOL / "server-truth-v2.json"
    projection = TOOL / "server-projection-v1.json"
    joins = TOOL / "server-join-v2.json"
    require(all(digest(file) == expected for file, expected in ((name, CONTRACT), (projection, PROJECTION), (joins, JOIN))),
            "server-truth-contract-mismatch")
    return json.loads(name.read_text()), json.loads(projection.read_text())["profiles"]


def check_value(item, response):
    require(response["status"] == "constructed" and response["profile"].startswith(item["release"] + "/"),
            "server-truth-construction", response)
    require(all(canonical(item[key]) == canonical(response[key]) for key in ("where", "columns", "source")),
            "server-truth-shape-mismatch", {"expected": item, "actual": response})


def check_observation(item, actual, sql):
    predicted = expected(item)
    witness = {"sql": sql, "expected": predicted, "observed": actual}
    require(actual["status"] == "rows", "server-truth-native-rejection", witness)
    require(canonical(predicted) == canonical(actual), "meaning-mismatch", witness)


def check_fault_witness(item, witness):
    same(expected(item), witness["expected"])
    require(witness["observed"]["status"] == "rows", "server-truth-fault-native")


def required_forms(engine, _tag):
    return {
        *("logic/" + operator for operator in (*binary(engine), "NOT")),
        "null-test/False", "null-test/True", "reference/first", "reference/other",
        "predicate/nested", "predicate/leaf", "comparison", "condition/where", "condition/join",
        "join/INNER", "join/LEFT", "join/RIGHT",
    }


def sample_forms(forms, response, _engine, _item):
    def visit(term, depth):
        role = term[0]
        if depth > 1:
            forms["predicate/nested"] += 1
        if role == "logic":
            forms["logic/" + term[1]] += 1
            for child in term[2:]:
                if child is not None:
                    visit(child, depth + 1)
        elif role == "null-test":
            forms[f"null-test/{term[1]}"] += 1
            visit(term[2], depth + 1)
        elif role == "comparison":
            forms["comparison"] += 1
            for child in term[2:]:
                visit(child, depth + 1)
        elif role == "slot":
            forms["reference/" + ("first" if len(term) == 2 else "other")] += 1
        else:
            require(role in ("boolean", "null", "integer"), "server-truth-unregistered-observed-role", term)
            forms["predicate/leaf"] += 1

    forms["condition/where"] += 1
    visit(response["where"], 0)
    source = response["source"]
    if source is not None and is_join(source):
        forms["condition/join"] += 1
        forms["join/" + source["kind"]] += 1
        visit(source["on"], 0)


def controls():
    item = scalar("postgres", "pg-17.2", null_test(logic("AND", VALUES[0], VALUES[2])), "unknown")
    prediction = expected(item)
    check_observation(item, prediction, "TRUE AND NULL is UNKNOWN")
    altered = {
        "unknown-treated-as-false": {**prediction, "rows": []},
        "wrong-observed-type": {**prediction, "fields": [["unknown", "int8"]]},
        "duplicated-admission": {**prediction, "rows": [[7], [7]]},
    }
    detections = {}
    for name, actual in altered.items():
        try:
            check_observation(item, actual, name)
        except Failure as error:
            require(error.signal == "meaning-mismatch", "server-truth-wrong-control-signal", str(error))
            detections[name] = error.signal
        else:
            raise Failure("server-truth-comparator-accepted", name)
    return {"input": item, "prediction": prediction, "mismatches": altered, "detections": detections}


def faults(engine, tag):
    target = f"sql-semantics-{engine}/src/Ir/SelectSql.php"
    lhs = table(engine, "accounts")
    rhs = table(engine, "independent")
    filter_ = logic("AND", VALUES[0], null_test(slot(1, 1), True))
    joined = leaf(engine, tag, [[literal(1), "joined"]], join("INNER", lhs, rhs, filter_))
    return [
        RenderFault("replace-conjunction-with-disjunction", target,
                    "$condition->operator->value . ' ' . $this->predicate($condition->right",
                    "'OR' . ' ' . $this->predicate($condition->right",
                    scalar(engine, tag, logic("AND", VALUES[0], VALUES[1])), "meaning-mismatch"),
        RenderFault("negate-null-as-false", target, "return '(NOT ' . $left . ')';",
                    "return '(NOT COALESCE(' . $left . ', FALSE))';",
                    scalar(engine, tag, logic("NOT", VALUES[2])), "meaning-mismatch"),
        RenderFault("reverse-null-test", target, "? ' IS NOT NULL)' : ' IS NULL)'", "? ' IS NULL)' : ' IS NOT NULL)'",
                    scalar(engine, tag, null_test(VALUES[2])), "meaning-mismatch"),
        RenderFault("read-first-occurrence-in-nested-test", target, "$this->alias($offset + $expression->occurrence)",
                    "$this->alias($offset)", joined, "meaning-mismatch"),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])
