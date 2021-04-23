<?php
require 'vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use PHPMailer\PHPMailer\PHPMailer;

/**
 * 判断是否命令行模式
 *
 * @return bool
 */
function is_cli(): bool
{
	return (bool) preg_match('/cli/i', php_sapi_name());
}

/**
 * 浏览器友好的变量输出
 *
 * @param ...$vars
 */
function dump(...$vars)
{
	ob_start();
	var_dump(...$vars);

	$output = ob_get_clean();
	$output = preg_replace('/]=>\n(\s+)/m', '] => ', $output);

	if (PHP_SAPI == 'cli') {
		$output = PHP_EOL . $output . PHP_EOL;
	} else {
		if (!extension_loaded('xdebug')) {
			$output = htmlspecialchars($output, ENT_SUBSTITUTE);
		}
		$output = '<pre>' . $output . '</pre>';
	}

	echo $output;
}

/**
 * 获取配置项
 *
 * @param string $name
 *
 * @return mixed
 */
function config($name = '')
{
	$config = json_decode(file_get_contents('config'), true);
	if (!empty($name)) {
		$configHierarchy = explode('.', $name);
		foreach ($configHierarchy as $value) {
			$config = $config[$value];
		}
	}
	return $config;
}

/**
 * 单用户签到所有贴吧
 *
 * @param $account
 */
function startSign($account)
{
	$name  = $account['name'];
	$email = $account['email'];

	$successLogName = './runtime/' . $email . '.log';
	$failLogName    = './runtime/' . $email . '-error.log';

	//初始化日志文件
	file_put_contents($failLogName, "");
	file_put_contents($successLogName, "$name--- 开始获取用户信息" . PHP_EOL);

	//获取用户信息
	$userInfo = getUserInfo($account);

	if ($userInfo) {
		//贴吧列表
		$bars = $userInfo['like_forum'];
		//用户tbs
		$tbs = $userInfo['itb_tbs'];
		file_put_contents($successLogName, "$name--- 获取用户信息成功" . PHP_EOL, FILE_APPEND | LOCK_EX);
	} else {
		file_put_contents($successLogName, "$name--- 获取用户信息失败" . PHP_EOL, FILE_APPEND | LOCK_EX);
		exit;
	}

	//签到成功个数
	$signed = 0;

	//循环签到所有贴吧
	foreach ($bars as $bar) {
		startSign:
		//签到
		$result = sign($bar, $account, $tbs);
		//状态码
		$code = (int) $result['error_code'];

		if ($code !== 0) {
			var_dump("$name--- {$bar['forum_name']} 吧 {$result['error_msg']}");
			//之前已经签到过了
			if ($code === 160002) {
				continue;
			}
			file_put_contents($failLogName, "$name--- {$bar['forum_name']} 吧 {$result['error_msg']}" . PHP_EOL, FILE_APPEND | LOCK_EX);
		} else {
			$signed++;
			var_dump("$name--- {$bar['forum_name']} 吧 签到成功 今日本吧第{$result['user_info']['user_sign_rank']}个签到");
			file_put_contents($successLogName, "$name--- {$bar['forum_name']} 吧 签到成功 今日本吧第{$result['user_info']['user_sign_rank']}个签到" . PHP_EOL, FILE_APPEND | LOCK_EX);
		}

		//签到频率1秒
		sleep(1);
	}

	var_dump("$name--- 已成功签到：" . $signed . "/" . count($bars) . " 个贴吧。");
	file_put_contents($successLogName, "$name--- 已成功签到：" . $signed . "/" . count($bars) . " 个贴吧。" . PHP_EOL, FILE_APPEND | LOCK_EX);

	notify($email, $failLogName);
}

/**
 * 获取所有贴吧
 *
 * @param $account
 *
 * @return array
 */
function getUserInfo($account): array
{
	$guzzleClient = new Client();

	try {
		$response = $guzzleClient->request('GET', 'https://tieba.baidu.com/mo/q/newmoindex', [
			'headers' => [
				'cookie' => "BDUSS={$account['bduss']}"
			]
		]);
	} catch (GuzzleException $e) {
		return [];
	}

	$response = json_decode($response->getBody()->getContents(), true);
	if (!is_array($response) || $response['no'] !== 0) {
		return [];
	}

	return $response['data'];
}

/**
 * 单个用户签到单个贴吧
 *
 * @param $bar
 * @param $account
 * @param $tbs
 *
 * @return array|mixed
 */
function sign($bar, $account, $tbs)
{
	$guzzleClient = new Client();
	try {
		$response = $guzzleClient->request('POST', 'http://c.tieba.baidu.com/c/c/forum/sign', [
			'headers' => [
				'cookie' => "BDUSS={$account['bduss']}"
			],
			'body'    => "kw={$bar['forum_name']}&tbs=$tbs&sign=" . md5("kw={$bar['forum_name']}tbs={$tbs}tiebaclient!!!"),
		]);
	} catch (GuzzleException $e) {
		return [];
	}

	return json_decode($response->getBody()->getContents(), true);
}

/**
 * 邮件通知
 *
 * @param $email
 * @param $failLogName
 */
function notify($email, $failLogName)
{
	if (filesize($failLogName) !== 0) {
		$config = config('email');

		$mail = new PHPMailer();
		// 是否启用smtp的debug进行调试 开发环境建议开启 生产环境注释掉即可 默认关闭debug调试模式
		//$mail->SMTPDebug = 1;
		// 使用smtp鉴权方式发送邮件
		$mail->isSMTP();
		// smtp需要鉴权 这个必须是true
		$mail->SMTPAuth = true;
		// 链接qq域名邮箱的服务器地址
		$mail->Host = 'smtp.qq.com';
		// 设置使用ssl加密方式登录鉴权
		$mail->SMTPSecure = 'ssl';
		// 设置ssl连接smtp服务器的远程服务器端口号
		$mail->Port = 465;
		// 设置发送的邮件的编码
		$mail->CharSet = 'UTF-8';
		// 设置发件人昵称 显示在收件人邮件的发件人邮箱地址前的发件人姓名
		$mail->FromName = $config['sender_name'];
		// smtp登录的账号 QQ邮箱即可
		$mail->Username = $config['email_address'];
		// smtp登录的密码 第一步中qq邮箱生成的授权码
		$mail->Password = $config['auth_code'];
		// 设置发件人邮箱地址 同登录账号
		$mail->From = $config['email_address'];
		// 邮件正文是否为html编码 注意此处是一个方法
		$mail->isHTML(true);
		// 设置收件人邮箱地址
		$mail->addAddress($email);
		// 添加多个收件人 则多次调用方法即可
		//$mail->addAddress('18365989898@163.com');
		// 添加该邮件的主题
		$mail->Subject = $config['email_title'];
		// 添加邮件正文
		$mail->Body = str_replace(PHP_EOL, '<br />', file_get_contents($failLogName));
		// 为该邮件添加附件
		//$mail->addAttachment('./example.pdf');
		// 发送邮件 返回状态
		$mail->send();
	}
}