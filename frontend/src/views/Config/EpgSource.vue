<template>
  <div class="epg-source">
    <el-card>
      <template #header>
        <span>EPG 源配置</span>
      </template>
      
      <el-form :model="formData" label-width="120px">
        <el-form-item label="EPG 源地址">
          <el-input
            v-model="formData.xml_urls_text"
            type="textarea"
            :rows="10"
            placeholder="每行一个 EPG 源地址，以 # 开头的行为注释"
          />
          <div class="form-tip">
            每行一个 EPG 源地址，支持 http:// 和 https://，以 # 开头的行会被注释
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
  xml_urls_text: ''
})

onMounted(async () => {
  await loadConfig()
})

async function loadConfig() {
  try {
    await configStore.fetchConfig()
    if (configStore.config) {
      formData.value.xml_urls_text = Array.isArray(configStore.config.xml_urls) 
        ? configStore.config.xml_urls.join('\n') 
        : ''
    }
  } catch (error) {
    ElMessage.error('加载配置失败')
  }
}

async function handleSave() {
  try {
    const config = {
      ...configStore.config,
      xml_urls: formData.value.xml_urls_text.split('\n').filter(line => line.trim())
    }
    await configStore.updateConfig(config)
    ElMessage.success('保存成功')
  } catch (error) {
    ElMessage.error('保存失败')
  }
}
</script>

<style scoped>
.epg-source {
  padding: 20px;
}

.form-tip {
  font-size: 12px;
  color: #909399;
  margin-top: 5px;
}
</style>
