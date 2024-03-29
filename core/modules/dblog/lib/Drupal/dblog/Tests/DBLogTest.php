<?php

/**
 * @file
 * Definition of Drupal\dblog\Tests\DBLogTest.
 */

namespace Drupal\dblog\Tests;

use Drupal\simpletest\WebTestBase;
use SimpleXMLElement;

class DBLogTest extends WebTestBase {
  protected $profile = 'standard';

  protected $big_user;
  protected $any_user;

  public static function getInfo() {
    return array(
      'name' => 'DBLog functionality',
      'description' => 'Generate events and verify dblog entries; verify user access to log reports based on persmissions.',
      'group' => 'DBLog',
    );
  }

  /**
   * Enable modules and create users with specific permissions.
   */
  function setUp() {
    parent::setUp('dblog', 'poll');
    // Create users.
    $this->big_user = $this->drupalCreateUser(array('administer site configuration', 'access administration pages', 'access site reports', 'administer users'));
    $this->any_user = $this->drupalCreateUser(array());
  }

  /**
   * Login users, create dblog events, and test dblog functionality through the admin and user interfaces.
   */
  function testDBLog() {
    // Login the admin user.
    $this->drupalLogin($this->big_user);

    $row_limit = 100;
    $this->verifyRowLimit($row_limit);
    $this->verifyCron($row_limit);
    $this->verifyEvents();
    $this->verifyReports();

    // Login the regular user.
    $this->drupalLogin($this->any_user);
    $this->verifyReports(403);
  }

  /**
   * Verify setting of the dblog row limit.
   *
   * @param integer $count Log row limit.
   */
  private function verifyRowLimit($row_limit) {
    // Change the dblog row limit.
    $edit = array();
    $edit['dblog_row_limit'] = $row_limit;
    $this->drupalPost('admin/config/development/logging', $edit, t('Save configuration'));
    $this->assertResponse(200);

    // Check row limit variable.
    $current_limit = variable_get('dblog_row_limit', 1000);
    $this->assertTrue($current_limit == $row_limit, t('[Cache] Row limit variable of @count equals row limit of @limit', array('@count' => $current_limit, '@limit' => $row_limit)));
    // Verify dblog row limit equals specified row limit.
    $current_limit = unserialize(db_query("SELECT value FROM {variable} WHERE name = :dblog_limit", array(':dblog_limit' => 'dblog_row_limit'))->fetchField());
    $this->assertTrue($current_limit == $row_limit, t('[Variable table] Row limit variable of @count equals row limit of @limit', array('@count' => $current_limit, '@limit' => $row_limit)));
  }

  /**
   * Verify cron applies the dblog row limit.
   *
   * @param integer $count Log row limit.
   */
  private function verifyCron($row_limit) {
    // Generate additional log entries.
    $this->generateLogEntries($row_limit + 10);
    // Verify dblog row count exceeds row limit.
    $count = db_query('SELECT COUNT(wid) FROM {watchdog}')->fetchField();
    $this->assertTrue($count > $row_limit, t('Dblog row count of @count exceeds row limit of @limit', array('@count' => $count, '@limit' => $row_limit)));

    // Run cron job.
    $this->cronRun();
    // Verify dblog row count equals row limit plus one because cron adds a record after it runs.
    $count = db_query('SELECT COUNT(wid) FROM {watchdog}')->fetchField();
    $this->assertTrue($count == $row_limit + 1, t('Dblog row count of @count equals row limit of @limit plus one', array('@count' => $count, '@limit' => $row_limit)));
  }

  /**
   * Generate dblog entries.
   *
   * @param integer $count
   *   Number of log entries to generate.
   * @param $type
   *   The type of watchdog entry.
   * @param $severity
   *   The severity of the watchdog entry.
   */
  private function generateLogEntries($count, $type = 'custom', $severity = WATCHDOG_NOTICE) {
    global $base_root;

    // Prepare the fields to be logged
    $log = array(
      'type'        => $type,
      'message'     => 'Log entry added to test the dblog row limit.',
      'variables'   => array(),
      'severity'    => $severity,
      'link'        => NULL,
      'user'        => $this->big_user,
      'uid'         => isset($this->big_user->uid) ? $this->big_user->uid : 0,
      'request_uri' => $base_root . request_uri(),
      'referer'     => $_SERVER['HTTP_REFERER'],
      'ip'          => ip_address(),
      'timestamp'   => REQUEST_TIME,
      );
    $message = 'Log entry added to test the dblog row limit. Entry #';
    for ($i = 0; $i < $count; $i++) {
      $log['message'] = $message . $i;
      dblog_watchdog($log);
    }
  }

  /**
   * Verify the logged in user has the desired access to the various dblog nodes.
   *
   * @param integer $response HTTP response code.
   */
  private function verifyReports($response = 200) {
    $quote = '&#039;';

    // View dblog help node.
    $this->drupalGet('admin/help/dblog');
    $this->assertResponse($response);
    if ($response == 200) {
      $this->assertText(t('Database Logging'), t('DBLog help was displayed'));
    }

    // View dblog report node.
    $this->drupalGet('admin/reports/dblog');
    $this->assertResponse($response);
    if ($response == 200) {
      $this->assertText(t('Recent log messages'), t('DBLog report was displayed'));
    }

    // View dblog page-not-found report node.
    $this->drupalGet('admin/reports/page-not-found');
    $this->assertResponse($response);
    if ($response == 200) {
      $this->assertText(t('Top ' . $quote . 'page not found' . $quote . ' errors'), t('DBLog page-not-found report was displayed'));
    }

    // View dblog access-denied report node.
    $this->drupalGet('admin/reports/access-denied');
    $this->assertResponse($response);
    if ($response == 200) {
      $this->assertText(t('Top ' . $quote . 'access denied' . $quote . ' errors'), t('DBLog access-denied report was displayed'));
    }

    // View dblog event node.
    $this->drupalGet('admin/reports/event/1');
    $this->assertResponse($response);
    if ($response == 200) {
      $this->assertText(t('Details'), t('DBLog event node was displayed'));
    }
  }

  /**
   * Verify events.
   */
  private function verifyEvents() {
    // Invoke events.
    $this->doUser();
    $this->doNode('article');
    $this->doNode('page');
    $this->doNode('poll');

    // When a user account is canceled, any content they created remains but the
    // uid = 0. Records in the watchdog table related to that user have the uid
    // set to zero.
  }

  /**
   * Generate and verify user events.
   *
   */
  private function doUser() {
    // Set user variables.
    $name = $this->randomName();
    $pass = user_password();
    // Add user using form to generate add user event (which is not triggered by drupalCreateUser).
    $edit = array();
    $edit['name'] = $name;
    $edit['mail'] = $name . '@example.com';
    $edit['pass[pass1]'] = $pass;
    $edit['pass[pass2]'] = $pass;
    $edit['status'] = 1;
    $this->drupalPost('admin/people/create', $edit, t('Create new account'));
    $this->assertResponse(200);
    // Retrieve user object.
    $user = user_load_by_name($name);
    $this->assertTrue($user != NULL, t('User @name was loaded', array('@name' => $name)));
    $user->pass_raw = $pass; // Needed by drupalLogin.
    // Login user.
    $this->drupalLogin($user);
    // Logout user.
    $this->drupalLogout();
    // Fetch row ids in watchdog that relate to the user.
    $result = db_query('SELECT wid FROM {watchdog} WHERE uid = :uid', array(':uid' => $user->uid));
    foreach ($result as $row) {
      $ids[] = $row->wid;
    }
    $count_before = (isset($ids)) ? count($ids) : 0;
    $this->assertTrue($count_before > 0, t('DBLog contains @count records for @name', array('@count' => $count_before, '@name' => $user->name)));

    // Login the admin user.
    $this->drupalLogin($this->big_user);
    // Delete user.
    // We need to POST here to invoke batch_process() in the internal browser.
    $this->drupalPost('user/' . $user->uid . '/cancel', array('user_cancel_method' => 'user_cancel_reassign'), t('Cancel account'));

    // View the dblog report.
    $this->drupalGet('admin/reports/dblog');
    $this->assertResponse(200);

    // Verify events were recorded.
    // Add user.
    // Default display includes name and email address; if too long then email is replaced by three periods.
    $this->assertLogMessage(t('New user: %name (%email).', array('%name' => $name, '%email' => $user->mail)), t('DBLog event was recorded: [add user]'));
    // Login user.
    $this->assertLogMessage(t('Session opened for %name.', array('%name' => $name)), t('DBLog event was recorded: [login user]'));
    // Logout user.
    $this->assertLogMessage(t('Session closed for %name.', array('%name' => $name)), t('DBLog event was recorded: [logout user]'));
    // Delete user.
    $message = t('Deleted user: %name %email.', array('%name' => $name, '%email' => '<' . $user->mail . '>'));
    $message_text = truncate_utf8(filter_xss($message, array()), 56, TRUE, TRUE);
    // Verify full message on details page.
    $link = FALSE;
    if ($links = $this->xpath('//a[text()="' . html_entity_decode($message_text) . '"]')) {
      // Found link with the message text.
      $links = array_shift($links);
      foreach ($links->attributes() as $attr => $value) {
        if ($attr == 'href') {
          // Extract link to details page.
          $link = drupal_substr($value, strpos($value, 'admin/reports/event/'));
          $this->drupalGet($link);
          // Check for full message text on the details page.
          $this->assertRaw($message, t('DBLog event details was found: [delete user]'));
          break;
        }
      }
    }
    $this->assertTrue($link, t('DBLog event was recorded: [delete user]'));
    // Visit random URL (to generate page not found event).
    $not_found_url = $this->randomName(60);
    $this->drupalGet($not_found_url);
    $this->assertResponse(404);
    // View dblog page-not-found report page.
    $this->drupalGet('admin/reports/page-not-found');
    $this->assertResponse(200);
    // Check that full-length url displayed.
    $this->assertText($not_found_url, t('DBLog event was recorded: [page not found]'));
  }

  /**
   * Generate and verify node events.
   *
   * @param string $type Content type.
   */
  private function doNode($type) {
    // Create user.
    $perm = array('create ' . $type . ' content', 'edit own ' . $type . ' content', 'delete own ' . $type . ' content');
    $user = $this->drupalCreateUser($perm);
    // Login user.
    $this->drupalLogin($user);

    // Create node using form to generate add content event (which is not triggered by drupalCreateNode).
    $edit = $this->getContent($type);
    $langcode = LANGUAGE_NOT_SPECIFIED;
    $title = $edit["title"];
    $this->drupalPost('node/add/' . $type, $edit, t('Save'));
    $this->assertResponse(200);
    // Retrieve node object.
    $node = $this->drupalGetNodeByTitle($title);
    $this->assertTrue($node != NULL, t('Node @title was loaded', array('@title' => $title)));
    // Edit node.
    $edit = $this->getContentUpdate($type);
    $this->drupalPost('node/' . $node->nid . '/edit', $edit, t('Save'));
    $this->assertResponse(200);
    // Delete node.
    $this->drupalPost('node/' . $node->nid . '/delete', array(), t('Delete'));
    $this->assertResponse(200);
    // View node (to generate page not found event).
    $this->drupalGet('node/' . $node->nid);
    $this->assertResponse(404);
    // View the dblog report (to generate access denied event).
    $this->drupalGet('admin/reports/dblog');
    $this->assertResponse(403);

    // Login the admin user.
    $this->drupalLogin($this->big_user);
    // View the dblog report.
    $this->drupalGet('admin/reports/dblog');
    $this->assertResponse(200);

    // Verify events were recorded.
    // Content added.
    $this->assertLogMessage(t('@type: added %title.', array('@type' => $type, '%title' => $title)), t('DBLog event was recorded: [content added]'));
    // Content updated.
    $this->assertLogMessage(t('@type: updated %title.', array('@type' => $type, '%title' => $title)), t('DBLog event was recorded: [content updated]'));
    // Content deleted.
    $this->assertLogMessage(t('@type: deleted %title.', array('@type' => $type, '%title' => $title)), t('DBLog event was recorded: [content deleted]'));

    // View dblog access-denied report node.
    $this->drupalGet('admin/reports/access-denied');
    $this->assertResponse(200);
    // Access denied.
    $this->assertText(t('admin/reports/dblog'), t('DBLog event was recorded: [access denied]'));

    // View dblog page-not-found report node.
    $this->drupalGet('admin/reports/page-not-found');
    $this->assertResponse(200);
    // Page not found.
    $this->assertText(t('node/@nid', array('@nid' => $node->nid)), t('DBLog event was recorded: [page not found]'));
  }

  /**
   * Create content based on content type.
   *
   * @param string $type Content type.
   * @return array Content.
   */
  private function getContent($type) {
    $langcode = LANGUAGE_NOT_SPECIFIED;
    switch ($type) {
      case 'poll':
        $content = array(
          "title" => $this->randomName(8),
          'choice[new:0][chtext]' => $this->randomName(32),
          'choice[new:1][chtext]' => $this->randomName(32),
        );
      break;

      default:
        $content = array(
          "title" => $this->randomName(8),
          "body[$langcode][0][value]" => $this->randomName(32),
        );
      break;
    }
    return $content;
  }

  /**
   * Create content update based on content type.
   *
   * @param string $type Content type.
   * @return array Content.
   */
  private function getContentUpdate($type) {
    switch ($type) {
      case 'poll':
        $content = array(
          'choice[chid:1][chtext]' => $this->randomName(32),
          'choice[chid:2][chtext]' => $this->randomName(32),
        );
      break;

      default:
        $langcode = LANGUAGE_NOT_SPECIFIED;
        $content = array(
          "body[$langcode][0][value]" => $this->randomName(32),
        );
      break;
    }
    return $content;
  }

  /**
   * Login an admin user, create dblog event, and test clearing dblog functionality through the admin interface.
   */
  protected function testDBLogAddAndClear() {
    global $base_root;
    // Get a count of how many watchdog entries there are.
    $count = db_query('SELECT COUNT(*) FROM {watchdog}')->fetchField();
    $log = array(
      'type'        => 'custom',
      'message'     => 'Log entry added to test the doClearTest clear down.',
      'variables'   => array(),
      'severity'    => WATCHDOG_NOTICE,
      'link'        => NULL,
      'user'        => $this->big_user,
      'uid'         => isset($this->big_user->uid) ? $this->big_user->uid : 0,
      'request_uri' => $base_root . request_uri(),
      'referer'     => $_SERVER['HTTP_REFERER'],
      'ip'          => ip_address(),
      'timestamp'   => REQUEST_TIME,
    );
    // Add a watchdog entry.
    dblog_watchdog($log);
    // Make sure the table count has actually incremented.
    $this->assertEqual($count + 1, db_query('SELECT COUNT(*) FROM {watchdog}')->fetchField(), t('dblog_watchdog() added an entry to the dblog :count', array(':count' => $count)));
    // Login the admin user.
    $this->drupalLogin($this->big_user);
    // Now post to clear the db table.
    $this->drupalPost('admin/reports/dblog', array(), t('Clear log messages'));
    // Count rows in watchdog that previously related to the deleted user.
    $count = db_query('SELECT COUNT(*) FROM {watchdog}')->fetchField();
    $this->assertEqual($count, 0, t('DBLog contains :count records after a clear.', array(':count' => $count)));
  }

  /**
   * Test the dblog filter on admin/reports/dblog.
   */
  protected function testFilter() {
    $this->drupalLogin($this->big_user);

    // Clear log to ensure that only generated entries are found.
    db_delete('watchdog')->execute();

    // Generate watchdog entries.
    $type_names = array();
    $types = array();
    for ($i = 0; $i < 3; $i++) {
      $type_names[] = $type_name = $this->randomName();
      $severity = WATCHDOG_EMERGENCY;
      for ($j = 0; $j < 3; $j++) {
        $types[] = $type = array(
          'count' => mt_rand(1, 5),
          'type' => $type_name,
          'severity' => $severity++,
        );
        $this->generateLogEntries($type['count'], $type['type'], $type['severity']);
      }
    }

    // View the dblog.
    $this->drupalGet('admin/reports/dblog');

    // Confirm all the entries are displayed.
    $count = $this->getTypeCount($types);
    foreach ($types as $key => $type) {
      $this->assertEqual($count[$key], $type['count'], 'Count matched');
    }

    // Filter by each type and confirm that entries with various severities are
    // displayed.
    foreach ($type_names as $type_name) {
      $edit = array(
        'type[]' => array($type_name),
      );
      $this->drupalPost(NULL, $edit, t('Filter'));

      // Count the number of entries of this type.
      $type_count = 0;
      foreach ($types as $type) {
        if ($type['type'] == $type_name) {
          $type_count += $type['count'];
        }
      }

      $count = $this->getTypeCount($types);
      $this->assertEqual(array_sum($count), $type_count, 'Count matched');
    }

    // Set filter to match each of the three type attributes and confirm the
    // number of entries displayed.
    foreach ($types as $key => $type) {
      $edit = array(
        'type[]' => array($type['type']),
        'severity[]' => array($type['severity']),
      );
      $this->drupalPost(NULL, $edit, t('Filter'));

      $count = $this->getTypeCount($types);
      $this->assertEqual(array_sum($count), $type['count'], 'Count matched');
    }

    // Clear all logs and make sure the confirmation message is found.
    $this->drupalPost('admin/reports/dblog', array(), t('Clear log messages'));
    $this->assertText(t('Database log cleared.'), t('Confirmation message found'));
  }

  /**
   * Get the log entry information form the page.
   *
   * @return
   *   List of entries and their information.
   */
  protected function getLogEntries() {
    $entries = array();
    if ($table = $this->xpath('.//table[@id="admin-dblog"]')) {
      $table = array_shift($table);
      foreach ($table->tbody->tr as $row) {
        $entries[] = array(
          'severity' => $this->getSeverityConstant($row['class']),
          'type' => $this->asText($row->td[1]),
          'message' => $this->asText($row->td[3]),
          'user' => $this->asText($row->td[4]),
        );
      }
    }
    return $entries;
  }

  /**
   * Get the count of entries per type.
   *
   * @param $types
   *   The type information to compare against.
   * @return
   *   The count of each type keyed by the key of the $types array.
   */
  protected function getTypeCount(array $types) {
    $entries = $this->getLogEntries();
    $count = array_fill(0, count($types), 0);
    foreach ($entries as $entry) {
      foreach ($types as $key => $type) {
        if ($entry['type'] == $type['type'] && $entry['severity'] == $type['severity']) {
          $count[$key]++;
          break;
        }
      }
    }
    return $count;
  }

  /**
   * Get the watchdog severity constant corresponding to the CSS class.
   *
   * @param $class
   *   CSS class attribute.
   * @return
   *   The watchdog severity constant or NULL if not found.
   */
  protected function getSeverityConstant($class) {
    // Reversed array from dblog_overview().
    $map = array(
      'dblog-debug' => WATCHDOG_DEBUG,
      'dblog-info' => WATCHDOG_INFO,
      'dblog-notice' => WATCHDOG_NOTICE,
      'dblog-warning' => WATCHDOG_WARNING,
      'dblog-error' => WATCHDOG_ERROR,
      'dblog-critical' => WATCHDOG_CRITICAL,
      'dblog-alert' => WATCHDOG_ALERT,
      'dblog-emerg' => WATCHDOG_EMERGENCY,
    );

    // Find the class that contains the severity.
    $classes = explode(' ', $class);
    foreach ($classes as $class) {
      if (isset($map[$class])) {
        return $map[$class];
      }
    }
    return NULL;
  }

  /**
   * Extract the text contained by the element.
   *
   * @param $element
   *   Element to extract text from.
   * @return
   *   Extracted text.
   */
  protected function asText(SimpleXMLElement $element) {
    if (!is_object($element)) {
      return $this->fail('The element is not an element.');
    }
    return trim(html_entity_decode(strip_tags($element->asXML())));
  }

  /**
   * Assert messages appear on the log overview screen.
   *
   * This function should be used only for admin/reports/dblog page, because it
   * check for the message link text truncated to 56 characters. Other dblog
   * pages have no detail links so contains a full message text.
   *
   * @param $log_message
   *   The message to check.
   * @param $message
   *   The message to pass to simpletest.
   */
  protected function assertLogMessage($log_message, $message) {
    $message_text = truncate_utf8(filter_xss($log_message, array()), 56, TRUE, TRUE);
    // After filter_xss() HTML entities should be converted to their characters
    // because assertLink() uses this string in xpath() to query DOM.
    $this->assertLink(html_entity_decode($message_text), 0, $message);
  }
}
