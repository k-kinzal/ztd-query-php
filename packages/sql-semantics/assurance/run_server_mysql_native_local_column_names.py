"""Compare native UTF-8 local names and the delivered positional IR."""

from collections import Counter
from functools import lru_cache
import json
import sys

from evidence_transcript import canonical
import mysql_inherited_local_alias_cases as local
import mysql_native_local_case_table as table
import mysql_native_local_column_name_cases as names
from run import Failure, TOOL, digest, require
from run_server import rejects, same
from run_server_join import main
import run_server_mysql_inherited_local_alias as modern
import run_server_mysql_legacy_inherited_alias as legacy
from run_server_mysql_signed_membership import refusal
from run_server_mysql_signed_projection_alias import OBSERVER, observation, observer_request
from run_server_mysql_signed_range import specifications as range_profiles
from run_server_mysql_with_projection import request
from sqlite_domain import RenderFault

LABEL = "mysql-native-local-column-names"
CONTRACT_NAME = "mysql-native-local-column-names-v1.json"
CONTRACT = "079088225b5a90cf54915864f4381557629eb83160e3e469b69590106c630b36"
NATIVE_NAME = "mysql-native-local-name-rejection"
COMPARATOR_FAULTS = {"Ignore a native name or warning difference.": "nativeNameDifference"}


def specifications():
    path = TOOL / CONTRACT_NAME
    require(digest(path) == CONTRACT, "mysql-native-local-name-contract")
    contract = json.loads(path.read_text())
    fixed = {name: digest(TOOL / name) for name in (modern.CONTRACT_NAME, legacy.CONTRACT_NAME)}
    require(contract["extends"] == fixed and contract["caseTable"]["sha256"] == table.TABLE_DIGEST
            and contract["finite"]["allowedExclusions"] == contract["exploration"]["allowedExclusions"] == [],
            "mysql-native-local-name-base")
    profiles = range_profiles()[1]
    require(contract["profiles"] == ["mysql-" + release for release in profiles["mysql"]["releases"]],
            "mysql-native-local-name-profiles")
    return contract, profiles


def prepare(engine, db):
    fixture = legacy.prepare(engine, db)
    return {"fixture": fixture, "measurements": table.measure(db)}


def check_preparation(engine, environment, response):
    legacy.check_preparation(engine, environment, response["fixture"])
    table.verify(response["measurements"])


def structure(item, observed=False):
    return legacy.structure(item, observed) if item["tag"] in legacy.LEGACY else local.structure(item, observed)


def finite_ids(_, contract, tag):
    listed = list(names.grid(contract, tag))
    independent = names.independent_ids(tag)
    variant = "legacy" if tag in legacy.LEGACY else "modern"
    require(len(listed) == len(independent) == contract["finite"]["coordinatesPerRoute"][variant]
            and set(listed) == independent, "mysql-native-local-name-finite-definition")
    return independent


@lru_cache
def observed_ids(tag):
    items = [(canonical(legacy.graph(structure(names.finite(tag, *cell), observed=True))), cell)
             for cell in names.independent_ids(tag)]
    distinct = dict(items)
    require(len(distinct) == len(items), "mysql-native-local-name-finite-overlap")
    return distinct


def finite_id(_, response, item):
    observed = canonical(legacy.graph(response))
    distinct = observed_ids(item["tag"])
    require(observed in distinct, "mysql-native-local-name-finite-missing", response)
    return distinct[observed]


def analysis_finite_id(engine, response, item, sql):
    same(local.command(item), sql)
    return finite_id(engine, response, item)


def direct_finite_id(engine, response, item, input_):
    same(structure(item), input_)
    return finite_id(engine, response, item)


def rejected_finite_ids(*_):
    return ("partial-prefix", "folded-duplicate", "accent-missing")


def observe_rejection(_, item, raw, sql):
    cause = item["reason"]
    duplicate = cause == "folded-duplicate"
    expected = {"status": "native-error", "sqlState": "42S21" if duplicate else "42S22", "code": 1060 if duplicate else 1054}
    require(raw == {mode: expected for mode in table.MODES}, "mysql-native-local-name-refusal",
            {"source": sql, "expected": expected, "observed": raw})
    return cause


def negatives(tag):
    clipped = " " + "漢" * 90
    sources = [
        ("partial-prefix", f"SELECT d.`{names.COMPLETE_PREFIX}` FROM (SELECT 1 AS `{clipped}`) d", "does not exist."),
        ("folded-duplicate", "SELECT d.target FROM (SELECT 1 AS ` K`, 2 AS k, 3 AS target) d", "Duplicate column name"),
        ("accent-missing", "SELECT d.e FROM (SELECT 1 AS ` É`) d", "Column d.e does not exist."),
    ]
    for reason, statement, message in sources:
        yield request(tag, statement, []), {"reason": reason}, modern.semantic(message)
    if tag in legacy.LEGACY:
        item = names.finite(tag, names.CLIP, "local", "in-order")
        model = structure(item)
        model["local"]["columns"] = [[0, "missing"]]
        yield model, None, refusal("An inherited local field needs a complete UTF-8 name.")
    else:
        item = names.finite(tag, "accent-case", "local", "in-order")
        model = structure(item)
        model["local"][legacy.INHERITED] = True
        yield model, None, refusal("Retaining inherited derived aliases requires a legacy SELECT with requested aliases.")


def requests(_, tag, contract):
    for index, coordinate in enumerate(names.grid(contract, tag)):
        item = names.finite(tag, *coordinate)
        yield f"finite/{index}", request(tag, local.command(item), []), item, None
        yield f"direct/{index}", structure(item), item, None
    for index, (input_, item, reason) in enumerate(negatives(tag)):
        yield f"negative/{index}", input_, item, reason
    budget = contract["exploration"]
    pairs = [(chr(folded), chr(source)) for source, folded in table.cases()]
    for seed in budget["seeds"]:
        for offset, prefix, count in ((0, "seed", budget["casesPerSeed"]), (1, "direct-seed", budget["directCasesPerSeed"])):
            for index, item in enumerate(names.seeded(tag, seed + offset, count, pairs)):
                input_ = structure(item) if offset else request(tag, local.command(item), [])
                yield f"{prefix}/{seed}/{index}", input_, item, None


def check_value(item, response):
    if item["tag"] in legacy.LEGACY:
        legacy.check_value(item, response)
    else:
        modern.check_value(item, response)


def expected(item):
    return legacy.expected(item) if item["tag"] in legacy.LEGACY else local.expected(item)


def check_observation(item, native, emitted):
    correct = expected(item)
    detail = {"sql": emitted, "expected": correct, "observed": native}
    require(all(native[mode]["status"] == "rows" for mode in table.MODES), "mysql-native-local-name-execution-rejected", detail)
    require(canonical(correct) == canonical(native), modern.MEANING, detail)


def sample_forms(forms: Counter, response, engine, item):
    modern.sample_forms(forms, response, engine, item)
    for columns in modern.native_scans(response):
        output = [label[2] if isinstance(label, list) else label for _, label in columns]
        forms[f"native-byte-label/{any(not name.isascii() for name in output)}"] += 1


def required_forms(_, tag):
    placements = legacy.PLACEMENTS if tag in legacy.LEGACY else local.PLACEMENTS
    return {*(f"placement/{place}" for place in placements), "width/2", "width/3", "width/4",
            "occurrence/0", "occurrence/1", "native-byte-label/True"}


def controls():
    item = names.finite("mysql-8.0.44", "case-to-ascii", "local", "repeat")
    native = expected(item)
    check_observation(item, native, local.command(item))
    changed = dict(native)
    fields = [list(field) for field in native["native-prepared"]["fields"]]
    fields[0][0] = ("other".encode().hex())
    changed["native-prepared"] = {**native["native-prepared"], "fields": fields, "prepareWarnings": []}
    try:
        check_observation(item, changed, local.command(item))
    except Failure as error:
        same(modern.MEANING, error.signal)
        return {"nativeNameDifference": error.detail}
    raise Failure("mysql-native-local-name-comparator-accepted")


def faults(_, tag):
    package = "sql-semantics-mysql/src/"
    cases = [
        ("use-displayed-local-prefix-in-binding", "Rules/Query/InheritedAliases.php", "$names->prefix($item->alias->value)",
         'mb_strcut(ltrim($item->alias->value, "\\x00..\\x20\\x7f"), 0, 256, "UTF-8")', names.CLIP, "Duplicate column name"),
        ("use-ascii-lookup-for-native-local-names", "Model/Resolution/LookupLevel.php",
         "$relation->shape->nativeColumnNames ? new NativeLocalNames() : null", "null", "accent-case", "does not exist."),
        ("fold-accents-in-native-local-names", "Model/Statement/Identifier/NativeLocalNames.php",
         "strtr($name, self::LOWER)", "str_replace('é', 'e', strtr($name, self::LOWER))", "accent-distinct", "Duplicate column name"),
    ]
    result = []
    for label, file, old, new, shape, message in cases:
        item = names.finite(tag, shape, "local", "in-order")
        item["wrongRejection"] = message
        result.append(RenderFault(label, package + file, old, new, item, NATIVE_NAME, request(tag, local.command(item), [])))
    item = names.finite(tag, "accent-case", "local", "in-order")
    result.append(RenderFault("read-other-complete-native-heading", package + "Ir/Rows/LocalProjectionSql.php",
                              "$renderer->localColumn($column->slot, $inherited)", "$renderer->localColumn(1, $inherited)",
                              item, modern.MEANING, structure(item)))
    return result


def wrong_rejection(item, response):
    require("wrongRejection" in item and rejects(response, modern.semantic(item["wrongRejection"])),
            "mysql-native-local-name-unrelated-rejection", response)
    raise Failure(NATIVE_NAME, {"sql": local.command(item), "response": response})


def fault_probe(item, response, db):
    if response["status"] == "rejected":
        wrong_rejection(item, response)
    check_value(item, response)
    sql = response["sql"]
    check_observation(item, observation(db.request(observer_request(item, sql))), sql)


def replay_fault(rule, record):
    item = rule.item
    mutant = record["mutantResponse"]
    detail = record["detail"]
    check_value(item, record["healthyResponse"])
    try:
        if rule.signal == NATIVE_NAME:
            wrong_rejection(item, mutant)
        check_value(item, mutant)
        same(mutant["sql"], detail["sql"])
        check_observation(item, detail["observed"], detail["sql"])
    except Failure as error:
        same(rule.signal, error.signal)
        same(detail, error.detail)
        return
    raise Failure("mysql-native-local-name-fault-not-reproduced")


if __name__ == "__main__":
    main(sys.modules[__name__])
