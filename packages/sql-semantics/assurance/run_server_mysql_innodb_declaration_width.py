"""Verify ordinary checked InnoDB declarations in the stock native storage profiles."""

from copy import deepcopy
from itertools import product
import json
import sys

from mysql_innodb_declaration_width_cases import (
    CLUSTERS, EMPTY_LIST, LOGICAL_LIMIT, PHYSICAL_LIMIT, SCOPES, SIGNED, STATUS, TAG_56, WIDTH_LIMIT, WIDTHS,
    analyze, column, command, coordinate, coordinate_failure, coordinates, definition, edges, exploration, key_explicit,
    prediction,
)
from run import Failure, TOOL, digest, require
from run_server import same
from run_server_join import main, negative_count
from run_server_mysql_create_varchar import DIAGNOSTIC, specifications as native_profiles
from sqlite_domain import RenderFault

LABEL = "mysql-innodb-declaration-width"
NAME = "mysql-innodb-declaration-width-evidence-v1.json"
CONTRACT = "d2e586ccdcc6bf395aaa6350d56504258706099749ace11d173782e8a6db4b60"
BASE = "752b3a7ce4801e28c2b6fdd6b6189e01528184b6ffea6d2492a6cd51d861b8a3"
OBSERVER = "mysql_innodb_declaration_width_observer.php"
PROFILE = "/utf8mb4/lower-case-table-names=0/sql-mode=empty/native/innodb"
COMPARATOR_FAULTS = {"Comparison silently ignores changed slot metadata and row shape.": "changed-high-column"}


def specifications():
    path = TOOL / NAME
    require(digest(path) == CONTRACT and digest(TOOL / "mysql-innodb-declaration-width-v1.json") == BASE,
            "mysql-innodb-declaration-width-contract")
    spec = json.loads(path.read_text())
    profiles = native_profiles()[1]
    same(spec["releases"], profiles["mysql"]["releases"])
    same([], spec["exploration"]["allowedExclusions"])
    return spec, profiles


def grid(spec):
    finite = spec["finite"]
    return product(finite["widths"], finite["nullable"], finite["cluster"], finite["scope"])


def finite_ids(_, specification, tag):
    expected_points = coordinates()
    require(len(expected_points) == 88 and expected_points == set(grid(specification)), "mysql-innodb-width-grid")
    return {point for point in expected_points if coordinate_failure(tag, point) is None}


def finite_key(point):
    return "/".join(map(str, point))


def rejected_finite_ids(specification, tag):
    return {finite_key(point) for point in coordinates() - finite_ids("mysql", specification, tag)} | {
        label for label, _, failure in edges(tag) if failure is not None
    }


def measured_coordinate(layout, kind):
    fields = layout["columns"]
    width = len(fields)
    terms = layout.get("primaryKey", [])
    require(all(field[1] == "BIGINT" for field in fields), "mysql-innodb-width-finite-type")
    same([[width - 1, False]] if terms else [], terms)
    eligible = fields[:-1] if terms else fields
    if eligible:
        nullable = any(field[2] for field in eligible)
        require(all(field[2] == nullable for field in eligible), "mysql-innodb-width-finite-nullability")
    else:
        # A single clustered key is always required. Its signed default identifies the coordinate.
        nullable = fields[0][3] == ["integer", "-7"]
    cluster = "explicit" if terms else "implicit"
    scope = {"table": "persistent", "temporary-table": "temporary"}[kind]
    return width, nullable, cluster, scope


def analysis_finite_id(_, response, item, request_sql):
    point = measured_coordinate(response["create"], response["createKind"])
    same(command(coordinate(item["model"]["release"], item["point"])), request_sql)
    return point


def direct_finite_id(_, response, item, request):
    point = measured_coordinate(response["create"], response["createKind"])
    same(coordinate(item["model"]["release"], item["point"]), request)
    return point


def boundary(message):
    return {"exception": DIAGNOSTIC, "message": message}


def request_pair(prefix, id_, item, failure=None, point=None):
    context = {"model": item, "failure": failure, "id": id_, "point": point}
    direct_prefix = {"finite": "direct", "seed": "direct-seed"}.get(prefix, prefix + "-direct")
    if failure is None:
        yield f"{prefix}/{id_}", analyze(item), context, None
        yield f"{direct_prefix}/{id_}", item, context, None
    else:
        yield f"negative/{prefix}/{id_}/analysis", analyze(item), context, boundary(failure)
        yield f"negative/{prefix}/{id_}/direct", item, None, boundary(failure)


def local_negatives(tag):
    empty = definition(tag, "persistent", [])
    yield empty, EMPTY_LIST
    fields = [column(0, "INTEGER", False, 31)]
    yield definition(tag, "persistent", fields, [[1, False]]), "A primary key must reference an existing required signed column."
    yield definition(tag, "persistent", fields, [[0, False], [0, True]]), "A primary key cannot repeat a column position."
    yield definition(tag, "persistent", fields, [[1017, False]]), "A primary key needs a column slot from 0 through 1016."


def requests(engine, tag, spec):
    attempts = 0
    for point in grid(spec):
        failure = coordinate_failure(tag, point)
        yield from request_pair("finite", finite_key(point), coordinate(tag, point), failure, point)
        attempts += 2 if failure is not None else 0
    for name, item, failure in edges(tag):
        yield from request_pair("edge", name, item, failure)
        attempts += 2 if failure is not None else 0
    settings = spec["exploration"]
    for seed in settings["seeds"]:
        for ordinal, item in enumerate(exploration(tag, seed, settings["casesPerSeed"])):
            yield from request_pair("seed", f"{seed}/{ordinal}", item)
    for index, (item, message) in enumerate(local_negatives(tag)):
        yield f"negative/local/{index}", item, None, boundary(message)
        attempts += 1
    same(negative_count(spec, engine, tag), attempts)


def check_value(item, response):
    if "model" in item:
        item = item["model"]
    require(response["status"] == "constructed", "mysql-innodb-width-construction", response)
    same(item["release"] + PROFILE, response["profile"])
    same(item["createKind"], response["createKind"])
    same(item["create"], response["create"])


def observer_request(item, sql):
    if "model" in item:
        item = item["model"]
    return {"sql": sql, "preparePrior": item["create"]["ifNotExists"], "temporary": item["createKind"] == "temporary-table"}


def observation(raw):
    return raw


def check_observation(item, actual, sql):
    failure = item.get("failure")
    if "model" in item:
        item = item["model"]
    expected = prediction(item, failure)
    witness = {"sql": sql, "expected": expected, "observed": actual}
    require(actual["status"] == STATUS, "mysql-innodb-width-observer-scope", witness)
    require({key: actual[key] for key in expected} == expected, "meaning-mismatch", witness)
    if failure in (PHYSICAL_LIMIT, LOGICAL_LIMIT):
        match = "8126" if failure == PHYSICAL_LIMIT else "65535"
        require(any(match in warning[2] for warning in actual["diagnostics"]), "meaning-mismatch", witness)


def observe_rejection(_, item, actual, sql):
    same(command(item["model"]), sql)
    check_observation(item, actual, sql)
    return item["id"]


def sample_forms(forms, response, _, item):
    shape = response["create"]
    for form in (f"scope/{response['createKind']}", f"key/{key_explicit(item['model'])}", f"wide/{len(shape['columns']) > 128}"):
        forms[form] += 1
    for name, storage, nullable, _ in shape["columns"]:
        forms[f"name/{'`' in name}"] += 1
        forms[f"nullable/{nullable}"] += 1
        forms[f"storage/{storage if isinstance(storage, str) else 'varchar'}"] += 1


def required_forms(*_):
    return {"scope/table", "scope/temporary-table", "storage/varchar", *(f"storage/{kind}" for kind in SIGNED),
            *(f"{axis}/{value}" for axis in ("key", "wide", "name", "nullable") for value in (False, True))}


def outsiders():
    return ("CREATE TABLE ir_width_data (x INT)", "DROP TABLE ir_width_data",
            command(definition(TAG_56, "persistent", [])) + "; SELECT 1")


def prepare(_, db):
    item = definition(TAG_56, "persistent", [])
    return {"storage": db.environment["storage"], "empty": db.request(observer_request(item, command(item))),
            "outside": [db.request(observer_request(item, sql)) for sql in outsiders()]}


def check_preparation(_, environment, preparation):
    legacy = environment["database"][0] == "5.6.51"
    same({"pageBytes": 16384, "strict": 0 if legacy else 1, "engine": "InnoDB",
          "temporaryEngine": "InnoDB", "rowFormat": "Compact" if legacy else "Dynamic"}, preparation["storage"])
    same({"status": STATUS, "effect": ["db-error", "42000", 1064], "warnings": [["Error", 1064]],
          "visible": None, "global": False, "write": None},
         {key: value for key, value in preparation["empty"].items() if key not in {"active", "committedMarker", "diagnostics"}})
    same([{"status": "outside-observer-scope"} for _ in outsiders()], preparation["outside"])


def high_column(tag):
    return coordinate(tag, (129, False, "explicit", "temporary"))


def controls():
    item = high_column("mysql-9.1.0")
    good = {**prediction(item), "diagnostics": []}
    check_observation(item, good, command(item))
    altered = deepcopy(good)
    altered["visible"]["columns"][-1][1] = "int"
    altered["visible"]["rows"][0].pop()
    try:
        check_observation(item, altered, command(item))
    except Failure as error:
        same("meaning-mismatch", error.signal)
        return {"changed-high-column": error.detail}
    raise Failure("mysql-innodb-width-comparator-accepted-wrong-slot")


def check_fault_witness(item, witness):
    same(prediction(item), witness["expected"])
    same(STATUS, witness["observed"]["status"])
    same(["executed", 0], witness["observed"]["effect"])
    same("int(11)" if item["release"] in {TAG_56, "mysql-5.7.44"} else "int",
         witness["observed"]["visible"]["columns"][-1][1])


def faults(_, tag):
    item = high_column(tag)
    return [RenderFault(
        "change-high-ordered-type", "sql-semantics-mysql/src/Ir/Schema/CreateTable.php",
        "$columns[] = $column->toString();",
        "$columns[] = count($columns) === 128 ? str_replace(' BIGINT', ' INTEGER', $column->toString()) : $column->toString();",
        item, "meaning-mismatch", item,
    )]


if __name__ == "__main__":
    main(sys.modules[__name__])
