"""Compare inherited derived labels on MySQL releases without separate lists."""

from collections import Counter
from functools import lru_cache
import json
import sys

from evidence_transcript import canonical
import mysql_inherited_local_alias_cases as law
import run_server_mysql_inherited_local_alias as modern
from run import Failure, TOOL, digest, require
from run_server import rejects, same
from run_server_join import main
from run_server_mysql_signed_membership import refusal
from run_server_mysql_signed_projection_alias import OBSERVER, observation, observer_request
from run_server_mysql_with_projection import core, request
from run_server_mysql_with_projection_alias import check_preparation as check_merged, prepare as prepare_merged
from sqlite_domain import RenderFault

LABEL = "mysql-legacy-inherited-alias"
CONTRACT_NAME = "mysql-legacy-inherited-alias-v2.json"
CONTRACT = "93d4657509db3fd7129a1d78364332f94ec4f533ad94341d563358dc2094650b"
LEGACY = ("mysql-5.6.51", "mysql-5.7.44")
PLACEMENTS = ("local", "nested", "join")
INHERITED = "inheritsRequestedAliases"
UNBOUND = "mysql-legacy-inherited-alias-wrong-native-name"
DUPLICATE = "mysql-legacy-inherited-alias-duplicate-published"
COMPARATOR_FAULTS = {"Ignore a missing warning from the native prepared phase.": "missingDiagnostics"}


def specifications():
    path = TOOL / CONTRACT_NAME
    require(digest(path) == CONTRACT, "mysql-legacy-inherited-alias-contract")
    contract = json.loads(path.read_text())
    first = TOOL / "mysql-legacy-inherited-alias-v1.json"
    require(contract["extends"] == {"path": first.name, "sha256": digest(first)}, "mysql-legacy-inherited-alias-base-contract")
    previous = json.loads(first.read_text())
    require(all(contract[key] == previous[key] for key in ("finite", "exploration", "negative", "faults"))
            and contract["observations"][:len(previous["observations"])] == previous["observations"]
            and contract["optimizer"] == {"mysql-5.6.51": "materialized", "mysql-5.7.44": "derived-merge=on"},
            "mysql-legacy-inherited-alias-baseline-obligations")
    former = TOOL / modern.CONTRACT_NAME
    require(contract["related"] == {"path": former.name, "sha256": digest(former)}
            and contract["profiles"] == list(LEGACY)
            and contract["finite"]["allowedExclusions"] == contract["exploration"]["allowedExclusions"] == [],
            "mysql-legacy-inherited-alias-profile")
    from run_server_mysql_signed_range import specifications as profiles

    mysql = profiles()[1]["mysql"]
    return contract, {"mysql": {**mysql, "releases": [tag.removeprefix("mysql-") for tag in LEGACY]}}


def prepare(engine, db):
    if db.environment["database"][0] == "5.6.51":
        fixture = db.request({"fixture": True})
        options = db.request({"sql": "SELECT @@session.optimizer_switch AS options"})
        return {"fixture": fixture, "optimizerSwitch": options}
    return prepare_merged(engine, db)


def check_preparation(engine, environment, response):
    if environment["database"][0] != "5.6.51":
        return check_merged(engine, environment, response)
    same(["native-prepared", "text"], environment["protocols"])
    same({"status": "fixture"}, response["fixture"])
    for protocol in environment["protocols"]:
        measured = response["optimizerSwitch"][protocol]
        require(measured["status"] == "rows" and "derived_merge" not in measured["rows"][0][0],
                "mysql-legacy-inherited-alias-materialized-profile", measured)


def finite_ids(_, contract, _tag):
    scheduled = list(law.grid(contract))
    independent = {(shape, place, order) for shape, place, order in law.independent_ids() if place in PLACEMENTS}
    require(len(scheduled) == len(independent) == contract["finite"]["coordinatesPerRoute"]
            and set(scheduled) == independent, "mysql-legacy-inherited-alias-finite-definition")
    return independent


def requested(child):
    return any(isinstance(label, list) and label[0] == "requested-result-alias" for _, label in child["columns"])


def structure(item, observed=False):
    model = law.structure(item, observed)
    if item["placement"] == "join":
        for position in ("left", "right"):
            side = model["source"][position]
            if requested(side["derived"]):
                side[INHERITED] = True
    else:
        projection = model["local"]
        if item["placement"] == "nested":
            projection = projection["definition"]["local"]
        if requested(projection["definition"]):
            projection[INHERITED] = True
    return model


def graph(query):
    if "local" in query:
        local = query["local"]
        return "local", local.get(INHERITED, False), local["columns"], graph(local["definition"])
    return core(query)


@lru_cache
def observed_ids(tag):
    identities = [(canonical(graph(structure(law.finite(tag, *identity), observed=True))), identity)
                 for identity in law.independent_ids() if identity[1] in PLACEMENTS]
    observed = dict(identities)
    require(len(observed) == len(identities), "mysql-legacy-inherited-alias-ambiguous-identity")
    return observed


def finite_id(_, response, item):
    key = canonical(graph(response))
    identities = observed_ids(item["tag"])
    require(key in identities, "mysql-legacy-inherited-alias-missing-identity", response)
    return identities[key]


def analysis_finite_id(engine, response, item, sql):
    same(law.command(item), sql)
    return finite_id(engine, response, item)


def direct_finite_id(engine, response, item, input_):
    same(structure(item), input_)
    return finite_id(engine, response, item)


def rejected_finite_ids(*_):
    return "written-alias", "duplicate", "separate-list"


def observe_rejection(_, item, raw, sql):
    if item["invalid"] == "separate-list":
        result = {"status": "native-error", "sqlState": "42000", "code": 1064}
        require(raw == {"native-prepared": result, "text": result}, "mysql-legacy-alias-native-separate-list", raw)
    else:
        modern.check_native_error(item, raw, sql)
    return item["invalid"]


def negatives(tag):
    yield next(modern.negatives(tag))
    yield (request(tag, "SELECT d.target FROM (SELECT 1 AS ` First`, 2 AS first, 3 AS target) d", []),
           {"invalid": "duplicate"}, modern.semantic("Duplicate column name"))
    yield (request(tag, "SELECT d.name FROM (SELECT 1 AS ` first`) d (name)", []),
           {"invalid": "separate-list"}, syntax_boundary(tag))
    good = structure(law.finite(tag, "trim", "local", "in-order"))
    outside = {**good["local"], "columns": [[2, "outside"]]}
    yield {**good, "local": outside}, None, refusal("The local slot must belong to the definition.")
    duplicate = structure(law.finite(tag, "trim", "local", "in-order"))
    duplicate["local"]["definition"]["columns"][1][1] = "first"
    yield duplicate, None, duplicate_boundary()


def syntax_boundary(_):
    return {"exception": modern.DIAGNOSTICS + "AnalysisException", "message": "Unexpected '(' at line 1, column 45, expected ;"}


def duplicate_boundary():
    return refusal("Inherited requested aliases must resolve to distinct names.")


def requests(_, tag, contract):
    for index, identity in enumerate(law.grid(contract)):
        item = law.finite(tag, *identity)
        yield f"finite/{index}", request(tag, law.command(item), []), item, None
        yield f"direct/{index}", structure(item), item, None
    for index, (input_, item, reason) in enumerate(negatives(tag)):
        yield f"negative/{index}", input_, item, reason
    settings = contract["exploration"]
    for seed in settings["seeds"]:
        for offset, group, count in ((0, "seed", settings["casesPerSeed"]), (1, "direct-seed", settings["directCasesPerSeed"])):
            generated = law.seeded(tag, seed + offset, count, placements=PLACEMENTS)
            for index, item in enumerate(generated):
                input_ = structure(item) if offset else request(tag, law.command(item), [])
                yield f"{group}/{seed}/{index}", input_, item, None


def check_value(item, response):
    optimizer = "materialized" if item["tag"] == LEGACY[0] else "derived-merge=on"
    profile = item["tag"] + "/utf8mb4/lower-case-table-names=0/sql-mode=empty/native/" + optimizer
    require(response["status"] == "constructed" and response["profile"] == profile,
            "mysql-legacy-inherited-alias-construction", response)
    expected = graph(structure(item, observed=True))
    require(graph(response) == expected, "mysql-legacy-inherited-alias-graph", {"expected": expected, "observed": response})


def expected(item):
    native = law.expected(item)
    native["native-prepared"]["executeWarnings"] = []
    return native


def check_observation(item, actual, sql):
    observed = expected(item)
    detail = {"sql": sql, "expected": observed, "observed": actual}
    missing = {"status": "native-error", "sqlState": "42S22", "code": 1054}
    if actual == {mode: missing for mode in observed}:
        raise Failure(UNBOUND, detail)
    require(all(actual[mode]["status"] == "rows" for mode in observed), "mysql-legacy-inherited-alias-native-refusal", detail)
    require(canonical(observed) == canonical(actual), modern.MEANING, detail)


def sample_forms(forms: Counter, response, engine, item):
    modern.sample_forms(forms, response, engine, item)
    if "local" in response:
        local = response["local"]
        enabled = local.get(INHERITED) or local["definition"].get("local", {}).get(INHERITED, False)
        forms[f"inherited/{bool(enabled)}"] += 1
    else:
        for side in ("left", "right"):
            forms[f"inherited/{response['source'][side].get(INHERITED, False)}"] += 1


def required_forms(*_):
    return {*(f"placement/{place}" for place in PLACEMENTS), "width/2", "width/3", "width/4",
            "occurrence/0", "occurrence/1", "inherited/True", "inherited/False"}


def controls():
    item = law.finite(LEGACY[0], "empty", "local", "reverse")
    healthy = expected(item)
    check_observation(item, healthy, law.command(item))
    lost = {**healthy, "native-prepared": {**healthy["native-prepared"], "prepareWarnings": []}}
    try:
        check_observation(item, lost, law.command(item))
    except Failure as error:
        same(modern.MEANING, error.signal)
        return {"missingDiagnostics": error.detail}
    raise Failure("mysql-legacy-inherited-alias-comparator-accepted")


def faults(_, tag):
    item = law.finite(tag, "trim", "local", "in-order")
    rejected = list(negatives(tag))[-1][0]
    return [
        RenderFault("render-inherited-column-by-generated-name", "sql-semantics-mysql/src/Ir/SelectSql.php",
                    "if ($inherited === null) {", "if (true) {", item, UNBOUND, structure(item)),
        RenderFault("admit-duplicate-inherited-local-alias", "sql-semantics-mysql/src/Ir/Ordering/LocalProjectionAliases.php",
                    "Check::input(!isset($seen[$key]),", "Check::input(true,", {"invalid": "duplicate"}, DUPLICATE, rejected),
    ]


def fault_probe(item, response, db):
    if "invalid" in item:
        if rejects(response, duplicate_boundary()):
            return
        require(response["status"] == "constructed", "mysql-legacy-inherited-alias-spurious-refusal", response)
        sql = response["sql"]
        native = db.request(observer_request(item, sql))
        modern.check_native_error(item, native, sql)
        raise Failure(DUPLICATE, {"sql": sql, "observed": native})
    check_value(item, response)
    sql = response["sql"]
    check_observation(item, observation(db.request(observer_request(item, sql))), sql)


def replay_fault(rule, record):
    item = rule.item
    healthy, mutant = record["healthyResponse"], record["mutantResponse"]
    detail = record["detail"]
    same(mutant["sql"], detail["sql"])
    if rule.signal == DUPLICATE:
        require(rejects(healthy, duplicate_boundary()), "mysql-legacy-inherited-alias-control-refusal", healthy)
        modern.check_native_error(item, detail["observed"], detail["sql"])
        return
    check_value(item, healthy)
    check_value(item, mutant)
    try:
        check_observation(item, detail["observed"], detail["sql"])
    except Failure as error:
        same(rule.signal, error.signal)
        same(detail, error.detail)
        return
    raise Failure("mysql-legacy-inherited-alias-fault-not-reproduced")


if __name__ == "__main__":
    main(sys.modules[__name__])
