# 本地环境重建与非Git资料

本说明用于交接，不在本次任务中执行安装、重新建库、重采集、升级依赖或开启后续阶段。原电脑已有环境时直接复用scripts/local/start.ps1，不能重跑bootstrap。新电脑步骤以下以Windows PowerShell、用户目录为英文且无空格为当前验证口径；Linux、其它路径条件尚未做运行验收。

## 1. 获取正确代码和数据

~~~text
git clone --branch codex/lativ-phase1 https://github.com/runy5231gunygn-glitch/lativ.git lativ-crmeb
cd lativ-crmeb
git status --short --branch
~~~

远程分支以最终push结果为准；已有main不一定含交接。若远程推送失败，原电脑的.local/handoff/lativ-crmeb-opencode.bundle可独立clone：

~~~text
git clone /path/to/lativ-crmeb-opencode.bundle lativ-crmeb
~~~

仓库已包含vendor/composer.lock、后台与小程序已有编译包，无需为启动演示商城执行composer update或npm update。

Git不包含以下资料：

| 资料 | 处理 |
|---|---|
| .local/runtime、mysql-data、两个ini、processes.json | 新机重建；不要复制旧PID或带旧绝对路径的配置 |
| crmeb/.env、.local/local-access.json、token | 凭据只做安全私有迁移；新演示库由bootstrap产生新凭据，不能提交 |
| crmeb/public/uploads/theme/20260303181550中的39张官方演示图片 | 从固定官方包恢复，见第3步；它们不是LATIV图 |
| .local/mini-program及用户cache测试工程 | 按第5步重建；源UniApp与官方编译包已在Git |
| 独立lativ-sync-service源码、state/、冻结证据 | 本仓库外另行保存/迁移，见第6步；不能靠商城clone恢复，也不能重新抓替代 |

如果需要原有CRMEB数据库内容，应停写后安全备份/恢复MySQL及上传文件，使用对应私有凭据，禁止用新演示初始化覆盖。当前库无LATIV商品，因此一般用独立新测试库即可。

## 2. 固定本地运行库

当前使用PHP7.4.33 NTS VC15 x64、MariaDB10.11.10便携包。PHP入口拒绝8.x；这只是兼容测试，不是生产版本建议。需要对应微软VC运行库；缺DLL时使用官方运行库，不拷贝来源不明的DLL。

在仓库根创建.local/downloads与.local/runtime。下载并核对SHA256：

| 文件/来源 | SHA256 |
|---|---|
| https://museum.php.net/php7/php-7.4.33-nts-Win32-vc15-x64.zip | 14AE3250D4447C8CCFC4C45A70D90ADFBCD61E728D85F0BE56A7DDF8F9C8AACE |
| https://archive.mariadb.org/mariadb-10.11.10/winx64-packages/mariadb-10.11.10-winx64.zip | 5626478891370011B568E3776BA76D840846C2A08261B14B82DE97F0608311CF |

~~~powershell
$taskRoot = (Get-Location).Path
$taskEncoding = [Text.UTF8Encoding]::new($false)
New-Item -ItemType Directory -Force -Path .local/downloads,.local/runtime | Out-Null
Invoke-WebRequest 'https://museum.php.net/php7/php-7.4.33-nts-Win32-vc15-x64.zip' -OutFile .local/downloads/php74.zip
Invoke-WebRequest 'https://archive.mariadb.org/mariadb-10.11.10/winx64-packages/mariadb-10.11.10-winx64.zip' -OutFile .local/downloads/mariadb.zip
Get-FileHash .local/downloads/php74.zip,.local/downloads/mariadb.zip -Algorithm SHA256
# 人工/脚本比对上表，只有完全一致才解压；目标须为新的空目录。
Expand-Archive .local/downloads/php74.zip .local/runtime/php74
Expand-Archive .local/downloads/mariadb.zip .local/runtime
~~~

最终目录必须是.local/runtime/php74/php.exe和.local/runtime/mariadb-10.11.10-winx64/bin/mysqld.exe。本次不改变锁定版本，不执行Windows服务安装。

建立运行junction（现存指向其它checkout时停止，不删除/覆盖它）：

~~~powershell
$taskLink = Join-Path $env:USERPROFILE '.cache/lativ-crmeb-phase1'
New-Item -ItemType Directory -Force -Path (Split-Path $taskLink) | Out-Null
if (Test-Path -LiteralPath $taskLink) {
    if ((Get-Item -LiteralPath $taskLink).Target -ne $taskRoot) {
        throw 'Existing junction belongs to another checkout; keep that environment or migrate it explicitly.'
    }
} else {
    New-Item -ItemType Junction -Path $taskLink -Target $taskRoot | Out-Null
}
$taskLinkIni = $taskLink.Replace([char]92,[char]47)
$taskPhp = Join-Path $taskLink '.local/runtime/php74/php.exe'
~~~

用UTF8无BOM生成php.ini（在同一PowerShell会话保留上面的变量）：

~~~powershell
$taskPhpIni = @"
[PHP]
extension_dir="$taskLinkIni/.local/runtime/php74/ext"
extension=curl
extension=mbstring
extension=openssl
extension=pdo_mysql
extension=mysqli
extension=gd2
extension=fileinfo
extension=sockets
date.timezone=Asia/Shanghai
memory_limit=512M
max_execution_time=120
upload_max_filesize=32M
post_max_size=40M
display_errors=Off
log_errors=On
"@
[IO.File]::WriteAllText((Join-Path $taskRoot '.local/runtime/php74/php.ini'), $taskPhpIni, $taskEncoding)
& $taskPhp -v
& $taskPhp -m
~~~

检查curl/mbstring/bcmath/openssl/mysqli/pdo_mysql/gd/fileinfo/zip/SimpleXML等；bcmath、zip等在此发行包可能内置，不要重复加载不存在的扩展。

生成本机数据库配置：

~~~powershell
$taskDbIni = @"
[mysqld]
basedir=$taskLinkIni/.local/runtime/mariadb-10.11.10-winx64
datadir=$taskLinkIni/.local/mysql-data
port=13306
bind-address=127.0.0.1
character-set-server=utf8mb4
collation-server=utf8mb4_general_ci
sql-mode=NO_ENGINE_SUBSTITUTION
log-error=$taskLinkIni/.local/mysql-error.log
[client]
port=13306
"@
[IO.File]::WriteAllText((Join-Path $taskRoot '.local/mysql-local.ini'), $taskDbIni, $taskEncoding)
~~~

仅全新数据目录可初始化。MariaDB安装工具无需--service，不允许远程root；bootstrap只适合此新库的初始空root密码状态：

~~~powershell
if (Test-Path -LiteralPath .local/mysql-data) {
    throw 'Database directory already exists; restore/reuse it, never reinitialize.'
}
& "$taskLink/.local/runtime/mariadb-10.11.10-winx64/bin/mariadb-install-db.exe" "--datadir=$taskLink/.local/mysql-data" --port=13306
& ./scripts/local/start.ps1
# 确认13306已监听且mysql-error.log无启动错误后再执行下一条。
& $taskPhp ./scripts/local/bootstrap.php
~~~

bootstrap只导原生演示SQL，生成随机本地DB/后台凭据与忽略的.env/install.lock，拒绝现存数据库或已完成安装，不读取LATIV库。中途失败应查日志和已有库状态，不能删库“重试”。

访问http://127.0.0.1:18080/admin/。使用本机私有文件中的后台账号，不把凭据输出到日志或文档。start.ps1同时维护本项目进程记录；stop.ps1只停核验归属的DB/PHP，保留数据。

## 3. 恢复官方演示资源

上传目录被原.gitignore忽略，所以Git基线不含39张主题演示图片。固定源码包：

https://codeload.github.com/crmeb/CRMEB/zip/da8d3b93400f3c660aa54d38b54ada387c963cd9

SHA256：A80EC88577DF482BE52085E57ACE95704FD2888B043501F028FCF21BB0151BF2。

下载到.local/downloads，核对hash后解压到新的.local/upstream-demo目录；只将包里的crmeb/public/uploads/theme/20260303181550复制到本机对应演示目录。不复制app/vendor/template覆盖当前Git代码，不覆盖已有真实上传文件，不重新导入数据库。

本机该目录应39个文件；这只恢复官方主题演示资产，不涉及LATIV下载或商品导入。若资源未恢复，测试API正常也可能图片空白。

## 4. 基础验证

~~~powershell
& $taskPhp -l scripts/local/bootstrap.php
& $taskPhp -l scripts/local/router.php
node --check scripts/local/mini-loopback.js
git diff --check
Invoke-RestMethod http://127.0.0.1:18080/api/category
Invoke-RestMethod 'http://127.0.0.1:18080/api/products?page=1&limit=2'
~~~

业务status应200；本机只有4个原生演示商品。完整构建未作为交接要求执行。以后需要后台构建时，在template/admin使用符合package.engines的Node/npm与已有lock执行npm ci、npm run dev/build；不运行update。UniApp通过HBuilderX，npm package没有对应构建scripts。README中的npm run lint不在实际scripts里；eslint带自动修改参数，不在冻结任务执行。

## 5. 微信测试工程

安装腾讯官方微信开发者工具，原电脑版本2.02.2608070、基础库3.15.2。用户自行扫码登录及按需要开启服务端口/客户端授权；不复制原电脑登录缓存。普通安装版可直接GUI导入，不依赖原电脑解包版bat/cmd包装。

在新的$env:USERPROFILE/.cache/lativ-crmeb-mini-phase1目录复制crmeb/public/statics/mp_view。目标已存在时先核对归属，不覆盖既有开发工程。

只修改测试副本：

1. project.config.json中的appid填自己的小程序AppID（当前项目公开ID见HANDOFF），compileType保持miniprogram；必要时private配置统一projectname。
2. 在app.json的users分包中去掉live-player-plugin声明（provider wx2b03c6e691cd7370），保留原文件备份。MVP不做直播；无需申请插件来运行演示。
3. 将scripts/local/mini-loopback.js内容前置到原始app.js，后接官方app.js的三个require，避免重复前置。这只将测试请求和演示图片转到127.0.0.1:18080。
4. GUI导入该目录并普通编译；看真实页面和请求响应，不把CLI成功当页面通过。原电脑截图/证据见docs/evidence/phase1-mini.png和phase1-mini-runtime.json。

此回环只能供本机模拟器；手机中的127.0.0.1是手机自身，不能用于真机/生产。正式构建必须在template/uni-app/config/app.js、manifest.json及pages.json做自己的环境/插件配置，经对应阶段验收。基础库内部timeout/clickCheckTask日志仍是已知限制，不删除商城代码来压日志。

## 6. 独立采集器迁移

原电脑C:/Users/13470/Desktop/lativ-sync-service不是商城Git子模块，也没有自己的Git目录。必须另外保存完整源码、schema、outputs中运行/验证依赖、requirements、VERSION、AGENTS及release证据，同时保留state/。不要仅复制sync.py或两个SQLite文件。

同一电脑换Agent可以直接只读使用原目录，不需搬数据。新电脑迁移按原LINUX_DEPLOY.md及AGENTS：先经用户批准暂停其API/图片任务，等待退出/关闭连接、处理WAL；用既有SQLite backup方法保存一致两个库，还要保存同一时点的state/runs与state/media内容。绝不单独复制活跃.db或清checkpoint。ops.py backup带保留期清理，不在本次交接中擅自运行它。

在新机器准备Python3.11+虚拟环境并按requirements.txt恢复固定依赖，环境变量/私有.env指定迁移后路径；不复制旧Windows虚拟环境。先读WECHAT_INTEGRATION.md和schema/API_BOUNDARY.md，再运行离线plan/health/测试，不能默认启动采集或timer。

~~~text
python -m venv .venv
.venv的python -m pip install -r requirements.txt
.venv的python -B sync.py --plan
.venv的python -B ops.py health
.venv的python -B scripts/check_portability.py
.venv的python -B scripts/test_all.py
~~~

这里的“ .venv的python ”须替换为Windows .venv/Scripts/python.exe或Linux .venv/bin/python。health对约7.56GB库做quick_check可能需数分钟；完整回归写release测试报告，不能当只读操作。保留原发行SHA清单与新验证结果，勿用覆盖旧清单来掩盖差异。

## 7. 边界

这些是交接后的环境恢复方法，未在新电脑实际执行验证。不得把新环境启动、图片演示或native API通过当LATIV链路完成；继续严格遵守原Phase门禁。非Windows使用官方隔离安装与数据库配置，HTTP根为crmeb/public；当前PowerShell/便携初始化脚本不适用，不声称Linux已验证。
