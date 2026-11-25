"""Compare persistent MySQL view reference bindings and read headings."""

from collections import Counter
import json
import sys

from evidence_transcript import canonical
import mysql_external_view_read_name_cases as law
from run import Failure, TOOL, digest, require
from run_server import same
from run_server_join import OBSERVER, main
from run_server_mysql_external_views import DIAGNOSTICS, specifications as previous_specification
from run_server_mysql_signed_membership import refusal
from sqlite_domain import RenderFault

LABEL = "mysql-external-view-read-names"
CONTRACT_NAME = "mysql-external-view-read-names-v2.json"
CONTRACT = "c995f94d2554e292aed4d3edb48b3c768f39362f6f577f6356f6d5a3fa3df6ec"
MEANING = "meaning-mismatch"
GRAPH = "mysql-view-read-graph"
COMPARATOR_FAULTS = {"Ignore an exact visible result heading mismatch.": "missingViewHeading"}


def specifications():
    path = TOOL / CONTRACT_NAME
    require(digest(path) == CONTRACT, "mysql-view-read-contract")
    contract = json.loads(path.read_text())
    reference = contract["versionedFrom"]
    same(reference["sha256"], digest(TOOL / reference["path"]))
    same([], contract["finite"]["allowedExclusions"])
    same([], contract["exploration"]["allowedExclusions"])
    return contract, previous_specification()[1]


def metadata():
    return ("SELECT TABLE_NAME, HEX(COLUMN_NAME), ORDINAL_POSITION, DATA_TYPE, IS_NULLABLE "
            "FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = 'ir_fixture' "
            "AND LEFT(TABLE_NAME, 10) = 'view_read_' AND TABLE_NAME <> 'view_read_data' "
            "ORDER BY BINARY TABLE_NAME, ORDINAL_POSITION")


def prepare(_, db):
    contract, _ = specifications()
    tag = "mysql-" + db.environment["database"][0]
    catalog = list(law.fixtures(tag, contract))
    cells = ", ".join("(" + ", ".join(str(value) if value is not None else "NULL" for value in row) + ")"
                      for row in law.ROWS)
    setup = db.request({"fixture": [
        law.BASE_DDL, f"INSERT INTO {law.SCHEMA}.{law.BASE} VALUES {cells}",
        *(law.declaration(shape, algorithm) for shape, algorithm in catalog),
    ]})
    return {"fixture": setup, "catalog": db.request({"sql": metadata()})}


def check_preparation(_, environment, prepared):
    tag = "mysql-" + environment["database"][0]
    same({"status": "fixture"}, prepared["fixture"])
    expected = []
    fixture_ids = {coordinate[:2] for coordinate in law.independent_ids(tag, True)}
    for shape, algorithm in sorted(fixture_ids, key=lambda cell: law.identity(*cell)):
        table = law.identity(shape, algorithm)
        for index, name in enumerate(law.headings(tag, shape)):
            displayed = name[:64] if tag in law.LEGACY else name
            expected.append([table, displayed.encode().hex().upper(), index + 1, "int", "YES" if index == 1 else "NO"])
    same("rows", prepared["catalog"]["status"])
    same(expected, prepared["catalog"]["rows"])


def finite_identities(contract, tag, direct=False):
    listed = list(law.finite_grid(tag, contract, direct))
    independent = law.independent_ids(tag, direct)
    phase = "direct" if direct else "analyze"
    family = "legacy" if tag in law.LEGACY else "modern"
    require(len(listed) == len(independent) == contract["finite"]["counts"][family][phase]
            and set(listed) == independent, "mysql-view-read-finite-definition")
    return independent


def finite_ids(_, contract, tag):
    return finite_identities(contract, tag)


def direct_finite_ids(_, contract, tag):
    return finite_identities(contract, tag, True)


def graph_id(response, tag):
    matches = [coordinate[:3] for coordinate in law.independent_ids(tag, True)
               if matches_graph(response, law.graph(law.finite(tag, *coordinate)))]
    require(len(matches) == 1, "mysql-view-read-finite-identity", matches)
    return matches[0]


def analysis_finite_id(_, response, item, sql):
    same(law.sql(item), sql)
    shape, algorithm, pattern = graph_id(response, item["tag"])
    qualified = sql.endswith(" AS v")
    same(item["qualified"], qualified)
    return shape, algorithm, pattern, "alias" if qualified else "bare"


def direct_finite_id(_, response, item, request):
    same(law.direct(item), request)
    return *graph_id(response, item["tag"]), None


def rejected_finite_ids(*_):
    return {"missing-view-field"}


def semantic(message):
    return {"exception": DIAGNOSTICS + "SemanticException", "message": message}


def observe_rejection(_, item, actual, sql):
    same({"status": "db-error", "state": "42S22", "vendorCode": 1054}, actual)
    same(item["negativeSql"], sql)
    return "missing-view-field"


def negatives(tag):
    item = law.finite(tag, "inherited-leading", "UNDEFINED", "canonical", "alias")
    missing = f"SELECT v.missing_a FROM {law.SCHEMA}.{law.identity(item['shape'], item['algorithm'])} AS v"
    yield law.analyzed(item, missing), {**item, "negativeSql": missing}, semantic("Column v.missing_a does not exist.")
    template = law.direct(item)
    source = template["source"]
    yield {**template, "source": {**source, "columns": ["K", "k"]}}, None, refusal("A table source cannot repeat a column name.")
    yield {**template, "source": {**source, "columns": ["漢" * 86, "e"]}}, None, refusal("An external view field needs at most 256 native bytes.")
    yield {**template, "source": {**source, "relationKind": "table", "materializedView": True}}, None, refusal("Only a view uses the native materialized view heading.")
    invalid = {**template, "columns": [[["slot", 0, 0], " k"]],
               "source": {**source, "columns": [" K"], "columnTypes": ["int-not-null"]}}
    yield invalid, None, refusal("An intrinsic MySQL result name must equal its physical input field.")


def requests(_, tag, contract):
    for index, coordinate in enumerate(law.finite_grid(tag, contract)):
        item = law.finite(tag, *coordinate)
        yield f"finite/{index}", law.analyzed(item), item, None
    for index, coordinate in enumerate(law.finite_grid(tag, contract, True)):
        item = law.finite(tag, *coordinate)
        yield f"direct/{index}", law.direct(item), item, None
    for index, (request, item, reason) in enumerate(negatives(tag)):
        yield f"negative/{index}", request, item, reason
    settings = contract["exploration"]
    for seed in settings["seeds"]:
        for index, item in enumerate(law.seeded(tag, seed, settings["casesPerSeed"])):
            yield f"seed/{seed}/{index}", law.analyzed(item), item, None
        for index, item in enumerate(law.seeded(tag, seed + 1, settings["directCasesPerSeed"])):
            yield f"direct-seed/{seed}/{index}", law.direct(item), item, None


def matches_graph(reply, expected):
    return all(key in reply and reply[key] == value for key, value in expected.items())


def check_value(item, reply):
    profile = item["tag"] + "/utf8mb4/lower-case-table-names=0/sql-mode=empty/native/"
    profile += "materialized" if item["tag"] == "mysql-5.6.51" else "derived-merge=on"
    expected = law.graph(item)
    require(reply["status"] == "constructed" and reply["profile"] == profile and matches_graph(reply, expected),
            GRAPH, {"expected": expected, "observed": reply})


def observer_request(_, sql):
    return {"sql": sql, "observeVendorCode": True}


def observation(raw):
    return raw


def check_observation(item, actual, sql):
    expected = law.predict(item)
    witness = {"sql": sql, "expected": expected, "observed": actual}
    require(actual.get("status") == "rows", "mysql-view-read-native-rejection", witness)
    require(actual["fields"] == expected["fields"]
            and Counter(map(canonical, actual["rows"])) == Counter(map(canonical, expected["rows"])), MEANING, witness)


def sample_forms(forms, response, _engine, item):
    source = response["source"]
    forms[f"materialized/{source.get('materializedView', False)}"] += 1
    forms[f"width/{len(response['columns'])}"] += 1
    seen = []
    for (reference, label), (_, written, explicit) in zip(response["columns"], item["projection"], strict=True):
        index = reference[1]
        seen.append(index)
        name = source["columns"][index]
        forms[f"empty/{not name}"] += 1
        forms[f"non-ASCII/{not name.isascii()}"] += 1
        forms[f"wide/{len(name) > 64}"] += 1
        alternate = source.get("materializedView") is True and explicit is None and label == written and label != name
        forms[f"alternate-implicit/{alternate}"] += 1
        forms[f"nullability/{source.get('columnTypes', ['int'] * len(source['columns']))[index]}"] += 1
    forms[f"repeat/{len(seen) != len(set(seen))}"] += 1


def required_forms(_, tag):
    common = {"materialized/True", "materialized/False", "width/2", "width/6", "empty/True", "empty/False",
              "non-ASCII/True", "non-ASCII/False", "repeat/True", "repeat/False", "alternate-implicit/True",
              "nullability/int-not-null", "nullability/int"}
    return common | ({"wide/True"} if tag in law.LEGACY else set())


def controls():
    item = law.finite("mysql-9.1.0", "explicit-leading", "TEMPTABLE", "folded", "alias")
    healthy = law.predict(item)
    check_observation(item, healthy, law.sql(item))
    observed = {**healthy, "fields": [["K", "LONG"], *healthy["fields"][1:]]}
    try:
        check_observation(item, observed, law.sql(item))
    except Failure as error:
        same(MEANING, error.signal)
        return {"missingViewHeading": error.detail}
    raise Failure("mysql-view-read-comparator-ignored-heading")


def faults(_, tag):
    source = "sql-semantics-mysql/src/"
    inherited = law.finite(tag, "inherited-leading", "UNDEFINED", "canonical", "alias")
    inherited["wrongRejection"] = "Column v.K does not exist."
    folded = law.finite(tag, "exact-utf8", "UNDEFINED", "folded", "bare")
    folded["wrongRejection"] = "Column k does not exist."
    materialized = law.finite(tag, "explicit-leading", "TEMPTABLE", "folded", "alias")
    consumed = law.finite(tag, "exact-utf8", "MERGE", "twice-mixed", "alias")
    return [
        RenderFault("read-raw-declared-view-alias", source + "Rules/TableDefinition/ViewColumns.php",
                    "$inputs->assignedAlias($item->alias->value)", "$item->alias->value",
                    inherited, GRAPH, law.analyzed(inherited)),
        RenderFault("resolve-external-view-columns-in-ascii", source + "Model/Resolution/LookupLevel.php",
                    "$relation->shape->nativeColumnNames ? new NativeLocalNames() : null", "null",
                    folded, GRAPH, law.analyzed(folded)),
        RenderFault("read-first-external-view-field", source + "Ir/SelectSql.php",
                    "$input->columns[$expression->slot]->quoted()", "$input->columns[0]->quoted()",
                    consumed, MEANING, law.direct(consumed)),
        RenderFault("lose-materialized-view-name-rule", source + "Rules/TableDefinition/ViewFacts.php",
                    "$definition->algorithm === ViewAlgorithm::TempTable", "false",
                    materialized, GRAPH, law.analyzed(materialized)),
    ]


def verify_response(item, reply):
    if reply["status"] == "constructed":
        try:
            check_value(item, reply)
        except Failure as error:
            same(GRAPH, error.signal)
            raise Failure(GRAPH, {**error.detail, "sql": law.sql(item)}) from error
        return
    require("wrongRejection" in item and reply == {"status": "rejected", **semantic(item["wrongRejection"])},
            "mysql-view-read-unrelated-fault-rejection", reply)
    raise Failure(GRAPH, {"expected": law.graph(item), "observed": reply, "sql": law.sql(item)})


def fault_probe(item, reply, db):
    verify_response(item, reply)
    sql = reply["sql"]
    check_observation(item, db.request(observer_request(item, sql)), sql)


def replay_fault(rule, record):
    check_value(rule.item, record["healthyResponse"])
    reply, detail = record["mutantResponse"], record["detail"]
    try:
        verify_response(rule.item, reply)
        same(reply["sql"], detail["sql"])
        check_observation(rule.item, detail["observed"], detail["sql"])
    except Failure as error:
        same(rule.signal, error.signal)
        same(detail, error.detail)
        return
    raise Failure("mysql-view-read-fault-not-reproduced")


if __name__ == "__main__":
    main(sys.modules[__name__])
