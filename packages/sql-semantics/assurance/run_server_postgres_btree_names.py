"""Verify resolved automatic B-tree names through the public and native routes."""

from copy import deepcopy
import json
import sys

from evidence_transcript import canonical
from postgres_btree_name_cases import (
    DECOY, NS, enumerate_coordinates, expected, graph, identities, index_shape, schema_member, seeded, standalone,
)
from postgres_create_btree_cases import refusal
from run import Failure, TOOL, digest, require
from run_server import rejects, same
from run_server_join import main
from run_server_postgres_create_btree import check_preparation, observation, prepare
from run_server_postgres_create_btree import specifications as btree_profiles
from run_server_postgres_create_view import graph as view_graph
from sqlite_domain import RenderFault

LABEL = "postgres-btree-names"
OBSERVER = "postgres_btree_name_observer.py"
CONTRACT_NAME = "postgres-automatic-btree-name-evidence-v1.json"
CONTRACT = "24ab8dff9d692510c6d1493380f49eb3c2952603f2b694037a3cf173b8a65530"
COMPLETE_SEEDS = True
MEANING = "meaning-mismatch"
REFUSAL = "postgres-btree-lost-automatic-name-admission"
GRAPH = "postgres-automatic-index-graph"
SUM = "postgres-automatic-index-observer"
COMPARATOR_FAULTS = {"Ignore a native physical attribute label difference": "physical-label-order"}
OPEN = refusal("CREATE INDEX cannot allocate an automatic name in an open index namespace.")
COLLISION = refusal("A schema index must retain the actual presence of its name.")


def specifications():
    path = TOOL / CONTRACT_NAME
    require(digest(path) == CONTRACT, "postgres-automatic-index-contract")
    spec = json.loads(path.read_text())
    reference = spec["reference"]
    require(digest(TOOL / reference["file"]) == reference["sha256"] and spec["exploration"]["allowedExclusions"] == [],
            "postgres-automatic-index-reference")
    return spec, btree_profiles()[1]


def finite_ids(_, spec, _tag):
    declared = identities()
    sizes = {"standalone": 192, "schema": 30}
    require(all(spec["finite"][role]["coordinates"] == count for role, count in sizes.items())
            and all(sum(coordinate[0] == role for coordinate in declared) == count for role, count in sizes.items()),
            "postgres-automatic-index-finite-domain")
    return declared


def finite_id(_, response, item):
    check_value(item, response)
    return tuple(item["coordinate"])


def analysis_finite_id(engine, response, item, statement):
    same(item["original"], statement)
    return finite_id(engine, response, item)


def direct_finite_id(engine, response, item, request):
    same(direct(item), request)
    return finite_id(engine, response, item)


def trial(tag, coordinate):
    kind, *recipe = coordinate
    return standalone(tag, *recipe) if kind == "standalone" else schema_member(tag, *recipe)


def direct(item):
    return {"dialect": "postgres", "release": item["tag"], **item["direct"]}


def analyzed(item):
    return {"dialect": "postgres", "release": item["tag"], "sql": item["original"], "declarations": item["setup"],
            "schemaPath": [NS, DECOY, "public", "pg_catalog"]}


def refusals(tag):
    item = standalone(tag, "single", "named-table", 0, "plain")
    ctx = analyzed(item)
    yield {**ctx, "complete": False}, OPEN
    opaque = "CREATE INDEX ON " + NS + ".orders (id)"
    yield {**ctx, "declarations": [*ctx["declarations"], opaque]}, OPEN
    wrong = direct(item)
    wrong["createBtree"] = {**wrong["createBtree"], "presence": "occupied"}
    yield wrong, refusal("occupied index name requires IF NOT EXISTS")
    copied = deepcopy(direct(schema_member(tag, "single", "index")))
    earlier, second, _ = copied["createSchema"]["indexes"]
    second["name"] = earlier["name"]
    yield copied, COLLISION


def requests(_, tag, spec):
    for identity, coordinate in enumerate(enumerate_coordinates(spec["finite"])):
        item = trial(tag, coordinate)
        yield f"finite/{identity}", analyzed(item), item, None
        yield f"direct/{identity}", direct(item), item, None
    exploration = spec["exploration"]
    for seed in exploration["seeds"]:
        for number, item in enumerate(seeded(tag, seed, exploration["casesPerSeed"])):
            yield f"seed/{seed}/{number}", analyzed(item), item, None
    for number, (request, boundary) in enumerate(refusals(tag)):
        yield f"negative/{number}", request, None, boundary


def check_value(item, reply):
    profile = item["tag"] + "/utf8/standard-conforming-strings/native"
    require(reply["status"] == "constructed" and reply["profile"] == profile, GRAPH, reply)
    actual, desired = graph(item, reply), [index_shape(item, definition) for definition in item["indexes"]]
    require(canonical(actual) == canonical(desired), GRAPH, {"expected": desired, "observed": actual, "sql": reply["sql"]})
    if item["scope"] == "schema":
        direct = item["direct"]["createSchema"]
        same({name: direct[name] for name in ("name", "owner", "ifNotExists")}, reply["createSchema"])
        same(direct["tables"], reply["schemaTables"])
        same(direct["sequences"], reply["schemaSequences"])
        same([{**definition, "query": view_graph(definition["query"])} for definition in direct["views"]], reply["schemaViews"])
        same([], reply["schemaGrants"])


def observer_request(item, sql):
    return {"sql": sql, "trial": item}


def check_observation(item, observed, sql):
    require(observed["status"] in ("automatic-index-observation", "automatic-index-native-refusal"), SUM, observed)
    model = expected(item)
    require(canonical(model) == canonical(observed), MEANING, {"sql": sql, "expected": model, "observed": observed})


def sample_forms(forms, reply, _, item):
    root = reply["createBtree"]
    labels = [field for _, field, _ in root["bindings"]]
    layout = item["coordinate"][2]
    forms[f"competitor/{layout}"] += 1
    forms[f"arity/{len(root['keys'])}"] += 1
    forms[f"included/{bool(root['included'])}"] += 1
    forms[f"repeated/{len({slot for slot, _ in root['keys']}) != len(root['keys'])}"] += 1
    forms[f"utf8/{any(not field.isascii() for field in labels)}"] += 1


def required_forms(*_):
    return {*(f"arity/{n}" for n in range(1, 7)), *(f"competitor/{name}" for name in
            ("named-table", "generated-index", "sequence", "namespace-decoy")),
            "included/False", "included/True", "repeated/False", "repeated/True", "utf8/True"}


def controls():
    item = standalone("pg-17.2", "label-collisions", "named-table", 1, "plain")
    healthy = expected(item)
    check_observation(item, healthy, item["original"])
    damaged = deepcopy(healthy)
    damaged["indexes"][0]["orderedLabels"][1] = damaged["indexes"][0]["orderedLabels"][0]
    try:
        check_observation(item, damaged, item["original"])
    except Failure as error:
        same(MEANING, error.signal)
        return {"physical-label-order": {"input": item, "witness": error.detail}}
    raise Failure("postgres-automatic-index-comparator-accepted")


def fault_probe(item, reply, db):
    if reply["status"] != "constructed":
        require(item["scope"] == "schema" and rejects(reply, COLLISION),
                "postgres-automatic-index-unrelated-fault", reply)
        actual = db.request(observer_request(item, item["original"]))
        check_observation(item, actual, item["original"])
        raise Failure(REFUSAL, {"sql": item["original"], "expected": expected(item), "observed": actual, "boundary": reply})
    check_observation(item, db.request(observer_request(item, reply["sql"])), reply["sql"])
    check_value(item, reply)


def replay_fault(rule, record):
    item, detail = rule.item, record["detail"]
    check_value(item, record["healthyResponse"])
    if rule.signal == REFUSAL:
        require(rejects(record["mutantResponse"], COLLISION), REFUSAL)
        same(record["mutantResponse"], detail["boundary"])
        same(item["original"], detail["sql"])
        same(expected(item), detail["expected"])
        check_observation(item, detail["observed"], detail["sql"])
        return
    same(record["mutantResponse"]["sql"], detail["sql"])
    same(expected(item), detail["expected"])
    try:
        check_observation(item, detail["observed"], detail["sql"])
    except Failure as error:
        same(rule.signal, error.signal)
        same(detail, error.detail)
        return
    raise Failure("postgres-automatic-index-fault-not-replayed")


def faults(_, tag):
    source = "sql-semantics-postgres/src/Ir/Schema/"
    binder = source + "Index/Btree/Binding/IndexNameLowering.php"
    picks = [
        ("forget-included-attribute", binder, "[...$keys, ...$included]", "$keys",
         standalone(tag, "includes", "named-table", 0, "plain"), MEANING),
        ("reuse-occupied-suffix", binder, "while ($occupied($name));", "while (false);",
         standalone(tag, "includes", "named-table", 3, "plain"), MEANING),
        ("change-name-namespace", binder, "$name, $schema)", '$name, new Name("alternate_fixture"))',
         standalone(tag, "includes", "namespace-decoy", 3, "plain"), MEANING),
        ("clip-one-more-character", source + "Key/IndexNames.php", "max($limit, 0)", "max($limit - 3, 0)",
         standalone(tag, "equal-utf8-bytes", "named-table", 0, "plain"), MEANING),
        ("forget-earlier-schema-index", source + "Namespace/Index/SchemaIndexLowering.php",
         "$occupied[$index->name->value] ??= true;", "", schema_member(tag, "single", "none"), REFUSAL),
    ]
    return [RenderFault("postgres-automatic-" + name, file, before, after, item, signal, analyzed(item))
            for name, file, before, after, item, signal in picks]


if __name__ == "__main__":
    main(sys.modules[__name__])
