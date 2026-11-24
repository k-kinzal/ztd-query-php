"""Reconcile exact PostgreSQL sequence logging observations."""

from combine_server_join_evidence import main
import run_server_postgres_sequence_logging

if __name__ == "__main__":
    main(run_server_postgres_sequence_logging)
