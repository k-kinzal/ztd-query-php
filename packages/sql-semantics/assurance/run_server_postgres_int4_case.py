"""Compare the ordered PostgreSQL searched CASE to native typed results."""

import json
import sys

from postgres_int4_case_cases import NULL, case, decision, finite_id, finite_ids, requests
from postgres_int4_cases import integer, projection, source_role
from run import TOOL, Failure, digest, require
from run_server import CONTRACT as PROJECTION, same
from run_server_join import main
from run_server_postgres_int4 import check_fault_witness, check_observation, check_value, observer_request
from server_cases import expected, fixtures, observation
from sqlite_domain import RenderFault

LABEL = "postgres-int4-case"
CONTRACT = "7eb2ce7eefebccb894a7b47ce725a08d381978f2ff235a7475533f074da1620b"
OBSERVER = "server_observer.php"


def specifications():
    path, projection_contract = TOOL / "postgres-int4-case-v1.json", TOOL / "server-projection-v1.json"
    require(digest(path) == CONTRACT and digest(projection_contract) == PROJECTION, "postgres-int4-case-contract-mismatch")
    projection_profiles = json.loads(projection_contract.read_text())["profiles"]
    return json.loads(path.read_text()), {"postgres": projection_profiles["postgres"]}


def prepare(engine, db):
    result = db.request({"fixture": fixtures(engine)})
    same({"status": "fixture"}, result)
    return result


def check_preparation(_, environment, prepared):
    require(environment["database"][-1] == "pg_catalog, public", "postgres-int4-case-default-profile")
    same({"status": "fixture"}, prepared)


def required_forms(_engine, _tag):
    return {"source/unit", "source/only", "source/inherited", "arms/1", "arms/2", "arms/3", "arms/4",
            "condition/boolean", "condition/null", "condition/comparison", "condition/logic",
            "result/null", "result/integer", "result/slot", "result/operation", "result/int4-case",
            "else/omitted", "else/typed-null", "else/present", "nested"}


def sample_forms(forms, response, _engine, _item):
    def visit(value, height):
        tag = value[0]
        if tag == "operation":
            for operand in value[2:]:
                visit(operand, height + 1)
        if tag != "int4-case":
            return
        if height:
            forms["nested"] += 1
        _, arms, otherwise = value
        forms[f"arms/{len(arms)}"] += 1
        for condition, result in arms:
            forms["condition/" + condition[0]] += 1
            forms["result/" + result[0]] += 1
            visit(result, height + 1)
        form = "omitted" if otherwise is None else "typed-null" if otherwise == NULL else "present"
        forms["else/" + form] += 1
        if otherwise is not None:
            visit(otherwise, height + 1)

    forms["source/" + source_role(response["source"])] += 1
    for value, _ in response["columns"]:
        visit(value, 0)


def controls():
    item = projection("pg-17.2", case([(NULL, integer(2)), (decision(True), integer(7))], integer(11)))
    correct = expected(item)
    controls = {
        "null-admitted": {**correct, "rows": [[2]]},
        "else-selected-early": {**correct, "rows": [[11]]},
        "type-widened": {**correct, "fields": [["result", "int8"]]},
        "label-replaced": {**correct, "fields": [["renamed", "int4"]]},
    }
    check_observation(item, correct, "healthy")
    records = {}
    for name, observation in controls.items():
        try:
            check_observation(item, observation, name)
        except Failure as error:
            require(error.signal == "meaning-mismatch", "postgres-int4-case-control-signal", name)
            records[name] = {"input": item, **error.detail}
        else:
            raise Failure("postgres-int4-case-accepted-control", name)
    return records


def faults(_engine, tag):
    path = "sql-semantics-postgres/src/Ir/SelectSql.php"
    yes = decision(True)
    no = decision(False)
    condition = "$this->predicate($arm->condition, $source, $offset, $qualify)"
    return [
        RenderFault("reverse-case-order", path, "foreach ($expression->arms as $arm)",
                    "foreach (array_reverse($expression->arms) as $arm)",
                    projection(tag, case([(yes, integer(2)), (yes, integer(7))])), "meaning-mismatch"),
        RenderFault("admit-case-unknown", path, condition,
                    f"('(' . {condition} . ') IS NOT FALSE')",
                    projection(tag, case([(NULL, integer(2))], integer(7))), "meaning-mismatch"),
        RenderFault("drop-case-otherwise", path, "if ($expression->otherwise !== null)", "if (false)",
                    projection(tag, case([(no, integer(2))], integer(7))), "meaning-mismatch"),
        RenderFault("widen-case-result", path, "return $sql . ' END)';",
                    "return $sql . ' END)::pg_catalog.int8';",
                    projection(tag, case([(yes, NULL)])), "meaning-mismatch"),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])
