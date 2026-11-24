"""Verify PostgreSQL CREATE VIEW references to earlier ordinary views."""

from copy import deepcopy
from itertools import product
import json
import os
from random import Random
import sys

from native_postgres_fixture import ENV as FIXTURE_CONTAINER
from postgres_create_btree_cases import refusal
from postgres_create_schema_cases import EFFECTIVE
from postgres_schema_view_dependencies_cases import (
    EXTERNAL, NS, ORDER, OWNERS, PROVIDERS, STATUS, analyzed, direct, expected, sql, trial,
)
from run import Failure, TOOL, digest, require
from run_server import same
from run_server_join import main, negative_count
from run_server_postgres_create_schema import check_preparation, observation, prepare
from run_server_postgres_create_schema import specifications as namespace_profiles
from run_server_postgres_create_view import graph
from sqlite_domain import RenderFault

LABEL = "postgres-schema-view-dependencies"
CONTRACT_NAME = "postgres-schema-view-dependencies-v1.json"
CONTRACT = "1351a17f257e9339f7bedf812f521caf6b51e6fb340d47721b47f6fc9bc2d81f"
OBSERVER = "postgres_schema_view_dependencies_observer.py"
AXES = ("owners", "providers", "widths", "transactional")
WRONG_FIELD = "A schema view requires each local input field in the checked native kind."
FUTURE = "A schema view must resolve its local view input to a preceding ordinary view."


def specifications():
    path = TOOL / CONTRACT_NAME
    require(digest(path) == CONTRACT, "postgres-schema-view-dependencies-contract")
    spec = json.loads(path.read_text())
    same([], spec["exploration"]["allowedExclusions"])
    return spec, namespace_profiles()[1]


def grid(spec):
    return product(*(spec["finite"][axis] for axis in AXES))


def finite_ids(_, spec, _tag):
    independent = set(product(OWNERS, PROVIDERS, (2, 3), (False, True)))
    require(set(grid(spec)) == independent and len(independent) == 24, "postgres-schema-view-dependencies-grid")
    return independent


def analysis_finite_id(_, response, item, command):
    key = tuple(item["coordinate"])
    same(sql(trial(item["release"], *key)), command)
    check_value(item, response)
    return key


def direct_finite_id(_, response, item, request):
    key = tuple(item["coordinate"])
    same(direct(trial(item["release"], *key)), request)
    check_value(item, response)
    return key


def generated(tag, seed, count):
    rng = Random(seed)
    providers = list(PROVIDERS)
    rng.shuffle(providers)
    for i in range(count):
        width = i % 4 + 2
        roles = [{"parent": (step - 1 if i % 2 == 0 else rng.randrange(step)), "order": rng.sample(ORDER, len(ORDER)),
                  "rename": bool(rng.getrandbits(1)), "filter": bool(rng.getrandbits(1))}
                 for step in range(1, width)]
        yield trial(tag, OWNERS[i % len(OWNERS)], providers[i % len(providers)], width, bool(rng.getrandbits(1)),
                    quoted_name=bool(rng.getrandbits(1)), explicit=bool(rng.getrandbits(1)), roles=roles, base=rng.randrange(4, 85))


def edges(tag):
    for active in (False, True):
        value = trial(tag, "effective", "external-view", 1, active, explicit=True, quoted_name=active)
        yield f"standalone/{active}", {**value, "standalone": True}


def refusals(tag):
    item = trial(tag, "effective", "constant-values", 2, True)
    original = f"CREATE SCHEMA {NS} CREATE VIEW early AS SELECT n FROM {NS}.future CREATE VIEW future AS SELECT 1 AS n"
    native = {**item, "command": original, "nativeRefusal": "42P01"}
    yield analyzed(native), native, refusal("Relation future does not exist.\nColumn n does not exist.", "SemanticException")
    request = direct(item)
    request["createSchema"]["views"].reverse()
    yield request, None, refusal(FUTURE)
    request = direct(item)
    request["createSchema"]["views"][1]["query"]["source"]["relationKind"] = "table"
    yield request, None, refusal("A schema view must resolve its local input to a preceding base table.")
    request = direct(item)
    for row in request["createSchema"]["views"][0]["query"]["postgresValues"]:
        if row[0][0] == "integer":
            row[0][0] = "wide-integer"
    yield request, None, refusal(WRONG_FIELD)
    request = direct(item)
    request["createSchema"]["views"][0]["columns"][0] = "other_heading"
    yield request, None, refusal(WRONG_FIELD)
    request = direct({**item, "provider": "external-view", "standalone": True, "width": 1})
    request["createView"]["checkOption"] = "LOCAL"
    yield request, None, refusal("A view check option requires a simple base-table projection.")


def requests(engine, tag, spec):
    for index, key in enumerate(grid(spec)):
        item = trial(tag, *key, coordinate=list(key))
        yield f"finite/{index}", analyzed(item), item, None
        yield f"direct/{index}", direct(item), item, None
    for label, item in edges(tag):
        yield f"edge/{label}/analyze", analyzed(item), item, None
        yield f"edge/{label}/direct", direct(item), item, None
    exploration = spec["exploration"]
    for seed in exploration["seeds"]:
        for index, item in enumerate(generated(tag, seed, exploration["casesPerSeed"])):
            yield f"seed/{seed}/{index}", analyzed(item), item, None
            yield f"direct-seed/{seed}/{index}", direct(item), item, None
    invalid = list(refusals(tag))
    same(negative_count(spec, engine, tag), len(invalid))
    for index, (request, item, rejected) in enumerate(invalid):
        yield f"negative/{index}", request, item, rejected


def check_value(item, response):
    same(["constructed", item["release"] + "/utf8/standard-conforming-strings/native"],
         [response.get("status"), response.get("profile")])
    construction = direct(item)
    if item["standalone"]:
        shape = {key: value for key, value in construction["createView"].items() if key != "release"}
        same({**shape, "query": graph(shape["query"])}, response["createView"])
        return
    root = construction["createSchema"]
    same({key: root[key] for key in ("name", "owner", "ifNotExists")}, response["createSchema"])
    same(root["tables"], response["schemaTables"])
    for key in ("schemaSequences", "schemaIndexes", "schemaGrants"):
        same([], response[key])
    same([{**shape, "query": graph(shape["query"])} for shape in root["views"]], response["schemaViews"])


def measured_view(state, oids, target, own, witness):
    rows = state["physicalDependencies"]
    require(sorted((edge[:5] for edge in rows), key=json.dumps) == state["dependencies"], "meaning-mismatch", witness)
    for schema, name, column, slot, kind, oid in rows:
        require(oids.get(schema, {}).get(name) == oid and ((kind == "i") == (
            (schema, name, column, slot) == (target, own, None, 0))), "meaning-mismatch", witness)
    return {key: value for key, value in state.items() if key != "physicalDependencies"}


def measure(state, item, witness):
    if state is None:
        return None
    namespace = item["namespace"]
    coords, oids = state["namespaces"], state["relationOids"]
    scopes = [EXTERNAL] if state["owner"] is None else [EXTERNAL, namespace]
    names = set(state["views"]) | ({"feed_data"} if state["table"] is not None else set())
    identities = [node for node, _ in coords.values()] + [oid for objects in oids.values() for oid in objects.values()]
    require(set(scopes) == set(coords) and set(oids) == ({EXTERNAL, namespace} if names else {EXTERNAL})
            and set(oids.get(namespace, {})) == names and set(oids[EXTERNAL]) == {"provider"}
            and len(identities) == len(set(identities)) and all(type(oid) is int and oid > 0 for oid in identities)
            and coords[EXTERNAL][1] == EFFECTIVE and coords.get(namespace, [None, None])[1] == state["owner"],
            "meaning-mismatch", witness)
    external = measured_view(state["external"], oids, EXTERNAL, "provider", witness)
    views = {name: measured_view(view, oids, namespace, name, witness) for name, view in state["views"].items()}
    return {**{key: state[key] for key in ("owner", "table")}, "external": external, "views": views}


def check_observation(item, actual, statement):
    model = expected(item)
    witness = {"expected": model, "observed": actual, "sql": statement}
    require(actual.get("status") == STATUS, "postgres-schema-view-dependencies-observer-scope", witness)
    parts = ("before", "during", "settled")
    checked = {key: measure(actual[key], item, witness) if key in parts else actual[key] for key in model}
    require(checked == model, "meaning-mismatch", witness)
    retained = [actual[phase] for phase in parts if actual[phase] is not None]
    require(len({state["relationOids"][EXTERNAL]["provider"] for state in retained}) == 1
            and len({state["namespaces"][EXTERNAL][0] for state in retained}) == 1, "meaning-mismatch", witness)
    if not item["active"] and actual["during"] is not None:
        require(actual["settled"] == actual["during"], "meaning-mismatch", witness)
    error = actual["error"]
    require(error is None if model["sqlState"] == "00000" else error is not None and error[:2] == ["ERROR", model["sqlState"]],
            "meaning-mismatch", witness)


def observer_request(item, statement):
    return {"trial": item, "sql": statement}


def observe_rejection(_, item, actual, statement):
    same(item["command"], statement)
    check_observation(item, actual, statement)
    return "future-reference"


def rejected_finite_ids(_, _tag):
    return {"future-reference"}


def sample_forms(forms, response, _engine, item):
    for field in ("owner", "provider", "active", "quoted", "qualified"):
        forms[f"{field}/{item[field]}"] += 1
    declarations = response["schemaViews"]
    forms[f"width/{len(declarations)}"] += 1
    depths = {}
    for view in declarations:
        target, query = view["target"], view["query"]
        source = query.get("source")
        resolved = "constant" if source is None else source.get("relationKind", "table")
        forms[f"operand/{resolved}"] += 1
        if source is not None:
            forms[f"filter/{query['where'] is not None}"] += 1
            for kind in source["columnKinds"]:
                forms[f"kind/{kind}"] += 1
        parent = None if source is None else (source["schema"], source["table"])
        depth = 0 if parent not in depths else depths[parent] + 1
        depths[tuple(target)] = depth
        forms[f"depth/{depth}"] += 1
    for plan in item["plans"]:
        forms[f"renamed/{plan['rename']}"] += 1


def required_forms(*_):
    return {*(f"owner/{mode}" for mode in OWNERS), *(f"provider/{kind}" for kind in PROVIDERS),
            *(f"width/{width}" for width in range(2, 6)), *(f"depth/{depth}" for depth in range(5)),
            *(f"{key}/{value}" for key in ("active", "quoted", "qualified", "filter", "renamed") for value in (True, False)),
            "operand/constant", "operand/view", "operand/table", "kind/int4", "kind/int8", "kind/text", "kind/bool"}


def controls():
    item = trial("pg-17.2", "effective", "constant-values", 2, True)
    good = deepcopy({**expected(item), "error": None})
    for phase in ("before", "during", "settled"):
        stage = good[phase]
        stage["namespaces"] = {EXTERNAL: [100, EFFECTIVE]}
        stage["relationOids"] = {EXTERNAL: {"provider": 102}}
        stage["external"]["physicalDependencies"] = [[*stage["external"]["dependencies"][0], 102]]
        if phase == "during":
            stage["namespaces"][NS] = [101, EFFECTIVE]
            stage["relationOids"][NS] = {"provider": 103, "level1": 104}
            for view in stage["views"].values():
                view["physicalDependencies"] = [[*edge, stage["relationOids"][edge[0]][edge[1]]] for edge in view["dependencies"]]
    check_observation(item, good, sql(item))
    evidence = {}
    for label in ("rows", "physicalDependencies"):
        corrupted = deepcopy(good)
        value = corrupted["during"]["views"]["level1"]
        if label == "rows":
            value[label].pop(1)
        else:
            next(edge for edge in value[label] if edge[1] == "provider")[-1] = 102
        try:
            check_observation(item, corrupted, sql(item))
        except Failure as failure:
            same("meaning-mismatch", failure.signal)
            evidence[label] = failure.detail
            continue
        raise Failure("schema-view-dependency-comparison-ignored-observation", label)
    return evidence


def check_fault_witness(item, witness):
    same(expected(item), witness["expected"])
    same(STATUS, witness["observed"]["status"])


def faults(_, tag):
    path = "sql-semantics-postgres/src/Ir/Schema/Namespace/CreateSchema.php"
    once = trial(tag, "effective", "constant-values", 2, True)
    root = "$view->toString()"
    return [
        RenderFault("schema-view-dependency-reverse-phase", path, "foreach ($this->views as $view)",
                    "foreach (array_reverse($this->views) as $view)", once, "meaning-mismatch", direct(once)),
        RenderFault("schema-view-dependency-choose-shadow", path, root,
                    """str_replace(' FROM "ir_view_chain"."provider"', ' FROM "ir_view_chain_decoy"."provider"', $view->toString())""",
                    once, "meaning-mismatch", direct(once)),
        RenderFault("schema-view-dependency-read-different-text-field", path, root,
                    """str_replace('"__ztd_source"."mystery" AS "mystery"', '"__ztd_source"."note" AS "mystery"', $view->toString())""",
                    once, "meaning-mismatch", direct(once)),
    ]


if __name__ == "__main__":
    require(FIXTURE_CONTAINER in os.environ, "postgres-schema-view-dependencies-disposable-service-required")
    main(sys.modules[__name__])
