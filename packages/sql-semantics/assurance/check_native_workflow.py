"""Measure the fixed native GitHub Actions routing against the independent launch plan."""

import argparse
import json
import re
from pathlib import Path

from native_postgres_fixture import DOMAINS as FIXTURE_DOMAINS, ENV as FIXTURE_CONTAINER, fixed_contract
from run import TOOL, digest, require
from scope import CONTRACTS


CONTRACT_NAME = "native-workflow-routing-v1.json"
CONTRACT = "d9b89a29b87927a986d0665675abf4d049ae8702efe2721fb227f50084767778"
WORKFLOW = ".github/workflows/semantics-ir-release-matrix.yml"
WORKER = "trusted/packages/sql-semantics/assurance"
TOOL_AUTOLOAD = "trusted/packages/sql-semantics-sqlite/vendor/autoload.php"
ARTIFACT_ACTION = "actions/upload-artifact@043fb46d1a93c77aae656e7c1c64a875d1fc6a0a"
CHECKOUT_ACTION = "actions/checkout@3d3c42e5aac5ba805825da76410c181273ba90b1"
RELEASE = r"[0-9]+\.[0-9]+(?:\.[0-9]+)?"
PREDICATE = re.compile(rf"matrix\.(engine|release) (==|!=) '({RELEASE}|mysql|postgres)'")
ALTERNATIVES = re.compile(rf"\(matrix\.release == '({RELEASE})' \|\| matrix\.release == '({RELEASE})'\)")
RUNNER = re.compile(r"\b(run_server(?:_\w+)?\.py|check_recursive_availability\.py)\b")


def steps(workflow):
    match = re.search(r"(?ms)^  comparison:\n(.*?)(?=^  [A-Za-z][\w-]*:\n|\Z)", workflow)
    require(match is not None, "native-workflow-comparison-job")
    job = match[1]
    require(re.search(r"(?m)^    if: github\.ref == 'refs/heads/main'$", job) and
            not re.search(r"(?m)^\s+continue-on-error:|^    defaults:", job) and
            not re.search(r"(?m)^defaults:", workflow), "native-workflow-comparison-job")
    return re.findall(r"(?ms)^      - name: .*?(?=^      - name: |\Z)", job)


def field(step, key, indent=8):
    entries = re.findall(rf"(?m)^ {{{indent}}}{key}: (.+)$", step)
    require(len(entries) <= 1, "native-workflow-duplicate-field", [step.splitlines()[0].strip(), key])
    return entries[0] if entries else None


def predicate(condition, profile):
    group = ALTERNATIVES.fullmatch(condition)
    if group:
        return profile[1] in group.groups()
    match = PREDICATE.fullmatch(condition)
    require(match is not None, "native-workflow-condition-syntax", condition)
    key, operator, value = match.groups()
    selected = profile[0 if key == "engine" else 1]
    return (selected == value) == (operator == "==")


def selected_profiles(step, expected_status, profiles):
    condition = field(step, "if")
    if condition is None:
        require(expected_status == "success()", "native-workflow-condition-status", step.splitlines()[0])
        return sorted(profiles)
    if condition.startswith("${{ ") and condition.endswith(" }}"):
        condition = condition[4:-3]
    status, *conditions = condition.split(" && ")
    require(status == expected_status, "native-workflow-condition-status", condition)
    return sorted(profile for profile in profiles if all(predicate(test, profile) for test in conditions))


def route_paths(domain):
    slug = domain.replace("_", "-")
    out = "semantics-ir-evidence" if domain == "projection" else f"semantics-ir-{slug}-evidence"
    prefix = "semantics-ir" if domain == "projection" else f"native-{slug}"
    return out, prefix + "-${{ matrix.engine }}-${{ matrix.release }}"


def route_index(authorized_steps):
    runners, uploads = {}, {}
    for step in authorized_steps:
        command = step.partition("        run: |\n")[2]
        match = RUNNER.search(command)
        if match:
            identifier = match[1]
            require(identifier not in runners, "native-workflow-duplicate-runner", identifier)
            runners[identifier] = (step, " ".join(command.replace("\\\n", " ").split()))
        action = field(step, "uses")
        if action and action.startswith("actions/upload-artifact@"):
            name = field(step, "name", 10)
            require(action == ARTIFACT_ACTION and name, "native-workflow-artifact-action", step.splitlines()[0])
            require(name not in uploads, "native-workflow-duplicate-artifact", name)
            uploads[name] = step
    return runners, uploads


def audit(workflow, plan):
    document = TOOL / CONTRACT_NAME
    require(digest(document) == CONTRACT, "native-workflow-contract-mismatch")
    fixed_contract()
    authorized_steps = steps(workflow.read_text())
    for ref, role in (("${{ github.workflow_sha }}", "trusted"), ("${{ inputs.candidate }}", "candidate")):
        checkouts = [step for step in authorized_steps if field(step, "path", 10) == role]
        require(len(checkouts) == 1 and field(checkouts[0], "ref", 10) == ref
                and field(checkouts[0], "persist-credentials", 10) == "false"
                and field(checkouts[0], "uses") == CHECKOUT_ACTION,
                "native-workflow-checkout-authority", role)
    expected = {}
    for profile, jobs in plan.items():
        for domain, runner, _ in jobs:
            expected.setdefault((domain, runner), []).append(profile)
    runners, uploads = route_index(authorized_steps)
    expected_runners = {runner for _, runner in expected}
    expected_uploads = {route_paths(domain)[1] for domain, _ in expected}
    require(runners.keys() == expected_runners, "native-workflow-runner-inventory", sorted(runners.keys() ^ expected_runners))
    require(uploads.keys() == expected_uploads, "native-workflow-artifact-inventory", sorted(uploads.keys() ^ expected_uploads))
    routes = {}
    for (domain, runner), profiles in sorted(expected.items()):
        out, artifact = route_paths(domain)
        step, command = runners[runner]
        upload = uploads[artifact]
        options = '--release "$RELEASE"' if domain == "recursive_unavailable" else '--engine "$ENGINE" --release "$RELEASE"'
        correct = (f"python3 {WORKER}/{runner} {options} --candidate candidate --tool-autoload {TOOL_AUTOLOAD} "
                   f'--out "$RUNNER_TEMP/{out}"')
        require(command == correct, "native-workflow-runner-command", {"domain": domain, "observed": command, "expected": correct})
        require(field(step, "working-directory") is None and field(step, "shell") is None
                and field(step, "ENGINE", 10) == "${{ matrix.engine }}" and field(step, "RELEASE", 10) == "${{ matrix.release }}",
                "native-workflow-runner-environment", domain)
        isolation = field(step, FIXTURE_CONTAINER, 10)
        expected_isolation = "${{ job.services.database.id }}" if domain in FIXTURE_DOMAINS else None
        require(isolation == expected_isolation, "native-workflow-postgres-fixture-route", domain)
        require(field(upload, "path", 10) == "${{ runner.temp }}/" + out + "/", "native-workflow-artifact-path", domain)
        run_status = "success()" if domain == "projection" else "!cancelled()"
        compared = selected_profiles(step, run_status, plan)
        preserved = selected_profiles(upload, "always()", plan)
        require(compared == sorted(profiles) == preserved, "native-workflow-route-selection",
                {"domain": domain, "expected": sorted(profiles), "compared": compared, "preserved": preserved})
        routes[domain] = {"runner": runner, "output": out, "artifact": artifact, "profiles": [list(p) for p in compared],
                          **({"fixtureIsolation": isolation} if isolation else {})}
    return {"contract": CONTRACT, "checker": digest(Path(__file__)), "workflow": digest(workflow), "routes": routes}


def main():
    from run_native_matrix import release_fixtures, release_jobs

    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("workflow", nargs="?", type=Path, default=TOOL.parents[2] / WORKFLOW)
    args = parser.parse_args()
    fixtures = release_fixtures(args.workflow, TOOL / "server-projection-v1.json")
    recursive = TOOL / "server-recursive-series-v2.json"
    require(digest(recursive) == CONTRACTS[recursive.name], "native-launch-recursive-profile-digest")
    definition = json.loads(recursive.read_text())
    print(json.dumps(audit(args.workflow, {profile: list(release_jobs(profile, definition)) for profile in fixtures}), sort_keys=True))


if __name__ == "__main__":
    main()
