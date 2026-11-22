"""Replay the PostgreSQL Boolean truth test release matrix."""

import run_server_postgres_boolean_tests
from combine_server_join_evidence import main


if __name__ == "__main__":
    main(run_server_postgres_boolean_tests)
