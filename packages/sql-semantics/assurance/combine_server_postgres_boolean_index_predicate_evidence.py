"""Replay checked compound partial-index evidence for both pinned PostgreSQL releases."""

from combine_server_join_evidence import main
import run_server_postgres_boolean_index_predicate

if __name__ == "__main__":
    main(run_server_postgres_boolean_index_predicate)
