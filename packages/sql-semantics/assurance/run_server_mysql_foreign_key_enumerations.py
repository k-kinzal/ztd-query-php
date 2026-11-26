"""Compare native ENUM and SET references by unsigned storage and actual label bytes."""

from copy import deepcopy
import json
import sys

import mysql_foreign_key_enumeration_cases as laws
import mysql_foreign_key_enumeration_values as values
from run import TOOL, Failure, digest, require
from run_server import same
from run_server_join import main, negative_count
from run_server_mysql_drop_index import DIAGNOSTIC, specifications as native_profiles
from run_server_mysql_foreign_key_strings import note_codes
from sqlite_domain import RenderFault

LABEL = "mysql-foreign-key-enumerations"
OBSERVER = "mysql_foreign_key_enumeration_observer.php"
NAME = "mysql-foreign-key-enumeration-evidence-v2.json"
CONTRACT = "cd01a0d2fb9a7d5327aa98b63dd4e537d6e1353f42c772284413ff702732d2b9"
BASE = "d2557c5f551a0e4c836d0fa9c04aa92e6d1763e596fa2523fa93a11c9686528d"
BOUNDARIES = {
    "family": "A native ENUM or SET reference requires equal-width unsigned field encodings.",
    "slots": "A native ENUM or SET field needs settled member slots.",
    "comma": "A native SET member cannot contain a comma.",
    "distinct": "A native SET requires a checked upper bound of 64 distinct members.",
    "role": "An ENUM or SET comparison needs a declared label-based field.",
    "release": "A native ENUM or SET label must match its release dictionary format.",
    "collation": "A native ENUM or SET field needs a resolved utf8mb4 collation.",
}
COMPARATOR_FAULTS = {
    "Compare the printed label instead of the ordinal.": "compare-native-label-text",
    "Mask off stored bits outside the member list.": "erase-hidden-mask-bits",
}


def specifications():
    file = TOOL / NAME
    require(digest(file) == CONTRACT, "mysql-foreign-key-enumeration-contract")
    spec = json.loads(file.read_text())
    name, checksum = next(iter(spec["law"].items()))
    same(BASE, checksum)
    require(digest(TOOL / name) == BASE, "mysql-foreign-key-enumeration-law")
    rules = json.loads((TOOL / name).read_text())
    for prior, expected in {**spec["revises"], **rules["extends"], **rules["revises"]}.items():
        require(digest(TOOL / prior) == expected, "mysql-foreign-key-enumeration-prior", prior)
    _, profiles = native_profiles()
    same(rules["language"]["releases"], spec["releases"])
    same(profiles["mysql"]["releases"], spec["releases"])
    return spec, profiles


def finite_ids(_, spec, tag):
    fixed = spec["finite"]
    points = laws.finite_points(spec)
    fields = fixed["fields"]
    counterpart_only = sum(field[0] != "enumeration" for field in fields)
    same(len(fields) ** 2 - counterpart_only ** 2, len(points))
    same(fixed["coordinates"], len(points))
    positive = {point for point in points if laws.coordinate(spec, tag, point)["admitted"]}
    same(fixed["admitted"], len(positive))
    return positive


def pair(group, name, value, point=None):
    routes = {"finite": "direct", "seed": "direct-seed", "authored": "direct-authored"}
    for branch, construct in ((group, laws.analyze), (routes[group], laws.direct)):
        yield f"{branch}/{name}", construct(value), {"model": value, "point": list(point) if point else None}, None


def refusal(reason):
    return {"exception": DIAGNOSTIC, "message": BOUNDARIES[reason]}


def boundary_cases(tag):
    field = laws.enumeration("ENUM", ["red", "green"])
    value = laws.model(tag, [field], [field])
    native_single = laws.integer("TINYINT", True)
    bad_label = "has\0zero" if tag in laws.LEGACY else "has-space "
    for case, sides, reason in (
        ("no-slots", [laws.enumeration("ENUM", []), field], "slots"),
        ("set-comma", [laws.enumeration("SET", ["a,b"]), field], "comma"),
        ("set-too-many-distinct", [laws.named("SET", 65), field], "distinct"),
        ("no-enumeration-role", [native_single, native_single], "role"),
        ("release-label-format", [laws.enumeration("ENUM", [bad_label]), field], "release"),
    ):
        direct = laws.direct(value)
        direct["mysqlAddForeignKeys"]["additions"][0]["pairs"][0][3:5] = sides
        yield f"negative/boundary/{case}", direct, None, refusal(reason)
    for case, shape, reason in (
        ("unknown-collation", [field, field, None], "collation"),
        ("set-comma-after-zero", [laws.enumeration("SET", ["red\0,tail"]), field, None], "comma"),
        ("too-many-distinct-set-slots", [laws.named("SET", 65), field, None], "distinct"),
    ):
        child, parent, syntax = shape
        request = laws.analyze(laws.model(tag, [child], [parent], syntax=syntax))
        if case == "unknown-collation":
            request["declarations"] = [ddl.replace(" CHARACTER SET utf8mb4 COLLATE utf8mb4_bin", "")
                                       for ddl in request["declarations"]]
        yield f"negative/boundary/{case}", request, None, refusal(reason)


def plan(spec, tag):
    for point in laws.finite_points(spec):
        value = laws.coordinate(spec, tag, point)
        name = "/".join(map(str, point))
        if value["admitted"]:
            yield from pair("finite", name, value, point)
        else:
            for route, construct in (("analysis", laws.analyze), ("direct", laws.direct)):
                cid = f"{name}/{route}"
                yield f"negative/finite/{cid}", construct(value), {"model": value, "rejectionId": cid}, refusal("family")
    for seed in spec["exploration"]["seeds"]:
        for index, value in enumerate(laws.explored(spec, tag, seed)):
            yield from pair("seed", f"{seed}/{index}", value)
    authored = list(laws.authored(tag))
    same(len(authored), spec["authoredPositive"])
    for name, value in authored:
        yield from pair("authored", name, value)
    negatives = list(boundary_cases(tag))
    same(len(negatives), spec["boundaryNegatives"])
    yield from negatives


def requests(engine, tag, spec):
    rows = list(plan(spec, tag))
    same(negative_count(spec, engine, tag), sum(entry[3] is not None for entry in rows))
    yield from rows


def rejected_finite_ids(spec, tag):
    return {item["rejectionId"] for _, _, item, boundary in plan(spec, tag) if boundary and item is not None}


def rejection_sql(item, _):
    return laws.sql(item["model"], True)


def observe_rejection(_, item, actual, sql):
    require(not item["model"]["admitted"], "mysql-foreign-key-enumeration-should-be-admitted")
    check_observation(item, actual, sql)
    return item["rejectionId"]


def check_value(item, actual):
    model = item["model"]
    require(actual.get("status") == "constructed", "mysql-foreign-key-enumeration-not-constructed", actual)
    same(model["release"] + laws.PROFILE, actual["profile"])
    same({**laws.direct(model)["mysqlAddForeignKeys"], "removedIndexes": []}, actual["mysqlAddForeignKeys"])


def coordinate_seen(response, item):
    _, _, role, left, right, _, _ = response["mysqlAddForeignKeys"]["additions"][0]["pairs"][0]
    same("enumeration", role)
    fixed, _ = specifications()
    fields = [laws.wire_field(field) for field in fixed["finite"]["fields"]]
    point = (fields.index(left), fields.index(right))
    same(item["point"], list(point))
    return point


def analysis_finite_id(_, response, item, source):
    same(laws.sql(item["model"]), source)
    same(laws.sql(item["model"], True), response["sql"])
    return coordinate_seen(response, item)


def direct_finite_id(_, response, item, request):
    same(laws.direct(item["model"]), request)
    same(laws.sql(item["model"], True), response["sql"])
    return coordinate_seen(response, item)


def required_forms(_, _tag):
    return {"shape/1", "shape/2", "shape/3", "child/ENUM", "child/SET", "parent/ENUM", "parent/SET",
            "peer/ordinary", "peer/year", "row/admitted", "row/refused", "row/null", "event/update", "event/delete",
            "key/1", "key/2", "key/3", "key/4", "key/8"}


def sample_forms(counts, actual, _, _item):
    pairs = actual["mysqlAddForeignKeys"]["additions"][0]["pairs"]
    counts[f"shape/{len(pairs)}"] += 1
    for _, _, _, left, right, *_ in pairs:
        interpretable = [left[0], "INT", *left[2:]] if left[:2] == ["integer", "INTEGER"] else left
        counts[f"key/{laws.width(interpretable)}"] += 1
        for role, field in (("child", left), ("parent", right)):
            counts[f"{role}/{field[1]}"] += 1
            if field[0] != "enumeration":
                counts["peer/year" if field[1] == "YEAR" else "peer/ordinary"] += 1


def sample_native_forms(counts, actual, _, _item):
    for effect, row, _ in actual["data"][1]:
        label = "refused" if effect[0] == "db-error" else "null" if all(value is None for value in row) else "admitted"
        counts[f"row/{label}"] += 1
    for event, *_ in actual["events"]:
        counts[f"event/{event}"] += 1


def observation(actual):
    return actual


def observer_request(item, sql):
    model = item["model"]
    return {"sql": sql, "declarations": laws.declarations(model), "width": len(model["child"]),
            "inputs": values.prepared(model)}


def duplicates(fields):
    return [["Note", 1291] for field in fields if field[0] == "enumeration"
            for _ in range(len(field[3]) - len(set(field[3])))]


def expected(model):
    tag = model["release"]
    admitted = model["admitted"]
    legacy = tag in laws.LEGACY
    children, parents = laws.columns(model, "x"), laws.columns(model, "p")
    primary = [["PRIMARY", 1, "z", 0, "A"]]
    adds = [[laws.SYMBOL, index + 1, name, 1, "A"] for index, name in enumerate(children)] if admitted else []
    refs = [[laws.SYMBOL, index + 1, name, laws.DB, laws.PARENT, target]
            for index, (name, target) in enumerate(zip(children, parents))] if admitted else []
    types = [[owner, name, *values.native_type(field, tag)]
             for owner, names, role in ((laws.CHILD, children, "child"), (laws.PARENT, parents, "parent"))
             for name, field in zip(names, model[role])]
    effect = ["executed"] if admitted else ["db-error", "HY000", 1215 if legacy else 3780]
    diagnostics = duplicates(model["child"]) if admitted else ([["Warning", 150]] if legacy else []) + [["Error", effect[2]]]
    state = values.measure(model) if admitted else {"data": [[], []], "events": []}
    return {"status": laws.STATUS, "before": primary, "after": sorted(primary + adds), "edges": refs,
            "rules": [[laws.SYMBOL, "CASCADE", "SET NULL", "NONE"]] if admitted else [], "types": types, "checks": [1,1],
            "marker": 7, "active": False, "effect": effect, "affected": 0 if admitted else None, "diagnostics": diagnostics,
            "setupWarnings": [duplicates(model[role]) for role in ("parent", "child")], **state}


def project_warnings(actual):
    result = deepcopy(actual)
    result["diagnostics"] = note_codes(actual["diagnostics"])
    result["setupWarnings"] = [note_codes(warnings) for warnings in actual["setupWarnings"]]
    result["data"][1] = [[effect, row, note_codes(notes)] for effect, row, notes in actual["data"][1]]
    result["events"] = [[event, effect, parent, child, note_codes(notes)] for event, effect, parent, child, notes in actual["events"]]
    return result


def check_observation(item, actual, sql):
    wanted = expected(item["model"])
    detail = {"expected": wanted, "observed": actual, "sql": sql}
    require(actual.get("status") == laws.STATUS, "mysql-foreign-key-enumeration-unobserved", detail)
    require(project_warnings(actual) == wanted, "meaning-mismatch", detail)


def prepare(_, db):
    value = laws.enumeration("ENUM", ["red", "green"])
    item = {"model": laws.model("mysql-9.1.0", [value], [value])}
    sql = laws.sql(item["model"])
    return [db.request(observer_request(item, statement)) for statement in (
        sql + "; SELECT 1", sql.replace(laws.CHILD, "elsewhere"), sql.replace(laws.PARENT, "elsewhere"))]


def check_preparation(_, _environment, observed):
    same([{"status": "outside-observer-scope"}] * 3, observed)


def controls():
    probes = dict(laws.authored("mysql-9.1.0"))
    witnesses = (("compare-native-label-text", "swapped-labels"), ("erase-hidden-mask-bits", "mask-with-hidden-bits"))
    result = {}
    for fault, name in witnesses:
        item = {"model": probes[name]}
        sql = laws.sql(item["model"])
        normal = expected(item["model"])
        check_observation(item, normal, sql)
        changed = deepcopy(normal)
        if fault == "compare-native-label-text":
            changed["data"][1][1] = [values.SUCCESS, ["726564", "2"], []]
        else:
            changed["events"][0][3][0][1] = "3"
        try:
            check_observation(item, changed, sql)
        except Failure as error:
            same("meaning-mismatch", error.signal)
            result[fault] = error.detail
        else:
            raise Failure("mysql-foreign-key-enumeration-control-missed", fault)
    return result


def faults(_, tag):
    path = "sql-semantics-mysql/src/Ir/Schema/ForeignKey/Add/ForeignKeyEffects.php"
    model = dict(laws.authored(tag))["swapped-labels"]
    item = {"model": model}
    return [
        RenderFault("drop-native-enumeration-cascade", path, "$this->update->value", "'NO ACTION'",
                    item, "meaning-mismatch", laws.direct(model)),
        RenderFault("drop-native-enumeration-set-null", path, "$this->delete->value", "'NO ACTION'",
                    item, "meaning-mismatch", laws.direct(model)),
    ]


def check_fault_witness(_, witness):
    expected, actual = witness["expected"], witness["observed"]
    same(["executed"], actual["effect"])
    same(expected["data"], project_warnings(actual)["data"])
    require(expected["rules"] != actual["rules"] and expected["events"] != actual["events"],
            "mysql-foreign-key-enumeration-fault-no-effect", witness)


if __name__ == "__main__":
    main(sys.modules[__name__])
