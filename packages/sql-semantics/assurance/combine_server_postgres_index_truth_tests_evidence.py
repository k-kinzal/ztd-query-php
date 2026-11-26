"""Replay PostgreSQL index truth test evidence across the pinned releases."""

from combine_server_join_evidence import main
import run_server_postgres_index_truth_tests

if __name__ == "__main__":
    main(run_server_postgres_index_truth_tests)
