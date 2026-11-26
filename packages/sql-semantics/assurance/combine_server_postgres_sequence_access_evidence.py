"""Replay both exact PostgreSQL sequence access profiles."""

from combine_server_join_evidence import main
import run_server_postgres_sequence_access


if __name__ == "__main__":
    main(run_server_postgres_sequence_access)
