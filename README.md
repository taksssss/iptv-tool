# 📺 IPTV工具箱
<div align="center">

<a href="https://trendshift.io/repositories/12969" target="_blank">
  <img src="https://trendshift.io/api/badge/repositories/12969" alt="IPTV Tool | Trendshift" style="width: 250px; height: 55px;" width="250" height="55"/>
</a>

[![GitHub Stars](https://img.shields.io/github/stars/taksssss/iptv-tool?style=social)](https://github.com/taksssss/iptv-tool/stargazers)
[![GitHub Forks](https://img.shields.io/github/forks/taksssss/iptv-tool?style=social)](https://github.com/taksssss/iptv-tool/network/members)
[![GitHub Issues](https://img.shields.io/github/issues/taksssss/iptv-tool)](https://github.com/taksssss/iptv-tool/issues)
[![GitHub Pull Requests](https://img.shields.io/github/issues-pr/taksssss/iptv-tool)](https://github.com/taksssss/iptv-tool/pulls)
[![License](https://img.shields.io/github/license/taksssss/iptv-tool)](https://github.com/taksssss/iptv-tool/blob/main/LICENSE)
![Docker Pulls](https://img.shields.io/docker/pulls/taksss/php-epg)
![Image Size](https://img.shields.io/docker/image-size/taksss/php-epg/latest)
</div>

IPTV 工具箱， `Docker` 部署，支持 **EPG 管理**、**直播源管理**、**台标管理**，兼容 **DIYP/百川**、 **超级直播**以及 **xmltv** 格式。

> [!TIP]
> ⚠️ 使用前请仔细阅读「管理页面」底部的[「使用说明」](/epg/assets/html/readme.md)
> 
> 原贴：[【IPTV工具箱】EPG节目单管理、直播源管理、台标管理](https://www.right.com.cn/forum/thread-8386320-1-1.html)
> 
> `xmltv` 用户使用方法：[【一键生成】匹配 M3U 文件的 XML 节目单](https://www.right.com.cn/forum/thread-8392662-1-1.html)
>
> `直播源管理` 使用方法：[【IPTV工具箱】直播源管理使用说明](https://www.right.com.cn/forum/thread-8417162-1-1.html)
>
> `自定数据源` 使用方法：[【IPTV工具箱】自定义数据源（timetv、51livetv、diyp）](https://www.right.com.cn/forum/thread-8432214-1-1.html)


## 💻 主要功能

📡 **多格式**：支持返回 DIYP/百川、超级直播以及 xmltv 格式文件。

🐳 **多架构**：提供 amd64、arm64 和 armv7 架构的 Docker 镜像。

📦 **小体积镜像**：基于 Alpine 构建，压缩后仅 20 MB。

🗃️ **数据库管理**：支持 SQLite 和 MySQL 数据库，内置 phpLiteAdmin 管理工具。

🖼️ **台标管理**：支持台标模糊匹配，支持 tvbox 接口。

➰ **直播源管理**：支持聚合 TXT/M3U 直播源、测速校验、直播源代理。

🔒 **访问权限控制**：支持设置 TOKEN 、User-Agent、IP 黑白名单。

⏱️ **缓存支持**：集成 Memcached，支持 Redis。

🔄 **频道匹配**：支持繁体中文频道匹配、模糊匹配；支持频道别名、正则表达式。

⏳ **定时任务**：支持定时更新数据。

📝 **节目单生成**：支持生成指定频道节目单并匹配 M3U 的 xmltv 格式文件。

🗂️ **兼容多种格式**：支持不同标准格式的 XMLTV 文件，支持自定义数据源。

🛠️ **文件管理**：集成 tinyfilemanager 文件管理器。

🌐 **界面设置**：包含简单易用的网页设置页面，便于操作和管理。

<picture>
  <source
    media="(prefers-color-scheme: dark)"
    srcset="/pic/management-dark.png"
  />
  <source
    media="(prefers-color-scheme: light)"
    srcset="/pic/management.png"
  />
  <img
    alt="设置页面"
    src="/pic/management.png"
  />
</picture>

> **内置正则表达式说明：**
> - 包含 `regex:`
> - 示例：
>   - `CCTV$1 => regex:/^CCTV[-\s]*(\d{1,2}(\s*P(LUS)?|[K\+])?)(?![\s-]*(美洲|欧洲)).*/i` ：将 `CCTV 1综合`、`CCTV-4K频道`、`CCTV - 5+频道`、`CCTV - 5PLUS频道` 等替换成 `CCTV1`、`CCTV4K`、`CCTV5+`、`CCTV5PLUS`（排除 `CCTV4美洲` 和 `CCTV4欧洲`）

## 📝 更新日志

### [CHANGELOG.md](./CHANGELOG.md)

## 🚀 部署步骤
### 部署/管理脚本（推荐）
```bash
bash <(curl -Ls https://raw.githubusercontent.com/taksssss/iptv-tool/master/install.sh)
```
或者
```bash
bash <(curl -Ls https://gitee.com/taksssss/iptv-tool/raw/main/install.sh)
```
或者
```bash
bash <(curl -Ls https://gcore.jsdelivr.net/gh/taksssss/iptv-tool@master/install.sh)
```

### 手动部署
```bash
docker run -d --name php-epg \
  -p 5678:80 \
  -v $HOME/epg:/htdocs/data \
  -e PHP_MEMORY_LIMIT=512M \
  -e ENABLE_FFMPEG=false \
  --restart unless-stopped \
  taksss/php-epg:latest
```

> `$HOME/epg`：默认数据目录，根据需要自行修改  
> `5678`：默认端口，根据需要自行修改（注意端口占用）  
> `-e PHP_MEMORY_LIMIT=512M`：PHP 内存限制，默认 `512M`  
> `-e ENABLE_FFMPEG=true`：启用 ffmpeg 组件  
> 无法正常拉取镜像的，可将 `taksss/php-epg:latest` 替换为 `ccr.ccs.tencentyun.com/taksss/php-epg:latest`

> **HTTPS 相关：**  
> `-p 5679:443`：映射 HTTPS 端口  
> `-e ENABLE_HTTPS=true`：启用 HTTPS  
> `-e FORCE_HTTPS=true`：强制跳转到 HTTPS  
> `-v /path/to/server.crt:/etc/ssl/certs/server.crt`：挂载证书文件  
> `-v /path/to/server.key:/etc/ssl/private/server.key`：挂载私钥文件

手动升级
```bash
docker run --rm -v /var/run/docker.sock:/var/run/docker.sock containrrr/watchtower php-epg --cleanup --run-once
```

## 🛠️ 使用步骤

1. 在浏览器中打开 `http://{服务器IP地址}:5678/manage.php`
2. **默认密码为空**，根据需要自行设置
3. 添加 `EPG 地址`， GitHub 源确保能够访问，点击 `保存配置` 保存
4. 点击 `更新数据` 拉取数据，点击 `更新日志` 查看日志，点击 `频道管理` 查看具体条目
5. 设置 `定时任务` ，点击 `保存配置` 保存，点击 `定时日志` 查看定时任务时间表

    > 建议从 `凌晨1点` 左右开始抓，很多源 `00:00 ~ 00:30` 都是无数据。
    > 隔 `6 ~ 12` 小时抓一次即可。

6. 点击 `更多设置`，选择是否 `生成xml文件`、`xml内容`，设置`匹配频道列表`
7. 测试各个接口的返回结果是否正确：

- `xmltv` 接口：`http://{服务器IP地址}:5678/index.php`
- `DIYP/百川` 接口：`http://{服务器IP地址}:5678/index.php?ch=CCTV1`
- `超级直播` 接口：`http://{服务器IP地址}:5678/index.php?channel=CCTV1`
- `tvbox` 接口：
  - `"epg":"http://{服务器IP地址}:5678/index.php?ch={name}&date={date}"`
  - `"logo":"http://{服务器IP地址}:5678/index.php?ch={name}&type=icon"`

8. 将 **`http://{服务器IP地址}:5678/index.php`** 填入 `DIYP`、`TiviMate` 等软件的 `EPG 地址栏`

- ⚠️ 直接使用 `docker run` 运行的话，可以将 `:5678/index.php` 替换为 **`:5678/`**。
- ⚠️ 部分软件不支持跳转解析 `xmltv` 文件，可直接使用 **`:5678/t.xml.gz`** 或 **`:5678/t.xml`** 访问。

> **快捷键：**
>
> - `Ctrl + S`：保存设置
> - `Ctrl + /`：对选中 EPG 地址设置（取消）注释

## ☕ Buy Me a Coffee

<picture>
  <source
    media="(prefers-color-scheme: dark)"
    srcset="/pic/buymeacofee-dark.png"
  />
  <source
    media="(prefers-color-scheme: light)"
    srcset="/pic/buymeacofee.png"
  />
  <img
    alt="Buy Me a Coffee"
    src="/pic/buymeacofee.png"
  />
</picture>

[查看打赏列表](/DONATIONS.md)

## ⭐ Star History

<picture>
  <source
    media="(prefers-color-scheme: dark)"
    srcset="https://api.star-history.com/svg?repos=taksssss/iptv-tool&type=Date&theme=dark"
  />
  <source
    media="(prefers-color-scheme: light)"
    srcset="https://api.star-history.com/svg?repos=taksssss/iptv-tool&type=Date"
  />
  <img
    alt="Star History Chart"
    src="https://api.star-history.com/svg?repos=taksssss/iptv-tool&type=Date"
  />
</picture>

## 👍 特别鸣谢
- [ChatGPT](https://chatgpt.com/)
- [celetor/epg](https://github.com/celetor/epg)
- [sparkssssssssss/epg](https://github.com/sparkssssssssss/epg)
- [Black_crow/xmlgz](https://gitee.com/Black_crow/xmlgz)
- [112114](https://diyp.112114.xyz/)
- [EPG 51zmt](http://epg.51zmt.top:8000/)
- [fanmingming/live](https://github.com/fanmingming/live)
- [wanglindl/TVlogo](https://github.com/wanglindl/TVlogo)
- [Guovin/iptv-api](https://github.com/Guovin/iptv-api)