"""Verify MySQL requested result aliases in both native protocol phases."""

from collections import Counter
import json
import sys

from evidence_transcript import canonical
import mysql_signed_projection_alias_cases as law
from run import Failure, TOOL, digest, require
from run_server import same
from run_server_join import main
from run_server_mysql_signed_membership import refusal
from run_server_mysql_signed_range import specifications as source_profiles
from sqlite_domain import RenderFault

LABEL = "mysql-signed-projection-alias"
OBSERVER = "mysql_signed_projection_alias_observer.php"
CONTRACT_NAME = "mysql-signed-projection-alias-v1.json"
CONTRACT = "f3a86eb38cad5c432e3b7f6d08fe851b05e86935cd8c9cfc76201f5c94c9dc8a"
MEANING = "meaning-mismatch"
COMPARATOR_FAULTS = {
    "Truncate requested alias bytes without maintaining whole UTF-8 characters.": "brokenUtf8",
    "Measure only execution diagnostics in native prepared mode.": "missingPrepare",
    "Deduplicate or reorder identical alias warnings.": "deduplicatedWarnings",
}


def specifications():
    path = TOOL / CONTRACT_NAME
    require(digest(path) == CONTRACT, "mysql-projection-alias-contract")
    contract = json.loads(path.read_text())
    require(contract["finite"]["allowedExclusions"] == contract["exploration"]["allowedExclusions"] == [],
            "mysql-projection-alias-no-exclusions")
    return contract, source_profiles()[1]


def prepare(_, db):
    return db.request({"fixture": True})


def check_preparation(_, environment, response):
    same(["native-prepared", "text"], environment["protocols"])
    same({"status": "fixture"}, response)


def identities(contract, analyze):
    listed = list(law.grid(contract, analyze))
    checked = law.independent_ids(analyze)
    field = "analyzeCoordinates" if analyze else "directCoordinates"
    require(len(listed) == len(checked) == contract["finite"][field] and set(listed) == checked,
            "mysql-projection-alias-finite-definition", field)
    return checked


def finite_ids(_, contract, _tag):
    return identities(contract, True)


def direct_finite_ids(_, contract, _tag):
    return identities(contract, False)


def finite_id(_, response, item):
    return law.physical(response, item["columns"][0][2])


def analysis_finite_id(engine, response, item, sql):
    same(law.command(item), sql)
    return finite_id(engine, response, item)


def direct_finite_id(engine, response, item, request):
    same(law.direct(item), request)
    return finite_id(engine, response, item)


def negatives(tag):
    default = law.direct(law.case(tag, ("1", "value", "quoted-identifier")))
    invalid = "A requested MySQL result alias needs BMP characters without NUL."
    for value, reason in (
        (["requested-result-alias", "name\x00tail"], invalid),
        (["requested-result-alias-hex", "ff"], invalid),
        (["requested-result-alias", "𐐷"], invalid),
        (["requested-result-alias", "already_effective"], "An unchanged result alias uses its effective identifier directly."),
        ("漢" * 90, "A MySQL alias needs an effective heading of at most 256 bytes."),
        ("\x7fvalue", "A MySQL result name must begin with a native graphic character or be empty."),
    ):
        yield {**default, "columns": [[["integer", "1"], value]]}, refusal(reason)


def requests(_, tag, contract):
    for prefix, analyze in (("finite", True), ("direct", False)):
        for index, (token, alias, style) in enumerate(law.grid(contract, analyze)):
            item = law.case(tag, (token, law.ALIASES[alias], style))
            request = {"dialect": "mysql", "release": tag, "sql": law.command(item)} if analyze else law.direct(item)
            yield f"{prefix}/{index}", request, item, None
    for index, (request, reason) in enumerate(negatives(tag)):
        yield f"negative/{index}", request, None, reason
    budget = contract["exploration"]
    for seed in budget["seeds"]:
        for index, item in enumerate(law.seeded(tag, seed, budget["casesPerSeed"])):
            yield f"seed/{seed}/{index}", {"dialect": "mysql", "release": tag, "sql": law.command(item)}, item, None
        for index, item in enumerate(law.seeded(tag, seed + 1, budget["directCasesPerSeed"])):
            yield f"direct-seed/{seed}/{index}", law.direct(item), item, None


def check_value(item, response):
    legacy = "materialized" if item["tag"] == "mysql-5.6.51" else "derived-merge=on"
    profile = item["tag"] + "/utf8mb4/lower-case-table-names=0/sql-mode=empty/native/" + legacy
    expected = {"profile": profile, "source": None, "where": None, "distinct": False, "orderBy": [], "limit": None,
                "columns": law.allocated(item), "status": "constructed"}
    require({key: response.get(key) for key in expected} == expected, "mysql-projection-alias-graph",
            {"expected": expected, "observed": response})


def observer_request(_, sql):
    return {"sql": sql}


def observation(value):
    return value


def check_observation(item, value, sql):
    expected = law.expected(item)
    detail = {"sql": sql, "expected": expected, "observed": value}
    require(all(value[mode]["status"] == "rows" for mode in expected), "mysql-projection-alias-native-refusal", detail)
    require(canonical(value) == canonical(expected), MEANING, detail)


def sample_forms(forms: Counter, response, *_):
    columns = response["columns"]
    forms[f"projection/{len(columns)}"] += 1
    names = []
    for _, label in columns:
        forms[f"label/{type(label).__name__}"] += 1
        if isinstance(label, list):
            names.append(label[1])
            forms[f"normalized/{not label[2]}"] += 1
            forms[f"width/{len(label[1].encode('utf-8')) > 256}"] += 1
    if len(names) > len(set(names)):
        forms["repeated-requested-name"] += 1


def required_forms(*_):
    return {"projection/1", "projection/2", "projection/3", "projection/4", "label/str", "label/list",
            "normalized/False", "normalized/True", "width/True", "width/False", "repeated-requested-name"}


def warnings_example(tag):
    return law.case(tag, ("1", " value", "quoted-identifier"), ("1", " value", "quoted-identifier"),
                    ("-00001", "  ", "quoted-identifier"))


def controls():
    item = warnings_example("mysql-9.1.0")
    healthy = law.expected(item)
    check_observation(item, healthy, law.command(item))
    native = healthy["native-prepared"]
    repeated = native["prepareWarnings"]
    clipped = law.case("mysql-9.1.0", ("1", law.ALIASES["mixed-prefix-over"], "quoted-identifier"))
    utf8 = law.expected(clipped)
    text = utf8["text"]
    field = text["fields"][0]
    raw_prefix = law.ALIASES["mixed-prefix-over"].encode("utf-8")[:256]
    anomalies = {
        "brokenUtf8": (clipped, {**utf8, "text": {**text, "fields": [[raw_prefix.hex(), *field[1:]]]}}),
        "missingPrepare": (item, {**healthy, "native-prepared": {**native, "prepareWarnings": []}}),
        "deduplicatedWarnings": (item, {**healthy, "native-prepared": {**native, "prepareWarnings": repeated[1:]}}),
    }
    verified = {}
    for name, (subject, damaged) in anomalies.items():
        try:
            check_observation(subject, damaged, law.command(subject))
        except Failure as error:
            same(MEANING, error.signal)
            verified[name] = error.detail
        else:
            raise Failure("mysql-projection-alias-comparator-ignored-fault", name)
    return verified


def faults(_, tag):
    item = warnings_example(tag)
    return [RenderFault("drop-native-alias-diagnostics", "sql-semantics-mysql/src/Ir/SelectSql.php",
                        "$name->aliasSql()", "'`' . str_replace('`', '``', $label) . '`'",
                        item, MEANING, law.direct(item))]


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
    raise Failure("mysql-projection-alias-fault-not-reproduced")


if __name__ == "__main__":
    main(sys.modules[__name__])
