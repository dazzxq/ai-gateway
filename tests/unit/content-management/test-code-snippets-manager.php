<?php
/**
 * Unit Tests for CodeSnippetsManager
 *
 * Tests Code Snippets plugin database CRUD operations, validation,
 * pagination, filtering, and PHP linting integration.
 *
 * @package AI_Gateway\Tests\Unit
 */

namespace AI_Gateway\Tests\Unit\ContentManagement;

use AI_Gateway\ContentManagement\CodeSnippetsManager;
use AI_Gateway\FileOperations\PHPLinter;
use WP_Error;

/**
 * Test_CodeSnippetsManager
 *
 * Unit test class for CodeSnippetsManager.
 */
class Test_CodeSnippetsManager extends \WP_UnitTestCase {
	/**
	 * CodeSnippetsManager instance.
	 *
	 * @var CodeSnippetsManager
	 */
	private $manager;

	/**
	 * PHPLinter mock.
	 *
	 * @var PHPLinter|\PHPUnit\Framework\MockObject\MockObject
	 */
	private $linter_mock;

	/**
	 * Whether Code Snippets plugin is active.
	 *
	 * @var bool
	 */
	private $plugin_active;

	/**
	 * Set up test fixtures.
	 */
	public function setUp(): void {
		parent::setUp();

		$this->linter_mock = $this->createMock( PHPLinter::class );
		$this->manager     = new CodeSnippetsManager( $this->linter_mock );
		$this->plugin_active = $this->manager->is_code_snippets_active();
	}

	/**
	 * Test: is_code_snippets_active returns boolean.
	 */
	public function test_is_code_snippets_active_returns_boolean() {
		$result = $this->manager->is_code_snippets_active();
		$this->assertIsBool( $result );
	}

	/**
	 * Test: get_snippets returns array or WP_Error depending on plugin state.
	 */
	public function test_get_snippets_returns_expected_type() {
		$result = $this->manager->get_snippets();

		if ( $this->plugin_active ) {
			$this->assertIsArray( $result );
		} else {
			$this->assertInstanceOf( WP_Error::class, $result );
			$this->assertSame( 'plugin_inactive', $result->get_error_code() );
		}
	}

	/**
	 * Test: get_snippet returns WP_Error for nonexistent ID.
	 */
	public function test_get_snippet_returns_error_for_nonexistent() {
		$result = $this->manager->get_snippet( 999999 );

		$this->assertInstanceOf( WP_Error::class, $result );
		// Error code is 'plugin_inactive' when inactive, or 'snippet_not_found' when active.
		$this->assertContains(
			$result->get_error_code(),
			array( 'plugin_inactive', 'snippet_not_found' )
		);
	}

	/**
	 * Test: get_snippet returns WP_Error for invalid IDs.
	 */
	public function test_get_snippet_validates_id_is_positive() {
		$result = $this->manager->get_snippet( -1 );
		$this->assertInstanceOf( WP_Error::class, $result );

		$result = $this->manager->get_snippet( 0 );
		$this->assertInstanceOf( WP_Error::class, $result );
	}

	/**
	 * Test: insert_snippet with valid data returns integer or WP_Error.
	 */
	public function test_insert_snippet_returns_expected_type() {
		if ( ! $this->plugin_active ) {
			$data   = array( 'name' => 'Test', 'code' => '<?php echo "test";' );
			$result = $this->manager->insert_snippet( $data );
			$this->assertInstanceOf( WP_Error::class, $result );
			$this->assertSame( 'plugin_inactive', $result->get_error_code() );
			return;
		}

		// Plugin is active -- mock linter to pass.
		$this->linter_mock->method( 'lint_content' )
			->willReturn( array( 'valid' => true, 'errors' => array() ) );

		$data   = array(
			'name' => 'Test Insert ' . uniqid(),
			'code' => 'echo "test insert";',
		);
		$result = $this->manager->insert_snippet( $data );
		$this->assertIsInt( $result );
		$this->assertGreaterThan( 0, $result );

		// Cleanup.
		global $wpdb;
		$wpdb->delete( $wpdb->prefix . 'snippets', array( 'id' => $result ), array( '%d' ) );
	}

	/**
	 * Test: insert_snippet returns error when name is empty.
	 */
	public function test_insert_snippet_returns_error_when_name_empty() {
		$manager = $this->getMockBuilder( CodeSnippetsManager::class )
			->setConstructorArgs( array( $this->linter_mock ) )
			->onlyMethods( array( 'is_code_snippets_active' ) )
			->getMock();

		$manager->expects( $this->any() )
			->method( 'is_code_snippets_active' )
			->willReturn( true );

		$data = array(
			'name' => '',
			'code' => '<?php echo "test";',
		);

		$result = $manager->insert_snippet( $data );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'empty_name', $result->get_error_code() );
	}

	/**
	 * Test: insert_snippet returns error when code is empty.
	 */
	public function test_insert_snippet_returns_error_when_code_empty() {
		$manager = $this->getMockBuilder( CodeSnippetsManager::class )
			->setConstructorArgs( array( $this->linter_mock ) )
			->onlyMethods( array( 'is_code_snippets_active' ) )
			->getMock();

		$manager->expects( $this->any() )
			->method( 'is_code_snippets_active' )
			->willReturn( true );

		$data = array(
			'name' => 'Test Snippet',
			'code' => '',
		);

		$result = $manager->insert_snippet( $data );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'empty_code', $result->get_error_code() );
	}

	/**
	 * Test: insert_snippet validates PHP syntax via linter.
	 */
	public function test_insert_snippet_validates_php_syntax() {
		$manager = $this->getMockBuilder( CodeSnippetsManager::class )
			->setConstructorArgs( array( $this->linter_mock ) )
			->onlyMethods( array( 'is_code_snippets_active' ) )
			->getMock();

		$manager->expects( $this->any() )
			->method( 'is_code_snippets_active' )
			->willReturn( true );

		// Mock linter to return WP_Error (syntax failure).
		$this->linter_mock->method( 'lint_content' )
			->willReturn( new WP_Error(
				'php_syntax_error',
				'Syntax error',
				array(
					array(
						'line'    => 1,
						'message' => 'Unexpected token',
					),
				)
			) );

		$data = array(
			'name' => 'Invalid Snippet',
			'code' => '<?php invalid php',
		);

		$result = $manager->insert_snippet( $data );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'invalid_php_syntax', $result->get_error_code() );
	}

	/**
	 * Test: update_snippet returns error for nonexistent snippet.
	 */
	public function test_update_snippet_returns_error_for_nonexistent() {
		$result = $this->manager->update_snippet( 999999, array( 'code' => 'echo "test";' ) );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertContains(
			$result->get_error_code(),
			array( 'plugin_inactive', 'snippet_not_found' )
		);
	}

	/**
	 * Test: update_snippet returns error for invalid ID.
	 */
	public function test_update_snippet_validates_id_is_positive() {
		$result = $this->manager->update_snippet( -1, array() );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertContains(
			$result->get_error_code(),
			array( 'plugin_inactive', 'invalid_snippet_id' )
		);
	}

	/**
	 * Test: update_snippet validates PHP syntax when code provided.
	 */
	public function test_update_snippet_validates_php_when_code_provided() {
		$manager = $this->getMockBuilder( CodeSnippetsManager::class )
			->setConstructorArgs( array( $this->linter_mock ) )
			->onlyMethods( array( 'is_code_snippets_active', 'get_snippet' ) )
			->getMock();

		$manager->expects( $this->any() )
			->method( 'is_code_snippets_active' )
			->willReturn( true );

		$snippet = (object) array(
			'id'   => 1,
			'name' => 'Existing Snippet',
			'code' => '<?php echo "test";',
		);

		$manager->expects( $this->any() )
			->method( 'get_snippet' )
			->willReturn( $snippet );

		// Mock linter to return WP_Error (syntax failure).
		$this->linter_mock->method( 'lint_content' )
			->willReturn( new WP_Error(
				'php_syntax_error',
				'Syntax error',
				array(
					array(
						'line'    => 1,
						'message' => 'Parse error',
					),
				)
			) );

		$data = array(
			'code' => '<?php invalid',
		);

		$result = $manager->update_snippet( 1, $data );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'invalid_php_syntax', $result->get_error_code() );
	}

	/**
	 * Test: delete_snippet returns error for nonexistent snippet.
	 */
	public function test_delete_snippet_returns_error_for_nonexistent() {
		$result = $this->manager->delete_snippet( 999999 );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertContains(
			$result->get_error_code(),
			array( 'plugin_inactive', 'snippet_not_found' )
		);
	}

	/**
	 * Test: delete_snippet returns error for invalid ID.
	 */
	public function test_delete_snippet_validates_id_is_positive() {
		$result = $this->manager->delete_snippet( 0 );
		$this->assertInstanceOf( WP_Error::class, $result );

		$result = $this->manager->delete_snippet( -5 );
		$this->assertInstanceOf( WP_Error::class, $result );
	}

	/**
	 * Test: Constructor accepts PHPLinter dependency.
	 */
	public function test_constructor_accepts_linter_dependency() {
		$linter = new PHPLinter();
		$manager = new CodeSnippetsManager( $linter );

		$this->assertInstanceOf( CodeSnippetsManager::class, $manager );
	}

	/**
	 * Test: get_snippets handles pagination parameters.
	 */
	public function test_get_snippets_handles_pagination() {
		$result = $this->manager->get_snippets( 1, 10 );

		if ( $this->plugin_active ) {
			$this->assertIsArray( $result );
		} else {
			$this->assertInstanceOf( WP_Error::class, $result );
		}
	}

	/**
	 * Test: get_snippets handles page parameter.
	 */
	public function test_get_snippets_handles_page_parameter() {
		$result = $this->manager->get_snippets( 1 );

		if ( $this->plugin_active ) {
			$this->assertIsArray( $result );
		} else {
			$this->assertInstanceOf( WP_Error::class, $result );
		}
	}

	// =========================================================================
	// deploy_snippet() tests
	// =========================================================================

	/**
	 * Test: deploy_snippet returns WP_Error when plugin is inactive.
	 */
	public function test_deploy_snippet_returns_error_when_plugin_inactive() {
		$manager = $this->getMockBuilder( CodeSnippetsManager::class )
			->setConstructorArgs( array( $this->linter_mock ) )
			->onlyMethods( array( 'is_code_snippets_active' ) )
			->getMock();

		$manager->expects( $this->any() )
			->method( 'is_code_snippets_active' )
			->willReturn( false );

		$result = $manager->deploy_snippet( 1 );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'plugin_inactive', $result->get_error_code() );
	}

	/**
	 * Test: deploy_snippet returns WP_Error for invalid ID (0 or negative).
	 */
	public function test_deploy_snippet_returns_error_for_invalid_id() {
		$manager = $this->getMockBuilder( CodeSnippetsManager::class )
			->setConstructorArgs( array( $this->linter_mock ) )
			->onlyMethods( array( 'is_code_snippets_active' ) )
			->getMock();

		$manager->expects( $this->any() )
			->method( 'is_code_snippets_active' )
			->willReturn( true );

		$result = $manager->deploy_snippet( 0 );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'invalid_snippet_id', $result->get_error_code() );

		$result = $manager->deploy_snippet( -1 );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'invalid_snippet_id', $result->get_error_code() );
	}

	/**
	 * Test: deploy_snippet returns WP_Error for nonexistent snippet.
	 */
	public function test_deploy_snippet_returns_error_for_nonexistent_snippet() {
		$manager = $this->getMockBuilder( CodeSnippetsManager::class )
			->setConstructorArgs( array( $this->linter_mock ) )
			->onlyMethods( array( 'is_code_snippets_active', 'get_snippet' ) )
			->getMock();

		$manager->expects( $this->any() )
			->method( 'is_code_snippets_active' )
			->willReturn( true );

		$manager->expects( $this->any() )
			->method( 'get_snippet' )
			->willReturn( new WP_Error( 'snippet_not_found', 'Not found', array( 'status' => 404 ) ) );

		$result = $manager->deploy_snippet( 99999 );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'snippet_not_found', $result->get_error_code() );
	}

	/**
	 * Test: deploy_snippet returns steps array with deployed, active, and steps keys.
	 */
	public function test_deploy_snippet_returns_steps_array() {
		global $wpdb;
		$table = $wpdb->prefix . 'snippets';

		$table_exists = $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $table )
		);
		if ( ! $table_exists ) {
			$this->markTestSkipped( 'Code Snippets table does not exist in test environment' );
		}

		$wpdb->insert(
			$table,
			array(
				'name'     => 'Deploy Unit Test ' . uniqid(),
				'code'     => 'echo "deploy test";',
				'active'   => 1,
				'scope'    => 'global',
				'priority' => 10,
				'modified' => current_time( 'mysql' ),
				'revision' => 1,
			),
			array( '%s', '%s', '%d', '%s', '%d', '%s', '%d' )
		);
		$snippet_id = (int) $wpdb->insert_id;
		$this->assertGreaterThan( 0, $snippet_id, 'Test snippet should be inserted' );

		$result = $this->manager->deploy_snippet( $snippet_id );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'deployed', $result );
		$this->assertArrayHasKey( 'active', $result );
		$this->assertArrayHasKey( 'steps', $result );
		$this->assertTrue( $result['deployed'] );

		$this->assertCount( 3, $result['steps'] );
		$this->assertSame( 'deactivate', $result['steps'][0]['action'] );
		$this->assertSame( 'activate', $result['steps'][1]['action'] );
		$this->assertSame( 'verify', $result['steps'][2]['action'] );

		$wpdb->delete( $table, array( 'id' => $snippet_id ), array( '%d' ) );
	}

	/**
	 * Test: insert_snippet includes cloud_id key in inserted data (CS v6+).
	 */
	public function test_insert_snippet_includes_cloud_id() {
		global $wpdb;
		$table = $wpdb->prefix . 'snippets';

		$table_exists = $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $table )
		);
		if ( ! $table_exists ) {
			$this->markTestSkipped( 'Code Snippets table does not exist in test environment' );
		}

		// Mock linter to return valid.
		$this->linter_mock->method( 'lint_content' )
			->willReturn( array( 'valid' => true, 'errors' => array() ) );

		$snippet_name = 'Cloud ID Test ' . uniqid();
		$result = $this->manager->insert_snippet( array(
			'name' => $snippet_name,
			'code' => 'echo "cloud_id test";',
		) );

		$this->assertIsInt( $result );
		$this->assertGreaterThan( 0, $result );

		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT cloud_id FROM {$table} WHERE id = %d", $result ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);
		$this->assertNotNull( $row, 'Inserted row should exist' );
		$this->assertNull( $row->cloud_id, 'cloud_id should be NULL' );

		$wpdb->delete( $table, array( 'id' => $result ), array( '%d' ) );
	}

	/**
	 * Test: update_snippet uses description column (not desc) for DB update.
	 */
	public function test_update_snippet_uses_description_column() {
		global $wpdb;
		$table = $wpdb->prefix . 'snippets';

		$table_exists = $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $table )
		);
		if ( ! $table_exists ) {
			$this->markTestSkipped( 'Code Snippets table does not exist in test environment' );
		}

		$snippet_name = 'Desc Column Test ' . uniqid();
		$wpdb->insert(
			$table,
			array(
				'name'        => $snippet_name,
				'description' => 'Original description',
				'code'        => 'echo "desc test";',
				'active'      => 1,
				'scope'       => 'global',
				'priority'    => 10,
				'modified'    => current_time( 'mysql' ),
				'revision'    => 1,
			),
			array( '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%d' )
		);
		$snippet_id = (int) $wpdb->insert_id;
		$this->assertGreaterThan( 0, $snippet_id );

		$result = $this->manager->update_snippet( $snippet_id, array(
			'desc' => 'Updated description via API',
		) );

		$this->assertTrue( $result );

		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT description FROM {$table} WHERE id = %d", $snippet_id ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);
		$this->assertSame( 'Updated description via API', $row->description );

		$wpdb->delete( $table, array( 'id' => $snippet_id ), array( '%d' ) );
	}
}
