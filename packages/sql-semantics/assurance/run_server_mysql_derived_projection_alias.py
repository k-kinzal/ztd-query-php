"""Check native derived projection labels, occurrence bindings and diagnostics."""

from collections import Counter
import json
import sys

from evidence_transcript import canonical
import mysql_derived_projection_alias_cases as law
from run import Failure, TOOL, digest, require
from run_server import same
from run_server_join import main
from run_server_mysql_signed_membership import refusal
from run_server_mysql_signed_projection_alias import OBSERVER, observation, observer_request
from run_server_mysql_with_projection import DIAGNOSTICS, core, request, specifications as cte_profile
from run_server_mysql_with_projection_alias import check_preparation, prepare
from sqlite_domain import RenderFault

LABEL = "mysql-derived-projection-alias"
CONTRACT_NAME = "mysql-derived-projection-alias-v1.json"
CONTRACT = "9e60ba0b66f49b729d9b7fe5bda7e769d649e7dc2b717baf71e10d3a6612c19d"
MEANING = "meaning-mismatch"
COMPARATOR_FAULTS = {"Compare only values and drop the derived table's prepare diagnostics.": "missingDerivedPrepare"}


def specifications():
    path = TOOL / CONTRACT_NAME
    require(digest(path) == CONTRACT, "mysql-derived-alias-contract")
    contract = json.loads(path.read_text())
    base, profiles = cte_profile()
    require(contract["profiles"] == base["profiles"] and contract["rejectedReleases"] == base["rejectedReleases"]
            and contract["finite"]["allowedExclusions"] == contract["exploration"]["allowedExclusions"] == [],
            "mysql-derived-alias-profile")
    return contract, profiles


def finite_ids(_, contract, _tag):
    listed = list(law.grid(contract))
    independent = law.independent_ids()
    require(len(listed) == len(independent) == contract["finite"]["coordinatesPerRoute"] and set(listed) == independent,
            "mysql-derived-alias-finite-definition")
    return independent


def finite_id(_, response, item):
    actual = core(response)
    matching = [identity for identity in law.independent_ids()
                if core(law.structure(law.finite(item["tag"], *identity), observed=True)) == actual]
    require(len(matching) == 1, "mysql-derived-alias-finite-identity", matching)
    return matching[0]


def analysis_finite_id(engine, response, item, sql):
    same(law.command(item), sql)
    return finite_id(engine, response, item)


def direct_finite_id(engine, response, item, request_):
    same(law.structure(item), request_)
    return finite_id(engine, response, item)


def negatives(tag):
    item = law.finite(tag, "trim", "local", "in-order")
    good = law.structure(item)
    outside = {**good["local"], "columns": [[2, "outside"]]}
    yield {**good, "local": outside}, refusal("The local slot must belong to the definition.")
    yield request(tag, "SELECT a.first AS value FROM (SELECT 1 AS ` inner`, 2 AS next) AS a (first)", []), {
        "exception": DIAGNOSTICS + "SemanticException", "message": "SELECT list and column names list have different column counts (1 and 2)."}
    yield {**good, "release": "mysql-5.7.44"}, refusal("A derived table with requested aliases requires MySQL 8.0 or later.")


def requests(_, tag, contract):
    for index, identity in enumerate(law.grid(contract)):
        item = law.finite(tag, *identity)
        yield f"finite/{index}", request(tag, law.command(item), []), item, None
        yield f"direct/{index}", law.structure(item), item, None
    for index, (item, reason) in enumerate(negatives(tag)):
        yield f"negative/{index}", item, None, reason
    exploration = contract["exploration"]
    for seed in exploration["seeds"]:
        for index, item in enumerate(law.seeded(tag, seed, exploration["casesPerSeed"])):
            yield f"seed/{seed}/{index}", request(tag, law.command(item), []), item, None
        for index, item in enumerate(law.seeded(tag, seed + 1, exploration["directCasesPerSeed"])):
            yield f"direct-seed/{seed}/{index}", law.structure(item), item, None


def check_value(item, response):
    profile = item["tag"] + "/utf8mb4/lower-case-table-names=0/sql-mode=empty/native/derived-merge=on"
    require(response["status"] == "constructed" and response["profile"] == profile,
            "mysql-derived-alias-construction", response)
    expected = core(law.structure(item, observed=True))
    require(core(response) == expected, "mysql-derived-alias-graph", {"expected": expected, "observed": response})


def check_observation(item, value, sql):
    expected = law.expected(item)
    detail = {"sql": sql, "expected": expected, "observed": value}
    require(all(value[mode]["status"] == "rows" for mode in expected), "mysql-derived-alias-native-refusal", detail)
    require(canonical(value) == canonical(expected), MEANING, detail)


def sample_forms(forms: Counter, response, _, item):
    forms[f"placement/{item['placement']}"] += 1
    if "local" in response:
        keys = [tuple(value[:1]) for value in response["local"]["columns"]]
        forms[f"repeat/{len(set(keys)) < len(keys)}"] += 1
    else:
        for position, _ in response["columns"]:
            forms[f"occurrence/{position[2] if len(position) > 2 else 0}"] += 1
    for child in item["sources"]:
        forms[f"width/{len(child['columns'])}"] += 1


def required_forms(*_):
    return {"placement/local", "placement/nested", "placement/with-local", "placement/join", "occurrence/0",
            "occurrence/1", "repeat/True", "repeat/False", "width/2", "width/3", "width/4"}


def controls():
    item = law.finite("mysql-9.1.0", "duplicate", "join", "repeat")
    healthy = law.expected(item)
    check_observation(item, healthy, law.command(item))
    changed = {**healthy, "native-prepared": {**healthy["native-prepared"], "prepareWarnings": []}}
    try:
        check_observation(item, changed, law.command(item))
    except Failure as error:
        same(MEANING, error.signal)
        return {"missingDerivedPrepare": error.detail}
    raise Failure("mysql-derived-alias-comparator-ignored-preparation")


def faults(_, tag):
    item = law.finite(tag, "duplicate", "local", "reverse")
    return [
        RenderFault("discard-requested-derived-aliases", "sql-semantics-mysql/src/Ir/Rows/LocalProjectionSql.php",
                    "$source instanceof Select => $renderer->render($source, !$retain),",
                    "$source instanceof Select => $renderer->render($source, true),", item, MEANING, law.structure(item)),
        RenderFault("swap-derived-column-list", "sql-semantics-mysql/src/Ir/SelectSql.php",
                    "implode(', ', $names)", "implode(', ', array_reverse($names))", item, MEANING, law.structure(item)),
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
    raise Failure("mysql-derived-alias-fault-not-reproduced")


if __name__ == "__main__":
    main(sys.modules[__name__])
