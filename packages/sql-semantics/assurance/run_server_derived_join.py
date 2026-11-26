"""Compare native derived join occurrences against their independently evaluated definitions."""

import json
import sys

from evidence_transcript import canonical
from run import TOOL, Failure, digest, require
from run_server import same
from run_server_join import OBSERVER, main, observer_request
from run_server_local_projection import check_preparation, prepare, specifications as local_specification
from server_cases import observation
from server_derived_join_cases import binary, derived, expected, finite_id, finite_ids, requests, structural_model
from server_join_cases import case, is_join, join, leaves, slot, table
from server_local_projection_cases import definition
from sqlite_domain import RenderFault

LABEL = "derived-join"
CONTRACT = "3d2b1652912fe098f37f7a11248f81c791762a690b799f53beac73678d1844ee"


def specifications():
    path = TOOL / "server-derived-join-v2.json"
    require(digest(path) == CONTRACT, "server-derived-join-contract-mismatch")
    _, profiles = local_specification()
    return json.loads(path.read_text()), profiles


def check_value(item, response):
    require(response["status"] == "constructed", "server-derived-join-positive-rejected", response)
    require(response["profile"].startswith(item["release"] + "/"), "server-derived-join-profile-mismatch", response)
    if item["dialect"] == "mysql":
        suffix = "/materialized" if item["release"].startswith("mysql-5.6.") else "/derived-merge=on"
        require(response["profile"].endswith(suffix), "server-derived-join-optimizer-mismatch", response)
    prediction = structural_model(item)
    actual = {field: response[field] for field in prediction}
    require(canonical(prediction) == canonical(actual), "server-derived-join-shape-mismatch", {"expected": prediction, "actual": actual})


def check_observation(item, actual, sql):
    prediction = expected(item)
    witness = {"sql": sql, "expected": prediction, "observed": actual}
    require(actual["status"] == "rows", "server-derived-join-native-rejection", witness)
    require(canonical(prediction) == canonical(actual), "meaning-mismatch", witness)


def check_fault_witness(item, witness):
    same(expected(item), witness["expected"])
    require(witness["observed"]["status"] == "rows", "server-derived-join-fault-native")


def required_forms(engine, _tag):
    kinds = {"CROSS", "INNER", "LEFT", "RIGHT"} | ({"FULL"} if engine == "postgres" else set())
    return {"derived/left", "derived/right", "definition/external", "definition/literal", "filter/True", "filter/False",
            "distinct/True", "distinct/False", "width/binary", "width/nested", "scope/shared-names", "projection/high-slot",
            "projection/first", "projection/later", *(f"kind/{kind}" for kind in kinds)}


def sample_forms(forms, response, _engine, _item):
    source = response["source"]
    inputs = leaves(source)
    forms["width/" + ("binary" if len(inputs) == 2 else "nested")] += 1
    if sum("derived" in leaf for leaf in inputs) >= 2:
        forms["scope/shared-names"] += 1

    def visit(node):
        if not is_join(node):
            return
        forms["kind/" + node["kind"]] += 1
        for side in ("left", "right"):
            child = node[side]
            if "derived" in child:
                inner = child["derived"]
                forms["derived/" + side] += 1
                forms["definition/" + ("literal" if inner["source"] is None else "external")] += 1
                forms[f"filter/{inner['where'] is not None}"] += 1
                forms[f"distinct/{inner['distinct']}"] += 1
            visit(child)

    visit(source)
    for expression, _ in response["columns"]:
        forms["projection/" + ("later" if len(expression) == 3 else "first")] += 1
        if expression[1] > 1:
            forms["projection/high-slot"] += 1


def controls():
    item = binary("postgres", "pg-17.2", None, "right", "LEFT", (1, 0))
    prediction = expected(item)
    check_observation(item, prediction, "known derived rows")
    altered = {
        "changed-result-name": {**prediction, "fields": [["wrong", "int4"], prediction["fields"][1]]},
        "lost-type-tag": {**prediction, "fields": [["r0", "int8"], prediction["fields"][1]]},
        "missing-unmatched-row": {**prediction, "rows": prediction["rows"][1:]},
    }
    detected = {}
    for name, actual in altered.items():
        try:
            check_observation(item, actual, name)
        except Failure as failure:
            require(failure.signal == "meaning-mismatch", "server-derived-join-wrong-control-signal", name)
            detected[name] = failure.signal
        else:
            raise Failure("server-derived-join-comparator-accepted", name)
    return {"input": item, "prediction": prediction, "mismatches": altered, "detections": detected}


def faults(engine, tag):
    target = f"sql-semantics-{engine}/src/Ir/SelectSql.php"
    first = derived(definition(engine, tag, None))
    second = derived(definition(engine, tag, "accounts"))
    both = case(engine, tag, join("CROSS", first, second), [[slot(1, 1), "value"]])
    return [
        RenderFault("wrong-derived-occurrence", target, "$this->alias($offset + $expression->occurrence)", "$this->alias($offset)",
                    both, "meaning-mismatch"),
        RenderFault("wrong-definition-ordinal", target,
                    "$name = $this->localColumn($expression->slot," if engine == "mysql" else "$name = $this->localColumn($expression->slot);",
                    "$name = $this->localColumn(0," if engine == "mysql" else "$name = $this->localColumn(0);",
                    binary(engine, tag, None, "right", "CROSS", (1, 0)), "meaning-mismatch"),
        RenderFault("remove-derived-null-extension", target, "$source->kind->value . ' JOIN '",
                    "($source->kind->value === 'LEFT' ? 'INNER' : $source->kind->value) . ' JOIN '",
                    binary(engine, tag, "empty", "right", "LEFT", (1, 0)), "meaning-mismatch"),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])
