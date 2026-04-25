// 页面加载时预加载数据，减少等待时间
document.addEventListener('DOMContentLoaded', function() {
    // 新用户弹出使用说明
    if (!localStorage.getItem('hasVisitedBefore') && 
        (!document.getElementById('xml_urls')?.value.trim())) {
        showHelpModal();
        localStorage.setItem('hasVisitedBefore', 1);
    }

    showModal('live', popup = false);
    showModal('channel', popup = false);
    showModal('update', popup = false);
    showVersionLog(doCheckUpdate = 1);
});

// 提交配置表单
document.getElementById('settingsForm').addEventListener('submit', function(event) {
    event.preventDefault();  // 阻止默认表单提交

    const fields = ['update_config', 'gen_xml', 'include_future_only', 'channel_fuzzy_match', 'ret_default', 'cht_to_chs', 'db_type', 
        'mysql_host', 'mysql_dbname', 'mysql_username', 'mysql_password', 'cached_type', 'gen_list_enable', 'check_update', 
        'token_range', 'user_agent_range', 'notify', 'access_log_enable', 'target_time_zone', 'ip_list_mode', 'live_source_config', 
        'live_template_enable', 'live_fuzzy_match', 'live_url_comment', 'live_tvg_logo_enable', 'live_tvg_id_enable', 
        'live_tvg_name_enable', 'live_source_auto_sync', 'live_channel_name_process', 'gen_live_update_time', 'm3u_icon_first', 
        'ku9_secondary_grouping', 'tag_gen_mode', 'check_speed_filter', 'min_resolution_width', 'min_resolution_height', 'urls_limit', 
        'sort_by_delay', 'check_speed_auto_sync', 'check_speed_interval_factor'];

    // 创建隐藏字段并将其添加到表单
    const form = this;
    fields.forEach(field => {
        const hiddenInput = document.createElement('input');
        hiddenInput.type = 'hidden';
        hiddenInput.name = field;
        hiddenInput.value = document.getElementById(field).value;
        form.appendChild(hiddenInput);
    });

    // 获取表单数据
    const formData = new FormData(form);

    // 执行 fetch 请求
    fetch('manage.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        const { db_type_set, interval_time, start_time, end_time } = data;
        
        let message = '配置已更新<br><br>';
        if (!db_type_set) {
            message += '<span style="color:red">MySQL 启用失败<br>数据库已设为 SQLite</span><br><br>';
            document.getElementById('db_type').value = 'sqlite';
        }
        message += interval_time === 0 
            ? "已取消定时任务" 
            : `已设置定时任务<br>开始时间：${start_time}<br>结束时间：${end_time}<br>间隔周期：${formatTime(interval_time)}`;
    
        showMessageModal(message);
    })
    .catch(() => showMessageModal('发生错误，请重试。'));
});

// 保存配置
function updateConfig(){
    document.getElementById('update_config').click();
}

// 检查数据库状况
function handleDbManagement() {
    if (document.getElementById('db_type').value === 'mysql') {
        var img = new Image();
        var timeout = setTimeout(function() {img.onerror();}, 1000); // 设置 1 秒超时
        img.onload = function() {
            clearTimeout(timeout); // 清除超时
            window.open('http://' + window.location.hostname + ':8080', '_blank');
        };
        img.onerror = function() {
            clearTimeout(timeout); // 清除超时
            showMessageModal('无法访问 phpMyAdmin 8080 端口，请自行使用 MySQL 管理工具进行管理。');
        };
        img.src = 'http://' + window.location.hostname + ':8080/favicon.ico'; // 测试 8080 端口
        return false;
    }
    return true; // 如果不是 MySQL，正常跳转
}

// 退出登录
function logout() {
    // 清除所有cookies
    document.cookie.split(";").forEach(function(cookie) {
        var name = cookie.split("=")[0].trim();
        document.cookie = name + '=;expires=Thu, 01 Jan 1970 00:00:00 GMT;path=/';
    });
    // 清除本地存储
    sessionStorage.clear();
    // 重定向到登录页面
    window.location.href = 'manage.php';
}

// Ctrl+S 保存设置
document.addEventListener("keydown", function(event) {
    if (event.ctrlKey && event.key === "s") {
        event.preventDefault(); // 阻止默认行为，如保存页面
        setGenListAndUpdateConfig();
    }
});

// Ctrl+/ 设置（取消）注释
document.getElementById('xml_urls').addEventListener('keydown', handleKeydown);
document.getElementById('sourceUrlTextarea').addEventListener('keydown', handleKeydown);
function handleKeydown(event) {
    if (event.ctrlKey && event.key === '/') {
        event.preventDefault();
        const textarea = this;
        const { selectionStart, selectionEnd, value } = textarea;
        const lines = value.split('\n');
        // 计算当前选中的行
        const startLine = value.slice(0, selectionStart).split('\n').length - 1;
        const endLine = value.slice(0, selectionEnd).split('\n').length - 1;
        // 判断选中的行是否都已注释
        const allCommented = lines.slice(startLine, endLine + 1).every(line => line.trim().startsWith('#'));
        const newLines = lines.map((line, index) => {
            if (index >= startLine && index <= endLine) {
                return allCommented ? line.replace(/^#\s*/, '') : '# ' + line;
            }
            return line;
        });
        // 更新 textarea 的内容
        textarea.value = newLines.join('\n');
        // 检查光标开始位置是否在行首
        const startLineStartIndex = value.lastIndexOf('\n', selectionStart - 1) + 1;
        const isStartInLineStart = (selectionStart - startLineStartIndex < 2);
        // 检查光标结束位置是否在行首
        const endLineStartIndex = value.lastIndexOf('\n', selectionEnd - 1) + 1;
        const isEndInLineStart = (selectionEnd - endLineStartIndex < 2);
        // 计算光标新的开始位置
        const newSelectionStart = isStartInLineStart 
            ? startLineStartIndex
            : selectionStart + newLines[startLine].length - lines[startLine].length;
        // 计算光标新的结束位置
        const lengthDiff = newLines.join('').length - lines.join('').length;
        const endLineDiff = newLines[endLine].length - lines[endLine].length;
        const newSelectionEnd = isEndInLineStart
            ? (endLineDiff > 0 ? endLineStartIndex + lengthDiff : endLineStartIndex + lengthDiff - endLineDiff)
            : selectionEnd + lengthDiff;
        // 恢复光标位置
        textarea.setSelectionRange(newSelectionStart, newSelectionEnd);
    }
}

// 禁用/启用所有源
function commentAll(id) {
    const textarea = document.getElementById(id);
    const lines = textarea.value.split('\n');
    const allCommented = lines.every(line => line.trim().startsWith('#'));
    const newLines = lines.map(line => {
        return allCommented ? line.replace(/^#\s*/, '') : '# ' + line;
    });
    textarea.value = newLines.join('\n');
}

// 格式化时间
function formatTime(seconds) {
    const formattedHours = String(Math.floor(seconds / 3600));
    const formattedMinutes = String(Math.floor((seconds % 3600) / 60));
    return `${formattedHours}小时${formattedMinutes}分钟`;
}

// 统一模态框打开函数
function openModal(modal) {
    if (!modal) return;
    document.body.style.overflow = "hidden";
    modal.style.cssText = `display:block;z-index:${zIndex++}`;
    modal.onmousedown = e => {
        if (e.target === modal || e.target.classList.contains("close")) {
            document.body.style.overflow = "auto";
            modal.style.display = 'none';
        }
    };
}

// 显示带消息的模态框
function showModalWithMessage(modalId, messageId = '', message = '') {
    const modal = document.getElementById(modalId);
    if (messageId) {
        const el = document.getElementById(messageId);
        el && (el.tagName === 'TEXTAREA' ? el.value = message : el.innerHTML = message);
    }
    openModal(modal);
}

// 显示消息模态框
function showMessageModal(message) {
    showModalWithMessage("messageModal", "messageModalMessage", message);
}

let zIndex = 100;
// 显示模态框公共函数
function showModal(type, popup = true, data = '') {
    var modal, logSpan, logContent;
    switch (type) {
        case 'epg':
            modal = document.getElementById("epgModal");
            fetchData("manage.php?get_epg_by_channel=1&channel=" + encodeURIComponent(data.channel) + "&date=" + data.date, updateEpgContent);

            // 更新日期的点击事件
            const updateDate = function(offset) {
                const currentDate = new Date(document.getElementById("epgDate").innerText);
                currentDate.setDate(currentDate.getDate() + offset);
                const newDateString = currentDate.toISOString().split('T')[0];
                fetchData(`manage.php?get_epg_by_channel=1&channel=${encodeURIComponent(data.channel)}&date=${newDateString}`, updateEpgContent);
                document.getElementById("epgDate").innerText = newDateString;
            };

            // 前一天和后一天的点击事件
            document.getElementById('prevDate').onclick = () => updateDate(-1);
            document.getElementById('nextDate').onclick = () => updateDate(1);

            break;

        case 'update':
            modal = document.getElementById("updatelogModal");
            fetchData('manage.php?get_update_logs=1', updateLogTable);
            break;
        case 'cron':
            modal = document.getElementById("cronlogModal");
            fetchData('manage.php?get_cron_logs=1', updateCronLogContent);
            break;
        case 'channel':
            modal = document.getElementById("channelModal");
            fetchData('manage.php?get_channel=1', updateChannelList);
            break;
        case 'icon':
            modal = document.getElementById("iconModal");
            fetchData('manage.php?get_icon=1', updateIconList);
            break;
        case 'allicon':
            modal = document.getElementById("iconModal");
            fetchData('manage.php?get_icon=1&get_all_icon=1', updateIconList);
            break;
        case 'channelbindepg':
            modal = document.getElementById("channelBindEPGModal");
            fetchData('manage.php?get_channel_bind_epg=1', updateChannelBindEPGList);
            break;
        case 'channelmatch':
            modal = document.getElementById("channelMatchModal");
            fetchData('manage.php?get_channel_match=1', updateChannelMatchList);
            break;
        case 'live':
            modal = document.getElementById("liveSourceManageModal");
            // 重置数据并加载第一页
            allLiveData = [];
            filteredLiveData = [];
            currentPage = 1;
            window.liveDataMap = new Map();
            window.loadedPages = new Set();
            window.pageDataMap = new Map();
            window.clientModifiedTags = new Set();
            window.currentSearchKeyword = ''; // 清除搜索关键词
            fetchData(`manage.php?get_live_data=1&page=1&per_page=${rowsPerPage}`, updateLiveSourceModal);
            break;
        case 'chekspeed':
            modal = document.getElementById("checkSpeedModal");
            break;
        case 'morelivesetting':
            modal = document.getElementById("moreLiveSettingModal");
            break;
        case 'moresetting':
            modal = document.getElementById("moreSettingModal");
            fetchData('manage.php?get_gen_list=1', updateGenList);
            break;
        default:
            console.error('Unknown type:', type);
            break;
    }
    if (!popup) {
        return;
    }
    openModal(modal);
}

function fetchData(endpoint, callback) {
    fetch(endpoint)
        .then(response => response.json())
        .then(data => callback(data))
        .catch(error => {
            console.error('Error fetching log:', error);
            callback([]);
        });
}

// 显示 update.php、check.php 执行结果
function showExecResult(fileName, callback, fullSize = true) {
    showMessageModal('');

    const modal = document.getElementById('messageModal');
    const modalContent = modal.querySelector('.message-modal-content');

    if (fullSize) {
        modalContent.classList.add('fullsize-modal');
    }

    const messageContainer = document.getElementById('messageModalMessage');
    messageContainer.innerHTML = ''; // 清空 messageContainer，避免内容重复

    const wrapper = document.createElement('div');
    if (fullSize) {
        wrapper.style.width = '1000px';
        wrapper.style.height = '504px';
    } else {
        wrapper.style.maxWidth = '600px';
    }
    wrapper.style.overflow = 'auto';
    wrapper.style.whiteSpace = 'nowrap'
    wrapper.id = "execLog";
    messageContainer.appendChild(wrapper);

    // 创建 XMLHttpRequest 对象
    const xhr = new XMLHttpRequest();
    xhr.open('GET', fileName, true);

    // 处理接收到的数据
    xhr.onprogress = function () {
        wrapper.innerHTML = xhr.responseText;
        wrapper.scrollTop = wrapper.scrollHeight;
    };

    xhr.onload = function () {
        if (xhr.status === 200 && typeof callback === 'function') {
            callback();
        } else if (xhr.status !== 200) {
            wrapper.innerHTML += '<p>检测失败，请检查服务器。</p>';
        }
    };

    xhr.onerror = function () {
        wrapper.innerHTML += '<p>请求出错，请检查网络连接。</p>';
    };

    xhr.send();

    modal.onmousedown = e => {
        if (e.target === modal || e.target.classList.contains("close")) {
            document.body.style.overflow = "auto";
            modal.style.display = 'none';
            modalContent.classList.remove('fullsize-modal');
        }
    };
}

// 显示版本更新日志
function showVersionLog(doCheckUpdate = 0) {
    fetch(`manage.php?get_version_log=1&do_check_update=${doCheckUpdate}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (!doCheckUpdate || data.is_updated) {
                    showModalWithMessage("versionLogModal", "versionLogMessage", data.content);
                }
            } else {
                showMessageModal(data.message || '获取版本日志失败');
            }
        })
        .catch(() => {
            showMessageModal('无法获取版本日志，请稍后重试');
        });
}

// 显示使用说明
function showHelpModal() {
    fetch("manage.php?get_readme_content=1")
        .then(response => response.json())
        .then(data => {
            showModalWithMessage("helpModal", "helpMessage", data.content);
        });
}

// 显示打赏图片
function showDonationImage() {
    const isDark = document.body.classList.contains('dark');
    const img = isDark ? 'assets/img/buymeacofee-dark.png' : 'assets/img/buymeacofee.png';

    showMessageModal('');
    messageModalMessage.innerHTML = `
        <div class="modal-inner">
            <img src="${img}" style="max-width:100%; display:block; margin: 0 auto; margin-top:55px;">
            <p style="margin-top:10px; text-align:center;">感谢鼓励！</p>
        </div>
    `;
}

// 更新 EPG 内容
function updateEpgContent(epgData) {
    document.getElementById('epgTitle').innerHTML = epgData.channel;
    document.getElementById('epgSource').innerHTML = `来源：<a href="${epgData.source}" target="_blank" style="word-break: break-all;">${epgData.source}</a>`;
    document.getElementById('epgDate').innerHTML = epgData.date;
    var epgContent = document.getElementById("epgContent");
    epgContent.value = epgData.epg;
    epgContent.scrollTop = 0;
}

// 更新日志表格
function updateLogTable(logData) {
    var logTableBody = document.querySelector("#logTable tbody");
    logTableBody.innerHTML = '';

    logData.forEach(log => {
        var row = document.createElement("tr");
        row.innerHTML = `
            <td>${new Date(log.timestamp).toLocaleString('zh-CN').replace(' ', '<br>')}</td>
            <td>${log.log_message}</td>
        `;
        logTableBody.appendChild(row);
    });
    var logTableContainer = document.getElementById("log-table-container");
    logTableContainer.scrollTop = logTableContainer.scrollHeight;
}

// 更新 cron 日志内容
function updateCronLogContent(logData) {
    var logContent = document.getElementById("cronLogContent");
    logContent.value = logData.map(log => 
        `[${new Date(log.timestamp).toLocaleString('zh-CN', {
            month: '2-digit', day: '2-digit', 
            hour: '2-digit', minute: '2-digit', second: '2-digit', 
            hour12: false 
        })}] ${log.log_message}`)
    .join('\n');
    logContent.scrollTop = logContent.scrollHeight;
}

// 确认关闭提示
function confirmSelect(el, msg, trigger = '0') {
    let prev = el.value;
    el.onfocus = () => prev = el.value;
    el.onchange = () => {
        if (el.value === trigger && !confirm(msg)) {
            el.value = prev;
        } else {
            prev = el.value;
        }
    };
}
const confirmConfigs = [
    {
        id: 'cht_to_chs',
        message: '关闭后将不支持繁简频道匹配，是否继续？'
    },
    {
        id: 'channel_fuzzy_match',
        message: '关闭后EPG、台标、直播频道将不进行模糊匹配，是否继续？'
    }
];
confirmConfigs.forEach(({ id, message }) => {
    const el = document.getElementById(id);
    if (el) {
        confirmSelect(el, message);
    }
});

// 修改数据库相关信息
function changeDbType(selectElem) {
    if (selectElem.value === 'sqlite') return;
    showModalWithMessage('mysqlConfigModal');
}

// 修改数据缓存相关信息
function changeCachedType(selectElem) {
    if (selectElem.value === 'memcached') return;
    showModalWithMessage('redisConfigModal');
    setTimeout(() => {
        redisConfigModal.querySelector('.close')?.addEventListener('mousedown', () => selectElem.value = 'memcached');
        window.addEventListener('mousedown', function handler(e) {
            if (e.target === redisConfigModal) {
                selectElem.value = 'memcached';
                window.removeEventListener('mousedown', handler);
            }
        });
    });
}

// 通用：将字段写入 config.json
function saveConfigField(params) {
    params.update_config_field = 1;
    return fetch('manage.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams(params)
    }).then(r => r.json());
}

// 更新并测试 Redis 账号信息
async function saveAndTestRedisConfig() {
    try {
        // 保存配置
        await saveConfigField({
            'redis[host]': document.getElementById('redis_host').value.trim(),
            'redis[port]': document.getElementById('redis_port').value.trim(),
            'redis[password]': document.getElementById('redis_password').value.trim()
        });
  
        // 测试连接
        const res = await fetch('manage.php?test_redis=1');
        const data = await res.json();
    
        document.getElementById('cached_type').value = data.success ? 'redis' : 'memcached';
        showMessageModal(data.success ? '配置已保存，Redis 连接成功' : 'Redis 连接失败，已恢复为 Memcached');
    } catch {
        document.getElementById('cached_type').value = 'memcached';
        showMessageModal('请求失败，已恢复为 Memcached');
    }
}

let accessLogMinId = 0, accessLogMaxId = 0, accessLogTimer = null, isLoadingOlderLogs = false;

// 显示访问日志
function showAccessLogModal() {
    const box = document.getElementById("accessLogContent");
    const modal = document.getElementById("accesslogModal");
    
    // 重置状态
    accessLogMinId = 0;
    accessLogMaxId = 0;
    isLoadingOlderLogs = false;
    
    // 初始加载最新100条
    const loadInitial = () => {
        fetch('manage.php?get_access_log=1&limit=100')
            .then(r => r.json())
            .then(d => {
                if (!d.success) return;

                // 将数据库中已知的归属地写入内存缓存
                if (d.locations) Object.assign(ipLocationCache, d.locations);
                
                let content = '';
                if (d.logs && d.logs.length > 0) {
                    d.logs.forEach(log => {
                        content += formatLogLine(log.text) + '\n';
                    });
                    accessLogMinId = d.min_id;
                    accessLogMaxId = d.max_id;
                }
                
                const hasMoreIndicator = d.has_more ? '<div id="loadMoreLogs" style="text-align:center;padding:10px;cursor:pointer;color:#888;">向上滚动加载更早的日志...</div>' : '';
                box.innerHTML = hasMoreIndicator + `<pre id="accessLogPre">${content}</pre><div>持续监听新日志中...</div>`;
                box.scrollTop = box.scrollHeight; // 滚动到底部
                
                // 开始轮询新日志
                if (!accessLogTimer) {
                    accessLogTimer = setInterval(pollNewLogs, 1000);
                }
            });
    };
    
    // 轮询新日志
    const pollNewLogs = () => {
        if (accessLogMaxId === 0) return;
        
        fetch(`manage.php?get_access_log=1&after_id=${accessLogMaxId}`)
            .then(r => r.json())
            .then(d => {
                if (!d.success || !d.changed || !d.logs || d.logs.length === 0) return;

                // 将数据库中已知的归属地写入内存缓存
                if (d.locations) Object.assign(ipLocationCache, d.locations);
                
                const pre = document.getElementById('accessLogPre');
                if (!pre) return;
                
                const atBottom = box.scrollTop + box.clientHeight >= box.scrollHeight - 50;
                
                let newContent = '';
                d.logs.forEach(log => {
                    newContent += formatLogLine(log.text) + '\n';
                });
                
                pre.innerHTML += newContent;
                accessLogMaxId = d.max_id;
                
                // 如果用户在底部，自动滚动
                if (atBottom) {
                    box.scrollTop = box.scrollHeight;
                }
            });
    };
    
    // 加载更早的日志
    const loadOlderLogs = () => {
        if (isLoadingOlderLogs || accessLogMinId === 0) return;
        
        const loadMoreDiv = document.getElementById('loadMoreLogs');
        if (!loadMoreDiv) return;
        
        isLoadingOlderLogs = true;
        loadMoreDiv.textContent = '加载中...';
        
        fetch(`manage.php?get_access_log=1&before_id=${accessLogMinId}&limit=100`)
            .then(r => r.json())
            .then(d => {
                if (!d.success || !d.logs || d.logs.length === 0) {
                    loadMoreDiv.textContent = '没有更早的日志了';
                    isLoadingOlderLogs = false;
                    return;
                }

                // 将数据库中已知的归属地写入内存缓存
                if (d.locations) Object.assign(ipLocationCache, d.locations);
                
                const pre = document.getElementById('accessLogPre');
                if (!pre) return;
                
                const oldScrollHeight = box.scrollHeight;
                
                let olderContent = '';
                d.logs.forEach(log => {
                    olderContent += formatLogLine(log.text) + '\n';
                });
                
                pre.innerHTML = olderContent + pre.innerHTML;
                accessLogMinId = d.min_id;
                
                // 保持滚动位置
                box.scrollTop = box.scrollHeight - oldScrollHeight;
                
                if (!d.has_more) {
                    loadMoreDiv.textContent = '没有更早的日志了';
                } else {
                    loadMoreDiv.textContent = '向上滚动加载更早的日志...';
                }
                
                isLoadingOlderLogs = false;
            });
    };
    
    // 监听滚动事件，接近顶部时加载更早日志
    box.onscroll = () => {
        if (box.scrollTop < 100) {
            loadOlderLogs();
        }
    };
    
    modal.style.zIndex = zIndex++;
    modal.style.display = "block";
    loadInitial();
    document.body.style.overflow = "hidden";

    modal.onmousedown = e => {
        if (e.target === modal || e.target.classList.contains("close")) {
            document.body.style.overflow = "auto";
            modal.style.display = "none";
            clearInterval(accessLogTimer);
            accessLogTimer = null;
            box.onscroll = null;
        }
    };
}

// 格式化日志行
function formatLogLine(text) {
    const ipRegex = /\[(\d{1,3}(?:\.\d{1,3}){3})\]/g;

    // 替换 IP 为可点击链接
    const bracketCount = (text.match(/\[/g) || []).length;
    let result = text;
    if (bracketCount <= 3) {
        result = text.replace(ipRegex, (match, ip) => {
            return `[<a href="#" onclick="queryIpLocation('${ip}', true); return false;">${ip}</a>]`;
        });
    }

    // 如果包含「访问被拒绝」，整行加粗+标红
    if (text.includes('访问被拒绝')) {
        result = `<span style="color:red; font-weight:bold; user-select:text;">${result}</span>`;
    }

    return result;
}

let currentSourceOnly = 0;

// 访问日志统计
function showAccessStats(sourceOnly = 0) {
    currentSourceOnly = sourceOnly;

    const modal = document.getElementById("accessStatsModal");
    const title = modal.querySelector("h2");
    title.textContent = sourceOnly ? "直播源访问统计" : "访问统计";

    clearInterval(accessLogTimer);
    accessLogTimer = null;

    modal.style.zIndex = zIndex++;
    modal.style.display = "block";
    loadAccessStats();
    document.body.style.overflow = "hidden";

    modal.onmousedown = e => {
        if (e.target === modal || e.target.classList.contains("close")) {
            modal.style.display = "none";
            document.body.style.overflow = "auto";
            showAccessLogModal();
        }
    };
}

let currentSort = { column: 'total', order: 'desc' };
let cachedData = { ipData: [], dates: [], rawStats: {} };
let ipLocationCache = {};

function loadAccessStats() {
    const tbody = document.querySelector("#accessStatsTable tbody");
    tbody.innerHTML = `<tr><td colspan="99">加载中...</td></tr>`;

    fetch(`manage.php?get_access_stats=1&source_only=${currentSourceOnly}`)
        .then(res => res.json())
        .then(d => {
            if (!d.success) return;
            // 将数据库中已知的归属地写入内存缓存
            (d.ipData || []).forEach(row => {
                if (row.location) ipLocationCache[row.ip] = row.location;
            });
            cachedData = { ipData: d.ipData, dates: d.dates };
            renderAccessStatsTable();
        });
}

function renderAccessStatsTable() {
    const table = document.getElementById("accessStatsTable");
    const thead = table.querySelector("thead");
    const tbody = table.querySelector("tbody");
    const { ipData, dates } = cachedData;

    if (ipData.length === 0) {
        tbody.innerHTML = `<tr><td colspan="99">暂无数据</td></tr>`;
        return;
    }

    // 排序逻辑
    ipData.sort((a, b) => {
        const { column, order } = currentSort;
        let result;

        if (column === 'ip') {
            result = a.ip.localeCompare(b.ip);
        } else if (column === 'total') {
            result = a.total - b.total;
        } else if (column === 'deny') {
            result = a.deny - b.deny;
        } else {
            const i = dates.indexOf(column);
            result = a.counts[i] - b.counts[i];
        }

        return order === 'asc' ? result : -result;
    });

    // 渲染表头
    thead.innerHTML = renderTableHeader(dates);

    // 渲染表体
    tbody.innerHTML = ipData.map(row => renderTableRow(row)).join('');
}

function renderTableHeader(dates) {
    const arrow = col => currentSort.column === col ? (currentSort.order === 'asc' ? ' ▲' : ' ▼') : '';
    return `
        <tr>
            <th onclick="sortByColumn('ip')">IP地址${arrow('ip')}</th>
            <th>归属地</th>
            ${dates.map(date => `<th onclick="sortByColumn('${date}')">${date.slice(5)}${arrow(date)}</th>`).join('')}
            <th onclick="sortByColumn('deny')">拒绝${arrow('deny')}</th>
            <th onclick="sortByColumn('total')">总计${arrow('total')}</th>
            <th>操作</th>
        </tr>
    `;
}

function renderTableRow({ ip, counts, total, deny }) {
    const countCells = counts.map(c => `<td>${c}</td>`).join('');
    const locationCell = ipLocationCache[ip]
        ? `<span>${ipLocationCache[ip]}</span>`
        : `<a href="#" onclick="queryIpLocation('${ip}'); return false;">点击查询</a>`;
    return `
        <tr>
            <td><a href="#" onclick="filterLogByIp('${ip}'); return false;">${ip}</a></td>
            <td id="loc-${ip}">${locationCell}</td>
            ${countCells}
            <td>${deny}</td>
            <td>${total}</td>
            <td>
                <button onclick="addIp('${ip}','black')" style="width: 30px; padding: 1px;">黑</button>
                <button onclick="addIp('${ip}','white')" style="width: 30px; padding: 1px;">白</button>
            </td>
        </tr>
    `;
}

function queryIpLocation(ip, showModal = false) {
    const cell = document.getElementById(`loc-${ip}`);

    if (cell) cell.textContent = "查询中...";
    if (showModal) showMessageModal(`正在查询 ${ip} 的归属地，请稍候...`);

    // 使用 JSONP 查询（仅限 IPv4 格式）
    if (!/^(\d{1,3}\.){3}\d{1,3}$/.test(ip)) return;
    const callbackName = "jsonp_cb_" + ip.replace(/\./g, "_");
    window[callbackName] = function(d) {
        let location = "未找到";
        if (d && d.data && d.data[0] && d.data[0].location) {
            location = d.data[0].location;
            ipLocationCache[ip] = location;

            // 将结果写入数据库
            fetch('manage.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ save_ip_location: 1, ip, location })
            });
        }

        if (cell) cell.textContent = location;
        if (showModal) {
            showMessageModal(`${ip} 的归属地：${location}`);
        
            const pre = document.getElementById('accessLogPre');
            if (pre) {
                const pattern = new RegExp(`\\[<a[^>]*>${ip}<\\/a>\\]`, 'g');
                pre.innerHTML = pre.innerHTML.replace(pattern, `[${ip}] [${location}]`);
            }
        }

        delete window[callbackName];
    };

    const script = document.createElement("script");
    script.src = `https://opendata.baidu.com/api.php?co=&resource_id=6006&oe=utf8&query=${ip}&cb=${callbackName}`;
    document.body.appendChild(script);
}

function filterLogByIp(ip) {
    // 从服务器获取该IP的所有日志
    fetch(`manage.php?filter_access_log_by_ip=1&source_only=${currentSourceOnly}&ip=${encodeURIComponent(ip)}`)
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                showMessageModal(`查询失败：${data.message || '未知错误'}`);
                return;
            }

            // 如果数据库有归属地，更新内存缓存
            if (data.location) ipLocationCache[ip] = data.location;
            
            let content = '';
            if (data.logs && data.logs.length > 0) {
                content = data.logs.map(log => log.text).join('<br>');
            } else {
                content = `无记录：${ip}`;
            }

            const locationInfo = data.location ? ` (${data.location})` : '';
            showMessageModal(`
                <div style="text-align:left; margin-bottom:10px;">IP: ${ip}${locationInfo} - 共 ${data.count} 条记录</div>
                <div id="filteredLog" style="width:1000px; height:504px; overflow:auto; font-family:monospace; white-space:pre;">${content}</div>
            `);
            const d = document.getElementById("filteredLog");
            if (d) d.scrollTop = d.scrollHeight;
        })
        .catch(error => {
            showMessageModal(`查询出错：${error}`);
        });
}

function addIp(ip, type) {
    const listName = type === 'white' ? '白名单' : '黑名单';
    if (!confirm(`确定将 ${ip} 加入${listName}？`)) return;

    const file = type === 'white' ? 'ipWhiteList.txt' : 'ipBlackList.txt';

    fetch(`manage.php?get_ip_list=1&file=${file}`)
        .then(res => res.json())
        .then(data => {
            const set = new Set(data.list || []);
            set.add(ip);

            return fetch('manage.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    save_content_to_file: 1,
                    file_path: `/data/${file}`,
                    content: [...set].join('\n')
                })
            });
        })
        .then(res => res.json())
        .then(data => showMessageModal(data.success ? `已加入${listName}` : '保存失败'));
}

function sortByColumn(col) {
    if (currentSort.column === col) {
        currentSort.order = currentSort.order === 'asc' ? 'desc' : 'asc';
    } else {
        currentSort = {
            column: col,
            order: col === 'ip' ? 'asc' : 'desc'
        };
    }
    renderAccessStatsTable();
}

// 清空访问日志
function clearAccessLog() {
    if (!confirm('确定清空访问日志及 IP 归属地数据？')) return;
    fetch('manage.php?clear_access_log=1')
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert('数据已清空');
                document.getElementById('accessLogContent').innerHTML = '';
                ipLocationCache = {};
            } else alert('清空失败');
        }).catch(() => alert('请求失败'));
}

// 下载访问日志
function downloadAccessLog() {
    const a = document.createElement('a');
    a.href = 'manage.php?download_access_log=1';
    a.download = 'access.log';
    a.click();
}

// 显示 IP 列表模态框
function showIpModal() {
    const mode = document.getElementById('ip_list_mode').value;
    
    saveConfigField({ ip_list_mode: mode });

    if (mode === '0') return;
    const file = (mode === '1' ? 'ipWhiteList.txt' : 'ipBlackList.txt');
    const modeName = (mode === '1' ? '白名单' : '黑名单');

    fetch(`manage.php?get_ip_list=1&file=${encodeURIComponent(file)}`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showModalWithMessage("ipModal", "ipListTextarea", data.list.join('\n'));
                const textarea = document.getElementById('ipListTextarea');
                textarea.dataset.file = file;
                textarea.focus();
                textarea.selectionStart = textarea.selectionEnd = textarea.value.length;
                const h2 = document.querySelector('#ipModal h2'); // 修改标题内容
                h2.textContent = `IP 列表：${modeName}`;
            } else {
                showMessageModal('读取 IP 列表失败');
            }
        });
}

// 保存 IP 列表
function saveIpList() {
    const textarea = document.getElementById('ipListTextarea');
    const file = textarea.dataset.file || 'ipBlackList.txt';

    const lines = textarea.value.split('\n').map(s => s.trim()).filter(Boolean);

    // 支持单个 IPv4、IPv6、IPv4 CIDR、IPv4 通配符（可选）
    const patterns = [
        /^(\*|25[0-5]|2\d{1,2}|1\d{1,2}|\d{1,2})(\.(\*|25[0-5]|2\d{1,2}|1\d{1,2}|\d{1,2})){3}$/,  // IPv4 + 通配符
        /^(\d{1,3}\.){3}\d{1,3}\/([0-9]|[1-2][0-9]|3[0-2])$/,                                     // CIDR
        /^([0-9a-fA-F]{0,4}:){2,7}[0-9a-fA-F]{0,4}$/                                              // IPv6（简化）
    ];
    
    const valid = [], invalid = [];
    
    for (const ip of [...new Set(lines)]) {
        (patterns.some(re => re.test(ip)) ? valid : invalid).push(ip);
    }    

    textarea.value = valid.join('\n');

    if (invalid.length) alert(`以下 IP 无效，已忽略：\n${invalid.join('\n')}`);

    fetch('manage.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            save_content_to_file: 1,
            file_path: `/data/${file}`,
            content: textarea.value
        })
    })
    .then(res => res.json())
    .then(data => showMessageModal(data.success ? '保存成功' : '保存失败'));
}

// 显示频道别名列表
function updateChannelList(channelsData) {
    const channelTitle = document.getElementById('channelModalTitle');
    channelTitle.innerHTML = `频道列表<span style="font-size: 18px;">（总数：${channelsData.count}）</span>`; // 更新频道总数
    document.getElementById('channelTable').dataset.allChannels = JSON.stringify(channelsData.channels); // 将原始频道和映射后的频道数据存储到 dataset 中
    filterChannels('channel'); // 生成数据
}

// 显示台标列表
function updateIconList(iconsData) {
    const channelTitle = document.getElementById('iconModalTitle');
    channelTitle.innerHTML = `频道列表<span style="font-size: 18px;">（总数：${iconsData.count}）</span>`; // 更新频道总数
    document.getElementById('iconTable').dataset.allIcons = JSON.stringify(iconsData.channels); // 将频道名和台标地址存储到 dataset 中
    filterChannels('icon'); // 生成数据
}

// 显示频道绑定 EPG 列表
function updateChannelBindEPGList(channelBindEPGData) {
    // 创建并添加隐藏字段
    const channelBindEPGInput = document.createElement('input');
    channelBindEPGInput.type = 'hidden';
    channelBindEPGInput.name = 'channel_bind_epg';
    document.getElementById('settingsForm').appendChild(channelBindEPGInput);

    document.getElementById('channelBindEPGTable').dataset.allChannelBindEPG = JSON.stringify(channelBindEPGData);
    var channelBindEPGTableBody = document.querySelector("#channelBindEPGTable tbody");
    var allChannelBindEPG = JSON.parse(document.getElementById('channelBindEPGTable').dataset.allChannelBindEPG);
    channelBindEPGInput.value = JSON.stringify(allChannelBindEPG);

    // 清空现有表格
    channelBindEPGTableBody.innerHTML = '';

    allChannelBindEPG.forEach(channelbindepg => {
        var row = document.createElement('tr');
        row.innerHTML = `
            <td>${String(channelbindepg.epg_src)}</td>
            <td contenteditable="true">${channelbindepg.channels}</td>
        `;

        row.querySelector('td[contenteditable]').addEventListener('input', function() {
            channelbindepg.channels = this.textContent;
            document.getElementById('channelBindEPGTable').dataset.allChannelBindEPG = JSON.stringify(allChannelBindEPG);
            channelBindEPGInput.value = JSON.stringify(allChannelBindEPG);
        });

        channelBindEPGTableBody.appendChild(row);
    });
}

// 显示频道匹配结果
function updateChannelMatchList(channelMatchdata) {
    const channelMatchTableBody = document.querySelector("#channelMatchTable tbody");
    channelMatchTableBody.innerHTML = '';

    const typeOrder = { '未匹配': 1, '反向模糊': 2, '正向模糊': 3, '别名/忽略': 4, '精确匹配': 5 };

    // 处理并排序匹配数据
    const sortedMatches = Object.values(channelMatchdata)
        .flat()
        .sort((a, b) => typeOrder[a.type] - typeOrder[b.type]);

    // 创建表格行
    sortedMatches.forEach(({ ori_channel, clean_channel, match, type }) => {
        const matchType = type === '精确匹配' ? '' : type;
        const row = document.createElement("tr");
        row.innerHTML = `
            <td>${ori_channel}</td>
            <td>${clean_channel}</td>
            <td>${match || ''}</td>
            <td>${matchType}</td>
        `;
        channelMatchTableBody.appendChild(row);
    });

    document.getElementById("channel-match-table-container").style.display = 'block';
}

// 显示限定频道列表
function updateGenList(genData) {
    const gen_list_text = document.getElementById('gen_list_text');
    if(!gen_list_text.value) {
        gen_list_text.value = genData.join('\n');
    }
}

// 显示指定页码的数据
function displayPage(data, page) {
    const tableBody = document.querySelector('#liveSourceTable tbody');
    tableBody.innerHTML = ''; // 清空表格内容

    // 如果需要的数据不在本地缓存中，从服务器加载
    if (data === filteredLiveData && !isPageDataLoaded(page)) {
        loadPageDataFromServer(page);
        return;
    }
    
    // 从pageDataMap获取当前页的数据
    const displayData = window.pageDataMap && window.pageDataMap.get(page) 
        ? window.pageDataMap.get(page) 
        : [];

    if (displayData.length === 0) {
        tableBody.innerHTML = '<tr><td colspan="12">暂无数据</td></tr>';
        return;
    }

    // 列索引和对应字段的映射
    const columns = ['groupPrefix', 'groupTitle', 'channelName', 'streamUrl', 'iconUrl', 
                    'tvgId', 'tvgName', 'resolution', 'speed', 'disable', 'modified'];

    // 填充当前页的表格数据
    displayData.forEach((item, index) => {
        const row = document.createElement('tr');
        
        // 计算全局索引（用于行号显示）
        const globalIndex = (page - 1) * rowsPerPage + index + 1;
        
        // 检查该项是否在客户端被修改过
        const isClientModified = window.clientModifiedTags && window.clientModifiedTags.has(item.tag);
        
        row.innerHTML = `
            <td>${globalIndex}</td>
            ${columns.map((col, columnIndex) => {
                let cellContent = String(item[col] || '').replace(/&/g, "&amp;");
                let cellClass = '';
                
                // 处理 disable 和 modified 列
                if (col === 'disable' || col === 'modified') {
                    cellContent = item[col] == 1 ? '是' : '否';
                    cellClass = (col === 'disable' && item[col] == 1)
                        ? 'table-cell-disable'
                        : (col === 'modified' && item[col] == 1)
                        ? 'table-cell-modified'
                        : 'table-cell-clickable';
                }
        
                const editable = ['resolution', 'speed', 'disable', 'modified'].includes(col) ? '' : 'contenteditable="true"';
                const clickableClass = (col === 'disable' || col === 'modified') ? 'table-cell-clickable' : '';
        
                return `<td ${editable} class="${clickableClass} ${cellClass}">
                            <div class="limited-row">${cellContent}</div>
                        </td>`;
            }).join('')}
        `;

        // 为每个单元格添加事件监听器
        row.querySelectorAll('td[contenteditable="true"]').forEach((cell, columnIndex) => {
            cell.addEventListener('input', () => {
                // 直接使用item而不是通过索引查找
                if (item && item.tag) {
                    // 标记为客户端修改
                    if (window.clientModifiedTags) {
                        window.clientModifiedTags.add(item.tag);
                    }
                    
                    // 更新allLiveData中的对应项
                    const dataIndex = allLiveData.findIndex(d => d.tag === item.tag);
                    if (dataIndex >= 0) {
                        allLiveData[dataIndex][columns[columnIndex]] = cell.textContent.trim();
                        allLiveData[dataIndex]['modified'] = 1;
                    }
                    
                    // 更新Map
                    if (window.liveDataMap) {
                        const mapItem = window.liveDataMap.get(item.tag);
                        if (mapItem) {
                            mapItem[columns[columnIndex]] = cell.textContent.trim();
                            window.liveDataMap.set(item.tag, mapItem);
                        }
                    }
                    
                    // 更新pageDataMap中当前页的数据
                    if (window.pageDataMap && window.pageDataMap.has(currentPage)) {
                        const pageData = window.pageDataMap.get(currentPage);
                        const pageItemIndex = pageData.findIndex(d => d.tag === item.tag);
                        if (pageItemIndex >= 0) {
                            pageData[pageItemIndex][columns[columnIndex]] = cell.textContent.trim();
                        }
                    }
                    
                    const lastCell = cell.closest('tr').lastElementChild;
                    lastCell.textContent = '是';
                    lastCell.classList.add('table-cell-modified');
                }
            });
        });

        // 为 disable 和 modified 列添加点击事件，切换 "是/否"
        row.querySelectorAll('td.table-cell-clickable').forEach((cell, columnIndex) => {
            cell.addEventListener('click', () => {
                // 直接使用item而不是通过索引查找
                if (item && item.tag) {
                    const isDisable = columnIndex === 0;
                    const isModified = columnIndex === 1;
                    
                    if (isModified) {
                        // 修改列：切换数据库的modified字段，并同步客户端修改标记
                        const dataIndex = allLiveData.findIndex(d => d.tag === item.tag);
                        if (dataIndex >= 0) {
                            const newValue = allLiveData[dataIndex]['modified'] == 1 ? 0 : 1;
                            allLiveData[dataIndex]['modified'] = newValue;
                            
                            // 更新Map
                            if (window.liveDataMap) {
                                const mapItem = window.liveDataMap.get(item.tag);
                                if (mapItem) {
                                    mapItem['modified'] = newValue;
                                    window.liveDataMap.set(item.tag, mapItem);
                                }
                            }
                            
                            // 更新pageDataMap
                            if (window.pageDataMap && window.pageDataMap.has(currentPage)) {
                                const pageData = window.pageDataMap.get(currentPage);
                                const pageItemIndex = pageData.findIndex(d => d.tag === item.tag);
                                if (pageItemIndex >= 0) {
                                    pageData[pageItemIndex]['modified'] = newValue;
                                }
                            }
                            
                            // 标记为客户端修改
                            if (window.clientModifiedTags) {
                                window.clientModifiedTags.add(item.tag);
                            }
                            
                            cell.textContent = newValue == 1 ? '是' : '否';
                            cell.classList.toggle('table-cell-modified', newValue == 1);
                        }
                    } else if (isDisable) {
                        // Disable列：更新数据库字段并标记为客户端修改
                        const dataIndex = allLiveData.findIndex(d => d.tag === item.tag);
                        if (dataIndex >= 0) {
                            const newValue = allLiveData[dataIndex]['disable'] == 1 ? 0 : 1;
                            allLiveData[dataIndex]['disable'] = newValue;
                            allLiveData[dataIndex]['modified'] = 1;
                            
                            // 更新Map
                            if (window.liveDataMap) {
                                const mapItem = window.liveDataMap.get(item.tag);
                                if (mapItem) {
                                    mapItem['disable'] = newValue;
                                    window.liveDataMap.set(item.tag, mapItem);
                                }
                            }
                            
                            // 更新pageDataMap
                            if (window.pageDataMap && window.pageDataMap.has(currentPage)) {
                                const pageData = window.pageDataMap.get(currentPage);
                                const pageItemIndex = pageData.findIndex(d => d.tag === item.tag);
                                if (pageItemIndex >= 0) {
                                    pageData[pageItemIndex]['disable'] = newValue;
                                }
                            }
                            
                            cell.textContent = newValue == 1 ? '是' : '否';
                            cell.classList.toggle('table-cell-disable', newValue == 1);
                            
                            // 标记为客户端修改
                            if (window.clientModifiedTags) {
                                window.clientModifiedTags.add(item.tag);
                            }
                            
                            const lastCell = cell.closest('tr').lastElementChild;
                            lastCell.textContent = '是';
                            allLiveData[dataIndex]['modified'] = 1;
                            lastCell.classList.add('table-cell-modified');
                        }
                    }
                }
            });
        });
    
        tableBody.appendChild(row);
    });

    // 为单元格添加鼠标点击事件
    tableBody.addEventListener('focusin', e => {
        const td = e.target.closest('td')
        if (!td) return
        const tr = td.closest('tr')
        tr.querySelectorAll('.limited-row').forEach(d => d.classList.add('expanded'))
    })
    tableBody.addEventListener('focusout', e => {
        const td = e.target.closest('td')
        if (!td) return
        const tr = td.closest('tr')
        tr.querySelectorAll('.limited-row').forEach(d => d.classList.remove('expanded'))
    })
}

// 检查某一页的数据是否已加载到本地缓存
function isPageDataLoaded(page) {
    return window.loadedPages && window.loadedPages.has(page);
}

// 从服务器加载指定页的数据
function loadPageDataFromServer(page) {
    const selectedConfig = document.getElementById('live_source_config').value;
    const tableBody = document.querySelector('#liveSourceTable tbody');
    tableBody.innerHTML = '<tr><td colspan="12">加载中...</td></tr>';
    
    currentPage = page; // 更新当前页码
    
    // 构建URL，包含搜索关键词（如果有）
    let url = `manage.php?get_live_data=1&live_source_config=${selectedConfig}&page=${page}&per_page=${rowsPerPage}`;
    if (window.currentSearchKeyword) {
        url += `&search=${encodeURIComponent(window.currentSearchKeyword)}`;
    }
    
    fetch(url)
        .then(response => response.json())
        .then(data => {
            updateLiveSourceModal(data);
        })
        .catch(error => {
            console.error('Error loading page data:', error);
            tableBody.innerHTML = '<tr><td colspan="12">加载失败，请重试</td></tr>';
        });
}

// 创建分页控件
function setupPagination(data) {
    const paginationContainer = document.getElementById('paginationContainer');
    paginationContainer.innerHTML = ''; // 清空分页容器

    // 使用服务器端总数
    const totalItems = window.liveDataTotalCount || 0;
    const totalPages = Math.ceil(totalItems / rowsPerPage);
    
    if (totalPages <= 1) return;

    const maxButtons = 11; // 总显示按钮数，包括“<”和“>”
    const pageButtons = maxButtons - 2; // 除去 "<" 和 ">" 的按钮数

    // 创建按钮
    const createButton = (text, page, isActive = false, isDisabled = false) => {
        const button = document.createElement('button');
        button.textContent = text;
        button.className = isActive ? 'active' : '';
        button.disabled = isDisabled;
        button.onclick = () => {
            if (!isDisabled) {
                currentPage = page;
                displayPage(data, currentPage); // 更新页面显示内容
                setupPagination(data); // 更新分页控件
            }
        };
        return button;
    };

    // 前部
    paginationContainer.appendChild(createButton('<', currentPage - 1, false, currentPage === 1));
    paginationContainer.appendChild(createButton(1, 1, currentPage === 1));
    if (currentPage > 5 && totalPages > pageButtons) paginationContainer.appendChild(createButton('...', null, false, true));

    // 中部
    let startPage = Math.max(2, currentPage - Math.floor(pageButtons / 2) + 2);
    let endPage = Math.min(totalPages - 1, currentPage + Math.floor(pageButtons / 2) - 2);
    if (currentPage <= 5) { startPage = 2; endPage = Math.min(pageButtons - 2, totalPages - 1); }
    else if (currentPage >= totalPages - 4) { startPage = Math.max(totalPages - pageButtons + 3, 2); endPage = totalPages - 1; }
    for (let i = startPage; i <= endPage; i++) {
        paginationContainer.appendChild(createButton(i, i, currentPage === i));
    }

    // 后部
    if (currentPage < totalPages - 4 && totalPages > pageButtons) paginationContainer.appendChild(createButton('...', null, false, true));
    paginationContainer.appendChild(createButton(totalPages, totalPages, currentPage === totalPages));
    paginationContainer.appendChild(createButton('>', currentPage + 1, false, currentPage === totalPages));
}

let currentPage = 1; // 当前页码
let allLiveData = []; // 用于存储直播源数据
let filteredLiveData = []; // 搜索后的结果

let rowsPerPage = parseInt(localStorage.getItem('rowsPerPage')) || 100; // 每页显示的行数
document.getElementById('rowsPerPageSelect').value = rowsPerPage;

// 更改每页显示条数
document.getElementById('rowsPerPageSelect').addEventListener('change', (e) => {
    rowsPerPage = parseInt(e.target.value);
    localStorage.setItem('rowsPerPage', rowsPerPage);
    currentPage = 1; // 重置到第一页
    displayPage(filteredLiveData, currentPage);
    setupPagination(filteredLiveData);
});

// 优化搜索框中文输入
const liveSourceSearchInput = document.getElementById('liveSourceSearchInput');
let isComposingIME = false;
liveSourceSearchInput.addEventListener('compositionstart', () => isComposingIME = true);
liveSourceSearchInput.addEventListener('compositionend', () => { isComposingIME = false; filterLiveSourceData(); });
liveSourceSearchInput.addEventListener('input', () => { if (!isComposingIME) filterLiveSourceData(); });

// 根据关键词过滤数据（服务器端搜索）
function filterLiveSourceData() {
    const keyword = liveSourceSearchInput.value.trim();
    
    if (!keyword) {
        // 如果搜索为空，重新加载第一页
        currentPage = 1;
        allLiveData = [];
        filteredLiveData = [];
        window.liveDataMap = new Map();
        window.loadedPages = new Set();
        window.pageDataMap = new Map();
        const selectedConfig = document.getElementById('live_source_config').value;
        fetchData(`manage.php?get_live_data=1&live_source_config=${selectedConfig}&page=1&per_page=${rowsPerPage}`, updateLiveSourceModal);
        return;
    }
    
    // 执行服务器端搜索
    const selectedConfig = document.getElementById('live_source_config').value;
    const searchUrl = `manage.php?get_live_data=1&live_source_config=${selectedConfig}&page=1&per_page=${rowsPerPage}&search=${encodeURIComponent(keyword)}`;
    
    // 重置数据结构
    allLiveData = [];
    filteredLiveData = [];
    currentPage = 1;
    window.liveDataMap = new Map();
    window.loadedPages = new Set();
    window.pageDataMap = new Map();
    window.currentSearchKeyword = keyword; // 保存当前搜索关键词
    
    fetchData(searchUrl, updateLiveSourceModal);
}

// 更新模态框内容并初始化分页
function updateLiveSourceModal(data) {
    document.getElementById('sourceUrlTextarea').value = data.source_content || '';
    document.getElementById('liveTemplateTextarea').value = data.template_content || '';
    document.getElementById('live_source_config').innerHTML = data.config_options_html;
    
    const channels = Array.isArray(data.channels) ? data.channels : [];
    
    // 初始化客户端编辑跟踪Map（如果还未初始化）
    if (!window.clientModifiedTags) {
        window.clientModifiedTags = new Set();
    }
    
    // 初始化数据结构（如果还未初始化）
    if (!window.liveDataMap) {
        window.liveDataMap = new Map();
    }
    if (!window.loadedPages) {
        window.loadedPages = new Set();
    }
    if (!window.pageDataMap) {
        window.pageDataMap = new Map(); // 存储每页的数据
    }
    
    // 存储当前页的数据到Map中，使用tag作为key
    channels.forEach(channel => {
        if (channel.tag) {
            window.liveDataMap.set(channel.tag, channel);
        }
    });
    
    // 存储当前页的数据列表
    const currentPageNum = data.page || 1;
    window.pageDataMap.set(currentPageNum, channels);
    
    // 标记当前页已加载
    window.loadedPages.add(currentPageNum);
    
    // 设置总数和分页信息
    window.liveDataTotalCount = data.total_count;
    window.liveDataPerPage = data.per_page || 100;
    
    // 从Map重建allLiveData数组
    allLiveData = Array.from(window.liveDataMap.values());
    
    filteredLiveData = allLiveData; // 初始化过滤结果
    displayPage(filteredLiveData, currentPage); // 显示当前页数据
    setupPagination(filteredLiveData); // 初始化分页控件
}

// 更新直播源配置
function onLiveSourceConfigChange() {
    const selectedConfig = document.getElementById('live_source_config').value;
    // 重置数据
    allLiveData = [];
    filteredLiveData = [];
    currentPage = 1;
    window.liveDataMap = new Map();
    window.loadedPages = new Set();
    window.pageDataMap = new Map();
    window.clientModifiedTags = new Set();
    window.currentSearchKeyword = ''; // 清除搜索关键词
    liveSourceSearchInput.value = ''; // 清空搜索框
    // 获取第一页数据
    fetchData(`manage.php?get_live_data=1&live_source_config=${selectedConfig}&page=1&per_page=${rowsPerPage}`, updateLiveSourceModal);
}

// 上传直播源文件
document.getElementById('liveSourceFile').addEventListener('change', function() {
    const file = this.files[0];
    const allowedExtensions = ['m3u', 'txt'];
    const fileExtension = file.name.split('.').pop().toLowerCase();

    // 检查文件类型
    if (!allowedExtensions.includes(fileExtension)) {
        showMessageModal('只接受 .m3u 和 .txt 文件');
        return;
    }

    // 创建 FormData 并发送 AJAX 请求
    const formData = new FormData();
    formData.append('liveSourceFile', file);

    fetch('manage.php', { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showModal('live');
                showMessageModal('上传成功，请重新解析');
            } else {
                showMessageModal('上传失败: ' + data.message);
            }
        })
        .catch(error => showMessageModal('上传过程中发生错误：' + error));

    this.value = ''; // 重置文件输入框的值，确保可以连续上传相同文件
});

// 保存编辑后的直播源地址
function saveLiveSourceFile() {
    const source = document.getElementById('sourceUrlTextarea');
    const sourceContent = source.value.replace(/^\s*[\r\n]+/gm, '').replace(/\n$/, '');
    source.value = sourceContent;

    const liveSourceConfig = document.getElementById('live_source_config').value;
    const updateObj = {};
    updateObj[liveSourceConfig] = sourceContent.split('\n');

    // 返回 fetch 的 Promise
    return fetch('manage.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            save_content_to_file: 1,
            file_path: '/data/live/source.json',
            content: JSON.stringify(updateObj)
        })
    });
}

document.getElementById('sourceUrlTextarea').addEventListener('blur', saveLiveSourceFile);

// 保存编辑后的直播源信息
function saveLiveSourceInfo() {
    // 获取配置
    const liveSourceConfig = document.getElementById('live_source_config').value;
    const liveTvgLogoEnable = document.getElementById('live_tvg_logo_enable').value;
    const liveTvgIdEnable = document.getElementById('live_tvg_id_enable').value;
    const liveTvgNameEnable = document.getElementById('live_tvg_name_enable').value;

    // 只发送客户端修改过的记录
    const dataToSend = allLiveData.filter(item => window.clientModifiedTags && window.clientModifiedTags.has(item.tag));

    // 保存直播源信息
    fetch('manage.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            save_source_info: 1,
            live_source_config: liveSourceConfig,
            live_tvg_logo_enable: liveTvgLogoEnable,
            live_tvg_id_enable: liveTvgIdEnable,
            live_tvg_name_enable: liveTvgNameEnable,
            content: JSON.stringify(dataToSend)
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showMessageModal('保存成功<br>已生成 M3U 及 TXT 文件');
            
            // 清除客户端修改标记
            if (window.clientModifiedTags) {
                window.clientModifiedTags.clear();
            }
            
            // 刷新当前页显示
            displayPage(filteredLiveData, currentPage);
        } else {
            showMessageModal('保存失败');
        }
    })
    .catch(error => {
        showMessageModal('保存过程中出现错误: ' + error);
    });
}

// 新建或另存直播源配置
function openLiveSourceConfigDialog(isNew = 0) {
    showMessageModal('');
    document.getElementById('messageModalMessage').innerHTML = `
        <div style="width: 180px;">
            <h3>${isNew ? '新建配置' : '另存为新配置'}</h3>
            <input type="text" value="" id="newConfigName" placeholder="请输入配置名"
                style="text-align: center; font-size: 15px; margin-bottom: 15px;" />
            <div class="button-container button-container-source-setting" style="text-align: center; margin-bottom: -10px;">
                <button id="confirmBtn">确认</button>
                <button onclick="document.getElementById('messageModal').style.display='none'">取消</button>
            </div>
        </div>
    `;

    document.getElementById('newConfigName').focus();
    document.getElementById('confirmBtn').onclick = () => {
        const liveSourceConfig = document.getElementById('newConfigName').value.trim();
        if (!liveSourceConfig) {
            showMessageModal('请输入配置名');
            return;
        }
        const select = document.getElementById('live_source_config');
        const oldConfig = select.value.trim();
        if (![...select.options].some(o => o.value === liveSourceConfig)) {
            select.add(new Option(liveSourceConfig, liveSourceConfig));
        }
        select.value = liveSourceConfig;

        fetch('manage.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                create_source_config: 1,
                old_source_config: oldConfig,
                new_source_config: liveSourceConfig,
                is_new: isNew
            })
        })
        .then(() => {
            showModal('live', false);
        })
        .catch(error => {
            showMessageModal('保存过程中出现错误: ' + error);
        });
    };
}

// 删除直播源配置
function deleteSource() {
    const select = document.getElementById('live_source_config');
    const configName = select.value;

    if (configName === 'default') {
        showMessageModal('默认配置不能删除！');
        return;
    }

    showMessageModal('');
    document.getElementById('messageModalMessage').innerHTML = `
        <div style="width: 300px; text-align: center;">
            <h3>确认删除</h3>
            <p>确定删除配置 "${configName}"？此操作不可恢复。</p>
            <div class="button-container button-container-source-setting">
                <button id="confirmBtn">确认</button>
                <button id="cancelBtn">取消</button>
            </div>
        </div>
    `;

    document.getElementById('confirmBtn').onclick = () => {
        fetch(`manage.php?delete_source_config=1&live_source_config=${encodeURIComponent(configName)}`)
            .then(() => {
                const i = select.selectedIndex;
                select.remove(i);
                select.selectedIndex = i >= select.options.length ? i - 1 : i;
                onLiveSourceConfigChange();
            })
            .catch(err => showMessageModal('删除失败：' + err));
        document.getElementById('messageModal').style.display = 'none';
    };

    document.getElementById('cancelBtn').onclick = () => {
        document.getElementById('messageModal').style.display = 'none';
    };
}

// 清理未使用的直播源文件
function cleanUnusedSource() {
    fetch('manage.php?delete_unused_live_data=1')
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            parseSourceInfo(data.message);
            document.getElementById('moreLiveSettingModal').style.display = 'none';
        } else {
            showMessageModal('清理失败');
        }
    })
    .catch(error => {
        showMessageModal('Error: ' + error);
    });
}

// 显示 EPG、直播源地址
async function showUrl() {
    try {
        // 并行获取 serverUrl 和 config
        const [serverRes, configRes] = await Promise.all([
            fetch('manage.php?get_env=1'),
            fetch('manage.php?get_config=1')
        ]);

        const serverData = await serverRes.json();
        const configData = await configRes.json();

        const serverUrl  = serverData.server_url;
        const token      = configData.token.split('\n')[0];
        const tokenMd5   = configData.token_md5;
        const tokenRange = parseInt(configData.token_range, 10);
        const rewriteEnable = serverData.rewrite_enable ? true : false;
        const liveSourceElem = configData.live_source_config;
        const configValue = liveSourceElem || 'default';

        const liveTokenStr = (tokenRange === 1 || tokenRange === 3) ? `token=${token}` : '';
        const liveUrlParam = (configValue == 'default') ? '' : `url=${configValue}`;
        const liveQuery = [liveTokenStr, liveUrlParam].filter(Boolean).join('&');

        const m3uPath = rewriteEnable ? '/tv.m3u' : '/index.php?type=m3u';
        const txtPath = rewriteEnable ? '/tv.txt' : '/index.php?type=txt';
        const gzPath = rewriteEnable ? '/t.xml.gz' : '/index.php?type=gz';
        const epgQuery = (tokenRange === 2 || tokenRange === 3) ? `token=${token}` : '';

        function buildUrl(base, path, query) {
            let url = base + path;
            if (query) url += (url.includes('?') ? '&' : '?') + query;
            return url;
        }
        
        const m3uUrl = buildUrl(serverUrl, m3uPath, liveQuery);
        const txtUrl = buildUrl(serverUrl, txtPath, liveQuery);
        const gzUrl = buildUrl(serverUrl, gzPath, epgQuery);
        
        function buildCustomUrl(originalUrl, tokenMd5, mode) {
            const url = new URL(originalUrl, location.origin);
            if (tokenRange === 1 || tokenRange === 3) {
                url.searchParams.set('token', tokenMd5);
            }
            url.searchParams.set(mode, '1');
            return url.toString();
        }
        
        const proxyM3uUrl = buildCustomUrl(m3uUrl, tokenMd5, 'proxy');
        const proxyTxtUrl = buildCustomUrl(txtUrl, tokenMd5, 'proxy');
        
        const btnBase = `
            display:inline-flex;
            align-items:center;
            justify-content:center;
            height:22px;
            width:48px;
            margin-left:6px;
            border-radius:4px;
            font-size:12px;
            cursor:pointer;
            text-decoration:none;
            flex-shrink:0;
        `;

        const btn = (type, text, extra = '', filename = '') => {
            const styles = {
                copy: 'border:none;background:rgba(82,196,26,0.85);color:#fff;',
                open: 'background:rgba(22,119,255,0.85);color:#fff;',
                download: 'background:rgba(250,140,16,0.85);color:#fff;'
            };
        
            const tag = type === 'copy' ? 'button' : 'a';
            const attrs =
                type === 'copy'
                    ? `data-copy="${encodeURIComponent(extra)}"`
                    : `href="${extra}" ${type === 'open' ? 'target="_blank"' : ''} ${
                        type === 'download' ? `download="${filename}"` : ''
                    }`;

            return `<${tag} class="${type}-btn" ${attrs}
                style="${btnBase}${styles[type]}">${text}</${tag}>`;
        };

        const linkBlock = (url, name, opts = {}) => {
            const { showOpen = true, showDownload = false, filename = '' } = opts;

            return `
            <div style="margin:6px 0;display:flex;align-items:center;gap:6px;">
                <span style="flex-shrink:0;">${name}：</span>
        
                <div style="flex:1;overflow-x:auto;white-space:nowrap;">
                    <span style="font-family:monospace;background:rgba(128,128,128,0.12);padding:2px 6px;border-radius:4px;">
                        ${url}
                    </span>
                </div>
        
                ${showDownload ? btn('download', '下载', url, filename) : ''}
                ${showOpen ? btn('open', '打开', url) : ''}
                ${btn('copy', '复制', url)}
            </div>`;
        };

        // 配置驱动
        const sections = [
            {
                title: 'EPG接口',
                links: [
                    [ gzUrl, 'xmltv' ],
                    [ buildUrl(serverUrl, '/index.php', epgQuery), 'DIYP/百川、超级直播' ]
                ]
            },
            {
                title: 'tvbox',
                links: [
                    [
                        buildUrl(serverUrl, '/index.php?ch={name}&date={date}', epgQuery),
                        '"epg"',
                        { showOpen: false }
                    ],
                    [
                        buildUrl(serverUrl, '/index.php?ch={name}&type=icon', epgQuery),
                        '"logo"',
                        { showOpen: false }
                    ]
                ]
            },
            {
                title: '直播源地址',
                links: [
                    [ m3uUrl, 'M3U', { showDownload: true, filename: 'tv.m3u' } ],
                    [ txtUrl, 'TXT', { showDownload: true, filename: 'tv.txt' } ]
                ]
            },
            {
                title: '直播源代理',
                links: [
                    [ proxyM3uUrl, 'M3U', { showDownload: true, filename: 'tv.m3u' } ],
                    [ proxyTxtUrl, 'TXT', { showDownload: true, filename: 'tv.txt' } ]
                ]
            }
        ];

        const message = `
        <div id="copy-container" style="line-height:1.8;font-size:14px;">
            ${sections.map(sec => `
                <div style="font-weight:bold;margin:10px 0 4px;">${sec.title}</div>
                ${sec.links.map(([url, name, opts]) => linkBlock(url, name, opts)).join('')}
            `).join('')}
        </div>`;

        showMessageModal(message);

    } catch (err) {
        console.error('获取 serverUrl 或 config 失败:', err);
        showMessageModal('无法获取服务器地址或配置信息，请稍后重试');
    }
}

document.addEventListener('click', function(e) {
    const btn = e.target.closest('.copy-btn');
    if (!btn) return;

    const text = decodeURIComponent(btn.getAttribute('data-copy'));

    const input = document.createElement('textarea');
    input.value = text;
    document.body.appendChild(input);
    input.select();
    document.execCommand('copy');
    document.body.removeChild(input);

    // 可选：提示
    btn.innerText = '成功';
    setTimeout(() => btn.innerText = '复制', 1000);
});

// 显示直播源模板
function showLiveTemplate() {
    showModalWithMessage("liveTemplateModal");
}

// 保存编辑后的直播源模板
function saveLiveTemplate() {
    // 保存配置
    liveTemplateEnable = document.getElementById('live_template_enable').value;
    liveFuzzyMatch = document.getElementById('live_fuzzy_match').value;
    liveUrlComment = document.getElementById('live_url_comment').value;
    saveConfigField({
        live_template_enable: liveTemplateEnable,
        live_fuzzy_match: liveFuzzyMatch,
        live_url_comment: liveUrlComment
    });

    // 内容写入 template.json 文件
    const liveSourceConfig = document.getElementById('live_source_config').value;
    const updateObj = {};
    updateObj[liveSourceConfig] = document.getElementById('liveTemplateTextarea').value.split('\n');
    fetch('manage.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            save_content_to_file: 1,
            file_path: '/data/live/template.json',
            content: JSON.stringify(updateObj)
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            parseSourceInfo("保存成功<br>正在重新解析...");
            document.getElementById('liveTemplateModal').style.display = 'none';
        } else {
            showMessageModal('保存失败');
        }
    })
    .catch(error => {
        showMessageModal('保存失败: ' + error);
    });
}

// 搜索频道
function filterChannels(type) {
    const tableId = type === 'channel' ? 'channelTable' : 'iconTable';
    const dataAttr = type === 'channel' ? 'allChannels' : 'allIcons';
    const input = document.getElementById(type === 'channel' ? 'channelSearchInput' : 'iconSearchInput').value.toUpperCase();
    const tableBody = document.querySelector(`#${tableId} tbody`);
    const allData = JSON.parse(document.getElementById(tableId).dataset[dataAttr]);

    tableBody.innerHTML = ''; // 清空表格

    // 创建行的通用函数
    function createEditableRow(item, itemIndex, insertAfterRow = null) {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td name="channel" contenteditable="true" onclick="this.innerText='';"><span style="color: #aaa;">创建自定义频道</span>${item.channel || ''}</td>
            <td name="icon" contenteditable="true">${item.icon || ''}</td>
            <td></td>
            <td>
                <input type="file" accept="image/png" style="display:none;" id="icon_new_${itemIndex}">
                <button onclick="document.getElementById('icon_new_${itemIndex}').click()" style="font-size: 14px; width: 50px;">上传</button>
            </td>
        `;
        
        // 动态更新 allData
        row.querySelectorAll('td[contenteditable]').forEach(cell => {
            cell.addEventListener('input', () => {
                allData[itemIndex][cell.getAttribute('name')] = cell.textContent.trim();
                document.getElementById(tableId).dataset[dataAttr] = JSON.stringify(allData);
                if (cell.getAttribute('name') === 'channel' && item.channel && !allData.some(e => !e.channel)) {
                    allData.push({ channel: '', icon: '' });
                    createEditableRow(allData[allData.length - 1], allData.length - 1, row); // 插入新行到当前行后
                }
            });
        });

        // 上传文件
        row.querySelector(`#icon_new_${itemIndex}`).addEventListener('change', event => handleIconFileUpload(event, item, row, allData));

        // 如果指定了插入位置，则插入到该行之后，否则追加到表格末尾
        if (insertAfterRow) {
            insertAfterRow.insertAdjacentElement('afterend', row);
        } else {
            tableBody.appendChild(row);
        }
    }

    // 创建初始空行（仅用于 icon）
    if (!input && type === 'icon') {
        allData.push({ channel: '', icon: '' });
        createEditableRow(allData[allData.length - 1], allData.length - 1);
    }

    // 筛选并显示行的逻辑
    allData.forEach((item, index) => {
        const searchText = type === 'channel' ? item.original : item.channel;
        if (String(searchText).toUpperCase().includes(input)) {
            const row = document.createElement('tr');
            if (type === 'channel') {
                row.innerHTML = `<td class="blue-span" 
                                    onclick="showModal('epg', true, { channel: '${item.original}', date: '${new Date().toLocaleDateString('en-CA', { timeZone: 'Asia/Shanghai' })}' })">
                                    ${item.original} </td>
                                <td contenteditable="true">${String(item.mapped || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')}</td>`;
                row.querySelector('td[contenteditable]').addEventListener('input', function() {
                    item.mapped = this.textContent.trim();
                    document.getElementById(tableId).dataset[dataAttr] = JSON.stringify(allData);
                });
            } else if (type === 'icon' && searchText) {
                row.innerHTML = `
                    <td contenteditable="true">${item.channel}</td>
                    <td contenteditable="true">${item.icon || ''}</td>
                    <td>${item.icon ? `<a href="${item.icon}" target="_blank"><img loading="lazy" src="${item.icon}" style="max-width: 80px; max-height: 50px; background-color: #ccc;"></a>` : ''}</td>
                    <td>
                        <input type="file" accept="image/png" style="display:none;" id="file_${index}">
                        <button onclick="document.getElementById('file_${index}').click()" style="font-size: 14px; width: 50px;">上传</button>
                    </td>
                `;
                row.querySelectorAll('td[contenteditable]').forEach((cell, idx) => {
                    cell.addEventListener('input', function() {
                        if (idx === 0) item.channel = this.textContent.trim();  // 第一个可编辑单元格更新 channel
                        else item.icon = this.textContent.trim();  // 第二个可编辑单元格更新 icon
                        document.getElementById(tableId).dataset[dataAttr] = JSON.stringify(allData);
                    });
                });
                row.querySelector(`#file_${index}`).addEventListener('change', event => handleIconFileUpload(event, item, row, allData));
            }
            tableBody.appendChild(row);
        }
    });
}

// 台标上传
function handleIconFileUpload(event, item, row, allData) {
    const file = event.target.files[0];
    if (file && file.type === 'image/png') {
        const formData = new FormData();
        formData.append('iconFile', file);

        fetch('manage.php', { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const iconUrl = data.iconUrl;
                    row.cells[1].innerText = iconUrl;
                    item.icon = iconUrl;
                    row.cells[2].innerHTML = `
                        <a href="${iconUrl}?${new Date().getTime()}" target="_blank">
                            <img loading="lazy" src="${iconUrl}?${new Date().getTime()}" style="max-width: 80px; max-height: 50px; background-color: #ccc;">
                        </a>
                    `;
                    document.getElementById('iconTable').dataset.allIcons = JSON.stringify(allData);
                    updateIconListJsonFile();
                } else {
                    showMessageModal('上传失败：' + data.message);
                }
            })
            .catch(error => showMessageModal('上传过程中发生错误：' + error));
    } else {
        showMessageModal('请选择PNG文件上传');
    }
    event.target.value = ''; // 重置文件输入框的值，确保可以连续上传相同文件
}

// 转存所有台标到服务器
function uploadAllIcons() {
    const iconTable = document.getElementById('iconTable');
    const allIcons = JSON.parse(iconTable.dataset.allIcons);
    const rows = Array.from(document.querySelectorAll('#iconTable tbody tr'));

    let totalIcons = 0;
    let uploadedIcons = 0;
    const rowsToUpload = rows.filter(row => {
        const iconUrl = row.cells[1]?.innerText.trim();
        if (iconUrl) {
            totalIcons++;
            if (!iconUrl.startsWith('/data/icon/')) {
                return true;
            } else {
                uploadedIcons++;
            }
        }
        return false;
    });

    const progressDisplay = document.getElementById('progressDisplay') || document.createElement('div');
    progressDisplay.id = 'progressDisplay';
    progressDisplay.style.cssText = 'margin: 10px 0; text-align: right;';
    progressDisplay.textContent = `已转存 ${uploadedIcons}/${totalIcons}`;
    iconTable.before(progressDisplay);

    const uploadPromises = rowsToUpload.map(row => {
        const [channelCell, iconCell, previewCell] = row.cells;
        const iconUrl = iconCell?.innerText.trim();
        const fileName = decodeURIComponent(iconUrl.split('/').pop().split('?')[0]);

        return fetch(iconUrl)
            .then(res => res.blob())
            .then(blob => {
                const formData = new FormData();
                formData.append('iconFile', new File([blob], fileName, { type: 'image/png' }));

                return fetch('manage.php', { method: 'POST', body: formData });
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const iconUrl = data.iconUrl;
                    const channelName = channelCell.innerText.trim();
                    iconCell.innerText = iconUrl;
                    previewCell.innerHTML = `
                        <a href="${iconUrl}?${Date.now()}" target="_blank">
                            <img loading="lazy" src="${iconUrl}?${Date.now()}" style="max-width: 80px; max-height: 50px; background-color: #ccc;">
                        </a>
                    `;

                    allIcons.forEach(item => {
                        if (item.channel === channelName) item.icon = iconUrl;
                    });
                    iconTable.dataset.allIcons = JSON.stringify(allIcons);
                    uploadedIcons++;
                    progressDisplay.textContent = `已转存 ${uploadedIcons}/${totalIcons}`;
                } else {
                    previewCell.innerHTML = `上传失败: ${data.message}`;
                }
            })
            .catch(() => {
                previewCell.innerHTML = '上传出错';
            });
    });

    Promise.all(uploadPromises).then(() => {
        if (uploadedIcons !== totalIcons) {
            uploadAllIcons(); // 继续上传
        }
        else {
            updateIconListJsonFile();
            showMessageModal("全部转存成功，已保存！");
        }
    });
}

// 清理未使用的台标文件
function deleteUnusedIcons() {
    fetch('manage.php?delete_unused_icons=1')
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showMessageModal(data.message);
        } else {
            showMessageModal('清理失败');
        }
    })
    .catch(error => {
        showMessageModal('Error: ' + error);
    });
}

// 更新频道别名
function updateChannelMapping() {
    const allChannels = JSON.parse(document.getElementById('channelTable').dataset.allChannels);
    const channelMappings = document.getElementById('channel_mappings');
    const map = allChannels.filter(c => c.original !== '【频道忽略字符】' && c.mapped.trim())
                           .map(c => `${c.original} => ${c.mapped}`);
    const regex = channelMappings.value.split('\n').filter(l => l.includes('regex:'));
    const ignore = allChannels.find(c => c.original === '【频道忽略字符】');
    const done = () => { channelMappings.value = [...map, ...regex].join('\n'); updateConfig(); };
    ignore ? saveConfigField({
        channel_ignore_chars: ignore.mapped.trim()
    }).then(done).catch(console.error) : done();
}

// 解析 txt、m3u 直播源，并生成频道列表（仅频道）
async function parseSource() {
    const textarea = document.getElementById('gen_list_text');
    let text = textarea.value.trim();
    const channels = new Set();

    // 拆分输入的内容，可能包含多个 URL 或文本
    if(!text.includes('#EXTM3U')) {
        let lines = text.split('\n').map(line => line.trim());
        let urls = lines.filter(line => line.startsWith('http'));

        // 如果存在 URL，则清空原本的 text 内容并逐个请求获取数据
        if (urls.length > 0) {
            text = '';
            for (let url of urls) {
                try {
                    const response = await fetch('manage.php?download_source_data=1&url=' + encodeURIComponent(url));
                    const result = await response.json(); // 解析 JSON 响应
                    
                    if (result.success) {
                        text += '\n' + result.data;
                    } else {
                        showMessageModal(`${result.message}：\n${url}`);
                    }
                } catch (error) {
                    showMessageModal(`无法获取URL内容: ${url}\n错误信息: ${error.message}`); // 显示网络错误信息
                }
            }
        }
    }

    // 处理 m3u 、 txt 文件内容
    text.split('\n').forEach(line => {
        line = line.trim();
        if (!line) return;

        // 匹配任意协议的 URL
        if (/^[a-z][a-z0-9+\-.]*:\/\//i.test(line)) return;
        
        // # 开头 → 只允许 #EXTINF，其他全部跳过
        if (line.startsWith('#') && !/^#EXTINF:/i.test(line)) return;
        
        if (/^#EXTINF:/i.test(line)) {
            const tvgIdMatch = line.match(/tvg-id="([^"]+)"/i);
            const tvgNameMatch = line.match(/tvg-name="([^"]+)"/i);
            
            channelName = (
                tvgIdMatch && /\D/.test(tvgIdMatch[1])
                    ? tvgIdMatch[1]
                    : tvgNameMatch
                        ? tvgNameMatch[1]
                        : line.split(',').slice(-1)[0]
            ).trim();
            
        } else {
            // TXT 格式行：频道名在逗号前
            channelName = line.split(',')[0].trim();
        }
        
        if (channelName) channels.add(channelName);
    });

    // 将解析后的频道列表放回文本区域
    textarea.value = Array.from(channels).join('\n');
    
    // 保存限定频道列表到数据库
    setGenList();
}

// 解析 txt、m3u 直播源，并生成直播列表（包含分组、地址等信息）
async function parseSourceInfo(message = '') {
    showMessageModal(message || "在线源解析较慢<br>请耐心等待...");

    try {
        await saveLiveSourceFile();

        const response = await fetch(`manage.php?parse_source_info=1`);
        const data = await response.json();

        showModal('live');

        if (data.success == 'full') {
            showMessageModal('解析成功<br>已生成 M3U 及 TXT 文件');
        } else if (data.success == 'part') {
            showMessageModal('已生成 M3U 及 TXT 文件<br>部分源异常<br>' + data.message);
        }
    } catch (error) {
        showMessageModal('解析过程中发生错误：' + error);
    }

    liveSourceSearchInput.value = ''; // 清空搜索框内容
}

// 保存限定频道列表
async function setGenList() {
    const genListText = document.getElementById('gen_list_text').value;
    try {
        const response = await fetch('manage.php?set_gen_list=1', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ data: genListText })
        });

        const responseText = await response.text();

        if (responseText.trim() !== 'success') {
            console.error('服务器响应错误:', responseText);
        }
    } catch (error) {
        console.error(error);
    }
}

// 保存限定频道列表并更新配置
function setGenListAndUpdateConfig() {
    setGenList();
    updateConfig();
}

// 更新台标文件 iconList.json
function updateIconListJsonFile(notify = false) {
    var iconTableElement = document.getElementById('iconTable');
    var allIcons = iconTableElement && iconTableElement.dataset.allIcons ? JSON.parse(iconTableElement.dataset.allIcons) : null;
    if (allIcons) {
        fetch('manage.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                update_icon_list: 1,
                updatedIcons: JSON.stringify(allIcons) // 传递更新后的图标数据
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && notify) {
                showModal('icon');
                showMessageModal('保存成功');
            } else if (data.success == false) {
                showMessageModal(data.message);
            }
        })
        .catch(error => showMessageModal('更新过程中发生错误：' + error));
    }
}

// 导入配置
document.getElementById('importFile').addEventListener('change', function() {
    const file = this.files[0];
    const fileExtension = file.name.split('.').pop().toLowerCase();

    // 检查文件类型
    if (fileExtension != 'gz') {
        showMessageModal('只接受 .gz 文件');
        return;
    }

    // 发送 AJAX 请求
    const formData = new FormData(document.getElementById('importForm'));

    fetch('manage.php', { method: 'POST', body: formData })
    .then(response => response.json())
    .then(data => {
        showMessageModal(data.message);
        if (data.success) {
            // 延迟刷新页面
            setTimeout(() => {
                window.location.href = 'manage.php';
            }, 3000);
        }
    })
    .catch(error => showMessageModal('导入过程中发生错误：' + error));

    this.value = ''; // 重置文件输入框的值，确保可以连续上传相同文件
});

// 修改 token、user_agent 对话框
async function changeTokenUA(type) {
    try {
        // 获取 config
        const res = await fetch('manage.php?get_config=1');
        const config = await res.json();

        // 根据 type 获取对应的值
        let currentTokenUA = '';
        if (type === 'token') {
            currentTokenUA = config.token || '';
        } else if (type === 'user_agent') {
            currentTokenUA = config.user_agent || '';
        }

        showMessageModal('');
        const typeStr = (type === 'token' ? 'Token' : 'User-Agent') + '<br>支持多个，每行一个';
        document.getElementById('messageModalMessage').innerHTML = `
            <div class="modal-inner" style="width: 450px;">
                <h3>修改 ${typeStr}</h3>
                <textarea id="newTokenUA" style="min-height: 250px; margin-bottom: 15px;">${currentTokenUA}</textarea>
                <button onclick="updateTokenUA('${type}')" style="margin-bottom: -10px;">确认</button>
            </div>
        `;
    } catch (err) {
        console.error('获取 config 失败:', err);
        showMessageModal('无法获取配置信息，请稍后重试');
    }
}

// 更新 token、user_agent 到 config.json
function updateTokenUA(type) {
    var newTokenUA = document.getElementById('newTokenUA').value.trim().split('\n').filter(l=>l.trim()).join('\n');
    const type_range = document.getElementById(`${type}_range`).value;

    // 内容写入 config.json 文件
    saveConfigField({
        [`${type}_range`]: type_range,
        [type]: newTokenUA
    })
    .then(data => {
        if (data.success) {
            showMessageModal('修改成功');
        } else {
            showMessageModal('修改失败');
        }
    })
    .catch(err => showMessageModal('保存过程中出现错误: ' + err));
}

// token_range 更变后进行提示
async function showTokenRangeMessage() {
    const tokenRange = document.getElementById("token_range")?.value ?? "1";
    if (tokenRange === '0') return;

    try {
        // 并行获取 serverUrl 和 config
        const [serverRes, configRes] = await Promise.all([
            fetch('manage.php?get_env=1'),
            fetch('manage.php?get_config=1')
        ]);

        const serverData = await serverRes.json();
        const configData = await configRes.json();

        const serverUrl  = serverData.server_url;
        const tokenFull  = configData.token;
        const rewriteEnable = serverData.rewrite_enable ? true : false;
        const token = tokenFull.split('\n')[0];
        let message = '';

        if (tokenRange === "1" || tokenRange === "3") {
            const m3u = rewriteEnable ? `${serverUrl}/tv.m3u?token=${token}` : `${serverUrl}/index.php?type=m3u&token=${token}`;
            const txt = rewriteEnable ? `${serverUrl}/tv.txt?token=${token}` : `${serverUrl}/index.php?type=txt&token=${token}`;
            message += `直播源地址：<br><a href="${m3u}" target="_blank">${m3u}</a><br>
                        <a href="${txt}" target="_blank">${txt}</a>`;
        }

        if (tokenRange === "2" || tokenRange === "3") {
            if (message) message += '<br>';
            const xml = rewriteEnable ? `${serverUrl}/t.xml?token=${token}` : `${serverUrl}/index.php?type=xml&token=${token}`;
            const gz = rewriteEnable ? `${serverUrl}/t.xml.gz?token=${token}` : `${serverUrl}/index.php?type=gz&token=${token}`;
            message += `EPG地址：<br><a href="${serverUrl}/index.php?token=${token}" target="_blank">${serverUrl}/index.php?token=${token}</a><br>
                        <a href="${xml}" target="_blank">${xml}</a><br>
                        <a href="${gz}" target="_blank">${gz}</a>`;
        }

        showMessageModal(message);
    } catch (err) {
        console.error('获取 serverUrl 或 config 失败:', err);
        showMessageModal('无法获取服务器地址或配置信息，请稍后重试');
    }
}

// 修改通知信息对话框
async function changeNotifyInfo() {
    const notifyMode = document.getElementById('notify')?.value ?? "0";
    if (notifyMode === '0') return;

    try {
        // 获取 config
        const res = await fetch('manage.php?get_config=1');
        const config = await res.json();
        let currentSCKey = config.serverchan_key || '';

        showMessageModal('');
        document.getElementById('messageModalMessage').innerHTML = `
            <div class="modal-inner" style="width: auto;">
                <h3>Sendkey</h3>
                <div>同时支持 <a href="https://sct.ftqq.com/r/15503" target="_blank">Server酱ᵀ</a>（免费5次/天）
						与 <a href="https://sc3.ft07.com/" target="_blank">Server酱³</a>（公测不限次）</div>
                <input type="text" id="newSCKey" value="${currentSCKey}" style="margin-top: 20px; margin-bottom: 20px;"/>
                <button onclick="updateNotifyInfo()" style="margin-bottom: -10px;">确认</button>
            </div>
        `;
    } catch (err) {
        console.error('获取 config 失败:', err);
        showMessageModal('无法获取配置信息，请稍后重试');
    }
}

// 更新 serverchan_key 到 config.json
function updateNotifyInfo() {
    var newSCKey = document.getElementById('newSCKey').value.trim();

    // 内容写入 config.json 文件
    saveConfigField({
        serverchan_key: newSCKey
    })
    .then(data => {
        if (data.success) {
            showMessageModal('修改成功');
        } else {
            showMessageModal('修改失败');
        }
    })
    .catch(err => showMessageModal('保存过程中出现错误: ' + err));
}

// 修改测速过滤规则对话框
async function changeCheckSpeedFilterRules() {
    const filterEnabled = document.getElementById('check_speed_filter')?.value ?? "1";
    if (filterEnabled === '0') return;

    try {
        const res = await fetch('manage.php?get_config=1');
        const config = await res.json();
        const currentRules =
            config.check_speed_filter_rules == null
                ? 'regex:/^https?:\\/\\/\\[[a-f0-9:]+\\]/i'
                : config.check_speed_filter_rules.trim();

        showMessageModal('');
        document.getElementById('messageModalMessage').innerHTML = `
            <div class="modal-inner" style="width: 500px;">
                <h3>修改测速过滤规则</h3>
                <div style="margin-bottom: 10px; text-align: left;">
                    过滤规则仅对<strong>直播地址</strong>生效<br>
                    每行一条规则：<br>
                    普通表达式：包含即过滤（例如：m3u8）<br>
                    正则表达式：以 regex: 开头（内置 IPv6 过滤规则）<br>
                    使用 <code>#</code> 开头可临时停用该行规则
                </div>
                <textarea id="newCheckSpeedFilterRules" style="min-height: 200px; margin-bottom: 15px;">${currentRules}</textarea>
                <button onclick="updateCheckSpeedFilterRules()" style="margin-bottom: -10px;">确认</button>
            </div>
        `;
    } catch (err) {
        console.error('获取 config 失败:', err);
        showMessageModal('无法获取配置信息，请稍后重试');
    }
}

// 更新测速过滤规则到 config.json
function updateCheckSpeedFilterRules() {
    const textarea = document.getElementById('newCheckSpeedFilterRules');
    const newRules = (textarea?.value || '').trim().split('\n').map(l => l.trim()).filter(Boolean).join('\n');

    saveConfigField({
        check_speed_filter_rules: newRules
    })
    .then(data => {
        if (data.success) {
            showMessageModal('修改成功');
        } else {
            showMessageModal('修改失败');
        }
    })
    .catch(err => showMessageModal('保存过程中出现错误: ' + err));
}

// 监听 access_log_enable 更变
function accessLogEnable(selectElem) {
    document.getElementById("accessLogBtn").style.display = selectElem.value === "1" ? "inline-block" : "none";
}

// 页面加载时恢复大小
const ids = ["xml_urls", "channel_mappings", "sourceUrlTextarea", "live-source-table-container", "gen_list_text"];
const prefix = "height_";
const saveHeight = (el) => {
    if (el.offsetHeight > 0) localStorage.setItem(prefix + el.id, el.offsetHeight);
};
ids.forEach(id => {
    const el = document.getElementById(id);
    if (!el) return;
    const h = localStorage.getItem(prefix + id);
    if (h) el.style.height = h + "px";
    new ResizeObserver(() => saveHeight(el)).observe(el);
});

// 切换主题
document.getElementById('themeSwitcher').addEventListener('click', function() {
    // 获取当前主题，并切换到下一个主题
    const currentTheme = localStorage.getItem('theme');
    const newTheme = currentTheme === 'light' ? 'dark' : (currentTheme === 'dark' ? '' : 'light');
    
    // 更新主题
    document.body.classList.add('theme-transition');
    document.body.classList.remove('dark', 'light');

    if (newTheme === '') {
        const prefersDarkScheme = window.matchMedia("(prefers-color-scheme: dark)").matches;
        document.body.classList.add(prefersDarkScheme ? 'dark' : 'light');
    } else {
        document.body.classList.add(newTheme);
    }

    // 更新图标和文字
    document.getElementById("themeIcon");
    const labelText = document.querySelector('.label-text');
    themeIcon.className = `fas ${newTheme === 'dark' ? 'fa-moon' : newTheme === 'light' ? 'fa-sun' : 'fa-adjust'}`;
    labelText.textContent = newTheme === 'dark' ? 'Dark' : newTheme === 'light' ? 'Light' : 'Auto';
    
    // 保存到本地存储
    localStorage.setItem('theme', newTheme);
});

// 监听系统主题变化
window.matchMedia("(prefers-color-scheme: dark)").addEventListener("change", (e) => {
    if(!localStorage.getItem('theme')) {
        const theme = e.matches ? 'dark' : 'light';
        document.body.classList.remove('dark', 'light');
        document.body.classList.add(theme);
    }
});