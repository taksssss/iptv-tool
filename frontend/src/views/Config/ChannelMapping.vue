<template>
  <div class="channel-mapping">
    <el-card>
      <template #header>
        <span>频道别名配置</span>
      </template>
      
      <el-form :model="formData" label-width="120px">
        <el-form-item label="频道别名">
          <el-input
            v-model="formData.channel_mappings_text"
            type="textarea"
            :rows="15"
            placeholder="格式：原频道名 => 新频道名&#10;例如：CCTV-1 => CCTV1"
          />
          <div class="form-tip">
            格式：原频道名 => 新频道名，每行一个映射关系
          </div>
        </el-form-item>
        
        <el-form-item>
          <el-button type="primary" @click="handleSave" :loading="configStore.loading">
            保存配置
          </el-button>
          <el-button @click="$router.back()">返回</el-button>
        </el-form-item>
      </el-form>
    </el-card>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { useConfigStore } from '@/stores/config'

const configStore = useConfigStore()
const formData = ref({
  channel_mappings_text: ''
})

onMounted(async () => {
  await loadConfig()
})

async function loadConfig() {
  try {
    await configStore.fetchConfig()
    if (configStore.config) {
      formData.value.channel_mappings_text = formatChannelMappings(configStore.config.channel_mappings)
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
    const config = {
      ...configStore.config,
      channel_mappings: parseChannelMappings(formData.value.channel_mappings_text)
    }
    await configStore.updateConfig(config)
    ElMessage.success('保存成功')
  } catch (error) {
    ElMessage.error('保存失败')
  }
}
</script>

<style scoped>
.channel-mapping {
  padding: 20px;
}

.form-tip {
  font-size: 12px;
  color: #909399;
  margin-top: 5px;
}
</style>
