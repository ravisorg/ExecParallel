<?php namespace ExecParallel;

class Controller {

	protected $_jobs = array();
	protected $_pendingJobs = array();
	protected $_runningJobs = array();
	protected $_completedJobs = array();
	protected $_jobProcesses = array();
	protected $_jobPipes = array();
	protected $_events = array();
	protected $_triggeredStart = false;
	protected $_triggeredComplete = false;
	public $startTime = null;
	public $completeTime = null;
	public $runTime = null;

	public $maxJobs = 2;

	public function on($event,$callback) {
		if (!isset($this->_events[$event])) {
			$this->_events[$event] = array();
		}
		$this->_events[$event][] = $callback;
	}

	public function trigger($event,$parameters=array()) {
		array_unshift($parameters,$this);
		if (isset($this->_events[$event])) {
			foreach ($this->_events[$event] as $callback) {
				call_user_func_array($callback,$parameters);
			}
		}
	}

	public function addJob(Job $job) {
		$jobId = count($this->_jobs);
		$job->jobId = $jobId;
		$this->_jobs[$jobId] = $job;
		return $jobId;
	}

	/**
	 * [addJob description]
	 *
	 * @param [type] $command [description]
	 * @param array $options keys: input, onStart onOutput onError onComplete
	 */
	public function addCommand($command,$options=array()) {
		$jobId = count($this->_jobs);

		$job = new Job($jobId);
		$job->command = $command;
		$job->status = 'queued';
		if (isset($options['input'])) {
			$job->stdin = $options['input'];
		}
		if (isset($options['onStart'])) {
			$job->on('start',$options['onStart']);
		}
		if (isset($options['onOutput'])) {
			$job->on('stdout',$options['onOutput']);
		}
		if (isset($options['onError'])) {
			$job->on('stderr',$options['onError']);
		}
		if (isset($options['onComplete'])) {
			$job->on('complete',$options['onComplete']);
		}
		$this->_jobs[$jobId] = $job;

		return $jobId;
	}

	public function getPendingJobs() {
		$jobs = array();
		foreach ($this->_jobs as $job) {
			if ($job->status=='queued') {
				$jobs[$job->jobId] = $job;
			}
		}
		return $jobs;
	}

	public function getRunningJobs() {
		$jobs = array();
		foreach ($this->_jobs as $job) {
			if ($job->status=='running') {
				$jobs[$job->jobId] = $job;
			}
		}
		return $jobs;
	}

	public function getCompletedJobs() {
		$jobs = array();
		foreach ($this->_jobs as $job) {
			if ($job->status=='complete') {
				$jobs[$job->jobId] = $job;
			}
		}
		return $jobs;
	}

	protected function startJobs() {

		// start as many jobs as needed to get to maxJobs
		// if fewer than max processes are running, start some more
		$runningCount = 0;
		foreach ($this->_jobs as $job) {
			if ($job->status=='running') {
				$runningCount++;
			}
		}
		if ($runningCount<$this->maxJobs) {
			$jobs = $this->getPendingJobs();
			while ($jobs && $runningCount<$this->maxJobs) {
				if (is_null($this->startTime)) {
					$this->startTime = microtime(true);
				}
				if (!$this->_triggeredStart) {
					$this->trigger('start');
					$this->_triggeredStart = true;
				}

				$job = array_shift($jobs);
				$job->start();
				if ($job->status=='running') {
					$runningCount++;
				}
			}
		}

	}

	public function process($seconds=1) {

		$pipes = array();
		$pipeJobs = array();
		$write = NULL;
		$ex = NULL;

		// update the status of any jobs
		foreach ($this->_jobs as $job) {
			$status = $job->checkStatus();
		}

		// if fewer than max processes are running, start some more
		$this->startJobs();

		// collect all open pipes for all running jobs
		foreach ($this->_jobs as $job) {
			if ($job->status=='running') {
				// print "Job ".$job->jobId." running... ";
				if (!feof($job->pipes[1])) {
					$pipes[] = $job->pipes[1];
					$pipeJobs[(int)$job->pipes[1]] = $job;
					// print "stdout ";
				}

				if (!feof($job->pipes[2])) {
					$pipes[] = $job->pipes[2];
					$pipeJobs[(int)$job->pipes[2]] = $job;
					// print "stderr ";
				}
				// print "\n";
			}
		}

		// If there are no pipes, then we're probably done everything
		if (!$pipes) {
			if (!$this->completeTime) {
				$this->completeTime = microtime(true);
				$this->runTime = $this->completeTime - $this->startTime;
			}
			if (!$this->_triggeredComplete) {
				$this->trigger('complete');
				$this->_triggeredComplete = true;
			}
			return false;
		}

		// watch all these collected pipes for updates for $seconds max
		$ready = stream_select($pipes, $write, $ex, $seconds);

		// if it returned false, that shouldn't happen because we should only be watching
		// active pipes, but....
		if ($ready === false) {
			return; #should never happen - something died
		}

		// instruct all running jobs to read their pipes (ideally we'd only ask pipes that
		// updated via stream_select, but that's a pain and we're not going to have tens of
		// thousands of jobs, so....)
		foreach ($this->_jobs as $job) {
			if ($status=='running') {
				$job->readPipes();
			}
		}

		// return true to show something's still happening
		return true;
	}

	public function waitUntilComplete() {
		while (true) {
			$running = $this->process();
			if (!$running) {
				break;
			}
		}
	}

}


