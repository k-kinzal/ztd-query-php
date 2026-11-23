"""Compare native set expressions with independent typed bag operations."""

import json
import sys

from evidence_transcript import canonical
from run import TOOL, Failure, digest, require
from run_server import CONTRACT as PROJECTION, same
from run_server_join import CONTRACT as JOIN, OBSERVER, check_preparation, main, observer_request, prepare
from server_compound_cases import OPERATORS, case, expected as model, finite_id, finite_ids, leaf, legacy, observation, pair, requests, without_profiles
from server_delete_cases import literal
from sqlite_domain import RenderFault

LABEL = "compound"
CONTRACT = "dbbf8f67d81e09156521992de45ac350e7dffdac774200f54fc80511c8d27bf8"


def specifications():
    path, projection, join = (TOOL / filename for filename in ("server-compound-v2.json", "server-projection-v1.json", "server-join-v2.json"))
    require(all(digest(path) == expected for path, expected in ((path, CONTRACT), (projection, PROJECTION), (join, JOIN))),
            "server-compound-contract-mismatch")
    return json.loads(path.read_text()), json.loads(projection.read_text())["profiles"]


def expected(item):
    specification, _ = specifications()
    return model(item, specification["mysqlNullOnlyTags"])


def check_value(item, response):
    require(response["status"] == "constructed", "server-compound-positive-rejected", response)
    require(response["profile"].startswith(item["release"] + "/"), "server-compound-profile-mismatch", response)
    require(without_profiles(item)["compound"] == response["compound"], "server-compound-shape-mismatch", response)


def check_observation(item, actual, sql):
    model = expected(item)
    witness = {"sql": sql, "expected": model, "observed": actual}
    require(actual["status"] == "rows", "server-compound-native-rejection", witness)
    require(canonical(model) == canonical(actual), "meaning-mismatch", witness)


def check_fault_witness(item, witness):
    same(expected(item), witness["expected"])
    require(witness["observed"]["status"] == "rows", "server-compound-fault-native")


def required_forms(engine, tag):
    operators = OPERATORS[:1] if legacy(tag) else OPERATORS
    return {"operator/" + role for role in operators} | {"all/True", "all/False", "nested/left", "width/1", "width/2", "width/3",
           "source/table", "source/scalar", "source/filtered"} | ({"nested/right"} if not legacy(tag) else set())


def sample_forms(forms, response, engine, _item):
    def visit(query):
        if "compound" not in query:
            forms["source/" + ("table" if query["source"] is not None else "scalar")] += 1
            if query["where"] is not None:
                forms["source/filtered"] += 1
            return len(query["columns"])
        operation = query["compound"]
        forms["operator/" + operation["operator"]] += 1
        forms[f"all/{operation['all']}"] += 1
        for side in ("left", "right"):
            if "compound" in operation[side]:
                forms["nested/" + side] += 1
        width = visit(operation["left"])
        visit(operation["right"])
        return width

    forms[f"width/{visit(response)}"] += 1


def controls():
    tag = "pg-17.2"
    a, b = pair("postgres", tag, 0, 1)
    item = case("postgres", tag, a, "UNION", True, b)
    prediction = expected(item)
    check_observation(item, prediction, "known additive bag")
    altered = {
        "lost-multiplicity": {**prediction, "rows": [[1]]},
        "renamed-first-column": {**prediction, "fields": [["b0", "int4"]]},
        "lost-type": {**prediction, "fields": [["a0", "int8"]]},
    }
    detected = {}
    for name, value in altered.items():
        try:
            check_observation(item, value, name)
        except Failure as failure:
            require(failure.signal == "meaning-mismatch", "server-compound-wrong-control-signal", name)
            detected[name] = failure.signal
        else:
            raise Failure("server-compound-comparator-accepted", name)
    return {"input": item, "prediction": prediction, "mismatches": altered, "detections": detected}


def faults(engine, tag):
    path = f"sql-semantics-{engine}/src/Ir/Rows/"
    a, b = pair(engine, tag, 0, 1)
    c = leaf(engine, tag, [[literal(3), "c0"]])
    normal_tail = "CompoundShape::sql($this->right)"
    output = "$left . ' ' . $this->operator->value . ($this->all ? ' ALL ' : ' ') . " + normal_tail
    reversed_query = normal_tail + " . ' ' . $this->operator->value . ($this->all ? ' ALL ' : ' ') . $left"
    results = [
        RenderFault("swap-compound-operands", path + "Compound.php", output, reversed_query,
                    case(engine, tag, a, "UNION", True, c), "meaning-mismatch"),
        RenderFault("collapse-union-all", path + "Compound.php", "$this->all ? ' ALL ' : ' '", "false ? ' ALL ' : ' '",
                    case(engine, tag, a, "UNION", True, b), "meaning-mismatch"),
    ]
    if legacy(tag):
        results.append(RenderFault("replace-right-union-operand", path + "Compound.php", normal_tail, "CompoundShape::sql($this->left)",
                                   case(engine, tag, a, "UNION", True, c), "meaning-mismatch"))
    else:
        grouping = "return $query instanceof Compound ? '(' . $sql . ')' : $sql;"
        results.append(RenderFault("erase-right-compound-group", path + "CompoundShape.php", grouping, "return $sql;",
                                   case(engine, tag, c, "UNION", True, case(engine, tag, a, "EXCEPT", True, c)), "meaning-mismatch"))
    return results


if __name__ == "__main__":
    main(sys.modules[__name__])
