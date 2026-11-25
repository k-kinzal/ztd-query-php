"""Compare MySQL ordered simultaneous index renames with native catalog effects."""

from copy import deepcopy
import json
from itertools import product
import sys

import run_server_mysql_drop_index as baseline
from mysql_alter_index_cases import GRAMMAR_ERROR, INVALID, LEXICAL, SEMANTIC
from mysql_drop_index_cases import LIFETIMES
from mysql_index_policy_cases import refusal
from mysql_index_rename_cases import (
    STATUS, TOPOLOGIES, analyze, coordinate, declarations, explored, finite_points, native_rows, operation,
    prediction, sql, topology_seen,
)
from mysql_index_auto_increment_cases import native_key
from run_server_mysql_create_btree_index import check_validation_contract
from run import Failure, TOOL, digest, require
from run_server import same
from run_server_join import main, negative_count
from sqlite_domain import RenderFault

LABEL = "mysql-index-rename"
OBSERVER = "mysql_index_rename_observer.php"
NAME = "mysql-index-renames-evidence-v2.json"
BASE = "6942384ad796566a7e0100365126c4d60091b0e4647d08a8e10f0d165c55f554"
CONTRACT = "c0f6530d90d23d9fbdbac1ce380006c21d06d034994d9dce10c4648c1f5881c7"
STATISTICS = "a00330675b2d716b960e4d440ecb59a72a2110dd6edf4ad2474d317e91ff3d4f"
PROFILE = "/utf8mb4/lower-case-table-names=0/sql-mode=empty/native/innodb/rename-indexes"
LEGACY = "mysql-5.7.44"
NATIVE = {
    "missing": ["db-error", "42000", 1176], "duplicate": ["db-error", "42000", 1061],
    "invalid-name": ["db-error", "42000", 1280], "name-length": ["db-error", "42000", 1059],
    "table-qualifier": ["db-error", "42000", 1103], "schema-qualifier": ["db-error", "42000", 1102],
}
MESSAGES = {
    "reused": "Each source must name a distinct original native index.",
    "destination": "The resulting native index names must be distinct.",
    "primary": "A native index rename cannot name the physical primary key.",
    "name": "An index name has 1 to 64 characters and no trailing space.",
    "policy": "The algorithm and lock cannot rename indexes on this native InnoDB table.",
}
COMPARATOR_FAULTS = {"Ignore the ordered native statistics warnings.": "omitted-native-statistics-warning"}


def specifications():
    base, record = TOOL / "mysql-index-renames-v1.json", TOOL / NAME
    require(digest(base) == BASE and digest(record) == CONTRACT
            and digest(TOOL / "mysql-index-rename-statistics-v1.json") == STATISTICS, "mysql-index-rename-contract")
    spec, fixed = json.loads(record.read_text()), json.loads(base.read_text())
    check_validation_contract(spec)
    same(fixed["finite"], spec["finite"])
    same(fixed["exploration"], spec["exploration"])
    profiles = baseline.specifications()[1]
    same(profiles["mysql"]["releases"], [*spec["legacyRefusals"], *spec["releases"]])
    return spec, {"mysql": {**profiles["mysql"], "releases": spec["releases"]}}


def finite_ids(_, spec, _tag):
    points = finite_points(spec["finite"])
    same(spec["finiteLayouts"], len(points))
    return points


def planned(model, point=None, *, error=None, native_id=None):
    return {"model": model, "point": list(point) if point else None, "error": error, "nativeId": native_id}


def both(group, ident, model, point=None):
    item = planned(model, point)
    yield f"{group}/{ident}", analyze(model), item, None
    route = {"finite": "direct", "seed": "direct-seed"}.get(group, group + "-direct")
    yield f"{route}/{ident}", model, item, None


def defaults(tag, life="persistent", *, add_primary=False):
    keys = [native_key("origin", "ordinary", ["a", "c"]), native_key("alternate", "ordinary", ["b"]),
            native_key("unmoved", "ordinary", ["c"])]
    if add_primary:
        keys.insert(0, native_key("PRIMARY", "primary", ["a"]))
    return operation(tag, life, keys, [("origin", "new_name")])


def edges(tag):
    for life in LIFETIMES:
        for requested in ("x", "É" * 64, "a " + chr(96) + " quoted", "origin", "Origin"):
            item = defaults(tag, life)
            item["mysqlIndexRenames"]["moves"][0][1] = item["written"][0][1] = requested
            yield f"name/{life}/{requested}", item
        keys = [native_key("first_unique", "clustered-unique", ["b"]), native_key("generating", "ordinary", ["a", "c"])]
        gen = operation(tag, life, keys, [("first_unique", "fresh_cluster"), ("generating", "fresh_identity")], generated=True,
                        policy=("INPLACE", "NONE") if life == "persistent" else ("COPY", "NONE"))
        yield f"generation/{life}", gen
    if tag == LEGACY:
        item = defaults(tag)
        item["qualify"] = True
        yield "legacy-alias-qualification", item


def options(tag):
    for life, algorithm, lock in product(LIFETIMES, ("DEFAULT", "INPLACE", "COPY"), ("DEFAULT", "NONE", "SHARED", "EXCLUSIVE")):
        item = defaults(tag, life)
        item["mysqlIndexRenames"]["policy"] = [algorithm, lock]
        yield f"{life}/{algorithm}/{lock}", item, refusal(tag, "drop-ordinary", life, algorithm, lock)


def invalid_native(tag):
    for name, origin, destination, code, message in (
        ("absent-source", "absent", "fresh", "missing", "requires one resolved ordinary index"),
        ("occupied-destination", "origin", "Unmoved", "duplicate", MESSAGES["destination"]),
        ("physical-primary", "PRIMARY", "fresh", "invalid-name", MESSAGES["primary"]),
        ("destination-primary", "origin", "PRİMARY", "invalid-name", MESSAGES["primary"]),
        ("long-destination", "origin", "É" * 65, "name-length", MESSAGES["name"]),
        ("trailing-space", "origin", "bad name ", "invalid-name", MESSAGES["name"]),
    ):
        model = defaults(tag, add_primary=name == "physical-primary")
        model["written"][0] = [origin, destination]
        yield name, model, sql(model, True), NATIVE[code], INVALID, message

    for name, source, destination, expected in (
        ("later-source-uses-initial", "new_name", "fresh", "requires one resolved ordinary index"),
        ("original-source-twice", "ORIGIN", "fresh", MESSAGES["reused"]),
        ("duplicate-result", "alternate", "NEW_NAME", MESSAGES["destination"]),
    ):
        model = defaults(tag)
        model["written"].append([source, destination])
        code = "duplicate" if name == "duplicate-result" else "missing"
        yield name, model, sql(model, True), NATIVE[code], INVALID, expected

    model = defaults(tag)
    model["qualify"] = True
    if tag == LEGACY:
        valid = sql(model, True)
        for name, search, substitution, code, message in (
            ("wrong-schema", "ir_fixture", "other_schema", "schema-qualifier", "different owning database"),
            ("wrong-table", "ir_index_set", "other_table", "table-qualifier", "different owning table"),
        ):
            prefix, clause = valid.split("RENAME", 1)
            supplied = prefix + "RENAME" + clause.replace(search, substitution, 1)
            yield name, model, supplied, NATIVE[code], INVALID, message
    else:
        yield "legacy-qualifier-unavailable", model, sql(model, True), GRAMMAR_ERROR, LEXICAL, "Unexpected '.'"

    model = defaults(tag)
    if tag == LEGACY:
        error, exception, msg = ["db-error", "HY000", 1800], SEMANTIC, "INSTANT is not an ALGORITHM"
    else:
        error, exception, msg = ["db-error", "0A000", 1845], INVALID, "accept DEFAULT, INPLACE or COPY"
    yield "instant-unavailable", model, sql(model, True) + ", ALGORITHM=INSTANT", error, exception, msg


def invalid_direct(tag):
    good = defaults(tag)
    for name, field, invalid, message in (
        ("empty-list", "moves", [], "nonempty ordered move list"),
        ("empty-source", "moves", [["", "fresh"]], MESSAGES["name"]),
        ("empty-destination", "moves", [["origin", ""]], MESSAGES["name"]),
        ("source-in-unmoved", "unmoved", ["origin"], MESSAGES["reused"]),
        ("duplicate-unmoved", "unmoved", ["Étude", "éTUDE"], "unmoved native index names must be distinct"),
        ("empty-unmoved-name", "unmoved", [""], "unmoved native index names must be distinct"),
        ("misnamed-primary", "unmoved", ["Prİmary"], "Only the native PRIMARY name"),
        ("source-is-primary", "moves", [["Prİmary", "fresh"]], MESSAGES["primary"]),
        ("native-key-capacity", "unmoved", [f"untouched_{n}" for n in range(64)], "at most 64 physical keys"),
    ):
        item = deepcopy(good)
        item["mysqlIndexRenames"][field] = invalid
        yield name, item, message


def requests(engine, tag, spec):
    for point in sorted(finite_ids(engine, spec, tag)):
        yield from both("finite", "/".join(map(str, point)), coordinate(tag, point), point)
    for seed in spec["exploration"]["seeds"]:
        for ordinal, item in enumerate(explored(tag, seed, spec["exploration"]["casesPerSeed"])):
            yield from both("seed", f"{seed}/{ordinal}", item)
    for name, model in edges(tag):
        yield from both("edge", name, model)
    rejected = 0
    for name, model, error in options(tag):
        if error is None:
            yield from both("policy", name, model)
            continue
        boundary = {"exception": INVALID, "message": MESSAGES["policy"]}
        yield "negative/analysis/policy/" + name, analyze(model), planned(model, error=error, native_id="policy/"+name), boundary
        yield "negative/direct/policy/" + name, model, None, boundary
        rejected += 2
    for name, model, command, error, exception, message in invalid_native(tag):
        yield "negative/analysis/" + name, {**analyze(model), "sql": command}, planned(model, error=error, native_id=name), {
            "exception": exception, "message": message,
        }
        rejected += 1
    for name, model, message in invalid_direct(tag):
        yield "negative/direct/" + name, model, None, {"exception": INVALID, "message": message}
        rejected += 1
    same(negative_count(spec, engine, tag), rejected)


def check_value(item, response):
    model = item["model"]
    require(response["status"] == "constructed", "mysql-index-rename-constructed", response)
    same(model["release"] + PROFILE, response["profile"])
    change = model["mysqlIndexRenames"]
    same({field: change[field] for field in ("owner", "moves", "policy")}, response["mysqlIndexRenames"])


def seen(response, item):
    shape = response["mysqlIndexRenames"]
    model = item["model"]
    same("temporary-table" if model["life"] != "persistent" else "table", shape["owner"][2])
    body = shape["moves"]
    positions = {entry["name"]: index for index, entry in enumerate(model["keys"])}
    first, last = body[0][0], body[-1][0]
    reverse = "reverse" in first if len(body) == 1 else positions[first] > positions[last]
    return model["life"], len(body), topology_seen(body), "reverse" if reverse else "forward"


def analysis_finite_id(_, response, item, written):
    same(sql(item["model"], True), written)
    return seen(response, item)


def direct_finite_id(_, response, item, request):
    same(item["model"], request)
    return seen(response, item)


def rejected_finite_ids(_, tag):
    return {*(name for name, *_ in invalid_native(tag)), *("policy/" + name for name, _, error in options(tag) if error is not None)}


def observer_request(item, sql):
    model = item["model"]
    return {"sql": sql, "declarations": declarations(model), "generated": model["declaration"] != "plain",
            "rows": {"temporary": native_rows(model, True), "persistent": native_rows(model, False)}}


def observation(raw):
    return raw


def statistics_warnings(model):
    shape = model["mysqlIndexRenames"]
    if model["life"] != "persistent" or shape["policy"][0] == "COPY":
        return []
    stats = {key["name"] for key in model["keys"]}
    warnings = []
    for source, destination in shape["moves"]:
        if source == destination:
            continue
        if destination in stats:
            warnings.append(["Warning", 1025, f"Error renaming an index of table 'ir_index_set' from '{source}' to '{destination}' "
                             "in InnoDB persistent statistics storage: Duplicate key"])
        else:
            stats.remove(source)
            stats.add(destination)
    return warnings


def check_observation(item, actual, sql):
    model, error = item["model"], item["error"]
    expected = prediction(model, error)
    witness = {"expected": expected, "observed": actual, "sql": sql}
    require(actual.get("status") == STATUS, "mysql-index-rename-observer-scope", witness)
    require({key: actual[key] for key in expected} == expected, "meaning-mismatch", witness)
    if error:
        require(any(note[1] == error[2] for note in actual["diagnostics"]), "meaning-mismatch", witness)
    else:
        require(type(actual["affected"]) is int and actual["diagnostics"] == statistics_warnings(model), "meaning-mismatch", witness)


def observe_rejection(_, item, actual, sql):
    check_observation(item, actual, sql)
    return item["nativeId"]


def required_forms(_, tag):
    visibility = ("visibility/False",) if tag != LEGACY else ()
    return {*(f"namespace/{life}" for life in LIFETIMES), *(f"topology/{form}" for form in TOPOLOGIES),
            "generated/False", "generated/True", "width/narrow", "width/wide", *visibility}


def sample_forms(forms, response, _, item):
    model, shape = item["model"], response["mysqlIndexRenames"]
    roles = (f"namespace/{model['life']}", f"topology/{topology_seen(shape['moves'])}",
             f"generated/{model['declaration'] != 'plain'}", "width/wide" if len(shape["moves"]) >= 8 else "width/narrow")
    forms.update(roles)


def sample_native_forms(forms, actual, _, item):
    for key in actual["after"]["indexes"]:
        forms[f"visibility/{key[7]}"] += 1
        forms[f"direction/{key[4]}"] += 1


def outsiders():
    return ["ALTER TABLE other RENAME INDEX a TO b", "ALTER TABLE ir_fixture.ir_index_set DROP INDEX a",
            sql(defaults("mysql-8.0.44")) + "; SELECT 1"]


def prepare(_, db):
    item = planned(defaults("mysql-8.0.44"))
    return {"outside": [db.request(observer_request(item, command)) for command in outsiders()]}


def check_preparation(_, _environment, receipt):
    same({"outside": [{"status": "outside-observer-scope"}] * 3}, receipt)


def controls():
    model = coordinate("mysql-8.0.44", ("persistent", 3, "closed-cycle", "forward"))
    item = planned(model)
    actual = {**prediction(model, None), "affected": 0, "diagnostics": statistics_warnings(model)}
    check_observation(item, actual, sql(model))
    wrong = {**actual, "diagnostics": actual["diagnostics"][1:]}
    try:
        check_observation(item, wrong, sql(model))
    except Failure as error:
        same("meaning-mismatch", error.signal)
        return {"omitted-native-statistics-warning": error.detail}
    raise Failure("mysql-index-rename-comparator-control")


def check_fault_witness(item, verdict):
    same(prediction(item["model"], None), verdict["expected"])
    same(["executed"], verdict["observed"]["effect"])


def faults(_, tag):
    item = coordinate(tag, ("temporary-shadow", 3, "fresh", "forward"))
    path = "sql-semantics-mysql/src/Ir/Schema/Index/Rename/RenameIndexes.php"
    return [RenderFault("omit-initial-index-rename", path, "implode(', ', $actions)", "implode(', ', array_slice($actions, 1))",
                        planned(item), "meaning-mismatch", item)]


if __name__ == "__main__":
    main(sys.modules[__name__])
