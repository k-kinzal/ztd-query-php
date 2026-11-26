"""Compare native transaction state and committed visibility with an independent program model."""

import json
import sys

from evidence_transcript import canonical
from run import TOOL, Failure, digest, require
from run_server import CONTRACT as PROJECTION, same
from run_server_join import main
from server_transaction_cases import COMMANDS, INITIAL, case, checkpoint, expected, finite_id, finite_ids, labels, observation, requests, step
from sqlite_domain import RenderFault

LABEL = "transaction"
OBSERVER = "server_transaction_observer.php"
CONTRACT = "c0f7fb70d54ca3321d9104e0a239c62bdf24e48ce65febffb1906faae429dd09"


def specifications():
    path, projection = TOOL / "server-transaction-v1.json", TOOL / "server-projection-v1.json"
    require(digest(path) == CONTRACT and digest(projection) == PROJECTION, "server-transaction-contract-mismatch")
    return json.loads(path.read_text()), json.loads(projection.read_text())["profiles"]


def check_value(item, response):
    require(response["status"] == "constructed", "server-transaction-positive-rejected", response)
    release, profile = item["release"], response["profile"]
    require(profile.startswith(release + "/") and (item["dialect"] != "mysql" or profile.endswith("/completion-type=0")),
            "server-transaction-profile-mismatch", response)
    require(all(response[key] == item[key] for key in ("command", "name")), "server-transaction-shape-mismatch", response)


def observer_request(item, sql):
    return {"sql": sql, "prefix": item["prefix"], "suffix": item["suffix"]}


def check_observation(item, actual, sql):
    model = expected(item)
    witness = {"sql": sql, "expected": model, "observed": actual}
    require(actual["status"] == "observed", "server-transaction-native-rejection", witness)
    require(canonical(model) == canonical(actual), "meaning-mismatch", witness)


def check_fault_witness(item, witness):
    same(expected(item), witness["expected"])
    require(witness["observed"]["status"] == "observed", "server-transaction-fault-native")


def required_forms(engine, _tag):
    return {"command/" + command for command in COMMANDS} | {"label/ordinary", "label/delimiter", "label/space",
           "checkpoint/one", "checkpoint/two", "checkpoint/reused", "begin/active", "begin/inactive"}


def sample_forms(forms, response, engine, item):
    command, name = response["command"], response["name"]
    forms["command/" + command] += 1
    if name is not None:
        forms["label/" + ("ordinary", "delimiter", "space")[labels(engine).index(name)]] += 1
    checkpoints = [argument for operation, argument in item["prefix"] if operation == "savepoint"]
    if checkpoints:
        forms["checkpoint/" + ("one" if len(checkpoints) == 1 else "reused" if checkpoints[0] == checkpoints[1] else "two")] += 1
    if command == "begin":
        forms["begin/" + ("active" if item["prefix"] else "inactive")] += 1


def controls():
    item = case("postgres", "pg-17.2", "commit", prefix=[step("begin"), step("write", 5)])
    prediction = expected(item)
    check_observation(item, prediction, "known transaction")
    final = prediction["trace"][-1]
    altered = {
        "lost-commit": {**final, "committed": [0]},
        "lost-visibility": {**final, "visible": [0]},
        "left-open": {**final, "active": True},
        "changed-native-type": {**final, "committed": [0, "5"]},
    }
    detected = {}
    for name, value in altered.items():
        actual = {**prediction, "trace": [*prediction["trace"][:-1], value]}
        try:
            check_observation(item, actual, name)
        except Failure as failure:
            require(failure.signal == "meaning-mismatch", "server-transaction-wrong-control-signal", name)
            detected[name] = failure.signal
        else:
            raise Failure("server-transaction-comparator-accepted", name)
    return {"input": item, "prediction": prediction, "mismatches": altered, "detections": detected}


def outsiders():
    return ["COMMIT; ROLLBACK", "SET TRANSACTION READ ONLY", "ROLLBACK AND CHAIN", "SELECT 1"]


def prepare(engine, db):
    return {sql: db.request({"sql": sql, "prefix": [], "suffix": []}) for sql in outsiders()}


def check_preparation(engine, environment, preparation):
    same({"active": False, "visible": INITIAL, "committed": INITIAL}, environment["initial"])
    same("NO_CHAIN" if engine == "mysql" else None, environment["completionType"])
    same({sql: {"status": "outside-observer-scope"} for sql in outsiders()}, preparation)


def faults(engine, tag):
    path = f"sql-semantics-{engine}/src/Ir/Transaction/Transaction.php"
    suffix = " AND NO CHAIN NO RELEASE" if engine == "mysql" else ""
    quote = chr(96) if engine == "mysql" else '"'
    prefix = checkpoint("p", "two", (1, 2, 3))
    return [
        RenderFault("rollback-instead-of-commit", path, "TransactionCommand::Commit => 'COMMIT" + suffix,
                    "TransactionCommand::Commit => 'ROLLBACK" + suffix, case(engine, tag, "commit", prefix=prefix), "meaning-mismatch"),
        RenderFault("roll-back-to-other-checkpoint", path, "$this->name->quoted()", "'" + quote + "other" + quote + "'",
                    case(engine, tag, "rollback-to", "p", prefix, [step("commit")]), "meaning-mismatch"),
        RenderFault("commit-instead-of-rollback", path, "TransactionCommand::Rollback => 'ROLLBACK" + suffix,
                    "TransactionCommand::Rollback => 'COMMIT" + suffix, case(engine, tag, "rollback", prefix=prefix), "meaning-mismatch"),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])
