<?php

/**
 * @file
 * User page callbacks for the aggregator module.
 */

/**
 * Page callback: Displays the most recent items gathered from any feed.
 *
 * @see aggregator_menu()
 */
function aggregator_page_last() {
  drupal_add_feed('aggregator/rss', variable_get('site_name', 'Drupal') . ' ' . t('aggregator'));

  $items = aggregator_load_feed_items('sum');

  return _aggregator_page_list($items, arg(1));
}

/**
 * Page callback: Displays all the items captured from a particular feed.
 *
 * @param $feed
 *   The feed for which to display all items.
 *
 * @see aggregator_menu()
 */
function aggregator_page_source($feed) {
  drupal_set_title($feed->title);
  $feed_source = theme('aggregator_feed_source', array('feed' => $feed));

  // It is safe to include the fid in the query because it's loaded from the
  // database by aggregator_feed_load().
  $items = aggregator_load_feed_items('source', $feed);

  return _aggregator_page_list($items, arg(3), $feed_source);
}

/**
 * Form constructor to show all items captured from a feed.
 *
 * @param $feed
 *   The feed for which to list all the aggregated items.
 *
 * @see aggregator_menu()
 * @see aggregator_page_source()
 * @ingroup forms
 */
function aggregator_page_source_form($form, $form_state, $feed) {
  return aggregator_page_source($feed);
}

/**
 * Form constructor to list items aggregated in a category.
 *
 * @param $category
 *   The category for which to list all the aggregated items.
 *
 * @see aggregator_menu()
 * @ingroup forms
 */
function aggregator_page_category($category) {
  drupal_add_feed('aggregator/rss/' . $category['cid'], variable_get('site_name', 'Drupal') . ' ' . t('aggregator - @title', array('@title' => $category['title'])));

  // It is safe to include the cid in the query because it's loaded from the
  // database by aggregator_category_load().
  $items = aggregator_load_feed_items('category', $category);

  return _aggregator_page_list($items, arg(3));
}

/**
 * Form constructor to list items aggregated in a category.
 *
 * @param $category
 *   The category for which to list all the aggregated items.
 *
 * @see aggregator_menu()
 * @see aggregator_page_category()
 * @ingroup forms
 */
function aggregator_page_category_form($form, $form_state, $category) {
  return aggregator_page_category($category);
}

/**
 * Loads and optionally filters feed items.
 *
 * @param $type
 *   The type of filter for the items. Possible values are:
 *   - sum: No filtering.
 *   - source: Filter the feed items, limiting the result to items from a
 *     single source.
 *   - category: Filter the feed items by category.
 * @param $data
 *   Feed or category data used for filtering. The type and value of $data
 *   depends on $type:
 *   - source: $data is an object with $data->fid identifying the feed used to
 *     as filter.
 *   - category: $data is an array with $data['cid'] being the category id to
 *     filter on.
 *   The $data parameter is not used when $type is 'sum'.
 *
 * @return
 *   An array of the feed items.
 */
function aggregator_load_feed_items($type, $data = NULL) {
  $items = array();
  switch ($type) {
    case 'sum':
      $query = db_select('aggregator_item', 'i');
      $query->join('aggregator_feed', 'f', 'i.fid = f.fid');
      $query->fields('i');
      $query->addField('f', 'title', 'ftitle');
      $query->addField('f', 'link', 'flink');
      break;
    case 'source':
      $query = db_select('aggregator_item', 'i');
      $query
        ->fields('i')
        ->condition('i.fid', $data->fid);
      break;
    case 'category':
      $query = db_select('aggregator_category_item', 'c');
      $query->leftJoin('aggregator_item', 'i', 'c.iid = i.iid');
      $query->leftJoin('aggregator_feed', 'f', 'i.fid = f.fid');
      $query
        ->fields('i')
        ->condition('cid', $data['cid']);
      $query->addField('f', 'title', 'ftitle');
      $query->addField('f', 'link', 'flink');
      break;
  }

  $result = $query
    ->extend('Drupal\Core\Database\Query\PagerSelectExtender')
    ->limit(20)
    ->orderBy('i.timestamp', 'DESC')
    ->orderBy('i.iid', 'DESC')
    ->execute();

  foreach ($result as $item) {
    $item->categories = db_query('SELECT c.title, c.cid FROM {aggregator_category_item} ci LEFT JOIN {aggregator_category} c ON ci.cid = c.cid WHERE ci.iid = :iid ORDER BY c.title', array(':iid' => $item->iid))->fetchAll();
    $items[] = $item;
  }

  return $items;
}

/**
 * Prints an aggregator page listing a number of feed items.
 *
 * Various menu callbacks use this function to print their feeds.
 *
 * @param $items
 *   The items to be listed.
 * @param $op
 *   Which form should be added to the items. Only 'categorize' is now
 *   recognized.
 * @param $feed_source
 *   The feed source URL.
 *
 * @return
 *   The rendered list of items for a feed.
 */
function _aggregator_page_list($items, $op, $feed_source = '') {
  if (user_access('administer news feeds') && ($op == 'categorize')) {
    // Get form data.
    $output = aggregator_categorize_items($items, $feed_source);
  }
  else {
    // Assemble themed output.
    $output = $feed_source;
    foreach ($items as $item) {
      $output .= theme('aggregator_item', array('item' => $item));
    }
    $output = theme('aggregator_wrapper', array('content' => $output));
  }

  return $output;
}

/**
 * Form constructor to build the page list form.
 *
 * @param $items
 *   An array of the feed items.
 * @param $feed_source
 *   The feed source URL.
 *
 * @ingroup forms
 * @see aggregator_categorize_items_submit()
 */
function aggregator_categorize_items($items, $feed_source = '') {
  $form['#submit'][] = 'aggregator_categorize_items_submit';
  $form['#theme'] = 'aggregator_categorize_items';
  $form['feed_source'] = array(
    '#value' => $feed_source,
  );
  $categories = array();
  $done = FALSE;
  $form['items'] = array();
  $form['categories'] = array(
    '#tree' => TRUE,
  );
  foreach ($items as $item) {
    $form['items'][$item->iid] = array('#markup' => theme('aggregator_item', array('item' => $item)));
    $form['categories'][$item->iid] = array();
    $categories_result = db_query('SELECT c.cid, c.title, ci.iid FROM {aggregator_category} c LEFT JOIN {aggregator_category_item} ci ON c.cid = ci.cid AND ci.iid = :iid', array(':iid' => $item->iid));
    $selected = array();
    foreach ($categories_result as $category) {
      if (!$done) {
        $categories[$category->cid] = check_plain($category->title);
      }
      if ($category->iid) {
        $selected[] = $category->cid;
      }
    }
    $done = TRUE;
    $form['categories'][$item->iid] = array(
      '#type' => variable_get('aggregator_category_selector', 'checkboxes'),
      '#default_value' => $selected,
      '#options' => $categories,
      '#size' => 10,
      '#multiple' => TRUE
    );
  }
  $form['actions'] = array('#type' => 'actions');
  $form['actions']['submit'] = array('#type' => 'submit', '#value' => t('Save categories'));

  return $form;
}

/**
 * Form submission handler for aggregator_categorize_items().
 */
function aggregator_categorize_items_submit($form, &$form_state) {
  if (!empty($form_state['values']['categories'])) {
    foreach ($form_state['values']['categories'] as $iid => $selection) {
      db_delete('aggregator_category_item')
        ->condition('iid', $iid)
        ->execute();
      $insert = db_insert('aggregator_category_item')->fields(array('iid', 'cid'));
      $has_values = FALSE;
      foreach ($selection as $cid) {
        if ($cid && $iid) {
          $has_values = TRUE;
          $insert->values(array(
            'iid' => $iid,
            'cid' => $cid,
          ));
        }
      }
      if ($has_values) {
        $insert->execute();
      }
    }
  }
  drupal_set_message(t('The categories have been saved.'));
}

/**
 * Returns HTML for the aggregator page list form for assigning categories.
 *
 * @param $variables
 *   An associative array containing:
 *   - form: A render element representing the form.
 *
 * @ingroup themeable
 */
function theme_aggregator_categorize_items($variables) {
  $form = $variables['form'];

  $output = drupal_render($form['feed_source']);
  $rows = array();
  if (!empty($form['items'])) {
    foreach (element_children($form['items']) as $key) {
      $rows[] = array(
        drupal_render($form['items'][$key]),
        array('data' => drupal_render($form['categories'][$key]), 'class' => array('categorize-item')),
      );
    }
  }
  $output .= theme('table', array('header' => array('', t('Categorize')), 'rows' => $rows));
  $output .= drupal_render($form['submit']);
  $output .= drupal_render_children($form);

  return theme('aggregator_wrapper', array('content' => $output));
}

/**
 * Default theme implementation to present a linked feed item for summaries.
 *
 * @param $variables
 *   An associative array containing:
 *   - item_link: Link to item.
 *   - item_age: Age of the item.
 *
 * @see template_preprocess()
 * @see template_preprocess_aggregator_summary_item()
 */
function theme_aggregator_summary_item($variables) {
  return $variables['item_url'] . ' ' . $variables['item_age'];
}

/**
 * Processes variables for aggregator-wrapper.tpl.php.
 *
 * @see aggregator-wrapper.tpl.php
 */
function template_preprocess_aggregator_wrapper(&$variables) {
  $variables['pager'] = theme('pager');
}

/**
 * Processes variables for aggregator-item.tpl.php.
 *
 * @see aggregator-item.tpl.php
 */
function template_preprocess_aggregator_item(&$variables) {
  $item = $variables['item'];

  $variables['feed_url'] = check_url($item->link);
  $variables['feed_title'] = check_plain($item->title);
  $variables['content'] = aggregator_filter_xss($item->description);

  $variables['source_url'] = '';
  $variables['source_title'] = '';
  if (isset($item->ftitle) && isset($item->fid)) {
    $variables['source_url'] = url("aggregator/sources/$item->fid");
    $variables['source_title'] = check_plain($item->ftitle);
  }
  if (date('Ymd', $item->timestamp) == date('Ymd')) {
    $variables['source_date'] = t('%ago ago', array('%ago' => format_interval(REQUEST_TIME - $item->timestamp)));
  }
  else {
    $variables['source_date'] = format_date($item->timestamp, 'custom', variable_get('date_format_medium', 'D, m/d/Y - H:i'));
  }

  $variables['categories'] = array();
  foreach ($item->categories as $category) {
    $variables['categories'][$category->cid] = l($category->title, 'aggregator/categories/' . $category->cid);
  }
}

/**
 * Page callback: Displays all the feeds used by the aggregator.
 *
 * @see aggregator_menu()
 */
function aggregator_page_sources() {
  $result = db_query('SELECT f.fid, f.title, f.description, f.image, MAX(i.timestamp) AS last FROM {aggregator_feed} f LEFT JOIN {aggregator_item} i ON f.fid = i.fid GROUP BY f.fid, f.title, f.description, f.image ORDER BY last DESC, f.title');

  $output = '';
  foreach ($result as $feed) {
    // Most recent items:
    $summary_items = array();
    if (variable_get('aggregator_summary_items', 3)) {
      $items = db_query_range('SELECT i.title, i.timestamp, i.link FROM {aggregator_item} i WHERE i.fid = :fid ORDER BY i.timestamp DESC', 0, variable_get('aggregator_summary_items', 3), array(':fid' => $feed->fid));
      foreach ($items as $item) {
        $summary_items[] = theme('aggregator_summary_item', array('item' => $item));
      }
    }
    $feed->url = url('aggregator/sources/' . $feed->fid);
    $output .= theme('aggregator_summary_items', array('summary_items' => $summary_items, 'source' => $feed));
  }
  $output .= theme('feed_icon', array('url' => 'aggregator/opml', 'title' => t('OPML feed')));

  return theme('aggregator_wrapper', array('content' => $output));
}

/**
 * Page callback: Displays all the categories used by the aggregator.
 *
 * @see aggregator_menu()
 */
function aggregator_page_categories() {
  $result = db_query('SELECT c.cid, c.title, c.description FROM {aggregator_category} c LEFT JOIN {aggregator_category_item} ci ON c.cid = ci.cid LEFT JOIN {aggregator_item} i ON ci.iid = i.iid GROUP BY c.cid, c.title, c.description');

  $output = '';
  foreach ($result as $category) {
    if (variable_get('aggregator_summary_items', 3)) {
      $summary_items = array();
      $items = db_query_range('SELECT i.title, i.timestamp, i.link, f.title as feed_title, f.link as feed_link FROM {aggregator_category_item} ci LEFT JOIN {aggregator_item} i ON i.iid = ci.iid LEFT JOIN {aggregator_feed} f ON i.fid = f.fid WHERE ci.cid = :cid ORDER BY i.timestamp DESC', 0, variable_get('aggregator_summary_items', 3), array(':cid' => $category->cid));
      foreach ($items as $item) {
        $summary_items[] = theme('aggregator_summary_item', array('item' => $item));
      }
    }
    $category->url = url('aggregator/categories/' . $category->cid);
    $output .= theme('aggregator_summary_items', array('summary_items' => $summary_items, 'source' => $category));
  }

  return theme('aggregator_wrapper', array('content' => $output));
}

/**
 * Page callback: Generates an RSS 0.92 feed of aggregator items or categories.
 *
 * @see aggregator_menu()
 */
function aggregator_page_rss() {
  $result = NULL;
  // arg(2) is the passed cid, only select for that category.
  if (arg(2)) {
    $category = db_query('SELECT cid, title FROM {aggregator_category} WHERE cid = :cid', array(':cid' => arg(2)))->fetchObject();
    $result = db_query_range('SELECT i.*, f.title AS ftitle, f.link AS flink FROM {aggregator_category_item} c LEFT JOIN {aggregator_item} i ON c.iid = i.iid LEFT JOIN {aggregator_feed} f ON i.fid = f.fid WHERE cid = :cid ORDER BY timestamp DESC, i.iid DESC', 0, config('system.rss-publishing')->get('feed_default_items'), array(':cid' => $category->cid));
  }
  // Or, get the default aggregator items.
  else {
    $category = NULL;
    $result = db_query_range('SELECT i.*, f.title AS ftitle, f.link AS flink FROM {aggregator_item} i INNER JOIN {aggregator_feed} f ON i.fid = f.fid ORDER BY i.timestamp DESC, i.iid DESC', 0, config('system.rss-publishing')->get('feed_default_items'));
  }

  $feeds = $result->fetchAll();
  return theme('aggregator_page_rss', array('feeds' => $feeds, 'category' => $category));
}

/**
 * Prints the RSS page for a feed.
 *
 * @param $variables
 *   An associative array containing:
 *   - feeds: An array of the feeds to theme.
 *   - category: A common category, if any, for all the feeds.
 *
 * @return void
 *
 * @ingroup themeable
 */
function theme_aggregator_page_rss($variables) {
  $feeds = $variables['feeds'];
  $category = $variables['category'];

  drupal_add_http_header('Content-Type', 'application/rss+xml; charset=utf-8');

  $items = '';
  $feed_length = config('system.rss-publishing')->get('feed_item_length');
  foreach ($feeds as $feed) {
    switch ($feed_length) {
      case 'teaser':
        $summary = text_summary($feed->description, NULL, variable_get('aggregator_teaser_length', 600));
        if ($summary != $feed->description) {
          $summary .= '<p><a href="' . check_url($feed->link) . '">' . t('read more') . "</a></p>\n";
        }
        $feed->description = $summary;
        break;
      case 'title':
        $feed->description = '';
        break;
    }
    $items .= format_rss_item($feed->ftitle . ': ' . $feed->title, $feed->link, $feed->description, array('pubDate' => date('r', $feed->timestamp)));
  }

  $site_name = variable_get('site_name', 'Drupal');
  $url = url((isset($category) ? 'aggregator/categories/' . $category->cid : 'aggregator'), array('absolute' => TRUE));
  $description = isset($category) ? t('@site_name - aggregated feeds in category @title', array('@site_name' => $site_name, '@title' => $category->title)) : t('@site_name - aggregated feeds', array('@site_name' => $site_name));

  $output  = "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n";
  $output .= "<rss version=\"2.0\">\n";
  $output .= format_rss_channel(t('@site_name aggregator', array('@site_name' => $site_name)), $url, $description, $items);
  $output .= "</rss>\n";

  print $output;
}

/**
 * Page callback: Generates an OPML representation of all feeds.
 *
 * @param $cid
 *   If set, feeds are exported only from a category with this ID. Otherwise,
 *   all feeds are exported.
 *
 * @see aggregator_menu()
 */
function aggregator_page_opml($cid = NULL) {
  if ($cid) {
    $result = db_query('SELECT f.title, f.url FROM {aggregator_feed} f LEFT JOIN {aggregator_category_feed} c on f.fid = c.fid WHERE c.cid = :cid ORDER BY title', array(':cid' => $cid));
  }
  else {
    $result = db_query('SELECT * FROM {aggregator_feed} ORDER BY title');
  }

  $feeds = $result->fetchAll();
  return theme('aggregator_page_opml', array('feeds' => $feeds));
}

/**
 * Prints the OPML page for a feed.
 *
 * @param $variables
 *   An associative array containing:
 *   - feeds: An array of the feeds to theme.
 *
 * @return void
 *
 * @ingroup themeable
 */
function theme_aggregator_page_opml($variables) {
  $feeds = $variables['feeds'];

  drupal_add_http_header('Content-Type', 'text/xml; charset=utf-8');

  $output  = "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n";
  $output .= "<opml version=\"1.1\">\n";
  $output .= "<head>\n";
  $output .= '<title>' . check_plain(variable_get('site_name', 'Drupal')) . "</title>\n";
  $output .= '<dateModified>' . gmdate(DATE_RFC2822, REQUEST_TIME) . "</dateModified>\n";
  $output .= "</head>\n";
  $output .= "<body>\n";
  foreach ($feeds as $feed) {
    $output .= '<outline text="' . check_plain($feed->title) . '" xmlUrl="' . check_url($feed->url) . "\" />\n";
  }
  $output .= "</body>\n";
  $output .= "</opml>\n";

  print $output;
}

/**
 * Processes variables for aggregator-summary-items.tpl.php.
 *
 * @see aggregator-summary-items.tpl.php
 */
function template_preprocess_aggregator_summary_items(&$variables) {
  $variables['title'] = check_plain($variables['source']->title);
  $variables['summary_list'] = theme('item_list', array('items' => $variables['summary_items']));
  $variables['source_url'] = $variables['source']->url;
}

/**
 * Processes variables for aggregator-summary-item.tpl.php.
 *
 * @see aggregator-summary-item.tpl.php
 */
function template_preprocess_aggregator_summary_item(&$variables) {
  $item = $variables['item'];

  $variables['item_url'] = l(check_plain($item->title), check_url(url($item->link, array('absolute' => TRUE))), array(
    'attributes' => array(
      'class' => array('feed-item-url',),
    ),
  ));
  $variables['item_age'] = theme('datetime', array(
    'attributes' => array(
      'datetime' => format_date($item->timestamp, 'html_datetime', '', 'UTC'),
      'class' => array('feed-item-age',),
    ),
    'text' => t('%age old', array('%age' => format_interval(REQUEST_TIME - $item->timestamp))),
    'html' => TRUE,
  ));
}

/**
 * Processes variables for aggregator-feed-source.tpl.php.
 *
 * @see aggregator-feed-source.tpl.php
 */
function template_preprocess_aggregator_feed_source(&$variables) {
  $feed = $variables['feed'];

  $variables['source_icon'] = theme('feed_icon', array('url' => $feed->url, 'title' => t('!title feed', array('!title' => $feed->title))));

  if (!empty($feed->image) && !empty($feed->title) && !empty($feed->link)) {
    $variables['source_image'] = l(theme('image', array('path' => $feed->image, 'alt' => $feed->title)), $feed->link, array('html' => TRUE, 'attributes' => array('class' => 'feed-image')));
  }
  else {
    $variables['source_image'] = '';
  }

  $variables['source_description'] = aggregator_filter_xss($feed->description);
  $variables['source_url'] = check_url(url($feed->link, array('absolute' => TRUE)));

  if ($feed->checked) {
    $variables['last_checked'] = t('@time ago', array('@time' => format_interval(REQUEST_TIME - $feed->checked)));
  }
  else {
    $variables['last_checked'] = t('never');
  }

  if (user_access('administer news feeds')) {
    $variables['last_checked'] = l($variables['last_checked'], 'admin/config/services/aggregator');
  }
}
