<template>
  <div class="config-page">
    <el-card>
      <template #header>
        <div class="card-header">
          <span>系统配置</span>
          <el-button type="primary" :loading="loading" @click="handleSave">
            保存配置
          </el-button>
        </div>
      </template>

      <el-form v-if="formData" ref="formRef" :model="formData" label-width="180px">
        <!-- EPG Configuration Section -->
        <el-divider content-position="left">EPG 配置</el-divider>
        
        <el-form-item label="EPG 源地址">
          <el-input
            ref="xmlUrlsRef"
            v-model="formData.xml_urls"
            type="textarea"
            :rows="8"
            placeholder="每行一个 EPG 源地址，以 # 开头的行会被注释，Ctrl+/ 快速注释/取消注释"
            @keydown="handleCommentToggle"
          />
          <div class="form-tip">
            提示：每行一个 EPG 源地址，可使用 Ctrl+/ 快速注释/取消注释选中行
          </div>
        </el-form-item>

        <el-form-item label="频道别名">
          <el-input
            v-model="formData.channel_mappings"
            type="textarea"
            :rows="6"
            placeholder="格式：原频道名 => 新频道名，每行一个"
          />
        </el-form-item>

        <el-form-item label="频道忽略字符">
          <el-input v-model="formData.channel_ignore_chars" placeholder="&nbsp, -" />
          <div class="form-tip">多个字符用逗号分隔</div>
        </el-form-item>

        <!-- Schedule Configuration -->
        <el-divider content-position="left">定时任务配置</el-divider>

        <el-form-item label="开始时间">
          <el-time-picker
            v-model="formData.start_time"
            format="HH:mm"
            value-format="HH:mm"
            placeholder="选择开始时间"
          />
        </el-form-item>

        <el-form-item label="结束时间">
          <el-time-picker
            v-model="formData.end_time"
            format="HH:mm"
            value-format="HH:mm"
            placeholder="选择结束时间"
          />
        </el-form-item>

        <el-form-item label="间隔周期">
          <el-input-number v-model="formData.interval_hour" :min="0" :max="23" />
          <span style="margin: 0 10px;">小时</span>
          <el-input-number v-model="formData.interval_minute" :min="0" :max="59" />
          <span style="margin-left: 10px;">分钟</span>
          <div class="form-tip">设置为 0 小时 0 分钟则取消定时任务</div>
        </el-form-item>

        <el-form-item label="数据保存天数">
          <el-input-number v-model="formData.days_to_keep" :min="1" :max="30" />
        </el-form-item>

        <!-- General Settings -->
        <el-divider content-position="left">常规设置</el-divider>

        <el-form-item label="生成 XML 文件">
          <el-switch v-model="formData.gen_xml" />
        </el-form-item>

        <el-form-item label="仅未来节目数据">
          <el-switch v-model="formData.include_future_only" />
        </el-form-item>

        <el-form-item label="获取不到返回默认数据">
          <el-switch v-model="formData.ret_default" />
        </el-form-item>

        <el-form-item label="繁体转简体">
          <el-switch v-model="formData.cht_to_chs" />
        </el-form-item>

        <el-form-item label="检查更新">
          <el-switch v-model="formData.check_update" />
        </el-form-item>

        <el-form-item label="启用通知">
          <el-switch v-model="formData.notify" />
        </el-form-item>

        <el-form-item label="调试模式">
          <el-switch v-model="formData.debug_mode" />
        </el-form-item>

        <!-- Database Configuration -->
        <el-divider content-position="left">数据库配置</el-divider>

        <el-form-item label="数据库类型">
          <el-radio-group v-model="formData.db_type">
            <el-radio label="sqlite">SQLite</el-radio>
            <el-radio label="mysql">MySQL</el-radio>
          </el-radio-group>
        </el-form-item>

        <template v-if="formData.db_type === 'mysql'">
          <el-form-item label="MySQL 主机地址">
            <el-input v-model="formData.mysql_host" placeholder="localhost" />
          </el-form-item>

          <el-form-item label="数据库名">
            <el-input v-model="formData.mysql_dbname" placeholder="iptv_tool" />
          </el-form-item>

          <el-form-item label="用户名">
            <el-input v-model="formData.mysql_username" />
          </el-form-item>

          <el-form-item label="密码">
            <el-input v-model="formData.mysql_password" type="password" show-password />
          </el-form-item>
        </template>

        <el-form-item label="缓存类型">
          <el-select v-model="formData.cached_type" placeholder="请选择缓存类型">
            <el-option label="无缓存" value="none" />
            <el-option label="文件缓存" value="file" />
            <el-option label="Redis缓存" value="redis" />
          </el-select>
        </el-form-item>

        <!-- Advanced Settings -->
        <el-divider content-position="left">高级设置</el-divider>

        <el-form-item label="Token范围">
          <el-radio-group v-model="formData.token_range">
            <el-radio :label="0">完整Token</el-radio>
            <el-radio :label="1">前8位</el-radio>
          </el-radio-group>
        </el-form-item>

        <el-form-item label="User-Agent范围">
          <el-radio-group v-model="formData.user_agent_range">
            <el-radio :label="0">完整UA</el-radio>
            <el-radio :label="1">前8位</el-radio>
          </el-radio-group>
        </el-form-item>

        <el-form-item label="目标时区">
          <el-input v-model.number="formData.target_time_zone" type="number" placeholder="8" />
          <div class="form-tip">北京时间为 +8</div>
        </el-form-item>

        <el-form-item label="IP访问控制">
          <el-radio-group v-model="formData.ip_list_mode">
            <el-radio :label="0">白名单模式</el-radio>
            <el-radio :label="1">黑名单模式</el-radio>
          </el-radio-group>
        </el-form-item>
      </el-form>

      <div v-else v-loading="true" style="min-height: 400px;"></div>
    </el-card>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { useConfigStore } from '@/stores/config'

const configStore = useConfigStore()
const formRef = ref(null)
const formData = ref(null)
const loading = ref(false)
const xmlUrlsRef = ref(null)

onMounted(async () => {
  await loadConfig()
  // Add Ctrl+S keyboard shortcut
  document.addEventListener('keydown', handleSaveShortcut)
})

onUnmounted(() => {
  document.removeEventListener('keydown', handleSaveShortcut)
})

// Ctrl+S to save
function handleSaveShortcut(event) {
  if (event.ctrlKey && event.key === 's') {
    event.preventDefault()
    handleSave()
  }
}

// Ctrl+/ to toggle comments (from manage.js lines 109-150)
function handleCommentToggle(event) {
  if (event.ctrlKey && event.key === '/') {
    event.preventDefault()
    const textarea = event.target
    const { selectionStart, selectionEnd, value } = textarea
    const lines = value.split('\n')
    
    // Calculate selected lines
    const startLine = value.slice(0, selectionStart).split('\n').length - 1
    const endLine = value.slice(0, selectionEnd).split('\n').length - 1
    
    // Check if all selected lines are commented
    const allCommented = lines.slice(startLine, endLine + 1).every(line => line.trim().startsWith('#'))
    
    const newLines = lines.map((line, index) => {
      if (index >= startLine && index <= endLine) {
        return allCommented ? line.replace(/^#\s*/, '') : '# ' + line
      }
      return line
    })
    
    // Update textarea value
    formData.value.xml_urls = newLines.join('\n')
    
    // Restore cursor position
    setTimeout(() => {
      const startLineStartIndex = value.lastIndexOf('\n', selectionStart - 1) + 1
      const isStartInLineStart = (selectionStart - startLineStartIndex < 2)
      const endLineStartIndex = value.lastIndexOf('\n', selectionEnd - 1) + 1
      const isEndInLineStart = (selectionEnd - endLineStartIndex < 2)
      
      const newSelectionStart = isStartInLineStart 
        ? startLineStartIndex
        : selectionStart + newLines[startLine].length - lines[startLine].length
        
      const lengthDiff = newLines.join('').length - lines.join('').length
      const endLineDiff = newLines[endLine].length - lines[endLine].length
      const newSelectionEnd = isEndInLineStart
        ? (endLineDiff > 0 ? endLineStartIndex + lengthDiff : endLineStartIndex + lengthDiff - endLineDiff)
        : selectionEnd + lengthDiff
        
      textarea.setSelectionRange(newSelectionStart, newSelectionEnd)
    }, 0)
  }
}

async function loadConfig() {
  loading.value = true
  try {
    await configStore.fetchConfig()
    if (configStore.config) {
      const config = configStore.config
      // Format data for form (matching manage.js format)
      formData.value = {
        xml_urls: Array.isArray(config.xml_urls) ? config.xml_urls.join('\n') : '',
        channel_mappings: formatChannelMappings(config.channel_mappings),
        channel_ignore_chars: config.channel_ignore_chars || '&nbsp, -',
        start_time: config.start_time || '01:00',
        end_time: config.end_time || '06:00',
        interval_hour: Math.floor((config.interval_time || 0) / 3600),
        interval_minute: Math.floor(((config.interval_time || 0) % 3600) / 60),
        days_to_keep: config.days_to_keep || 7,
        gen_xml: config.gen_xml !== false,
        include_future_only: config.include_future_only || false,
        ret_default: config.ret_default || false,
        cht_to_chs: config.cht_to_chs !== false,
        check_update: config.check_update !== false,
        notify: config.notify || false,
        debug_mode: config.debug_mode || false,
        db_type: config.db_type || 'sqlite',
        mysql_host: config.mysql?.host || 'localhost',
        mysql_dbname: config.mysql?.dbname || '',
        mysql_username: config.mysql?.username || '',
        mysql_password: config.mysql?.password || '',
        cached_type: config.cached_type || 'none',
        token_range: config.token_range || 0,
        user_agent_range: config.user_agent_range || 0,
        target_time_zone: config.target_time_zone !== undefined ? config.target_time_zone : 8,
        ip_list_mode: config.ip_list_mode || 0
      }
    }
  } catch (error) {
    ElMessage.error('加载配置失败')
  } finally {
    loading.value = false
  }
}

function formatChannelMappings(mappings) {
  if (!mappings || typeof mappings !== 'object') return ''
  return Object.entries(mappings)
    .map(([key, value]) => `${key} => ${value}`)
    .join('\n')
}

function parseChannelMappings(text) {
  const mappings = {}
  if (!text) return mappings
  const lines = text.split('\n')
  lines.forEach(line => {
    if (line = line.trim()) {
      const parts = line.split(/=》|=>/)
      if (parts.length === 2) {
        const key = parts[0].trim()
        const value = parts[1].trim().replace(/，/g, ',')
        if (key && value) {
          mappings[key] = value
        }
      }
    }
  })
  return mappings
}

function formatTime(seconds) {
  const hours = Math.floor(seconds / 3600)
  const minutes = Math.floor((seconds % 3600) / 60)
  if (hours > 0 && minutes > 0) {
    return `${hours}小时${minutes}分钟`
  } else if (hours > 0) {
    return `${hours}小时`
  } else if (minutes > 0) {
    return `${minutes}分钟`
  }
  return ''
}

async function handleSave() {
  loading.value = true
  try {
    // Build config data (matching manage.php format)
    const interval_time = formData.value.interval_hour * 3600 + formData.value.interval_minute * 60
    
    const configData = {
      xml_urls: formData.value.xml_urls,
      channel_mappings: formData.value.channel_mappings,
      channel_ignore_chars: formData.value.channel_ignore_chars,
      start_time: formData.value.start_time,
      end_time: formData.value.end_time,
      interval_hour: formData.value.interval_hour,
      interval_minute: formData.value.interval_minute,
      days_to_keep: formData.value.days_to_keep,
      gen_xml: formData.value.gen_xml ? 1 : 0,
      include_future_only: formData.value.include_future_only ? 1 : 0,
      ret_default: formData.value.ret_default ? 1 : 0,
      cht_to_chs: formData.value.cht_to_chs ? 1 : 0,
      check_update: formData.value.check_update ? 1 : 0,
      notify: formData.value.notify ? 1 : 0,
      debug_mode: formData.value.debug_mode ? 1 : 0,
      db_type: formData.value.db_type,
      mysql_host: formData.value.mysql_host,
      mysql_dbname: formData.value.mysql_dbname,
      mysql_username: formData.value.mysql_username,
      mysql_password: formData.value.mysql_password,
      cached_type: formData.value.cached_type,
      token_range: formData.value.token_range,
      user_agent_range: formData.value.user_agent_range,
      target_time_zone: formData.value.target_time_zone,
      ip_list_mode: formData.value.ip_list_mode
    }

    const result = await configStore.updateConfig(configData)
    
    // Build success message (matching manage.js lines 48-61)
    let message = '配置已更新<br><br>'
    if (result && result.db_type_set === false) {
      message += '<span style="color:red">MySQL 启用失败<br>数据库已设为 SQLite</span><br><br>'
      formData.value.db_type = 'sqlite'
    }
    
    message += interval_time === 0 
      ? '已取消定时任务' 
      : `已设置定时任务<br>开始时间：${formData.value.start_time}<br>结束时间：${formData.value.end_time}<br>间隔周期：${formatTime(interval_time)}`
    
    await ElMessageBox.alert(message, '保存成功', {
      dangerouslyUseHTMLString: true,
      confirmButtonText: '确定'
    })
  } catch (error) {
    ElMessage.error('配置保存失败: ' + (error.message || '未知错误'))
  } finally {
    loading.value = false
  }
}
</script>

<style scoped>
.config-page {
  padding: 20px;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.form-tip {
  font-size: 12px;
  color: #909399;
  margin-top: 5px;
}

:deep(.el-divider__text) {
  font-weight: bold;
  color: #409EFF;
}
</style>
