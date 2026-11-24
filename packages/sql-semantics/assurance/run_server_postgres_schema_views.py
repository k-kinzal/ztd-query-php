"""Verify namespace view composition and PostgreSQL's native view effects."""

from copy import deepcopy
from itertools import product
import json
import os
from random import Random
import sys

from native_postgres_fixture import ENV as FIXTURE_CONTAINER
from postgres_create_btree_cases import refusal
from postgres_schema_sequences_cases import sequence_shape
from postgres_schema_views_cases import (
    EXTERNAL, LAYOUTS, NS, ORDERS, OWNERS, STATUS, analyzed, child, direct, expected, generators, members, sql, trial,
)
from run import Failure, TOOL, digest, require
from run_server import same
from run_server_join import main, negative_count
from run_server_postgres_create_schema import check_preparation, observation, prepare
from run_server_postgres_create_schema import specifications as namespace_profiles
from run_server_postgres_create_view import graph
from sqlite_domain import RenderFault

LABEL = "postgres-schema-views"
CONTRACT_NAME = "postgres-schema-views-v3.json"
CONTRACT = "01a84a719c11e3d51196c3436cedbc7055620d347a55993d022806493dbc6102"
OBSERVER = "postgres_schema_views_observer.py"
AXES = ("owners", "layouts", "writtenOrders", "transactional")
MEMBER_SCOPE = "A schema view must belong to its namespace and release."
EMPTY_KIND = (None, True, False)


def specifications():
    path = TOOL / CONTRACT_NAME
    require(digest(path) == CONTRACT, "postgres-schema-views-contract")
    spec = json.loads(path.read_bytes())
    same([], spec["exploration"]["allowedExclusions"])
    return spec, namespace_profiles()[1]


def grid(spec):
    return product(*(spec["finite"][axis] for axis in AXES))


def finite_ids(_, spec, _tag):
    mathematical = set(product(OWNERS, LAYOUTS, ORDERS, (False, True)))
    require(set(grid(spec)) == mathematical and len(mathematical) == 32, "postgres-schema-view-grid")
    return mathematical


def analysis_finite_id(_, response, item, statement):
    key = tuple(item["coordinate"])
    same(sql(trial(item["release"], *key)), statement)
    check_value(item, response)
    return key


def direct_finite_id(_, response, item, request):
    key = tuple(item["coordinate"])
    same(direct(trial(item["release"], *key)), request)
    check_value(item, response)
    return key


def generated(tag, seed, count):
    rng = Random(seed)
    layouts = list(LAYOUTS)
    rng.shuffle(layouts)
    for index in range(count):
        owner = OWNERS[index % 2]
        yield trial(tag, owner, layouts[index % len(layouts)], rng.choice(ORDERS), bool(rng.getrandbits(1)),
                    width=index % 4 + 1, base=rng.randrange(-133, 221), quoted_name=bool(rng.getrandbits(1)),
                    explicit=bool(rng.getrandbits(1)), derived=owner == "named" and bool(rng.getrandbits(1)),
                    barrier=EMPTY_KIND[index % 3], invoker=EMPTY_KIND[(index // 3) % 3],
                    filtered=bool(rng.getrandbits(1)), heading=bool(rng.getrandbits(1)))


def edges(tag, spec):
    commands = spec["requiredFormerRefusals"]
    same(["CREATE SCHEMA ir_ns_sequence_subject CREATE VIEW feed AS SELECT 1", "CREATE SCHEMA making CREATE VIEW feed AS SELECT 1"],
         commands)
    for label, namespace, command in zip(("sequence-promotion", "table-promotion"),
                                         ("ir_ns_sequence_subject", "making"), commands):
        item = trial(tag, "effective", "values", "view-first", False)
        yield label, {**item, "constant": True, "namespace": namespace, "command": command}
    yield "named-owner-derived-quoted", trial(tag, "named", "local", "view-first", True, quoted_name=True, derived=True,
                                              filtered=True, heading=True, barrier=True, invoker=False, replace=True)
    yield "distinct-reference-columns", trial(tag, "effective", "guarded-local", "view-first", True, width=4, filtered=True,
                                              heading=True)


def refusals(tag):
    item = trial(tag, "effective", "local", "view-first", True)
    boundaries = (
        ("cross-namespace", f"CREATE SCHEMA {NS} CREATE VIEW public.feed AS SELECT 1", "42P15",
         refusal(f"CREATE specifies a schema (public) different from the one being created ({NS})", "SemanticException")),
        ("temporary-member", f"CREATE SCHEMA {NS} CREATE TEMP VIEW feed AS SELECT 1", "42P16",
         refusal("CREATE VIEW requires a persistent ordinary view in an absolute schema.")),
        ("duplicate-view", f"CREATE SCHEMA {NS} CREATE VIEW feed AS SELECT 1 CREATE VIEW feed AS SELECT 2", "42P07",
         refusal("A schema view requires an unused relation name.")),
        ("guarded-namespace", f"CREATE SCHEMA IF NOT EXISTS {NS} CREATE VIEW feed AS SELECT 1", None,
         refusal("CREATE SCHEMA IF NOT EXISTS cannot include schema elements", "SemanticException")),
        ("missing-field-provider", f"CREATE SCHEMA {NS} CREATE VIEW feed AS SELECT id FROM {NS}.missing", "42P01",
         refusal("Relation missing does not exist.\nColumn id does not exist.", "SemanticException")),
    )
    for label, command, native, rejection in boundaries:
        selected = {**item, "command": command, "nativeRefusal": native, "negative": label}
        yield analyzed(selected), selected if native else None, rejection
    request = direct(item)
    request["createSchema"]["tables"][0]["columns"][0][1] = "BIGINT"
    yield request, None, refusal("A schema view requires each local input field in the checked native kind.")
    request = direct(item)
    request["createSchema"]["views"][0]["release"] = "pg-16.6" if tag == "pg-17.2" else "pg-17.2"
    yield request, None, refusal(MEMBER_SCOPE)
    request = direct(item)
    request["createSchema"]["views"][0] = "unchecked view"
    yield request, None, refusal("Schema views are an ordered list of checked view declarations.")


def requests(engine, tag, spec):
    for index, key in enumerate(grid(spec)):
        item = trial(tag, *key, coordinate=list(key))
        yield f"finite/{index}", analyzed(item), item, None
        yield f"direct/{index}", direct(item), item, None
    for label, item in edges(tag, spec):
        yield f"edge/{label}", analyzed(item), item, None
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
    same([], response.get("schemaIndexes"))
    same(["constructed", item["release"] + "/utf8/standard-conforming-strings/native"],
         [response.get("status"), response.get("profile")])
    schema = direct(item)["createSchema"]
    same({**{key: schema[key] for key in ("owner", "ifNotExists")}, "name": item["namespace"]}, response["createSchema"])
    same(members(item), response["schemaTables"])
    same([sequence_shape(value, True) for value in generators(item)], response["schemaSequences"])
    views = (child(item, ordinal) for ordinal in range(item["width"]))
    same([{**view, "query": graph(view["query"])} for view in views], response["schemaViews"])


def measure(state, item, witness):
    if state is None:
        return None
    namespace_id, external_id, relations = (state[name] for name in ("namespaceOid", "externalOid", "relationOids"))
    named = set(state["sequences"]) | set(state["views"])
    if state["table"] is not None:
        named.add(state["table"][0])
    identities = [external_id, *relations.values()] + ([] if state["owner"] is None else [namespace_id])
    require(set(relations) == named and len(set(identities)) == len(identities)
            and all(type(oid) is int and oid > 0 for oid in identities)
            and (namespace_id is None) == (state["owner"] is None), "meaning-mismatch", witness)
    views = {}
    for name, observed in state["views"].items():
        edges = observed["physicalDependencies"]
        require(sorted((edge[:5] for edge in edges), key=json.dumps) == observed["dependencies"], "meaning-mismatch", witness)
        for scope, relation, label, slot, kind, oid in edges:
            resolved = external_id if (scope, relation) == (EXTERNAL, item["relation"]) else (
                relations.get(relation) if scope == item["namespace"] else None)
            require(resolved is not None and oid == resolved and ((kind == "i") == (
                (scope, relation, label, slot) == (item["namespace"], name, None, 0))), "meaning-mismatch", witness)
        views[name] = {key: value for key, value in observed.items() if key != "physicalDependencies"}
    return {**{key: state[key] for key in ("owner", "table", "sequences", "external")}, "views": views}


def check_observation(item, actual, statement):
    model = expected(item)
    witness = {"expected": model, "observed": actual, "sql": statement}
    require(actual.get("status") == STATUS, "postgres-schema-views-observer-scope", witness)
    parts = ("before", "during", "settled")
    seen = {key: measure(actual[key], item, witness) if key in parts else actual[key] for key in model}
    require(seen == model, "meaning-mismatch", witness)
    require(len({actual[part]["externalOid"] for part in parts if actual[part] is not None}) == 1, "meaning-mismatch", witness)
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
    return item["negative"]


def rejected_finite_ids(_, _tag):
    return {"cross-namespace", "temporary-member", "duplicate-view", "missing-field-provider"}


def sample_forms(forms, response, _engine, item):
    for name in ("owner", "order", "layout", "qualified", "active", "quoted", "heading"):
        forms[f"{name}/{item[name]}"] += 1
    views = response["schemaViews"]
    forms[f"width/{len(views)}"] += 1
    for view in views:
        query = view["query"]
        forms[f"barrier/{view['securityBarrier']}"] += 1
        forms[f"invoker/{view['securityInvoker']}"] += 1
        if "postgresValues" in query:
            forms["body/values"] += 1
            continue
        source = query["source"]
        forms[f"body/{'external' if source['schema'] == EXTERNAL else 'local'}"] += 1
        forms[f"filtered/{query['where'] is not None}"] += 1
        for kind in source["columnKinds"]:
            forms[f"kind/{kind}"] += 1


def required_forms(*_):
    return {*(f"owner/{value}" for value in OWNERS), *(f"order/{value}" for value in ORDERS),
            *(f"layout/{value}" for value in LAYOUTS), *(f"width/{width}" for width in range(1, 5)),
            *(f"{name}/{value}" for name in ("qualified", "active", "quoted", "heading", "filtered") for value in (True, False)),
            *(f"{name}/{value}" for name in ("barrier", "invoker") for value in EMPTY_KIND),
            "body/values", "body/local", "body/external", "kind/int4", "kind/int8", "kind/bool", "kind/text"}


def controls():
    item = trial("pg-17.2", "effective", "local", "view-first", True)
    good = deepcopy({**expected(item), "error": None})
    for key in ("before", "during", "settled"):
        good[key].update(namespaceOid=None, externalOid=110, relationOids={})
    during = good["during"]
    during.update(namespaceOid=111, relationOids={"accounts": 112, "tickets": 113, "report0": 114})
    view = during["views"]["report0"]
    view["physicalDependencies"] = [[*edge, during["relationOids"][edge[1]]] for edge in view["dependencies"]]
    check_observation(item, good, sql(item))
    failures = {}
    for key in ("rows", "physicalDependencies"):
        changed = deepcopy(good)
        changed_view = changed["during"]["views"]["report0"]
        if key == "rows":
            changed_view[key].pop(1)
        else:
            next(edge for edge in changed_view[key] if edge[1] == "accounts")[-1] = changed["during"]["externalOid"]
        try:
            check_observation(item, changed, sql(item))
        except Failure as failure:
            same("meaning-mismatch", failure.signal)
            failures[key] = failure.detail
            continue
        raise Failure("schema-view-comparison-ignored-observation", key)
    return failures


def check_fault_witness(item, witness):
    same(expected(item), witness["expected"])
    same(STATUS, witness["observed"]["status"])


def faults(_, tag):
    schema_path = "sql-semantics-postgres/src/Ir/Schema/Namespace/CreateSchema.php"
    view_path = "sql-semantics-postgres/src/Ir/Schema/View/CreateView.php"
    local = trial(tag, "effective", "local", "view-first", True, filtered=True, heading=True)
    return [
        RenderFault("schema-views-remove-phase", schema_path, "foreach ($this->views as $view)",
                    "foreach ([] as $view)", local, "meaning-mismatch", direct(local)),
        RenderFault("schema-views-redirect-to-decoy", schema_path, "$view->toString()",
                    """str_replace(' FROM "ir_view_namespace".', ' FROM "ir_view_source".', $view->toString())""",
                    local, "meaning-mismatch", direct(local)),
        RenderFault("schema-views-drop-heading", view_path, " ' (' . implode(', ', $columns) . ')'", " ''",
                    local, "meaning-mismatch", direct(local)),
    ]


if __name__ == "__main__":
    require(FIXTURE_CONTAINER in os.environ, "postgres-schema-views-disposable-service-required")
    main(sys.modules[__name__])
