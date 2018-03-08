<?php

/**
 * Log Plugin Deactivate event data
 */
final class WC_EBANX_Plugin_Deactivate_Logger extends WC_EBANX_Logger {
	/**
	 * @inheritdoc
	 */
	public static function persist( array $logData = [] ) {
		parent::save(
			'plugin_deactivate',
			array_merge(
				WC_EBANX_Log::get_platform_info(),
				$logData
			)
		);
	}
}
