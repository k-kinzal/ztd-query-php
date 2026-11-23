"""Compare resolved recursive working rows with a signed progression model."""

import json
import sys

from evidence_transcript import canonical
from run import TOOL, Failure, digest, require
from run_server import CONTRACT as PROJECTION, same
from run_server_join import CONTRACT as JOIN, OBSERVER, check_preparation, main, observer_request, prepare
from server_recursive_series_cases import case, expected, finite_id, finite_ids, observation, requests
from sqlite_domain import RenderFault

LABEL = "recursive-series"
CONTRACT = "57ec24e29d2ed8d9ff1a18cd899165303c9dc3032db1738feb61a973da7842ff"


def specifications():
    entries = (("server-recursive-series-v2.json", CONTRACT), ("server-projection-v1.json", PROJECTION), ("server-join-v2.json", JOIN))
    require(all(digest(TOOL / name) == identity for name, identity in entries), "server-recursive-series-contract-mismatch")
    specification = json.loads((TOOL / entries[0][0]).read_text())
    profiles = json.loads((TOOL / entries[1][0]).read_text())["profiles"]
    for engine, settings in profiles.items():
        admitted = specification["profiles"][engine]
        unavailable = specification["unavailableSyntax"].get(engine, [])
        require(set(admitted).isdisjoint(unavailable) and set(settings["releases"]) == set(admitted + unavailable),
                "server-recursive-release-profile-incomplete", engine)
        settings["releases"] = admitted
    return specification, profiles


def check_value(item, response):
    require(response["status"] == "constructed" and response["profile"].startswith(item["release"] + "/"),
            "server-recursive-series-construction", response)
    require(response["series"] == item["series"], "server-recursive-series-shape-mismatch",
            {"expected": item, "observed": response})


def check_observation(item, actual, sql):
    predicted = expected(item)
    witness = {"sql": sql, "expected": predicted, "observed": actual}
    require(actual["status"] == "rows", "server-recursive-series-native-rejection", witness)
    require(canonical(predicted) == canonical(actual), "meaning-mismatch", witness)


def check_fault_witness(item, witness):
    same(expected(item), witness["expected"])
    require(witness["observed"]["status"] == "rows", "server-recursive-fault-native")


def required_forms(_engine, _tag):
    return {"operator/<", "operator/>", "all/False", "all/True", "width/single", "width/repeated"}


def sample_forms(forms, response, _engine, _item):
    series = response["series"]
    forms["operator/" + series["comparison"]] += 1
    forms[f"all/{series['all']}"] += 1
    forms["width/" + ("single" if len(series["columns"]) == 1 else "repeated")] += 1


def controls():
    item = case("postgres", "pg-17.2", -2, 3, 5, False, 2)
    prediction = expected(item)
    check_observation(item, prediction, "known recursion past the continuation boundary")
    altered = {
        "lost-initial-row": {**prediction, "rows": [row for row in prediction["rows"] if row != [-2, -2]]},
        "wrong-step": {**prediction, "rows": [[-2, -2], [2, 2], [6, 6]]},
        "wrong-native-field": {**prediction, "fields": [["value", "int8"], prediction["fields"][1]]},
    }
    detections = {}
    for name, response in altered.items():
        try:
            check_observation(item, response, name)
        except Failure as failure:
            require(failure.signal == "meaning-mismatch", "server-recursive-wrong-control-signal", name)
            detections[name] = failure.signal
        else:
            raise Failure("server-recursive-comparator-accepted", name)
    return {"input": item, "prediction": prediction, "mismatches": altered, "detections": detections}


def faults(engine, tag):
    source = f"sql-semantics-{engine}/src/Ir/Rows/RecursiveSeries.php"
    item = case(engine, tag, 1, 2, 5, True, 2)
    comparison = "$this->comparison->value"
    return [
        RenderFault("reverse-recursive-continuation", source, comparison, "'>'", item, "meaning-mismatch"),
        RenderFault("drop-recursive-anchor", source, ". $start\n            .", ". $start . ' WHERE FALSE'\n            .", item, "meaning-mismatch"),
        RenderFault("read-boundary-as-increment", source, "expression($this->increment, null)", "expression($this->boundary, null)", item, "meaning-mismatch"),
        RenderFault("omit-repeated-recursive-result", source, "implode(', ', $columns)", "implode(', ', [$columns[0]])", item, "meaning-mismatch"),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])
