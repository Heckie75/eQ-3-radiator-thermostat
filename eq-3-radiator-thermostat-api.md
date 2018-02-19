# eQ-3 radiator thermostat API

## Device Information

**Handle 0x0321 - The product name of the thermostat**
- Encoded in ASCII, you must transform hex to ascii
- Default value: „CC-RT-BLE“
- Get: char-read-hnd 321
- Characteristic value/descriptor: 43 43 2d 52 54 2d 42 4c 45
- Set: n/a

**Handle 0x311 – The vendor of the thermostat**
- Encoded in ASCII, you must transform hex to ascii
- Default value: „eq-3“
- Get: char-read-hnd 311 
- Characteristic value/descriptor: 65 71 2d 33 
- Set: n/a

**Read serial number from device**

The serial number that is printed on the little badge between the two batteries can be quired as follows:

```
char-write-req 0411 00
                    01 6e 00 00 7f 75 81 60 66 61 66 64 61 64 9b
                                 |  |  |  |  |  |  |  |  |  |
Byte:                0  1  2  3  4  5  6  7  8  9 10 11 12 13 14
                                 |  |  |  |  |  |  |  |  |  |
Serial from badge:               O  E  Q  0  6  1  6  4  1  4
  
  ascii = char(hex - 0x30)
```


## Read current status and mode, sync time

It is possible to request some status information of the thermostat, i.e.
- current mode
- target temperature
- current level of valve
- and details of vacation mode

Requesting the current status and mode requires to set the current date and time explicitly.

Therefore the request is as follows:
```
char-write-req 0411 03110208151f05
               |    | + Byte 2 to 7: yy-mm-day hh-MM-ss in hex
               |    +-- Byte 1: request command "03"
               + request via handle 411
```

Data will be returned via notification handle
```
Notification handle = 0x0421 value: 02 01 00 00 04 2a
```

*Note:*
Earlier I have written that it is good enough just to send the request w/o date and time like this:
```
char-write-req 0411 03
```

But these days I got the feedback that this way corrupts the internal clock so that timers and vacation 
mode do not work anymore as long as the clock has been set explicitly again. 
I have also dumped the bluetooth communication of the official app on Android devices. 
The app also sends date and time each time it requests the status. So we should do it as well.

Note: It does not seem to be possible to set the "daylight summertime" (dst) via bluetooth. 

### Modes (Byte 3)
The thermostat has the following modes which can be active at one and the same time:
- "auto"        - Bit 1 is not set (mask 0x00)
- "manual"      - Bit 1 is set (mask 0x01)
- "vacation"    - Bit 2 is set (mask 0x02)
- "boost"       - Bit 3 is set (mask 0x04)
- "dst"         - Bit 4 is set (mask 0x08)
- "open window" - Bit 5 is set (mask 0x10)
- "locked"      - Bit 6 is set (mask 0x20)
- "unknown"     - Bit 7 is set (mask 0x40)
- "low battery" - Bit 8 is set (mask 0x80)

### Valve (Byte 4)
Byte 4 represents the percentage value of the valve. However, I haven't seen a value more than 80% in normal operation even if boost mode is running. If thermostat is switched to "on" it is really 100%.

### Current target temperature (Byte 6)
Byte 6 represents the target temperature. It has to be calculated.

```
temp = dec(value of byte 6) / 2.0
```  

### Vacation data
The bytes 7 to 9 are only returned on case that vacation mode is active. 
- Byte 8: Vacation year (yy) in hex
- Byte 9: Vacation month (mm) in hex
- Byte 7: Vacation day in month (dd) in hex
- Byte 10: Vacation time in 30 minutes steps in hex. Time can be calculated like this:
```
hh = int(dec(value of byte 10) / 2)
mm = dec(value of byte 10) modulo 2 * 30
```

**Example 1 - auto mode**
```
Request:
char-write-req 0411 03
               |    + request status command
               + request via handle 411

Notification handle = 0x0421 value: 02 01 00 00 04 2a
                      |             |  |  |  |  |  + Byte 6: Current temperature in 0.5°C intervals in hex, here 21°C
                      |             |  |  |  |  +--- Byte 5: Always "04" 
                      |             |  |  |  +------ Byte 4: Current level of valve in percent in hex
                      |             |  |  +--------- Byte 3: Current mode, here "auto", see modes
                      |             |  +------------ Byte 2: Always "01"
                      |             +--------------- Byte 1: Always "02"
                      + response via notification handle on 0x421
```

Human readable status:
```
Status (0x0411 03):		02 01 00 00 04 2a 
Temperature:			21.0°C
Valve:				0%
Mode:				auto 
Vacation mode:			off
```

**Example 2 - active vacation mode until 2017-02-29**
```
char-write-req 0411 03
               |    + request status
               + request via handle 411

Notification handle = 0x0421 value: 02 01 02 00 04 26 1c 11 03 02 
                      |             |  |  |  |  |  |  |  |  |  + Byte 10: Vacation month, here 02 = February
                      |             |  |  |  |  |  |  |  |  +--- Byte 9: Vacation time in 30 minutes steps in hex, here 01:30
                      |             |  |  |  |  |  |  |  +------ Byte 8: Vacation year (yy) in hex, here 2017
                      |             |  |  |  |  |  |  +--------- Byte 7: Vacation day in month (dd) in hex, here 29
                      |             |  |  |  |  |  +------------ Byte 6: Current temperature in 0.5°C intervals in hex, here 19°C
                      |             |  |  |  |  +--------------- Byte 5: Always "04" 
                      |             |  |  |  +------------------ Byte 4: Current level of valve in percent in hex
                      |             |  |  +--------------------- Byte 3: Current mode, here "vacation mode", see modes
                      |             |  +------------------------ Byte 2: Always "01"
                      |             +--------------------------- Byte 1: Always "02"
                      + response via notification handle on 0x421
```

## Set mode
You can set the operation mode of the thermostat via bluetooth.

### Set auto mode
In auto mode the thermostat follows the temperatures that are configured in timers for each week day. 

Request:
```
char-write-req 0411 4000
               |    | + Byte 2: Set "00" for mode "auto"
               |    +-- Byte 1: "40" indicates request in order to change mode
               + request via handle 411
```

The thermostat returns status via notification handle simular to what we have seen before:
```
Notification handle = 0x0421 value: 02 01 00 00 04 2a
                                          + mode, now "auto"
```

### Set manual mode
In manual mode the thermostat does not follow the configured timers. The target temperature that is set at this moment won't change in manual mode. 

Request:
```
char-write-req 0411 4040
               |    | + Byte 2: Set "40" for mode "manual"
               |    +-- Byte 1: "40" indicates request in order to change mode
               + request via handle 411
```

The thermostat returns status via notification handle simular to what we have seen before:
```
Notification handle = 0x0421 value: 02 01 01 00 04 2a
                                          + mode, now "manual"
```

### Set vacation mode
In vacation mode the target temperatures taken from auto or manual mode will be overruled for a certain period.

Request:
```
char-write-req 0411 40a31f112b03
               |    | | | | | + Byte 6: Until date, month (mm) in hex
               |    | | | | +-- Byte 5: Encoded until time hh:mm in 30 minutes steps in hex, here 21:30
               |    | | | +---- Byte 4: Until date, year (yy) in hex
               |    | | +------ Byte 3: Until date, day in month (dd) in hex
               |    | +-------- Byte 2: Target temperature calculated as follows: temperature * 2 + 128 in hex, here 17.5°C
               |    +---------- Byte 1: "40" indicates request in order to change mode
               + request via handle 411
```

The thermostat returns status via notification handle simular to what we have seen in status for vacation mode:
```
Notification handle = 0x0421 value: 02 01 02 00 04 23 1f 11 2b 03 
                                          + mode, now "vacation"
```

## Target temperatures
The desired temperature can be set via several ways, e.g. by switching to a predefined temperature or by selecting a temperature directly. 
In addition you can activate the boost mode.

### Switch to comfort temperature
Switch to the comfort temperature by the following request:

Request:
```
char-write-req 0411 43
               |    +---- Byte 1: "43" indicates request in order to change to comfort temperature
               + request via handle 411
```

The thermostat returns status via notification handle simular to what we have seen before:
```
Notification handle = 0x0421 value: 02 01 00 00 04 2c
                                          + mode, still "auto"!
```

### Switch to eco temperature
Switch to the eco temperature by the following request:

Request:
```
char-write-req 0411 44
               |    +---- Byte 1: "44" indicates request in order to change to eco temperature
               + request via handle 411
```

The thermostat returns status via notification handle simular to what we have seen before:
```
Notification handle = 0x0421 value: 02 01 00 26 04 24
                                          + mode, still "auto"!
```

### Set target temperature
Switch to any temperature by the following request:

Request
```
char-write-req 0411 412d
               |    | +---------- Byte 2: Target temperature calculated as follows: temperature * 2 in hex, here 22.5°C
               |    +------------ Byte 1: "41" indicates request in order to change to a given temperature
               + request via handle 411
```

The thermostat returns status via notification handle simular to what we have seen before:
```
Notification handle = 0x0421 value: 02 01 00 00 04 2d
                                          + mode, still "auto"!
```

### Set thermostat on
Switch the thermostat on by setting temperature to 30°C. 

**Note** I don't know if this stops timers. Probably yes.

Request
```
char-write-req 0411 413c
               |    | +---------- Byte 2: Target temperature calculated as follows: temperature * 2 in hex, for "on" mode set 30.0°C
               |    +------------ Byte 1: "41" indicates request in order to change to a given temperature
               + request via handle 411
```

The thermostat returns status via notification handle simular to what we have seen before:
```
Notification handle = 0x0421 value: 02 01 00 37 04 3c
                                          + mode, still "auto" althougt I expect that timers are off now!
```

### Set thermostat off

Switch the thermostat off by setting temperature to 4.5°C. 

**Note** I don't know if this stops timers. Probably yes.

Request
```
char-write-req 0411 4109
               |    | +---------- Byte 2: Target temperature calculated as follows: temperature * 2 in hex, for "on" mode set 4.5°C
               |    +------------ Byte 1: "41" indicates request in order to change to a given temperature
               + request via handle 411
```

The thermostat returns status via notification handle simular to what we have seen before:
```
Notification handle = 0x0421 value: 02 01 00 37 04 09
                                          + mode, still "auto" althougt I expect that timers are off now!
```

### Activate boost mode
You can turn on the boost mode by the following command. Boost mode will be activated for 5 minutes until it stops. 
Unfortunately it does not seem to be possible to read the ETA before boost mode turns off automatically. 

Request
```
char-write-req 0411 45ff
               |    | +---------- Byte 2: Any value greater or equal than 1 starts boost mode. Values don't seem to make a difference.
               |    +------------ Byte 1: "45" indicates request in order to start boost mode
               + request via handle 411
```

The thermostat returns status via notification handle simular to what we have seen before:
```
Notification handle = 0x0421 value: 02 01 05 50 04 2c 
                                          + mode, now "boost" in combination with "manual"
```

### Stop boost mode
Turn off the boost mode by the following command.

Request
```
char-write-req 0411 4500
               |    | +---------- Byte 2: "00" stops boost mode. All values greater than 0 start boost mode.
               |    +------------ Byte 1: "45" indicates request in order to start boost mode
               + request via handle 411
```

The thermostat returns status via notification handle simular to what we have seen before:
```
Notification handle = 0x0421 value: 02 01 01 50 04 2c 
                                          + mode, now back to "normal"
```

## Timers
The thermostat has at least one time plan per day. In other forums you can read that there are even more timer programs possible but I haven't double-checked it. 
From my point of view it is good enough to have the possibility to have a schedule plan for each day of the week. 

Timers can be read and written via bluetooth. 

### Read timer for a day
The programmed timers can be read for every single day. There can be up to 7 changes of temperature per day.

Request
```
char-write-req 0411 2002
               |    | +---------- Byte 2: "00" day which will be queried, starting with "00" for Saturday and "06" for Friday
               |    +------------ Byte 1: "20" indicates request in order to request timer for given day
               + request via handle 411
```

The thermostat returns the data for timer of that day via notification handle 0x0421:
```
Notification handle = 0x0421 value: 21 02 27 24 29 84 27 90 00 00 00 00 00 00 00 00 
                                    |  |  |  |  |  |  |  |  |  |  |  |  |  |  |  +-- Byte 16: Encoded final time of seventh event in 10 minutes steps in hex, must be "24:00"
                                    |  |  |  |  |  |  |  |  |  |  |  |  |  |  +----- Byte 15: Temperature between sixth and seventh event, here it is not set
                                    |  |  |  |  |  |  |  |  |  |  |  |  |  +-------- Byte 14: Encoded time of sixth event in 10 minutes steps in hex, here not set
                                    |  |  |  |  |  |  |  |  |  |  |  |  +----------- Byte 13: Temperature between fifth and sixth event, here it is not set
                                    |  |  |  |  |  |  |  |  |  |  |  +-------------- Byte 12: Encoded time of fifth event in 10 minutes steps in hex, here not set
                                    |  |  |  |  |  |  |  |  |  |  +----------------- Byte 11: Temperature between forth and fifth event, here it is not set
                                    |  |  |  |  |  |  |  |  |  +-------------------- Byte 10: Encoded time of forth event in 10 minutes steps in hex, here not set
                                    |  |  |  |  |  |  |  |  +----------------------- Byte 9: Temperature between third and forth event, here it is not set
                                    |  |  |  |  |  |  |  +-------------------------- Byte 8: Encoded time of third event in 10 minutes steps in hex, here the final event that must always be 24:00
                                    |  |  |  |  |  |  +----------------------------- Byte 7: Temperature between second event and third event, here 20.5°C
                                    |  |  |  |  |  +-------------------------------- Byte 6: Encoded time of second event in 10 minutes steps in hex, here 22:00
                                    |  |  |  |  +----------------------------------- Byte 5: Temperature between first event and second event, here 19.5°C
                                    |  |  |  +-------------------------------------- Byte 4: Encoded time of first event in 10 minutes steps in hex, here 06:00
                                    |  |  +----------------------------------------- Byte 3: Temperature between midnight and first event, here 19.5°C
                                    |  +-------------------------------------------- Byte 2: Day, starting with "00" for Saturday and "06" for Friday, see Days
                                    +----------------------------------------------- Byte 1: Always "21"
```

#### Timer schema
- Byte 2: Day of timer
- Byte 3: Temperature between midnight and first event 

n = No. of event which must be between 1 and 7

- Byte 2 * n + 2: Encoded time of event n in 10 minutes steps in hex. If it is the last event it must be "24:00" (hex: "90")
- Byte 2 * n + 1: Temperature between event n - 1 and event n

It seems to be very important that:
1.  The time of the final event must have the value for "24:00"
2.  All events after the final event must be filled with zeros!

#### Days (Byte 2)
- 00 = Saturday
- 01 = Sunday
- 02 = Monday
- 03 = Tuesday
- 04 = Wednesday
- 05 = Thursday
- 06 = Friday

#### Time in timer
The time is encoded in intervals of 10 minutes.
```
hh = int(dec(value of byte) / 6)
mm = dec(value of byte 10) modulo 6 * 10
```

#### Temperature
The temperature is encoded in intervals of 0.5
```
temp = dec(value of byte) / 2.0
```  

### Set timer for a day
Setting of a timer program for a weekday is very simular to reading timers.

Request
```
char-write-req 0411 100622632a8922900000000000000000
               |    | | | | | | | | | | | | | | | +-- Byte 16: Encoded final time of seventh event in 10 minutes steps in hex, must be "24:00"
               |    | | | | | | | | | | | | | | +---- Byte 15: Temperature between sixth and seventh event, here it is not set
               |    | | | | | | | | | | | | | +------ Byte 14: Encoded time of sixth event in 10 minutes steps in hex, here not set
               |    | | | | | | | | | | | | +-------- Byte 13: Temperature between fifth and sixth event, here it is not set
               |    | | | | | | | | | | | +---------- Byte 12: Encoded time of fifth event in 10 minutes steps in hex, here not set
               |    | | | | | | | | | | +------------ Byte 11: Temperature between forth and fifth event, here it is not set
               |    | | | | | | | | | +-------------- Byte 10: Encoded time of forth event in 10 minutes steps in hex, here not set
               |    | | | | | | | | +---------------- Byte 9: Temperature between third and forth event, here it is not set
               |    | | | | | | | +------------------ Byte 8: Encoded time of third event in 10 minutes steps in hex, here the final event that must always be 24:00
               |    | | | | | | +-------------------- Byte 7: Temperature between second event and third event, here 17.5°C
               |    | | | | | +---------------------- Byte 6: Encoded time of second event in 10 minutes steps in hex, here 22:50
               |    | | | | +------------------------ Byte 5: Temperature between first event and second event, here 21.0°C
               |    | | | +-------------------------- Byte 4: Encoded time of first event in 10 minutes steps in hex, here 16:30
               |    | | +---------------------------- Byte 3: Temperature between midnight and first event, here 17.5°C
               |    | +------------------------------ Byte 2: "06" day which will be programmed, here Friday
               |    +-------------------------------- Byte 1: "10" indicates request in order to program timer
               + request via handle 411
```

The thermostat returns via notification handle
```
Notification handle = 0x0421 value: 02 02 06 
                                          + mode, the day that has been programmedm here Friday
```

#### Timer schema

- Byte 1: "10" indicates request in order to program timer
- Byte 2: Day of timer
- Byte 3: Temperature between midnight and first event 

n = No. of event which must be between 1 and 7

- Byte 2 * n + 2: Encoded time of event n in 10 minutes steps in hex. If it is the last event it must be "24:00" (hex: "90")
- Byte 2 * n + 1: Temperature between event n - 1 and event n

It seems to be very important that:

1.  The time of the final event must have the value for "24:00"
2.  All events after the final event must be filled with zeros!

#### Days (Byte 2)

- 00 = Saturday
- 01 = Sunday
- 02 = Monday
- 03 = Tuesday
- 04 = Wednesday
- 05 = Thursday
- 06 = Friday

#### Time in timer
The time is encoded in intervals of 10 minutes.
```
hh = int(dec(value of byte) / 6)
mm = dec(value of byte) modulo 6 * 10
```

#### Temperature
The temperature is encoded in intervals of 0.5
```
value of byte = hex(temp * 2.0)
```  

## Configuration
### Configure comfort and eco temperature
The comfort temperature (sun symbol) and the eco temperature (night symbol) will be programmed by a single request. 

Request
```
char-write-req 0411 112b23
               |    | | | 
               |    | | +---------------------------- Byte 3: Temperature for eco mode, here 17.5°C 
               |    | +------------------------------ Byte 2: Temperature for comfort mode, here 21.5°C
               |    +-------------------------------- Byte 1: "11" indicates request in order to program comfort and eco temperature
               + request via handle 411
```

The thermostat returns status via notification handle simular to the normal status that we have seen before:
```
Notification handle = 0x0421 value: 02 01 00 00 04 2a
```

### Configure window open mode
The window open mode can be programmed as follows:

Request
```
char-write-req 0411 14191e
               |    | | | 
               |    | | +---------------------------- Byte 3: Encoded period in 5 minutes steps in hex, here 150 minutes (02:30)
               |    | +------------------------------ Byte 2: Temperature for open window mode, here 12.5°C
               |    +-------------------------------- Byte 1: "14" indicates request in order to program open window mode
               + request via handle 411
```

The thermostat returns status via notification handle simular to the normal status that we have seen before:
```
Notification handle = 0x0421 value: 02 01 00 00 04 2a
```

### Configure offset temperature
The offset temperature can be programmed as follows:

Request
```
char-write-req 0411 1304
               |    | |
               |    | |
               |    | +------------------------------ Byte 2: Encoded offset temperature must be between -3.5°C and 3.5°C in steps of 0.5°C starting at -3.5°C
               |    +-------------------------------- Byte 1: "13" indicates request in order to configure offset temperature
               + request via handle 411
```

The thermostat returns status via notification handle simular to the normal status that we have seen before:
```
Notification handle = 0x0421 value: 02 01 00 00 04 2a
```

### Offset temperature
The offest temperature is encoded in intervals of 0.5 starting at -3.5°C
```
value of byte = hex((temp + 3.5) * 2.0)
```  

## Others

### Lock thermostat
The thermostat can be locked by this request:

Request
```
char-write-req 0411 8001
               |    | |
               |    | |
               |    | +------------------------------ Byte 2: "01" for lock thermostat
               |    +-------------------------------- Byte 1: "80" indicates request in order to lock/unlock thermostat
               + request via handle 411
```

The thermostat returns status via notification handle simular to the normal status that we have seen before:
```
Notification handle = 0x0421 value: 02 01 00 00 04 2a
```

### Unlock thermostat
The thermostat can be locked by this request:

Request
```
char-write-req 0411 8000
               |    | |
               |    | |
               |    | +------------------------------ Byte 2: "00" for unlock thermostat
               |    +-------------------------------- Byte 1: "80" indicates request in order to lock/unlock thermostat
               + request via handle 411
```

The thermostat returns status via notification handle simular to the normal status that we have seen before:
```
Notification handle = 0x0421 value: 02 01 00 00 04 2a
```

### Read latest command request
As you have seen all commands will be pushed via write request on handle 0x0411. The thermostat seems to remember the value that has been pushed before so that you can read it afterwards via char-read-hnd.

This was also very helpful in order to understand what the official app sends to the thermostat ;-)

```
char-read-hnd 0411

Characteristic value/descriptor: 03 11 02 09 14 2e 2e 90 00 00 00 00 00 00 00 00 
```

**Note** The thermostat does not overwrite the whole value. It overwrites just the length of bytes that has been sent. Therefore you should clear the buffer before use it for analysis. See next.

```
char-write-req 0411 00000000000000000000000000000000
```

### Factory reset 
In other forums I have read that there is this command in order to reset the thermostat to factory settings. Actually I haven't double-checked it. 

Request
```
char-write-req 0411 f0
```

Since I haven't tried it out, I don't know what the thermostat will do afterwards. 

### What's about the PIN that everyone of us has entered?
I don't know. There is nothing to do in order to control the thermostat via bluetooth and gatttool!
Seems to be fake ;-)
