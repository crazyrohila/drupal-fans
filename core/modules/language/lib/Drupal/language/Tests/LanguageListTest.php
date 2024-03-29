<?php

/**
 * @file
 * Definition of Drupal\language\Tests\LanguageListTest.
 */

namespace Drupal\language\Tests;

use Drupal\simpletest\WebTestBase;

/**
 * Functional tests for the language list configuration forms.
 */
class LanguageListTest extends WebTestBase {
  public static function getInfo() {
    return array(
      'name' => 'Language list configuration',
      'description' => 'Adds a new language and tests changing its status and the default language.',
      'group' => 'Language',
    );
  }

  function setUp() {
    parent::setUp('language');
  }

  /**
   * Functional tests for adding, editing and deleting languages.
   */
  function testLanguageList() {
    global $base_url;

    // User to add and remove language.
    $admin_user = $this->drupalCreateUser(array('administer languages', 'access administration pages'));
    $this->drupalLogin($admin_user);

    // Add predefined language.
    $edit = array(
      'predefined_langcode' => 'fr',
    );
    $this->drupalPost('admin/config/regional/language/add', $edit, t('Add language'));
    $this->assertText('French', t('Language added successfully.'));
    $this->assertEqual($this->getUrl(), url('admin/config/regional/language', array('absolute' => TRUE)), t('Correct page redirection.'));

    // Add custom language.
    $langcode = 'xx';
    $name = $this->randomName(16);
    $edit = array(
      'predefined_langcode' => 'custom',
      'langcode' => $langcode,
      'name' => $name,
      'direction' => '0',
    );
    $this->drupalPost('admin/config/regional/language/add', $edit, t('Add custom language'));
    $this->assertEqual($this->getUrl(), url('admin/config/regional/language', array('absolute' => TRUE)), t('Correct page redirection.'));
    $this->assertRaw('"edit-site-default-' . $langcode .'"', t('Language code found.'));
    $this->assertText(t($name), t('Test language added.'));

    // Check if we can change the default language.
    $path = 'admin/config/regional/language';
    $this->drupalGet($path);
    $this->assertFieldChecked('edit-site-default-en', t('English is the default language.'));
    // Change the default language.
    $edit = array(
      'site_default' => $langcode,
    );
    $this->drupalPost(NULL, $edit, t('Save configuration'));
    $this->assertNoFieldChecked('edit-site-default-en', t('Default language updated.'));
    $this->assertEqual($this->getUrl(), url('admin/config/regional/language', array('absolute' => TRUE)), t('Correct page redirection.'));

    // Ensure we can't delete the default language.
    $this->drupalGet('admin/config/regional/language/delete/' . $langcode);
    $this->assertEqual($this->getUrl(), url('admin/config/regional/language', array('absolute' => TRUE)), t('Correct page redirection.'));
    $this->assertText(t('The default language cannot be deleted.'), t('Failed to delete the default language.'));

    // Ensure 'edit' link works.
    $this->clickLink(t('edit'));
    $this->assertTitle(t('Edit language | Drupal'), t('Page title is "Edit language".'));
    // Edit a language.
    $name = $this->randomName(16);
    $edit = array(
      'name' => $name,
    );
    $this->drupalPost('admin/config/regional/language/edit/' . $langcode, $edit, t('Save language'));
    $this->assertRaw($name, t('The language has been updated.'));
    $this->assertEqual($this->getUrl(), url('admin/config/regional/language', array('absolute' => TRUE)), t('Correct page redirection.'));

    // Change back the default language.
    $edit = array(
      'site_default' => 'en',
    );
    $this->drupalPost(NULL, $edit, t('Save configuration'));
    // Ensure 'delete' link works.
    $this->drupalGet('admin/config/regional/language');
    $this->clickLink(t('delete'));
    $this->assertText(t('Are you sure you want to delete the language'), t('"delete" link is correct.'));
    // Delete a language.
    $this->drupalGet('admin/config/regional/language/delete/' . $langcode);
    // First test the 'cancel' link.
    $this->clickLink(t('Cancel'));
    $this->assertEqual($this->getUrl(), url('admin/config/regional/language', array('absolute' => TRUE)), t('Correct page redirection.'));
    $this->assertRaw($name, t('The language was not deleted.'));
    // Delete the language for real. This a confirm form, we do not need any
    // fields changed.
    $this->drupalPost('admin/config/regional/language/delete/' . $langcode, array(), t('Delete'));
    // We need raw here because %language and %langcode will add HTML.
    $t_args = array('%language' => $name, '%langcode' => $langcode);
    $this->assertRaw(t('The %language (%langcode) language has been removed.', $t_args), t('The test language has been removed.'));
    $this->assertEqual($this->getUrl(), url('admin/config/regional/language', array('absolute' => TRUE)), t('Correct page redirection.'));
    // Verify that language is no longer found.
    $this->drupalGet('admin/config/regional/language/delete/' . $langcode);
    $this->assertResponse(404, t('Language no longer found.'));
    // Make sure the "language_count" variable has been updated correctly.
    drupal_static_reset('language_list');
    $languages = language_list();
    $this->assertEqual(variable_get('language_count', 1), count($languages), t('Language count is correct.'));
    // Delete French.
    $this->drupalPost('admin/config/regional/language/delete/fr', array(), t('Delete'));
    // Get the count of languages.
    drupal_static_reset('language_list');
    $languages = language_list();
    // We need raw here because %language and %langcode will add HTML.
    $t_args = array('%language' => 'French', '%langcode' => 'fr');
    $this->assertRaw(t('The %language (%langcode) language has been removed.', $t_args), t('Disabled language has been removed.'));
    $this->assertEqual($this->getUrl(), url('admin/config/regional/language', array('absolute' => TRUE)), t('Correct page redirection.'));
    // Verify that language is no longer found.
    $this->drupalGet('admin/config/regional/language/delete/fr');
    $this->assertResponse(404, t('Language no longer found.'));
    // Make sure the "language_count" variable has not changed.
    $this->assertEqual(variable_get('language_count', 1), count($languages), t('Language count is correct.'));

    // Ensure we can delete the English language. Right now English is the only
    // language so we must add a new language and make it the default before
    // deleting English.
    $langcode = 'xx';
    $name = $this->randomName(16);
    $edit = array(
      'predefined_langcode' => 'custom',
      'langcode' => $langcode,
      'name' => $name,
      'direction' => '0',
    );
    $this->drupalPost('admin/config/regional/language/add', $edit, t('Add custom language'));
    $this->assertEqual($this->getUrl(), url('admin/config/regional/language', array('absolute' => TRUE)), t('Correct page redirection.'));
    $this->assertText($name, t('Name found.'));

    // Check if we can change the default language.
    $path = 'admin/config/regional/language';
    $this->drupalGet($path);
    $this->assertFieldChecked('edit-site-default-en', t('English is the default language.'));
    // Change the default language.
    $edit = array(
      'site_default' => $langcode,
    );
    $this->drupalPost(NULL, $edit, t('Save configuration'));
    $this->assertNoFieldChecked('edit-site-default-en', t('Default language updated.'));
    $this->assertEqual($this->getUrl(), url('admin/config/regional/language', array('absolute' => TRUE)), t('Correct page redirection.'));

    $this->drupalPost('admin/config/regional/language/delete/en', array(), t('Delete'));
    // We need raw here because %language and %langcode will add HTML.
    $t_args = array('%language' => 'English', '%langcode' => 'en');
    $this->assertRaw(t('The %language (%langcode) language has been removed.', $t_args), t('The English language has been removed.'));
  }
}
