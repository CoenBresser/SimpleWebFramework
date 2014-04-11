<?php
if (session_id() == '') session_start();
checkAndFixResponseCodeExistence();

// Check if we have a random number set by captcha, if not, everything else is useless
if (!isset($_SESSION['security_code'])) {
  // send an error
  http_response_code(400);
  echo('{"errors": ["service": "No valid session!"]}');
  die();
}

$data = file_get_contents("php://input");
if (!$data || $data === "") {
  http_response_code(400);
  echo('{"response": "error", "errors": ["service": "No data!"]}');
  die();
}

$objData = objectToArray(json_decode($data));

// Check the existence of the correct contents
$respData = array();
checkFieldExistenceAndNotEmpty($objData, "name", $respData);
checkFieldExistenceAndNotEmpty($objData, "email", $respData);
checkFieldExistenceAndNotEmpty($objData, "message", $respData);
checkFieldExistenceAndNotEmpty($objData, "captcha", $respData);

if (count($respData) > 0) {
  // Bad request
  http_response_code(400);
  echo('{"response": "error", "errors": ' . json_encode($respData) . '}');
  die();
}
unset($respData);

// Check the captcha value
if ($objData["captcha"] != $_SESSION['security_code']) {
  // Bad request
  http_response_code(400);
  echo('{"response": "error", "errors": [{"field": "captcha", "message": "Mismatch!"}]}');
  die();
}

// Everything okay now, send the email
$headers = 'From: ' . $objData['email'] . "\r\n" . 'X-Mailer: PHP/' . phpversion();
if( mail('REPLACE_ME', 
          "Feedback via Joke's Schilderijen.nl van " . $objData['name'],
          $objData['message'], $headers) ) {
  echo ('{"response": "ok"}');
} else {
  echo ('{"response": "failed"}');
}

die();

/**
 * Check's the existence of a datafield and that the contents is not empty
 */
function checkFieldExistenceAndNotEmpty($obj, $fieldName, &$respArray) {
  if (!isset($obj[$fieldName])) {
    $resp["field"] = $fieldName;
    $resp["message"] = "Missing!";
    $respArray[] = $resp;
  } else if (strlen($obj[$fieldName]) == 0) {
    $resp["field"] = $fieldName;
    $resp["message"] = "Empty!";
    $respArray[] = $resp;
  }
}

/*
 * Helper methods
 */
function checkAndFixResponseCodeExistence() {
  if (!function_exists('http_response_code')) {
    function http_response_code($code = NULL) {
        if ($code !== NULL) {
            switch ($code) {
                case 100: $text = 'Continue'; break;
                case 101: $text = 'Switching Protocols'; break;
                case 200: $text = 'OK'; break;
                case 201: $text = 'Created'; break;
                case 202: $text = 'Accepted'; break;
                case 203: $text = 'Non-Authoritative Information'; break;
                case 204: $text = 'No Content'; break;
                case 205: $text = 'Reset Content'; break;
                case 206: $text = 'Partial Content'; break;
                case 300: $text = 'Multiple Choices'; break;
                case 301: $text = 'Moved Permanently'; break;
                case 302: $text = 'Moved Temporarily'; break;
                case 303: $text = 'See Other'; break;
                case 304: $text = 'Not Modified'; break;
                case 305: $text = 'Use Proxy'; break;
                case 400: $text = 'Bad Request'; break;
                case 401: $text = 'Unauthorized'; break;
                case 402: $text = 'Payment Required'; break;
                case 403: $text = 'Forbidden'; break;
                case 404: $text = 'Not Found'; break;
                case 405: $text = 'Method Not Allowed'; break;
                case 406: $text = 'Not Acceptable'; break;
                case 407: $text = 'Proxy Authentication Required'; break;
                case 408: $text = 'Request Time-out'; break;
                case 409: $text = 'Conflict'; break;
                case 410: $text = 'Gone'; break;
                case 411: $text = 'Length Required'; break;
                case 412: $text = 'Precondition Failed'; break;
                case 413: $text = 'Request Entity Too Large'; break;
                case 414: $text = 'Request-URI Too Large'; break;
                case 415: $text = 'Unsupported Media Type'; break;
                case 500: $text = 'Internal Server Error'; break;
                case 501: $text = 'Not Implemented'; break;
                case 502: $text = 'Bad Gateway'; break;
                case 503: $text = 'Service Unavailable'; break;
                case 504: $text = 'Gateway Time-out'; break;
                case 505: $text = 'HTTP Version not supported'; break;
                default:
                    exit('Unknown http status code "' . htmlentities($code) . '"');
                break;
            }

            $protocol = (isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0');
            header($protocol . ' ' . $code . ' ' . $text);
            $GLOBALS['http_response_code'] = $code;
        } else {
            $code = (isset($GLOBALS['http_response_code']) ? $GLOBALS['http_response_code'] : 200);
        }
        return $code;
    }
  }
}

function objectToArray($d) {
  if (is_object($d)) {
    // Gets the properties of the given object
    // with get_object_vars function
    $d = get_object_vars($d);
  }

  if (is_array($d)) {
    /*
    * Return array converted to object
    * Using __FUNCTION__ (Magic constant)
    * for recursive call
    */
    return array_map(__FUNCTION__, $d);
  }
  else {
    // Return array
    return $d;
  }
}


?>