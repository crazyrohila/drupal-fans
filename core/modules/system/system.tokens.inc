<?php

/**
 * @file
 * Builds placeholder replacement tokens system-wide data.
 *
 * This file handles tokens for the global 'site' token type, as well as
 * 'date' and 'file' tokens.
 */

/**
 * Implements hook_token_info().
 */
function system_token_info() {
  $types['site'] = array(
    'name' => t("Site information"),
    'description' => t("Tokens for site-wide settings and other global information."),
  );
  $types['date'] = array(
    'name' => t("Dates"),
    'description' => t("Tokens related to times and dates."),
  );
  $types['file'] = array(
    'name' => t("Files"),
    'description' => t("Tokens related to uploaded files."),
    'needs-data' => 'file',
  );

  // Site-wide global tokens.
  $site['name'] = array(
    'name' => t("Name"),
    'description' => t("The name of the site."),
  );
  $site['slogan'] = array(
    'name' => t("Slogan"),
    'description' => t("The slogan of the site."),
  );
  $site['mail'] = array(
    'name' => t("Email"),
    'description' => t("The administrative email address for the site."),
  );
  $site['url'] = array(
    'name' => t("URL"),
    'description' => t("The URL of the site's front page."),
  );
  $site['url-brief'] = array(
    'name' => t("URL (brief)"),
    'description' => t("The URL of the site's front page without the protocol."),
  );
  $site['login-url'] = array(
    'name' => t("Login page"),
    'description' => t("The URL of the site's login page."),
  );

  // Date related tokens.
  $date['short'] = array(
    'name' => t("Short format"),
    'description' => t("A date in 'short' format. (%date)", array('%date' => format_date(REQUEST_TIME, 'short'))),
  );
  $date['medium'] = array(
    'name' => t("Medium format"),
    'description' => t("A date in 'medium' format. (%date)", array('%date' => format_date(REQUEST_TIME, 'medium'))),
  );
  $date['long'] = array(
    'name' => t("Long format"),
    'description' => t("A date in 'long' format. (%date)", array('%date' => format_date(REQUEST_TIME, 'long'))),
  );
  $date['custom'] = array(
    'name' => t("Custom format"),
    'description' => t("A date in a custom format. See !php-date for details.", array('!php-date' => l(t('the PHP documentation'), 'http://php.net/manual/function.date.php'))),
  );
  $date['since'] = array(
    'name' => t("Time-since"),
    'description' => t("A date in 'time-since' format. (%date)", array('%date' => format_interval(REQUEST_TIME - 360, 2))),
  );
  $date['raw'] = array(
    'name' => t("Raw timestamp"),
    'description' => t("A date in UNIX timestamp format (%date)", array('%date' => REQUEST_TIME)),
  );


  // File related tokens.
  $file['fid'] = array(
    'name' => t("File ID"),
    'description' => t("The unique ID of the uploaded file."),
  );
  $file['name'] = array(
    'name' => t("File name"),
    'description' => t("The name of the file on disk."),
  );
  $file['path'] = array(
    'name' => t("Path"),
    'description' => t("The location of the file relative to Drupal root."),
  );
  $file['mime'] = array(
    'name' => t("MIME type"),
    'description' => t("The MIME type of the file."),
  );
  $file['size'] = array(
    'name' => t("File size"),
    'description' => t("The size of the file."),
  );
  $file['url'] = array(
    'name' => t("URL"),
    'description' => t("The web-accessible URL for the file."),
  );
  $file['timestamp'] = array(
    'name' => t("Timestamp"),
    'description' => t("The date the file was most recently changed."),
    'type' => 'date',
  );
  $file['owner'] = array(
    'name' => t("Owner"),
    'description' => t("The user who originally uploaded the file."),
    'type' => 'user',
  );

  return array(
    'types' => $types,
    'tokens' => array(
      'site' => $site,
      'date' => $date,
      'file' => $file,
    ),
  );
}

/**
 * Implements hook_tokens().
 */
function system_tokens($type, $tokens, array $data = array(), array $options = array()) {
  $url_options = array('absolute' => TRUE);
  if (isset($options['language'])) {
    $url_options['language'] = $options['language'];
    $language_code = $options['language']->langcode;
  }
  else {
    $language_code = NULL;
  }
  $sanitize = !empty($options['sanitize']);

  $replacements = array();

  if ($type == 'site') {
    foreach ($tokens as $name => $original) {
      switch ($name) {
        case 'name':
          $site_name = variable_get('site_name', 'Drupal');
          $replacements[$original] = $sanitize ? check_plain($site_name) : $site_name;
          break;

        case 'slogan':
          $slogan = variable_get('site_slogan', '');
          $replacements[$original] = $sanitize ? check_plain($slogan) : $slogan;
          break;

        case 'mail':
          $replacements[$original] = variable_get('site_mail', '');
          break;

        case 'url':
          $replacements[$original] = url('<front>', $url_options);
          break;

        case 'url-brief':
          $replacements[$original] = preg_replace(array('!^https?://!', '!/$!'), '', url('<front>', $url_options));
          break;

        case 'login-url':
          $replacements[$original] = url('user', $url_options);
          break;
      }
    }
  }

  elseif ($type == 'date') {
    if (empty($data['date'])) {
      $date = REQUEST_TIME;
    }
    else {
      $date = $data['date'];
    }

    foreach ($tokens as $name => $original) {
      switch ($name) {
        case 'short':
          $replacements[$original] = format_date($date, 'short', '', NULL, $language_code);
          break;

        case 'medium':
          $replacements[$original] = format_date($date, 'medium', '', NULL, $language_code);
          break;

        case 'long':
          $replacements[$original] = format_date($date, 'long', '', NULL, $language_code);
          break;

        case 'since':
          $replacements[$original] = format_interval((REQUEST_TIME - $date), 2, $language_code);
          break;

        case 'raw':
          $replacements[$original] = $sanitize ? check_plain($date) : $date;
          break;
      }
    }

    if ($created_tokens = token_find_with_prefix($tokens, 'custom')) {
      foreach ($created_tokens as $name => $original) {
        $replacements[$original] = format_date($date, 'custom', $name, NULL, $language_code);
      }
    }
  }

  elseif ($type == 'file' && !empty($data['file'])) {
    $file = $data['file'];

    foreach ($tokens as $name => $original) {
      switch ($name) {
        // Basic keys and values.
        case 'fid':
          $replacements[$original] = $file->fid;
          break;

        // Essential file data
        case 'name':
          $replacements[$original] = $sanitize ? check_plain($file->filename) : $file->filename;
          break;

        case 'path':
          $replacements[$original] = $sanitize ? check_plain($file->uri) : $file->uri;
          break;

        case 'mime':
          $replacements[$original] = $sanitize ? check_plain($file->filemime) : $file->filemime;
          break;

        case 'size':
          $replacements[$original] = format_size($file->filesize);
          break;

        case 'url':
          $replacements[$original] = $sanitize ? check_plain(file_create_url($file->uri)) : file_create_url($file->uri);
          break;

        // These tokens are default variations on the chained tokens handled below.
        case 'timestamp':
          $replacements[$original] = format_date($file->timestamp, 'medium', '', NULL, $language_code);
          break;

        case 'owner':
          $account = user_load($file->uid);
          $name = user_format_name($account);
          $replacements[$original] = $sanitize ? check_plain($name) : $name;
          break;
      }
    }

    if ($date_tokens = token_find_with_prefix($tokens, 'timestamp')) {
      $replacements += token_generate('date', $date_tokens, array('date' => $file->timestamp), $options);
    }

    if (($owner_tokens = token_find_with_prefix($tokens, 'owner')) && $account = user_load($file->uid)) {
      $replacements += token_generate('user', $owner_tokens, array('user' => $account), $options);
    }
  }

  return $replacements;
}
