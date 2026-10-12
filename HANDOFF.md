# OpenCode 动态交接

核对日期：2026-10-12（Asia/Shanghai）。本次仅冻结、核查和交接，没有开始Phase 2。长期规则见AGENTS.md；历史分析见docs/PHASE1_REPORT.md；新电脑重建见docs/LOCAL_REBUILD.md。

## 项目目标

用CRMEB PHP v6原生商城完成LATIV台湾服装代购微信小程序MVP。用户完成登录、商品/分类/搜索、颜色尺码SKU、购物车、地址、下单支付、订单物流及退款售后；后台使用原生商品/订单/用户/分销/商家管理，新增最小LATIV同步入口、价格配置、周期/开关/日志。

采集层已经独立存在；这里只接收其已提交数据，不重写爬虫或商城。先稳定打通LATIV→Adapter→CRMEB→小程序，不为架构完整增加复杂模块。

## 当前状态

- 分支codex/lativ-phase1；本次开始时git status干净，git diff和暂存区均为空。
- 固定官方源码da8d3b93400f3c660aa54d38b54ada387c963cd9，CRMEB-KY v6.0.0。源码以codeload导入本地基线，未保留官方完整Git提交历史。
- 本地基线501ffcc4；Phase 1成果26470407。相对基线的改动仅.gitignore、docs、scripts/local，crmeb/app、vendor、template业务代码没有改动。
- 已有后台和小程序编译包能在本机运行；小程序首页截图展示的是官方演示商品/主题，绝不是LATIV同步商品。
- app/services/lativ、dao/lativ、model/lativ、LATIV后台、tools/lativ、业务测试及migration均未创建。不存在可调用的LATIV→CRMEB同步实现。
- 官方README含通用/历史说明；实际后台没有npm run lint、UniApp没有npm构建scripts。以当前package.json、源码和此交接为准。

## Phase 状态

沿用原用户定义，不自行重新分期。

| Phase | 原范围 | 状态与限制 |
|---|---|---|
| 1 | 本机环境、源码/数据库/商品SKU/订单/采集结构分析 | 已完成基础审计与本机演示启动；源码重新构建、生产MySQL/Linux及真实交易不在已通过证据中 |
| 2 | 商品/SKU/分类映射、简单价格、图片、5–10样本 | 未开始，必须等用户明确“开始 Phase 2” |
| 3 | 商品新增/更新、SKU/库存/价格、来源上下架、扩全量 | 未开始 |
| 4 | 30分钟调度、Redis锁、失败重试、同步日志、后台 | 未开始 |
| 5 | 原生微信登录、购物车、地址、下单支付、发货物流退款 | 未开始业务闭环验收 |
| 6 | 统计/分销入口、手机商家管理、UI与性能 | 未开始 |

采集器是单独的冻结local-shadow工程，不归入本仓库Phase 2/3“已完成”。当前没有正在推进的商城开发阶段，也未授权新Agent自动进入Phase 2。

## 已完成功能与未完成边界

| 项目 | 当前真实情况 |
|---|---|
| LATIV分类采集 | 已存在原始三级树/商品membership，读取现成SQLite即可；见下方334/333差异 |
| 商品采集 | 已提交2,821条，active=2,792；29条非活动 |
| SKU采集 | commerce_product.wap.v2模型有来源sku_id、color_id、size_id、reported_num；合计29,152，活动商品29,126 |
| 商品详情 | 模型含basic_info、详情、fit/size表、图片区域、display_*；未转换为CRMEB商品描述 |
| 图片URL | top_carousel/detail_images等保留有序引用；运营图片队列已登记94,442项 |
| 实际图片下载 | 当前运营队列全部BASELINE_ONLY；DONE=0、local_file非空=0、sha256非空=0，state/media/content不存在。下载器及部分历史预览证据存在，不能说运营商品图片已下载 |
| 上传至CRMEB/OSS/COS | LATIV链路未实现，未验收；本机39张官方演示图片不是LATIV上传成果 |
| 分类映射/防重复分类 | 仅方案：一级+组合二级；没有mapping表或幂等实现 |
| CRMEB商品导入 | 未实现；数据库仅4个官方演示商品 |
| SPU/SKU稳定映射 | 未实现；三映射表尚不存在 |
| 价格换算/集中配置 | 未实现；没有LativPriceServices或lativ_*配置 |
| LATIV库存写入CRMEB | 未实现；原生uploadStock/syncStock已找到但未做LATIV调用验收 |
| 增量同步 | 采集器events/hash/原子提交已存在；CRMEB Adapter游标、事务和重试未实现 |
| 下架 | 采集器active/lifecycle已有；CRMEB来源下架、人工禁售保护未实现 |
| 第二轮幂等/SKU不重复 | 没有同步实现，未测试，不能宣称通过 |
| 定时 | 采集器原配置60分钟；CRMEB原生定时代码存在，LATIV mark/30分钟调度/锁未实现、未启用 |
| 微信小程序 | 官方编译包首页、轮播及导航图片实际显示且接本机API；真实登录/支付/退款、所有页面、UniApp源码构建尚未验收 |
| CRMEB后台适配 | 原生后台登录/商品列表/分销与商品统计API已验证；LATIV同步管理、价格表单、映射和日志入口未开发 |

原生订单、支付、售后、物流、分销、手机商家代码存在，后续直接复用；“代码存在”不等于本项目已完成全部外部配置和闭环验收。

## 未完成功能

按原Phase顺序完成三映射与来源Reader、简单价格/图片样本；之后再实现实际商品/SKU增量写入、库存/禁售、一商品事务及全量同步；再做调度/锁/日志/后台，最后原生交易与商家入口验收。不新增库存引擎、复杂价格规则、图片队列或第二套商城。

## 最近修改

| Commit/文件 | 目的 |
|---|---|
| 501ffcc4 | 导入固定官方v6源码；属于商城基线，不是LATIV实现 |
| 26470407 | Phase 1报告、本地隔离环境脚本、截图和验证；业务源码不改 |
| scripts/local/bootstrap.php | 初次建隔离演示库，随机本地凭据；拒绝覆盖已有库/安装 |
| scripts/local/start.ps1、stop.ps1、router.php | 启停归属核验、回环服务、后台history路由 |
| scripts/local/mini-loopback.js | 只拼到测试app.js，转向本机API和包内演示图片，不属于正式商城/图片同步 |
| 本次指定交接commit | 新增AGENTS.md/HANDOFF.md/重建说明/只读核验记录，README增加导航；不改业务逻辑 |

最近核心文件的Git提交仍是基线；StoreProduct/OutStoreProductServices/StoreProductServices、规格、分类、上传、订单、调度均无本项目修改。近期核对它们只是读代码。

## 当前数据库状态

### CRMEB本机库

只读核对：crmeb_phase1，127.0.0.1:13306，MariaDB 10.11.10，前缀eb_。

- 157张原生表；eb_store_product=4，eb_store_product_attr_value=54（含原生活动类型），eb_store_category=35，eb_system_timer=11。
- LATIV新增表0、修改核心表0、LATIV配置项0。没有LATIV ID与CRMEB ID绑定数据。
- 初次导入用crmeb/public/install/crmeb.sql，只在导入内存字符串将ROW_FORMAT=COMPACT改成DYNAMIC，解决本机utf8mb4索引限制；官方SQL未改。这不能作为生产MySQL验证。
- 本仓库没有crmeb/database目录或LATIV migration。think-migration依赖及命令存在，只是尚未创建/执行业务迁移。
- 本机配置site_url为http://127.0.0.1:18080、queue_open=0、upload_type=1（本地）。

关键SKU实际字段：id/product_id、suk varchar(128)、unique char(8)、stock unsigned、price/cost decimal(12,2)、ot_price、image varchar(128)、bar_code/bar_code_number varchar(50)、type、is_show。unique索引不是唯一约束。

### 采集器实库

当前机器独立目录：C:/Users/13470/Desktop/lativ-sync-service。VERSION=1.0.0-local-shadow；该目录不是Git仓库，不能靠本仓库push或clone迁走它。

- state/data/platform2.db：meta、runs、run_categories、staging、products、events、sync_errors。
- generation=10；products 2,821/active 2,792；SKU 29,152/active商品29,126；events 5,505，ID 1–5,505。
- 最近COMMITTED run为20261011T114347399630Z，2026-10-11 20:23:42北京时间结束。
- 当前meta.seed_tree直接统计为5大类、38中类、334条唯一三级路径；历史Phase 1报告写333。尚未确认333是否来自其它投影/过滤口径，不能硬编码。Phase 2 Reader先核对这一个差异，不重新采集。
- state/media/queue.db：settings、items；94,442 BASELINE_ONLY，无DONE、local_file或sha256值。
- 使用schema/commerce_product.wap.v2.json、platform2.sql、image_queue.sql描述已有边界；不改采集库schema或状态。
- release/SHA256SUMS.json核对103个现有py/ini/sh及规则/版本/依赖文件全部一致。旧source-files.sha256.json的24项中，10个历史辅助脚本不在发行目录，unified_sync.py与旧清单不同但符合当前发行清单；不要为旧清单恢复这些文件或改运行代码。

### 后续DDL方案（未实现）

Phase 2：lativ_product_mapping（来源product_id→CRMEB product_id）、lativ_sku_mapping（来源product+sku→CRMEB unique及行ID）、lativ_category_mapping（完整三级路径→两级分类）。未绑定ID允许NULL，外部ID用字符串，来源唯一约束。

Phase 4：一张lativ_sync_logs记录导入结果。汇率/加价/舍入、调度、权限沿原生配置/菜单/任务表。详细字段见历史Phase 1报告；实际迁移前按真实schema核对，不一次创建后续阶段表。

## 配置

| 项目 | 实际位置与交接规则 |
|---|---|
| 服务端.env | crmeb/.env被Git忽略；数据库、cache、redis、queue等分组由crmeb/config读取。本机凭据在.local/local-access.json，不打印/提交 |
| 本机运行库/数据库 | .local/runtime、mysql-data、mysql-local.ini、PHP php.ini均忽略；两个ini含当前用户绝对路径，换机要按重建文档生成 |
| 管理端API | template/admin/.env.dev/.env.production的VUE_APP_API_URL；原配置为空，同源模式。本机使用public/admin已有包，不是npm构建结果 |
| UniApp API/AppID | config/app.js仍为官方demo域名；manifest.json仍为官方示例AppID。正式构建前改自己的环境，当前业务源码未改 |
| 测试小程序 | C:/Users/13470/.cache/lativ-crmeb-mini-phase1，备用.local/mini-program；AppID wxdcbc3311b379e6e7（公开标识），没有保存AppSecret/商户凭据 |
| Redis/队列 | 未安装启用Redis，cache=file、queue_open=0；.env中的Redis端口16379只是占位；原queue.php默认redis，不代表已有worker |
| 定时 | 原生SystemCrontabServices及Timer存在；没有LATIV任务或同步锁；不要在交接时启动worker/timer |
| 采集器 | .env/环境变量控制LATIV_STATE_DIR/DB/MEDIA/CONFIG；outputs/platform2-incremental/sync_config.ini配置60分钟、Detail 3 worker、2 QPS、TTL 1h、图片4 worker |
| 图片存储 | 原UploadService及本地/OSS/COS/七牛；本机upload_type=1，未配置/验证LATIV云上传 |

没有把密码、token或secret写入本文件。原生上游示例配置/域名不等于已配置自己的生产环境；不要复制演示账号作为正式凭据。

本次只读检查到LATIV-Platform2-LocalShadow-Hourly任务存在但Enabled=false/Disabled，没有匹配独立采集器路径的Python进程；没有修改任务开关或启动采集。DB/PHP仅监听本机13306/18080，16379无监听。

## 当前已知问题、高风险与技术债

1. LATIV链路尚未实现：映射、导入、更新、防重复、增量游标、幂等、库存、下架、集中价格均不能验收为完成。
2. StoreProductAttrServices::saveProductAttr当前仍三次del后重建；只保留unique也会改变行ID/删除历史行。Phase 3需最小托管增量分支，覆盖同步及普通后台编辑；现在不要提前改。
3. unique只有8字符且非强唯一；按SKU数组顺序匹配不可行。新值需碰撞检查，既有id/unique必须保留。
4. uploadStock只按bar_code定位，静默跳过无匹配；入口要求的bar_code_number不参与定位；批100、qty绝对数。当前读写/汇总缺type=0限制，ProductStockJob/队列模式也须实际核对。
5. 来源库存覆盖和订单扣减存在竞争，退款可能回补已停售SKU；库存0不等于永久禁售。is_show与购买校验须一起验证，不能拦已支付回调。
6. 失效商品不能删除，来源恢复不能取消人工禁售；以上CRMEB逻辑未实现。
7. originPrice/promotionPrice可能有组合语义，采购单件价未确认；不可默认为促销金额。没有集中价格代码，更没有价格版本系统。
8. 图片运营队列只是基线登记，reconcile的INSERT OR IGNORE不会自动将旧BASELINE_ONLY变QUEUED；样本应复用下载器到独立Adapter目录，不修改冻结队列来制造成功。
9. 本机39张演示上传目录被Git忽略；clone缺这些图片，测试shim无法凭空提供文件。重建文档说明从固定官方包恢复。
10. PHP7.4及Windows/MariaDB是测试方案，不是已验证生产环境；入口拒绝PHP8，不在交接中升级依赖或改入口。
11. Windows启动脚本依赖USERPROFILE cache junction、Get-NetTCPConnection/CIM及便携目录；ini和解包版开发工具的CLI包装是当前机器方案。不是跨平台一键启动；用户名含空格/中文及Linux路径尚未验收。
12. 小程序从已有编译包验证，未做HBuilderX源码重新构建；微信3.15.2仍有timeout/clickCheckTask内部TypeError，但实际首页可显示。不把它叫“零错误完整验收”。
13. v6统计和分销代码/路由/API已存在；统计部分入口缺种子菜单；手机pages/admin/manage/index不存在，优先复用order/index等原页面，Phase 6再补入口，不复制v5文件。
14. 手机SKU修改汇总价与主商品流程有max/min差异；商品差价场景待后续验收。普通后台编辑也可能重建SKU，不能只验证Adapter。
15. collector目录/数据库没有在商城Git里；克隆商城不会得到源数据。数据库很大，不能把SQLite、图片队列、秘密或运行库强行git add。
16. 历史分类数量和旧文件hash清单口径有差异，已按当前实况记录；不回写collector、不“修复”历史证据。
17. 尚未做真实微信登录/支付/回调/退款、外部物流、分销提现、手机店员授权、Linux定时运行或生产存储验收。
18. 当前命令路径的Node是24.19.0，超出后台package.engines要求的>14且<23；本次只用它做JS语法检查，未构建后台，未切换/升级依赖。源码构建前恢复兼容的Node环境。
19. 本地bootstrap/loopback有固定测试库名、端口、演示域名及图片前缀，这是隔离演示配置，不能复制为LATIV生产配置。没有LATIV价格/分类/映射硬编码模块，因为这些模块尚不存在。
20. .local/phase1-install.php是早期临时建库实验，含重建行为，不是接手入口；保留但禁止运行。只使用有覆盖保护的已提交bootstrap或既有库恢复流程，不能自动执行.local里的实验脚本。

## 测试状态

历史Phase 1通过：9,279个关键源码文件与官方包字节一致；后台实际登录；首页/分类/商品/分销统计/商品统计API；小程序currentPage=pages/index/index、真实截图、本机18次HTTP及业务状态200。证据在docs/evidence和docs/phase1-verification.json。

采集器历史release/pytest-final.xml：2026-10-11有424测试、failures/errors/skipped均0。这是历史记录，不冒充本次重跑。当前103项发行源码校验无差异；本次没有运行采集、重置状态或做云上传。

本次基础检查的准确结果见docs/handoff-verification.json，包括PHP语法、JS语法、JSON、PowerShell解析、只读SQL/SQLite、公开API及静态路径检查。完整前端构建没有执行：node_modules未安装、HBuilderX未准备；不为交接安装/升级依赖。历史自动化SDK启动失败已由官方wechatide-skill真实截图取证替代，不能用旧SDK失败推断页面未运行。

本次通过10个PHP文件语法、mini-loopback JS语法、两份PowerShell脚本及重建文档6个PowerShell片段解析、3份验证JSON、本机category/products/index业务status200及admin/index HTTP200、采集器静态portability。portability报告字节未改变；完整424回归未重跑。此交接未做新电脑实机重建测试。

复跑命令：

~~~powershell
$taskPhp = Join-Path $env:USERPROFILE '.cache/lativ-crmeb-phase1/.local/runtime/php74/php.exe'
& $taskPhp -l scripts/local/bootstrap.php
& $taskPhp -l scripts/local/router.php
node --check scripts/local/mini-loopback.js
git diff --check
Invoke-RestMethod http://127.0.0.1:18080/api/category
Invoke-RestMethod 'http://127.0.0.1:18080/api/products?page=1&limit=2'
~~~

生产/交易测试需自己的外部配置和阶段授权，不能伪造成功。采集器portability和完整回归按其AGENTS执行；health的quick_check可能需数分钟，不能当高频探针。

## 启动方法

### 原电脑交接

工作目录C:/Users/13470/Documents/ChatGPT/lativ微信小程序。既有DB/PHP服务使用.local/processes.json记录的进程，启动脚本核验端口归属；不要重跑bootstrap、重置数据库或停止采集器。

~~~powershell
Set-Location 'C:/Users/13470/Documents/ChatGPT/lativ微信小程序'
git status --short --branch
& ./scripts/local/start.ps1
~~~

后台http://127.0.0.1:18080/admin/；凭据只读本机私有JSON。微信开发工具手动打开英文测试目录并编译；扫码、安全设置和客户端授权由用户操作。

### 新电脑clone

~~~text
git clone --branch codex/lativ-phase1 https://github.com/runy5231gunygn-glitch/lativ.git lativ-crmeb
cd lativ-crmeb
~~~

推送是否成功以本次最终报告或远程refs为准；不要误从远程已有main接手。若分支未推送，使用原电脑.local/handoff/lativ-crmeb-opencode.bundle离线clone。

clone后先按docs/LOCAL_REBUILD.md准备固定PHP/MariaDB、本地配置、全新隔离库、演示资源和测试副本，再启动。仅clone不能直接运行start/bootstrap。另行安全迁移完整采集器源码及state；同一电脑可直接使用原目录。禁止因缺数据重新抓一套或将7.56GB SQLite放Git。

## Git与远程交接

- origin为用户指定的https://github.com/runy5231gunygn-glitch/lativ.git；官方upstream保持https://github.com/crmeb/CRMEB.git。
- 远程已有main（本次只读查到58b3f8e1e63b895f8aee3bcdbb0e073e58fdc421），不覆盖/合并/强推它。本交接只推codex/lativ-phase1。
- 提交消息按用户要求：chore: prepare project handoff for OpenCode。
- .local、crmeb/.env、数据库/运行文件、上传目录均有意不入Git；有效商城源码和交接文档在Git。临时只读审计脚本不是业务成果，不需要迁进服务层。
- 对新电脑重要的非Git资料：collector全目录/state/冻结证据；需要复用旧后台数据时的安全DB备份及配置；当前测试副本可重建，不必复制敏感运行环境。按重建文档处理。

## 下一步任务（有顺序，不重新规划）

1. OpenCode先读AGENTS.md和本文件，确认checkout交接分支、git干净、原电脑还是新电脑；核验已有环境和数据，保持冻结。
2. 原电脑复用现成环境；新电脑先重建/安全迁移源数据。不要先建业务表、启动采集或自动定时。
3. 等用户明确“开始 Phase 2”。未收到时只解决接手/环境核验，不写LATIV业务。
4. 获授权后先建三映射的可回滚migration/Model/DAO，确认只连接独立测试库，未绑定CRMEB ID允许NULL。
5. 实现独立只读来源Reader/导出，以committed generation/events为界，去除source_raw；核验334/333分类口径、价格单件语义、稳定来源SKU键。
6. 选5–10真实商品覆盖颜色尺码/缺货/多分类/停售/特殊详情，先做规范化与两级分类映射预览。
7. 用原配置做简单价格预览，复用现有下载器及UploadService完成样本图片；重复执行不重复上传、不改变引用顺序。
8. 验证映射唯一、乱序仍同SKU、价格边界、图片失败/复用和只读边界；单独Phase 2提交后停止。
9. 只有“开始 Phase 3”后才做真实商品导入、SKU增量保留、库存/价格更新、人工/来源下架，并扩全量。

本次交接结束后停止。不能因为读到待办就自动执行Phase 2–6。
