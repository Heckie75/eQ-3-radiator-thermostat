<?php
// TODO handle errors: send error response
// TODO logging
$script = "/var/www/openhab2/eqiva/eq3.exp ";
$mac_regex = "/^([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})$/";

$modes = array("ON" => "auto", "OFF" => "manual");

function readShortStatus($mac) {
  global $script;
  $cmd = $script . $mac . " devjson";

  return shell_exec($cmd);
}

function setTemperature($mac, $temp) {
  global $script;
  $cmd = $script . $mac . " temp " . $temp;
  return shell_exec($cmd);
}

function setComforteco($mac, $comfort, $eco) {
  global $script;
  $cmd = $script . $mac . " comforteco " . $comfort . " " . $eco;
  return shell_exec($cmd);
}

function setMode($mac, $mode) {
  global $script;
  $cmd = $script . $mac . " " . $mode;
  return shell_exec($cmd);
}

function setBoost($mac, $off) {
  global $script;
  $cmd = $script . $mac . " boost";
  if ($off) {
    $cmd .= " off";
  }
  return shell_exec($cmd);
}

if(strcasecmp($_SERVER['REQUEST_METHOD'], 'POST') == 0){
  $request_parameters = $_POST;
} else if(strcasecmp($_SERVER['REQUEST_METHOD'], 'GET') == 0) {
  $request_parameters = $_GET;
}

if (isset($request_parameters['mac'])) {
  $mac = str_replace("-", ":", $request_parameters['mac']);
}
if (isset($request_parameters['temperature'])) {
  $temperature = str_replace(",", ".", $request_parameters['temperature']);
}
if (isset($request_parameters['mode'])) {
  $mode = $request_parameters['mode'];
}
if (isset($request_parameters['boost'])) {
  $boost = $request_parameters['boost'];
}

$response = "error";
if (isset($mac)) {
  if (preg_match($mac_regex, $mac)) {
    // temperature
    if (isset($temperature)) {
      $temperature = floatval($temperature);
      $result = setTemperature($mac, $temperature);
    }
    // mode
    if (isset($mode) &&  in_array($mode,  array_keys($modes))) {
      $result = setMode($mac, $modes[$mode]);
    }
    // boost
    if (isset($boost)) {
      if (strcasecmp($boost, "ON") == 0) {
        $result = setBoost($mac, 0);
      }
      if (strcasecmp($boost, "OFF") == 0) {
        $result = setBoost($mac, 1);
      }
    }
    $response = readShortStatus($mac);
  }
}

header('Content-Type: application/json');
echo $response;

?>
