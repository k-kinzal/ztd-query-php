"""Replay the exact named PostgreSQL partition index matrix."""

from combine_server_join_evidence import main
import run_server_postgres_reindex_partition_index as domain


if __name__ == "__main__":
    main(domain)
