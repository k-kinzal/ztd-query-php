"""Compare signed MySQL DIV and remainder using the fixed native projection profile."""

import json
import sys

from evidence_transcript import canonical
from mysql_signed_quotient_cases import expected, finite_id, finite_ids, integer, operation, projection, requests, source_role
from run import TOOL, Failure, digest, require
from run_server import CONTRACT as PROJECTION, same
from run_server_join import main
from server_cases import fixtures, observation
from sqlite_domain import RenderFault

LABEL = "mysql-signed-quotient"
CONTRACT = "66925621bd8a6e02a6e006be1163f34622ba75af77497f1958e052b7f9097996"
OBSERVER = "server_observer.php"


def specifications():
    path, profile = TOOL / "mysql-signed-quotient-v1.json", TOOL / "server-projection-v1.json"
    require(digest(path) == CONTRACT and digest(profile) == PROJECTION, "mysql-signed-quotient-contract-mismatch")
    return json.loads(path.read_text()), {"mysql": json.loads(profile.read_text())["profiles"]["mysql"]}


def prepare(engine, db):
    same({"status": "fixture"}, db.request({"fixture": fixtures(engine)}))
    query = "SELECT (-11 DIV 3) AS q, (-11 % 3) AS r, (7 DIV 0) AS missing"
    healthy = db.request(observer_request(None, query))
    # Force a genuine diagnostic, then restore the empty mode before candidate queries.
    same({"status": "fixture"}, db.request({"fixture": ["SET SESSION sql_mode='ERROR_FOR_DIVISION_BY_ZERO'"]}))
    warned = db.request(observer_request(None, query))
    same({"status": "fixture"}, db.request({"fixture": ["SET SESSION sql_mode=''"]}))
    restored = db.request(observer_request(None, query))
    return {"healthy": healthy, "warned": warned, "restored": restored}


def check_preparation(_, environment, prepared):
    require(environment["database"][2] == "", "mysql-signed-quotient-profile")
    healthy = {"status": "rows", "fields": [[name, "LONGLONG"] for name in ("q", "r", "missing")],
               "rows": [[-3, -2, None]], "warningCount": 0}
    same({"healthy": healthy, "warned": {**healthy, "warningCount": 1}, "restored": healthy}, prepared)


def observer_request(_, sql):
    return {"sql": sql, "observeWarnings": True}


def check_value(item, response):
    require(response["status"] == "constructed" and response["profile"].startswith(item["release"] + "/"),
            "mysql-signed-quotient-positive-rejected", response)
    require(all(canonical(item[key]) == canonical(response[key]) for key in ("source", "columns", "where")),
            "mysql-signed-quotient-structure-mismatch", {"expected": item, "actual": response})


def check_observation(item, actual, sql):
    prediction = expected(item)
    require(canonical(prediction) == canonical(actual), "meaning-mismatch",
            {"sql": sql, "expected": prediction, "observed": actual})


def check_fault_witness(item, witness):
    same(expected(item), witness["expected"])
    require(witness["observed"]["status"] == "rows", "mysql-signed-quotient-fault-native")


def required_forms(_engine, _tag):
    return {"scope/unit", "scope/table", "nested", "leaf/slot", "leaf/integer", "operator/DIV", "operator/%"}


def sample_forms(forms, response, _engine, _item):
    def visit(operand, depth):
        if operand[0] == "operation":
            forms["operator/" + operand[1]] += 1
            if depth:
                forms["nested"] += 1
            for child in operand[2:]:
                visit(child, depth + 1)
        else:
            forms["leaf/" + operand[0]] += 1

    forms["scope/" + source_role(response["source"])] += 1
    for operand, _ in response["columns"]:
        visit(operand, 0)


def controls():
    tag = "mysql-9.1.0"
    negative = projection(tag, operation("DIV", integer(-11), integer(3)))
    zero = projection(tag, operation("%", integer(9), integer(0)))
    result = expected(negative)
    missing = expected(zero)
    altered = [
        (negative, "floor-division", {**result, "rows": [[-4]]}),
        (negative, "wrong-type", {**result, "fields": [["result", "NEWDECIMAL"]]}),
        (zero, "null-erased", {**missing, "rows": [[0]]}),
        (zero, "warning-erased", {**missing, "warningCount": 1}),
    ]
    records = {}
    for item, name, actual in altered:
        check_observation(item, expected(item), name)
        try:
            check_observation(item, actual, name)
        except Failure as error:
            require(error.signal == "meaning-mismatch", "mysql-signed-quotient-control-signal", name)
            records[name] = {"input": item, **error.detail}
        else:
            raise Failure("mysql-signed-quotient-accepted-control", name)
    return records


def faults(_engine, tag):
    renderer = "sql-semantics-mysql/src/Ir/SelectSql.php"
    operator = "$expression->operator->value"
    left = "$this->expression($expression->left, $source, $offset, $qualify)"
    right = "$this->expression($expression->right, $source, $offset, $qualify)"
    binary = f"'(' . {left} . ' ' . {operator} . ' ' . {right} . ')'"
    quotient = projection(tag, operation("DIV", integer(-11), integer(3)))
    remainder = projection(tag, operation("%", integer(-11), integer(3)))
    missing = projection(tag, operation("%", integer(9), integer(0)))
    return [
        RenderFault("remainder-for-division", renderer, binary, binary.replace(operator, "'%'"), quotient, "meaning-mismatch"),
        RenderFault("round-negative-quotient-down", renderer, binary,
                    f"'CAST(FLOOR(' . {left} . ' / ' . {right} . ') AS SIGNED)'", quotient, "meaning-mismatch"),
        RenderFault("reverse-remainder-operands", renderer, binary,
                    f"'(' . {right} . ' ' . {operator} . ' ' . {left} . ')'", remainder, "meaning-mismatch"),
        RenderFault("zero-instead-of-null", renderer, binary,
                    f"'COALESCE(' . {binary} . ', 0)'", missing, "meaning-mismatch"),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])
