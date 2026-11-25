"""Compare resolved MySQL truncation foreign-key observations across native releases."""

from copy import deepcopy
import json
import sys

import run_server_mysql_truncate as base
from mysql_truncate_fk_cases import (
    FORMS, OLDER, RESTRICTED, STATUS, TABLES, analyzed, coordinates, declarations, expected, profile_case, seed_cases, shape, statement, wire,
)
from run import Failure, TOOL, digest, require
from run_server import same
from run_server_join import main, negative_count
from sqlite_domain import RenderFault

LABEL = "mysql-truncate-foreign-keys"
OBSERVER = "mysql_truncate_fk_observer.php"
NAME = "mysql-truncate-foreign-keys-evidence-v1.json"
CONTRACT = "dcab4637dd718fb5c9dce3f7bc8dfe39f00b359287b422d25c0ba7b6424f9dd5"
PREMISES = "360b2331f177deb6e7e060341d3441d3dab037f874836b8debd3cc65149ee57c"
PROFILE = "/utf8mb4/lower-case-table-names=0/sql-mode=empty/native/foreign-key-checks=1"
COMPARATOR_FAULTS = {"Erase the origin of a persistent foreign-key declaration.": "lost-foreign-key-origin"}


def specifications():
    path = TOOL / NAME
    require(digest(path) == CONTRACT and digest(TOOL / "mysql-truncate-foreign-dependencies-v2.json") == PREMISES,
            "mysql-truncate-foreign-keys-contract")
    spec = json.loads(path.read_text())
    _, profiles = base.specifications()
    same(profiles["mysql"]["releases"], spec["releases"])
    return spec, profiles


def legal(model):
    return expected(model)["effect"] != RESTRICTED


def finite_ids(_, spec, tag):
    points = coordinates(spec["finite"])
    same(spec["finite"]["identities"], len(points))
    admitted = {coord for coord in points if legal(shape(tag, coord))}
    same(spec["finite"]["before9Admitted" if tag in OLDER else "mysql9Admitted"], len(admitted))
    return admitted


def paired(group, name, model, point=None):
    item = {"model": model, "coordinate": list(point) if point is not None else None}
    direct = {"finite": "direct", "seed": "direct-seed"}.get(group, group + "-direct")
    yield f"{group}/{name}", analyzed(model), item, None
    yield f"{direct}/{name}", wire(model), item, None


def refusals(group, name, model):
    boundary = {"exception": base.DIAGNOSTIC, "message": base.DEPENDENCY_FAILURE}
    for origin, request in (("direct", wire(model)), ("analysis", analyzed(model))):
        key = f"{group}/{name}/{origin}"
        yield f"negative/{key}", request, {"model": model, "rejectionId": key}, boundary


def plan(spec, tag):
    for point in sorted(coordinates(spec["finite"])):
        model = shape(tag, point)
        name = "/".join(point)
        if legal(model):
            yield from paired("finite", name, model, point)
        else:
            yield from refusals("finite", name, model)

    for seed in spec["exploration"]["seeds"]:
        for index, model in enumerate(seed_cases(tag, seed, spec["exploration"]["casesPerSeed"])):
            name = f"{seed}/{index}"
            if not legal(model):
                yield from refusals("seed", name, model)
                model = profile_case(tag, model["form"], model["schema"], "temporary-shadow", model["occupied"], model["offset"])
            yield from paired("seed", name, model)

    empty = profile_case(tag, "same", TABLES[0], "persistent", occupied=False)
    yield from refusals("edge", "empty-child", empty)
    yield from paired("edge", "known-temporary", profile_case(tag, "shadow-child", TABLES[0], "temporary-shadow", occupied=False))


def requests(engine, tag, spec):
    rejected = 0
    for entry in plan(spec, tag):
        if entry[3] is not None:
            rejected += 1
        yield entry
    same(negative_count(spec, engine, tag), rejected)


def rejected_finite_ids(spec, tag):
    return {item["rejectionId"] for _, _, item, boundary in plan(spec, tag) if boundary is not None}


def rejection_sql(item, _request):
    return statement(item["model"])


def observe_rejection(_, item, actual, sql):
    require(not legal(item["model"]), "mysql-truncate-foreign-keys-native-refusal", item)
    check_observation(item, actual, sql)
    return item["rejectionId"]


def check_value(item, actual):
    model = item["model"]
    require(actual["status"] == "constructed", "mysql-truncate-foreign-keys-constructed", actual)
    same(model["mysqlTruncate"], actual["mysqlTruncate"])
    same(model["release"] + PROFILE, actual["profile"])


def coordinate_seen(actual, item):
    model = item["model"]
    identity = actual["mysqlTruncate"]
    scope = "temporary-shadow" if identity[2] == "temporary-table" else "persistent"
    return model["form"], identity[0], scope


def analysis_finite_id(_, actual, item, sql):
    same(statement(item["model"], True), sql)
    return coordinate_seen(actual, item)


def direct_finite_id(_, actual, item, request):
    same(wire(item["model"]), request)
    return coordinate_seen(actual, item)


def observer_request(item, sql):
    model = item["model"]
    return {"sql": sql, "declarations": declarations(model), "occupied": model["occupied"],
            "temporary": model["scope"] == "temporary-shadow", "offset": model["offset"],
            "schema": model["schema"], "form": model["form"]}


def observation(raw):
    return raw


def check_observation(item, actual, sql):
    wanted = expected(item["model"])
    detail = {"expected": wanted, "observed": actual, "sql": sql}
    require(actual.get("status") == STATUS, "mysql-truncate-foreign-keys-observer-scope", detail)
    require({key: actual[key] for key in wanted} == wanted, "meaning-mismatch", detail)
    if wanted["effect"] == RESTRICTED:
        require(len(actual["diagnostics"]) == 1 and actual["diagnostics"][0][:2] == ["Error", 1701], "meaning-mismatch", detail)
    else:
        require(actual["diagnostics"] == [], "meaning-mismatch", detail)


def required_forms(_, tag):
    common = {*(f"form/{form}" for form in FORMS), *(f"database/{schema}" for schema in TABLES),
              "scope/temporary-shadow", "scope/persistent", "relations/with-foreign-key", "relations/no-foreign-key"}
    return common | ({"inline/enforced"} if tag not in OLDER else {"inline/ignored"})


def sample_forms(forms, actual, _, item):
    form, database, scope = coordinate_seen(actual, item)
    forms.update((f"form/{form}", f"database/{database}", f"scope/{scope}"))


def sample_native_forms(forms, actual, _, item):
    keys = actual["before"]["referrers"]
    forms["relations/with-foreign-key" if keys else "relations/no-foreign-key"] += 1
    if item["model"]["form"] == "inline":
        forms["inline/enforced" if keys else "inline/ignored"] += 1


def outside():
    return ["DROP TABLE ir_truncate_parent", "TRUNCATE TABLE unrelated", "TRUNCATE \x60ir_truncate_parent\x60; SELECT 1"]


def prepare(_, db):
    item = {"model": profile_case("mysql-9.1.0", "none", TABLES[0], "persistent")}
    return {"outside": [db.request(observer_request(item, sql)) for sql in outside()]}


def check_preparation(_, _environment, receipt):
    same({"outside": [{"status": "outside-observer-scope"}] * len(outside())}, receipt)


def controls():
    model = profile_case("mysql-9.1.0", "cross-local", TABLES[0], "temporary-shadow")
    item = {"model": model}
    before = {**expected(model), "diagnostics": []}
    check_observation(item, before, statement(model))
    changed = deepcopy(before)
    changed["before"]["referrers"][0][2] = TABLES[0]
    try:
        check_observation(item, changed, statement(model))
    except Failure as error:
        same("meaning-mismatch", error.signal)
        return {"lost-foreign-key-origin": error.detail}
    raise Failure("mysql-truncate-missed-origin-control")


def check_fault_witness(item, detail):
    same(expected(item["model"]), detail["expected"])
    same(["executed"], detail["observed"]["effect"])


def faults(_, tag):
    model = profile_case(tag, "none", TABLES[0], "persistent")
    file = "sql-semantics-mysql/src/Ir/Schema/Truncate/TruncateTable.php"
    swapped = "'" + statement({**model, "schema": TABLES[1]}).removeprefix("TRUNCATE TABLE ") + "'"
    return [RenderFault("truncate-references-redirect-target", file, "$this->target->quoted()", swapped,
                        {"model": model}, "meaning-mismatch", wire(model))]


if __name__ == "__main__":
    main(sys.modules[__name__])
