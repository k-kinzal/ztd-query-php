"""Observe signed MySQL CAST projections with native protocol flags and values."""

import json
import sys

from evidence_transcript import canonical
from mysql_signed_cast_cases import arithmetic, expected, finite_id, finite_ids, leaf, requests, role_of, single
from run import TOOL, Failure, digest, require
from run_server import CONTRACT as PROJECTION, same
from run_server_join import main
from server_cases import fixtures, observation
from sqlite_domain import RenderFault

LABEL = "mysql-signed-casts"
CONTRACT_NAME = "mysql-signed-casts-v2.json"
CONTRACT = "b6a092682ba55ea4ca044c0131d35612de5cb42a8d85130e2834bb8ea395c069"
OBSERVER = "mysql_cast_observer.php"


def specifications():
    scope, profile = TOOL / CONTRACT_NAME, TOOL / "server-projection-v1.json"
    require(digest(scope) == CONTRACT and digest(profile) == PROJECTION, "mysql-signed-casts-contract-mismatch")
    return json.loads(scope.read_text()), {"mysql": json.loads(profile.read_text())["profiles"]["mysql"]}


def prepare(engine, db):
    return db.request({"fixture": fixtures(engine)})


def check_preparation(_, environment, preparation):
    same({"status": "fixture"}, preparation)
    require(environment["client"].lower().startswith("mysqlnd"), "mysql-signed-casts-observer-client", environment)


def observer_request(_, sql):
    return {"sql": sql}


def check_value(item, response):
    require(response["status"] == "constructed" and response["profile"].startswith(item["release"] + "/"),
            "mysql-signed-casts-positive-rejected", response)
    structure = {key: item[key] for key in ("columns", "source", "where", "distinct")}
    actual = {key: response[key] for key in structure}
    require(canonical(structure) == canonical(actual), "mysql-signed-casts-structure-mismatch",
            {"expected": structure, "actual": actual})


def check_observation(item, actual, sql):
    prediction = expected(item)
    require(canonical(prediction) == canonical(actual), "meaning-mismatch",
            {"sql": sql, "expected": prediction, "observed": actual})


def check_fault_witness(item, witness):
    same(expected(item), witness["expected"])
    require(witness["observed"]["status"] == "rows", "mysql-signed-casts-fault-native-rows")


def required_forms(_, _tag):
    return {"source/unit", "source/table", "leaf/slot/0", "leaf/slot/1",
            "leaf/integer", "leaf/boolean", "leaf/null", "depth/6", "width/6", "distinct/False", "distinct/True",
            "operation/+", "operation/-", "operation/*"}


def sample_forms(forms, response, _engine, _item):
    forms["source/" + role_of(response["source"])] += 1
    forms[f"width/{len(response['columns'])}"] += 1
    forms[f"distinct/{response['distinct']}"] += 1
    for expression, _ in response["columns"]:
        depth = 0
        while expression[0] == "signed-cast":
            expression = expression[1]
            depth += 1
        forms[f"depth/{depth}"] += 1
        role = expression[0]
        if role == "operation":
            forms["operation/" + expression[1]] += 1
        else:
            forms["leaf/" + (f"slot/{expression[1]}" if role == "slot" else role)] += 1


def controls():
    witness = single("mysql-8.4.7", leaf(1), "table")
    sound = expected(witness)
    check_observation(witness, sound, "healthy")
    records = {}
    for name, error in (
        ("unsigned-flag", {"fields": [["result", 8, True]]}),
        ("wrong-width", {"fields": [["result", 3, False]]}),
        ("wrong-field-name", {"fields": [["changed", 8, False]]}),
        ("lost-repetition", {"rows": [[1]]}),
    ):
        try:
            check_observation(witness, {**sound, **error}, name)
        except Failure as failure:
            require(failure.signal == "meaning-mismatch", "mysql-signed-casts-control-signal", name)
            records[name] = {"input": witness, **failure.detail}
        else:
            raise Failure("mysql-signed-casts-control-accepted", name)
    return records


def faults(_engine, tag):
    renderer = "sql-semantics-mysql/src/Ir/SelectSql.php"
    operand = "$this->expression($expression->operand, $source, $offset, $qualify)"
    arm = f"$expression instanceof SignedCast => 'CAST(' . {operand} . ' AS SIGNED)'"
    return [
        RenderFault("replace-signed-with-unsigned", renderer, arm, arm.replace("AS SIGNED", "AS UNSIGNED"),
                    single(tag, leaf(-2)), "meaning-mismatch"),
        RenderFault("emit-untyped-null", renderer, arm, "$expression instanceof SignedCast => 'NULL'",
                    single(tag, leaf(None)), "meaning-mismatch"),
        RenderFault("replace-cast-input-slot", renderer, arm,
                    arm.replace("$expression->operand", "new ColumnReference(1 - $expression->operand->slot)"),
                    single(tag, ["slot", 0], "table"), "meaning-mismatch"),
        RenderFault("drop-cast-operand-arithmetic", renderer, arm, arm.replace(operand, "'2'"),
                    single(tag, arithmetic("*")), "meaning-mismatch"),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])
