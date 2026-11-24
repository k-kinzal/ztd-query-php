"""Replay the fixed PostgreSQL named exclusion-index rebuild matrix."""

from combine_server_join_evidence import main
import run_server_postgres_reindex_exclusion_index as domain


if __name__ == "__main__":
    main(domain)
