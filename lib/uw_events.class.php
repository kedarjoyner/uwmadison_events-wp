<?php

class UwEvents {
  // The events calendar base API url
  public $api_base = 'http://test.today.wisc.edu';

  // Constructor
  public function __construct() {
    // Constructor code
  }

  /**
   * Init our wordpress stuff
   */
  public function init() {
    // Wordpress init code
  }

  /**
   * Public parse interface
   * Builds the <ul> for all events
   *
   * @param $url {string}
   * @param $opts {array}
   *
   * @return {string}
   *  Returns an html <ul> for the events requested
   *
   */
  public function parse($url, $opts=array()) {
    $opts = $this->sanitizeOpts($opts);
    if ( $data = $this->getRemote($url, $opts) ) {
      $out = '<h2 class="uw_events_title">' . $opts['title'] . "</h2>\n";
      $out .= '<ul class="uw_events">';
      foreach ( $data->data->events as $event ) {
        $out .= $this->eventHtml($event, $opts);
      }
      $out .= '</ul>';
      return $out;
    }
    else {
      return '';
    }
  }

  /**
   * HTML for a specific event
   *
   * @param $event {object}
   *
   * @return {string}
   *  Returns the <li> string for the event object
   *
   */
  public function eventHtml($event, $opts=array()) {
    $opts = $this->sanitizeOpts($opts); // sanitize the options

    $out = '<li class="uw_event">';
    $out .= '<span class="uw_event_title">' . $event->title . '</span>';
    if ( ! empty($event->subtitle) )
      $out .= '<span class="uw_event_subtitle">' . $event->subtitle . '</span>';
    if ( $opts['show_description'] ) {
      if ( ! empty($event->description) )
        $out .= '<span class="uw_event_description">' . $event->description . '</span>';
    }
    $out .= '</li>';
    return $out;
  }

  /**
   * Get remote data
   *
   * @param $url {string}
   * @return {array}
   *  Returns an array of data or FALSE
   */
  public function getRemote($url, $opts=array()) {
    $opts = $this->sanitizeOpts($opts); // Sanitize the options

    if ( $parsed_url = $this->parseUrl($url) ) {
      $get = wp_remote_get($this->buildUrl($parsed_url, $opts));
      if ( isset($get['body']) && !empty($get['body']) ) {
        $data = $this->processRemoteData($get['body']);
        return (object) array(
          'method' => $parsed_url['method'],
          'id' => $parsed_url['id'],
          'data' => $data,
        );
      }
      else {
        return FALSE;
      }
    }
    else {
      return FALSE;
    }
  }

  /**
   * Process the remote JSON encoded string into a date sorted object
   * Deal with the Wordpress timezone insanity here.
   * All date formatting should be done in here so
   * we only have to worry about timezone switching once.
   *
   * @param $data {string}
   * @return {object}
   *  Return a formatted data object for events
   *
   */
  public function processRemoteData($data) {
    // Switch to sanity from Wordpress
    $wp_timezone = date_default_timezone_get();
    date_default_timezone_set('America/Chicago');

    $out = json_decode($data);

    // Restore ourselves to Wordpress insanity
    date_default_timezone_set($wp_timezone);

    // Return
    return $out;
  }

  /**
   * Parse an events calendar url
   * @param $url {string}
   * @return {array}
   *  Return an array with a method and id keys i.e. array('id' => 'arts', 'method' => 'tag')
   */
  public function parseUrl($url) {
    // We're only interested in the path
    $parse = parse_url($url);
    $url = $parse['path'];

    $pattern = '~events/([^/]+)/(.*)$~i';
    if ( preg_match($pattern, $url, $matches) ) {
      return array(
        'method' => $matches[1],
        'id' => $this->stripExtension($matches[2]),
      );
    }
    else {
      return FALSE;
    }
  }

  /**
   * Re-build a URL from a parseUrl() parsed url
   * @param $parsed_url {string}
   * @return {string}
   *  Return a full url string
   */
  public function buildUrl($parsed_url, $opts=array()) {
    $opts = $this->sanitizeOpts($opts); // Sanitize the options

    $query = !isset($opts['limit']) ? '' : '?limit=' . (int) $opts['limit'];
    return $this->api_base . '/events/' . $parsed_url['method'] . '/' . $parsed_url['id'] . '.json' . $query;
  }

  /**
   * Strip content format extensions from a string
   * @param $s {string}
   * @return {string}
   *  Return the string less any content format extensions, or the original string
   *  if none are found.
   */
  private function stripExtension($s) {
    return preg_replace('~\.(json|html|rss|rss2|xml)$~i', '', $s);
  }

  /**
   * Sanitize the opts array and inject defaults
   *
   * @param $opts {array}
   * @return {array}
   *  Return a sanitized and default injected options array
   */
  private function sanitizeOpts($opts) {
    // Validate the limit
    if ( isset($opts['limit']) && (int) $opts['limit'] < 1 ) {
      unset($opts['limit']);
    }
    else {
      $opts['limit'] = (int) $opts['limit'];
    }

    // Defaults
    $defaults = array(
      'limit' => 5,
      'title' => 'Events',
      'show_description' => FALSE,
    );

    // Merge in the defaults
    $opts = array_merge($defaults, $opts);

    return $opts;
  }
}
