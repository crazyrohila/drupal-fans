<?php

/**
 * @file
 * Definition of Drupal\system\Tests\Bootstrap\MiscUnitTest.
 */

namespace Drupal\system\Tests\Bootstrap;

use Drupal\simpletest\UnitTestBase;

/**
 * Tests miscellaneous functions in bootstrap.inc.
 */
class MiscUnitTest extends UnitTestBase {

  public static function getInfo() {
    return array(
      'name' => 'Miscellaneous bootstrap unit tests',
      'description' => 'Test miscellaneous functions in bootstrap.inc.',
      'group' => 'Bootstrap',
    );
  }

  /**
   * Test miscellaneous functions in bootstrap.inc.
   */
  function testMisc() {
    // Test drupal_array_merge_deep().
    $link_options_1 = array('fragment' => 'x', 'attributes' => array('title' => 'X', 'class' => array('a', 'b')), 'language' => 'en');
    $link_options_2 = array('fragment' => 'y', 'attributes' => array('title' => 'Y', 'class' => array('c', 'd')), 'html' => TRUE);
    $expected = array('fragment' => 'y', 'attributes' => array('title' => 'Y', 'class' => array('a', 'b', 'c', 'd')), 'language' => 'en', 'html' => TRUE);
    $this->assertIdentical(drupal_array_merge_deep($link_options_1, $link_options_2), $expected, t('drupal_array_merge_deep() returned a properly merged array.'));
  }

  /**
   * Tests that the drupal_check_memory_limit() function works as expected.
   */
  function testCheckMemoryLimit() {
    $memory_limit = ini_get('memory_limit');
    // Test that a very reasonable amount of memory is available.
    $this->assertTrue(drupal_check_memory_limit('30MB'), '30MB of memory tested available.');

    // Get the available memory and multiply it by two to make it unreasonably
    // high.
    $twice_avail_memory = ($memory_limit * 2) . 'MB';
    $this->assertFalse(drupal_check_memory_limit($twice_avail_memory), 'drupal_check_memory_limit() returns FALSE for twice the available memory limit.');

    // The function should always return true if the memory limit is set to -1.
    $this->assertTrue(drupal_check_memory_limit($twice_avail_memory, -1), 'drupal_check_memory_limit() returns TRUE when a limit of -1 (none) is supplied');

    // Test that even though we have 30MB of memory available - the function
    // returns FALSE when given an upper limit for how much memory can be used.
    $this->assertFalse(drupal_check_memory_limit('30MB', '16MB'), 'drupal_check_memory_limit() returns FALSE with a 16MB upper limit on a 30MB requirement.');
  }
}
