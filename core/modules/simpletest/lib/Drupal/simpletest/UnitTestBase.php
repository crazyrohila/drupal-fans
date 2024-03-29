<?php

/**
 * @file
 * Definition of Drupal\simpletest\UnitTestBase.
 */

namespace Drupal\simpletest;

use Drupal\Core\Database\Database;
use Drupal\Core\Database\ConnectionNotDefinedException;

/**
 * Test case for Drupal unit tests.
 *
 * These tests can not access the database nor files. Calling any Drupal
 * function that needs the database will throw exceptions. These include
 * watchdog(), module_implements(), module_invoke_all() etc.
 */
abstract class UnitTestBase extends TestBase {

  /**
   * Constructor for UnitTestBase.
   */
  function __construct($test_id = NULL) {
    parent::__construct($test_id);
    $this->skipClasses[__CLASS__] = TRUE;
  }

  /**
   * Sets up unit test environment.
   *
   * Unlike Drupal\simpletest\WebTestBase::setUp(), UnitTestBase::setUp() does not
   * install modules because tests are performed without accessing the database.
   * Any required files must be explicitly included by the child class setUp()
   * method.
   */
  protected function setUp() {
    global $conf;

    // Create the database prefix for this test.
    $this->prepareDatabasePrefix();

    // Prepare the environment for running tests.
    $this->prepareEnvironment();
    $this->originalThemeRegistry = theme_get_registry(FALSE);

    // Reset all statics and variables to perform tests in a clean environment.
    $conf = array();
    drupal_static_reset();

    // Empty out module list.
    module_list(TRUE, FALSE, FALSE, array());
    // Prevent module_load_all() from attempting to refresh it.
    $has_run = &drupal_static('module_load_all');
    $has_run = TRUE;

    // Re-implant theme registry.
    // Required for l() and other functions to work correctly and not trigger
    // database lookups.
    $theme_get_registry = &drupal_static('theme_get_registry');
    $theme_get_registry[FALSE] = $this->originalThemeRegistry;

    $conf['file_public_path'] = $this->public_files_directory;

    // Change the database prefix.
    // All static variables need to be reset before the database prefix is
    // changed, since Drupal\Core\Utility\CacheArray implementations attempt to
    // write back to persistent caches when they are destructed.
    $this->changeDatabasePrefix();

    // Set user agent to be consistent with WebTestBase.
    $_SERVER['HTTP_USER_AGENT'] = $this->databasePrefix;

    $this->setup = TRUE;
  }
}
