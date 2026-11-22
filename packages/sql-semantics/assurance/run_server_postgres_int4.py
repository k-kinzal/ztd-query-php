"""Compare PostgreSQL int4 scalar roles, including exact arithmetic failures."""

import json
import sys

from evidence_transcript import canonical
from postgres_int4_cases import finite_id, finite_ids, integer, operation, projection, requests, source_role
from run import TOOL, Failure, digest, require
from run_server import CONTRACT as PROJECTION, same
from run_server_join import main
from server_cases import expected, fixtures, observation
from sqlite_domain import RenderFault

LABEL = "postgres-int4"
CONTRACT = "977d858b305d7eb4d33f3061a70781c9dc3412a4280003016b0ae92910f52bfa"
OBSERVER = "server_observer.php"


def specification():
    path = TOOL / "postgres-int4-operators-v2.json"
    require(digest(path) == CONTRACT, "postgres-int4-contract-mismatch")
    return json.loads(path.read_text())


def specifications():
    scope = specification()
    scope["exploration"]["timeoutSecondsPerRelease"] = scope["exploration"]["timeoutSeconds"]
    projection_contract = TOOL / "server-projection-v1.json"
    require(digest(projection_contract) == PROJECTION, "server-projection-contract-mismatch")
    profiles = json.loads(projection_contract.read_text())["profiles"]
    return scope, {"postgres": profiles["postgres"]}


def prepare(engine, db):
    same({"status": "fixture"}, db.request({"fixture": [
        *fixtures(engine),
        "DROP OPERATOR IF EXISTS public./ (integer, integer)",
        "CREATE OR REPLACE FUNCTION public.ir_shadow_int4_division(integer, integer) RETURNS integer LANGUAGE SQL IMMUTABLE AS 'SELECT 81'",
        "CREATE OPERATOR public./ (FUNCTION = public.ir_shadow_int4_division, LEFTARG = integer, RIGHTARG = integer)",
        "SET search_path = public, pg_catalog",
    ]}))
    return {
        "path": db.request({"sql": "SELECT current_setting('search_path') AS path"}),
        "operator": db.request({"sql": "SELECT 8 / 3 AS shadowed, 8 OPERATOR(pg_catalog./) 3 AS builtin"}),
    }


def check_preparation(_, environment, prepared):
    require(environment["database"][-1] == "pg_catalog, public", "postgres-int4-default-profile")
    same({"path": {"status": "rows", "fields": [["path", "text"]], "rows": [["public, pg_catalog"]]},
          "operator": {"status": "rows", "fields": [["shadowed", "int4"], ["builtin", "int4"]], "rows": [[81, 2]]}},
         prepared)


def observer_request(_, sql):
    return {"sql": sql}


def check_value(item, response):
    require(response["status"] == "constructed" and response["profile"].startswith(item["release"] + "/"),
            "postgres-int4-positive-rejected", response)
    require(all(canonical(item[key]) == canonical(response[key]) for key in ("source", "columns", "where")),
            "postgres-int4-structure-mismatch", {"expected": item, "actual": response})


def check_observation(item, actual, sql):
    prediction = expected(item)
    require(canonical(prediction) == canonical(actual), "meaning-mismatch",
            {"sql": sql, "expected": prediction, "observed": actual})


def check_fault_witness(item, witness):
    same(expected(item), witness["expected"])
    require(witness["observed"]["status"] == "rows", "postgres-int4-fault-native")


def required_forms(_engine, _tag):
    return {"scope/unit", "scope/inherited", "scope/only", "nested", "leaf/slot", "leaf/integer",
            *("operator/" + name for name in ("/", "%", "&", "|", "#"))}


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
    tag = "pg-17.2"
    item = projection(tag, operation("/", integer(-11), integer(3)))
    failure = projection(tag, operation("%", integer(5), integer(0)))
    altered = [
        (item, "floor-division", {"status": "rows", "fields": [["result", "int4"]], "rows": [[-4]]}),
        (item, "wrong-type", {"status": "rows", "fields": [["result", "int8"]], "rows": [[-3]]}),
        (failure, "wrong-error", {"status": "db-error", "state": "22003"}),
        (failure, "missing-error", {"status": "rows", "fields": [["result", "int4"]], "rows": [[None]]}),
    ]
    records = {}
    for request, name, actual in altered:
        check_observation(request, expected(request), name)
        try:
            check_observation(request, actual, name)
        except Failure as error:
            require(error.signal == "meaning-mismatch", "postgres-int4-control-signal", name)
            records[name] = {"input": request, **error.detail}
        else:
            raise Failure("postgres-int4-accepted-control", name)
    return records


def faults(_engine, tag):
    renderer = "sql-semantics-postgres/src/Ir/SelectSql.php"
    operator = "$expression->operator->value"
    left = "$this->expression($expression->left, $source, $offset, $qualify)"
    right = "$this->expression($expression->right, $source, $offset, $qualify)"
    return [
        RenderFault("remainder-for-division", renderer, operator, f"({operator} === '/' ? '%' : {operator})",
                    projection(tag, operation("/", integer(11), integer(3))), "meaning-mismatch"),
        RenderFault("reverse-remainder-operands", renderer, f"{left} . ' OPERATOR(pg_catalog.' . {operator} . ') ' . {right}",
                    f"{right} . ' OPERATOR(pg_catalog.' . {operator} . ') ' . {left}",
                    projection(tag, operation("%", integer(-11), integer(3))), "meaning-mismatch"),
        RenderFault("or-instead-of-xor", renderer, operator, f"({operator} === '#' ? '|' : {operator})",
                    projection(tag, operation("#", integer(9), integer(3))), "meaning-mismatch"),
        RenderFault("resolve-operator-in-caller-schema", renderer, "' OPERATOR(pg_catalog.' . $expression->operator->value . ') '",
                    "' ' . $expression->operator->value . ' '",
                    projection(tag, operation("/", integer(8), integer(3))), "meaning-mismatch"),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])
