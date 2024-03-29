<?php

/**
 * @file
 * Definition of Drupal\field_ui\Tests\FieldUiTestBase.
 */

namespace Drupal\field_ui\Tests;

use Drupal\simpletest\WebTestBase;

/**
 * Provides common functionality for the Field UI test classes.
 */
class FieldUiTestBase extends WebTestBase {

  function setUp() {
    // Since this is a base class for many test cases, support the same
    // flexibility that Drupal\simpletest\WebTestBase::setUp() has for the
    // modules to be passed in as either an array or a variable number of string
    // arguments.
    $modules = func_get_args();
    if (isset($modules[0]) && is_array($modules[0])) {
      $modules = $modules[0];
    }
    $modules[] = 'node';
    $modules[] = 'field_ui';
    $modules[] = 'field_test';
    $modules[] = 'taxonomy';
    parent::setUp($modules);

    // Create test user.
    $admin_user = $this->drupalCreateUser(array('access content', 'administer content types', 'administer taxonomy'));
    $this->drupalLogin($admin_user);

    // Create content type, with underscores.
    $type_name = strtolower($this->randomName(8)) . '_test';
    $type = $this->drupalCreateContentType(array('name' => $type_name, 'type' => $type_name));
    $this->type = $type->type;
  }

  /**
   * Creates a new field through the Field UI.
   *
   * @param $bundle_path
   *   Admin path of the bundle that the new field is to be attached to.
   * @param $initial_edit
   *   $edit parameter for drupalPost() on the first step ('Manage fields'
   *   screen).
   * @param $field_edit
   *   $edit parameter for drupalPost() on the second step ('Field settings'
   *   form).
   * @param $instance_edit
   *   $edit parameter for drupalPost() on the third step ('Instance settings'
   *   form).
   */
  function fieldUIAddNewField($bundle_path, $initial_edit, $field_edit = array(), $instance_edit = array()) {
    // Use 'test_field' field type by default.
    $initial_edit += array(
      'fields[_add_new_field][type]' => 'test_field',
      'fields[_add_new_field][widget_type]' => 'test_field_widget',
    );
    $label = $initial_edit['fields[_add_new_field][label]'];
    $field_name = $initial_edit['fields[_add_new_field][field_name]'];

    // First step : 'Add new field' on the 'Manage fields' page.
    $this->drupalPost("$bundle_path/fields",  $initial_edit, t('Save'));
    $this->assertRaw(t('These settings apply to the %label field everywhere it is used.', array('%label' => $label)), t('Field settings page was displayed.'));

    // Second step : 'Field settings' form.
    $this->drupalPost(NULL, $field_edit, t('Save field settings'));
    $this->assertRaw(t('Updated field %label field settings.', array('%label' => $label)), t('Redirected to instance and widget settings page.'));

    // Third step : 'Instance settings' form.
    $this->drupalPost(NULL, $instance_edit, t('Save settings'));
    $this->assertRaw(t('Saved %label configuration.', array('%label' => $label)), t('Redirected to "Manage fields" page.'));

    // Check that the field appears in the overview form.
    $this->assertFieldByXPath('//table[@id="field-overview"]//td[1]', $label, t('Field was created and appears in the overview page.'));
  }

  /**
   * Adds an existing field through the Field UI.
   *
   * @param $bundle_path
   *   Admin path of the bundle that the field is to be attached to.
   * @param $initial_edit
   *   $edit parameter for drupalPost() on the first step ('Manage fields'
   *   screen).
   * @param $instance_edit
   *   $edit parameter for drupalPost() on the second step ('Instance settings'
   *   form).
   */
  function fieldUIAddExistingField($bundle_path, $initial_edit, $instance_edit = array()) {
    // Use 'test_field_widget' by default.
    $initial_edit += array(
      'fields[_add_existing_field][widget_type]' => 'test_field_widget',
    );
    $label = $initial_edit['fields[_add_existing_field][label]'];
    $field_name = $initial_edit['fields[_add_existing_field][field_name]'];

    // First step : 'Add existing field' on the 'Manage fields' page.
    $this->drupalPost("$bundle_path/fields", $initial_edit, t('Save'));

    // Second step : 'Instance settings' form.
    $this->drupalPost(NULL, $instance_edit, t('Save settings'));
    $this->assertRaw(t('Saved %label configuration.', array('%label' => $label)), t('Redirected to "Manage fields" page.'));

    // Check that the field appears in the overview form.
    $this->assertFieldByXPath('//table[@id="field-overview"]//td[1]', $label, t('Field was created and appears in the overview page.'));
  }

  /**
   * Deletes a field instance through the Field UI.
   *
   * @param $bundle_path
   *   Admin path of the bundle that the field instance is to be deleted from.
   * @param $field_name
   *   The name of the field.
   * @param $label
   *   The label of the field.
   * @param $bundle_label
   *   The label of the bundle.
   */
  function fieldUIDeleteField($bundle_path, $field_name, $label, $bundle_label) {
    // Display confirmation form.
    $this->drupalGet("$bundle_path/fields/$field_name/delete");
    $this->assertRaw(t('Are you sure you want to delete the field %label', array('%label' => $label)), t('Delete confirmation was found.'));

    // Submit confirmation form.
    $this->drupalPost(NULL, array(), t('Delete'));
    $this->assertRaw(t('The field %label has been deleted from the %type content type.', array('%label' => $label, '%type' => $bundle_label)), t('Delete message was found.'));

    // Check that the field does not appear in the overview form.
    $this->assertNoFieldByXPath('//table[@id="field-overview"]//span[@class="label-field"]', $label, t('Field does not appear in the overview page.'));
  }
}
