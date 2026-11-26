"""Exercise fail-closed evidence, runtime package, and readonly-storage checks."""

import argparse
import copy
import difflib
import json
import shutil
import subprocess
from pathlib import Path
from types import SimpleNamespace

from check_fixture_launch_recovery import controls as launch_recovery_controls
from check_native_boundaries import native_boundary_procedures
from check_native_workflow import CONTRACT_NAME as WORKFLOW_CONTRACT, WORKFLOW, audit as audit_workflow, steps as workflow_steps
from compare_assurance_revisions import review
from constructor_trace import DECLARATIONS, check_scope, declaration_contract
from evidence_transcript import events, load_replay_contract
from index_evidence import Evidence, replay_grammar, verify_fixture_recovery, verify_sqlite_preflight
from run_native_matrix import RELEASE_ROW, release_fixtures, release_jobs
from replay_sqlite_evidence import replay
from replay_sqlite_faults import replay_registered
from run_sqlite_mutation_conflicts import FIXTURE as CONFLICT_FIXTURE, check_faults as conflict_faults
from renderer_fault_dispatch import FAILURE_NAME as DISPATCH_FAILURE, PLAN_NAME as DISPATCH_PLAN
from run import IDENTITY_CONTRACT, TOOL, PACKAGES, Failure, candidate_distribution, compare, digest, manifest, require, shape, write_json
from sqlite_domain import RenderFault, check_render_faults
from scope import CONTRACTS, public_scope
from sqlite_engine import OBSERVER_CONTRACT, Database, ObserverAuthorizationError, observer_policy


def observe_rejection(test, expected):
    try:
        test()
    except Failure as failure:
        require(failure.signal == expected, "wrong-assurance-control-signal", str(failure))
        return failure
    raise Failure("undetected-assurance-control", expected)


def evidence_controls(args):
    directory = args.root / "semantics-ir-sqlite-delete-evidence"
    path = directory / "events.jsonl"
    index = json.loads((directory / "index.json").read_text())
    contract = "sqlite-delete-v1.json"
    replay(directory, contract, index, args.sqlite_library)
    original = list(events(path))
    constructed = next(index for index, event in enumerate(original) if event["event"] == "constructed")
    observed = next(index for index, event in enumerate(original) if event["event"] == "observed")

    changed_observation = copy.deepcopy(original)
    changed_observation[observed]["observation"]["temp"][0][0] = ["text", "changed"]
    changed_input = copy.deepcopy(original)
    changed_input[0]["input"]["target"][0] = "temp" if changed_input[0]["input"]["target"][0] == "main" else "main"
    cases = (
        ("missing-ledger", None, "missing-evidence-transcript"),
        ("missing-construction", original[:constructed] + original[constructed + 1:], "incomplete-evidence-transcript"),
        ("changed-observation", changed_observation, "evidence-ledger-mismatch"),
        ("changed-input", changed_input, "evidence-request-mismatch"),
        ("unknown-comparison", [*original, {"id": "unknown/0", "event": "compared"}], "evidence-ledger-mismatch"),
    )
    require(len(cases) == len(load_replay_contract()["requiredCorruptions"]), "missing-evidence-control")
    results = []
    for name, records, expected in (*cases, ("empty-ledger", [], "empty-evidence-transcript")):
        target = args.out / name
        target.mkdir()
        trace = target / path.name
        if records is not None:
            with trace.open("w") as stream:
                for event in records:
                    stream.write(json.dumps(event) + "\n")
        failure = observe_rejection(lambda: replay(target, contract, index, args.sqlite_library), expected)
        results.append({
            "case": name, "control": "passed", "original": digest(path), "mutated": digest(trace) if trace.exists() else None,
            "expected": expected, "signal": failure.signal, "detail": failure.detail,
        })
    return results


def scope_controls(args, distribution):
    root = args.root / "semantics-ir-evidence"
    path = root / "scope.json"
    scope = json.loads(path.read_text())
    inventory = json.loads((root / "inventory.json").read_text())
    check_scope(inventory, scope, distribution)
    records = []
    for name, field in (("missing-constructor-obligation", "registeredConstructors"),
                        ("missing-publication-route", "publicationRoutes")):
        mutated = copy.deepcopy(scope)
        removed = sorted(mutated[field])[0]
        del mutated[field][removed]
        folder = args.out / name
        folder.mkdir()
        target = folder / path.name
        write_json(target, mutated)
        (folder / "injected.diff").write_text("".join(difflib.unified_diff(
            path.read_text().splitlines(keepends=True), target.read_text().splitlines(keepends=True),
            str(path), str(target),
        )))
        expected = "trace-scope-mismatch"
        failure = observe_rejection(lambda: check_scope(inventory, mutated, distribution), expected)
        records.append({"case": name, "removed": removed, "control": "passed", "original": digest(path),
                        "mutated": digest(target), "expected": expected, "signal": failure.signal})
    records.append(generic_return_control(args, distribution))
    return records


def generic_return_control(args, distribution):
    name = "undeclared-generic-publications"
    folder = args.out / name
    package = "sql-semantics-postgres"
    source = f"packages/{package}/src/Model/Contract/LanguageProfile.php"
    for member in ("sql-semantics", "sql-semantics-mysql", package, "sql-semantics-sqlite"):
        original = args.candidate / "packages" / member / "src"
        target = folder / "packages" / member / "src"
        target.parent.mkdir(parents=True)
        if member == package:
            shutil.copytree(original, target)
        else:
            target.symlink_to(original, target_is_directory=True)
    original = args.candidate / source
    target = folder / source
    source_text = original.read_text()
    boundary = source_text.rfind("\n}")
    require(boundary > 0, "generic-publication-injection-anchor", source)
    patch = r"""
    public function unregisteredList(): ?array
    {
        return [(new \SqlSemantics\Platform\PostgreSql\Semantics())->analyze('SELECT 1')];
    }

    public function unregisteredIterator(): \Iterator
    {
        yield (new \SqlSemantics\Platform\PostgreSql\Semantics())->analyze('SELECT 1');
    }

    public function unregisteredValue()
    {
        return (new \SqlSemantics\Platform\PostgreSql\Semantics())->analyze('SELECT 1');
    }

    public function unregisteredMixed(): mixed
    {
        return (new \SqlSemantics\Platform\PostgreSql\Semantics())->analyze('SELECT 1');
    }
"""
    target.write_text(source_text[:boundary] + "\n" + patch + source_text[boundary:])
    diff = folder / "injected.diff"
    diff.write_text("".join(difflib.unified_diff(
        source_text.splitlines(keepends=True), target.read_text().splitlines(keepends=True), str(original), str(target)
    )))
    measured = folder / "inventory.json"
    with measured.open("w") as stream:
        subprocess.run(["php", str(TOOL / "inventory.php"), str(folder), str(args.tool_autoload)],
                       check=True, timeout=60, stdout=stream)
    inventory = json.loads(measured.read_text())
    manifest = {**distribution, source: digest(target)}
    scope = public_scope(inventory, manifest)
    expected = "trace-unregistered-role-or-path"
    failure = observe_rejection(lambda: check_scope(inventory, scope, manifest), expected)
    owner = "SqlSemantics\\Platform\\PostgreSql\\Model\\Contract\\LanguageProfile::"
    intended = {owner + "unregisteredList": ["array", "null"], owner + "unregisteredIterator": ["iterator"],
                owner + "unregisteredValue": ["untyped"], owner + "unregisteredMixed": ["mixed"]}
    require(failure.detail["unexpectedUnconstrainedRoutes"] == intended,
            "generic-publication-mutation-mismatch", failure.detail)
    return {"case": name, "control": "passed", "path": source, "original": digest(original), "mutated": digest(target),
            "injection": digest(diff), "inventory": digest(measured), "expected": expected, "signal": failure.signal,
            "observed": intended}


def declaration_control(args):
    original = TOOL / DECLARATIONS
    valid = declaration_contract(original)
    folder = args.out / "weakened-constructor-observation"
    folder.mkdir()
    target = folder / DECLARATIONS
    valid["obligations"]["Q04"]["criterion"] = "Accept an output statement when the database executes it."
    write_json(target, valid)
    (folder / "injected.diff").write_text("".join(difflib.unified_diff(
        original.read_text().splitlines(keepends=True), target.read_text().splitlines(keepends=True),
        str(original), str(target),
    )))
    expected = "trace-declaration-contract-mismatch"
    failure = observe_rejection(lambda: declaration_contract(target), expected)
    return {"case": "weakened-constructor-observation", "control": "passed", "original": digest(original),
            "mutated": digest(target), "expected": expected, "signal": failure.signal}


def copy_package_metadata(args, fixture):
    for package in PACKAGES:
        destination = fixture / "packages" / package
        (destination / "src").mkdir(parents=True)
        for name in ("composer.json", "composer.lock"):
            shutil.copyfile(args.candidate / "packages" / package / name, destination / name)


def distribution_control(args, distribution):
    fixture = args.out / "runtime-package"
    copy_package_metadata(args, fixture)
    manifest(fixture)
    composer = fixture / "packages/sql-semantics/composer.json"
    original = digest(composer)
    metadata = json.loads(composer.read_text())
    metadata["autoload"]["files"] = ["src/SemanticsIR.php"]
    write_json(composer, metadata)
    expected = "runtime-package-bootstrap-mismatch"
    failure = observe_rejection(lambda: manifest(fixture), expected)
    require(original == distribution["packages/sql-semantics/composer.json"], "runtime-control-input-mismatch")
    return {"case": "unregistered-composer-bootstrap", "control": "passed", "original": original,
            "mutated": digest(composer), "expected": expected, "signal": failure.signal, "detail": failure.detail}


def identity_controls(args):
    fixture = args.out / "candidate-identity"
    copy_package_metadata(args, fixture)
    target = fixture / "packages/sql-semantics/src/SemanticsIR.php"
    candidate = args.candidate / target.relative_to(fixture)
    original = candidate.read_bytes()
    target.write_bytes(original)

    def git(*arguments):
        subprocess.run(["git", "-C", str(fixture), *arguments], check=True, stdout=subprocess.PIPE, timeout=60)

    git("init", "--quiet")
    git("add", "--force", "--", "packages")
    git("-c", "user.name=SemanticsIR Controls", "-c", "user.email=semantics-ir@example.invalid",
        "-c", "commit.gpgsign=false", "-c", "core.hooksPath=/dev/null", "commit", "--quiet", "-m", "Known candidate")
    commit, healthy = candidate_distribution(fixture)
    unrelated = fixture / "packages/sql-semantics/unmeasured.txt"
    unrelated.write_text("Outside the source and resource trees.\n")
    require(candidate_distribution(fixture, commit) == (commit, healthy), "unrelated-file-changed-runtime")
    results = [{"case": "unchanged-checkout-with-unrelated-files", "commit": commit, "path": str(unrelated.relative_to(fixture)),
                "digest": digest(unrelated), "control": "passed", "expected": "same-runtime-distribution",
                "signal": "same-runtime-distribution"}]
    added = target.with_name("Untracked.php")
    cases = (
        ("changed-committed-value", target, original + b"\n"),
        ("untracked-runtime-file", added, original),
        ("missing-runtime-file", target, None),
    )
    specification = json.loads((TOOL / "candidate-identity-v1.json").read_text())
    require(set(specification["faults"]) == {record["case"] for record in results} | {name for name, _, _ in cases},
            "missing-candidate-identity-control")
    for name, path, replacement in cases:
        if replacement is None:
            path.unlink()
        else:
            path.write_bytes(replacement)
        relative = str(path.relative_to(fixture))
        injected = args.out / name
        injected.mkdir()
        if replacement is not None:
            (injected / path.name).write_bytes(replacement)
        expected = specification["faults"][name]
        failure = observe_rejection(lambda: candidate_distribution(fixture, commit), expected)
        require(failure.detail == [relative], "wrong-candidate-identity-detail", failure.detail)
        results.append({
            "case": name, "commit": commit, "path": relative, "original": healthy.get(relative),
            "mutated": digest(path) if replacement is not None else None, "control": "passed", "expected": expected,
            "signal": failure.signal, "detail": failure.detail,
        })
        if relative in healthy:
            path.write_bytes(original)
        else:
            path.unlink()
        require(candidate_distribution(fixture, commit) == (commit, healthy), "candidate-control-did-not-restore")
    return results


def source_shape_mutation(args, dialect, file, name, original, replacement, detail):
    relative = Path(f"sql-semantics-{dialect}/src/Ir/{file}")
    source = args.candidate / "packages" / relative
    text = source.read_text()
    require(text.count(original) == 1, "storage-control-anchor-mismatch", [dialect, name])
    overlay = args.out / name / dialect
    target = overlay / relative
    target.parent.mkdir(parents=True)
    mutated = text.replace(original, replacement, 1)
    target.write_text(mutated)
    (overlay / "injected.diff").write_text("".join(difflib.unified_diff(
        text.splitlines(keepends=True), mutated.splitlines(keepends=True), f"a/{relative}", f"b/{relative}",
    )))
    failure = observe_rejection(lambda: shape(args, overlay, dialect), "shape-mismatch")
    require(detail in failure.detail["message"], "wrong-source-shape-control-detail", failure.detail)
    return {"case": name, "dialect": dialect, "control": "passed", "original": digest(source), "mutated": digest(target),
            "expected": "shape-mismatch", "signal": failure.signal, "detail": failure.detail}


def storage_controls(args):
    results = []
    for dialect in ("sqlite", "mysql", "postgres"):
        shape(args, dialect=dialect)
        expected_list_failure = "Native boundary differs in TableSource::__construct"
        for name, replacement in (
            ("store-unchecked-list", "$this->columns = $columns;"),
            ("rewrite-checked-list", "[$names] = [$columns];\n        $this->columns = $names;"),
        ):
            results.append(source_shape_mutation(args, dialect, "TableSource.php", name, "$this->columns = $names;",
                                            replacement, expected_list_failure))
        name = "Ordering/Order.php"
        location = args.candidate / f"packages/sql-semantics-{dialect}/src/Ir" / name
        assignments = [line for line in location.read_text().splitlines(keepends=True) if line.startswith("        $this->nullsFirst = ")]
        require(len(assignments) == 1, "uninitialized-field-control-anchor", dialect)
        initialization_failure = ("Native boundary differs in Ordering/Order::__construct" if dialect == "postgres"
                                  else "Value field needs one unconditional initialization in Ordering\\Order::nullsFirst")
        results.append(source_shape_mutation(args, dialect, name, "leave-readonly-field-uninitialized", assignments[0], "",
                                            initialization_failure))
    return results


def dispatch_controls(args):
    source = "SelectSql.php"
    branch = "            $expression instanceof IntegerLiteral => $expression->decimal,"
    shape(args, dialect="sqlite")
    return [
        source_shape_mutation(args, "sqlite", source, "omit-required-scalar-dispatch", branch, "",
                              "Incomplete scalar dispatch in expression"),
        source_shape_mutation(args, "sqlite", source, "insert-silent-scalar-fallback", branch,
                              branch + "\n            default => '0',", "Unhandled fallback in scalar dispatch expression"),
    ]


def publication_controls(args):
    results = []
    for dialect in ("sqlite", "mysql", "postgres"):
        shape(args, dialect=dialect)
        relative = Path(f"sql-semantics-{dialect}/src/Semantics.php")
        source = args.candidate / "packages" / relative
        text = source.read_text()
        require(text.endswith("\n}\n"), "publication-control-anchor-mismatch", dialect)
        mutated = text[:-2] + "    public function inspect(): mixed\n    {\n        return null;\n    }\n}\n"
        overlay = args.out / "additional-publication-route" / dialect
        target = overlay / relative
        target.parent.mkdir(parents=True)
        target.write_text(mutated)
        (overlay / "injected.diff").write_text("".join(difflib.unified_diff(
            text.splitlines(keepends=True), mutated.splitlines(keepends=True),
            f"a/{relative}", f"b/{relative}",
        )))
        failure = observe_rejection(lambda: shape(args, overlay, dialect), "shape-mismatch")
        require(failure.detail["message"] == "Unexpected public façade methods", "wrong-publication-control-detail", failure.detail)
        results.append({
            "case": "additional-publication-route", "dialect": dialect,
            "original": digest(source), "mutated": digest(target), "control": "passed",
            "expected": "shape-mismatch", "signal": failure.signal, "detail": failure.detail,
        })
    return results


def quote_path(path):
    return "'" + path.as_posix().replace("'", "''") + "'"


def observer_controls(args):
    directory = args.out / "sqlite-observer"
    directory.mkdir()
    database = Database(args.sqlite_library)
    result = []
    select = "SELECT value FROM fixture"
    attach = directory / "attached.sqlite"
    copy = directory / "copied.sqlite"
    cases = (
        ("attach-file", f"ATTACH DATABASE {quote_path(attach)} AS external", 24, attach),
        ("vacuum-into-file", f"VACUUM INTO {quote_path(copy)}", 24, copy),
        ("unregistered-pragma", "PRAGMA writable_schema=ON", 19, None),
        ("unregistered-function", "SELECT abs(value) FROM fixture", 31, None),
    )
    try:
        database.execute("CREATE TABLE fixture (value); INSERT INTO fixture VALUES (7)")
        healthy = database.observe(select, {})
        require(healthy["rows"] == [[["integer", "7"]]], "sqlite-observer-positive-control-failed", healthy)
        result.append({"case": "ordinary-select", "sql": select, "observation": healthy, "control": "passed"})
        for case, sql, action, path in cases:
            try:
                database.observe(sql, {}, capture_evaluation_error=True)
            except ObserverAuthorizationError as denial:
                require(denial.action == action, "sqlite-observer-wrong-denial", str(denial))
                result.append({
                    "case": case, "sql": sql, "expectedAction": action, "action": denial.action,
                    "arguments": denial.arguments, "control": "passed",
                })
            else:
                raise Failure("sqlite-observer-accepted-forbidden-action", case)
            require(path is None or not path.exists(), "sqlite-observer-created-file", case)
            require(database.observe(select, {}) == healthy, "sqlite-observer-fixture-changed", case)
        require({record["case"] for record in result} == observer_policy()["controls"].keys(),
                "sqlite-observer-control-set-mismatch")
        result.extend(text_controls(database))
        write_json(directory / "index.json", {"policy": OBSERVER_CONTRACT, "engine": database.version,
                                              "textObservation": database.text_observation_digest, "controls": result})
        return result
    finally:
        database.close(database.db)


def text_controls(db):
    query = "SELECT CAST(X'80FF00' AS TEXT), X'80FF00', CAST(X'410042' AS TEXT)"
    expected = [[["text-bytes", "80ff00"], ["blob", "80ff00"], ["text", "A" + chr(0) + "B"]]]
    observed = db.observe(query, {})["rows"]
    compare(expected, observed)
    signals = {}
    for name, changed in (
        ("invalid-text-converted-to-blob", [[expected[0][1], *expected[0][1:]]]),
        ("embedded-zero-removed", [[*expected[0][:2], ["text", "AB"]]]),
    ):
        failure = observe_rejection(lambda: compare(expected, changed), "meaning-mismatch")
        signals[name] = failure.signal
    return [{"case": "lossless-native-text", "control": "passed", "sql": query, "expected": expected,
             "observed": observed, "mismatches": signals}]


def grammar_control(args):
    root = args.root / "semantics-ir-evidence"
    source = root / "grammar-scope.json"
    summary = json.loads((root / "index.json").read_text())["grammarScope"]
    record = replay_grammar(source, args.candidate, summary)
    path = sorted(record["artifacts"])[0]
    deleted_group = record["artifacts"][path]["statementContexts"]["groups"].pop()
    folder = args.out / "omitted-required-grammar-context"
    folder.mkdir()
    target = folder / source.name
    write_json(target, record)
    expected = "bundle-grammar-inventory-mismatch"
    failure = observe_rejection(lambda: replay_grammar(target, args.candidate, summary), expected)
    return {"case": folder.name, "control": "passed", "grammar": path, "removed": deleted_group,
            "original": digest(source), "mutated": digest(target), "expected": expected, "signal": failure.signal}


def fault_replay_controls(args, distribution):
    origin = args.root / "semantics-ir-sqlite-mutation-conflicts-evidence"
    originals = json.loads((origin / "index.json").read_text())["faults"]
    case = "discard-failed-insert-prefix"

    def run(directory, records):
        db = Database(args.sqlite_library)
        try:
            db.execute(CONFLICT_FIXTURE)
            evidence = SimpleNamespace(directory=directory, candidate=args.candidate, manifest=distribution, records=records)
            return conflict_faults(evidence, db, runner=replay_registered)
        finally:
            db.close(db.db)

    healthy = run(origin, originals)
    require(case in healthy and all(record["status"] == "native-replayed" for record in healthy.values()),
            "sqlite-fault-replay-control-healthy", sorted(healthy))
    results = []
    for label, expected in (
        ("omit-healthy-report", "sqlite-fault-witness-missing"),
        ("replace-mutant-response", "sqlite-fault-replay-accepted-mutant"),
        ("change-fault-patch", "sqlite-fault-patch-mismatch"),
        ("omit-renderer-fault-dispatch", "renderer-fault-dispatch-plan-missing"),
        ("replace-renderer-fault-request", "renderer-fault-dispatch-plan-mismatch"),
        ("replace-renderer-fault-plan-digest", "renderer-fault-dispatch-receipt-mismatch"),
    ):
        folder = args.out / label
        shutil.copytree(origin / "faults", folder / "faults")
        shutil.copyfile(origin / DISPATCH_PLAN, folder / DISPATCH_PLAN)
        records = copy.deepcopy(originals)
        changed = next(record for record in records if record["case"] == case)
        result = folder / "faults" / case / "result.json"
        if label == "change-fault-patch":
            target = result.with_name("injected.diff")
            baseline = digest(target)
            target.write_text(target.read_text() + "\n")
        elif label in {"omit-renderer-fault-dispatch", "replace-renderer-fault-request"}:
            target = folder / DISPATCH_PLAN
            baseline = digest(target)
            shutil.copyfile(target, folder / "original-dispatch.json")
            if label == "omit-renderer-fault-dispatch":
                target.unlink()
            else:
                plan = json.loads(target.read_text())
                entry = next(item for item in plan["faults"] if item["case"] == case)
                entry["request"] = {"invalidConstructionRequest": True}
                write_json(target, plan)
        else:
            target = result
            baseline = digest(target)
            if label == "omit-healthy-report":
                del changed["healthyResponse"]
            elif label == "replace-renderer-fault-plan-digest":
                changed["dispatch"]["planSha256"] = "0" * 64
            else:
                changed["mutantResponse"] = changed["healthyResponse"]
            write_json(result, changed)
        write_json(folder / "fault-records.json", records)
        failure = observe_rejection(lambda: run(folder, records), expected)
        results.append({"case": label, "control": "passed", "fault": case, "expected": expected, "signal": failure.signal,
                        "path": target.relative_to(folder).as_posix(), "source": baseline, "mutated": digest(target) if target.is_file() else None})
    return results


def renderer_fault_transport_control(args):
    label = "preserve-malformed-renderer-fault-dispatch"
    folder = args.out / label
    folder.mkdir()
    case = RenderFault(label, "sql-semantics-sqlite/src/Ir/Select.php", "unused", "unused-change",
                       {"independent": "known fixture"}, "meaning-mismatch", {"invalidConstructionRequest": True})
    run = SimpleNamespace(candidate=args.candidate, local=False, out=folder, tool_autoload=args.tool_autoload)

    def unexpected_probe(_input, response, _database):
        raise Failure("renderer-fault-transport-unexpected-valid-response", response)

    try:
        check_render_faults(run, None, [case], unexpected_probe, "renderer_fault_transport_control.php")
    except json.JSONDecodeError:
        pass
    else:
        raise Failure("renderer-fault-transport-control-missed")
    plan = folder / DISPATCH_PLAN
    failure = folder / DISPATCH_FAILURE
    observed = json.loads(failure.read_text())
    require(observed["phase"] == "healthy-request" and observed["exception"] == "JSONDecodeError"
            and observed["fault"]["case"] == label and observed["fault"]["request"] == case.request
            and observed["planSha256"] == digest(plan) and not (folder / "faults").exists(),
            "renderer-fault-transport-control-witness", observed)
    return {"case": label, "expected": "healthy-request", "signal": observed["phase"],
            "plan": {"path": plan.name, "sha256": digest(plan)},
            "failure": {"path": failure.name, "sha256": digest(failure)}}


def sqlite_preflight_control(args, distribution):
    original = args.root / "sqlite-fault-preflight/index.json"
    record = json.loads(original.read_text())
    verify_sqlite_preflight(record, args.candidate, distribution)
    domain = "sqlite-mutation-conflicts-v2.json"
    original_record = record["domains"][domain].pop()
    folder = args.out / "omitted-sqlite-fault-placement"
    folder.mkdir()
    target = folder / "index.json"
    write_json(target, record)
    fault = observe_rejection(lambda: verify_sqlite_preflight(record, args.candidate, distribution),
                              "bundle-sqlite-fault-preflight-source")
    (folder / "injected.diff").write_text("".join(difflib.unified_diff(
        original.read_text().splitlines(keepends=True), target.read_text().splitlines(keepends=True),
        "healthy/index.json", "mutant/index.json",
    )))
    return {"case": folder.name, "control": "passed", "original": digest(original), "mutated": digest(target),
            "signal": fault.signal, "omitted": {"domain": domain, **original_record}}


def native_schedule_control(args):
    workflow = TOOL.parents[2] / ".github/workflows/semantics-ir-release-matrix.yml"
    profile_contract = TOOL / "server-projection-v1.json"
    healthy = release_fixtures(workflow, profile_contract)
    original = workflow.read_text()
    fixture = next(match for match in RELEASE_ROW.finditer(original) if match.group(1, 2) == ("postgres", "16.6"))
    modified = original[:fixture.start()] + original[fixture.end():]
    folder = args.out / "omitted-native-launch-release"
    folder.mkdir()
    target = folder / workflow.name
    target.write_text(modified)
    fault = observe_rejection(lambda: release_fixtures(target, profile_contract), "native-launch-fixture-release-set")
    require(fault.detail == [("postgres", "16.6")], "native-launch-control-wrong-profile", fault.detail)
    write_json(folder / "healthy.json", list(healthy.values()))
    (folder / "injected.diff").write_text("".join(difflib.unified_diff(
        original.splitlines(keepends=True), modified.splitlines(keepends=True),
        "before/" + workflow.name, "after/" + workflow.name,
    )))
    return {"case": folder.name, "control": "passed", "original": digest(workflow), "mutated": digest(target),
            "signal": fault.signal, "omitted": fault.detail}


def native_workflow_controls(args):
    workflow = TOOL.parents[2] / WORKFLOW
    contract = json.loads((TOOL / WORKFLOW_CONTRACT).read_text())
    fixtures = release_fixtures(workflow, TOOL / "server-projection-v1.json")
    recursive = TOOL / "server-recursive-series-v2.json"
    require(digest(recursive) == CONTRACTS[recursive.name], "native-workflow-control-recursive-profile-digest")
    definition = json.loads(recursive.read_text())
    plan = {profile: list(release_jobs(profile, definition)) for profile in fixtures}
    original = workflow.read_text()
    verified = audit_workflow(workflow, plan)
    steps = workflow_steps(original)
    launch = next(step for step in steps if "/run_server_postgres_int4.py " in step)
    upload = next(step for step in steps if "/semantics-ir-postgres-int4-evidence/" in step)
    mutations = {
        "duplicate-native-comparison": (launch, launch + launch),
        "missing-native-comparison": (launch, ""),
        "narrowed-native-release-condition": (launch, launch.replace("matrix.engine == 'postgres'",
            "matrix.engine == 'postgres' && matrix.release != '16.6'")),
        "mismatched-native-upload-path": (upload, upload.replace("/semantics-ir-postgres-int4-evidence/",
            "/unrelated-evidence/")),
        "candidate-native-verifier-path": (launch, launch.replace("python3 trusted/", "python3 candidate/")),
    }
    require(mutations.keys() == {rule["id"] for rule in contract["faults"]}, "native-workflow-control-contract-coverage")
    healthy = args.out / "native-workflow-healthy.json"
    write_json(healthy, verified)
    results = []
    for rule in contract["faults"]:
        identifier = rule["id"]
        before, after = mutations[identifier]
        require(original.count(before) == 1 and before != after, "native-workflow-control-not-applied", identifier)
        modified = original.replace(before, after)
        folder = args.out / identifier
        folder.mkdir()
        target = folder / workflow.name
        target.write_text(modified)
        rejection = observe_rejection(lambda: audit_workflow(target, plan), rule["expected"])
        difference = folder / "injected.diff"
        difference.write_text("".join(difflib.unified_diff(
            original.splitlines(keepends=True), modified.splitlines(keepends=True),
            "trusted/" + WORKFLOW, "mutated/" + WORKFLOW,
        )))
        results.append({
            "case": identifier, "control": "passed", "original": digest(workflow), "mutated": digest(target),
            "expected": rule["expected"], "signal": rejection.signal, "detail": rejection.detail,
            "healthy": {"path": healthy.name, "sha256": digest(healthy)}, "checker": verified["checker"],
            "difference": {"path": difference.relative_to(args.out).as_posix(), "sha256": digest(difference)},
        })
    return results



def native_fixture_controls():
    observed = launch_recovery_controls()
    for outcome in observed:
        verify_fixture_recovery(outcome["observed"]["receipt"], outcome["case"])
    healthy = observed[0]["observed"]["receipt"]
    faults = {
        "reuse-original-fixture-name": ("retriedAs", healthy["failedLaunch"]["containerName"], "bundle-native-fixture-retry-boundary"),
        "omit-failed-launch-exit": ("failure", {**healthy["failedLaunch"]["failure"], "exitCode": 0}, "bundle-native-fixture-original-launch"),
        "claim-existing-fixture-was-absent": ("inspect", {**healthy["failedLaunch"]["inspect"], "exitCode": 0}, "bundle-native-fixture-retry-boundary"),
    }
    for name, (field, value, signal) in faults.items():
        changed = copy.deepcopy(healthy)
        changed["failedLaunch"][field] = value
        rejected = observe_rejection(lambda: verify_fixture_recovery(changed, name), signal)
        observed.append({"case": name, "signal": rejected.signal, "altered": changed})
    return observed


def native_boundary_inventory_control(args, commit):
    name = "omit-native-boundary-procedure"
    folder = args.out / name
    folder.mkdir()
    evidence = Evidence(folder.resolve(), commit, None, TOOL)
    healthy, removed = folder / "healthy.json", folder / "mutant.json"
    status = "provisional-native-construction-shape"
    procedures = native_boundary_procedures(TOOL)
    write_json(healthy, {"candidate": commit, "status": status, "procedures": procedures})
    evidence.artifact(healthy.name, status)
    altered = dict(procedures)
    del altered["value_storage.php"]
    write_json(removed, {"candidate": commit, "status": status, "procedures": altered})
    failure = observe_rejection(lambda: evidence.artifact(removed.name, status), "bundle-native-boundary-procedure-inventory")
    return {"case": name, "signal": failure.signal, "healthy": digest(healthy), "mutant": digest(removed),
            "omittedProcedure": "value_storage.php"}


def assurance_revision_case(args, name, filename, registry_name, field):
    folder = args.out / name
    before = folder / "previous" / "packages/sql-semantics/assurance"
    after = folder / "current" / "packages/sql-semantics/assurance"
    shutil.copytree(TOOL, before, copy_function=shutil.copyfile)
    shutil.copytree(TOOL, after, copy_function=shutil.copyfile)
    healthy = review(before, after)
    require(healthy["status"] == "identical-measured-inputs" and not healthy["observedPotentialReductions"],
            "revision-control-healthy-mismatch")
    source = before / filename
    target = after / source.name
    altered = json.loads(source.read_text())
    keys = field.split("/")
    parent = altered if len(keys) == 1 else altered[keys[0]]
    previous = parent[keys[-1]]
    parent[keys[-1]] -= 1
    write_json(target, altered)
    tampered = observe_rejection(lambda: review(before, after), "revision-contract-digest")
    registry = after / registry_name
    text = registry.read_text()
    require(text.count(digest(source)) == 1, "revision-control-registry-anchor")
    registry.write_text(text.replace(digest(source), digest(target)))
    measured = review(before, after)
    family = filename.rsplit("-v", 1)[0]
    if registry_name == "compare_assurance_revisions.py":
        family = "native-schedule/" + family
    expected = [{"family": family, "field": field,
                 "before": previous, "after": previous - 1}]
    require(measured["status"] == "review-required" and measured["observedPotentialReductions"] == expected,
            "revision-control-reduction-not-flagged", measured["observedPotentialReductions"])
    write_json(folder / "healthy.json", healthy)
    write_json(folder / "observed.json", measured)
    (folder / "injected.diff").write_text("".join(difflib.unified_diff(
        source.read_text().splitlines(keepends=True), target.read_text().splitlines(keepends=True),
        "previous/" + source.name, "current/" + target.name,
    )) + "".join(difflib.unified_diff(text.splitlines(keepends=True), registry.read_text().splitlines(keepends=True),
                                     "previous/" + registry_name, "current/" + registry_name)))
    return {"case": folder.name, "control": "passed", "original": digest(source), "mutated": digest(target),
            "digestMismatch": tampered.signal, "expected": expected, "observed": measured["observedPotentialReductions"]}


def reduction_controls(args):
    from counterexample_reduction import record

    original = {"columns": [[["integer", "1"], "a"], [["integer", "2"], "b"]]}
    smaller = {"columns": [original["columns"][0]]}
    signal = "meaning-mismatch"

    def test_proposals(_):
        yield smaller

    def failed_generator(_):
        raise RuntimeError("The controlled proposal generator failed.")

    controls = (
        ("confirm-smaller-counterexample", "reproduced", test_proposals),
        ("reject-unconstructed-counterexample", "no-confirmed-smaller-input", test_proposals),
        ("reject-flaky-counterexample", "no-confirmed-smaller-input", test_proposals),
        ("preserve-counterexample-on-reducer-failure", "reducer-failure", failed_generator),
    )
    observed = []
    for name, expectation, proposals in controls:
        folder = args.out / name
        folder.mkdir()
        path = folder / "counterexample.json"
        visits = 0

        def probe(item):
            nonlocal visits
            require(path.is_file(), "missing-original-during-reduction")
            if item == smaller:
                visits += 1
                if name == "reject-unconstructed-counterexample":
                    raise Failure("positive-construction-failed", "Missing premise.")
                if name == "reject-flaky-counterexample" and visits == 2:
                    return
            raise Failure(signal, {"expected": [1], "measured": [0]})

        record(path, {"original": original, "failure": "Known observation difference."}, signal, proposals, probe)
        reduction = path.with_suffix(".reduction.json")
        result = json.loads(reduction.read_text())
        require(json.loads(path.read_text())["original"] == original and result["originalDigest"] == digest(path)
                and result["status"] == expectation and result["shrunk"] == (smaller if expectation == "reproduced" else None),
                "reduction-control-mismatch", result)
        observed.append({"case": name, "control": "passed", "original": digest(path), "reduction": digest(reduction),
                         "expected": expectation, "observed": result["status"]})
    return observed


def native_reduction_controls(args):
    from native_counterexample_reduction import CONTROL_RESULTS, record

    columns = [[["slot", 0], f"field{position}"] for position in range(8)]
    before = {"status": "rows", "fields": [["field", "int4"]], "rows": [[3]]}
    other_rows = {"expected": before, "observed": {**before, "rows": [[7]]}}
    other_metadata = {"expected": before, "observed": {**before, "fields": [["field", "int8"]]}}
    base = {"dialect": "postgres", "release": "pg-17.2", "source": {"schema": "public", "table": "accounts", "columns": ["id"]},
            "where": None}
    observed = []
    for name, expectation in CONTROL_RESULTS.items():
        long = name == "native-reduce-proposal-budget"
        input_ = {**base, "columns": columns if long else columns[:2]}
        if name == "native-reduce-ineligible":
            input_["orderBy"] = [[1, False, False]]
        directory = args.out / name
        directory.mkdir()
        path = directory / "counterexample.json"
        shorter = {**input_, "columns": columns[1:2]}
        visits = 0

        def probe(item):
            nonlocal visits
            require(path.is_file(), "missing-native-original-during-reduction")
            if item == input_:
                raise Failure("meaning-mismatch", other_rows)
            if item != shorter:
                return
            visits += 1
            if name == "native-reduce-rejected-construction":
                raise Failure("positive-construction-failed")
            if name == "native-reduce-different-comparison":
                raise Failure("meaning-mismatch", other_metadata)
            if name == "native-reduce-flaky" and visits == 2:
                return
            raise Failure("meaning-mismatch", other_rows)

        record(path, {"original": input_, "signal": "meaning-mismatch", "detail": other_rows}, input_, probe)
        reduction = path.with_suffix(".reduction.json")
        result = json.loads(reduction.read_text())
        require(result["status"] == expectation and result["originalDigest"] == digest(path)
                and json.loads(path.read_text())["original"] == input_
                and result["shrunk"] == (shorter if expectation == "reproduced" else None),
                "native-reduction-control-mismatch", result)
        outcomes = [entry["outcome"] for entry in result["attempts"]]
        if long:
            require(outcomes == ["meaning-mismatch", *["passed"] * 4], "native-reduction-control-budget-mismatch", outcomes)
        if name == "native-reduce-ineligible":
            require(outcomes == [], "native-reduction-control-ineligible-probe", outcomes)
        if name == "native-reduce-different-comparison":
            require("different-observation-failure" in outcomes, "native-reduction-control-failure-family", outcomes)
        observed.append({"case": name, "control": "passed", "original": digest(path), "reduction": digest(reduction),
                         "expected": expectation, "observed": result["status"]})
    return observed


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    for option in ("root", "candidate", "tool-autoload", "sqlite-library", "out"):
        parser.add_argument("--" + option, type=Path, required=True)
    args = parser.parse_args()
    args.out = args.out.resolve()
    args.candidate = args.candidate.resolve()
    require(args.candidate != args.out and args.candidate not in args.out.parents, "evidence-directory-inside-candidate")
    args.out.mkdir(parents=True)
    commit, distribution = candidate_distribution(args.candidate)
    result = (evidence_controls(args) + [distribution_control(args, distribution)] + identity_controls(args)
              + storage_controls(args) + dispatch_controls(args) + publication_controls(args) + observer_controls(args) + scope_controls(args, distribution)
              + [declaration_control(args), grammar_control(args), native_schedule_control(args)]
              + [assurance_revision_case(args, "reduced-assurance-revision", "postgres-int4-case-v1.json",
                                         "scope.py", "exploration/casesPerSeed"),
                 assurance_revision_case(args, "reduced-evidence-revision", "mysql-signed-comparison-widths-evidence-v2.json",
                                         "compare_assurance_revisions.py", "negativeCount")]
              + native_workflow_controls(args) + native_fixture_controls() + [native_boundary_inventory_control(args, commit)]
              + [sqlite_preflight_control(args, distribution)] + fault_replay_controls(args, distribution)
              + [renderer_fault_transport_control(args)] + reduction_controls(args) + native_reduction_controls(args))
    require(candidate_distribution(args.candidate, commit)[1] == distribution, "candidate-changed-during-controls")
    write_json(args.out / "index.json", {"status": "verified", "candidate": commit,
                                        "candidateIdentityContract": IDENTITY_CONTRACT, "controls": result})


if __name__ == "__main__":
    main()
