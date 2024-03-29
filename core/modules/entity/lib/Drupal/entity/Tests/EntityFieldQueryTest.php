<?php

/**
 * @file
 * Definition of Drupal\entity\Tests\EntityFieldQueryTest.
 */

namespace Drupal\entity\Tests;

use Drupal\simpletest\WebTestBase;
use Drupal\entity\EntityFieldQuery;
use Drupal\entity\EntityFieldQueryException;
use stdClass;

/**
 * Tests Drupal\entity\EntityFieldQuery.
 */
class EntityFieldQueryTest extends WebTestBase {


  public static function getInfo() {
    return array(
      'name' => 'Entity query',
      'description' => 'Test the EntityFieldQuery class.',
      'group' => 'Entity API',
    );
  }

  function setUp() {
    parent::setUp(array('field_test'));

    field_test_create_bundle('bundle1');
    field_test_create_bundle('bundle2');
    field_test_create_bundle('test_bundle');
    field_test_create_bundle('test_entity_bundle');

    $instances = array();
    $this->fields = array();
    $this->field_names[0] = $field_name = drupal_strtolower($this->randomName() . '_field_name');
    $field = array('field_name' => $field_name, 'type' => 'test_field', 'cardinality' => 4);
    $field = field_create_field($field);
    $this->fields[0] = $field;
    $instance = array(
      'field_name' => $field_name,
      'entity_type' => '',
      'bundle' => '',
      'label' => $this->randomName() . '_label',
      'description' => $this->randomName() . '_description',
      'weight' => mt_rand(0, 127),
      'settings' => array(
        'test_instance_setting' => $this->randomName(),
      ),
      'widget' => array(
        'type' => 'test_field_widget',
        'label' => 'Test Field',
        'settings' => array(
          'test_widget_setting' => $this->randomName(),
        )
      )
    );

    $instances[0] = $instance;

    // Add an instance to that bundle.
    $instances[0]['bundle'] = 'bundle1';
    $instances[0]['entity_type'] = 'test_entity_bundle_key';
    field_create_instance($instances[0]);
    $instances[0]['bundle'] = 'bundle2';
    field_create_instance($instances[0]);
    $instances[0]['bundle'] = $instances[0]['entity_type'] = 'test_entity_bundle';
    field_create_instance($instances[0]);

    $this->field_names[1] = $field_name = drupal_strtolower($this->randomName() . '_field_name');
    $field = array('field_name' => $field_name, 'type' => 'shape', 'cardinality' => 4);
    $field = field_create_field($field);
    $this->fields[1] = $field;
    $instance = array(
      'field_name' => $field_name,
      'entity_type' => '',
      'bundle' => '',
      'label' => $this->randomName() . '_label',
      'description' => $this->randomName() . '_description',
      'weight' => mt_rand(0, 127),
      'settings' => array(
        'test_instance_setting' => $this->randomName(),
      ),
      'widget' => array(
        'type' => 'test_field_widget',
        'label' => 'Test Field',
        'settings' => array(
          'test_widget_setting' => $this->randomName(),
        )
      )
    );

    $instances[1] = $instance;

    // Add a field instance to the bundles.
    $instances[1]['bundle'] = 'bundle1';
    $instances[1]['entity_type'] = 'test_entity_bundle_key';
    field_create_instance($instances[1]);
    $instances[1]['bundle'] = $instances[1]['entity_type'] = 'test_entity_bundle';
    field_create_instance($instances[1]);

    $this->instances = $instances;
    // Write entity base table if there is one.
    $entities = array();

    // Create entities which have a 'bundle key' defined.
    for ($i = 1; $i < 7; $i++) {
      $entity = new stdClass();
      $entity->ftid = $i;
      $entity->fttype = ($i < 5) ? 'bundle1' : 'bundle2';

      $entity->{$this->field_names[0]}[LANGUAGE_NOT_SPECIFIED][0]['value'] = $i;
      drupal_write_record('test_entity_bundle_key', $entity);
      field_attach_insert('test_entity_bundle_key', $entity);
    }

    $entity = new stdClass();
    $entity->ftid = 5;
    $entity->fttype = 'test_entity_bundle';
    $entity->{$this->field_names[1]}[LANGUAGE_NOT_SPECIFIED][0]['shape'] = 'square';
    $entity->{$this->field_names[1]}[LANGUAGE_NOT_SPECIFIED][0]['color'] = 'red';
    $entity->{$this->field_names[1]}[LANGUAGE_NOT_SPECIFIED][1]['shape'] = 'circle';
    $entity->{$this->field_names[1]}[LANGUAGE_NOT_SPECIFIED][1]['color'] = 'blue';
    drupal_write_record('test_entity_bundle', $entity);
    field_attach_insert('test_entity_bundle', $entity);

    $instances[2] = $instance;
    $instances[2]['bundle'] = 'test_bundle';
    $instances[2]['field_name'] = $this->field_names[0];
    $instances[2]['entity_type'] = 'test_entity';
    field_create_instance($instances[2]);

    // Create entities with support for revisions.
    for ($i = 1; $i < 5; $i++) {
      $entity = new stdClass();
      $entity->ftid = $i;
      $entity->ftvid = $i;
      $entity->fttype = 'test_bundle';
      $entity->{$this->field_names[0]}[LANGUAGE_NOT_SPECIFIED][0]['value'] = $i;

      drupal_write_record('test_entity', $entity);
      field_attach_insert('test_entity', $entity);
      drupal_write_record('test_entity_revision', $entity);
    }

    // Add two revisions to an entity.
    for ($i = 100; $i < 102; $i++) {
      $entity = new stdClass();
      $entity->ftid = 4;
      $entity->ftvid = $i;
      $entity->fttype = 'test_bundle';
      $entity->{$this->field_names[0]}[LANGUAGE_NOT_SPECIFIED][0]['value'] = $i;

      drupal_write_record('test_entity', $entity, 'ftid');
      drupal_write_record('test_entity_revision', $entity);

      db_update('test_entity')
       ->fields(array('ftvid' => $entity->ftvid))
       ->condition('ftid', $entity->ftid)
       ->execute();

      field_attach_update('test_entity', $entity);
    }
  }

  /**
   * Tests EntityFieldQuery.
   */
  function testEntityFieldQuery() {
    $query = new EntityFieldQuery();
    $query
      ->entityCondition('entity_type', 'test_entity_bundle')
      ->entityCondition('entity_id', '5');
    $this->assertEntityFieldQuery($query, array(
      array('test_entity_bundle', 5),
    ), t('Test query on an entity type with a generated bundle.'));

    // Test entity_type condition.
    $query = new EntityFieldQuery();
    $query->entityCondition('entity_type', 'test_entity_bundle_key');
    $this->assertEntityFieldQuery($query, array(
      array('test_entity_bundle_key', 1),
      array('test_entity_bundle_key', 2),
      array('test_entity_bundle_key', 3),
      array('test_entity_bundle_key', 4),
      array('test_entity_bundle_key', 5),
      array('test_entity_bundle_key', 6),
    ), t('Test entity entity_type condition.'));

    // Test entity_id condition.
    $query = new EntityFieldQuery();
    $query
      ->entityCondition('entity_type', 'test_entity_bundle_key')
      ->entityCondition('entity_id', '3');
    $this->assertEntityFieldQuery($query, array(
      array('test_entity_bundle_key', 3),
    ), t('Test entity entity_id condition.'));

    $query = new EntityFieldQuery();
    $query
      ->entityCondition('entity_type', 'test_entity_bundle_key')
      ->propertyCondition('ftid', '3');
    $this->assertEntityFieldQuery($query, array(
      array('test_entity_bundle_key', 3),
    ), t('Test entity entity_id condition and entity_id property condition.'));

    // Test bundle condition.
    $query = new EntityFieldQuery();
    $query
      ->entityCondition('entity_type', 'test_entity_bundle_key')
      ->entityCondition('bundle', 'bundle1');
    $this->assertEntityFieldQuery($query, array(
      array('test_entity_bundle_key', 1),
      array('test_entity_bundle_key', 2),
      array('test_entity_bundle_key', 3),
      array('test_entity_bundle_key', 4),
    ), t('Test entity bundle condition: bundle1.'));

    $query = new EntityFieldQuery();
    $query
      ->entityCondition('entity_type', 'test_entity_bundle_key')
      ->entityCondition('bundle', 'bundle2');
    $this->assertEntityFieldQuery($query, array(
      array('test_entity_bundle_key', 5),
      array('test_entity_bundle_key', 6),
    ), t('Test entity bundle condition: bundle2.'));

    $query = new EntityFieldQuery();
    $query
      ->entityCondition('entity_type', 'test_entity_bundle_key')
      ->propertyCondition('fttype', 'bundle2');
    $this->assertEntityFieldQuery($query, array(
      array('test_entity_bundle_key', 5),
      array('test_entity_bundle_key', 6),
    ), t('Test entity bundle condition and bundle property condition.'));

    // Test revision_id condition.
    $query = new EntityFieldQuery();
    $query
      ->entityCondition('entity_type', 'test_entity')
      ->entityCondition('revision_id', '3');
    $this->assertEntityFieldQuery($query, array(
      array('test_entity', 3),
    ), t('Test entity revision_id condition.'));

    $query = new EntityFieldQuery();
    $query
      ->entityCondition('entity_type', 'test_entity')
      ->propertyCondition('ftvid', '3');
    $this->assertEntityFieldQuery($query, array(
      array('test_entity', 3),
    ), t('Test entity revision_id condition and revision_id property condition.'));

    $query = new EntityFieldQuery();
    $query
      ->entityCondition('entity_type', 'test_entity')
      ->fieldCondition($this->fields[0], 'value', 100, '>=')
      ->age(FIELD_LOAD_REVISION);
    $this->assertEntityFieldQuery($query, array(
        array('test_entity', 100),
        array('test_entity', 101),
    ), t('Test revision age.'));

    // Test that fields attached to the non-revision supporting entity
    // 'test_entity_bundle_key' are reachable in FIELD_LOAD_REVISION.
    $query = new EntityFieldQuery();
    $query
      ->fieldCondition($this->fields[0], 'value', 100, '<')
      ->age(FIELD_LOAD_REVISION);
    $this->assertEntityFieldQuery($query, array(
        array('test_entity_bundle_key', 1),
        array('test_entity_bundle_key', 2),
        array('test_entity_bundle_key', 3),
        array('test_entity_bundle_key', 4),
        array('test_entity_bundle_key', 5),
        array('test_entity_bundle_key', 6),
        array('test_entity', 1),
        array('test_entity', 2),
        array('test_entity', 3),
        array('test_entity', 4),
    ), t('Test that fields are reachable from FIELD_LOAD_REVISION even for non-revision entities.'));

    // Test entity sort by entity_id.
    $query = new EntityFieldQuery();
    $query
      ->entityCondition('entity_type', 'test_entity_bundle_key')
      ->entityOrderBy('entity_id', 'ASC');
    $this->assertEntityFieldQuery($query, array(
      array('test_entity_bundle_key', 1),
      array('test_entity_bundle_key', 2),
      array('test_entity_bundle_key', 3),
      array('test_entity_bundle_key', 4),
      array('test_entity_bundle_key', 5),
      array('test_entity_bundle_key', 6),
    ), t('Test sort entity entity_id in ascending order.'), TRUE);

    $query = new EntityFieldQuery();
    $query
      ->entityCondition('entity_type', 'test_entity_bundle_key')
      ->entityOrderBy('entity_id', 'DESC');
    $this->assertEntityFieldQuery($query, array(
      array('test_entity_bundle_key', 6),
      array('test_entity_bundle_key', 5),
      array('test_entity_bundle_key', 4),
      array('test_entity_bundle_key', 3),
      array('test_entity_bundle_key', 2),
      array('test_entity_bundle_key', 1),
    ), t('Test sort entity entity_id in descending order.'), TRUE);

    // Test entity sort by entity_id, with a field condition.
    $query = new EntityFieldQuery();
    $query
      ->entityCondition('entity_type', 'test_entity_bundle_key')
      ->fieldCondition($this->fields[0], 'value', 0, '>')
      ->entityOrderBy('entity_id', 'ASC');
    $this->assertEntityFieldQuery($query, array(
      array('test_entity_bundle_key', 1),
      array('test_entity_bundle_key', 2),
      array('test_entity_bundle_key', 3),
      array('test_entity_bundle_key', 4),
      array('test_entity_bundle_key', 5),
      array('test_entity_bundle_key', 6),
    ), t('Test sort entity entity_id in ascending order, with a field condition.'), TRUE);

    $query = new EntityFieldQuery();
    $query
      ->entityCondition('entity_type', 'test_entity_bundle_key')
      ->fieldCondition($this->fields[0], 'value', 0, '>')
      ->propertyOrderBy('ftid', 'DESC');
    $this->assertEntityFieldQuery($query, array(
      array('test_entity_bundle_key', 6),
      array('test_entity_bundle_key', 5),
      array('test_entity_bundle_key', 4),
      array('test_entity_bundle_key', 3),
      array('test_entity_bundle_key', 2),
      array('test_entity_bundle_key', 1),
    ), t('Test sort entity entity_id property in descending order, with a field condition.'), TRUE);

    // Test property sort by entity id.
    $query = new EntityFieldQuery();
    $query
      ->entityCondition('entity_type', 'test_entity_bundle_key')
      ->propertyOrderBy('ftid', 'ASC');
    $this->assertEntityFieldQuery($query, array(
      array('test_entity_bundle_key', 1),
      array('test_entity_bundle_key', 2),
      array('test_entity_bundle_key', 3),
      array('test_entity_bundle_key', 4),
      array('test_entity_bundle_key', 5),
      array('test_entity_bundle_key', 6),
    ), t('Test sort entity entity_id property in ascending order.'), TRUE);

    $query = new EntityFieldQuery();
    $query
      ->entityCondition('entity_type', 'test_entity_bundle_key')
      ->propertyOrderBy('ftid', 'DESC');
    $this->assertEntityFieldQuery($query, array(
      array('test_entity_bundle_key', 6),
      array('test_entity_bundle_key', 5),
      array('test_entity_bundle_key', 4),
      array('test_entity_bundle_key', 3),
      array('test_entity_bundle_key', 2),
      array('test_entity_bundle_key', 1),
    ), t('Test sort entity entity_id property in descending order.'), TRUE);

    // Test property sort by entity id, with a field condition.
    $query = new EntityFieldQuery();
    $query
      ->entityCondition('entity_type', 'test_entity_bundle_key')
      ->fieldCondition($this->fields[0], 'value', 0, '>')
      ->propertyOrderBy('ftid', 'ASC');
    $this->assertEntityFieldQuery($query, array(
      array('test_entity_bundle_key', 1),
      array('test_entity_bundle_key', 2),
      array('test_entity_bundle_key', 3),
      array('test_entity_bundle_key', 4),
      array('test_entity_bundle_key', 5),
      array('test_entity_bundle_key', 6),
    ), t('Test sort entity entity_id property in ascending order, with a field condition.'), TRUE);

    $query = new EntityFieldQuery();
    $query
      ->entityCondition('entity_type', 'test_entity_bundle_key')
      ->fieldCondition($this->fields[0], 'value', 0, '>')
      ->propertyOrderBy('ftid', 'DESC');
    $this->assertEntityFieldQuery($query, array(
      array('test_entity_bundle_key', 6),
      array('test_entity_bundle_key', 5),
      array('test_entity_bundle_key', 4),
      array('test_entity_bundle_key', 3),
      array('test_entity_bundle_key', 2),
      array('test_entity_bundle_key', 1),
    ), t('Test sort entity entity_id property in descending order, with a field condition.'), TRUE);

    // Test entity sort by bundle.
    $query = new EntityFieldQuery();
    $query
      ->entityCondition('entity_type', 'test_entity_bundle_key')
      ->entityOrderBy('bundle', 'ASC')
      ->propertyOrderBy('ftid', 'DESC');
    $this->assertEntityFieldQuery($query, array(
      array('test_entity_bundle_key', 4),
      array('test_entity_bundle_key', 3),
      array('test_entity_bundle_key', 2),
      array('test_entity_bundle_key', 1),
      array('test_entity_bundle_key', 6),
      array('test_entity_bundle_key', 5),
    ), t('Test sort entity bundle in ascending order, property in descending order.'), TRUE);

    $query = new EntityFieldQuery();
    $query
      ->entityCondition('entity_type', 'test_entity_bundle_key')
      ->entityOrderBy('bundle', 'DESC')
      ->propertyOrderBy('ftid', 'ASC');
    $this->assertEntityFieldQuery($query, array(
      array('test_entity_bundle_key', 5),
      array('test_entity_bundle_key', 6),
      array('test_entity_bundle_key', 1),
      array('test_entity_bundle_key', 2),
      array('test_entity_bundle_key', 3),
      array('test_entity_bundle_key', 4),
    ), t('Test sort entity bundle in descending order, property in ascending order.'), TRUE);

    // Test entity sort by bundle, with a field condition.
    $query = new EntityFieldQuery();
    $query
      ->entityCondition('entity_type', 'test_entity_bundle_key')
      ->fieldCondition($this->fields[0], 'value', 0, '>')
      ->entityOrderBy('bundle', 'ASC')
      ->propertyOrderBy('ftid', 'DESC');
    $this->assertEntityFieldQuery($query, array(
      array('test_entity_bundle_key', 4),
      array('test_entity_bundle_key', 3),
      array('test_entity_bundle_key', 2),
      array('test_entity_bundle_key', 1),
      array('test_entity_bundle_key', 6),
      array('test_entity_bundle_key', 5),
    ), t('Test sort entity bundle in ascending order, property in descending order, with a field condition.'), TRUE);

    $query = new EntityFieldQuery();
    $query
      ->entityCondition('entity_type', 'test_entity_bundle_key')
      ->fieldCondition($this->fields[0], 'value', 0, '>')
      ->entityOrderBy('bundle', 'DESC')
      ->propertyOrderBy('ftid', 'ASC');
    $this->assertEntityFieldQuery($query, array(
      array('test_entity_bundle_key', 5),
      array('test_entity_bundle_key', 6),
      array('test_entity_bundle_key', 1),
      array('test_entity_bundle_key', 2),
      array('test_entity_bundle_key', 3),
      array('test_entity_bundle_key', 4),
    ), t('Test sort entity bundle in descending order, property in ascending order, with a field condition.'), TRUE);

    // Test entity sort by bundle, field.
    $query = new EntityFieldQuery();
    $query
      ->entityCondition('entity_type', 'test_entity_bundle_key')
      ->entityOrderBy('bundle', 'ASC')
      ->fieldOrderBy($this->fields[0], 'value', 'DESC');
    $this->assertEntityFieldQuery($query, array(
      array('test_entity_bundle_key', 4),
      array('test_entity_bundle_key', 3),
      array('test_entity_bundle_key', 2),
      array('test_entity_bundle_key', 1),
      array('test_entity_bundle_key', 6),
      array('test_entity_bundle_key', 5),
    ), t('Test sort entity bundle in ascending order, field in descending order.'), TRUE);

    $query = new EntityFieldQuery();
    $query
      ->entityCondition('entity_type', 'test_entity_bundle_key')
      ->entityOrderBy('bundle', 'DESC')
      ->fieldOrderBy($this->fields[0], 'value', 'ASC');
    $this->assertEntityFieldQuery($query, array(
      array('test_entity_bundle_key', 5),
      array('test_entity_bundle_key', 6),
      array('test_entity_bundle_key', 1),
      array('test_entity_bundle_key', 2),
      array('test_entity_bundle_key', 3),
      array('test_entity_bundle_key', 4),
    ), t('Test sort entity bundle in descending order, field in ascending order.'), TRUE);

    // Test entity sort by revision_id.
    $query = new EntityFieldQuery();
    $query
      ->entityCondition('entity_type', 'test_entity')
      ->entityOrderBy('revision_id', 'ASC');
    $this->assertEntityFieldQuery($query, array(
      array('test_entity', 1),
      array('test_entity', 2),
      array('test_entity', 3),
      array('test_entity', 4),
    ), t('Test sort entity revision_id in ascending order.'), TRUE);

    $query = new EntityFieldQuery();
    $query
      ->entityCondition('entity_type', 'test_entity')
      ->entityOrderBy('revision_id', 'DESC');
    $this->assertEntityFieldQuery($query, array(
      array('test_entity', 4),
      array('test_entity', 3),
      array('test_entity', 2),
      array('test_entity', 1),
    ), t('Test sort entity revision_id in descending order.'), TRUE);

    // Test entity sort by revision_id, with a field condition.
    $query = new EntityFieldQuery();
    $query
      ->entityCondition('entity_type', 'test_entity')
      ->fieldCondition($this->fields[0], 'value', 0, '>')
      ->entityOrderBy('revision_id', 'ASC');
    $this->assertEntityFieldQuery($query, array(
      array('test_entity', 1),
      array('test_entity', 2),
      array('test_entity', 3),
      array('test_entity', 4),
    ), t('Test sort entity revision_id in ascending order, with a field condition.'), TRUE);

    $query = new EntityFieldQuery();
    $query
      ->entityCondition('entity_type', 'test_entity')
      ->fieldCondition($this->fields[0], 'value', 0, '>')
      ->entityOrderBy('revision_id', 'DESC');
    $this->assertEntityFieldQuery($query, array(
      array('test_entity', 4),
      array('test_entity', 3),
      array('test_entity', 2),
      array('test_entity', 1),
    ), t('Test sort entity revision_id in descending order, with a field condition.'), TRUE);

    // Test property sort by revision_id.
    $query = new EntityFieldQuery();
    $query
      ->entityCondition('entity_type', 'test_entity')
      ->propertyOrderBy('ftvid', 'ASC');
    $this->assertEntityFieldQuery($query, array(
      array('test_entity', 1),
      array('test_entity', 2),
      array('test_entity', 3),
      array('test_entity', 4),
    ), t('Test sort entity revision_id property in ascending order.'), TRUE);

    $query = new EntityFieldQuery();
    $query
      ->entityCondition('entity_type', 'test_entity')
      ->propertyOrderBy('ftvid', 'DESC');
    $this->assertEntityFieldQuery($query, array(
      array('test_entity', 4),
      array('test_entity', 3),
      array('test_entity', 2),
      array('test_entity', 1),
    ), t('Test sort entity revision_id property in descending order.'), TRUE);

    // Test property sort by revision_id, with a field condition.
    $query = new EntityFieldQuery();
    $query
      ->entityCondition('entity_type', 'test_entity')
      ->fieldCondition($this->fields[0], 'value', 0, '>')
      ->propertyOrderBy('ftvid', 'ASC');
    $this->assertEntityFieldQuery($query, array(
      array('test_entity', 1),
      array('test_entity', 2),
      array('test_entity', 3),
      array('test_entity', 4),
    ), t('Test sort entity revision_id property in ascending order, with a field condition.'), TRUE);

    $query = new EntityFieldQuery();
    $query
      ->entityCondition('entity_type', 'test_entity')
      ->fieldCondition($this->fields[0], 'value', 0, '>')
      ->propertyOrderBy('ftvid', 'DESC');
    $this->assertEntityFieldQuery($query, array(
      array('test_entity', 4),
      array('test_entity', 3),
      array('test_entity', 2),
      array('test_entity', 1),
    ), t('Test sort entity revision_id property in descending order, with a field condition.'), TRUE);

    $query = new EntityFieldQuery();
    $query
      ->entityCondition('entity_type', 'test_entity_bundle_key')
      ->fieldOrderBy($this->fields[0], 'value', 'ASC');
    $this->assertEntityFieldQuery($query, array(
      array('test_entity_bundle_key', 1),
      array('test_entity_bundle_key', 2),
      array('test_entity_bundle_key', 3),
      array('test_entity_bundle_key', 4),
      array('test_entity_bundle_key', 5),
      array('test_entity_bundle_key', 6),
    ), t('Test sort field in ascending order without field condition.'), TRUE);

    $query = new EntityFieldQuery();
    $query
      ->entityCondition('entity_type', 'test_entity_bundle_key')
      ->fieldOrderBy($this->fields[0], 'value', 'DESC');
    $this->assertEntityFieldQuery($query, array(
      array('test_entity_bundle_key', 6),
      array('test_entity_bundle_key', 5),
      array('test_entity_bundle_key', 4),
      array('test_entity_bundle_key', 3),
      array('test_entity_bundle_key', 2),
      array('test_entity_bundle_key', 1),
    ), t('Test sort field in descending order without field condition.'), TRUE);

    $query = new EntityFieldQuery();
    $query
      ->entityCondition('entity_type', 'test_entity_bundle_key')
      ->fieldCondition($this->fields[0], 'value', 0, '>')
      ->fieldOrderBy($this->fields[0], 'value', 'ASC');
    $this->assertEntityFieldQuery($query, array(
      array('test_entity_bundle_key', 1),
      array('test_entity_bundle_key', 2),
      array('test_entity_bundle_key', 3),
      array('test_entity_bundle_key', 4),
      array('test_entity_bundle_key', 5),
      array('test_entity_bundle_key', 6),
    ), t('Test sort field in ascending order.'), TRUE);

    $query = new EntityFieldQuery();
    $query
      ->entityCondition('entity_type', 'test_entity_bundle_key')
      ->fieldCondition($this->fields[0], 'value', 0, '>')
      ->fieldOrderBy($this->fields[0], 'value', 'DESC');
    $this->assertEntityFieldQuery($query, array(
      array('test_entity_bundle_key', 6),
      array('test_entity_bundle_key', 5),
      array('test_entity_bundle_key', 4),
      array('test_entity_bundle_key', 3),
      array('test_entity_bundle_key', 2),
      array('test_entity_bundle_key', 1),
    ), t('Test sort field in descending order.'), TRUE);

    // Test "in" operation with entity entity_type condition and entity_id
    // property condition.
    $query = new EntityFieldQuery();
    $query
      ->entityCondition('entity_type', 'test_entity_bundle_key')
      ->propertyCondition('ftid', array(1, 3, 4), 'IN');
    $this->assertEntityFieldQuery($query, array(
      array('test_entity_bundle_key', 1),
      array('test_entity_bundle_key', 3),
      array('test_entity_bundle_key', 4),
    ), t('Test "in" operation with entity entity_type condition and entity_id property condition.'));

    // Test "in" operation with entity entity_type condition and entity_id
    // property condition. Sort in descending order by entity_id.
    $query = new EntityFieldQuery();
    $query
      ->entityCondition('entity_type', 'test_entity_bundle_key')
      ->propertyCondition('ftid', array(1, 3, 4), 'IN')
      ->propertyOrderBy('ftid', 'DESC');
    $this->assertEntityFieldQuery($query, array(
      array('test_entity_bundle_key', 4),
      array('test_entity_bundle_key', 3),
      array('test_entity_bundle_key', 1),
    ), t('Test "in" operation with entity entity_type condition and entity_id property condition. Sort entity_id in descending order.'), TRUE);

    // Test query count
    $query = new EntityFieldQuery();
    $query_count = $query
      ->entityCondition('entity_type', 'test_entity_bundle_key')
      ->count()
      ->execute();
    $this->assertEqual($query_count, 6, t('Test query count on entity condition.'));

    $query = new EntityFieldQuery();
    $query_count = $query
      ->entityCondition('entity_type', 'test_entity_bundle_key')
      ->propertyCondition('ftid', '1')
      ->count()
      ->execute();
    $this->assertEqual($query_count, 1, t('Test query count on entity and property condition.'));

    $query = new EntityFieldQuery();
    $query_count = $query
      ->entityCondition('entity_type', 'test_entity_bundle_key')
      ->propertyCondition('ftid', '4', '>')
      ->count()
      ->execute();
    $this->assertEqual($query_count, 2, t('Test query count on entity and property condition with operator.'));

    $query = new EntityFieldQuery();
    $query_count = $query
      ->entityCondition('entity_type', 'test_entity_bundle_key')
      ->fieldCondition($this->fields[0], 'value', 3, '=')
      ->count()
      ->execute();
    $this->assertEqual($query_count, 1, t('Test query count on field condition.'));

    // First, test without options.
    $query = new EntityFieldQuery();
    $query
      ->entityCondition('entity_type', 'test_entity_bundle_key')
      ->propertyCondition('fttype', 'und', 'CONTAINS');
    $this->assertEntityFieldQuery($query, array(
      array('test_entity_bundle_key', 1),
      array('test_entity_bundle_key', 2),
      array('test_entity_bundle_key', 3),
      array('test_entity_bundle_key', 4),
      array('test_entity_bundle_key', 5),
      array('test_entity_bundle_key', 6),
    ), t('Test the "contains" operation on a property.'));

    $query = new EntityFieldQuery();
    $query->fieldCondition($this->fields[1], 'shape', 'uar', 'CONTAINS');
    $this->assertEntityFieldQuery($query, array(
      array('test_entity_bundle', 5),
    ), t('Test the "contains" operation on a field.'));

    $query = new EntityFieldQuery();
    $query
      ->entityCondition('entity_type', 'test_entity_bundle_key')
      ->propertyCondition('ftid', 1, '=');
    $this->assertEntityFieldQuery($query, array(
      array('test_entity_bundle_key', 1),
    ), t('Test the "equal to" operation on a property.'));

    $query = new EntityFieldQuery();
    $query->fieldCondition($this->fields[0], 'value', 3, '=');
    $this->assertEntityFieldQuery($query, array(
      array('test_entity_bundle_key', 3),
      array('test_entity', 3),
    ), t('Test the "equal to" operation on a field.'));

    $query = new EntityFieldQuery();
    $query
      ->entityCondition('entity_type', 'test_entity_bundle_key')
      ->propertyCondition('ftid', 3, '<>');
    $this->assertEntityFieldQuery($query, array(
      array('test_entity_bundle_key', 1),
      array('test_entity_bundle_key', 2),
      array('test_entity_bundle_key', 4),
      array('test_entity_bundle_key', 5),
      array('test_entity_bundle_key', 6),
    ), t('Test the "not equal to" operation on a property.'));

    $query = new EntityFieldQuery();
    $query->fieldCondition($this->fields[0], 'value', 3, '<>');
    $this->assertEntityFieldQuery($query, array(
      array('test_entity_bundle_key', 1),
      array('test_entity_bundle_key', 2),
      array('test_entity_bundle_key', 4),
      array('test_entity_bundle_key', 5),
      array('test_entity_bundle_key', 6),
      array('test_entity', 1),
      array('test_entity', 2),
      array('test_entity', 4),
    ), t('Test the "not equal to" operation on a field.'));

    $query = new EntityFieldQuery();
    $query
      ->entityCondition('entity_type', 'test_entity_bundle_key')
      ->propertyCondition('ftid', 2, '<');
    $this->assertEntityFieldQuery($query, array(
      array('test_entity_bundle_key', 1),
    ), t('Test the "less than" operation on a property.'));

    $query = new EntityFieldQuery();
    $query->fieldCondition($this->fields[0], 'value', 2, '<');
    $this->assertEntityFieldQuery($query, array(
      array('test_entity_bundle_key', 1),
      array('test_entity', 1),
    ), t('Test the "less than" operation on a field.'));

    $query = new EntityFieldQuery();
    $query
      ->entityCondition('entity_type', 'test_entity_bundle_key')
      ->propertyCondition('ftid', 2, '<=');
    $this->assertEntityFieldQuery($query, array(
      array('test_entity_bundle_key', 1),
      array('test_entity_bundle_key', 2),
    ), t('Test the "less than or equal to" operation on a property.'));

    $query = new EntityFieldQuery();
    $query->fieldCondition($this->fields[0], 'value', 2, '<=');
    $this->assertEntityFieldQuery($query, array(
      array('test_entity_bundle_key', 1),
      array('test_entity_bundle_key', 2),
      array('test_entity', 1),
      array('test_entity', 2),
    ), t('Test the "less than or equal to" operation on a field.'));

    $query = new EntityFieldQuery();
    $query
      ->entityCondition('entity_type', 'test_entity_bundle_key')
      ->propertyCondition('ftid', 4, '>');
    $this->assertEntityFieldQuery($query, array(
      array('test_entity_bundle_key', 5),
      array('test_entity_bundle_key', 6),
    ), t('Test the "greater than" operation on a property.'));

    $query = new EntityFieldQuery();
    $query->fieldCondition($this->fields[0], 'value', 2, '>');
    $this->assertEntityFieldQuery($query, array(
      array('test_entity_bundle_key', 3),
      array('test_entity_bundle_key', 4),
      array('test_entity_bundle_key', 5),
      array('test_entity_bundle_key', 6),
      array('test_entity', 3),
      array('test_entity', 4),
    ), t('Test the "greater than" operation on a field.'));

    $query = new EntityFieldQuery();
    $query
      ->entityCondition('entity_type', 'test_entity_bundle_key')
      ->propertyCondition('ftid', 4, '>=');
    $this->assertEntityFieldQuery($query, array(
      array('test_entity_bundle_key', 4),
      array('test_entity_bundle_key', 5),
      array('test_entity_bundle_key', 6),
    ), t('Test the "greater than or equal to" operation on a property.'));

    $query = new EntityFieldQuery();
    $query->fieldCondition($this->fields[0], 'value', 3, '>=');
    $this->assertEntityFieldQuery($query, array(
      array('test_entity_bundle_key', 3),
      array('test_entity_bundle_key', 4),
      array('test_entity_bundle_key', 5),
      array('test_entity_bundle_key', 6),
      array('test_entity', 3),
      array('test_entity', 4),
    ), t('Test the "greater than or equal to" operation on a field.'));

    $query = new EntityFieldQuery();
    $query
      ->entityCondition('entity_type', 'test_entity_bundle_key')
      ->propertyCondition('ftid', array(3, 4), 'NOT IN');
    $this->assertEntityFieldQuery($query, array(
      array('test_entity_bundle_key', 1),
      array('test_entity_bundle_key', 2),
      array('test_entity_bundle_key', 5),
      array('test_entity_bundle_key', 6),
    ), t('Test the "not in" operation on a property.'));

    $query = new EntityFieldQuery();
    $query->fieldCondition($this->fields[0], 'value', array(3, 4, 100, 101), 'NOT IN');
    $this->assertEntityFieldQuery($query, array(
      array('test_entity_bundle_key', 1),
      array('test_entity_bundle_key', 2),
      array('test_entity_bundle_key', 5),
      array('test_entity_bundle_key', 6),
      array('test_entity', 1),
      array('test_entity', 2),
    ), t('Test the "not in" operation on a field.'));

    $query = new EntityFieldQuery();
    $query
      ->entityCondition('entity_type', 'test_entity_bundle_key')
      ->propertyCondition('ftid', array(3, 4), 'IN');
    $this->assertEntityFieldQuery($query, array(
      array('test_entity_bundle_key', 3),
      array('test_entity_bundle_key', 4),
    ), t('Test the "in" operation on a property.'));

    $query = new EntityFieldQuery();
    $query->fieldCondition($this->fields[0], 'value', array(2, 3), 'IN');
    $this->assertEntityFieldQuery($query, array(
      array('test_entity_bundle_key', 2),
      array('test_entity_bundle_key', 3),
      array('test_entity', 2),
      array('test_entity', 3),
    ), t('Test the "in" operation on a field.'));

    $query = new EntityFieldQuery();
    $query
      ->entityCondition('entity_type', 'test_entity_bundle_key')
      ->propertyCondition('ftid', array(1, 3), 'BETWEEN');
    $this->assertEntityFieldQuery($query, array(
      array('test_entity_bundle_key', 1),
      array('test_entity_bundle_key', 2),
      array('test_entity_bundle_key', 3),
    ), t('Test the "between" operation on a property.'));

    $query = new EntityFieldQuery();
    $query->fieldCondition($this->fields[0], 'value', array(1, 3), 'BETWEEN');
    $this->assertEntityFieldQuery($query, array(
      array('test_entity_bundle_key', 1),
      array('test_entity_bundle_key', 2),
      array('test_entity_bundle_key', 3),
      array('test_entity', 1),
      array('test_entity', 2),
      array('test_entity', 3),
    ), t('Test the "between" operation on a field.'));

    $query = new EntityFieldQuery();
    $query
      ->entityCondition('entity_type', 'test_entity_bundle_key')
      ->propertyCondition('fttype', 'bun', 'STARTS_WITH');
    $this->assertEntityFieldQuery($query, array(
      array('test_entity_bundle_key', 1),
      array('test_entity_bundle_key', 2),
      array('test_entity_bundle_key', 3),
      array('test_entity_bundle_key', 4),
      array('test_entity_bundle_key', 5),
      array('test_entity_bundle_key', 6),
    ), t('Test the "starts_with" operation on a property.'));

    $query = new EntityFieldQuery();
    $query->fieldCondition($this->fields[1], 'shape', 'squ', 'STARTS_WITH');
    $this->assertEntityFieldQuery($query, array(
      array('test_entity_bundle', 5),
    ), t('Test the "starts_with" operation on a field.'));

    $query = new EntityFieldQuery();
    $query->fieldCondition($this->fields[0], 'value', 3);
    $this->assertEntityFieldQuery($query, array(
      array('test_entity_bundle_key', 3),
      array('test_entity', 3),
    ), t('Test omission of an operator with a single item.'));

    $query = new EntityFieldQuery();
    $query->fieldCondition($this->fields[0], 'value', array(2, 3));
    $this->assertEntityFieldQuery($query, array(
      array('test_entity_bundle_key', 2),
      array('test_entity_bundle_key', 3),
      array('test_entity', 2),
      array('test_entity', 3),
    ), t('Test omission of an operator with multiple items.'));

    $query = new EntityFieldQuery();
    $query
      ->entityCondition('entity_type', 'test_entity_bundle_key')
      ->propertyCondition('ftid', 1, '>')
      ->fieldCondition($this->fields[0], 'value', 4, '<');
    $this->assertEntityFieldQuery($query, array(
      array('test_entity_bundle_key', 2),
      array('test_entity_bundle_key', 3),
    ), t('Test entity, property and field conditions.'));

    $query = new EntityFieldQuery();
    $query
      ->entityCondition('entity_type', 'test_entity_bundle_key')
      ->entityCondition('bundle', 'bundle', 'STARTS_WITH')
      ->propertyCondition('ftid', 4)
      ->fieldCondition($this->fields[0], 'value', 4);
    $this->assertEntityFieldQuery($query, array(
      array('test_entity_bundle_key', 4),
    ), t('Test entity condition with "starts_with" operation, and property and field conditions.'));

    $query = new EntityFieldQuery();
    $query
      ->entityCondition('entity_type', 'test_entity_bundle_key')
      ->propertyOrderBy('ftid', 'ASC')
      ->range(0, 2);
    $this->assertEntityFieldQuery($query, array(
      array('test_entity_bundle_key', 1),
      array('test_entity_bundle_key', 2),
    ), t('Test limit on a property.'), TRUE);

    $query = new EntityFieldQuery();
    $query
      ->entityCondition('entity_type', 'test_entity_bundle_key')
      ->fieldCondition($this->fields[0], 'value', 0, '>=')
      ->fieldOrderBy($this->fields[0], 'value', 'ASC')
      ->range(0, 2);
    $this->assertEntityFieldQuery($query, array(
      array('test_entity_bundle_key', 1),
      array('test_entity_bundle_key', 2),
    ), t('Test limit on a field.'), TRUE);

    $query = new EntityFieldQuery();
    $query
      ->entityCondition('entity_type', 'test_entity_bundle_key')
      ->propertyOrderBy('ftid', 'ASC')
      ->range(4, 6);
    $this->assertEntityFieldQuery($query, array(
      array('test_entity_bundle_key', 5),
      array('test_entity_bundle_key', 6),
    ), t('Test offset on a property.'), TRUE);

    $query = new EntityFieldQuery();
    $query
      ->entityCondition('entity_type', 'test_entity_bundle_key')
      ->fieldCondition($this->fields[0], 'value', 0, '>')
      ->fieldOrderBy($this->fields[0], 'value', 'ASC')
      ->range(2, 4);
    $this->assertEntityFieldQuery($query, array(
      array('test_entity_bundle_key', 3),
      array('test_entity_bundle_key', 4),
      array('test_entity_bundle_key', 5),
      array('test_entity_bundle_key', 6),
    ), t('Test offset on a field.'), TRUE);

    for ($i = 6; $i < 10; $i++) {
      $entity = new stdClass();
      $entity->ftid = $i;
      $entity->fttype = 'test_entity_bundle';
      $entity->{$this->field_names[0]}[LANGUAGE_NOT_SPECIFIED][0]['value'] = $i - 5;
      drupal_write_record('test_entity_bundle', $entity);
      field_attach_insert('test_entity_bundle', $entity);
    }

    $query = new EntityFieldQuery();
    $query->fieldCondition($this->fields[0], 'value', 2, '>');
    $this->assertEntityFieldQuery($query, array(
      array('test_entity_bundle_key', 3),
      array('test_entity_bundle_key', 4),
      array('test_entity_bundle_key', 5),
      array('test_entity_bundle_key', 6),
      array('test_entity', 3),
      array('test_entity', 4),
      array('test_entity_bundle', 8),
      array('test_entity_bundle', 9),
    ), t('Select a field across multiple entities.'));

    $query = new EntityFieldQuery();
    $query
      ->fieldCondition($this->fields[1], 'shape', 'square')
      ->fieldCondition($this->fields[1], 'color', 'blue');
    $this->assertEntityFieldQuery($query, array(
      array('test_entity_bundle', 5),
    ), t('Test without a delta group.'));

    $query = new EntityFieldQuery();
    $query
      ->fieldCondition($this->fields[1], 'shape', 'square', '=', 'group')
      ->fieldCondition($this->fields[1], 'color', 'blue', '=', 'group');
    $this->assertEntityFieldQuery($query, array(), t('Test with a delta group.'));

    // Test query on a deleted field.
    field_attach_delete_bundle('test_entity_bundle_key', 'bundle1');
    field_attach_delete_bundle('test_entity', 'test_bundle');
    $query = new EntityFieldQuery();
    $query->fieldCondition($this->fields[0], 'value', '3');
    $this->assertEntityFieldQuery($query, array(
      array('test_entity_bundle', 8),
    ), t('Test query on a field after deleting field from some entities.'));

    field_attach_delete_bundle('test_entity_bundle', 'test_entity_bundle');
    $query = new EntityFieldQuery();
    $query->fieldCondition($this->fields[0], 'value', '3');
    $this->assertEntityFieldQuery($query, array(), t('Test query on a field after deleting field from all entities.'));

    $query = new EntityFieldQuery();
    $query
      ->fieldCondition($this->fields[0], 'value', '3')
      ->deleted(TRUE);
    $this->assertEntityFieldQuery($query, array(
      array('test_entity_bundle_key', 3),
      array('test_entity_bundle', 8),
      array('test_entity', 3),
    ), t('Test query on a deleted field with deleted option set to TRUE.'));

    $pass = FALSE;
    $query = new EntityFieldQuery();
    try {
      $query->execute();
    }
    catch (EntityFieldQueryException $exception) {
      $pass = ($exception->getMessage() == t('For this query an entity type must be specified.'));
    }
    $this->assertTrue($pass, t("Can't query the universe."));
  }

  /**
   * Tests querying translatable fields.
   */
  function testEntityFieldQueryTranslatable() {

    // Make a test field translatable AND cardinality one.
    $this->fields[0]['translatable'] = TRUE;
    $this->fields[0]['cardinality'] = 1;
    field_update_field($this->fields[0]);
    field_test_entity_info_translatable('test_entity', TRUE);

    // Create more items with different languages.
    $entity = new stdClass();
    $entity->ftid = 1;
    $entity->ftvid = 1;
    $entity->fttype = 'test_bundle';

    // Set fields in two languages with one field value.
    foreach (array(LANGUAGE_NOT_SPECIFIED, 'en') as $langcode) {
      $entity->{$this->field_names[0]}[$langcode][0]['value'] = 1234;
    }

    field_attach_update('test_entity', $entity);

    // Look up number of results when querying a single entity with multilingual
    // field values.
    $query = new EntityFieldQuery();
    $query_count = $query
      ->entityCondition('entity_type', 'test_entity')
      ->entityCondition('bundle', 'test_bundle')
      ->entityCondition('entity_id', '1')
      ->fieldCondition($this->fields[0])
      ->count()
      ->execute();

    $this->assertEqual($query_count, 1, t("Count on translatable cardinality one field is correct."));
  }


  /**
   * Tests field meta conditions.
   */
  function testEntityFieldQueryMetaConditions() {
    // Make a test field translatable.
    $this->fields[0]['translatable'] = TRUE;
    field_update_field($this->fields[0]);
    field_test_entity_info_translatable('test_entity', TRUE);

    // Create more items with different languages.
    $entity = new stdClass();
    $entity->ftid = 1;
    $entity->ftvid = 1;
    $entity->fttype = 'test_bundle';
    $j = 0;

    foreach (array(LANGUAGE_NOT_SPECIFIED, 'en') as $langcode) {
      for ($i = 0; $i < 4; $i++) {
        $entity->{$this->field_names[0]}[$langcode][$i]['value'] = $i + $j;
      }
      $j += 4;
    }

    field_attach_update('test_entity', $entity);

    // Test delta field meta condition.
    $query = new EntityFieldQuery();
    $query
      ->entityCondition('entity_type', 'test_entity', '=')
      ->fieldDeltaCondition($this->fields[0], 0, '>');
    $this->assertEntityFieldQuery($query, array(
      array('test_entity', 1),
    ), t('Test with a delta meta condition.'));

    // Test language field meta condition.
    $query = new EntityFieldQuery();
    $query
      ->entityCondition('entity_type', 'test_entity', '=')
      ->fieldLanguageCondition($this->fields[0], LANGUAGE_NOT_SPECIFIED, '<>');
    $this->assertEntityFieldQuery($query, array(
      array('test_entity', 1),
    ), t('Test with a language meta condition.'));

    // Test delta grouping.
    $query = new EntityFieldQuery();
    $query
      ->entityCondition('entity_type', 'test_entity', '=')
      ->fieldCondition($this->fields[0], 'value', 0, '=', 'group')
      ->fieldDeltaCondition($this->fields[0], 1, '<', 'group');
    $this->assertEntityFieldQuery($query, array(
      array('test_entity', 1),
    ), t('Test with a grouped delta meta condition.'));

    $query = new EntityFieldQuery();
    $query
      ->entityCondition('entity_type', 'test_entity', '=')
      ->fieldCondition($this->fields[0], 'value', 0, '=', 'group')
      ->fieldDeltaCondition($this->fields[0], 1, '>=', 'group');
    $this->assertEntityFieldQuery($query, array(), t('Test with a grouped delta meta condition (empty result set).'));

    // Test language grouping.
    $query = new EntityFieldQuery();
    $query
      ->entityCondition('entity_type', 'test_entity', '=')
      ->fieldCondition($this->fields[0], 'value', 0, '=', NULL, 'group')
      ->fieldLanguageCondition($this->fields[0], 'en', '<>', NULL, 'group');
    $this->assertEntityFieldQuery($query, array(
      array('test_entity', 1),
    ), t('Test with a grouped language meta condition.'));

    $query = new EntityFieldQuery();
    $query
      ->entityCondition('entity_type', 'test_entity', '=')
      ->fieldCondition($this->fields[0], 'value', 0, '=', NULL, 'group')
      ->fieldLanguageCondition($this->fields[0], LANGUAGE_NOT_SPECIFIED, '<>', NULL, 'group');
    $this->assertEntityFieldQuery($query, array(), t('Test with a grouped language meta condition (empty result set).'));

    // Test delta and language grouping.
    $query = new EntityFieldQuery();
    $query
      ->entityCondition('entity_type', 'test_entity', '=')
      ->fieldCondition($this->fields[0], 'value', 0, '=', 'delta', 'language')
      ->fieldDeltaCondition($this->fields[0], 1, '<', 'delta', 'language')
      ->fieldLanguageCondition($this->fields[0], 'en', '<>', 'delta', 'language');
    $this->assertEntityFieldQuery($query, array(
      array('test_entity', 1),
    ), t('Test with a grouped delta + language meta condition.'));

    $query = new EntityFieldQuery();
    $query
      ->entityCondition('entity_type', 'test_entity', '=')
      ->fieldCondition($this->fields[0], 'value', 0, '=', 'delta', 'language')
      ->fieldDeltaCondition($this->fields[0], 1, '>=', 'delta', 'language')
      ->fieldLanguageCondition($this->fields[0], 'en', '<>', 'delta', 'language');
    $this->assertEntityFieldQuery($query, array(), t('Test with a grouped delta + language meta condition (empty result set, delta condition unsatisifed).'));

    $query = new EntityFieldQuery();
    $query
      ->entityCondition('entity_type', 'test_entity', '=')
      ->fieldCondition($this->fields[0], 'value', 0, '=', 'delta', 'language')
      ->fieldDeltaCondition($this->fields[0], 1, '<', 'delta', 'language')
      ->fieldLanguageCondition($this->fields[0], LANGUAGE_NOT_SPECIFIED, '<>', 'delta', 'language');
    $this->assertEntityFieldQuery($query, array(), t('Test with a grouped delta + language meta condition (empty result set, language condition unsatisifed).'));

    $query = new EntityFieldQuery();
    $query
      ->entityCondition('entity_type', 'test_entity', '=')
      ->fieldCondition($this->fields[0], 'value', 0, '=', 'delta', 'language')
      ->fieldDeltaCondition($this->fields[0], 1, '>=', 'delta', 'language')
      ->fieldLanguageCondition($this->fields[0], LANGUAGE_NOT_SPECIFIED, '<>', 'delta', 'language');
    $this->assertEntityFieldQuery($query, array(), t('Test with a grouped delta + language meta condition (empty result set, both conditions unsatisifed).'));

    // Test grouping with another field to ensure that grouping cache is reset
    // properly.
    $query = new EntityFieldQuery();
    $query
      ->entityCondition('entity_type', 'test_entity_bundle', '=')
      ->fieldCondition($this->fields[1], 'shape', 'circle', '=', 'delta', 'language')
      ->fieldCondition($this->fields[1], 'color', 'blue', '=', 'delta', 'language')
      ->fieldDeltaCondition($this->fields[1], 1, '=', 'delta', 'language')
      ->fieldLanguageCondition($this->fields[1], LANGUAGE_NOT_SPECIFIED, '=', 'delta', 'language');
    $this->assertEntityFieldQuery($query, array(
      array('test_entity_bundle', 5),
    ), t('Test grouping cache.'));
  }

  /**
   * Tests the routing feature of EntityFieldQuery.
   */
  function testEntityFieldQueryRouting() {
    // Entity-only query.
    $query = new EntityFieldQuery();
    $query->entityCondition('entity_type', 'test_entity_bundle_key');
    $this->assertIdentical($query->queryCallback(), array($query, 'propertyQuery'), t('Entity-only queries are handled by the propertyQuery handler.'));

    // Field-only query.
    $query = new EntityFieldQuery();
    $query->fieldCondition($this->fields[0], 'value', '3');
    $this->assertIdentical($query->queryCallback(), 'field_sql_storage_field_storage_query', t('Pure field queries are handled by the Field storage handler.'));

    // Mixed entity and field query.
    $query = new EntityFieldQuery();
    $query
      ->entityCondition('entity_type', 'test_entity_bundle_key')
      ->fieldCondition($this->fields[0], 'value', '3');
    $this->assertIdentical($query->queryCallback(), 'field_sql_storage_field_storage_query', t('Mixed queries are handled by the Field storage handler.'));

    // Overriding with $query->executeCallback.
    $query = new EntityFieldQuery();
    $query->entityCondition('entity_type', 'test_entity_bundle_key');
    $query->executeCallback = 'field_test_dummy_field_storage_query';
    $this->assertEntityFieldQuery($query, array(
      array('user', 1),
    ), t('executeCallback can override the query handler.'));

    // Overriding with $query->executeCallback via hook_entity_query_alter().
    $query = new EntityFieldQuery();
    $query->entityCondition('entity_type', 'test_entity_bundle_key');
    // Add a flag that will be caught by field_test_entity_query_alter().
    $query->alterMyExecuteCallbackPlease = TRUE;
    $this->assertEntityFieldQuery($query, array(
      array('user', 1),
    ), t('executeCallback can override the query handler when set in a hook_entity_query_alter().'));

    // Mixed-storage queries.
    $query = new EntityFieldQuery();
    $query
      ->fieldCondition($this->fields[0], 'value', '3')
      ->fieldCondition($this->fields[1], 'shape', 'squ', 'STARTS_WITH');
    // Alter the storage of the field.
    $query->fields[1]['storage']['module'] = 'dummy_storage';
    try {
      $query->queryCallback();
    }
    catch (EntityFieldQueryException $exception) {
      $pass = ($exception->getMessage() == t("Can't handle more than one field storage engine"));
    }
    $this->assertTrue($pass, t('Cannot query across field storage engines.'));
  }

  /**
   * Tests the pager integration of EntityFieldQuery.
   */
  function testEntityFieldQueryPager() {
    // Test pager in propertyQuery
    $_GET['page'] = '0,1';
    $query = new EntityFieldQuery();
    $query
      ->entityCondition('entity_type', 'test_entity_bundle_key')
      ->propertyOrderBy('ftid', 'ASC')
      ->pager(3, 0);
    $this->assertEntityFieldQuery($query, array(
      array('test_entity_bundle_key', 1),
      array('test_entity_bundle_key', 2),
      array('test_entity_bundle_key', 3),
    ), t('Test pager integration in propertyQuery: page 1.'), TRUE);

    $query = new EntityFieldQuery();
    $query
      ->entityCondition('entity_type', 'test_entity_bundle_key')
      ->propertyOrderBy('ftid', 'ASC')
      ->pager(3, 1);
    $this->assertEntityFieldQuery($query, array(
      array('test_entity_bundle_key', 4),
      array('test_entity_bundle_key', 5),
      array('test_entity_bundle_key', 6),
    ), t('Test pager integration in propertyQuery: page 2.'), TRUE);

    // Test pager in field storage
    $_GET['page'] = '0,1';
    $query = new EntityFieldQuery();
    $query
      ->entityCondition('entity_type', 'test_entity_bundle_key')
      ->fieldCondition($this->fields[0], 'value', 0, '>')
      ->propertyOrderBy('ftid', 'ASC')
      ->pager(2, 0);
    $this->assertEntityFieldQuery($query, array(
      array('test_entity_bundle_key', 1),
      array('test_entity_bundle_key', 2),
    ), t('Test pager integration in field storage: page 1.'), TRUE);

    $query = new EntityFieldQuery();
    $query
      ->entityCondition('entity_type', 'test_entity_bundle_key')
      ->fieldCondition($this->fields[0], 'value', 0, '>')
      ->propertyOrderBy('ftid', 'ASC')
      ->pager(2, 1);
    $this->assertEntityFieldQuery($query, array(
      array('test_entity_bundle_key', 3),
      array('test_entity_bundle_key', 4),
    ), t('Test pager integration in field storage: page 2.'), TRUE);

    unset($_GET['page']);
  }

  /**
   * Tests disabling the pager in EntityFieldQuery.
   */
  function testEntityFieldQueryDisablePager() {
    // Test enabling a pager and then disabling it.
    $query = new EntityFieldQuery();
    $query
      ->entityCondition('entity_type', 'test_entity_bundle_key')
      ->propertyOrderBy('ftid', 'ASC')
      ->pager(1)
      ->pager(0);
    $this->assertEntityFieldQuery($query, array(
      array('test_entity_bundle_key', 1),
      array('test_entity_bundle_key', 2),
      array('test_entity_bundle_key', 3),
      array('test_entity_bundle_key', 4),
      array('test_entity_bundle_key', 5),
      array('test_entity_bundle_key', 6),
    ), 'All test entities are listed when the pager is enabled and then disabled.', TRUE);
  }

  /**
   * Tests the TableSort integration of EntityFieldQuery.
   */
  function testEntityFieldQueryTableSort() {
    // Test TableSort in propertyQuery
    $_GET['sort'] = 'asc';
    $_GET['order'] = 'Id';
    $header = array(
      'id' => array('data' => 'Id', 'type' => 'property',  'specifier' => 'ftid'),
      'type' => array('data' => 'Type', 'type' => 'entity', 'specifier' => 'bundle'),
    );
    $query = new EntityFieldQuery();
    $query
      ->entityCondition('entity_type', 'test_entity_bundle_key')
      ->tableSort($header);
    $this->assertEntityFieldQuery($query, array(
      array('test_entity_bundle_key', 1),
      array('test_entity_bundle_key', 2),
      array('test_entity_bundle_key', 3),
      array('test_entity_bundle_key', 4),
      array('test_entity_bundle_key', 5),
      array('test_entity_bundle_key', 6),
    ), t('Test TableSort by property: ftid ASC in propertyQuery.'), TRUE);

    $_GET['sort'] = 'desc';
    $_GET['order'] = 'Id';
    $query = new EntityFieldQuery();
    $query
      ->entityCondition('entity_type', 'test_entity_bundle_key')
      ->tableSort($header);
    $this->assertEntityFieldQuery($query, array(
      array('test_entity_bundle_key', 6),
      array('test_entity_bundle_key', 5),
      array('test_entity_bundle_key', 4),
      array('test_entity_bundle_key', 3),
      array('test_entity_bundle_key', 2),
      array('test_entity_bundle_key', 1),
    ), t('Test TableSort by property: ftid DESC in propertyQuery.'), TRUE);

    $_GET['sort'] = 'asc';
    $_GET['order'] = 'Type';
    $query = new EntityFieldQuery();
    $query
      ->entityCondition('entity_type', 'test_entity_bundle_key')
      ->tableSort($header);
    $this->assertEntityFieldQuery($query, array(
      array('test_entity_bundle_key', 1),
      array('test_entity_bundle_key', 2),
      array('test_entity_bundle_key', 3),
      array('test_entity_bundle_key', 4),
      array('test_entity_bundle_key', 5),
      array('test_entity_bundle_key', 6),
    ), t('Test TableSort by entity: bundle ASC in propertyQuery.'), TRUE);

    $_GET['sort'] = 'desc';
    $_GET['order'] = 'Type';
    $query = new EntityFieldQuery();
    $query
      ->entityCondition('entity_type', 'test_entity_bundle_key')
      ->tableSort($header);
    $this->assertEntityFieldQuery($query, array(
      array('test_entity_bundle_key', 5),
      array('test_entity_bundle_key', 6),
      array('test_entity_bundle_key', 1),
      array('test_entity_bundle_key', 2),
      array('test_entity_bundle_key', 3),
      array('test_entity_bundle_key', 4),
    ), t('Test TableSort by entity: bundle DESC in propertyQuery.'), TRUE);

    // Test TableSort in field storage
    $_GET['sort'] = 'asc';
    $_GET['order'] = 'Id';
    $header = array(
      'id' => array('data' => 'Id', 'type' => 'property',  'specifier' => 'ftid'),
      'type' => array('data' => 'Type', 'type' => 'entity', 'specifier' => 'bundle'),
      'field' => array('data' => 'Field', 'type' => 'field', 'specifier' => array('field' => $this->field_names[0], 'column' => 'value')),
    );
    $query = new EntityFieldQuery();
    $query
      ->entityCondition('entity_type', 'test_entity_bundle_key')
      ->fieldCondition($this->fields[0], 'value', 0, '>')
      ->tableSort($header);
    $this->assertEntityFieldQuery($query, array(
      array('test_entity_bundle_key', 1),
      array('test_entity_bundle_key', 2),
      array('test_entity_bundle_key', 3),
      array('test_entity_bundle_key', 4),
      array('test_entity_bundle_key', 5),
      array('test_entity_bundle_key', 6),
    ), t('Test TableSort by property: ftid ASC in field storage.'), TRUE);

    $_GET['sort'] = 'desc';
    $_GET['order'] = 'Id';
    $query = new EntityFieldQuery();
    $query
      ->entityCondition('entity_type', 'test_entity_bundle_key')
      ->fieldCondition($this->fields[0], 'value', 0, '>')
      ->tableSort($header);
    $this->assertEntityFieldQuery($query, array(
      array('test_entity_bundle_key', 6),
      array('test_entity_bundle_key', 5),
      array('test_entity_bundle_key', 4),
      array('test_entity_bundle_key', 3),
      array('test_entity_bundle_key', 2),
      array('test_entity_bundle_key', 1),
    ), t('Test TableSort by property: ftid DESC in field storage.'), TRUE);

    $_GET['sort'] = 'asc';
    $_GET['order'] = 'Type';
    $query = new EntityFieldQuery();
    $query
      ->entityCondition('entity_type', 'test_entity_bundle_key')
      ->fieldCondition($this->fields[0], 'value', 0, '>')
      ->tableSort($header)
      ->entityOrderBy('entity_id', 'DESC');
    $this->assertEntityFieldQuery($query, array(
      array('test_entity_bundle_key', 4),
      array('test_entity_bundle_key', 3),
      array('test_entity_bundle_key', 2),
      array('test_entity_bundle_key', 1),
      array('test_entity_bundle_key', 6),
      array('test_entity_bundle_key', 5),
    ), t('Test TableSort by entity: bundle ASC in field storage.'), TRUE);

    $_GET['sort'] = 'desc';
    $_GET['order'] = 'Type';
    $query = new EntityFieldQuery();
    $query
      ->entityCondition('entity_type', 'test_entity_bundle_key')
      ->fieldCondition($this->fields[0], 'value', 0, '>')
      ->tableSort($header)
      ->entityOrderBy('entity_id', 'ASC');
    $this->assertEntityFieldQuery($query, array(
      array('test_entity_bundle_key', 5),
      array('test_entity_bundle_key', 6),
      array('test_entity_bundle_key', 1),
      array('test_entity_bundle_key', 2),
      array('test_entity_bundle_key', 3),
      array('test_entity_bundle_key', 4),
    ), t('Test TableSort by entity: bundle DESC in field storage.'), TRUE);

    $_GET['sort'] = 'asc';
    $_GET['order'] = 'Field';
    $query = new EntityFieldQuery();
    $query
      ->entityCondition('entity_type', 'test_entity_bundle_key')
      ->fieldCondition($this->fields[0], 'value', 0, '>')
      ->tableSort($header);
    $this->assertEntityFieldQuery($query, array(
      array('test_entity_bundle_key', 1),
      array('test_entity_bundle_key', 2),
      array('test_entity_bundle_key', 3),
      array('test_entity_bundle_key', 4),
      array('test_entity_bundle_key', 5),
      array('test_entity_bundle_key', 6),
    ), t('Test TableSort by field ASC.'), TRUE);

    $_GET['sort'] = 'desc';
    $_GET['order'] = 'Field';
    $query = new EntityFieldQuery();
    $query
      ->entityCondition('entity_type', 'test_entity_bundle_key')
      ->fieldCondition($this->fields[0], 'value', 0, '>')
      ->tableSort($header);
    $this->assertEntityFieldQuery($query, array(
      array('test_entity_bundle_key', 6),
      array('test_entity_bundle_key', 5),
      array('test_entity_bundle_key', 4),
      array('test_entity_bundle_key', 3),
      array('test_entity_bundle_key', 2),
      array('test_entity_bundle_key', 1),
    ), t('Test TableSort by field DESC.'), TRUE);

    unset($_GET['sort']);
    unset($_GET['order']);
  }

  /**
   * Fetches the results of an EntityFieldQuery and compares.
   *
   * @param $query
   *   An EntityFieldQuery to run.
   * @param $intended_results
   *   A list of results, every entry is again a list, first being the entity
   *   type, the second being the entity_id.
   * @param $message
   *   The message to be displayed as the result of this test.
   * @param $ordered
   *   If FALSE then the result of EntityFieldQuery will match
   *   $intended_results even if the order is not the same. If TRUE then order
   *   should match too.
   */
  function assertEntityFieldQuery($query, $intended_results, $message, $ordered = FALSE) {
    $results = array();
    try {
      foreach ($query->execute() as $entity_type => $entity_ids) {
        foreach ($entity_ids as $entity_id => $stub_entity) {
          $results[] = array($entity_type, $entity_id);
        }
      }
      if (!isset($ordered) || !$ordered) {
        sort($results);
        sort($intended_results);
      }
      if (!$this->assertEqual($results, $intended_results, $message)) {
        debug($results, $message);
      }
    }
    catch (Exception $e) {
      $this->fail('Exception thrown: '. $e->getMessage());
    }
  }
}
