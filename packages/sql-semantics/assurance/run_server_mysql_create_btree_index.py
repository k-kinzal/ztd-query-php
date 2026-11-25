"""Verify checked native signed B-tree CREATE INDEX statements."""

from copy import deepcopy
from itertools import product
import json
import sys

from mysql_create_btree_index_cases import (
    FIELD_NAMES, IDENTITY, LAYOUTS, LIFETIMES, OLD, STATUS,
    analyze, command, coordinate, coordinates, definition, generated, native_edges, prediction,
)
from run import Failure, TOOL, digest, require
from run_server import same
from run_server_join import main, negative_count
from run_server_mysql_create_varchar import DIAGNOSTIC, specifications as native_profiles
from sqlite_domain import RenderFault

LABEL = "mysql-create-btree-index"
NAME = "mysql-create-btree-index-evidence-v3.json"
CONTRACT = "f52e95fb78702ed2f66c761db4990fd7feb9f59d21fc299c7a10a1e0b7df0d05"
BASE = "022cb79259ca6314c358643120765737c90f1baa96d8dc7a926b77e6463df830"
CASE_CONTRACT = "a1c9a4243a939dc8d13a1971277bc84f5b91e5366ef1c5a96ab41c21eb34a307"
PREVIOUS = "1ce5c77c55d8a79bebcee8b6a80c20d2b7522822a97d99a6f7bc9cc25811dcf7"
IDENTITY_CONTRACT = "2d274610477ef7ac302bd4006949f4f9df2d4259379740a29f0e5c215fa325c0"
OBSERVER = "mysql_btree_index_observer.php"
PROFILE = "/utf8mb4/lower-case-table-names=0/sql-mode=empty/native/innodb/btree-index"
SEMANTIC = "SqlSemantics\\Platform\\MySql\\Model\\Diagnostic\\SemanticException"
COMPARATOR_FAULTS = {"Ignore a table of contents with a wrong ordered part.": "wrong-index-part"}


def specifications():
    path = TOOL / NAME
    require(digest(path) == CONTRACT and digest(TOOL / "mysql-create-btree-index-v1.json") == BASE
            and digest(TOOL / "mysql-btree-identity-visibility-v1.json") == IDENTITY_CONTRACT
            and digest(TOOL / "mysql-native-index-case-v1.json") == CASE_CONTRACT
            and digest(TOOL / "mysql-create-btree-index-evidence-v1.json") == PREVIOUS,
            "mysql-create-btree-index-contract")
    spec = json.loads(path.read_text())
    profiles = native_profiles()[1]
    same(spec["releases"], profiles["mysql"]["releases"])
    same([], spec["exploration"]["allowedExclusions"])
    return spec, profiles


def grid(spec, tag):
    finite = spec["finite"]
    visibility = finite["visibility"]["legacy" if tag in OLD else "modern"]
    return product(finite["lifetimes"], finite["keys"], finite["directions"], finite["unique"], visibility)


def finite_ids(_, spec, tag):
    points = coordinates(tag)
    require(points == set(grid(spec, tag)) and len(points) == spec["finite"]["identities"]["legacy" if tag in OLD else "modern"],
            "mysql-btree-index-grid")
    return points


def expected_point(response, item):
    checked = response["mysqlCreateIndex"]
    source = checked["target"]
    kind = source.get("relationKind", "table")
    temporary = kind == "temporary-table"
    same(item["model"]["lifetime"] != "persistent", temporary)
    layout = tuple(int(source["columns"][slot][1:]) for slot, _ in checked["columns"])
    role = next(label for label, expected in LAYOUTS.items() if expected == layout)
    orientation = {"ix_a": "ascending", "ix_d": "descending"}[checked["name"]]
    same([orientation == "descending" and item["model"]["release"] not in OLD] * len(layout),
         [direction for _, direction in checked["columns"]])
    return item["model"]["lifetime"], role, orientation, checked["unique"], "visible" if checked["visible"] else "invisible"


def analysis_finite_id(_, response, item, sql):
    same(command(item["model"], original=True), sql)
    return expected_point(response, item)


def direct_finite_id(_, response, item, request):
    same(item["model"], request)
    return expected_point(response, item)


def case_pair(route, id_, item, sql=None):
    wrapped = {"model": item, "id": id_}
    direct = {"finite": "direct", "seed": "direct-seed"}.get(route, route + "-direct")
    yield f"{route}/{id_}", analyze(item, sql=sql), wrapped, None
    yield f"{direct}/{id_}", item, wrapped, None


def negative_cases(tag):
    base = definition(tag, "persistent", [0], [False], False, True, "ix")
    scope = deepcopy(base)
    scope["mysqlCreateIndex"]["columns"] = [[1, False]]
    yield "out-of-owner", scope, None, DIAGNOSTIC, "select a declared signed column"
    for label, parts in (("empty", []), ("sparse", {"1": [0, False]}), ("foreign", ["unknown-value"])):
        item = deepcopy(base)
        item["mysqlCreateIndex"]["columns"] = parts
        yield label, item, None, DIAGNOSTIC, "nonempty ordered list of index parts"
    case_columns = deepcopy(base)
    case_columns["mysqlCreateIndex"]["target"]["columns"] = ["Élan", "élan"]
    case_columns["mysqlCreateIndex"]["target"]["columnTypes"] = ["int", "int"]
    case_columns["mysqlCreateIndex"]["columns"] = [[0, False], [1, False]]
    yield "same-native-column", case_columns, None, DIAGNOSTIC, "cannot repeat a key column"
    not_innodb = analyze(base)
    not_innodb["declarations"] = [s.replace("ENGINE=InnoDB", "ENGINE=MyISAM") for s in not_innodb["declarations"]]
    yield "non-innodb", not_innodb, None, DIAGNOSTIC, "resolved ordinary InnoDB declaration"
    view = deepcopy(base)
    view["mysqlCreateIndex"]["target"]["relationKind"] = "view"
    yield "view", view, None, DIAGNOSTIC, "persistent or temporary InnoDB table"

    unknown = analyze(base, command(base).replace("`f0`", "`unknown`"))
    yield "unknown-field", unknown, (base, ["db-error", "42000", 1072]), SEMANTIC, "Key column unknown"

    repeated = definition(tag, "persistent", [0], [False, False], False, True, "repeat_ix")
    repeated["mysqlCreateIndex"]["columns"] = [[0, False], [0, False]]
    excess = definition(tag, "persistent", list(range(17)), [False] * 17, False, True, "large_ix")
    reserved = definition(tag, "persistent", [0], [False], False, True, "pRiMaRy")
    dotted = definition(tag, "persistent", [0], [False], False, True, "PRİMARY")
    large_name = definition(tag, "persistent", [0], [False], False, True, "界" * 65)
    both = [
        ("repeat", repeated, ["db-error", "42S21", 1060], "cannot repeat a key column"),
        ("arity", excess, ["db-error", "42000", 1070], "at most 16 key parts"),
        ("primary", reserved, ["db-error", "42000", 1280], "other than PRIMARY"),
        ("dotted-primary", dotted, ["db-error", "42000", 1280], "other than PRIMARY"),
        ("long-name", large_name, ["db-error", "42000", 1059], "1 to 64 characters"),
    ]
    occupied = definition(tag, "persistent", [0], [False], False, True, "Occupied_IX", data="known-occupied")
    both.append(("occupied-native-name", occupied, ["db-error", "42000", 1061], "unoccupied native index name"))
    if tag in OLD:
        invisible = definition(tag, "persistent", [0], [False], False, False, "old_hidden")
        yield "legacy-invisible", invisible, None, DIAGNOSTIC, "no invisible index role"
        descending = deepcopy(base)
        descending["mysqlCreateIndex"]["columns"] = [[0, True]]
        yield "legacy-descending", descending, None, DIAGNOSTIC, "does not store descending index parts"
    else:
        for identity in ("none", "nullable"):
            invis = definition(tag,"persistent",[0],[False],True,False,"hidden_ix",identity=identity)
            both.append((f"implicit-{identity}", invis, ["db-error", "HY000", 3522], IDENTITY))
    for name, model, effect, reason in both:
        yield name + "/direct", model, None, DIAGNOSTIC, reason
        yield name + "/analysis", analyze(model), (model, effect), DIAGNOSTIC, reason


def requests(engine, tag, spec):
    for point in grid(spec, tag):
        id_ = "/".join(map(str, point))
        yield from case_pair("finite", id_, coordinate(tag, point))
    for name, item in native_edges(tag):
        yield from case_pair("edge", name, item)
    example = definition(tag, "temporary-shadow", [17, 2], [True, False], False, True, "qualified_ix")
    yield from case_pair("edge", "unqualified-shadow", example, command(example, original=True, qualified=False, algorithm="before"))
    yield from case_pair("edge", "btree-after", example, command(example, original=True, algorithm="after"))
    if tag not in OLD:
        hidden = definition(tag, "persistent", [2], [False], False, False, "last_visibility")
        yield from case_pair("edge", "last-effective-visibility", hidden, command(hidden, original=True, spelling=" VISIBLE INVISIBLE"))
    for seed in spec["exploration"]["seeds"]:
        for ordinal, model in enumerate(generated(tag, seed, spec["exploration"]["casesPerSeed"])):
            yield from case_pair("seed", f"{seed}/{ordinal}", model)
    all_negatives = list(negative_cases(tag))
    same(negative_count(spec, engine, tag), len(all_negatives))
    for name, request, native, exception, message in all_negatives:
        context = None if native is None else {"model": native[0], "nativeError": native[1], "id": name}
        yield f"negative/{name}", request, context, {"exception":exception,"message":message}


def rejected_finite_ids(_, tag):
    base = {"unknown-field", "repeat/analysis", "arity/analysis", "primary/analysis", "dotted-primary/analysis", "long-name/analysis", "occupied-native-name/analysis"}
    return base if tag in OLD else base | {"implicit-none/analysis", "implicit-nullable/analysis"}


def check_value(item, response):
    shape = item.get("model", item)
    require(response["status"] == "constructed", "mysql-btree-index-construction", response)
    same(shape["release"] + PROFILE, response["profile"])
    same(shape["mysqlCreateIndex"], response["mysqlCreateIndex"])


def observer_request(item, sql):
    shape = item.get("model", item)
    data = "occupied" if shape["data"] in ("known-occupied", "accent-distinct") else shape["data"]
    return {"sql": sql, "lifetime": shape["lifetime"], "identity": shape["identity"], "data": data,
            "probe": not item.get("nativeError")}


def observation(raw):
    return raw


def check_observation(item, actual, sql):
    shape = item.get("model", item)
    expected = prediction(shape, item.get("nativeError"))
    witness = {"expected": expected, "observed": actual, "sql": sql}
    require(actual["status"] == STATUS, "mysql-btree-index-observer-scope", witness)
    require({key: actual[key] for key in expected} == expected, "meaning-mismatch", witness)
    if actual["effect"][0] == "executed":
        require(type(actual["affected"]) is int and actual["affected"] >= 0 and actual["diagnostics"] == [],
                "meaning-mismatch", witness)
    else:
        require(actual["affected"] is None and any(note[1] == actual["effect"][2] for note in actual["diagnostics"]),
                "meaning-mismatch", witness)


def observe_rejection(_, item, actual, sql):
    check_observation(item, actual, sql)
    return item["id"]


def sample_forms(forms, response, _, item):
    index, shape = response["mysqlCreateIndex"], item["model"]
    forms[f"scope/{shape['lifetime']}"] += 1
    forms[f"unique/{index['unique']}"] += 1
    forms[f"visible/{index['visible']}"] += 1
    forms[f"wide/{len(index['columns']) > 8}"] += 1
    for _, direction in index["columns"]:
        forms[f"descending/{direction}"] += 1
    for field in index["target"]["columnTypes"]:
        forms[f"type/{field}"] += 1


def required_forms(_, tag):
    return {*(f"scope/{kind}" for kind in LIFETIMES), "type/int", "type/int-not-null", "visible/True", "descending/False",
            *(f"{axis}/{flag}" for axis in ("unique","wide") for flag in (True,False)),
            *([] if tag in OLD else ["visible/False","descending/True"])}


def outsiders():
    return ("DROP TABLE ir_btree_subject", "CREATE INDEX `i` ON `elsewhere` (`f0`)",
            "CREATE INDEX `i` ON `ir_btree_subject` (`f0`); SELECT 1")


def prepare(_, db):
    model = definition("mysql-5.6.51", "persistent", [0], [False], False, True, "ix")
    return {"outside": [db.request(observer_request(model, sql)) for sql in outsiders()]}


def check_preparation(_, _environment, receipt):
    same([{"status":"outside-observer-scope"} for _ in outsiders()], receipt["outside"])


def witness(tag):
    return definition(tag, "temporary-shadow", [2, 0], [False, True], True, True, "ix_fault")


def controls():
    model = witness("mysql-9.1.0")
    good = {**prediction(model), "affected":0,"diagnostics":[]}
    check_observation(model, good, command(model))
    changed = deepcopy(good)
    key = next(part for part in changed["after"]["indexes"] if part[0] == "ix_fault")
    key[3] = "f17"
    try:
        check_observation(model, changed, command(model))
    except Failure as failure:
        same("meaning-mismatch", failure.signal)
        return {"wrong-index-part": failure.detail}
    raise Failure("mysql-btree-index-ignored-wrong-key")


def check_fault_witness(item, cause):
    same(prediction(item), cause["expected"])
    same(STATUS, cause["observed"]["status"])
    same(["executed"], cause["observed"]["effect"])


def faults(_, tag):
    item = witness(tag)
    path = "sql-semantics-mysql/src/Ir/Schema/Index/CreateIndex.php"
    return [
        RenderFault("substitute-mysql-index-field",path,
                    "$this->target->columns[$column->slot]->quoted()", "($column->slot === 0 ? '`f17`' : $this->target->columns[$column->slot]->quoted())",
                    item,"meaning-mismatch",item),
        RenderFault("erase-mysql-index-unique",path,"$this->unique ? 'UNIQUE ' : ''","false ? 'UNIQUE ' : ''",item,"meaning-mismatch",item),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])
