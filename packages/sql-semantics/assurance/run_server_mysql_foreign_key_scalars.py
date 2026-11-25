"""Compare resolved native scalar references with exact dictionary and row effects."""

from copy import deepcopy
import json
import sys

import mysql_foreign_key_scalar_cases as cases
import mysql_foreign_key_scalar_values as values
from run import TOOL, Failure, digest, require
from run_server import same
from run_server_join import main, negative_count
from run_server_mysql_drop_index import DIAGNOSTIC, specifications as native_profiles
from run_server_mysql_foreign_key_strings import note_codes
from sqlite_domain import RenderFault

LABEL = "mysql-foreign-key-scalars"
OBSERVER = "mysql_foreign_key_scalar_observer.php"
NAME = "mysql-foreign-key-scalar-evidence-v2.json"
BASE = "ae27a6035d83b9f41606e208340253e3fb2d657006c5c337d23f0983801c2745"
CONTRACT = "14e178cbf69702558ccd1ab6753daae67c91f177d543f84f9be8d923ae104c6a"
BOUNDARY = {
    "family": "A native foreign-key pair needs compatible checked scalar field encodings.",
    "dimensions": "The native scalar field dimensions must fit the selected encoding.",
    "attributes": "Native scalar numeric attributes belong to an eligible unsigned role.",
}
COMPARATOR_FAULTS = {
    "Treat unequal packed decimal storage as equal.": "compare-decimal-display",
    "Erase the retained fractional precision of a time field.": "erase-time-precision",
}


def specifications():
    path = TOOL / NAME
    require(digest(path) == CONTRACT, "mysql-foreign-key-scalar-contract")
    spec = json.loads(path.read_text())
    law, expected = next(iter(spec["law"].items()))
    same(BASE, expected)
    require(digest(TOOL / law) == BASE, "mysql-foreign-key-scalar-law")
    rules = json.loads((TOOL / law).read_text())
    for name, checksum in {**spec["extensions"], **rules["extends"], **rules["revises"]}.items():
        require(digest(TOOL / name) == checksum, "mysql-foreign-key-scalar-prior", name)
    _, profiles = native_profiles()
    same(rules["language"]["releases"], spec["releases"])
    same(profiles["mysql"]["releases"], spec["releases"])
    return spec, profiles


def finite_ids(_, spec, tag):
    grid = spec["finite"]
    fields = grid["fields"]
    coordinates = cases.coordinates(spec)
    same(len(fields) ** 2 - sum(field[0] != "scalar" for field in fields) ** 2, len(coordinates))
    same(grid["coordinates"], len(coordinates))
    selected = {point for point in coordinates if cases.coordinate(spec, tag, point)["admitted"]}
    same(grid["admitted"], len(selected))
    return selected


def pair(axis, name, item, point=None):
    routes = {"finite": "direct", "seed": "direct-seed", "authored": "direct-authored"}
    for key, construct in ((axis, cases.analyze), (routes[axis], cases.direct)):
        yield f"{key}/{name}", construct(item), {"model": item, "point": list(point) if point is not None else None}, None


def rejection(reason):
    return {"exception": DIAGNOSTIC, "message": BOUNDARY[reason]}


def direct_boundary(tag):
    item = cases.model(tag, [cases.scalar("DECIMAL", 10, 0)], [cases.scalar("DECIMAL", 10, 0)])
    for name, field, reason in (
        ("zero-bit", cases.scalar("BIT", 0), "dimensions"),
        ("too-many-bits", cases.scalar("BIT", 65), "dimensions"),
        ("scale-over-precision", cases.scalar("DECIMAL", 2, 3), "dimensions"),
        ("scale-over-native-limit", cases.scalar("DECIMAL", 65, 31), "dimensions"),
        ("floating-with-scale-only", cases.scalar("DOUBLE", None, 2), "dimensions"),
        ("unsigned-date", cases.scalar("DATE", unsigned=True), "attributes"),
    ):
        broken = cases.direct(item)
        broken["mysqlAddForeignKeys"]["additions"][0]["pairs"][0][3] = field
        yield f"negative/boundary/{name}", broken, None, rejection(reason)


def plan(spec, tag):
    for point in cases.coordinates(spec):
        item = cases.coordinate(spec, tag, point)
        case_id = "/".join(map(str, point))
        if item["admitted"]:
            yield from pair("finite", case_id, item, point)
        else:
            for axis, construct in (("analysis", cases.analyze), ("direct", cases.direct)):
                ref = f"{case_id}/{axis}"
                yield f"negative/finite/{ref}", construct(item), {"model": item, "rejectionId": ref}, rejection("family")
    for seed in spec["exploration"]["seeds"]:
        for index, value in enumerate(cases.explored(spec, tag, seed)):
            yield from pair("seed", f"{seed}/{index}", value)
    directed = list(cases.authored(tag))
    same(spec["authoredPositive"], len(directed))
    for name, item in directed:
        yield from pair("authored", name, item)
    boundaries = list(direct_boundary(tag))
    same(spec["directInvalidBoundaryCases"], len(boundaries))
    yield from boundaries


def requests(engine, tag, spec):
    entries = list(plan(spec, tag))
    same(negative_count(spec, engine, tag), sum(boundary is not None for _, _, _, boundary in entries))
    yield from entries


def rejected_finite_ids(spec, tag):
    return {item["rejectionId"] for _, _, item, boundary in plan(spec, tag) if boundary and item is not None}


def rejection_sql(item, _):
    return cases.sql(item["model"], True)


def observe_rejection(_, item, verdict, sql):
    require(not item["model"]["admitted"], "mysql-foreign-key-scalar-unexpected-admission")
    check_observation(item, verdict, sql)
    return item["rejectionId"]


def check_value(item, actual):
    model = item["model"]
    require(actual.get("status") == "constructed", "mysql-foreign-key-scalar-not-constructed", actual)
    same(model["release"] + cases.PROFILE, actual["profile"])
    same(cases.direct(model)["mysqlAddForeignKeys"], actual["mysqlAddForeignKeys"])


def coordinate_seen(actual, item):
    _, _, role, child, parent, _, _ = actual["mysqlAddForeignKeys"]["additions"][0]["pairs"][0]
    same("scalar", role)
    spec, _ = specifications()
    fields = spec["finite"]["fields"]
    point = (fields.index(child), fields.index(parent))
    same(item["point"], list(point))
    return point


def analysis_finite_id(_, actual, item, original):
    same(cases.sql(item["model"]), original)
    same(cases.sql(item["model"], True), actual["sql"])
    return coordinate_seen(actual, item)


def direct_finite_id(_, actual, item, request):
    same(cases.direct(item["model"]), request)
    same(cases.sql(item["model"], True), actual["sql"])
    return coordinate_seen(actual, item)


def required_forms(_, _tag):
    return {"shape/1", "shape/2", "shape/3", "packed", "integral-codec", "approximate",
            "native/probe-matched", "native/probe-refused", "native/probe-null",
            "native/event-update", "native/event-delete", "native/cross-kind-stored-match"}


def sample_forms(forms, actual, _, item):
    pairs = actual["mysqlAddForeignKeys"]["additions"][0]["pairs"]
    forms[f"shape/{len(pairs)}"] += 1
    for _, _, _, child, parent, *_ in pairs:
        family = cases.family(child)
        forms[f"family/{family}"] += 1
        form = "packed" if family == "packed" else "approximate" if family in {"float", "double"} else "integral-codec"
        forms[form] += 1
        forms[f"pair/{child[1]}-to-{parent[1]}"] += 1


def sample_native_forms(forms, actual, _, item):
    hit = False
    for effect, row, _ in actual["data"][1]:
        if effect[0] == "db-error":
            forms["native/probe-refused"] += 1
        elif all(value is None for value in row):
            forms["native/probe-null"] += 1
        else:
            hit = True
            forms["native/probe-matched"] += 1
    if hit and any(left[1] != right[1] for left, right in zip(item["model"]["child"], item["model"]["parent"])):
        forms["native/cross-kind-stored-match"] += 1
    for event, _, _, _, _ in actual["events"]:
        forms[f"native/event-{event}"] += 1


def observer_request(item, sql):
    model = item["model"]
    return {"sql": sql, "declarations": cases.declarations(model), "width": len(model["child"]),
            "inputs": values.measurements(model)["input"],
            **{f"{side}Hex": [field[0] == "string" or field[1] == "BIT" for field in model[side]] for side in ("child", "parent")}}


def observation(actual):
    return actual


def setup_notes(model):
    older = model["release"] in cases.LEGACY
    result = []
    for index, side in enumerate(("parent", "child")):
        if not older:
            authored = model["aliases"][1-index] if model["aliases"] else ""
            notes = [["Warning", 1287]] if authored == "YEAR(4)" else []
            for field in model[side]:
                if field[0] == "scalar":
                    _, kind, _, scale, unsigned, zerofill = field
                    if kind in {"FLOAT", "DOUBLE"} and scale is not None:
                        notes.append(["Warning", 1681])
                    if unsigned or zerofill:
                        notes.append(["Warning", 1681])
            result.append(notes)
        else:
            alias = model["aliases"][1-index] if model["aliases"] else ""
            result.append([["Warning", 1818]] if model["release"] == "mysql-5.6.51" and alias == "YEAR(2)" else [])
    return result


def expected(model):
    legacy = model["release"] in cases.LEGACY
    admitted = model["admitted"]
    children, parents = cases.columns(model, "x"), cases.columns(model, "p")
    before = [["PRIMARY", 1, "z", 0, "A"]]
    added = [[cases.SYMBOL, n + 1, name, 1, "A"] for n, name in enumerate(children)] if admitted else []
    edges = [[cases.SYMBOL, n + 1, name, cases.DB, cases.PARENT, target]
             for n, (name, target) in enumerate(zip(children, parents))] if admitted else []
    types = [[table, name, values.column_type(field, model["release"])]
             for table, role, names in ((cases.CHILD, "child", children), (cases.PARENT, "parent", parents))
             for name, field in zip(names, model[role])]
    checks = [[1, "+00:00"], [1, "+00:00"]]
    effect = ["executed"] if admitted else ["db-error", "HY000", 1215 if legacy else 3780]
    notes = [] if admitted else ([["Warning", 150]] if legacy else []) + [["Error", effect[2]]]
    rules = [[cases.SYMBOL, "CASCADE", "SET NULL", "NONE"]] if admitted else []
    data, events = [[], []], []
    if admitted:
        measured = values.measurements(model)
        data = [measured["data"][0], [entry + ([[["Error", 1452]]] if entry[0][0] == "db-error" else [[]])
                                     for entry in measured["data"][1]]]
        events = [entry + [[]] for entry in measured["events"]]
    return {"status": cases.STATUS, "effect": effect, "affected": 0 if admitted else None, "diagnostics": notes,
            "before": before, "after": sorted(before + added), "edges": edges, "rules": rules, "types": types, "profile": checks,
            "marker": 7, "active": False, "data": data, "events": events, "setupWarnings": setup_notes(model)}


def project_warnings(actual):
    result = deepcopy(actual)
    result["diagnostics"] = note_codes(actual["diagnostics"])
    result["setupWarnings"] = [note_codes(group) for group in actual["setupWarnings"]]
    result["data"][1] = [[effect, row, note_codes(notes)] for effect, row, notes in actual["data"][1]]
    result["events"] = [[event, effect, parent, child, note_codes(notes)] for event, effect, parent, child, notes in actual["events"]]
    return result


def check_observation(item, actual, sql):
    wanted = expected(item["model"])
    detail = {"expected": wanted, "observed": actual, "sql": sql}
    require(actual.get("status") == cases.STATUS, "mysql-foreign-key-scalar-unobserved", detail)
    require(project_warnings(actual) == wanted, "meaning-mismatch", detail)


def prepare(_, db):
    value = cases.scalar("DECIMAL", 9, 0)
    item = {"model": cases.model("mysql-9.1.0", [value], [value])}
    sql = cases.sql(item["model"])
    return [db.request(observer_request(item, changed)) for changed in (
        sql + "; SELECT 1", sql.replace(cases.CHILD, "other"), sql.replace(cases.PARENT, "other"))]


def check_preparation(_, _environment, observed):
    same([{"status": "outside-observer-scope"}] * 3, observed)


def controls():
    tag = "mysql-9.1.0"
    samples = {
        "compare-decimal-display": cases.model(tag, [cases.scalar("DECIMAL", 8, 2)], [cases.scalar("DECIMAL", 9, 2)]),
        "erase-time-precision": cases.model(tag, [cases.scalar("TIME", 1)], [cases.scalar("TIME", 2)]),
    }
    result = {}
    for defect, model in samples.items():
        item = {"model": model}
        sql = cases.sql(model)
        observed = expected(model)
        check_observation(item, observed, sql)
        mutated = deepcopy(observed)
        if defect == "compare-decimal-display":
            mutated["data"][1][0] = [values.SUCCESS, ["7.50"], []]
        else:
            mutated["events"][0][3][0][0] = "03:04:06"
        try:
            check_observation(item, mutated, sql)
        except Failure as error:
            same("meaning-mismatch", error.signal)
            result[defect] = error.detail
        else:
            raise Failure("mysql-foreign-key-scalar-control-missed", defect)
    return result


def faults(_, tag):
    path = "sql-semantics-mysql/src/Ir/Schema/ForeignKey/Add/ForeignKeyEffects.php"
    model = cases.model(tag, [cases.binary(4)], [cases.scalar("DECIMAL", 9, 0)])
    item = {"model": model}
    return [
        RenderFault("discard-scalar-update-cascade", path, "$this->update->value", "'NO ACTION'",
                    item, "meaning-mismatch", cases.direct(model)),
        RenderFault("discard-scalar-delete-null-effect", path, "$this->delete->value", "'NO ACTION'",
                    item, "meaning-mismatch", cases.direct(model)),
    ]


def check_fault_witness(_, witness):
    actual, wanted = witness["observed"], witness["expected"]
    same(["executed"], actual["effect"])
    same(wanted["data"], project_warnings(actual)["data"])
    require(wanted["rules"] != actual["rules"] and wanted["events"] != actual["events"],
            "mysql-foreign-key-scalar-fault-no-effect", witness)


if __name__ == "__main__":
    main(sys.modules[__name__])
