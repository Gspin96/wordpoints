<?php

/**
 * A test case for AJAX saving points hooks.
 *
 * @package WordPoints\Tests
 * @since 1.3.0
 */

/**
 * Test that points hooks are saved properly via AJAX.
 *
 * @since 1.3.0
 *
 * @group ajax
 *
 * @covers ::wordpoints_ajax_save_points_hook
 */
class WordPoints_Points_Hooks_AJAX_Test extends WordPoints_PHPUnit_TestCase_Ajax_Points {

	/**
	 * Test that it fails for subscribers.
	 *
	 * @since 1.3.0
	 */
	public function test_as_subscriber() {

		$this->_setRole( 'subscriber' );

		$hook = WordPoints_Points_Hooks::get_handler_by_id_base( 'wordpoints_registration_points_hook' );
		$this->assertInstanceOf( 'WordPoints_Registration_Points_Hook', $hook );

		$_POST['savehooks']    = wp_create_nonce( 'save-wordpoints-points-hooks' );
		$_POST['id_base']      = 'wordpoints_registration_points_hook';
		$_POST['hook-id']      = '';
		$_POST['points_type']  = 'points';
		$_POST['hook_number']  = '';
		$_POST['multi_number'] = $hook->next_hook_id_number();
		$_POST['add_new']      = $hook->next_hook_id_number();

		$_POST['hook-wordpoints_registration_points_hook'] = array(
			'points' => '10',
		);

		$this->setExpectedException( 'WPAjaxDieStopException', '-1' );
		$this->_handleAjax( 'save-wordpoints-points-hook' );
	}

	/**
	 * Test creating a new hook.
	 *
	 * @since 1.3.0
	 */
	public function test_add_new_hook() {

		$this->_setRole( 'administrator' );

		$hook = WordPoints_Points_Hooks::get_handler_by_id_base( 'wordpoints_registration_points_hook' );
		$this->assertInstanceOf( 'WordPoints_Registration_Points_Hook', $hook );

		$_POST['savehooks']    = wp_create_nonce( 'save-wordpoints-points-hooks' );
		$_POST['id_base']      = 'wordpoints_registration_points_hook';
		$_POST['hook-id']      = '';
		$_POST['points_type']  = 'points';
		$_POST['hook_number']  = '';
		$_POST['multi_number'] = $hook->next_hook_id_number();
		$_POST['add_new']      = $hook->next_hook_id_number();

		$_POST['hook-wordpoints_registration_points_hook'] = array(
			array( 'points' => '15' ),
		);

		try {

			$this->_handleAjax( 'save-wordpoints-points-hook' );

		} catch ( WPAjaxDieStopException $e ) {

			if ( $e->getMessage() !== '' ) {
				$this->fail( 'Unexpected exception message: "' . $e->getMessage() . '"' );
			}
		}

		$hooks = WordPoints_Points_Hooks::get_points_type_hooks( 'points' );
		$this->assertCount( 1, $hooks );
		$this->assertSame( $hook->get_id(), $hooks[0] );

		$instances = $hook->get_instances();
		$this->assertCount( 1, $instances );
		$this->assertSame( array( 'points' => 15 ), $instances[1] );
	}

	/**
	 * Test updating an existing hook.
	 *
	 * @since 1.3.0
	 */
	public function test_update_hook() {

		$this->_setRole( 'administrator' );

		// Add a hook.
		$hook = wordpointstests_add_points_hook(
			'wordpoints_registration_points_hook'
			, array( 'points' => '20' )
		);

		$_POST['savehooks']    = wp_create_nonce( 'save-wordpoints-points-hooks' );
		$_POST['id_base']      = 'wordpoints_registration_points_hook';
		$_POST['hook-id']      = $hook->get_id();
		$_POST['points_type']  = 'points';
		$_POST['hook_number']  = $hook->get_number();
		$_POST['multi_number'] = $hook->next_hook_id_number();
		$_POST['add_new']      = 0;

		$_POST['hook-wordpoints_registration_points_hook'] = array(
			array( 'points' => '15' ),
		);

		try {
			$this->_handleAjax( 'save-wordpoints-points-hook' );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		}

		$document = new DOMDocument;
		$document->loadHTML( $this->_last_response );
		$xpath = new DOMXPath( $document );
		$this->assertSame( 1, $xpath->query( '//p/input[@value = "15"]' )->length );

		$hooks = WordPoints_Points_Hooks::get_points_type_hooks( 'points' );
		$hook = WordPoints_Points_Hooks::get_handler_by_id_base( 'wordpoints_registration_points_hook' );
		$this->assertInstanceOf( 'WordPoints_Registration_Points_Hook', $hook );

		$this->assertCount( 1, $hooks );
		$this->assertSame( $hook->get_id(), $hooks[0] );

		$instances = $hook->get_instances();
		$this->assertCount( 1, $instances );
		$this->assertSame( array( 'points' => 15 ), $instances[ $hook->get_number() ] );
	}

	/**
	 * Test deleting an existing hook.
	 *
	 * @since 1.3.0
	 */
	public function test_delete_hook() {

		$this->_setRole( 'administrator' );

		// Add a hook.
		$hook = wordpointstests_add_points_hook(
			'wordpoints_registration_points_hook'
			, array( 'points' => '20' )
		);

		$hook_number = $hook->get_number();

		$_POST['savehooks']    = wp_create_nonce( 'save-wordpoints-points-hooks' );
		$_POST['id_base']      = 'wordpoints_registration_points_hook';
		$_POST['hook-id']      = $hook->get_id();
		$_POST['points_type']  = 'points';
		$_POST['hook_number']  = $hook_number;
		$_POST['multi_number'] = $hook->next_hook_id_number();
		$_POST['add_new']      = 0;
		$_POST['delete_hook']  = $hook_number;

		$_POST['hook-wordpoints_registration_points_hook'] = array(
			array( 'points' => '15' ),
		);

		try {
			$this->_handleAjax( 'save-wordpoints-points-hook' );
		} catch ( WPAjaxDieStopException $e ) {
			$this->assertSame( 'deleted:' . $hook->get_id(), $e->getMessage() );
		}

		$hooks = WordPoints_Points_Hooks::get_points_type_hooks( 'points' );
		$this->assertCount( 0, $hooks );

		$this->assertArrayNotHasKey( $hook->get_id(), WordPoints_Points_Hooks::get_handlers() );
	}

	/**
	 * Test that only super-admins can save network-wide hooks.
	 *
	 * @since 1.3.0
	 *
	 * @requires WordPress multisite
	 */
	public function test_network_as_admin() {

		$this->_setRole( 'administrator' );

		$_POST['savehooks']    = wp_create_nonce( 'save-network-wordpoints-points-hooks' );
		$_POST['id_base']      = 'wordpoints_registration_points_hook';
		$_POST['hook-id']      = '';
		$_POST['points_type']  = 'points';
		$_POST['hook_number']  = '';
		$_POST['multi_number'] = 1;
		$_POST['add_new']      = 1;

		$_POST['hook-wordpoints_registration_points_hook'] = array(
			'points' => '10',
		);

		$this->setExpectedException( 'WPAjaxDieStopException', '-1' );
		$this->_handleAjax( 'save-wordpoints-points-hook' );
	}

	/**
	 * Test that super admins can save network-wide hooks.
	 *
	 * @since 1.3.0
	 *
	 * @requires WordPress multisite
	 */
	public function test_network_as_super_admin() {

		$this->_setRole( 'administrator' );
		grant_super_admin( get_current_user_id() );

		$_POST['savehooks']    = wp_create_nonce( 'save-network-wordpoints-points-hooks' );
		$_POST['id_base']      = 'wordpoints_registration_points_hook';
		$_POST['hook-id']      = '';
		$_POST['points_type']  = 'points';
		$_POST['hook_number']  = '';
		$_POST['multi_number'] = 1;
		$_POST['add_new']      = 1;

		$_POST['hook-wordpoints_registration_points_hook'] = array(
			array( 'points' => '15' ),
		);

		try {

			$this->_handleAjax( 'save-wordpoints-points-hook' );

		} catch ( WPAjaxDieStopException $e ) {

			if ( $e->getMessage() !== '' ) {
				$this->fail( 'Unexpected exception message: "' . $e->getMessage() . '"' );
			}
		}

		$hooks = WordPoints_Points_Hooks::get_points_type_hooks( 'points' );
		$this->assertCount( 1, $hooks );
		$this->assertSame( 'wordpoints_registration_points_hook-1', $hooks[0] );

		$hook = WordPoints_Points_Hooks::get_handler_by_id_base( 'wordpoints_registration_points_hook' );
		$this->assertInstanceOf( 'WordPoints_Registration_Points_Hook', $hook );

		$instances = $hook->get_instances();
		$this->assertCount( 1, $instances );
		$this->assertSame( array( 'points' => 15 ), $instances['network_1'] );
	}
}

// EOF
