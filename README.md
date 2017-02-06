# eQ-3-radiator-thermostat

Full-featured shell script interface based on expect and gatttool for eQ-3 radiator thermostat

This script allows to control the bluetooth radiator thermostat with the Raspberry Pi's Raspian and other Linux distributions.   

```
$ ./eq3.exp 00:1A:22:07:FD:03 
Usage: <mac> <command> <parameters...>


Commands:
 status                                         - Print current temperature and mode
 sync                                           - Sync time and prints current mode and mode
 window 16.5 01:25                              - Sets temperature in °C and time for open window mode
 auto                                           - Sets auto mode
 manual                                         - Sets manual mode
 daynight 22.5 17                               - Sets day and night temperature in °C
 day                                            - Sets current temperature to programmed day temperature
 night                                          - Sets current temperature to programmed night temperature
 temp 22.5                                      - Sets current temperature to given value
 boost                                          - Activates boost mode for 5 minutes
 boost off                                      - Deactivates boost mode
 off                                            - Sets temperate to off
 lock                                           - Locks radiator thermostat (LOC). No PIN required!
 unlock                                         - Unlocks radiator thermostat. No PIN required!
 reset                                          - Factory reset
```

## Examples
### Receive status

```
$ ./eq3.exp 00:1A:22:07:FD:03 status

Device mac:         00:1A:22:07:FD:03
Device name (0x0321):       CC-RT-BLE
Device vendor (0x0311):     eq-3

Status (0x0411 03):     02 01 08 00 04 2a 
Temperature:            21.0°C
Valve:              0%
Mode:               auto (08)
Vacation mode:          off

Timer for Sat (0x0411 2000):    21 00 22 2e 2a 86 22 90 00 00 00 00 00 00 00 00 
    Sat, 00:00 - 07:40: 17.0°C
    Sat, 07:40 - 22:20: 21.0°C
    Sat, 22:20 - 24:00: 17.0°C

Timer for Sun (0x0411 2001):    21 01 22 2e 2a 86 22 90 00 00 00 00 00 00 00 00 
    Sun, 00:00 - 07:40: 17.0°C
    Sun, 07:40 - 22:20: 21.0°C
    Sun, 22:20 - 24:00: 17.0°C

Timer for Mon (0x0411 2002):    21 02 22 65 2a 82 22 90 00 00 00 00 00 00 00 00 
    Mon, 00:00 - 16:50: 17.0°C
    Mon, 16:50 - 21:40: 21.0°C
    Mon, 21:40 - 24:00: 17.0°C

Timer for Tue (0x0411 2003):    21 03 22 65 2a 82 22 90 00 00 00 00 00 00 00 00 
    Tue, 00:00 - 16:50: 17.0°C
    Tue, 16:50 - 21:40: 21.0°C
    Tue, 21:40 - 24:00: 17.0°C

Timer for Wed (0x0411 2004):    21 04 22 65 2a 82 22 90 00 00 00 00 00 00 00 00 
    Wed, 00:00 - 16:50: 17.0°C
    Wed, 16:50 - 21:40: 21.0°C
    Wed, 21:40 - 24:00: 17.0°C

Timer for Thu (0x0411 2005):    21 05 22 65 2a 82 22 90 00 00 00 00 00 00 00 00 
    Thu, 00:00 - 16:50: 17.0°C
    Thu, 16:50 - 21:40: 21.0°C
    Thu, 21:40 - 24:00: 17.0°C

Timer for Fri (0x0411 2006):    21 06 22 64 2a 86 22 90 00 00 00 00 00 00 00 00 
    Fri, 00:00 - 16:40: 17.0°C
    Fri, 16:40 - 22:20: 21.0°C
    Fri, 22:20 - 24:00: 17.0°C
```

### Sync time from PC to thermostat

```
$ ./eq3.exp 00:1A:22:07:FD:03 sync

Sync time:          17-02-06 20:56:40

Status (0x0411 03):     02 01 08 00 04 2a 
Temperature:            21.0°C
Valve:              0%
Mode:               auto (08)
Vacation mode:          off
```

### Program window open mode

```
$ ./eq3.exp 00:1A:22:07:FD:03 window 16.5 01:00

Window open temperature:    16.5°C
Window open time:       01:00

Status (0x0411 03):     02 01 08 00 04 2a 
Temperature:            21.0°C
Valve:              0%
Mode:               auto (08)
Vacation mode:          off
```

### Set to auto and manual mode

```
$ ./eq3.exp 00:1A:22:07:FD:03 auto

Status (0x0411 03):     02 01 08 00 04 2a 
Temperature:            21.0°C
Valve:              0%
Mode:               auto (08)
Vacation mode:          off

$ ./eq3.exp 00:1A:22:07:FD:03 manual

Status (0x0411 03):     02 01 09 00 04 2a 
Temperature:            21.0°C
Valve:              0%
Mode:               manual (09)
Vacation mode:          off
```

### Program day and night temperature and switch to programmed temperatures

```
$ ./eq3.exp 00:1A:22:07:FD:03 daynight 22.0 17.0

Day temperature:        22.0°C
Night temperature:      17.0°C

Status (0x0411 03):     02 01 09 00 04 2d 
Temperature:            22.5°C
Valve:              0%
Mode:               manual (09)
Vacation mode:          off

$ ./eq3.exp 00:1A:22:07:FD:03 day

Status (0x0411 03):     02 01 09 00 04 2c 
Temperature:            22.0°C
Valve:              0%
Mode:               manual (09)
Vacation mode:          off

$ ./eq3.exp 00:1A:22:07:FD:03 night

Status (0x0411 03):     02 01 09 0f 04 22 
Temperature:            17.0°C
Valve:              15%
Mode:               manual (09)
Vacation mode:          off
```

### Set current temperature

```
$ ./eq3.exp 00:1A:22:07:FD:03 temp 22.5

Status (0x0411 03):     02 01 09 0f 04 2d 
Temperature:            22.5°C
Valve:              15%
Mode:               manual (09)
Vacation mode:          off
```

### Start and stop boost mode

```
$ ./eq3.exp 00:1A:22:07:FD:03 boost

Status (0x0411 03):     02 01 0d 50 04 2d 
Temperature:            22.5°C
Valve:              80%
Mode:               boost-manual (0d)
Vacation mode:          off

$ ./eq3.exp 00:1A:22:07:FD:03 boost off

Status (0x0411 03):     02 01 09 0f 04 2d 
Temperature:            22.5°C
Valve:              15%
Mode:               manual (09)
Vacation mode:          off
```

### Lock and unlock radiator thermostat

```
$ ./eq3.exp 00:1A:22:07:FD:03 lock

Status (0x0411 03):     02 01 29 2a 04 2d 
Temperature:            22.5°C
Valve:              42%
Mode:               locked (29)
Vacation mode:          off

$ ./eq3.exp 00:1A:22:07:FD:03 unlock

Status (0x0411 03):     02 01 09 2a 04 2d 
Temperature:            22.5°C
Valve:              42%
Mode:               manual (09)
Vacation mode:          off
```