"""Compare wide native query-backed InnoDB tables and their checked observations."""

from copy import deepcopy
from itertools import product
import json
import sys

from mysql_create_table_as_declaration_cases import capacity
from mysql_create_table_as_width_cases import (
    COLLATION, FIXTURE, LOGICAL_LIMIT, RECORD_LIMIT, SCOPES, STATUS, TAG_56, VALUES, WIDTHS,
    analyze, command, coordinate, coordinates, definition, edge_cases, generated, integer,
    observed_create, prediction, source_nullability, width_error,
)
from run import Failure, TOOL, digest, require
from run_server import same
from run_server_join import main, negative_count
from run_server_mysql_create_table_as import former_width_probe
from run_server_mysql_create_varchar import DIAGNOSTIC, specifications as native_profiles
from run_server_mysql_session_create_table import table
from sqlite_domain import RenderFault

LABEL = "mysql-create-table-as-width"
NAME = "mysql-create-table-as-width-evidence-v1.json"
CONTRACT = "597ce5dfcc3b68fe6558b2a2d2236692c4c29e8f82b867a800bfddf3783c55c7"
BASE = "f39bb0aac888ffcd34b3c415880d121631a7aff146c7a4a6f92f19f91e1beaaf"
OBSERVER = "mysql_ctas_width_observer.php"
PROFILE = "/utf8mb4/lower-case-table-names=0/sql-mode=empty/native/innodb"
COMPARATOR_FAULTS = {"Erase a duplicate materialized row while retaining the other two rows.": "drop-duplicate"}


def specifications():
    path = TOOL / NAME
    require(digest(path) == CONTRACT and digest(TOOL / "mysql-create-table-as-width-v1.json") == BASE,
            "mysql-create-table-as-width-contract")
    spec = json.loads(path.read_text())
    profiles = native_profiles()[1]
    same(spec["releases"], profiles["mysql"]["releases"])
    same([], spec["exploration"]["allowedExclusions"])
    spec["negativeByRelease"] = {tag: list(range(total)) for tag, total in spec["finite"]["typedRefusalsPerTwoRoutes"].items()}
    return spec, profiles


def grid(spec):
    for family, widths in spec["finite"]["widthsByFamily"].items():
        for width, scope, populated in product(widths, SCOPES, (True, False)):
            yield family, width, scope, populated


def finite_ids(_, spec, tag):
    points = coordinates()
    require(len(points) == spec["finite"]["identities"] and points == set(grid(spec)), "mysql-ctas-width-grid")
    return {point for point in points if width_error(coordinate(tag, point)) is None}


def measured_coordinate(response):
    model = response["mysqlCreateTableAs"]
    query = model["query"]
    columns = query["columns"]
    source, explicit = query["source"], model.get("declaredColumns", [])
    if explicit:
        family = "explicit-same-name" if explicit[0][0] == columns[0][1] else "explicit-extra-name"
    elif source:
        slot = source["columns"][0]
        family = {"required": "inherited-signed", "short": "inherited-varchar-nullable", "pad": "inherited-varchar-required"}[slot]
    else:
        operand = columns[0][0]
        role = operand[0]
        if role == "integer":
            family = "small-int" if operand[1] == "17" else "long-int"
        elif role == "signed-cast":
            family = "signed-null" if operand[1] == ["null"] else "signed-cast"
        else:
            same(["null"], operand)
            family = "untyped-null"
    scope = "temporary" if model["target"][2] == "temporary-table" else "persistent"
    return family, len(columns), scope, query["where"] is None


def point_id(point):
    return "/".join(map(str, point))


def checked_point(response, item):
    point = measured_coordinate(response)
    same(list(item["point"]), list(point))
    return point


def analysis_finite_id(_, response, item, sql):
    same(command(coordinate(item["model"]["release"], item["point"])), sql)
    return checked_point(response, item)


def direct_finite_id(_, response, item, request):
    same(coordinate(item["model"]["release"], item["point"]), request)
    return checked_point(response, item)


def rejected_finite_ids(spec, tag):
    return {point_id(point) for point in coordinates() - finite_ids("mysql", spec, tag)} | {
        name for name, item in edge_cases(tag) if width_error(item) is not None
    }


def pair(route, label, model, failure, point=None):
    item = {"model": model, "failure": failure, "id": label, "point": point}
    direct = {"finite": "direct", "seed": "direct-seed"}.get(route, route + "-direct")
    if failure is None:
        yield f"{route}/{label}", analyze(model), item, None
        yield f"{direct}/{label}", model, item, None
    else:
        error = {"exception": DIAGNOSTIC, "message": failure}
        yield f"negative/{route}/{label}/analysis", analyze(model), item, error
        yield f"negative/{route}/{label}/direct", model, None, error


def requests(engine, tag, spec):
    negatives = 0
    for point in grid(spec):
        model = coordinate(tag, point)
        failure = width_error(model)
        yield from pair("finite", point_id(point), model, failure, point)
        negatives += 2 if failure is not None else 0
    edge = list(edge_cases(tag))
    same(spec["finite"]["additionalEdges"], len(edge))
    for name, model in edge:
        failure = width_error(model)
        yield from pair("edge", name, model, failure)
        negatives += 2 if failure is not None else 0
    prior = spec["predecessorReceipts"]
    yield from pair("predecessor", "projection", former_width_probe(tag, prior["projection"]["previousProjectedWidth"]), None)
    yield from pair("predecessor", "effective-heading", capacity(tag, prior["combined"]["previousExplicitWidth"]), None)
    for seed in spec["exploration"]["seeds"]:
        for ordinal, model in enumerate(generated(tag, seed, spec["exploration"]["casesPerSeed"])):
            require(width_error(model) is None, "mysql-ctas-width-seed-recipe", [seed, ordinal])
            yield from pair("seed", f"{seed}/{ordinal}", model, None)
    same(negative_count(spec, engine, tag), negatives)


def check_value(item, response):
    model = item.get("model", item)
    require(response["status"] == "constructed", "mysql-ctas-width-construction", response)
    same(model["release"] + PROFILE, response["profile"])
    same(observed_create(model), response["mysqlCreateTableAs"])
    same(source_nullability(model["mysqlCreateTableAs"]), response["mysqlInputNullability"])


def observer_request(item, sql):
    model = item.get("model", item)["mysqlCreateTableAs"]
    return {"sql": sql, "target": model["target"][1], "preparePrior": model["ifNotExists"], "temporary": model["target"][2] == "temporary-table"}


def observation(raw):
    return raw


def table_with_sorted_bag(value):
    if value is None:
        return None
    return {**value, "rows": sorted(value["rows"], key=lambda row: json.dumps(row, ensure_ascii=False))}


def check_observation(item, actual, sql):
    expected = prediction(item.get("model", item), item.get("failure"))
    witness = {"expected": expected, "observed": actual, "sql": sql}
    require(actual["status"] == STATUS, "mysql-ctas-width-observer-scope", witness)
    fields = {key: actual[key] for key in expected if key != "visible"}
    model = {key: value for key, value in expected.items() if key != "visible"}
    require(fields == model and table_with_sorted_bag(actual["visible"]) == table_with_sorted_bag(expected["visible"]),
            "meaning-mismatch", witness)
    if item.get("failure") in (LOGICAL_LIMIT, RECORD_LIMIT):
        limit = "65535" if item["failure"] == LOGICAL_LIMIT else "8126"
        require(any(limit in warning[2] for warning in actual["diagnostics"]), "meaning-mismatch", witness)


def observe_rejection(_, item, actual, sql):
    same(command(item["model"]), sql)
    check_observation(item, actual, sql)
    return item["id"]


def sample_forms(forms, response, _, item):
    model = response["mysqlCreateTableAs"]
    query = model["query"]
    for role in (f"source/{bool(query['source'])}", f"explicit/{bool(model.get('declaredColumns'))}",
                 f"scope/{model['target'][2]}", f"populated/{query['where'] is None}",
                 f"wide/{len(query['columns']) > 128}"):
        forms[role] += 1
    for expression, _ in query["columns"]:
        forms[f"operand/{expression[0]}"] += 1
    for flag in response["mysqlInputNullability"]:
        forms[f"nullable/{flag}"] += 1


def required_forms(*_):
    return {"scope/table", "scope/temporary-table", *(f"operand/{r}" for r in ("signed-cast", "null", "integer", "boolean", "text-slot", "slot")),
            *(f"{axis}/{value}" for axis in ("source", "explicit", "populated", "wide", "nullable") for value in (True, False))}


def outsiders():
    return ("DROP TABLE ir_ctas_wide", "CREATE TABLE ir_other ENGINE=InnoDB AS SELECT 1 AS x",
            command(definition(TAG_56, "persistent", False, [integer(1)])) + "; SELECT 1")


def prepare(_, db):
    item = definition(TAG_56, "persistent", True, [integer(1)])
    env = db.environment
    return {"storage": env["storage"], "fixture": env["fixture"],
            "outside": [db.request(observer_request(item, sql)) for sql in outsiders()]}


def check_preparation(_, env, preparation):
    tag = "mysql-" + env["database"][0]
    same([16384, int(tag != TAG_56), "InnoDB", "InnoDB", "Compact" if tag == TAG_56 else "Dynamic"], preparation["storage"])
    same(table_with_sorted_bag(table(tag, "table", FIXTURE, [], VALUES)), table_with_sorted_bag(preparation["fixture"]))
    same([{"status": "outside-observer-scope"} for _ in outsiders()], preparation["outside"])


def witness(tag, inherited=False):
    return (definition(tag, "persistent", True, [], field_numbers=[0] * 128 + [1]) if inherited else
            definition(tag, "temporary", True, [integer(17)] * 129))


def controls():
    item = witness("mysql-9.1.0", inherited=True)
    good = prediction(item, None)
    check_observation(item, good, command(item))
    wrong = deepcopy(good)
    wrong["visible"]["rows"].pop()
    try:
        check_observation(item, wrong, command(item))
    except Failure as error:
        same("meaning-mismatch", error.signal)
        return {"drop-duplicate": error.detail}
    raise Failure("mysql-ctas-width-ignored-duplicate-witness")


def check_fault_witness(item, value):
    same(prediction(item, None), value["expected"])
    same(STATUS, value["observed"]["status"])
    same(value["expected"]["effect"], value["observed"]["effect"])
    require(value["observed"]["visible"] is not None, "mysql-ctas-width-fault-must-execute", value)


def faults(_, tag):
    output = "sql-semantics-mysql/src/Ir/SelectSql.php"
    missing = witness(tag)
    replaced = witness(tag, inherited=True)
    suffix = "$items[] = $this->expression($column->expression, $source) . $alias;"
    name = "$input->columns[$expression->slot]->quoted()"
    return [
        RenderFault("drop-wide-ctas-projection", output, suffix, "if ($slot !== 128) { " + suffix + " }",
                    missing, "meaning-mismatch", missing),
        RenderFault("substitute-ctas-inherited-last-slot", output, name, "$input->columns[0]->quoted()",
                    replaced, "meaning-mismatch", replaced),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])
