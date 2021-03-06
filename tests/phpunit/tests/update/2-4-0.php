<?php

/**
 * A test case for the plugin update to 2.4.0.
 *
 * @package WordPoints\Tests
 * @since 2.4.0
 */

/**
 * Test that the plugin updates to 2.4.0 properly.
 *
 * @since 2.4.0
 *
 * @group update
 *
 * @covers WordPoints_Un_Installer::update_single_to_2_4_0_alpha_2
 * @covers WordPoints_Un_Installer::update_network_to_2_4_0_alpha_2()
 * @covers WordPoints_Un_Installer::update_site_to_2_4_0_alpha_2
 * @covers WordPoints_Un_Installer::update_2_4_0_add_new_custom_caps
 */
class WordPoints_2_4_0_Update_Test extends WordPoints_PHPUnit_TestCase {

	/**
	 * @since 2.4.0
	 */
	protected $previous_version = '2.3.0';

	/**
	 * Test that the new custom caps are added.
	 *
	 * @since 2.4.0
	 */
	public function test_adds_new_custom_caps() {

		wordpoints_remove_custom_caps( array( 'update_wordpoints_modules' ) );

		$administrator = get_role( 'administrator' );
		$this->assertFalse( $administrator->has_cap( 'update_wordpoints_modules' ) );

		// Simulate the update.
		$this->update_wordpoints();

		// Check that the capabilities were added.
		$administrator = get_role( 'administrator' );
		$this->assertTrue( $administrator->has_cap( 'update_wordpoints_modules' ) );
	}

	/**
	 * Test that the WordPoints.org module is deactivated.
	 *
	 * @since 2.4.0
	 */
	public function test_deactivates_wordpoints_org_module() {

		add_filter( 'wordpoints_modules_dir', 'wordpointstests_modules_dir' );

		$module = 'wordpointsorg/wordpointsorg.php';

		wordpoints_activate_module( $module );

		$this->assertTrue( is_wordpoints_module_active( $module ) );

		// Simulate the update.
		$this->update_wordpoints();

		$this->assertFalse( is_wordpoints_module_active( $module ) );
		$this->assertSame(
			array( $module )
			, get_site_option( 'wordpoints_merged_modules' )
		);
	}
}

// EOF
