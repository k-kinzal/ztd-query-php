"""Compare bound ALTER TABLE index removals with independent native effects."""

from copy import deepcopy
import json
import sys

import run_server_mysql_drop_index as native
from mysql_alter_index_cases import (
    FORMS, GRAMMAR_ERROR, IGNORED_WARNING, INVALID, LEGACY_IGNORE, LEXICAL, SEMANTIC, UNKNOWN_ALGORITHM,
    coordinate, core_points, direct_points, expected, legacy_points, output_sql, qualified_points, seeded, written_sql,
)
from mysql_drop_index_cases import ABSENT, LIFETIMES, analyze
from run import Failure, TOOL, digest, require
from run_server import same
from run_server_join import main, negative_count
from sqlite_domain import RenderFault

LABEL = "mysql-alter-index-removal"
OBSERVER = "mysql_alter_index_observer.php"
NAME = "mysql-alter-index-removal-evidence-v1.json"
BASE = "b0c41129a37d7d30becf318f31ea5f82f016be99a9664543195689d11311c401"
CONTRACT = "a3d9f469bdf68a59640d35e9840c8a41c3c2a841b44e5592db85c4786ed7af3c"
COMPARATOR_FAULTS = {"Ignore absence of the legacy deprecation warning.": "lost-native-legacy-warning"}


def specifications():
    base, path = TOOL / "mysql-alter-index-removal-v1.json", TOOL / NAME
    require(digest(base) == BASE and digest(path) == CONTRACT, "mysql-alter-index-removal-contract")
    specification = json.loads(path.read_text())
    fixed = json.loads(base.read_text())
    for field in ("forms", "ownership", "orderedDirectives", "qualifications"):
        same(fixed["finite"][field], specification["finite"][field])
    same(fixed["finite"]["budgets"] | {"allowedExclusions": []}, specification["exploration"])
    return specification, native.specifications()[1]


def points(spec, direct):
    finite = spec["finite"]
    adapters = (direct_points,) if direct else (core_points, qualified_points, legacy_points)
    selection = [point for generate in adapters for point in generate(finite)]
    require(len(selection) == len(set(selection)) == spec["directCoordinates" if direct else "analysisCoordinates"],
            "mysql-alter-index-unbalanced-domain")
    return selection


def by_route(spec, tag, direct):
    return [coordinate(point, tag) for point in points(spec, direct)]


def finite_ids(_, spec, tag):
    return permitted(spec, tag, False)


def direct_finite_ids(_, spec, tag):
    return permitted(spec, tag, True)


def permitted(spec, tag, direct):
    cases = {tuple(item["point"]) for item in by_route(spec, tag, direct) if item["nativeError"] is None}
    grid = spec["directAdmitted" if direct else "analysisAdmitted"]
    key = tag.removeprefix("mysql-")
    require(len(cases) == grid.get(key, grid["default"]), "mysql-alter-index-unbalanced-admission")
    return cases


def edge_cases(tag):
    point = ("edge", "drop-primary-key", "persistent", "primary", "no-options", "unqualified", False)
    absent = coordinate(point, tag)
    clustered = coordinate(("edge", "drop-index", "persistent", "clustered-unique", "no-options", "unqualified", False), tag)
    absent["model"] = clustered["model"]
    absent.update(nativeError=ABSENT, boundary=(INVALID, native.RESOLUTION), rejectionId="no-physical-primary")
    yield absent
    unknown = deepcopy(clustered)
    unknown.update(syntax=("ALGORITHM=SPELLING", "action", "ALGORITHM=DEFAULT"), nativeError=UNKNOWN_ALGORITHM,
                   boundary=(SEMANTIC, "SPELLING is not an ALGORITHM the server knows."), rejectionId="invalid-overridden-algorithm")
    yield unknown
    restrict = deepcopy(clustered)
    restrict.update(nativeError=GRAMMAR_ERROR,
                    boundary=(LEXICAL, "Unexpected 'RESTRICT'"), rejectionId="restrict-is-unavailable")
    yield restrict


def sql_input(item):
    if item.get("rejectionId") == "restrict-is-unavailable":
        return written_sql(item) + " RESTRICT"
    return written_sql(item)


def request(item):
    return analyze(item["model"], sql=sql_input(item))


def id_for(item):
    return item.get("rejectionId", "/".join(map(str, item["point"])))


def requests(engine, tag, spec):
    rejected = 0
    for direct in (False, True):
        items = by_route(spec, tag, direct)
        if not direct:
            items += list(edge_cases(tag))
        area = "direct" if direct else "finite"
        for item in items:
            value = item["model"] if direct else request(item)
            if item["nativeError"]:
                rejected += 1
                diagnostic, message = item["boundary"]
                if direct and item["point"][-1] and tag != LEGACY_IGNORE:
                    diagnostic, message = INVALID, "Only MySQL 5.6.51 admits ALTER IGNORE TABLE."
                yield f"negative/{area}/{id_for(item)}", value, None if direct else item, {"exception": diagnostic, "message": message}
            else:
                yield f"{area}/{id_for(item)}", value, item, None
    same(negative_count(spec, engine, tag), rejected)
    for seed in spec["exploration"]["seeds"]:
        for ordinal, item in enumerate(seeded(tag, spec["finite"], seed, spec["exploration"]["casesPerSeed"])):
            yield f"seed/{seed}/{ordinal}", request(item), item, None
            yield f"direct-seed/{seed}/{ordinal}", item["model"], item, None


def analysis_finite_id(_, response, item, sql):
    same(sql_input(item), sql)
    return measured_point(response, item)


def direct_finite_id(_, response, item, value):
    same(item["model"], value)
    return measured_point(response, item)


def measured_point(response, item):
    group, form, life, _role, directive, qualifier, _ignore = item["point"]
    physical = response["mysqlDropIndex"]
    same("table" if life == "persistent" else "temporary-table", physical["owner"][2])
    return group, form, life, physical["role"], directive, qualifier, physical["ignore"]


def rejected_finite_ids(spec, tag):
    return {id_for(item) for item in [*by_route(spec, tag, False), *edge_cases(tag)] if item["nativeError"]}


def check_value(item, response):
    native.check_value(item, response)


def observer_request(item, sql):
    return native.observer_request(item, sql)


def observation(raw):
    return raw


def check_observation(item, actual, sql):
    predicted = expected(item)
    witness = {"expected": predicted, "observed": actual, "sql": sql}
    require(actual.get("status") == predicted["status"], "mysql-alter-index-observer-scope", witness)
    require({key: actual[key] for key in predicted} == predicted, "meaning-mismatch", witness)
    failure = item["nativeError"]
    if failure:
        require(actual["affected"] is None and any(note[1] == failure[2] for note in actual["diagnostics"]), "meaning-mismatch", witness)
    else:
        warnings = [IGNORED_WARNING] if item["model"]["mysqlDropIndex"]["ignore"] else []
        require(type(actual["affected"]) is int and actual["affected"] >= 0 and actual["diagnostics"] == warnings, "meaning-mismatch", witness)
    expected_sql = (sql_input(item), output_sql(item))
    require(sql in expected_sql, "mysql-alter-index-sql-mismatch", {**witness, "expectedSQL": expected_sql})


def observe_rejection(_, item, actual, sql):
    check_observation(item, actual, sql)
    return id_for(item)


def required_forms(_, tag):
    return {*(f"form/{form}" for form in FORMS), *(f"owner/{life}" for life in LIFETIMES),
            "role/primary", "role/clustered-unique", "role/unique", "role/ordinary", "ignore/False",
            *(("ignore/True",) if tag == LEGACY_IGNORE else ())}


def sample_forms(forms, response, _, item):
    _, form, life, _, directive, qualifier, _ = item["point"]
    physical = response["mysqlDropIndex"]
    forms.update((f"form/{form}", f"owner/{life}", f"role/{physical['role']}", f"ignore/{physical['ignore']}",
                  f"directives/{directive}", f"qualification/{qualifier}"))


def witness(tag):
    point = ("edge", "drop-key", "persistent", "clustered-unique", "no-options", "unqualified", tag == LEGACY_IGNORE)
    return coordinate(point, tag)


def outsiders():
    return ("ALTER TABLE other DROP KEY plain", "ALTER TABLE ir_index_removal DROP COLUMN a",
            "ALTER TABLE ir_index_removal DROP KEY plain; SELECT 1")


def prepare(_, db):
    item = witness(LEGACY_IGNORE)
    return {"outside": [db.request(observer_request(item, sql)) for sql in outsiders()]}


def check_preparation(_, _environment, preparation):
    same({"outside": [{"status": "outside-observer-scope"} for _ in outsiders()]}, preparation)


def controls():
    item = witness(LEGACY_IGNORE)
    sql = output_sql(item)
    before = {**expected(item), "affected": 0, "diagnostics": [IGNORED_WARNING]}
    check_observation(item, before, sql)
    changed = {**before, "diagnostics": []}
    try:
        check_observation(item, changed, sql)
    except Failure as error:
        same("meaning-mismatch", error.signal)
        return {"lost-native-legacy-warning": error.detail}
    raise Failure("mysql-alter-index-unobserved-warning")


def check_fault_witness(item, receipt):
    same(expected(item), receipt["expected"])
    same(["executed"], receipt["observed"]["effect"])
    same([], receipt["observed"]["diagnostics"])


def faults(_, tag):
    item = witness(tag)
    path = "sql-semantics-mysql/src/Ir/Schema/Index/DropIndex.php"
    if tag == LEGACY_IGNORE:
        return [RenderFault("erase-mysql-alter-ignore", path, "'ALTER IGNORE TABLE '", "'ALTER TABLE '",
                            item, "meaning-mismatch", item["model"])]
    unrelated = "'" + chr(96) + "retained_c" + chr(96) + "'"
    return [RenderFault("remove-unrelated-mysql-alter-index", path, "$this->target->name->quoted()", unrelated,
                        item, "meaning-mismatch", item["model"])]


if __name__ == "__main__":
    main(sys.modules[__name__])
