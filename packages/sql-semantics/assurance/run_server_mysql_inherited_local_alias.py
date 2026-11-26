"""Check resolved implicit local MySQL headings against native results."""

from collections import Counter
from functools import lru_cache
import json
import sys

from evidence_transcript import canonical
import mysql_inherited_local_alias_cases as law
from run import Failure, TOOL, digest, require
from run_server import rejects, same
from run_server_join import main, probe
from run_server_mysql_signed_membership import refusal
from run_server_mysql_signed_projection_alias import OBSERVER, observation, observer_request
from run_server_mysql_with_projection import DIAGNOSTICS, core, request, specifications as cte_profile
from run_server_mysql_with_projection_alias import check_preparation, prepare
from sqlite_domain import RenderFault

LABEL = "mysql-inherited-local-alias"
CONTRACT_NAME = "mysql-inherited-local-alias-v2.json"
CONTRACT = "bf8092d648b1800c51fcd22ca61a13ca17758b35f1dbc63554530f2a0810ba44"
MEANING = "meaning-mismatch"
LOST_BINDING = "mysql-inherited-local-alias-lost-binding"
COMPARATOR_FAULTS = {"Discard the lookup failure or a native warning in the comparison.": "missingDiagnostics"}


def specifications():
    path = TOOL / CONTRACT_NAME
    require(digest(path) == CONTRACT, "mysql-inherited-alias-contract")
    contract = json.loads(path.read_text())
    former = TOOL / "mysql-inherited-local-alias-v1.json"
    require(contract["extends"] == {"path": former.name, "sha256": digest(former)}, "mysql-inherited-alias-base-contract")
    original = json.loads(former.read_text())
    require(all(contract[key] == original[key] for key in ("constructors", "finite", "exploration", "negative", "faults"))
            and contract["observations"][:len(original["observations"])] == original["observations"],
            "mysql-inherited-alias-baseline-obligations")
    base, profiles = cte_profile()
    require(contract["profiles"] == base["profiles"]
            and contract["finite"]["allowedExclusions"] == contract["exploration"]["allowedExclusions"] == [],
            "mysql-inherited-alias-profile")
    return contract, profiles


def finite_ids(_, contract, _tag):
    listed = list(law.grid(contract))
    independent = law.independent_ids()
    require(len(listed) == len(independent) == contract["finite"]["coordinatesPerRoute"] and set(listed) == independent,
            "mysql-inherited-alias-finite-definition")
    return independent


@lru_cache
def observed_ids(tag):
    pairs = [(canonical(core(law.structure(law.finite(tag, *identity), observed=True))), identity)
             for identity in law.independent_ids()]
    result = dict(pairs)
    require(len(result) == len(pairs), "mysql-inherited-alias-finite-ambiguous")
    return result


def finite_id(_, response, item):
    observed = canonical(core(response))
    identities = observed_ids(item["tag"])
    require(observed in identities, "mysql-inherited-alias-finite-identity", response)
    return identities[observed]


def analysis_finite_id(engine, response, item, sql):
    same(law.command(item), sql)
    return finite_id(engine, response, item)


def direct_finite_id(engine, response, item, input_):
    same(law.structure(item), input_)
    return finite_id(engine, response, item)


def semantic(message):
    return {"exception": DIAGNOSTICS + "SemanticException", "message": message}


def rejected_finite_ids(*_):
    return ("written-alias", "duplicate")


def check_native_error(item, raw, sql):
    code = 1054 if item["invalid"] == "written-alias" else 1060
    sqlstate = "42S22" if code == 1054 else "42S21"
    expected = {"status": "native-error", "sqlState": sqlstate, "code": code}
    require(raw == {"native-prepared": expected, "text": expected}, "mysql-inherited-alias-native-rejection",
            {"input": item, "sql": sql, "expected": expected, "observed": raw})


def observe_rejection(_, item, raw, sql):
    check_native_error(item, raw, sql)
    return item["invalid"]


def negatives(tag):
    yield (request(tag, "SELECT d.`  first` AS n FROM (SELECT 1 AS `  first`) d", []),
           {"invalid": "written-alias"}, semantic("Column d.  first does not exist."))
    yield (request(tag, "WITH d AS (SELECT 1 AS ` First`, 2 AS first, 3 AS target) SELECT d.target FROM d", []),
           {"invalid": "duplicate"}, semantic("Duplicate column name"))
    good = law.structure(law.finite(tag, "trim", "local", "in-order"))
    outside = {**good["local"], "columns": [[2, "outside"]]}
    yield {**good, "local": outside}, None, refusal("The local slot must belong to the definition.")


def requests(_, tag, contract):
    for index, identity in enumerate(law.grid(contract)):
        item = law.finite(tag, *identity)
        yield f"finite/{index}", request(tag, law.command(item), []), item, None
        yield f"direct/{index}", law.structure(item), item, None
    for index, (input_, item, reason) in enumerate(negatives(tag)):
        yield f"negative/{index}", input_, item, reason
    settings = contract["exploration"]
    for seed in settings["seeds"]:
        for index, item in enumerate(law.seeded(tag, seed, settings["casesPerSeed"])):
            yield f"seed/{seed}/{index}", request(tag, law.command(item), []), item, None
        for index, item in enumerate(law.seeded(tag, seed + 1, settings["directCasesPerSeed"])):
            yield f"direct-seed/{seed}/{index}", law.structure(item), item, None


def check_value(item, response):
    profile = item["tag"] + "/utf8mb4/lower-case-table-names=0/sql-mode=empty/native/derived-merge=on"
    require(response["status"] == "constructed" and response["profile"] == profile,
            "mysql-inherited-alias-construction", response)
    expected = core(law.structure(item, observed=True))
    require(core(response) == expected, "mysql-inherited-alias-graph", {"expected": expected, "observed": response})


def check_observation(item, value, sql):
    expected = law.expected(item)
    detail = {"sql": sql, "expected": expected, "observed": value}
    require(all(value[mode]["status"] == "rows" for mode in expected), "mysql-inherited-alias-native-refusal", detail)
    require(canonical(value) == canonical(expected), MEANING, detail)


def native_scans(reply):
    if "with" in reply:
        node = reply["with"]
        for _, child in node["definitions"]:
            yield from native_scans(child)
        yield from native_scans(node["body"])
    elif "local" in reply:
        yield from native_scans(reply["local"]["definition"])
    elif "columns" in reply:
        if reply["source"] is not None:
            for side in ("left", "right"):
                yield from native_scans(reply["source"][side]["derived"])
        else:
            yield reply["columns"]


def sample_forms(forms: Counter, response, _, item):
    forms[f"placement/{item['placement']}"] += 1
    for columns in native_scans(response):
        forms[f"width/{len(columns)}"] += 1
        forms[f"requested/{any(isinstance(label, list) for _, label in columns)}"] += 1
    if "columns" in response:
        for expression, _ in response["columns"]:
            forms[f"occurrence/{expression[2] if len(expression) > 2 else 0}"] += 1


def required_forms(*_):
    return {*(f"placement/{place}" for place in law.PLACEMENTS),
            "occurrence/0", "occurrence/1", "requested/True", "requested/False", "width/2", "width/3", "width/4"}


def controls():
    item = law.finite("mysql-9.1.0", "trim", "local", "reverse")
    healthy = law.expected(item)
    check_observation(item, healthy, law.command(item))
    changed = {**healthy, "native-prepared": {**healthy["native-prepared"], "prepareWarnings": []}}
    try:
        check_observation(item, changed, law.command(item))
    except Failure as error:
        same(MEANING, error.signal)
        return {"missingDiagnostics": error.detail}
    raise Failure("mysql-inherited-alias-comparator-accepted")


def faults(_, tag):
    item = law.finite(tag, "trim", "local", "in-order")
    return [
        RenderFault("keep-untrimmed-inherited-alias", "sql-semantics-mysql/src/Rules/Query/InheritedAliases.php",
                    "new Name($name)", "$item->alias", item, LOST_BINDING, request(tag, law.command(item), [])),
        RenderFault("erase-inherited-alias-diagnostics", "sql-semantics-mysql/src/Ir/Rows/LocalProjectionSql.php",
                    "$source instanceof Select => $renderer->render($source, !$retain),",
                    "$source instanceof Select => $renderer->render($source, true),", item, MEANING, law.structure(item)),
    ]


def lost_binding(item, response):
    expected = semantic("Column d0.fIRST does not exist.\nColumn d0.Next does not exist.")
    require(rejects(response, expected), "mysql-inherited-alias-unrelated-refusal", response)
    raise Failure(LOST_BINDING, {"sql": law.command(item), "response": response})


def fault_probe(item, response, db):
    if response["status"] == "rejected":
        lost_binding(item, response)
    probe(item, response, db, sys.modules[__name__])


def replay_fault(rule, record):
    check_value(rule.item, record["healthyResponse"])
    detail = record["detail"]
    mutant = record["mutantResponse"]
    try:
        if rule.signal == LOST_BINDING:
            lost_binding(rule.item, mutant)
        check_value(rule.item, mutant)
        same(mutant["sql"], detail["sql"])
        check_observation(rule.item, detail["observed"], detail["sql"])
    except Failure as error:
        same(rule.signal, error.signal)
        same(detail, error.detail)
        return
    raise Failure("mysql-inherited-alias-fault-not-reproduced")


if __name__ == "__main__":
    main(sys.modules[__name__])
