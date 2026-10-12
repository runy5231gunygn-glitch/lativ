# LATIV × CRMEB v6：Phase 1 分析报告

核对日期：2026-10-12，Asia/Shanghai。仅 Phase 1；本报告不授权执行 Phase 2。

本报告的运行证据来自Windows；Intel Mac接手读[MAC_HANDOFF.md](MAC_HANDOFF.md)，最新远程分支/真实进度读根目录HANDOFF.md。GitHub交接分支已在Mac移交前核对同步，Mac运行尚未验收。

分类统计补充：后续交接只读核验meta.seed_tree得到5/38/334；本报告历史333口径未确认，保留历史记录，不作为硬编码依据。数据库与源码未因此改动。

## 1. Phase 1 分析结果

### 基线与环境

- 唯一商城主干：官方 `crmeb/CRMEB` master，`da8d3b93400f3c660aa54d38b54ada387c963cd9`；`crmeb/.version` 为 CRMEB-KY v6.0.0。
- 原工作区只有空 Git 仓库，已导入官方固定提交源码包。由于 Git 网络拉取长期无进展，采用官方 codeload 包，不声称保留了上游完整 Git 历史。
- 官方包 SHA-256：`A80EC88577DF482BE52085E57ACE95704FD2888B043501F028FCF21BB0151BF2`。
- 已扫描全部 17,873 个树条目；对 app/vendor/template 内 9,279 个 PHP/Vue/JS/JSON 文件逐字节核对，修改数为零。未修改 vendor 或商城业务代码。
- 官方源码导入基线 commit：`501ffcc4`。分支：`codex/lativ-phase1`。
- 本机测试环境：PHP 7.4.33 + MariaDB 10.11.10，HTTP `127.0.0.1:18080`，数据库 `127.0.0.1:13306`；只监听本机回环地址，没有部署生产。
- 通过：实际后台网页登录、管理员 token、后台商品列表、首页 API、分类 API、商品列表 API、分销统计 API、带时间参数的商品统计 API。
- 本机共导入 157 张原生表和 4 个官方演示商品，没有导入任何 LATIV 商品，没有创建 LATIV 表。
- PHP 入口明确拒绝 PHP 8；README 的版本范围与入口一致。生产使用受支持 PHP 的兼容方案尚未验证。
- 官方 SQL 的部分 COMPACT 行格式在本机兼容引擎触发 767 字节索引限制；仅在本机导入字符串中转为 DYNAMIC，官方 SQL 文件未修改。生产 MySQL 尚未实际验收。
- 缓存使用原生 file 配置，队列开关在本机配置为关闭；Redis、队列 worker、LATIV 定时任务尚未启用，留待 Phase 4。
- 微信开发者工具 2.02.2608070 的腾讯官方安装包已验证 Microsoft WinGet 清单 SHA-256；采用免安装文件解包，无系统安装器执行。用户已本人扫码、开启服务端口并授权 Codex 调试接口。工具自带 wechatide-skill 0.3.9 返回版本一致、登录有效、不要求 CLI 令牌。
- 模拟器启动与本机 API 接入已取得实际证据：automation_runtime_info 返回 `pages/index/index`；simulator_screenshot 保存首页截图；18 次本机 API 响应的 HTTP 与业务 status 均为200，包括 basic_config、theme_info/home、products。证据为 `docs/evidence/phase1-mini.png`、`phase1-mini-runtime.json`。
- 使用官方已有小程序编译包的独立测试副本：工作区 `.local/mini-program`，实际打开的纯英文目录为 `C:/Users/13470/.cache/lativ-crmeb-mini-phase1`。只配置测试 AppID、回环请求路由，并移除本地 app.json 中不属于 MVP 的直播插件声明；原声明有备份，上游前端和 vendor 未改。这不替代未来从 UniApp 源码正式构建。
- 首次启动确有直播插件未授权阻断（provider wx2b03c6e691cd7370）。移除测试副本声明并重新打开后，首页可以渲染。演示数据图片仍指向官方demo站，测试副本通过 `scripts/local/mini-loopback.js`转向包内已有39张本机演示图片，最新截图已显示轮播及导航图片，未改数据库或商城图片系统。
- 微信3.15.2基础库仍有 SDK timeout/clickCheckTask TypeError 日志，但实际首页已显示。全部页面图片、从源码重新构建和完整交易流程尚未验收，不能称为“无启动错误”或商城完整通过。没有为了该日志修改商城代码或强行更换基础库。

登录截图：`docs/evidence/phase1-admin.png`。账号密码、token、运行库、数据库、开发工具均在被忽略的 `.local/` 中，不提交 Git。

### CRMEB 结构与现有能力

仓库根下 `crmeb/` 是服务端；因此用户提到的 `app/...` 实际位于 `crmeb/app/...`。

| 能力 | 已确认位置 / 方法 | MVP 处理 |
|---|---|---|
| 商品新增、修改、上下架、查询 | `app/outapi/controller/StoreProduct.php`；`OutStoreProductServices::save/setShow/getInfo` | 复用商品流程，LATIV更新接增量SKU分支 |
| 商品后台 | `app/adminapi/controller/v1/product/StoreProduct.php`；`StoreProductServices`；`template/admin/src/pages/product/productAdd/` | 复用 |
| SKU | `app/services/product/sku/StoreProductAttrServices.php`、`StoreProductAttrValueServices.php`；`validateProductAttr/saveProductAttr` | 复用校验、模型和DAO，补受控增量写入 |
| 库存 | `StoreProduct::uploadStock`、`OutStoreProductServices::syncStock/calcStockByAttrValue`、`app/jobs/ProductStockJob.php` | 复用，不增加库存引擎 |
| 分类 | `StoreCategoryServices::createData/editData`，明确限制两级 | 保留两级，压平来源二/三级 |
| 图片 | `app/services/other/UploadService.php`；`crmeb/services/upload/storage/Local/Oss/Cos/Qiniu.php` | 复用 stream 与附件登记 |
| 登录 | `app/services/wechat/RoutineServices.php`、`WechatUserServices.php` | 原生微信登录，Phase 5 联调 |
| 购物车 / 地址 | `app/services/order/StoreCartServices.php`、`app/services/user/UserAddressServices.php` | 复用 |
| 下单 | `app/api/controller/v1/order/StoreOrderController.php`；`StoreOrderCreateServices::createOrder` | 复用 |
| 支付 | `app/services/pay/OrderPayServices.php::beforePay/afterPay`、`PayServices.php` | 复用，不模拟收款事实 |
| 售后 / 退款 | `StoreOrderRefundServices::applyRefund/payOrderRefund/regressionStock` | 复用 |
| 发货 / 物流 | `StoreOrderDeliveryServices::delivery/splitDelivery`、订单 Controller 的 express | 复用；外部物流通道配置待验收 |
| 分销 | `app/adminapi/controller/v1/agent/`、`app/services/agent/`、`UserBrokerageServices/UserExtractServices` | 保留原生一级/二级返佣、关系、二维码、佣金、提现 |
| 统计 | `app/adminapi/controller/v1/statistic/`、`app/services/statistic/`、`app/adminapi/route/statistic.php`；Vue/API/router 均存在 | 复用，Phase 6补入口/权限 |
| 手机商家端 | `StoreManageController`、`StoreOrderController`；`app/services/admin/StoreManageServices.php`；`pages/admin/goods/orderList/user/refund...` | 复用页面和 API |
| 调度 | `app/services/system/crontab/SystemCrontabServices.php`、`CrontabRunServices.php`、`crmeb/command/Timer.php` | Phase 4注册 LATIV mark，复用开关/周期 |
| 后台配置 / 权限 | `SystemConfigServices`、`SystemMenusServices`、`eb_system_config/system_menus/system_role` | 复用，不新增配置/权限体系 |

这些已验证能力符合当前以CRMEB v6为唯一主干的目标。是否比相同版本条件下的ShopXO更适合：本次未做ShopXO逐项源码对照，尚未确认；不据此引入第二套商城。

#### SKU 的重要区别

`eb_store_product_attr_value`：`id` 是数据行ID；`unique char(8)`是业务SKU标识；`suk`按规格顺序拼接（如黑色,S）；`bar_code`为库存匹配编码；`bar_code_number`为条形码；另有 `stock/sales/price/cost/ot_price/image/is_show/type`。

| 字段 | 原生用途 / LATIV接入方式 |
|---|---|
| unique | 购物车/订单等使用的规格身份；已有值可保留，新SKU才生成；不能按同步轮次重生成 |
| suk | 规格组合字符串；固定颜色、尺码维度顺序，用原校验生成，不用作来源SKU身份 |
| bar_code | syncStock实际匹配键；分配带LATIV前缀的稳定编码，校验全商城不重复且不超过50字符 |
| stock | 当前可售数量，数据库为unsigned整数；Adapter校验非负整数，qty按绝对数更新 |
| price | 人民币销售价；用简单配置换算计算 |
| cost | 人民币采购成本；TWD单件结算价格语义确认后除汇率 |
| ot_price | 原价/划线展示价；不是汇率或来源币种字段；没有可靠展示价依据时不制造折扣 |
| image | 规格图片URL，字段最长128字符；引用自己存储的已上传图片 |
| is_show | 规格显示标记；停售置0，但原购买校验仍须配合库存/禁售判断 |

方法归属：`StoreProductServices::validateProductAttr()`在商品Service；`StoreProductAttrServices::saveProductAttr()`在规格Service；`StoreProductAttrValueServices`负责规格行读写。

`StoreProductAttrValue::setUniqueAttr()`接受非空原 unique；但 `saveProductAttr()`第51–53行删除规格、结果及SKU行后重建。因此“保留 unique”仍不满足本次禁止物理删除历史SKU的要求。

方案：LATIV托管商品走一个小范围增量分支，继续调用原 `validateProductAttr()`，并通过已有 AttrValue Service/DAO 做 UPDATE/INSERT。已有 SKU 的 `id/unique`保留；新 SKU才INSERT；停售保留行，stock=0、is_show=0；不遗漏历史行，不按来源排列顺序识别身份。属性定义/结果缓存可按原机制更新，不能误删实际SKU行。普通非LATIV商品仍走原流程。

OutAPI还存在：完整保存去掉 stock并以 validate=0生成重建数据；首次新增生成的真实 unique未回填到多规格 OutAPI getInfo结果。增量分支中保留现有业务计数，真实unique从数据库或已有 `StoreProductServices::getInfo()`读取；不从未回填的 AttrResult JSON猜测，也不必为此新增详情接口。unique只有普通索引，新增前需要检测碰撞；映射中同时保留商品ID、unique与行ID。

`uploadStock()`每批最多100条，入口要求bar_code/bar_code_number/qty，但syncStock仅用bar_code匹配；qty是绝对值，未匹配项被跳过。查询和总量汇总目前没有普通商品type=0限定，复用时应限制类型并回读核对。总量、AttrResult通过原ProductStockJob更新；不新增仓库系统。

#### 统计、分销与手机入口

- 商品/用户/订单/交易统计的Controller、Service、API、Vue页面及 `router/modules/statistic.js`存在，`router/routers.js`已导入；本机商品统计API实测200。初始菜单数据未提供对应四类独立统计入口，不能据菜单缺失判定没有统计能力。Phase 6补菜单/角色权限，不重写统计。
- 分销菜单及原API存在，本机分销统计实测200；`StoreOrderCreateServices`包含spread_uid、spread_two_uid、one_brokerage、two_brokerage；退款有原生退佣。没有必要迁移v5分销。
- 手机商品、SKU价格/库存、订单、发货、退款、用户页面已经在 `pages.json`的 `pages/admin`分包注册。`StoreManageServices::saveProductAttr()`按unique更新实际行，没有全删重建。
- `pages/admin/manage/index.vue`未找到；个人中心保留了该URL判断。优先使用现有 `pages/admin/order/index.vue`作为经营入口，补商品/用户导航，不开发独立商家APP。
- 手机商家API已有CustomerMiddleware，按客服/店员/配送员身份判断权限；后台admin token不能直接当作手机用户token使用。
- 在v6缺少manage页面之后，核对官方v5.6.3.1相同路径也未找到；参考了其 `pages/admin/order/index.vue`和pages.json业务入口思路。未复制或覆盖任何v5文件。统计和分销业务在v6已存在，无迁移必要。

### LATIV 当前实库

只读打开：`state/data/platform2.db`和`state/media/queue.db`，未执行采集、重置checkpoint或改动解析器。

| 实测项 | 当前结果 |
|---|---|
| 模型 | commerce_product.wap.v2 |
| generation | 10 |
| 三级分类 | 5大类 / 38中类 / 333小类 |
| 商品 | 2,821记录；2,792活动；29非活动 |
| SKU | 模型合计29,152；活动商品29,126 |
| 事件 | 5,505；游标范围1–5,505 |
| 最近提交 | 2026-10-11 20:23:42北京时间；完整目录、errors=0 |
| 图片队列 | 94,442条，全部BASELINE_ONLY；DONE=0 |

商品读取真值：products.active、model_json、model_hash、updated_at、memberships_json。events提供单调event_id。runs提供健康度和catalogue_complete。staging不能成为正式商城数据源。

实际SKU已有sku_id、color_id、size_id、display_color_name、display_size_name、display_size_suffix、reported_num。名称用basic_info.display_name。图片引用已有rendered_url、source_index、display_index。保留重复引用和展示顺序，资产去重不能删除展示引用。

来源分类身份应取(big,medium,small)路径键；smallCategories.hash是图片资源hash，不能当category_id。group是商品发现分组，不能取代分类路径。多个商品分类membership须保留。

实际价格示例同时有originPrice=350、promotionPrice=199，且unit_bundle_price_inferred=false；不能自行认定促销金额就是单件采购结算价。数据没有已验证的商品页面URL；可保留source_endpoint，source_url无真实证据时为NULL。

图片BASELINE_ONLY是基线登记，不是已下载：现有ImageQueue.reconcile使用INSERT OR IGNORE，不会把这些旧行自动转QUEUED。Phase 2只为5–10个样本复用既有下载器，在独立Adapter图片工作目录建立所需任务，不重写或改动冻结采集层。

## 2. 技术方案

```text
已提交 LATIV标准模型 + events + 已下载图片
  → 独立只读导出/Adapter（排除source_raw巨型内容）
  → app/services/lativ/各小Service
  → CRMEB现有商品/规格/上传/配置Service
  → CRMEB原生商城与手机商家端
```

- 不让小程序读SQLite；不由采集器直接写MySQL；不重新抓一套商品。
- 分类维持两级：根=LATIV一级，子=“来源二级 / 三级”。显示中文按既有DISPLAY策略处理，原三级路径与来源原文留在映射中。无需修改分类Service、表结构、API或分类页。
- 来源SKU映射按(product_id,sku_id)查找，不靠数组下标或颜色/尺码的展示名称。固定维度顺序，原有id/unique不重建。
- 库存按用户要求采用最新已验证来源reported_num写入，复用原库存更新/汇总。普通同步锁只防同步重叠，不自动防交易竞争；本地订单扣减与来源快照覆盖的业务风险在Phase 3/5验收，不擅自新增库存规则引擎。
- 人工下架和来源下架区分，mapping保留manual_offline。来源健康恢复可自动上架，但不能撤销人工禁售。
- 价格：cost=TWD/exchange_rate；sale=cost*(1+markup_percent/100)+fixed_markup；price_rounding仅控制普通四舍五入或向上取整。BCMath/定点计算，不硬编码4.32，不增加规则版本体系。
- 图片复用UploadService::init()->stream()及SystemAttachmentServices::attachmentAdd()。以采集器hash命名和查已有附件，product映射内保存简单URL缓存；不新增图片队列/上传SDK。
- 一商品一个事务，先占来源唯一映射，再写核心商品和SKU，再提交映射。失败写该商品错误后继续。沿用events与hash跳过无变化项，记录每商品last_event_id；失败映射可直接重试，无需独立任务表。
- Phase 4复用SystemCrontabServices的type=2、minute=30、is_open。在CrontabRunServices注册lativSync入口，执行体调用独立Service；不使用数据库customCode/eval作为主要实现。
- 原LockService默认6秒、固定owner，CacheService在file缓存下直接执行回调，不能直接拿来锁40分钟同步。Phase 4仅增加一个LATIV Redis锁辅助：随机owner、租约/续租、比较释放、非阻塞跳过；不改全商城LockService。
- 所有营销/订单/支付/退款/分销保留原系统；不自动采购、不增加ERP、不改原订单状态含义。

## 3. 文件改动清单

### 本次实际变化（Phase 1）

- 导入未改动的官方v6基线；不属于LATIV功能实现。
- `.gitignore`：忽略`.local/`。
- `scripts/local/bootstrap.php`：仅新建隔离本机原生演示库，拒绝覆盖已有数据库；不操作LATIV库。
- `scripts/local/start.ps1`、`stop.ps1`、`router.php`：回环测试服务、进程归属检查、后台history路由；不改商城代码。
- `scripts/local/mini-loopback.js`：仅拼接进小程序测试副本，转向本机API及包内演示图片；不构成LATIV图片模块，不修改上游包或数据库。
- `docs/PHASE1_REPORT.md`、`docs/LOCAL_ENVIRONMENT.md`、`docs/evidence/`、`docs/phase1-verification.json`：报告与证据。
- 所有凭据、数据库、工具、临时脚本与小程序测试副本位于忽略目录，未提交。

### 预计新增文件：按阶段，当前均未创建

```text
Phase 2
crmeb/app/services/lativ/LativSourceServices.php
crmeb/app/services/lativ/LativProductServices.php
crmeb/app/services/lativ/LativSkuServices.php
crmeb/app/services/lativ/LativCategoryServices.php
crmeb/app/services/lativ/LativImageServices.php
crmeb/app/services/lativ/LativPriceServices.php
crmeb/app/dao/lativ/LativProductMappingDao.php
crmeb/app/dao/lativ/LativSkuMappingDao.php
crmeb/app/dao/lativ/LativCategoryMappingDao.php
crmeb/app/model/lativ/LativProductMapping.php
crmeb/app/model/lativ/LativSkuMapping.php
crmeb/app/model/lativ/LativCategoryMapping.php
crmeb/database/migrations/<timestamp>_lativ_mappings.php
tools/lativ/export_snapshot.py        # 在采集器之外，只读既有数据
tests/lativ/                         # 5–10商品映射、价格、图片验证

Phase 3
扩展上述Product/SkuService的实际写入；不另造商品系统

Phase 4
crmeb/app/services/lativ/LativSyncServices.php
crmeb/app/services/lativ/LativLogServices.php
crmeb/app/services/lativ/LativSyncLockServices.php
crmeb/app/dao/lativ/LativSyncLogDao.php
crmeb/app/model/lativ/LativSyncLog.php
crmeb/app/jobs/lativ/LativSyncJob.php
crmeb/app/adminapi/controller/v1/lativ/LativSync.php
crmeb/app/adminapi/route/lativ.php
template/admin/src/api/lativ.js
template/admin/src/pages/lativ/sync/index.vue
template/admin/src/pages/lativ/log/index.vue
template/admin/src/router/modules/lativ.js
crmeb/database/migrations/<timestamp>_lativ_sync_log_and_menu.php
```

Phase 2仅做映射、价格、图片和样本转换，不提前实现Phase 3商品/SKU生产写入，或Phase 4自动调度/同步后台。表的未绑定CRMEB ID用NULL。

### 预计必要或有条件修改的原文件（后续阶段，当前未改）

| 文件 | 必要范围 | 阶段 |
|---|---|---|
| `app/services/product/sku/StoreProductAttrServices.php` | saveProductAttr最前面增加LATIV托管商品增量分支，覆盖同步和普通后台编辑，保留历史行 | 3 |
| `app/services/product/product/OutStoreProductServices.php` | 复用库存接口时读写/汇总限定type=0；商品详情直接复用现有商品Service，不额外扩展getInfo | 3 |
| `app/services/order/StoreCartServices.php`及真正复用的公共购买校验 | SKU is_show禁售，防止回补库存后售出；不能拦截已收款回调 | 3/5 |
| `app/services/system/crontab/CrontabRunServices.php` | 增加一个mark和转调方法 | 4 |
| `template/admin/src/router/routers.js` | 导入LATIV路由模块 | 4 |
| `template/uni-app/config/app.js`、`manifest.json` | 正式构建配置自己的域名/AppID | 5前的配置联调 |
| `template/uni-app/pages.json` | 正式构建继续移除无授权且MVP不用的直播插件声明；不改普通商城页面 | 正式构建时 |
| `template/uni-app/pages/admin/order/index.vue`、个人中心入口 | 复用现有经营页补商品/用户导航，处理无效manage链接 | 6，确认入口后 |

分类核心、商品表结构、订单/支付/退款/分销Service、vendor原则上零修改。统计入口优先通过现有菜单数据和权限补齐，不改已有统计业务或迁移v5。

## 4. 数据库方案

所有新表用实际数据库前缀；不改CRMEB核心表，不新增汇率、规则版本、图片或采集日志表。

### Phase 2：三张映射

| 表 | 主要字段 / 约束 |
|---|---|
| `lativ_product_mapping` | id；lativ_product_id(string唯一)；crmeb_product_id(nullable唯一)；source_url(nullable)；source_endpoint；source_hash/applied_hash；source_status/sync_status；manual_offline；last_event_id；last_sync_time；image_cache_json；last_error；created_at/updated_at |
| `lativ_sku_mapping` | id；lativ_product_id/lativ_sku_id联合唯一；crmeb_product_id；crmeb_unique；crmeb_attr_value_id；source_color_id/source_size_id；color/size；source_stock；source_price_twd；cost_cny/sale_price_cny；bar_code；source_hash；source_status/sync_status；last_sync_time；created_at/updated_at |
| `lativ_category_mapping` | id；source_path_key(string唯一)；source_big/source_medium/source_small代码及原文/展示名；source_parent_key；crmeb_category_id；mapping_level；created_at/updated_at |

SKU行ID与unique均保存，但来源身份以来源product+sku真值为准。颜色/尺码标签不能作唯一键；NULL表示尚未落地，不用0占唯一键。外部ID不转整数，保留大小写及前导零。

分类映射保存原三级身份：一级到根，二级中间节点保留来源结构信息而不生成商城节点，三级到组合名称的二级商城节点。允许同一商品多个叶子分类。

四个价格配置存原`eb_system_config`：lativ_exchange_rate、lativ_markup_percent、lativ_fixed_markup、lativ_price_rounding；后台复用原配置表单。人工禁售、错误、图片缓存在映射中保存，不增加独立配置表。

### Phase 4：一张执行日志

`lativ_sync_logs`保存run_id、触发类型、来源generation、started_at/finished_at/duration、scanned/created/updated/offlined商品数、created/updated SKU数、failed_count、new_image_count、status、failure_details、error_message。

这是商城导入结果，与采集器请求日志不同；新增一张是用户明确要求，非重建采集日志。没有独立sync_jobs、价格版本、库存ledger或图片队列表。

## 5. 风险点

1. 原规格保存物理删除：必须做托管商品分支，不能只回传unique就认为满足新要求。
2. unique仅8字符且无数据库强唯一；新SKU需检查碰撞，映射也必须加唯一约束。
3. OutAPI完整修改会覆盖默认值、重建SKU，库存/销量可能丢失；不要每30分钟调用完整保存。
4. syncStock按bar_code定位、静默跳过、绝对覆盖、总量依赖原队列；要限制普通SKU并核对结果。
5. 以来源库存覆盖会影响本地订单扣减，30分钟来源快照也不能保证供货；按明确需求实现，不擅自增加库存引擎，Phase 3/5做并发/退款验收。
6. 仅SKU清零不能永久禁售，退款回补可能增加库存；is_show与购买校验需一致。
7. 人工禁售和自动恢复必须分开，避免同步撤销运营决定。
8. 采购单件价格语义、促销组合条件尚未确认；未确认只做价格预览，不自行推断促销价。
9. 图片队列94,442条只是基线，不能宣称可直接上传已有文件；首批复用下载器完成所选样本即可。
10. 来源图常用无扩展名URL；上传文件名扩展名须按已验证MIME选择。SKU image字段只有128字符，Phase 2检查自己存储URL长度。
11. 最近两轮采集约28分钟、40分钟；30分钟触发不等于30分钟数据新鲜，上轮未完应跳过；源采集目前配置60分钟，后续调度阶段再协调。
12. 原LockService默认短租约与固定owner不适合长同步，file缓存的CacheService::lock没有锁；必须独立使用Redis租约，不改全局锁。
13. 手机SKU编辑汇总价格用max，而主商品保存通常用min；全SKU同价时不体现，差价商品须在原页面验收，不盲目把手机保存当同步全流程。
14. 本机PHP7.4已非受支持生产版本，入口拒绝PHP8；本阶段不为运行测试大改核心，生产版本升级/兼容另行验证。
15. 本机MariaDB兼容测试与生产MySQL不同，Windows也不等于Linux队列/定时运行验证。
16. AppID已提供，但真实微信登录、支付、回调、退款、合法域名及商户凭据仍留Phase 5验收；不填写假凭据或伪造支付成功。
17. 当前只验收官方已有编译包的启动、首页图片与本机接口；微信基础库内部错误仍有记录，UniApp重新构建尚未验收，不能当作完整小程序验收通过。官方CRMEB社区有相同基础库timeout反馈，但没有提供已验证解决方案：[问题记录](https://www.crmeb.com/ask/thread/74661)。

## 6. Phase 2 实施计划

只有用户明确发出“开始 Phase 2”才执行。

1. 增加三映射的可回滚migration、Model、DAO；未绑定商城ID允许NULL；只对独立测试库执行。
2. 增加只读来源导出/Reader，以committed generation/events为边界，排除staging和庞大source_raw；不改冻结采集器。
3. 从实际数据选择5–10件，覆盖多颜色、多尺码、缺货、多分类、停售及特殊详情入口；选择基于数据，不制造SKU。
4. 做两级分类转换及原三级映射、商品/SKU规范化预览；同一SKU顺序交换后身份不变。
5. 接原配置系统实现简单换算及批量重新计算预览，确认单件价格取值；不做版本引擎。
6. 为这些样本复用现有图片下载器、hash和CRMEB当前存储/附件登记；重复运行不重复上传，保持图片引用顺序。
7. 测试映射唯一性、重复执行、价格边界、图片失败/重用、source失效以及只读边界。不得提前导入全部商品或启用定时。
8. 单独Phase 2 commit后停止，等待“开始 Phase 3”。商品新增/实际SKU增量写入只在Phase 3实现，日志/锁/后台调度只在Phase 4实现。

参考源码：官方固定提交 https://github.com/crmeb/CRMEB/tree/da8d3b93400f3c660aa54d38b54ada387c963cd9 。v5参考仅官方tag https://github.com/crmeb/CRMEB/tree/v5.6.3.1 。
