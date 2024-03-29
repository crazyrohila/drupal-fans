<?php

/**
 * @file
 * Definition of Drupal\system\Tests\System\MainContentFallbackTest.
 */

namespace Drupal\system\Tests\System;

use Drupal\simpletest\WebTestBase;

/**
 * Test main content rendering fallback provided by system module.
 */
class MainContentFallbackTest extends WebTestBase {
  protected $admin_user;
  protected $web_user;

  public static function getInfo() {
    return array(
      'name' => 'Main content rendering fallback',
      'description' => ' Test system module main content rendering fallback.',
      'group' => 'System',
    );
  }

  function setUp() {
    parent::setUp(array('block', 'system_test'));

    // Create and login admin user.
    $this->admin_user = $this->drupalCreateUser(array(
      'access administration pages',
      'administer site configuration',
      'administer modules',
    ));
    $this->drupalLogin($this->admin_user);

    // Create a web user.
    $this->web_user = $this->drupalCreateUser(array('access user profiles'));
  }

  /**
   * Test availability of main content.
   */
  function testMainContentFallback() {
    $edit = array();
    // Disable the block module.
    $edit['modules[Core][block][enable]'] = FALSE;
    $this->drupalPost('admin/modules', $edit, t('Save configuration'));
    $this->assertText(t('The configuration options have been saved.'), t('Modules status has been updated.'));
    module_list(TRUE);
    $this->assertFalse(module_exists('block'), t('Block module disabled.'));

    // At this point, no region is filled and fallback should be triggered.
    $this->drupalGet('admin/config/system/site-information');
    $this->assertField('site_name', t('Admin interface still available.'));

    // Fallback should not trigger when another module is handling content.
    $this->drupalGet('system-test/main-content-handling');
    $this->assertRaw('id="system-test-content"', t('Content handled by another module'));
    $this->assertText(t('Content to test main content fallback'), t('Main content still displayed.'));

    // Fallback should trigger when another module
    // indicates that it is not handling the content.
    $this->drupalGet('system-test/main-content-fallback');
    $this->assertText(t('Content to test main content fallback'), t('Main content fallback properly triggers.'));

    // Fallback should not trigger when another module is handling content.
    // Note that this test ensures that no duplicate
    // content gets created by the fallback.
    $this->drupalGet('system-test/main-content-duplication');
    $this->assertNoText(t('Content to test main content fallback'), t('Main content not duplicated.'));

    // Request a user* page and see if it is displayed.
    $this->drupalLogin($this->web_user);
    $this->drupalGet('user/' . $this->web_user->uid . '/edit');
    $this->assertField('mail', t('User interface still available.'));

    // Enable the block module again.
    $this->drupalLogin($this->admin_user);
    $edit = array();
    $edit['modules[Core][block][enable]'] = 'block';
    $this->drupalPost('admin/modules', $edit, t('Save configuration'));
    $this->assertText(t('The configuration options have been saved.'), t('Modules status has been updated.'));
    module_list(TRUE);
    $this->assertTrue(module_exists('block'), t('Block module re-enabled.'));
  }
}
