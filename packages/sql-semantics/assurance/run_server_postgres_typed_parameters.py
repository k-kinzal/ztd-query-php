"""Compare declared positional input roles with native inferred types and projection values."""

import json
import sys

from evidence_transcript import canonical
from postgres_typed_parameter_cases import cast, coverage, expected, finite_id, finite_ids, ref, requests, select, shape
from run import TOOL, Failure, digest, require
from run_server import CONTRACT as PROJECTION, same
from run_server_join import main
from server_cases import observation
from server_parameter_cases import reconcile_catalog_rejections
from sqlite_domain import RenderFault

LABEL = "postgres-typed-parameters"
CONTRACT_NAME = "postgres-typed-parameters-v2.json"
CONTRACT = "ead84cd2f2f8292111a668ccefc0952b17815bc6ad3194cd6f889ce1ca1f5a83"
OBSERVER = "postgres_typed_parameter_observer.php"


def specifications():
    path, projection = TOOL / CONTRACT_NAME, TOOL / "server-projection-v1.json"
    require(digest(path) == CONTRACT and digest(projection) == PROJECTION, "postgres-typed-parameter-contract-mismatch")
    return reconcile_catalog_rejections(json.loads(path.read_text())), {"postgres": json.loads(projection.read_text())["profiles"]["postgres"]}


def check_value(item, response):
    require(response["status"] == "constructed" and response["profile"].startswith(item["release"] + "/"),
            "postgres-typed-parameter-positive-rejected", response)
    require(canonical(shape(item)) == canonical(shape(response)), "postgres-typed-parameter-shape-mismatch",
            {"expected": shape(item), "actual": shape(response)})


def observer_request(item, sql):
    return {"sql": sql, "bindings": item["bindings"]}


def check_observation(item, actual, sql):
    prediction = expected(item)
    require(canonical(prediction) == canonical(actual), "meaning-mismatch",
            {"sql": sql, "expected": prediction, "observed": actual})


def check_fault_witness(item, witness):
    same(expected(item), witness["expected"])
    require(witness["observed"]["status"] == "rows", "postgres-typed-parameter-fault-native")


def outsiders():
    return [{"sql": 'SELECT CAST($1 AS integer) AS a0; SELECT 1', "bindings": [0]},
            {"sql": 'SELECT CAST($1 AS bytea) AS a0', "bindings": [0]},
            {"sql": 'SELECT CAST($1 AS integer) AS a0', "bindings": []}]


def prepare(_, db):
    return [db.request(item) for item in outsiders()]


def check_preparation(_, environment, result):
    same("PREPARE without a supplied input signature", environment["binding"])
    same([{"status": "outside-observer-scope"}] * len(outsiders()), result)


def required_forms(_, _tag):
    return {"input/int4", "input/int8", "input/bool", "output/int4", "output/int8", "output/bool",
            "depth/0", "depth/1", "depth/2", "depth/3", "depth/4", "position/1", "position/2", "position/3",
            "position/4", "repeated-position", "union"}


def sample_forms(forms, response, _, _item):
    forms.update(coverage(response))


def controls():
    input_case = select("pg-17.2", [cast("int4", ref(1, "int8")), ref(2, "int4")], [9, 17])
    prediction = expected(input_case)
    check_observation(input_case, prediction, "known observation")
    controls = {
        "input-signature": {**prediction, "inputOids": ["23", "23"]},
        "slot-correspondence": {**prediction, "rows": [[17, 9]]},
        "output-type": {**prediction, "fields": [["a0", "int8"], ["a1", "int4"]]},
    }
    for name, observed in controls.items():
        try:
            check_observation(input_case, observed, name)
        except Failure as failure:
            require(failure.signal == "meaning-mismatch", "postgres-typed-parameter-wrong-control", name)
        else:
            raise Failure("postgres-typed-parameter-comparator-accepted", name)
    return {"case": input_case, "expected": prediction, "knownDifferences": controls}


def faults(_, tag):
    root = "sql-semantics-postgres/src/Ir/"
    renderer = root + "Binding/Parameter.php"
    cast_sql = "$expression instanceof BuiltinCast => 'CAST(' . $this->expression($expression->operand, $source, $offset, $qualify) . ' AS pg_catalog.' . $expression->target->value . ')'"
    marker = "$this->input->value"
    int4_marker = "'int4'"
    wide = ref(1, "int8")
    return [
        RenderFault("swap-typed-positions", renderer, "$this->position . ' AS", "(3 - $this->position) . ' AS",
                    select(tag, [ref(1, "int4"), ref(2, "int4")], [-13, 42]), "meaning-mismatch"),
        RenderFault("narrow-wide-marker", renderer, marker, int4_marker, select(tag, [wide], [9]), "meaning-mismatch"),
        RenderFault("erase-parameter-narrowing", root + "SelectSql.php", cast_sql,
                    "$expression instanceof BuiltinCast => $this->expression($expression->operand, $source, $offset, $qualify)",
                    select(tag, [cast("int4", wide)], [2147483648]), "meaning-mismatch"),
        RenderFault("change-input-only", renderer, marker, int4_marker,
                    select(tag, [cast("int4", wide)], [9]), "meaning-mismatch"),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])
