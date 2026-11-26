"""Compare lowered wildcard projections with independent slot layouts and both SQL results."""

import json
import sys

from run import TOOL, Failure, digest, require
from evidence_transcript import canonical
from run_server import same
from run_server_join import OBSERVER, check_preparation, check_value, main, observer_request
from run_server_join import prepare as prepare_join, specifications as join_specification
from server_cases import observation
from server_join_cases import fixture, slot
from server_wildcard_cases import LABEL_DDL, LABEL_RELATION, expected, finite_id, finite_ids, named_slot, requests, shape, written_form
from sqlite_domain import RenderFault

LABEL = "wildcard"
CONTRACT = "6cb667142ac182d5e6d5b4eb950963c621cec3b4f62303ad72acb8c3ce166789"


def specifications():
    path = TOOL / "wildcard-projection-v2.json"
    require(digest(path) == CONTRACT, "server-wildcard-contract-mismatch")
    specification = json.loads(path.read_text())
    _, profiles = join_specification()
    return {**specification, **specification["native"]}, profiles


def analysis_finite_id(engine, reply, item, sql):
    require(sql == item["wildcardSql"], "native-wildcard-input-mismatch", sql)
    return finite_id(engine, reply, {**item, "wildcardSql": sql})


def prepare(engine, db):
    if engine != "mysql":
        return prepare_join(engine, db)
    statements = [*fixture(engine), f"DROP TABLE IF EXISTS ir_fixture.{LABEL_RELATION}", LABEL_DDL,
                  f"INSERT INTO ir_fixture.{LABEL_RELATION} VALUES (1,2),(3,NULL),(1,2)"]
    return db.request({"fixture": statements})


def check_observation(item, actual, sql):
    wanted = expected(item)
    witness = {"sql": sql, "expected": wanted, "observed": actual}
    require(actual["status"] == "rows", "server-wildcard-native-rejection", witness)
    require(canonical(wanted) == canonical(actual), "meaning-mismatch", witness)


def check_fault_witness(item, witness):
    same(expected(item), witness["expected"])
    require(witness["observed"]["status"] == "rows", "server-wildcard-fault-native")


def required_forms(_engine, _tag):
    return {"scope/single", "scope/join", "projection/qualified", "projection/unqualified", "projection/repeated",
            "projection/mixed", "slots/repeated", "slots/reordered", "filter/present", "filter/absent"}


def sample_forms(forms, response, _engine, item):
    forms["scope/" + ("join" if "kind" in response["source"] else "single")] += 1
    forms["projection/" + written_form(item["wildcardSql"])] += 1
    forms["filter/" + ("present" if response["where"] is not None else "absent")] += 1
    indexes = [(expr[2] if len(expr) == 3 else 0, expr[1]) for expr, _ in response["columns"]]
    if len(set(indexes)) < len(indexes):
        forms["slots/repeated"] += 1
    if sorted(indexes) != indexes:
        forms["slots/reordered"] += 1


def controls():
    _, item = shape("postgres", "pg-17.2", "self-join", "mixed")
    correct = expected(item)
    check_observation(item, correct, "known expanded rows")
    invalid = {
        "renamed-column": {**correct, "fields": [["incorrect", "int4"], *correct["fields"][1:]]},
        "missing-row": {**correct, "rows": correct["rows"][1:]},
        "misordered-layout": {**correct, "fields": list(reversed(correct["fields"]))},
    }
    detected = {}
    for key, altered in invalid.items():
        try:
            check_observation(item, altered, key)
        except Failure as failure:
            require(failure.signal == "meaning-mismatch", "server-wildcard-wrong-control-signal", key)
            detected[key] = failure.signal
        else:
            raise Failure("server-wildcard-comparator-accepted", key)
    return {"input": item, "prediction": correct, "mismatches": invalid, "detections": detected}


def faults(engine, tag):
    renderer = f"sql-semantics-{engine}/src/Ir/SelectSql.php"
    _, wide = shape(engine, tag, "self-join", "qualified")
    _, plain = shape(engine, tag, "ordinary", "unqualified")
    mutants = [
        RenderFault("reverse-expanded-field-order", renderer, "foreach ($select->columns as $slot => $column)",
                    "foreach (array_reverse($select->columns) as $slot => $column)", plain, "meaning-mismatch"),
        RenderFault("use-first-expanded-occurrence", renderer, "$this->alias($offset + $expression->occurrence)", "$this->alias($offset)",
                    wide, "meaning-mismatch"),
        RenderFault("drop-expanded-final-field", renderer, "foreach ($select->columns as $slot => $column)",
                    "foreach (array_slice($select->columns, 0, -1) as $slot => $column)", plain, "meaning-mismatch"),
    ]
    if engine == "mysql":
        intrinsic = named_slot(tag, [" lead"], [[slot(0), " lead"]])
        operand = "$this->projectionOperand($column, $source, $name) . $alias"
        forced_alias = "$this->projectionOperand($column, $source, $name) . ' AS ' . $name->quoted()"
        mutants.append(RenderFault("force-intrinsic-field-alias", renderer, operand, forced_alias,
                                  intrinsic, "meaning-mismatch"))
    return mutants


if __name__ == "__main__":
    main(sys.modules[__name__])
