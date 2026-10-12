# AI Coding Agent 长期工作规则

本文件适用于本仓库。动态进度、已知问题、当前机器路径与交接记录读取根目录 HANDOFF.md；源码分析参考 docs/PHASE1_REPORT.md。先核对代码、Git和数据库，不把方案或演示截图当成已完成的LATIV功能。用户最新明确指令优先。

## 目标与范围

以CRMEB PHP v6开源版为唯一商城主干，实现LATIV台湾服装代购微信小程序MVP：浏览/分类/搜索、多颜色尺码SKU、购物车、地址、微信登录支付、订单、物流、退款售后，以及后台同步管理。直接复用CRMEB原生商品、用户、订单、支付、分销、统计、后台和手机商家端。

先查Controller、Service、API、路由、Vue页面、菜单和权限；入口不显示不代表功能不存在。只有v6确实缺失时才参考官方v5.6.3.1业务思路；禁止用v5文件覆盖v6。禁止重写商城、采集器、上传系统、分销或商家APP。

## 技术栈与版本

- 固定源码基线：crmeb/CRMEB master提交 da8d3b93400f3c660aa54d38b54ada387c963cd9，crmeb/.version标识CRMEB-KY v6.0.0。不要自动跟进master或升级依赖。
- 后端：ThinkPHP 6；已有vendor中framework为6.1.2、ORM为2.0.33。实际入口要求PHP 7.1–7.4，拒绝PHP 8；composer.json的最低版本声明不能代替入口检查。
- 数据库：原生MySQL/InnoDB；本地MariaDB兼容测试不能当生产MySQL/Linux验证。缓存可用file，队列/长同步锁需要按实际配置接Redis。
- 管理端：Vue 2、Vue Router/Vuex、Element UI、Vue CLI。前端package.json的5.7.0不是商城主干版本。
- 小程序：Vue 2/UniApp，由HBuilderX构建；已有官方编译包可用于环境验证，不能代替源码构建。
- 采集器：独立Python 3.11+项目、SQLite、commerce_product.wap.v2；不属于这个Git仓库。

## 目录与分层

| 目录 | 职责 |
|---|---|
| crmeb/app/adminapi/controller/v1、route | 管理端Controller/路由/权限入口 |
| crmeb/app/api、outapi | 客户端API、外部商品等API |
| crmeb/app/services、dao、model | 业务Service、查询DAO、表模型；沿用Base类及依赖注入规范 |
| crmeb/app/jobs、listener | 原生队列与事件 |
| crmeb/crmeb/services、command | 上传/支付等基础服务、timer/workerman命令 |
| crmeb/config | 环境、缓存、数据库、队列等配置，凭据由忽略的.env提供 |
| crmeb/public | HTTP入口、已有后台/小程序编译包、install/crmeb.sql |
| crmeb/vendor、composer.lock | 固定已有依赖；禁止直接改vendor |
| template/admin/src | 原生后台API、pages、router |
| template/uni-app | 小程序源代码、pages.json、manifest.json、config/app.js；pages/admin为手机商家端 |
| scripts/local | 本地环境辅助；不得用于生产 |
| docs | 分析、环境、验证证据、重建说明 |

LATIV业务按现有分层新增到crmeb/app/services/lativ、dao/lativ、model/lativ及必要的adminapi/controller/v1/lativ；这些是目标目录，是否已创建以HANDOFF和实际代码为准。不要把全部逻辑塞进一个大文件，也不要提前创建无用抽象。

## 采集边界与同步架构

~~~text
已有LATIV采集器的已提交商品模型 / events / 图片
  → 独立只读Reader或导出Adapter
  → LATIV小Service
  → CRMEB现有商品 / 规格 / 库存 / 上传 / 配置Service或OutAPI
  → 原生微信小程序与后台
~~~

接触采集器前读其AGENTS.md、WECHAT_INTEGRATION.md、schema/API_BOUNDARY.md。保持冻结的解析、模板判断、SKU身份、生命周期和图片顺序；source/raw不改，文字展示使用既有display_*策略，图片二进制不改。

SQLite使用mode=ro、query_only和短读事务；读committed products.model_json、products.active、generation、events，不把staging作为商城商品。导出不要携带巨大的source_raw。采集器和商城各自持有自己的事务；商城故障不能回写采集状态。小程序只访问后端API，禁止访问SQLite或暴露采集器配置。

不重复抓数据，不重置checkpoint，不通过删队列恢复运行。迁移采集器数据库时依其原迁移规则暂停任务、关闭连接、处理WAL并整体保存state；本仓库任务不得擅自启用采集或生产定时任务。

## 商品、SPU、SKU与身份

- SPU使用原eb_store_product.id；普通多规格商品spec_type=1。描述、属性定义、属性结果继续使用原生表和Service。
- SKU使用eb_store_product_attr_value；id是行ID，unique为char(8)业务标识，type=0表示普通商品。unique不是数据库强唯一约束，新增需检查碰撞。
- 来源身份是字符串(product_id,sku_id)，不得转成整数、丢前导零或用顺序/展示名称替代。
- 固定颜色、尺码维度顺序；suk是规格组合展示/索引值，不是LATIV身份。映射同时保留来源ID、CRMEB product_id、unique、attr_value_id。
- 现有StoreProductServices::validateProductAttr可复用；StoreProductAttrServices::saveProductAttr会删除规格及SKU行后重建。禁止将它的完整重建路径当作LATIV增量更新。
- 已有SKU的id/unique必须保留，新增SKU才INSERT；有历史订单的SKU不物理删除。停售保留记录并禁售/清零，购买校验要防止退款回补后重新售出；不得阻断已支付回调。
- OutAPI完整商品保存不是PATCH，不能每轮完整保存或全删全建。真实unique从已有商品Service或AttrValue表读取，不从缺失unique的结果缓存猜测。
- 商品失效执行下架而非删除，保留历史订单。库存0只表示缺货；来源下架依采集器健康、完整目录及active判定。人工下架不能被自动恢复撤销。

## 分类、图片与价格

- MVP沿用两级分类：一级=LATIV一级；二级=来源二级/三级组合，如女装→上衣 / T恤。在映射中保留原三级路径；不重构分类Service/表/API/页面。
- 分类身份取稳定(big,medium,small)路径，不使用图片hash或展示名称。group是发现分组；保留商品的多个membership。按唯一映射复用分类，禁止每轮重建；不能硬编码分类数量或ID。
- 图片优先复用采集器已有下载器/hash和CRMEB UploadService::init()->stream()、SystemAttachmentServices::attachmentAdd()。原生存储支持本地(1)、七牛(2)、OSS(3)、COS(4)。
- 区分URL登记、实际下载、成功上传和最终商品引用。BASELINE_ONLY不是DONE。资产hash去重不能删除展示数组中的重复引用或调整source_index/display_index顺序。
- 商品与SKU图片用自己的存储URL，不长期使用LATIV外链；检查MIME、扩展名与SKU image字段128字符限制。scripts/local/mini-loopback.js只处理本地官方演示资源，不能当作LATIV图片同步实现。
- 价格只做简单配置：cost_cny=price_twd/exchange_rate；sale_cny=cost_cny*(1+markup_percent/100)+fixed_markup。集中到小的LativPriceServices，使用BCMath/定点数；舍入规则配置化。
- 汇率、百分比加价、固定费用、舍入存原生系统配置；不要硬编码4.32。来源单件结算价与促销组合语义未确认时不能擅自推断。ot_price是展示原价，不是台币字段。

## 库存、增量与定时

- 复用OutAPI StoreProduct::uploadStock / OutStoreProductServices::syncStock；最多100项/批，入口要求bar_code、bar_code_number、qty，实际按bar_code定位，qty为绝对库存。Adapter校验非负整数、编码唯一性、类型和回读结果；接口成功不表示所有编码匹配。
- bar_code最长50字符，给LATIV SKU分配稳定、带来源命名空间的编码。现有库存读写/汇总缺普通商品type=0限制时只补必要范围，不开发InventoryService/库存账本。
- 总库存和属性结果沿原ProductStockJob等机制汇总。来源库存覆盖与商城订单扣减可能竞争，必须做阶段验收，不把同步锁当交易锁。
- 按events游标和source/applied hash增量处理，成功后推进映射；单商品异常记录并继续，不能整批全部回滚。重复运行、顺序变化、失败重试必须幂等。
- Phase 4复用SystemCrontabServices/CrontabRunServices/Timer注册一个LATIV入口；默认30分钟、可开关/改周期/手动触发。采集周期与导入周期不同。
- 防重入锁使用Redis随机owner、合理租约/续租、比较释放；上一轮未完成则跳过。原LockService默认6秒及固定owner不能直接锁长同步。不要写通用调度框架或修改全局锁。

## 数据库与API规则

- 首版Phase 2只新增lativ_product_mapping、lativ_sku_mapping、lativ_category_mapping；Phase 4按需求新增一张lativ_sync_logs。实际加CRMEB表前缀，不新增十几张表。
- 配置、菜单、权限、调度使用原生表。DDL应有可回滚migration，执行前确认目标库及备份；不改collector schema，不污染核心商品表；业务回滚不能删除有历史订单的原生SKU。
- 外部ID用字符串；来源product+sku及分类路径设唯一键，未绑定CRMEB ID允许NULL。来源错误与已应用状态须可区分。
- /api、/adminapi、/outapi分别沿用原认证与权限；OutAPI需要其原生token。后台admin token不能当手机用户token。
- 前端API地址使用实际template/uni-app/config/app.js和管理端.env.dev/.env.production；不要照旧README猜config/api.js。新增接口、路由、权限需一起验收，接口返回统一沿原JSON/异常机制。
- 密码、token、AppSecret、商户/云存储密钥留.env或安全私有存储；禁止写入代码、文档、Git、日志/API响应。AppID只是公开标识，不代替密钥或实际授权。

## 编码、Git与阶段边界

沿用PHP PSR-2、现有命名/注释/异常规范和Vue 2风格。新增代码写清来源边界、单位和必要注释；不自动全库格式化，不改无关文件，不升级依赖、不删除有效代码。

每次先git status / diff / log，保留有效未提交成果；默认工作分支codex/前缀，遵从用户明确分支。阶段独立测试与commit，禁止强推或覆盖远程已有main，未经授权不创建PR/部署/发消息。

严格执行原六阶段，不重设计：

1. 环境及源码、数据库、订单与采集结构分析。
2. 三映射、简单价格、图片、5–10商品样本转换与测试。
3. 商品创建/更新、SKU/库存/价格、来源上下架，扩至全量。
4. 30分钟增量调度、锁、失败重试、日志、后台开关/周期/立即同步。
5. 原生微信登录→商品/SKU→购物车→地址→下单支付→后台发货物流→退款流程验收。
6. 统计/分销入口、手机商家管理、UI与性能。

每阶段结束停止，等待用户“开始 Phase N”。交接、分析和方案不授权下一阶段；当前允许范围查HANDOFF和用户最新指令。

禁止提前做三级分类大改、ERP、多平台同步、多商户、复杂会员/营销/BI、AI推荐、直播、社区、积分商城或APP。不因架构完整而添加系统。

## 测试、构建与启动命令

从仓库根执行；php/node需为本机准备好的版本，不把某台电脑绝对路径写进业务代码。

~~~powershell
git status --short --branch
git diff --check
php -l scripts/local/bootstrap.php
php -l scripts/local/router.php
node --check scripts/local/mini-loopback.js
~~~

当前没有LATIV业务自动测试。后续按变更增加有意义的幂等/SKU身份/价格/库存/图片测试，勿宣称语法检查等于业务通过。采集器测试在其独立项目中：

~~~text
python -B scripts/check_portability.py
python -B scripts/test_all.py
python -B ops.py health
~~~

portability为静态检查；test_all写自身release测试报告；health做SQLite quick_check可能很慢。不得把测试变成重新采集。

后台构建（准备已有锁定依赖后）：

~~~text
cd template/admin
npm ci
npm run dev
npm run build
~~~

本仓库无npm run lint，npm run eslint带--fix，不在交接任务中全库执行。UniApp package.json无构建scripts，用HBuilderX运行/发行微信小程序；不要编造npm run build:mp-weixin。依赖恢复按lock，禁止composer update/npm update。

当前Windows启动/停止：

~~~powershell
& ./scripts/local/start.ps1
& ./scripts/local/stop.ps1
~~~

这些脚本依赖未入Git的运行库、初始化数据库与本机配置；新电脑必须先按docs/LOCAL_REBUILD.md重建，不能只clone后直接bootstrap。已有数据库禁止重新初始化/覆盖。Linux的原生php think timer/workerman/queue命令仅在对应阶段和环境授权后启动，不能在交接时启用。

## 接手顺序

先读HANDOFF.md，确认交接分支与本机/新机状态，再核验git和只读数据。不要重新规划整套商城。没有明确“开始 Phase 2”时保持冻结；获授权后先做Phase 2的三映射及只读来源边界，按5–10样本验证，不提前改SKU核心/导全量/开定时。
