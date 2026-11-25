"""Verify physical MySQL index removal by independent native catalog observations."""

from copy import deepcopy
from itertools import product
import json
import sys

from mysql_drop_index_cases import (
    ABSENT, KINDS, LIFETIMES, QUALIFIERS, SPELLINGS, STATUS, SUFFIX_BYTES, analyze, cases, command,
    coordinates, declarations, definition, field_rows, prediction, probe_last, published_single_removal,
)
from mysql_drop_index_cases import check_removal_facts_contract
from run import Failure, TOOL, digest, require
from run_server import same
from run_server_join import main, negative_count
from run_server_mysql_create_varchar import DIAGNOSTIC, specifications as native_profiles
from sqlite_domain import RenderFault


LABEL = "mysql-drop-index"
NAME = "mysql-drop-native-index-evidence-v4.json"
CONTRACT = "3d97452ae70caf96c47f57111ed99507304460f3260eb1a794665bc247219389"
SUFFIX_CONTRACT = "cba633bc81924ce3a8e7eee3507b0af23294bce1dbb634c894b3f2d05e8e9902"
BASE = "b913362b3246efb53355e3bbe9128134f7bb9d80b408d18c616f70579a73b309"
CASE_CONTRACT = "a1c9a4243a939dc8d13a1971277bc84f5b91e5366ef1c5a96ab41c21eb34a307"
PRESENTATION = "377fae924fbac47b1c370e0a49f118162d8fa812370d0a4455aaedd4a280a230"
OBSERVER = "mysql_drop_index_observer.php"
PROFILE = "/utf8mb4/lower-case-table-names=0/sql-mode=empty/native/innodb/drop-index"
COMPARATOR_FAULTS = {
    "Ignore changed retained uniqueness.": "ignored-retained-uniqueness",
    "Ignore changed column metadata.": "ignored-column-metadata",
}
RESOLUTION = "DROP INDEX requires one resolved ordinary index on its owning table."
ROLE = "Only the physical primary key has the canonical PRIMARY index identity."


def specifications():
    path = TOOL / NAME
    require(digest(path) == CONTRACT and digest(TOOL / "mysql-drop-native-index-v1.json") == BASE
            and digest(TOOL / "mysql-native-index-case-v1.json") == CASE_CONTRACT
            and digest(TOOL / "mysql-drop-index-native-metadata-v1.json") == PRESENTATION, "mysql-drop-index-contract")
    names = TOOL / "mysql-native-index-generated-name-v2.json"
    require(digest(names) == SUFFIX_CONTRACT, "mysql-drop-index-suffix-contract")
    same(json.loads(names.read_text())["bytesByRelease"], {tag.removeprefix("mysql-"): width for tag, width in SUFFIX_BYTES.items()})
    spec = json.loads(path.read_text())
    check_removal_facts_contract(spec)
    profiles = native_profiles()[1]
    same(spec["releases"], profiles["mysql"]["releases"])
    same([], spec["exploration"]["allowedExclusions"])
    return spec, profiles


def grid(spec):
    finite = spec["finite"]
    for life, policy in product(finite["lifetimes"], finite["targetKinds"]):
        for origin, spelling, qualification in product(finite["nameOrigins"][policy], finite["sourceSpellings"], finite["qualifiers"]):
            yield life, policy, origin, spelling, qualification


def finite_ids(_, spec, _tag):
    expected = coordinates()
    require(expected == set(grid(spec)) and len(expected) == spec["finite"]["identities"], "mysql-drop-index-finite-domain")
    return expected


def observed_point(response, item):
    model = item["model"]
    observer = response["mysqlDropIndex"]
    lifetime, _, origin, spelling, qualification = model["point"]
    same("table" if lifetime == "persistent" else "temporary-table", observer["owner"][2])
    same(model["mysqlDropIndex"]["name"], observer["name"])
    role = "unique" if observer["role"] == "clustered-unique" else observer["role"]
    return lifetime, role, origin, spelling, qualification


def analysis_finite_id(_, response, item, sql):
    same(command(item["model"], original=True), sql)
    return observed_point(response, item)


def direct_finite_id(_, response, item, request):
    same(item["model"], request)
    return observed_point(response, item)


def case_pair(route, id_, model, **kwargs):
    item = {"model": model, "id": id_}
    direct = {"finite": "direct", "seed": "direct-seed"}.get(route, route + "-direct")
    yield f"{route}/{id_}", analyze(model, **kwargs), item, None
    yield f"{direct}/{id_}", model, item, None


def negative_cases(tag):
    plain = definition(tag, ("persistent", "unique", "explicit", "declared", "explicit"))
    for name, property_, value, reason in (
        ("reserved-secondary", "name", "PRİMARY", ROLE),
        ("false-primary-role", "role", "primary", ROLE),
        ("trailing-space", "name", "wrong ", "1 to 64 characters"),
        ("overlong", "name", "界" * 65, "1 to 64 characters"),
        ("non-bmp", "name", "𐐀", "BMP characters"),
        ("view-owner", "owner", [*plain["mysqlDropIndex"]["owner"][:2], "view"], "resolved table identity"),
    ):
        broken = deepcopy(plain)
        broken["mysqlDropIndex"][property_] = value
        yield name, broken, None, reason

    for name, model, written in (
        ("unknown", plain, "unknown"),
        ("implicit-is-not-primary", definition(tag, ("persistent", "unique", "automatic-in-order", "declared", "explicit")), "PRIMARY"),
        ("shadow-is-not-persistent", definition(tag, ("temporary-shadow", "ordinary", "explicit", "declared", "explicit")), "persistent_only"),
    ):
        model = {**model, "written": written}
        yield name, analyze(model), {"model": model, "id": name, "nativeError": ABSENT}, RESOLUTION

    long_field = "W" * 64
    wrong_width = 61 if SUFFIX_BYTES[tag] == 30 else 30
    model = definition(tag, ("persistent", "ordinary", "automatic-suffixed", "declared", "explicit"), field=long_field)
    model["written"] = long_field[:wrong_width] + "_2"
    case_id = "wrong-release-suffix-width"
    yield case_id, analyze(model), {"model": model, "id": case_id, "nativeError": ABSENT}, RESOLUTION

    outside = analyze(plain, context=[ddl.replace("InnoDB", "MyISAM") for ddl in declarations(plain)])
    yield "different-engine", outside, None, "resolved ordinary InnoDB declaration"

    ambiguous = declarations(plain)
    ambiguous[0] = ambiguous[0].replace(") ENGINE=", ", INDEX `lIsTé` (`b`)) ENGINE=")
    yield "ambiguous-index-names", analyze(plain, context=ambiguous), None, RESOLUTION


def requests(engine, tag, spec):
    for point in grid(spec):
        yield from case_pair("finite", "/".join(point), definition(tag, point))
    for seed in spec["exploration"]["seeds"]:
        for ordinal, model in enumerate(cases(tag, seed, spec["exploration"]["casesPerSeed"])):
            yield from case_pair("seed", f"{seed}/{ordinal}", model)

    automatic = ("temporary-shadow", "unique", "automatic-suffixed", "permitted-case-variant", "current-database")
    for name, field in (("ascii-suffix-capacity", "X" * 64), ("utf8-suffix-capacity", "é" * 20)):
        yield from case_pair("edge", name, definition(tag, automatic, field=field))
    yield from case_pair("edge", "ascii-first-free-suffix", definition(tag, automatic, field="X" * 64, suffix=3))
    primary = definition(tag, ("persistent", "primary", "table", "permitted-case-variant", "explicit"))
    primary = {**primary, "written": "PRİMARY", "option": " LOCK=DEFAULT ALGORITHM DEFAULT"}
    yield from case_pair("edge", "native-dotted-I-selects-primary", primary)
    greek = definition(tag, ("temporary", "ordinary", "explicit", "permitted-case-variant", "explicit"), label="Άλφα")
    yield from case_pair("edge", "native-greek-case", greek)

    unique = ("persistent", "unique", "explicit", "declared", "explicit")
    yield from case_pair("edge", "unique-remains-secondary-if-nullable", definition(tag, unique, nullable=True))

    negatives = list(negative_cases(tag))
    same(negative_count(spec, engine, tag), len(negatives))
    for name, request, item, reason in negatives:
        yield f"negative/{name}", request, item, {"exception": DIAGNOSTIC, "message": reason}


def rejected_finite_ids(_, _tag):
    return {"unknown", "implicit-is-not-primary", "shadow-is-not-persistent", "wrong-release-suffix-width"}


def check_value(item, response):
    model = item.get("model", item)
    require(response["status"] == "constructed", "mysql-drop-index-construction", response)
    same(model["release"] + PROFILE, response["profile"])
    same(published_single_removal(model["mysqlDropIndex"]), response["mysqlDropIndex"])


def observer_request(item, sql):
    model = item.get("model", item)
    return {"sql": sql, "field": model["field"], "lifetime": model["point"][0], "declarations": declarations(model),
            "rows": {"temporary": field_rows(model, True), "persistent": field_rows(model, False)}, "probe": not item.get("nativeError"), "probeLast": probe_last(model)}


def observation(raw):
    return raw


def check_observation(item, actual, sql):
    model = item.get("model", item)
    expected = prediction(model, item.get("nativeError"))
    witness = {"expected": expected, "observed": actual, "sql": sql}
    require(actual["status"] == STATUS, "mysql-drop-index-observer-scope", witness)
    require({key: actual[key] for key in expected} == expected, "meaning-mismatch", witness)
    if actual["effect"][0] == "executed":
        require(type(actual["affected"]) is int and actual["affected"] >= 0 and actual["diagnostics"] == [], "meaning-mismatch", witness)
    else:
        require(actual["affected"] is None and any(note[1] == actual["effect"][2] for note in actual["diagnostics"]),
                "meaning-mismatch", witness)


def observe_rejection(_, item, actual, sql):
    check_observation(item, actual, sql)
    return item["id"]


def sample_forms(forms, response, _, item):
    model = item["model"]
    forms[f"scope/{model['point'][0]}"] += 1
    forms[f"policy/{response['mysqlDropIndex']['role']}"] += 1
    forms[f"origin/{model['point'][2]}"] += 1
    forms[f"parts/{len(model['fields'])}"] += 1


def required_forms(_, _tag):
    return {*(f"scope/{life}" for life in LIFETIMES), *(f"policy/{role}" for role in KINDS), "policy/clustered-unique",
            *(f"origin/{origin}" for roles in KINDS.values() for origin in roles), "parts/1", "parts/2"}


def outsiders():
    return ("DROP TABLE ir_index_removal", "DROP INDEX `safe` ON `outside`",
            "DROP INDEX `safe` ON `ir_index_removal`; SELECT 1")


def prepare(_, db):
    model = witness("mysql-5.6.51")
    return {"outside": [db.request(observer_request(model, sql)) for sql in outsiders()]}


def check_preparation(_, _environment, receipt):
    same([{"status": "outside-observer-scope"} for _ in outsiders()], receipt["outside"])


def witness(tag):
    return definition(tag, ("temporary-shadow", "unique", "automatic-suffixed", "declared", "explicit"))


def controls():
    model = witness("mysql-9.1.0")
    original = {**prediction(model), "affected": 0, "diagnostics": []}
    check_observation(model, original, command(model))
    metadata = deepcopy(original)
    metadata["after"]["columns"][1][2] = False
    changed = {**original, "retained": ["executed"]}
    verdicts = {}
    for key, mutated in (("ignored-retained-uniqueness", changed), ("ignored-column-metadata", metadata)):
        try:
            check_observation(model, mutated, command(model))
        except Failure as error:
            same("meaning-mismatch", error.signal)
            verdicts[key] = error.detail
            continue
        raise Failure("mysql-index-removal-unobserved-catalog-difference", key)
    return verdicts


def check_fault_witness(item, cause):
    same(prediction(item), cause["expected"])
    same(STATUS, cause["observed"]["status"])
    same(["executed"], cause["observed"]["effect"])


def faults(_, tag):
    item = witness(tag)
    path = "sql-semantics-mysql/src/Ir/Schema/Index/DropIndex.php"
    return [RenderFault("drop-unrelated-mysql-index", path, "$this->target->name->quoted()", "'`retained_c`'",
                        item, "meaning-mismatch", item)]


if __name__ == "__main__":
    main(sys.modules[__name__])
