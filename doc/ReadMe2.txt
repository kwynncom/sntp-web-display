2019/11/28 12:51pm

This is now running at  https://kwynn.com/t/9/11/sntp/

I am not changing the following, although several things have changed relative to this readme.  


************
2019/11/24 11:38pm EST (GMT -5)
Kwynn Buess, kwynn.com
NTP / SNTP code to compare machine running code to "official" time

The purpose of this code is to compare the time / clock the machine running the code to NTP servers.  NTP is network time protocol.  I have read parts of the 
Simple NTP protocol which match this code.  I'm not sure what the relationship is between NTP and SNTP, but I am calling port 123 which is listed as 
NTP. On the other hand, I use "sntp" within the code.

The purpose is not to sync the time.  That seems to be done quite well.  But I found myself asking the simple question of how to compare time, and 
I couldn't find a simple answer.  This code isn't necessarily simple because it enforces quotas and such, but it does solve the problem of 
comparing clocks.

This code will probably come with t1.php and t2.php or try1 or whatever [now utils/simple_standalone.php].  That's my first adaptations of the 
FRa StackOverflow user's code.  It is easiest to run that code first to get an idea of what's going on.  That code should "just run," as opposed 
to your needing PHP-MongoDB and such.

My goals to advance FRa's code were:

Clean his code up, such as modularizing it.
Get greater precision by setting a base integer timestamp and then doing math relative to that base.  That's a long discussion perhaps for later.
Enforce "Kiss of Death" / exponential backoff.  That is, if a server doesn't work, back off for a while, and then a longer while until the code never 
tries again.
Similarly, enforce quotas to not stress servers.
Lay out the data nicely.
Do calcuations.

Some detailed comments, more or less in execution order:

Attempt to get an NTP server from the database.
If there are 0 servers in the database, go to the getServers.php list.
I'm keeping track of both every host and its "family."  For example, NIST and Virgia Tech have a number of servers.  NIST specifies to not 
query their servers more than every 4 seconds.  I enforce that as for all of NIST, not a particular server.  

Meanwhile, I also keep track of each server's activity and round-robin within the family.  

The "name" array / DB attribute is the family name.

The KOD or kod is the "kiss of death" tracker.  That number gets higher with each failure of a host / server.

Within index, we get a server and then run the NTP query.  If we get to the "OK" line, that means we haven't had an Exception.  In that case, 
save the data from the query with an OK status.

Then run the template as in "output" to HTML or text / console / command line.

*** DAO / dao.php ****

The server table / collection is just a copy of the array in getServers.php.  

The tables / collections are usage header and usage line item.  The header keeps the latest info on each host.  The line items are an exhaustive 
record of each usage.  I am pretty sure line items are write-only in the sense that I'm not using / reading that data.  I'm just keeping the data.  

Dat are the final results with calcs.  

Upon init, write each host.  "waituntil" as in don't execute until.  Initially set it to now.  I use microtime not for any timing purposes but because 
if I'm enforcing down to 4 seconds, I don't want any rounding issues.  

minpoll is the quota or least inverval between polls.  I got the default of 67 from some reading of ntp.org.

I separated the servers into a "from" for calling from Georgia, anywhere, or Virginia.  My AWS server is in VA, so it will call "anywhere" and VA, 
etc.  I am in GA.  There are more servers at ntp.org that could be added.  

The "get()" is based on which servers are where and within quota, then grab the one that was used the longest ago--a round-robin.


*** SNTP.php *****

The "bit_max" goes to the packet format of the SNTP reply.  bit_max is 2^32.  The packet comes back with an integer base timestamp 
plus a fraction of a second.  The fraction is x / 2^32.  So the exact timestamp returned is base + fraction.

The UNIX epoch used by microtime() and time() and the NTP epoch are decades off.  That is the adjustment.

Part of the packet sent to the server is esoteric SNTP stuff that I didn't touch from the original.  It seems to work.

I had to temporarily change the error handler because the socket open throws a warning upon failure.  I want to handle that as an 
exception and not a warning, so I swap the error level in and out.

I "unset" things because I found it's easier when using the debugger.

Note that I never changed some of FRa's variable names.  

The NTP protocol request should have the client's time encoded in NTP format.  Thus the call to PHP microtime() at that point.  

The NTP protocol's epoch / beginning of time 0 second is decades earlier than the Unix epoch; thus the conversion.  (I have no idea why it's earlier.  
It doesn't make sense, but I haven't looked up the decision.)

"stratum" is 0 for "kiss of death" / dead server.  1 for atomic clock-connected servers, 2 for the next tier of servers, etc.

I call it "sharpen" because I'm keeping higher precision than FRa's code.  64 bit / double precision floating points cannot quite hold the 
precision for today's UNIX era timestamps to the microsecond.  You lose 1 or 2 decimal points.  So I take the minimum second of the stamps and then 
subtract that out to keep precision.

The calcs result in:

The time offset / error.  I use the opposite sign from the official NTP formula.  In my case a negative means that my clock--the clock running this 
code--is behind the official time.

Note that the time measurement varies widely with network jitter: the difference in to and from delay.  That's the best you can possibly calculate 
with this method.  Thus, the average of the offsets is the best you can do to measure your clock.  I mention my results below.

srvd - server delay--the server receive versus send ts.
outd, ind - outbound and incoming delay

******** OUTPUT ********

The top number is the average of what's on the screen -- the best estimate of clock offset.  

Then I give the last 10 results in one table and then the last result via other formats.

The last result is the base time then the following timestamps.  The number between the server times is another way of expressing the 
offset measurement or the offset basis--the average of my machine's send and receive compared to the server's send and receive.  


RESULTS / TIME KEEPING METHOD

[Update: I disabled timesyncd and am using chrony now.  timesyncd will "kill" chrony on boot if not disabled.]

On my local dev machine, the individual measurements are usually within 3 ms.  My clock's error based on the average is around 0.1ms.  That's ms or milli 
as in 10^-3.

This is my machines timekeeping mechanism:

$ systemctl status systemd-timesyncd

● systemd-timesyncd.service - Network Time Synchronization
   Loaded: loaded (/lib/systemd/system/systemd-timesyncd.service; enabled; vendor preset: enabled)
   Active: active (running) since Sat 2019-11-23 18:43:53 EST; 1 day 3h ago
     Docs: man:systemd-timesyncd.service(8)
 Main PID: 11614 (systemd-timesyn)
   Status: "Synchronized to time server 91.189.94.4:123 (ntp.ubuntu.com)."
    Tasks: 2 (limit: 9830)
   CGroup: /system.slice/systemd-timesyncd.service
           └─11614 /lib/systemd/systemd-timesyncd

Nov 23 18:43:52 ... systemd[1]: Starting Network Time Synchronization...
Nov 23 18:43:53 ... systemd[1]: Started Network Time Synchronization.
Nov 23 18:43:53 ... systemd-timesyncd[11614]: Synchronized to time server 91.189.89.199:123 (ntp.ubuntu.com).
Nov 24 16:57:00 ... systemd-timesyncd[11614]: Synchronized to time server 91.189.94.4:123 (ntp.ubuntu.com).


FUTURE WORK

See what SNTP versus NTP are.  Depending, I should probably use NTP throughout.  It would make the code easier to find.

[Update: SNTP is more accurate.  Given that I'm not trying to sync time, I'm leaving out a bunch of complexity of NTP.]

I want to add one more layer of quota which limits queries to something like 30 an hour.  This is a toy and not a reason to beating on servers.

Lots of cleanup to output.  Perhaps more to come.

I'm sure there's more, but time to get this out the door.


CREDITS

I started from FRa's code:

https://stackoverflow.com/questions/16592142/retrieve-time-from-ntp-server-via-php
https://stackoverflow.com/users/4622767/fra

answered Mar 3 '15 at 9:14, FRa is the SO user.
FRa had been a "Member for 4 years, 8 months" as of 2019/11/19 9:14pm EST (GMT -5)

Most immediately useful: SNTPv4: https://tools.ietf.org/html/rfc4330
https://tools.ietf.org/html/rfc5905#page-19
https://tf.nist.gov/tf-cgi/servers.cgi

http://support.ntp.org/bin/view/Servers/StratumTwoTimeServers?sortcol=0;table=1;up=0#sorted_table


HISTORY

2019/11/28 12:51pm - This version has been running on my site for about 19 hours.  I'll tentatively call this 0.0.6.  I'm going to send this to 1 
apprentice.  0.0.5 will be anything in between.  

This timestamp: 2019/11/24 11:38pm EST (GMT -5).  This is about when I plan to send this code to at least 4 potential apprentices.  It will be the 
first release to anyone but me.  

This code is not live on Kwynn.com yet mostly because of the new quota layer I want to add, mentioned above.  

I've been running this on my machine for several days, maybe a week.  At some point maybe I'll look up the history.

I'll tentatively call this version 0.0.4 because there is t1 and t2 and then anything I ran before this.  But I give a timestamp because that's more 
reliable than version numbers.
