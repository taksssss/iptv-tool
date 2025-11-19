<template>
  <div class="config-page">
    <el-card>
      <template #header>
        <div class="card-header">
          <span>配置管理</span>
          <el-button type="primary" :loading="configStore.loading" @click="handleSave">
            保存配置
          </el-button>
        </div>
      </template>

      <el-form v-if="formData" label-width="120px">
        <el-form-item label="EPG 地址">
          <el-input
            v-model="formData.xml_urls_text"
            type="textarea"
            :rows="8"
            placeholder="每行一个 EPG 源地址"
          />
          <div class="form-tip">
            每行一个 EPG 源地址，以 # 开头的行会被注释
          </div>
        </el-form-item>

        <el-form-item label="频道别名">
          <el-input
            v-model="formData.channel_mappings_text"
            type="textarea"
            :rows="8"
            placeholder="格式：原频道名 => 新频道名"
          />
          <div class="form-tip">
            格式：原频道名 => 新频道名，每行一个映射
          </div>
        </el-form-item>

        <el-divider />

        <el-form-item label="数据保存天数">
          <el-input-number v-model="formData.days_to_keep" :min="1" :max="30" />
        </el-form-item>

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
          <el-input-number v-model="formData.interval_hours" :min="0" :max="23" />
          <span style="margin: 0 10px;">小时</span>
          <el-input-number v-model="formData.interval_minutes" :min="0" :max="59" />
          <span style="margin-left: 10px;">分钟</span>
        </el-form-item>

        <el-divider />

        <el-form-item label="生成 XML">
          <el-switch v-model="formData.gen_xml" />
        </el-form-item>

        <el-form-item label="繁体转简体">
          <el-switch v-model="formData.cht_to_chs" />
        </el-form-item>

        <el-form-item label="数据库类型">
          <el-radio-group v-model="formData.db_type">
            <el-radio label="sqlite">SQLite</el-radio>
            <el-radio label="mysql">MySQL</el-radio>
          </el-radio-group>
        </el-form-item>

        <template v-if="formData.db_type === 'mysql'">
          <el-form-item label="MySQL 主机">
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
      </el-form>

      <div v-else v-loading="true" style="min-height: 400px;"></div>
    </el-card>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import { ElMessage } from 'element-plus'
import { useConfigStore } from '@/stores/config'

const configStore = useConfigStore()
const formData = ref(null)

onMounted(async () => {
  await loadConfig()
})

async function loadConfig() {
  try {
    await configStore.fetchConfig()
    if (configStore.config) {
      // Convert config to form data
      const config = configStore.config
      formData.value = {
        xml_urls_text: Array.isArray(config.xml_urls) ? config.xml_urls.join('\n') : '',
        channel_mappings_text: formatChannelMappings(config.channel_mappings),
        days_to_keep: config.days_to_keep || 7,
        start_time: config.start_time || '01:00',
        end_time: config.end_time || '06:00',
        interval_hours: Math.floor((config.interval_time || 0) / 3600),
        interval_minutes: Math.floor(((config.interval_time || 0) % 3600) / 60),
        gen_xml: config.gen_xml !== false,
        cht_to_chs: config.cht_to_chs !== false,
        db_type: config.db_type || 'sqlite',
        mysql_host: config.mysql?.host || '',
        mysql_dbname: config.mysql?.dbname || '',
        mysql_username: config.mysql?.username || '',
        mysql_password: config.mysql?.password || ''
      }
    }
  } catch (error) {
    ElMessage.error('加载配置失败')
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
  const lines = text.split('\n')
  lines.forEach(line => {
    const match = line.match(/^(.+?)\s*=》|=>\s*(.+)$/)
    if (match) {
      const [, key, value] = match
      if (key && value) {
        mappings[key.trim()] = value.trim()
      }
    }
  })
  return mappings
}

async function handleSave() {
  try {
    const configData = {
      xml_urls: formData.value.xml_urls_text.split('\n').filter(line => line.trim()),
      channel_mappings: parseChannelMappings(formData.value.channel_mappings_text),
      days_to_keep: formData.value.days_to_keep,
      start_time: formData.value.start_time,
      end_time: formData.value.end_time,
      interval_time: formData.value.interval_hours * 3600 + formData.value.interval_minutes * 60,
      gen_xml: formData.value.gen_xml,
      cht_to_chs: formData.value.cht_to_chs,
      db_type: formData.value.db_type,
      mysql: {
        host: formData.value.mysql_host,
        dbname: formData.value.mysql_dbname,
        username: formData.value.mysql_username,
        password: formData.value.mysql_password
      }
    }

    await configStore.updateConfig(configData)
    ElMessage.success('配置保存成功')
  } catch (error) {
    ElMessage.error('配置保存失败')
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
</style>
