<?php

/**
 * @file
 * Definition of Drupal\locale\Tests\LocalePathTest.
 */

namespace Drupal\locale\Tests;

use Drupal\simpletest\WebTestBase;

/**
 * Functional tests for configuring a different path alias per language.
 */
class LocalePathTest extends WebTestBase {
  public static function getInfo() {
    return array(
      'name' => 'Path language settings',
      'description' => 'Checks you can configure a language for individual url aliases.',
      'group' => 'Locale',
    );
  }

  function setUp() {
    parent::setUp(array('node', 'locale', 'path'));

    $this->drupalCreateContentType(array('type' => 'page', 'name' => 'Basic page'));
    variable_set('site_frontpage', 'node');
  }

  /**
   * Test if a language can be associated with a path alias.
   */
  function testPathLanguageConfiguration() {
    global $base_url;

    // User to add and remove language.
    $admin_user = $this->drupalCreateUser(array('administer languages', 'create page content', 'administer url aliases', 'create url aliases', 'access administration pages'));

    // Add custom language.
    $this->drupalLogin($admin_user);
    // Code for the language.
    $langcode = 'xx';
    // The English name for the language.
    $name = $this->randomName(16);
    // The domain prefix.
    $prefix = $langcode;
    $edit = array(
      'predefined_langcode' => 'custom',
      'langcode' => $langcode,
      'name' => $name,
      'direction' => '0',
    );
    $this->drupalPost('admin/config/regional/language/add', $edit, t('Add custom language'));

    // Set path prefix.
    $edit = array( "prefix[$langcode]" => $prefix );
    $this->drupalPost('admin/config/regional/language/detection/url', $edit, t('Save configuration'));

    // Check that the "xx" front page is readily available because path prefix
    // negotiation is pre-configured.
    $this->drupalGet($prefix);
    $this->assertText(t('Welcome to Drupal'), t('The "xx" front page is readibly available.'));

    // Create a node.
    $node = $this->drupalCreateNode(array('type' => 'page'));

    // Create a path alias in default language (English).
    $path = 'admin/config/search/path/add';
    $english_path = $this->randomName(8);
    $edit = array(
      'source'   => 'node/' . $node->nid,
      'alias'    => $english_path,
      'langcode' => 'en',
    );
    $this->drupalPost($path, $edit, t('Save'));

    // Create a path alias in new custom language.
    $custom_language_path = $this->randomName(8);
    $edit = array(
      'source'   => 'node/' . $node->nid,
      'alias'    => $custom_language_path,
      'langcode' => $langcode,
    );
    $this->drupalPost($path, $edit, t('Save'));

    // Confirm English language path alias works.
    $this->drupalGet($english_path);
    $this->assertText($node->title, t('English alias works.'));

    // Confirm custom language path alias works.
    $this->drupalGet($prefix . '/' . $custom_language_path);
    $this->assertText($node->title, t('Custom language alias works.'));

    // Create a custom path.
    $custom_path = $this->randomName(8);

    // Check priority of language for alias by source path.
    $edit = array(
      'source'   => 'node/' . $node->nid,
      'alias'    => $custom_path,
      'langcode' => LANGUAGE_NOT_SPECIFIED,
    );
    path_save($edit);
    $lookup_path = drupal_lookup_path('alias', 'node/' . $node->nid, 'en');
    $this->assertEqual($english_path, $lookup_path, t('English language alias has priority.'));
    // Same check for language 'xx'.
    $lookup_path = drupal_lookup_path('alias', 'node/' . $node->nid, $prefix);
    $this->assertEqual($custom_language_path, $lookup_path, t('Custom language alias has priority.'));
    path_delete($edit);

    // Create language nodes to check priority of aliases.
    $first_node = $this->drupalCreateNode(array('type' => 'page', 'promote' => 1));
    $second_node = $this->drupalCreateNode(array('type' => 'page', 'promote' => 1));

    // Assign a custom path alias to the first node with the English language.
    $edit = array(
      'source'   => 'node/' . $first_node->nid,
      'alias'    => $custom_path,
      'langcode' => 'en',
    );
    path_save($edit);

    // Assign a custom path alias to second node with LANGUAGE_NOT_SPECIFIED.
    $edit = array(
      'source'   => 'node/' . $second_node->nid,
      'alias'    => $custom_path,
      'langcode' => LANGUAGE_NOT_SPECIFIED,
    );
    path_save($edit);

    // Test that both node titles link to our path alias.
    $this->drupalGet('<front>');
    $custom_path_url = base_path() . $GLOBALS['script_path'] . $custom_path;
    $elements = $this->xpath('//a[@href=:href and .=:title]', array(':href' => $custom_path_url, ':title' => $first_node->title));
    $this->assertTrue(!empty($elements), t('First node links to the path alias.'));
    $elements = $this->xpath('//a[@href=:href and .=:title]', array(':href' => $custom_path_url, ':title' => $second_node->title));
    $this->assertTrue(!empty($elements), t('Second node links to the path alias.'));

    // Confirm that the custom path leads to the first node.
    $this->drupalGet($custom_path);
    $this->assertText($first_node->title, t('Custom alias returns first node.'));

    // Confirm that the custom path with prefix leads to the second node.
    $this->drupalGet($prefix . '/' . $custom_path);
    $this->assertText($second_node->title, t('Custom alias with prefix returns second node.'));

  }
}
