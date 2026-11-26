"""Compare unsigned MySQL scalar and compound results using native signedness."""

import json
import sys

from evidence_transcript import canonical
from mysql_unsigned_cast_cases import (
    S, U, arms, expected, finite_id, finite_ids, leaf, names_and_kinds, one, requests, role_of, signed, unsigned,
)
from run import TOOL, Failure, digest, require
from run_server import CONTRACT as PROJECTION, same
from run_server_compound import without_profiles
from run_server_join import main
from run_server_mysql_signed_casts import check_preparation, observer_request, prepare
from server_cases import observation
from sqlite_domain import RenderFault

LABEL = "mysql-unsigned-casts"
CONTRACT_NAME = "mysql-unsigned-casts-v4.json"
CONTRACT = "3de3c3690474f785b177629264d836df01cdcf64f5487c5213033b70e98c1b0e"
OBSERVER = "mysql_cast_observer.php"


def specifications():
    scope, profiles = TOOL / CONTRACT_NAME, TOOL / "server-projection-v1.json"
    require(digest(scope) == CONTRACT and digest(profiles) == PROJECTION, "mysql-unsigned-casts-contract-mismatch")
    return json.loads(scope.read_text()), {"mysql": json.loads(profiles.read_text())["profiles"]["mysql"]}


def check_value(item, response):
    require(response["status"] == "constructed" and response["profile"].startswith(item["release"] + "/"),
            "mysql-unsigned-casts-positive-rejected", response)
    if "compound" in item:
        require(without_profiles(item)["compound"] == response["compound"], "mysql-unsigned-casts-compound-shape", response)
        require([kind for _, kind in names_and_kinds(item)] == response["resultKinds"], "mysql-unsigned-compound-result-kind", response)
    else:
        expected_shape = {key: item[key] for key in ("columns", "source", "where", "distinct")}
        actual = {key: response[key] for key in expected_shape}
        require(expected_shape == actual, "mysql-unsigned-casts-scalar-shape", response)


def check_observation(item, actual, sql):
    prediction = expected(item)
    require(canonical(prediction) == canonical(actual), "meaning-mismatch", {
        "sql": sql, "expected": prediction, "observed": actual,
    })


def check_fault_witness(item, witness):
    same(expected(item), witness["expected"])
    require(witness["observed"]["status"] == "rows", "mysql-unsigned-cast-fault-native-rows")


def required_forms(_, _tag):
    return {
        "source/unit", "source/table", "width/1", "width/6", "cast/" + S, "cast/" + U, "outer/" + S, "outer/" + U,
        "leaf/integer", "leaf/range-integer", "leaf/boolean", "leaf/null", "leaf/slot/0", "leaf/slot/1",
        "depth/6", "operation/+", "operation/-", "operation/*", "distinct/True", "distinct/False",
    }


def sample_forms(forms, response, _engine, _item):
    forms["source/" + role_of(response["source"])] += 1
    forms[f"width/{len(response['columns'])}"] += 1
    forms[f"distinct/{response['distinct']}"] += 1
    for expression, _ in response["columns"]:
        depth = 0
        forms["outer/" + expression[0]] += 1
        while expression[0] in (S, U):
            forms["cast/" + expression[0]] += 1
            expression = expression[1]
            depth += 1
        forms[f"depth/{depth}"] += 1
        role = expression[0]
        if role == "operation":
            forms["operation/" + expression[1]] += 1
        else:
            forms["leaf/" + (f"slot/{expression[1]}" if role == "slot" else role)] += 1


def controls():
    item = arms("mysql-8.4.7", "unsigned-bound", "signed-minimum", True)
    healthy = expected(item)
    check_observation(item, healthy, "healthy mixed reference")
    cases = {}
    for name, change in (
        ("lost-decimal-kind", {**healthy, "fields": [["result", 8, True]]}),
        ("lost-sign", {**healthy, "rows": [[str(1 << 63)], [str((1 << 64) - 1)]]}),
        ("lost-left-label", {**healthy, "fields": [["right_result", 246, False]]}),
    ):
        try:
            check_observation(item, change, name)
        except Failure as result:
            require(result.signal == "meaning-mismatch", "mysql-unsigned-cast-comparator-control", result.detail)
            cases[name] = result.detail
        else:
            raise Failure("mysql-unsigned-cast-comparator-accepted", name)
    duplicate = arms("mysql-8.4.7", "unsigned-zero", "signed-zero", True)
    reduced = {**expected(duplicate), "rows": [["0"]]}
    try:
        check_observation(duplicate, reduced, "lost-multiplicity")
    except Failure as result:
        require(result.signal == "meaning-mismatch", "mysql-unsigned-cast-comparator-control", result.detail)
        cases["lost-multiplicity"] = result.detail
    else:
        raise Failure("mysql-unsigned-cast-comparator-accepted", "lost-multiplicity")
    return cases


def faults(_engine, tag):
    renderer = "sql-semantics-mysql/src/Ir/SelectSql.php"
    set_sql = "sql-semantics-mysql/src/Ir/Rows/Compound.php"
    operand = "$this->expression($expression->operand, $source, $offset, $qualify)"
    unsigned_arm = f"$expression instanceof UnsignedCast => 'CAST(' . {operand} . ' AS UNSIGNED)'"
    signed_arm = unsigned_arm.replace("UnsignedCast", "SignedCast").replace("AS UNSIGNED", "AS SIGNED")
    original = "$left . ' ' . $this->operator->value . ($this->all ? ' ALL ' : ' ') . CompoundShape::sql($this->right)"
    swapped = "CompoundShape::sql($this->right) . ' ' . $this->operator->value . ($this->all ? ' ALL ' : ' ') . $left"
    return [
        RenderFault("replace-unsigned-target", renderer, unsigned_arm, unsigned_arm.replace("AS UNSIGNED", "AS SIGNED"),
                    one(tag, unsigned(leaf(-2))), "meaning-mismatch"),
        RenderFault("drop-unsigned-conversion-input", renderer, unsigned_arm, unsigned_arm.replace(operand, "'0'"),
                    one(tag, unsigned(leaf(-3))), "meaning-mismatch"),
        RenderFault("drop-outer-signed-target", renderer, signed_arm, "$expression instanceof SignedCast => " + operand,
                    one(tag, signed(unsigned(leaf(-1)))), "meaning-mismatch"),
        RenderFault("read-other-unsigned-input", renderer, unsigned_arm,
                    unsigned_arm.replace("$expression->operand", "new ColumnReference(1 - $expression->operand->slot)"),
                    one(tag, unsigned(["slot", 0]), "table"), "meaning-mismatch"),
        RenderFault("reverse-mixed-unsigned-union", set_sql, original, swapped,
                    arms(tag, "unsigned-bound", "signed-minimum", True), "meaning-mismatch"),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])
