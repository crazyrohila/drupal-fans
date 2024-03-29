<?php

/**
 * @file
 * Definition of Drupal\system\Tests\System\FrontPageTest.
 */

namespace Drupal\system\Tests\System;

use Drupal\simpletest\WebTestBase;

/**
 * Test front page functionality and administration.
 */
class FrontPageTest extends WebTestBase {

  public static function getInfo() {
    return array(
      'name' => 'Front page',
      'description' => 'Tests front page functionality and administration.',
      'group' => 'System',
    );
  }

  function setUp() {
    parent::setUp(array('node', 'system_test'));

    // Create admin user, log in admin user, and create one node.
    $this->admin_user = $this->drupalCreateUser(array('access content', 'administer site configuration'));
    $this->drupalLogin($this->admin_user);
    $this->node_path = "node/" . $this->drupalCreateNode(array('promote' => 1))->nid;

    // Configure 'node' as front page.
    variable_set('site_frontpage', 'node');
    // Enable front page logging in system_test.module.
    variable_set('front_page_output', 1);
  }

  /**
   * Test front page functionality.
   */
  function testDrupalIsFrontPage() {
    $this->drupalGet('');
    $this->assertText(t('On front page.'), t('Path is the front page.'));
    $this->drupalGet('node');
    $this->assertText(t('On front page.'), t('Path is the front page.'));
    $this->drupalGet($this->node_path);
    $this->assertNoText(t('On front page.'), t('Path is not the front page.'));

    // Change the front page to an invalid path.
    $edit = array('site_frontpage' => 'kittens');
    $this->drupalPost('admin/config/system/site-information', $edit, t('Save configuration'));
    $this->assertText(t("The path '@path' is either invalid or you do not have access to it.", array('@path' => $edit['site_frontpage'])));

    // Change the front page to a valid path.
    $edit['site_frontpage'] = $this->node_path;
    $this->drupalPost('admin/config/system/site-information', $edit, t('Save configuration'));
    $this->assertText(t('The configuration options have been saved.'), t('The front page path has been saved.'));

    $this->drupalGet('');
    $this->assertText(t('On front page.'), t('Path is the front page.'));
    $this->drupalGet('node');
    $this->assertNoText(t('On front page.'), t('Path is not the front page.'));
    $this->drupalGet($this->node_path);
    $this->assertText(t('On front page.'), t('Path is the front page.'));
  }
}
