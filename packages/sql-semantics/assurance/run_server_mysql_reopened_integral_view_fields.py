"""Compare signed persistent view readings and exact physical projection widths."""

from collections import Counter
import json
import sys

from evidence_transcript import canonical
import mysql_reopened_integral_view_field_cases as law
from run import Failure, TOOL, digest, require
from run_server import same
from run_server_join import OBSERVER, main
from run_server_mysql_external_view_read_names import matches_graph, semantic, specifications as view_specification
from run_server_mysql_signed_membership import refusal
from sqlite_domain import RenderFault

LABEL = "mysql-reopened-integral-view-fields"
CONTRACT_NAME = "mysql-reopened-integral-view-fields-v3.json"
CONTRACT = "601dee017349a6e2195e11f37d8b4309384723497ed33e30ae0df478aee05e84"
GRAPH = "mysql-reopened-integral-field-graph"
MEANING = "meaning-mismatch"
COMPARATOR_FAULTS = {"Ignore a changed native type or nullability in an otherwise equal observation": "wrongMetadata"}


def specifications():
    path = TOOL / CONTRACT_NAME
    require(digest(path) == CONTRACT, "mysql-reopened-field-contract-mismatch")
    contract = json.loads(path.read_text())
    prior = contract["versionedFrom"]
    same(prior["sha256"], digest(TOOL / prior["path"]))
    same([], contract["finite"]["allowedExclusions"])
    same([], contract["exploration"]["allowedExclusions"])
    return {**contract, "negativeCount": 6}, view_specification()[1]


def catalog_query():
    return ("SELECT TABLE_NAME, COLUMN_NAME, ORDINAL_POSITION, DATA_TYPE, IS_NULLABLE FROM information_schema.COLUMNS "
            "WHERE TABLE_SCHEMA='ir_fixture' AND LEFT(TABLE_NAME,7)='sir_vi_' ORDER BY BINARY TABLE_NAME, ORDINAL_POSITION")


def negative_definition(name, value):
    return f"CREATE VIEW {law.SCHEMA}.{name} AS SELECT {value} AS p"


def prepare(_, db):
    contract, _ = specifications()
    coords = list(law.view_coordinates(contract))
    base = [command for width in law.WIDTHS for command in law.base_fixture(width)]
    base.extend((negative_definition("sir_unsigned", "9223372036854775808"), negative_definition("sir_decimal", "1.25")))
    initialized = db.request({"fixture": base})
    views = db.request({"fixture": [law.declaration(*entry) for entry in coords],
                        "observeViewFixtureWarnings": [law.view_name(*entry) for entry in coords]})
    return {"base": initialized, "views": views, "catalog": db.request({"sql": catalog_query()})}


def check_preparation(_, environment, prepared):
    contract, _ = specifications()
    tag = "mysql-" + environment["database"][0]
    same({"status": "fixture"}, prepared["base"])
    same("fixture", prepared["views"]["status"])
    coordinates = list(law.view_coordinates(contract))
    for coordinate, view in zip(coordinates, prepared["views"]["views"], strict=True):
        mode = coordinate[2]
        expected = [["Warning", 1354, "View merge algorithm can't be used here for now (assumed undefined algorithm)"]] if mode == "MERGE" else []
        same(expected, view["warnings"])
        algorithm = mode if mode != "MERGE" else "UNDEFINED"
        require(view["definition"].startswith(f"CREATE ALGORITHM={algorithm} ")
                and law.quote(law.view_name(*coordinate)) in view["definition"], "mysql-reopened-installed-view", view)
    native = prepared["catalog"]
    same("rows", native["status"])
    expected = sorted((row for coordinate in coordinates for row in law.catalog(tag, *coordinate)),
                      key=lambda row: (row[0], row[2]))
    same(expected, native["rows"])


def finite_ids(_, contract, tag):
    coordinates = list(law.grid(contract, tag))
    independent = law.independent_ids()
    require(len(coordinates) == len(independent) and set(coordinates) == independent, "mysql-reopened-finite-grid")
    return independent


def graph_id(response, tag):
    target = response["source"]["table"]
    matches = [cell for cell in law.independent_ids() if law.fixture(cell)[0] == target
               and matches_graph(response, law.graph(law.coordinate_case(tag, cell)))]
    require(len(matches) == 1, "mysql-reopened-ambiguous-identity", matches)
    return matches[0]


def analysis_finite_id(_, response, item, sql):
    same(law.sql(item), sql)
    return graph_id(response, item["tag"])


def direct_finite_id(_, response, item, request):
    same(law.direct(item), request)
    return graph_id(response, item["tag"])


def negatives(tag):
    wide = law.coordinate_case(tag, ("view", "1000000000", "plain", "UNDEFINED", "implicit-unit", "alias"))
    request = law.direct(wide)
    standard = law.coordinate_case(tag, ("view", "17", "plain", "UNDEFINED", "implicit-unit", "wildcard"))
    mismatch = law.direct(standard)
    yield {**mismatch, "columns": [[["integral-slot", 0, 0], "wrong"]]}, None, refusal("An integral slot must belong to the SELECT source.")
    yield {**request, "columns": [[["slot", 0], "wrong"]]}, None, refusal("Column slot must belong to the SELECT source.")
    yield {**request, "columns": [[["integral-slot", 0, 1], "wrong"]]}, None, refusal("An integral slot must belong to the SELECT source.")
    missing = f"SELECT v.absent FROM {law.SCHEMA}.{request['source']['table']} AS v"
    yield {**law.analyzed(wide), "sql": missing}, {"missingSql": missing}, semantic("Column v.absent does not exist.")
    for name, value in [("sir_unsigned", "9223372036854775808"), ("sir_decimal", "1.25")]:
        source = {"dialect": "mysql", "release": tag, "sql": f"SELECT p FROM {law.SCHEMA}.{name}",
                  "declarations": [negative_definition(name, value)]}
        yield source, None, refusal("An external view field must have a resolved native read type.")


def observe_rejection(_, item, actual, sql):
    same(item["missingSql"], sql)
    same({"status": "db-error", "state": "42S22", "vendorCode": 1054}, actual)
    return "missing-external-view-field"


def rejected_finite_ids(*_):
    return {"missing-external-view-field"}


def requests(_, tag, contract):
    for index, coordinate in enumerate(law.grid(contract, tag)):
        item = law.coordinate_case(tag, coordinate)
        yield f"finite/{index}", law.analyzed(item), item, None
        yield f"direct/{index}", law.direct(item), item, None
    for index, (request, item, reason) in enumerate(negatives(tag)):
        yield f"negative/{index}", request, item, reason
    settings = contract["exploration"]
    for seed in settings["seeds"]:
        for index, item in enumerate(law.seeded(tag, seed, settings["casesPerSeed"])):
            yield f"seed/{seed}/{index}", law.analyzed(item), item, None
        for index, item in enumerate(law.seeded(tag, seed + 1, settings["directCasesPerSeed"])):
            yield f"direct-seed/{seed}/{index}", law.direct(item), item, None


def check_value(item, response):
    tag = item["tag"]
    profile = tag + "/utf8mb4/lower-case-table-names=0/sql-mode=empty/native/"
    profile += "materialized" if tag == "mysql-5.6.51" else "derived-merge=on"
    expected = law.graph(item)
    require(response["status"] == "constructed" and response["profile"] == profile and matches_graph(response, expected),
            GRAPH, {"expected": expected, "observed": response, "sql": law.sql(item)})


def observer_request(_, sql):
    return {"sql": sql, "observeVendorCode": True, "observeMysqlIntegerMetadata": True}


def observation(raw):
    return raw


def check_observation(item, actual, sql):
    expected = law.predict(item)
    witness = {"sql": sql, "expected": expected, "observed": actual}
    require(actual["status"] == "rows", "mysql-reopened-native-rejection", witness)
    require(actual["fields"] == expected["fields"]
            and Counter(map(canonical, actual["rows"])) == Counter(map(canonical, expected["rows"])), MEANING, witness)


def sample_forms(forms, value, _engine, item):
    forms[f"width/{len(value['columns'])}"] += 1
    source = value["source"]
    forms["fixture/" + item["coordinate"][0]] += 1
    refs = {ref[1] for ref, _ in value["columns"]}
    forms[f"repeated/{len(refs) != len(value['columns'])}"] += 1
    for field in source.get("columnTypes", ["int"] * len(source["columns"])):
        forms["storage/" + (field[1] if isinstance(field, list) else "INT")] += 1
        forms[f"nullable/{field[-1] is True if isinstance(field, list) else field == 'int'}"] += 1
    forms[f"materialized/{source.get('materializedView', False)}"] += 1


def required_forms(*_):
    return {"fixture/view", "fixture/base", "width/1", "width/8", "repeated/True", "repeated/False",
            "nullable/True", "nullable/False", "materialized/True", "materialized/False",
            *("storage/" + storage for storage in law.WIDTHS)}


def controls():
    item = law.coordinate_case("mysql-9.1.0", ("view", "1000000000", "plain", "MERGE", "dual", "alias"))
    good = law.predict(item)
    check_observation(item, good, law.sql(item))
    faulty = {}
    for position, value in ((1, "LONG"), (3, False)):
        changed = [field[:] for field in good["fields"]]
        changed[0][position] = value
        try:
            check_observation(item, {**good, "fields": changed}, law.sql(item))
        except Failure as error:
            same(MEANING, error.signal)
            faulty[str(position)] = error.detail
        else:
            raise Failure("mysql-reopened-comparator-missed-metadata", position)
    return {"wrongMetadata": faulty}


def faults(_, tag):
    directory = "sql-semantics-mysql/src/"
    padded = law.coordinate_case(tag, ("view", "17", "zero-padded", "UNDEFINED", "dual", "bare"))
    wide = law.coordinate_case(tag, ("view", "1000000000", "plain", "TEMPTABLE", "implicit-unit", "alias"))
    coerced = {**wide, "wrongRejection": "The bound column must have the signed 32-bit integer type."}
    return [
        RenderFault("read-padded-integer-type", directory + "Rules/TableDefinition/ViewReadLayout.php",
                    "strlen($digits)", "strlen($expression->text)", padded, GRAPH, law.analyzed(padded)),
        RenderFault("coerce-reopened-wide-field", directory + "Rules/TableDefinition/ViewReadLayout.php",
                    "$width < 10 ? IntegralKind::Int : IntegralKind::BigInt", "IntegralKind::Int",
                    wide, GRAPH, law.analyzed(wide)),
        RenderFault("erase-physical-integral-projection", directory + "Ir/Ordering/ProjectionColumns.php",
                    "$binding instanceof SourceBinding ? $binding->result($resolution) : $binding->projected($resolution)",
                    "$binding->projected($resolution)", coerced, GRAPH, law.analyzed(coerced)),
        RenderFault("render-first-slot-instead-of-signed-field", directory + "Ir/SelectSql.php",
                    "$input->columns[$expression->slot]->quoted()", "$input->columns[0]->quoted()",
                    wide, MEANING, law.direct(wide)),
    ]


def fault_response(item, response):
    if response["status"] != "constructed":
        require("wrongRejection" in item and response == {"status": "rejected", **refusal(item["wrongRejection"])},
                "mysql-reopened-unrelated-fault-rejection", response)
    check_value(item, response)


def fault_probe(item, response, db):
    fault_response(item, response)
    sql = response["sql"]
    check_observation(item, db.request(observer_request(item, sql)), sql)


def replay_fault(rule, record):
    check_value(rule.item, record["healthyResponse"])
    response, detail = record["mutantResponse"], record["detail"]
    try:
        fault_response(rule.item, response)
        same(response["sql"], detail["sql"])
        check_observation(rule.item, detail["observed"], detail["sql"])
    except Failure as error:
        same(rule.signal, error.signal)
        same(detail, error.detail)
        return
    raise Failure("mysql-reopened-fault-not-reproduced")


if __name__ == "__main__":
    main(sys.modules[__name__])
