<?php

/**
 * @file
 * Hooks provided by the base system for language support.
 */

/**
 * @addtogroup hooks
 * @{
 */

/**
 * Allows modules to act after language initialization has been performed.
 *
 * This is primarily needed to provide translation for configuration variables
 * in the proper bootstrap phase. Variables are user-defined strings and
 * therefore should not be translated via t(), since the source string can
 * change without notice and any previous translation would be lost. Moreover,
 * since variables can be used in the bootstrap phase, we need a bootstrap hook
 * to provide a translation early enough to avoid misalignments between code
 * using the original values and code using the translated values. However
 * modules implementing hook_boot() should be aware that language initialization
 * did not happen yet and thus they cannot rely on translated variables.
 */
function hook_language_init() {
  global $conf;

  switch (drupal_container()->get(LANGUAGE_TYPE_INTERFACE)->langcode) {
    case 'it':
      $conf['site_name'] = 'Il mio sito Drupal';
      break;

    case 'fr':
      $conf['site_name'] = 'Mon site Drupal';
      break;
  }
}

/**
 * Perform alterations on language switcher links.
 *
 * A language switcher link may need to point to a different path or use a
 * translated link text before going through l(), which will just handle the
 * path aliases.
 *
 * @param $links
 *   Nested array of links keyed by language code.
 * @param $type
 *   The language type the links will switch.
 * @param $path
 *   The current path.
 */
function hook_language_switch_links_alter(array &$links, $type, $path) {
  $language_interface = drupal_container()->get(LANGUAGE_TYPE_INTERFACE);

  if ($type == LANGUAGE_TYPE_CONTENT && isset($links[$language_interface->langcode])) {
    foreach ($links[$language_interface->langcode] as $link) {
      $link['attributes']['class'][] = 'active-language';
    }
  }
}

/**
 * Define language types.
 *
 * @return
 *   An associative array of language type definitions. The keys are the
 *   identifiers, which are also used as names for global variables representing
 *   the types in the bootstrap phase. The values are associative arrays that
 *   may contain the following elements:
 *   - name: The human-readable language type identifier.
 *   - description: A description of the language type.
 *   - fixed: A fixed array of language negotiation method identifiers to use to
 *     initialize this language. Defining this key makes the language type
 *     non-configurable, so it will always use the specified methods in the
 *     given priority order. Omit to make the language type configurable.
 *
 * @see hook_language_types_info_alter()
 * @ingroup language_negotiation
 */
function hook_language_types_info() {
  return array(
    'custom_language_type' => array(
      'name' => t('Custom language'),
      'description' => t('A custom language type.'),
    ),
    'fixed_custom_language_type' => array(
      'fixed' => array('custom_language_negotiation_method'),
    ),
  );
}

/**
 * Perform alterations on language types.
 *
 * @param $language_types
 *   Array of language type definitions.
 *
 * @see hook_language_types_info()
 * @ingroup language_negotiation
 */
function hook_language_types_info_alter(array &$language_types) {
  if (isset($language_types['custom_language_type'])) {
    $language_types['custom_language_type_custom']['description'] = t('A far better description.');
  }
}

/**
 * Define language negotiation methods.
 *
 * @return
 *   An associative array of language negotiation method definitions. The keys
 *   are method identifiers, and the values are associative arrays definining
 *   each method, with the following elements:
 *   - types: An array of allowed language types. If a language negotiation
 *     method does not specify which language types it should be used with, it
 *     will be available for all the configurable language types.
 *   - callbacks: An associative array of functions that will be called to
 *     perform various tasks. Possible elements are:
 *     - negotiation: (required) Name of the callback function that determines
 *       the language value.
 *     - language_switch: (optional) Name of the callback function that
 *       determines links for a language switcher block associated with this
 *       method. See language_switcher_url() for an example.
 *     - url_rewrite: (optional) Name of the callback function that provides URL
 *       rewriting, if needed by this method.
 *   - file: The file where callback functions are defined (this file will be
 *     included before the callbacks are invoked).
 *   - weight: The default weight of the method.
 *   - name: The translated human-readable name for the method.
 *   - description: A translated longer description of the method.
 *   - config: An internal path pointing to the method's configuration page.
 *   - cache: The value Drupal's page cache should be set to for the current
 *     method to be invoked.
 *
 * @see hook_language_negotiation_info_alter()
 * @ingroup language_negotiation
 */
function hook_language_negotiation_info() {
  return array(
    'custom_language_negotiation_method' => array(
      'callbacks' => array(
        'negotiation' => 'custom_negotiation_callback',
        'language_switch' => 'custom_language_switch_callback',
        'url_rewrite' => 'custom_url_rewrite_callback',
      ),
      'file' => drupal_get_path('module', 'custom') . '/custom.module',
      'weight' => -4,
      'types' => array('custom_language_type'),
      'name' => t('Custom language negotiation method'),
      'description' => t('This is a custom language negotiation method.'),
      'cache' => 0,
    ),
  );
}

/**
 * Perform alterations on language negotiation methods.
 *
 * @param $negotiation_info
 *   Array of language negotiation method definitions.
 *
 * @see hook_language_negotiation_info()
 * @ingroup language_negotiation
 */
function hook_language_negotiation_info_alter(array &$negotiation_info) {
  if (isset($negotiation_info['custom_language_method'])) {
    $negotiation_info['custom_language_method']['config'] = 'admin/config/regional/language/detection/custom-language-method';
  }
}

/**
 * Perform alterations on the language fallback candidates.
 *
 * @param $fallback_candidates
 *   An array of language codes whose order will determine the language fallback
 *   order.
 */
function hook_language_fallback_candidates_alter(array &$fallback_candidates) {
  $fallback_candidates = array_reverse($fallback_candidates);
}

/**
 * @} End of "addtogroup hooks".
 */
