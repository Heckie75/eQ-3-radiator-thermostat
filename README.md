
# eQ-3-radiator-thermostat for OpenHab2

An extension of @Heckie75 repository. It allows to control the eqiva eQ-3 bluetooth radiator thermostat directly from OpenHab2 whith HTTP or EXEC binding. The OpenHab-Bluethooth binding is not necessary.
You can also control the thermostat over HTTP with GET or POST requests:

Request (thermostat status):
```
http://192.168.1.100/eq3/eq3.php?mac=00-11-42-DD-EE-69
```

Response (thermostat status):
```
{
  "mac" : "00-11-42-DD-EE-69",
  "temperature" : 22.0,
  "valve" : 80,
  "mode" : {
    "auto" : "ON",
    "manual" : "OFF",
    "low battery" : "OFF",
    "open window" : "OFF",
    "vacation" : "OFF",
    "locked" : "OFF",
    "boost" : "OFF",
    "unknown" : "OFF",
    "dst" : "ON",
    "on" : "OFF",
    "off" : "OFF"
  },
  "vacation" : null
}
```

The thermostat's parameters can be setted with HTTP-requests, for example:

Request (set temperature to 24 °C and thermostat mode to manual):
```
http://192.168.1.100/eq3/eq3.php?mac=00-11-42-DD-EE-69&temperature=24.0&mode=manual
```

Response (set temperature to 24 °C and thermostat mode to manual):
```
{
  "mac" : "00-11-42-DD-EE-69",
  "temperature" : 24.0,
  "valve" : 90,
  "mode" : {
    "auto" : "OFF",
    "manual" : "ON",
    "low battery" : "OFF",
    "open window" : "OFF",
    "vacation" : "OFF",
    "locked" : "OFF",
    "boost" : "OFF",
    "unknown" : "OFF",
    "dst" : "ON",
    "on" : "OFF",
    "off" : "OFF"
  },
  "vacation" : null
}
``` 

## Setup

### Expect and bluetooth paaring

0. Check pre-conditions

Install `expect`:
```
$ sudo apt install expect
```

Check if `gatttool` is available:
```
$ gatttool
Usage:
  gatttool [OPTION...]
...

```

1. Discover the MAC address of your thermostat

```
$ sudo hcitool lescan
LE Scan ...
38:01:95:84:A8:B1 (unknown)
00:1A:22:0A:91:CF (unknown)
00:1A:22:0A:91:CF CC-RT-BLE
```

It is the one related to the device CC-RT-BLE.

2. Pair bluetooth

Actually I am not 100% sure, but it seems that the thermostat must be explicitly paired (maybe only newer versions). Therefore you have to press the button on the termostat
and start the pairing procedure. It depends on your linux distribution how devices must be paired there. The pin seems not to be required for pairing.

After that check if you can run expect script:

```
$ ./eq3.exp 00:1A:22:07:FD:03 sync

Temperature:			10.5°C
Valve:				0%
Mode:				manual 
Vacation mode:			off
```

## WebServer and PHP
Install any WebServer (NginX, Apache, Lighttpd) with php support.
Then you can call the php script at url like this:

```
http://localhost/eq3/eq3.php?mac=00-1A-22-77-42-69
```

## OpenHab2
1. Install HTTP-Binding and JSONPath Transformation

2. Approach to read and write thermostat values with Rules

2.1 Item

/etc/openhab2/items/thermostat.items

```
Number thermostat_wz "Thermostat Wohnzimmer [%.1f °C]" <temperature>
Switch thermostat_wz_mode "Thermostat Auto [%s]"
Switch thermostat_wz_boost "Thermostat Boost [%s]"
Number thermostat_wz_valve "Thermostat Ventil [%.1f]"
```

2.2 Rules

/etc/openhab2/rules/thermostat.rules

```
import java.util.concurrent.locks.ReentrantLock 

val ReentrantLock lock = new ReentrantLock() 
var eqUrl = "http://localhost/eq3/eq3.php?mac=" 
var mac_wz =     "00-1A-22-33-44-69" 
// here comes other thermostats

val readThermostat = [ eqUrl, mac |

  val ReentrantLock lock2 = new ReentrantLock()

  lock2.lock()
  try{
    var url = eqUrl + mac
    logDebug("thermostat", "read thermostat " + url)
    var response = sendHttpGetRequest(url)

    if (response !== null) {
      // TODO handle { "error" : "Connection failed." }
      var temperature = transform("JSONPATH", "$.temperature", response)
      var valve = transform("JSONPATH", "$.valve", response)
      var mode_auto = transform("JSONPATH", "$.mode.auto", response)
      var mode_boost = transform("JSONPATH", "$.mode.boost", response)

      var temperatureNumber = Float::parseFloat(String::format("%s", temperature))
      var valveNumber = Float::parseFloat(String::format("%s", valve))

      // TODO use map or group
      if(mac == "00-1A-22-0D-E1-69") {
        logDebug("thermostat", "Update " + "Wohnzimmer")
        thermostat_wz.postUpdate(temperatureNumber)
        thermostat_wz_valve.postUpdate(valveNumber)
        thermostat_wz_mode.postUpdate(mode_auto)
        thermostat_wz_boost.postUpdate(mode_boost)
      }
      // here comes other thermostats

      logDebug("thermostat", "update successful " + mac)
    } else {
      logDebug("thermostat", "update error: response is null")
    }
  } finally {
    lock2.unlock()
  }

]

val updateTemperature = [ eqUrl, mac, GenericItem item |

  var url = eqUrl + mac
  url +=  "&temperature=" + item.state
  logDebug("thermostat", "call URL " + url)
  sendHttpGetRequest(url)
  
]

val updateBoost = [ eqUrl, mac, GenericItem item |

  var url = eqUrl + mac
  url +=  "&boost=" + item.state
  logDebug("thermostat", "call URL " + url)
  sendHttpGetRequest(url)

]


rule  "Thermostat WZ Temperature"
  when
    Item thermostat_wz changed
  then
    updateTemperature.apply(eqUrl, mac_wz, thermostat_wz)
    readThermostat.apply(eqUrl, mac_wz)
  end

rule  "Thermostat WZ Boost"
  when
    Item thermostat_wz_boost changed
  then
    updateBoost.apply(eqUrl, mac_wz, thermostat_wz_boost)
    readThermostat.apply(eqUrl, mac_wz)
  end 

// copy both rules for other thermostats
  

rule "Thermostat Read"
  when
    // execute every two minutes at 0th second
    Time cron "0 0/2 * * * ?"
  then
    lock.lock()
    try {
      readThermostat.apply(eqUrl, mac_wz)
    } finally {
      lock.unlock()
    }
	// here comes other thermostats
  end

```

3. Without Rules

3.1. Item

/etc/openhab2/items/thermostat.items

```
Number thermostat_wz "Thermostat Wohnzimmer [%.1f °C]" <temperature> { http=">[*:GET:http://localhost/eq3/eq3.php?mac=00-11-22-33-42-69&temperature=%2$s{Authorization=Basic SECRET}] <[thermostatWohnzimmer:600000:JSONPATH($.temperature)]" }
Switch thermostat_wz_mode "Thermostat Auto [%s]" { http=">[*:GET:http://localhost/eq3/eq3.php?mac=00-11-22-33-42-69&mode=%2$s{Authorization=Basic SECRET}] <[thermostatWohnzimmer:600000:JSONPATH($.mode.auto)]" }
Switch thermostat_wz_boost "Thermostat Boost [%s]" { http=">[*:GET:http://localhost/eq3/eq3.php?mac=00-11-22-33-42-69&boost=%2$s{Authorization=Basic SECRET}] <[thermostatWohnzimmer:120000:JSONPATH($.mode.boost)]" }
Number thermostat_wz_valve "Thermostat Ventil [%.1f]"  { http="<[thermostatWohnzimmer:600000:JSONPATH($.valve)]"}
```

3.2. HTTP-config 

/etc/openhab2/services/http.cfg

```
thermostatWohnzimmer.url=http://localhost/eq3/eq3.php?mac=00-11-22-33-42-69{Authorization=Basic SECRET}
thermostatWohnzimmer.updateInterval=120000
```


4. Sitemap 

/etc/openhab2/sitemaps/default.sitemap

```
sitemap default label="SmartHome"
{

  Setpoint item=thermostat_wz label="Thermostat [%.1f °C]" minValue=4.5 maxValue=30 step=0.5
  Text item=thermostat_wz_valve
  Switch item=thermostat_wz_mode
  Switch item=thermostat_wz_boost

}
```




Below is the @Heckie75's original README

# eQ-3-radiator-thermostat

Full-featured shell script interface based on expect and gatttool for eqiva eQ-3 radiator thermostat

This script allows to control the bluetooth radiator thermostat with the Raspberry Pi's Raspian and other Linux distributions.


```
$ ./eq3.exp 00:1A:22:07:FD:03 

Full-featured CLI for radiator thermostat eQ-3 CC-RT-BLE

Usage: <mac> <command> <parameters...>

Sync:
 sync                                           - Syncs time and prints target temperature and mode

Mode:
 auto                                           - Sets auto mode and deactivates vacation mode if active.
 manual                                         - Sets manual mode and deactivates vacation mode if active.

Temperature:
 comfort                                        - Sets target temperature to programmed comfort temperature
 eco                                            - Sets target temperature to programmed eco temperature
 boost                                          - Activates boost mode for 5 minutes
 boost off                                      - Deactivates boost mode
 temp <temp>                                    - Sets target temperature to given value
                                                  temp: 5.0 to 29.5 in intervals of 0.5°C, e.g. 19.5
 on                                             - Sets thermostat to permanent on (30°C)
 off                                            - Sets thermostat to permanent off (4.5°C)

Timers:
 timers                                         - Reads all timers
 timer <day>                                    - Reads timer for given day
 timer <day> <base> <hh:mm> <temp> <hh:mm> ...  - Sets timer for given day and up to 7 events with temperature and time
                                                  day:  mon, tue, wed, thu, fri, sat, sun, work, weekend, everyday, today, tomorrow
                                                  base temperature before first and after last schedule: 5.0 to 29.5 in intervals of 0.5°C, e.g. 19.5 
                                                  target temperature 5.0 to 29.5 in intervals of 0.5°C, e.g. 19.5 
                                                  hh:mm: time where minutes must be in intervals of 10 minutes, e.g. 23:40
 vacation <yy-mm-dd> <hh:mm> <temp>             - Activates vacation mode until date and time and temperature in °C
                                                  yy-mm-dd: until date, e.g. 17-03-31
                                                  hh:mm: until time where minutes must be 00 or 30, e.g. 23:30
                                                  temp: 5.0 to 29.5 in intervals of 0.5°C, e.g. 19.5 
 vacation <hh> <temp>                           - Activates vacation mode for given period in hours and temperature in °C
                                                  hh: Period in hours
                                                  temp: 5.0 to 29.5 in intervals of 0.5°C, e.g. 19.5

Configuration:
 comforteco <temp_comfort> <temp_eco>           - Sets comfort and eco temperature in °C
                                                  temp: 5.0 to 29.5 in intervals of 0.5°C, e.g. 19.5
 window <temp> <hh:mm>                          - Sets temperature in °C and period for open window mode
                                                  temp: 5.0 to 29.5 in intervals of 0.5°C, e.g. 19.5 
                                                  hh:mm: time where minutes in intervals of 5 minutes, e.g. 02:05
 offset <offset>                                - Sets the offset temperature in °C
                                                  offset: temperature between -3.5 and 3.5 in intervals of 0.5°C, e.g. 1.5

Others:
 lock                                           - Locks thermostat (LOC). No PIN required!
 unlock                                         - Unlocks thermostat. No PIN required!
 serial                                         - Prints serial of thermostat (see little badge where batteries are) and PIN that is required to pair device in official app
 status                                         - Syncs time, Prints target temperature, mode and timers
                                                  (in debug mode also last command even of official app, set log_user to 1 in code!)
 json                                           - Simular to status but in json format
 clear                                          - Clear buffer of last request (will be printed in debug mode, set log_user to 1 in code!)
 reset                                          - Factory reset
```

## Initial setup

0. Check pre-conditions

Install `expect`:
```
$ sudo apt install expect
```

Check if `gatttool` is available:
```
$ gatttool
Usage:
  gatttool [OPTION...]
...

```

1. Discover the MAC address of your thermostat

```
$ sudo hcitool lescan
LE Scan ...
38:01:95:84:A8:B1 (unknown)
00:1A:22:0A:91:CF (unknown)
00:1A:22:0A:91:CF CC-RT-BLE
```

It is the one related to the device CC-RT-BLE.

2. Pair bluetooth

Actually I am not 100% sure, but it seems that the thermostat must be explicitly paired (maybe only newer versions). Therefore you have to press the button on the termostat
and start the pairing procedure. It depends on your linux distribution how devices must be paired there. The pin seems not to be required for pairing.


## Examples

### Sync time from PC to thermostat

```
$ ./eq3.exp 00:1A:22:07:FD:03 sync

Temperature:			10.5°C
Valve:				0%
Mode:				manual 
Vacation mode:			off
```

### Dump status

```
$ ./eq3.exp 00:1A:22:07:FD:03 status


Temperature:			10.5°C
Valve:				0%
Mode:				manual 
Vacation mode:			off


Timer for Sun:
	Sun, 00:00 - 07:30:	17.0°C
	Sun, 07:30 - 10:00:	21.0°C
	Sun, 10:00 - 14:30:	19.0°C
	Sun, 14:30 - 21:20:	21.0°C
	Sun, 21:20 - 24:00:	17.0°C

Timer for Mon:
	Mon, 00:00 - 17:00:	17.0°C
	Mon, 17:00 - 21:00:	21.0°C
	Mon, 21:00 - 24:00:	17.0°C

Timer for Tue:
	Tue, 00:00 - 17:00:	17.0°C
	Tue, 17:00 - 21:00:	21.0°C
	Tue, 21:00 - 24:00:	17.0°C

Timer for Wed:
	Wed, 00:00 - 17:00:	17.0°C
	Wed, 17:00 - 21:00:	21.0°C
	Wed, 21:00 - 24:00:	17.0°C

Timer for Thu:
	Thu, 00:00 - 17:00:	17.0°C
	Thu, 17:00 - 21:00:	21.0°C
	Thu, 21:00 - 24:00:	17.0°C

Timer for Fri:
	Fri, 00:00 - 17:00:	17.0°C
	Fri, 17:00 - 22:00:	21.0°C
	Fri, 22:00 - 24:00:	19.0°C

Timer for Sat:
	Sat, 00:00 - 07:30:	17.0°C
	Sat, 07:30 - 10:00:	21.0°C
	Sat, 10:00 - 14:30:	19.0°C
	Sat, 14:30 - 22:00:	21.0°C
	Sat, 22:00 - 24:00:	19.0°C

Device mac:			00:1A:22:07:FD:03
Device name:			CC-RT-BLE
Device vendor:			eq-3

```


### Program window open mode

```
$ ./eq3.exp 00:1A:22:07:FD:03 window 16.5 2:00

Window open temperature:    16.5°C
Window open time:           2:00

Temperature:                22.0°C
Valve:                      0%
Mode:                       auto 
Vacation mode:              off
```

### Set to auto and manual mode

```
$ ./eq3.exp 00:1A:22:07:FD:03 auto

Temperature:            21.0°C
Valve:                  0%
Mode:                   auto
Vacation mode:          off

$ ./eq3.exp 00:1A:22:07:FD:03 manual

Temperature:            21.0°C
Valve:                  0%
Mode:                   manual
Vacation mode:          off
```

### Program comfort and eco temperature and switch to programmed temperatures

```
$ ./eq3.exp 00:1A:22:07:FD:03 comforteco 22.0 17.0

Comfort temperature:    22.0°C
Eco temperature:        17.0°C

Temperature:            22.5°C
Valve:                  0%
Mode:                   manual
Vacation mode:          off

$ ./eq3.exp 00:1A:22:07:FD:03 comfort

Temperature:            22.0°C
Valve:                  0%
Mode:                   manual
Vacation mode:          off

$ ./eq3.exp 00:1A:22:07:FD:03 eco

Temperature:            17.0°C
Valve:                  15%
Mode:                   manual
Vacation mode:          off
```

### Set current temperature

```
$ ./eq3.exp 00:1A:22:07:FD:03 temp 22.5

Temperature:            22.5°C
Valve:                  15%
Mode:                   manual
Vacation mode:          off
```

### Start and stop boost mode

```
$ ./eq3.exp 00:1A:22:07:FD:03 boost

Temperature:            22.5°C
Valve:                  80%
Mode:                   manual boost
Vacation mode:          off

$ ./eq3.exp 00:1A:22:07:FD:03 boost off

Temperature:            22.5°C
Valve:                  15%
Mode:                   manual
Vacation mode:          off
```

### Start vacation mode 
```
$ ./eq3.exp 00:1A:22:07:FD:03 vacation 17-03-31 21:30 14.5

Vacation mode:          17-03-31 21:30 14.5°C

Temperature:            14.5°C
Valve:                  0%
Mode:                   auto vacation 
Vacation mode:          on
Vacation until:         2017-03-31 21:30

$ ./eq3.exp 00:1A:22:07:FD:03 vacation 120 19

Vacation mode:          17-02-14 21:30 19°C

Temperature:            19.0°C
Valve:                  0%
Mode:                   auto vacation 
Vacation mode:          on
Vacation until:         2017-02-14 21:30
```

### Set timer for Wednesday with 7 events and read it
```
$ ./eq3.exp 00:1A:22:07:FD:03 timer wed 19 03:00 23 06:00 19 09:00 23 12:00 19 15:00 23 18:00 24 24:00

Timer set: wed 19 03:00 23 06:00 19 09:00 23 12:00 19 15:00 23 18:00 24 24:00

$ ./eq3.exp 00:1A:22:07:FD:03 timer wed

Timer for Wed (0x0411 2004):    21 04 26 12 2e 24 26 36 2e 48 26 5a 2e 6c 30 90 
    Wed, 00:00 - 03:00: 19.0°C
    Wed, 03:00 - 06:00: 23.0°C
    Wed, 06:00 - 09:00: 19.0°C
    Wed, 09:00 - 12:00: 23.0°C
    Wed, 12:00 - 15:00: 19.0°C
    Wed, 15:00 - 18:00: 23.0°C
    Wed, 18:00 - 24:00: 24.0°C
```

### Set offset temperature
```
$ ./eq3.exp 00:1A:22:07:FD:03 offset 1.0

Offset temperature:     1.0°C

Temperature:            22.0°C
Valve:                  0%
Mode:                   auto 
Vacation mode:          off
```

### Lock and unlock radiator thermostat

```
$ ./eq3.exp 00:1A:22:07:FD:03 lock

Temperature:            22.5°C
Valve:                  42%
Mode:                   manual locked
Vacation mode:          off

$ ./eq3.exp 00:1A:22:07:FD:03 unlock

Temperature:            22.5°C
Valve:                  42%
Mode:                   manual
Vacation mode:          off
```
