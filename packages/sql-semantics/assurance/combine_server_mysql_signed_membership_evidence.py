"""Independently replay all fixed native MySQL signed membership profiles."""

from combine_server_join_evidence import main
import run_server_mysql_signed_membership

if __name__ == "__main__":
    main(run_server_mysql_signed_membership)
