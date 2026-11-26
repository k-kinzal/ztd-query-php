"""Verify native MySQL signed projection headings and numeric digit extent."""

from collections import Counter
import json
import sys

import mysql_signed_projection_cases as law
from evidence_transcript import canonical
from run import Failure, TOOL, digest, require
from run_server import same
from run_server_join import main
from run_server_mysql_signed_membership import refusal
from run_server_mysql_signed_range import specifications as source_profiles
from sqlite_domain import RenderFault

LABEL = "mysql-signed-projection"
OBSERVER = "mysql_signed_projection_observer.php"
CONTRACT_NAME = "mysql-signed-literal-projection-v2.json"
CONTRACT = "09611c7b3e9d8469a80543aa0b8e931db6f68c257772dc21289b872e69b9009e"
BASE_NAME = "mysql-signed-literal-projection-v1.json"
BASE = "a0294a1e33ddc757efd780fae5e99196109bc420663263676ddfeff3536471ee"
COMPARATOR_FAULTS = {"Ignore a changed visible width with an unchanged integer value": "wireExtent"}
MEANING = "meaning-mismatch"


def specifications():
    path = TOOL / CONTRACT_NAME
    require(digest(path) == CONTRACT, "mysql-signed-projection-contract")
    contract = json.loads(path.read_text())
    require(contract["reference"] == {"file": BASE_NAME, "sha256": BASE} and digest(TOOL / BASE_NAME) == BASE,
            "mysql-signed-projection-base")
    require(contract["finite"]["allowedExclusions"] == contract["exploration"]["allowedExclusions"] == [],
            "mysql-signed-projection-no-exclusions")
    return contract, source_profiles()[1]


def prepare(_, db):
    return db.request({"fixture": True})


def check_preparation(_, environment, response):
    same(65536, environment["commandBuffer"])
    same({"status": "fixture"}, response)


def direct_finite_ids(_, contract, _tag):
    ordered = list(law.grid(contract))
    independent = law.independent_ids()
    require(set(ordered) == independent and len(ordered) == len(independent) == contract["finite"]["coordinatesPerRoute"]
            == contract["finite"]["directCoordinates"],
            "mysql-signed-projection-finite-definition")
    return independent


def finite_ids(engine, contract, tag):
    original = {("plain", *coordinate) for coordinate in direct_finite_ids(engine, contract, tag)}
    ordered = list(law.wrapped_grid(contract))
    wrapped = law.independent_wrapped_ids()
    require(set(ordered) == wrapped and len(ordered) == len(wrapped) == contract["finite"]["wrappedAnalyzeCoordinates"]
            and len(original) + len(wrapped) == contract["finite"]["analyzeCoordinates"],
            "mysql-signed-projection-wrapped-finite-definition")
    return original | wrapped


def finite_id(_, response, item):
    check_value(item, response)
    return law.physical(response["columns"][0])


def analysis_finite_id(engine, response, item, sql):
    same(law.command(item), sql)
    return (item["columns"][0][5], *finite_id(engine, response, item))


def direct_finite_id(engine, response, item, request):
    same(law.direct(item), request)
    return finite_id(engine, response, item)


def negatives(tag):
    base = law.direct(law.case(tag, law.term("1", 0, "normal")))
    for token, reason in (
        ("1.25", "A projected integer token needs decimal digits and an optional minus sign."),
        ("9223372036854775808", "Range integer is outside the signed 64-bit range."),
        ("-9223372036854775809", "Range integer is outside the signed 64-bit range."),
    ):
        yield {**base, "columns": [[["projected-integer", token], "bad"]]}, refusal(reason)
    long = "0" * 256
    message = refusal("An intrinsic integer name must match the exact projected integer token.")
    yield {**base, "columns": [[["projected-integer", long + "2"], ["intrinsic-integer-name", long + "1"]]]}, message
    yield {**base, "columns": [[["integer", "1"], ["intrinsic-integer-name", long + "1"]]]}, message
    yield {"dialect": "mysql", "release": tag, "sql": "SELECT 9223372036854775808 AS bad"}, refusal(
        "Range integer is outside the signed 64-bit range.")


def requests(_, tag, contract):
    for index, coordinate in enumerate(law.grid(contract)):
        item = law.case(tag, law.term(*coordinate))
        yield f"finite/{index}", law.analyze(item), item, None
        yield f"direct/{index}", law.direct(item), item, None
    for index, (wrapper, value, padding, label) in enumerate(law.wrapped_grid(contract), contract["finite"]["coordinatesPerRoute"]):
        item = law.case(tag, law.term(value, padding, label, wrapper=wrapper))
        yield f"finite/{index}", law.analyze(item), item, None
    for index, (request, reason) in enumerate(negatives(tag)):
        yield f"negative/{index}", request, None, reason
    budget = contract["exploration"]
    for seed in budget["seeds"]:
        original = budget["predecessorCasesPerSeed"]
        for index, item in enumerate(law.seeded(tag, seed, original)):
            yield f"seed/{seed}/{index}", law.analyze(item), item, None
        extra = budget["casesPerSeed"] - original
        for index, item in enumerate(law.seeded(tag, seed, extra, wrapped=True), original):
            yield f"seed/{seed}/{index}", law.analyze(item), item, None
        for index, item in enumerate(law.seeded(tag, seed + 1, budget["directCasesPerSeed"])):
            yield f"direct-seed/{seed}/{index}", law.direct(item), item, None


def check_value(item, response):
    legacy = "materialized" if item["tag"] == "mysql-5.6.51" else "derived-merge=on"
    profile = item["tag"] + "/utf8mb4/lower-case-table-names=0/sql-mode=empty/native/" + legacy
    expected = {"profile": profile, "source": None, "where": None, "distinct": False, "orderBy": [], "limit": None,
                "columns": [law.allocated(column) for column in item["columns"]], "status": "constructed"}
    require({key: response.get(key) for key in expected} == expected, "mysql-signed-projection-graph",
            {"expected": expected, "observed": response})


def observer_request(_, sql):
    return {"sql": sql}


def observation(value):
    return value


def check_observation(item, value, sql):
    expected = law.expected(item)
    detail = {"sql": sql, "expected": expected, "observed": value}
    require(value["status"] == "rows", "mysql-signed-projection-native-refusal", detail)
    require(canonical(value) == canonical(expected), MEANING, detail)


def sample_forms(forms: Counter, response, _, item):
    forms[f"projection/{len(response['columns'])}"] += 1
    for (value, heading), column in zip(response["columns"], item["columns"]):
        signed, padding, _ = law.physical((value, heading))
        forms[f"value/{value[0]}"] += 1
        forms[f"width/{padding > 32}"] += 1
        forms[f"heading/{type(heading).__name__}"] += 1
        forms[f"source-wrapper/{column[5]}"] += 1
        if signed == "-0":
            forms["value/negative-zero"] += 1


def required_forms(*_):
    return {"projection/1", "projection/2", "projection/3", "projection/4", "value/integer",
            "value/projected-integer", "width/False", "width/True", "heading/str", "heading/list", "value/negative-zero",
            *(f"source-wrapper/{wrapper}" for wrapper in law.WRAPPERS)}


def control_pad(tag):
    return law.case(tag, law.term("2147483648", 68, "observed"))


def controls():
    item = control_pad("mysql-9.1.0")
    healthy = law.expected(item)
    check_observation(item, healthy, law.command(item))
    heading = healthy["fields"][0]
    altered = {**healthy, "fields": [[*heading[:3], heading[3] - 1]]}
    try:
        check_observation(item, altered, law.command(item))
    except Failure as error:
        same(MEANING, error.signal)
        return {"wireExtent": error.detail}
    raise Failure("mysql-signed-projection-comparator-ignored-width")


def faults(_, tag):
    source = "sql-semantics-mysql/src/Ir/"
    literal = source + "Literal/ProjectedIntegerLiteral.php"
    renderer = source + "SelectSql.php"
    anchor = "                $alias = '';"
    padding = control_pad(tag)
    wide = law.case(tag, law.term("2147483648", 0, "wide"))
    name = law.case(tag, law.term("1", 256, "intrinsic"))
    return [
        RenderFault("drop-projected-integer-padding", literal, "return $this->token;",
                    "return $this->decimal();", padding, MEANING, law.direct(padding)),
        RenderFault("replace-projected-integer-value", literal, "return $this->token;",
                    "return '2147483649';", wide, MEANING, law.direct(wide)),
        RenderFault("clip-intrinsic-integer-heading", renderer, anchor,
                    "                $alias = ' AS `' . substr($label, 0, 256) . '`';", name, MEANING, law.direct(name)),
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
    raise Failure("mysql-signed-projection-fault-not-reproduced")


if __name__ == "__main__":
    main(sys.modules[__name__])
