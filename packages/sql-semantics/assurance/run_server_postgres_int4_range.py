"""Verify int4 range predicates against native signed comparisons and WHERE admission."""

import json
import sys

from postgres_int4_cases import integer, source_role
from postgres_int4_range_cases import NULL, finite_id, finite_ids, int4_range, item, requests
from run import TOOL, Failure, digest, require
from run_server import CONTRACT as PROJECTION, same
from run_server_join import main
from run_server_postgres_int4 import check_fault_witness, check_observation, check_value
from server_cases import expected, fixtures, observation
from sqlite_domain import RenderFault

LABEL = "postgres-int4-range"
CONTRACT = "18d4b12d531f691206f92ba36a1bf7932421581d690c72be4829289999fcb676"
OBSERVER = "server_observer.php"


def specifications():
    scope, projection = TOOL / "postgres-int4-range-v1.json", TOOL / "server-projection-v1.json"
    require(digest(scope) == CONTRACT and digest(projection) == PROJECTION, "postgres-int4-range-contract-mismatch")
    return json.loads(scope.read_text()), {"postgres": json.loads(projection.read_text())["profiles"]["postgres"]}


def prepare(engine, db):
    same({"status": "fixture"}, db.request({"fixture": [
        *fixtures(engine),
        "DROP OPERATOR IF EXISTS public.>= (integer, integer)",
        "DROP OPERATOR IF EXISTS public.<= (integer, integer)",
        "CREATE OR REPLACE FUNCTION public.ir_shadow_int4_range(integer, integer) RETURNS boolean LANGUAGE SQL IMMUTABLE AS 'SELECT false'",
        "CREATE OPERATOR public.>= (FUNCTION = public.ir_shadow_int4_range, LEFTARG = integer, RIGHTARG = integer)",
        "CREATE OPERATOR public.<= (FUNCTION = public.ir_shadow_int4_range, LEFTARG = integer, RIGHTARG = integer)",
    ]}))
    return {
        "shadow": db.request({"sql": "SELECT current_setting('search_path') AS path, 2 >= 1 AS lower, 2 <= 3 AS upper",
                              "shadowBuiltinOperators": True}),
        "restored": db.request({"sql": "SELECT current_setting('search_path') AS path, 2 >= 1 AS lower, 2 <= 3 AS upper"}),
    }


def check_preparation(_, environment, prepared):
    fields = [["path", "text"], ["lower", "bool"], ["upper", "bool"]]
    require(environment["database"][-1] == "pg_catalog, public", "postgres-int4-range-default-profile")
    same({"shadow": {"status": "rows", "fields": fields, "rows": [["public, pg_catalog", False, False]]},
          "restored": {"status": "rows", "fields": fields, "rows": [["pg_catalog, public", True, True]]}}, prepared)


def observer_request(request, sql):
    return {"sql": sql, **({"shadowBuiltinOperators": True} if request.get("shadow") else {})}


def required_forms(_engine, _tag):
    return {"source/unit", "source/only", "source/inherited", "placement/column", "placement/where",
            "placement/case", "placement/logic", "placement/null-test", "negated/True", "negated/False",
            "symmetric/True", "symmetric/False", "width/4", "lower-first", "upper-first",
            "boundary/minimum", "boundary/maximum",
            *(f"{position}/{kind}" for position in ("subject", "lower", "upper") for kind in ("null", "slot", "integer"))}


def sample_forms(forms, response, _engine, _item):
    def visit(value, placement):
        if value is None:
            return
        tag = value[0]
        if tag == "int4-range":
            _, negated, symmetric, subject, lower, upper = value
            forms["placement/" + placement] += 1
            forms[f"negated/{negated}"] += 1
            forms[f"symmetric/{symmetric}"] += 1
            for position, leaf in zip(("subject", "lower", "upper"), (subject, lower, upper)):
                forms[f"{position}/{leaf[0]}"] += 1
                if leaf == integer(-2147483648):
                    forms["boundary/minimum"] += 1
                if leaf == integer(2147483647):
                    forms["boundary/maximum"] += 1
            if lower[0] == upper[0] == "integer":
                order = int(lower[1]) - int(upper[1])
                forms["lower-first" if order < 0 else "upper-first" if order > 0 else "equal"] += 1
        elif tag == "int4-case":
            for condition, result in value[1]:
                visit(condition, "case")
                visit(result, "case")
            visit(value[2], "case")
        elif tag in ("logic", "null-test"):
            for nested in value[2:]:
                visit(nested, tag)
        else:
            require(tag in {"integer", "slot", "null"}, "postgres-int4-range-unknown-form", value)

    forms["source/" + source_role(response["source"])] += 1
    forms[f"width/{len(response['columns'])}"] += 1
    for value, _ in response["columns"]:
        visit(value, "column")
    visit(response["where"], "where")


def controls():
    one, two, three = integer(1), integer(2), integer(3)
    cases = [
        ("null-replaced", item("pg-17.2", "projection", int4_range(two, NULL, three), "unit"), {"rows": [[False]]}),
        ("lost-bool-type", item("pg-17.2", "projection", int4_range(one, one, three), "unit"), {"fields": [["result", "int4"]]}),
        ("deduplicated-rows", item("pg-17.2", "where", int4_range(["slot", 0], two, two), "only"), {"rows": [[2]]}),
    ]
    records = {}
    for name, request, delta in cases:
        correct = expected(request)
        check_observation(request, correct, "healthy")
        try:
            check_observation(request, {**correct, **delta}, name)
        except Failure as error:
            require(error.signal == "meaning-mismatch", "postgres-int4-range-control-signal", name)
            records[name] = {"input": request, **error.detail}
        else:
            raise Failure("postgres-int4-range-accepted-control", name)
    return records


def faults(_engine, tag):
    renderer = "sql-semantics-postgres/src/Ir/SelectSql.php"
    one, two, three = integer(1), integer(2), integer(3)
    return [
        RenderFault("exclude-lower-equality", renderer, "OPERATOR(pg_catalog.>=)", "OPERATOR(pg_catalog.>)",
                    item(tag, "projection", int4_range(two, two, three), "unit"), "meaning-mismatch"),
        RenderFault("omit-reversed-pair", renderer, "if ($range->symmetric)", "if (false)",
                    item(tag, "projection", int4_range(two, three, one, symmetric=True), "unit"), "meaning-mismatch"),
        RenderFault("substitute-lower-for-upper", renderer, "$this->integerOrNull($range->upper,", "$this->integerOrNull($range->lower,",
                    item(tag, "projection", int4_range(two, one, three), "unit"), "meaning-mismatch"),
        RenderFault("erase-unknown-range", renderer, "return $range->negated ? '(NOT ' . $interval . ')' : $interval;",
                    "return 'COALESCE(' . ($range->negated ? '(NOT ' . $interval . ')' : $interval) . ', FALSE)';",
                    item(tag, "projection", int4_range(two, NULL, three), "unit"), "meaning-mismatch"),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])
