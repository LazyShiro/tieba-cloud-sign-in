# tieba-cloud-sign-in

#### 介绍

本项目可帮助你自动签到账号下所有贴吧

#### 使用说明

1. cp account.example account
2. cp config.example config
3. composer install
4. 完善account
5. 完善config
7. 创建一个定时任务每天执行 'index.php -aexec'

#### 注意

1. account内邮箱不填写则无提醒
2. 定时任务后接参数 -a是固定的 exec可以在config中entrance.key更改

#### 站外账号
[小白ゞ丶](https://tieba.baidu.com/home/main?un=%E5%B0%8F%E7%99%BD%E3%82%9E%E4%B8%B6)

#### 感谢（不分先后）

* [AutoCloudSign](https://github.com/XcantloadX/AutoCloudSign) 提供了初期设计思路
* [TiebaSignIn](https://github.com/lqbby/TiebaSignIn) 提供了sign算法