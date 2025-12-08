<template>
  <div class="scheduler">
    <el-card>
      <template #header>
        <span>定时任务配置</span>
      </template>
      
      <el-form :model="formData" label-width="120px">
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
  start_time: '01:00',
  end_time: '06:00',
  interval_hours: 0,
  interval_minutes: 0
})

onMounted(async () => {
  await loadConfig()
})

async function loadConfig() {
  try {
    await configStore.fetchConfig()
    if (configStore.config) {
      const config = configStore.config
      formData.value = {
        start_time: config.start_time || '01:00',
        end_time: config.end_time || '06:00',
        interval_hours: Math.floor((config.interval_time || 0) / 3600),
        interval_minutes: Math.floor(((config.interval_time || 0) % 3600) / 60)
      }
    }
  } catch (error) {
    ElMessage.error('加载配置失败')
  }
}

async function handleSave() {
  try {
    const config = {
      ...configStore.config,
      start_time: formData.value.start_time,
      end_time: formData.value.end_time,
      interval_time: formData.value.interval_hours * 3600 + formData.value.interval_minutes * 60
    }
    await configStore.updateConfig(config)
    ElMessage.success('保存成功')
  } catch (error) {
    ElMessage.error('保存失败')
  }
}
</script>

<style scoped>
.scheduler {
  padding: 20px;
}
</style>
