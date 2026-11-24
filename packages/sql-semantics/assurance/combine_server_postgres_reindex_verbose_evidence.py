"""Replay the fixed PostgreSQL rebuild reporting matrix."""

from combine_server_join_evidence import main
import run_server_postgres_reindex_verbose as domain


if __name__ == "__main__":
    main(domain)
