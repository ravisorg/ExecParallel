<?php namespace ExecParallel;

class Job {
	public $jobId = null;
	public $status = 'queued';
	public $command = null;
	public $stdin = '';
	public $stdout = '';
	public $stderr = '';
	protected $_events = array();
	protected $_triggeredStart = false;
	protected $_triggeredComplete = false;
	public $pipes = array();
	public $process = null;
	public $returnCode = null;
	public $startTime = null;
	public $completeTime = null;
	public $runTime = null;

	public function __construct($jobId=null) {
		$this->jobId = $jobId;
	}

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

	public function start() {
		$descriptorspec = array(
			0 => array("pipe", "r"),  // stdin is a pipe that the child will read from
			1 => array("pipe", "w"),  // stdout is a pipe that the child will write to
			2 => array("pipe", "w") // stderr is a file to write to
		);

		$this->pipes = array();
		$this->stdout = '';
		$this->stderr = '';

		$this->process = proc_open($this->command, $descriptorspec, $this->pipes);
		if (!$this->process || !is_resource($this->process)) {
			$this->status = 'error';
			return false;
		}

		$this->status = 'running';
		$this->startTime = microtime(true);

		// write stdin to the first pipe and then close it
		fwrite($this->pipes[0],$this->stdin);
		fclose($this->pipes[0]);
		$this->pipes[0] = null;

		// disable blocking on the stdout and stderr (we want to run everything in
		// parallel, so nothing can block)
		stream_set_blocking($this->pipes[1],false);
		stream_set_blocking($this->pipes[2],false);

		if (!$this->_triggeredStart) {
			$this->trigger('start');
			$this->_triggeredStart = true;
		}

		$this->checkStatus();

		return true;
	}

	protected function complete() {
		$this->status = 'complete';

		if (is_resource($this->pipes[1])) {
			fclose($this->pipes[1]);
		}
		$this->pipes[1] = null;
		if (is_resource($this->pipes[2])) {
			fclose($this->pipes[2]);
		}
		$this->pipes[2] = null;

		if (is_resource($this->process)) {
			$returnCode = proc_close($this->process);
			if (is_null($this->returnCode)) {
				$this->returnCode = $returnCode;
			}
		}
		$this->process = null;

		if (!$this->completeTime) {
			$this->completeTime = microtime(true);
			$this->runTime = $this->completeTime - $this->startTime;
		}

		if (!$this->_triggeredComplete) {
			$this->trigger('complete',array($this->returnCode));
			$this->_triggeredComplete = true;
		}
	}

	public function checkStatus() {

		// if the stdout stream has closed, then this job is complete
		if ($this->status=='running') {

			$status = proc_get_status($this->process);
			if ($status['exitcode']!=-1) {
				$this->returnCode = $status['exitcode'];
			}
			if (!$status['running']) {
				$this->complete();
			}

		}

		return $this->status;
	}

	public function readPipes() {
		if ($this->status!='running') {
			return;
		}

		if (!feof($this->pipes[1])) {
			$stdout = fread($this->pipes[1],1024);
			if ($stdout) {
				$this->stdout .= $stdout;
				$this->trigger('stdout',array($stdout));
			}
		}

		if (!feof($this->pipes[2])) {
			$stderr = fread($this->pipes[2],1024);
			if ($stderr) {
				$this->stderr .= $stderr;
				$this->trigger('stderr',array($stderr));
			}
		}

	}

}

