"""Compare all six PostgreSQL truth tests against independent three-valued evaluation."""

import json
import sys

from postgres_boolean_test_cases import NULL, finite_id, finite_ids, item, leaf, requests, test
from postgres_int4_cases import source_role
from run import TOOL, Failure, digest, require
from run_server import CONTRACT as PROJECTION, same
from run_server_join import main
from run_server_postgres_int4 import check_fault_witness, check_observation, check_value, observer_request
from server_cases import expected, fixtures, observation
from sqlite_domain import RenderFault

LABEL = "postgres-boolean-tests"
CONTRACT_NAME = "postgres-boolean-tests-v1.json"
CONTRACT = "db8f3a718d0eec95297d153076a3af9d8b456e4d706ebd16f10238d3a7fda32d"
OBSERVER = "server_observer.php"


def specifications():
    scope, profile = TOOL / CONTRACT_NAME, TOOL / "server-projection-v1.json"
    require(digest(scope) == CONTRACT and digest(profile) == PROJECTION, "postgres-boolean-tests-contract-mismatch")
    return json.loads(scope.read_text()), {"postgres": json.loads(profile.read_text())["profiles"]["postgres"]}


def prepare(engine, db):
    return db.request({"fixture": fixtures(engine)})


def check_preparation(_, environment, preparation):
    same({"status": "fixture"}, preparation)
    require(environment["database"][-1] == "pg_catalog, public", "postgres-boolean-tests-default-profile")


def required_forms(_engine, _tag):
    modes = specifications()[0]["finite"]["modes"]
    return {
        "source/unit", "source/only", "source/inherited", "placement/column", "placement/where", "placement/case",
        "placement/nested", "width/4", *("mode/" + mode for mode in modes),
        *("operand/" + role for role in ("boolean", "null", "comparison", "int4-range", "int4-distinctness", "truth-test", "logic", "null-test")),
    }


def sample_forms(forms, response, _engine, _item):
    def visit(value, position):
        if value is None:
            return
        role = value[0]
        if role == "truth-test":
            operand = value[2]
            forms["mode/" + value[1]] += 1
            forms["operand/" + operand[0]] += 1
            forms["placement/" + position] += 1
            visit(operand, "nested")
        elif role == "int4-case":
            for decision, _ in value[1]:
                visit(decision, "case")
        elif role in ("logic", "null-test"):
            for child in value[2:]:
                visit(child, position)

    forms["source/" + source_role(response["source"])] += 1
    forms[f"width/{len(response['columns'])}"] += 1
    for expression, _ in response["columns"]:
        visit(expression, "column")
    visit(response["where"], "where")


def controls():
    example = item("pg-17.2", "projection", test("IS UNKNOWN", NULL), "unit")
    correct = expected(example)
    records = {}
    check_observation(example, correct, "healthy")
    for name, change in (
        ("unknown-misread-as-false", {"rows": [[False]]}),
        ("wrong-nullability", {"rows": [[None]]}),
        ("type-lost", {"fields": [["result", "int4"]]}),
    ):
        try:
            check_observation(example, {**correct, **change}, name)
        except Failure as error:
            require(error.signal == "meaning-mismatch", "postgres-boolean-tests-control-signal", name)
            records[name] = {"input": example, **error.detail}
        else:
            raise Failure("postgres-boolean-tests-accepted-control", name)
    return records


def faults(_engine, tag):
    renderer = "sql-semantics-postgres/src/Ir/SelectSql.php"
    operator = "$test->operator->value"
    operand = "$this->predicate($test->operand, $source, $offset, $qualify)"
    sql = f"'(' . {operand} . ' ' . {operator} . ')'"
    tests = [("erase-not-true", "IS NOT TRUE", "IS TRUE", leaf(True)),
             ("exchange-true-false", "IS TRUE", "IS FALSE", leaf(True)),
             ("misread-unknown", "IS UNKNOWN", "IS FALSE", NULL)]
    mutated = [
        RenderFault(name, renderer, sql, sql.replace(operator, f"({operator} === '{mode}' ? '{wrong}' : {operator})"),
                    item(tag, "projection", test(mode, value), "unit"), "meaning-mismatch")
        for name, mode, wrong, value in tests
    ]
    return mutated + [
        RenderFault("drop-truth-test", renderer, sql, operand,
                    item(tag, "projection", test("IS TRUE", NULL), "unit"), "meaning-mismatch")
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])
