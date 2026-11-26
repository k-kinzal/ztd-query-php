"""Compare PostgreSQL null-safe int4 predicates against a separate typed effect model."""

import json
import sys

from postgres_int4_cases import integer, source_role
from postgres_int4_distinctness_cases import NULL, distinctness, finite_id, finite_ids, item, requests
from run import TOOL, Failure, digest, require
from run_server import CONTRACT as PROJECTION, same
from run_server_join import main
from run_server_postgres_int4 import check_fault_witness, check_observation, check_value
from run_server_postgres_int4_range import observer_request
from server_cases import expected, fixtures, observation
from sqlite_domain import RenderFault

LABEL = "postgres-int4-distinctness"
CONTRACT_NAME = "postgres-int4-distinctness-v1.json"
CONTRACT = "7389b300bd6ee4a8eb0094bdf5ba7bf9d8aaea4bde8ee87412e725e996278cdc"
OBSERVER = "server_observer.php"


def specifications():
    scope, profile = TOOL / CONTRACT_NAME, TOOL / "server-projection-v1.json"
    require(digest(scope) == CONTRACT and digest(profile) == PROJECTION, "postgres-int4-distinctness-contract-mismatch")
    return json.loads(scope.read_text()), {"postgres": json.loads(profile.read_text())["profiles"]["postgres"]}


def prepare(engine, db):
    same({"status": "fixture"}, db.request({"fixture": [
        *fixtures(engine),
        "DROP OPERATOR IF EXISTS public.= (integer, integer)",
        "CREATE OR REPLACE FUNCTION public.ir_shadow_int4_equal(integer, integer) RETURNS boolean LANGUAGE SQL IMMUTABLE AS 'SELECT false'",
        "CREATE OPERATOR public.= (FUNCTION = public.ir_shadow_int4_equal, LEFTARG = integer, RIGHTARG = integer)",
    ]}))
    probe = "SELECT current_setting('search_path') AS path, 2 = 2 AS equal"
    return {"shadow": db.request({"sql": probe, "shadowBuiltinOperators": True}),
            "restored": db.request({"sql": probe})}


def check_preparation(_, environment, prepared):
    fields = [["path", "text"], ["equal", "bool"]]
    require(environment["database"][-1] == "pg_catalog, public", "postgres-int4-distinctness-default-profile")
    same({"shadow": {"status": "rows", "fields": fields, "rows": [["public, pg_catalog", False]]},
          "restored": {"status": "rows", "fields": fields, "rows": [["pg_catalog, public", True]]}}, prepared)


def required_forms(_engine, _tag):
    return {
        "source/unit", "source/only", "source/inherited", "placement/column", "placement/where",
        "placement/case", "placement/logic", "placement/null-test", "equal/True", "equal/False",
        "boundary/minimum", "boundary/maximum", "width/4",
        *(f"{side}/{kind}" for side in ("left", "right") for kind in ("null", "slot", "integer")),
    }


def sample_forms(forms, response, _engine, _item):
    def visit(value, role):
        if value is None:
            return
        kind = value[0]
        if kind == "int4-distinctness":
            _, equal, left, right = value
            forms[f"equal/{equal}"] += 1
            forms["placement/" + role] += 1
            for side, operand in (("left", left), ("right", right)):
                forms[f"{side}/{operand[0]}"] += 1
                if operand == integer(-2147483648):
                    forms["boundary/minimum"] += 1
                if operand == integer(2147483647):
                    forms["boundary/maximum"] += 1
            return
        if kind in ("logic", "null-test"):
            for operand in value[2:]:
                visit(operand, kind)
        elif kind == "int4-case":
            for condition, _ in value[1]:
                visit(condition, "case")
        else:
            raise Failure("postgres-int4-distinctness-unknown-exploration-shape", value)

    forms["source/" + source_role(response["source"])] += 1
    forms[f"width/{len(response['columns'])}"] += 1
    for value, _ in response["columns"]:
        visit(value, "column")
    visit(response["where"], "where")


def controls():
    tag = "pg-17.2"
    nulls = item(tag, "projection", distinctness(NULL, NULL, True), "unit")
    one_null = item(tag, "projection", distinctness(NULL, integer(2), False), "unit")
    records = {}
    for name, request, delta in (
        ("nullable-equality", nulls, {"rows": [[None]]}),
        ("one-null-treated-as-equal", one_null, {"rows": [[False]]}),
        ("boolean-type-erased", nulls, {"fields": [["result", "int4"]]}),
    ):
        healthy = expected(request)
        check_observation(request, healthy, "healthy")
        try:
            check_observation(request, {**healthy, **delta}, name)
        except Failure as error:
            require(error.signal == "meaning-mismatch", "postgres-int4-distinctness-control-signal", name)
            records[name] = {"input": request, **error.detail}
        else:
            raise Failure("postgres-int4-distinctness-accepted-control", name)
    return records


def faults(_engine, tag):
    renderer = "sql-semantics-postgres/src/Ir/SelectSql.php"
    polarity = "$test->negated ? $same : '(NOT ' . $same . ')'"
    qualified = "$equality = '(' . $left . ' OPERATOR(pg_catalog.=) ' . $right . ')';"
    null_fallback = "$same = 'COALESCE(' . $equality . ', (' . $left . ' IS NULL AND ' . $right . ' IS NULL))';"
    shadow = {**item(tag, "projection", distinctness(integer(1), integer(1), True), "unit"), "shadow": True}
    return [
        RenderFault("invert-distinctness-polarity", renderer, polarity, polarity.replace("$test->negated", "!$test->negated"),
                    item(tag, "projection", distinctness(integer(1), integer(2)), "unit"), "meaning-mismatch"),
        RenderFault("return-nullable-equality", renderer, null_fallback, "$same = $equality;",
                    item(tag, "projection", distinctness(NULL, NULL, True), "unit"), "meaning-mismatch"),
        RenderFault("compare-the-left-value-twice", renderer, "$this->integerOrNull($test->right,", "$this->integerOrNull($test->left,",
                    item(tag, "projection", distinctness(["slot", 0], ["slot", 1], True), "only"), "meaning-mismatch"),
        RenderFault("rebind-equality-in-caller-schema", renderer, qualified, qualified.replace("OPERATOR(pg_catalog.=)", "="), shadow, "meaning-mismatch"),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])
