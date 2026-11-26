"""Compare ordered MySQL signed ranges and native metadata on all pinned releases."""

import json
import sys

from evidence_transcript import canonical
from mysql_signed_range_cases import MAXIMUM, MINIMUM, NULL, finite_id, finite_ids, item, literal, requests, signed_range, source_role
from run import TOOL, Failure, digest, require
from run_server import CONTRACT as PROJECTION, same
from run_server_join import main
from server_cases import expected as projection_expected, fixtures, observation
from sqlite_domain import RenderFault

LABEL = "mysql-signed-range"
CONTRACT_NAME = "mysql-signed-range-v3.json"
CONTRACT = "2c2a318ddc782dd6052e9e9935880ce64c83d7106b0a85867adf70ddd42a35be"
OBSERVER = "server_observer.php"


def specifications():
    path, profile = TOOL / CONTRACT_NAME, TOOL / "server-projection-v1.json"
    require(digest(path) == CONTRACT and digest(profile) == PROJECTION, "mysql-signed-range-contract-mismatch")
    return json.loads(path.read_text()), {"mysql": json.loads(profile.read_text())["profiles"]["mysql"]}


def prepare(engine, db):
    same({"status": "fixture"}, db.request({"fixture": fixtures(engine)}))
    return db.request(observer_request(None, "SELECT 2 BETWEEN 2 AND 2 AS inclusive, 2 NOT BETWEEN NULL AND 3 AS unknown_range"))


def check_preparation(_, environment, prepared):
    require(environment["database"][2] == "", "mysql-signed-range-profile")
    same({"status": "rows", "fields": [["inclusive", "LONGLONG"], ["unknown_range", "LONGLONG"]],
          "rows": [[1, None]], "warningCount": 0}, prepared)


def observer_request(_, sql):
    return {"sql": sql, "observeWarnings": True}


def expected(request):
    return {**projection_expected(request), "warningCount": 0}


def check_value(request, response):
    require(response["status"] == "constructed" and response["profile"].startswith(request["release"] + "/"),
            "mysql-signed-range-positive-rejected", response)
    require(all(canonical(request[key]) == canonical(response[key]) for key in ("source", "columns", "where")),
            "mysql-signed-range-structure-mismatch", {"expected": request, "actual": response})


def check_observation(request, actual, sql):
    prediction = expected(request)
    require(canonical(prediction) == canonical(actual), "meaning-mismatch",
            {"sql": sql, "expected": prediction, "observed": actual})


def check_fault_witness(request, witness):
    same(expected(request), witness["expected"])
    require(witness["observed"]["status"] == "rows", "mysql-signed-range-fault-native")


def required_forms(_engine, _tag):
    return {"scope/unit", "scope/table", "placement/column", "placement/where", "placement/logic", "placement/null-test",
            "flag/True", "flag/False", "padding", "negative-zero", "limit/minimum", "limit/maximum", "limit/non-binary64",
            "order/reversed", "order/equal", "width/4",
            *(f"{name}/{role}" for name in ("subject", "lower", "upper") for role in ("range-integer", "null", "slot"))}


def sample_forms(forms, response, _engine, _item):
    def visit(value, placement):
        if value is None:
            return
        if value[0] in ("logic", "null-test"):
            for nested in value[2:]:
                visit(nested, value[0])
            return
        require(value[0] == "signed-range", "mysql-signed-range-unregistered-shape", value)
        _, negated, subject, lower, upper = value
        forms["placement/" + placement] += 1
        forms[f"flag/{negated}"] += 1
        for name, atom in zip(("subject", "lower", "upper"), (subject, lower, upper)):
            forms[f"{name}/{atom[0]}"] += 1
            if atom[0] == "range-integer":
                number, padding, negative_zero = int(atom[1]), atom[2], atom[3]
                limits = {MINIMUM: "minimum", MAXIMUM: "maximum", 9007199254740993: "non-binary64"}
                if number in limits:
                    forms["limit/" + limits[number]] += 1
                if padding:
                    forms["padding"] += 1
                if negative_zero:
                    forms["negative-zero"] += 1
        if lower[0] == upper[0] == "range-integer":
            difference = int(lower[1]) - int(upper[1])
            order = "reversed" if difference > 0 else "equal" if difference == 0 else "normal"
            forms["order/" + order] += 1

    forms["scope/" + source_role(response["source"])] += 1
    forms[f"width/{len(response['columns'])}"] += 1
    for expression, _ in response["columns"]:
        visit(expression, "column")
    visit(response["where"], "where")


def controls():
    tag = "mysql-9.1.0"
    choice = item(tag, "projection", signed_range(literal(2), NULL, literal(3)), "unit")
    filtered = item(tag, "where", signed_range(["slot", 0], literal(2), literal(2)), "table")
    records = {}
    for name, request, delta in (
        ("null-erased", choice, {"rows": [[0]]}),
        ("native-type-lost", choice, {"fields": [["r0", "LONG"]]}),
        ("warning-hidden", choice, {"warningCount": 1}),
        ("duplicate-filtered-row-lost", filtered, {"rows": [[2]]}),
    ):
        correct = expected(request)
        check_observation(request, correct, "healthy")
        try:
            check_observation(request, {**correct, **delta}, name)
        except Failure as error:
            require(error.signal == "meaning-mismatch", "mysql-signed-range-control-signal", name)
            records[name] = {"input": request, **error.detail}
        else:
            raise Failure("mysql-signed-range-accepted-control", name)
    return records


def faults(_, tag):
    renderer = "sql-semantics-mysql/src/Ir/SelectSql.php"
    subject = "$this->rangeOperand($range->subject, $source, $offset, $qualify)"
    decision = "'(' . $subject . ($range->negated ? ' NOT BETWEEN ' : ' BETWEEN ') . $lower . ' AND ' . $upper . ')'"
    one, two, three = map(literal, (1, 2, 3))
    wide = literal(9007199254740992)
    return [
        RenderFault("exchange-range-bounds", renderer, "$lower . ' AND ' . $upper", "$upper . ' AND ' . $lower",
                    item(tag, "projection", signed_range(two, one, three), "unit"), "meaning-mismatch"),
        RenderFault("strip-range-negation", renderer, "($range->negated ? ' NOT BETWEEN ' : ' BETWEEN ')", "' BETWEEN '",
                    item(tag, "projection", signed_range(two, one, three, True), "unit"), "meaning-mismatch"),
        RenderFault("narrow-range-precision", renderer, subject, f"'(' . {subject} . ' + 0e0)'",
                    item(tag, "projection", signed_range(literal(9007199254740993), wide, wide), "unit"), "meaning-mismatch"),
        RenderFault("zero-instead-of-unknown-range", renderer, decision, f"'COALESCE(' . {decision} . ', 0)'",
                    item(tag, "projection", signed_range(two, one, NULL), "unit"), "meaning-mismatch"),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])
