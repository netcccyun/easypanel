# Easypanel面板优化版

这个是集成在 [Kangle一键脚本](http://kangle.cccyun.cn/) 中的Easypanel源码，是基于Kangle官方原版，全解密后进行了深度优化的版本。

### 优化内容

- 全解密并升级smarty框架
- SSL证书可同步到cdn节点
- SSL配置页面新增"HTTP跳转到HTTPS"选项
- SSL配置页面新增"开启HTTP2"选项
- CDN可以给单个域名设置SSL证书
- 增加独立的PHP版本切换页面
- EP管理员后台增加选项：默认PHP版本、允许域名泛绑定
- 修复带有空格的文件名无法解压和重命名的问题
- CDN绑定域名可以自定义回源协议，增加tcp四层转发
- 优化防CC设置页面，支持设置IP和URL白名单
- 清除缓存页面支持批量清除
- 支持设置URL黑名单
- 绑定域名页面新增编辑按钮
- 独立的仿宝塔的伪静态设置页面

### 更新方法

- Kangle一键脚本主菜单选择单独安装/更新组件，然后选择更新Easypanel；或手动上传至服务器/vhs/kangle/nodewww/webftp目录下

### 推荐

- [彩虹云主机](https://www.cccyun.net/)
