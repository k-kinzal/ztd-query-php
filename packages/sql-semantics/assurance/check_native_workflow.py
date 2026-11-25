"""Measure the fixed native GitHub Actions routing against the independent launch plan."""

import argparse
from importlib import import_module
import json
import re
from pathlib import Path

from native_postgres_fixture import (
    DOMAINS as FIXTURE_DOMAINS, ENV as FIXTURE_CONTAINER, fixed_contract, fixed_storage, observe_storage,
    storage_option, verify_storage,
)
from native_schedule import release_subset
from run import TOOL, digest, require
from scope import CONTRACTS


CONTRACT_NAME = "native-workflow-routing-v2.json"
CONTRACT = "00b0b9f2073190d95b32dbe0a19eb9b2c2253e24c0f5efc8995c9fde1da849dc"
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


def verify_domain_replay_profile(domain, runner, planned):
    """Require the independently registered subset at both execution and replay."""
    if release_subset(domain) is None:
        return None
    module = import_module(Path(runner).stem)
    _, acceptance = module.specifications()
    profiles = sorted((engine, release) for engine, scope in acceptance.items() for release in scope["releases"])
    require(profiles == sorted(planned), "native-workflow-replay-profile-selection",
            {"domain": domain, "planned": sorted(planned), "replay": profiles})
    return [list(profile) for profile in profiles]


def audit(workflow, plan):
    document = TOOL / CONTRACT_NAME
    require(digest(document) == CONTRACT, "native-workflow-contract-mismatch")
    fixed_contract()
    content = workflow.read_text()
    option = storage_option()
    declaration = "          ${{ matrix.engine == 'postgres' && '--tmpfs " + option + "' || '' }}"
    database = re.search(r"(?ms)^    services:\n      database:\n(.*?)^    steps:\n", content)
    require(database is not None and "        options: >-\n" + declaration + "\n" in database[1]
            and content.count(option) == 1,
            "native-workflow-postgres-fixture-storage")
    authorized_steps = steps(content)
    checks = [step for step in authorized_steps if "/check_native_workflow.py" in step]
    require(len(checks) == 1 and field(checks[0], "if") is None
            and field(checks[0], "ENGINE", 10) == "${{ matrix.engine }}"
            and field(checks[0], "FIXTURE_CONTAINER", 10) == "${{ job.services.database.id }}"
            and field(checks[0], "run") == f'python3 {WORKER}/check_native_workflow.py --service "$ENGINE" "$FIXTURE_CONTAINER"',
            "native-workflow-fixture-observation")
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
    grants = "postgres_schema_sequence_grants"
    acl = "postgres_sequence_access"
    if any(grants in {domain for domain, _, _ in jobs} for jobs in plan.values()):
        require(all(acl in names and names.index(acl) < names.index(grants)
                    for jobs in plan.values() if grants in (names := [domain for domain, _, _ in jobs])),
                "native-schedule-shared-postgres-role-order")
        require(authorized_steps.index(runners[f"run_server_{acl}.py"][0])
                < authorized_steps.index(runners[f"run_server_{grants}.py"][0]),
                "native-workflow-shared-postgres-role-order")
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
        replay = verify_domain_replay_profile(domain, runner, compared)
        routes[domain] = {"runner": runner, "output": out, "artifact": artifact, "profiles": [list(p) for p in compared],
                          **({"fixtureIsolation": isolation} if isolation else {}),
                          **({"replayProfiles": replay} if replay is not None else {})}
    return {"contract": CONTRACT, "checker": digest(Path(__file__)), "workflow": digest(workflow),
            "postgresFixtureStorage": fixed_storage(), "routes": routes}


def main():
    from run_native_matrix import docker, release_fixtures, release_jobs

    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("workflow", nargs="?", type=Path, default=TOOL.parents[2] / WORKFLOW)
    parser.add_argument("--service", nargs=2, metavar=("ENGINE", "CONTAINER"))
    args = parser.parse_args()
    fixtures = release_fixtures(args.workflow, TOOL / "server-projection-v1.json")
    recursive = TOOL / "server-recursive-series-v2.json"
    require(digest(recursive) == CONTRACTS[recursive.name], "native-launch-recursive-profile-digest")
    definition = json.loads(recursive.read_text())
    report = audit(args.workflow, {profile: list(release_jobs(profile, definition)) for profile in fixtures})
    if args.service:
        engine, container = args.service
        require(engine in {"mysql", "postgres"} and re.fullmatch(r"[a-f0-9]{64}", container),
                "native-workflow-service-identity")
        if engine == "postgres":
            observed = observe_storage(docker, container)
            verify_storage(observed, "workflow/postgres")
            report["observedPostgresFixtureStorage"] = observed
    print(json.dumps(report, sort_keys=True))


if __name__ == "__main__":
    main()
