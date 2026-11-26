"""Independently replay the native MySQL index removal evidence."""

from combine_server_join_evidence import main
import run_server_mysql_drop_index

if __name__ == "__main__":
    main(run_server_mysql_drop_index)
