"""Reconcile exact PostgreSQL sequence option observations."""

from combine_server_join_evidence import main
import run_server_postgres_reconfigure_sequence

if __name__ == "__main__":
    main(run_server_postgres_reconfigure_sequence)
