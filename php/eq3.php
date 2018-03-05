<?php

$script = "/home/pi/eQ-3-radiator-thermostat/eq3.exp ";
$mac_regex = "/^([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})$/";
//TODO $error_response = '{"error":""}';

//$modes = array("auto", "manual");
$modes = array("ON" => "auto", "OFF" => "manual");

function readShortStatus($mac) {
  global $script;
  //echo "read short json MAC: " . $mac;
  $cmd = $script . $mac . " devjson";

  return shell_exec($cmd);
}

function setTemperature($mac, $temp) {
  global $script;
  //echo "set temperature to " . $temp . "\n";
  $cmd = $script . $mac . " temp " . $temp;
  // TODO log
  return shell_exec($cmd);
}

function setComforteco($mac, $comfort, $eco) {
  global $script;
  //echo "set temperature to " . $temp . "\n";
  $cmd = $script . $mac . " comforteco " . $comfort . " " . $eco;
  // TODO log
  return shell_exec($cmd);
}

function setMode($mac, $mode) {
  global $script;
  //echo "set mode to " . $mode . "\n";
  $cmd = $script . $mac . " " . $mode;
  //echo $cmd;
  // TODO log
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

$mac = str_replace("-", ":", $request_parameters['mac']);
if (isset($request_parameters['temperature'])) {
  $temperature = str_replace(",", ".", $request_parameters['temperature']);
}
if (isset($request_parameters['mode'])) {
  $mode = $request_parameters['mode'];
}
if (isset($request_parameters['boost'])) {
  $boost = $request_parameters['boost'];
}

if (isset($mac)) {
  if (preg_match($mac_regex, $mac)) {
    // temperature
    if (isset($temperature)) {
      $temperature = floatval($temperature);
      $result = setTemperature($mac, $temperature);
    }
    // mode
    if (in_array($mode,  array_keys($modes))) {
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
