<?php
/**
 * Persistence layer for donation records.
 *
 * @package Matrix_Donations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Matrix_Donations_Donations_Repository {

	/**
	 * Get table name.
	 *
	 * @return string
	 */
	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . 'matrix_donations';
	}

	/**
	 * Create or update donations table.
	 *
	 * @return void
	 */
	public static function create_table() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table_name      = self::table_name();
		$charset_collate = $wpdb->get_charset_collate();
		$sql             = "CREATE TABLE {$table_name} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			donation_type VARCHAR(30) NOT NULL DEFAULT 'single',
			donation_mode VARCHAR(10) NOT NULL DEFAULT 'test',
			frequency VARCHAR(20) NOT NULL DEFAULT '',
			currency VARCHAR(10) NOT NULL DEFAULT 'eur',
			amount_cents BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			status VARCHAR(20) NOT NULL DEFAULT 'pending',
			donor_email VARCHAR(190) NOT NULL DEFAULT '',
			donor_first_name VARCHAR(120) NOT NULL DEFAULT '',
			donor_last_name VARCHAR(120) NOT NULL DEFAULT '',
			stripe_session_id VARCHAR(255) NOT NULL DEFAULT '',
			stripe_payment_intent_id VARCHAR(255) NOT NULL DEFAULT '',
			stripe_subscription_id VARCHAR(255) NOT NULL DEFAULT '',
			stripe_event_id VARCHAR(255) NOT NULL DEFAULT '',
			metadata LONGTEXT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY uniq_stripe_session_id (stripe_session_id),
			UNIQUE KEY uniq_stripe_event_id (stripe_event_id),
			KEY idx_donor_email (donor_email),
			KEY idx_status (status),
			KEY idx_created_at (created_at),
			KEY idx_mode_status_created (donation_mode,status,created_at),
			KEY idx_subscription (stripe_subscription_id)
		) {$charset_collate};";
		dbDelta( $sql );
	}

	/**
	 * Save pending donation row when checkout session is created.
	 *
	 * @param array $data Donation row data.
	 * @return int|false
	 */
	public static function insert_pending_donation( $data ) {
		global $wpdb;

		$table_name = self::table_name();
		$inserted   = $wpdb->insert(
			$table_name,
			array(
				'donation_type'      => sanitize_text_field( $data['donation_type'] ?? 'single' ),
				'donation_mode'      => sanitize_text_field( $data['donation_mode'] ?? Matrix_Donations_Settings::get_mode() ),
				'frequency'          => sanitize_text_field( $data['frequency'] ?? '' ),
				'currency'           => sanitize_text_field( strtolower( $data['currency'] ?? 'eur' ) ),
				'amount_cents'       => absint( $data['amount_cents'] ?? 0 ),
				'status'             => sanitize_text_field( $data['status'] ?? 'pending' ),
				'donor_email'        => sanitize_email( $data['donor_email'] ?? '' ),
				'donor_first_name'   => sanitize_text_field( $data['donor_first_name'] ?? '' ),
				'donor_last_name'    => sanitize_text_field( $data['donor_last_name'] ?? '' ),
				'stripe_session_id'  => sanitize_text_field( $data['stripe_session_id'] ?? '' ),
				'metadata'           => wp_json_encode( $data['metadata'] ?? array() ),
			),
			array( '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $inserted ) {
			return false;
		}
		return (int) $wpdb->insert_id;
	}

	/**
	 * Upsert a donation by Stripe session ID.
	 *
	 * @param array $data Donation data.
	 * @return void
	 */
	public static function upsert_by_session( $data ) {
		global $wpdb;
		$table_name  = self::table_name();
		$session_id  = sanitize_text_field( $data['stripe_session_id'] ?? '' );
		if ( '' === $session_id ) {
			return;
		}

		$existing_row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE stripe_session_id = %s LIMIT 1",
				$session_id
			),
			ARRAY_A
		);
		$existing_id = isset( $existing_row['id'] ) ? (int) $existing_row['id'] : 0;

		$row = array(
			'donation_type'           => sanitize_text_field( $data['donation_type'] ?? 'single' ),
			'donation_mode'           => sanitize_text_field( $data['donation_mode'] ?? Matrix_Donations_Settings::get_mode() ),
			'frequency'               => sanitize_text_field( $data['frequency'] ?? '' ),
			'currency'                => sanitize_text_field( strtolower( $data['currency'] ?? 'eur' ) ),
			'amount_cents'            => absint( $data['amount_cents'] ?? 0 ),
			'status'                  => sanitize_text_field( $data['status'] ?? 'pending' ),
			'donor_email'             => sanitize_email( $data['donor_email'] ?? '' ),
			'donor_first_name'        => sanitize_text_field( $data['donor_first_name'] ?? '' ),
			'donor_last_name'         => sanitize_text_field( $data['donor_last_name'] ?? '' ),
			'stripe_session_id'       => $session_id,
			'stripe_payment_intent_id'=> sanitize_text_field( $data['stripe_payment_intent_id'] ?? '' ),
			'stripe_subscription_id'  => sanitize_text_field( $data['stripe_subscription_id'] ?? '' ),
			'stripe_event_id'         => sanitize_text_field( $data['stripe_event_id'] ?? '' ),
			'metadata'                => wp_json_encode( $data['metadata'] ?? array() ),
		);
		$formats = array( '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' );

		if ( $existing_id ) {
			// Preserve known donor/session details when a webhook payload omits them.
			if ( '' === trim( (string) $row['donor_first_name'] ) && ! empty( $existing_row['donor_first_name'] ) ) {
				$row['donor_first_name'] = sanitize_text_field( (string) $existing_row['donor_first_name'] );
			}
			if ( '' === trim( (string) $row['donor_last_name'] ) && ! empty( $existing_row['donor_last_name'] ) ) {
				$row['donor_last_name'] = sanitize_text_field( (string) $existing_row['donor_last_name'] );
			}
			if ( '' === trim( (string) $row['donor_email'] ) && ! empty( $existing_row['donor_email'] ) ) {
				$row['donor_email'] = sanitize_email( (string) $existing_row['donor_email'] );
			}
			if ( '' === trim( (string) $row['stripe_payment_intent_id'] ) && ! empty( $existing_row['stripe_payment_intent_id'] ) ) {
				$row['stripe_payment_intent_id'] = sanitize_text_field( (string) $existing_row['stripe_payment_intent_id'] );
			}
			if ( '' === trim( (string) $row['stripe_subscription_id'] ) && ! empty( $existing_row['stripe_subscription_id'] ) ) {
				$row['stripe_subscription_id'] = sanitize_text_field( (string) $existing_row['stripe_subscription_id'] );
			}

			$wpdb->update(
				$table_name,
				$row,
				array( 'id' => (int) $existing_id ),
				$formats,
				array( '%d' )
			);
			return;
		}

		$wpdb->insert( $table_name, $row, $formats );
	}

	/**
	 * Find donation row by Stripe session ID.
	 *
	 * @param string $session_id Stripe checkout session ID.
	 * @return array|null
	 */
	public static function get_by_session_id( $session_id ) {
		$session_id = sanitize_text_field( $session_id );
		if ( '' === $session_id ) {
			return null;
		}

		global $wpdb;
		$table_name = self::table_name();
		$row        = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE stripe_session_id = %s LIMIT 1",
				$session_id
			),
			ARRAY_A
		);
		return is_array( $row ) ? $row : null;
	}

	/**
	 * Find donation row by Stripe subscription ID.
	 *
	 * @param string $subscription_id Stripe subscription ID.
	 * @return array|null
	 */
	public static function get_by_subscription_id( $subscription_id ) {
		$subscription_id = sanitize_text_field( $subscription_id );
		if ( '' === $subscription_id ) {
			return null;
		}

		global $wpdb;
		$table_name = self::table_name();
		$row        = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE stripe_subscription_id = %s ORDER BY created_at DESC LIMIT 1",
				$subscription_id
			),
			ARRAY_A
		);
		return is_array( $row ) ? $row : null;
	}

	/**
	 * Update donation row by primary key.
	 *
	 * @param int   $id       Donation row ID.
	 * @param array $data     Data to update.
	 * @param array $formats  Optional formats.
	 * @return bool
	 */
	public static function update_by_id( $id, $data, $formats = array() ) {
		$id = absint( $id );
		if ( ! $id || empty( $data ) || ! is_array( $data ) ) {
			return false;
		}
		global $wpdb;
		$table_name = self::table_name();
		$result     = $wpdb->update(
			$table_name,
			$data,
			array( 'id' => $id ),
			$formats,
			array( '%d' )
		);
		return false !== $result;
	}

	/**
	 * Check if an event was already processed.
	 *
	 * @param string $event_id Stripe event ID.
	 * @return bool
	 */
	public static function has_event_id( $event_id ) {
		if ( '' === $event_id ) {
			return false;
		}
		global $wpdb;
		$table_name = self::table_name();
		$exists     = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table_name} WHERE stripe_event_id = %s LIMIT 1",
				$event_id
			)
		);
		return ! empty( $exists );
	}

	/**
	 * Recent donation rows.
	 *
	 * @param int $limit Row count.
	 * @return array
	 */
	public static function get_recent( $limit = 100 ) {
		global $wpdb;
		$table_name = self::table_name();
		$args = array();
		if ( is_array( $limit ) ) {
			$args = $limit;
		} else {
			$args['limit'] = $limit;
		}

		$max_rows = max( 1, absint( $args['limit'] ?? 100 ) );
		$page     = max( 1, absint( $args['page'] ?? 1 ) );
		$mode     = sanitize_text_field( $args['mode'] ?? '' );
		$status   = sanitize_text_field( $args['status'] ?? '' );
		$search   = sanitize_text_field( $args['search'] ?? '' );
		$offset   = ( $page - 1 ) * $max_rows;

		$where_clauses = array();
		$params        = array();
		if ( '' !== $mode ) {
			$where_clauses[] = 'donation_mode = %s';
			$params[]        = $mode;
		}
		if ( '' !== $status ) {
			$where_clauses[] = 'status = %s';
			$params[]        = $status;
		}
		if ( '' !== $search ) {
			$like            = '%' . $wpdb->esc_like( $search ) . '%';
			$where_clauses[] = '(donor_email LIKE %s OR donor_first_name LIKE %s OR donor_last_name LIKE %s OR stripe_session_id LIKE %s OR stripe_event_id LIKE %s OR stripe_payment_intent_id LIKE %s OR stripe_subscription_id LIKE %s)';
			$params[]        = $like;
			$params[]        = $like;
			$params[]        = $like;
			$params[]        = $like;
			$params[]        = $like;
			$params[]        = $like;
			$params[]        = $like;
		}

		$sql = "SELECT * FROM {$table_name}";
		if ( ! empty( $where_clauses ) ) {
			$sql .= ' WHERE ' . implode( ' AND ', $where_clauses );
		}
		$sql .= " ORDER BY created_at DESC LIMIT {$max_rows} OFFSET {$offset}";

		if ( empty( $params ) ) {
			return $wpdb->get_results( $sql, ARRAY_A );
		}
		return $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );
	}

	/**
	 * Count donation rows using the same filters as get_recent().
	 *
	 * @param array $args Filters: mode, status, search.
	 * @return int
	 */
	public static function get_count( $args = array() ) {
		global $wpdb;
		$table_name = self::table_name();
		$mode       = sanitize_text_field( $args['mode'] ?? '' );
		$status     = sanitize_text_field( $args['status'] ?? '' );
		$search     = sanitize_text_field( $args['search'] ?? '' );

		$where_clauses = array();
		$params        = array();
		if ( '' !== $mode ) {
			$where_clauses[] = 'donation_mode = %s';
			$params[]        = $mode;
		}
		if ( '' !== $status ) {
			$where_clauses[] = 'status = %s';
			$params[]        = $status;
		}
		if ( '' !== $search ) {
			$like            = '%' . $wpdb->esc_like( $search ) . '%';
			$where_clauses[] = '(donor_email LIKE %s OR donor_first_name LIKE %s OR donor_last_name LIKE %s OR stripe_session_id LIKE %s OR stripe_event_id LIKE %s OR stripe_payment_intent_id LIKE %s OR stripe_subscription_id LIKE %s)';
			$params[]        = $like;
			$params[]        = $like;
			$params[]        = $like;
			$params[]        = $like;
			$params[]        = $like;
			$params[]        = $like;
			$params[]        = $like;
		}

		$sql = "SELECT COUNT(*) FROM {$table_name}";
		if ( ! empty( $where_clauses ) ) {
			$sql .= ' WHERE ' . implode( ' AND ', $where_clauses );
		}

		if ( empty( $params ) ) {
			return (int) $wpdb->get_var( $sql );
		}
		return (int) $wpdb->get_var( $wpdb->prepare( $sql, $params ) );
	}

	/**
	 * Get donation rows with missing donor names.
	 *
	 * @param int $limit Maximum rows to return.
	 * @return array
	 */
	public static function get_missing_name_rows( $limit = 500 ) {
		global $wpdb;
		$table_name = self::table_name();
		$max_rows   = max( 1, absint( $limit ) );
		$sql        = $wpdb->prepare(
			"SELECT * FROM {$table_name} WHERE donor_first_name = %s AND donor_last_name = %s ORDER BY created_at DESC LIMIT %d",
			'',
			'',
			$max_rows
		);
		return $wpdb->get_results( $sql, ARRAY_A );
	}

	/**
	 * Summarize donations by mode and status for admin diagnostics.
	 *
	 * @return array
	 */
	public static function get_summary_stats() {
		global $wpdb;
		$table_name = self::table_name();
		$stats      = array(
			'total'    => 0,
			'by_mode'  => array(),
			'by_status'=> array(),
		);

		$total = $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name}" );
		$stats['total'] = (int) $total;

		$mode_rows = $wpdb->get_results( "SELECT donation_mode, COUNT(*) AS total FROM {$table_name} GROUP BY donation_mode", ARRAY_A );
		foreach ( (array) $mode_rows as $row ) {
			$mode = sanitize_text_field( $row['donation_mode'] ?? '' );
			if ( '' !== $mode ) {
				$stats['by_mode'][ $mode ] = (int) ( $row['total'] ?? 0 );
			}
		}

		$status_rows = $wpdb->get_results( "SELECT status, COUNT(*) AS total FROM {$table_name} GROUP BY status", ARRAY_A );
		foreach ( (array) $status_rows as $row ) {
			$status = sanitize_text_field( $row['status'] ?? '' );
			if ( '' !== $status ) {
				$stats['by_status'][ $status ] = (int) ( $row['total'] ?? 0 );
			}
		}

		return $stats;
	}
}
