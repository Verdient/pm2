<?php
namespace Verdient\pm2;

use chorus\InvalidConfigException;
use chorus\InvalidParamException;
use chorus\UnsatisfiedExcepiton;

/**
 * PM2
 * @author Verdient。
 */
class PM2 extends \chorus\BaseObject
{
	/**
	 * @var string 检查运行环境事件
	 * @author Verdient。
	 */
	const EVENT_CHECK_ENVIRONMENT = 'checkEnvironment';

	/**
	 * @var string 启动进程事件
	 * @author Verdient。
	 */
	const EVENT_START = 'start';

	/**
	 * @var string 停止进程事件
	 * @author Verdient。
	 */
	const EVENT_STOP = 'stop';

	/**
	 * @var string 重启进程事件
	 * @author Verdient。
	 */
	const EVENT_RESTART = 'restart';

	/**
	 * @var string 重置进程事件
	 * @author Verdient。
	 */
	const EVENT_RESET = 'reset';

	/**
	 * @var string 删除进程事件
	 * @author Verdient。
	 */
	const EVENT_DELETE = 'delete';

	/**
	 * @var bool 是否跳过环境检查
	 * @author Verdient。
	 */
	public $skipEnvironmentCheck = false;

	/**
	 * @var array 脚本配置
	 * @author Verdient。
	 */
	public $scripts = [];

	/**
	 * @var bool 是否允许合并
	 * @author Verdient。
	 */
	public $enableMerge = true;

	/**
	 * @var array 支持合并的命令
	 * @author Verdient。
	 */
	protected $_supportMergeCommands = [
		'start', 'stop', 'restart', 'delete'
	];

	/**
	 * @var array 格式化后的脚本配置
	 * @author Verdient。
	 */
	protected $_normalizedScripts = false;

	/**
	 * @var bool 环境是否正常
	 * @author Verdient。
	 */
	protected $_environmentOK = false;

	/**
	 * 是否使用合并
	 * @param string $command 命令
	 * @return bool
	 * @author Verdient。
	 */
	protected function useMerge($command){
		if($this->enableMerge === true){
			return in_array($command, $this->_supportMergeCommands);
		}
		return false;
	}

	/**
	 * 获取请求的脚本
	 * @param array $args 请求的参数
	 * @param bool $exist 是否只包含已存在的
	 * @param bool $running 是否只包含正在运行的
	 * @return array
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
	 * 执行命令
	 * @param string $command 命令
	 * @return array
	 * @author Verdient。
	 */
	protected function runCommand($command){
		exec($command, $output);
		return $output;
	}

	/**
	 * 运行PM2命令
	 * @param string $command 命令
	 * @return array
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
		return $this->runCommand($command);
	}

	/**
	 * 格式化输出
	 * @param array $output 输出
	 * @return array
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
	 * 获取格式化后的脚本配置
	 * @return array
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
	 * 获取已存在的进程
	 * @return array
	 * @author Verdient。
	 */
	public function getExistScrpits(){
		$scripts = [];
		foreach($this->list() as $row){
			$scripts[] = isset($row['appname']) ? $row['appname'] : $row['name'];
		}
		return $scripts;
	}

	/**
	 * 获取正在运行的进程
	 * @return array
	 * @author Verdient。
	 */
	protected function getRuningScrpits(){
		$scripts = [];
		foreach($this->list() as $row){
			if($row['status'] === 'online'){
				$scripts[] = isset($row['appname']) ? $row['appname'] : $row['name'];
			}
		}
		return $scripts;
	}

	/**
	 * 检查Node.js
	 * @return bool
	 * @author Verdient。
	 */
	protected function checkNodeJs(){
		$result = $this->runCommand('node -v');
		return preg_match('/^v\d+\.\d+\.\d+$/', $result[0]) !== 0;
	}

	/**
	 * 检查PM2
	 * @return bool
	 * @author Verdient。
	 */
	protected function checkPM2(){
		$result = $this->runCommand('pm2 -v');
		return preg_match('/^\d+\.\d+\.\d+$/', $result[0]) !== 0;
	}

	/**
	 * 检查环境
	 * @return bool
	 * @author Verdient。
	 */
	protected function checkEnvironment(){
		if($this->_environmentOK === false){
			if($this->skipEnvironmentCheck === false){
				$this->trigger(static::EVENT_CHECK_ENVIRONMENT);
				if(!$this->checkPM2()){
					if(!$this->checkNodeJs()){
						throw new UnsatisfiedExcepiton('请先安装 node.js');
					}
					throw new UnsatisfiedExcepiton('请先安装 PM2');
				}
			}
			$this->_environmentOK = true;
		}
		return $this->_environmentOK;
	}

	/**
	 * 获取事件名称
	 * @param string $operation 操作
	 * @return string
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
	 * 执行
	 * @param string $operation 操作
	 * @param array $names 名称
	 * @param array $args 参数
	 * @return bool
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
	 * 启动
	 * @param array $names 名称
	 * @param array $args 参数
	 * @return bool
	 * @author Verdient。
	 */
	public function start($names = [], $args = []){
		return $this->execute('start', $names, $args);
	}

	/**
	 * 停止
	 * @param array $names 名称
	 * @return bool
	 * @author Verdient。
	 */
	public function stop($names = []){
		return $this->execute('stop', $names);
	}

	/**
	 * 重启
	 * @param array $names 名称
	 * @return bool
	 * @author Verdient。
	 */
	public function restart($names = []){
		return $this->execute('restart', $names);
	}

	/**
	 * 重置
	 * @param array $names 名称
	 * @return bool
	 * @author Verdient。
	 */
	public function reset($names = []){
		return $this->execute('reset', $names);
	}

	/**
	 * 删除
	 * @param array $names 名称
	 * @return bool
	 * @author Verdient。
	 */
	public function delete($names = []){
		return $this->execute('delete', $names);
	}

	/**
	 * 刷新日志
	 * @param array $names 名称
	 * @return bool
	 * @author Verdient。
	 */
	public function flush($names = []){
		return $this->execute('flush', $names);
	}

	/**
	 * 列表
	 * @return array
	 * @author Verdient。
	 */
	public function list(){
		$scripts = [];
		$output = $this->normalizeOutput($this->runPM2Command('list'));
		$length = count($output);
		if($length > 4){
			$startAt = 0;
			$listStartAt = 0;
			$endAt = 0;
			foreach($output as $line => $value){
				$value = mb_substr($value[0], 0, 1);
				if($value === '┌'){
					$startAt = $line;
				}else if($value === '├'){
					$listStartAt = $line;
				}else if($value === '└'){
					$endAt = $line;
				}
			}
			if($endAt > $listStartAt && $listStartAt > $startAt && $listStartAt > 0){
				$tiltes = [];
				foreach($output[$startAt + 1] as $element){
					$tiltes[] = strtolower(str_replace(' ', '', $element));
				}
				for($i = $listStartAt + 1; $i < $endAt; $i++){
					$row = [];
					foreach($tiltes as $index => $tilte){
						if(!empty($tilte)){
							$row[$tilte] = $output[$i][$index];
						}
					}
					$scripts[] = $row;
				}
			}
		}
		return $scripts;
	}
}