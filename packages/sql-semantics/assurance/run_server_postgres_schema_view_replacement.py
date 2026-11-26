"""Verify native PostgreSQL view replacement and retained physical heading signatures."""

from copy import deepcopy
from itertools import product
import json
import os
from random import Random
import sys

from native_postgres_fixture import ENV as FIXTURE_CONTAINER
from postgres_create_btree_cases import refusal
from postgres_schema_view_replacement_cases import (
    LAYOUTS, NS, OWNERS, POSITIONS, STATUS, analyzed, direct, expected, mutation, root, sql, trial, view_sql,
)
from run import Failure, TOOL, digest, require
from run_server import same
from run_server_join import main, negative_count
from run_server_postgres_create_schema import check_preparation, observation, prepare
from run_server_postgres_create_schema import specifications as namespace_profiles
from run_server_postgres_create_view import graph
from sqlite_domain import RenderFault

LABEL = "postgres-schema-view-replacement"
CONTRACT_NAME = "postgres-schema-view-replacement-v1.json"
CONTRACT = "e56e28f03abc55470b56e00f23eeac53c3aeea757720b7676df17d6f0a4f975a"
OBSERVER = "postgres_schema_view_replacement_observer.py"
AXES = ("owners", "replacements", "dependentPositions", "transactional")
NAMES = "A schema view replacement cannot rename established fields."
COLLATIONS = {None: [0, None, None], "default": [100, "default", "pg_catalog"], "C": [950, "C", "pg_catalog"]}


def specifications():
    path = TOOL / CONTRACT_NAME
    require(digest(path) == CONTRACT, "postgres-schema-view-replacement-contract")
    spec = json.loads(path.read_text())
    same([], spec["exploration"]["allowedExclusions"])
    return spec, namespace_profiles()[1]


def grid(spec):
    return product(*(spec["finite"][axis] for axis in AXES))


def finite_ids(_, spec, _tag):
    independent = set(product(OWNERS, LAYOUTS, POSITIONS, (False, True)))
    require(set(grid(spec)) == independent and len(independent) == 24, "postgres-schema-view-replacement-grid")
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
    modes = list(LAYOUTS)
    rng.shuffle(modes)
    for i in range(count):
        yield trial(tag, OWNERS[i % len(OWNERS)], modes[i % len(modes)], rng.choice(POSITIONS), bool(rng.getrandbits(1)),
                    replacements=1 + ((i + i // 3) % 3), base=rng.randrange(2, 71), quoted_name=bool(rng.getrandbits(1)),
                    reverse=bool(rng.getrandbits(1)), select=bool(rng.getrandbits(1)), option=rng.choice([None, False, True]))


def edges(tag):
    yield "quoted-appended-field", trial(tag, "named", "append-boolean", "earlier-and-later", False, quoted_name=True)
    yield "bare-text-default-collation", trial(tag, "effective", "same-heading", "earlier-and-later", True, select=True)
    yield "replaced-twice-with-appends", trial(tag, "effective", "append-boolean", "earlier-and-later", True, replacements=2)


def refusals(tag):
    item = trial(tag, "effective", "append-boolean", "earlier-and-later", True)
    base = f"CREATE SCHEMA {NS} CREATE VIEW feed AS SELECT 1 AS n "
    occupied = refusal("A schema view requires an unused relation name.")
    boundaries = (
        ("duplicate", base + "CREATE VIEW feed AS SELECT 2 AS n", "42P07", occupied),
        ("rename", base + "CREATE OR REPLACE VIEW feed AS SELECT 2 AS changed", "42P16", refusal(NAMES)),
        ("remove", f"CREATE SCHEMA {NS} CREATE VIEW feed AS SELECT 1 AS n, 2 AS m CREATE OR REPLACE VIEW feed AS SELECT 2 AS n",
         "42P16", refusal("A schema view replacement cannot remove established fields.")),
        ("retype", base + "CREATE OR REPLACE VIEW feed AS SELECT 4000000002 AS n", "42P16",
         refusal("A schema view replacement cannot change established native types.")),
        ("collation", f"""CREATE SCHEMA {NS} CREATE VIEW feed AS SELECT 'old' COLLATE pg_catalog."C" AS caption """
         "CREATE OR REPLACE VIEW feed AS SELECT 'new' AS caption", "42P16",
         refusal("A schema view replacement requires the same established collations.")),
        ("non-view", f"CREATE SCHEMA {NS} CREATE TABLE feed(n integer) CREATE OR REPLACE VIEW feed AS SELECT 1 AS n", "42809", occupied),
    )
    for name, sql, code, declined in boundaries:
        selected = {**item, "command": sql, "nativeRefusal": code, "negative": name}
        yield analyzed(selected), selected, declined
    patch = direct(item)
    patch["createSchema"]["views"][2]["columns"][0] = "different"
    yield patch, None, refusal(NAMES)
    patch = direct(item)
    patch["createSchema"]["views"][-1]["query"]["source"]["columns"][0] = "unavailable"
    yield patch, None, refusal("A schema view requires each local input field in the checked native kind.")


def requests(engine, tag, spec):
    for number, key in enumerate(grid(spec)):
        recipe = trial(tag, *key, coordinate=list(key))
        yield f"finite/{number}", analyzed(recipe), recipe, None
        yield f"direct/{number}", direct(recipe), recipe, None
    for label, recipe in edges(tag):
        yield f"edge/{label}", analyzed(recipe), recipe, None
    exploration = spec["exploration"]
    for seed in exploration["seeds"]:
        for index, recipe in enumerate(generated(tag, seed, exploration["casesPerSeed"])):
            yield f"seed/{seed}/{index}", analyzed(recipe), recipe, None
            yield f"direct-seed/{seed}/{index}", direct(recipe), recipe, None
    invalid = list(refusals(tag))
    same(negative_count(spec, engine, tag), len(invalid))
    for number, (request, recipe, rejection) in enumerate(invalid):
        yield f"negative/{number}", request, recipe, rejection


def check_value(item, response):
    value = direct(item)["createSchema"]
    same(["constructed", item["release"] + "/utf8/standard-conforming-strings/native"],
         [response.get("status"), response.get("profile")])
    same({key: value[key] for key in ("name", "owner", "ifNotExists")}, response["createSchema"])
    for key in ("schemaTables", "schemaSequences", "schemaIndexes", "schemaGrants"):
        same([], response[key])
    same([{**view, "query": graph(view["query"])} for view in value["views"]], response["schemaViews"])


def measure(state, item, witness):
    if state is None:
        return None
    scope, identities = state["namespaceOid"], state["relationOids"]
    nodes = [*identities.values(), *([] if scope is None else [scope])]
    require(set(identities) == set(state["views"]) and len(set(nodes)) == len(nodes)
            and all(type(oid) is int and oid > 0 for oid in nodes)
            and (scope is None) == (state["owner"] is None), "meaning-mismatch", witness)
    structures = {}
    for name, definition in state["views"].items():
        physical = definition["physicalDependencies"]
        require(sorted((entry[:5] for entry in physical), key=json.dumps) == definition["dependencies"], "meaning-mismatch", witness)
        for schema, relation, column, slot, kind, oid in physical:
            require(schema == item["namespace"] and identities.get(relation) == oid
                    and ((kind == "i") == ((name, None, 0) == (relation, column, slot))), "meaning-mismatch", witness)
        attributes = definition["attributes"]
        require(all(value[-1] in COLLATIONS for value in attributes), "meaning-mismatch", witness)
        require([[value[0], *COLLATIONS[value[-1]]] for value in attributes] == definition["physicalCollations"],
                "meaning-mismatch", witness)
        structures[name] = {key: value for key, value in definition.items() if key not in ("physicalDependencies", "physicalCollations")}
    return {"owner": state["owner"], "views": structures}


def check_observation(item, actual, command):
    model = expected(item)
    witness = {"expected": model, "observed": actual, "sql": command}
    require(actual.get("status") == STATUS, "postgres-schema-view-replacement-observer-scope", witness)
    measured = {key: measure(actual[key], item, witness) if key in ("before", "during", "settled") else actual[key] for key in model}
    require(measured == model, "meaning-mismatch", witness)
    if not item["active"] and actual["during"] is not None:
        require(actual["during"] == actual["settled"], "meaning-mismatch", witness)
    error = actual["error"]
    require(error is None if model["sqlState"] == "00000" else error is not None and error[:2] == ["ERROR", model["sqlState"]],
            "meaning-mismatch", witness)


def observer_request(item, command):
    return {"trial": item, "sql": command}


def observe_rejection(_, item, actual, command):
    same(item["command"], command)
    check_observation(item, actual, command)
    return item["negative"]


def rejected_finite_ids(_, _tag):
    return {"duplicate", "rename", "remove", "retype", "collation", "non-view"}


def sample_forms(forms, response, _engine, item):
    for key in ("ownership", "layout", "position", "active", "quoted", "reverse"):
        forms[f"{key}/{item[key]}"] += 1
    phase = response["schemaViews"]
    choices = [view for view in phase if view["replace"]]
    forms[f"replacements/{len(choices)}"] += 1
    forms[f"width/{len(choices[-1]['columns'])}"] += 1
    for view in choices:
        forms[f"select/{'postgresValues' not in view['query']}"] += 1
        forms[f"barrier/{view['securityBarrier']}"] += 1
        forms[f"invoker/{view['securityInvoker']}"] += 1


def required_forms(*_):
    return {*(f"ownership/{role}" for role in OWNERS), *(f"layout/{mode}" for mode in LAYOUTS),
            *(f"position/{mode}" for mode in POSITIONS), *(f"replacements/{value}" for value in range(1, 4)),
            *(f"width/{width}" for width in range(2, 6)),
            *(f"{key}/{value}" for key in ("active", "quoted", "reverse", "invoker", "select") for value in (True, False)),
            *(f"barrier/{value}" for value in (True, False, None))}


def controls():
    item = trial("pg-17.2", "effective", "explicit-collation", "earlier-and-later", True)
    good = deepcopy({**expected(item), "error": None})
    for name in ("before", "during", "settled"):
        state = good[name]
        occupied = name == "during"
        state.update(namespaceOid=101 if occupied else None, relationOids={k: 111 + i for i, k in enumerate(state["views"])})
        for view in state["views"].values():
            view["physicalDependencies"] = [[*edge, state["relationOids"][edge[1]]] for edge in view["dependencies"]]
            view["physicalCollations"] = [[attr[0], *COLLATIONS[attr[-1]]] for attr in view["attributes"]]
    check_observation(item, good, sql(item))
    errors = {}
    for label in ("modifier", "dependent-row"):
        broken = deepcopy(good)
        changed = broken["during"]["views"]["before_change"]
        if label == "modifier":
            changed["attributes"][1][3] = 8
        else:
            changed["rows"][0][1] = "source-0"
        try:
            check_observation(item, broken, sql(item))
        except Failure as error:
            same("meaning-mismatch", error.signal)
            errors[label] = error.detail
            continue
        raise Failure("view-replacement-comparator-ignored-difference", label)
    return errors


def check_fault_witness(item, witness):
    same(expected(item), witness["expected"])
    same(STATUS, witness["observed"]["status"])


def php_string(value):
    return "'" + value.replace("\\", "\\\\").replace("'", "\\'") + "'"


def replace_view(item, mode):
    before = view_sql(root(item, 1), True)
    new = next(view for view in mutation(item, mode) if view["replace"])
    return "str_replace(" + php_string(before) + ", " + php_string(view_sql(new, True)) + ", $view->toString())"


def faults(_, tag):
    schema = "sql-semantics-postgres/src/Ir/Schema/Namespace/CreateSchema.php"
    definition = "sql-semantics-postgres/src/Ir/Schema/View/CreateView.php"
    item = trial(tag, "effective", "same-heading", "earlier", True)
    extra = trial(tag, "effective", "append-boolean", "earlier", True)
    return [
        RenderFault("view-replacement-omit-operation", schema, "$elements .= ' ' . $view->toString();",
                    "$elements .= $view->replace ? '' : ' ' . $view->toString();", item, "meaning-mismatch", direct(item)),
        RenderFault("view-replacement-erase-choice", definition, "($this->replace ? 'OR REPLACE ' : '')",
                    "''", item, "meaning-mismatch", direct(item)),
        RenderFault("view-replacement-reuse-original-result", schema, "$view->toString()", replace_view(item, "old-value"),
                    item, "meaning-mismatch", direct(item)),
        RenderFault("view-replacement-drop-appended-field", schema, "$view->toString()", replace_view(extra, "append"),
                    extra, "meaning-mismatch", direct(extra)),
    ]


if __name__ == "__main__":
    require(FIXTURE_CONTAINER in os.environ, "postgres-schema-view-replacement-disposable-service-required")
    main(sys.modules[__name__])
