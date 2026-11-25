"""Check native view alias diagnostics and the persistent signed literal effect."""

from collections import Counter
import json
import sys

from evidence_transcript import canonical
import mysql_create_view_projection_alias_cases as law
from run import Failure, TOOL, digest, require
from run_server import same
from run_server_join import main
from run_server_mysql_create_view import specifications as source_profiles
from run_server_mysql_signed_membership import refusal
from sqlite_domain import RenderFault

LABEL = "mysql-create-view-projection-alias"
OBSERVER = "mysql_create_view_projection_alias_observer.php"
CONTRACT_NAME = "mysql-create-view-projection-alias-v1.json"
CONTRACT = "fbb47880cde2e45f72899d6bf18abc720c75fdf9fc2587bd7f02bc447486eecf"
INVALID = "A MySQL view projection alias needs 1 to 64 BMP characters without a trailing space."
MEANING = "meaning-mismatch"
COMPARATOR_FAULTS = {"Ignore a missing persistent catalog row when the queried row is unchanged.": "missingStoredView"}


def specifications():
    path = TOOL / CONTRACT_NAME
    require(digest(path) == CONTRACT, "mysql-view-alias-contract")
    contract = json.loads(path.read_text())
    require(contract["finite"]["allowedExclusions"] == contract["exploration"]["allowedExclusions"] == [],
            "mysql-view-alias-no-exclusions")
    return contract, source_profiles()[1]


def prepare(_, db):
    return db.request({"fixture": True})


def check_preparation(_, environment, result):
    same(["native-prepared", "text"], environment["protocols"])
    same("root@%", environment["principal"])
    same({"status": "fixture"}, result)


def finite_ids(_, contract, _tag):
    listed = list(law.grid(contract))
    independent = law.independent_ids()
    require(len(listed) == len(independent) == contract["finite"]["coordinatesPerRoute"] and set(listed) == independent,
            "mysql-view-alias-finite-definition")
    return independent


def finite_id(_, response, item):
    actual = response["createView"]
    matches = [identity for identity in law.independent_ids()
               if law.structure(law.finite(item["tag"], *identity), observed=True)["createView"] == actual]
    require(len(matches) == 1, "mysql-view-alias-finite-identity", matches)
    return matches[0]


def analysis_finite_id(engine, response, item, sql):
    same(law.command(item), sql)
    return finite_id(engine, response, item)


def direct_finite_id(engine, response, item, input_):
    same(law.structure(item), input_)
    return finite_id(engine, response, item)


def rejected_finite_ids(*_):
    return law.INVALID_ALIASES.keys()


def observe_rejection(_, item, actual, sql):
    name = item["invalidAlias"]
    requested = law.INVALID_ALIASES[name]
    message = law.refusal_text(item["tag"], requested)
    failure = {"status": "native-refusal", "sqlState": "42000", "code": 1166, "message": message,
               "warnings": [["Error", 1166, message]], "catalogExists": False}
    expected = {"native-prepared": {**failure, "phase": "prepare"}, "text": {**failure, "phase": "execute"}}
    require(canonical(actual) == canonical(expected), "mysql-view-alias-native-negative-mismatch",
            {"input": item, "sql": sql, "expected": expected, "observed": actual})
    return name


def source_request(tag, sql):
    return {"dialect": "mysql", "release": tag, "sql": sql}


def negatives(tag):
    for name, requested in law.INVALID_ALIASES.items():
        item = {"tag": tag, "columns": [["1", requested, "quoted-identifier"]], "fields": ["visible"], "invalidAlias": name}
        yield source_request(tag, law.command(item)), item, refusal(INVALID)
    item = {"tag": tag, "columns": [["1", " " + "x" * 64, "quoted-identifier"]], "fields": ["visible"]}
    yield law.structure(item), None, refusal(INVALID)


def requests(_, tag, contract):
    for index, identity in enumerate(law.grid(contract)):
        item = law.finite(tag, *identity)
        yield f"finite/{index}", source_request(tag, law.command(item)), item, None
        yield f"direct/{index}", law.structure(item), item, None
    for index, (input_, item, reason) in enumerate(negatives(tag)):
        yield f"negative/{index}", input_, item, reason
    settings = contract["exploration"]
    for seed in settings["seeds"]:
        for index, item in enumerate(law.seeded(tag, seed, settings["casesPerSeed"])):
            yield f"seed/{seed}/{index}", source_request(tag, law.command(item)), item, None
        for index, item in enumerate(law.seeded(tag, seed + 1, settings["directCasesPerSeed"])):
            yield f"direct-seed/{seed}/{index}", law.structure(item), item, None


def check_value(item, reply):
    profile = item["tag"] + "/utf8mb4/lower-case-table-names=0/sql-mode=empty/native/view-definition"
    expected = law.structure(item, observed=True)["createView"]
    require(reply["status"] == "constructed" and reply["profile"] == profile and reply["createView"] == expected,
            "mysql-view-alias-graph", {"expected": expected, "actual": reply})


def observer_request(_, sql):
    return {"sql": sql}


def observation(raw):
    return raw


def check_observation(item, actual, sql):
    expected = law.expected(item)
    detail = {"sql": sql, "expected": expected, "observed": actual}
    for mode in expected:
        record = actual[mode]
        require(record["status"] == "created", "mysql-view-alias-native-refusal", detail)
        require(isinstance(record.get("definitionSql"), str) and record["definitionSql"].startswith("CREATE ALGORITHM="),
                "mysql-view-alias-definition-unobserved", detail)
        effect = {key: value for key, value in record.items() if key != "definitionSql"}
        require(canonical(effect) == canonical(expected[mode]), MEANING, detail)


def sample_forms(forms: Counter, response, *_):
    body = response["createView"]["query"]["columns"]
    forms[f"width/{len(body)}"] += 1
    names = []
    for value, label in body:
        forms[f"label/{type(label).__name__}"] += 1
        forms[f"atom/{value[0]}"] += 1
        if isinstance(label, list):
            names.append(label[1])
            forms[f"empty-effective/{not label[2]}"] += 1
    if len(names) > len(set(names)):
        forms["duplicate-requested"] += 1


def required_forms(*_):
    return {"width/2", "width/3", "width/4", "label/list", "label/str", "atom/integer",
            "atom/projected-integer", "empty-effective/True", "empty-effective/False", "duplicate-requested"}


def controls():
    item = law.finite("mysql-9.1.0", "trim", law.ATOMS[0], "source-order")
    healthy = {mode: {**result, "definitionSql": "CREATE ALGORITHM=UNDEFINED VIEW fixture"}
               for mode, result in law.expected(item).items()}
    check_observation(item, healthy, law.command(item))
    broken = {**healthy, "text": {**healthy["text"], "catalog": []}}
    try:
        check_observation(item, broken, law.command(item))
    except Failure as error:
        same(MEANING, error.signal)
        return {"missingStoredView": error.detail}
    raise Failure("mysql-view-alias-comparator-ignored-persistent-catalog")


def faults(_, tag):
    target = "sql-semantics-mysql/src/Ir/Schema/View/CreateView.php"
    item = law.finite(tag, "trim", law.ATOMS[0], "source-order")
    input_ = law.structure(item)
    return [
        RenderFault("discard-requested-view-aliases", target, "->render($this->query, true, true)",
                    "->render($this->query, true)", item, MEANING, input_),
        RenderFault("reverse-catalog-view-names", target, "implode(', ', $names)", "implode(', ', array_reverse($names))",
                    item, MEANING, input_),
    ]


def replay_fault(rule, record):
    check_value(rule.item, record["healthyResponse"])
    check_value(rule.item, record["mutantResponse"])
    detail = record["detail"]
    same(record["mutantResponse"]["sql"], detail["sql"])
    try:
        check_observation(rule.item, detail["observed"], detail["sql"])
    except Failure as error:
        same(rule.signal, error.signal)
        same(detail, error.detail)
        return
    raise Failure("mysql-view-alias-fault-not-reproduced")


if __name__ == "__main__":
    main(sys.modules[__name__])
