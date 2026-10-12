# Intel Mac 接手入口与报告目录

2026-10-12（Asia/Shanghai）。用户确认Intel Mac。本文件只整理迁移与环境恢复，未在Mac上执行安装/构建/启动，未进入Phase 2。

## 先知道这四件事

1. GitHub分支为codex/lativ-phase1；本次整理前已只读确认远程与本地e2f5524e一致，跟踪关系是origin/codex/lativ-phase1。使用该分支，不从已有main重新开始。
2. Phase 1基础分析与Windows演示运行已完成；Phase 2–6未开始。LATIV映射、商品导入、库存/价格同步及自动调度尚未实现。
3. GitHub有源码、规则、报告、截图及本地辅助脚本；没有采集器完整目录、SQLite、Windows运行库、私有配置和演示上传图片。
4. Mac第一件事是恢复并验收Phase 1环境及源数据，不重新规划商城；用户未说“开始 Phase 2”前不开发LATIV功能。

## 1. 克隆并核对分支

~~~bash
mkdir -p "$HOME/Projects"
cd "$HOME/Projects"
git clone --branch codex/lativ-phase1 https://github.com/runy5231gunygn-glitch/lativ.git lativ-crmeb
cd lativ-crmeb
git status --short --branch
git branch -vv
git log -3 --oneline
git rev-parse HEAD
git rev-parse '@{upstream}'
~~~

最后两个提交应一致，工作区应干净。此文档更新会产生新的文档提交，所以不要固定checkout旧e2f5524e。已有clone时先检查未提交修改，再使用git pull --ff-only；不reset、不强推、不覆盖main。

Windows的GitHub认证已由用户处理并成功上传。Mac使用自己的HTTPS认证/钥匙串；不复制Windows凭据缓存、token或开发工具登录目录。

## 2. 报告阅读顺序

| 文件 | 用途 |
|---|---|
| [本文件](MAC_HANDOFF.md) | Intel Mac接手操作、缺失资料、环境验证顺序 |
| [AGENTS.md](../AGENTS.md) | 长期规则、原生复用边界、六Phase门禁 |
| [HANDOFF.md](../HANDOFF.md) | 实际进度、已知问题、数据库/配置、下一步任务 |
| [PHASE1_REPORT.md](PHASE1_REPORT.md) | 商品/SKU/库存/分类/图片/订单/调度源码分析、最小改造文件及表方案 |
| [handoff-verification.json](handoff-verification.json) | Windows交接实况与基础检查，另含Mac移交前Git核验；不是Mac运行通过记录 |
| [phase1-verification.json](phase1-verification.json) | 历史Phase 1验收记录 |
| [后台截图](evidence/phase1-admin.png)、[小程序截图](evidence/phase1-mini.png) | Windows原生演示截图，不是LATIV商品导入成果 |
| [LOCAL_ENVIRONMENT.md](LOCAL_ENVIRONMENT.md)、[LOCAL_REBUILD.md](LOCAL_REBUILD.md) | Windows原环境与重建参考；其中.exe、junction和.ps1步骤不适用于Mac |

当前采集器快照：generation=10、2,821商品/2,792活动、29,152 SKU/活动商品29,126、events=5,505。原始分类树5/38/334与旧报告333口径差异尚未解释。94,442图片队列全部BASELINE_ONLY，当前运营图片没有下载文件或上传CRMEB成果。数值是2026-10-12的Windows只读核验，Mac搬入后应再核对。

## 3. Git之外必须单独搬的资料

| 资料 | Windows来源 | Mac处理 |
|---|---|---|
| 完整采集器源码与冻结证据 | C:/Users/13470/Desktop/lativ-sync-service，包括schema、outputs运行/测试依赖、scripts、release、requirements、VERSION、AGENTS | 例如~/Projects/lativ-sync-service，保持目录层级；该工程不在商城Git里 |
| 已提交商品/事件库、队列与检查点 | 上述工程state/data/platform2.db、state/media/queue.db、state/runs，以及存在的其它state内容 | 同一时点整体迁移，保留generation/events/checkpoint；不能重新采集替代 |
| CRMEB旧测试数据（若需保留） | 当前独立crmeb_phase1数据库 | 用安全逻辑SQL备份/恢复，不拷贝Windows mysql-data物理目录到Mac |
| 官方演示图片 | crmeb/public/uploads/theme/20260303181550，39文件 | 单独复制，或按LOCAL_REBUILD第3步从固定官方ZIP恢复；不要覆盖已有上传文件 |
| 私有配置/后台凭据 | crmeb/.env、.local/local-access.json等 | 经安全私人渠道保存，按Mac数据库地址和自己的账号重新配置；不要上传GitHub |

本次未新生成7.56GB采集数据迁移包或SQL备份，也未把它们上传GitHub。只有clone时源数据不会自动出现；Mac Agent应把“资料已到位”与“代码已clone”分开检查。

迁移采集器前依其AGENTS/LINUX_DEPLOY关闭API和图片进程、确认连接退出与WAL处理，用一致快照保留两库及同一时点state其它文件。当前Windows核验时原定时任务Disabled、没有匹配采集进程，但真正复制前仍需再次核对。不要复制活跃.db、清空state或重置discover/checkpoint。ops.py backup含保留期清理，先阅读现有逻辑，不在整理报告时擅自运行。

不能搬的运行文件：Windows php.exe/mysqld.exe/.dll、旧php.ini/mysql-local.ini、processes.json、用户cache junction、Windows虚拟环境、浏览器或微信开发工具登录缓存。

## 4. Mac先检查环境，不能假设已可运行

~~~bash
uname -m
sw_vers
git --version
command -v php
command -v python3
command -v node
command -v docker
~~~

Intel通常报告x86_64；实际macOS版本与Docker安装情况尚未提供。Docker官方有Intel安装入口及系统支持范围，请按实际Mac版本核对：[Docker Desktop on Mac](https://docs.docker.com/desktop/setup/install/mac-install/)。

服务端需要兼容PHP7.4；源码入口拒绝PHP8，不能直接安装当前默认PHP并修改版本判断。PHP7.4已结束官方支持，仅作为既有项目本地兼容测试，不作生产承诺：[PHP EOL](https://www.php.net/eol.php)。本次不升级项目依赖。

### 已有本机PHP7.4与数据库时

先确认PHP版本和扩展：

~~~bash
php -v
php -m
~~~

检查curl、mbstring、bcmath、openssl、mysqli/pdo_mysql、gd、fileinfo、zip、SimpleXML等。数据库按原生MySQL或兼容MariaDB配置；Windows实际验证的是MariaDB10.11.10，不等于Mac已验证。

先在独立测试库恢复逻辑SQL，或走CRMEB原生安装流程；修改忽略的crmeb/.env对应[DATABASE] HOSTNAME/HOSTPORT/USERNAME/PASSWORD/DATABASE/PREFIX/CHARSET及[CACHE] DRIVER=file，数据库使用自己的Mac地址/凭据。检查原生site_url为本机测试地址、queue_open=0。不要把Windows的空root初始化假设套到Mac，不直接运行scripts/local/bootstrap.php。

若原生演示SQL导入遇到COMPACT索引限制，沿Windows分析仅处理自己的临时导入副本/测试库行格式；不改上游SQL，不删除现存库。已有库恢复与全新演示安装二选一，不混用覆盖。

环境和数据库准备完成后，从仓库根启动已有开发路由：

~~~bash
cd crmeb
php -S 127.0.0.1:18080 -t public ../scripts/local/router.php
~~~

此命令使用已经提交的router.php，未新写Mac商城服务；PHP内置服务用于开发，本地停止用Ctrl+C：[PHP内置服务器说明](https://www.php.net/features.commandline.webserver)。

### 使用Docker恢复旧PHP时

优先读已有help/docker/docker-compose.yml及nginx/vhost.conf，不复制另一套商城、不使用预装另一份源码的镜像代替当前checkout。现有Compose挂载../../crmeb到/var/www，PHP镜像为ccr.ccs.tencentyun.com/crmebky_php/php:v7.4。

仅检查配置和运行库：

~~~bash
docker version
docker compose version
docker compose -f help/docker/docker-compose.yml config --quiet
docker run --rm --platform linux/amd64 --entrypoint php ccr.ccs.tencentyun.com/crmebky_php/php:v7.4 -v
docker run --rm --platform linux/amd64 --entrypoint php ccr.ccs.tencentyun.com/crmebky_php/php:v7.4 -m
~~~

config --quiet只校验配置、不启动服务：[Docker Compose config](https://docs.docker.com/reference/cli/docker/compose/config/)。这两条run覆盖镜像默认入口，只用于PHP检查；镜像拉取/扩展/Intel Mac运行尚未实际验证，失败时记录原错误，不自动换PHP8或升级源码。

原Compose不能未经核对直接作为Mac安全启动配置：它有示例密码、对外端口、固定容器名及192.168.10.0/24网络，原服务URL为8011；全新环境应由Mac Agent在忽略的本地配置中确认自己的随机凭据、数据目录、回环端口、挂载和网络，保留原源码/配置。容器里的数据库地址用对应服务名，不把宿主机127.0.0.1当另一个容器。

禁止运行help/docker/run.sh的install/start/delete作为无损接手：install/delete包含删库/删runtime，start也调用全局网络清理。不要运行down -v、rm -rf数据目录、network prune来“修复”。原脚本此次保持不改。

此文档不声称已完成可启动的Mac Docker配置；接手者第一阶段在Mac核验并恢复现有能力即可，不开LATIV任务、队列或复杂新环境框架。

## 5. 微信小程序和前端

在Mac安装适用的微信开发者工具，由本人登录。测试副本使用.local/mini-program-mac，复制Git里的crmeb/public/statics/mp_view；AppID采用HANDOFF中的自己公开ID，去掉测试副本的直播插件声明，前置scripts/local/mini-loopback.js。这些都是已有Windows验收做法，不改上游UniApp/vendor。

API和演示图片测试地址需与Mac服务一致。沿18080启动可直接复用loopback；若Docker用8011，只调整测试副本地址或本地端口映射，不把测试地址改进正式业务代码。恢复39张演示图片后再判断图片显示；127.0.0.1仅供Mac模拟器，不用于手机真机。

正式源码构建仍用template/uni-app及HBuilderX，尚未验收；package.json没有npm小程序构建scripts。后台已有public/admin可先用，无需为接手立即重建。以后构建后台按package-lock和Node engines >14且<23选择兼容环境，使用npm ci，不用npm update；Windows当前Node24不符合该构建范围。

## 6. 采集器到Mac后只做离线核验

源码/state已经完整到位后，在其独立目录使用新Python3.11+虚拟环境，不搬Windows虚拟环境。按requirements.txt恢复固定依赖，私有.env路径用Mac/项目相对路径：

~~~bash
cd "$HOME/Projects/lativ-sync-service"
python3 -m venv .venv
.venv/bin/python -m pip install -r requirements.txt
.venv/bin/python -B sync.py --plan
.venv/bin/python -B ops.py health
.venv/bin/python -B scripts/check_portability.py
~~~

这些是Mac待执行的验证，不是本次通过记录。health读取两库quick_check可能需数分钟；静态portability不会证明Mac实际全链路运行。完整回归为.venv/bin/python -B scripts/test_all.py，原424通过是Windows历史证据，Mac须自己核验；该测试会写自身release报告。不要启动sync.py正式采集或systemd/cron/launchd任务。

## 7. 接手验收与下一步

Mac Agent应先记录：Git分支/HEAD/干净状态、实际macOS/PHP/DB/Node版本、采集器路径和generation/计数、图片BASELINE_ONLY、后台登录/API、小程序真实截图，以及尚未通过项。基础API可在另一终端检查：

~~~bash
curl --fail http://127.0.0.1:18080/api/category
curl --fail 'http://127.0.0.1:18080/api/products?page=1&limit=2'
git status --short --branch
~~~

Mac环境恢复完仍停在Phase 1；“开始 Phase 2”后才做三映射、只读来源Reader、简单价格和5–10商品图片/转换测试。不要抢先导全量、改SKU核心或开定时。

可直接给OpenCode的首条任务：

> 先读取AGENTS.md、HANDOFF.md和docs/MAC_HANDOFF.md，核对codex/lativ-phase1分支及已迁移资料。当前Intel Mac，仅恢复并验证Phase 1本地环境，优先复用已有CRMEB与采集器，不升级依赖、不重写商城、不重新抓数据。列出缺失资料或未通过项，完成后停止；没有“开始 Phase 2”不开发LATIV同步模块。
