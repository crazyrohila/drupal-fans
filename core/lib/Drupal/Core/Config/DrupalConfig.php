<?php

namespace Drupal\Core\Config;

use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Config\ConfigException;

/**
 * Represents the default configuration storage object.
 */
class DrupalConfig {

  /**
   * The storage engine to save this config object to.
   *
   * @var StorageInterface
   */
  protected $storage;

  /**
   * The data of the configuration object.
   *
   * @var array
   */
  protected $data = array();

  /**
   * Constructs a DrupalConfig object.
   *
   * @param StorageInterface $storage
   *   The storage engine where this config object should be saved.
   *
   * @todo $this should really know about $name and make it publicly accessible.
   */
  public function __construct(StorageInterface $storage) {
    $this->storage = $storage;
    $this->read();
  }

  /**
   * Reads config data from the active store into our object.
   */
  public function read() {
    $data = $this->storage->read();
    $this->setData($data !== FALSE ? $data : array());
    return $this;
  }

  /**
   * Checks whether a particular value is overridden.
   *
   * @param $key
   *   @todo
   *
   * @return
   *   @todo
   */
  public function isOverridden($key) {
    return isset($this->_overrides[$key]);
  }

  /**
   * Gets data from this config object.
   *
   * @param $key
   *   A string that maps to a key within the configuration data.
   *   For instance in the following configuation array:
   *   @code
   *   array(
   *     'foo' => array(
   *       'bar' => 'baz',
   *     ),
   *   );
   *   @endcode
   *   A key of 'foo.bar' would return the string 'baz'. However, a key of 'foo'
   *   would return array('bar' => 'baz').
   *   If no key is specified, then the entire data array is returned.
   *
   * The configuration system does not retain data types. Every saved value is
   * casted to a string. In most cases this is not an issue; however, it can
   * cause issues with Booleans, which are casted to "1" (TRUE) or "0" (FALSE).
   * In particular, code relying on === or !== will no longer function properly.
   *
   * @see http://php.net/manual/language.operators.comparison.php.
   *
   * @return
   *   The data that was requested.
   */
  public function get($key = '') {
    global $conf;

    $name = $this->storage->getName();
    if (isset($conf[$name])) {
      $merged_data = drupal_array_merge_deep($this->data, $conf[$name]);
    }
    else {
      $merged_data = $this->data;
    }

    if (empty($key)) {
      return $merged_data;
    }
    else {
      $parts = explode('.', $key);
      if (count($parts) == 1) {
        return isset($merged_data[$key]) ? $merged_data[$key] : NULL;
      }
      else {
        $key_exists = NULL;
        $value = drupal_array_get_nested_value($merged_data, $parts, $key_exists);
        return $key_exists ? $value : NULL;
      }
    }
  }

  /**
   * Replaces the data of this configuration object.
   *
   * @param array $data
   *   The new configuration data.
   */
  public function setData(array $data) {
    $this->data = $data;
    return $this;
  }

  /**
   * Sets value in this config object.
   *
   * @param $key
   *   @todo
   * @param $value
   *   @todo
   */
  public function set($key, $value) {
    // Type-cast value into a string.
    $value = $this->castValue($value);

    // The dot/period is a reserved character; it may appear between keys, but
    // not within keys.
    $parts = explode('.', $key);
    if (count($parts) == 1) {
      $this->data[$key] = $value;
    }
    else {
      drupal_array_set_nested_value($this->data, $parts, $value);
    }
    return $this;
  }

  /**
   * Casts a saved value to a string.
   *
   * The configuration system only saves strings or arrays. Any scalar
   * non-string value is cast to a string. The one exception is boolean FALSE
   * which would normally become '' when cast to a string, but is manually
   * cast to '0' here for convenience and consistency.
   *
   * Any non-scalar value that is not an array (aka objects) gets cast
   * to an array.
   *
   * @param $value
   *   A value being saved into the configuration system.
   * @param $value
   *   The value cast to a string or array.
   */
  public function castValue($value) {
    if (is_scalar($value)) {
      // Handle special case of FALSE, which should be '0' instead of ''.
      if ($value === FALSE) {
        $value = '0';
      }
      else {
        $value = (string) $value;
      }
    }
    else {
      // Any non-scalar value must be an array.
      if (!is_array($value)) {
        $value = (array) $value;
      }
      // Recurse into any nested keys.
      foreach ($value as $key => $nested_value) {
        $value[$key] = $this->castValue($nested_value);
      }
    }
    return $value;
  }

  /**
   * Unsets value in this config object.
   *
   * @param $key
   *   Name of the key whose value should be unset.
   */
  public function clear($key) {
    $parts = explode('.', $key);
    if (count($parts) == 1) {
      unset($this->data[$key]);
    }
    else {
      drupal_array_unset_nested_value($this->data, $parts);
    }
  }

  /**
   * Saves the configuration object.
   */
  public function save() {
    $this->storage->write($this->data);
  }

  /**
   * Deletes the configuration object.
   */
  public function delete() {
    $this->data = array();
    $this->storage->delete();
  }
}
