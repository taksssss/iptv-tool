<template>
  <div class="advanced">
    <el-card>
      <template #header>
        <span>高级设置</span>
      </template>
      
      <el-form :model="formData" label-width="150px">
        <el-divider content-position="left">数据设置</el-divider>
        
        <el-form-item label="数据保存天数">
          <el-input-number v-model="formData.days_to_keep" :min="1" :max="30" />
          <span style="margin-left: 10px;">天</span>
        </el-form-item>

        <el-form-item label="生成 XML">
          <el-switch v-model="formData.gen_xml" />
        </el-form-item>

        <el-form-item label="繁体转简体">
          <el-switch v-model="formData.cht_to_chs" />
        </el-form-item>

        <el-divider content-position="left">数据库设置</el-divider>

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
  days_to_keep: 7,
  gen_xml: true,
  cht_to_chs: true,
  db_type: 'sqlite',
  mysql_host: '',
  mysql_dbname: '',
  mysql_username: '',
  mysql_password: ''
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
        days_to_keep: config.days_to_keep || 7,
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

async function handleSave() {
  try {
    const config = {
      ...configStore.config,
      days_to_keep: formData.value.days_to_keep,
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
    await configStore.updateConfig(config)
    ElMessage.success('保存成功')
  } catch (error) {
    ElMessage.error('保存失败')
  }
}
</script>

<style scoped>
.advanced {
  padding: 20px;
}
</style>
