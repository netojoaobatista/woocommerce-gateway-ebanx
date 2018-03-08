<?php

require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

/**
 * Class WC_EBANX_Database
 */
class WC_EBANX_Database {
	/**
	 * Table names
	 */
	public static function tables() {
		global $wpdb;

		return [
			'logs' => $wpdb->prefix . 'ebanx_logs',
		];
	}

	/**
	 * Migrate tables
	 */
	public static function migrate() {
		self::create_log_table();
	}

	/**
	 * Creates table used to store logs
	 */
	private static function create_log_table() {
		global $wpdb;

		$table_name = self::tables()['logs'];
		$charset_collate = $wpdb->get_charset_collate();

		if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE '%s'", $table_name ) ) ) {
			return;
		}

		$sql = "CREATE TABLE $table_name (
			id int NOT NULL AUTO_INCREMENT,
			time datetime NOT NULL,
			event varchar(150) NOT NULL,
			log blob NOT NULL,
			UNIQUE KEY id (id)
		) $charset_collate";

		dbDelta( $sql );
	}

	/**
	 * Wrapper for `$wpdb` `insert` method, getting table name from `tables` method
	 */
	public static function insert( $table, $data ) {
		global $wpdb;

		return $wpdb->insert( self::tables()[$table], $data );
	}

	/**
	 * Truncate table
	 * 
	 * @var string $table
	 */
	public static function truncate( $table ) {
		global $wpdb;

		$wpdb->query( 'TRUNCATE TABLE ' . self::tables()[$table] );
	}

	/**
	 * Select all columns from $table
	 * 
	 * Commonly used to get all logs before truncate table
	 * 
	 * @var string $table
	 */
	public static function select( $table ) {
		global $wpdb;

		return $wpdb->get_results( 'SELECT * FROM ' . self::tables()[$table] );
	}
}
