"""Compare full native string-key storage, index capacity and stored-row admission."""

from copy import deepcopy
import json
import sys

import mysql_foreign_key_string_cases as laws
from run import TOOL, Failure, digest, require
from run_server import same
from run_server_join import main, negative_count
from run_server_mysql_drop_index import DIAGNOSTIC, specifications as native_profiles
from sqlite_domain import RenderFault

LABEL = "mysql-foreign-key-strings"
OBSERVER = "mysql_foreign_key_string_observer.php"
NAME = "mysql-foreign-key-string-evidence-v1.json"
BASE = "f8e763f689a851c3cca878963d3a41e60b8921bfae5b676af0fda42e43b07e9d"
CONTRACT = "2b34e40c25a85120c62955a0fdeeb15b772bdb46c502609470ff5bc287391159"
COMPARATOR_FAULTS = {
    "Erase retained zero bytes after a successful native text comparison.": "ignore-exact-zero-bytes",
    "Treat a binary-collated case difference as equal.": "ignore-binary-collation",
}
BOUNDARY = {
    "collation": "Native foreign-key string fields need the same comparison collation.",
    "zero": "A native string key needs a nonzero field length within its storage family.",
    "term-capacity": "The full foreign-key field exceeds the native InnoDB index field limit.",
    "key-capacity": "The full foreign-key prefix exceeds the native InnoDB index byte limit.",
}


def specifications():
    law, fixed = TOOL / "mysql-native-foreign-string-fields-v3.json", TOOL / NAME
    require(digest(law) == BASE and digest(fixed) == CONTRACT, "mysql-foreign-key-string-contract")
    source, spec = json.loads(law.read_text()), json.loads(fixed.read_text())
    for name, expected in {**source["extends"], **source["revises"]}.items():
        require(digest(TOOL / name) == expected, "mysql-foreign-key-string-prior", name)
    _, profiles = native_profiles()
    same(source["language"]["releases"], spec["releases"])
    same(profiles["mysql"]["releases"], spec["releases"])
    return spec, profiles


def finite_ids(_, spec, tag):
    coordinates = laws.finite_coords(spec)
    same(spec["finiteIdentities"], len(coordinates))
    accepted = {point for point in coordinates if laws.coordinate(tag, point)["violation"] is None}
    same(spec["admitted"][tag], len(accepted))
    return accepted


def pair(axis, name, value):
    direct_axis = {"finite": "direct", "seed": "direct-seed", "authored": "direct-authored"}[axis]
    yield f"{axis}/{name}", laws.analyze(value), {"model": value}, None
    yield f"{direct_axis}/{name}", laws.direct(value), {"model": value}, None


def rejected(axis, name, value):
    for route, build in (("analysis", laws.analyze), ("direct", laws.direct)):
        ref = f"{axis}/{name}/{route}"
        yield f"negative/{ref}", build(value), {"model": value, "rejectionId": ref}, {
            "exception": DIAGNOSTIC, "message": BOUNDARY[value["violation"]],
        }


def plan(spec, tag):
    for point in sorted(laws.finite_coords(spec)):
        value = laws.coordinate(tag, point)
        name = "/".join(map(str, point))
        yield from pair("finite", name, value) if value["violation"] is None else rejected("finite", name, value)
    for seed in spec["exploration"]["seeds"]:
        for index, value in enumerate(laws.explored(tag, seed, spec["exploration"]["casesPerSeed"])):
            require(value["violation"] is None, "mysql-foreign-key-string-seed-ineligible", value)
            yield from pair("seed", f"{seed}/{index}", value)
    for name, value in laws.width_cases(tag):
        yield from pair("authored", name, value) if value["violation"] is None else rejected("authored", name, value)
    legal = laws.model(tag, [laws.storage("VARCHAR", 4, laws.COLLATIONS[0])], [laws.storage("CHAR", 4, laws.COLLATIONS[0])])
    for name, transform, reason in (
        ("unsettled-text-collation", lambda member: member.update(declarations=[
            sql.replace(" CHARACTER SET utf8mb4 COLLATE utf8mb4_bin", "") for sql in member["declarations"]]),
         "A native foreign-key pair needs compatible checked field domains."),
        ("wrong-native-rules", lambda member: member.update(complete=False),
         "Native foreign-key addition needs a complete catalog in the default InnoDB profile."),
    ):
        request = laws.analyze(legal)
        transform(request)
        yield f"negative/{name}", request, None, {"exception": DIAGNOSTIC, "message": reason}


def requests(engine, tag, spec):
    entries = list(plan(spec, tag))
    same(negative_count(spec, engine, tag), sum(boundary is not None for _, _, _, boundary in entries))
    yield from entries


def rejected_finite_ids(spec, tag):
    return {item["rejectionId"] for _, _, item, boundary in plan(spec, tag) if boundary is not None and item is not None}


def rejection_sql(item, _):
    return laws.sql(item["model"], True)


def observe_rejection(_, item, actual, sql):
    require(item["model"]["violation"] is not None, "mysql-foreign-key-string-predicted-admitted")
    check_observation(item, actual, sql)
    return item["rejectionId"]


def check_value(item, actual):
    model = item["model"]
    require(actual.get("status") == "constructed", "mysql-foreign-key-string-not-constructed", actual)
    same(model["release"] + laws.PROFILE, actual["profile"])
    same(laws.direct(model)["mysqlAddForeignKeys"], actual["mysqlAddForeignKeys"])


def coordinate_seen(actual):
    left, right = actual["mysqlAddForeignKeys"]["additions"][0]["pairs"][0][3:5]
    return ("binary", left[0], right[0], left[1], right[1]) if left[2] is None else ("character", left[0], right[0], left[2], right[2])


def analysis_finite_id(_, actual, item, sql):
    same(laws.sql(item["model"], True), sql)
    return coordinate_seen(actual)


def direct_finite_id(_, actual, item, request):
    same(laws.direct(item["model"]), request)
    return coordinate_seen(actual)


def required_forms(_, tag):
    result = {*(f"child/{kind}" for kind in (*laws.TEXT, *laws.BINARY)), *(f"parent/{kind}" for kind in (*laws.TEXT, *laws.BINARY)),
              *(f"collation/{label}" for label in laws.COLLATIONS), "shape/1", "shape/2", "shape/3",
              "format/Compact", "bytes/with-zero-padding", "bytes/unpadded", "probe/accepted", "probe/refused", "event/update", "event/delete"}
    if tag != "mysql-5.6.51":
        result.add("format/Dynamic")
    return result


def sample_forms(forms, actual, _, item):
    pairs = actual["mysqlAddForeignKeys"]["additions"][0]["pairs"]
    forms[f"shape/{len(pairs)}"] += 1
    for _, _, _, child, parent, *_ in pairs:
        forms[f"child/{child[0]}"] += 1
        forms[f"parent/{parent[0]}"] += 1
        if child[2] is not None:
            forms[f"collation/{child[2]}"] += 1


def sample_native_forms(forms, actual, _, item):
    forms[f"format/{actual['format']}"] += 1
    for role, *_ in actual["events"]:
        forms[f"event/{role}"] += 1
    for effect, row in actual["data"][1]:
        ok = effect[0] == "executed"
        forms["probe/accepted" if ok else "probe/refused"] += 1
        if ok:
            for field in row:
                if field is not None:
                    forms["bytes/with-zero-padding" if field.endswith("00") else "bytes/unpadded"] += 1


def observer_request(item, sql):
    model = item["model"]
    return {"sql": sql,  "declarations": laws.declarations(model), "width": len(model["child"]), "matching": laws.matching_input(model)}


def observation(actual):
    return actual


def refusal(model):
    reason, legacy = model["violation"], model["release"] in laws.LEGACY
    if reason == "collation":
        return ["db-error", "HY000", 1215 if legacy else 3780]
    if reason == "zero":
        return ["db-error", "42000", 1167]
    if reason == "term-capacity":
        return ["db-error", "HY000", 1215 if legacy else 1821]
    if reason == "key-capacity":
        return ["db-error", "42000", 1071]
    return ["executed"]


def ddl_notes(model, effect):
    legacy = model["release"] in laws.LEGACY
    limited = model["violation"] == "term-capacity"
    legacy_compaction = model["release"] == "mysql-5.6.51" and model["layout"] == "dynamic"
    oversized_terms = sum(laws.full_width(term) > model["nativeLimit"] for term in model["child"]) if limited else 0
    notes = [["Warning", 1071]] * oversized_terms
    if legacy_compaction:
        notes += [["Warning", 1478], ["Warning", 1478]]
    if legacy and model["violation"] in {"term-capacity", "collation"}:
        notes += [["Warning", 150]]
    return notes + ([] if effect == ["executed"] else [["Error", effect[2]]])


def probe_notes(model, expected):
    if model["violation"]:
        return []
    result = []
    for datum, (effect, _) in zip(laws.TEST_HEX, expected["data"][1]):
        truncated = sum(len(datum) // 2 > term[1] for term in model["child"]) if datum is not None else 0
        result.append([["Warning", 1265]] * truncated + ([["Error", 1452]] if effect[0] == "db-error" else []))
    return result


def note_codes(notes):
    return [note[:2] for note in notes]


def check_observation(item, actual, sql):
    model = item["model"]
    effect = refusal(model)
    compact = model["release"] == "mysql-5.6.51" and model["layout"] == "dynamic"
    expected = {**laws.expected(model), "effect": effect, "affected": 0 if effect == ["executed"] else None, "marker": 7, "active": False}
    witness = {"expected": expected, "observed": actual, "sql": sql}
    require(actual.get("status") == laws.STATUS, "mysql-foreign-key-string-unobserved", witness)
    require({key: actual.get(key) for key in expected} == expected, "meaning-mismatch", witness)
    require(note_codes(actual["diagnostics"]) == ddl_notes(model, effect), "meaning-mismatch", witness)
    require([note_codes(raw) for raw in actual["setupWarnings"]] == [[], [["Warning",1478]]*2 if compact else []], "meaning-mismatch", witness)
    require([note_codes(raw) for raw in actual["probeWarnings"]] == probe_notes(model, expected), "meaning-mismatch", witness)


def prepare(_, db):
    model = laws.model("mysql-9.1.0",[laws.storage("BINARY",4)],[laws.storage("VARBINARY",4)])
    text = laws.sql(model)
    item = {"model": model}
    return [db.request(observer_request(item, sql)) for sql in (text + "; SELECT 1", text.replace(laws.CHILD,"other"), text.replace(laws.PARENT,"other"))]


def check_preparation(_, _environment, facts):
    same([{"status": "outside-observer-scope"}]*3, facts)


def controls():
    result = {}
    for witness, collation in (("ignore-exact-zero-bytes", "utf8mb4_unicode_ci"), ("ignore-binary-collation", "utf8mb4_bin")):
        storage = laws.storage("VARCHAR", 8, collation)
        model = laws.model("mysql-9.1.0", [storage], [storage])
        expected = laws.expected(model)
        observed = {**expected, "effect": ["executed"], "marker": 7, "active": False, "affected": 0, "diagnostics": [],
                    "setupWarnings": [[], []], "probeWarnings": [list(note + ["probe"] for note in notes) for notes in probe_notes(model, expected)]}
        item, sql = {"model": model}, laws.sql(model)
        check_observation(item, observed, sql)
        altered = deepcopy(observed)
        if witness == "ignore-exact-zero-bytes":
            altered["data"][1][3][1] = ["4162"]
        else:
            altered["data"][1][2] = [["executed",1],["6162"]]
        try:
            check_observation(item, altered, sql)
        except Failure as failure:
            same("meaning-mismatch", failure.signal)
            result[witness] = failure.detail
        else:
            raise Failure("mysql-foreign-key-string-control-missed", witness)
    return result


def faults(_, tag):
    path = "sql-semantics-mysql/src/Ir/Schema/ForeignKey/Add/ForeignKeyEffects.php"
    item = laws.model(tag, [laws.storage("VARCHAR",8,laws.COLLATIONS[2])], [laws.storage("CHAR",4,laws.COLLATIONS[2])])
    return [
        RenderFault("discard-string-update-cascade", path, "$this->update->value", "'NO ACTION'",
                    {"model": item}, "meaning-mismatch", laws.direct(item)),
        RenderFault("discard-string-delete-null-effect", path, "$this->delete->value", "'NO ACTION'",
                    {"model": item}, "meaning-mismatch", laws.direct(item)),
    ]


def check_fault_witness(item, witness):
    expected, actual = witness["expected"], witness["observed"]
    same(["executed"], actual["effect"])
    same(expected["data"], actual["data"])
    require(expected["rules"] != actual["rules"] and expected["events"] != actual["events"],
            "mysql-foreign-key-string-fault-no-declaration-effect", witness)


if __name__ == "__main__":
    main(sys.modules[__name__])
