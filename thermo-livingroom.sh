#!/bin/bash
DIR="$(dirname "$0")"
$DIR/eq3.exp 00:1A:22:0A:91:CF "$@"

case "$@" in
  "boost" )
    notify-send -i /home/heckie/opt/thermostat-boost.png Livingroom "$1"
  ;;
  "comfort" )
    notify-send -i /home/heckie/opt/thermostat-day.png Livingroom "$1"
  ;;
  "eco" )
    notify-send -i /home/heckie/opt/thermostat-night.png Livingroom "$@"
  ;;
  "temp 18.0" )
    notify-send -i /home/heckie/opt/thermostat-cold.png Livingroom "18.0°C"
  ;;
  "temp 21.0" )
    notify-send -i /home/heckie/opt/thermostat-warm.png Livingroom "21.0°C"
  ;;
  "temp 23.0" )
    notify-send -i /home/heckie/opt/thermostat-hot.png Livingroom "23.0°C"
  ;;
  * )
    notify-send -i /home/heckie/opt/thermostat-warm.png Livingroom "$1"
  ;;
esac
