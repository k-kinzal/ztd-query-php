"""Verify index identity after PostgreSQL catalogs that contain unnamed declarations."""

from copy import deepcopy
import json
import sys

from postgres_anonymous_catalog_cases import (
    OPERATIONS, PLACEMENTS, analyzed, coordinates, direct, expected, generated, identities, make_case, value_shape,
)
from postgres_create_btree_cases import refusal
from run import Failure, TOOL, digest, require
from run_server import rejects, same
from run_server_join import main
from run_server_postgres_create_btree import check_preparation, observation, prepare
from run_server_postgres_create_btree import specifications as btree_profiles
from sqlite_domain import RenderFault

LABEL = "postgres-anonymous-catalog"
OBSERVER = "postgres_anonymous_catalog_observer.py"
CONTRACT_NAME = "postgres-anonymous-catalog-index-evidence-v1.json"
CONTRACT = "c63e68a988bc0e8909f9ea8d0979600b0e9471372c3004623dce92f49633e93d"
MEANING = "meaning-mismatch"
REFUSED = "postgres-catalog-forgot-allocated-index"
OPEN = refusal("CREATE INDEX cannot allocate an automatic name in an open index namespace.")
COMPARATOR_FAULTS = {"Ignore a replacement of the wrong physical index in the comparator": "wrong-file-replaced"}


def specifications():
    path = TOOL / CONTRACT_NAME
    require(digest(path) == CONTRACT, "postgres-anonymous-catalog-contract")
    spec = json.loads(path.read_text())
    reference = spec["reference"]
    require(digest(TOOL / reference["file"]) == reference["sha256"] and spec["exploration"]["allowedExclusions"] == [],
            "postgres-anonymous-catalog-reference")
    return spec, btree_profiles()[1]


def finite_ids(_, spec, _tag):
    fixed = identities()
    require(set(coordinates(spec["finite"])) == fixed and len(fixed) == spec["finite"]["coordinates"] * len(OPERATIONS),
            "postgres-anonymous-index-finite-domain")
    return fixed


def finite_id(_, response, item):
    check_value(item, response)
    return tuple(item["coordinate"])


def analysis_finite_id(engine, response, item, statement):
    same(item["original"], statement)
    return finite_id(engine, response, item)


def direct_finite_id(engine, response, item, request):
    same(direct(item), request)
    return finite_id(engine, response, item)


def refusals(tag):
    item = make_case(tag, "single", "permanent", 0, "create")
    context = analyzed(item)
    yield {**context, "complete": False}, OPEN
    opaque = "CREATE INDEX ON app_catalog.orders ((id + 1))"
    unknown = {**context, "declarations": [*context["declarations"], opaque]}
    yield unknown, OPEN
    yield ({**unknown, "sql": "DROP INDEX IF EXISTS app_catalog.unavailable"},
           refusal("The index selector cannot infer absence in an open index namespace."))
    wrong = direct(make_case(tag, "single", "permanent", 0, "drop"))
    wrong["dropIndex"] = {**wrong["dropIndex"], "targets": [["missing", "app_catalog", "unavailable", False]]}
    yield wrong, refusal("DROP INDEX needs ordinary bound indexes or guarded absence.")


def requests(_, tag, spec):
    for ordinal, axes in enumerate(coordinates(spec["finite"])):
        item = make_case(tag, *axes)
        yield f"finite/{ordinal}", analyzed(item), item, None
        yield f"direct/{ordinal}", direct(item), item, None
    exploration = spec["exploration"]
    for seed in exploration["seeds"]:
        for ordinal, item in enumerate(generated(tag, seed, exploration["casesPerSeed"])):
            yield f"seed/{seed}/{ordinal}", analyzed(item), item, None
        for ordinal, item in enumerate(generated(tag, seed + 1, exploration["directCasesPerSeed"])):
            yield f"direct-seed/{seed}/{ordinal}", direct(item), item, None
    for ordinal, (request, boundary) in enumerate(refusals(tag)):
        yield f"negative/{ordinal}", request, None, boundary


def check_value(item, response):
    key, shape = value_shape(item)
    profile = item["tag"] + "/utf8/standard-conforming-strings/native"
    require(response["status"] == "constructed" and response["profile"] == profile
            and response[key] == shape and (key != "reindex" or response.get("verbose") is False),
            "postgres-anonymous-index-graph", {"expected": shape, "observed": response})


def observer_request(item, sql):
    return {"sql": sql, "trial": item}


def check_observation(item, actual, sql):
    model = expected(item)
    detail = {"sql": sql, "expected": model, "observed": actual}
    require(actual["status"] in ("anonymous-catalog-observation", "anonymous-catalog-native-refusal"),
            "postgres-anonymous-index-observer", detail)
    require(actual == model, MEANING, detail)


def sample_forms(forms, response, _, item):
    kind, _ = value_shape(item)
    actual = response[kind]
    forms[f"role/{kind}"] += 1
    owner = actual["table"][:2] if kind == "createBtree" else (
        [actual["target"][0], actual["target"][2]] if kind == "reindex" else [actual["targets"][0][1], actual["targets"][0][3]])
    forms[f"schema/{owner[0]}"] += 1
    forms[f"utf8/{not owner[1].isascii()}"] += 1
    if kind == "createBtree":
        slots = [slot for slot, _ in actual["keys"]]
        forms[f"repeat/{len(slots) != len(set(slots))}"] += 1
        forms[f"include/{bool(actual['included'])}"] += 1


def sample_native_forms(forms, observed, _, item):
    forms[f"previous/{len(observed['before'][0][1])}"] += 1
    forms[f"occupied-prefix/{item['coordinate'][2] > 0}"] += 1
    forms[f"replaced/{bool(observed['changedFiles'])}"] += 1


def required_forms(*_):
    return {*(f"schema/{namespace}" for namespace in PLACEMENTS.values()), "role/createBtree", "role/dropIndex", "role/reindex",
            "utf8/True", "utf8/False", "repeat/True", "repeat/False", "include/True", "include/False",
            "previous/2", "previous/3", "previous/4", "occupied-prefix/True", "occupied-prefix/False", "replaced/True", "replaced/False"}


def controls():
    item = make_case("pg-17.2", "single", "permanent", 0, "reindex")
    healthy = expected(item)
    check_observation(item, healthy, item["original"])
    wrong = deepcopy(healthy)
    wrong["changedFiles"][0][0] = "alter_catalog_decoy"
    try:
        check_observation(item, wrong, item["original"])
    except Failure as error:
        same(MEANING, error.signal)
        return {"wrong-file-replaced": {"input": item, "witness": error.detail}}
    raise Failure("postgres-anonymous-index-comparator-accepted")


def fault_probe(item, response, db):
    if response["status"] != "constructed":
        require(rejects(response, OPEN), "postgres-anonymous-index-unrelated-refusal", response)
        actual = db.request(observer_request(item, item["original"]))
        check_observation(item, actual, item["original"])
        raise Failure(REFUSED, {"boundary": response, "sql": item["original"], "observed": actual})
    command = response["sql"]
    check_observation(item, db.request(observer_request(item, command)), command)
    check_value(item, response)


def replay_fault(rule, record):
    item, detail = rule.item, record["detail"]
    check_value(item, record["healthyResponse"])
    if rule.signal == REFUSED:
        require(rejects(record["mutantResponse"], OPEN), "postgres-anonymous-index-fault-boundary")
        same(record["mutantResponse"], detail["boundary"])
        same(item["original"], detail["sql"])
        check_observation(item, detail["observed"], detail["sql"])
        return
    same(record["mutantResponse"]["sql"], detail["sql"])
    if item["operation"] == "create":
        same("42P07", detail["observed"]["result"]["sqlState"])
        same(expected(item)["before"], detail["observed"]["before"])
    else:
        same("anonymous-catalog-observation", detail["observed"]["status"])
        same([["alter_catalog_decoy", item["initial"][-1]]], detail["observed"]["changedFiles"])
    try:
        check_observation(item, detail["observed"], detail["sql"])
    except Failure as error:
        same(rule.signal, error.signal)
        same(detail, error.detail)
        return
    raise Failure("postgres-anonymous-index-fault-not-replayed")


def faults(_, tag):
    source = "sql-semantics-postgres/src/"
    prefix = make_case(tag, "single", "schema-member", 1, "create")
    wrong = make_case(tag, "single", "permanent", 0, "reindex")
    removed = "$index->name ?? (new AnonymousIndexName())->resolve($binding, $index, $schema, $table, $this->snapshot())"
    target = "$this->target->quoted()"
    reindex = """str_replace('"app_catalog".', '"alter_catalog_decoy".', $this->target->quoted())"""
    return [
        RenderFault("forget-prior-anonymous-catalog-names", source + "Lowering/Catalog/CatalogIndexes.php",
                    removed, "$index->name", prefix, REFUSED, analyzed(prefix)),
        RenderFault("ignore-local-namespace-occupancy", source + "Lowering/Catalog/Index/AnonymousIndexName.php",
                    "isset($local[$candidate->value])", "false", prefix, MEANING, analyzed(prefix)),
        RenderFault("rebuild-the-same-name-in-another-schema", source + "Ir/Schema/Index/ReindexIndex.php",
                    target, reindex, wrong, MEANING, analyzed(wrong)),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])
