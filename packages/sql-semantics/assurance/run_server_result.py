"""Compare native DISTINCT, positional order and bounds with the tie-band contract."""

import json
import sys
from collections import Counter

from evidence_transcript import canonical
from run import TOOL, Failure, digest, require
from run_server import CONTRACT as PROJECTION, same
from run_server_join import CONTRACT as JOIN
from run_server_join import OBSERVER, check_preparation, main, observer_request, prepare
from server_join_cases import is_join, slot, table
from server_result_cases import case, expected, finite_id, finite_ids, match, order, requests
from sqlite_domain import RenderFault

LABEL = "result"
CONTRACT = "9c2b6f1012a17b4d12adc032c2a7ab89d7e66a5ee64d6648d30b1feb13fb06d6"


def specifications():
    path, projection = TOOL / "server-result-v2.json", TOOL / "server-projection-v1.json"
    require(digest(path) == CONTRACT and digest(projection) == PROJECTION
            and digest(TOOL / "server-join-v2.json") == JOIN, "server-result-contract-mismatch")
    return json.loads(path.read_text()), json.loads(projection.read_text())["profiles"]


def check_value(item, response):
    require(response["status"] == "constructed", "server-result-positive-rejected", response)
    require(response["profile"].startswith(item["release"] + "/"), "server-result-profile-mismatch", response)
    for role in ("source", "columns", "where", "distinct", "orderBy", "limit"):
        require(canonical(item[role]) == canonical(response[role]), "server-result-shape-mismatch",
                {"role": role, "expected": item[role], "actual": response[role]})


def observation(witness):
    return witness


def check_observation(item, actual, sql):
    match(expected(item), actual, sql)


def check_fault_witness(item, witness):
    same(expected(item), witness["expected"])
    require(witness["observed"]["status"] == "rows", "server-result-fault-native")


def required_forms(_engine, _tag):
    return {"rows/repeated", "rows/distinct", "null/first", "null/last", "order/single", "order/multiple",
            "limit/tie-cut", "source/joined", "source/table", "projection/repeated-label"}


def sample_forms(forms, response, engine, _):
    plan = expected({**response, "dialect": engine})
    eligible = [row for band in plan["bands"] for row in band["members"]]
    if len(eligible) > len({canonical(row) for row in eligible}):
        forms["rows/repeated"] += 1
    if len({canonical(row) for row in eligible}) >= 2 and response["distinct"]:
        forms["rows/distinct"] += 1
    keys = response["orderBy"]
    if keys:
        forms["order/" + ("single" if len(keys) == 1 else "multiple")] += 1
        for position, _, first in keys:
            if any(row[position - 1] is None for row in eligible) and any(row[position - 1] is not None for row in eligible):
                forms["null/" + ("first" if first else "last")] += 1
        if any(0 < band["multiplicity"] < len(band["members"]) for band in plan["bands"]):
            forms["limit/tie-cut"] += 1
    forms["source/" + ("joined" if is_join(response["source"]) else "table")] += 1
    labels = Counter(name for _, name in response["columns"])
    if max(labels.values()) > 1:
        forms["projection/repeated-label"] += 1


def controls():
    fields = [["k", "int4"], ["payload", "int4"]]
    prediction = {"fields": fields, "total": 4, "bands": [
        {"multiplicity": 2, "members": [[1, 20], [1, 21], [1, 21]]},
        {"multiplicity": 1, "members": [[2, 30]]},
        {"multiplicity": 1, "members": [[None, 99]]},
    ]}
    healthy = {"status": "rows", "fields": fields, "rows": [[1, 21], [1, 20], [2, 30], [None, 99]]}
    other_valid = {**healthy, "rows": [[1, 21], [1, 21], [2, 30], [None, 99]]}
    match(prediction, healthy, "ordered control")
    match(prediction, other_valid, "alternate tie selection")
    errors = {
        "lost-duplicate-count": {**other_valid, "rows": other_valid["rows"][1:]},
        "out-of-band-position": {**healthy, "rows": [[2, 30], [1, 20], [1, 21], [None, 99]]},
        "ignored-null-placement": {**healthy, "rows": [[None, 99], [1, 21], [1, 20], [2, 30]]},
        "excess-tie-multiplicity": {**healthy, "rows": [[1, 20], [1, 20], [2, 30], [None, 99]]},
        "changed-name": {**healthy, "fields": [["wrong", "int4"], fields[1]]},
        "changed-type": {**healthy, "fields": [["k", "text"], fields[1]]},
    }
    detections = {}
    for name, invalid in errors.items():
        try:
            match(prediction, invalid, name)
        except Failure as failure:
            require(failure.signal == "meaning-mismatch", "server-result-wrong-control-signal", name)
            detections[name] = failure.signal
        else:
            raise Failure("server-result-comparator-accepted", name)
    return {"healthy": healthy, "alternateTieSelection": other_valid, "prediction": prediction,
            "mismatches": errors, "detections": detections}


def faults(engine, tag):
    base = f"sql-semantics-{engine}/src/Ir/"
    source = table(engine, "accounts")
    projection = [[slot(0), "k"], [slot(1), "payload"]]
    first = engine == "mysql"
    ordered = [order(1, False, first), order(2, False, first)]
    return [
        RenderFault("remove-native-distinct", base + "SelectSql.php", "'SELECT DISTINCT '", "'SELECT '",
                    case(engine, tag, source, projection, True, ordered, None), "meaning-mismatch"),
        RenderFault("reverse-native-sort-direction", base + "Ordering/ResultSql.php", "$key->descending ? ' DESC' : ' ASC'",
                    "$key->descending ? ' ASC' : ' DESC'",
                    case(engine, tag, source, projection, False, ordered, None), "meaning-mismatch"),
        RenderFault("swap-native-row-bounds", base + "Ordering/ResultSql.php",
                    """ . $select->limit->count;
        }
        if ($select->limit !== null && $select->limit->offset > 0) {
            $sql .= ' OFFSET ' . $select->limit->offset;""",
                    """ . $select->limit->offset;
        }
        if ($select->limit !== null && $select->limit->count > 0) {
            $sql .= ' OFFSET ' . $select->limit->count;""",
                    case(engine, tag, source, projection, False, ordered, [2, 1]), "meaning-mismatch"),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])
