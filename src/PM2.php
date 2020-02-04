<?php
namespace pm2;

use chorus\InvalidConfigException;
use chorus\InvalidParamException;
use chorus\UnsatisfiedExcepiton;

/**
 * PM2
 * PM2
 * ---
 * @author Verdient。
 */
class PM2 extends \chorus\BaseObject
{
	/**
	 * @var const EVENT_CHECK_ENVIRONMENT
	 * 检查运行环境
	 * ----------------------------------
	 * @author Verdient。
	 */
	const EVENT_CHECK_ENVIRONMENT = 'checkEnvironment';

	/**
	 * @var const EVENT_START
	 * 启动进程
	 * ----------------------
	 * @author Verdient。
	 */
	const EVENT_START = 'start';

	/**
	 * @var const EVENT_STOP
	 * 停止进程
	 * ---------------------
	 * @author Verdient。
	 */
	const EVENT_STOP = 'stop';

	/**
	 * @var const EVENT_RESTART
	 * 停止进程
	 * ------------------------
	 * @author Verdient。
	 */
	const EVENT_RESTART = 'restart';

	/**
	 * @var const EVENT_RESET
	 * 重置进程
	 * ----------------------
	 * @author Verdient。
	 */
	const EVENT_RESET = 'reset';

	/**
	 * @var const EVENT_DELETE
	 * 删除进程
	 * -----------------------
	 * @author Verdient。
	 */
	const EVENT_DELETE = 'delete';

	/**
	 * @var Array $scripts
	 * 脚本
	 * -------------------
	 * @author Verdient。
	 */
	public $scripts = [];

	/**
	 * @var Boolean $enableMerge
	 * 是否允许合并
	 * -------------------------
	 * @author Verdient。
	 */
	public $enableMerge = true;

	/**
	 * @var Array $_supportMergeCommands
	 * 支持合并的命令
	 * ---------------------------------
	 * @author Verdient。
	 */
	protected $_supportMergeCommands = [
		'start', 'stop', 'restart', 'delete'
	];

	/**
	 * @var Array $_normalizedScripts
	 * 格式化后的脚本配置
	 * ------------------------------
	 * @author Verdient。
	 */
	protected $_normalizedScripts = false;

	/**
	 * @var Boolean $_environmentOK
	 * 环境是否正常
	 * ----------------------------
	 * @author Verdient。
	 */
	protected $_environmentOK = [
		'start', 'stop', 'restart', 'delete'
	];

	/**
	 * useMerge(String $command)
	 * 是否使用合并
	 * -------------------------
	 * @param String $command 命令
	 * --------------------------
	 * @return Boolean
	 * @author Verdient。
	 */
	protected function useMerge($command){
		if($this->enableMerge === true){
			return in_array($command, $this->_supportMergeCommands);
		}
		return false;
	}

	/**
	 * requestScripts(Array $args[, Boolean $exist = true, Boolean $running = false])
	 * 获取请求的脚本
	 * ------------------------------------------------------------------------------
	 * @param Array $args 请求的参数
	 * @param Boolean $exist 是否只包含已存在的
	 * @param Boolean $exist 是否只包含正在运行的
	 * --------------------------------------
	 * @return Array
	 * @author Verdient。
	 */
	protected function requestScripts($args, $exist = true, $running = false){
		$scripts = array_keys($this->scripts);
		if(!empty($args)){
			$scripts = array_intersect($scripts, $args);
			if(empty($scripts)){
				throw new InvalidParamException('未知的脚本: ' . implode(', ', $args));
			}
		}
		if($exist === true){
			if($running === true){
				$scripts = array_intersect($scripts, $this->getRuningScrpits());
			}else{
				$scripts = array_intersect($scripts, $this->getExistScrpits());
			}
		}else{
			$scripts = array_diff($scripts, $this->getExistScrpits());
		}
		return $scripts;
	}

	/**
	 * runCommand(String $command)
	 * 执行命令
	 * ---------------------------
	 * @param String $command 命令
	 * --------------------------
	 * @return Array
	 * @author Verdient。
	 */
	protected function runCommand($command){
		exec($command, $output);
		return $output;
	}

	/**
	 * runPM2Command(String $command)
	 * 运行PM2命令
	 * ------------------------------
	 * @param String $command 命令
	 * --------------------------
	 * @return Array
	 * @author Verdient。
	 */
	protected function runPM2Command($command, $args = []){
		$this->checkEnvironment();
		$command = 'pm2 ' . $command;
		foreach($args as $key => $value){
			if(is_array($value)){
				foreach($value as $element){
					$element = trim($element);
					$parcel = '';
					if(strpos($element, ' ') !== false){
						$parcel = '"';
					}
					$command .= ' --' . $key . ' ' . $parcel . $element . $parcel;
				}
			}else{
				$command .= ' --' . $key;
				if(!empty($value)){
					$parcel = '';
					$value = trim($value);
					if(strpos($value, ' ') !== false){
						$parcel = '"';
					}
					$command .= ' ' . $parcel . $value . $parcel;
				}
			}
		}
		echo $command . PHP_EOL;
		return $this->runCommand($command);
	}

	/**
	 * normalizeOutput(Array $output)
	 * 格式化输出
	 * ------------------------------
	 * @param Array $output 输出
	 * ------------------------
	 * @return Array
	 * @author Verdient。
	 */
	protected function normalizeOutput($output){
		foreach($output as &$row){
			$row = explode('│', $row);
			foreach($row as &$element){
				$element = trim($element);
			}
		}
		return $output;
	}

	/**
	 * getNormalizedScripts()
	 * 获取格式化后的脚本配置
	 * ----------------------
	 * @return Array
	 * @author Verdient。
	 */
	public function getNormalizedScripts(){
		if($this->_normalizedScripts === false){
			$this->_normalizedScripts = [];
			foreach($this->scripts as $name => $script){
				if(is_array($script)){
					if(!isset($script['script'])){
						throw new InvalidConfigException('脚本配置数组必须包含script元素');
					}
					$this->_normalizedScripts[$name] = [
						'name' => $name,
						'script' => $script['script'],
						'cwd' => isset($script['cwd']) ? $script['cwd'] : null,
						'args' => isset($script['args']) ? $script['args'] : [],
						'interpreter' => isset($script['interpreter']) ? $script['interpreter'] : 'php',
						'interpreter_args' => isset($script['interpreter_args']) ? $script['interpreter_args'] : []
					];
				}else{
					$this->_normalizedScripts[$name] = [
						'name' => $name,
						'script' => $script,
						'cwd' => null,
						'args' => [],
						'interpreter' => 'php',
						'interpreter_args' => []
					];
				}
			}
		}
		return $this->_normalizedScripts;
	}

	/**
	 * getExistScrpits()
	 * 获取已存在的进程
	 * -----------------
	 * @return Array
	 * @author Verdient。
	 */
	public function getExistScrpits(){
		return array_column($this->list(), 'appname');
	}

	/**
	 * getRuningScrpits()
	 * 获取正在运行的进程
	 * -------------------
	 * @return Array
	 * @author Verdient。
	 */
	protected function getRuningScrpits(){
		$scripts = [];
		foreach($this->list() as $row){
			if($row['status'] === 'online'){
				$scripts[] = $row['appname'];
			}
		}
		return $scripts;
	}

	/**
	 * checkNodeJs()
	 * 检查node.js
	 * -------------
	 * @return Boolean
	 * @author Verdient。
	 */
	protected function checkNodeJs(){
		$result = $this->runCommand('node -v');
		return preg_match('/^v\d+\.\d+\.\d+$/', $result[0]) !== 0;
	}

	/**
	 * checkPM2()
	 * 检查PM2
	 * ----------
	 * @author Verdient。
	 */
	protected function checkPM2(){
		$result = $this->runCommand('pm2 -v');
		return preg_match('/^\d+\.\d+\.\d+$/', $result[0]) !== 0;
	}

	/**
	 * checkEnvironment()
	 * 检查环境
	 * ------------------
	 * @author Verdient。
	 */
	protected function checkEnvironment(){
		if($this->_environmentOK === false){
			$this->trigger(static::EVENT_CHECK_ENVIRONMENT);
			if(!$this->checkPM2()){
				if(!$this->checkNodeJs()){
					throw new UnsatisfiedExcepiton('请先安装 node.js');
				}
				throw new UnsatisfiedExcepiton('请先安装 PM2');
			}
			$this->_environmentOK = true;
		}
		return $this->_environmentOK;
	}

	/**
	 * getEventName(String $operation)
	 * 获取事件名称
	 * -----------------------------
	 * @param String $operation 操作
	 * ----------------------------
	 * @return String
	 * @author Verdient。
	 */
	protected function getEventName($operation){
		$map = [
			'start' => static::EVENT_START,
			'stop'=> static::EVENT_STOP,
			'restart' => static::EVENT_RESTART,
			'reset' => static::EVENT_RESET,
			'delete' => static::EVENT_DELETE
		];
		return isset($map[$operation]) ? $map[$operation] : null;
	}

	/**
	 * execute(String $operation, Array $names[, Array $args = []])
	 * 执行
	 * ------------------------------------------------------------
	 * @param String $operation 操作
	 * @param Array $names 名称
	 * @param Array $args 附加参数
	 * -----------------------------
	 * @return Boolean
	 * @author Verdient。
	 */
	protected function execute($operation, $names, $args = []){
		switch($operation){
			case 'start':
				$names = $this->requestScripts($names, false);
				break;
			case 'stop':
				$names = $this->requestScripts($names, true, true);
				break;
			default:
				$names = $this->requestScripts($names);
				break;
		}
		$count = count($names);
		if($count > 0){
			$eventName = $this->getEventName($operation);
			$normalizedScripts = $this->getNormalizedScripts();
			$scripts = [];
			foreach($names as $name){
				$scripts[] = $normalizedScripts[$name];
			}
			if($this->useMerge($operation)){
				$fileName = sys_get_temp_dir() . DIRECTORY_SEPARATOR . '.scripts.json';
				if(file_exists(__DIR__ . DIRECTORY_SEPARATOR . $fileName)){
					unlink($fileName);
				}
				file_put_contents($fileName, json_encode(array_values($scripts)));
				$command = $operation . ' ' . $fileName;
				$this->runPM2Command($command);
				unlink($fileName);
				if($eventName){
					$this->trigger($eventName, $count, $count, array_column($scripts, 'name'));
				}
			}else{
				$index = 0;
				foreach($scripts as $script){
					$command = $operation;
					if($operation === 'start'){
						$command .= ' ' . $script['interpreter'];
						if(!empty($script['interpreter_args'])){
							$command .= ' ' . implode(' ', $script['interpreter_args']);
						}
						$path = ($script['cwd'] ? ($script['cwd'] . DIRECTORY_SEPARATOR) : '') . $script['script'];
						$args = $path;
						if(!empty($script['args'])){
							$args = array_merge([$path], $script['args']);
						}
						$this->runPM2Command($command, [
							'name' => $script['name'],
							'' => $args
						]);
					}else{
						$command .= ' ' . $script['name'];
						$this->runPM2Command($command);
					}
					$index ++;
					if($eventName){
						$this->trigger($eventName, $index, $count, [$script['name']]);
					}
				}
			}
		}
		return true;
	}

	/**
	 * start([Array $names = [], Array $args = []])
	 * 启动
	 * --------------------------------------------
	 * @param Array $names 名称
	 * @param Array $args 参数
	 * ------------------------
	 * @return Boolean
	 * @author Verdient。
	 */
	public function start($names = [], $args = []){
		return $this->execute('start', $names, $args);
	}

	/**
	 * stop([Array $names = []])
	 * 停止
	 * -------------------------
	 * @param Array $names 名称
	 * -----------------------
	 * @return Boolean
	 * @author Verdient。
	 */
	public function stop($names = []){
		return $this->execute('stop', $names);
	}

	/**
	 * restart([Array $names = []])
	 * 重启
	 * ----------------------------
	 * @param Array $names 名称
	 * ------------------------
	 * @return Boolean
	 * @author Verdient。
	 */
	public function restart($names = []){
		return $this->execute('restart', $names);
	}

	/**
	 * reset([Array $names = []])
	 * 重置
	 * --------------------------
	 * @param Array $names 名称
	 * ------------------------
	 * @return Boolean
	 * @author Verdient。
	 */
	public function reset($names = []){
		return $this->execute('reset', $names);
	}

	/**
	 * delete([Array $names = []])
	 * 删除
	 * ---------------------------
	 * @param Array $names 名称
	 * -----------------------
	 * @return Boolean
	 * @author Verdient。
	 */
	public function delete($names = []){
		return $this->execute('delete', $names);
	}

	/**
	 * flush([Array $names = []])
	 * 刷新日志
	 * --------------------------
	 * @param Array $names 名称
	 * -----------------------
	 * @return Boolean
	 * @author Verdient。
	 */
	public function flush($names = []){
		return $this->execute('flush', $names);
	}

	/**
	 * list()
	 * 列表
	 * ------
	 * @return Array
	 * @author Verdient。
	 */
	public function list(){
		$scripts = [];
		$output = $this->normalizeOutput($this->runPM2Command('list'));
		$length = count($output);
		if($length > 5){
			$tiltes = [];
			foreach($output[1] as $element){
				$tiltes[] = strtolower(str_replace(' ', '', $element));
			}
			for($i = 3; $i < ($length - 2); $i++){
				$row = [];
				foreach($tiltes as $index => $tilte){
					if(!empty($tilte)){
						$row[$tilte] = $output[$i][$index];
					}
				}
				$scripts[] = $row;
			}
		}
		return $scripts;
	}
}