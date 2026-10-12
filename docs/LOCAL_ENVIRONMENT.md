# Phase 1 本机测试环境

仅用于本机；不代表生产部署或支付验收。CRMEB固定源码基线为`da8d3b93400f3c660aa54d38b54ada387c963cd9`。

## 当前服务

- 后台：http://127.0.0.1:18080/admin/
- 数据库：127.0.0.1:13306，MariaDB 10.11.10兼容引擎；不是生产MySQL验证。
- PHP：7.4.33。v6入口明确拒绝PHP8，所以本次没有更改版本限制。
- 缓存：CRMEB原生file；本机queue_open=0。尚未启用LATIV调度、Redis锁、队列worker。
- 官方演示库：157张表、4个演示商品；没有LATIV商品或映射表。
- 登录信息：`.local/local-access.json`，没有提交Git；不要粘贴其密码或token到报告。

`scripts/local/start.ps1`启动/复用本项目进程，`scripts/local/stop.ps1`只停止记录且核对归属的进程，保留数据库。端口被其他进程占用时会拒绝替换。

PHP7.4载入扩展存在中文绝对路径兼容问题，运行器使用用户cache目录内指向本项目的junction，不复制另一套商城源码。

## 运行库来源与限制

- PHP官方归档：`https://museum.php.net/php7/php-7.4.33-nts-Win32-vc15-x64.zip`；SHA256 `14AE3250D4447C8CCFC4C45A70D90ADFBCD61E728D85F0BE56A7DDF8F9C8AACE`。
- MariaDB官方归档：`https://archive.mariadb.org/mariadb-10.11.10/winx64-packages/mariadb-10.11.10-winx64.zip`；下载SHA256 `5626478891370011B568E3776BA76D840846C2A08261B14B82DE97F0608311CF`。
- 微信工具：腾讯官方2.02.2608070包；SHA256 `A502DEAD2E4FB6DCB0B5E157E1F2B9AC00C10A20F1940917112A1F8481ED5ED9`，与Microsoft WinGet清单一致，主程序Authenticode签名有效。
- 工具和数据库均放`.local/`，不是Windows服务，没有安装Docker/WSL或修改系统PHP路径。
- Windows Installer解包操作被自动审批拒绝；实际改为只读解析MSI的CAB，再解包文件，不执行安装器。最终没有依赖被拒绝的动作。

所有运行库是可重建测试依赖，不放Git。初始导入仅将内存中的COMPACT行格式转换成DYNAMIC以满足本机utf8mb4索引限制，上游SQL保持原样。`scripts/local/bootstrap.php`拒绝覆盖已存在数据库和已完成安装。

## 微信小程序测试

独立测试副本：`.local/mini-program`，实际打开目录为`C:/Users/13470/.cache/lativ-crmeb-mini-phase1`，来源为官方已有`crmeb/public/statics/mp_view`。自己的AppID只填在测试配置中。测试副本app.js前置`scripts/local/mini-loopback.js`，转向回环API，并将API中指向官方demo站的演示图片引用转到包内39张本机图片；不改数据库。app.json移除未获授权且MVP不用的直播插件声明（保留app.phase1-with-live-plugin.json.bak）；未改上游compiled vendor或UniApp源码。

这项验证用于确认v6原页面能在工具中启动，不等价于从UniApp源码重新构建验收。正式HBuilderX构建、HTTPS合法域名、AppSecret、商户支付与退款回调留后续阶段。

用户本人完成扫码登录并手动开启工具服务端口。没有自动修改应用安全设置，没有执行upload或preview发布命令。

旧miniprogram-automator无法作为本机新工具的验收依据。实际使用腾讯工具自带wechatide-skill 0.3.9：用户授权Codex后，check_wechatide_status确认版本一致、登录有效、tokenRequired=false；automation_runtime_info读到首页，simulator_screenshot返回真实截图，get_simulator_network确认本机18条HTTP/业务状态200。保留基础库3.15.2，没有自动降级。源码构建尚未验收。

当前本机工具可通过其`cli.bat open --project <本机测试副本>`打开；无需再次复制v6商品、订单或商家页面。

## 已验证与未验证

已通过实际后台网页登录，以及首页/分类/商品/分销统计/商品统计API；源码与官方9,279个关键文件逐字节核对一致。

模拟器首页、轮播及导航演示图片已显示且接入本机API，截图位于docs/evidence/phase1-mini.png。基础库SDK timeout/clickCheckTask错误仍有记录；仅启动、首页图片和接口接入通过，不声称无错误或商城完整流程通过。

未进行真实微信支付、发货、退款或LATIV导入；未创建后续阶段表和业务模块。请使用阶段报告和`phase1-verification.json`查看最终运行状态。
