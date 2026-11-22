"""Exercise fail-closed evidence, runtime package, and readonly-storage checks."""

import argparse
import copy
import difflib
import json
import shutil
import subprocess
from pathlib import Path

from constructor_trace import check_scope
from evidence_transcript import events, load_replay_contract
from replay_sqlite_evidence import replay
from run import IDENTITY_CONTRACT, TOOL, PACKAGES, Failure, candidate_distribution, digest, manifest, require, shape, write_json
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
    return records


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


def storage_controls(args):
    results = []
    for dialect in ("sqlite", "mysql", "postgres"):
        shape(args, dialect=dialect)
        relative = Path(f"sql-semantics-{dialect}/src/Ir/TableSource.php")
        source = args.candidate / "packages" / relative
        text = source.read_text()
        original = "$this->columns = $names;"
        require(text.count(original) == 1, "storage-control-anchor-mismatch", dialect)
        for name, replacement, detail in (
            ("store-unchecked-list", "$this->columns = $columns;", "List was not assigned from one checked local"),
            ("rewrite-checked-list", "[$names] = [$columns];\n        $this->columns = $names;", "List was not assigned from one checked local"),
        ):
            overlay = args.out / name / dialect
            target = overlay / relative
            target.parent.mkdir(parents=True)
            mutated = text.replace(original, replacement, 1)
            target.write_text(mutated)
            (overlay / "injected.diff").write_text("".join(difflib.unified_diff(
                text.splitlines(keepends=True), mutated.splitlines(keepends=True), f"a/{relative}", f"b/{relative}",
            )))
            failure = observe_rejection(lambda: shape(args, overlay, dialect), "shape-mismatch")
            require(detail in failure.detail["message"], "wrong-storage-control-detail", failure.detail)
            results.append({
                "case": name, "dialect": dialect, "control": "passed", "original": digest(source),
                "mutated": digest(target), "expected": "shape-mismatch", "signal": failure.signal, "detail": failure.detail,
            })
    return results


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
        write_json(directory / "index.json", {"policy": OBSERVER_CONTRACT, "engine": database.version, "controls": result})
        return result
    finally:
        database.close(database.db)


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
              + storage_controls(args) + publication_controls(args) + observer_controls(args) + scope_controls(args, distribution))
    require(candidate_distribution(args.candidate, commit)[1] == distribution, "candidate-changed-during-controls")
    write_json(args.out / "index.json", {"status": "verified", "candidate": commit,
                                        "candidateIdentityContract": IDENTITY_CONTRACT, "controls": result})


if __name__ == "__main__":
    main()
