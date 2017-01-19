#ExecParallel

##Description
A CLI job queue for running multiple commands in parallel. Add jobs on the fly and use event 
callbacks to get notified when processes start/write to stdout/stderr/complete/etc.

##Mini Project Disclaimer
This is a mini-project, for my own use, and as such may not contain all the proper 
setters/getters/validation/testing that you would normally expect from production ready code. You 
may find it useful, however I don't consider it robust enough to recommend to the general public. 
Fixes / pull requests are always welcome.

##Example

###1. Create and configure the controller object
```php
use \ExecParallel\Controller;
use \ExecParallel\Job;

// Create the ExecParallel controller.
$jobControl = new Controller();

// Maximum number of simultaneous jobs you want running. You can queue up more than this and 
// Controller will start more as others finish.
$jobControl->maxJobs = 2;

// Set an event / callback when the first job starts, and another when all jobs are complete. 
// Controller triggers all pass in the Controller object as the first parameter for easy 
// reference. Some events may pass in additional parameters as well.
$jobControl->on('start',function($controller) { print "Starting jobs...\n"; });
$jobControl->on('complete',function($controller) { print "Completed all jobs...\n"; });
```
###2. Create, configure, and add one or more jobs to the queue

Repeat as needed:

```php
// Add a job
$job = new Job();
$job->command = 'sleep 5 && echo "5 seconds complete!"';

// Set some events for when the job starts, completes, or writes to stdout/stderr. Job triggers all
// include the Job object as the first parameter, for easy reference. Some events may pass in 
// additional parameters as well (eg: complete passes the exit code of the completed command).
$job->on('start',function($job) { print "Stated the echo job\n"; });
$job->on('stdout',function($job,$output) { print "Echo job output: $output\n"; });
$job->on('stderr',function($job,$output) { print "Echo job output to ERR: $output\n"; });
$job->on('complete',function($job,$exitCode) { print "Completed the echo job, exited with ".var_export($exitCode,true).".\n"; });

// If you want to pass data to the command on stdin, uncomment and set appropriately...
// $job->stdin = 'Input to pass to command';

// Add the job
$jobControl->addJob($job);
```

###3. Start the queue and wait until complete

Finally you want to start the jobs and wait until they're complete. There are a couple ways you can
do this. If you just want to wait until everything is done and not do anything else in the meantime,
you can call waitUntilComplete.

```php
// Start jobs and wait until all jobs are complete
$jobControl->waitUntilComplete();

print "Total run time: ".$jobControl->runTime." sec\n";
```

Alternatively you can continue doing other things in the meantime, occasionally calling process to
check in on the running jobs and fire events as needed, which will return false once all jobs are 
complete.

```php
while ($jobControl->process()) {
	// do some things in here
}

print "Total run time: ".$jobControl->runTime." sec\n";
```

##Controller Object

###Properties
Accessed via (eg) $jobControl->startTime.

####startTime
The Unix timestamp of when the first job started, or NULL if nothing has started yet.

####completeTime
The Unix timestamp of when the last job completed, or NULL if nothing has started yet, or jobs are 
still running.

####runTime
The number of seconds jobs were running for (completeTime - startTime), or NULL if completeTime 
isn't set.

###Events
Register events with (eg) $jobControl->on('event',callable). Your callable function should accept a 
Controller object as it's first parameter, and optionally other parameters for specific events.

####start
Called just before the first job is started, and before the first job's start event.

####complete
Called after the last job is completed, and after the last job's complete event.

##Job Object

###Job properties
Accessed via (eg) $job->startTime.

####stdout
Everything this command wrote to stdout. Available while the job is running, however cannot be 
considered "complete" until the job is completed (the command may continue to write to stdout, 
which will be added to this as it does).

####stderr
Everything this command wrote to stderr. Available while the job is running, however cannot be 
considered "complete" until the job is completed (the command may continue to write to stderr, 
which will be added to this as it does).

####returnCode
The exit / return code of the command. NULL until the job is complete.

####startTime
A Unix timestamp of the time the command was started (eg: use with date), or NULL if the job hasn't 
started yet.

####$job->completeTime
A Unix timestamp of the time the command completed (eg: use with date), or NULL if the job hasn't 
completed yet.

####$job->runTime
The number of seconds the command was running for. NULL if completeTime isn't set.

###Events
Register events with $job->on('event',callable). Your callable function should accept a Job object 
as it's first parameter, and optionally other parameters for specific events.

####start
Called just after the command is executed.

####complete
Called just after the job's command has completed. Second parameter will be the exit/return code 
from the command.

####stdout
Called when the job's command writes to stdout (standard output). Second parameter will be a string 
with the output that triggered the event.

####stderr
Called when the job's command writes to stderr (error output). Second parameter will be a string 
with the output that triggered the event.

##License

ExecParallel is licensed under the Modified BSD License (aka the 3 Clause BSD). Basically you can 
use it for any purpose, including commercial, so long as you leave the copyright notice intact and 
don't use my name or the names of any other contributors to promote products derived from 
ExecParallel.

	Copyright (c) 2012, ravisorg
	All rights reserved.
	
	Redistribution and use in source and binary forms, with or without
	modification, are permitted provided that the following conditions are met:
	    * Redistributions of source code must retain the above copyright
	      notice, this list of conditions and the following disclaimer.
	    * Redistributions in binary form must reproduce the above copyright
	      notice, this list of conditions and the following disclaimer in the
	      documentation and/or other materials provided with the distribution.
	    * Neither the name of the Travis Richardson nor the names of its 
	      contributors may be used to endorse or promote products derived 
	      from this software without specific prior written permission.
	
	THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
	ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
	WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
	DISCLAIMED. IN NO EVENT SHALL TRAVIS RICHARDSON BE LIABLE FOR ANY
	DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
	(INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
	LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
	ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
	(INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
	SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.