<?php

/**
 * @file
 * Definition of Drupal\Core\ExceptionController.
 */

namespace Drupal\Core;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Exception\FlattenException;

/**
 * This controller handles HTTP errors generated by the routing system.
 */
class ExceptionController {

  /**
   * The kernel that spawned this controller.
   *
   * We will use this to fire subrequests as needed.
   *
   * @var Symfony\Component\HttpKernel\HttpKernelInterface
   */
  protected $kernel;

  /**
   * The content negotiation library.
   *
   * @var Drupal\Core\ContentNegotiation
   */
  protected $negotiation;

  /**
   * Constructor.
   *
   * @param Symfony\Component\HttpKernel\HttpKernelInterface $kernel
   *   The kernel that spawned this controller, so that it can be reused
   *   for subrequests.
   * @param Drupal\Core\ContentNegotiation $negotiation
   *   The content negotiation library to use to determine the correct response
   *   format.
   */
  public function __construct(HttpKernelInterface $kernel, ContentNegotiation $negotiation) {
    $this->kernel = $kernel;
    $this->negotiation = $negotiation;
  }

  /**
   * Handles an exception on a request.
   *
   * @param Symfony\Component\HttpKernel\Exception\FlattenException $exception
   *   The flattened exception.
   * @param Symfony\Component\HttpFoundation\Request $request
   *   The request that generated the exception.
   *
   * @return Symfony\Component\HttpFoundation\Response
   *   A response object to be sent to the server.
   */
  public function execute(FlattenException $exception, Request $request) {
    $method = 'on' . $exception->getStatusCode() . $this->negotiation->getContentType($request);

    if (method_exists($this, $method)) {
      return $this->$method($exception, $request);
    }

    return new Response('A fatal error occurred: ' . $exception->getMessage(), $exception->getStatusCode());
  }

  /**
   * Processes a MethodNotAllowed exception into an HTTP 405 response.
   *
   * @param Symfony\Component\HttpKernel\Exception\FlattenException $exception
   *   The flattened exception.
   * @param Symfony\Component\HttpFoundation\Request $request
   *   The request object that triggered this exception.
   */
  public function on405Html(FlattenException $exception, Request $request) {
    $event->setResponse(new Response('Method Not Allowed', 405));
  }

  /**
   * Processes an AccessDenied exception into an HTTP 403 response.
   *
   * @param Symfony\Component\HttpKernel\Exception\FlattenException $exception
   *   The flattened exception.
   * @param Symfony\Component\HttpFoundation\Request $request
   *   The request object that triggered this exception.
   */
  public function on403Html(FlattenException $exception, Request $request) {
    $system_path = $request->attributes->get('system_path');
    watchdog('access denied', $system_path, NULL, WATCHDOG_WARNING);

    $path = drupal_get_normal_path(variable_get('site_403', ''));
    if ($path && $path != $system_path) {
      // Keep old path for reference, and to allow forms to redirect to it.
      if (!isset($_GET['destination'])) {
        $_GET['destination'] = $system_path;
      }

      $subrequest = Request::create('/' . $path, 'get', array('destination' => $system_path), $request->cookies->all(), array(), $request->server->all());

      // The active trail is being statically cached from the parent request to
      // the subrequest, like any other static.  Unfortunately that means the
      // data in it is incorrect and does not get regenerated correctly for
      // the subrequest.  In this instance, that even causes a fatal error in
      // some circumstances because menu_get_active_trail() ends up having
      // a missing localized_options value.  To work around that, reset the
      // menu static variables and let them be regenerated as needed.
      // @todo It is likely that there are other such statics that need to be
      //   reset that are not triggering test failures right now.  If found,
      //   add them here.
      // @todo Refactor the breadcrumb system so that it does not rely on static
      //   variables in the first place, which will eliminate the need for this
      //   hack.
      drupal_static_reset('menu_set_active_trail');
      menu_reset_static_cache();

      $response = $this->kernel->handle($subrequest, DrupalKernel::SUB_REQUEST);
      $response->setStatusCode(403, 'Access denied');
    }
    else {
      $response = new Response('Access Denied', 403);

      // @todo Replace this block with something cleaner.
      $return = t('You are not authorized to access this page.');
      drupal_set_title(t('Access denied'));
      drupal_set_page_content($return);
      $page = element_info('page');
      $content = drupal_render_page($page);

      $response->setContent($content);
    }

    return $response;
  }

  /**
   * Processes a NotFound exception into an HTTP 404 response.
   *
   * @param Symfony\Component\HttpKernel\Exception\FlattenException $exception
   *   The flattened exception.
   * @param Sonfony\Component\HttpFoundation\Request $request
   *   The request object that triggered this exception.
   */
  public function on404Html(FlattenException $exception, Request $request) {
    watchdog('page not found', check_plain($request->attributes->get('system_path')), NULL, WATCHDOG_WARNING);

    // Check for and return a fast 404 page if configured.
    // @todo Inline this rather than using a function.
    drupal_fast_404();

    $system_path = $request->attributes->get('system_path');

    // Keep old path for reference, and to allow forms to redirect to it.
    if (!isset($_GET['destination'])) {
      $_GET['destination'] = $system_path;
    }

    $path = drupal_get_normal_path(variable_get('site_404', ''));
    if ($path && $path != $system_path) {
      // @todo Um, how do I specify an override URL again? Totally not clear. Do
      //   that and sub-call the kernel rather than using meah().
      // @todo The create() method expects a slash-prefixed path, but we store a
      //   normal system path in the site_404 variable.
      $subrequest = Request::create('/' . $path, 'get', array(), $request->cookies->all(), array(), $request->server->all());

      // The active trail is being statically cached from the parent request to
      // the subrequest, like any other static.  Unfortunately that means the
      // data in it is incorrect and does not get regenerated correctly for
      // the subrequest.  In this instance, that even causes a fatal error in
      // some circumstances because menu_get_active_trail() ends up having
      // a missing localized_options value.  To work around that, reset the
      // menu static variables and let them be regenerated as needed.
      // @todo It is likely that there are other such statics that need to be
      //   reset that are not triggering test failures right now.  If found,
      //   add them here.
      // @todo Refactor the breadcrumb system so that it does not rely on static
      //   variables in the first place, which will eliminate the need for this
      //   hack.
      drupal_static_reset('menu_set_active_trail');
      menu_reset_static_cache();

      $response = $this->kernel->handle($subrequest, HttpKernelInterface::SUB_REQUEST);
      $response->setStatusCode(404, 'Not Found');
    }
    else {
      $response = new Response('Not Found', 404);

      // @todo Replace this block with something cleaner.
      $return = t('The requested page "@path" could not be found.', array('@path' => $request->getPathInfo()));
      drupal_set_title(t('Page not found'));
      drupal_set_page_content($return);
      $page = element_info('page');
      $content = drupal_render_page($page);

      $response->setContent($content);
    }

    return $response;
  }

  /**
   * Processes a generic exception into an HTTP 500 response.
   *
   * @param Symfony\Component\HttpKernel\Exception\FlattenException $exception
   *   Metadata about the exception that was thrown.
   * @param Symfony\Component\HttpFoundation\Request $request
   *   The request object that triggered this exception.
   */
  public function on500Html(FlattenException $exception, Request $request) {
    $error = $this->decodeException($exception);

    // Because the kernel doesn't run until full bootstrap, we know that
    // most subsystems are already initialized.

    $headers = array();

    // When running inside the testing framework, we relay the errors
    // to the tested site by the way of HTTP headers.
    $test_info = &$GLOBALS['drupal_test_info'];
    if (!empty($test_info['in_child_site']) && !headers_sent() && (!defined('SIMPLETEST_COLLECT_ERRORS') || SIMPLETEST_COLLECT_ERRORS)) {
      // $number does not use drupal_static as it should not be reset
      // as it uniquely identifies each PHP error.
      static $number = 0;
      $assertion = array(
        $error['!message'],
        $error['%type'],
        array(
          'function' => $error['%function'],
          'file' => $error['%file'],
          'line' => $error['%line'],
        ),
      );
      $headers['X-Drupal-Assertion-' . $number] = rawurlencode(serialize($assertion));
      $number++;
    }

    watchdog('php', '%type: !message in %function (line %line of %file).', $error, $error['severity_level']);

    // Display the message if the current error reporting level allows this type
    // of message to be displayed, and unconditionnaly in update.php.
    if (error_displayable($error)) {
      $class = 'error';

      // If error type is 'User notice' then treat it as debug information
      // instead of an error message, see dd().
      if ($error['%type'] == 'User notice') {
        $error['%type'] = 'Debug';
        $class = 'status';
      }

      drupal_set_message(t('%type: !message in %function (line %line of %file).', $error), $class);
    }

    drupal_set_title(t('Error'));
    // We fallback to a maintenance page at this point, because the page
    // generation itself can generate errors.
    $output = theme('maintenance_page', array('content' => t('The website encountered an unexpected error. Please try again later.')));

    $response = new Response($output, 500);
    $response->setStatusCode(500, '500 Service unavailable (with message)');

    return $response;
  }

  /**
   * Processes an AccessDenied exception that occured on a JSON request.
   *
   * @param Symfony\Component\HttpKernel\Exception\FlattenException $exception
   *   The flattened exception.
   * @param Symfony\Component\HttpFoundation\Request $request
   *   The request object that triggered this exception.
   */
  public function on403Json(FlattenException $exception, Request $request) {
    $response = new JsonResponse();
    $response->setStatusCode(403, 'Access Denied');
    return $response;
  }

  /**
   * Processes a NotFound exception that occured on a JSON request.
   *
   * @param Symfony\Component\HttpKernel\Exception\FlattenException $exception
   *   The flattened exception.
   * @param Symfony\Component\HttpFoundation\Request $request
   *   The request object that triggered this exception.
   */
  public function on404Json(FlattenException $exception, Request $request) {
    $response = new JsonResponse();
    $response->setStatusCode(404, 'Not Found');
    return $response;
  }

  /**
   * Processes a MethodNotAllowed exception that occured on a JSON request.
   *
   * @param Symfony\Component\HttpKernel\Exception\FlattenException $exception
   *   The flattened exception.
   * @param Symfony\Component\HttpFoundation\Request $request
   *   The request object that triggered this exception.
   */
  public function on405Json(FlattenException $exception, Request $request) {
    $response = new JsonResponse();
    $response->setStatusCode(405, 'Method Not Allowed');
    return $response;
  }


  /**
   * This method is a temporary port of _drupal_decode_exception().
   *
   * @todo This should get refactored. FlattenException could use some
   *   improvement as well.
   *
   * @return array
   */
  protected function decodeException(FlattenException $exception) {
    $message = $exception->getMessage();

    $backtrace = $exception->getTrace();

    // This value is missing from the stack for some reason in the
    // FlattenException version of the backtrace.
    $backtrace[0]['line'] = $exception->getLine();

    // For database errors, we try to return the initial caller,
    // skipping internal functions of the database layer.
    if (strpos($exception->getClass(), 'DatabaseExceptionWrapper') !== FALSE) {
      // A DatabaseExceptionWrapper exception is actually just a courier for
      // the original PDOException.  It's the stack trace from that exception
      // that we care about.
      $backtrace = $exception->getPrevious()->getTrace();
      $backtrace[0]['line'] = $exception->getLine();

      // The first element in the stack is the call, the second element gives us the caller.
      // We skip calls that occurred in one of the classes of the database layer
      // or in one of its global functions.
      $db_functions = array('db_query',  'db_query_range');
      while (!empty($backtrace[1]) && ($caller = $backtrace[1]) &&
          ((strpos($caller['namespace'], 'Drupal\Core\Database') !== FALSE || strpos($caller['class'], 'PDO') !== FALSE)) ||
          in_array($caller['function'], $db_functions)) {
        // We remove that call.
        array_shift($backtrace);
      }
    }
    $caller = $this->getLastCaller($backtrace);

    return array(
      '%type' => $exception->getClass(),
      // The standard PHP exception handler considers that the exception message
      // is plain-text. We mimick this behavior here.
      '!message' => check_plain($message),
      '%function' => $caller['function'],
      '%file' => $caller['file'],
      '%line' => $caller['line'],
      'severity_level' => WATCHDOG_ERROR,
    );
  }

  /**
   * Gets the last caller from a backtrace.
   *
   * The last caller is not necessarily the first item in the backtrace. Rather,
   * it is the first item in the backtrace that is a PHP userspace function,
   * and not one of our debug functions.
   *
   * @param $backtrace
   *   A standard PHP backtrace.
   *
   * @return
   *   An associative array with keys 'file', 'line' and 'function'.
   */
  protected function getLastCaller($backtrace) {
    // Ignore black listed error handling functions.
    $blacklist = array('debug', '_drupal_error_handler', '_drupal_exception_handler');

    // Errors that occur inside PHP internal functions do not generate
    // information about file and line.
    while (($backtrace && !isset($backtrace[0]['line'])) ||
          (isset($backtrace[1]['function']) && in_array($backtrace[1]['function'], $blacklist))) {
      array_shift($backtrace);
    }

    // The first trace is the call itself.
    // It gives us the line and the file of the last call.
    $call = $backtrace[0];

    // The second call give us the function where the call originated.
    if (isset($backtrace[1])) {
      if (isset($backtrace[1]['class'])) {
        $call['function'] = $backtrace[1]['class'] . $backtrace[1]['type'] . $backtrace[1]['function'] . '()';
      }
      else {
        $call['function'] = $backtrace[1]['function'] . '()';
      }
    }
    else {
      $call['function'] = 'main()';
    }
    return $call;
  }
}
