# PM2
通过PHP控制PM2进程管理器

## 安装
```
composer require verdient/pm2
```
## 配置脚本
```php
use Verdient\pm2\PM2;

/**
 * 是否允许合并操作
 * 对于支持合并的操作，如果设置为true，则会对进程进行批量操作
 * 否则会依次对各个进程进行操作
 * 可选参数，默认为true
 */
$enableMerge = true;

/**
 * 是否跳过环境检查
 * 对于确定安装了PM2的系统，将该配置项设置为true，跳过环境检查
 * 否则每次运行都会检查PM2的安装状态
 * 可选参数，默认为false
 */
$skipEnvironmentCheck = false;

/**
 * 脚本配置，格式为[$name => $config]的数组
 * $config 可以为字符串也可以为数组
 * 当$config为数组时，等同于仅配置了数组的$config['script']
 * 当$config为数组时：
 *     script 运行的脚本，与cwd一起组成完成的脚本路径
 *     cwd 脚本运行的文件夹
 *     args 需要传递给脚本的参数，默认为空数组
 *     interpreter 解释脚本的程序，默认为php
 *     interpreter_args 需要传递给解释程序（interpreter）的参数
 * 程序以$name作为唯一标识，所以$name不允许重复
 */
$scripts = [
	'test' => [
		'script' => 'index.php',
		'cwd' => __DIR__,
		'args' => [],
		'interpreter' => 'php',
		'interpreter_args' => []
	],
	'test2' => __DIR__ . DIRECTORY_SEPARATOR . 'index.php',
];

$pm2 = new PM2([
		'scripts ' => $scripts,
		'skipEnvironmentCheck' => $skipEnvironmentCheck,
		'enableMerge' => $enableMerge
	]
]);

```
## 启动脚本
```php
/**
 * 要操作的脚本名称
 * 默认为空数组，既操作所有配置的脚本
 * stop restart delete reset方法$names参数含义与此相同
 */
$names = [];
/**
 * 附加的参数
 * 具体参数见 https://pm2.keymetrics.io/docs/usage/startup/
 */
$args = [];
$pm2->start($names, $args);
```

## 停止脚本
```php
$pm2->stop($names = []);
```

## 重启脚本
```php
$pm2->restart($names = []);
```

## 重置脚本
```php
$pm2->reset($names = []);
```

## 删除脚本
```php
$pm2->delete($names = []);
```
## 事件挂载
如果需要对操作的过程进行观察监控，可以通过挂载事件来实现
```php
$pm2->on(PM2::EVENT_START, function($started, $count, $names){
	echo '已启动' . $started . '个进程，共需启动' . $count . '个进程，名字分别为：' . implode(', ', $names) . PHP_EOL;
});

$pm2->on(PM2::EVENT_STOP, function($started, $count, $names){
	echo '已停止' . $started . '个进程，共需停止' . $count . '个进程，名字分别为：' . implode(', ', $names) . PHP_EOL;
});

$pm2->on(PM2::EVENT_RESTART, function($started, $count, $names){
	echo '已重启' . $started . '个进程，共需重启' . $count . '个进程，名字分别为：' . implode(', ', $names) . PHP_EOL;
});

$pm2->on(PM2::EVENT_RESET, function($started, $count, $names){
	echo '已重置' . $started . '个进程，共需重置' . $count . '个进程，名字分别为：' . implode(', ', $names) . PHP_EOL;
});

$pm2->on(PM2::EVENT_DELETE, function($started, $count, $names){
	echo '已删除' . $started . '个进程，共需删除' . $count . '个进程，名字分别为：' . implode(', ', $names) . PHP_EOL;
});
```