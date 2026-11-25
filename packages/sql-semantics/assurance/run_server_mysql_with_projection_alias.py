"""Verify source alias diagnostics across MySQL WITH position bindings."""

from collections import Counter
from itertools import product
import json
import sys

from evidence_transcript import canonical
import mysql_with_projection_alias_cases as law
from run import Failure, TOOL, digest, require
from run_server import same
from run_server_join import main
from run_server_mysql_signed_membership import refusal
from run_server_mysql_signed_projection_alias import OBSERVER, check_preparation, observation, observer_request, prepare
from run_server_mysql_with_projection import DIAGNOSTICS, core, request, specifications as cte_profile
from sqlite_domain import RenderFault

LABEL = "mysql-with-projection-alias"
CONTRACT_NAME = "mysql-with-projection-alias-v1.json"
CONTRACT = "462456fa60990115fda184224bcc14018da7cb4538187afd05176a20119d05bf"
MEANING = "meaning-mismatch"
COMPARATOR_FAULTS = {"Accept an observed candidate with the same values and missing prepare diagnostics.": "missingWithPrepare"}


def specifications():
    path = TOOL / CONTRACT_NAME
    require(digest(path) == CONTRACT, "mysql-with-alias-contract")
    contract = json.loads(path.read_text())
    predecessor, profiles = cte_profile()
    require(contract["profiles"] == predecessor["profiles"]
            and contract["rejectedReleases"] == predecessor["rejectedReleases"]
            and contract["finite"]["allowedExclusions"] == contract["exploration"]["allowedExclusions"] == [],
            "mysql-with-alias-profile")
    return contract, profiles


def finite_ids(_, contract, _tag):
    enumerated = list(law.grid(contract))
    independent = law.independent_ids()
    require(set(enumerated) == independent and len(enumerated) == len(independent) == contract["finite"]["coordinatesPerRoute"],
            "mysql-with-alias-finite-definition")
    return independent


def finite_id(_, response, item):
    actual = core(response)
    identities = product(law.LABELS, law.PLACEMENTS, law.LAYOUTS)
    matches = [identity for identity in identities
               if core(law.structure(law.finite(item["tag"], *identity), observed=True)) == actual]
    require(len(matches) == 1, "mysql-with-alias-finite-identity", matches)
    return matches[0]


def analysis_finite_id(engine, response, item, sql):
    same(law.command(item), sql)
    return finite_id(engine, response, item)


def direct_finite_id(engine, response, item, request_):
    same(law.structure(item), request_)
    return finite_id(engine, response, item)


def negative(tag):
    accepted = law.structure(law.finite(tag, "trim", "definition", "in-order"))
    broken = {**accepted["with"], "body": {"read": [0, [[2, "outside"]]]}}
    yield {**accepted, "with": broken}, refusal("A local read must select an existing WITH output slot.")
    yield request(tag, "WITH a(one) AS (SELECT 1 AS ` second`, 2 AS next) SELECT one AS observed FROM a", []), {
        "exception": DIAGNOSTICS + "SemanticException", "message": "SELECT list and column names list have different column counts (1 and 2)."}
    yield {**accepted, "release": "mysql-5.7.44"}, refusal("WITH requires MySQL 8.0 or later.")


def requests(_, tag, contract):
    for index, identity in enumerate(law.grid(contract)):
        item = law.finite(tag, *identity)
        yield f"finite/{index}", request(tag, law.command(item), []), item, None
        yield f"direct/{index}", law.structure(item), item, None
    for index, (attempt, rejection) in enumerate(negative(tag)):
        yield f"negative/{index}", attempt, None, rejection
    exploration = contract["exploration"]
    for seed in exploration["seeds"]:
        for index, item in enumerate(law.seeded(tag, seed, exploration["casesPerSeed"])):
            yield f"seed/{seed}/{index}", request(tag, law.command(item), []), item, None
        for index, item in enumerate(law.seeded(tag, seed + 1, exploration["directCasesPerSeed"])):
            yield f"direct-seed/{seed}/{index}", law.structure(item), item, None


def check_value(item, response):
    profile = item["tag"] + "/utf8mb4/lower-case-table-names=0/sql-mode=empty/native/derived-merge=on"
    require(response["status"] == "constructed" and response["profile"] == profile,
            "mysql-with-alias-construction", response)
    expected = core(law.structure(item, observed=True))
    require(core(response) == expected, "mysql-with-alias-graph", {"expected": expected, "observed": response})


def check_observation(item, value, sql):
    expected = law.expected(item)
    detail = {"sql": sql, "expected": expected, "observed": value}
    require(all(value[mode]["status"] == "rows" for mode in expected), "mysql-with-alias-native-refusal", detail)
    require(canonical(value) == canonical(expected), MEANING, detail)


def sample_forms(forms: Counter, response, _, item):
    outer = response["with"]
    forms[f"definitions/{len(outer['definitions'])}"] += 1
    forms[f"placement/{item['placement']}"] += 1
    forms[f"repeated-position/{len(set(item['order'])) < len(item['order'])}"] += 1


def required_forms(*_):
    return {"definitions/1", "definitions/2", "definitions/3", "placement/body", "placement/nested",
            "placement/definition", "repeated-position/True", "repeated-position/False"}


def controls():
    item = law.finite("mysql-9.1.0", "duplicate", "nested", "reverse")
    healthy = law.expected(item)
    check_observation(item, healthy, law.command(item))
    damaged = {**healthy, "native-prepared": {**healthy["native-prepared"], "prepareWarnings": []}}
    try:
        check_observation(item, damaged, law.command(item))
    except Failure as error:
        same(MEANING, error.signal)
        return {"missingWithPrepare": error.detail}
    raise Failure("mysql-with-alias-comparator-ignored-preparation")


def faults(_, tag):
    item = law.finite(tag, "duplicate", "definition", "reverse")
    path = "sql-semantics-mysql/src/Ir/Rows/With/WithSql.php"
    return [
        RenderFault("discard-requested-cte-aliases", path, "$body, !$retain", "$body, true", item, MEANING, law.structure(item)),
        RenderFault("swap-local-cte-column-list", path, "implode(', ', $names)", "implode(', ', array_reverse($names))",
                    item, MEANING, law.structure(item)),
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
    raise Failure("mysql-with-alias-fault-not-reproduced")


if __name__ == "__main__":
    main(sys.modules[__name__])
